#!/usr/bin/env python3
"""Temporary one-shot repair for the proven 2026-07-17 U+0641 deletion incident.

This script is deliberately scoped to public_html/account/index.html. Every token
below is taken from the exact ca06d157..5d34c753 historical diff where a Persian
letter FEH (ف, U+0641) was deleted from otherwise-correct UI copy. It does not
scan or rewrite stored/user/educational content.
"""
from __future__ import annotations

import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parents[1]
TARGET = ROOT / "public_html" / "account" / "index.html"
PERSIAN_LETTERS = "\u0600-\u06ff"

TOKEN_FIXES = {
    "پروایل": "پروفایل",
    "صحه": "صفحه",
    "قط": "فقط",
    "رم": "فرم",
    "راموش": "فراموش",
    "عال": "فعال",
    "سارش": "سفارش",
    "تکلی": "تکلیف",
    "حذ": "حذف",
    "رمت": "فرمت",
    "معری": "معرفی",
    "استاده": "استفاده",
    "ارسی": "فارسی",
    "ید": "فید",
    "دریات": "دریافت",
    "وری": "فوری",
    "بلااصله": "بلافاصله",
    "رتن": "رفتن",
    "هرست": "فهرست",
    "ردی": "ردیف",
    "ازودن": "افزودن",
    "ایل": "فایل",
    "حظ": "حفظ",
    "ضای": "فضای",
    "اقد": "فاقد",
    "اضاه": "اضافه",
    "پیش‌رض": "پیش‌فرض",
    "قی": "قیف",
    "موق": "موفق",
}

PHRASE_FIXES = {
    "رمز علی": "رمز فعلی",
    "وضعیت علی شماره": "وضعیت فعلی شماره",
    "شماره علی": "شماره فعلی",
}

CRITICAL_BAD = (
    "پروایل",
    "صحه اصلی",
    "رمز عبور خود را راموش",
    "نشست عال",
    "حذ عکس",
    "رمت مجاز",
    "یک معری کوتاه",
    "اعلان وری",
    "هرست اعلان‌ها",
    "پیش‌رض کاربران",
    "قی خرید",
    "پرداخت موق",
)
CRITICAL_GOOD = (
    "پروفایل",
    "صفحه اصلی",
    "فراموش کرده‌اید",
    "نشست فعال",
    "حذف عکس",
    "فرمت مجاز",
    "معرفی کوتاه",
    "اعلان فوری",
    "فهرست اعلان‌ها",
    "پیش‌فرض کاربران",
    "قیف خرید",
    "پرداخت موفق",
)


def token_pattern(token: str) -> re.Pattern[str]:
    return re.compile(
        rf"(?<![{PERSIAN_LETTERS}]){re.escape(token)}(?![{PERSIAN_LETTERS}])"
    )


def replace_token(text: str, bad: str, good: str) -> tuple[str, int]:
    return token_pattern(bad).subn(good, text)


def has_bad_phrase(text: str, phrase: str) -> bool:
    first, separator, rest = phrase.partition(" ")
    if not separator:
        return bool(token_pattern(first).search(text))
    pattern = re.compile(
        rf"(?<![{PERSIAN_LETTERS}]){re.escape(first)}(?![{PERSIAN_LETTERS}])"
        rf"{re.escape(separator + rest)}"
    )
    return bool(pattern.search(text))


def main() -> int:
    original = TARGET.read_text(encoding="utf-8")
    if not all(marker in original for marker in CRITICAL_BAD):
        missing = [marker for marker in CRITICAL_BAD if marker not in original]
        raise SystemExit(
            "Refusing repair: branch does not match the audited corrupted source; "
            f"missing markers: {missing}"
        )

    fixed = original
    counts: dict[str, int] = {}
    for bad, good in PHRASE_FIXES.items():
        count = fixed.count(bad)
        if count:
            fixed = fixed.replace(bad, good)
            counts[bad] = count

    for bad, good in TOKEN_FIXES.items():
        fixed, count = replace_token(fixed, bad, good)
        if count:
            counts[bad] = count

    remaining = [marker for marker in CRITICAL_BAD if has_bad_phrase(fixed, marker)]
    if remaining:
        raise SystemExit(f"Repair incomplete; known corruptions remain: {remaining}")
    missing_good = [marker for marker in CRITICAL_GOOD if marker not in fixed]
    if missing_good:
        raise SystemExit(f"Repair validation failed; expected copy missing: {missing_good}")
    if fixed == original:
        raise SystemExit("Repair made no changes")

    TARGET.write_text(fixed, encoding="utf-8", newline="\n")
    print(f"Repaired {sum(counts.values())} proven corrupted token occurrences")
    for bad in sorted(counts):
        print(f"  {bad!r}: {counts[bad]}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
