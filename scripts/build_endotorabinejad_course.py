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
HALF_INDEX = {"اول": 1, "دوم": 2}
HALF_LABEL = {"اول": "نیمه اول", "دوم": "نیمه دوم"}

SECTION_HEADER_1_5_RE = re.compile(
    r"^فصل\s*(?P<chapter>[0-9۰-۹٠-٩]+)\s*-\s*نیمه[ٔ‌\s]*(?P<half>اول|دوم)\s*:\s*(?P<title>.+)$"
)
SECTION_HEADER_6_10_RE = re.compile(
    r"^فصل\s*(?P<chapter>[0-9۰-۹٠-٩]+)\s*-\s*نیمه\s*(?P<half>اول|دوم)\s*:\s*(?P<title>.+)$"
)
SECTION_HEADER_11_15_RE = re.compile(
    r"^(?:قسمت|پاسخنامه\s+قسمت)\s*(?P<part>[0-9۰-۹٠-٩]+)\s*:\s*فصل\s*(?P<chapter>[0-9۰-۹٠-٩]+)\s*[،,]\s*نیمه\s*(?P<half>اول|دوم)\s*:\s*(?P<title>.+)$"
)
QUESTION_RE = re.compile(r"^(?P<number>[0-9۰-۹٠-٩]+)\.\s*(?P<text>.+)$")
OPTION_RE = re.compile(r"^(?P<label>الف|ب|ج|د)\)\s*(?P<text>.+)$")
ANSWER_ENTRY_1_5_RE = re.compile(
    r"^(?P<number>[0-9۰-۹٠-٩]+)\.\s*پاسخ:\s*گزینه\s*(?P<label>الف|ب|ج|د)\.\s*(?P<text>.*)$"
)
ANSWER_ENTRY_6_10_RE = re.compile(r"^(?P<number>[0-9۰-۹٠-٩]+)\.\s*گزینه\s*(?P<label>الف|ب|ج|د)\s*$")
ANSWER_ENTRY_11_15_RE = re.compile(
    r"^(?P<number>[0-9۰-۹٠-٩]+)\.\s*پاسخ\s*صحیح:\s*گزینه\s*(?P<label>الف|ب|ج|د)\s*$"
)
OPTION_EXPLANATION_RE = re.compile(
    r"^(?P<label>الف|ب|ج|د)\)\s*(?P<verdict>درست است|نادرست است)[؛;:]?\s*(?P<text>.+)$"
)

DEFAULT_1_5_SOURCE = Path("public_html/api/data/torabinejad_ch1_5_500_mcq_fa.txt")
DEFAULT_6_10_SOURCE = Path("public_html/api/data/torabinejad_ch6_10_500_mcq_fa.txt")
DEFAULT_11_15_SOURCE = Path("public_html/api/data/torabinejad_ch11_15_500_mcq_fa.txt")
DEFAULT_OUTPUT = Path("public_html/api/exams_endotorabinejad_data.php")
DEFAULT_TEMPLATE = Path("public_html/exams/endotorabinejad/11-15/11-1/index.html")
DEFAULT_EXAMS_ROOT = Path("public_html/exams/endotorabinejad")


@dataclass
class ParsedQuestion:
    number: int
    text: str
    options: list[str] = field(default_factory=list)


@dataclass
class SectionData:
    chapter: int
    half_word: str
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
    return stripped.startswith("بخش ") or stripped.startswith("سؤالات") or stripped.startswith("پاسخنامه") or stripped.startswith("محدوده منبع:")


def build_explanation(correct_label: str, correct_text: str) -> str:
    lines = [f"**پاسخ درست:** {correct_label}"]
    if correct_text:
        lines.append(f"**توضیح:** {correct_text}")
    return "\n".join(lines)


def block_slug_for_chapter(chapter: int) -> str:
    if 1 <= chapter <= 5:
        return "1-5"
    if 6 <= chapter <= 10:
        return "6-10"
    if 11 <= chapter <= 15:
        return "11-15"
    raise ValueError(f"Unsupported chapter {chapter}")


def split_section_lines(lines: list[str], header_re: re.Pattern[str]) -> list[SectionData]:
    sections: list[SectionData] = []
    current_section: SectionData | None = None
    buffer: list[str] = []

    def flush_section() -> None:
        nonlocal current_section, buffer
        if current_section is None:
            return
        current_section.questions = parse_question_lines(buffer)
        sections.append(current_section)
        current_section = None
        buffer = []

    for raw_line in lines:
        stripped = clean_inline(raw_line)
        if not stripped or is_divider(stripped):
            continue

        header_match = header_re.match(stripped)
        if header_match:
            flush_section()
            current_section = SectionData(
                chapter=digits_to_int(header_match.group("chapter")),
                half_word=header_match.group("half"),
                title=clean_inline(header_match.group("title")),
            )
            buffer = []
            continue

        if current_section is None or should_skip_line(stripped):
            continue

        buffer.append(raw_line)

    flush_section()
    return sections


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


def parse_answers_1_5(lines: list[str], header_re: re.Pattern[str]) -> dict[tuple[int, int], dict[int, tuple[str, str]]]:
    answers: dict[tuple[int, int], dict[int, tuple[str, str]]] = {}
    current_key: tuple[int, int] | None = None
    current_number: int | None = None
    current_label: str | None = None
    current_text = ""

    def flush_answer() -> None:
        nonlocal current_number, current_label, current_text
        if current_key is None or current_number is None or current_label is None:
            return
        answers.setdefault(current_key, {})[current_number] = (current_label, clean_inline(current_text))
        current_number = None
        current_label = None
        current_text = ""

    for raw_line in lines:
        stripped = clean_inline(raw_line)
        if not stripped or is_divider(stripped):
            continue

        header_match = header_re.match(stripped)
        if header_match:
            flush_answer()
            current_key = (digits_to_int(header_match.group("chapter")), HALF_INDEX[header_match.group("half")])
            continue

        if current_key is None or should_skip_line(stripped):
            continue

        answer_match = ANSWER_ENTRY_1_5_RE.match(stripped)
        if answer_match:
            flush_answer()
            current_number = digits_to_int(answer_match.group("number"))
            current_label = answer_match.group("label")
            current_text = clean_inline(answer_match.group("text"))
            continue

        if current_number is not None:
            current_text = append_piece(current_text, stripped)

    flush_answer()
    return answers


def parse_answers_option_blocks(
    lines: list[str],
    header_re: re.Pattern[str],
    answer_entry_re: re.Pattern[str],
) -> dict[tuple[int, int], dict[int, tuple[str, str]]]:
    answers: dict[tuple[int, int], dict[int, tuple[str, str]]] = {}
    current_key: tuple[int, int] | None = None
    current_number: int | None = None
    current_label: str | None = None
    option_texts: dict[str, str] = {}
    last_option_label: str | None = None

    def flush_answer() -> None:
        nonlocal current_number, current_label, option_texts, last_option_label
        if current_key is None or current_number is None or current_label is None:
            return
        correct_text = clean_inline(option_texts.get(current_label, ""))
        answers.setdefault(current_key, {})[current_number] = (current_label, correct_text)
        current_number = None
        current_label = None
        option_texts = {}
        last_option_label = None

    for raw_line in lines:
        stripped = clean_inline(raw_line)
        if not stripped or is_divider(stripped):
            continue

        header_match = header_re.match(stripped)
        if header_match:
            flush_answer()
            current_key = (digits_to_int(header_match.group("chapter")), HALF_INDEX[header_match.group("half")])
            continue

        if current_key is None or should_skip_line(stripped):
            continue

        answer_match = answer_entry_re.match(stripped)
        if answer_match:
            flush_answer()
            current_number = digits_to_int(answer_match.group("number"))
            current_label = answer_match.group("label")
            option_texts = {}
            last_option_label = None
            continue

        option_match = OPTION_EXPLANATION_RE.match(stripped)
        if option_match and current_number is not None:
            label = option_match.group("label")
            option_texts[label] = append_piece(option_texts.get(label, ""), option_match.group("text"))
            last_option_label = label
            continue

        if current_number is not None and last_option_label is not None:
            option_texts[last_option_label] = append_piece(option_texts.get(last_option_label, ""), stripped)

    flush_answer()
    return answers


def validate_sections(sections: list[SectionData], answers: dict[tuple[int, int], dict[int, tuple[str, str]]], expected_sections: int) -> list[SectionData]:
    if len(sections) != expected_sections:
        raise RuntimeError(f"Expected {expected_sections} sections, found {len(sections)}")

    finalized: list[SectionData] = []
    for section in sections:
        half_number = HALF_INDEX[section.half_word]
        key = (section.chapter, half_number)
        answer_map = answers.get(key, {})

        if len(section.questions) != 50:
            raise RuntimeError(
                f"Section {section.chapter}-{half_number} expected 50 questions, found {len(section.questions)}"
            )
        if len(answer_map) != 50:
            raise RuntimeError(
                f"Section {section.chapter}-{half_number} expected 50 answers, found {len(answer_map)}"
            )

        question_numbers = [question.number for question in section.questions]
        if question_numbers != list(range(1, 51)):
            raise RuntimeError(f"Section {section.chapter}-{half_number} question numbering is not 1..50")

        for question in section.questions:
            if len(question.options) != 4:
                raise RuntimeError(
                    f"Section {section.chapter}-{half_number} question {question.number} has {len(question.options)} options"
                )

            answer = answer_map.get(question.number)
            if answer is None:
                raise RuntimeError(f"Missing answer for section {section.chapter}-{half_number} question {question.number}")
            label, explanation = answer
            section.correct_labels[question.number] = label
            section.explanations[question.number] = build_explanation(label, explanation)

        finalized.append(section)

    return finalized


def parse_ch1_5(source: Path) -> list[SectionData]:
    text = source.read_text(encoding="utf-8-sig")
    parts = text.split("بخش دوم", 1)
    question_lines = parts[0].splitlines()
    answer_lines = parts[1].splitlines() if len(parts) > 1 else []
    sections = split_section_lines(question_lines, SECTION_HEADER_1_5_RE)
    answers = parse_answers_1_5(answer_lines, SECTION_HEADER_1_5_RE)
    return validate_sections(sections, answers, expected_sections=10)


def parse_ch6_10(source: Path) -> list[SectionData]:
    text = source.read_text(encoding="utf-8-sig")
    parts = text.split("بخش دوم", 1)
    question_lines = parts[0].splitlines()
    answer_lines = parts[1].splitlines() if len(parts) > 1 else []
    sections = split_section_lines(question_lines, SECTION_HEADER_6_10_RE)
    answers = parse_answers_option_blocks(answer_lines, SECTION_HEADER_6_10_RE, ANSWER_ENTRY_6_10_RE)
    return validate_sections(sections, answers, expected_sections=10)


def parse_ch11_15(source: Path) -> list[SectionData]:
    text = source.read_text(encoding="utf-8-sig")
    parts = text.split("بخش دوم", 1)
    question_lines = parts[0].splitlines()
    answer_lines = parts[1].splitlines() if len(parts) > 1 else []
    sections = split_section_lines(question_lines, SECTION_HEADER_11_15_RE)
    answers = parse_answers_option_blocks(answer_lines, SECTION_HEADER_11_15_RE, ANSWER_ENTRY_11_15_RE)
    return validate_sections(sections, answers, expected_sections=10)


def build_exam_payload(section: SectionData) -> tuple[str, dict]:
    half_number = HALF_INDEX[section.half_word]
    slug = f"{section.chapter}-{half_number}"
    block_slug = block_slug_for_chapter(section.chapter)
    course_slug = f"endotorabinejad-{block_slug}"
    block_label = {
        "1-5": "۱ تا ۵",
        "6-10": "۶ تا ۱۰",
        "11-15": "۱۱ تا ۱۵",
    }[block_slug]
    label = f"فصل {to_persian_digits(section.chapter)} - {HALF_LABEL[section.half_word]}"
    back_href = f"/exams/endotorabinejad/{block_slug}/"
    back_label = f"بازگشت به فهرست فصول {block_label} اندو ترابی‌نژاد"

    payload_questions = []
    for question in section.questions:
        payload_questions.append(
            {
                "question": question.text,
                "options": question.options,
                "correctIndex": OPTION_INDEX[section.correct_labels[question.number]],
                "explanation": section.explanations[question.number],
            }
        )

    return slug, {
        "slug": slug,
        "path": f"/exams/endotorabinejad/{block_slug}/{slug}/",
        "questionCount": len(payload_questions),
        "label": label,
        "title": f"آزمون {label}",
        "subtitle": f"{to_persian_digits(len(payload_questions))} سوال چهارگزینه‌ای با پاسخ تشریحی از مبحث «{section.title}».",
        "eyebrow": f"اندو ترابی‌نژاد | آزمون فصل {to_persian_digits(section.chapter)} | {HALF_LABEL[section.half_word]}",
        "backHref": back_href,
        "backLabel": back_label,
        "autoAdvance": True,
        "siteTitle": "ورودی ۱۴۰۲ دندانپزشکی تهران",
        "siteSubtitle": "آزمون‌ها",
        "siteBadge": "آزمون تمرینی",
        "footerText": "ورودی ۱۴۰۲ دندانپزشکی تهران",
        "questions": payload_questions,
        "courseSlug": course_slug,
    }


def render_php(bank: dict[str, dict]) -> str:
    normalized_bank = {
        slug: {key: value for key, value in exam.items() if key != "courseSlug"}
        for slug, exam in bank.items()
    }
    json_payload = json.dumps(normalized_bank, ensure_ascii=False, indent=2)
    return "\n".join(
        [
            "<?php",
            "declare(strict_types=1);",
            "",
            "function dent_exams_endotorabinejad_exam_bank(): array",
            "{",
            "    static $bank = null;",
            "    if (is_array($bank)) {",
            "        return $bank;",
            "    }",
            "",
            "    $json = <<<'JSON'",
            json_payload,
            "JSON;",
            "",
            "    $decoded = json_decode($json, true);",
            "    $bank = is_array($decoded) ? $decoded : [];",
            "    return $bank;",
            "}",
            "",
        ]
    )


def render_exam_html(template: str, course_slug: str, exam_slug: str, title: str, description: str, back_href: str, back_label: str) -> str:
    page = template
    page = re.sub(r"<title>.*?</title>", f"<title>{title}</title>", page, count=1, flags=re.S)
    page = re.sub(
        r'<meta name="description" content=".*?">',
        f'<meta name="description" content="{description}">',
        page,
        count=1,
        flags=re.S,
    )
    page = re.sub(
        r'<body class="quiz-page" data-exams-course="[^"]+" data-exams-exam="[^"]+">',
        f'<body class="quiz-page" data-exams-course="{course_slug}" data-exams-exam="{exam_slug}">',
        page,
        count=1,
    )
    page = re.sub(
        r'<a class="back-btn" href="[^"]+">.*?</a>',
        f'<a class="back-btn" href="{back_href}">{back_label}</a>',
        page,
        count=1,
        flags=re.S,
    )
    return page


def write_exam_pages(bank: dict[str, dict], exams_root: Path, template_path: Path) -> list[Path]:
    template = template_path.read_text(encoding="utf-8")
    written: list[Path] = []

    for exam in bank.values():
        chapter = digits_to_int(str(exam["slug"]).split("-", 1)[0])
        block_slug = block_slug_for_chapter(chapter)
        course_slug = str(exam["courseSlug"])
        page_title = f"{exam['title']} | ورودی ۱۴۰۲ دندانپزشکی تهران"
        page_description = f"{exam['title']} اندو ترابی‌نژاد | ورودی ۱۴۰۲ دندانپزشکی تهران"
        page_html = render_exam_html(
            template,
            course_slug=course_slug,
            exam_slug=str(exam["slug"]),
            title=page_title,
            description=page_description,
            back_href=str(exam["backHref"]),
            back_label=str(exam["backLabel"]),
        )

        target = exams_root / block_slug / str(exam["slug"]) / "index.html"
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(page_html, encoding="utf-8")
        written.append(target)

    return written


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Build Torabinejad exam bank for chapters 1-15.")
    parser.add_argument("--source-1-5", type=Path, default=DEFAULT_1_5_SOURCE)
    parser.add_argument("--source-6-10", type=Path, default=DEFAULT_6_10_SOURCE)
    parser.add_argument("--source-11-15", type=Path, default=DEFAULT_11_15_SOURCE)
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT)
    parser.add_argument("--template", type=Path, default=DEFAULT_TEMPLATE)
    parser.add_argument("--exams-root", type=Path, default=DEFAULT_EXAMS_ROOT)
    return parser.parse_args()


def main() -> int:
    args = parse_args()

    sections = parse_ch1_5(args.source_1_5) + parse_ch6_10(args.source_6_10) + parse_ch11_15(args.source_11_15)
    sections.sort(key=lambda item: (item.chapter, HALF_INDEX[item.half_word]))

    bank: dict[str, dict] = {}
    for section in sections:
        slug, payload = build_exam_payload(section)
        bank[slug] = payload

    args.output.write_text(render_php(bank), encoding="utf-8")
    written_pages = write_exam_pages(bank, args.exams_root, args.template)

    print(f"Built {len(bank)} exams into {args.output}")
    print(f"Wrote {len(written_pages)} exam pages under {args.exams_root}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
