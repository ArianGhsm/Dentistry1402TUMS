#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

service = (ROOT / "bot_runtime/dent_bot/service.py").read_text(encoding="utf-8")
bale = (ROOT / "bot_runtime/dent_bot/bale_service.py").read_text(encoding="utf-8")
app = (ROOT / "bot_runtime/dent_bot/app.py").read_text(encoding="utf-8")
runtime = (ROOT / "bot_runtime/dent_bot/runtime.py").read_text(encoding="utf-8")
router = (ROOT / "bot_runtime/dent_bot/feature_router.py").read_text(encoding="utf-8")
ui = (ROOT / "bot_runtime/dent_bot/classops_ui.py").read_text(encoding="utf-8")
classops_product = (ROOT / "bot_runtime/dent_bot/class_operations.py").read_text(encoding="utf-8")
term7 = (ROOT / "bot_runtime/dent_bot/term7_group_management.py").read_text(encoding="utf-8")
classops_runtime = (ROOT / "bot_runtime/dent_bot/classops_runtime.py").read_text(encoding="utf-8")
rich = (ROOT / "bot_runtime/dent_bot/academic_term7_rich.py").read_text(encoding="utf-8")
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
    assert "run_service(settings=settings, api=api" in entrypoint, f"{name} must use shared runtime"
    assert "install_" not in entrypoint, f"{name} entrypoint must not depend on installer order"

# One explicit routing chain; feature modules may not replace DentBotApp/runtime methods.
assert "route_feature_callback(self, callback" in app
assert "route_feature_message(self, message)" in app
assert "decorate_feature_screen(self, name, user_id, screen)" in app
assert "handle_classops_ui_callback" in router
assert "handle_term7_callback" in router
assert "handle_class_operations_callback" in router
assert router.index("handle_classops_ui_callback(app") < router.index("handle_term7_callback(app") < router.index("handle_class_operations_callback(app")

for source_name, source in (
    ("classops_ui.py", ui),
    ("class_operations.py", classops_product),
    ("term7_group_management.py", term7),
    ("classops_runtime.py", classops_runtime),
    ("academic_term7_rich.py", rich),
):
    for forbidden in (
        "DentBotApp._callback =", "DentBotApp._message =", "DentBotApp.handle =",
        "runtime_module._run_background_tasks =", "app_module.home =", "app_module.section =",
        "notification_detail_screen =", "notification_push_screen =",
    ):
        assert forbidden not in source, f"{source_name} still monkey-patches runtime: {forbidden}"
    assert "def install_" not in source, f"{source_name} still exposes order-sensitive installer"

assert "run_classops_background_loop" in runtime, "ClassOps companion must be lifecycle-owned by runtime"
assert "target=run_classops_background_loop" in runtime
assert "def run_classops_background_loop" in classops_runtime
assert 'or "<b>امور کلاس</b>"' in classops_runtime, "empty ClassOps delivery fallback must remain Persian"
assert "def decorate_academic_notification_screen" in rich
assert "decorate_academic_notification_screen(screen, notification)" in runtime

assert 'action="classops-v2:' not in ui, "current renderer must not emit v2 callbacks"
assert 'action=f"classops-v2:' not in ui, "current renderer must not emit v2 callbacks"
assert "classops-v2:" in ui and "_legacy_classops_action" in ui, "old messages need ingress-only callback translation"
assert 'action="c3:owner"' in shell, "owner shell must route to the current ClassOps owner surface"
assert '"classops-ux-v2"' in owner, "active pre-upgrade owner dialogs must remain resumable"

assert "classops_bot_ui.php" in bot_api
for forbidden in ("classops_bot_ux_v2", "classops_bot_ux_v3"):
    assert forbidden not in bot_api, f"PHP dispatcher still contains generation chain: {forbidden}"
assert "classops_bot_ui_action($serviceAction)" in bot_api
assert "classopsAckStatusV2" in php_ui
assert "classopsNotificationStatusV3" in php_ui
assert "classopsTimelineV3" in php_ui

print("Bot feature routing is explicit, single-generation and free of runtime monkey-patch installers.")
