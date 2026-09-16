#!/usr/bin/env bash
set -Eeuo pipefail

root=/srv/dentistry1402
[[ "$(readlink -f "$root/current")" == "$root/releases/"* ]] || exit 70
nginx -t >/dev/null
php-fpm8.3 -t >/dev/null

python3 - "$root/shared/storage" <<'PY'
import json
import pathlib
import sys

root = pathlib.Path(sys.argv[1])
targets = {
    "accounts": ("auth/users.json", "users"),
    "bot_links": ("integrations/bot_links.json", "links"),
    "onboarding_profiles": ("integrations/bot_links.json", "onboardingProfiles"),
    "payment_deliveries": ("integrations/bot_payment_deliveries.json", "deliveries"),
    "notification_deliveries": ("integrations/bot_notification_deliveries.json", "deliveries"),
    "orders": ("payments/store.json", "orders"),
    "notifications": ("notifications/store.json", "notifications"),
    "classops_items": ("classops/store.json", "items"),
    "classops_revisions": ("classops/store.json", "revisions"),
    "forms": ("forms/store.json", "forms"),
    "exam_records": ("exams/store.json", "examRecords"),
}

counts = {}
for label, (relative, key) in targets.items():
    payload = json.loads((root / relative).read_text(encoding="utf-8"))
    value = payload.get(key, [])
    counts[label] = len(value) if isinstance(value, (list, dict)) else -1
print("DATA_COUNTS " + " ".join(f"{key}={counts[key]}" for key in sorted(counts)))

for path in root.rglob("*.json"):
    json.loads(path.read_text(encoding="utf-8"))
print("JSON_STORES_VALID")
PY

python3 - <<'PY'
from pathlib import Path

paths = [
    Path("/srv/dentistry1402/shared/storage/logs/errors.jsonl"),
    Path("/var/log/nginx/dentistry1402.error.log"),
    Path("/var/log/php8.3-fpm-dentistry1402.log"),
]
text = "\n".join(path.read_text(encoding="utf-8", errors="replace") for path in paths if path.is_file())
for marker in (
    "BOT_STORE_SHORT_WRITE",
    "JSON_STORE_SHORT_WRITE",
    "CLASSOPS_INTERNAL_ERROR",
    "PHP Fatal",
    "Parse error",
):
    print(f"LOG_MARKER {marker.replace(' ', '_')}={text.count(marker)}")
PY

for service in nginx php8.3-fpm integrated-dent-bot.service integrated-dent-bale-bot.service integrated-dent-telegram-egress.service; do
  systemctl is-active --quiet "$service"
done

for timer in dentistry1402-session-clean.timer dentistry1402-backup.timer dentistry1402-restore-drill.timer dentistry1402-housekeeping.timer; do
  systemctl is-active --quiet "$timer"
  systemctl is-enabled --quiet "$timer"
done
for executable in session-clean backup-runtime restore-drill housekeeping; do
  test -x "/usr/local/lib/dentistry1402/$executable"
done

test -d /var/backups/dentistry1402-runtime
test "$(stat -c %a /var/backups/dentistry1402-runtime)" = 700

curl --fail --silent --show-error --output /dev/null https://dentistry1402tums.ir/
curl --fail --silent --show-error --output /dev/null 'https://dentistry1402tums.ir/api/auth_api.php?action=me'
echo SITE_VERIFY_OK
