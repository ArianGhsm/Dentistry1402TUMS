#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

backup_root=/var/backups/dentistry1402-runtime
report_root="$backup_root/restore-drills"
drill=""
php_pid=""
report=""

cleanup() {
  local rc=$?
  if [[ -n "$php_pid" ]]; then
    kill "$php_pid" 2>/dev/null || true
    wait "$php_pid" 2>/dev/null || true
  fi
  if [[ -n "$drill" ]]; then
    rm -rf --one-file-system -- "$drill" || true
  fi
  if [[ -n "$report" ]]; then
    if [[ "$rc" -eq 0 ]]; then
      printf 'RESTORE_DRILL=SUCCESS\n'
    else
      printf 'RESTORE_DRILL=FAIL rc=%s\n' "$rc"
    fi
    printf 'REPORT=%s\n' "$report"
  fi
  return "$rc"
}
trap cleanup EXIT

for binary in tar sha256sum sqlite3 python3 readlink systemctl curl php ss awk grep setpriv find stat install; do
  command -v "$binary" >/dev/null 2>&1 || { echo "$binary is required" >&2; exit 2; }
done

install -d -o root -g root -m 0700 "$backup_root" "$report_root"
latest="$(find "$backup_root" -maxdepth 1 -type f -name 'dentistry1402-runtime-*.tar.gz' -printf '%T@ %p\n' \
  | sort -nr | head -n 1 | cut -d' ' -f2-)"
[[ -n "$latest" && -f "$latest" ]] || { echo 'no runtime backup archive found' >&2; exit 3; }
sidecar="$latest.sha256"
[[ -f "$sidecar" ]] || { echo 'backup checksum sidecar missing' >&2; exit 4; }

stamp="$(date -u +%Y%m%dT%H%M%SZ)"
report="$report_root/restore-drill-$stamp.txt"
touch "$report"
chmod 0600 "$report"
exec > >(tee "$report") 2>&1

printf 'RESTORE_DRILL_START=%s\n' "$stamp"
printf 'ARCHIVE=%s\n' "$(basename "$latest")"

# Capture live pointers before the drill. The drill must never activate a release
# or restart the bot services.
site_before="$(readlink -f /srv/dentistry1402/current)"
bot_pid_before="$(systemctl show -p MainPID --value integrated-dent-bot.service)"
bale_pid_before="$(systemctl show -p MainPID --value integrated-dent-bale-bot.service)"

# Verify the outer archive and the checksums embedded by backup-runtime.
(
  cd "$backup_root"
  sha256sum -c "$(basename "$sidecar")"
)
tar -tzf "$latest" >/dev/null
printf 'ARCHIVE_CHECK=PASS\n'

drill="$(mktemp -d /var/tmp/dentistry1402-restore-drill.XXXXXX)"
chmod 0711 "$drill"
extract="$drill/extracted"
install -d -o root -g root -m 0700 "$extract"
tar -xzf "$latest" -C "$extract"
(
  cd "$extract"
  sha256sum -c SHA256SUMS >/dev/null
)
printf 'INTERNAL_MANIFEST=PASS\n'

# Backup metadata is the bridge between runtime state and GitHub-managed code.
# Validate the pointer shapes without requiring old releases to remain on disk.
python3 - "$extract/metadata/runtime-pointers.txt" <<'PY'
import pathlib
import re
import sys
path = pathlib.Path(sys.argv[1])
rows = {}
for raw in path.read_text(encoding="utf-8").splitlines():
    if "=" in raw:
        key, value = raw.split("=", 1)
        rows[key] = value
required = {"snapshot", "created_utc", "site_current", "telegram_current", "bale_current"}
missing = sorted(required - rows.keys())
if missing:
    raise SystemExit("runtime pointer metadata missing: " + ",".join(missing))
if re.fullmatch(r"/srv/dentistry1402/releases/[0-9a-f]{40}", rows["site_current"]) is None:
    raise SystemExit("invalid site release pointer metadata")
if re.fullmatch(r"/opt/integrated-dent/releases/telegram-[A-Za-z0-9._-]+", rows["telegram_current"]) is None:
    raise SystemExit("invalid Telegram release pointer metadata")
if re.fullmatch(r"/opt/integrated-dent/releases/bale-[A-Za-z0-9._-]+", rows["bale_current"]) is None:
    raise SystemExit("invalid Bale release pointer metadata")
print("RUNTIME_POINTER_METADATA=PASS")
PY

json_count="$(python3 - "$extract/payload/site-storage" <<'PY'
import json
import pathlib
import sys
root = pathlib.Path(sys.argv[1])
count = 0
for path in root.rglob("*.json"):
    json.loads(path.read_text(encoding="utf-8"))
    count += 1
print(count)
PY
)"
[[ "$json_count" -gt 0 ]] || { echo 'restored snapshot contains no JSON stores' >&2; exit 5; }
printf 'JSON_VALID=%s\n' "$json_count"

# The current integrated runtime has exactly three SQLite databases with three
# distinct ownership contracts. A new DB must be added here deliberately before
# a drill may pass, otherwise a restore could be readable only by root.
mapfile -t sqlite_relatives < <(
  find "$extract/payload/integrated-dent-var" -type f -name '*.sqlite3' -printf '%P\n' | sort
)
expected_sqlite=(
  bale-bot/state.sqlite3
  dent-bot/state.sqlite3
  shared/payment-offers.sqlite3
)
[[ "${sqlite_relatives[*]}" == "${expected_sqlite[*]}" ]] || {
  printf 'unexpected SQLite restore set: %s\n' "${sqlite_relatives[*]}" >&2
  exit 6
}
for relative in "${sqlite_relatives[@]}"; do
  database="$extract/payload/integrated-dent-var/$relative"
  [[ "$(sqlite3 -cmd '.timeout 10000' "$database" 'PRAGMA quick_check;')" == 'ok' ]] || {
    printf 'SQLite quick_check failed: %s\n' "$relative" >&2
    exit 7
  }
  table_count="$(sqlite3 "$database" "SELECT count(*) FROM sqlite_master WHERE type='table';")"
  printf 'SQLITE_OK=%s tables=%s\n' "$relative" "$table_count"
done

# Reconstruct destination shape in isolation. The SQLite backup copies are
# root-owned by design, so restore ownership is an explicit part of this drill.
recovery="$drill/recovery"
install -d -o root -g root -m 0711 \
  "$recovery" "$recovery/srv" "$recovery/srv/dentistry1402" "$recovery/srv/dentistry1402/shared" \
  "$recovery/var" "$recovery/var/lib" "$recovery/etc"
cp -a "$extract/payload/site-storage" "$recovery/srv/dentistry1402/shared/storage"
cp -a "$extract/payload/site-server-only" "$recovery/srv/dentistry1402/shared/server-only"
install -d -o root -g root -m 0755 "$recovery/var/lib/integrated-dent"
install -d -o dentbot -g dentbot -m 0700 "$recovery/var/lib/integrated-dent/dent-bot"
install -d -o dentbale -g dentbale -m 0700 "$recovery/var/lib/integrated-dent/bale-bot"
install -d -o root -g dentcommerce -m 2770 "$recovery/var/lib/integrated-dent/shared"
install -o dentbot -g dentbot -m 0600 \
  "$extract/payload/integrated-dent-var/dent-bot/state.sqlite3" \
  "$recovery/var/lib/integrated-dent/dent-bot/state.sqlite3"
install -o dentbale -g dentbale -m 0600 \
  "$extract/payload/integrated-dent-var/bale-bot/state.sqlite3" \
  "$recovery/var/lib/integrated-dent/bale-bot/state.sqlite3"
install -o root -g dentcommerce -m 0660 \
  "$extract/payload/integrated-dent-var/shared/payment-offers.sqlite3" \
  "$recovery/var/lib/integrated-dent/shared/payment-offers.sqlite3"
cp -a "$extract/payload/integrated-dent-etc" "$recovery/etc/integrated-dent"
chown root:root "$recovery/etc/integrated-dent"
chmod 0711 "$recovery/etc/integrated-dent"

[[ "$(stat -c '%U:%G %a' "$recovery/srv/dentistry1402/shared/storage")" == 'dentweb:dentweb 750' ]]
[[ "$(stat -c '%U:%G %a' "$recovery/srv/dentistry1402/shared/server-only")" == 'dentweb:dentweb 750' ]]
[[ "$(stat -c '%U:%G %a' "$recovery/var/lib/integrated-dent/dent-bot/state.sqlite3")" == 'dentbot:dentbot 600' ]]
[[ "$(stat -c '%U:%G %a' "$recovery/var/lib/integrated-dent/bale-bot/state.sqlite3")" == 'dentbale:dentbale 600' ]]
[[ "$(stat -c '%U:%G %a' "$recovery/var/lib/integrated-dent/shared/payment-offers.sqlite3")" == 'root:dentcommerce 660' ]]
printf 'OWNERSHIP_RECONSTRUCTION=PASS\n'

# Prove the actual service identities can read the restored databases.
setpriv --reuid=dentbot --regid=dentbot --init-groups \
  sqlite3 "$recovery/var/lib/integrated-dent/dent-bot/state.sqlite3" 'PRAGMA quick_check;' | grep -qx ok
setpriv --reuid=dentbale --regid=dentbale --init-groups \
  sqlite3 "$recovery/var/lib/integrated-dent/bale-bot/state.sqlite3" 'PRAGMA quick_check;' | grep -qx ok
setpriv --reuid=dentbot --regid=dentbot --init-groups \
  sqlite3 "$recovery/var/lib/integrated-dent/shared/payment-offers.sqlite3" 'PRAGMA quick_check;' | grep -qx ok
setpriv --reuid=dentbale --regid=dentbale --init-groups \
  sqlite3 "$recovery/var/lib/integrated-dent/shared/payment-offers.sqlite3" 'PRAGMA quick_check;' | grep -qx ok
printf 'SERVICE_USER_DB_ACCESS=PASS\n'

# Exercise production PHP against restored mutable state. No values from the
# user store are printed; only the number of normalized records is reported.
sessions="$drill/sessions"
install -d -o dentweb -g dentweb -m 0700 "$sessions"
storage="$recovery/srv/dentistry1402/shared/storage"
server_only="$recovery/srv/dentistry1402/shared/server-only"
auth_count="$(setpriv --reuid=dentweb --regid=dentweb --init-groups env \
  DENT_STORAGE_ROOT="$storage" \
  DENT_SERVER_ONLY_ROOT="$server_only" \
  DENT_SESSION_SAVE_PATH="$sessions" \
  php -r 'require "/srv/dentistry1402/current/public_html/api/bootstrap.php"; require "/srv/dentistry1402/current/public_html/api/auth_store.php"; $s=dent_load_user_store(); echo is_array($s)?count(($s["users"]??[])):"ERR";')"
[[ "$auth_count" =~ ^[0-9]+$ ]] || { echo 'restored auth store failed to load' >&2; exit 8; }
printf 'PHP_AUTH_STORE_LOAD=PASS users=%s\n' "$auth_count"

# Boot the site on loopback only. The systemd unit additionally denies all
# non-loopback IP traffic, preventing external SMS/bot/payment side effects.
port=''
for candidate in $(seq 18120 18180); do
  if ! ss -ltnH | awk '{print $4}' | grep -qE "(^|:)${candidate}$"; then
    port="$candidate"
    break
  fi
done
[[ -n "$port" ]] || { echo 'no loopback drill port available' >&2; exit 9; }
php_log="$drill/php-server.log"
setpriv --reuid=dentweb --regid=dentweb --init-groups env \
  DENT_STORAGE_ROOT="$storage" \
  DENT_SERVER_ONLY_ROOT="$server_only" \
  DENT_SESSION_SAVE_PATH="$sessions" \
  php -S "127.0.0.1:$port" -t /srv/dentistry1402/current/public_html >"$php_log" 2>&1 &
php_pid=$!
ready=0
for _ in $(seq 1 30); do
  if curl --fail --silent --show-error --output /dev/null "http://127.0.0.1:$port/" 2>/dev/null; then
    ready=1
    break
  fi
  sleep 0.2
done
[[ "$ready" -eq 1 ]] || { echo 'isolated PHP server did not become ready' >&2; exit 10; }
root_code="$(curl --silent --output /dev/null --write-out '%{http_code}' "http://127.0.0.1:$port/")"
auth_code="$(curl --silent --output /dev/null --write-out '%{http_code}' "http://127.0.0.1:$port/api/auth_api.php?action=me")"
[[ "$root_code" == 200 && "$auth_code" == 200 ]] || {
  printf 'isolated HTTP smoke failed root=%s auth=%s\n' "$root_code" "$auth_code" >&2
  exit 11
}
if grep -Eqi 'Fatal error|Uncaught|Parse error' "$php_log"; then
  echo 'PHP fatal detected during isolated restore boot' >&2
  exit 12
fi
printf 'ISOLATED_PHP_BOOT=PASS root=%s auth=%s\n' "$root_code" "$auth_code"

# A restore drill is not allowed to mutate live deployment pointers or restart
# the bot processes.
[[ "$(readlink -f /srv/dentistry1402/current)" == "$site_before" ]]
[[ "$(systemctl show -p MainPID --value integrated-dent-bot.service)" == "$bot_pid_before" ]]
[[ "$(systemctl show -p MainPID --value integrated-dent-bale-bot.service)" == "$bale_pid_before" ]]
for service in nginx php8.3-fpm integrated-dent-bot.service integrated-dent-bale-bot.service integrated-dent-telegram-egress.service; do
  systemctl is-active --quiet "$service"
done
printf 'PRODUCTION_UNTOUCHED=PASS\n'

# Keep at most two years of monthly drill reports.
mapfile -t old_reports < <(find "$report_root" -maxdepth 1 -type f -name 'restore-drill-*.txt' -printf '%T@ %p\n' \
  | sort -nr | tail -n +25 | cut -d' ' -f2-)
for old_report in "${old_reports[@]}"; do
  [[ -n "$old_report" ]] && rm -f -- "$old_report"
done
