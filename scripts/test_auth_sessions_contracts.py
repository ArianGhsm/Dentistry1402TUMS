from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]


def require(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


auth_store = (ROOT / "public_html/api/auth_store.php").read_text(encoding="utf-8")
auth_api = (ROOT / "public_html/api/auth_api.php").read_text(encoding="utf-8")
page = (ROOT / "public_html/account/devices/index.html").read_text(encoding="utf-8")
script = (ROOT / "public_html/assets/site/scripts/account-sessions.js").read_text(encoding="utf-8")

for marker in (
    "function dent_auth_session_register",
    "function dent_auth_session_validate_and_touch",
    "function dent_auth_sessions_for_user",
    "function dent_auth_session_revoke",
    "function dent_auth_session_revoke_others",
):
    require(marker in auth_store, f"missing auth session helper: {marker}")

require("hash('sha256', session_id())" in auth_store, "raw PHP session ids must not be persisted")
require("dent_auth_session_register((string) ($user['studentNumber'] ?? ''), true)" in auth_store, "login must register its session")
require("if (!dent_auth_session_validate_and_touch($studentNumber))" in auth_store, "current user must enforce revocation")
require("bool $required = false" in auth_store, "session registry must be fail-safe for authentication")

for action in ("authSessions", "revokeAuthSession", "revokeOtherAuthSessions"):
    require(f"$action === '{action}'" in auth_api, f"missing auth API action: {action}")

for element_id in (
    "auth-sessions-list",
    "end-other-sessions",
):
    require(f'id="{element_id}"' in page, f"missing devices UI element: {element_id}")

require('request("authSessions"' in script, "UI must load real auth sessions")
require('request("revokeAuthSession"' in script, "UI must revoke a selected auth session")
require('request("revokeOtherAuthSessions"' in script, "UI must revoke other auth sessions")
require("private-notes" not in script, "account session UI must not depend on the retired private reader")

print("OK: active auth session contracts are wired end to end.")
