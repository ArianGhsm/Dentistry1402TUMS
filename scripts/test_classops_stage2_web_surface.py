#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


html = read("public_html/classops/index.html")
dom = read("public_html/assets/classops_ops/classops_stage2_dom.js")
product = read("public_html/assets/classops_ops/class_operations_product.js")
css = read("public_html/assets/classops_ops/class_operations_product.css")
api = read("public_html/api/classops_api.php")
home = read("public_html/app/index.html")

# The page is a first-class site surface, not a separate technical dashboard.
assert "امور کلاس" in html
assert "class-operations-page" in html
assert "/assets/site/styles/core.css" in html
assert "/assets/site/styles/app.css" in html
assert "/assets/site/styles/theme.css" in html
assert "/assets/site/scripts/shell.js" in html
assert 'href="/app/"' in html
assert 'href="/account/"' in html
assert "class_operations_product.css" in html
assert "class_operations_product.js" in html

# Owner management remains server-authorized before controls are exposed.
assert 'id="classops-owner-center" hidden' in html
assert "await client.status()" in dom
assert "setHidden(ownerCenter, false)" in dom
assert "localStorage" not in dom
assert "indexedDB" not in dom

# Student website surface uses the existing authenticated ClassOps student API.
assert 'id="classops-student-center"' in html
assert 'id="classops-student-list"' in html
assert 'id="classops-student-detail"' in html
for action in [
    'student-list',
    'student-get',
    'student-task-transition',
    'student-ack',
    'student-service-transition',
    'tomorrow-summary',
    'weekly-digest',
]:
    assert action in product, action
    assert action in api, action

# User-facing copy must not expose implementation language that caused the UI island.
visible_forbidden = [
    "مرکز عملیات کلاس",
    "Owner Operations Center",
    "نقش canonical",
    "هش مخاطب",
    "مرکز عملیات وب",
    "Trusted preview",
    "Runtime capabilities",
    "Canonical items",
    "Selected revision",
    "Deterministic digests",
]
for marker in visible_forbidden:
    assert marker not in html, marker

# Owner workflow keeps preview -> confirmation semantics and symbolic audience/destinations.
assert "client.request('preview'" in dom
assert "client.request('confirm'" in dom
assert "expectedAudienceHash" in dom
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

# AI remains draft-only while its copy is product-facing.
assert "client.request('ai-draft-create'" in dom
assert "client.request('ai-draft-edit'" in dom
assert "ساخت پیش‌نویس با هوش مصنوعی" in html
assert "بدون تأیید شما چیزی منتشر نمی‌شود" in html

# Type-aware presentation and Saba reminder-only warning are visible in product language.
assert "installTypeAwareComposer" in product
assert "این بخش فقط یادآوری است" in html
assert "نام کاربری، رمز" in html

# Responsive/accessibility contract.
for marker in [".class-operations-card", ".class-operations-grid", "@media (max-width: 560px)"]:
    assert marker in css
assert 'aria-live="polite"' in html

# The home page must expose the class-operations destination after this integration pass.
assert 'href="/classops/"' in home
assert "مدیریت امور کلاس" in home

print("Class operations unified product surface checks passed")
