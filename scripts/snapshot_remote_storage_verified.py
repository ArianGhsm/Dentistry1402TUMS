#!/usr/bin/env python3
"""Create a read-only, verified FTP snapshot of production storage.

The snapshot is assembled in a sibling staging directory and promoted only
after every downloaded JSON document parses, critical schemas are present,
all recorded hashes re-verify, and the bot identity store remains byte-stable
across the capture. Credentials are read from the ignored VS Code deployment
configuration and are never printed or copied into the manifest.
"""

from __future__ import annotations

import argparse
import ftplib
import hashlib
import json
import os
import shutil
import sys
import time
from datetime import datetime, timezone
from pathlib import Path, PurePosixPath
from typing import Any, Iterator


# Ephemeral scratch, lock-adjacent forensic copies and rotating previous
# generations are not canonical live state. Capturing them while writers prune
# old generations can make a snapshot internally racy even though every active
# atomic store is sound.
SKIPPED_DIRECTORY_NAMES = {"tmp", "sessions", "backups", "cache", ".generations", ".corrupt"}
CRITICAL_SCHEMAS: dict[str, tuple[str, ...]] = {
    "integrations/bot_links.json": ("schemaVersion", "links", "audit"),
    "integrations/bot_payment_deliveries.json": ("schemaVersion", "deliveries", "poll"),
    "integrations/bot_notification_deliveries.json": ("schemaVersion", "deliveries", "dispatchSince"),
    "auth/users.json": (),
    "payments/store.json": (),
}


class SnapshotError(RuntimeError):
    pass


def sha256_bytes(payload: bytes) -> str:
    return hashlib.sha256(payload).hexdigest()


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        while chunk := handle.read(1024 * 1024):
            digest.update(chunk)
    return digest.hexdigest()


def load_credentials(path: Path) -> tuple[str, int, str, str]:
    try:
        raw = json.loads(path.read_text(encoding="utf-8-sig"))
    except (OSError, json.JSONDecodeError) as exc:
        raise SnapshotError(f"Unable to read FTP configuration: {path}") from exc
    required = ("host", "username", "password")
    if not all(isinstance(raw.get(key), str) and raw[key] for key in required):
        raise SnapshotError("FTP configuration is missing a required credential field")
    return raw["host"], int(raw.get("port") or 21), raw["username"], raw["password"]


def connect(config_path: Path) -> ftplib.FTP:
    host, port, username, password = load_credentials(config_path)
    ftp = ftplib.FTP()
    ftp.connect(host=host, port=port, timeout=60)
    ftp.login(user=username, passwd=password)
    ftp.set_pasv(True)
    return ftp


def iter_files(ftp: ftplib.FTP, root: str) -> Iterator[str]:
    def walk(directory: str) -> Iterator[str]:
        try:
            entries = list(ftp.mlsd(directory, facts=["type", "size", "modify"]))
        except ftplib.all_errors as exc:
            raise SnapshotError(f"Unable to list remote storage directory: {directory}") from exc
        for name, facts in entries:
            if name in {".", ".."}:
                continue
            child = str(PurePosixPath(directory) / name)
            entry_type = (facts.get("type") or "").lower()
            if entry_type == "dir":
                if name.lower() not in SKIPPED_DIRECTORY_NAMES:
                    yield from walk(child)
            elif entry_type == "file":
                yield child

    yield from walk(root.strip("/"))


def download_bytes(ftp: ftplib.FTP, remote_path: str) -> bytes:
    chunks: list[bytes] = []
    try:
        ftp.retrbinary(f"RETR {remote_path}", chunks.append, blocksize=1024 * 1024)
    except ftplib.all_errors as exc:
        raise SnapshotError(f"Unable to download remote storage file: {remote_path}") from exc
    return b"".join(chunks)


def validate_json(relative_path: str, payload: bytes) -> dict[str, Any] | None:
    if not relative_path.lower().endswith(".json"):
        return None
    try:
        decoded = json.loads(payload.decode("utf-8-sig"))
    except (UnicodeDecodeError, json.JSONDecodeError) as exc:
        raise SnapshotError(f"JSON validation failed: {relative_path}") from exc
    if relative_path in CRITICAL_SCHEMAS:
        if not isinstance(decoded, dict):
            raise SnapshotError(f"Critical JSON must be an object: {relative_path}")
        missing = [key for key in CRITICAL_SCHEMAS[relative_path] if key not in decoded]
        if missing:
            raise SnapshotError(f"Critical JSON schema mismatch: {relative_path}")
    return decoded if isinstance(decoded, dict) else None


def capture_once(
    config_path: Path,
    remote_root: str,
    staging_path: Path,
    critical_only: bool = False,
    allow_legacy_combined: bool = False,
) -> dict[str, Any]:
    required_schemas = dict(CRITICAL_SCHEMAS)
    if allow_legacy_combined:
        required_schemas.pop("integrations/bot_payment_deliveries.json", None)
        required_schemas.pop("integrations/bot_notification_deliveries.json", None)
    ftp = connect(config_path)
    records: list[dict[str, Any]] = []
    try:
        files = (
            [str(PurePosixPath(remote_root.strip("/")) / relative) for relative in required_schemas]
            if critical_only
            else sorted(iter_files(ftp, remote_root))
        )
        for remote_path in files:
            relative = str(PurePosixPath(remote_path).relative_to(remote_root.strip("/")))
            target = staging_path.joinpath(*PurePosixPath(relative).parts)
            target.parent.mkdir(parents=True, exist_ok=True)
            payload = download_bytes(ftp, remote_path)
            parsed = validate_json(relative, payload)
            with target.open("xb") as handle:
                written = handle.write(payload)
                if written != len(payload):
                    raise SnapshotError(f"Short local snapshot write: {relative}")
                handle.flush()
                os.fsync(handle.fileno())
            records.append(
                {
                    "path": relative,
                    "size": len(payload),
                    "sha256": sha256_bytes(payload),
                    "json": relative.lower().endswith(".json"),
                    "schemaVersion": parsed.get("schemaVersion") if parsed else None,
                }
            )
        stable_critical: dict[str, bytes] = {}
        for critical_relative in required_schemas:
            critical_remote = str(PurePosixPath(remote_root.strip("/")) / critical_relative)
            prior: bytes | None = None
            for _ in range(8):
                candidate = download_bytes(ftp, critical_remote)
                validate_json(critical_relative, candidate)
                if prior is not None and sha256_bytes(prior) == sha256_bytes(candidate):
                    stable_critical[critical_relative] = candidate
                    break
                prior = candidate
                time.sleep(0.25)
            if critical_relative not in stable_critical:
                raise SnapshotError(f"{critical_relative} did not produce two consecutive stable valid reads")
    finally:
        try:
            ftp.quit()
        except ftplib.all_errors:
            ftp.close()

    for critical_relative, stable_payload in stable_critical.items():
        captured = staging_path.joinpath(*PurePosixPath(critical_relative).parts)
        if not captured.is_file():
            raise SnapshotError(f"captured critical store is missing: {critical_relative}")
        with captured.open("wb") as handle:
            if handle.write(stable_payload) != len(stable_payload):
                raise SnapshotError(f"Short local snapshot write: {critical_relative}")
            handle.flush()
            os.fsync(handle.fileno())
        stable_hash = sha256_bytes(stable_payload)
        for record in records:
            if record["path"] == critical_relative:
                record["size"] = len(stable_payload)
                record["sha256"] = stable_hash
                break
        if sha256_file(captured) != stable_hash:
            raise SnapshotError(f"captured store does not match stable remote generation: {critical_relative}")

    for record in records:
        path = staging_path.joinpath(*PurePosixPath(record["path"]).parts)
        if path.stat().st_size != record["size"] or sha256_file(path) != record["sha256"]:
            raise SnapshotError(f"Snapshot re-verification failed: {record['path']}")

    return {
        "format": "dent-storage-snapshot-v1",
        "capturedAt": datetime.now(timezone.utc).isoformat(),
        "remoteRoot": "/" + remote_root.strip("/"),
        "consistency": {
            "mode": "critical-atomic-double-read" if critical_only else "atomic-double-read",
            "criticalStoresStable": sorted(stable_critical),
            "eligibleForLatest": not allow_legacy_combined,
            "note": (
                "Transition evidence only: legacy combined bot store is accepted and must not become latest."
                if allow_legacy_combined
                else "Every JSON parsed and every critical atomic store matched across consecutive remote reads."
            ),
        },
        "fileCount": len(records),
        "totalBytes": sum(int(record["size"]) for record in records),
        "files": records,
    }


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--config", type=Path, default=Path(".vscode/sftp.json"))
    parser.add_argument("--remote-root", default="storage")
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--attempts", type=int, default=3)
    parser.add_argument("--critical-only", action="store_true")
    parser.add_argument("--allow-legacy-combined", action="store_true")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    output = args.output.resolve()
    if output.exists():
        raise SnapshotError(f"Snapshot output already exists: {output}")
    output.parent.mkdir(parents=True, exist_ok=True)

    last_error: Exception | None = None
    for attempt in range(1, max(1, args.attempts) + 1):
        staging = output.with_name(output.name + f".partial-{os.getpid()}-{attempt}")
        if staging.exists():
            shutil.rmtree(staging)
        staging.mkdir(parents=True)
        try:
            manifest = capture_once(
                args.config.resolve(),
                args.remote_root,
                staging,
                args.critical_only,
                args.allow_legacy_combined,
            )
            manifest_path = staging / "snapshot-manifest.json"
            encoded = (json.dumps(manifest, ensure_ascii=False, indent=2) + "\n").encode("utf-8")
            with manifest_path.open("xb") as handle:
                if handle.write(encoded) != len(encoded):
                    raise SnapshotError("Short manifest write")
                handle.flush()
                os.fsync(handle.fileno())
            os.replace(staging, output)
            print(
                json.dumps(
                    {
                        "status": "verified-snapshot",
                        "output": str(output),
                        "fileCount": manifest["fileCount"],
                        "totalBytes": manifest["totalBytes"],
                    },
                    separators=(",", ":"),
                )
            )
            return 0
        except Exception as exc:  # noqa: BLE001 - CLI boundary records no credentials
            last_error = exc
            shutil.rmtree(staging, ignore_errors=True)
            if attempt < max(1, args.attempts):
                time.sleep(attempt)
    raise SnapshotError(str(last_error or "Snapshot failed"))


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except SnapshotError as exc:
        print(f"snapshot failed: {exc}", file=sys.stderr)
        raise SystemExit(1)
