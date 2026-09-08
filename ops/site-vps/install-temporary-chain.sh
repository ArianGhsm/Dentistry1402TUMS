#!/usr/bin/env bash
set -Eeuo pipefail

tls=/srv/dentistry1402/shared/tls
curl -fsSL --max-time 20 http://yr1.i.lencr.org/ -o /tmp/dent-yr1.der
openssl x509 -inform DER -in /tmp/dent-yr1.der -out /tmp/dent-yr1.pem
curl -fsSL --max-time 20 https://letsencrypt.org/certs/gen-y/root-yr-by-x1.pem -o /tmp/dent-root-yr-by-x1.pem

python3 - "$tls/fullchain.pem.leaf" /tmp/dent-yr1.pem /tmp/dent-root-yr-by-x1.pem "$tls/fullchain.pem.new" <<'PY'
from pathlib import Path
import sys

leaf, intermediate, cross_signed_root, output = map(Path, sys.argv[1:])
output.write_bytes(
    leaf.read_bytes().rstrip()
    + b"\n"
    + intermediate.read_bytes().rstrip()
    + b"\n"
    + cross_signed_root.read_bytes()
)
PY

chmod 0644 "$tls/fullchain.pem.new"
mv "$tls/fullchain.pem.new" "$tls/fullchain.pem"
nginx -t
systemctl reload nginx
rm -f /tmp/dent-yr1.der /tmp/dent-yr1.pem /tmp/dent-root-yr-by-x1.pem
echo TLS_CHAIN_INSTALLED
