from __future__ import annotations

import html
from datetime import datetime
from zoneinfo import ZoneInfo

from .persian_datetime import PERSIAN_WEEKDAYS, gregorian_to_jalali, to_persian_digits
from .ui import Screen, button, keyboard, native_rich_text

_TEHRAN = ZoneInfo("Asia/Tehran")


def exam_schedule_parts(rows: list[dict], heading: str, *, subtitle: str = "",
                        show_count: bool = False, show_status: bool = False,
                        show_type: bool = True, now: datetime | None = None) -> tuple[list[str], list[str]]:
    """Shared semantic table for daily, full timetable, channel and reminders."""
    clock = (now or datetime.now(_TEHRAN)).astimezone(_TEHRAN)
    entries = []
    for row in rows:
        if not isinstance(row, dict) or not str(row.get("title") or "").strip():
            continue
        try:
            instant = datetime.fromisoformat(str(row.get("startsAt") or "").replace("Z", "+00:00"))
            if instant.tzinfo is None:
                continue
            local = instant.astimezone(_TEHRAN)
        except ValueError:
            continue
        title = to_persian_digits(str(row["title"]).strip())[:180]
        location = to_persian_digits(str(row.get("location") or "اعلام نشده").strip())[:180]
        jy, jm, jd = gregorian_to_jalali(local.date())
        date_label = to_persian_digits(f"{PERSIAN_WEEKDAYS[local.weekday()]} {jy}/{jm:02d}/{jd:02d}")
        time = to_persian_digits(local.strftime("%H:%M"))
        type_label = str(row.get("typeLabel") or "").strip()
        if show_type and type_label:
            title += " · " + type_label
        entries.append((local, title, date_label, time, location))
    entries.sort(key=lambda entry: entry[0])
    fallback = [f"<b>{html.escape(heading)}</b>"]
    rich = [f"<h2>{html.escape(heading)}</h2>"]
    if subtitle:
        fallback.append(html.escape(subtitle))
        rich.append(f"<p>{html.escape(subtitle)}</p>")
    if not entries:
        fallback.append("<blockquote>امتحانی در این بازه ثبت نشده است.</blockquote>")
        rich.append("<blockquote>امتحانی در این بازه ثبت نشده است.</blockquote>")
        return fallback, rich
    if show_count:
        count = f"تعداد امتحانات: {to_persian_digits(len(entries))}"
        fallback.append(f"<b>{count}</b>")
        rich.append(f"<p><b>{count}</b></p>")
    common_location = entries[0][4] if all(entry[4] == entries[0][4] for entry in entries) else ""
    rich.append("<table bordered striped compact><tr><th>تاریخ</th><th>درس</th><th>ساعت</th>"
                + ("" if common_location else "<th>محل</th>")
                + ("<th>وضعیت</th>" if show_status else "") + "</tr>")
    for local, title, date_label, time, location in entries:
        status = "گذشته" if local < clock else "پیش رو"
        title, date_label, time, location = map(html.escape, (title, date_label, time, location))
        fallback.extend(("", f"📝 <b>{title}</b>", f"{date_label} · <code>{time}</code>"))
        if show_status:
            fallback[-1] += " · " + status
        if not common_location:
            fallback.append(f"📍 {location}")
        rich.append(f"<tr><td>{date_label}</td><td><b>{title}</b></td><td><code>{time}</code></td>"
                    + ("" if common_location else f"<td>{location}</td>")
                    + (f"<td>{status}</td>" if show_status else "") + "</tr>")
    rich.append("</table>")
    if common_location:
        venue = f"📍 محل برگزاری: {html.escape(common_location)}"
        fallback.extend(("", venue))
        rich.append(f"<footer>{venue}</footer>")
    return fallback, rich


def final_exam_schedule_screen(payload: dict, *, category: str = "all", now: datetime | None = None) -> Screen:
    heading = "📝 برنامه امتحانات نظری"
    if category == "final":
        heading = "📝 برنامه امتحانات پایان‌ترم"
    elif category == "midterm":
        heading = "📝 برنامه امتحانات میان‌ترم"
    if payload.get("eligible") is not True:
        fallback = [f"<b>{heading}</b>", "این برنامه مخصوص دانشجویان ورودی ۱۴۰۲ دندان‌پزشکی است."]
        rich = [f"<h2>{heading}</h2>", "<blockquote>این برنامه مخصوص دانشجویان ورودی ۱۴۰۲ دندان‌پزشکی است.</blockquote>"]
    else:
        semester = str(payload.get("semesterLabel") or "")
        if category == "all" and "midtermRows" in payload:
            fallback = [f"<b>{heading}</b>", html.escape(semester)]
            rich = [f"<h2>{heading}</h2>", f"<p>{html.escape(semester)}</p>"]
            for key, title in (("midtermRows", "میان‌ترم"), ("rows", "پایان‌ترم")):
                sub_fallback, sub_rich = exam_schedule_parts(payload.get(key) or [], title,
                    show_count=True, show_status=True, show_type=False, now=now)
                fallback.extend(("", *sub_fallback))
                rich.extend(sub_rich)
        else:
            key = "midtermRows" if category == "midterm" else "rows"
            fallback, rich = exam_schedule_parts(payload.get(key) or [], heading,
                subtitle=semester, show_count=True, show_status=True, show_type=False, now=now)
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)),
        keyboard([button("↻ تازه‌سازی", action="c3:final-exams", style="primary")],
                 [button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")]))
