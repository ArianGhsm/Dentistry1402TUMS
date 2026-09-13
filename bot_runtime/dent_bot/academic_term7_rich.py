from __future__ import annotations

import html
from typing import Any

from . import ui as ui_module


def academic_notification_text(item: dict[str, Any]):
    if str(item.get("source") or "") != "academic-term7":
        return None
    raw_title = " ".join(str(item.get("title") or "").split())
    date_label = raw_title.split("|", 1)[1].strip() if "|" in raw_title else ""
    if not date_label:
        return None

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
            current = {"kind": section_kind, "title": title, "time": "—", "location": "—"}
            rows.append(current)
            continue
        if current is not None and line.startswith("⏰"):
            current["time"] = line.removeprefix("⏰").strip().replace(" تا ", "–")
            continue
        if current is not None and line.startswith("📍"):
            current["location"] = line.removeprefix("📍").strip() or "—"

    fallback = ["<b>📅 برنامه فردا</b>", "", f"<blockquote>{html.escape(date_label)}</blockquote>"]
    rich = ["<h2>📅 برنامه فردا</h2>", f"<p><b>{html.escape(date_label)}</b></p>"]
    if rows:
        rich.append("<table bordered striped compact><tr><th>زمان</th><th>برنامه</th><th>مکان</th></tr>")
        for row in rows[:16]:
            icon = "📚" if row.get("kind") == "theory" else "🦷"
            title = " ".join(str(row.get("title") or "").split())[:160] or "برنامه"
            time_text = ui_module.to_persian_digits(str(row.get("time") or "—"))
            location = " ".join(str(row.get("location") or "—").split())[:140] or "—"
            fallback.extend(("", f"{icon} <b>{html.escape(title)}</b>", f"<code>{html.escape(time_text)}</code> · {html.escape(location)}"))
            rich.append(
                f"<tr><td><code>{html.escape(time_text)}</code></td>"
                f"<td>{icon} <b>{html.escape(title)}</b></td>"
                f"<td>{html.escape(location)}</td></tr>"
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


def install_academic_term7_rich_notifications() -> None:
    from . import app as app_module
    from . import runtime as runtime_module

    if getattr(ui_module, "_academic_term7_rich_installed", False):
        return

    original_detail = ui_module.notification_detail_screen
    original_push = ui_module.notification_push_screen

    def detail(item, ref, *, platform, is_owner):
        screen = original_detail(item, ref, platform=platform, is_owner=is_owner)
        rich = academic_notification_text(item)
        return ui_module.Screen(rich, screen.keyboard) if rich is not None else screen

    def push(item, ref, *, platform, is_owner):
        screen = original_push(item, ref, platform=platform, is_owner=is_owner)
        rich = academic_notification_text(item)
        return ui_module.Screen(rich, screen.keyboard) if rich is not None else screen

    ui_module.notification_detail_screen = detail
    ui_module.notification_push_screen = push
    app_module.notification_detail_screen = detail
    if hasattr(app_module, "notification_push_screen"):
        app_module.notification_push_screen = push
    runtime_module.notification_push_screen = push
    ui_module._academic_term7_rich_installed = True
