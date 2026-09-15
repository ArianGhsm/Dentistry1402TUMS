#!/usr/bin/env bash
set -Eeuo pipefail

session_dir=/srv/dentistry1402/shared/server-only/sessions
php_bin=/usr/bin/php8.3

[[ -d "$session_dir" ]] || {
  echo "SESSION_CLEAN_OK before=0 after=0 max_age_minutes=0 reason=missing-directory"
  exit 0
}
[[ -x "$php_bin" ]] || { echo "php8.3 binary is unavailable" >&2; exit 70; }

gc_seconds="$($php_bin -r 'echo (int) ini_get("session.gc_maxlifetime");')"
[[ "$gc_seconds" =~ ^[0-9]+$ ]] && (( gc_seconds > 0 )) || {
  echo "invalid session.gc_maxlifetime: $gc_seconds" >&2
  exit 71
}
gc_minutes=$(( (gc_seconds + 59) / 60 ))

# Match Debian's sessionclean safety behavior: refresh ctime on session files
# that are currently open by PHP-FPM workers before deleting expired files.
for pid in $(pidof php-fpm8.3 2>/dev/null || true); do
  find "/proc/$pid/fd" -ignore_readdir_race -lname "$session_dir/sess_*" \
    -exec touch -c -- {} \; 2>/dev/null || true
done

before="$(find "$session_dir" -maxdepth 1 -type f -name 'sess_*' -printf '.' | wc -c)"
find -O3 "$session_dir/" -ignore_readdir_race -depth -mindepth 1 \
  -name 'sess_*' -type f -cmin "+$gc_minutes" -delete
after="$(find "$session_dir" -maxdepth 1 -type f -name 'sess_*' -printf '.' | wc -c)"

printf 'SESSION_CLEAN_OK before=%s after=%s deleted=%s max_age_minutes=%s\n' \
  "$before" "$after" "$(( before - after ))" "$gc_minutes"
