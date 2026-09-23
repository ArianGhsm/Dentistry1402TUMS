from __future__ import annotations

import html
from datetime import datetime, timezone
from zoneinfo import ZoneInfo

from .booklets import RESOURCE_LABELS, course_by_key, session_by_number
from .persian_datetime import to_persian_digits
from .ui import Screen, keyboard, native_rich_text


DIGEST_HOUR = 22
DIGEST_TIMEZONE = "Asia/Tehran"
COHORT_KEY = "dentistry-1402"
CONTENT_KIND_ORDER = ("voice", "booklet", "ai_booklet", "power", "reference")
CONTENT_KIND_TITLES = {
    "voice": "ویس",
    "booklet": "جزوه",
    "ai_booklet": "جزوه هوش مصنوعی",
    "power": "پاورپوینت",
    "reference": "رفرنس",
}


def _utc_sql(value: datetime) -> str:
    return value.astimezone(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")


def digest_window(*, now: datetime, previous_cutoff: str = "") -> tuple[str, str]:
    local = now.astimezone(ZoneInfo(DIGEST_TIMEZONE))
    if previous_cutoff:
        try:
            parsed = datetime.fromisoformat(previous_cutoff.replace("Z", "+00:00"))
            if parsed.tzinfo is None:
                parsed = parsed.replace(tzinfo=timezone.utc)
            start = parsed.astimezone(timezone.utc)
        except ValueError:
            start = local.replace(hour=0, minute=0, second=0, microsecond=0).astimezone(timezone.utc)
    else:
        start = local.replace(hour=0, minute=0, second=0, microsecond=0).astimezone(timezone.utc)
    end = local.astimezone(timezone.utc)
    return _utc_sql(start), _utc_sql(end)


def build_digest_payload(*, rows: list[dict], catalog: dict, digest_date: str, window_start: str, window_end: str) -> dict:
    items: list[dict] = []
    for row in rows:
        course_code = str(row.get("courseCode") or "")
        course = course_by_key(catalog, course_code)
        session_no = int(row.get("sessionNo") or 0)
        session = session_by_number(course or {}, session_no) if course else None
        items.append({
            "sourceMessageId": int(row.get("sourceMessageId") or 0),
            "courseCode": course_code,
            "courseName": str((course or {}).get("courseTitle") or row.get("courseName") or "درس"),
            "sessionNo": session_no,
            "sessionTitle": str((session or {}).get("title") or "عنوان جلسه ثبت نشده"),
            "contentKind": str(row.get("contentKind") or ""),
        })
    items.sort(key=lambda item: (
        CONTENT_KIND_ORDER.index(item["contentKind"]) if item["contentKind"] in CONTENT_KIND_ORDER else 99,
        item["courseName"],
        item["sessionNo"],
        item["sourceMessageId"],
    ))
    return {
        "version": 1,
        "digestDate": digest_date,
        "windowStart": window_start,
        "windowEnd": window_end,
        "items": items,
    }


def _kind_label(kind: str) -> str:
    return RESOURCE_LABELS.get(kind) or CONTENT_KIND_TITLES.get(kind) or "📎 محتوا"


def render_daily_content_digest(payload: dict) -> Screen:
    items = [dict(item) for item in payload.get("items", []) if isinstance(item, dict)]
    counts = {
        kind: sum(1 for item in items if str(item.get("contentKind") or "") == kind)
        for kind in CONTENT_KIND_ORDER
    }
    total = len(items)
    fallback = ["<b><u>📚 گزارش محتوای جدید</u></b>", ""]
    rich = ["<h2>📚 گزارش محتوای جدید</h2>"]
    if not items:
        fallback.append("<blockquote>از گزارش قبلی تا این لحظه محتوای تازه‌ای به آرشیو آموزشی اضافه نشده است.</blockquote>")
        rich.append("<blockquote>از گزارش قبلی تا این لحظه محتوای تازه‌ای به آرشیو آموزشی اضافه نشده است.</blockquote>")
        return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard())

    total_fa = to_persian_digits(total)
    fallback.append(f"از گزارش قبلی تا الان <b>{total_fa}</b> محتوای جدید اضافه شده است.")
    rich.append(f"<blockquote>از گزارش قبلی تا الان <b>{total_fa}</b> محتوای جدید اضافه شده است.</blockquote>")

    summary_rows = [(kind, count) for kind, count in counts.items() if count]
    fallback.extend(("", "<b>جمع‌بندی</b>"))
    rich.append("<table bordered striped compact><tr><th>نوع محتوا</th><th>تعداد</th></tr>")
    for kind, count in summary_rows:
        label = _kind_label(kind)
        count_fa = to_persian_digits(count)
        fallback.append(f"<code>{html.escape(label)}</code>  <b>{count_fa}</b>")
        rich.append(f"<tr><td>{html.escape(label)}</td><td><b>{count_fa}</b></td></tr>")
    rich.append("</table>")

    for kind, count in summary_rows:
        label = _kind_label(kind)
        fallback.extend(("", f"<b>{html.escape(label)} · {to_persian_digits(count)}</b>"))
        rich.append(f"<h3>{html.escape(label)} · {to_persian_digits(count)}</h3>")
        grouped: dict[tuple[str, int, str], int] = {}
        for item in (entry for entry in items if entry.get("contentKind") == kind):
            key = (
                str(item.get("courseName") or "درس"),
                int(item.get("sessionNo") or 0),
                str(item.get("sessionTitle") or "عنوان جلسه ثبت نشده"),
            )
            grouped[key] = grouped.get(key, 0) + 1
        for (course_raw, session_raw, title_raw), copies in grouped.items():
            course = html.escape(course_raw)
            session_no = to_persian_digits(session_raw)
            title = html.escape(title_raw)
            multiplier = f" · ×{to_persian_digits(copies)}" if copies > 1 else ""
            fallback.append(f"<b>{course} · جلسه {session_no}{multiplier}</b>\n{title}")
            rich.append(f"<p><b>{course} · جلسه {session_no}{multiplier}</b><br>{title}</p>")

    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard())


def eligible_digest_recipients(directory_payload: dict) -> list[int]:
    ids: set[int] = set()
    for item in directory_payload.get("items", []):
        if not isinstance(item, dict) or str(item.get("cohortKey") or "") != COHORT_KEY:
            continue
        raw = str(item.get("platformUserId") or "")
        if raw.isdigit() and int(raw) > 0:
            ids.add(int(raw))
    return sorted(ids)
