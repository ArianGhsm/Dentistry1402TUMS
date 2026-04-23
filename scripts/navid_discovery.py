#!/usr/bin/env python
"""
Phase-1 discovery helper for Navid login and post-login structure analysis.

This script is interactive-assisted and does not persist credentials.
It stores only discovery artifacts (HTML, screenshots, metadata, storage state).
"""

from __future__ import annotations

import argparse
import base64
import json
import os
import re
import sys
import time
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple
from urllib.parse import urljoin

from playwright.sync_api import Browser, BrowserContext, Page, TimeoutError, sync_playwright

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass


LOGIN_URL_DEFAULT = "https://navid.tums.ac.ir/account/loginsipadservice"
DEFAULT_SEED_COURSE_URL = "https://navid.tums.ac.ir/coursetemplate/details/24948/1/"
ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / ".codex-local" / "navid-discovery"
COURSE_URL_RE = re.compile(r"/coursetemplate/details/\d+/\d+/?", flags=re.IGNORECASE)


def _sanitize_label(value: str) -> str:
    value = value.strip().lower()
    value = re.sub(r"[^a-z0-9._-]+", "-", value)
    value = value.strip("-")
    return value or "run"


def _write_text(path: Path, text: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text, encoding="utf-8")


def _write_json(path: Path, payload: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")


def _launch_browser(headless: bool) -> Tuple[Browser, BrowserContext, Page]:
    # Force direct connection and bypass local/system proxy settings.
    os.environ["HTTP_PROXY"] = ""
    os.environ["HTTPS_PROXY"] = ""
    os.environ["NO_PROXY"] = "*"

    pw = sync_playwright().start()
    browser = pw.chromium.launch(
        channel="chrome",
        headless=headless,
        args=[
            "--proxy-server=direct://",
            "--proxy-bypass-list=*",
            "--no-proxy-server",
            "--ignore-certificate-errors",
            "--disable-dev-shm-usage",
            "--disable-gpu",
        ],
    )
    context = browser.new_context(
        ignore_https_errors=True,
        locale="fa-IR",
        viewport={"width": 1440, "height": 2200},
    )
    page = context.new_page()
    # Attach handle so we can stop playwright cleanly at teardown.
    setattr(browser, "_playwright_handle", pw)
    return browser, context, page


def _close_browser(browser: Browser) -> None:
    pw = getattr(browser, "_playwright_handle", None)
    try:
        browser.close()
    finally:
        if pw:
            pw.stop()


def _collect_meta(page: Page) -> Dict[str, Any]:
    return page.evaluate(
        """
        () => {
          const pick = (node, attrs) => {
            const out = {};
            for (const a of attrs) out[a] = node.getAttribute(a);
            return out;
          };

          const forms = Array.from(document.querySelectorAll("form")).map((f, i) => ({
            index: i,
            id: f.id || null,
            name: f.getAttribute("name"),
            method: f.getAttribute("method"),
            action: f.getAttribute("action"),
          }));

          const inputs = Array.from(document.querySelectorAll("input, textarea, select")).map((el, i) => ({
            index: i,
            tag: el.tagName.toLowerCase(),
            type: el.getAttribute("type"),
            name: el.getAttribute("name"),
            id: el.getAttribute("id"),
            class: el.getAttribute("class"),
            placeholder: el.getAttribute("placeholder"),
            ariaLabel: el.getAttribute("aria-label"),
            labelHint:
              el.getAttribute("aria-label") ||
              el.getAttribute("placeholder") ||
              el.getAttribute("name") ||
              el.getAttribute("id") ||
              "",
          }));

          const buttons = Array.from(
            document.querySelectorAll("button, input[type='submit'], a.btn, a[role='button']")
          ).map((el, i) => ({
            index: i,
            tag: el.tagName.toLowerCase(),
            type: el.getAttribute("type"),
            id: el.getAttribute("id"),
            name: el.getAttribute("name"),
            class: el.getAttribute("class"),
            text: (el.textContent || "").trim(),
            value: el.getAttribute("value"),
          }));

          const images = Array.from(document.querySelectorAll("img")).slice(0, 80).map((el, i) => ({
            index: i,
            ...pick(el, ["id", "name", "class", "src", "alt", "title"]),
          }));

          const links = Array.from(document.querySelectorAll("a")).slice(0, 500).map((el, i) => ({
            index: i,
            text: (el.textContent || "").trim(),
            href: el.getAttribute("href"),
            class: el.getAttribute("class"),
          })).filter(l => l.text || l.href);

          return {
            url: location.href,
            title: document.title || "",
            forms,
            inputs,
            buttons,
            images,
            links,
          };
        }
        """
    )


def _capture_state(page: Page, run_dir: Path, label: str) -> Dict[str, Path]:
    safe = _sanitize_label(label)
    html_path = run_dir / f"{safe}.html"
    png_path = run_dir / f"{safe}.png"
    json_path = run_dir / f"{safe}.json"

    _write_text(html_path, page.content())
    page.screenshot(path=str(png_path), full_page=True)
    _write_json(json_path, _collect_meta(page))
    return {"html": html_path, "png": png_path, "json": json_path}


def _matches_any(text: str, patterns: List[str]) -> bool:
    for p in patterns:
        if re.search(p, text, flags=re.IGNORECASE):
            return True
    return False


def _find_input_selector(page: Page, patterns: List[str], allowed_types: Optional[List[str]] = None) -> Optional[str]:
    nodes = page.query_selector_all("input")
    for idx, node in enumerate(nodes):
        attrs = " ".join(
            [
                node.get_attribute("name") or "",
                node.get_attribute("id") or "",
                node.get_attribute("placeholder") or "",
                node.get_attribute("aria-label") or "",
                node.get_attribute("class") or "",
            ]
        )
        itype = (node.get_attribute("type") or "").lower()
        if allowed_types and itype and itype not in allowed_types:
            continue
        if _matches_any(attrs, patterns):
            node_id = node.get_attribute("id")
            if node_id:
                return f"#{node_id}"
            node_name = node.get_attribute("name")
            if node_name:
                return f"input[name=\"{node_name}\"]"
            return f"input >> nth={idx}"
    return None


def _find_submit_selector(page: Page) -> Optional[str]:
    candidates = page.query_selector_all("button, input[type='submit'], a.btn, a[role='button']")
    for idx, node in enumerate(candidates):
        text = " ".join(
            [
                node.inner_text() if node else "",
                node.get_attribute("value") or "",
                node.get_attribute("name") or "",
                node.get_attribute("id") or "",
                node.get_attribute("class") or "",
            ]
        )
        if _matches_any(text, [r"login", r"sign", r"submit", r"ورود", r"ادامه"]):
            node_id = node.get_attribute("id")
            if node_id:
                return f"#{node_id}"
            node_class = (node.get_attribute("class") or "").strip().split()
            if node_class:
                return f".{node_class[0]}"
            return f"button >> nth={idx}"
    if candidates:
        return "button, input[type='submit']"
    return None


def _detect_fields(page: Page) -> Dict[str, Optional[str]]:
    return {
        "username": _find_input_selector(
            page,
            [
                r"user",
                r"login",
                r"student",
                r"username",
                r"email",
                r"mobile",
                r"code",
                r"\buid\b",
                r"نام",
                r"کد",
                r"شناسه",
            ],
        ),
        "password": _find_input_selector(page, [r"pass", r"password", r"رمز"], ["password", "text"]),
        "captcha": _find_input_selector(
            page, [r"captcha", r"verify", r"security", r"کپچا", r"تصویر", r"امنیت", r"code"]
        ),
        "submit": _find_submit_selector(page),
    }


def _find_course_like_links(links: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    out = []
    for link in links:
        text = f"{link.get('text', '')} {link.get('href', '')}"
        if re.search(r"course|lesson|class|درس|کلاس|آموزش|training|coursetemplate", text, flags=re.IGNORECASE):
            out.append(link)
    return out


def _find_assignment_like_links(links: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    out = []
    for link in links:
        text = f"{link.get('text', '')} {link.get('href', '')}"
        if re.search(r"assignment|task|homework|تکلیف|تمرین|وظیفه", text, flags=re.IGNORECASE):
            out.append(link)
    return out


def _extract_course_urls_from_links(links: List[Dict[str, Any]], base_url: str) -> List[str]:
    urls: List[str] = []
    for link in links:
        href = (link.get("href") or "").strip()
        if not href:
            continue
        full = urljoin(base_url, href)
        if COURSE_URL_RE.search(full):
            urls.append(full)

    unique: List[str] = []
    seen = set()
    for url in urls:
        if url in seen:
            continue
        seen.add(url)
        unique.append(url)
    return unique


def _extract_assignment_signals_from_body(body_text: str) -> Dict[str, Any]:
    text = re.sub(r"\s+", " ", body_text or "").strip()
    empty_markers = [
        "اطلاعاتی یافت نشد",
        "تکلیفی یافت نشد",
        "موردی یافت نشد",
        "داده ای یافت نشد",
        "داده‌ای یافت نشد",
    ]
    found_empty = [m for m in empty_markers if m in text]

    date_hits = re.findall(r"(?:\d{4}/\d{1,2}/\d{1,2}|[۰-۹]{4}/[۰-۹]{1,2}/[۰-۹]{1,2})", text)

    assignment_keywords = [
        "تکلیف",
        "تمرین",
        "مهلت",
        "تحویل",
        "assignment",
        "homework",
        "deadline",
    ]
    has_assignment_keyword = any(k in text.lower() for k in [k.lower() for k in assignment_keywords])

    return {
        "empty_markers_found": found_empty,
        "date_hits": date_hits[:20],
        "has_assignment_keyword": has_assignment_keyword,
    }


def _collect_assignment_snippets(page: Page, limit: int = 80) -> List[str]:
    snippets = page.evaluate(
        f"""
        () => {{
          const out = [];
          const seen = new Set();
          const keywords = [/تکلیف/, /تمرین/, /مهلت/, /تحویل/, /assignment/i, /homework/i, /deadline/i];
          const nodes = document.querySelectorAll('h1,h2,h3,h4,h5,p,span,div,li,a,td,th');
          for (const n of nodes) {{
            let t = (n.textContent || '').replace(/\\s+/g, ' ').trim();
            if (!t || t.length < 2 || t.length > 180) continue;
            if (!keywords.some((k) => k.test(t))) continue;
            if (seen.has(t)) continue;
            seen.add(t);
            out.push(t);
            if (out.length >= {limit}) break;
          }}
          return out;
        }}
        """
    )
    return snippets if isinstance(snippets, list) else []


def _click_assignment_tab_if_present(page: Page) -> bool:
    selectors = [
        "a:has-text('تکالیف')",
        "button:has-text('تکالیف')",
        "[role='tab']:has-text('تکالیف')",
        "text=تکالیف",
    ]
    for selector in selectors:
        try:
            loc = page.locator(selector).first
            if loc.count() < 1:
                continue
            loc.click(timeout=3000)
            page.wait_for_timeout(2500)
            return True
        except Exception:
            continue
    return False


def _analyze_course_page(page: Page, run_dir: Path, course_url: str, index: int) -> Dict[str, Any]:
    page.goto(course_url, wait_until="domcontentloaded", timeout=90000)
    page.wait_for_timeout(4000)
    overview_state = _capture_state(page, run_dir, f"course-{index:02d}-overview")
    overview_meta = _collect_meta(page)

    clicked_assignment_tab = _click_assignment_tab_if_present(page)
    assignment_state = _capture_state(page, run_dir, f"course-{index:02d}-assignments")

    body_text = page.inner_text("body")
    assignment_signals = _extract_assignment_signals_from_body(body_text)
    snippets = _collect_assignment_snippets(page, limit=120)

    return {
        "course_url": course_url,
        "final_url": page.url,
        "title": page.title(),
        "clicked_assignment_tab": clicked_assignment_tab,
        "assignment_signals": assignment_signals,
        "assignment_snippets_count": len(snippets),
        "assignment_snippets_sample": snippets[:30],
        "course_links_on_page": _extract_course_urls_from_links(overview_meta["links"], page.url),
        "artifacts": {
            "overview_png": str(overview_state["png"]),
            "overview_meta": str(overview_state["json"]),
            "assignment_png": str(assignment_state["png"]),
            "assignment_meta": str(assignment_state["json"]),
        },
    }


def _wait_for_manual_login(page: Page, timeout_sec: int) -> bool:
    started = time.time()
    while time.time() - started < timeout_sec:
        url = (page.url or "").lower()
        if "loginsipadservice" not in url and "account/index" not in url:
            return True

        # If the portal redirected to dashboard root while still containing account path.
        if "navid.tums.ac.ir" in url and (
            "/coursetemplate/" in url
            or "/dashboard" in url
            or "/home" in url
            or "/lms" in url
            or "/course" in url
        ):
            return True

        page.wait_for_timeout(1200)
    return False


def _run_post_login_discovery(
    page: Page,
    context: BrowserContext,
    run_dir: Path,
    seed_course_url: Optional[str],
    max_courses: int,
) -> Dict[str, Any]:
    post_landing = _capture_state(page, run_dir, "02-post-login-landing")
    post_meta = _collect_meta(page)

    storage_state_path = run_dir / "storage-state.json"
    context.storage_state(path=str(storage_state_path))

    course_like_links = _find_course_like_links(post_meta["links"])
    assignment_like_links = _find_assignment_like_links(post_meta["links"])
    course_urls = _extract_course_urls_from_links(post_meta["links"], page.url)

    if seed_course_url:
        seed = seed_course_url.strip()
        if seed and seed not in course_urls:
            course_urls.insert(0, seed)

    analyzed_courses: List[Dict[str, Any]] = []
    for idx, course_url in enumerate(course_urls[:max_courses], start=1):
        try:
            analyzed_courses.append(_analyze_course_page(page, run_dir, course_url, idx))
        except Exception as exc:
            analyzed_courses.append(
                {
                    "course_url": course_url,
                    "error": f"{type(exc).__name__}: {exc}",
                }
            )

    _write_json(run_dir / "course-links.json", course_like_links)
    _write_json(run_dir / "assignment-links.json", assignment_like_links)
    _write_json(run_dir / "courses-analyzed.json", analyzed_courses)

    return {
        "url_after_login": page.url,
        "title_after_login": page.title(),
        "course_like_links_count": len(course_like_links),
        "assignment_like_links_count": len(assignment_like_links),
        "detected_course_urls_count": len(course_urls),
        "analyzed_courses_count": len(analyzed_courses),
        "analyzed_courses": analyzed_courses,
        "artifacts": {
            "post_landing_png": str(post_landing["png"]),
            "post_landing_meta": str(post_landing["json"]),
            "storage_state": str(storage_state_path),
        },
    }


def _fill_if_found(page: Page, selector: Optional[str], value: str) -> bool:
    if not selector:
        return False
    try:
        loc = page.locator(selector).first
        loc.fill(value)
        return True
    except Exception:
        return False


def _click_if_found(page: Page, selector: Optional[str]) -> bool:
    if not selector:
        return False
    try:
        page.locator(selector).first.click()
        return True
    except Exception:
        return False


def _normalize_captcha_text(raw: str) -> str:
    text = (raw or "").strip().upper()
    text = re.sub(r"[^A-Z0-9]", "", text)
    return text


def _extract_captcha_bytes(page: Page) -> Optional[bytes]:
    img = page.locator("img[alt='captcha-img']").first
    if img.count() < 1:
        return None
    src = img.get_attribute("src") or ""
    if src.startswith("data:image"):
        _, b64 = src.split(",", 1)
        return base64.b64decode(b64)
    try:
        return img.screenshot()
    except Exception:
        pass
    if src:
        full = urljoin(page.url, src)
        res = page.request.get(full, timeout=20000)
        if res.ok:
            return res.body()
    return None


def _solve_captcha_with_ocr(page: Page, run_dir: Path, attempt: int) -> Optional[str]:
    try:
        import ddddocr  # type: ignore
    except Exception:
        return None

    image_bytes = _extract_captcha_bytes(page)
    if not image_bytes:
        return None

    img_path = run_dir / f"captcha-attempt-{attempt}.png"
    img_path.write_bytes(image_bytes)

    ocr = ddddocr.DdddOcr(show_ad=False)
    raw = ocr.classification(image_bytes)
    text = _normalize_captcha_text(raw)
    if len(text) < 4:
        return None
    _write_text(run_dir / f"captcha-attempt-{attempt}.txt", text)
    return text


def run_discovery(
    login_url: str,
    username: Optional[str],
    password: Optional[str],
    captcha: Optional[str],
    headless: bool,
    manual_login: bool,
    manual_timeout_sec: int,
    seed_course_url: Optional[str],
    max_courses: int,
) -> int:
    run_id = time.strftime("%Y%m%d-%H%M%S")
    run_dir = OUT_DIR / run_id
    run_dir.mkdir(parents=True, exist_ok=True)

    browser, context, page = _launch_browser(headless=headless)
    try:
        page.goto(login_url, wait_until="domcontentloaded", timeout=90000)
        # Give the anti-bot/login shell time to render.
        page.wait_for_timeout(6000)
        pre = _capture_state(page, run_dir, "01-login-page")

        fields = _detect_fields(page)
        field_report = {
            "username_found": bool(fields["username"]),
            "password_found": bool(fields["password"]),
            "captcha_found": bool(fields["captcha"]),
            "submit_found": bool(fields["submit"]),
            "selectors": fields,
        }
        _write_json(run_dir / "field-detection.json", field_report)

        if manual_login:
            print("Manual-assisted mode: complete login in the opened browser window.")
            print(f"Waiting up to {manual_timeout_sec} seconds for successful login...")
            if not _wait_for_manual_login(page, manual_timeout_sec):
                print("Manual login timeout reached.")
                _capture_state(page, run_dir, "02-manual-login-timeout")
                print(f"Artifacts: {run_dir}")
                return 7

            post_summary = _run_post_login_discovery(
                page=page,
                context=context,
                run_dir=run_dir,
                seed_course_url=seed_course_url,
                max_courses=max_courses,
            )
            summary = {
                "mode": "manual-assisted",
                "login_success_guess": True,
                "run_dir": str(run_dir),
                **post_summary,
            }
            _write_json(run_dir / "summary.json", summary)
            print(json.dumps(summary, ensure_ascii=False, indent=2))
            return 0

        if not (username and password):
            print("Discovery captured without login attempt.")
            print(f"Artifacts: {run_dir}")
            print(f"Login screenshot: {pre['png']}")
            print(f"Field detection: {run_dir / 'field-detection.json'}")
            return 0

        if not all([fields["username"], fields["password"], fields["captcha"], fields["submit"]]):
            print("Cannot attempt login; required fields not detected reliably.")
            print(f"Artifacts: {run_dir}")
            return 2

        max_attempts = 5
        last_captcha = ""
        for attempt in range(1, max_attempts + 1):
            if attempt > 1:
                retry_btn = page.locator(".user-login-captcha-retry").first
                if retry_btn.count() > 0:
                    retry_btn.click()
                    page.wait_for_timeout(1500)

            captcha_value = captcha or _solve_captcha_with_ocr(page, run_dir, attempt)
            if not captcha_value:
                print("Unable to solve captcha automatically.")
                print(f"Artifacts: {run_dir}")
                return 3
            last_captcha = captcha_value

            ok_user = _fill_if_found(page, fields["username"], username)
            ok_pass = _fill_if_found(page, fields["password"], password)
            ok_capt = _fill_if_found(page, fields["captcha"], captcha_value)
            if not (ok_user and ok_pass and ok_capt):
                print("Unable to fill one or more login fields.")
                print(f"Artifacts: {run_dir}")
                return 4

            before_url = page.url
            clicked = _click_if_found(page, fields["submit"])
            if not clicked:
                print("Unable to click submit control.")
                print(f"Artifacts: {run_dir}")
                return 5

            try:
                page.wait_for_load_state("domcontentloaded", timeout=20000)
            except TimeoutError:
                pass
            page.wait_for_timeout(5000)

            # Success heuristic: left login URL or page has course-like links.
            post_meta_try = _collect_meta(page)
            post_course_like = _find_course_like_links(post_meta_try["links"])
            if "loginsipadservice" not in (page.url or "").lower() or len(post_course_like) > 0:
                break
        else:
            print("Login attempts exhausted (captcha likely failed).")
            print(f"Last captcha guess: {last_captcha}")
            print(f"Artifacts: {run_dir}")
            _capture_state(page, run_dir, "02-login-failed")
            return 6

        post_summary = _run_post_login_discovery(
            page=page,
            context=context,
            run_dir=run_dir,
            seed_course_url=seed_course_url,
            max_courses=max_courses,
        )
        login_success_guess = "loginsipadservice" not in (page.url or "").lower()
        summary = {
            "mode": "auto-credentials+ocr",
            "login_success_guess": login_success_guess,
            "run_dir": str(run_dir),
            "pre_login_png": str(pre["png"]),
            **post_summary,
        }
        _write_json(run_dir / "summary.json", summary)
        print(json.dumps(summary, ensure_ascii=False, indent=2))
        return 0
    finally:
        _close_browser(browser)


def main() -> int:
    parser = argparse.ArgumentParser(description="Navid phase-1 discovery helper")
    parser.add_argument("--login-url", default=LOGIN_URL_DEFAULT)
    parser.add_argument("--username")
    parser.add_argument("--password")
    parser.add_argument("--captcha")
    parser.add_argument("--headed", action="store_true", help="Run with visible browser window.")
    parser.add_argument(
        "--manual-login",
        action="store_true",
        help="Wait for human-assisted login in opened browser, then continue discovery.",
    )
    parser.add_argument(
        "--manual-timeout-sec",
        type=int,
        default=240,
        help="Timeout for manual-assisted login wait.",
    )
    parser.add_argument(
        "--seed-course-url",
        default=DEFAULT_SEED_COURSE_URL,
        help="Known course URL to inspect when dashboard course discovery is sparse.",
    )
    parser.add_argument(
        "--max-courses",
        type=int,
        default=3,
        help="How many course pages to inspect after login.",
    )
    args = parser.parse_args()

    return run_discovery(
        login_url=args.login_url,
        username=args.username,
        password=args.password,
        captcha=args.captcha,
        headless=not args.headed,
        manual_login=args.manual_login,
        manual_timeout_sec=args.manual_timeout_sec,
        seed_course_url=args.seed_course_url,
        max_courses=args.max_courses,
    )


if __name__ == "__main__":
    sys.exit(main())
