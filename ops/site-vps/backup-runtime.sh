#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

backup_root=/var/backups/dentistry1402-runtime
snapshot="dentistry1402-runtime-$(date -u +%Y%m%dT%H%M%SZ)"
stage=""
verify=""
partial=""

cleanup() {
  local rc=$?
  if [[ -n "$stage" ]]; then
    rm -rf --one-file-system -- "$stage" || true
  fi
  if [[ -n "$verify" ]]; then
    rm -rf --one-file-system -- "$verify" || true
  fi
  if [[ -n "$partial" ]]; then
    rm -f -- "$partial" || true
  fi
  return "$rc"
}
trap cleanup EXIT

for binary in tar sha256sum sqlite3 python3 readlink systemctl; do
  command -v "$binary" >/dev/null 2>&1 || { echo "$binary is required" >&2; exit 2; }
done

install -d -o root -g root -m 0700 "$backup_root"
stage="$(mktemp -d "$backup_root/.stage.XXXXXX")"
verify="$(mktemp -d "$backup_root/.verify.XXXXXX")"
partial="$backup_root/.${snapshot}.tar.gz.partial"
archive="$backup_root/${snapshot}.tar.gz"
checksum="$archive.sha256"

install -d -m 0700 \
  "$stage/payload/site-storage" \
  "$stage/payload/site-server-only" \
  "$stage/payload/site-tls" \
  "$stage/payload/integrated-dent-var" \
  "$stage/payload/integrated-dent-etc" \
  "$stage/payload/system-config" \
  "$stage/metadata"

# Website mutable state. Sessions, temp files and nested backups are disposable
# and intentionally excluded from recovery snapshots.
cp -a /srv/dentistry1402/shared/storage/. "$stage/payload/site-storage/"
tar -C /srv/dentistry1402/shared/server-only \
  --exclude='./sessions' --exclude='./sessions/**' \
  --exclude='./tmp' --exclude='./tmp/**' \
  --exclude='./backups' --exclude='./backups/**' \
  -cf - . | tar -C "$stage/payload/site-server-only" -xf -
if [[ -d /srv/dentistry1402/shared/tls ]]; then
  cp -a /srv/dentistry1402/shared/tls/. "$stage/payload/site-tls/"
fi

# SQLite databases are copied transactionally with sqlite's backup API rather
# than by copying live database/WAL files.
while IFS= read -r -d '' db; do
  relative="${db#/var/lib/integrated-dent/}"
  target="$stage/payload/integrated-dent-var/$relative"
  install -d -m 0700 "$(dirname "$target")"
  sqlite3 -cmd '.timeout 10000' "$db" ".backup '$target'"
done < <(find /var/lib/integrated-dent -type f -name '*.sqlite3' -print0 2>/dev/null)

if [[ -d /etc/integrated-dent ]]; then
  cp -a /etc/integrated-dent/. "$stage/payload/integrated-dent-etc/"
fi
for config in \
  /etc/nginx/sites-available/dentistry1402.conf \
  /etc/php/8.3/fpm/pool.d/dentistry1402.conf; do
  if [[ -f "$config" ]]; then
    cp -a "$config" "$stage/payload/system-config/"
  fi
done

{
  printf 'snapshot=%s\n' "$snapshot"
  printf 'created_utc=%s\n' "$(date -u +%FT%TZ)"
  printf 'site_current=%s\n' "$(readlink -f /srv/dentistry1402/current)"
  printf 'telegram_current=%s\n' "$(readlink -f /opt/integrated-dent/telegram/current)"
  printf 'bale_current=%s\n' "$(readlink -f /opt/integrated-dent/bale/current)"
} > "$stage/metadata/runtime-pointers.txt"

systemctl is-active \
  nginx php8.3-fpm integrated-dent-bot.service integrated-dent-bale-bot.service \
  integrated-dent-telegram-egress.service > "$stage/metadata/service-active.txt" 2>&1 || true
systemctl is-enabled \
  dentistry1402-session-clean.timer integrated-dent-telegram-egress-refresh.timer \
  > "$stage/metadata/service-enabled.txt" 2>&1 || true

(
  cd "$stage"
  find payload metadata -type f -print0 | sort -z | xargs -0 sha256sum > SHA256SUMS
)

tar -C "$stage" -czf "$partial" payload metadata SHA256SUMS
chmod 0600 "$partial"
tar -tzf "$partial" >/dev/null
tar -xzf "$partial" -C "$verify"
(
  cd "$verify"
  sha256sum -c SHA256SUMS >/dev/null
)

python3 - "$verify/payload/site-storage" <<'PY'
import json
import pathlib
import sys
root = pathlib.Path(sys.argv[1])
for path in root.rglob('*.json'):
    json.loads(path.read_text(encoding='utf-8'))
PY

while IFS= read -r -d '' db; do
  [[ "$(sqlite3 -cmd '.timeout 10000' "$db" 'PRAGMA quick_check;')" == "ok" ]] || {
    echo "sqlite verification failed for restored snapshot copy" >&2
    exit 3
  }
done < <(find "$verify/payload/integrated-dent-var" -type f -name '*.sqlite3' -print0)

mv -f "$partial" "$archive"
partial=""
sha256sum "$archive" > "$checksum"
chmod 0600 "$archive" "$checksum"

# Keep the 14 newest daily snapshots plus one snapshot for each of the eight
# newest ISO weeks. This gives short-term density and longer rollback coverage.
python3 - "$backup_root" <<'PY'
import datetime as dt
import pathlib
import sys
root = pathlib.Path(sys.argv[1])
archives = sorted(root.glob('dentistry1402-runtime-*.tar.gz'), key=lambda p: p.stat().st_mtime, reverse=True)
keep = set(archives[:14])
weekly = set()
for path in archives:
    stamp = dt.datetime.fromtimestamp(path.stat().st_mtime, tz=dt.timezone.utc)
    key = stamp.isocalendar()[:2]
    if key not in weekly and len(weekly) < 8:
        weekly.add(key)
        keep.add(path)
for path in archives:
    if path in keep:
        continue
    path.unlink(missing_ok=True)
    pathlib.Path(str(path) + '.sha256').unlink(missing_ok=True)
PY

printf 'BACKUP_OK archive=%s bytes=%s\n' "$archive" "$(stat -c %s "$archive")"
