#!/usr/bin/env python3
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCHEMA = json.loads((ROOT / "contracts/classops-v1.schema.json").read_text(encoding="utf-8"))
BOUNDARIES = json.loads((ROOT / "contracts/shared-boundaries-v1.json").read_text(encoding="utf-8"))
PHP = (ROOT / "public_html/api/classops_store.php").read_text(encoding="utf-8")


def php_array(function_name: str) -> list[str]:
    match = re.search(rf"function\s+{re.escape(function_name)}\(\):\s*array\s*\{{.*?return\s*\[(.*?)\];", PHP, re.S)
    if not match:
        raise AssertionError(f"missing {function_name}")
    return re.findall(r"'([^']+)'", match.group(1))


assert SCHEMA["properties"]["contractVersion"]["const"] == "classops-v1"
assert SCHEMA["properties"]["type"]["enum"] == php_array("classops_allowed_types")
assert SCHEMA["properties"]["status"]["enum"] == php_array("classops_allowed_statuses")
assert SCHEMA["properties"]["importance"]["enum"] == php_array("classops_allowed_importance")
for required in ("structuredDraft", "canonicalStudentIdentity", "runtimeSiteService", "notificationIntegration"):
    assert required in BOUNDARIES["boundaries"]
assert BOUNDARIES["boundaries"]["canonicalStudentIdentity"]["displayNameIsIdentity"] is False
assert BOUNDARIES["boundaries"]["runtimeSiteService"]["dependsOnEndUserBotLink"] is False
assert BOUNDARIES["boundaries"]["notificationIntegration"]["parallelFeedAllowed"] is False
print("shared contract freeze: ok")
