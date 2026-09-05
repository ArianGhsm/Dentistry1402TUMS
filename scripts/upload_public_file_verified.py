#!/usr/bin/env python3
"""Upload one public file through a verified temp and recoverable rename."""

from __future__ import annotations

import argparse
import ftplib
import hashlib
import os
from pathlib import Path, PurePosixPath

from snapshot_remote_storage_verified import SnapshotError, connect, download_bytes


def write_atomic(path: Path, payload: bytes) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temp = path.with_name(path.name + f".tmp.{os.getpid()}")
    with temp.open("xb") as handle:
        if handle.write(payload) != len(payload):
            raise RuntimeError("short local backup write")
        handle.flush()
        os.fsync(handle.fileno())
    os.replace(temp, path)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--config", type=Path, default=Path(".vscode/sftp.json"))
    parser.add_argument("--local", type=Path, required=True)
    parser.add_argument("--relative", required=True)
    parser.add_argument("--backup", type=Path, required=True)
    args = parser.parse_args()
    relative = str(PurePosixPath(args.relative.replace("\\", "/"))).strip("/")
    if relative.startswith("../") or relative == ".." or not relative:
        raise RuntimeError("unsafe public relative path")
    local = args.local.resolve()
    payload = local.read_bytes()
    expected = hashlib.sha256(payload).hexdigest()
    config = args.config.resolve()
    ftp = connect(config)
    import json
    remote_root = str(json.loads(config.read_text(encoding="utf-8-sig"))["remotePath"]).strip("/")
    active = str(PurePosixPath(remote_root) / relative)
    remote_temp = active + f".verified-upload.{os.getpid()}.tmp"
    remote_previous = active + f".previous.{os.getpid()}.{expected[:12]}"
    moved = False
    committed = False
    had_previous = False
    try:
        try:
            previous = download_bytes(ftp, active)
            had_previous = True
            write_atomic(args.backup.resolve(), previous)
        except (ftplib.error_perm, SnapshotError) as exc:
            cause = exc.__cause__ if isinstance(exc, SnapshotError) else exc
            if not isinstance(cause, ftplib.error_perm) or not str(cause).startswith("550"):
                raise
        with local.open("rb") as handle:
            ftp.storbinary("STOR " + remote_temp, handle, blocksize=1024 * 1024)
        staged = download_bytes(ftp, remote_temp)
        if len(staged) != len(payload) or hashlib.sha256(staged).hexdigest() != expected:
            raise RuntimeError("remote staged upload failed size/hash verification")
        if had_previous:
            ftp.rename(active, remote_previous)
            moved = True
        try:
            ftp.rename(remote_temp, active)
        except Exception:
            if moved:
                ftp.rename(remote_previous, active)
                moved = False
            raise
        active_bytes = download_bytes(ftp, active)
        if active_bytes != payload:
            failed = active + f".failed.{hashlib.sha256(active_bytes).hexdigest()[:16]}"
            ftp.rename(active, failed)
            if moved:
                ftp.rename(remote_previous, active)
                moved = False
            raise RuntimeError("active upload verification failed; previous file restored")
        if moved:
            ftp.delete(remote_previous)
            moved = False
        committed = True
        print({"status": "verified-atomic-upload", "relative": relative, "created": not had_previous, "size": len(payload), "sha256": expected})
        return 0
    finally:
        if not committed:
            try:
                ftp.delete(remote_temp)
            except ftplib.all_errors:
                pass
            if moved:
                try:
                    ftp.rename(remote_previous, active)
                except ftplib.all_errors:
                    pass
        try:
            ftp.quit()
        except ftplib.all_errors:
            ftp.close()


if __name__ == "__main__":
    raise SystemExit(main())
