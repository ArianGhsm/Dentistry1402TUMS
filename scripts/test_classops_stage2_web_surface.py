#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


html = read("public_html/classops/index.html")
dom = read("public_html/assets/classops_ops/classops_stage2_dom.js")
css = read("public_html/assets/classops_ops/classops_stage2.css")
api = read("public_html/api/classops_api.php")

# The owner surface is server-authorized before controls are exposed.
assert 'id="classops-owner-center" hidden' in html
assert "await client.status()" in dom
assert "setHidden(ownerCenter, false)" in dom
assert "localStorage" not in dom
assert "indexedDB" not in dom

# Stage2, not the old foundation-only candidate, is the mounted controller.
assert "classops_stage2_dom.js?v=classops-stage2-v1" in html
assert "mountOperationsCenter" in dom
assert "zero mutation until confirm" in html
assert "Pending integration" not in html
assert "هیچ provider فراخوانی نمی‌شود" not in html

# Canonical owner workflow is preview -> exact audience hash -> confirm.
for marker in [
    "client.request('preview'",
    "client.request('confirm'",
    "expectedAudienceHash",
    "audienceSpec",
    "destinations",
    "idempotencyKey",
    "expectedRevision",
]:
    assert marker in dom, marker
assert "CLASSOPS_AUDIENCE_DRIFT" in dom
assert "CLASSOPS_REVISION_CONFLICT" in dom

# Audience and destination controls are symbolic/canonical only.
for marker in [
    'id="classops-audience-mode"',
    'id="classops-audience-students"',
    'id="classops-audience-selector-kind"',
    'id="classops-audience-selector-key"',
    'id="classops-audience-include"',
    'id="classops-audience-exclude"',
    'id="classops-dest-private_users"',
    'id="classops-dest-class_group"',
    'id="classops-dest-information_channel"',
]:
    assert marker in html, marker
for forbidden in ["chat_id", "telegram_id", "bale_id", "bot_token"]:
    assert forbidden not in dom.lower(), forbidden

# AI is a draft producer only and manual mode remains available when unconfigured.
assert "client.request('ai-draft-create'" in dom
assert "client.request('ai-draft-edit'" in dom
assert "lastAiDraft" in dom
assert "AI authority" in html
assert "مسیر دستی کاملاً فعال است" in dom
assert "applyAiToComposer" in dom

# Domain projections are exposed without creating a parallel UI/store.
for action in ["ack-stats", "task-state", "tomorrow-summary", "weekly-digest"]:
    assert action in dom
    assert f"$action === '{action}'" in api

# Saba remains reminder-only and credential-free in presentation semantics.
assert "service_reminder" in dom
assert "serviceRef = 'saba'" in dom
assert "نام کاربری، رمز، token یا session صبا" in html

# Basic responsive/accessibility styling for newly promoted controls is present.
for marker in [".classops-fieldset", ".classops-inline-tools", ".classops-preview-panel", "@media"]:
    assert marker in css
assert 'aria-live="polite"' in html

print("ClassOps Stage2 integrated website surface checks passed")
