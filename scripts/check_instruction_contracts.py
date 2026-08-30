#!/usr/bin/env python3
"""Audit a subset of enforceable project instruction contracts."""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path
from urllib.parse import parse_qs

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

TEXT_EXTENSIONS = {".html", ".php", ".css", ".js", ".py", ".ps1", ".md"}
SKIP_DIR_NAMES = {".git", ".codex-local", "fonts", "icons", "images", "node_modules", "vendor", "__pycache__"}

VIEWPORT_RE = re.compile(
    r"""<meta[^>]+name=["']viewport["'][^>]+content=["'](?P<content>[^"']+)["']""",
    re.IGNORECASE,
)
FORBIDDEN_VIEWPORT_RE = re.compile(
    r"""\b(?:maximum-scale\s*=\s*1(?:\.0+)?|user-scalable\s*=\s*no)\b""",
    re.IGNORECASE,
)
UNSAFE_BIDI_PLAINTEXT_RE = re.compile(r"unicode-bidi\s*:\s*plaintext\b", re.IGNORECASE)
UNSAFE_BIDI_ALLOW_MARKER = "rtl-bidi-allow-plaintext"
MARKDOWN_DOC_BIDI_SNIPPET = "`unicode-bidi" + ": plaintext`"
ASSET_REF_RE = re.compile(
    r"""(?P<attr>href|src)\s*=\s*["'](?P<path>/assets/site/(?:styles|scripts)/[^"'?#]+\.(?:css|js))(?P<query>\?[^"']*)?["']""",
    re.IGNORECASE,
)
FORBIDDEN_EXAM_SHELL_RE = re.compile(r"""data-shell(?:-header)?\s*=\s*["']off["']""", re.IGNORECASE)
REQUIRE_RE = re.compile(r"""require(?:_once)?\s+__DIR__\s*\.\s*['"]/(?P<path>[^'"]+)['"]""", re.IGNORECASE)
ALLOWED_EXAMS_CORE_REQUIRES = {
    "exams_api.php",
    "exams_bank.php",
    "exams_bank_helpers.php",
    "exams_catalog_timeline.php",
    "exams_home_highlights.php",
    "exams_home_highlights_index.php",
    "exams_modules.php",
    "exams_store.php",
}


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def iter_files(root: Path, extensions: set[str]) -> list[Path]:
    files: list[Path] = []
    for path in root.rglob("*"):
        if not path.is_file():
            continue
        if any(part in SKIP_DIR_NAMES for part in path.parts):
            continue
        if path.suffix.lower() not in extensions:
            continue
        files.append(path)
    return files


def issue(rel_path: Path, line_no: int, kind: str, detail: str) -> tuple[Path, int, str, str]:
    return (rel_path, line_no, kind, detail)


def line_number_for_offset(text: str, offset: int) -> int:
    return text.count("\n", 0, offset) + 1


def check_viewport_contracts(repo_root: Path) -> list[tuple[Path, int, str, str]]:
    issues: list[tuple[Path, int, str, str]] = []
    for path in iter_files(repo_root / "public_html", {".html", ".php"}):
        text = read_text(path)
        for match in VIEWPORT_RE.finditer(text):
            content = match.group("content")
            if FORBIDDEN_VIEWPORT_RE.search(content):
                issues.append(
                    issue(
                        path.relative_to(repo_root),
                        line_number_for_offset(text, match.start()),
                        "forbidden-viewport-lock",
                        content.strip(),
                    )
                )
    return issues


def check_bidi_contracts(repo_root: Path) -> list[tuple[Path, int, str, str]]:
    issues: list[tuple[Path, int, str, str]] = []
    targets = [repo_root / "public_html", repo_root / "scripts", repo_root / "AGENTS.md", repo_root / "DEPLOY.md"]
    for target in targets:
        files = [target] if target.is_file() else iter_files(target, TEXT_EXTENSIONS)
        for path in files:
            text = read_text(path)
            for line_no, line in enumerate(text.splitlines(), 1):
                if path.suffix.lower() == ".md" and MARKDOWN_DOC_BIDI_SNIPPET in line:
                    continue
                if UNSAFE_BIDI_PLAINTEXT_RE.search(line) and UNSAFE_BIDI_ALLOW_MARKER not in line:
                    issues.append(issue(path.relative_to(repo_root), line_no, "unsafe-bidi-plaintext", line.strip()))
    return issues


def check_asset_versioning(repo_root: Path) -> list[tuple[Path, int, str, str]]:
    issues: list[tuple[Path, int, str, str]] = []
    for path in iter_files(repo_root / "public_html", {".html", ".php"}):
        text = read_text(path)
        for match in ASSET_REF_RE.finditer(text):
            query = match.group("query")
            if query is None:
                issues.append(
                    issue(
                        path.relative_to(repo_root),
                        line_number_for_offset(text, match.start()),
                        "missing-asset-version",
                        match.group("path"),
                    )
                )
                continue
            params = parse_qs(query[1:], keep_blank_values=True)
            if "v" not in params:
                issues.append(
                    issue(
                        path.relative_to(repo_root),
                        line_number_for_offset(text, match.start()),
                        "missing-asset-version-param",
                        match.group(0).strip(),
                    )
                )
    return issues


def check_exam_shell_contracts(repo_root: Path) -> list[tuple[Path, int, str, str]]:
    issues: list[tuple[Path, int, str, str]] = []
    exams_root = repo_root / "public_html" / "exams"
    for path in iter_files(exams_root, {".html", ".php"}):
        text = read_text(path)
        for match in FORBIDDEN_EXAM_SHELL_RE.finditer(text):
            issues.append(
                issue(
                    path.relative_to(repo_root),
                    line_number_for_offset(text, match.start()),
                    "forbidden-exam-shell-disable",
                    match.group(0),
                )
            )
    return issues


def check_exams_registry_contract(repo_root: Path) -> list[tuple[Path, int, str, str]]:
    issues: list[tuple[Path, int, str, str]] = []
    api_root = repo_root / "public_html" / "api"
    for path in sorted(api_root.glob("*.php")):
        text = read_text(path)
        current = path.name
        for match in REQUIRE_RE.finditer(text):
            required = Path(match.group("path")).name
            if not required.startswith("exams_"):
                continue
            if required in ALLOWED_EXAMS_CORE_REQUIRES:
                continue
            if current == "exams_modules.php" and required.endswith("_overrides.php"):
                continue
            if current.startswith("exams_") and current.endswith("_overrides.php") and required.endswith("_data.php"):
                continue
            issues.append(
                issue(
                    path.relative_to(repo_root),
                    line_number_for_offset(text, match.start()),
                    "forbidden-direct-exams-module-require",
                    f"{current} -> {required}",
                )
            )
    return issues


def check_shared_nav_contracts(repo_root: Path) -> list[tuple[Path, int, str, str]]:
    issues: list[tuple[Path, int, str, str]] = []

    shell_path = repo_root / "public_html" / "assets" / "site" / "scripts" / "shell.js"
    shell_text = read_text(shell_path)
    has_exams_label = 'label: "آزمون‌ها"' in shell_text
    has_exams_href = 'sharedNavHref("/exams/", state)' in shell_text or 'href: "/exams/"' in shell_text
    if not has_exams_label or not has_exams_href:
        issues.append(issue(shell_path.relative_to(repo_root), 0, "missing-exams-bottom-nav", "shell.js navItems must expose /exams/."))
    if 'label: "خرید"' in shell_text:
        issues.append(issue(shell_path.relative_to(repo_root), 0, "forbidden-buy-bottom-nav", 'Shared shell nav must not expose "خرید".'))

    guest_nav_match = re.search(r"if \(!canUseChat\) \{(?P<guest>.*?)\}\s*else if", shell_text, re.DOTALL)
    if guest_nav_match is None or guest_nav_match.group("guest").count("items.push(") != 2:
        issues.append(issue(shell_path.relative_to(repo_root), 0, "invalid-guest-nav-count", "Guest/shared bottom nav must contain home plus exactly two branch items."))

    logged_nav_match = re.search(
        r"else if \(!isProsthesis\) \{(?P<dental>.*?)\}\s*else \{(?P<prosthesis>.*?)\}\s*return items;",
        shell_text,
        re.DOTALL,
    )
    if logged_nav_match is None:
        issues.append(issue(shell_path.relative_to(repo_root), 0, "missing-logged-nav-branches", "Unable to verify logged-in bottom-nav order."))
    else:
        for branch_name in ("dental", "prosthesis"):
            branch = logged_nav_match.group(branch_name)
            if branch.count("items.push(") != 2:
                issues.append(issue(shell_path.relative_to(repo_root), 0, "invalid-logged-nav-count", f"{branch_name} bottom nav must contain home plus exactly two branch items."))
            exams_index = branch.find('"/exams/"')
            chat_index = branch.find('"/chat/"')
            if exams_index < 0 or chat_index < 0 or exams_index > chat_index:
                issues.append(issue(shell_path.relative_to(repo_root), 0, "invalid-logged-nav-order", f"{branch_name} nav must place exams before chat in RTL DOM order."))

    chat_path = repo_root / "public_html" / "chat" / "index.html"
    chat_text = read_text(chat_path)
    chat_nav_match = re.search(r"""<nav class="chat-mobile-nav".*?</nav>""", chat_text, re.DOTALL)
    if chat_nav_match is None:
        issues.append(issue(chat_path.relative_to(repo_root), 0, "missing-chat-mobile-nav", "chat mobile nav block not found"))
    else:
        snippet = chat_nav_match.group(0)
        if "/exams/" not in snippet:
            issues.append(issue(chat_path.relative_to(repo_root), 0, "missing-chat-nav-exams", "chat mobile nav must link to /exams/"))
        if "خرید" in snippet or "/buy/" in snippet:
            issues.append(issue(chat_path.relative_to(repo_root), 0, "forbidden-chat-nav-buy", "chat mobile nav must not expose /buy/"))
        item_count = snippet.count('chat-mobile-nav__item')
        home_index = snippet.find('id="chat-nav-home"')
        exams_index = snippet.find('id="chat-nav-exams"')
        chat_index = snippet.find('id="chat-nav-list"')
        if item_count != 3:
            issues.append(issue(chat_path.relative_to(repo_root), 0, "invalid-chat-nav-count", "chat mobile nav must contain exactly three items"))
        if min(home_index, exams_index, chat_index) < 0 or not (home_index < exams_index < chat_index):
            issues.append(issue(chat_path.relative_to(repo_root), 0, "invalid-chat-nav-order", "chat mobile nav DOM order must be home, exams, chat for RTL layout"))

    return issues


def check_home_access_contracts(repo_root: Path) -> list[tuple[Path, int, str, str]]:
    issues: list[tuple[Path, int, str, str]] = []
    home_path = repo_root / "public_html" / "app" / "index.html"
    home_script_path = repo_root / "public_html" / "assets" / "site" / "scripts" / "app-home.js"
    home_text = read_text(home_path)
    home_script_text = read_text(home_script_path)

    quick_nav_match = re.search(r'<nav class="home-quick-actions".*?</nav>', home_text, re.DOTALL)
    if quick_nav_match is None:
        issues.append(issue(home_path.relative_to(repo_root), 0, "missing-home-quick-nav", "Homepage quick navigation is required."))
    else:
        quick_nav = quick_nav_match.group(0)
        if 'data-home-quick="forms"' not in quick_nav:
            issues.append(issue(home_path.relative_to(repo_root), 0, "missing-home-forms-quick-action", "Homepage quick navigation must expose cohort-driven forms."))
        if 'data-home-quick="exams"' in quick_nav:
            issues.append(issue(home_path.relative_to(repo_root), 0, "duplicate-home-exams-quick-action", "Exams belongs in the shared bottom navigation, not the homepage quick row."))

    for forbidden_marker in ("home-resource-section", "home-class-section", "منابع ورودی‌ها", "مسیرهای ورودی فعال"):
        if forbidden_marker in home_text:
            issues.append(issue(home_path.relative_to(repo_root), 0, "forbidden-home-directory-group", forbidden_marker))

    if home_text.count('class="home-service-group"') != 2:
        issues.append(issue(home_path.relative_to(repo_root), 0, "invalid-home-directory-count", "Homepage directory must contain only owner tools and university sites."))
    if "data-owner-only" not in home_text or "ویژه مالک سایت" not in home_text or "سایت‌های دانشگاه" not in home_text:
        issues.append(issue(home_path.relative_to(repo_root), 0, "missing-home-directory-core-group", "Owner-only tools and university sites must remain available."))

    if not re.search(r"forms:\s*\{\s*enabled:\s*services\.forms\s*&&\s*!!routes\.forms", home_script_text):
        issues.append(issue(home_script_path.relative_to(repo_root), 0, "non-cohort-home-forms-route", "Homepage forms shortcut must follow the active cohort service flag and route map."))
    if re.search(r"exams:\s*\{\s*enabled:", home_script_text):
        issues.append(issue(home_script_path.relative_to(repo_root), 0, "duplicate-home-exams-target", "Homepage quick-action targets must not recreate the exams bottom-nav entry."))

    return issues


def check_upload_stream_contracts(repo_root: Path) -> list[tuple[Path, int, str, str]]:
    issues: list[tuple[Path, int, str, str]] = []
    notes_api = repo_root / "public_html" / "api" / "notes_api.php"
    notes_text = read_text(notes_api)
    if "php://input" not in notes_text or "notes_download_host_stream_upload_relay(" not in notes_text:
        issues.append(issue(notes_api.relative_to(repo_root), 0, "missing-notes-stream-upload", "notes upload path must preserve direct stream upload support"))

    content_api = repo_root / "public_html" / "api" / "content_tools_api.php"
    content_text = read_text(content_api)
    if "php://input" not in content_text or "content_download_host_upload_stream(" not in content_text:
        issues.append(issue(content_api.relative_to(repo_root), 0, "missing-content-stream-upload", "upload center must preserve direct stream upload support"))

    return issues


def check_pwa_update_signal_contract(repo_root: Path) -> list[tuple[Path, int, str, str]]:
    """PWA updates stay canonical, silent while applying, and navigation stays bounded."""
    issues: list[tuple[Path, int, str, str]] = []
    pwa_path = repo_root / "public_html" / "assets" / "site" / "scripts" / "pwa.js"
    pwa_text = read_text(pwa_path)
    sw_path = repo_root / "public_html" / "sw.js"
    sw_text = read_text(sw_path)
    shell_path = repo_root / "public_html" / "assets" / "site" / "scripts" / "shell.js"
    shell_text = read_text(shell_path)
    core_path = repo_root / "public_html" / "assets" / "site" / "styles" / "core.css"
    core_text = read_text(core_path)

    canonical_assignment = "state.updateAvailable = normalizedLatest !== state.currentVersion;"
    if canonical_assignment not in pwa_text:
        issues.append(
            issue(
                pwa_path.relative_to(repo_root),
                0,
                "non-canonical-pwa-update-signal",
                "Update visibility must be driven only by the canonical release version mismatch.",
            )
        )

    if re.search(r"state\.updateAvailable\s*=\s*[^;\n]*hasWaitingWorker", pwa_text):
        issues.append(
            issue(
                pwa_path.relative_to(repo_root),
                0,
                "worker-lifecycle-triggers-pwa-banner",
                "An installing/waiting worker may be the same version and must not trigger the update banner.",
            )
        )

    silent_banner = re.search(
        r"function\s+shouldShowUpdateBanner\s*\(\s*\)\s*\{[\s\S]{0,300}?return\s+false\s*;",
        pwa_text,
    )
    auto_apply_call = re.search(r"^\s*maybeAutoApplyUpdate\(\);\s*$", pwa_text, re.MULTILINE)
    if silent_banner is None or auto_apply_call is not None:
        issues.append(
            issue(
                pwa_path.relative_to(repo_root),
                0,
                "pwa-auto-update-banner-flash",
                "The native service-worker lifecycle must remain silent and must not mount or auto-apply a user-facing release banner.",
            )
        )

    if ".dent1402-pwa-update" not in core_text or "display: none !important" not in core_text:
        issues.append(
            issue(
                core_path.relative_to(repo_root),
                0,
                "missing-pwa-banner-kill-switch",
                "core.css must suppress a banner injected by an older cached pwa.js during worker takeover.",
            )
        )

    grace_match = re.search(r"const CACHED_NAVIGATION_GRACE_MS\s*=\s*(\d+)\s*;", sw_text)
    if grace_match is None or int(grace_match.group(1)) > 750:
        issues.append(
            issue(
                sw_path.relative_to(repo_root),
                0,
                "slow-cached-navigation-grace",
                "A healthy cached shell must win within 750ms while network revalidation continues.",
            )
        )

    required_sw_markers = (
        'PWA_RUNTIME_PATH + "?v=" + APP_VERSION',
        'CANONICAL_RUNTIME_PATHS.includes(url.pathname)',
        'currentCanonicalRuntime(request, url.pathname)',
        'fetch(request, { cache: "no-store" })',
        'event.data.type === "WARM_PAGE"',
    )
    for marker in required_sw_markers:
        if marker not in sw_text:
            issues.append(issue(sw_path.relative_to(repo_root), 0, "missing-fast-pwa-runtime-contract", marker))

    if 'worker.postMessage({ type: "WARM_PAGE", url: url });' not in shell_text:
        issues.append(
            issue(
                shell_path.relative_to(repo_root),
                0,
                "missing-bottom-nav-prewarm",
                "Shared bottom-navigation targets must be warmed without blocking the click.",
            )
        )

    return issues


def check_mature_flat_design_contract(repo_root: Path) -> list[tuple[Path, int, str, str]]:
    """Keep the shared anti-card layer active without allowing font drift."""
    issues: list[tuple[Path, int, str, str]] = []
    styles_root = repo_root / "public_html" / "assets" / "site" / "styles"
    theme_path = styles_root / "theme.css"
    flat_path = styles_root / "mature-flat.css"
    htaccess_path = repo_root / "public_html" / ".htaccess"
    expected_theme_version = "20260812-phase4b"
    expected_home_theme_version = "20260812-mobile-foundation3"
    expected_devices_theme_version = "20260812-mobile-foundation3"
    expected_account_theme_version = "20260813-login-refine4"
    expected_exams_theme_version = "20260813-exams-contrast1"
    expected_pilot_version = "20260823-mobile-rail3"
    expected_flat_version = "20260823-mobile-rail3"
    expected_pilot_import = f'redesign-pilot.css?v={expected_pilot_version}'
    expected_import = f'mature-flat.css?v={expected_flat_version}'

    if not flat_path.is_file():
        return [issue(flat_path.relative_to(repo_root), 0, "missing-mature-flat-layer", "Shared anti-card layer is required.")]

    theme_text = read_text(theme_path)
    flat_text = read_text(flat_path)
    if expected_pilot_import not in theme_text:
        issues.append(issue(theme_path.relative_to(repo_root), 0, "missing-redesign-pilot-import", expected_pilot_import))
    if expected_import not in theme_text:
        issues.append(issue(theme_path.relative_to(repo_root), 0, "missing-mature-flat-import", expected_import))
    if re.search(r"\bfont-family\s*:", flat_text, re.IGNORECASE):
        issues.append(
            issue(
                flat_path.relative_to(repo_root),
                0,
                "forbidden-redesign-font-override",
                "mature-flat.css must preserve the approved AbarHigh/YekanBakh mapping.",
            )
        )
    if not re.search(r"--flat-control-radius\s*:\s*8px\s*;", flat_text):
        issues.append(issue(flat_path.relative_to(repo_root), 0, "invalid-flat-control-radius", "Shared controls must remain at 8px."))

    htaccess_text = read_text(htaccess_path)
    if not re.search(r'Header\s+set\s+Cache-Control\s+"public, max-age=31536000, immutable"\s+"expr=%\{QUERY_STRING\}', htaccess_text):
        issues.append(issue(htaccess_path.relative_to(repo_root), 0, "missing-versioned-asset-detection", "Versioned CSS/JS assets must receive their immutable cache contract through mod_headers."))
    if not re.search(r'FilesMatch\s+"\^theme\\\.css\$"', htaccess_text) or "max-age=300, stale-while-revalidate=86400" not in htaccess_text:
        issues.append(issue(htaccess_path.relative_to(repo_root), 0, "missing-shared-theme-short-cache", "theme.css must avoid a blocking revalidation on every route while retaining a short freshness window."))

    sw_path = repo_root / "public_html" / "sw.js"
    sw_text = read_text(sw_path)
    asset_fetch_block = re.search(
        r'request\.destination\s*===\s*"style"[\s\S]{0,300}?request\.destination\s*===\s*"script"[\s\S]{0,800}?event\.respondWith\(staleWhileRevalidate\(request\)\)',
        sw_text,
    )
    if not asset_fetch_block:
        issues.append(issue(sw_path.relative_to(repo_root), 0, "missing-pwa-asset-swr", "Same-origin CSS/JS must use the version-rotated stale-while-revalidate cache."))

    required_markers = (
        ".buy-page .buy-feed > .buy-item-card",
        ".forms-page .forms-toggle",
        ".navid-page .navid-assignment",
        ".content-tools-page .ct-dropzone",
        ".shell-bottom-nav__inner",
    )
    for marker in required_markers:
        if marker not in flat_text:
            issues.append(issue(flat_path.relative_to(repo_root), 0, "missing-anti-card-marker", marker))

    for path in iter_files(repo_root / "public_html", {".html", ".php"}):
        text = read_text(path)
        if "theme.css?v=" not in text:
            continue
        relative_path = path.relative_to(repo_root).as_posix()
        if relative_path == "public_html/app/index.html":
            allowed_theme_version = expected_home_theme_version
        elif relative_path == "public_html/account/devices/index.html":
            allowed_theme_version = expected_devices_theme_version
        elif relative_path == "public_html/account/index.html":
            allowed_theme_version = expected_account_theme_version
        elif relative_path == "public_html/exams/index.html":
            allowed_theme_version = expected_exams_theme_version
        else:
            allowed_theme_version = expected_theme_version
        if f"theme.css?v={allowed_theme_version}" not in text:
            issues.append(
                issue(
                    path.relative_to(repo_root),
                    line_number_for_offset(text, text.find("theme.css?v=")),
                    "stale-shared-theme-version",
                    allowed_theme_version,
                )
            )

    return issues


def main() -> int:
    parser = argparse.ArgumentParser(description="Audit enforceable instruction contracts.")
    parser.parse_args()

    repo_root = Path(__file__).resolve().parents[1]

    checks = [
        check_viewport_contracts,
        check_bidi_contracts,
        check_asset_versioning,
        check_exam_shell_contracts,
        check_exams_registry_contract,
        check_shared_nav_contracts,
        check_home_access_contracts,
        check_upload_stream_contracts,
        check_pwa_update_signal_contract,
        check_mature_flat_design_contract,
    ]

    issues: list[tuple[Path, int, str, str]] = []
    for check in checks:
        issues.extend(check(repo_root))

    if issues:
        print("Instruction contract audit failed:")
        for rel_path, line_no, kind, detail in sorted(issues, key=lambda item: (str(item[0]), item[1], item[2], item[3])):
            location = f"{rel_path}:{line_no}" if line_no > 0 else str(rel_path)
            print(f"- {location} [{kind}] {detail}")
        return 1

    print(f"OK: instruction contract audit passed across {len(checks)} targeted checks.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
