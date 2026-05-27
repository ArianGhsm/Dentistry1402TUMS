#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import re
from pathlib import Path


SESSION_HEADER_RE = re.compile(r"^---\s*جلسه\s+([0-9۰-۹]+)\s*:\s*(.+?)\s*---$")
ANSWER_SESSION_HEADER_RE = re.compile(r"^---\s*پاسخنامه\s+جلسه\s+([0-9۰-۹]+)\s*:\s*(.+?)\s*---$")
QUESTION_RE = re.compile(r"^([0-9۰-۹]+)\.\s*(.+)$")
ANSWER_RE = re.compile(r"^([0-9۰-۹]+)\.\s*پاسخ\s+درست:\s*گزینه\s+(الف|ب|ج|د)\s*$")
OPTION_PREFIXES = ("الف)", "ب)", "ج)", "د)")
OPTION_INDEX = {"الف": 0, "ب": 1, "ج": 2, "د": 3}
PERSIAN_DIGITS = "۰۱۲۳۴۵۶۷۸۹"
ASCII_DIGITS = "0123456789"
DIGIT_TRANS = str.maketrans(PERSIAN_DIGITS, ASCII_DIGITS)


def digits_to_int(value: str) -> int:
    normalized = value.translate(DIGIT_TRANS)
    numeric = re.sub(r"[^0-9]", "", normalized)
    if not numeric:
        raise ValueError(f"Expected numeric value, got {value!r}")
    return int(numeric)


def to_persian_digits(value: int | str) -> str:
    text = str(value)
    return "".join(PERSIAN_DIGITS[int(ch)] if ch.isdigit() else ch for ch in text)


def clean_inline_text(value: str) -> str:
    return re.sub(r"\s+", " ", value).strip()


def clean_question_text(raw: str) -> str:
    text = clean_inline_text(raw)
    text = re.sub(
        r"^\(\s*جلسه\s+[0-9۰-۹]+\s*:\s*.+?\s*،\s*سؤال\s+[0-9۰-۹]+\s*\)\s*",
        "",
        text,
    )
    return text


def clean_option_text(raw: str) -> str:
    text = clean_inline_text(raw)
    for prefix in OPTION_PREFIXES:
        if text.startswith(prefix):
            return clean_inline_text(text[len(prefix) :])
    return text


def clean_explanation_text(raw_lines: list[str]) -> str:
    text = clean_inline_text(" ".join(line.strip() for line in raw_lines if line.strip()))
    if text.startswith("توضیح:"):
        text = clean_inline_text(text[len("توضیح:") :])
    return text


def parse_source(text: str) -> tuple[list[dict], dict[int, list[dict]]]:
    lines = text.splitlines()
    sessions: list[dict] = []
    answers_by_session: dict[int, list[dict]] = {}

    current_session: dict | None = None
    answer_session_started = False
    session_number = 0
    i = 0
    while i < len(lines):
        stripped = lines[i].strip()
        if not stripped:
            i += 1
            continue

        answer_session_match = ANSWER_SESSION_HEADER_RE.match(stripped)
        if answer_session_match:
            answer_session_started = True
            current_session = None
            session_number = digits_to_int(answer_session_match.group(1))
            answers_by_session.setdefault(session_number, [])
            i += 1
            continue

        if answer_session_started:
            answer_match = ANSWER_RE.match(stripped)
            if not answer_match:
                i += 1
                continue

            session_answers = answers_by_session.setdefault(session_number, [])
            question_number = digits_to_int(answer_match.group(1))
            correct_label = answer_match.group(2)
            explanation_lines: list[str] = []
            i += 1
            while i < len(lines):
                next_stripped = lines[i].strip()
                if not next_stripped:
                    i += 1
                    continue
                if ANSWER_RE.match(next_stripped) or ANSWER_SESSION_HEADER_RE.match(next_stripped):
                    break
                explanation_lines.append(next_stripped)
                i += 1

            session_answers.append(
                {
                    "number": question_number,
                    "correctIndex": OPTION_INDEX[correct_label],
                    "explanation": clean_explanation_text(explanation_lines),
                }
            )
            continue

        session_match = SESSION_HEADER_RE.match(stripped)
        if session_match:
            current_session = {
                "number": digits_to_int(session_match.group(1)),
                "title": clean_inline_text(session_match.group(2)),
                "questions": [],
            }
            sessions.append(current_session)
            i += 1
            continue

        question_match = QUESTION_RE.match(stripped)
        if not current_session or not question_match:
            i += 1
            continue

        question_number = digits_to_int(question_match.group(1))
        question_text = clean_question_text(question_match.group(2))
        options: list[str] = []
        i += 1
        while i < len(lines):
            option_line = lines[i].strip()
            if not option_line:
                i += 1
                continue

            if any(option_line.startswith(prefix) for prefix in OPTION_PREFIXES):
                options.append(clean_option_text(option_line))
                i += 1
                if len(options) == 4:
                    break
                continue

            if options:
                options[-1] = clean_inline_text(options[-1] + " " + option_line)
            else:
                question_text = clean_inline_text(question_text + " " + option_line)
            i += 1

        if len(options) != 4:
            raise RuntimeError(
                f"Expected 4 options for session {current_session['number']} question {question_number}, got {len(options)}"
            )

        current_session["questions"].append(
            {
                "number": question_number,
                "question": question_text,
                "options": options,
            }
        )

    return sessions, answers_by_session


def validate_sessions(sessions: list[dict], answers_by_session: dict[int, list[dict]]) -> None:
    if len(sessions) != 9:
        raise RuntimeError(f"Expected 9 sessions, found {len(sessions)}")

    for session in sessions:
        session_number = int(session["number"])
        questions = session["questions"]
        answers = answers_by_session.get(session_number, [])
        if len(questions) != 40:
            raise RuntimeError(f"Session {session_number} expected 40 questions, found {len(questions)}")
        if len(answers) != 40:
            raise RuntimeError(f"Session {session_number} expected 40 answers, found {len(answers)}")

        question_numbers = [int(item["number"]) for item in questions]
        answer_numbers = [int(item["number"]) for item in answers]
        if question_numbers != list(range(1, 41)):
            raise RuntimeError(f"Session {session_number} question numbering is not 1..40")
        if answer_numbers != list(range(1, 41)):
            raise RuntimeError(f"Session {session_number} answer numbering is not 1..40")


def build_course_payload(sessions: list[dict], answers_by_session: dict[int, list[dict]]) -> dict:
    total_questions = 0
    exams = []

    for session in sessions:
        session_number = int(session["number"])
        session_title = str(session["title"])
        answer_lookup = {
            int(item["number"]): {
                "correctIndex": int(item["correctIndex"]),
                "explanation": str(item["explanation"]),
            }
            for item in answers_by_session[session_number]
        }

        question_payloads = []
        for question in session["questions"]:
            question_number = int(question["number"])
            answer = answer_lookup.get(question_number)
            if answer is None:
                raise RuntimeError(f"Missing answer for session {session_number} question {question_number}")

            question_payloads.append(
                {
                    "question": str(question["question"]),
                    "options": list(question["options"]),
                    "correctIndex": int(answer["correctIndex"]),
                    "explanation": str(answer["explanation"]),
                }
            )

        total_questions += len(question_payloads)
        session_label = f"جلسه {to_persian_digits(session_number)}"
        exams.append(
            {
                "slug": str(session_number),
                "path": f"/exams/morphology/{session_number}/",
                "questionCount": len(question_payloads),
                "label": session_label,
                "title": f"سوالات {session_label} - {session_title}",
                "subtitle": "بعد از انتخاب هر گزینه، به‌صورت خودکار به سوال بعدی می‌روی. در انتها روی «پایان آزمون و مشاهده نتیجه» بزن تا نمره و پاسخ‌های تشریحی را ببینی.",
                "description": f"{to_persian_digits(len(question_payloads))} سوال از مبحث «{session_title}».",
                "eyebrow": f"آزمون تمرینی مورفولوژی | {session_label}",
                "backHref": "/exams/morphology/",
                "backLabel": "بازگشت به فهرست آزمون‌های مورفولوژی",
                "autoAdvance": True,
                "siteTitle": "ورودی ۱۴۰۲ دندانپزشکی تهران",
                "siteSubtitle": "آزمون‌ها",
                "siteBadge": "آزمون تمرینی",
                "footerText": "ورودی ۱۴۰۲ دندانپزشکی تهران",
                "questions": question_payloads,
            }
        )

    return {
        "slug": "morphology",
        "title": "آناتومی و مورفولوژی نظری",
        "shortTitle": "مورفولوژی",
        "badge": "۹ جلسه",
        "cardDescription": "جلسه‌های مورفولوژی را یک‌جا ببین و از همین‌جا وارد هر آزمون شو.",
        "heroTitle": "آزمون‌های آناتومی و مورفولوژی نظری",
        "heroDescription": "این مجموعه برای واحد «آناتومی و مورفولوژی نظری» ترم ۴ آماده شده و ۹ جلسه‌ی ۴۰ سوالی دارد. دسترسی کل درس با یک پرداخت ۲۰ هزار تومانی فعال می‌شود.",
        "path": "/exams/morphology/",
        "paymentTitle": "دسترسی کامل به آزمون‌های مورفولوژی",
        "paymentDescription": "با یک بار پرداخت ۲۰ هزار تومان، همه‌ی ۹ آزمون مورفولوژی برای همین حساب باز می‌شود.",
        "paymentSuccessMessage": "پرداخت شما تایید شد و همه‌ی آزمون‌های مورفولوژی برای این حساب باز شد.",
        "paymentFailureMessage": "پرداخت تایید نشد. در صورت کسر وجه، نتیجه را از همین صفحه دوباره بررسی کنید.",
        "defaultPaymentMode": "paid",
        "defaultAmount": 200000,
        "exams": exams,
        "questionCount": total_questions,
    }


def php_export_course(course: dict) -> str:
    json_payload = json.dumps(course, ensure_ascii=False, indent=2)
    return (
        "<?php\n"
        "declare(strict_types=1);\n\n"
        "function dent_exams_morphology_course(): array\n"
        "{\n"
        "    $json = <<<'JSON'\n"
        f"{json_payload}\n"
        "JSON;\n\n"
        "    $course = json_decode($json, true, 512, JSON_THROW_ON_ERROR);\n"
        "    if (!is_array($course)) {\n"
        "        throw new RuntimeException('Invalid morphology exams course payload.');\n"
        "    }\n\n"
        "    return $course;\n"
        "}\n"
    )


def course_html(version: str) -> str:
    return f"""<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>آزمون‌های مورفولوژی | ورودی ۱۴۰۲ دندانپزشکی تهران</title>
    <meta name="description" content="فهرست جلسه‌های آزمون آناتومی و مورفولوژی نظری ترم ۴.">
    <meta name="theme-color" content="#eef2f7">
    <link rel="manifest" href="/manifest.webmanifest?v={version}">
    <link rel="icon" type="image/png" href="/assets/images/favicon.png?v={version}">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png?v={version}">
    <script src="/assets/site/scripts/theme.js?v={version}"></script>
    <link rel="stylesheet" href="/assets/site/styles/core.css?v={version}">
    <link rel="stylesheet" href="/assets/site/styles/exams.css?v={version}">
    <link rel="stylesheet" href="/assets/site/styles/theme.css?v={version}">
</head>
<body class="exams-page" data-exams-course="morphology">
    <div class="background-overlay" aria-hidden="true"></div>
    <main class="exams-main">
        <section id="exams-course-root" class="exams-stack" aria-live="polite">
            <div class="exams-card exams-loading">در حال بارگذاری این درس...</div>
        </section>
    </main>
    <script src="/assets/site/scripts/auth.js?v={version}"></script>
    <script src="/assets/site/scripts/exams-course.js?v={version}"></script>
    <script src="/assets/site/scripts/pwa.js?v={version}"></script>
    <script src="/assets/site/scripts/shell.js?v={version}"></script>
</body>
</html>
"""


def session_html(version: str, session_number: int, session_title: str) -> str:
    session_label = f"جلسه {to_persian_digits(session_number)}"
    page_title = f"آزمون مورفولوژی - {session_label} ({session_title}) | ورودی ۱۴۰۲ دندانپزشکی"
    page_description = page_title
    return f"""<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{page_title}</title>
    <meta name="description" content="{page_description}">
    <meta name="theme-color" content="#eef2f7">
    <link rel="manifest" href="/manifest.webmanifest?v={version}">
    <link rel="icon" type="image/png" href="/assets/images/favicon.png?v={version}">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png?v={version}">
    <script src="/assets/site/scripts/theme.js?v={version}"></script>
    <link rel="stylesheet" href="/assets/site/styles/core.css?v={version}">
    <link rel="stylesheet" href="/assets/site/styles/content.css?v={version}">
    <link rel="stylesheet" href="/assets/site/styles/theme.css?v={version}">
    <link rel="stylesheet" href="/assets/site/styles/exam-quiz.css?v={version}">
</head>
<body class="quiz-page" data-exams-course="morphology" data-exams-exam="{session_number}">
    <div data-exam-app></div>
    <noscript>
        <main class="exam-main">
            <section class="exam-panel exam-empty-state">
                <h1>برای شرکت در این آزمون باید JavaScript فعال باشد.</h1>
                <p>لطفا JavaScript مرورگر خود را روشن کنید و صفحه را دوباره باز کنید.</p>
                <a class="back-btn" href="/exams/morphology/">بازگشت به فهرست آزمون‌های مورفولوژی</a>
            </section>
        </main>
    </noscript>
    <script src="/assets/site/scripts/auth.js?v={version}"></script>
    <script src="/assets/site/scripts/exam-bootstrap.js?v={version}"></script>
    <script src="/assets/site/scripts/pwa.js?v={version}"></script>
    <script src="/assets/site/scripts/shell.js?v={version}"></script>
</body>
</html>
"""


def write_text(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8", newline="\n")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source", required=True, type=Path)
    parser.add_argument("--php-output", required=True, type=Path)
    parser.add_argument("--course-dir", required=True, type=Path)
    parser.add_argument("--version", default="20260527-173221")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    source_text = args.source.read_text(encoding="utf-8")
    sessions, answers_by_session = parse_source(source_text)
    validate_sessions(sessions, answers_by_session)

    course = build_course_payload(sessions, answers_by_session)
    write_text(args.php_output, php_export_course(course))
    write_text(args.course_dir / "index.html", course_html(args.version))
    for session in sessions:
        session_number = int(session["number"])
        write_text(
            args.course_dir / str(session_number) / "index.html",
            session_html(args.version, session_number, str(session["title"])),
        )

    print(
        json.dumps(
            {
                "sessions": len(sessions),
                "questions": sum(len(session["questions"]) for session in sessions),
                "answers": sum(len(items) for items in answers_by_session.values()),
                "courseSlug": course["slug"],
            },
            ensure_ascii=False,
        )
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
