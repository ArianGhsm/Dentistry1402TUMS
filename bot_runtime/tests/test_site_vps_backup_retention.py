from __future__ import annotations

import hashlib
import importlib.util
import sys
import tempfile
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
OPS = ROOT / "ops" / "site-vps"
SPEC = importlib.util.spec_from_file_location("dentistry_backup_retention", OPS / "backup_retention.py")
assert SPEC is not None and SPEC.loader is not None
retention = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = retention
SPEC.loader.exec_module(retention)


def stamp(day: int, *, minute: int = 0) -> str:
    return f"202610{day:02d}T{minute:02d}0000Z"


def write_sidecar(path: Path) -> None:
    digest = hashlib.sha256(path.read_bytes()).hexdigest()
    Path(f"{path}.sha256").write_text(f"{digest}  {path.name}\n", encoding="ascii")


def make_runtime(root: Path, date: str) -> Path:
    path = root / f"dentistry1402-runtime-{date}.tar.gz"
    path.write_bytes(f"runtime {date}".encode())
    write_sidecar(path)
    return path


def make_bale(root: Path, date: str) -> Path:
    path = root / f"dentistry1402-database-{date}.tar.gz"
    path.write_bytes(f"bale {date}".encode())
    write_sidecar(path)
    return path


def make_site(root: Path, date: str) -> Path:
    path = root / f"dent-site-data-{date}-abcdef123456"
    path.mkdir()
    (path / "runtime-pointers.txt").write_text("previous=old\ntarget=new\n", encoding="utf-8")
    (path / "storage.tar.gz").write_bytes(f"site {date}".encode())
    lines = []
    for name in ("runtime-pointers.txt", "storage.tar.gz"):
        digest = hashlib.sha256((path / name).read_bytes()).hexdigest()
        lines.append(f"{digest}  {name}")
    (path / "SHA256SUMS").write_text("\n".join(lines) + "\n", encoding="ascii")
    return path


class DentistryBackupRetentionTests(unittest.TestCase):
    def test_prunes_to_five_total_and_keeps_newest_of_each_family(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            runtime_root = root / "runtime"
            site_root = root / "site-data"
            bale_root = root / "bale-database"
            manual_root = root / "manual"
            for path in (runtime_root, site_root, bale_root, manual_root):
                path.mkdir()

            runtime_dates = [stamp(day) for day in (1, 2, 3, 4)]
            site_dates = [stamp(day) for day in (1, 5, 6)]
            bale_dates = [stamp(day) for day in (2, 7)]
            manual_dates = [stamp(day) for day in (3, 8)]
            runtime_paths = [make_runtime(runtime_root, date) for date in runtime_dates]
            site_paths = [make_site(site_root, date) for date in site_dates]
            bale_paths = [make_bale(bale_root, date) for date in bale_dates]
            manual_paths = []
            for date in manual_dates:
                manual = manual_root / f"academic_term7.php.pre-audit-{date[:8]}"
                manual.write_text(f"dated recovery artifact {date}", encoding="utf-8")
                manual_paths.append(manual)
            unknown = manual_root / "keep-me.txt"
            unknown.write_text("unclassified server-only file", encoding="utf-8")
            stale_workspace = manual_root / "stale-workspaces-20261001T000000Z"
            stale_workspace.mkdir()
            (stale_workspace / "work.txt").write_text("not a backup artifact", encoding="utf-8")

            before, after = retention.prune_backups(
                site_root=site_root,
                runtime_root=runtime_root,
                bale_root=bale_root,
                manual_root=manual_root,
            )

            self.assertEqual((before, after), (11, 5))
            retained = [*runtime_root.glob("*.tar.gz"), *site_root.iterdir(), *bale_root.glob("*.tar.gz"), *manual_root.glob("academic*")]
            self.assertEqual(len(retained), 5)
            self.assertIn(runtime_paths[-1], retained)
            self.assertIn(site_paths[-1], retained)
            self.assertIn(bale_paths[-1], retained)
            self.assertIn(manual_paths[-1], retained)
            self.assertIn(site_paths[-2], retained)
            self.assertTrue(unknown.exists())
            self.assertTrue((stale_workspace / "work.txt").exists())
            self.assertEqual(len(list(runtime_root.glob("*.sha256"))), 1)
            self.assertEqual(len(list(bale_root.glob("*.sha256"))), 1)

    def test_corrupt_archive_is_not_deleted_and_does_not_count_as_verified(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            runtime_root = root / "runtime"
            runtime_root.mkdir()
            paths = [make_runtime(runtime_root, stamp(day)) for day in range(1, 8)]
            paths[0].write_bytes(b"changed after checksum")

            before, after = retention.prune_backups(
                site_root=root / "site-data",
                runtime_root=runtime_root,
                bale_root=root / "bale-database",
                manual_root=root / "manual",
            )

            self.assertEqual((before, after), (6, 5))
            self.assertTrue(paths[0].exists())
            self.assertEqual(len(list(runtime_root.glob("*.tar.gz"))), 6)

    def test_migration_moves_verified_legacy_sets_without_copying(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            legacy_backups = root / "legacy-var-backups"
            legacy_runtime = legacy_backups / "dentistry1402-runtime"
            legacy_bale = legacy_runtime / "bale-database"
            runtime_root = root / "project" / "runtime"
            site_root = root / "project" / "site-data"
            bale_root = root / "project" / "bale-database"
            for path in (legacy_bale,):
                path.mkdir(parents=True)
            runtime = make_runtime(legacy_runtime, stamp(1))
            bale = make_bale(legacy_bale, stamp(2))
            site = make_site(legacy_backups, stamp(3))

            migrated = retention.migrate_legacy(
                backups_root=legacy_backups,
                runtime_root=runtime_root,
                site_root=site_root,
                bale_root=bale_root,
                legacy_runtime_root=legacy_runtime,
            )

            self.assertEqual(migrated, 3)
            self.assertFalse(runtime.exists())
            self.assertFalse(Path(f"{runtime}.sha256").exists())
            self.assertFalse(bale.exists())
            self.assertFalse(site.exists())
            self.assertTrue((runtime_root / runtime.name).is_file())
            self.assertTrue((bale_root / bale.name).is_file())
            self.assertTrue((site_root / site.name).is_dir())
            retention._verify_sidecar(runtime_root / runtime.name)
            retention._verify_sidecar(bale_root / bale.name)
            retention._verify_site_data(site_root / site.name)

    def test_migration_resumes_if_archive_or_sidecar_move_was_interrupted(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            legacy_root = root / "legacy" / "dentistry1402-runtime"
            legacy_bale = legacy_root / "bale-database"
            legacy_bale.mkdir(parents=True)
            runtime_root = root / "project" / "runtime"
            bale_root = root / "project" / "bale-database"
            site_root = root / "project" / "site-data"

            first = make_runtime(legacy_root, stamp(1))
            first_target = runtime_root / first.name
            runtime_root.mkdir(parents=True)
            Path(f"{first}.sha256").replace(Path(f"{first_target}.sha256"))

            second = make_bale(legacy_bale, stamp(2))
            second_target = bale_root / second.name
            bale_root.mkdir(parents=True)
            second.replace(second_target)

            retention.migrate_legacy(
                backups_root=root / "legacy",
                runtime_root=runtime_root,
                site_root=site_root,
                bale_root=bale_root,
                legacy_runtime_root=legacy_root,
            )

            self.assertFalse(first.exists())
            self.assertFalse(Path(f"{first}.sha256").exists())
            self.assertFalse(Path(f"{second}.sha256").exists())
            retention._verify_sidecar(first_target)
            retention._verify_sidecar(second_target)

    def test_service_scope_and_installation_cover_all_producers(self) -> None:
        retention_service = (OPS / "dentistry1402-backup-retention.service").read_text(encoding="utf-8")
        timer = (OPS / "dentistry1402-backup-retention.timer").read_text(encoding="utf-8")
        runtime_service = (OPS / "dentistry1402-backup.service").read_text(encoding="utf-8")
        bale_service = (OPS / "dentistry1402-bale-database-backup.service").read_text(encoding="utf-8")
        installer = (OPS / "install-site.sh").read_text(encoding="utf-8")
        deployer = (ROOT / "scripts" / "deploy_site_vps.ps1").read_text(encoding="utf-8")
        self.assertIn("ReadWritePaths=/var/backups/dentistry1402 /srv/dentistry1402/shared/server-only/backups", retention_service)
        self.assertNotIn("ReadWritePaths=/var/backups\n", retention_service)
        self.assertIn("OnCalendar=hourly", timer)
        self.assertIn("ExecStopPost=/usr/bin/systemctl start dentistry1402-backup-retention.service", runtime_service)
        self.assertIn("ExecStopPost=/usr/bin/systemctl start dentistry1402-backup-retention.service", bale_service)
        self.assertIn("backup_retention.py", installer)
        self.assertIn("dentistry1402-backup-retention.timer", installer)
        self.assertIn("count==12", deployer)
        self.assertIn("/var/backups/dentistry1402/site-data/", deployer)


if __name__ == "__main__":
    unittest.main()
