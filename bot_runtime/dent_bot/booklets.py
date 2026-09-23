from __future__ import annotations

import html
import re
from dataclasses import dataclass

from .ai_booklets import (
    AI_BOOKLET_CONTENT_KIND,
    AI_BOOKLET_PRICE_RIALS,
    AI_BOOKLET_SOURCE_TAG_KEY,
)
from .persian_datetime import to_persian_digits
from .ui import Screen, button, format_rials, keyboard


RESOURCE_LABELS = {
    "voice": "🎤 ویس",
    "power": "📒 پاور",
    "booklet": "📓 جزوه",
    "reference": "📘 رفرنس",
    AI_BOOKLET_CONTENT_KIND: "🤖 جزوه هوش مصنوعی",
}

_ORDINALS = {
    1: "اول", 2: "دوم", 3: "سوم", 4: "چهارم", 5: "پنجم", 6: "ششم", 7: "هفتم",
    8: "هشتم", 9: "نهم", 10: "دهم", 11: "یازدهم", 12: "دوازدهم", 13: "سیزدهم",
    14: "چهاردهم", 15: "پانزدهم", 16: "شانزدهم", 17: "هفدهم", 18: "هجدهم",
    19: "نوزدهم", 20: "بیستم", 21: "بیست و یکم", 22: "بیست و دوم",
    23: "بیست و سوم", 24: "بیست و چهارم", 25: "بیست و پنجم", 26: "بیست و ششم",
    27: "بیست و هفتم", 28: "بیست و هشتم", 29: "بیست و نهم", 30: "سی‌ام",
    31: "سی و یکم", 32: "سی و دوم", 33: "سی و سوم", 34: "سی و چهارم",
    35: "سی و پنجم", 36: "سی و ششم", 37: "سی و هفتم", 38: "سی و هشتم",
    39: "سی و نهم", 40: "چهلم",
}


def _normalized(value: str) -> str:
    return " ".join(
        str(value)
        .replace("ي", "ی")
        .replace("ى", "ی")
        .replace("ك", "ک")
        .replace("‌", " ")
        .split()
    )


_ORDINAL_LOOKUP = {_normalized(word): number for number, word in _ORDINALS.items()}
_ORDINAL_PATTERN = "|".join(
    re.escape(value) for value in sorted(_ORDINAL_LOOKUP, key=len, reverse=True)
)
_DIGIT_TRANSLATION = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")


def ordinal(number: int) -> str:
    return _ORDINALS.get(int(number), to_persian_digits(number))


def parse_session_number(caption: str) -> int | None:
    normalized = _normalized(caption).translate(_DIGIT_TRANSLATION)
    match = re.search(
        rf"(?:^|\s)جلسه\s+(?P<value>{_ORDINAL_PATTERN}|[0-9]{{1,2}})(?:\s|$|[-–—:])",
        normalized,
    )
    if not match:
        return None
    value = match.group("value")
    number = int(value) if value.isdigit() else _ORDINAL_LOOKUP.get(value, 0)
    return number if 1 <= number <= 40 else None


def _tag_key(value: str) -> str:
    normalized = (
        str(value)
        .replace("ي", "ی")
        .replace("ى", "ی")
        .replace("ك", "ک")
        .replace("‌", "_")
        .translate(_DIGIT_TRANSLATION)
    )
    return re.sub(r"[^A-Za-z0-9\u0600-\u06FF]+", "", normalized).casefold()


def _hashtags(caption: str) -> set[str]:
    return {
        _tag_key(match.group(1))
        for match in re.finditer(r"#([^\s#]+)", caption)
        if _tag_key(match.group(1))
    }


def _course_tag_keys(course: dict) -> set[str]:
    values = [str(course.get("bookletTag") or "")]
    aliases = course.get("bookletTagAliases")
    if isinstance(aliases, list):
        values.extend(str(alias or "") for alias in aliases)
    return {key for value in values if (key := _tag_key(value))}


def _content_kinds(caption: str) -> tuple[str, ...]:
    normalized = _normalized(caption)
    tags = _hashtags(caption)
    if AI_BOOKLET_SOURCE_TAG_KEY in tags:
        return (AI_BOOKLET_CONTENT_KIND,)
    # AI booklets are marker-only. A human-readable mention without the
    # explicit source hashtag must never fall through into ordinary booklets.
    if re.search(r"(?<!\w)جزوه\s+هوش\s+مصنوعی(?!\w)", normalized):
        return ()
    kinds: list[str] = []
    for kind, tokens in (
        ("voice", ("ویس",)),
        ("power", ("پاور", "پاورپوینت")),
        ("booklet", ("جزوه",)),
        ("reference", ("رفرنس",)),
    ):
        if any(re.search(rf"(?<!\w){re.escape(token)}(?!\w)", normalized) for token in tokens):
            kinds.append(kind)
    return tuple(kinds)


def catalog_courses(catalog: dict) -> list[dict]:
    courses = catalog.get("courses", []) if isinstance(catalog, dict) else []
    return [
        dict(course)
        for course in courses
        if isinstance(course, dict)
        and re.fullmatch(r"[a-z0-9-]{1,48}", str(course.get("courseKey") or ""))
        and str(course.get("bookletTag") or "").strip()
        and isinstance(course.get("sessions"), list)
    ]


def course_by_key(catalog: dict, course_key: str) -> dict | None:
    return next(
        (course for course in catalog_courses(catalog) if str(course.get("courseKey") or "") == course_key),
        None,
    )


def session_by_number(course: dict, session_no: int) -> dict | None:
    return next(
        (
            dict(session)
            for session in course.get("sessions", [])
            if isinstance(session, dict) and int(session.get("sessionNumber") or 0) == int(session_no)
        ),
        None,
    )


@dataclass(frozen=True)
class ParsedSource:
    course_code: str
    course_name: str
    course_tag: str
    term: int
    session_no: int
    kinds: tuple[str, ...]


def parse_source_caption(caption: str, catalog: dict) -> ParsedSource | None:
    tags = _hashtags(caption)
    matched_courses = [
        item
        for item in catalog_courses(catalog)
        if _course_tag_keys(item) & tags
    ]
    course = matched_courses[0] if len(matched_courses) == 1 else None
    term = 0
    for tag in tags:
        match = re.fullmatch(r"ترم([0-9]{1,2})", tag)
        if match:
            term = int(match.group(1))
            break
    session_no = parse_session_number(caption) or 0
    kinds = _content_kinds(caption)
    course_term = int(course.get("term") or 0) if course else 0
    if (
        course is None
        or not 1 <= term <= 12
        or term != course_term
        or not 1 <= session_no <= 40
        or session_by_number(course, session_no) is None
        or not kinds
    ):
        return None
    return ParsedSource(
        course_code=str(course["courseKey"]),
        course_name=str(course.get("courseTitle") or "درس"),
        course_tag=str(course["bookletTag"]),
        term=term,
        session_no=session_no,
        kinds=kinds,
    )


def source_records_from_channel_post(message: dict, catalog: dict) -> list[dict]:
    caption = str(message.get("caption") or message.get("text") or "")
    parsed = parse_source_caption(caption, catalog)
    if parsed is None:
        return []
    media_type = ""
    media: dict = {}
    for candidate in ("document", "audio", "voice"):
        if isinstance(message.get(candidate), dict):
            media_type = candidate
            media = dict(message[candidate])
            break
    if not media_type:
        return []
    method = {"document": "sendDocument", "audio": "sendAudio", "voice": "sendVoice"}[media_type]
    if AI_BOOKLET_CONTENT_KIND in parsed.kinds:
        is_pdf = (
            media_type == "document"
            and (
                str(media.get("mime_type") or "").lower() == "application/pdf"
                or str(media.get("file_name") or "").lower().endswith(".pdf")
            )
        )
        if not is_pdf:
            return []
    return [{
        "courseCode": parsed.course_code,
        "courseName": parsed.course_name,
        "courseTag": parsed.course_tag,
        "term": parsed.term,
        "sessionNo": parsed.session_no,
        "contentKind": kind,
        "telegramMethod": method,
        "fileId": str(media.get("file_id") or ""),
        "fileUniqueId": str(media.get("file_unique_id") or ""),
        "fileName": str(media.get("file_name") or "")[:240],
        "mimeType": str(media.get("mime_type") or "")[:120],
        "caption": caption[:3000],
    } for kind in parsed.kinds]


def _short(value: object, limit: int = 52) -> str:
    text = " ".join(str(value or "").split())
    return text if len(text) <= limit else text[: max(1, limit - 1)].rstrip() + "…"


def courses_screen(catalog: dict) -> Screen:
    courses = catalog_courses(catalog)
    rows = [
        [button(f"📚 {_short(course.get('courseTitle') or 'درس', 46)}", action=f"booklet-course:{course['courseKey']}")]
        for course in courses
        if course.get("sessions")
    ]
    rows.append([button("🏠 منوی اصلی", action="home")])
    if courses:
        body = (
            f"طرح درس <b>{to_persian_digits(len(courses))}</b> واحد از منبع مشترک برنامهٔ آموزشی خوانده شده است.\n"
            "<blockquote>درس را انتخاب کن؛ فهرست جلسات دقیقاً از همان طرح درسی نمایش داده می‌شود که برنامهٔ روزانه و امور کلاس استفاده می‌کنند.</blockquote>"
        )
    else:
        body = (
            "در حال حاضر طرح درس قابل استفاده‌ای از منبع آموزشی دریافت نشد.\n"
            "<blockquote>جلسه‌ای حدس زده یا به‌صورت محلی ساخته نمی‌شود.</blockquote>"
        )
    return Screen(
        "<b><u>📚 آرشیو امن جزوات</u></b>\n\n" + body,
        keyboard(*rows),
    )


def sessions_screen(catalog: dict, course_key: str) -> Screen:
    course = course_by_key(catalog, course_key)
    if course is None:
        return Screen(
            "<b>⚠️ درس پیدا نشد</b>\n\nاین درس دیگر در طرح درس مرجع وجود ندارد.",
            keyboard([button("↩️ فهرست درس‌ها", action="notes")], [button("🏠 منوی اصلی", action="home")]),
        )
    sessions = [
        dict(item)
        for item in course.get("sessions", [])
        if isinstance(item, dict) and 1 <= int(item.get("sessionNumber") or 0) <= 40
    ]
    sessions.sort(key=lambda item: int(item.get("sessionNumber") or 0))
    rows = [
        [button(
            f"{to_persian_digits(item['sessionNumber'])} · {_short(item.get('title') or 'بدون عنوان', 46)}",
            action=f"booklet-session:{course_key}:{int(item['sessionNumber'])}",
        )]
        for item in sessions
    ]
    rows.extend((
        [button("↩️ فهرست درس‌ها", action="notes")],
        [button("🏠 منوی اصلی", action="home")],
    ))
    body = (
        f"<b>{html.escape(str(course.get('courseTitle') or 'درس'))}</b>\n"
        f"<blockquote>تعداد جلسات: <b>{to_persian_digits(len(sessions))}</b> · منبع: طرح درس مشترک امور کلاس</blockquote>"
        if sessions
        else (
            f"<b>{html.escape(str(course.get('courseTitle') or 'درس'))}</b>\n"
            "<blockquote>برای این واحد هنوز جلسهٔ شماره‌دار قابل استفاده‌ای در طرح درس مرجع ثبت نشده است.</blockquote>"
        )
    )
    return Screen("<b><u>📚 جلسات درس</u></b>\n\n" + body, keyboard(*rows))


def resources_screen(catalog: dict, course_key: str, session_no: int) -> Screen:
    course = course_by_key(catalog, course_key)
    session = session_by_number(course or {}, session_no) if course is not None else None
    if course is None or session is None:
        return Screen(
            "<b>⚠️ جلسه پیدا نشد</b>\n\nاین جلسه دیگر در طرح درس مرجع وجود ندارد.",
            keyboard([button("↩️ فهرست درس‌ها", action="notes")], [button("🏠 منوی اصلی", action="home")]),
        )
    instructor = " ".join(str(session.get("instructor") or "").split())
    mode = " ".join(str(session.get("sessionModeLabel") or "").split())
    metadata = []
    if instructor:
        metadata.append(f"👨‍🏫 {html.escape(instructor)}")
    if mode:
        metadata.append(f"📍 {html.escape(mode)}")
    meta_text = "\n".join(metadata)
    if meta_text:
        meta_text += "\n\n"
    return Screen(
        f"<b><u>جلسه {to_persian_digits(session_no)} · {html.escape(str(session.get('title') or 'بدون عنوان'))}</u></b>\n\n"
        f"📚 {html.escape(str(course.get('courseTitle') or 'درس'))}\n"
        f"{meta_text}"
        "نوع محتوای موردنظر را انتخاب کن:",
        keyboard(
            [
                button(RESOURCE_LABELS["voice"], action=f"booklet-resource:{course_key}:{session_no}:voice"),
                button(RESOURCE_LABELS["power"], action=f"booklet-resource:{course_key}:{session_no}:power"),
            ],
            [
                button(RESOURCE_LABELS["booklet"], action=f"booklet-resource:{course_key}:{session_no}:booklet"),
                button(RESOURCE_LABELS["reference"], action=f"booklet-resource:{course_key}:{session_no}:reference"),
            ],
            [
                button(
                    RESOURCE_LABELS[AI_BOOKLET_CONTENT_KIND],
                    action=f"booklet-resource:{course_key}:{session_no}:{AI_BOOKLET_CONTENT_KIND}",
                    style="primary",
                )
            ],
            [button("↩️ جلسات", action=f"booklet-course:{course_key}")],
            [button("🏠 منوی اصلی", action="home")],
        ),
    )


def ai_booklet_purchase_screen(catalog: dict, course_key: str, session_no: int) -> Screen:
    course = course_by_key(catalog, course_key)
    session = session_by_number(course or {}, session_no) if course is not None else None
    if course is None or session is None:
        return Screen(
            "<b>⚠️ جلسه پیدا نشد</b>\n\nاین جلسه دیگر در طرح درس مرجع وجود ندارد.",
            keyboard([button("↩️ فهرست درس‌ها", action="notes")]),
        )
    course_title = html.escape(str(course.get("courseTitle") or "درس"))
    session_title = html.escape(str(session.get("title") or "بدون عنوان"))
    price = html.escape(to_persian_digits(format_rials(AI_BOOKLET_PRICE_RIALS)))
    return Screen(
        "<b><u>🤖 جزوه هوش مصنوعی</u></b>\n\n"
        f"<b>{course_title}</b>\n"
        f"جلسه {to_persian_digits(session_no)} · {session_title}\n\n"
        f"<blockquote>💳 هزینهٔ این جزوه: <code>{price}</code>\n"
        "🔐 تحویل: نسخهٔ محافظت‌شده و شخصی‌سازی‌شده</blockquote>\n\n"
        "این خرید فقط جزوه هوش مصنوعی همین جلسه را فعال می‌کند و از اشتراک "
        "جزوات و سیستم جزوه‌نویسی مستقل است.",
        keyboard(
            [
                button(
                    f"💳 پرداخت {price}",
                    action=f"booklet-ai-buy:{course_key}:{session_no}",
                    style="success",
                )
            ],
            [button("↩️ محتوای جلسه", action=f"booklet-session:{course_key}:{session_no}")],
            [button("🏠 منوی اصلی", action="home")],
        ),
    )


def bale_unavailable_screen() -> Screen:
    return Screen(
        "<b><u>📚 آرشیو امن جزوات</u></b>\n\n"
        "منبع فایل‌ها کانال خصوصی تلگرام است و شناسهٔ فایل آن در بله قابل استفاده نیست.\n"
        "<blockquote>این تفاوت فنی فقط در انتقال فایل است؛ احراز هویت و مجوزها مشترک می‌مانند.</blockquote>",
        keyboard([button("🏠 منوی اصلی", action="home")]),
    )
