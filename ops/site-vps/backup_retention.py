#!/usr/bin/env python3
"""Keep at most five newest complete Dentistry1402 backup sets in total."""

from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import os
import re
import shutil
import stat
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Callable

try:
    import fcntl
except ImportError:  # pragma: no cover - exercised on Windows development hosts
    fcntl = None
    import msvcrt


KEEP_TOTAL = 5
BACKUPS_ROOT = Path("/var/backups")
PROJECT_BACKUP_ROOT = BACKUPS_ROOT / "dentistry1402"
RUNTIME_ROOT = PROJECT_BACKUP_ROOT / "runtime"
SITE_ROOT = PROJECT_BACKUP_ROOT / "site-data"
BALE_ROOT = PROJECT_BACKUP_ROOT / "bale-database"
MANUAL_ROOT = Path("/srv/dentistry1402/shared/server-only/backups")
LOCK_PATH = PROJECT_BACKUP_ROOT / ".retention.lock"
LEGACY_RUNTIME_ROOT = BACKUPS_ROOT / "dentistry1402-runtime"

RUNTIME_NAME = re.compile(r"^dentistry1402-runtime-(\d{8}T\d{6}Z)\.tar\.gz$")
BALE_NAME = re.compile(r"^dentistry1402-database-(\d{8}T\d{6}Z)\.tar\.gz$")
SITE_NAME = re.compile(r"^dent-site-data-(\d{8}T\d{6}Z)-([0-9a-f]{12})$")
MANUAL_STAMP = re.compile(r"(20\d{6}(?:T\d{6}Z)?)(?:\.json)?$")
MANUAL_PREFIXES = (
    "academic_term7.php.pre-",
    "bot_notifications.php.pre-",
    "incident-cleanup-retire-",
    "term7-booklet-system.pre-",
    "payments-before-",
)


class RetentionError(RuntimeError):
    pass


@dataclass(frozen=True)
class BackupSet:
    path: Path
    category: str
    created_at: dt.datetime
    verify: Callable[[], None]


def _parse_stamp(value: str) -> dt.datetime:
    pattern = "%Y%m%dT%H%M%SZ" if "T" in value else "%Y%m%d"
    try:
        return dt.datetime.strptime(value, pattern).replace(tzinfo=dt.timezone.utc)
    except ValueError as error:
        raise RetentionError("backup timestamp is invalid") from error


def _sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def _verify_sidecar(archive: Path, sidecar: Path | None = None) -> None:
    sidecar = sidecar or Path(f"{archive}.sha256")
    if archive.is_symlink() or sidecar.is_symlink() or not archive.is_file() or not sidecar.is_file():
        raise RetentionError("backup archive or checksum is not a regular file")
    try:
        fields = sidecar.read_text(encoding="ascii").strip().split()
    except (OSError, UnicodeError) as error:
        raise RetentionError("backup checksum is unreadable") from error
    if len(fields) < 2 or not re.fullmatch(r"[0-9a-fA-F]{64}", fields[0]):
        raise RetentionError("backup checksum is malformed")
    if Path(fields[-1].lstrip("*")).name != archive.name:
        raise RetentionError("backup checksum names a different archive")
    if fields[0].lower() != _sha256(archive):
        raise RetentionError("backup checksum verification failed")


def _verify_site_data(path: Path) -> None:
    if path.is_symlink() or not path.is_dir():
        raise RetentionError("site-data backup is not a regular directory")
    expected = {"runtime-pointers.txt", "storage.tar.gz", "SHA256SUMS"}
    entries = list(path.iterdir())
    if {entry.name for entry in entries} != expected or any(entry.is_symlink() or not entry.is_file() for entry in entries):
        raise RetentionError("site-data backup has unexpected contents")
    checksums: dict[str, str] = {}
    try:
        for line in (path / "SHA256SUMS").read_text(encoding="ascii").splitlines():
            match = re.fullmatch(r"([0-9a-fA-F]{64})  ([A-Za-z0-9._-]+)", line)
            if match is None or match.group(2) in checksums:
                raise RetentionError("site-data checksum manifest is malformed")
            checksums[match.group(2)] = match.group(1).lower()
    except (OSError, UnicodeError) as error:
        raise RetentionError("site-data checksum manifest is unreadable") from error
    if set(checksums) != {"runtime-pointers.txt", "storage.tar.gz"}:
        raise RetentionError("site-data checksum manifest has unexpected entries")
    for name, expected_hash in checksums.items():
        if _sha256(path / name) != expected_hash:
            raise RetentionError("site-data checksum verification failed")


def _manual_candidate(path: Path, manual_root: Path) -> BackupSet | None:
    if not any(path.name.startswith(prefix) for prefix in MANUAL_PREFIXES):
        return None
    match = MANUAL_STAMP.search(path.name)
    if match is None:
        return None
    stamp = match.group(1)
    return BackupSet(path, "manual", _parse_stamp(stamp), lambda: _verify_manual_path(path, manual_root))


def _verify_manual_path(path: Path, parent: Path) -> None:
    if path.parent != parent or path.is_symlink():
        raise RetentionError("manual backup is outside its protected namespace")
    try:
        root_info = parent.stat()
        item_info = path.lstat()
    except OSError as error:
        raise RetentionError("manual backup path is unavailable") from error
    if path.is_mount() or item_info.st_dev != root_info.st_dev or not (stat.S_ISREG(item_info.st_mode) or stat.S_ISDIR(item_info.st_mode)):
        raise RetentionError("manual backup is not a regular same-filesystem item")
    if stat.S_ISDIR(item_info.st_mode):
        for current, dirs, files in os.walk(path, followlinks=False):
            for name in dirs + files:
                entry = Path(current) / name
                info = entry.lstat()
                if entry.is_mount() or stat.S_ISLNK(info.st_mode) or info.st_dev != root_info.st_dev:
                    raise RetentionError("manual backup contains a symlink or mount boundary")


def collect_backups(
    site_root: Path = SITE_ROOT,
    runtime_root: Path = RUNTIME_ROOT,
    bale_root: Path = BALE_ROOT,
    manual_root: Path = MANUAL_ROOT,
) -> list[BackupSet]:
    result: list[BackupSet] = []
    if runtime_root.is_dir() and not runtime_root.is_symlink():
        for path in runtime_root.iterdir():
            match = RUNTIME_NAME.fullmatch(path.name)
            if match and path.is_file() and not path.is_symlink():
                result.append(BackupSet(path, "runtime", _parse_stamp(match.group(1)), lambda p=path: _verify_sidecar(p)))
    if bale_root.is_dir() and not bale_root.is_symlink():
        for path in bale_root.iterdir():
            match = BALE_NAME.fullmatch(path.name)
            if match and path.is_file() and not path.is_symlink():
                result.append(BackupSet(path, "bale-database", _parse_stamp(match.group(1)), lambda p=path: _verify_sidecar(p)))
    if site_root.is_dir() and not site_root.is_symlink():
        for path in site_root.iterdir():
            match = SITE_NAME.fullmatch(path.name)
            if match and path.is_dir() and not path.is_symlink():
                result.append(BackupSet(path, "site-data", _parse_stamp(match.group(1)), lambda p=path: _verify_site_data(p)))
    if manual_root.is_dir() and not manual_root.is_symlink():
        for path in manual_root.iterdir():
            candidate = _manual_candidate(path, manual_root)
            if candidate is not None:
                result.append(candidate)
    return result


def _remove_manual(path: Path, parent: Path) -> None:
    _verify_manual_path(path, parent)
    if path.is_dir():
        shutil.rmtree(path)
    else:
        path.unlink()


def prune_backups(
    site_root: Path = SITE_ROOT,
    runtime_root: Path = RUNTIME_ROOT,
    bale_root: Path = BALE_ROOT,
    manual_root: Path = MANUAL_ROOT,
    keep_total: int = KEEP_TOTAL,
) -> tuple[int, int]:
    if keep_total < 1:
        raise ValueError("keep_total must be positive")
    items = collect_backups(site_root, runtime_root, bale_root, manual_root)
    if len(items) <= keep_total:
        return len(items), len(items)

    # Keep the newest verified family snapshot for each available backup type,
    # then fill remaining slots globally by timestamp. Dentistry currently has
    # four independent producers and the manual recovery namespace.
    verified_items: list[BackupSet] = []
    for item in items:
        try:
            item.verify()
        except (OSError, RetentionError):
            continue
        verified_items.append(item)

    if len(verified_items) <= keep_total:
        return len(verified_items), len(verified_items)

    by_category: dict[str, BackupSet] = {}
    for item in verified_items:
        if item.category not in by_category or item.created_at > by_category[item.category].created_at:
            by_category[item.category] = item
    retained = set(by_category.values())
    if len(retained) > keep_total:
        raise RetentionError("retention limit is lower than the number of backup families")
    for item in sorted(verified_items, key=lambda value: (value.created_at, value.path.name), reverse=True):
        if len(retained) >= keep_total:
            break
        retained.add(item)

    removed = [item for item in verified_items if item not in retained]
    # Validate every planned deletion before the first unlink/rmtree. This
    # avoids partial pruning when one old set is corrupt or unsafe to remove.
    for item in removed:
        item.verify()
    for item in sorted(removed, key=lambda value: (value.created_at, value.path.name)):
        item.verify()
        if item.category == "manual":
            _remove_manual(item.path, manual_root)
        elif item.category == "site-data":
            if item.path.parent != site_root or item.path.is_symlink():
                raise RetentionError("site-data backup is outside its protected namespace")
            shutil.rmtree(item.path)
        else:
            expected_parent = runtime_root if item.category == "runtime" else bale_root
            if item.path.parent != expected_parent or item.path.is_symlink():
                raise RetentionError("archive is outside its protected namespace")
            item.path.unlink()
            Path(f"{item.path}.sha256").unlink(missing_ok=True)
            for part in expected_parent.glob(item.path.name + ".part*-of-*"):
                if part.is_file() and not part.is_symlink():
                    part.unlink()
    return len(verified_items), len(verified_items) - len(removed)


def _site_checksum_map(path: Path) -> dict[str, str]:
    _verify_site_data(path)
    result: dict[str, str] = {}
    for line in (path / "SHA256SUMS").read_text(encoding="ascii").splitlines():
        match = re.fullmatch(r"([0-9a-fA-F]{64})  ([A-Za-z0-9._-]+)", line)
        if match is None:
            raise RetentionError("site-data checksum manifest is malformed")
        result[match.group(2)] = match.group(1).lower()
    return result


def _move_archive_pair(source: Path, target: Path) -> bool:
    """Move an archive/checksum pair and resume safely after interruption."""
    source_sidecar = Path(f"{source}.sha256")
    target_sidecar = Path(f"{target}.sha256")
    if source.is_symlink() or target.is_symlink():
        raise RetentionError("legacy archive path is a symlink")

    if source.is_file():
        usable_sidecar = source_sidecar if source_sidecar.is_file() else target_sidecar
        _verify_sidecar(source, usable_sidecar)
        if target.exists():
            if target_sidecar.is_file():
                _verify_sidecar(target, target_sidecar)
            else:
                _verify_sidecar(target, usable_sidecar)
            if _sha256(source) != _sha256(target):
                raise RetentionError("legacy archive conflicts with its destination")
            if not target_sidecar.exists():
                os.replace(usable_sidecar, target_sidecar)
            if source.exists():
                source.unlink()
            if source_sidecar.exists():
                source_sidecar.unlink()
            return True

        if source_sidecar.is_file():
            if target_sidecar.exists():
                _verify_sidecar(source, target_sidecar)
                _verify_sidecar(source, source_sidecar)
                if source_sidecar.read_text(encoding="ascii") != target_sidecar.read_text(encoding="ascii"):
                    raise RetentionError("legacy archive checksum conflicts with its destination")
                source_sidecar.unlink()
            else:
                os.replace(source_sidecar, target_sidecar)
        _verify_sidecar(source, target_sidecar)
        os.replace(source, target)
        return True

    if target.is_file() and source_sidecar.is_file():
        _verify_sidecar(target, source_sidecar)
        if target_sidecar.exists():
            _verify_sidecar(target, target_sidecar)
            if source_sidecar.read_text(encoding="ascii") != target_sidecar.read_text(encoding="ascii"):
                raise RetentionError("orphaned legacy checksum conflicts with its destination")
        else:
            os.replace(source_sidecar, target_sidecar)
        source_sidecar.unlink(missing_ok=True)
        return True
    return False


def _legacy_archive_paths(directory: Path, pattern: re.Pattern[str]) -> list[Path]:
    paths: set[Path] = set()
    for entry in directory.iterdir():
        name = entry.name.removesuffix(".sha256")
        if pattern.fullmatch(name):
            paths.add(entry.with_name(name))
    return sorted(paths)


def migrate_legacy(
    backups_root: Path = BACKUPS_ROOT,
    runtime_root: Path = RUNTIME_ROOT,
    site_root: Path = SITE_ROOT,
    bale_root: Path = BALE_ROOT,
    legacy_runtime_root: Path = LEGACY_RUNTIME_ROOT,
) -> int:
    """Move complete legacy backups into Dentistry's isolated backup root."""
    for directory in (runtime_root, site_root, bale_root):
        directory.mkdir(mode=0o700, parents=True, exist_ok=True)

    moved = 0
    if legacy_runtime_root.is_dir() and not legacy_runtime_root.is_symlink():
        for source in _legacy_archive_paths(legacy_runtime_root, RUNTIME_NAME):
            if not source.is_symlink():
                target = runtime_root / source.name
                moved += int(_move_archive_pair(source, target))

    legacy_bale = legacy_runtime_root / "bale-database"
    if legacy_bale.is_dir() and not legacy_bale.is_symlink():
        for source in _legacy_archive_paths(legacy_bale, BALE_NAME):
            if not source.is_symlink():
                target = bale_root / source.name
                moved += int(_move_archive_pair(source, target))

    if backups_root.is_dir() and not backups_root.is_symlink():
        for source in sorted(backups_root.iterdir()):
            if not SITE_NAME.fullmatch(source.name) or source.is_symlink() or not source.is_dir():
                continue
            _verify_site_data(source)
            target = site_root / source.name
            if target.exists():
                if _site_checksum_map(source) != _site_checksum_map(target):
                    raise RetentionError("legacy site-data backup conflicts with its destination")
                shutil.rmtree(source)
            else:
                os.replace(source, target)
            moved += 1
    return moved


def main() -> int:
    LOCK_PATH.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    with LOCK_PATH.open("a", encoding="ascii") as lock:
        if fcntl is not None:
            fcntl.flock(lock.fileno(), fcntl.LOCK_EX)
        else:  # pragma: no cover - exercised on Windows development hosts
            lock.seek(0)
            msvcrt.locking(lock.fileno(), msvcrt.LK_LOCK, 1)
        parser = argparse.ArgumentParser(description=__doc__)
        parser.add_argument("--migrate-legacy", action="store_true")
        args = parser.parse_args()
        if args.migrate_legacy:
            migrated = migrate_legacy()
        else:
            migrated = 0
        before, after = prune_backups()
        if fcntl is None:  # pragma: no cover - exercised on Windows development hosts
            lock.seek(0)
            msvcrt.locking(lock.fileno(), msvcrt.LK_UNLCK, 1)
    print(f"DENTISTRY_BACKUP_RETENTION_OK before={before} after={after} migrated={migrated} limit={KEEP_TOTAL}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (OSError, RetentionError) as error:
        print(f"DENTISTRY_BACKUP_RETENTION_FAILED reason={error}", file=sys.stderr)
        raise SystemExit(1)
