#!/usr/bin/env python3
"""Back up verified bot generations locally, then optionally prune that exact set."""

from __future__ import annotations

import argparse
import ftplib
import hashlib
import json
import os
import gzip
import re
from pathlib import Path

from snapshot_remote_storage_verified import connect, download_bytes


REMOTE_DIRECTORY = "storage/integrations/.generations"
ALLOWED_PREFIXES = (
    "bot_links.json.g",
    "bot_service_nonces.json.g",
    "bot_payment_deliveries.json.g",
    "bot_notification_deliveries.json.g",
)
GENERATION_RE = re.compile(
    r"^(?:bot_links|bot_service_nonces|bot_payment_deliveries|bot_notification_deliveries)"
    r"\.json\.g\d+\.([a-f0-9]{64})\.json(?:\.gz)?$"
)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--config", type=Path, default=Path(".vscode/sftp.json"))
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--commit", action="store_true")
    args = parser.parse_args()
    output = args.output.resolve()
    if output.exists():
        raise RuntimeError("output path already exists")
    output.mkdir(parents=True)
    ftp = connect(args.config.resolve())
    records: list[dict[str, object]] = []
    try:
        names = sorted(
            name
            for name, facts in ftp.mlsd(REMOTE_DIRECTORY, facts=["type", "size", "modify"])
            if facts.get("type") == "file" and name.startswith(ALLOWED_PREFIXES)
        )
        for name in names:
            payload = download_bytes(ftp, REMOTE_DIRECTORY + "/" + name)
            match = GENERATION_RE.fullmatch(name)
            if match is None:
                raise RuntimeError("unexpected generation filename")
            raw = gzip.decompress(payload) if name.endswith(".gz") else payload
            sha256 = hashlib.sha256(raw).hexdigest()
            claimed = match.group(1)
            if sha256 != claimed:
                raise RuntimeError("generation filename checksum mismatch")
            json.loads(raw.decode("utf-8-sig"))
            target = output / name
            with target.open("xb") as handle:
                if handle.write(payload) != len(payload):
                    raise RuntimeError("short local generation write")
                handle.flush()
                os.fsync(handle.fileno())
            records.append({"name": name, "size": len(payload), "storedSha256": hashlib.sha256(payload).hexdigest(), "contentSha256": sha256})
        manifest = {
            "format": "dent-remote-generations-backup-v1",
            "remoteDirectory": REMOTE_DIRECTORY,
            "fileCount": len(records),
            "totalBytes": sum(int(record["size"]) for record in records),
            "files": records,
        }
        manifest_raw = (json.dumps(manifest, indent=2) + "\n").encode("utf-8")
        with (output / "manifest.json").open("xb") as handle:
            if handle.write(manifest_raw) != len(manifest_raw):
                raise RuntimeError("short manifest write")
            handle.flush()
            os.fsync(handle.fileno())
        for record in records:
            target = output / str(record["name"])
            if target.stat().st_size != record["size"] or hashlib.sha256(target.read_bytes()).hexdigest() != record["storedSha256"]:
                raise RuntimeError("local generation verification failed")
        if args.commit:
            # The deletion set is the exact verified manifest, constrained to
            # one fixed directory and two filename prefixes.
            for record in records:
                ftp.delete(REMOTE_DIRECTORY + "/" + str(record["name"]))
            remaining = [
                name
                for name, facts in ftp.mlsd(REMOTE_DIRECTORY, facts=["type"])
                if facts.get("type") == "file" and name.startswith(ALLOWED_PREFIXES)
            ]
            if remaining:
                raise RuntimeError("remote generation cleanup was incomplete")
        print(
            json.dumps(
                {
                    "status": "backed-up-and-removed" if args.commit else "dry-run-backed-up",
                    "fileCount": len(records),
                    "bytesFreed": manifest["totalBytes"] if args.commit else 0,
                    "localBackup": str(output),
                    "recoverable": True,
                },
                separators=(",", ":"),
            )
        )
        return 0
    finally:
        try:
            ftp.quit()
        except ftplib.all_errors:
            ftp.close()


if __name__ == "__main__":
    raise SystemExit(main())
