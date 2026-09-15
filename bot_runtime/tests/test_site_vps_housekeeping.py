from __future__ import annotations

from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
OPS = ROOT / "ops" / "site-vps"


def test_backup_is_verified_and_has_bounded_retention() -> None:
    backup = (OPS / "backup-runtime.sh").read_text(encoding="utf-8")
    service = (OPS / "dentistry1402-backup.service").read_text(encoding="utf-8")
    timer = (OPS / "dentistry1402-backup.timer").read_text(encoding="utf-8")

    assert "sqlite3" in backup
    assert ".backup" in backup
    assert "PRAGMA quick_check;" in backup
    assert "sha256sum -c SHA256SUMS" in backup
    assert "json.loads" in backup
    assert "archives[:14]" in backup
    assert "len(weekly) < 8" in backup
    assert "--exclude='./sessions'" in backup
    assert "--exclude='./backups'" in backup
    assert "local rc=$?" in backup
    assert 'return "$rc"' in backup
    assert "ReadWritePaths=/var/backups/dentistry1402-runtime" in service
    assert "OnCalendar=*-*-* 03:20:00 Asia/Tehran" in timer
    assert "Persistent=true" in timer


def test_housekeeping_preserves_live_releases_and_limits_known_families() -> None:
    housekeeping = (OPS / "housekeeping.sh").read_text(encoding="utf-8")
    service = (OPS / "dentistry1402-housekeeping.service").read_text(encoding="utf-8")
    timer = (OPS / "dentistry1402-housekeeping.timer").read_text(encoding="utf-8")

    assert "site_keep=5" in housekeeping
    assert "bot_keep=5" in housekeeping
    assert "collect_referenced_releases" in housekeeping
    assert "/proc" in housekeeping
    assert "--one-file-system" in housekeeping
    assert "telegram-*" in housekeeping
    assert "bale-*" in housekeeping
    assert "HOUSEKEEPING_OK" in housekeeping
    assert "ReadWritePaths=/srv/dentistry1402/releases /opt/integrated-dent/releases" in service
    assert "OnCalendar=*-*-* 04:10:00 Asia/Tehran" in timer


def test_site_bootstrap_and_verifier_install_housekeeping_controls() -> None:
    installer = (OPS / "install-site.sh").read_text(encoding="utf-8")
    verifier = (OPS / "verify-site.sh").read_text(encoding="utf-8")

    for name in (
        "backup-runtime.sh",
        "dentistry1402-backup.service",
        "dentistry1402-backup.timer",
        "housekeeping.sh",
        "dentistry1402-housekeeping.service",
        "dentistry1402-housekeeping.timer",
    ):
        assert name in installer
    assert "dentistry1402-backup.timer dentistry1402-housekeeping.timer" in installer
    assert "dentistry1402-backup.timer" in verifier
    assert "dentistry1402-housekeeping.timer" in verifier
