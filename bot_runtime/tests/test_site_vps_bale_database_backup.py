from __future__ import annotations

import hashlib
import importlib.util
import sqlite3
import tarfile
import tempfile
import unittest
from datetime import datetime, timedelta, timezone
from pathlib import Path
from unittest.mock import patch


ROOT = Path(__file__).resolve().parents[2]
OPS = ROOT / "ops" / "site-vps"
SPEC = importlib.util.spec_from_file_location(
    "dentistry_bale_database_backup",
    OPS / "send-bale-database-backup.py",
)
assert SPEC is not None and SPEC.loader is not None
backup = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(backup)


def make_snapshot(backup_root: Path, *, created_at: datetime | None = None) -> Path:
    created_at = created_at or datetime.now(timezone.utc)
    stamp = created_at.strftime("%Y%m%dT%H%M%SZ")
    archive = backup_root / f"dentistry1402-runtime-{stamp}.tar.gz"
    source = backup_root / "source"
    paths = {
        "payload/site-storage/auth/users.json": '{"users": []}',
        "payload/site-storage/payments/store.json": '{"orders": []}',
        "payload/site-storage/classops/events.jsonl": '{"id":"evt_1"}\n',
        "payload/site-storage/logs/errors.jsonl": '{"message":"excluded log"}\n',
        "payload/site-storage/uploads/large.pdf": "%PDF-1.4 excluded" ,
        "payload/site-storage/secrets/signing.key": "excluded key",
        "payload/site-tls/privkey.pem": "excluded TLS key",
        "payload/system-config/nginx.conf": "excluded server config",
        "payload/integrated-dent-etc/bale-bot.env": "DENT_BALE_BOT_TOKEN=fake-test-token",
    }
    for relative, contents in paths.items():
        target = source / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(contents, encoding="utf-8")
    db = source / "payload/integrated-dent-var/bale-bot/state.sqlite3"
    db.parent.mkdir(parents=True, exist_ok=True)
    connection = sqlite3.connect(db)
    try:
        connection.execute("CREATE TABLE sample (id INTEGER PRIMARY KEY)")
        connection.execute("INSERT INTO sample DEFAULT VALUES")
        connection.commit()
    finally:
        connection.close()
    with tarfile.open(archive, "w:gz") as bundle:
        for path in source.rglob("*"):
            if path.is_file():
                bundle.add(path, arcname=path.relative_to(source).as_posix())
    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    Path(f"{archive}.sha256").write_text(f"{digest}  {archive}\n", encoding="ascii")
    return archive


class BaleDatabaseBackupTests(unittest.TestCase):
    def test_package_contains_site_stores_and_bot_databases_only(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            archive = make_snapshot(root)
            created_at = backup._snapshot_time(archive)
            package, package_hash, _size, file_count = backup.build_database_package(
                archive, created_at, root / "outbox",
            )
            self.assertEqual(file_count, 4)
            self.assertEqual(backup.verify_snapshot_checksum(package), package_hash)
            with tarfile.open(package, "r:gz") as bundle:
                names = set(bundle.getnames())
                self.assertEqual(names, {
                    "MANIFEST.txt",
                    "data/site/auth/users.json",
                    "data/site/payments/store.json",
                    "data/site/classops/events.jsonl",
                    "data/bots/bale-bot/state.sqlite3",
                })
                manifest_file = bundle.extractfile("MANIFEST.txt")
                assert manifest_file is not None
                manifest = manifest_file.read().decode("utf-8")
                self.assertIn("excluded=PDFs, images, logs, keys, credentials, TLS and server configuration", manifest)

    def test_latest_snapshot_rejects_stale_backup(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            old = datetime.now(timezone.utc) - timedelta(hours=27)
            archive = make_snapshot(root, created_at=old)
            with self.assertRaises(backup.BackupError):
                backup.latest_snapshot(root, now=datetime.now(timezone.utc))
            self.assertTrue(Path(f"{archive}.sha256").is_file())

    def test_split_package_stays_below_bale_upload_part_limit_and_reassembles(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            package = root / "package.tar.gz"
            original = bytes(range(251)) * 20_000
            package.write_bytes(original)
            parts = backup.split_package(package, root, part_bytes=1_000_000)
            self.assertEqual(len(parts), 6)
            self.assertTrue(all(path.stat().st_size <= 1_000_000 for path in parts))
            self.assertEqual(b"".join(path.read_bytes() for path in parts), original)

    def test_snapshot_checksum_mismatch_fails_closed(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            archive = make_snapshot(root)
            archive.write_bytes(archive.read_bytes() + b"tampered")
            with self.assertRaises(backup.BackupError):
                backup.verify_snapshot_checksum(archive)

    def test_run_sends_the_verified_package_only_to_configured_owner(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            make_snapshot(root)
            sent = []
            with patch.object(backup, "send_document", side_effect=lambda token, chat_id, path, caption: sent.append((token, chat_id, path, caption))):
                backup.run(
                    {"DENT_BALE_BOT_TOKEN": "test-token", "DENT_BALE_OWNER_ID": "12345"},
                    backup_root=root,
                    outbox=root / "outbox",
                )
            self.assertEqual(len(sent), 1)
            self.assertEqual(sent[0][0:2], ("test-token", 12345))
            self.assertIn("SHA-256:", sent[0][3])

    def test_sender_uses_project_scoped_paths_and_shared_retention(self) -> None:
        service = (OPS / "dentistry1402-bale-database-backup.service").read_text(encoding="utf-8")
        timer = (OPS / "dentistry1402-bale-database-backup.timer").read_text(encoding="utf-8")
        installer = (OPS / "install-site.sh").read_text(encoding="utf-8")
        verifier = (OPS / "verify-site.sh").read_text(encoding="utf-8")
        deployer = (ROOT / "scripts" / "deploy_site_vps.ps1").read_text(encoding="utf-8")
        source = (OPS / "send-bale-database-backup.py").read_text(encoding="utf-8")
        assert "EnvironmentFile=/etc/integrated-dent/bale-bot.env" in service
        assert "ProtectSystem=strict" in service
        assert "ReadOnlyPaths=/var/backups/dentistry1402/runtime /etc/integrated-dent" in service
        assert "ReadWritePaths=/var/backups/dentistry1402/bale-database" in service
        assert "ExecStopPost=/usr/bin/systemctl start dentistry1402-backup-retention.service" in service
        assert 'BACKUP_ROOT = Path("/var/backups/dentistry1402/runtime")' in source
        assert 'OUTBOX = Path("/var/backups/dentistry1402/bale-database")' in source
        assert "KEEP_PACKAGES" not in source
        assert "OnCalendar=*-*-* 03:35:00 Asia/Tehran" in timer
        assert "dentistry1402-backup.timer" in installer
        assert "dentistry1402-backup-retention.timer" in installer
        assert "dentistry1402-bale-database-backup.timer" in installer
        assert "dentistry1402-bale-database-backup.timer" in verifier
        assert "dentistry1402-backup-retention.timer" in verifier
        assert "opsBundleHash" in deployer
        assert "ops_expected='__OPS_HASH__'" in deployer
        assert "systemctl enable --now dentistry1402-backup.timer dentistry1402-backup-retention.timer" in deployer


if __name__ == "__main__":
    unittest.main()
