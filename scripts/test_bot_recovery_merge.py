#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import json
import subprocess
import sys
import tempfile
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
RECOVERY = ROOT / "scripts" / "recover_bot_links_merge.py"


def identity(label: str) -> str:
    return hashlib.sha256(label.encode("ascii")).hexdigest()


def link(key: str, platform: str, student: str, marker: str) -> dict:
    return {
        "identityHash": key,
        "platform": platform,
        "studentNumber": student,
        "platformUserIdEncrypted": {"ciphertext": marker},
        "authVersion": "bot-canonical-auth-v1",
        "authMethod": "secure-site-login",
        "authCompletedAt": "2026-09-01T00:00:00Z",
    }


def write(path: Path, value: dict) -> None:
    path.write_text(json.dumps(value, ensure_ascii=False), encoding="utf-8")


def main() -> int:
    owner = "402000001"
    other = "402000002"
    old_tg = identity("old-telegram-owner")
    old_bale = identity("old-bale-owner")
    current_tg = identity("current-telegram-owner")
    current_new = identity("current-new-user")
    with tempfile.TemporaryDirectory(prefix="dent-recovery-merge-") as raw:
        root = Path(raw)
        old = {
            "schemaVersion": 6,
            "links": {
                old_tg: link(old_tg, "telegram", owner, "old-tg"),
                old_bale: link(old_bale, "bale", owner, "old-bale"),
            },
            "onboardingProfiles": {"legacy": {"value": 1}},
            "audit": [{"event": "legacy"}],
            "notificationDeliveries": {"notice-old": {"deliveryId": "notice-old"}},
        }
        current = {
            "schemaVersion": 6,
            "links": {
                current_tg: link(current_tg, "telegram", owner, "current-tg"),
                current_new: link(current_new, "telegram", other, "current-new"),
            },
            "onboardingProfiles": {"new": {"value": 2}},
            "audit": [{"event": "current"}],
            "notificationDeliveries": {"notice-new": {"deliveryId": "notice-new"}},
        }
        users = {
            "schemaVersion": 1,
            "ownerStudentNumber": owner,
            "users": {
                owner: {"studentNumber": owner, "name": "Same Name"},
                other: {"studentNumber": other, "name": "Same Name"},
            },
        }
        payments = {"schemaVersion": 1, "orders": []}
        paths = {name: root / f"{name}.json" for name in ("old", "current", "users", "payments", "candidate", "report")}
        for name, value in (("old", old), ("current", current), ("users", users), ("payments", payments)):
            write(paths[name], value)
        result = subprocess.run(
            [
                sys.executable,
                str(RECOVERY),
                "--old", str(paths["old"]),
                "--current", str(paths["current"]),
                "--users", str(paths["users"]),
                "--payments", str(paths["payments"]),
                "--candidate", str(paths["candidate"]),
                "--report", str(paths["report"]),
            ],
            check=True,
            capture_output=True,
            text=True,
        )
        candidate = json.loads(paths["candidate"].read_text(encoding="utf-8"))
        report = json.loads(paths["report"].read_text(encoding="utf-8"))
        links = candidate["links"]
        assert current_tg in links and current_new in links, result.stdout
        assert old_bale in links, result.stdout
        assert old_tg not in links, result.stdout
        assert set(candidate["onboardingProfiles"]) == {"legacy", "new"}
        assert set(candidate["notificationDeliveries"]) == {"notice-old", "notice-new"}
        assert report["merge"]["newLinksPreserved"] == 2
        assert report["merge"]["currentRelinksPreserved"] == 1
        assert report["owner"] == {"telegram": True, "bale": True}
        assert any(item["resolution"] == "current-relink-wins" for item in report["conflicts"])
    print(json.dumps({"status": "passed", "fixture": "old-plus-new-plus-current-relink"}))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
