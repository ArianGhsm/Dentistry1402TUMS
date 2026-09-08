#!/usr/bin/env bash
set -Eeuo pipefail

bundle=${1:-/tmp/dent-site-migration-20260908}
root=/srv/dentistry1402
expected_hash=${FINAL_STORAGE_SHA256:?FINAL_STORAGE_SHA256 is required}
archive="$bundle/final-storage.tar.gz"
next="$root/shared/storage.next.20260908"
previous="$root/shared/storage.pre-vps-cutover"

[[ "$(readlink -f "$root")" == /srv/dentistry1402 ]] || exit 70
[[ "$(dirname "$(readlink -m "$next")")" == /srv/dentistry1402/shared ]] || exit 71
[[ "$(sha256sum "$archive" | awk '{print $1}')" == "$expected_hash" ]] || exit 72
[[ ! -e "$previous" ]] || { echo "pre-cutover storage backup already exists" >&2; exit 73; }

if [[ -e "$next" ]]; then
  [[ "$(readlink -m "$next")" == /srv/dentistry1402/shared/storage.next.20260908 ]] || exit 74
  rm -rf -- "$next"
fi
install -d -o dentweb -g dentweb -m 0750 "$next"
tar -xzf "$archive" -C "$next"

python3 - "$next" <<'PY'
import json
import pathlib
import sys

root = pathlib.Path(sys.argv[1])
files = [path for path in root.rglob("*") if path.is_file()]
for path in files:
    if path.suffix == ".json":
        json.loads(path.read_text(encoding="utf-8"))
print(f"FINAL_STORAGE_VALID files={len(files)} bytes={sum(path.stat().st_size for path in files)}")
PY

chown -R dentweb:dentweb "$next"
find "$next" -type d -exec chmod 0750 {} +
find "$next" -type f -exec chmod 0640 {} +
mv "$root/shared/storage" "$previous"
mv "$next" "$root/shared/storage"

cp -a /etc/hosts /etc/hosts.pre-dentistry1402-cutover
if ! grep -q 'dentistry1402tums.ir www.dentistry1402tums.ir # dent-site-local' /etc/hosts; then
  printf '\n127.0.0.1 dentistry1402tums.ir www.dentistry1402tums.ir # dent-site-local\n' >> /etc/hosts
fi

install -o root -g root -m 0644 "$bundle/nginx-dentistry1402.conf" /etc/nginx/sites-available/dentistry1402.conf
nginx -t
systemctl reload nginx
echo FINAL_STORAGE_SWAP_OK
