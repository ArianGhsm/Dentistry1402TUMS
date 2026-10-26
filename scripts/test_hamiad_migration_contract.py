#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
page = (ROOT / 'public_html/hamiad/index.php').read_text()
api = (ROOT / 'public_html/api/hamiad_bridge_api.php').read_text()
service = (ROOT / 'public_html/api/hamiad_bridge_service.php').read_text()
transport = (ROOT / 'public_html/api/hamiad_http.php').read_text()
storage = (ROOT / 'public_html/api/hamiad_storage.php').read_text()
home = (ROOT / 'public_html/app/index.html').read_text()
legacy = (ROOT / 'public_html/api/navid_api.php').read_text()
assert '/account/?returnTo=%2Fhamiad%2F' in page, 'unauthenticated owner must have a return path'
assert 'if (!$isOwner)' in page, 'never redirect to opaque blank stage'
assert 'hamiad-form' in page and 'hamiad-courses' in page
assert "require_once __DIR__ . '/hamiad_http.php'" in service
assert "require_once __DIR__ . '/hamiad_storage.php'" in service
assert 'navid_' not in service and 'navid_' not in transport
assert 'hamiad_seal($cookies)' in service
assert 'aes-256-gcm' in storage and 'hamiad/.secret.key' in storage
assert "action === 'courses'" in api and "action === 'course'" in api
assert '/hamiad/' in home and 'href="/navid/"' not in home
assert 'http_response_code(410)' in legacy
assert 'NAVID_RETIRED' in legacy
assert 'navid.tums.ac.ir' not in home
print('Hamiad migration UI, session and legacy retirement regression: ok')

http = (ROOT / 'public_html/api/hamiad_http.php').read_text()
course = (ROOT / 'public_html/api/hamiad_courses.php').read_text()
pwa = (ROOT / 'public_html/sw.js').read_text()
account_js = (ROOT / 'public_html/assets/site/scripts/account.js').read_text()
home_js = (ROOT / 'public_html/assets/site/scripts/app-home.js').read_text()
assert 'function hamiad_get_html_with_waf_retry' in http
assert "hamiad_merge_cookies($cookies" in http
assert 'hamiad_get_html_with_waf_retry($url, $cookies)' in course
assert "hamiad_get_html_with_waf_retry(" in course
assert 'data-home-quick="hamiad"' in home
assert 'hamiad: {' in home_js
assert 'نوید' not in account_js
assert 'نوید' not in home_js
assert '20261024-hamiad-assignments' in pwa
assert 'hamiadAssignmentAlerts' in (ROOT / 'public_html/api/notifications_store.php').read_text()
assert '⏳' in (ROOT / 'public_html/assets/site/scripts/hamiad-bridge.js').read_text()

# A systemd oneshot runs PHP CLI under ProtectSystem=strict. Unlike PHP-FPM,
# it does not inherit FPM session settings; it must use the shared writable
# owner session directory and explicitly grant write access to that path.
unit = (ROOT / 'ops/site-vps/dentistry1402-hamiad-reminders.service').read_text()
assert 'ProtectSystem=strict' in unit
assert 'User=dentweb' in unit
assert 'Environment=DENT_SERVER_ONLY_ROOT=/srv/dentistry1402/shared/server-only' in unit
assert 'Environment=DENT_SESSION_SAVE_PATH=/srv/dentistry1402/shared/server-only/sessions' in unit
assert 'ReadWritePaths=/srv/dentistry1402/shared/storage /srv/dentistry1402/shared/server-only/sessions' in unit
assert '/srv/dentistry1402/current/public_html/api/hamiad_reminders_cli.php' in unit
