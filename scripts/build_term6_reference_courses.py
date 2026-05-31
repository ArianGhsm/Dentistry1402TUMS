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
HALF_PART_INDEX = {"اول": 1, "دوم": 2}
HALF_LABEL = {1: "نیمه اول", 2: "نیمه دوم"}
PART_LABEL = {1: "قسمت اول", 2: "قسمت دوم"}

QUESTION_RE = re.compile(r"^(?P<number>[0-9۰-۹٠-٩]+)\.\s*(?P<text>.+)$")
OPTION_RE = re.compile(r"^(?P<label>الف|ب|ج|د)\)\s*(?P<text>.+)$")
ANSWER_ENTRY_RE = re.compile(
    r"^(?P<number>[0-9۰-۹٠-٩]+)\.\s*(?:(?:پاسخ(?:\s*(?:صحیح|درست))?\s*:?\s*)?(?:گزینه\s*)?)?(?P<label>الف|ب|ج|د)(?:\)|\b)(?P<tail>.*)$"
)
ANSWER_PROMPT_RE = re.compile(
    r"^(?:پاسخ(?:\s+(?:س(?:ؤ|و)?ال))?|س(?:ؤ|و)?ال)\s*(?P<number>[0-9۰-۹٠-٩]+)\s*:\s*"
    r"(?:گزینه(?:[ٔ‌]\s*|\s*))?(?P<label>الف|ب|ج|د)(?P<tail>.*)$"
)

MALAMED_CHAPTER_RE = re.compile(
    r"^(?:پاسخنامه\s+)?فصل\s*(?P<chapter>[0-9۰-۹٠-٩]+)(?:\s*:\s*(?P<title>.+))?$"
)
MALAMED_HALF_RE = re.compile(r"^نیمه\s*(?P<half>اول|دوم)$")
MALAMED_COMBINED_SECTION_RE = re.compile(
    r"^فصل\s*(?P<chapter>[0-9۰-۹٠-٩]+)\s*[—\-]\s*نیمه\s*(?P<half>اول|دوم)(?:\s*:\s*(?P<title>.+))?$"
)
SUMMIT_SECTION_RE = re.compile(
    r"^فصل\s*(?P<chapter>[0-9۰-۹٠-٩]+)\s*[—\-]\s*نیمه\s*(?P<half>اول|دوم)"
    r"(?:\s*:\s*(?P<title>.+?))?(?:\s*\((?:سوالات|سؤالات).*\))?$"
)
PETERSON_SECTION_RE = re.compile(
    r"^(?:#+\s*)?(?:پاسخنامه\s+)?فصل\s*(?P<chapter>[0-9۰-۹٠-٩]+)\s*-\s*(?:قسمت|بخش)\s*"
    r"(?P<part>(?:اول|دوم|[0-9۰-۹٠-٩]+))(?:\s*:\s*(?P<title>.+)|\s*-\s*(?P<title_fallback>.+))?$"
)
PETERSON_CHAPTER_RE = re.compile(
    r"^(?:[=\-]+\s*)?(?:پاسخنامه\s+)?فصل\s*(?P<chapter>[0-9۰-۹٠-٩]+)\s*:\s*(?P<title>.+?)(?:\s*[=\-]+)?$"
)
PETERSON_EMBEDDED_PART_RE = re.compile(
    r"^(?:[\-]+\s*)?(?:پاسخنامه\s+)?فصل\s*(?P<chapter>[0-9۰-۹٠-٩]+)\s*[،,\-]\s*(?:قسمت|بخش)\s*"
    r"(?P<part>(?:اول|دوم|[0-9۰-۹٠-٩]+))(?:\s*[\-]+)?$"
)
PETERSON_PART_RE = re.compile(
    r"^قسمت\s*(?P<part>(?:اول|دوم|[0-9۰-۹٠-٩]+))(?:\s*-\s*(?P<suffix>.+))?$"
)
WHITE_SECTION_RE = re.compile(
    r"^(?:پاسخنامه\s+)?فصل\s*(?P<chapter>[0-9۰-۹٠-٩]+)\s*(?:[،,\-]| - )\s*قسمت\s*"
    r"(?P<part>(?:اول|دوم|[0-9۰-۹٠-٩]+))(?:\s*:\s*(?P<title>.+))?$"
)
GENERIC_SECTION_TITLES = {
    "سوالات",
    "سؤالات",
    "سوالات تستی",
    "سؤالات تستی",
    "پاسخنامه",
    "پاسخنامه تشریحی",
    "پاسخنامه تشریحی کامل",
}

DEFAULT_SITE_TITLE = "ورودی ۱۴۰۲ دندانپزشکی تهران"
DEFAULT_SITE_SUBTITLE = "آزمون‌ها"
DEFAULT_SITE_BADGE = "آزمون رفرنسی"
DEFAULT_FOOTER_TEXT = "ورودی ۱۴۰۲ دندانپزشکی تهران"
REFERENCE_PRICE = 300000


@dataclass(frozen=True)
class FileSpec:
    filename: str
    range_slug: str
    range_label: str


@dataclass(frozen=True)
class SourceConfig:
    key: str
    folder_name: str
    style: str
    course_slug: str
    title: str
    short_title: str
    unit_title: str
    reference_title: str
    range_specs: tuple[FileSpec, ...]


@dataclass
class ParsedQuestion:
    number: int
    text: str
    options: list[str] = field(default_factory=list)


@dataclass
class SectionBlock:
    chapter: int
    part: int
    title: str
    lines: list[str] = field(default_factory=list)


@dataclass
class ParsedSection:
    chapter: int
    part: int
    title: str
    label_style: str
    questions: list[ParsedQuestion] = field(default_factory=list)
    answers: dict[int, tuple[str, str]] = field(default_factory=dict)


SOURCES: tuple[SourceConfig, ...] = (
    SourceConfig(
        key="malamed",
        folder_name="malamed oral anesthesia",
        style="malamed",
        course_slug="malamed-local-anesthesia",
        title="مالامد | بی‌حسی موضعی",
        short_title="مالامد",
        unit_title="بی‌حسی موضعی",
        reference_title="Handbook of Local Anesthesia",
        range_specs=(
            FileSpec("questions_ch1_to_ch5_400_fa.txt", "1-5", "فصول ۱ تا ۵"),
            FileSpec("questions_chapters_6_to_10_400_fa.txt", "6-10", "فصول ۶ تا ۱۰"),
            FileSpec("questions_ch16_21_480.txt", "16-21", "فصول ۱۶ تا ۲۱"),
        ),
    ),
    SourceConfig(
        key="peterson",
        folder_name="Peterson",
        style="peterson",
        course_slug="peterson-oral-surgery",
        title="پیترسون | جراحی عملی ۱",
        short_title="پیترسون",
        unit_title="جراحی عملی ۱",
        reference_title="Contemporary Oral and Maxillofacial Surgery",
        range_specs=(
            FileSpec("quiz_fa_chapters_1_to_5_2parts_40each.txt", "1-5", "فصول ۱ تا ۵"),
            FileSpec("questions_ch6_to_ch10_2parts_40each_fa.txt", "6-10", "فصول ۶ تا ۱۰"),
            FileSpec("quiz_chapters_11_15_2parts_50q_each.txt", "11-15", "فصول ۱۱ تا ۱۵"),
            FileSpec("questions_ch16_20_2parts_50each_fa.txt", "16-20", "فصول ۱۶ تا ۲۰"),
            FileSpec("mcqs_ch21_25.txt", "21-25", "فصول ۲۱ تا ۲۵"),
        ),
    ),
    SourceConfig(
        key="summit",
        folder_name="Summit",
        style="summit",
        course_slug="summit-operative-dentistry",
        title="سامیت | ترمیمی نظری ۱",
        short_title="سامیت",
        unit_title="ترمیمی نظری ۱",
        reference_title="Summitt’s Fundamentals of Operative Dentistry",
        range_specs=(
            FileSpec("quiz_f1_to_f5_40_per_half_farsi.txt", "1-5", "فصول ۱ تا ۵"),
            FileSpec("questions_f6_to_f10_400_fa.txt", "6-10", "فصول ۶ تا ۱۰"),
            FileSpec("questions_ch11_15_farsi.txt", "11-15", "فصول ۱۱ تا ۱۵"),
            FileSpec("questions_ch16_21_persian.txt", "16-21", "فصول ۱۶ تا ۲۱"),
        ),
    ),
    SourceConfig(
        key="white",
        folder_name="white & pharoah",
        style="white",
        course_slug="whitepharoah-radiology-term6",
        title="White & Pharoah | رادیو عملی ۲",
        short_title="White & Pharoah",
        unit_title="رادیو عملی ۲",
        reference_title="White and Pharoah's Oral Radiology",
        range_specs=(
            FileSpec("oral_radiology_ch16_20_400_mcq_fa.txt", "16-20", "فصول ۱۶ تا ۲۰"),
            FileSpec("questions_ch21_25_persian_mcq.txt", "21-25", "فصول ۲۱ تا ۲۵"),
            FileSpec("quiz_chapters_26_to_30_persian.txt", "26-30", "فصول ۲۶ تا ۳۰"),
        ),
    ),
)


def digits_to_int(value: str) -> int:
    normalized = str(value).translate(DIGIT_TRANS)
    numeric = re.sub(r"[^0-9]", "", normalized)
    if not numeric:
        raise ValueError(f"Expected digits, got {value!r}")
    return int(numeric)


def to_persian_digits(value: int | str) -> str:
    text = str(value)
    return "".join(PERSIAN_DIGITS[int(ch)] if ch.isdigit() else ch for ch in text)


def part_to_int(value: str) -> int:
    clean = clean_inline(value)
    if clean in HALF_PART_INDEX:
        return HALF_PART_INDEX[clean]
    return digits_to_int(clean)


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


def is_divider(text: str) -> bool:
    stripped = text.strip()
    return bool(stripped) and set(stripped) <= {"=", "-", "—", "_", "*"}


def strip_hash_heading(text: str) -> str:
    return text.lstrip("# ").strip()


def normalize_section_title(value: str) -> str:
    clean = clean_inline(value)
    return "" if clean in GENERIC_SECTION_TITLES else clean


def should_skip_content_line(text: str) -> bool:
    clean = strip_hash_heading(clean_inline(text))
    if not clean or is_divider(clean):
        return True
    return clean in {
        "سؤالات",
        "پاسخنامه تشریحی",
        "پاسخنامه تشریحی کامل",
        "بخش سؤال‌ها",
        "بخش سوال‌ها",
        "بخش اول: سؤالات",
        "بخش دوم: پاسخنامه تشریحی کامل",
        "بخش پاسخنامه تشریحی کامل",
    }


def is_answer_marker_line(text: str) -> bool:
    clean = strip_hash_heading(clean_inline(text))
    return clean in {"پاسخنامه تشریحی", "پاسخنامه تشریحی کامل"}


def extract_asset_version(root: Path) -> str:
    template = root / "public_html" / "exams" / "endotorabinejad" / "index.html"
    content = template.read_text(encoding="utf-8")
    match = re.search(r"\?v=([0-9A-Za-z._-]+)", content)
    return match.group(1) if match else ""


def split_question_answer_halves(lines: list[str]) -> tuple[list[str], list[str]]:
    marker_index = None
    for index, raw_line in enumerate(lines):
        text = strip_hash_heading(clean_inline(raw_line))
        if (
            text.startswith("بخش دوم:")
            or text == "پاسخنامه تشریحی کامل"
            or text == "بخش پاسخنامه تشریحی کامل"
        ):
            marker_index = index
            break
    if marker_index is None:
        raise ValueError("Could not locate answer section marker.")
    return lines[:marker_index], lines[marker_index + 1 :]


def split_malamed_blocks(lines: list[str]) -> list[SectionBlock]:
    blocks: list[SectionBlock] = []
    current_chapter = 0
    current_title = ""
    current_block: SectionBlock | None = None

    def flush() -> None:
        nonlocal current_block
        if current_block is None:
            return
        blocks.append(current_block)
        current_block = None

    for raw_line in lines:
        text = clean_inline(raw_line)
        if not text or is_divider(text):
            continue
        if text.startswith("بخش "):
            continue

        chapter_match = MALAMED_CHAPTER_RE.match(text)
        if chapter_match:
            flush()
            current_chapter = digits_to_int(chapter_match.group("chapter"))
            current_title = clean_inline(chapter_match.group("title") or "")
            continue

        half_match = MALAMED_HALF_RE.match(text)
        if half_match and current_chapter > 0:
            flush()
            current_block = SectionBlock(
                chapter=current_chapter,
                part=HALF_PART_INDEX[half_match.group("half")],
                title=current_title,
            )
            continue

        if current_block is not None and (is_answer_marker_line(text) or not should_skip_content_line(text)):
            current_block.lines.append(raw_line)

    flush()
    return blocks


def split_peterson_blocks(lines: list[str]) -> list[SectionBlock]:
    blocks: list[SectionBlock] = []
    current_chapter = 0
    current_title = ""
    current_block: SectionBlock | None = None

    def flush() -> None:
        nonlocal current_block
        if current_block is None:
            return
        blocks.append(current_block)
        current_block = None

    for raw_line in lines:
        text = clean_inline(raw_line)
        if not text or is_divider(text):
            continue
        if text.startswith("بخش "):
            continue

        chapter_match = PETERSON_CHAPTER_RE.match(text)
        if chapter_match:
            flush()
            current_chapter = digits_to_int(chapter_match.group("chapter"))
            current_title = normalize_section_title(chapter_match.group("title") or "")
            continue

        embedded_part_match = PETERSON_EMBEDDED_PART_RE.match(text)
        if embedded_part_match:
            flush()
            current_chapter = digits_to_int(embedded_part_match.group("chapter"))
            current_block = SectionBlock(
                chapter=current_chapter,
                part=part_to_int(embedded_part_match.group("part")),
                title=current_title,
            )
            continue

        part_match = PETERSON_PART_RE.match(text)
        if part_match and current_chapter > 0:
            flush()
            current_block = SectionBlock(
                chapter=current_chapter,
                part=part_to_int(part_match.group("part")),
                title=current_title,
            )
            continue

        if current_block is not None and (is_answer_marker_line(text) or not should_skip_content_line(text)):
            current_block.lines.append(raw_line)

    flush()
    return blocks


def split_section_blocks(lines: list[str], header_re: re.Pattern[str], *, part_group: str, title_group: str | None) -> list[SectionBlock]:
    blocks: list[SectionBlock] = []
    current_block: SectionBlock | None = None

    def flush() -> None:
        nonlocal current_block
        if current_block is None:
            return
        blocks.append(current_block)
        current_block = None

    for raw_line in lines:
        text = clean_inline(raw_line)
        if not text or is_divider(text):
            continue
        if text.startswith("بخش "):
            continue

        header_match = header_re.match(text)
        if header_match:
            flush()
            if part_group == "half":
                part = HALF_PART_INDEX[header_match.group("half")]
            else:
                part = part_to_int(header_match.group(part_group))
            title = ""
            if title_group:
                title = (
                    header_match.group(title_group)
                    or header_match.groupdict().get(f"{title_group}_fallback")
                    or ""
                )
            current_block = SectionBlock(
                chapter=digits_to_int(header_match.group("chapter")),
                part=part,
                title=normalize_section_title(title) if title_group else "",
            )
            continue

        if current_block is not None and (is_answer_marker_line(text) or not should_skip_content_line(text)):
            current_block.lines.append(raw_line)

    flush()
    return blocks


def parse_question_lines(lines: list[str]) -> list[ParsedQuestion]:
    questions: list[ParsedQuestion] = []
    pending: ParsedQuestion | None = None

    def flush() -> None:
        nonlocal pending
        if pending is None:
            return
        pending.text = clean_inline(pending.text)
        pending.options = [clean_inline(option) for option in pending.options]
        questions.append(pending)
        pending = None

    for raw_line in lines:
        text = clean_inline(raw_line)
        if should_skip_content_line(text):
            continue

        question_match = QUESTION_RE.match(text)
        if question_match:
            flush()
            pending = ParsedQuestion(
                number=digits_to_int(question_match.group("number")),
                text=clean_inline(question_match.group("text")),
            )
            continue

        option_match = OPTION_RE.match(text)
        if option_match and pending is not None:
            pending.options.append(clean_inline(option_match.group("text")))
            continue

        if pending is None:
            continue

        if pending.options:
            pending.options[-1] = append_piece(pending.options[-1], text)
        else:
            pending.text = append_piece(pending.text, text)

    flush()
    return questions


def parse_answer_lines(lines: list[str]) -> dict[int, tuple[str, str]]:
    answers: dict[int, tuple[str, str]] = {}
    current_number: int | None = None
    current_label: str | None = None
    current_explanation = ""

    def flush() -> None:
        nonlocal current_number, current_label, current_explanation
        if current_number is None or current_label is None:
            return
        answers[current_number] = (current_label, clean_inline(current_explanation))
        current_number = None
        current_label = None
        current_explanation = ""

    for raw_line in lines:
        text = clean_inline(raw_line)
        if should_skip_content_line(text):
            continue

        answer_match = ANSWER_ENTRY_RE.match(text) or ANSWER_PROMPT_RE.match(text)
        if answer_match:
            flush()
            current_number = digits_to_int(answer_match.group("number"))
            current_label = answer_match.group("label")
            current_explanation = clean_inline(answer_match.group("tail").lstrip(")—-:؛ ")) if answer_match.group("tail") else ""
            continue

        if current_number is not None:
            current_explanation = append_piece(current_explanation, text)

    flush()
    return answers


def build_sections_from_separate_blocks(
    question_blocks: list[SectionBlock], answer_blocks: list[SectionBlock], *, label_style: str
) -> list[ParsedSection]:
    answer_lookup = {
        (block.chapter, block.part): parse_answer_lines(block.lines)
        for block in answer_blocks
    }
    return [
        ParsedSection(
            chapter=block.chapter,
            part=block.part,
            title=block.title,
            label_style=label_style,
            questions=parse_question_lines(block.lines),
            answers=answer_lookup.get((block.chapter, block.part), {}),
        )
        for block in question_blocks
    ]


def build_sections_from_inline_blocks(
    blocks: list[SectionBlock], *, label_style: str, path: Path
) -> list[ParsedSection]:
    sections: list[ParsedSection] = []
    for block in blocks:
        answer_marker_index = None
        for index, raw_line in enumerate(block.lines):
            clean = clean_inline(raw_line)
            if clean in {"پاسخنامه تشریحی", "پاسخنامه تشریحی کامل"}:
                answer_marker_index = index
                break
        if answer_marker_index is None:
            raise ValueError(f"Could not find answer block for chapter {block.chapter} part {block.part} in {path.name}")

        question_lines = block.lines[:answer_marker_index]
        answer_lines = block.lines[answer_marker_index + 1 :]
        sections.append(
            ParsedSection(
                chapter=block.chapter,
                part=block.part,
                title=block.title,
                label_style=label_style,
                questions=parse_question_lines(question_lines),
                answers=parse_answer_lines(answer_lines),
            )
        )

    return sections


def parse_malamed_file(path: Path) -> list[ParsedSection]:
    lines = path.read_text(encoding="utf-8").splitlines()
    if any(clean_inline(line).startswith("بخش دوم:") for line in lines):
        question_lines, answer_lines = split_question_answer_halves(lines)
        question_blocks = split_malamed_blocks(question_lines)
        answer_blocks = split_malamed_blocks(answer_lines)
        return build_sections_from_separate_blocks(question_blocks, answer_blocks, label_style="half")

    blocks = split_section_blocks(lines, MALAMED_COMBINED_SECTION_RE, part_group="half", title_group="title")
    return build_sections_from_inline_blocks(blocks, label_style="half", path=path)


def parse_summit_file(path: Path) -> list[ParsedSection]:
    lines = path.read_text(encoding="utf-8").splitlines()
    if any(clean_inline(line).startswith("بخش دوم:") for line in lines):
        question_lines, answer_lines = split_question_answer_halves(lines)
        question_blocks = split_section_blocks(question_lines, SUMMIT_SECTION_RE, part_group="half", title_group="title")
        answer_blocks = split_section_blocks(answer_lines, SUMMIT_SECTION_RE, part_group="half", title_group="title")
        return build_sections_from_separate_blocks(question_blocks, answer_blocks, label_style="half")

    blocks = split_section_blocks(lines, SUMMIT_SECTION_RE, part_group="half", title_group="title")
    return build_sections_from_inline_blocks(blocks, label_style="half", path=path)


def parse_peterson_file(path: Path) -> list[ParsedSection]:
    lines = path.read_text(encoding="utf-8").splitlines()
    question_lines, answer_lines = split_question_answer_halves(lines)
    question_blocks = split_section_blocks(question_lines, PETERSON_SECTION_RE, part_group="part", title_group="title")
    answer_blocks = split_section_blocks(answer_lines, PETERSON_SECTION_RE, part_group="part", title_group="title")

    if not question_blocks:
        question_blocks = split_peterson_blocks(question_lines)
    if not answer_blocks:
        answer_blocks = split_peterson_blocks(answer_lines)

    return build_sections_from_separate_blocks(question_blocks, answer_blocks, label_style="part")


def parse_white_file(path: Path) -> list[ParsedSection]:
    lines = path.read_text(encoding="utf-8").splitlines()
    if any("بخش دوم:" in strip_hash_heading(clean_inline(line)) for line in lines):
        question_lines, answer_lines = split_question_answer_halves(lines)
        question_blocks = split_section_blocks(question_lines, WHITE_SECTION_RE, part_group="part", title_group="title")
        answer_blocks = split_section_blocks(answer_lines, WHITE_SECTION_RE, part_group="part", title_group="title")
        return build_sections_from_separate_blocks(question_blocks, answer_blocks, label_style="part")

    blocks = split_section_blocks(lines, WHITE_SECTION_RE, part_group="part", title_group="title")
    return build_sections_from_inline_blocks(blocks, label_style="part", path=path)


def parse_sections(path: Path, style: str) -> list[ParsedSection]:
    if style == "malamed":
        return parse_malamed_file(path)
    if style == "summit":
        return parse_summit_file(path)
    if style == "peterson":
        return parse_peterson_file(path)
    if style == "white":
        return parse_white_file(path)
    raise ValueError(f"Unsupported style: {style}")


def explanation_text(label: str, detail: str) -> str:
    prefix = f"**پاسخ درست:** گزینه {label}"
    return prefix if not detail else prefix + "\n" + detail


def section_label(section: ParsedSection) -> str:
    chapter_label = f"فصل {to_persian_digits(section.chapter)}"
    suffix = HALF_LABEL[section.part] if section.label_style == "half" else PART_LABEL[section.part]
    return f"{chapter_label} - {suffix}"


def section_slug(section: ParsedSection) -> str:
    return f"{section.chapter}-{section.part}"


def exam_subtitle(section: ParsedSection, short_title: str, question_count: int) -> str:
    if section.title:
        return f"{to_persian_digits(question_count)} سؤال چهارگزینه‌ای از مبحث «{section.title}»."
    return f"{to_persian_digits(question_count)} سؤال چهارگزینه‌ای از مجموعه {short_title}."


def exam_description(section: ParsedSection, short_title: str, question_count: int) -> str:
    if section.title:
        return f"مرور {to_persian_digits(question_count)} سؤال از مبحث «{section.title}» در {short_title}."
    return f"مرور {to_persian_digits(question_count)} سؤال از مجموعه {short_title}."


def build_session_exam(config: SourceConfig, subcourse_slug: str, range_slug: str, range_label: str, section: ParsedSection) -> dict:
    label = section_label(section)
    slug = section_slug(section)
    questions_payload = []
    for question in section.questions:
        if len(question.options) != 4:
            raise ValueError(
                f"{config.course_slug} {range_slug} {slug}: expected 4 options, found {len(question.options)} for question {question.number}"
            )
        answer = section.answers.get(question.number)
        if answer is None:
            raise ValueError(
                f"{config.course_slug} {range_slug} {slug}: missing answer for question {question.number}"
            )
        label_text, detail = answer
        questions_payload.append(
            {
                "question": question.text,
                "options": question.options,
                "correctIndex": OPTION_INDEX[label_text],
                "explanation": explanation_text(label_text, detail),
            }
        )

    question_count = len(questions_payload)
    return {
        "slug": slug,
        "path": f"/exams/{config.course_slug}/{range_slug}/{slug}/",
        "questionCount": question_count,
        "label": label,
        "title": f"آزمون {label}",
        "subtitle": exam_subtitle(section, config.short_title, question_count),
        "description": exam_description(section, config.short_title, question_count),
        "eyebrow": f"{config.short_title} | {range_label} | {label}",
        "backHref": f"/exams/{config.course_slug}/{range_slug}/",
        "backLabel": f"بازگشت به {range_label} {config.short_title}",
        "autoAdvance": True,
        "siteTitle": DEFAULT_SITE_TITLE,
        "siteSubtitle": DEFAULT_SITE_SUBTITLE,
        "siteBadge": DEFAULT_SITE_BADGE,
        "footerText": DEFAULT_FOOTER_TEXT,
        "questions": questions_payload,
    }


def build_subcourse(config: SourceConfig, spec: FileSpec, exams: list[dict]) -> dict:
    total_questions = sum(int(exam["questionCount"]) for exam in exams)
    return {
        "slug": f"{config.course_slug}-{spec.range_slug}",
        "paymentGroupSlug": config.course_slug,
        "visibleOnCatalog": False,
        "title": f"{config.short_title} - {spec.range_label}",
        "shortTitle": spec.range_label,
        "badge": f"{to_persian_digits(len(exams))} آزمون",
        "cardDescription": f"این بخش {to_persian_digits(len(exams))} آزمون از {spec.range_label} را در بر می‌گیرد.",
        "heroTitle": f"{config.short_title} - {spec.range_label}",
        "heroDescription": (
            f"این بخش برای {config.unit_title} ترم ۶ آماده شده و "
            f"{to_persian_digits(len(exams))} آزمون با مجموع {to_persian_digits(total_questions)} سؤال دارد. "
            f"با خرید کامل مرجع {config.short_title} این بخش هم برای همین حساب فعال می‌شود."
        ),
        "path": f"/exams/{config.course_slug}/{spec.range_slug}/",
        "paymentTitle": f"دسترسی کامل به آزمون‌های {config.short_title}",
        "paymentDescription": (
            f"با یک بار پرداخت {to_persian_digits(30)} هزار تومان، همهٔ بخش‌های آزمون‌های مرجع "
            f"{config.short_title} برای همین حساب فعال می‌شود."
        ),
        "paymentSuccessMessage": f"پرداخت شما تایید شد و همهٔ بخش‌های آزمون‌های {config.short_title} برای این حساب باز شد.",
        "paymentFailureMessage": f"فعال‌سازی کامل آزمون‌های {config.short_title} انجام نشد. نتیجه را دوباره بررسی کنید.",
        "defaultPaymentMode": "paid",
        "defaultAmount": REFERENCE_PRICE,
        "exams": exams,
    }


def build_top_course(config: SourceConfig, subcourses: list[dict], specs: tuple[FileSpec, ...]) -> dict:
    items = []
    for spec, subcourse in zip(specs, subcourses):
        exams = subcourse.get("exams", [])
        total_questions = sum(int(exam.get("questionCount", 0)) for exam in exams if isinstance(exam, dict))
        items.append(
            {
                "slug": spec.range_slug,
                "path": f"/exams/{config.course_slug}/{spec.range_slug}/",
                "questionCount": total_questions,
                "label": spec.range_label,
                "title": f"{to_persian_digits(len(exams))} آزمون برای {spec.range_label}",
                "description": (
                    f"برای این بازه {to_persian_digits(len(exams))} آزمون با مجموع "
                    f"{to_persian_digits(total_questions)} سؤال آماده شده است و با خرید کامل این مرجع باز می‌شود."
                ),
                "ctaLabel": "مشاهده بخش",
            }
        )

    total_questions = sum(int(item["questionCount"]) for item in items)
    return {
        "slug": config.course_slug,
        "title": config.title,
        "shortTitle": config.short_title,
        "badge": f"{to_persian_digits(len(items))} بخش",
        "cardDescription": (
            f"آزمون‌های مرجع {config.short_title} برای {config.unit_title} ترم ۶ "
            f"در {to_persian_digits(len(items))} بخش فصل‌بندی شده‌اند."
        ),
        "heroTitle": f"آزمون‌های {config.short_title}",
        "heroDescription": (
            f"این مجموعه برای {config.unit_title} ترم ۶ آماده شده و "
            f"{to_persian_digits(len(items))} بخش با مجموع {to_persian_digits(total_questions)} سؤال دارد. "
            f"با یک بار پرداخت {to_persian_digits(30)} هزار تومان، دسترسی کامل این مرجع برای همین حساب فعال می‌شود."
        ),
        "path": f"/exams/{config.course_slug}/",
        "paymentTitle": f"آزمون‌های {config.short_title}",
        "paymentDescription": (
            f"با یک بار پرداخت {to_persian_digits(30)} هزار تومان، همهٔ بخش‌های آزمون‌های مرجع "
            f"{config.short_title} برای همین حساب فعال می‌شود."
        ),
        "paymentSuccessMessage": f"پرداخت شما تایید شد و همهٔ بخش‌های آزمون‌های {config.short_title} برای این حساب باز شد.",
        "paymentFailureMessage": f"فعال‌سازی کامل آزمون‌های {config.short_title} انجام نشد. نتیجه را دوباره بررسی کنید.",
        "defaultPaymentMode": "paid",
        "defaultAmount": REFERENCE_PRICE,
        "exams": items,
    }


def course_shell_html(title: str, description: str, course_slug: str, asset_version: str) -> str:
    version_suffix = f"?v={asset_version}" if asset_version else ""
    return f"""<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{title} | {DEFAULT_SITE_TITLE}</title>
    <meta name="description" content="{description}">
    <meta name="theme-color" content="#eef2f7">
    <link rel="manifest" href="/manifest.webmanifest{version_suffix}">
    <link rel="icon" type="image/png" href="/assets/images/favicon.png{version_suffix}">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png{version_suffix}">
    <script src="/assets/site/scripts/theme.js{version_suffix}"></script>
    <link rel="stylesheet" href="/assets/site/styles/core.css{version_suffix}">
    <link rel="stylesheet" href="/assets/site/styles/exams.css{version_suffix}">
    <link rel="stylesheet" href="/assets/site/styles/theme.css{version_suffix}">
</head>
<body class="exams-page" data-exams-course="{course_slug}">
    <div class="background-overlay" aria-hidden="true"></div>
    <main class="exams-main">
        <section id="exams-course-root" class="exams-stack" aria-live="polite">
            <div class="exams-card exams-loading">در حال بارگذاری این درس...</div>
        </section>
    </main>
    <script src="/assets/site/scripts/auth.js{version_suffix}"></script>
    <script src="/assets/site/scripts/exams-course.js{version_suffix}"></script>
    <script src="/assets/site/scripts/pwa.js{version_suffix}"></script>
    <script src="/assets/site/scripts/shell.js{version_suffix}"></script>
</body>
</html>
"""


def quiz_shell_html(title: str, description: str, course_slug: str, exam_slug: str, back_href: str, back_label: str, asset_version: str) -> str:
    version_suffix = f"?v={asset_version}" if asset_version else ""
    return f"""<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{title} | {DEFAULT_SITE_TITLE}</title>
    <meta name="description" content="{description}">
    <meta name="theme-color" content="#eef2f7">
    <link rel="manifest" href="/manifest.webmanifest{version_suffix}">
    <link rel="icon" type="image/png" href="/assets/images/favicon.png{version_suffix}">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png{version_suffix}">
    <script src="/assets/site/scripts/theme.js{version_suffix}"></script>
    <link rel="stylesheet" href="/assets/site/styles/core.css{version_suffix}">
    <link rel="stylesheet" href="/assets/site/styles/exam-quiz.css{version_suffix}">
    <link rel="stylesheet" href="/assets/site/styles/theme.css{version_suffix}">
</head>
<body class="quiz-page" data-exams-course="{course_slug}" data-exams-exam="{exam_slug}">
    <div data-exam-app></div>
    <noscript>
        <main class="exam-main">
            <section class="exam-panel exam-empty-state">
                <h1>برای اجرای آزمون جاوااسکریپت را فعال کنید.</h1>
                <p>این صفحه برای نمایش سوال‌ها و ذخیره پاسخ‌ها به جاوااسکریپت نیاز دارد.</p>
                <a class="back-btn" href="{back_href}">{back_label}</a>
            </section>
        </main>
    </noscript>
    <script src="/assets/site/scripts/exam-bootstrap.js{version_suffix}"></script>
</body>
</html>
"""


def write_file(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")


def build_course_map(root: Path, desktop_root: Path) -> tuple[dict[str, dict], list[str]]:
    course_map: dict[str, dict] = {}
    summary_lines: list[str] = []

    for config in SOURCES:
        subcourses: list[dict] = []
        for spec in config.range_specs:
            source_path = desktop_root / config.folder_name / spec.filename
            if not source_path.is_file():
                raise FileNotFoundError(f"Missing source file: {source_path}")

            sections = parse_sections(source_path, config.style)
            if not sections:
                raise ValueError(f"No sections parsed from {source_path}")

            session_exams = [
                build_session_exam(config, f"{config.course_slug}-{spec.range_slug}", spec.range_slug, spec.range_label, section)
                for section in sections
            ]
            subcourse = build_subcourse(config, spec, session_exams)
            subcourses.append(subcourse)
            course_map[subcourse["slug"]] = subcourse

            summary_lines.append(
                f"{config.course_slug}/{spec.range_slug}: {len(session_exams)} exams, "
                f"{sum(exam['questionCount'] for exam in session_exams)} questions"
            )

        top_course = build_top_course(config, subcourses, config.range_specs)
        course_map[config.course_slug] = top_course
        summary_lines.append(
            f"{config.course_slug}: {len(subcourses)} sections, "
            f"{sum(item['questionCount'] for item in top_course['exams'])} questions"
        )

    return course_map, summary_lines


def write_php_data(root: Path, course_map: dict[str, dict]) -> None:
    json_payload = json.dumps(course_map, ensure_ascii=False, indent=2)
    content = (
        "<?php\n"
        "declare(strict_types=1);\n\n"
        "function dent_exams_term6_reference_course_map(): array\n"
        "{\n"
        "    static $courses = null;\n"
        "    if (is_array($courses)) {\n"
        "        return $courses;\n"
        "    }\n\n"
        "    $json = <<<'JSON'\n"
        f"{json_payload}\n"
        "JSON;\n\n"
        "    $decoded = json_decode($json, true);\n"
        "    $courses = is_array($decoded) ? $decoded : [];\n"
        "    return $courses;\n"
        "}\n"
    )
    write_file(root / "public_html" / "api" / "exams_term6_reference_data.php", content)


def write_shells(root: Path, course_map: dict[str, dict], asset_version: str) -> None:
    public_root = root / "public_html"

    for slug, course in course_map.items():
        course_path_value = str(course.get("path", "")).strip()
        if not course_path_value:
            continue
        course_path = public_root / course_path_value.strip("/") / "index.html"
        title = str(course.get("title", slug)).strip() or slug
        description = str(course.get("heroDescription") or course.get("cardDescription") or title).strip()
        write_file(course_path, course_shell_html(title, description, slug, asset_version))

        for exam in course.get("exams", []):
            if not isinstance(exam, dict):
                continue
            exam_slug = str(exam.get("slug", "")).strip()
            exam_path_value = str(exam.get("path", "")).strip()
            if not exam_slug or not exam_path_value:
                continue

            if "questions" not in exam:
                section_dir = public_root / exam_path_value.strip("/") / "index.html"
                section_title = str(exam.get("title") or exam.get("label") or exam_slug).strip()
                section_description = str(exam.get("description") or section_title).strip()
                write_file(section_dir, course_shell_html(section_title, section_description, f"{slug}-{exam_slug}", asset_version))
                continue

            exam_dir = public_root / exam_path_value.strip("/") / "index.html"
            write_file(
                exam_dir,
                quiz_shell_html(
                    str(exam.get("title") or exam_slug),
                    str(exam.get("description") or exam.get("subtitle") or exam_slug),
                    slug,
                    exam_slug,
                    str(exam.get("backHref") or f"/exams/{slug}/"),
                    str(exam.get("backLabel") or "بازگشت"),
                    asset_version,
                ),
            )


def main() -> None:
    parser = argparse.ArgumentParser(description="Build term-6 reference exam courses from Desktop text files.")
    parser.add_argument(
        "--desktop-root",
        type=Path,
        default=Path.home() / "Desktop",
        help="Desktop root containing the reference folders.",
    )
    parser.add_argument(
        "--repo-root",
        type=Path,
        default=Path(__file__).resolve().parents[1],
        help="Repository root.",
    )
    args = parser.parse_args()

    repo_root = args.repo_root.resolve()
    desktop_root = args.desktop_root.resolve()
    asset_version = extract_asset_version(repo_root)

    course_map, summary_lines = build_course_map(repo_root, desktop_root)
    write_php_data(repo_root, course_map)
    write_shells(repo_root, course_map, asset_version)

    for line in summary_lines:
        print(line)


if __name__ == "__main__":
    main()
