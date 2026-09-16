from __future__ import annotations

from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
OPS = ROOT / "ops" / "site-vps"


def test_restore_drill_verifies_snapshot_and_reconstructs_service_ownership() -> None:
    script = (OPS / "restore-drill.sh").read_text(encoding="utf-8")

    assert "sha256sum -c" in script
    assert "sha256sum -c SHA256SUMS" in script
    assert "json.loads" in script
    assert "PRAGMA quick_check;" in script
    assert "unexpected SQLite restore set" in script
    assert "dentbot -g dentbot -m 0600" in script
    assert "dentbale -g dentbale -m 0600" in script
    assert "root -g dentcommerce -m 0660" in script
    assert "setpriv --reuid=dentbot" in script
    assert "setpriv --reuid=dentbale" in script
    assert "DENT_STORAGE_ROOT" in script
    assert "DENT_SERVER_ONLY_ROOT" in script
    assert "mktemp -d /var/tmp/dentistry1402-restore-drill.XXXXXX" in script
    assert 'php -S "127.0.0.1:$port"' in script
    assert "stop_php_server" in script
    assert script.index("ISOLATED_PHP_BOOT=PASS") < script.index("ISOLATED_PHP_SERVER_STOP=PASS")
    assert script.index("ISOLATED_PHP_SERVER_STOP=PASS") < script.index("PRODUCTION_UNTOUCHED=PASS")
    assert "PRODUCTION_UNTOUCHED=PASS" in script
    assert "restore-drill-*.txt" in script
    assert "tail -n +25" in script


def test_restore_drill_systemd_contract_is_monthly_and_loopback_only() -> None:
    service = (OPS / "dentistry1402-restore-drill.service").read_text(encoding="utf-8")
    timer = (OPS / "dentistry1402-restore-drill.timer").read_text(encoding="utf-8")

    assert "ExecStart=/usr/local/lib/dentistry1402/restore-drill" in service
    assert "ProtectSystem=strict" in service
    assert "NoNewPrivileges=false" in service
    assert "RestrictSUIDSGID=false" in service
    assert "NoNewPrivileges=true" not in service
    assert "RestrictSUIDSGID=true" not in service
    assert "CapabilityBoundingSet=CAP_CHOWN CAP_DAC_OVERRIDE CAP_FOWNER CAP_KILL CAP_SETGID CAP_SETUID" in service
    assert "TimeoutStartSec=5min" in service
    assert "IPAddressDeny=any" in service
    assert "IPAddressAllow=localhost" in service
    assert "ReadOnlyPaths=/srv/dentistry1402 /var/lib/integrated-dent /etc/integrated-dent" in service
    assert "ReadWritePaths=/var/backups/dentistry1402-runtime" in service
    assert "OnCalendar=Sun *-*-1..7 04:45:00 Asia/Tehran" in timer
    assert "Persistent=true" in timer
    assert "RandomizedDelaySec=10m" in timer


def test_site_installer_and_verifier_require_restore_drill() -> None:
    installer = (OPS / "install-site.sh").read_text(encoding="utf-8")
    verifier = (OPS / "verify-site.sh").read_text(encoding="utf-8")

    for name in (
        "restore-drill.sh",
        "dentistry1402-restore-drill.service",
        "dentistry1402-restore-drill.timer",
    ):
        assert name in installer
    assert "dentistry1402-restore-drill.timer" in verifier
    assert "restore-drill" in verifier
