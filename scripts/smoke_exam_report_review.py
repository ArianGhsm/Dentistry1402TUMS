#!/usr/bin/env python3
import os
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request


def find_free_port() -> int:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
        sock.bind(("127.0.0.1", 0))
        return int(sock.getsockname()[1])


def wait_for_server(base_url: str, timeout: float = 15.0) -> None:
    deadline = time.time() + timeout
    while time.time() < deadline:
        try:
            with urllib.request.urlopen(base_url + "/", timeout=5) as response:
                if response.status < 500:
                    return
        except Exception:
            time.sleep(0.2)
    raise RuntimeError("Local PHP server did not start in time.")


def request_text(url: str) -> str:
    request = urllib.request.Request(url, headers={"Connection": "close"})
    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            if response.status != 200:
                raise RuntimeError(f"Unexpected status {response.status} for {url}")
            return response.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"HTTP {exc.code} for {url}: {body}") from exc


def assert_contains(text: str, needle: str, label: str) -> None:
    if needle not in text:
        raise AssertionError(f"Missing {label}: {needle}")


def main() -> int:
    project_root = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
    public_root = os.path.join(project_root, "public_html")
    if not os.path.isdir(public_root):
        raise RuntimeError(f"public_html not found: {public_root}")

    port = find_free_port()
    base_url = f"http://127.0.0.1:{port}"
    log_dir = os.path.join(project_root, ".codex-local")
    os.makedirs(log_dir, exist_ok=True)
    log_path = os.path.join(log_dir, "smoke_exam_report_review.log")

    with open(log_path, "w", encoding="utf-8") as log_handle:
        process = subprocess.Popen(
            ["php", "-S", f"127.0.0.1:{port}", "-t", public_root],
            cwd=project_root,
            stdout=log_handle,
            stderr=log_handle,
        )

        try:
            wait_for_server(base_url)

            assessment_html = request_text(base_url + "/exams/systemicdiseases/1/?mode=assessment")
            learning_html = request_text(base_url + "/exams/systemicdiseases/1/?mode=learning")
            exams_home_html = request_text(base_url + "/exams/")
            exams_course_html = request_text(base_url + "/exams/systemicdiseases/")
            quiz_js = request_text(base_url + "/assets/site/scripts/exam-quiz.js")
            bootstrap_js = request_text(base_url + "/assets/site/scripts/exam-bootstrap.js")
            exams_home_js = request_text(base_url + "/assets/site/scripts/exams-home.js")
            exams_course_js = request_text(base_url + "/assets/site/scripts/exams-course.js")
            quiz_css = request_text(base_url + "/assets/site/styles/exam-quiz.css")

            assert_contains(assessment_html, "data-exam-app", "assessment app root")
            assert_contains(assessment_html, "/assets/site/scripts/exam-bootstrap.js", "assessment bootstrap asset")
            assert_contains(learning_html, "data-exam-app", "learning app root")
            assert_contains(exams_home_html, "exams-home-root", "exams home root")
            assert_contains(exams_course_html, "exams-course-root", "exams course root")

            assert_contains(quiz_js, "renderAssessmentReportDashboard", "report dashboard renderer")
            assert_contains(quiz_js, "review-filter-focus", "review shortcut action")
            assert_contains(quiz_js, "confirm-dialog-action", "confirm modal action")
            assert_contains(quiz_js, "exam-busy-overlay", "busy overlay renderer")
            assert_contains(quiz_js, "exam-toast-stack", "toast renderer")
            assert_contains(quiz_js, "getAssessmentDerived", "assessment derived cache")
            assert_contains(quiz_js, "flushPendingStatePersistence", "state persistence flush")
            assert_contains(quiz_js, "fetchWithTimeout", "quiz timeout fetch")

            assert_contains(bootstrap_js, "data-exam-bootstrap-action='retry'", "bootstrap retry action")
            assert_contains(bootstrap_js, "fetchWithTimeout", "bootstrap timeout fetch")

            assert_contains(exams_home_js, "fetchWithTimeout", "exams home timeout fetch")
            assert_contains(exams_course_js, "buildVisibleSessionCollection", "course session collection cache")
            assert_contains(exams_course_js, "renderAsyncState", "course async state rendering")
            assert_contains(exams_course_js, "SEARCH_RENDER_DEBOUNCE_MS", "course search debounce")

            assert_contains(quiz_css, ".exam-report-summary-card", "report summary card styles")
            assert_contains(quiz_css, ".exam-report-breakdown__grid", "report breakdown styles")
            assert_contains(quiz_css, ".exam-confirm-dialog", "confirm dialog styles")
            assert_contains(quiz_css, ".exam-toast-stack", "toast styles")

            print("OK: exam report/review smoke passed.")
            return 0
        finally:
            process.terminate()
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait(timeout=5)


if __name__ == "__main__":
    raise SystemExit(main())
