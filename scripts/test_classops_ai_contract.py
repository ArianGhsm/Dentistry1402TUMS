#!/usr/bin/env python3
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCHEMA = json.loads((ROOT / "contracts/candidates/classops-ai-draft-v1.json").read_text(encoding="utf-8"))
AI_DIR = ROOT / "public_html/api/classops_modules/ai"
CONTRACT_PHP = (AI_DIR / "contract.php").read_text(encoding="utf-8")
PROVIDER_PHP = (AI_DIR / "provider.php").read_text(encoding="utf-8")
COPILOT_PHP = (AI_DIR / "copilot.php").read_text(encoding="utf-8")
ALL_PHP = CONTRACT_PHP + PROVIDER_PHP + COPILOT_PHP


def php_array(function_name: str) -> list[str]:
    match = re.search(
        rf"function\s+{re.escape(function_name)}\(\):\s*array\s*\{{.*?return\s*\[(.*?)\];",
        CONTRACT_PHP,
        re.S,
    )
    if not match:
        raise AssertionError(f"missing {function_name}")
    return re.findall(r"'([^']+)'", match.group(1))


assert SCHEMA["properties"]["contractVersion"]["const"] == "classops-structured-draft-v1"
assert SCHEMA["additionalProperties"] is False
assert set(SCHEMA["required"]) == set(SCHEMA["properties"])
fields = SCHEMA["$defs"]["fields"]
assert fields["additionalProperties"] is False
assert fields["required"] == php_array("classops_ai_field_names")

type_enum = fields["properties"]["type"]["anyOf"][1]["enum"]
importance_enum = fields["properties"]["importance"]["anyOf"][1]["enum"]
assert type_enum == php_array("classops_ai_allowed_types")
assert importance_enum == php_array("classops_ai_allowed_importance")
assert SCHEMA["$defs"]["destinations"]["items"]["enum"] == php_array("classops_ai_allowed_destinations")

# AI output cannot claim deterministic identity/audience/course/date resolution.
course_props = fields["properties"]["course"]["anyOf"][1]["properties"]
timing_props = fields["properties"]["timing"]["anyOf"][1]["properties"]
audience_props = fields["properties"]["audience"]["anyOf"][1]["properties"]
assert course_props["ref"] == {"type": "null"}
for key in ("startsAt", "endsAt", "dueAt", "timezone"):
    assert timing_props[key] == {"type": "null"}
assert audience_props["mode"] == {"type": "null"}
assert audience_props["refs"] == {"const": []}

preview = SCHEMA["properties"]["preview"]["properties"]
assert preview["required"]["const"] is True
assert preview["confirmed"]["const"] is False
assert preview["mutationAuthority"]["const"] == "none"
assert preview["directSend"]["const"] is False

# Dedicated credential is exact. No generic AvalAI, Voice, STT or speech credential fallback in module code.
assert "DENT_CLASSOPS_AI_AVALAI_API_KEY" in PROVIDER_PHP
assert "DENT_CLASSOPS_AI_MODEL" in PROVIDER_PHP
for forbidden_credential in (
    "getenv('AVALAI_API_KEY')",
    'getenv("AVALAI_API_KEY")',
    "VOICE_STT_API_KEY",
    "VOICEMATN",
    "EBOO",
    "SPEECH_API_KEY",
):
    assert forbidden_credential not in ALL_PHP, forbidden_credential

# No wiring to ClassOps mutation API/store, notification delivery, bot runtime, or payment code.
for forbidden_dependency in (
    "classops_create_item(",
    "classops_update_item(",
    "classops_transition_item(",
    "notifications_enqueue_",
    "payments_api",
    "bot_api.php",
    "classops_api.php",
    "classops_store.php",
):
    assert forbidden_dependency not in ALL_PHP, forbidden_dependency

# Provenance and telemetry are versioned/aggregate; no prompt retention field exists in the contract.
provenance = SCHEMA["properties"]["provenance"]["properties"]
assert provenance["parserVersion"]["const"] == "classops-ai-parser-v1"
assert provenance["promptVersion"]["const"] == "classops-ai-prompt-v1"
serialized = json.dumps(SCHEMA, sort_keys=True).lower()
for forbidden_retention_field in ("rawprompt", "prompttext", "forwardedtext", "ownertext", "nationalcode", "phone"):
    assert forbidden_retention_field not in serialized

print("classops AI candidate contract: ok")
