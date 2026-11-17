#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Convert the owner-supplied 44-question diagnostic midterm answer transcript.
Do not infer a key for ambiguous questions or fabricate missing scan images.
Reproducible conversion into the existing exam-quiz answer-details schema.
"""
from __future__ import annotations

import argparse
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SOURCE = ROOT / "scripts/data/diagnostics3_midterm_44_source.txt"
OUTPUT = ROOT / "public_html/api/data/diagnostics3_midterm_sample/midterm-44.json"
NON_SCORABLE = {
    23: "گزینه‌های ۲ و ۳ یکسان‌اند و سؤال پاسخ علمی یکتا ندارد.",
    24: "منبع، امکان دفاع از بیش از یک گزینه را مطرح کرده است؛ برای جلوگیری از نمره‌دهی ناعادلانه کنار گذاشته شد.",
    30: "زوج ژنی استاندارد در گزینه‌ها وجود ندارد؛ پاسخ علمی قطعی ممکن نیست.",
    39: "این سؤال به عکس اسکن اصلی وابسته است و تصویر در فایل متنی پیوست موجود نیست.",
}
PERSIAN = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")
FA_DIGITS = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")


def norm_number(value: str) -> int:
    return int(value.translate(PERSIAN))


def text_after(block: str, label: str, next_labels: list[str]) -> str:
    match = re.search(r"(?m)^" + re.escape(label) + r"\s*", block)
    if not match:
        return ""
    remaining = block[match.end():]
    positions = [remaining.find("\n" + item) for item in next_labels]
    positions = [x for x in positions if x >= 0]
    return remaining[:min(positions) if positions else len(remaining)].strip()


def single_line(block: str, label: str) -> str:
    match = re.search(r"(?m)^" + re.escape(label) + r"\s*(.*)$", block)
    return match.group(1).strip() if match else ""


def build(source: str) -> list[dict]:
    sections = re.findall(
        r"(?ms)^سؤال ([۰-۹]+) \| صفحهٔ ([۰-۹]+) PDF اسکن‌شده\n(.*?)(?=^═{25,}|\Z)",
        source,
    )
    if len(sections) != 44:
        raise ValueError(f"Expected 44 source sections, got {len(sections)}")
    output: list[dict] = []
    for expected, (raw_number, page, block) in enumerate(sections, 1):
        n = norm_number(raw_number)
        if n != expected or norm_number(page) != n:
            raise ValueError(f"Invalid numbering/order at {n}")
        opt_match = list(re.finditer(r"(?m)^  ([۱-۴])\)\s*(.*)$", block))
        rat_match = list(re.finditer(r"(?m)^  گزینهٔ ([۱-۴]):\s*(.*)$", block))
        if len(opt_match) != 4 or len(rat_match) != 4:
            raise ValueError(f"Q{n}: missing options/rationales")
        options = [m.group(2).strip() for m in opt_match]
        rationales = [m.group(2).strip() for m in rat_match]
        if [norm_number(m.group(1)) for m in opt_match] != [1, 2, 3, 4]:
            raise ValueError(f"Q{n}: wrong option numbering")
        if [norm_number(m.group(1)) for m in rat_match] != [1, 2, 3, 4]:
            raise ValueError(f"Q{n}: wrong rationale numbering")
        question = block[:opt_match[0].start()].strip()
        if n == 39:
            question += " (تصویر اسکن در فایل متنی موجود نیست؛ سؤال فقط برای مرور نمایش داده می‌شود.)"
        scan = single_line(block, "گزینه علامت زده شده در فایل اسکن شده نمونه سوالات:")
        answer = single_line(block, "پاسخ علمی مستقل:")
        comparison = single_line(block, "مقایسه با اسکن:")
        if not scan or not answer or not comparison:
            raise ValueError(f"Q{n}: original scan/independent answer missing")
        scan_match = re.search(r"گزینهٔ ([۱-۴])", scan)
        if not scan_match:
            raise ValueError(f"Q{n}: original scan key missing")
        scan_index = norm_number(scan_match.group(1)) - 1
        scientific_match = re.match(r"([۱-۴])(?:\b|\s|$)", answer)
        scientific_index = norm_number(scientific_match.group(1)) - 1 if scientific_match else None
        if n not in (23, 30) and scientific_index is None:
            raise ValueError(f"Q{n}: independent scientific key missing")
        if n in NON_SCORABLE:
            correct_index = None
        else:
            correct_index = scientific_index

        explanation = text_after(block, "توضیح تشریحی اصلی:", ["بررسی چهار گزینه:"])
        revision = text_after(block, "مرور سریع آموزشی / نکات امتحانی:", [
            "رفرنس درس و جزوه:", "نکتهٔ انتقادی / اختلاف / محدودیت پاسخ:", "منبع تکمیلی خارج از جزوه‌ها",
        ])
        references = text_after(block, "رفرنس درس و جزوه:", [
            "نکتهٔ انتقادی / اختلاف / محدودیت پاسخ:", "منبع تکمیلی خارج از جزوه‌ها",
        ])
        critique = text_after(block, "نکتهٔ انتقادی / اختلاف / محدودیت پاسخ:", [
            "منبع تکمیلی خارج از جزوه‌ها",
        ])
        supplemental = text_after(block, "منبع تکمیلی خارج از جزوه‌ها (برای حل ابهام یا پوشش مطلبِ فاقد منبع داخلی):", [])
        if len(question) < 10 or not all(options) or not all(rationales) or len(explanation) < 10 or not references:
            raise ValueError(f"Q{n}: incomplete content")

        notes = re.findall(r"(?m)^(?:توجه منبعی پیش از پاسخ:|توجه پیش از پاسخ:)\s*(.*)$", block)
        warnings = list(notes)
        if n in NON_SCORABLE:
            warnings.insert(0, "این سؤال در نمره محاسبه نمی‌شود: " + NON_SCORABLE[n])
        if critique:
            warnings.append(critique)

        status = "بدون پاسخ یکتا" if scientific_index is None else "گزینهٔ " + str(scientific_index+1).translate(FA_DIGITS)
        if n in NON_SCORABLE:
            status += " — بدون نمره"
        agreement = "مطابق" if scientific_index == scan_index else "مغایر"
        if scientific_index is None:
            agreement = "قابل تعیین نیست"

        meta = [
            {"label": "گزینهٔ علامت‌خورده در اسکن", "value": scan, "tone": "info"},
            {"label": "پاسخ بررسی مستقل", "value": answer + (" — سؤال بدون نمره" if n in NON_SCORABLE else ""), "tone": "warning" if n in NON_SCORABLE else "success"},
            {"label": "تطبیق با اسکن", "value": comparison, "tone": "warning" if agreement != "مطابق" else "info"},
            {"label": "منابع جزوه و جلسه", "value": references, "tone": "info", "wide": True},
        ]
        sections = [
            {"label": "توضیح تشریحی مستقل", "value": explanation, "tone": "success" if n not in NON_SCORABLE else "warning"},
            {"label": "مرور سریع آموزشی", "value": revision, "tone": "info"},
        ]
        if warnings:
            sections.append({"label": "محدودیت و نکتهٔ انتقادی", "value": "\n".join(warnings), "tone": "warning"})
        if supplemental:
            sections.append({"label": "منبع تکمیلی خارج از جزوه‌ها", "value": supplemental, "tone": "info"})

        question_obj = {
            "number": n,
            "topic": "نمونه‌سؤالات میان‌ترم تشخیصی ۳",
            "question": question,
            "options": options,
            "correctIndex": correct_index,
            "answerMeta": meta,
            "answerSections": sections,
            "optionRationales": rationales,
            "explanation": explanation,
            "reference": references,
        }
        output.append(question_obj)
    if [q["number"] for q in output] != list(range(1, 45)):
        raise ValueError("Non-contiguous question numbers")
    if sum(q["correctIndex"] is not None for q in output) != 40:
        raise ValueError("Scored question count mismatch")
    return output


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true")
    args = parser.parse_args()
    text = SOURCE.read_text(encoding="utf-8-sig")
    questions = build(text)
    content = json.dumps(questions, ensure_ascii=False, indent=2) + "\n"
    if args.check:
        if not OUTPUT.exists() or OUTPUT.read_text(encoding="utf-8") != content:
            raise ValueError("Generated sample data differs from checked-in JSON")
    else:
        OUTPUT.parent.mkdir(parents=True, exist_ok=True)
        OUTPUT.write_text(content, encoding="utf-8")
    print(f"PASS: {len(questions)} questions, 40 scored, 4 review-only: {sorted(NON_SCORABLE)}")
    print(f"PASS: {sum(bool(q['reference']) for q in questions)} cited, {sum(len(q['optionRationales']) == 4 for q in questions)} complete option analyses")


if __name__ == "__main__":
    main()
