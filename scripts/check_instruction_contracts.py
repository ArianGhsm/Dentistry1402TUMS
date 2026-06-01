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

    return issues


def check_upload_stream_contracts(repo_root: Path) -> list[tuple[Path, int, str, str]]:
    issues: list[tuple[Path, int, str, str]] = []
    notes_api = repo_root / "public_html" / "api" / "notes_api.php"
    notes_text = read_text(notes_api)
    if "php://input" not in notes_text or "notes_download_host_upload_stream(" not in notes_text:
        issues.append(issue(notes_api.relative_to(repo_root), 0, "missing-notes-stream-upload", "notes upload path must preserve direct stream upload support"))

    content_api = repo_root / "public_html" / "api" / "content_tools_api.php"
    content_text = read_text(content_api)
    if "php://input" not in content_text or "content_download_host_upload_stream(" not in content_text:
        issues.append(issue(content_api.relative_to(repo_root), 0, "missing-content-stream-upload", "upload center must preserve direct stream upload support"))

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
        check_upload_stream_contracts,
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
