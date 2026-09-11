#!/usr/bin/env python3
"""Fail on suspicious Persian text corruption in user-facing files."""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

TEXT_EXTENSIONS = {
    ".html",
    ".css",
    ".js",
    ".php",
    ".json",
    ".txt",
    ".csv",
    ".md",
    ".webmanifest",
}

SKIP_DIR_NAMES = {".git", "fonts", "icons", "images"}
DEFAULT_TARGETS = ("public_html", "scripts", "AGENTS.md", "DEPLOY.md")
QUESTION_MARK_RE = re.compile(r"\?{4,}")
ARABIC_RE = re.compile(r"[\u0600-\u06FF]")
MOJIBAKE_PAIR_RE = re.compile(r"[\u00D8\u00D9\u00DA\u00DB][\u0080-\u00FF]")
DOUBLE_ENCODED_RE = re.compile(r"[\u00C3\u00C2\u00E2][\u0080-\u00BF]{1,2}")
MOJIBAKE_MARKERS = tuple(chr(codepoint) for codepoint in (0x00D8, 0x00D9, 0x00DA, 0x00DB, 0x00C3, 0x00C2))
BROKEN_FA_TOKEN_RE = re.compile(
    r"(?<!\w)(?:Ú¯ÙØªÚ¯|Ø§Ø±Ø³Ø§|Ø³Ø¬Ø§|Ø±Ø§Ø´|Ú©Ù¾|Ø§Ø·Ø§Ø¹Ø§Øª|Ø¯Ø±Ø§ÙØª|Ø³Ø±Ø±|Ø§Ø¹ØªØ¨Ø±|Ø§Ø¬Ø§|Ø¯Ø¨Ø§Ø±|Ù¾Ø¯Ø§|Ø¸Ø±Ø³Ø¬|Ø®Ø§Ø¯Ø´Ø¯|ØªØºØ±|Ø´Ø§ Ø¶)(?!\w)"
)
BROKEN_FA_PHRASE_RE = re.compile(r"Ø¯Ø± Ø­Ø§(?!Ù„)")
UNSAFE_BIDI_PLAINTEXT_RE = re.compile(r"unicode-bidi\s*:\s*plaintext\b", re.IGNORECASE)
UNSAFE_BIDI_ALLOW_MARKER = "rtl-bidi-allow-plaintext"
MOJIBAKE_ALLOW_MARKER = "text-integrity-allow-mojibake"

# Regression contract for the 2026-07-17 source-copy incident that literally
# deleted U+0641 (Persian/Arabic FEH, «ف») from account UI strings. These tokens
# are intentionally checked only in the canonical account source so stored user
# data, educational content, proper nouns, and unrelated Persian prose are not
# rewritten or rejected.
ACCOUNT_UI_PARTS = ("public_html", "account", "index.html")
PERSIAN_LETTER_CLASS = "\u0600-\u06ff"
ACCOUNT_FEH_DROP_TOKENS = (
    "پروایل",
    "صحه",
    "راموش",
    "سارش",
    "تکلی",
    "تکالی",
    "حذ",
    "رمت",
    "معری",
    "استاده",
    "ارسی",
    "دریات",
    "بلااصله",
    "رتن",
    "هرست",
    "ردی",
    "ازودن",
    "حظ",
    "ضای",
    "اقد",
    "اضاه",
    "پیش‌رض",
)
ACCOUNT_FEH_DROP_TOKEN_RE = re.compile(
    rf"(?<![{PERSIAN_LETTER_CLASS}])(?:"
    + "|".join(re.escape(token) for token in ACCOUNT_FEH_DROP_TOKENS)
    + rf")(?![{PERSIAN_LETTER_CLASS}])"
)
ACCOUNT_FEH_DROP_PHRASES = (
    "نشست عال",
    "ورود با موبایل را عال کن",
    "برای ورودی عال",
    "قط‌خواندنی",
    "رمز علی",
    "وضعیت علی شماره",
    "شماره علی",
    "اعلان وری",
    "قی خرید",
    "پرداخت موق",
    "کارت رم‌ها",
    "یادآور رم‌ها",
    "ید اعلان‌ها",
    "ایل‌سنتر",
)
ACCOUNT_REQUIRED_PERSIAN_ANCHORS = (
    "در صفحه اصلی حساب فقط",
    "رمز عبور خود را فراموش کرده‌اید",
    "پروفایل پیام‌رسان / هویت",
    "فید اعلان‌ها",
    "پرداخت موفق",
    "فایل‌سنتر",
)


def iter_text_files(root: Path) -> list[Path]:
    files: list[Path] = []
    for path in root.rglob("*"):
        if not path.is_file():
            continue
        if any(part in SKIP_DIR_NAMES for part in path.parts):
            continue
        if path.suffix.lower() not in TEXT_EXTENSIONS:
            continue
        files.append(path)
    return files


def looks_like_mojibake(line: str) -> bool:
    if "\ufffd" in line:
        return True

    marker_count = sum(line.count(marker) for marker in MOJIBAKE_MARKERS)
    if marker_count >= 3:
        return True

    if marker_count >= 2 and MOJIBAKE_PAIR_RE.search(line):
        return True

    if not ARABIC_RE.search(line) and DOUBLE_ENCODED_RE.search(line):
        return True

    return False


def is_account_ui_source(path: Path) -> bool:
    return len(path.parts) >= len(ACCOUNT_UI_PARTS) and tuple(path.parts[-3:]) == ACCOUNT_UI_PARTS


def scan_account_persian_contract(path: Path, text: str) -> list[tuple[int, str, str]]:
    if not is_account_ui_source(path):
        return []

    issues: list[tuple[int, str, str]] = []
    for line_no, line in enumerate(text.splitlines(), 1):
        token_match = ACCOUNT_FEH_DROP_TOKEN_RE.search(line)
        if token_match:
            issues.append((line_no, "persian-feh-drop", token_match.group(0)))
        for phrase in ACCOUNT_FEH_DROP_PHRASES:
            phrase_pattern = re.compile(
                rf"(?<![{PERSIAN_LETTER_CLASS}]){re.escape(phrase)}(?![{PERSIAN_LETTER_CLASS}])"
            )
            if phrase_pattern.search(line):
                issues.append((line_no, "persian-feh-drop", phrase))

    for anchor in ACCOUNT_REQUIRED_PERSIAN_ANCHORS:
        if anchor not in text:
            issues.append((0, "missing-persian-ui-anchor", anchor))

    return issues


def scan_file(path: Path) -> list[tuple[int, str, str]]:
    issues: list[tuple[int, str, str]] = []

    try:
        raw = path.read_bytes()
    except OSError as exc:
        issues.append((0, "read-error", str(exc)))
        return issues

    try:
        text = raw.decode("utf-8")
    except UnicodeDecodeError as exc:
        issues.append((0, "invalid-utf8", str(exc)))
        return issues

    issues.extend(scan_account_persian_contract(path, text))

    for line_no, line in enumerate(text.splitlines(), 1):
        if MOJIBAKE_ALLOW_MARKER in line:
            continue
        if QUESTION_MARK_RE.search(line):
            issues.append((line_no, "question-marks", line.strip()))
        elif looks_like_mojibake(line):
            issues.append((line_no, "mojibake", line.strip()))
        elif ARABIC_RE.search(line) and (
            BROKEN_FA_TOKEN_RE.search(line) or BROKEN_FA_PHRASE_RE.search(line)
        ):
            issues.append((line_no, "broken-fa-token", line.strip()))
        elif path.suffix.lower() in {".css", ".html", ".js", ".php"} and UNSAFE_BIDI_PLAINTEXT_RE.search(line):
            if UNSAFE_BIDI_ALLOW_MARKER not in line:
                issues.append((line_no, "unsafe-bidi-plaintext", line.strip()))

    return issues


def resolve_targets(repo_root: Path, raw_targets: list[str]) -> list[Path]:
    if not raw_targets:
        return [repo_root / target for target in DEFAULT_TARGETS]

    targets: list[Path] = []
    for raw in raw_targets:
        candidate = (repo_root / raw).resolve() if not Path(raw).is_absolute() else Path(raw).resolve()
        if not candidate.exists():
            print(f"warn: skipped missing path: {raw}", file=sys.stderr)
            continue
        targets.append(candidate)
    return targets


def main() -> int:
    parser = argparse.ArgumentParser(description="Check UTF-8/Persian text integrity.")
    parser.add_argument(
        "paths",
        nargs="*",
        help="Optional folders/files to scan (default: public_html)",
    )
    args = parser.parse_args()

    repo_root = Path(__file__).resolve().parents[1]
    targets = resolve_targets(repo_root, args.paths)
    if not targets:
        print("No valid paths to scan.", file=sys.stderr)
        return 2

    all_issues: list[tuple[Path, int, str, str]] = []
    checked_files = 0

    for target in targets:
        files = [target] if target.is_file() else iter_text_files(target)
        for path in files:
            checked_files += 1
            for line_no, issue_type, payload in scan_file(path):
                all_issues.append((path, line_no, issue_type, payload))

    if all_issues:
        print("Persian text integrity check failed:")
        for path, line_no, issue_type, payload in all_issues:
            rel = path.resolve().relative_to(repo_root.resolve())
            if line_no > 0:
                print(f"- {rel}:{line_no} [{issue_type}] {payload}")
            else:
                print(f"- {rel} [{issue_type}] {payload}")
        return 1

    print(f"OK: {checked_files} text files are UTF-8 safe and free of suspicious corruption.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
