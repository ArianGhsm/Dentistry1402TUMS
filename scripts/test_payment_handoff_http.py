"""Isolated real PHP + signed bot API + browser tests; no real gateway or payment.

Optional --browser uses test-only Playwright with an installed Chrome/Chromium.
The browser test serves the production PHP document at one loopback origin and
replaces only its fixed gateway origin with a second loopback receiver.
"""
from __future__ import annotations

import argparse
import base64
import contextlib
import hashlib
import hmac
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlsplit, parse_qs
from urllib.request import build_opener, ProxyHandler, HTTPRedirectHandler
from urllib.error import HTTPError

from test_bot_api_http import request

ROOT = Path(__file__).resolve().parents[1]
OPENER = build_opener(ProxyHandler({}))


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def read_url(url):
    try:
        response = OPENER.open(url, timeout=10)
    except HTTPError as error:
        response = error
    with response:
        return response.status, dict(response.headers), response.read()


def free_port():
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        return sock.getsockname()[1]


class FakeGateway(BaseHTTPRequestHandler):
    starts = []
    requests = []
    serial = 123456789

    def log_message(self, *args):
        pass

    def do_POST(self):
        payload = json.loads(self.rfile.read(int(self.headers["Content-Length"])))
        type(self).requests.append(payload)
        if self.path == "/request":
            type(self).serial += 1
            value = {"result": 100, "trackId": type(self).serial}
        else:
            value = {"result": 202, "status": 1}
        body = json.dumps(value).encode()
        self.send_response(200)
        self.send_header("Content-Type", "application/json")
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        type(self).starts.append(self.headers.get("Referer", ""))
        self.send_response(200)
        self.end_headers()
        self.wfile.write(b"Unpaid test gateway receiver")


def browser_check(document, headers, gateway_origin):
    from playwright.sync_api import sync_playwright

    # Test adapter only: retain the complete production body, nonce and headers.
    body = document.replace(b"https:\\/\\/gateway.zibal.ir", gateway_origin.encode())
    body = body.replace(b"https://gateway.zibal.ir", gateway_origin.encode())

    class FirstParty(BaseHTTPRequestHandler):
        def log_message(self, *args):
            pass

        def do_GET(self):
            self.send_response(200)
            for key in ("Content-Type", "Cache-Control", "Referrer-Policy", "Content-Security-Policy"):
                self.send_header(key, headers[key])
            self.end_headers()
            self.wfile.write(body)

    with running_server(FirstParty) as first_party:
        origin = f"http://127.0.0.1:{first_party.server_port}"
        chrome = os.environ.get("HANDOFF_TEST_CHROME") or shutil.which("chromium") or shutil.which("google-chrome")
        if not chrome and os.name == "nt":
            chrome = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True, executable_path=chrome)
            try:
                for javascript in (True, False):
                    with browser.new_context(java_script_enabled=javascript) as context:
                        page = context.new_page()
                        page.goto(origin + "/handoff?private-query=must-not-leak", wait_until="domcontentloaded")
                        if not javascript:
                            page.get_by_role("link").click()
                        page.wait_for_url(gateway_origin + "/start/*")
                        assert FakeGateway.starts[-1] == origin + "/", FakeGateway.starts[-1]
                        print(json.dumps({"browser_js": javascript, "observed_referer": FakeGateway.starts[-1]}))
            finally:
                browser.close()


@contextlib.contextmanager
def running_server(handler):
    server = ThreadingHTTPServer(("127.0.0.1", 0), handler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    try:
        yield server
    finally:
        server.shutdown()
        server.server_close()
        thread.join()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--browser", action="store_true")
    args = parser.parse_args()
    php = [os.environ.get("PHP_BIN", "php")]
    if os.name == "nt":
        php += ["-d", r"extension_dir=C:\php\ext", "-d", "extension=php_openssl.dll"]
    with tempfile.TemporaryDirectory(prefix="dent-handoff-") as directory, running_server(FakeGateway) as gateway:
        port = free_port()
        origin = f"http://127.0.0.1:{port}"
        gateway_origin = f"http://127.0.0.1:{gateway.server_port}"
        env = dict(os.environ, DENT_STORAGE_ROOT=directory + "/storage", DENT_SERVER_ONLY_ROOT=directory + "/private",
                   DENT_SESSION_SAVE_PATH=directory + "/sessions", DENT_HANDOFF_TEST="1", DENT_APP_ENV="test",
                   DENT_AUTH_SECRET_KEY=base64.b64encode(bytes(range(32))).decode(),
                   DENT_BOT_SERVICE_SECRET="ab" * 32, DENT_SITE_PUBLIC_URL="https://dentistry1402tums.ir",
                   DENT_STUDENT_ASSISTANT_TEST_CONNECTOR="1",
                   DENT_PAYMENT_ZIBAL_REQUEST_URL=gateway_origin + "/request",
                   DENT_PAYMENT_ZIBAL_VERIFY_URL=gateway_origin + "/verify")
        fixture = subprocess.run(php + ["scripts/setup_payment_handoff_fixture.php"], cwd=ROOT, env=env, capture_output=True)
        assert fixture.returncode == 0, fixture.stderr.decode(errors="replace")
        with open(Path(directory) / "server.log", "wb") as log:
            server = subprocess.Popen(php + ["-S", f"127.0.0.1:{port}", "-t", "public_html"], cwd=ROOT, env=env, stdout=log, stderr=log)
            try:
                for _ in range(50):
                    try:
                        read_url(origin + "/payment/start/zibal/")
                        break
                    except OSError:
                        time.sleep(.1)
                endpoint = origin + "/api/bot_api.php?action=service"
                secret = bytes.fromhex("ab" * 32)
                created_orders = []
                for platform, identity in (("telegram", "654321"), ("bale", "654322")):
                    payload = dict(action="createBotPayment", contractVersion="bot-commerce-v2", platform=platform,
                                   platformUserId=identity, offerRef="handoff_fixture_" + platform, title="Synthetic payment",
                                   amountRials=300000, requestId=hashlib.sha256(platform.encode()).hexdigest())
                    created = request(endpoint, secret, payload)
                    assert created["status"] == 200, created
                    value = created["payload"]
                    assert value["alreadyCreated"] is False
                    assert urlsplit(value["redirectUrl"]).hostname == "dentistry1402tums.ir"
                    assert "gateway.zibal.ir/start/" not in value["redirectUrl"]
                    duplicate = request(endpoint, secret, payload)
                    assert duplicate["payload"]["alreadyCreated"] is True, duplicate
                    assert duplicate["payload"]["orderToken"] == value["orderToken"]
                    assert duplicate["payload"]["resultUrl"] == value["resultUrl"]
                    assert urlsplit(duplicate["payload"]["redirectUrl"]).hostname == "dentistry1402tums.ir"
                    # Snapshot is deliberately raw, exactly as legacy pending orders.
                    store = json.loads((Path(directory) / "storage/payments/store.json").read_text(encoding="utf-8"))
                    order = next(o for o in store["orders"] if o["public_token"] == value["orderToken"])
                    assert order["gateway_response_snapshot"]["start"]["redirectUrl"].startswith("https://gateway.zibal.ir/start/")
                    assert order["extra_form_data"]["bot_origin_platform"] == platform
                    status = request(endpoint, secret, dict(action="paymentStatus", platform=platform, platformUserId=identity, orderToken=value["orderToken"]))
                    assert status["status"] == 200, status
                    url = value["redirectUrl"]
                    created_orders.append((platform, value["orderToken"]))
                    query = parse_qs(urlsplit(url).query)
                    canonical = f"zibal-payment-handoff-v1:{query['trackId'][0]}:{query['exp'][0]}".encode()
                    assert query['sig'][0] == hmac.new(bytes(range(32)), canonical, hashlib.sha256).hexdigest()
                    print(platform + ": new + existing/legacy snapshot + status + origin passed")
                voice = request(endpoint, secret, dict(action="voicePaymentStartV1", contractVersion="voice-payment-bridge-v1",
                    platform="telegram", platformUserId="123456", orderId="VT-20260905-TestVoice123", amountRials=200000, callbackToken="x" * 32))
                assert voice["status"] == 200, voice
                assert urlsplit(voice["payload"]["redirectUrl"]).hostname == "dentistry1402tums.ir"
                assert voice["payload"]["contractVersion"] == "voice-payment-bridge-v1"
                assert len(FakeGateway.requests) == 3, "duplicate checkout contacted provider"
                assert all(urlsplit(p["callbackUrl"]).hostname == "dentistry1402tums.ir" for p in FakeGateway.requests)
                no_redirect = build_opener(ProxyHandler({}), NoRedirect())
                for platform, token in created_orders:
                    try:
                        no_redirect.open(origin + "/api/payments_api.php?action=callback&orderToken=" + token + "&success=0", timeout=10)
                        raise AssertionError("callback did not redirect")
                    except HTTPError as response:
                        assert response.code == 302
                        destination = urlsplit(response.headers["Location"])
                        assert destination.hostname == ("t.me" if platform == "telegram" else "ble.ir")
                        assert parse_qs(destination.query)["start"] == ["receipt_" + token]
                parts = urlsplit(url)
                status, headers, document = read_url(origin + parts.path + "?" + parts.query)
                assert status == 200 and document.startswith(b"<!doctype html>")
                assert "Location" not in headers
                assert headers["Referrer-Policy"] == "origin"
                assert headers["Cache-Control"] == "no-store"
                assert headers["X-Content-Type-Options"] == "nosniff"
                assert headers["X-Robots-Tag"] == "noindex, nofollow, noarchive"
                for suffix in ("&url=https://evil.test", "&trackId=1", "&unknown=1"):
                    bad_status, _, bad_body = read_url(origin + parts.path + "?" + parts.query + suffix)
                    assert bad_status == 400 and b"gateway.zibal.ir" not in bad_body
                if args.browser:
                    browser_check(document, headers, gateway_origin)
                logs = (Path(directory) / "storage/payments/gateway.log").read_text(encoding="utf-8")
                assert "payment_handoff_rendered" in logs and "payment_handoff_rejected" in logs
                assert parse_qs(parts.query)["sig"][0] not in logs
                print("Voice bridge + headers + rejected destinations passed; no real transactions")
            finally:
                server.terminate()
                server.wait(timeout=10)


if __name__ == "__main__":
    main()
