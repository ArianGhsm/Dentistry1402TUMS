#!/usr/bin/env bash
set -Eeuo pipefail

site_keep=5
bot_keep=5
site_root=/srv/dentistry1402/releases
bot_root=/opt/integrated-dent/releases

canonical_dir() {
  readlink -f -- "$1"
}

collect_referenced_releases() {
  python3 - "$site_root" "$bot_root" <<'PY'
import os
import pathlib
import sys
roots = [pathlib.Path(x).resolve() for x in sys.argv[1:]]
seen = set()
for proc in pathlib.Path('/proc').iterdir():
    if not proc.name.isdigit():
        continue
    for leaf in ('cwd', 'exe'):
        try:
            target = pathlib.Path(os.readlink(proc / leaf)).resolve()
        except (OSError, RuntimeError):
            continue
        for root in roots:
            try:
                rel = target.relative_to(root)
            except ValueError:
                continue
            if rel.parts:
                seen.add(str(root / rel.parts[0]))
for path in sorted(seen):
    print(path)
PY
}

mapfile -t proc_references < <(collect_referenced_releases)
declare -A protected=()
for path in "${proc_references[@]}"; do
  protected["$path"]=1
done

site_current="$(canonical_dir /srv/dentistry1402/current)"
telegram_current="$(canonical_dir /opt/integrated-dent/telegram/current)"
bale_current="$(canonical_dir /opt/integrated-dent/bale/current)"

[[ "$site_current" == "$site_root/"* ]] || { echo "invalid site current target" >&2; exit 2; }
[[ "$telegram_current" == "$bot_root/telegram-"* ]] || { echo "invalid telegram current target" >&2; exit 2; }
[[ "$bale_current" == "$bot_root/bale-"* ]] || { echo "invalid bale current target" >&2; exit 2; }
protected["$site_current"]=1
protected["$telegram_current"]=1
protected["$bale_current"]=1

prune_family() {
  local root="$1"
  local glob="$2"
  local current="$3"
  local keep_count="$4"
  local label="$5"
  local -a entries=()
  mapfile -t entries < <(find "$root" -mindepth 1 -maxdepth 1 -type d -name "$glob" -printf '%T@ %p\n' | sort -nr | cut -d' ' -f2-)

  local kept=0
  local path
  for path in "${entries[@]}"; do
    if [[ "$path" == "$current" || -n "${protected[$path]:-}" ]]; then
      protected["$path"]=1
    fi
  done

  # Count protected entries first, then keep the newest unprotected releases
  # until the family reaches its target retention size.
  for path in "${entries[@]}"; do
    [[ -n "${protected[$path]:-}" ]] && kept=$((kept + 1))
  done
  for path in "${entries[@]}"; do
    [[ -n "${protected[$path]:-}" ]] && continue
    if (( kept < keep_count )); then
      protected["$path"]=1
      kept=$((kept + 1))
    fi
  done

  local removed=0
  for path in "${entries[@]}"; do
    [[ -n "${protected[$path]:-}" ]] && continue
    [[ "$(dirname "$path")" == "$root" ]] || { echo "unsafe release path: $path" >&2; exit 3; }
    rm -rf --one-file-system -- "$path"
    removed=$((removed + 1))
  done
  printf 'HOUSEKEEPING family=%s before=%s kept=%s removed=%s\n' "$label" "${#entries[@]}" "$(( ${#entries[@]} - removed ))" "$removed"
}

prune_family "$site_root" '[0-9a-f]*' "$site_current" "$site_keep" site
prune_family "$bot_root" 'telegram-*' "$telegram_current" "$bot_keep" telegram
prune_family "$bot_root" 'bale-*' "$bale_current" "$bot_keep" bale

printf 'HOUSEKEEPING_OK\n'
