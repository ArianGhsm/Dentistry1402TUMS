from __future__ import annotations

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CONTRACT = json.loads((ROOT / "contracts/candidates/classops-surface-v1.json").read_text(encoding="utf-8"))
MODEL = (ROOT / "bot_runtime/dent_bot/classops_surface/model.py").read_text(encoding="utf-8")
RENDER = (ROOT / "bot_runtime/dent_bot/classops_surface/render.py").read_text(encoding="utf-8")
ADAPTERS = (ROOT / "bot_runtime/dent_bot/classops_surface/adapters.py").read_text(encoding="utf-8")
SITE_CORE = (ROOT / "public_html/assets/classops_ops/classops_ops.js").read_text(encoding="utf-8")
SITE_DOM = (ROOT / "public_html/assets/classops_ops/classops_ops_dom.js").read_text(encoding="utf-8")
SITE = SITE_CORE + "\n" + SITE_DOM
PAGE = (ROOT / "public_html/classops/index.html").read_text(encoding="utf-8")
DOC = (ROOT / "docs/classops/CROSS_SURFACE_UX.md").read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise SystemExit("FAIL: " + message)


require(CONTRACT["contractVersion"] == "classops-surface-v1", "candidate contract version mismatch")
require(CONTRACT["status"] == "candidate-not-frozen", "candidate must not masquerade as frozen")
principles = CONTRACT["principles"]
require(principles["rawChatIdsAllowed"] is False, "raw chat ids must be forbidden")
require(principles["directDatabaseMutation"] is False, "surface must not directly mutate DB")
require(principles["directSend"] is False, "surface must not directly send")
require(principles["unsupportedCapabilityBehavior"] == "disabled-or-pending-never-success", "disabled behavior mismatch")

contract_actions = set(CONTRACT["definitions"]["actions"])
python_actions = set(re.findall(r'^    "([a-z0-9_.]+)": \{', MODEL, flags=re.M))
js_actions = set(re.findall(r"^        '([a-z0-9_.]+)': \{roles:", SITE, flags=re.M))
require(contract_actions == python_actions == js_actions, "website/Python/contract action registries diverged")

for action, spec in CONTRACT["definitions"]["actions"].items():
    if spec["kind"] == "mutation":
        require(spec["confirmationRequired"] is True, f"{action} mutation lacks confirmation")
        require(spec["idempotencyRequired"] is True, f"{action} mutation lacks idempotency")
    if spec["expectedRevisionRequired"]:
        require(spec["idempotencyRequired"] is True, f"{action} revision mutation lacks idempotency")

foundation = CONTRACT["definitions"]["foundationCapabilitiesAtBase"]
for pending in ("audience.resolve", "delivery.destinations", "task.requirement", "exam.view", "exam.critical_ack", "reminder.preview", "summary.tomorrow", "summary.weekly"):
    require(foundation[pending] is False, f"{pending} must remain disabled at foundation base")

require('_callback_key("disabled")' in ADAPTERS, "disabled callback fallback missing")
require('supports_button_style = False' in ADAPTERS, "Bale style fallback missing")
require('supports_button_style = True' in ADAPTERS, "Telegram style path missing")
require('چیزی ارسال یا ذخیره نشده' in RENDER, "AI preview-only copy missing")
require('backend-integration-pending' in MODEL, "pending capability reason missing from neutral model")

require('id="classops-owner-center" hidden' in PAGE, "owner controls must be hidden before auth")
require('button disabled' in PAGE, "pending capabilities need explicit disabled controls")
require('id="classops-conflict"' in PAGE, "stale revision conflict state missing")
require("['draft', 'scheduled', 'active'].includes(item.status)" in SITE, "cancel lifecycle guard missing")
require("item.status === 'archived'" in SITE, "archived mutation guard missing")
require("await refresh();\n                    hidden(conflict, false);" in SITE, "conflict state must remain visible after refresh")
require('aria-live="polite"' in PAGE, "accessible live state missing")
require('prefers-reduced-motion' in (ROOT / "public_html/assets/classops_ops/classops_ops.css").read_text(encoding="utf-8"), "reduced-motion state missing")
require('/api/classops_api.php' in SITE, "site must use canonical ClassOps endpoint")
require('/api/auth_api.php?action=authSessions' in SITE, "site must reuse canonical CSRF source")
require('localStorage' not in SITE and 'indexedDB' not in SITE, "ClassOps surface must not create browser persistence")
require('fetch(' not in MODEL and 'requests' not in MODEL, "platform-neutral Python model must not perform network I/O")

for hotspot in ("public_html/api/classops_api.php", "bot_runtime/dent_bot/app.py"):
    require(hotspot in DOC, f"integration handoff missing hotspot {hotspot}")

for forbidden in ("BOT_TOKEN=", "TELEGRAM_TOKEN=", "BALE_TOKEN=", "sk-", "BEGIN PRIVATE KEY"):
    require(forbidden not in "\n".join((MODEL, RENDER, ADAPTERS, SITE, PAGE, DOC)), f"secret-like marker present: {forbidden}")

print("OK: classops cross-surface candidate contract, parity boundaries, disabled states and privacy rules are consistent.")
