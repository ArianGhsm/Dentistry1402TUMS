#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import re
from dataclasses import dataclass, field
from pathlib import Path


PERSIAN_DIGITS = "۰۱۲۳۴۵۶۷۸۹"
ARABIC_DIGITS = "٠١٢٣٤٥٦٧٨٩"
ASCII_DIGITS = "0123456789"
DIGIT_TRANS = str.maketrans(PERSIAN_DIGITS + ARABIC_DIGITS, ASCII_DIGITS + ASCII_DIGITS)
ARABIC_TO_PERSIAN = str.maketrans(
    {
        "ك": "ک",
        "ي": "ی",
        "ى": "ی",
        "ة": "ه",
        "أ": "ا",
        "إ": "ا",
        "ؤ": "و",
        "ئ": "ی",
    }
)
OPTION_INDEX = {"الف": 0, "ب": 1, "ج": 2, "د": 3}

SESSION_HEADER_RE = re.compile(
    r"^جلسه\s*(?P<label>[0-9۰-۹٠-٩]+(?:\s*-\s*(?:الف|ب))?)\s*-\s*(?P<title>.+)$"
)
QUESTION_RE = re.compile(r"^(?P<number>[0-9۰-۹٠-٩]+)\.\s*(?P<text>.+)$")
OPTION_RE = re.compile(r"^(?P<label>الف|ب|ج|د)\)\s*(?P<text>.+)$")
ANSWER_ENTRY_RE = re.compile(
    r"^س[ؤو]ال\s*(?P<number>[0-9۰-۹٠-٩]+)\s*:\s*گزینه\s*(?P<label>الف|ب|ج|د)\.\s*(?P<text>.*)$"
)

DEFAULT_SOURCE = Path("public_html/api/data/radiology1_sessions_mcq_fa.txt")
DEFAULT_OUTPUT = Path("public_html/api/exams_radiology1_data.php")
DEFAULT_COURSE_TEMPLATE = Path("public_html/exams/radiology2/index.html")
DEFAULT_EXAM_TEMPLATE = Path("public_html/exams/radiology2/1/index.html")
DEFAULT_EXAMS_ROOT = Path("public_html/exams/radiology1")


@dataclass
class ParsedQuestion:
    number: int
    text: str
    options: list[str] = field(default_factory=list)


@dataclass
class SessionData:
    label: str
    title: str
    questions: list[ParsedQuestion] = field(default_factory=list)
    explanations: dict[int, str] = field(default_factory=dict)
    correct_labels: dict[int, str] = field(default_factory=dict)


def digits_to_int(value: str) -> int:
    normalized = str(value).translate(DIGIT_TRANS)
    numeric = re.sub(r"[^0-9]", "", normalized)
    if not numeric:
        raise ValueError(f"Expected digits, got {value!r}")
    return int(numeric)


def to_persian_digits(value: int | str) -> str:
    text = str(value)
    return "".join(PERSIAN_DIGITS[int(ch)] if ch.isdigit() else ch for ch in text)


def normalize_line(value: str) -> str:
    return str(value).replace("\ufeff", "").translate(ARABIC_TO_PERSIAN)


def clean_inline(value: str) -> str:
    return re.sub(r"\s+", " ", normalize_line(value)).strip()


def append_piece(current: str, piece: str) -> str:
    piece = clean_inline(piece)
    if not piece:
        return current
    if not current:
        return piece
    return current + " " + piece


def is_divider(line: str) -> bool:
    stripped = line.strip()
    return bool(stripped) and set(stripped) <= {"=", "-", "—", "–", "_", "*"}


def should_skip_line(line: str) -> bool:
    stripped = clean_inline(line)
    if not stripped or is_divider(stripped):
        return True
    return stripped.startswith("منبع:") or stripped == "سؤالات" or stripped == "پاسخنامه"


def build_explanation(correct_label: str, correct_text: str) -> str:
    lines = [f"**پاسخ درست:** {correct_label}"]
    if correct_text:
        lines.append(f"**توضیح:** {correct_text}")
    return "\n".join(lines)


def session_slug(label: str) -> str:
    normalized = clean_inline(label).translate(DIGIT_TRANS)
    normalized = normalized.replace(" ", "")
    normalized = normalized.replace("الف", "a").replace("ب", "b")
    return normalized


def parse_question_lines(lines: list[str]) -> list[ParsedQuestion]:
    questions: list[ParsedQuestion] = []
    pending: ParsedQuestion | None = None

    def flush_question() -> None:
        nonlocal pending
        if pending is None:
            return
        pending.text = clean_inline(pending.text)
        pending.options = [clean_inline(option) for option in pending.options]
        questions.append(pending)
        pending = None

    for raw_line in lines:
        stripped = clean_inline(raw_line)
        if not stripped or should_skip_line(stripped):
            continue

        question_match = QUESTION_RE.match(stripped)
        if question_match:
            flush_question()
            pending = ParsedQuestion(
                number=digits_to_int(question_match.group("number")),
                text=clean_inline(question_match.group("text")),
            )
            continue

        option_match = OPTION_RE.match(stripped)
        if option_match and pending is not None:
            pending.options.append(clean_inline(option_match.group("text")))
            continue

        if pending is not None:
            if pending.options:
                pending.options[-1] = append_piece(pending.options[-1], stripped)
            else:
                pending.text = append_piece(pending.text, stripped)

    flush_question()
    return questions


def parse_sessions(question_lines: list[str]) -> list[SessionData]:
    sessions: list[SessionData] = []
    current_session: SessionData | None = None
    buffer: list[str] = []

    def flush_session() -> None:
        nonlocal current_session, buffer
        if current_session is None:
            return
        current_session.questions = parse_question_lines(buffer)
        sessions.append(current_session)
        current_session = None
        buffer = []

    for raw_line in question_lines:
        stripped = clean_inline(raw_line)
        if not stripped or is_divider(stripped):
            continue

        header_match = SESSION_HEADER_RE.match(stripped)
        if header_match:
            flush_session()
            current_session = SessionData(
                label=clean_inline(header_match.group("label")),
                title=clean_inline(header_match.group("title")),
            )
            buffer = []
            continue

        if current_session is None or should_skip_line(stripped):
            continue

        buffer.append(raw_line)

    flush_session()
    return sessions


def parse_answers(answer_lines: list[str]) -> dict[str, dict[int, tuple[str, str]]]:
    answers: dict[str, dict[int, tuple[str, str]]] = {}
    current_label: str | None = None
    current_number: int | None = None
    current_correct_label: str | None = None
    current_text = ""

    def flush_answer() -> None:
        nonlocal current_number, current_correct_label, current_text
        if current_label is None or current_number is None or current_correct_label is None:
            return
        answers.setdefault(current_label, {})[current_number] = (
            current_correct_label,
            clean_inline(current_text),
        )
        current_number = None
        current_correct_label = None
        current_text = ""

    for raw_line in answer_lines:
        stripped = clean_inline(raw_line)
        if not stripped or is_divider(stripped):
            continue

        header_match = SESSION_HEADER_RE.match(stripped)
        if header_match:
            flush_answer()
            current_label = clean_inline(header_match.group("label"))
            continue

        if current_label is None or should_skip_line(stripped):
            continue

        answer_match = ANSWER_ENTRY_RE.match(stripped)
        if answer_match:
            flush_answer()
            current_number = digits_to_int(answer_match.group("number"))
            current_correct_label = answer_match.group("label")
            current_text = clean_inline(answer_match.group("text"))
            continue

        if current_number is not None:
            current_text = append_piece(current_text, stripped)

    flush_answer()
    return answers


def validate_sessions(sessions: list[SessionData], answers: dict[str, dict[int, tuple[str, str]]]) -> list[SessionData]:
    if len(sessions) != 15:
        raise RuntimeError(f"Expected 15 sessions, found {len(sessions)}")

    finalized: list[SessionData] = []
    for session in sessions:
        answer_map = answers.get(session.label, {})
        if len(session.questions) != 40:
            raise RuntimeError(f"Session {session.label} expected 40 questions, found {len(session.questions)}")
        if len(answer_map) != 40:
            raise RuntimeError(f"Session {session.label} expected 40 answers, found {len(answer_map)}")

        numbers = [question.number for question in session.questions]
        if numbers != list(range(1, 41)):
            raise RuntimeError(f"Session {session.label} question numbering is not 1..40")

        for question in session.questions:
            if len(question.options) != 4:
                raise RuntimeError(
                    f"Session {session.label} question {question.number} has {len(question.options)} options"
                )
            answer = answer_map.get(question.number)
            if answer is None:
                raise RuntimeError(f"Missing answer for session {session.label} question {question.number}")
            label, explanation = answer
            session.correct_labels[question.number] = label
            session.explanations[question.number] = build_explanation(label, explanation)

        finalized.append(session)

    return finalized


def parse_source(source: Path) -> list[SessionData]:
    text = normalize_line(source.read_text(encoding="utf-8-sig"))
    parts = re.split(r"پاسخنامه\s+تشریحی\s+کامل", text, maxsplit=1)
    question_lines = parts[0].splitlines()
    answer_lines = parts[1].splitlines() if len(parts) > 1 else []
    sessions = parse_sessions(question_lines)
    answers = parse_answers(answer_lines)
    return validate_sessions(sessions, answers)


def build_course(sessions: list[SessionData]) -> dict:
    exams: list[dict] = []
    for session in sessions:
        slug = session_slug(session.label)
        display_label = f"جلسه {session.label}"
        questions = [
            {
                "question": question.text,
                "options": question.options,
                "correctIndex": OPTION_INDEX[session.correct_labels[question.number]],
                "explanation": session.explanations[question.number],
            }
            for question in session.questions
        ]
        exams.append(
            {
                "slug": slug,
                "path": f"/exams/radiology1/{slug}/",
                "questionCount": len(questions),
                "label": display_label,
                "title": f"سوالات {display_label} – {session.title}",
                "subtitle": f"{to_persian_digits(len(questions))} سوال چهارگزینه‌ای با پاسخ تشریحی از مبحث «{session.title}».",
                "eyebrow": f"رادیولوژی نظری ۱ | {display_label} | {session.title}",
                "description": f"سوالات تمرینی این جلسه از مبحث «{session.title}».",
                "ctaLabel": "شروع آزمون",
                "backHref": "/exams/radiology1/",
                "backLabel": "بازگشت به فهرست آزمون‌های رادیولوژی نظری ۱",
                "autoAdvance": True,
                "siteTitle": "ورودی ۱۴۰۲ دندانپزشکی تهران",
                "siteSubtitle": "آزمون‌ها",
                "siteBadge": "آزمون تمرینی",
                "footerText": "ورودی ۱۴۰۲ دندانپزشکی تهران",
                "questions": questions,
            }
        )

    total_questions = sum(exam["questionCount"] for exam in exams)
    return {
        "slug": "radiology1",
        "title": "رادیولوژی نظری ۱",
        "shortTitle": "رادیولوژی ۱",
        "badge": "۱۵ جلسه",
        "cardDescription": "آزمون‌های رادیولوژی نظری ۱ بر اساس جزوات ارسالی در ۱۵ جلسه و با ۶۰۰ سوال چهارگزینه‌ای آماده شده است.",
        "heroTitle": "رادیولوژی نظری ۱",
        "heroDescription": "این مجموعه مربوط به درس رادیولوژی نظری ۱ است و ۱۵ جلسه را پوشش می‌دهد. برای هر جلسه ۴۰ سوال با پاسخ تشریحی آماده شده و دسترسی کل درس با یک خرید ۳۰ هزارتومانی فعال می‌شود.",
        "path": "/exams/radiology1/",
        "paymentTitle": "دسترسی به رادیولوژی نظری ۱",
        "paymentDescription": "با یک بار پرداخت، همهٔ آزمون‌های رادیولوژی نظری ۱ برای همین حساب فعال می‌شود.",
        "paymentSuccessMessage": "پرداخت شما تایید شد و همهٔ آزمون‌های رادیولوژی نظری ۱ برای این حساب باز شد.",
        "paymentFailureMessage": "پرداخت این درس تایید نشد. در صورت کسر وجه، نتیجه را دوباره از همین صفحه بررسی کنید.",
        "defaultPaymentMode": "paid",
        "defaultAmount": 300000,
        "statsLabel": f"{to_persian_digits(total_questions)} سوال",
        "exams": exams,
    }


def render_php(course: dict) -> str:
    json_payload = json.dumps(course, ensure_ascii=False, indent=2)
    return "\n".join(
        [
            "<?php",
            "declare(strict_types=1);",
            "",
            "function dent_exams_radiology1_course(): array",
            "{",
            "    static $course = null;",
            "    if (is_array($course)) {",
            "        return $course;",
            "    }",
            "",
            "    $json = <<<'JSON'",
            json_payload,
            "JSON;",
            "",
            "    $decoded = json_decode($json, true);",
            "    $course = is_array($decoded) ? $decoded : [];",
            "    return $course;",
            "}",
            "",
        ]
    )


def render_course_html(template: str) -> str:
    page = template
    page = re.sub(
        r"<title>.*?</title>",
        "<title>آزمون‌های رادیولوژی نظری ۱ | ورودی ۱۴۰۲ دندانپزشکی تهران</title>",
        page,
        count=1,
        flags=re.S,
    )
    page = re.sub(
        r'<meta name="description" content=".*?">',
        '<meta name="description" content="فهرست جلسه‌های آزمون رادیولوژی نظری ۱.">',
        page,
        count=1,
        flags=re.S,
    )
    page = re.sub(
        r'<body class="exams-page" data-exams-course="[^"]+">',
        '<body class="exams-page" data-exams-course="radiology1">',
        page,
        count=1,
    )
    return page


def render_exam_html(template: str, exam: dict) -> str:
    page = template
    page = re.sub(
        r"<title>.*?</title>",
        f"<title>{exam['title']} | ورودی ۱۴۰۲ دندانپزشکی</title>",
        page,
        count=1,
        flags=re.S,
    )
    page = re.sub(
        r'<meta name="description" content=".*?">',
        f'<meta name="description" content="{exam["title"]} | ورودی ۱۴۰۲ دندانپزشکی">',
        page,
        count=1,
        flags=re.S,
    )
    page = re.sub(
        r'<body class="quiz-page" data-exams-course="[^"]+" data-exams-exam="[^"]+">',
        f'<body class="quiz-page" data-exams-course="radiology1" data-exams-exam="{exam["slug"]}">',
        page,
        count=1,
    )
    page = re.sub(
        r'<a class="back-btn" href="[^"]+">.*?</a>',
        '<a class="back-btn" href="/exams/radiology1/">بازگشت به فهرست آزمون‌های رادیولوژی نظری ۱</a>',
        page,
        count=1,
        flags=re.S,
    )
    return page


def write_pages(course: dict, exams_root: Path, course_template_path: Path, exam_template_path: Path) -> list[Path]:
    written: list[Path] = []
    course_template = course_template_path.read_text(encoding="utf-8")
    exam_template = exam_template_path.read_text(encoding="utf-8")

    exams_root.mkdir(parents=True, exist_ok=True)
    course_index = exams_root / "index.html"
    course_index.write_text(render_course_html(course_template), encoding="utf-8")
    written.append(course_index)

    for exam in course["exams"]:
        target = exams_root / str(exam["slug"]) / "index.html"
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(render_exam_html(exam_template, exam), encoding="utf-8")
        written.append(target)

    return written


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Build Radiology 1 exam course.")
    parser.add_argument("--source", type=Path, default=DEFAULT_SOURCE)
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT)
    parser.add_argument("--course-template", type=Path, default=DEFAULT_COURSE_TEMPLATE)
    parser.add_argument("--exam-template", type=Path, default=DEFAULT_EXAM_TEMPLATE)
    parser.add_argument("--exams-root", type=Path, default=DEFAULT_EXAMS_ROOT)
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    sessions = parse_source(args.source)
    course = build_course(sessions)
    args.output.write_text(render_php(course), encoding="utf-8")
    written_pages = write_pages(course, args.exams_root, args.course_template, args.exam_template)
    print(f"Built Radiology 1 course into {args.output}")
    print(f"Wrote {len(written_pages)} pages under {args.exams_root}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
