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
# Anonymous visits keep the PHP CLI default grace period (currently 24 min).
# Authenticated sessions must survive for the same 30 days as the secure
# session cookie configured by public_html/api/bootstrap.php. The old cleaner
# applied the CLI default to ALL files and logged users out every 30 minutes.
anonymous_gc_minutes=$(( (gc_seconds + 59) / 60 ))
authenticated_gc_minutes=$(( 30 * 24 * 60 ))

# Match Debian's sessionclean safety behavior: refresh ctime on session files
# that are currently open by PHP-FPM workers before deleting expired files.
for pid in $(pidof php-fpm8.3 2>/dev/null || true); do
  find "/proc/$pid/fd" -ignore_readdir_race -lname "$session_dir/sess_*" \
    -exec touch -c -- {} \; 2>/dev/null || true
done

before="$(find "$session_dir" -maxdepth 1 -type f -name 'sess_*' -printf '.' | wc -c)"
# Only inspect sessions that exceeded the short anonymous grace period.
# PHP file-session format contains `student_number|s:` once a user logs in.
# Do not keep temporary anonymous sessions for 30 days (disk exhaustion).
dent_session_retention_minutes() {
  local candidate="$1"
  if grep -aFq 'student_number|s:' "$candidate" 2>/dev/null; then
    printf '%s\n' "$authenticated_gc_minutes"
  else
    printf '%s\n' "$anonymous_gc_minutes"
  fi
}

while IFS= read -r -d '' session_file; do
  expiry_minutes="$(dent_session_retention_minutes "$session_file")"

  # Recheck ctime immediately before deletion: a live PHP worker may have
  # updated the session after the initial find.
  if find "$session_file" -maxdepth 0 -type f -cmin "+$expiry_minutes" -print -quit 2>/dev/null | grep -q .; then
    rm -f -- "$session_file"
  fi
done < <(find -O3 "$session_dir/" -ignore_readdir_race -depth -mindepth 1 \
  -name 'sess_*' -type f -cmin "+$anonymous_gc_minutes" -print0)
after="$(find "$session_dir" -maxdepth 1 -type f -name 'sess_*' -printf '.' | wc -c)"

printf 'SESSION_CLEAN_OK before=%s after=%s deleted=%s anonymous_max_age_minutes=%s authenticated_max_age_minutes=%s\n' \
  "$before" "$after" "$(( before - after ))" "$anonymous_gc_minutes" "$authenticated_gc_minutes"
