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
        "payload/site-server-only/.env": "DENT_SIGNING_SECRET=fake-test-secret",
        "payload/site-server-only/sessions/session.json": "excluded session",
        "payload/site-tls/letsencrypt/privkey.pem": "fake TLS private key",
        "payload/system-config/nginx-dentistry1402.conf": "server config",
        "payload/system-config/php-fpm-dentistry1402.conf": "PHP-FPM config",
        "payload/integrated-dent-etc/dent-bot.env": "DENT_BOT_TOKEN=fake-telegram-token",
        "payload/integrated-dent-etc/bale-bot.env": "DENT_BALE_BOT_TOKEN=fake-bale-token",
        "metadata/runtime-pointers.txt": "site_current=/srv/dentistry1402/releases/test\n",
        "metadata/service-active.txt": "nginx\n",
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
    def test_package_contains_recovery_data_and_operations_but_excludes_heavy_files(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            archive = make_snapshot(root)
            created_at = backup._snapshot_time(archive)
            package, package_hash, _size, file_count = backup.build_recovery_package(
                archive, created_at, root / "work",
            )
            self.assertEqual(file_count, 12)
            self.assertEqual(backup.verify_snapshot_checksum(package), package_hash)
            with tarfile.open(package, "r:gz") as bundle:
                names = set(bundle.getnames())
                self.assertEqual(names, {
                    "MANIFEST.txt",
                    "data/site/auth/users.json",
                    "data/site/payments/store.json",
                    "data/site/classops/events.jsonl",
                    "data/bots/bale-bot/state.sqlite3",
                    "operations/site-server-only/.env",
                    "operations/site-tls/letsencrypt/privkey.pem",
                    "operations/integrated-dent-etc/dent-bot.env",
                    "operations/integrated-dent-etc/bale-bot.env",
                    "operations/system-config/nginx-dentistry1402.conf",
                    "operations/system-config/php-fpm-dentistry1402.conf",
                    "operations/metadata/runtime-pointers.txt",
                    "operations/metadata/service-active.txt",
                })
                manifest_file = bundle.extractfile("MANIFEST.txt")
                assert manifest_file is not None
                manifest = manifest_file.read().decode("utf-8")
                self.assertIn("included=website JSON/JSONL/SQLite stores; bot SQLite databases; durable env/secrets", manifest)
                self.assertIn("excluded=PDFs, images, uploads, logs, sessions, tmp files and nested backup archives", manifest)

    def test_age_encryption_uses_recipient_file_and_emits_ciphertext_only(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            plaintext = root / "recovery.tar.gz"
            plaintext.write_bytes(b"DENT_BALE_BOT_TOKEN=private-test-value")
            recipients = root / "recipient.pub"
            recipients.write_text("ssh-ed25519 AAAATEST recipient\n", encoding="utf-8")
            encrypted = root / "recovery.tar.gz.age"

            def fake_age(args, **kwargs):
                self.assertEqual(kwargs["stdin"], backup.subprocess.DEVNULL)
                self.assertEqual(kwargs["stderr"], backup.subprocess.DEVNULL)
                self.assertNotIn("private-test-value", " ".join(args))
                output = Path(args[args.index("-o") + 1])
                output.write_bytes(b"age-encryption.org/v1\n" + hashlib.sha256(plaintext.read_bytes()).digest())
                return backup.subprocess.CompletedProcess(args, 0)

            with patch.object(backup.shutil, "which", return_value="/usr/bin/age"), patch.object(
                backup.subprocess, "run", side_effect=fake_age,
            ) as age_run:
                digest, size = backup.encrypt_recovery_package(plaintext, encrypted, recipients)

            self.assertEqual(age_run.call_args.args[0][1:3], ["-R", str(recipients)])
            self.assertEqual(digest, hashlib.sha256(encrypted.read_bytes()).hexdigest())
            self.assertEqual(size, encrypted.stat().st_size)
            self.assertNotIn(b"private-test-value", encrypted.read_bytes())

    def test_age_encryption_fails_closed_without_single_recipient(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            plaintext = root / "recovery.tar.gz"
            plaintext.write_bytes(b"private")
            recipients = root / "recipient.pub"
            recipients.write_text("# no configured recipient\n", encoding="utf-8")
            with patch.object(backup.shutil, "which", return_value="/usr/bin/age"), patch.object(
                backup.subprocess, "run",
            ) as age_run:
                with self.assertRaises(backup.BackupError):
                    backup.encrypt_recovery_package(plaintext, root / "encrypted.age", recipients)
            age_run.assert_not_called()

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
            def fake_encrypt(package: Path, encrypted_path: Path, _recipient: Path):
                encrypted_path.write_bytes(b"age-encryption.org/v1\n" + hashlib.sha256(package.read_bytes()).digest())
                return backup._sha256(encrypted_path), encrypted_path.stat().st_size

            with patch.object(backup, "encrypt_recovery_package", side_effect=fake_encrypt), patch.object(
                backup, "send_document", side_effect=lambda token, chat_id, path, caption: sent.append((token, chat_id, path, caption)),
            ):
                backup.run(
                    {"DENT_BALE_BOT_TOKEN": "test-token", "DENT_BALE_OWNER_ID": "12345"},
                    backup_root=root,
                    outbox=root / "outbox",
                    runtime_tmp=root / "runtime",
                    recipient_file=root / "recipient.pub",
                )
            self.assertEqual(len(sent), 1)
            self.assertEqual(sent[0][0:2], ("test-token", 12345))
            self.assertIn("SHA-256:", sent[0][3])
            self.assertIn("رمزگذاری‌شده", sent[0][3])
            self.assertTrue(sent[0][2].name.endswith(".tar.gz.age"))
            self.assertNotIn(b"fake-bale-token", sent[0][2].read_bytes())

    def test_retention_keeps_only_seven_encrypted_recovery_packages_and_sidecars(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            for index in range(9):
                package = root / f"dentistry1402-recovery-2026100{index + 1}T000000Z.tar.gz.age"
                package.write_bytes(str(index).encode())
                Path(f"{package}.sha256").write_text("checksum  archive\n", encoding="ascii")
            stale_partial = root / ".dentistry1402-recovery-interrupted.tar.gz.age.partial"
            stale_partial.write_bytes(b"interrupted encrypted output")
            packages = sorted(root.glob("*.tar.gz.age"))
            for index, package in enumerate(packages):
                backup.os.utime(package, (index, index))
            backup.retain_packages(root)
            self.assertEqual(len(list(root.glob("dentistry1402-recovery-*.tar.gz.age"))), 7)
            self.assertEqual(len(list(root.glob("dentistry1402-recovery-*.tar.gz.age.sha256"))), 7)
            self.assertFalse(stale_partial.exists())

    def test_service_installs_hardened_daily_sender_after_runtime_backup(self) -> None:
        service = (OPS / "dentistry1402-bale-database-backup.service").read_text(encoding="utf-8")
        timer = (OPS / "dentistry1402-bale-database-backup.timer").read_text(encoding="utf-8")
        installer = (OPS / "install-site.sh").read_text(encoding="utf-8")
        verifier = (OPS / "verify-site.sh").read_text(encoding="utf-8")
        deployer = (ROOT / "scripts" / "deploy_site_vps.ps1").read_text(encoding="utf-8")
        assert "EnvironmentFile=/etc/integrated-dent/bale-bot.env" in service
        assert "ProtectSystem=strict" in service
        assert "RuntimeDirectory=dentistry1402-bale-recovery" in service
        assert "ReadWritePaths=/run/dentistry1402-bale-recovery /var/backups/dentistry1402-runtime/bale-recovery" in service
        assert "OnCalendar=*-*-* 03:35:00 Asia/Tehran" in timer
        assert "dentistry1402-backup.timer" in installer
        assert "dentistry1402-bale-database-backup.timer" in installer
        assert "dentistry1402-bale-database-backup.timer" in verifier
        assert "opsBundleHash" in deployer
        assert "ops_expected='__OPS_HASH__'" in deployer
        assert "systemctl enable --now dentistry1402-bale-database-backup.timer" in deployer
        assert "dentistry1402-recovery-recipient.pub" in deployer
        assert "age --version" in deployer
        assert "dentistry1402-recovery-recipient.pub" in installer
        assert "command -v age" in verifier


if __name__ == "__main__":
    unittest.main()
