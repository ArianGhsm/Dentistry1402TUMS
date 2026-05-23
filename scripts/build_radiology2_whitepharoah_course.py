#!/usr/bin/env python3
from __future__ import annotations

import argparse
import html
import json
import math
import re
import unicodedata
from collections import Counter
from dataclasses import dataclass
from pathlib import Path

import fitz


PDF_GLOB = "*WhitePharoah*.pdf"
COMPLETE_HINT = "PastedText2"
NO_OPTION_LABEL = "گزینه ندارد"
QUESTION_RE = re.compile(r"^س[ؤو]ال\s+([0-9۰-۹٠-٩]+)$")
PERSIAN_TOKEN_RE = re.compile(r"[\u0600-\u06FF]+")
TEXT_TOKEN_RE = re.compile(r"[\u0600-\u06FF]+|[A-Za-z0-9/&+_.:-]+")
LATIN_RUN_RE = re.compile(r"[A-Za-z0-9/%&+_.:=,\-]+(?:\s+[A-Za-z0-9/%&+_.:=,\-]+)*")
INLINE_OPTION_RE = re.compile(
    r"^(?P<question>.+?[؟?])\s*[\)\]]?\s*(?P<label>الف|ب|ج|د|[۱۲۳۴1-4])\s*[\(\)]*\s*(?P<rest>.*)$"
)
FILE_ANSWER_RE = re.compile(r"^پاسخ\s+فایل\s*:?\s*(?P<tail>.*)$")
WHITE_ANSWER_RE = re.compile(
    r"^پاسخ\s+(?:وایت(?:\s*فارو)?|White\s*&\s*Pharoah)\s*:?\s*(?P<tail>.*)$",
    re.IGNORECASE,
)
WHITE_CONTINUATION_RE = re.compile(r"^فارو\s*:?\s*(?P<tail>.*)$")
REASON_RE = re.compile(r"^علت\s*:?\s*(?P<tail>.*)$")
EMBEDDED_WHITE_ANSWER_RE = re.compile(
    r"^(?P<file>.*?)\s+پاسخ\s+(?:وایت(?:\s*فارو)?|White\s*&\s*Pharoah)\s*:?\s*(?P<white>.+)$",
    re.IGNORECASE,
)

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

PERSIAN_DIGITS = "۰۱۲۳۴۵۶۷۸۹"
ARABIC_DIGITS = "٠١٢٣٤٥٦٧٨٩"
ASCII_DIGITS = "0123456789"
DIGIT_TRANS = str.maketrans(PERSIAN_DIGITS + ARABIC_DIGITS, ASCII_DIGITS + ASCII_DIGITS)

COMMON_WORDS = {
    "از",
    "است",
    "انتخاب",
    "این",
    "اول",
    "ایجاد",
    "با",
    "باعث",
    "برای",
    "بافت",
    "بیشترین",
    "بین",
    "به",
    "پاسخ",
    "پانورامیک",
    "پرتو",
    "پزشکی",
    "پری",
    "پروگزیمال",
    "تصویر",
    "تصویربرداری",
    "توضیح",
    "تکنیک",
    "تکنیکی",
    "تواند",
    "توسط",
    "تحت",
    "تماس",
    "جسم",
    "جهت",
    "حین",
    "خلفی",
    "در",
    "دندان",
    "دندانها",
    "دارد",
    "دوز",
    "را",
    "رادیوگرافی",
    "روی",
    "زمان",
    "زیر",
    "سطح",
    "سنگ",
    "سوال",
    "شود",
    "شده",
    "صحیح",
    "طبق",
    "علت",
    "علامت",
    "فاصله",
    "فایل",
    "فک",
    "فضا",
    "قرار",
    "قابل",
    "گزینه",
    "کم",
    "کمتر",
    "که",
    "کند",
    "کندگی",
    "لاین",
    "لبه",
    "لینگوال",
    "می",
    "مخاط",
    "محل",
    "مورد",
    "مولر",
    "ناحیه",
    "نشان",
    "نسبت",
    "نیست",
    "نیز",
    "هم",
    "های",
    "وايت",
    "وایت",
    "وضعیت",
    "وگز",
    "وگسل",
    "وکسل",
    "وایت",
    "یابد",
    "یافته",
    "یونیزان",
    "افتد",
    "بهتر",
    "ترین",
    "تری",
    "رود",
    "شود",
    "شوند",
    "شدن",
    "همراه",
    "هستند",
}

ARTIFACT_SNIPPETS = (
    "نام فایل مبدا",
    "پاسخنامه تشریحی",
    "تعداد سوالات",
    "فونت",
    ".zip | بخش",
    "Paste شده نیامده است",
    "Pasted text",
)

FINAL_TEXT_REGEX_REPLACEMENTS: tuple[tuple[str, str], ...] = (
    (r":?file_[0-9۰-۹]+\.pdf", ""),
    (r"White & Pharoah\s*-\s*Pasted text\s*[0-9۰-۹]+\s*\|\s*صفحه\s*[0-9۰-۹]+", ""),
    (r"گزینه ها در متن Paste شده نیامده است", ""),
    (r"Paste شده نیامده است", ""),
    (r"\)\s*بدون گزینه\)?", ""),
)

FINAL_TEXT_REPLACEMENTS: tuple[tuple[str, str], ...] = (
    ("می.دتفا", "می افتد"),
    ("می.دهد", "می دهد"),
    ("می.شود", "می شود"),
    ("می.داند", "می داند"),
    ("می.دهند", "می دهند"),
    ("به کار.دور می", "به کار می رود"),
    ("همرها", "همراه"),
    (" CT؛دراد را یونیزان پرتو دوز بیشترین پزشکی", " CT بیشترین دوز پرتو یونیزان را در میان گزینه ها دارد"),
    ("CT؛دراد را یونیزان پرتو دوز بیشترین پزشکی", "CT بیشترین دوز پرتو یونیزان را در میان گزینه ها دارد"),
    ("scatter ،", "scatter،"),
    ("س ialography", "Sialography"),
    ("PSP latitude", "PSP latitude"),
    ("اگر یک گزینه خواسته دوش ب دقیق تر است", "اگر یک گزینه خواسته شود، گزینه ب دقیق تر است"),
    ("وقتی رخ می partial volume averaging دهد", "وقتی partial volume averaging رخ می دهد"),
    (
        "گزینه ج - CEJ تحت هر شرایطی رفرنس معتبر است:تلع CEJ سطح برای یبسانم سنرفر ،دشابن دامتعا قابل تصویربرداری یاطخ ای شیاس ،نوارک ،میمرت علت به یتقو استخوان آلویول نیست",
        "گزینه ج - CEJ تحت هر شرایطی رفرنس معتبر است علت: CEJ همیشه رفرنس مناسبی برای سطح استخوان آلویول نیست و وقتی به علت ترمیم، کراون، سایش یا خطای تصویربرداری قابل اعتماد نباشد نباید تنها رفرنس قرار گیرد",
    ),
    (
        "گزینه د - حرکت تیوب و receptor:تلع focal trough طقف است؛ یفارگونکسا/یفارگوموت لصا و پانورامیک در گیرنده و پرتو منبع گنهامه حرکت لصاح اشیای لایه انتخابی واضح می افتند",
        "گزینه د - حرکت تیوب و receptor علت: در پانورامیک و اصل توموگرافی/اسکنوگرافی، حرکت هماهنگ منبع پرتو و گیرنده اهمیت اصلی را دارد؛ focal trough فقط باعث می شود اشیای لایه انتخابی واضح دیده شوند",
    ),
)


@dataclass
class ParsedQuestion:
    source_kind: str
    source_question_number: str
    question: str
    options: list[str]
    correct_index: int
    explanation: str
    has_placeholder_options: bool


def to_ascii_digits(value: str) -> str:
    return value.translate(DIGIT_TRANS)


def digits_to_int(value: str) -> int:
    digits = re.sub(r"[^0-9]", "", to_ascii_digits(value))
    return int(digits) if digits else 0


def normalize_chars(text: str) -> str:
    return unicodedata.normalize("NFKC", text).translate(ARABIC_TO_PERSIAN)


def collapse_spaces(text: str) -> str:
    return re.sub(r"\s+", " ", text).strip()


def smart_reverse_preserving_latin(text: str) -> str:
    placeholders: list[str] = []

    def replace_latin(match: re.Match[str]) -> str:
        placeholders.append(match.group(0))
        index = len(placeholders) - 1
        return f"@@{index}@@"

    replaced = LATIN_RUN_RE.sub(replace_latin, text)
    reversed_text = replaced[::-1]
    for index, value in enumerate(placeholders):
        reversed_text = reversed_text.replace(f"@@{index}@@", value)
    return reversed_text


def tokenize_text(text: str) -> list[str]:
    return TEXT_TOKEN_RE.findall(text)


def reverse_token_if_better(token: str, token_freq: Counter[str]) -> str:
    if not PERSIAN_TOKEN_RE.fullmatch(token):
        return token

    normalized = token
    reversed_token = normalized[::-1]
    if normalized == reversed_token:
        return normalized

    current_freq = token_freq[normalized]
    reversed_freq = token_freq[reversed_token]
    if reversed_token in COMMON_WORDS and normalized not in COMMON_WORDS:
        return reversed_token
    if reversed_freq >= 2 and reversed_freq > current_freq * 2:
        return reversed_token
    return normalized


def normalize_candidate(line: str, token_freq: Counter[str]) -> str:
    source = normalize_chars(line)
    parts = re.split(r"(\s+)", source)
    fixed_parts: list[str] = []
    for part in parts:
        if not part or part.isspace():
            fixed_parts.append(part)
            continue
        if PERSIAN_TOKEN_RE.fullmatch(part):
            fixed_parts.append(reverse_token_if_better(part, token_freq))
            continue
        fixed_parts.append(part)

    text = "".join(fixed_parts)
    text = re.sub(r"(?<=[A-Za-z0-9])(?=[\u0600-\u06FF])", " ", text)
    text = re.sub(r"(?<=[\u0600-\u06FF])(?=[A-Za-z0-9])", " ", text)
    text = re.sub(r"\s+([.,؛:!?/)\]])", r"\1", text)
    text = re.sub(r"([(/])\s+", r"\1", text)
    text = collapse_spaces(text)
    if text.endswith("علت:"):
        text = "علت: " + collapse_spaces(text[:-5])
    return text


def build_token_frequency(raw_lines: list[str]) -> Counter[str]:
    frequency: Counter[str] = Counter()
    for line in raw_lines:
        for token in tokenize_text(normalize_chars(line)):
            if PERSIAN_TOKEN_RE.fullmatch(token):
                frequency[token] += 1
    return frequency


def build_bigram_frequency(lines: list[str]) -> Counter[tuple[str, str]]:
    bigrams: Counter[tuple[str, str]] = Counter()
    for line in lines:
        tokens = [token for token in tokenize_text(line) if token]
        for left, right in zip(tokens, tokens[1:]):
            bigrams[(left, right)] += 1
    return bigrams


def candidate_score(text: str, token_freq: Counter[str], bigram_freq: Counter[tuple[str, str]]) -> float:
    tokens = [token for token in tokenize_text(text) if token]
    score = 0.0
    for token in tokens:
        normalized = token.lower() if LATIN_RUN_RE.fullmatch(token) else token
        if normalized in COMMON_WORDS:
            score += 3.0
        score += math.log(token_freq[token] + 1, 2)
        reversed_token = token[::-1]
        if reversed_token in COMMON_WORDS and token not in COMMON_WORDS:
            score -= 2.25
        if token.startswith("می") or token.startswith("نمی"):
            score += 1.0
        if token.endswith(("ها", "های", "تر", "تری", "ترین", "شود", "شده", "دارد", "کند")):
            score += 0.6
    for left, right in zip(tokens, tokens[1:]):
        score += math.log(bigram_freq[(left, right)] + 1, 2) * 1.15
    if text and text[0] in "؛:.,!?":
        score -= 2.5
    if any(snippet in text for snippet in ARTIFACT_SNIPPETS):
        score -= 4.0
    return score


def choose_best_line(line: str, token_freq: Counter[str], bigram_freq: Counter[tuple[str, str]]) -> str:
    candidate_a = normalize_candidate(line, token_freq)
    candidate_b = normalize_candidate(smart_reverse_preserving_latin(line), token_freq)
    score_a = candidate_score(candidate_a, token_freq, bigram_freq)
    score_b = candidate_score(candidate_b, token_freq, bigram_freq)
    chosen = candidate_b if score_b > score_a + 0.75 else candidate_a
    chosen = chosen.replace("اه ", "ها ")
    chosen = chosen.replace("اه.", "ها.")
    chosen = collapse_spaces(chosen)
    return chosen


def should_skip_line(line: str) -> bool:
    if not line:
        return True
    if line in {".", "سوال", "سؤال"}:
        return True
    lowered = line.lower()
    if lowered.startswith("white & pharoah"):
        return True
    if "پاسخنامه تشریحی" in line:
        return True
    if line.startswith("بر اساس"):
        return True
    if line.startswith("تعداد سوالات"):
        return True
    if line.startswith("فونت"):
        return True
    if line.startswith("نام فایل مبدا"):
        return True
    if "| صفحه" in line:
        return True
    if line.startswith("Pasted text"):
        return True
    if line == "Paste شده":
        return True
    if line == ".zip":
        return True
    if line.startswith(".zip | بخش"):
        return True
    if line.startswith(".pdf"):
        return True
    return False


def extract_pdf_lines(path: Path) -> list[str]:
    doc = fitz.open(path)
    raw_lines: list[str] = []
    for page in doc:
        page_text = normalize_chars(page.get_text("text"))
        for raw_line in page_text.splitlines():
            line = collapse_spaces(raw_line)
            if line:
                raw_lines.append(line)

    token_freq = build_token_frequency(raw_lines)
    initial_lines = [normalize_candidate(line, token_freq) for line in raw_lines]
    bigram_freq = build_bigram_frequency(initial_lines)
    final_lines = [choose_best_line(line, token_freq, bigram_freq) for line in raw_lines]
    return [line for line in final_lines if not should_skip_line(line)]


def split_question_blocks(lines: list[str]) -> list[tuple[str, list[str]]]:
    blocks: list[tuple[str, list[str]]] = []
    current_marker: str | None = None
    current_lines: list[str] = []

    for line in lines:
        match = QUESTION_RE.match(line)
        if match:
            if current_marker is not None:
                blocks.append((current_marker, current_lines))
            current_marker = match.group(1)
            current_lines = []
            continue

        if current_marker is not None:
            current_lines.append(line)

    if current_marker is not None:
        blocks.append((current_marker, current_lines))
    return blocks


def option_index_from_label(label: str) -> int | None:
    cleaned = collapse_spaces(label)
    ascii_digits = to_ascii_digits(cleaned)
    if ascii_digits in {"1", "2", "3", "4"}:
        return int(ascii_digits) - 1
    mapping = {"الف": 0, "ب": 1, "ج": 2, "د": 3}
    return mapping.get(cleaned)


def parse_option_line(line: str) -> tuple[int, str] | None:
    work = line.strip()
    if not work.startswith("("):
        return None

    work = work.lstrip("(").lstrip()
    work = work.lstrip(")").lstrip()
    for label in ("الف", "ب", "ج", "د", "۱", "۲", "۳", "۴", "1", "2", "3", "4"):
        if work.startswith(label):
            option_index = option_index_from_label(label)
            if option_index is None:
                continue
            remainder = work[len(label):].lstrip(") (:-")
            return option_index, collapse_spaces(remainder)
    return None


def strip_answer_prefix(line: str, prefix: str) -> str:
    if line.startswith(prefix):
        return collapse_spaces(line[len(prefix):].lstrip(": "))
    return line


def join_parts(parts: list[str]) -> str:
    return collapse_spaces(" ".join(part for part in parts if part and part != "."))


def extract_prefixed_tail(line: str, pattern: re.Pattern[str]) -> str | None:
    match = pattern.match(line)
    if match is None:
        return None
    return collapse_spaces(match.group("tail") or "")


def split_inline_option(line: str) -> tuple[str, int, str] | None:
    match = INLINE_OPTION_RE.match(line)
    if match is None:
        return None

    option_index = option_index_from_label(match.group("label"))
    if option_index is None:
        return None

    question_part = collapse_spaces(match.group("question"))
    option_text = collapse_spaces(match.group("rest"))
    return question_part, option_index, option_text


def split_embedded_white_answer(file_answer: str, white_answer: str) -> tuple[str, str]:
    if white_answer:
        return file_answer, white_answer

    match = EMBEDDED_WHITE_ANSWER_RE.match(file_answer)
    if match is None:
        return file_answer, white_answer

    embedded_file = collapse_spaces(match.group("file"))
    embedded_white = collapse_spaces(match.group("white"))
    return embedded_file, embedded_white


def split_embedded_reason(answer: str) -> tuple[str, str]:
    cleaned_answer = cleanup_final_text(answer)
    for marker in (" علت: ", " علت :", "علت: "):
        if marker not in cleaned_answer:
            continue
        head, tail = cleaned_answer.split(marker, 1)
        return collapse_spaces(head), cleanup_final_text(tail)
    return cleaned_answer, ""


def cleanup_final_text(text: str) -> str:
    text = normalize_chars(text)
    text = collapse_spaces(text)
    for pattern, replacement in FINAL_TEXT_REGEX_REPLACEMENTS:
        text = re.sub(pattern, replacement, text)
    for source, target in FINAL_TEXT_REPLACEMENTS:
        text = text.replace(source, target)
    text = re.sub(r"\s+([.,؛:!?/)\]])", r"\1", text)
    text = re.sub(r"([(/])\s+", r"\1", text)
    text = re.sub(r"\s{2,}", " ", text)
    text = text.replace("CT scan", "CT-scan")
    text = text.replace("CBCT نسبت به CT می باشد؟", "محدودیت CBCT نسبت به CT می باشد؟")
    text = text.replace("منظور از receptor", "منظور از receptor")
    return text.strip()


def cleanup_question_text(text: str) -> str:
    question = cleanup_final_text(text)
    question = re.sub(r"\s+\)", ")", question)
    question = re.sub(r"\(\s+", "(", question)
    return collapse_spaces(question)


def parse_option_choice(text: str, options: list[str]) -> int | None:
    cleaned = cleanup_final_text(text)
    match = re.search(r"گزينه\s+([الفبجد]|[۱۲۳۴1-4])", cleaned)
    if match:
        return option_index_from_label(match.group(1))

    for index, option in enumerate(options):
        option_text = cleanup_final_text(option)
        if option_text and option_text in cleaned:
            return index
    return None


def format_explanation(file_answer: str, white_answer: str, reason: str) -> str:
    parts = []
    if file_answer:
        parts.append(f"**پاسخ فایل:** {file_answer}")
    if white_answer:
        parts.append(f"**پاسخ White & Pharoah:** {white_answer}")
    if reason:
        parts.append(f"**توضیح:** {reason}")
    return "\n".join(parts)


def parse_question_block(source_kind: str, source_question_number: str, lines: list[str]) -> ParsedQuestion:
    question_lines: list[str] = []
    options_by_index: dict[int, list[str]] = {}
    file_answer_lines: list[str] = []
    white_answer_lines: list[str] = []
    reason_lines: list[str] = []
    state = "question"
    current_option: int | None = None

    for line in lines:
        if line.startswith("پاسخ فایل"):
            state = "file"
            current_option = None
            tail = strip_answer_prefix(line, "پاسخ فایل")
            if tail:
                file_answer_lines.append(tail)
            continue

        if line.startswith("پاسخ وايت"):
            state = "white"
            current_option = None
            tail = strip_answer_prefix(line, "پاسخ وايت")
            if tail:
                white_answer_lines.append(tail)
            continue

        if line.startswith("فارو:"):
            if state == "white":
                tail = strip_answer_prefix(line, "فارو")
                if tail:
                    white_answer_lines.append(tail)
                continue

        if line.startswith("علت:"):
            state = "reason"
            current_option = None
            tail = strip_answer_prefix(line, "علت")
            if tail:
                reason_lines.append(tail)
            continue

        if state in {"question", "options"}:
            parsed_option = parse_option_line(line)
            if parsed_option is not None:
                state = "options"
                option_index, option_text = parsed_option
                current_option = option_index
                options_by_index.setdefault(option_index, [])
                if option_text:
                    options_by_index[option_index].append(option_text)
                continue

        if state == "question":
            question_lines.append(line)
            continue

        if state == "options":
            if current_option is not None:
                options_by_index.setdefault(current_option, []).append(line)
            else:
                question_lines.append(line)
            continue

        if state == "file":
            file_answer_lines.append(line)
            continue

        if state == "white":
            white_answer_lines.append(line)
            continue

        if state == "reason":
            reason_lines.append(line)

    question = cleanup_final_text(join_parts(question_lines))
    file_answer = cleanup_final_text(join_parts(file_answer_lines))
    white_answer = cleanup_final_text(join_parts(white_answer_lines))
    reason = cleanup_final_text(join_parts(reason_lines))

    options: list[str] = []
    has_placeholder_options = False
    if not options_by_index:
        options = [NO_OPTION_LABEL, NO_OPTION_LABEL, NO_OPTION_LABEL, NO_OPTION_LABEL]
        has_placeholder_options = True
    else:
        for option_index in range(4):
            option_text = cleanup_final_text(join_parts(options_by_index.get(option_index, [])))
            if option_text:
                options.append(option_text)
            else:
                options.append(NO_OPTION_LABEL)
                has_placeholder_options = True

    explanation = format_explanation(file_answer, white_answer, reason)
    correct_index = parse_option_choice(file_answer, options)
    if correct_index is None:
        correct_index = parse_option_choice(white_answer, options)
    if correct_index is None:
        correct_index = 0

    return ParsedQuestion(
        source_kind=source_kind,
        source_question_number=source_question_number,
        question=question,
        options=options,
        correct_index=correct_index,
        explanation=explanation,
        has_placeholder_options=has_placeholder_options,
    )


def parse_question_block(source_kind: str, source_question_number: str, lines: list[str]) -> ParsedQuestion:
    question_lines: list[str] = []
    options_by_index: dict[int, list[str]] = {}
    file_answer_lines: list[str] = []
    white_answer_lines: list[str] = []
    reason_lines: list[str] = []
    state = "question"
    current_option: int | None = None

    for line in lines:
        file_tail = extract_prefixed_tail(line, FILE_ANSWER_RE)
        if file_tail is not None:
            state = "file"
            current_option = None
            if file_tail:
                file_answer_lines.append(file_tail)
            continue

        white_tail = extract_prefixed_tail(line, WHITE_ANSWER_RE)
        if white_tail is not None:
            state = "white"
            current_option = None
            if white_tail:
                white_answer_lines.append(white_tail)
            continue

        white_continuation_tail = extract_prefixed_tail(line, WHITE_CONTINUATION_RE)
        if white_continuation_tail is not None:
            if state == "white" and white_continuation_tail:
                white_answer_lines.append(white_continuation_tail)
            continue

        reason_tail = extract_prefixed_tail(line, REASON_RE)
        if reason_tail is not None:
            state = "reason"
            current_option = None
            if reason_tail:
                reason_lines.append(reason_tail)
            continue

        if state in {"question", "options"}:
            parsed_option = parse_option_line(line)
            if parsed_option is not None:
                state = "options"
                option_index, option_text = parsed_option
                current_option = option_index
                options_by_index.setdefault(option_index, [])
                if option_text:
                    options_by_index[option_index].append(option_text)
                continue

        if state == "question":
            inline_option = split_inline_option(line)
            if inline_option is not None:
                question_part, option_index, option_text = inline_option
                if question_part:
                    question_lines.append(question_part)
                state = "options"
                current_option = option_index
                options_by_index.setdefault(option_index, [])
                if option_text:
                    options_by_index[option_index].append(option_text)
                continue

            question_lines.append(line)
            continue

        if state == "options":
            if current_option is not None:
                options_by_index.setdefault(current_option, []).append(line)
            else:
                question_lines.append(line)
            continue

        if state == "file":
            file_answer_lines.append(line)
            continue

        if state == "white":
            white_answer_lines.append(line)
            continue

        if state == "reason":
            reason_lines.append(line)

    question = cleanup_question_text(join_parts(question_lines))
    file_answer = cleanup_final_text(join_parts(file_answer_lines))
    white_answer = cleanup_final_text(join_parts(white_answer_lines))
    reason = cleanup_final_text(join_parts(reason_lines))
    file_answer, white_answer = split_embedded_white_answer(file_answer, white_answer)
    file_answer, embedded_file_reason = split_embedded_reason(file_answer)
    white_answer, embedded_white_reason = split_embedded_reason(white_answer)
    if not reason:
        reason = embedded_white_reason or embedded_file_reason
    elif embedded_white_reason and embedded_white_reason not in reason:
        reason = cleanup_final_text(reason + " " + embedded_white_reason)

    options: list[str] = []
    has_placeholder_options = False
    if not options_by_index:
        options = [NO_OPTION_LABEL, NO_OPTION_LABEL, NO_OPTION_LABEL, NO_OPTION_LABEL]
        has_placeholder_options = True
    else:
        for option_index in range(4):
            option_text = cleanup_final_text(join_parts(options_by_index.get(option_index, [])))
            if option_text:
                options.append(option_text)
            else:
                options.append(NO_OPTION_LABEL)
                has_placeholder_options = True

    explanation = format_explanation(file_answer, white_answer, reason)
    correct_index = parse_option_choice(file_answer, options)
    if correct_index is None:
        correct_index = parse_option_choice(white_answer, options)
    if correct_index is None:
        correct_index = 0

    return ParsedQuestion(
        source_kind=source_kind,
        source_question_number=source_question_number,
        question=question,
        options=options,
        correct_index=correct_index,
        explanation=explanation,
        has_placeholder_options=has_placeholder_options,
    )


def source_files(pdf_dir: Path) -> list[tuple[str, Path]]:
    candidates = list(pdf_dir.glob(PDF_GLOB))
    if not candidates:
        raise FileNotFoundError(f"No source PDF matched {PDF_GLOB!r} in {pdf_dir}")

    complete = next((path for path in candidates if COMPLETE_HINT in path.name), None)
    sample = next((path for path in candidates if COMPLETE_HINT not in path.name), None)
    if complete is None or sample is None:
        raise FileNotFoundError("Could not resolve both sample and complete WhitePharoah PDFs.")

    return [("sample", sample), ("complete", complete)]


def build_course_payload(questions: list[ParsedQuestion]) -> dict:
    question_payload = []
    for display_number, item in enumerate(questions, start=1):
        question_payload.append(
            {
                "question": item.question,
                "options": item.options,
                "correctIndex": item.correct_index,
                "explanation": item.explanation,
                "meta": {
                    "displayNumber": display_number,
                    "sourceKind": item.source_kind,
                    "sourceQuestionNumber": item.source_question_number,
                    "hasPlaceholderOptions": item.has_placeholder_options,
                },
            }
        )

    return {
        "slug": "radiology2-whitepharoah",
        "title": "نمونه سوالات رادیولوژی نظری ۲",
        "shortTitle": "رادیولوژی ۲ | نمونه سوال",
        "badge": "۱ آزمون",
        "cardDescription": "مجموع سوالات دو فایل White & Pharoah در یک آزمون یکپارچه.",
        "heroTitle": "نمونه سوالات رادیولوژی نظری ۲",
        "heroDescription": "همه سوالات دو فایل پاسخنامه White & Pharoah در یک آزمون تجمیع شده است و برای هر سوال هم پاسخ فایل، هم پاسخ White & Pharoah و هم توضیح آمده است.",
        "qualityGuard": True,
        "path": "/exams/radiology2-whitepharoah/",
        "paymentTitle": "دسترسی به نمونه سوالات رادیولوژی نظری ۲",
        "paymentDescription": "با یک بار پرداخت ۲۰ هزار تومانی، همین آزمون یکپارچه برای همین حساب باز می‌شود.",
        "paymentSuccessMessage": "پرداخت شما تایید شد و آزمون نمونه سوالات رادیولوژی نظری ۲ برای این حساب باز شد.",
        "paymentFailureMessage": "پرداخت تایید نشد. در صورت کسر وجه، نتیجه را از همین صفحه دوباره بررسی کنید.",
        "defaultPaymentMode": "paid",
        "defaultAmount": 200000,
        "exams": [
            {
                "slug": "1",
                "path": "/exams/radiology2-whitepharoah/1/",
                "questionCount": len(question_payload),
                "label": "آزمون ۱",
                "title": "آزمون کامل White & Pharoah",
                "subtitle": "همه سوالات دو فایل با شماره‌گذاری یکپارچه و پاسخ تشریحی خوانا.",
                "description": "در این آزمون، پاسخ فایل و پاسخ White & Pharoah هر سوال همراه با توضیح آمده است.",
                "eyebrow": "آزمون تمرینی رادیولوژی نظری ۲ | White & Pharoah",
                "backHref": "/exams/radiology2-whitepharoah/",
                "backLabel": "بازگشت به فهرست آزمون‌های این درس",
                "autoAdvance": True,
                "siteTitle": "ورودی ۱۴۰۲ دندانپزشکی تهران",
                "siteSubtitle": "آزمون‌ها",
                "siteBadge": "آزمون تمرینی",
                "footerText": "ورودی ۱۴۰۲ دندانپزشکی تهران",
                "questions": question_payload,
            }
        ],
    }


def write_php_data(target: Path, payload: dict) -> None:
    course_json = json.dumps(payload, ensure_ascii=False, indent=2)
    php = (
        "<?php\n"
        "declare(strict_types=1);\n\n"
        "function dent_exams_radiology2_whitepharoah_course(): array\n"
        "{\n"
        "    $json = <<<'JSON'\n"
        f"{course_json}\n"
        "JSON;\n\n"
        "    $course = json_decode($json, true, 512, JSON_THROW_ON_ERROR);\n"
        "    return is_array($course) ? $course : [];\n"
        "}\n"
    )
    target.write_text(php, encoding="utf-8", newline="\n")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--pdf-dir", required=True)
    parser.add_argument("--output-php", required=True)
    args = parser.parse_args()

    questions: list[ParsedQuestion] = []
    for source_kind, path in source_files(Path(args.pdf_dir)):
        lines = extract_pdf_lines(path)
        blocks = split_question_blocks(lines)
        for source_question_number, block_lines in blocks:
            questions.append(parse_question_block(source_kind, source_question_number, block_lines))

    payload = build_course_payload(questions)
    write_php_data(Path(args.output_php), payload)

    summary = {
        "questionCount": len(questions),
        "placeholderCount": sum(1 for item in questions if item.has_placeholder_options),
        "sources": {
            "sample": sum(1 for item in questions if item.source_kind == "sample"),
            "complete": sum(1 for item in questions if item.source_kind == "complete"),
        },
    }
    print(json.dumps(summary, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
