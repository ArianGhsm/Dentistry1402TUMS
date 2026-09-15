#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

service = (ROOT / "bot_runtime/dent_bot/service.py").read_text(encoding="utf-8")
bale = (ROOT / "bot_runtime/dent_bot/bale_service.py").read_text(encoding="utf-8")
ui = (ROOT / "bot_runtime/dent_bot/classops_ui.py").read_text(encoding="utf-8")
shell = (ROOT / "bot_runtime/dent_bot/classops_shell.py").read_text(encoding="utf-8")
owner = (ROOT / "bot_runtime/dent_bot/classops_owner_workflows.py").read_text(encoding="utf-8")
bot_api = (ROOT / "public_html/api/bot_api.php").read_text(encoding="utf-8")
php_ui = (ROOT / "public_html/api/classops_bot_ui.php").read_text(encoding="utf-8")

retired = (
    "bot_runtime/dent_bot/bot_home_classops_ux_v2.py",
    "bot_runtime/dent_bot/bot_home_classops_ux_v2_compat.py",
    "bot_runtime/dent_bot/classops_ux_v3.py",
    "public_html/api/classops_bot_ux_v2.php",
    "public_html/api/classops_bot_ux_v3.php",
)
for relative in retired:
    assert not (ROOT / relative).exists(), f"retired ClassOps generation returned: {relative}"

for name, entrypoint in (("Telegram", service), ("Bale", bale)):
    assert entrypoint.count("install_classops_ui()") == 1, f"{name} must install ClassOps UI exactly once"
    for forbidden in ("install_bot_home_classops_ux_v2", "install_classops_ux_v3", "bot_home_classops_ux_v2"):
        assert forbidden not in entrypoint, f"{name} still references retired ClassOps generation: {forbidden}"

assert "def install_classops_ui()" in ui
assert 'action="classops-v2:' not in ui, "current renderer must not emit v2 callbacks"
assert 'action=f"classops-v2:' not in ui, "current renderer must not emit v2 callbacks"
assert "classops-v2:" in ui and "_legacy_classops_action" in ui, "old messages need ingress-only callback translation"
assert 'action="c3:owner"' in shell, "owner shell must route to the current ClassOps owner surface"
assert '"classops-ux-v2"' in owner, "active pre-upgrade owner dialogs must remain resumable"

assert "classops_bot_ui.php" in bot_api
for forbidden in ("classops_bot_ux_v2", "classops_bot_ux_v3"):
    assert forbidden not in bot_api, f"PHP dispatcher still contains generation chain: {forbidden}"
assert "classops_bot_ui_action($serviceAction)" in bot_api
assert "classopsAckStatusV2" in php_ui, "service action compatibility must survive endpoint consolidation"
assert "classopsNotificationStatusV3" in php_ui
assert "classopsTimelineV3" in php_ui

print("ClassOps runtime wiring is single-generation and compatibility is ingress-only.")
