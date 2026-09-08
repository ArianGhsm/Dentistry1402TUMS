from __future__ import annotations

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCHEMA_PATH = ROOT / "contracts" / "candidates" / "classops-audience-v1.json"
FROZEN_PATH = ROOT / "contracts" / "classops-v1.schema.json"
BOUNDARIES_PATH = ROOT / "contracts" / "shared-boundaries-v1.json"
AUDIENCE_DIR = ROOT / "public_html" / "api" / "classops_modules" / "audience"
DOC_PATH = ROOT / "docs" / "classops" / "AUDIENCE_POLICY.md"


def check(condition: bool, message: str) -> None:
    if not condition:
        raise RuntimeError(message)


def read_json(path: Path) -> dict:
    data = json.loads(path.read_text(encoding="utf-8-sig"))
    check(isinstance(data, dict), f"{path} must contain a JSON object")
    return data


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8-sig")


def php_constant(source: str, name: str) -> int:
    match = re.search(rf"const\s+{re.escape(name)}\s*=\s*(\d+)\s*;", source)
    check(match is not None, f"missing PHP constant {name}")
    return int(match.group(1))


schema = read_json(SCHEMA_PATH)
check(schema.get("$schema") == "https://json-schema.org/draft/2020-12/schema", "candidate must use JSON Schema 2020-12")
check(schema.get("$ref") == "#/$defs/spec", "candidate root must be the canonical normalized spec")
check("CANDIDATE ONLY" in str(schema.get("$comment", "")), "candidate status must be explicit")

defs = schema.get("$defs")
check(isinstance(defs, dict), "candidate schema definitions missing")
for required_def in (
    "spec",
    "expression",
    "wholeCohortExpression",
    "studentsExpression",
    "selectorExpression",
    "notExpression",
    "anyExpression",
    "allExpression",
    "resolution",
    "snapshot",
    "preview",
):
    check(required_def in defs, f"candidate schema missing $defs/{required_def}")

spec = defs["spec"]
check(spec.get("additionalProperties") is False, "spec must reject unknown fields")
check(
    spec.get("required")
    == ["version", "resolutionMode", "expression", "includeStudentNumbers", "excludeStudentNumbers"],
    "normalized spec required fields drifted",
)
check(spec["properties"]["version"].get("const") == "classops-audience-v1", "candidate version drifted")
check(set(spec["properties"]["resolutionMode"].get("enum", [])) == {"snapshot", "live"}, "resolution modes drifted")

expr_refs = {entry.get("$ref") for entry in defs["expression"].get("oneOf", []) if isinstance(entry, dict)}
check(
    expr_refs
    == {
        "#/$defs/wholeCohortExpression",
        "#/$defs/studentsExpression",
        "#/$defs/selectorExpression",
        "#/$defs/notExpression",
        "#/$defs/anyExpression",
        "#/$defs/allExpression",
    },
    "expression operator set drifted",
)
check(
    set(defs["selectorExpression"]["properties"]["kind"].get("enum", [])) == {"role", "group", "category"},
    "selector kinds drifted",
)
check(defs["studentsExpression"]["properties"]["studentNumbers"].get("maxItems") == 500, "student-node bound drifted")
check(defs["anyExpression"]["properties"]["children"].get("maxItems") == 16, "OR child bound drifted")
check(defs["allExpression"]["properties"]["children"].get("maxItems") == 16, "AND child bound drifted")
check(defs["snapshot"]["properties"]["version"].get("const") == "classops-audience-snapshot-v1", "snapshot version drifted")
check(defs["resolution"]["properties"]["version"].get("const") == "classops-audience-resolution-v1", "resolution version drifted")
check(defs["preview"]["properties"]["version"].get("const") == "classops-audience-preview-v1", "preview version drifted")
check(defs["studentNumber"].get("pattern") == "^[0-9]{5,20}$", "canonical student-number pattern drifted")

core_source = read_text(AUDIENCE_DIR / "core.php")
spec_source = read_text(AUDIENCE_DIR / "spec.php")
resolution_source = read_text(AUDIENCE_DIR / "resolution.php")
resolver_source = read_text(AUDIENCE_DIR / "resolver.php")
snapshot_source = read_text(AUDIENCE_DIR / "snapshot.php")
adapter_source = read_text(AUDIENCE_DIR / "auth_store_source.php")
source_interface = read_text(AUDIENCE_DIR / "source.php")

check("CLASSOPS_AUDIENCE_CONTRACT_VERSION = 'classops-audience-v1'" in core_source, "runtime candidate version drifted")
check("CLASSOPS_AUDIENCE_RESOLUTION_VERSION = 'classops-audience-resolution-v1'" in core_source, "runtime resolution version drifted")
check("CLASSOPS_AUDIENCE_SNAPSHOT_VERSION = 'classops-audience-snapshot-v1'" in core_source, "runtime snapshot version drifted")
check("CLASSOPS_AUDIENCE_PREVIEW_VERSION = 'classops-audience-preview-v1'" in core_source, "runtime preview version drifted")
check(php_constant(core_source, "CLASSOPS_AUDIENCE_MAX_DEPTH") == 6, "runtime max depth drifted")
check(php_constant(core_source, "CLASSOPS_AUDIENCE_MAX_NODES") == 64, "runtime max nodes drifted")
check(php_constant(core_source, "CLASSOPS_AUDIENCE_MAX_CHILDREN") == 16, "runtime max children drifted")
check(php_constant(core_source, "CLASSOPS_AUDIENCE_MAX_STUDENT_REFS") == 500, "runtime list bound drifted")
check(php_constant(core_source, "CLASSOPS_AUDIENCE_MAX_TOTAL_STUDENT_REFS") == 1000, "runtime aggregate ref bound drifted")
check(php_constant(core_source, "CLASSOPS_AUDIENCE_MAX_ROSTER") == 5000, "runtime roster bound drifted")
check(php_constant(core_source, "CLASSOPS_AUDIENCE_MAX_SELECTORS") == 200, "runtime selector bound drifted")
check(php_constant(core_source, "CLASSOPS_AUDIENCE_MAX_SELECTOR_MEMBERS") == 5000, "runtime selector-member bound drifted")

for marker in (
    "classops_audience_normalize_student_number",
    "AUDIENCE_DUPLICATE_STUDENT_REF",
    "AUDIENCE_INCLUDE_EXCLUDE_CONFLICT",
    "CLASSOPS_AUDIENCE_LEGACY_MODE_REQUIRES_MIGRATION",
):
    check(marker in core_source + spec_source, f"missing normalization/policy marker {marker}")

check("AUDIENCE_NEGATION_UNRESOLVED" in resolution_source, "unresolved NOT must fail closed")
check("CLASSOPS_AUDIENCE_CROSS_COHORT_REFERENCE" in resolution_source + snapshot_source, "cross-cohort fail-closed marker missing")
check("CLASSOPS_AUDIENCE_DRIFT" in resolver_source, "live drift protection missing")
check("expectedResolutionHash" in resolver_source, "preview-confirm hash boundary missing")
check("includeIdentifiers = false" in resolver_source, "privacy-safe preview default missing")
check("classops_audience_snapshot_from_result" in snapshot_source, "snapshot production missing")
check("CLASSOPS_AUDIENCE_SNAPSHOT_TAMPERED" in snapshot_source, "snapshot integrity check missing")
check("interface DentClassOpsAudienceSourceV1" in source_interface, "read-only audience source interface missing")

for forbidden in (
    "dent_bot_",
    "bot_store.php",
    "telegram",
    "bale",
    "dent_rotation_assignment_for_name",
    "dent_user_rotation_assignment",
):
    check(forbidden.lower() not in adapter_source.lower(), f"auth-store audience adapter contains forbidden dependency: {forbidden}")
for required in (
    "dent_load_user_store",
    "dent_user_cohort_key",
    "dent_normalize_student_number",
    "dent_normalize_role",
):
    check(required in adapter_source, f"auth-store adapter missing canonical dependency: {required}")

frozen = read_json(FROZEN_PATH)
frozen_audience = frozen.get("$defs", {}).get("audience", {})
check(
    frozen_audience.get("properties", {}).get("version", {}).get("const") == "classops-audience-placeholder-v1",
    "feature branch must not silently replace the frozen audience placeholder",
)

boundaries = read_json(BOUNDARIES_PATH)
identity = boundaries.get("boundaries", {}).get("canonicalStudentIdentity", {})
notification = boundaries.get("boundaries", {}).get("notificationIntegration", {})
structured = boundaries.get("boundaries", {}).get("structuredDraft", {})
check(identity.get("displayNameIsIdentity") is False, "display-name identity boundary changed")
check(set(identity.get("keys", [])) == {"studentNumber", "canonicalUserId"}, "canonical identity keys changed")
check(notification.get("parallelFeedAllowed") is False, "parallel notification feed boundary changed")
check(structured.get("deterministicResolution") == "outside-producer", "AI/deterministic resolution boundary changed")
check(structured.get("directSend") is False, "AI direct-send boundary changed")

policy = read_text(DOC_PATH)
for marker in (
    "classops_normalize_audience()",
    "contracts/classops-v1.schema.json",
    "classops_api_owner_for_mutation()",
    "DentClassOpsAuthStoreAudienceSource",
    "expectedResolutionHash",
    "dent_rotation_assignment_for_name()",
    "notification subsystem",
    "academic_term7.php",
):
    check(marker in policy, f"integration handoff missing marker: {marker}")

# Synthetic normalized sample: guards the documented canonical surface without a
# new Python package/dependency. Runtime behavior is exercised by the PHP test.
sample = {
    "version": "classops-audience-v1",
    "resolutionMode": "snapshot",
    "expression": {
        "op": "any",
        "children": [
            {"op": "students", "studentNumbers": ["10001"]},
            {"op": "selector", "kind": "role", "key": "student"},
        ],
    },
    "includeStudentNumbers": [],
    "excludeStudentNumbers": [],
}
check(set(sample) == set(spec["required"]), "sample canonical spec surface drifted")
check(sample["version"] == spec["properties"]["version"]["const"], "sample candidate version invalid")
check(sample["resolutionMode"] in spec["properties"]["resolutionMode"]["enum"], "sample resolution mode invalid")

print("ClassOps audience contract consistency tests passed.")
