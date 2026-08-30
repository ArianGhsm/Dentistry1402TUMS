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
    direct_opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    while time.time() < deadline:
        try:
            with direct_opener.open(base_url + "/") as response:
                if response.status < 500:
                    return
        except Exception:
            time.sleep(0.2)
    raise RuntimeError("Local PHP server did not start in time.")


def request_json(opener: urllib.request.OpenerDirector, url: str, data: dict | None = None) -> dict:
    payload = None
    if data is not None:
        payload = urllib.parse.urlencode(data).encode("utf-8")
    request = urllib.request.Request(
        url,
        data=payload,
        headers={
            "Accept": "application/json",
            "Connection": "close",
        },
    )
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
        request = urllib.request.Request(url, headers={"Connection": "close"})
        with opener.open(request, timeout=30) as response:
            if response.status != 200:
                raise RuntimeError(f"Unexpected status {response.status} for {url}")
            return response.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"HTTP {exc.code} for {url}: {body}") from exc


def request_status_ok(opener: urllib.request.OpenerDirector, url: str) -> None:
    request = urllib.request.Request(url, headers={"Connection": "close"})
    try:
        with opener.open(request, timeout=30) as response:
            if response.status != 200:
                raise RuntimeError(f"Unexpected status {response.status} for {url}")
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"HTTP {exc.code} for {url}: {body}") from exc


def run_smoke_session(args: argparse.Namespace) -> None:
    public_root = os.path.join(args.project_root, "public_html")
    if not os.path.isdir(public_root):
        raise RuntimeError(f"public_html not found: {public_root}")

    port = find_free_port()
    base_url = f"http://127.0.0.1:{port}"
    smoke_log_dir = os.path.join(args.project_root, ".codex-local")
    os.makedirs(smoke_log_dir, exist_ok=True)
    smoke_log_path = os.path.join(smoke_log_dir, "smoke_multi_cohort_pages.log")

    with open(smoke_log_path, "w", encoding="utf-8") as smoke_log:
        process = subprocess.Popen(
            ["php", "-S", f"127.0.0.1:{port}", "-t", public_root],
            stdout=smoke_log,
            stderr=smoke_log,
            cwd=args.project_root,
        )

        try:
            wait_for_server(base_url)
            cookie_jar = http.cookiejar.CookieJar()
            # Force direct localhost access on Windows setups that export a global proxy.
            opener = urllib.request.build_opener(
                urllib.request.ProxyHandler({}),
                urllib.request.HTTPCookieProcessor(cookie_jar),
            )

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

            cohorts = ["dentistry-1402", "dentistry-1403", "dentistry-1404", "prosthesis-1402"]
            forms_by_cohort: dict[str, list[dict]] = {}
            for cohort in cohorts:
                request_status_ok(
                    opener,
                    base_url + f"/api/forms_api.php?action=session&cohort={urllib.parse.quote(cohort)}",
                )
                list_payload = request_json(
                    opener,
                    base_url + f"/api/forms_api.php?action=list&cohort={urllib.parse.quote(cohort)}",
                )
                if not list_payload.get("success"):
                    raise RuntimeError(f"Forms list failed for {cohort}: {list_payload}")
                forms = list_payload.get("forms")
                forms_by_cohort[cohort] = forms if isinstance(forms, list) else []

                first_form = forms_by_cohort[cohort][0] if forms_by_cohort[cohort] else None
                if isinstance(first_form, dict) and first_form.get("id"):
                    get_payload = request_json(
                        opener,
                        base_url
                        + f"/api/forms_api.php?action=get&cohort={urllib.parse.quote(cohort)}&form={urllib.parse.quote(str(first_form['id']))}",
                    )
                    if not get_payload.get("success"):
                        raise RuntimeError(f"Forms get failed for {cohort}: {get_payload}")

                manageable_form = next(
                    (
                        form
                        for form in forms_by_cohort[cohort]
                        if isinstance(form, dict)
                        and form.get("id")
                        and isinstance(form.get("permissions"), dict)
                        and form["permissions"].get("canManage")
                    ),
                    None,
                )
                if manageable_form is not None:
                    responses_payload = request_json(
                        opener,
                        base_url
                        + f"/api/forms_api.php?action=responses&cohort={urllib.parse.quote(cohort)}&formId={urllib.parse.quote(str(manageable_form['id']))}",
                    )
                    if not responses_payload.get("success"):
                        raise RuntimeError(f"Forms responses failed for {cohort}: {responses_payload}")

            notifications_summary = request_json(
                opener,
                base_url + "/api/notifications_api.php?action=summary",
            )
            if not notifications_summary.get("success"):
                raise RuntimeError(f"Notifications summary failed: {notifications_summary}")

            notifications_list = request_json(
                opener,
                base_url + "/api/notifications_api.php?action=list",
            )
            if not notifications_list.get("success"):
                raise RuntimeError(f"Notifications list failed: {notifications_list}")

            chat_summary = request_json(
                opener,
                base_url + "/chat/chat_api.php?action=navSummary",
            )
            if not chat_summary.get("success"):
                raise RuntimeError(f"Chat nav summary failed: {chat_summary}")

            navid_feed = request_json(
                opener,
                base_url + "/api/navid_api.php?action=feed",
            )
            if not navid_feed.get("success"):
                raise RuntimeError(f"Navid feed failed: {navid_feed}")

            owner_dashboard = request_json(
                opener,
                base_url + "/api/content_tools_api.php?action=ownerDashboard",
            )
            if not owner_dashboard.get("success"):
                raise RuntimeError(f"Content tools owner dashboard failed: {owner_dashboard}")

            owner_pastes = request_json(
                opener,
                base_url + "/api/content_tools_api.php?action=ownerPastes",
            )
            if not owner_pastes.get("success"):
                raise RuntimeError(f"Owner pastes failed: {owner_pastes}")

            exams_catalog = request_json(
                opener,
                base_url + "/api/exams_api.php?action=catalog",
            )
            if not exams_catalog.get("success"):
                raise RuntimeError(f"Exams catalog failed: {exams_catalog}")

            prosthesis_exams_catalog = request_json(
                opener,
                base_url + "/api/exams_api.php?action=catalog&cohort=prosthesis-1402",
            )
            if not prosthesis_exams_catalog.get("success"):
                raise RuntimeError(f"Prosthesis exams catalog failed: {prosthesis_exams_catalog}")

            pages = [
                "/app/",
                "/account/",
                "/chat/",
                "/exams/",
                "/exams/radiology2/",
                "/exams/radiology2/1/",
                "/forms/",
                "/forms/fill/",
                "/forms/?cohort=dentistry-1403",
                "/forms/?cohort=dentistry-1404",
                "/forms/?cohort=prosthesis-1402",
                "/forms/fill/?cohort=prosthesis-1402",
                "/resources/",
                "/buy/",
                "/navid/",
                "/files/",
                "/paste/",
                "/notes/files/",
                "/html-uploader/",
                "/notes/",
                "/notes/term/?term=6",
                "/notes/?cohort=dentistry-1403",
                "/notes/term/?cohort=dentistry-1403&term=3",
                "/notes/1403/",
                "/notes/?cohort=dentistry-1404",
                "/notes/term/?cohort=dentistry-1404&term=1",
                "/notes/1404/",
                "/notes/?cohort=prosthesis-1402",
                "/notes/term/?cohort=prosthesis-1402&term=1",
            ]
            for path in pages:
                html = request_html(opener, base_url + path)
                if "<html" not in html.lower():
                    raise RuntimeError(f"Unexpected HTML response for {path}")
        finally:
            process.terminate()
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait(timeout=5)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--project-root", required=True)
    parser.add_argument("--owner-student-number", required=True)
    parser.add_argument("--owner-password", required=True)
    args = parser.parse_args()

    last_error: Exception | None = None
    for _attempt in range(3):
        try:
            run_smoke_session(args)
            print("OK: multi-cohort shared-routes smoke test passed.")
            return 0
        except Exception as exc:
            last_error = exc
            time.sleep(0.35)

    assert last_error is not None
    raise last_error


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        raise
