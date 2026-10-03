from __future__ import annotations

import html
import re
from dataclasses import dataclass

from .ai_booklets import (
    AI_BOOKLET_CONTENT_KIND,
    AI_BOOKLET_PRICE_RIALS,
    AI_BOOKLET_SOURCE_TAG_KEY,
)
from .cart import ai_bulk_discount_percent, ai_bulk_discount_progress
from .persian_datetime import to_persian_digits
from .ui import Screen, button, format_rials, keyboard, native_rich_text


RESOURCE_LABELS = {
    "voice": "🎤 ویس",
    "power": "📒 پاور",
    "booklet": "📓 جزوه",
    "reference": "📘 رفرنس",
    AI_BOOKLET_CONTENT_KIND: "🤖 جزوه هوش مصنوعی",
}
AI_BULK_PAGE_SIZE = 8

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


def parse_session_numbers(caption: str) -> tuple[int, ...]:
    normalized = _normalized(caption).translate(_DIGIT_TRANSLATION)
    value_pattern = rf"(?:{_ORDINAL_PATTERN}|[0-9]{{1,2}})"
    match = re.search(
        rf"(?:^|\s)جلس(?:ه|ات)\s+"
        rf"(?P<values>{value_pattern}(?:\s*(?:،|,|و)\s*{value_pattern})*)"
        rf"(?=\s|$|[-–—:])",
        normalized,
    )
    if not match:
        return ()
    numbers: list[int] = []
    for token in re.findall(value_pattern, match.group("values")):
        number = int(token) if token.isdigit() else _ORDINAL_LOOKUP.get(token, 0)
        if not 1 <= number <= 40:
            return ()
        if number not in numbers:
            numbers.append(number)
    return tuple(numbers)


def parse_session_number(caption: str) -> int | None:
    numbers = parse_session_numbers(caption)
    return numbers[0] if numbers else None


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


def _explicit_course_title(caption: str) -> str:
    for raw_line in str(caption or "").splitlines():
        match = re.fullmatch(r"\s*📚\s*(?P<title>.+?)\s*", raw_line)
        if match:
            return _normalized(match.group("title")).casefold()
    return ""


def _course_title_key(course: dict) -> str:
    return _normalized(str(course.get("courseTitle") or "")).casefold()


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
    session_nos: tuple[int, ...]
    kinds: tuple[str, ...]

    @property
    def session_no(self) -> int:
        return self.session_nos[0]


def parse_source_caption(caption: str, catalog: dict) -> ParsedSource | None:
    tags = _hashtags(caption)
    courses = catalog_courses(catalog)
    tag_matches = [
        item
        for item in courses
        if _course_tag_keys(item) & tags
    ]
    explicit_title = _explicit_course_title(caption)
    title_matches = [
        item
        for item in courses
        if explicit_title and _course_title_key(item) == explicit_title
    ]
    course = None
    if len(tag_matches) == 1:
        course = tag_matches[0]
        if title_matches and (
            len(title_matches) != 1
            or str(title_matches[0].get("courseKey") or "")
            != str(course.get("courseKey") or "")
        ):
            course = None
    elif len(tag_matches) == 0 and len(title_matches) == 1:
        # Structured channel captions carry an explicit canonical course title.
        # It is a safe fallback when a newly introduced shorthand hashtag is not
        # yet present in the alias registry. Ambiguous titles still fail closed.
        course = title_matches[0]
    term = 0
    for tag in tags:
        match = re.fullmatch(r"ترم([0-9]{1,2})", tag)
        if match:
            term = int(match.group(1))
            break
    session_nos = parse_session_numbers(caption)
    kinds = _content_kinds(caption)
    course_term = int(course.get("term") or 0) if course else 0
    if (
        course is None
        or not 1 <= term <= 12
        or term != course_term
        or not session_nos
        or any(session_by_number(course, session_no) is None for session_no in session_nos)
        or not kinds
    ):
        return None
    return ParsedSource(
        course_code=str(course["courseKey"]),
        course_name=str(course.get("courseTitle") or "درس"),
        course_tag=str(course["bookletTag"]),
        term=term,
        session_nos=session_nos,
        kinds=kinds,
    )


def source_records_from_channel_post(
    message: dict,
    catalog: dict,
    *,
    allowed_kinds: set[str] | frozenset[str] | None = None,
) -> list[dict]:
    caption = str(message.get("caption") or message.get("text") or "")
    parsed = parse_source_caption(caption, catalog)
    if parsed is None:
        return []
    kinds = tuple(
        kind
        for kind in parsed.kinds
        if allowed_kinds is None or kind in allowed_kinds
    )
    if not kinds:
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
    if AI_BOOKLET_CONTENT_KIND in kinds:
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
        "sessionNo": session_no,
        "contentKind": kind,
        "telegramMethod": method,
        "fileId": str(media.get("file_id") or ""),
        "fileUniqueId": str(media.get("file_unique_id") or ""),
        "fileName": str(media.get("file_name") or "")[:240],
        "mimeType": str(media.get("mime_type") or "")[:120],
        "caption": caption[:3000],
    } for session_no in parsed.session_nos for kind in kinds]


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


def sessions_screen(
    catalog: dict,
    course_key: str,
    *,
    ai_published_sessions: set[int] | None = None,
    ai_owned_sessions: set[int] | None = None,
) -> Screen:
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
    valid_session_numbers = {int(item["sessionNumber"]) for item in sessions}
    published = {
        int(value)
        for value in (ai_published_sessions or set())
        if int(value) in valid_session_numbers
    }
    owned = {
        int(value)
        for value in (ai_owned_sessions or set())
        if int(value) in valid_session_numbers
    }
    purchasable = sorted(published - owned)

    rows: list[list[dict]] = []
    if purchasable:
        rows.append([
            button(
                f"🤖 خرید همه جزوه‌های منتشرشده · {to_persian_digits(len(purchasable))}",
                action=f"ai-all:{course_key}",
                style="success",
            )
        ])
        if len(purchasable) > 1:
            rows.append([
                button(
                    "☑️ انتخاب چند جلسه برای خرید",
                    action=f"ai-pick:{course_key}",
                    style="primary",
                )
            ])

    rows.extend([
        [button(
            f"{to_persian_digits(item['sessionNumber'])} · {_short(item.get('title') or 'بدون عنوان', 46)}",
            action=f"booklet-session:{course_key}:{int(item['sessionNumber'])}",
        )]
        for item in sessions
    ])
    rows.extend((
        [button("↩️ فهرست درس‌ها", action="notes")],
        [button("🏠 منوی اصلی", action="home")],
    ))

    body_lines = [f"<b>{html.escape(str(course.get('courseTitle') or 'درس'))}</b>"]
    if sessions:
        body_lines.append(
            f"<blockquote>تعداد جلسات: <b>{to_persian_digits(len(sessions))}</b> · "
            "منبع: طرح درس مشترک امور کلاس</blockquote>"
        )
    else:
        body_lines.append(
            "<blockquote>برای این واحد هنوز جلسهٔ شماره‌دار قابل استفاده‌ای در طرح درس مرجع ثبت نشده است.</blockquote>"
        )
    if published:
        if purchasable:
            body_lines.append(
                f"<blockquote>🤖 جزوه هوش مصنوعی منتشرشده و قابل خرید: "
                f"<b>{to_persian_digits(len(purchasable))}</b> جلسه"
                + (f" · خریداری‌شده: <b>{to_persian_digits(len(published & owned))}</b>" if published & owned else "")
                + "</blockquote>"
            )
        else:
            body_lines.append(
                "<blockquote>✅ همهٔ جزوه‌های هوش مصنوعی منتشرشدهٔ این درس قبلاً برای حساب شما فعال شده‌اند.</blockquote>"
            )
        body_lines.append(
            "<blockquote>🎁 خرید هم‌زمان ۱۱ تا ۱۵ جزوه: <b>۲۰٪</b> تخفیف · "
            "از ۱۶ جزوه به بالا: <b>۳۰٪</b> تخفیف خودکار</blockquote>"
        )
    return Screen(
        "<b><u>📚 جلسات درس</u></b>\n\n" + "\n".join(body_lines),
        keyboard(*rows),
    )


def resources_screen(
    catalog: dict,
    course_key: str,
    session_no: int,
    *,
    content_counts: dict[str, int],
) -> Screen:
    course = course_by_key(catalog, course_key)
    session = session_by_number(course or {}, session_no) if course is not None else None
    if course is None or session is None:
        return Screen(
            "<b>⚠️ جلسه پیدا نشد</b>\n\nاین جلسه دیگر در طرح درس مرجع وجود ندارد.",
            keyboard([button("↩️ فهرست درس‌ها", action="notes")], [button("🏠 منوی اصلی", action="home")]),
        )

    instructor = " ".join(str(session.get("instructor") or "").split())
    resident = " ".join(str(session.get("resident") or "").split())
    mode = " ".join(str(session.get("sessionModeLabel") or "").split())
    metadata = []
    if instructor:
        metadata.append(f"👨‍🏫 {html.escape(instructor)}")
    if resident:
        metadata.append(f"🩺 رزیدنت مسئول: {html.escape(resident)}")
    if mode:
        metadata.append(f"📍 {html.escape(mode)}")

    kinds = ("voice", "power", "booklet", "reference", AI_BOOKLET_CONTENT_KIND)
    counts = {kind: max(0, int(content_counts.get(kind) or 0)) for kind in kinds}
    available = {kind: counts[kind] > 0 for kind in kinds}

    fallback = [
        f"<b><u>جلسه {to_persian_digits(session_no)} · {html.escape(str(session.get('title') or 'بدون عنوان'))}</u></b>",
        "",
        f"📚 {html.escape(str(course.get('courseTitle') or 'درس'))}",
    ]
    fallback.extend(metadata)
    fallback.extend(("", "<b>وضعیت محتوای جلسه</b>"))
    status_lines = []
    for kind in kinds:
        count = counts[kind]
        if count:
            suffix = f" · {to_persian_digits(count)} فایل" if count > 1 else ""
            status_lines.append(f"{RESOURCE_LABELS[kind]}  ✅ موجود{suffix}")
        else:
            status_lines.append(f"{RESOURCE_LABELS[kind]}  — موجود نیست")
    fallback.append("<blockquote>" + "\n".join(status_lines) + "</blockquote>")

    rich = [
        f"<h2>جلسه {to_persian_digits(session_no)} · {html.escape(str(session.get('title') or 'بدون عنوان'))}</h2>",
        f"<p>📚 <b>{html.escape(str(course.get('courseTitle') or 'درس'))}</b>",
    ]
    if metadata:
        rich.append("<br/>" + "<br/>".join(metadata))
    rich.append("</p>")
    rich.append(
        "<table bordered striped compact><caption>وضعیت محتوای جلسه</caption>"
        "<tr><th>محتوا</th><th>وضعیت</th></tr>"
    )
    for kind in kinds:
        count = counts[kind]
        status = "✅ موجود" if count else "— موجود نیست"
        if count > 1:
            status += f" · {to_persian_digits(count)} فایل"
        rich.append(
            f"<tr><td>{html.escape(RESOURCE_LABELS[kind])}</td>"
            f"<td>{html.escape(status)}</td></tr>"
        )
    rich.append("</table>")
    rich.append("<footer>وضعیت از آرشیو زندهٔ همان جلسه خوانده می‌شود.</footer>")

    rows: list[list[dict]] = []
    first_row = [
        button(RESOURCE_LABELS[kind], action=f"booklet-resource:{course_key}:{session_no}:{kind}")
        for kind in ("voice", "power")
        if available[kind]
    ]
    second_row = [
        button(RESOURCE_LABELS[kind], action=f"booklet-resource:{course_key}:{session_no}:{kind}")
        for kind in ("booklet", "reference")
        if available[kind]
    ]
    if first_row:
        rows.append(first_row)
    if second_row:
        rows.append(second_row)
    if available[AI_BOOKLET_CONTENT_KIND]:
        rows.append([
            button(
                RESOURCE_LABELS[AI_BOOKLET_CONTENT_KIND],
                action=f"booklet-resource:{course_key}:{session_no}:{AI_BOOKLET_CONTENT_KIND}",
                style="primary",
            )
        ])
    rows.extend((
        [button("↩️ جلسات", action=f"booklet-course:{course_key}")],
        [button("🏠 منوی اصلی", action="home")],
    ))
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def ai_booklet_bulk_selection_screen(
    catalog: dict,
    course_key: str,
    *,
    published_sessions: set[int],
    owned_sessions: set[int],
    selected_sessions: set[int],
    page: int = 0,
) -> Screen:
    course = course_by_key(catalog, course_key)
    if course is None:
        return Screen(
            "<b>⚠️ درس پیدا نشد</b>",
            keyboard([button("↩️ فهرست درس‌ها", action="notes")]),
        )
    sessions = {
        int(item.get("sessionNumber") or 0): dict(item)
        for item in course.get("sessions", [])
        if isinstance(item, dict) and 1 <= int(item.get("sessionNumber") or 0) <= 40
    }
    eligible = sorted(
        number
        for number in published_sessions
        if number in sessions and number not in owned_sessions
    )
    selected = {number for number in selected_sessions if number in eligible}
    if not eligible:
        return Screen(
            "<b><u>🤖 خرید گروهی جزوات هوش مصنوعی</u></b>\n\n"
            f"<b>{html.escape(str(course.get('courseTitle') or 'درس'))}</b>\n\n"
            "<blockquote>جزوه هوش مصنوعی منتشرشده و قابل خریدی برای این درس باقی نمانده است.</blockquote>",
            keyboard(
                [button("↩️ جلسات درس", action=f"booklet-course:{course_key}")],
                [button("🏠 منوی اصلی", action="home")],
            ),
        )

    page_count = max(1, (len(eligible) + AI_BULK_PAGE_SIZE - 1) // AI_BULK_PAGE_SIZE)
    current_page = min(max(0, int(page)), page_count - 1)
    start = current_page * AI_BULK_PAGE_SIZE
    visible = eligible[start:start + AI_BULK_PAGE_SIZE]

    count = len(selected)
    subtotal = count * AI_BOOKLET_PRICE_RIALS
    percent = ai_bulk_discount_percent(count)
    discount = subtotal * percent // 100
    total = max(0, subtotal - discount)
    progress = ai_bulk_discount_progress(count)

    lines = [
        "<b><u>🤖 خرید گروهی جزوات هوش مصنوعی</u></b>",
        "",
        f"<b>{html.escape(str(course.get('courseTitle') or 'درس'))}</b>",
        f"<blockquote>منتشرشده و قابل خرید: <b>{to_persian_digits(len(eligible))}</b> جلسه · "
        f"انتخاب‌شده: <b>{to_persian_digits(count)}</b></blockquote>",
    ]
    if count:
        lines.append(f"جمع: <code>{html.escape(format_rials(subtotal))}</code>")
        if percent:
            lines.append(
                f"تخفیف خودکار {to_persian_digits(percent)}٪: "
                f"<code>{html.escape(format_rials(discount))}</code>"
            )
        lines.append(f"<b>مبلغ نهایی: <code>{html.escape(format_rials(total))}</code></b>")
    if percent >= 30:
        lines.append("<blockquote>🎁 بیشترین تخفیف گروهی، یعنی <b>۳۰٪</b>، فعال است.</blockquote>")
    elif percent:
        lines.append(
            f"<blockquote>🎁 تخفیف <b>{to_persian_digits(percent)}٪</b> فعال است · "
            f"با انتخاب {to_persian_digits(progress['remaining'])} جزوه دیگر به "
            f"<b>{to_persian_digits(progress['nextPercent'])}٪</b> می‌رسد.</blockquote>"
        )
    else:
        lines.append(
            "<blockquote>🎁 از ۱۱ جزوه: <b>۲۰٪</b> · از ۱۶ جزوه به بالا: "
            "<b>۳۰٪</b> تخفیف خودکار</blockquote>"
        )

    rows: list[list[dict]] = []
    for number in visible:
        item = sessions[number]
        marker = "✅" if number in selected else "◻️"
        rows.append([
            button(
                f"{marker} {to_persian_digits(number)} · {_short(item.get('title') or 'بدون عنوان', 38)}",
                action=f"ai-toggle:{number}",
            )
        ])

    nav: list[dict] = []
    if current_page > 0:
        nav.append(button("‹ قبلی", action=f"ai-page:{current_page - 1}"))
    nav.append(
        button(
            f"{to_persian_digits(current_page + 1)} / {to_persian_digits(page_count)}",
            action=f"ai-page:{current_page}",
        )
    )
    if current_page + 1 < page_count:
        nav.append(button("بعدی ›", action=f"ai-page:{current_page + 1}"))
    if page_count > 1:
        rows.append(nav)

    rows.append([
        button("✅ انتخاب همه", action="ai-select-all"),
        button("⬜ پاک‌کردن انتخاب", action="ai-select-none"),
    ])
    if selected:
        rows.append([
            button(
                f"💳 خرید {to_persian_digits(count)} جزوه · {to_persian_digits(format_rials(total))}",
                action="ai-checkout",
                style="success",
            )
        ])
    rows.extend((
        [button("↩️ جلسات درس", action=f"booklet-course:{course_key}")],
        [button("🏠 منوی اصلی", action="home")],
    ))
    return Screen("\n".join(lines), keyboard(*rows))


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
        "جزوات و سیستم جزوه‌نویسی مستقل است.\n\n"
        "<blockquote>🎁 اگر چند جزوه را باهم بخری: از ۱۱ جزوه <b>۲۰٪</b> و "
        "از ۱۶ جزوه به بالا <b>۳۰٪</b> تخفیف خودکار می‌گیری.</blockquote>",
        keyboard(
            [
                button(
                    f"⚡ خرید فوری · {price}",
                    action=f"booklet-ai-buy:{course_key}:{session_no}",
                    style="success",
                ),
                button(
                    "🛒 افزودن به سبد",
                    action=f"cart-add-ai:{course_key}:{session_no}",
                ),
            ],
            [
                button(
                    "☑️ جلسات دیگر را هم می‌خواهم",
                    action=f"ai-more:{course_key}:{session_no}",
                    style="primary",
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
