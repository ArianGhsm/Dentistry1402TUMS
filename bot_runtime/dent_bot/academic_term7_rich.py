from __future__ import annotations

import html
import re
from typing import Any

from . import ui as ui_module


_DIGIT_TRANSLATION = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")


def _clock_sort_key(value: object) -> int:
    raw = str(value or "").translate(_DIGIT_TRANSLATION)
    match = re.search(r"(?:^|\D)([01]?\d|2[0-3]):([0-5]\d)", raw)
    if not match:
        return 24 * 60 + 1
    return int(match.group(1)) * 60 + int(match.group(2))


def _row_sort_key(row: dict[str, str]) -> tuple[int, int, str]:
    return (
        _clock_sort_key(row.get("start") or row.get("time")),
        _clock_sort_key(row.get("end")),
        " ".join(str(row.get("title") or "").split()),
    )


def _structured_rows(item: dict[str, Any]) -> list[dict[str, str]]:
    meta = item.get("meta")
    raw_rows = meta.get("academicScheduleRows") if isinstance(meta, dict) else None
    if not isinstance(raw_rows, list):
        return []
    rows: list[dict[str, str]] = []
    for raw in raw_rows[:24]:
        if not isinstance(raw, dict):
            continue
        kind = str(raw.get("kind") or "").strip()
        if kind not in {"theory", "practical"}:
            continue
        title = " ".join(str(raw.get("title") or "").split())[:240]
        if not title:
            continue
        start = str(raw.get("start") or "").strip()
        end = str(raw.get("end") or "").strip()
        time_text = "—"
        if start:
            time_text = f"{start}–{end}" if end else start
        rows.append({
            "kind": kind,
            "title": title,
            "start": start,
            "end": end,
            "time": time_text,
            "location": " ".join(str(raw.get("location") or "").split())[:180] or "—",
            "instructor": " ".join(str(raw.get("instructor") or "").split())[:180] or "—",
        })
    return sorted(rows, key=_row_sort_key)


def _legacy_rows(item: dict[str, Any]) -> tuple[list[dict[str, str]], list[str]]:
    rows: list[dict[str, str]] = []
    current: dict[str, str] | None = None
    section_kind = ""
    notices: list[str] = []
    for raw_line in str(item.get("body") or "").splitlines():
        line = raw_line.strip()
        if not line:
            continue
        if line.startswith("📚 کلاس‌های نظری"):
            section_kind = "theory"
            continue
        if line.startswith("🦷 کارآموزی") or line.startswith("🌆 کارآموزی"):
            section_kind = "practical"
            continue
        if line.startswith("ℹ️"):
            notices.append(line.removeprefix("ℹ️").strip())
            continue
        if line.startswith("• "):
            title = line[2:].strip()
            if title in {
                "کلاس نظری ثبت‌شده‌ای ندارد.",
                "برنامه‌ای برای گروه شما ثبت نشده است.",
                "مورد تکمیلی ثبت نشده است.",
            }:
                current = None
                continue
            current = {
                "kind": section_kind,
                "title": title,
                "start": "",
                "end": "",
                "time": "—",
                "location": "—",
                "instructor": "—",
            }
            rows.append(current)
            continue
        if current is not None and line.startswith("⏰"):
            raw_time = line.removeprefix("⏰").strip().replace(" تا ", "–")
            current["time"] = raw_time
            parts = [part.strip() for part in raw_time.split("–", 1)]
            current["start"] = parts[0] if parts else ""
            current["end"] = parts[1] if len(parts) > 1 else ""
            continue
        if current is not None and line.startswith("👤"):
            current["instructor"] = line.removeprefix("👤").strip() or "—"
            continue
        if current is not None and line.startswith("📍"):
            current["location"] = line.removeprefix("📍").strip() or "—"
    return sorted(rows, key=_row_sort_key), notices


def academic_notification_text(item: dict[str, Any]):
    source = str(item.get("source") or "")
    raw_title = " ".join(str(item.get("title") or "").split())
    structured_correction = (
        source in {"manager", "manager-correction", "academic-term7-correction"}
        and raw_title.startswith(("📣 اصلاح برنامه |", "📣 اصلاحیه برنامه |"))
        and "📚 کلاس‌های نظری" in str(item.get("body") or "")
    )
    if source != "academic-term7" and not structured_correction:
        return None
    if "|" not in raw_title:
        return None
    heading, date_label = [part.strip() for part in raw_title.split("|", 1)]
    if not heading or not date_label:
        return None

    rows = _structured_rows(item)
    legacy_rows, notices = _legacy_rows(item)
    if not rows:
        rows = legacy_rows

    fallback = [f"<b>{html.escape(heading)}</b>", "", f"<blockquote>{html.escape(date_label)}</blockquote>"]
    rich = [f"<h2>{html.escape(heading)}</h2>", f"<p><b>{html.escape(date_label)}</b></p>"]
    if rows:
        rich.append("<table bordered striped compact><tr><th>زمان</th><th>برنامه</th><th>استاد</th></tr>")
        for row in rows[:16]:
            icon = "📚" if row.get("kind") == "theory" else "🦷"
            title = ui_module.to_persian_digits(
                " ".join(str(row.get("title") or "").split())[:160]
            ) or "برنامه"
            time_text = ui_module.to_persian_digits(str(row.get("time") or "—"))
            location = " ".join(str(row.get("location") or "—").split())[:140] or "—"
            instructor = " ".join(str(row.get("instructor") or "—").split())[:140] or "—"
            meta_bits = [f"👤 {instructor}"]
            if location != "—":
                meta_bits.append(f"📍 {location}")
            fallback.extend((
                "",
                f"{icon} <b>{html.escape(title)}</b>",
                f"<code>{html.escape(time_text)}</code> · {html.escape(' · '.join(meta_bits))}",
            ))
            location_html = "" if location == "—" else f"<br/>📍 {html.escape(location)}"
            rich.append(
                f"<tr><td><code>{html.escape(time_text)}</code></td>"
                f"<td>{icon} <b>{html.escape(title)}</b>{location_html}</td>"
                f"<td>{html.escape(instructor)}</td></tr>"
            )
        rich.append("</table>")
    else:
        fallback.extend(("", "برای فردا برنامه ثبت‌شده‌ای ندارید."))
        rich.append("<blockquote>برای فردا برنامه ثبت‌شده‌ای ندارید.</blockquote>")
    for notice in notices:
        fallback.extend(("", f"<blockquote>ℹ️ {html.escape(notice)}</blockquote>"))
        rich.append(f"<blockquote>ℹ️ {html.escape(notice)}</blockquote>")
    rich.append("<footer>منبع: برنامه شخصی ترم ۷ و امور کلاس</footer>")
    return ui_module.native_rich_text("\n".join(fallback), "".join(rich))


def decorate_academic_notification_screen(screen: ui_module.Screen, item: dict[str, Any]) -> ui_module.Screen:
    """Apply the existing Term 7 rich renderer explicitly, without module monkey-patching."""
    rich = academic_notification_text(item)
    return ui_module.Screen(rich, screen.keyboard) if rich is not None else screen
