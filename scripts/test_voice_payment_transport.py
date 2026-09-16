from __future__ import annotations

from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
return_source = (ROOT / "public_html" / "api" / "voice_payment_return.php").read_text(
    encoding="utf-8"
)
nginx_source = (ROOT / "ops" / "site-vps" / "nginx-dentistry1402.conf").read_text(
    encoding="utf-8"
)

assert "http://185.239.0.235" not in return_source
assert "Location: http://" not in return_source
assert "Location: https://dentistry1402tums.ir/" in return_source

relay_marker = "location = /api/voice_payment_return.php {"
assert relay_marker in nginx_source
relay_start = nginx_source.index(relay_marker)
relay_end = nginx_source.index("\n    }", relay_start)
relay_block = nginx_source[relay_start:relay_end]
assert "access_log off;" in relay_block
assert "fastcgi_pass unix:/run/php/php8.3-fpm-dentistry1402.sock;" in relay_block

for path, port in (("voice-pay", "18081"), ("voice-bale-pay", "18082")):
    marker = f'^/{path}/callback/([A-Za-z0-9_-]{{20,80}})$'
    assert marker in nginx_source
    block_start = nginx_source.index(marker)
    block_end = nginx_source.index("\n    }", block_start)
    block = nginx_source[block_start:block_end]
    assert "access_log off;" in block
    assert f"proxy_pass http://127.0.0.1:{port}/callback/$1;" in block
    assert "proxy_set_header X-Forwarded-Proto https;" in block

print("VOICE_PAYMENT_TRANSPORT_OK")
