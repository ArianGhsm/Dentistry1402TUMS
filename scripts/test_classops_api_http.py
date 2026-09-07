#!/usr/bin/env python3
from __future__ import annotations

import contextlib
import hashlib
import http.cookiejar
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request


ROOT = Path(__file__).resolve().parents[1]


def free_port() -> int:
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        return int(sock.getsockname()[1])


def request(opener, url: str, *, method: str = "GET", fields: dict | None = None, csrf: str = "") -> tuple[int, dict]:
    body = None
    headers = {"Accept": "application/json"}
    if fields is not None:
        body = json.dumps(fields, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
        headers["Content-Type"] = "application/json"
    if csrf:
        headers["X-CSRF-Token"] = csrf
    req = urllib.request.Request(url, data=body, method=method, headers=headers)
    try:
        with opener.open(req, timeout=10) as response:
            return response.status, json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as error:
        return error.code, json.loads(error.read().decode("utf-8"))


def form_request(opener, url: str, fields: dict) -> tuple[int, dict]:
    body = urllib.parse.urlencode(fields).encode("utf-8")
    req = urllib.request.Request(
        url, data=body, method="POST",
        headers={"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8", "Accept": "application/json"},
    )
    try:
        with opener.open(req, timeout=10) as response:
            return response.status, json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as error:
        return error.code, json.loads(error.read().decode("utf-8"))


def main() -> int:
    temp = Path(tempfile.mkdtemp(prefix="dent-classops-http-"))
    storage = temp / "storage"
    sessions = temp / "sessions"
    sessions.mkdir(parents=True)
    env = os.environ.copy()
    env.update({
        "DENT_APP_ENV": "test",
        "DENT_STORAGE_ROOT": str(storage),
        "DENT_SERVER_ONLY_ROOT": str(temp),
        "DENT_SESSION_SAVE_PATH": str(sessions),
    })
    php = os.environ.get("PHP_BIN", "php")
    fixture = subprocess.run(
        [php, str(ROOT / "scripts" / "setup_classops_api_fixture.php")],
        cwd=ROOT, env=env, check=True, capture_output=True, text=True, encoding="utf-8",
    )
    identities = json.loads(fixture.stdout)
    port = free_port()
    server = subprocess.Popen(
        [php, "-S", f"127.0.0.1:{port}", "-t", str(ROOT / "public_html")],
        cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
    )
    base = f"http://127.0.0.1:{port}"
    plain = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    try:
        for _ in range(50):
            try:
                status, capabilities = request(plain, base + "/api/classops_api.php?action=capabilities")
                if status == 200:
                    break
            except OSError:
                pass
            time.sleep(0.1)
        else:
            raise RuntimeError("ClassOps fixture server did not start")

        assert capabilities["contractVersion"] == "classops-v1"
        assert capabilities["features"]["delivery"] is False
        assert capabilities["features"]["ai"] is False

        status, payload = request(plain, base + "/api/classops_api.php?action=status")
        assert status == 401 and payload.get("loggedOut") is True, (status, payload)

        student_jar = http.cookiejar.CookieJar()
        student = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(student_jar))
        status, payload = form_request(student, base + "/api/auth_api.php", {
            "action": "login", "studentNumber": identities["student"], "password": "classops-test-password",
        })
        assert status == 200 and payload.get("loggedIn") is True, (status, payload)
        status, csrf_payload = request(student, base + "/api/auth_api.php?action=authSessions")
        student_csrf = csrf_payload["csrfToken"]
        status, payload = request(student, base + "/api/classops_api.php?action=create", method="POST", csrf=student_csrf, fields={
            "idempotencyKey": "student-create-0001",
            "item": {"cohortKey": "dentistry-1402", "type": "announcement", "title": "forbidden"},
        })
        assert status == 403, (status, payload)

        owner_jar = http.cookiejar.CookieJar()
        owner = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(owner_jar))
        status, payload = form_request(owner, base + "/api/auth_api.php", {
            "action": "login", "studentNumber": identities["owner"], "password": "classops-test-password",
        })
        assert status == 200 and payload.get("loggedIn") is True, (status, payload)
        status, csrf_payload = request(owner, base + "/api/auth_api.php?action=authSessions")
        csrf = csrf_payload["csrfToken"]
        status, payload = request(owner, base + "/api/classops_api.php?action=status")
        assert status == 200 and payload["status"]["initialized"] is False, (status, payload)

        create_body = {
            "idempotencyKey": "http-create-0001",
            "reason": "HTTP contract fixture",
            "item": {
                "cohortKey": "dentistry-1402", "type": "deadline", "title": "HTTP fixture",
                "timing": {"dueAt": "2026-09-09T23:59:00+03:30"},
            },
        }
        status, payload = request(owner, base + "/api/classops_api.php?action=create", method="POST", fields=create_body)
        assert status == 403, (status, payload)  # CSRF is mandatory.
        status, created = request(owner, base + "/api/classops_api.php?action=create", method="POST", csrf=csrf, fields=create_body)
        assert status == 200 and created["item"]["revision"] == 1, (status, created)
        item_id = created["item"]["id"]

        store_path = storage / "classops" / "store.json"
        before_retry = hashlib.sha256(store_path.read_bytes()).hexdigest()
        status, replay = request(owner, base + "/api/classops_api.php?action=create", method="POST", csrf=csrf, fields=create_body)
        assert status == 200 and replay["idempotentReplay"] is True and replay["writePerformed"] is False, (status, replay)
        assert hashlib.sha256(store_path.read_bytes()).hexdigest() == before_retry

        conflict_body = json.loads(json.dumps(create_body))
        conflict_body["item"]["title"] = "different"
        status, payload = request(owner, base + "/api/classops_api.php?action=create", method="POST", csrf=csrf, fields=conflict_body)
        assert status == 409 and payload.get("code") == "CLASSOPS_IDEMPOTENCY_CONFLICT", (status, payload)

        status, payload = request(owner, base + "/api/classops_api.php?action=create", method="POST", csrf=csrf, fields={
            "idempotencyKey": "invalid-cohort-0001",
            "item": {"cohortKey": "unknown-cohort", "type": "event", "title": "bad"},
        })
        assert status == 422 and payload.get("code") == "CLASSOPS_INVALID_COHORT", (status, payload)

        status, updated = request(owner, base + "/api/classops_api.php?action=update", method="POST", csrf=csrf, fields={
            "idempotencyKey": "http-update-0001", "id": item_id, "expectedRevision": 1,
            "reason": "HTTP update fixture", "patch": {"importance": "important"},
        })
        assert status == 200 and updated["item"]["revision"] == 2, (status, updated)
        status, payload = request(owner, base + "/api/classops_api.php?action=update", method="POST", csrf=csrf, fields={
            "idempotencyKey": "http-stale-0001", "id": item_id, "expectedRevision": 1,
            "reason": "stale", "patch": {"location": "bad"},
        })
        assert status == 409 and payload.get("code") == "CLASSOPS_REVISION_CONFLICT", (status, payload)

        status, revisions = request(owner, base + f"/api/classops_api.php?action=revisions&id={urllib.parse.quote(item_id)}")
        assert status == 200 and revisions["data"]["total"] == 2, (status, revisions)
        before_read = hashlib.sha256(store_path.read_bytes()).hexdigest()
        status, listed = request(owner, base + "/api/classops_api.php?action=list&limit=1")
        assert status == 200 and listed["data"]["count"] == 1, (status, listed)
        assert hashlib.sha256(store_path.read_bytes()).hexdigest() == before_read

        store = json.loads(store_path.read_text(encoding="utf-8"))
        serialized_audit = json.dumps(store["audit"], ensure_ascii=False)
        assert identities["owner"] not in serialized_audit
        assert "classops-test-password" not in store_path.read_text(encoding="utf-8")
        print(json.dumps({
            "status": "ok", "ownerOnly": True, "csrf": True, "revision": 2,
            "idempotentRetryZeroWrite": True, "auditContainsRawOwnerId": False,
        }, ensure_ascii=False))
        return 0
    finally:
        server.terminate()
        with contextlib.suppress(subprocess.TimeoutExpired):
            server.wait(timeout=5)
        if server.poll() is None:
            server.kill()
        shutil.rmtree(temp, ignore_errors=True)


if __name__ == "__main__":
    raise SystemExit(main())
