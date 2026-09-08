from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CONTRACT = ROOT / "contracts" / "candidates" / "classops-digest-v1.json"
ENGINE = ROOT / "public_html" / "api" / "classops_modules" / "digests" / "digest_engine.php"
DOC = ROOT / "docs" / "classops" / "DIGESTS_SUMMARIES.md"

contract = json.loads(CONTRACT.read_text(encoding="utf-8"))
assert contract["candidateContractVersion"] == "classops-digest-v1"
assert contract["schemaVersion"] == 1
assert contract["status"] == "candidate"
assert contract["boundary"]["timezone"] == "Asia/Tehran"
assert contract["boundary"]["projectionMayAuthorize"] is False
assert contract["boundary"]["directDatabaseWrite"] is False
assert contract["boundary"]["directSend"] is False
assert contract["boundary"]["aiSummarization"] is False
assert contract["boundary"]["term7ScheduleCopied"] is False
assert contract["boundary"]["notificationFeedCreated"] is False
assert contract["requestSchema"]["additionalProperties"] is False
assert contract["recordSchema"]["additionalProperties"] is False
assert contract["viewModelSchema"]["additionalProperties"] is False
assert contract["recordSchema"]["visibility"]["additionalProperties"] is False
assert contract["recordSchema"]["timing"]["additionalProperties"] is False
assert "canonical-student-identity-v1" in contract["consumes"]
assert "notification-integration-v1" in contract["consumes"]
assert contract["producerInterfaces"]["scheduleProjection"].startswith("classops-digest-record-v1")

source = ENGINE.read_text(encoding="utf-8")
assert "CLASSOPS_DIGEST_CONTRACT_VERSION = 'classops-digest-v1'" in source
assert "CLASSOPS_DIGEST_RECORD_VERSION = 'classops-digest-record-v1'" in source
assert "CLASSOPS_DIGEST_WEEK_START_ISO = 6" in source
for forbidden in (
    "require_once",
    "require ",
    "include_once",
    "include ",
    "academic_term7.php",
    "notifications_store.php",
    "classops_store.php",
    "auth_store.php",
    "file_put_contents(",
    "fopen(",
    "rename(",
    "unlink(",
    "curl_",
):
    assert forbidden not in source, f"digest engine must remain pure and integration-independent: {forbidden}"
for raw_transport_marker in ("chat_id", "chatId", "platformUserId", "telegramUserId", "baleUserId"):
    assert raw_transport_marker not in source

doc = DOC.read_text(encoding="utf-8")
for required in (
    "dent_term7_scheduler_tick()",
    "existing notification/delivery boundary",
    "site, Telegram and Bale",
    "No integration hotspot was changed",
):
    assert required in doc

print("classops digest contract checks passed")
