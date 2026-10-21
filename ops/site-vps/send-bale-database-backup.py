#!/usr/bin/env python3
"""Send a verified, database-only Dentistry snapshot to the configured Bale owner."""

from __future__ import annotations

import datetime as dt
import hashlib
import hmac
import json
import os
import re
import shutil
import sqlite3
import sys
import tarfile
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path, PurePosixPath


BACKUP_ROOT = Path("/var/backups/dentistry1402/runtime")
OUTBOX = Path("/var/backups/dentistry1402/bale-database")
ARCHIVE_PATTERN = re.compile(r"^dentistry1402-runtime-(\d{8}T\d{6}Z)\.tar\.gz$")
PART_BYTES = 19_000_000
MAX_AGE = dt.timedelta(hours=26)
BALE_API = "https://tapi.bale.ai"


class BackupError(RuntimeError):
    pass


def _snapshot_time(path: Path) -> dt.datetime:
    match = ARCHIVE_PATTERN.fullmatch(path.name)
    if not match:
        raise BackupError("snapshot filename is invalid")
    return dt.datetime.strptime(match.group(1), "%Y%m%dT%H%M%SZ").replace(tzinfo=dt.timezone.utc)


def latest_snapshot(backup_root: Path, *, now: dt.datetime | None = None) -> tuple[Path, dt.datetime]:
    now = now or dt.datetime.now(dt.timezone.utc)
    candidates: list[tuple[dt.datetime, Path]] = []
    for path in backup_root.glob("dentistry1402-runtime-*.tar.gz"):
        if path.is_file():
            try:
                candidates.append((_snapshot_time(path), path))
            except BackupError:
                continue
    if not candidates:
        raise BackupError("no verified runtime snapshot is available")
    created_at, archive = max(candidates)
    age = now - created_at
    if age < dt.timedelta(minutes=-5) or age > MAX_AGE:
        raise BackupError("latest runtime snapshot is outside the 26-hour sending window")
    return archive, created_at


def _sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def verify_snapshot_checksum(archive: Path) -> str:
    sidecar = Path(f"{archive}.sha256")
    try:
        fields = sidecar.read_text(encoding="ascii").strip().split()
    except (OSError, UnicodeError) as error:
        raise BackupError("snapshot checksum sidecar is missing or unreadable") from error
    if len(fields) < 2 or not re.fullmatch(r"[0-9a-fA-F]{64}", fields[0]):
        raise BackupError("snapshot checksum sidecar is malformed")
    if Path(fields[-1].lstrip("*")).name != archive.name:
        raise BackupError("snapshot checksum sidecar names a different archive")
    actual = _sha256(archive)
    if not hmac.compare_digest(fields[0].lower(), actual):
        raise BackupError("snapshot checksum verification failed")
    return actual


def _relative_regular_member(member: tarfile.TarInfo, prefix: str) -> PurePosixPath | None:
    name = member.name
    if not member.isfile() or "\\" in name:
        return None
    pure = PurePosixPath(name)
    raw_parts = name.split("/")
    if pure.is_absolute() or any(part in {"", ".", ".."} for part in raw_parts):
        return None
    if not name.startswith(prefix):
        return None
    relative = PurePosixPath(name[len(prefix):])
    if relative.is_absolute() or not relative.parts or any(part in {"", ".", ".."} for part in relative.parts):
        return None
    return relative


def _database_category(member: tarfile.TarInfo) -> tuple[str, PurePosixPath] | None:
    site = _relative_regular_member(member, "payload/site-storage/")
    if site is not None:
        if any(part.lower() == "logs" for part in site.parts):
            return None
        if site.suffix.lower() in {".json", ".jsonl", ".sqlite3"}:
            return "site", site
        return None
    bot = _relative_regular_member(member, "payload/integrated-dent-var/")
    if bot is not None and bot.suffix.lower() == ".sqlite3":
        return "bots", bot
    return None


def _validate_data_file(path: Path) -> None:
    suffix = path.suffix.lower()
    if suffix == ".json":
        with path.open("r", encoding="utf-8") as stream:
            json.load(stream)
    elif suffix == ".jsonl":
        with path.open("r", encoding="utf-8") as stream:
            for line_number, line in enumerate(stream, 1):
                if line.strip():
                    try:
                        json.loads(line)
                    except json.JSONDecodeError as error:
                        raise BackupError(f"invalid JSONL data at line {line_number}") from error
    elif suffix == ".sqlite3":
        database_uri = path.resolve().as_uri() + "?mode=ro"
        database = None
        try:
            database = sqlite3.connect(database_uri, uri=True)
            result = database.execute("PRAGMA quick_check;").fetchone()
        except sqlite3.Error as error:
            raise BackupError("a database in the runtime snapshot could not be opened") from error
        finally:
            if database is not None:
                database.close()
        if not result or result[0] != "ok":
            raise BackupError("a database in the runtime snapshot failed quick_check")


def build_database_package(
    archive: Path,
    created_at: dt.datetime,
    outbox: Path,
) -> tuple[Path, str, int, int]:
    verify_snapshot_checksum(archive)
    outbox.mkdir(mode=0o700, parents=True, exist_ok=True)
    os.chmod(outbox, 0o700)
    stamp = created_at.strftime("%Y%m%dT%H%M%SZ")
    package = outbox / f"dentistry1402-database-{stamp}.tar.gz"
    partial = outbox / f".{package.name}.partial"
    staging = Path(tempfile.mkdtemp(prefix=".stage.", dir=outbox))
    entries: list[tuple[str, Path, str, int]] = []
    counts = {"site": 0, "bots": 0}
    try:
        try:
            with tarfile.open(archive, mode="r:gz") as source:
                for member in source:
                    selected = _database_category(member)
                    if selected is None:
                        continue
                    category, relative = selected
                    target = staging / "data" / ("site" if category == "site" else "bots")
                    for part in relative.parts:
                        target = target / part
                    target.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
                    source_file = source.extractfile(member)
                    if source_file is None:
                        raise BackupError("a database file could not be read from the runtime snapshot")
                    copied_size = 0
                    with source_file, target.open("xb") as output:
                        while True:
                            chunk = source_file.read(1024 * 1024)
                            if not chunk:
                                break
                            output.write(chunk)
                            copied_size += len(chunk)
                    if copied_size != member.size:
                        raise BackupError("a database file in the runtime snapshot was truncated")
                    os.chmod(target, 0o600)
                    _validate_data_file(target)
                    arcname = PurePosixPath("data", "site" if category == "site" else "bots", *relative.parts).as_posix()
                    entries.append((category, target, arcname, member.size))
                    counts[category] += 1
        except (OSError, tarfile.TarError) as error:
            raise BackupError("the runtime snapshot archive could not be read") from error

        if counts["site"] == 0 or counts["bots"] == 0:
            raise BackupError("the runtime snapshot is missing website data or bot databases")

        manifest_lines = [
            "Dentistry1402 database-only backup",
            f"source_snapshot={archive.name}",
            f"snapshot_created_utc={created_at.strftime('%Y-%m-%dT%H:%M:%SZ')}",
            "included=website JSON/JSONL/SQLite stores and all bot SQLite databases",
            "excluded=PDFs, images, logs, keys, credentials, TLS and server configuration",
            "",
        ]
        for _category, path, arcname, size in sorted(entries, key=lambda item: item[2]):
            manifest_lines.append(f"{_sha256(path)}  {size}  {arcname}")
        manifest = staging / "MANIFEST.txt"
        manifest.write_text("\n".join(manifest_lines) + "\n", encoding="utf-8")
        os.chmod(manifest, 0o600)

        expected_members = {"MANIFEST.txt", *(entry[2] for entry in entries)}
        with tarfile.open(partial, mode="w:gz", compresslevel=6) as result:
            result.add(manifest, arcname="MANIFEST.txt", recursive=False)
            for _category, path, arcname, _size in sorted(entries, key=lambda item: item[2]):
                result.add(path, arcname=arcname, recursive=False)
        os.chmod(partial, 0o600)
        with tarfile.open(partial, mode="r:gz") as check:
            checked = check.getmembers()
            if {member.name for member in checked} != expected_members:
                raise BackupError("database package manifest verification failed")
            if any(not member.isfile() for member in checked):
                raise BackupError("database package contains a non-file entry")
            expected_hashes = {arcname: (_sha256(path), size) for _category, path, arcname, size in entries}
            for member in checked:
                if member.name == "MANIFEST.txt":
                    continue
                extracted = check.extractfile(member)
                if extracted is None:
                    raise BackupError("database package content could not be reread")
                digest = hashlib.sha256()
                copied_size = 0
                with extracted:
                    for chunk in iter(lambda: extracted.read(1024 * 1024), b""):
                        digest.update(chunk)
                        copied_size += len(chunk)
                if (digest.hexdigest(), copied_size) != expected_hashes[member.name]:
                    raise BackupError("database package content verification failed")
        os.replace(partial, package)
        package_hash = _sha256(package)
        checksum = Path(f"{package}.sha256")
        checksum.write_text(f"{package_hash}  {package.name}\n", encoding="ascii")
        os.chmod(checksum, 0o600)
        return package, package_hash, package.stat().st_size, counts["site"] + counts["bots"]
    finally:
        partial.unlink(missing_ok=True)
        shutil.rmtree(staging, ignore_errors=True)


def split_package(package: Path, outbox: Path, *, part_bytes: int = PART_BYTES) -> list[Path]:
    if part_bytes <= 0:
        raise ValueError("part_bytes must be positive")
    size = package.stat().st_size
    if size <= part_bytes:
        return [package]
    part_count = (size + part_bytes - 1) // part_bytes
    parts: list[Path] = []
    part: Path | None = None
    try:
        with package.open("rb") as source:
            for index in range(1, part_count + 1):
                part = outbox / f"{package.name}.part{index:03d}-of-{part_count:03d}"
                with part.open("wb") as target:
                    remaining = part_bytes
                    while remaining:
                        chunk = source.read(min(1024 * 1024, remaining))
                        if not chunk:
                            break
                        target.write(chunk)
                        remaining -= len(chunk)
                    target.flush()
                    os.fsync(target.fileno())
                os.chmod(part, 0o600)
                parts.append(part)
                part = None
    except OSError as error:
        if part is not None:
            part.unlink(missing_ok=True)
        for created in parts:
            created.unlink(missing_ok=True)
        raise BackupError("database package could not be split for Bale") from error
    return parts


def _multipart_request(token: str, chat_id: int, document: Path, caption: str) -> urllib.request.Request:
    boundary = "----dentistry1402-" + os.urandom(18).hex()
    filename = document.name.replace('"', "_").replace("\r", "_").replace("\n", "_")
    body = bytearray()
    body.extend(f"--{boundary}\r\nContent-Disposition: form-data; name=\"chat_id\"\r\n\r\n{chat_id}\r\n".encode())
    body.extend(f"--{boundary}\r\nContent-Disposition: form-data; name=\"caption\"\r\n\r\n{caption}\r\n".encode("utf-8"))
    body.extend(
        f"--{boundary}\r\nContent-Disposition: form-data; name=\"document\"; filename=\"{filename}\"\r\n"
        "Content-Type: application/gzip\r\n\r\n".encode("ascii")
    )
    body.extend(document.read_bytes())
    body.extend(f"\r\n--{boundary}--\r\n".encode("ascii"))
    request = urllib.request.Request(
        f"{BALE_API}/bot{token}/sendDocument",
        data=bytes(body),
        headers={"Content-Type": f"multipart/form-data; boundary={boundary}"},
        method="POST",
    )
    return request


def send_document(token: str, chat_id: int, document: Path, caption: str) -> None:
    request = _multipart_request(token, chat_id, document, caption)
    for attempt in range(3):
        try:
            with urllib.request.urlopen(request, timeout=90) as response:
                payload = json.loads(response.read(1024 * 1024).decode("utf-8"))
            if payload.get("ok") is not True:
                raise BackupError("Bale rejected the database document")
            return
        except BackupError:
            if attempt == 2:
                raise
        except (OSError, urllib.error.URLError, urllib.error.HTTPError, UnicodeError, json.JSONDecodeError) as error:
            if attempt == 2:
                raise BackupError("Bale document upload failed") from error
        time.sleep(2 ** (attempt + 1))


def send_failure_notice(token: str, chat_id: int, reason: str) -> None:
    fields = {"chat_id": str(chat_id), "text": f"ارسال خودکار بکاپ دیتابیس دنتیستری ناموفق بود.\nعلت: {reason}"}
    body = urllib.parse.urlencode(fields).encode("utf-8")
    request = urllib.request.Request(
        f"{BALE_API}/bot{token}/sendMessage",
        data=body,
        headers={"Content-Type": "application/x-www-form-urlencoded"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(request, timeout=20) as response:
            json.loads(response.read(256 * 1024).decode("utf-8"))
    except Exception:
        return


def run(environ: dict[str, str] | None = None, *, backup_root: Path = BACKUP_ROOT, outbox: Path = OUTBOX) -> None:
    os.umask(0o077)
    environ = os.environ if environ is None else environ
    token = str(environ.get("DENT_BALE_BOT_TOKEN") or "").strip()
    owner_raw = str(environ.get("DENT_BALE_OWNER_ID") or "").strip()
    if not token or not owner_raw.isdigit() or int(owner_raw) <= 0:
        raise BackupError("Bale bot token or owner chat ID is not configured")
    owner_id = int(owner_raw)
    try:
        archive, created_at = latest_snapshot(backup_root)
        package, package_hash, package_size, file_count = build_database_package(archive, created_at, outbox)
        parts = split_package(package, outbox)
        for index, part in enumerate(parts, 1):
            caption = (
                "بکاپ دیتابیس دنتیستری آماده است.\n"
                f"زمان snapshot: {created_at.strftime('%Y-%m-%d %H:%M UTC')}\n"
                f"حجم بسته: {package_size:,} بایت · فایل‌های داده: {file_count}\n"
                f"SHA-256: {package_hash}"
            )
            if len(parts) > 1:
                caption += (
                    f"\nبخش {index}/{len(parts)} · برای بازیابی همهٔ بخش‌ها را به ترتیب به هم بچسبان: "
                    "cat archive.part* > archive.tar.gz"
                )
            send_document(token, owner_id, part, caption)
    except Exception as error:
        send_failure_notice(token, owner_id, "فرایند بکاپ یا ارسال کامل نشد؛ گزارش سرویس روی سرور ثبت شد.")
        if isinstance(error, BackupError):
            raise
        raise BackupError("unexpected database backup sender failure") from error
    finally:
        if "parts" in locals():
            for part in parts:
                if part != package:
                    part.unlink(missing_ok=True)
    print(
        f"BALE_DATABASE_BACKUP_OK snapshot={archive.name} bytes={package_size} "
        f"files={file_count} parts={len(parts)} sha256={package_hash}"
    )


def main() -> int:
    try:
        run()
    except BackupError as error:
        print(f"BALE_DATABASE_BACKUP_FAILED reason={error}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
