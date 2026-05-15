#!/usr/bin/env python3
import argparse
import http.cookiejar
import json
import os
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request


def find_free_port() -> int:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
        sock.bind(("127.0.0.1", 0))
        return int(sock.getsockname()[1])


def wait_for_server(base_url: str, timeout: float = 15.0) -> None:
    deadline = time.time() + timeout
    while time.time() < deadline:
        try:
            with urllib.request.urlopen(base_url + "/") as response:
                if response.status < 500:
                    return
        except Exception:
            time.sleep(0.2)
    raise RuntimeError("Local PHP server did not start in time.")


def request_json(opener: urllib.request.OpenerDirector, url: str, data: dict | None = None) -> dict:
    payload = None
    if data is not None:
        payload = urllib.parse.urlencode(data).encode("utf-8")
    request = urllib.request.Request(url, data=payload, headers={"Accept": "application/json"})
    try:
        with opener.open(request, timeout=30) as response:
            body = response.read().decode("utf-8")
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"HTTP {exc.code} for {url}: {body}") from exc
    try:
        return json.loads(body)
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"Invalid JSON from {url}: {body}") from exc


def request_html(opener: urllib.request.OpenerDirector, url: str) -> str:
    try:
        with opener.open(url, timeout=30) as response:
            if response.status != 200:
                raise RuntimeError(f"Unexpected status {response.status} for {url}")
            return response.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"HTTP {exc.code} for {url}: {body}") from exc


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--project-root", required=True)
    parser.add_argument("--owner-student-number", required=True)
    parser.add_argument("--owner-password", required=True)
    args = parser.parse_args()

    public_root = os.path.join(args.project_root, "public_html")
    if not os.path.isdir(public_root):
        raise RuntimeError(f"public_html not found: {public_root}")

    port = find_free_port()
    base_url = f"http://127.0.0.1:{port}"
    process = subprocess.Popen(
        ["php", "-S", f"127.0.0.1:{port}", "-t", public_root],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
        cwd=args.project_root,
    )

    try:
        wait_for_server(base_url)
        cookie_jar = http.cookiejar.CookieJar()
        opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie_jar))

        login_payload = request_json(
            opener,
            base_url + "/api/auth_api.php",
            {
                "action": "login",
                "studentNumber": args.owner_student_number,
                "password": args.owner_password,
            },
        )
        if not login_payload.get("success") or not login_payload.get("loggedIn"):
            raise RuntimeError(f"Owner login failed in smoke test: {login_payload}")

        cohorts = ["dentistry-1402", "dentistry-1403", "prosthesis-1402"]
        for cohort in cohorts:
            session_payload = request_json(
                opener,
                base_url + f"/api/forms_api.php?action=session&cohort={urllib.parse.quote(cohort)}",
            )
            if not session_payload.get("success"):
                raise RuntimeError(f"forms session failed for {cohort}: {session_payload}")
            list_payload = request_json(
                opener,
                base_url + f"/api/forms_api.php?action=list&cohort={urllib.parse.quote(cohort)}",
            )
            if not list_payload.get("success"):
                raise RuntimeError(f"forms list failed for {cohort}: {list_payload}")

        pages = [
            "/notes/term-6/",
            "/notes/1403/",
            "/prosthesis-1402/term/?term=1",
        ]
        for path in pages:
            html = request_html(opener, base_url + path)
            if "<html" not in html.lower():
                raise RuntimeError(f"Unexpected HTML response for {path}")

        print("OK: multi-cohort forms/resources smoke test passed.")
        return 0
    finally:
        process.terminate()
        try:
            process.wait(timeout=5)
        except subprocess.TimeoutExpired:
            process.kill()
            process.wait(timeout=5)


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        raise
