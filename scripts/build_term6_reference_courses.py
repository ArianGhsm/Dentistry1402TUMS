#!/usr/bin/env python3
# Builds term-6 reference exam data as one PHP file per course under
# public_html/api/exams_term6_reference_data/<slug>.php (loaded lazily by
# exams_term6_reference_overrides.php). Never collapse this back into a single
# monolithic data file - that previously caused 500s under low memory limits.
from __future__ import annotations

import argparse
import json
import re
import subprocess
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

OPTION_LABELS = ("الف", "ب", "ج", "د", "پ", "ت")
OPTION_LABEL_PATTERN = "|".join(re.escape(label) for label in OPTION_LABELS)
PART_INDEX = {"اول": 1, "دوم": 2, "سوم": 3, "چهارم": 4}
HALF_PART_INDEX = PART_INDEX.copy()
HALF_LABEL = {1: "نیمه اول", 2: "نیمه دوم", 3: "نیمه سوم", 4: "نیمه چهارم"}
PART_LABEL = {1: "قسمت اول", 2: "قسمت دوم", 3: "قسمت سوم", 4: "قسمت چهارم"}

QUESTION_RE = re.compile(r"^(?:س(?:ؤ|و)?ال\s*)?(?P<number>[0-9۰-۹٠-٩]+)[\.\):]\s*(?P<text>.+)$")
OPTION_RE = re.compile(rf"^(?P<label>{OPTION_LABEL_PATTERN})\)\s*(?P<text>.+)$")
ANSWER_ENTRY_RE = re.compile(
    rf"^(?P<number>[0-9۰-۹٠-٩]+)[\.\)]\s*(?:(?:(?:پاسخ|گزینه)(?:\s*(?:صحیح|درست))?\s*:?\s*)?(?:گزینه\s*)?)?(?P<label>{OPTION_LABEL_PATTERN})(?:\)|\b)(?P<tail>.*)$"
)
ANSWER_PROMPT_RE = re.compile(
    rf"^(?:پاسخ(?:\s+(?:س(?:ؤ|و)?ال))?|س(?:ؤ|و)?ال)\s*(?P<number>[0-9۰-۹٠-٩]+)\s*:\s*"
    rf"(?:گزینه(?:[ٔ‌]\s*|\s*))?(?P<label>{OPTION_LABEL_PATTERN})(?P<tail>.*)$"
)
ANSWER_QUESTION_PROMPT_RE = re.compile(
    rf"^س(?:ؤ|و)?ال\s*(?P<number>[0-9۰-۹٠-٩]+)[\.\)]\s*پاسخ\s*:\s*"
    rf"(?:گزینه(?:[ٔ‌]\s*|\s*))?(?P<label>{OPTION_LABEL_PATTERN})(?P<tail>.*)$"
)
ANSWER_SECTION_HEADING_RE = re.compile(
    r"^پاسخنامه(?:\s+تشریحی(?:\s+کامل)?)?(?:(?:\s*[—\-:|،]\s*.*)|(?:\s+فصل\b.*))?$"
)

MALAMED_CHAPTER_RE = re.compile(
    r"^(?:پاسخنامه\s+)?فصل\s*(?P<chapter>[0-9۰-۹٠-٩]+)(?:\s*:\s*(?P<title>.+))?$"
)
MALAMED_HALF_RE = re.compile(r"^نیمه(?:[‌ ]?ی)?\s*(?P<half>اول|دوم|سوم|چهارم)$")
MALAMED_COMBINED_SECTION_RE = re.compile(
    r"^.*?فصل\s*(?P<chapter>[0-9۰-۹٠-٩]+)\s*[—\-|]\s*نیمه(?:[‌ ]?ی)?\s*(?P<half>اول|دوم|سوم|چهارم)"
    r"(?:\s*[:|]\s*(?P<title>.+?))?(?:\s*[-=#*\s|]+)?$"
)
SUMMIT_SECTION_RE = re.compile(
    r"^فصل\s*(?P<chapter>[0-9۰-۹٠-٩]+)\s*[—\-]\s*نیمه(?:[‌ ]?ی)?\s*(?P<half>اول|دوم|سوم|چهارم)"
    r"(?:\s*:\s*(?P<title>.+?))?(?:\s*\((?:سوالات|سؤالات).*\))?$"
)
PETERSON_SECTION_RE = re.compile(
    r"^(?:[=#\-]+\s*)?(?:#+\s*)?(?:پاسخنامه(?:\s+تشریحی(?:\s+کامل)?)?\s*[—\-:|]?\s*)?فصل\s*(?P<chapter>[0-9۰-۹٠-٩]+)"
    r"(?:\s*[:\-،]\s*(?P<title_before>.+?))?\s*[—\-،|:]\s*(?:قسمت|بخش|نیمه(?:[‌ ]?ی)?)\s*"
    r"(?P<part>(?:اول|دوم|سوم|چهارم|[0-9۰-۹٠-٩]+))(?:\s+از\s+[0-9۰-۹٠-٩]+)?"
    r"(?:\s*(?:[—:\-|]\s*|\(\s*)(?P<title>.+?)(?:\s*\))?)?(?:\s*[-=#*\s|]+)?$"
)
PETERSON_CHAPTER_RE = re.compile(
    r"^(?:[=\-]+\s*)?(?:پاسخنامه\s+)?فصل\s*(?P<chapter>[0-9۰-۹٠-٩]+)"
    r"(?:\s*[:\-|]\s*(?P<title>.+?))?(?:\s*[=\-]+)?$"
)
PETERSON_EMBEDDED_PART_RE = re.compile(
    r"^(?:[=\-]+\s*)?(?:پاسخنامه\s+)?فصل\s*(?P<chapter>[0-9۰-۹٠-٩]+)\s*[،,\-]\s*(?:قسمت|بخش|نیمه(?:[‌ ]?ی)?)\s*"
    r"(?P<part>(?:اول|دوم|سوم|چهارم|[0-9۰-۹٠-٩]+))(?:\s+از\s+[0-9۰-۹٠-٩]+)?"
    r"(?:\s*(?:[—:\-]\s*|\(\s*)(?P<title>.+?)(?:\s*\))?)?(?:\s*[=\-]+)?$"
)
PETERSON_PART_RE = re.compile(
    r"^(?:[=\-]+\s*)?(?:قسمت|بخش|نیمه(?:[‌ ]?ی)?)\s*(?P<part>(?:اول|دوم|سوم|چهارم|[0-9۰-۹٠-٩]+))(?:\s+از\s+[0-9۰-۹٠-٩]+)?"
    r"(?:\s*(?:[—:\-]\s*|\(\s*)(?P<suffix>.+?)(?:\s*\))?)?(?:\s*[=\-]+)?$"
)
WHITE_SECTION_RE = re.compile(
    r"^(?:پاسخنامه(?:\s+تشریحی(?:\s+کامل)?)?\s*[—\-:|]?\s*)?فصل\s*(?P<chapter>[0-9۰-۹٠-٩]+)"
    r"(?:\s*[:\-،]\s*(?P<title_before>.+?))?\s*[—\-،|:]\s*(?:قسمت|بخش)\s*"
    r"(?P<part>(?:اول|دوم|سوم|چهارم|[0-9۰-۹٠-٩]+))"
    r"(?:\s*(?:[—\-:|]\s*|\(\s*)(?P<title>.+?)(?:\s*\))?)?$"
)
SUBSECTION_SECTION_RE = re.compile(
    r"^(?:پاسخنامه(?:\s+تشریحی(?:\s+کامل)?)?\s*)?زیرسکشن\s*(?P<chapter>[0-9۰-۹٠-٩]+)\.(?P<part>[0-9۰-۹٠-٩]+)\s*:\s*(?P<title>.+)$"
)
CHAPTER_ONLY_SECTION_RE = re.compile(
    r"^(?:پاسخنامه(?:\s+تشریحی)?\s*)?(?:[-=#*\s]*)فصل\s*(?P<chapter>[0-9۰-۹٠-٩]+)(?:\s*:\s*(?P<title>.+))?(?:\s*[-=#*\s]+)?$"
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
    option_labels: list[str] = field(default_factory=list)


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
            FileSpec("malamed_ch11_15_400_mcq_fa (1).txt", "11-15", "فصول ۱۱ تا ۱۵"),
            FileSpec("questions_ch16_21_480.txt", "16-21", "فصول ۱۶ تا ۲۱"),
        ),
    ),
    SourceConfig(
        key="malamed-urgent",
        folder_name="malamed urgent",
        style="chapter-only-inline",
        course_slug="malamed-medical-emergencies",
        title="مالامد اورژانس | اورژانس‌های پزشکی",
        short_title="مالامد اورژانس",
        unit_title="اورژانس‌های پزشکی",
        reference_title="Medical Emergencies in the Dental Office",
        range_specs=(
            FileSpec("malamed_ch1-5_225_mcq_fa.txt", "1-5", "فصول ۱ تا ۵"),
            FileSpec("malamed_chapters_6_to_10_45q_each_fa.txt", "6-10", "فصول ۶ تا ۱۰"),
            FileSpec("questions_ch11_15_malamed_fa.txt", "11-15", "فصول ۱۱ تا ۱۵"),
            FileSpec("malamed_ch21_25_questions_fa.txt", "21-25", "فصول ۲۱ تا ۲۵"),
            FileSpec("Malamed_ch26-31_45MCQ_FA.txt", "26-31", "فصول ۲۶ تا ۳۱"),
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
            FileSpec("p.txt", "26-31", "فصول ۲۶ تا ۳۱"),
        ),
    ),
    SourceConfig(
        key="burket",
        folder_name="burket",
        style="chapter-part-separate",
        course_slug="burket-oral-medicine",
        title="برکت | طب دهان",
        short_title="برکت",
        unit_title="طب دهان",
        reference_title="Burket's Oral Medicine",
        range_specs=(
            FileSpec("burket_chapters_1_to_5_two_parts_400_mcq_fa.txt", "1-5", "فصول ۱ تا ۵"),
            FileSpec("Burket_ch6_10_MCQ_FA.txt", "6-10", "فصول ۶ تا ۱۰"),
            FileSpec("burket_chapters_11_15_mcq_persian.txt", "11-15", "فصول ۱۱ تا ۱۵"),
            FileSpec("burket_ch16_20_mcq_fa.txt", "16-20", "فصول ۱۶ تا ۲۰"),
            FileSpec("Burket_Ch21-25_400_MCQs_FA.txt", "21-25", "فصول ۲۱ تا ۲۵"),
            FileSpec("burket_ch25_29_400_mcq_fa.txt", "25-29", "فصول ۲۵ تا ۲۹"),
        ),
    ),
    SourceConfig(
        key="falace",
        folder_name="falace",
        style="chapter-part-separate",
        course_slug="falace-medically-compromised",
        title="فالاس | ملاحظات پزشکی بیمار",
        short_title="فالاس",
        unit_title="بیماران دارای ملاحظات پزشکی",
        reference_title="Dental Management in the Medically Compromised Patient",
        range_specs=(
            FileSpec("Falace_chapters_1_to_5_questions.txt", "1-5", "فصول ۱ تا ۵"),
            FileSpec("falace_chapters_6_to_10_mcq_fa.txt", "6-10", "فصول ۶ تا ۱۰"),
            FileSpec("questions_falace_ch11_15.txt", "11-15", "فصول ۱۱ تا ۱۵"),
            FileSpec("falace_ch16_20_mcq.txt", "16-20", "فصول ۱۶ تا ۲۰"),
            FileSpec("Falace_2024_Ch21-25_400_MCQs_FA.txt", "21-25", "فصول ۲۱ تا ۲۵"),
            FileSpec("falace_ch26_29_mcq_fa (1).txt", "26-29", "فصول ۲۶ تا ۲۹"),
        ),
    ),
    SourceConfig(
        key="craig",
        folder_name="Craig",
        style="chapter-part-separate",
        course_slug="craig-dental-materials",
        title="کریگ | مواد دندانی",
        short_title="کریگ",
        unit_title="مواد دندانی",
        reference_title="Craig's Restorative Dental Materials",
        range_specs=(
            FileSpec("craig_chapters_1_to_5_fa_400_mcq.txt", "1-5", "فصول ۱ تا ۵"),
            FileSpec("Craig_Chapters_6_to_10_MCQs_FA.txt", "6-10", "فصول ۶ تا ۱۰"),
            FileSpec("MCQ_Craig_Chapters_11-16_FA.txt", "11-16", "فصول ۱۱ تا ۱۶"),
        ),
    ),
    SourceConfig(
        key="vannoort",
        folder_name="van nourt",
        style="subsection-inline",
        course_slug="vannoort-dental-materials",
        title="ون نورت | مواد دندانی",
        short_title="ون نورت",
        unit_title="مواد دندانی",
        reference_title="Introduction to Dental Materials",
        range_specs=(
            FileSpec("section1_mcq_farsi.txt", "section-1", "سکشن ۱"),
            FileSpec("section2_320_mcq_fa.txt", "section-2", "سکشن ۲"),
            FileSpec("section3_mcq_persian.txt", "section-3", "سکشن ۳"),
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
        key="sturdevant",
        folder_name="art",
        style="chapter-part-separate",
        course_slug="sturdevant-operative-dentistry",
        title="استردوانت | ترمیمی",
        short_title="استردوانت",
        unit_title="ترمیمی",
        reference_title="Sturdevant's Art and Science of Operative Dentistry",
        range_specs=(
            FileSpec("sturdevant_chapters_1_to_4_480_mcq_fa.txt", "1-4", "فصول ۱ تا ۴"),
            FileSpec("sturdevant_ch5_8_480_mcq_fa.txt", "5-8", "فصول ۵ تا ۸"),
            FileSpec("questions_chapters_9_to_12_persian.txt", "9-12", "فصول ۹ تا ۱۲"),
            FileSpec("quiz_ch13_ch14_parts.txt", "13-14", "فصول ۱۳ تا ۱۴"),
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
            FileSpec("oral_radiology_ch1_5_persian_mcq.txt", "1-5", "فصول ۱ تا ۵"),
            FileSpec("oral_radiology_ch6_10_mcq_persian.txt", "6-10", "فصول ۶ تا ۱۰"),
            FileSpec("questions_ch11_to_ch15_oral_radiology_fa.txt", "11-15", "فصول ۱۱ تا ۱۵"),
            FileSpec("oral_radiology_ch16_20_400_mcq_fa.txt", "16-20", "فصول ۱۶ تا ۲۰"),
            FileSpec("questions_ch21_25_persian_mcq.txt", "21-25", "فصول ۲۱ تا ۲۵"),
            FileSpec("quiz_chapters_26_to_30_persian.txt", "26-30", "فصول ۲۶ تا ۳۰"),
            FileSpec("questions_ch31_33_fa.txt", "31-33", "فصول ۳۱ تا ۳۳"),
        ),
    ),
    SourceConfig(
        key="zarb",
        folder_name="zarb",
        style="chapter-part-separate",
        course_slug="zarb-complete-prosthodontics",
        title="زارب | پروتز کامل",
        short_title="زارب",
        unit_title="پروتز کامل",
        reference_title="Prosthodontic Treatment for Edentulous Patients",
        range_specs=(
            FileSpec("questions_chapters_1_to_5_500_mcq_fa.txt", "1-5", "فصول ۱ تا ۵"),
            FileSpec("quiz_fa_chapters_6_to_10_500_questions.txt", "6-10", "فصول ۶ تا ۱۰"),
            FileSpec("prosthodontics_ch11_15_mcq_fa.txt", "11-15", "فصول ۱۱ تا ۱۵"),
            FileSpec("prosthodontics_ch16_20_mcq_fa.txt", "16-20", "فصول ۱۶ تا ۲۰"),
            FileSpec("questions_ch21_23_fa.txt", "21-23", "فصول ۲۱ تا ۲۳"),
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
    if clean in PART_INDEX:
        return PART_INDEX[clean]
    return digits_to_int(clean)


def normalize_line(value: str) -> str:
    return str(value).replace("\ufeff", "").replace("ٔ", "").translate(ARABIC_TO_PERSIAN)


def clean_inline(value: str) -> str:
    return re.sub(r"[\s\u200c]+", " ", normalize_line(value)).strip()


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
        "بخش پاسخنامه تشریحی کامل",
        "بخش اول: سؤالات",
        "بخش دوم: پاسخنامه تشریحی کامل",
        "بخش پاسخنامه تشریحی کامل",
    }


def is_answer_marker_line(text: str) -> bool:
    clean = strip_hash_heading(clean_inline(text))
    return bool(ANSWER_SECTION_HEADING_RE.match(clean) or clean.startswith("بخش پاسخنامه"))


def is_answer_section_header(text: str) -> bool:
    clean = strip_hash_heading(clean_inline(text))
    return bool(ANSWER_SECTION_HEADING_RE.match(clean) or clean.startswith("بخش پاسخنامه"))


def extract_asset_version(root: Path) -> str:
    template = root / "public_html" / "exams" / "endotorabinejad" / "index.html"
    content = template.read_text(encoding="utf-8")
    match = re.search(r"\?v=([0-9A-Za-z._-]+)", content)
    return match.group(1) if match else ""


def split_question_answer_halves(lines: list[str]) -> tuple[list[str], list[str]]:
    marker_index = None
    for index, raw_line in enumerate(lines):
        text = strip_hash_heading(clean_inline(raw_line))
        if text.startswith("بخش دوم:") or is_answer_section_header(text):
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
        if should_skip_content_line(text) and current_chapter == 0 and current_block is None:
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


def split_malamed_named_half_blocks(lines: list[str]) -> list[SectionBlock]:
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
        if should_skip_content_line(text) and current_block is None:
            continue

        if "فصل" in text and "نیمه" in text:
            chapter_match = re.search(r"فصل\s*([0-9۰-۹٠-٩]+)", text)
            half_match = re.search(r"نیمه(?:[‌ ]?ی)?\s*(اول|دوم|سوم|چهارم)", text)
            if chapter_match and half_match:
                flush()
                current_block = SectionBlock(
                    chapter=digits_to_int(chapter_match.group(1)),
                    part=PART_INDEX[half_match.group(1)],
                    title="",
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
        if should_skip_content_line(text) and current_block is None:
            continue

        embedded_part_match = PETERSON_EMBEDDED_PART_RE.match(text)
        if embedded_part_match:
            flush()
            current_chapter = digits_to_int(embedded_part_match.group("chapter"))
            current_block = SectionBlock(
                chapter=current_chapter,
                part=part_to_int(embedded_part_match.group("part")),
                title=normalize_section_title(embedded_part_match.group("title") or current_title),
            )
            continue

        chapter_match = PETERSON_CHAPTER_RE.match(text)
        if chapter_match:
            flush()
            current_chapter = digits_to_int(chapter_match.group("chapter"))
            current_title = normalize_section_title(chapter_match.group("title") or "")
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


def build_section_title_from_match(header_match: re.Match[str], title_group: str | None) -> str:
    if not title_group:
        return ""
    title = (
        header_match.group(title_group)
        or header_match.groupdict().get("title_before")
        or header_match.groupdict().get(f"{title_group}_fallback")
        or ""
    )
    return normalize_section_title(title)


def split_section_blocks(
    lines: list[str],
    header_re: re.Pattern[str],
    *,
    part_group: str,
    title_group: str | None,
    answer_headers_inside: bool = False,
) -> list[SectionBlock]:
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
            if answer_headers_inside and is_answer_section_header(text):
                if current_block is not None:
                    current_block.lines.append(raw_line)
                continue
            flush()
            if part_group == "half":
                part = HALF_PART_INDEX[header_match.group("half")]
            else:
                part = part_to_int(header_match.group(part_group))
            current_block = SectionBlock(
                chapter=digits_to_int(header_match.group("chapter")),
                part=part,
                title=build_section_title_from_match(header_match, title_group),
            )
            continue

        if current_block is not None and (is_answer_marker_line(text) or not should_skip_content_line(text)):
            current_block.lines.append(raw_line)

    flush()
    return blocks


def split_chapter_only_blocks(
    lines: list[str],
    header_re: re.Pattern[str],
    *,
    allow_answer_headers_as_blocks: bool = False,
) -> list[SectionBlock]:
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
        if header_match and (allow_answer_headers_as_blocks or not is_answer_section_header(text)):
            flush()
            current_block = SectionBlock(
                chapter=digits_to_int(header_match.group("chapter")),
                part=1,
                title=normalize_section_title(header_match.group("title") or ""),
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
        pending.option_labels = [clean_inline(label) for label in pending.option_labels]
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
            pending.option_labels.append(clean_inline(option_match.group("label")))
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

        answer_match = ANSWER_ENTRY_RE.match(text) or ANSWER_PROMPT_RE.match(text) or ANSWER_QUESTION_PROMPT_RE.match(text)
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
            if is_answer_marker_line(clean):
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


def build_sections_from_chapter_answer_blocks(
    question_blocks: list[SectionBlock], answer_blocks: list[SectionBlock], *, label_style: str, path: Path
) -> list[ParsedSection]:
    answer_lookup = {
        block.chapter: parse_answer_lines(block.lines)
        for block in answer_blocks
    }
    sections: list[ParsedSection] = []
    for block in question_blocks:
        questions = parse_question_lines(block.lines)
        if not questions:
            sections.append(
                ParsedSection(
                    chapter=block.chapter,
                    part=block.part,
                    title=block.title,
                    label_style=label_style,
                    questions=[],
                    answers={},
                )
            )
            continue
        chapter_answers = answer_lookup.get(block.chapter, {})
        first_number = min(question.number for question in questions)
        last_number = max(question.number for question in questions)
        answers = {
            number: value
            for number, value in chapter_answers.items()
            if first_number <= number <= last_number
        }
        if not answers:
            raise ValueError(f"Could not map chapter-level answers for chapter {block.chapter} part {block.part} in {path.name}")
        sections.append(
            ParsedSection(
                chapter=block.chapter,
                part=block.part,
                title=block.title,
                label_style=label_style,
                questions=questions,
                answers=answers,
            )
        )

    return sections


def can_map_chapter_answer_blocks(question_blocks: list[SectionBlock], answer_blocks: list[SectionBlock]) -> bool:
    answer_lookup = {
        block.chapter: parse_answer_lines(block.lines)
        for block in answer_blocks
    }
    for block in question_blocks:
        questions = parse_question_lines(block.lines)
        if not questions:
            continue
        chapter_answers = answer_lookup.get(block.chapter, {})
        if not chapter_answers:
            return False
        if any(question.number not in chapter_answers for question in questions):
            return False
    return True


def parse_malamed_file(path: Path) -> list[ParsedSection]:
    lines = path.read_text(encoding="utf-8").splitlines()
    if any(clean_inline(line).startswith("بخش دوم:") for line in lines):
        question_lines, answer_lines = split_question_answer_halves(lines)
        question_blocks = split_malamed_blocks(question_lines)
        answer_blocks = split_malamed_blocks(answer_lines)
        if not question_blocks:
            question_blocks = split_malamed_named_half_blocks(question_lines)
        if not answer_blocks:
            answer_blocks = split_malamed_named_half_blocks(answer_lines)
        return build_sections_from_separate_blocks(question_blocks, answer_blocks, label_style="half")

    blocks = split_section_blocks(lines, MALAMED_COMBINED_SECTION_RE, part_group="half", title_group="title")
    if not blocks:
        blocks = split_malamed_named_half_blocks(lines)
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


def parse_chapter_part_separate_file(path: Path) -> list[ParsedSection]:
    lines = path.read_text(encoding="utf-8").splitlines()
    question_lines, answer_lines = split_question_answer_halves(lines)
    question_blocks = split_section_blocks(question_lines, PETERSON_SECTION_RE, part_group="part", title_group="title")
    answer_blocks = split_section_blocks(answer_lines, PETERSON_SECTION_RE, part_group="part", title_group="title")
    if not question_blocks:
        question_blocks = split_peterson_blocks(question_lines)
    if not answer_blocks:
        answer_blocks = split_peterson_blocks(answer_lines)
    question_keys = [(block.chapter, block.part) for block in question_blocks]
    answer_keys = [(block.chapter, block.part) for block in answer_blocks]
    if question_blocks and answer_blocks and question_keys == answer_keys:
        return build_sections_from_separate_blocks(question_blocks, answer_blocks, label_style="part")
    inline_blocks = split_section_blocks(
        lines,
        PETERSON_SECTION_RE,
        part_group="part",
        title_group="title",
        answer_headers_inside=True,
    )
    if inline_blocks:
        return build_sections_from_inline_blocks(inline_blocks, label_style="part", path=path)
    chapter_answer_blocks = split_chapter_only_blocks(
        answer_lines,
        CHAPTER_ONLY_SECTION_RE,
        allow_answer_headers_as_blocks=True,
    )
    if question_blocks and chapter_answer_blocks and can_map_chapter_answer_blocks(question_blocks, chapter_answer_blocks):
        return build_sections_from_chapter_answer_blocks(
            question_blocks,
            chapter_answer_blocks,
            label_style="part",
            path=path,
        )
    inline_peterson_blocks = split_peterson_blocks(lines)
    if inline_peterson_blocks:
        return build_sections_from_inline_blocks(inline_peterson_blocks, label_style="part", path=path)
    return build_sections_from_separate_blocks(question_blocks, answer_blocks, label_style="part")


def parse_chapter_part_inline_file(path: Path, *, header_re: re.Pattern[str]) -> list[ParsedSection]:
    lines = path.read_text(encoding="utf-8").splitlines()
    blocks = split_section_blocks(
        lines,
        header_re,
        part_group="part",
        title_group="title",
        answer_headers_inside=True,
    )
    return build_sections_from_inline_blocks(blocks, label_style="part", path=path)


def parse_chapter_only_inline_file(path: Path) -> list[ParsedSection]:
    lines = path.read_text(encoding="utf-8").splitlines()
    if any(clean_inline(line).startswith("بخش دوم:") for line in lines):
        question_lines, answer_lines = split_question_answer_halves(lines)
        question_blocks = split_chapter_only_blocks(question_lines, CHAPTER_ONLY_SECTION_RE)
        answer_blocks = split_chapter_only_blocks(
            answer_lines,
            CHAPTER_ONLY_SECTION_RE,
            allow_answer_headers_as_blocks=True,
        )
        return build_sections_from_separate_blocks(question_blocks, answer_blocks, label_style="chapter")
    blocks = split_chapter_only_blocks(lines, CHAPTER_ONLY_SECTION_RE)
    return build_sections_from_inline_blocks(blocks, label_style="chapter", path=path)


def parse_subsection_inline_file(path: Path) -> list[ParsedSection]:
    lines = path.read_text(encoding="utf-8").splitlines()
    try:
        question_lines, answer_lines = split_question_answer_halves(lines)
    except ValueError:
        question_lines = []
        answer_lines = []
    if question_lines and answer_lines:
        question_blocks = split_section_blocks(question_lines, SUBSECTION_SECTION_RE, part_group="part", title_group="title")
        answer_blocks = split_section_blocks(answer_lines, SUBSECTION_SECTION_RE, part_group="part", title_group="title")
        if question_blocks and answer_blocks and len(question_blocks) == len(answer_blocks):
            return build_sections_from_separate_blocks(question_blocks, answer_blocks, label_style="subsection")
    blocks = split_section_blocks(lines, SUBSECTION_SECTION_RE, part_group="part", title_group="title")
    return build_sections_from_inline_blocks(blocks, label_style="subsection", path=path)


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

    blocks = split_section_blocks(
        lines,
        WHITE_SECTION_RE,
        part_group="part",
        title_group="title",
        answer_headers_inside=True,
    )
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
    if style == "chapter-part-separate":
        return parse_chapter_part_separate_file(path)
    if style == "chapter-part-inline":
        return parse_chapter_part_inline_file(path, header_re=PETERSON_SECTION_RE)
    if style == "chapter-only-inline":
        return parse_chapter_only_inline_file(path)
    if style == "subsection-inline":
        return parse_subsection_inline_file(path)
    raise ValueError(f"Unsupported style: {style}")


def explanation_text(label: str, detail: str) -> str:
    prefix = f"**پاسخ درست:** گزینه {label}"
    return prefix if not detail else prefix + "\n" + detail


def section_label(section: ParsedSection) -> str:
    if section.label_style == "subsection":
        return f"زیرسکشن {to_persian_digits(section.chapter)}.{to_persian_digits(section.part)}"
    chapter_label = f"فصل {to_persian_digits(section.chapter)}"
    if section.label_style == "chapter":
        return chapter_label
    if section.label_style == "half":
        suffix = HALF_LABEL.get(section.part, f"نیمه {to_persian_digits(section.part)}")
    else:
        suffix = PART_LABEL.get(section.part, f"قسمت {to_persian_digits(section.part)}")
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
        if label_text not in question.option_labels:
            raise ValueError(
                f"{config.course_slug} {range_slug} {slug}: answer label {label_text!r} not found in question {question.number}"
            )
        questions_payload.append(
            {
                "question": question.text,
                "options": question.options,
                "correctIndex": question.option_labels.index(label_text),
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
    <script src="/assets/site/scripts/auth.js{version_suffix}"></script>
    <script src="/assets/site/scripts/exam-bootstrap.js{version_suffix}"></script>
    <script src="/assets/site/scripts/pwa.js{version_suffix}"></script>
    <script src="/assets/site/scripts/shell.js{version_suffix}"></script>
</body>
</html>
"""


def write_file(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")


def php_export(value, indent: int = 0) -> str:
    spacer = " " * indent
    child_indent = indent + 4
    child_spacer = " " * child_indent

    if value is None:
        return "null"
    if value is True:
        return "true"
    if value is False:
        return "false"
    if isinstance(value, (int, float)):
        return repr(value)
    if isinstance(value, str):
        escaped = value.replace("\\", "\\\\").replace("'", "\\'")
        return f"'{escaped}'"
    if isinstance(value, list):
        if not value:
            return "[]"
        lines = ["["]
        for item in value:
            lines.append(f"{child_spacer}{php_export(item, child_indent)},")
        lines.append(f"{spacer}]")
        return "\n".join(lines)
    if isinstance(value, dict):
        if not value:
            return "[]"
        lines = ["["]
        for key, item in value.items():
            key_export = php_export(str(key), child_indent)
            value_export = php_export(item, child_indent)
            lines.append(f"{child_spacer}{key_export} => {value_export},")
        lines.append(f"{spacer}]")
        return "\n".join(lines)
    raise TypeError(f"Unsupported PHP export type: {type(value)!r}")


def strip_questions_from_value(value):
    if isinstance(value, list):
        return [strip_questions_from_value(item) for item in value]
    if isinstance(value, dict):
        next_value = {}
        for key, item in value.items():
            if key == "questions":
                continue
            next_value[key] = strip_questions_from_value(item)
        return next_value
    return value


def course_data_function_name(slug: str) -> str:
    if not re.match(r"^[a-z][a-z0-9-]*$", slug):
        raise ValueError(f"Unsafe course slug for PHP identifier: {slug!r}")
    return "dent_exams_term6_reference_course_data_" + slug.replace("-", "_")


def load_existing_course_map(root: Path) -> dict[str, dict]:
    data_dir = root / "public_html" / "api" / "exams_term6_reference_data"
    php_files = sorted(data_dir.glob("*.php")) if data_dir.is_dir() else []
    if not php_files:
        return load_legacy_course_map(root)

    requires = "\n".join(f"require {str(path)!r};" for path in php_files)
    assigns = "\n".join(
        f"$map[{json.dumps(path.stem)}] = {course_data_function_name(path.stem)}();"
        for path in php_files
    )
    script = f"{requires}\n$map = [];\n{assigns}\necho json_encode($map, JSON_UNESCAPED_UNICODE);"

    try:
        result = subprocess.run(["php", "-r", script], check=True, capture_output=True, text=True, encoding="utf-8")
        decoded = json.loads(result.stdout.strip() or "{}")
    except Exception:
        return {}
    return decoded if isinstance(decoded, dict) else {}


def load_legacy_course_map(root: Path) -> dict[str, dict]:
    data_path = root / "public_html" / "api" / "exams_term6_reference_data.php"
    if not data_path.is_file():
        return {}

    text = data_path.read_text(encoding="utf-8")
    marker = "    $json = <<<'JSON'\n"
    decoded = None
    start = text.find(marker)
    if start >= 0:
        start += len(marker)
        end = text.find("\nJSON;", start)
        if end >= 0:
            payload = text[start:end]
            try:
                decoded = json.loads(payload)
            except json.JSONDecodeError:
                decoded = None
    if isinstance(decoded, dict):
        return decoded

    try:
        command = [
            "php",
            "-r",
            f"require {str(data_path)!r}; echo json_encode(dent_exams_term6_reference_course_map(), JSON_UNESCAPED_UNICODE);",
        ]
        result = subprocess.run(command, check=True, capture_output=True, text=True, encoding="utf-8")
        decoded = json.loads(result.stdout.strip() or "{}")
    except Exception:
        return {}
    return decoded if isinstance(decoded, dict) else {}


def build_course_map(root: Path, desktop_root: Path) -> tuple[dict[str, dict], list[str]]:
    course_map: dict[str, dict] = {}
    summary_lines: list[str] = []
    existing_course_map = load_existing_course_map(root)

    for config in SOURCES:
        subcourses: list[dict] = []
        for spec in config.range_specs:
            subcourse_slug = f"{config.course_slug}-{spec.range_slug}"
            source_path = desktop_root / config.folder_name / spec.filename
            if not source_path.is_file():
                source_path = desktop_root / spec.filename
            if not source_path.is_file():
                existing_subcourse = existing_course_map.get(subcourse_slug)
                if isinstance(existing_subcourse, dict):
                    subcourses.append(existing_subcourse)
                    course_map[subcourse_slug] = existing_subcourse
                    summary_lines.append(
                        f"WARNING: {config.course_slug}/{spec.range_slug}: source file missing; reused existing site data"
                    )
                    continue
                raise FileNotFoundError(f"Missing source file: {source_path}")

            sections = parse_sections(source_path, config.style)
            if not sections:
                raise ValueError(f"No sections parsed from {source_path}")

            session_exams = [
                build_session_exam(config, subcourse_slug, spec.range_slug, spec.range_label, section)
                for section in sections
            ]
            subcourse = build_subcourse(config, spec, session_exams)
            subcourses.append(subcourse)
            course_map[subcourse_slug] = subcourse

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


def write_course_data_files(root: Path, course_map: dict[str, dict]) -> None:
    data_dir = root / "public_html" / "api" / "exams_term6_reference_data"
    data_dir.mkdir(parents=True, exist_ok=True)

    written_paths: set[Path] = set()
    for slug, course in course_map.items():
        function_name = course_data_function_name(slug)
        php_payload = php_export(course, 4)
        content = (
            "<?php\n"
            "declare(strict_types=1);\n\n"
            f"function {function_name}(): array\n"
            "{\n"
            f"    return {php_payload};\n"
            "}\n"
        )
        file_path = data_dir / f"{slug}.php"
        write_file(file_path, content)
        written_paths.add(file_path)

    for stale_path in data_dir.glob("*.php"):
        if stale_path not in written_paths:
            stale_path.unlink()


def write_php_data(root: Path, course_map: dict[str, dict]) -> None:
    write_course_data_files(root, course_map)

    legacy_path = root / "public_html" / "api" / "exams_term6_reference_data.php"
    if legacy_path.is_file():
        legacy_path.unlink()

    catalog_payload = php_export(strip_questions_from_value(course_map), 4)
    catalog_content = (
        "<?php\n"
        "declare(strict_types=1);\n\n"
        "function dent_exams_term6_reference_catalog_course_map(): array\n"
        "{\n"
        "    static $courses = null;\n"
        "    if (is_array($courses)) {\n"
        "        return $courses;\n"
        "    }\n\n"
        f"    $courses = {catalog_payload};\n"
        "    return $courses;\n"
        "}\n"
    )
    write_file(root / "public_html" / "api" / "exams_term6_reference_catalog_data.php", catalog_content)


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
    parser.add_argument(
        "--migrate",
        action="store_true",
        help="Split the legacy monolithic exams_term6_reference_data.php into per-course files and exit.",
    )
    args = parser.parse_args()

    repo_root = args.repo_root.resolve()
    desktop_root = args.desktop_root.resolve()

    if args.migrate:
        course_map = load_legacy_course_map(repo_root)
        if not course_map:
            raise SystemExit("No legacy course map data found to migrate.")
        write_course_data_files(repo_root, course_map)
        legacy_path = repo_root / "public_html" / "api" / "exams_term6_reference_data.php"
        if legacy_path.is_file():
            legacy_path.unlink()
        print(f"Migrated {len(course_map)} courses into per-course files.")
        return

    asset_version = extract_asset_version(repo_root)

    course_map, summary_lines = build_course_map(repo_root, desktop_root)
    write_php_data(repo_root, course_map)
    write_shells(repo_root, course_map, asset_version)

    for line in summary_lines:
        print(line)


if __name__ == "__main__":
    main()
