from __future__ import annotations

import importlib.util
import tempfile
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
MODULE_PATH = ROOT / "scripts" / "snapshot_remote_storage_verified.py"
SPEC = importlib.util.spec_from_file_location("snapshot_verified", MODULE_PATH)
assert SPEC and SPEC.loader
snapshot = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(snapshot)


class FakeFtp:
    def __init__(self, files: dict[str, bytes]):
        self.files = files

    def retrbinary(self, command: str, callback, blocksize: int = 0) -> None:
        path = command.removeprefix("RETR ")
        if path not in self.files:
            raise snapshot.ftplib.error_perm("550 missing")
        callback(self.files[path])

    def quit(self) -> None:
        return None

    def close(self) -> None:
        return None


with tempfile.TemporaryDirectory(prefix="dent-snapshot-test-") as temp:
    base = Path(temp)
    staging = base / "candidate"
    staging.mkdir()
    latest = base / "latest"
    latest.mkdir()
    sentinel = latest / "sentinel.txt"
    sentinel.write_text("known-good", encoding="utf-8")

    files = {
        "storage/integrations/bot_links.json": b"{malformed",
        "storage/integrations/bot_payment_deliveries.json": b'{"schemaVersion":1,"deliveries":{},"poll":{}}',
        "storage/integrations/bot_notification_deliveries.json": b'{"schemaVersion":1,"deliveries":{},"dispatchSince":""}',
        "storage/auth/users.json": b"{}",
        "storage/payments/store.json": b"{}",
    }
    original_connect = snapshot.connect
    snapshot.connect = lambda _config: FakeFtp(files)
    try:
        try:
            snapshot.capture_once(Path("unused"), "storage", staging, critical_only=True)
        except snapshot.SnapshotError:
            pass
        else:
            raise AssertionError("corrupt critical JSON was accepted")
    finally:
        snapshot.connect = original_connect

    if sentinel.read_text(encoding="utf-8") != "known-good":
        raise AssertionError("failed snapshot changed latest")

deploy = (ROOT / "scripts" / "deploy_public_html.ps1").read_text(encoding="utf-8")
eligibility = deploy.index("eligibleForLatest")
promotion = deploy.index("Reset-DirectoryFromSource -source $snapshotPath")
if eligibility >= promotion:
    raise AssertionError("latest promotion occurs before verified eligibility check")

print("OK: corrupt critical storage cannot be promoted to latest.")
