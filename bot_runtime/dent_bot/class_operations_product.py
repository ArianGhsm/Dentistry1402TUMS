from __future__ import annotations

import html
from datetime import date
from typing import Any, Callable

from .api import BotApiError
from .app import DentBotApp
from .classops_runtime import install_classops_runtime
from .persian_datetime import (
    PERSIAN_WEEKDAYS,
    format_jalali_datetime,
    gregorian_to_jalali,
    to_persian_digits,
)
from .site_api import SiteApiError
from .ui import Screen, button, frame, keyboard, native_rich_text

_INSTALLED = False

_ITEM_LABELS = {
    "announcement": "اطلاعیه",
    "event": "رویداد",
    "class_change": "تغییر کلاس",
    "deadline": "مهلت",
    "task": "تکلیف",
    "requirement": "مورد الزامی",
    "exam": "امتحان",
    "critical_notice": "اطلاعیه مهم",
    "service_reminder": "یادآوری",
    "schedule_ref": "برنامه کلاس",
}

_STATE_LABELS = {
    "draft": "پیش‌نویس",
    "scheduled": "زمان‌بندی‌شده",
    "active": "فعال",
    "completed": "انجام‌شده",
    "cancelled": "لغوشده",
    "canceled": "لغوشده",
    "archived": "بایگانی‌شده",
    "pending": "در انتظار",
    "submitted": "ارسال‌شده",
    "needs_revision": "نیازمند اصلاح",
    "waived": "نیاز نیست",
    "overdue": "عقب‌افتاده",
    "acked": "تأییدشده",
    "not_required": "نیاز به تأیید ندارد",
    "superseded": "جایگزین‌شده",
}

_IMPORTANCE_LABELS = {
    "normal": "عادی",
    "important": "مهم",
    "critical": "فوری",
}

_FILTERS: dict[str, set[str]] = {
    "schedule": {"event", "class_change", "deadline", "schedule_ref"},
    "tasks": {"task", "requirement"},
    "exams": {"exam"},
    "important": {"critical_notice", "announcement"},
    "services": {"service_reminder"},
}

_FILTER_TITLES = {
    "all": "همه موارد",
    "schedule": "برنامه و تغییرات کلاس",
    "tasks": "تکالیف و کارها",
    "exams": "امتحان‌ها",
    "important": "اطلاعیه‌ها",
    "services": "یادآوری‌ها",
}

_DIGEST_SECTION_ICONS = {
    "critical_ack": "🚨",
    "changes": "🔄",
    "schedule": "📅",
    "deadlines": "⏳",
    "tasks_requirements": "✅",
    "outstanding_tasks": "✅",
    "exams": "📝",
    "service_reminders": "🔔",
    "other": "•",
}


def _visible(value: object, limit: int = 1800) -> str:
    raw = str(value or "").replace("\x00", "").strip()[:limit]
    return html.escape(to_persian_digits(raw))


def _plain_visible(value: object, limit: int = 1800) -> str:
    raw = str(value or "").replace("\x00", "").strip()[:limit]
    return to_persian_digits(raw)


def _site_url(app: DentBotApp) -> str:
    value = str(getattr(app, "site_url", "") or "").rstrip("/")
    return value if value.startswith("https://") else ""


def _item_label(item_type: object) -> str:
    return _ITEM_LABELS.get(str(item_type or ""), "مورد کلاس")


def _state_label(state: object) -> str:
    raw = str(state or "")
    return _STATE_LABELS.get(raw, "نامشخص" if raw else "")


def _importance_label(value: object) -> str:
    raw = str(value or "")
    return _IMPORTANCE_LABELS.get(raw, "")


def _format_time(value: object) -> str:
    return format_jalali_datetime(value)


def _format_local_date(value: object) -> str:
    raw = str(value or "").strip()
    if not raw:
        return ""
    try:
        parsed = date.fromisoformat(raw)
    except ValueError:
        return ""
    year, month, day = gregorian_to_jalali(parsed)
    return to_persian_digits(f"{PERSIAN_WEEKDAYS[parsed.weekday()]} {year}/{month}/{day}")


def _timing_values(timing: dict[str, Any]) -> list[tuple[str, str]]:
    values: list[tuple[str, str]] = []
    if bool(timing.get("allDay")) and timing.get("localDate"):
        local_date = _format_local_date(timing.get("localDate"))
        if local_date:
            values.append(("زمان", f"{local_date} · تمام‌روز"))
        return values
    for key, label in (
        ("startsAt", "شروع"),
        ("startsAtUtc", "شروع"),
        ("endsAt", "پایان"),
        ("endsAtUtc", "پایان"),
        ("dueAt", "مهلت"),
        ("dueAtUtc", "مهلت"),
    ):
        if any(existing_label == label for existing_label, _ in values):
            continue
        rendered = _format_time(timing.get(key))
        if rendered:
            values.append((label, rendered))
    return values


def _item_effective_time(item: dict[str, Any]) -> str:
    timing = dict(item.get("timing") or {})
    if bool(timing.get("allDay")):
        return _format_local_date(timing.get("localDate")) or "تمام‌روز"
    for key in ("dueAt", "dueAtUtc", "startsAt", "startsAtUtc"):
        rendered = _format_time(timing.get(key))
        if rendered:
            return rendered
    return "—"


def _item_course(item: dict[str, Any]) -> str:
    direct = str(item.get("courseTitle") or "").strip()
    if direct:
        return direct
    course = item.get("course")
    if isinstance(course, dict):
        return str(course.get("title") or "").strip()
    return ""


def _fact_table(caption: str, rows: list[tuple[str, str]]) -> str:
    if not rows:
        return ""
    parts = [f"<table bordered striped compact><caption>{_visible(caption, 100)}</caption>"]
    for label, value in rows:
        parts.append(f"<tr><th>{_visible(label, 80)}</th><td>{_visible(value, 1000)}</td></tr>")
    parts.append("</table>")
    return "".join(parts)


def _has_native_rich(screen: Screen) -> bool:
    return bool(getattr(screen.text, "rich_html", ""))


def _render_screen(
    app: DentBotApp,
    chat_id: int,
    screen: Screen,
    *,
    callback: dict[str, Any] | None = None,
) -> None:
    """Use the same regular-to-rich transition as the central bot callback path."""
    callback = dict(callback or {})
    message = dict(callback.get("message") or {})
    try:
        message_id = int(message.get("message_id") or 0)
    except (TypeError, ValueError):
        message_id = 0
    if not callback or message_id <= 0:
        app.api.send(chat_id, screen.text, screen.keyboard)
        return
    target_rich = _has_native_rich(screen)
    source_rich = isinstance(message.get("rich_message"), dict)
    if str(getattr(app, "platform", "telegram")) == "telegram" and target_rich and not source_rich:
        app.api.send(chat_id, screen.text, screen.keyboard)
        return
    try:
        app.api.edit(chat_id, message_id, screen.text, screen.keyboard)
    except BotApiError as error:
        message_text = str(error).lower()
        if "message is not modified" in message_text:
            return
        if any(marker in message_text for marker in ("message to edit not found", "message can't be edited", "message_id_invalid")):
            app.api.send(chat_id, screen.text, screen.keyboard)
            return
        raise


def _home_keyboard(screen: Screen) -> Screen:
    rows = [list(row) for row in screen.keyboard.get("inline_keyboard", [])]
    if any(
        str(item.get("callback_data") or "").endswith(":class-operations")
        for row in rows for item in row if isinstance(item, dict)
    ):
        return screen
    entry = [button("📅 امور کلاس", action="class-operations", style="primary")]
    insert_at = len(rows)
    for index, row in enumerate(rows):
        callbacks = {str(item.get("callback_data") or "") for item in row if isinstance(item, dict)}
        if any(value.endswith(":notifications") or value.endswith(":help") for value in callbacks):
            insert_at = index
            break
    rows.insert(insert_at, entry)
    return Screen(screen.text, keyboard(*rows))


def _class_home_screen(app: DentBotApp, *, role: str, items: list[dict[str, Any]]) -> Screen:
    active = [item for item in items if str(item.get("status") or "") not in {"cancelled", "canceled", "archived", "completed"}]
    important = sum(1 for item in active if str(item.get("type") or "") == "critical_notice")
    tasks = sum(1 for item in active if str(item.get("type") or "") in {"task", "requirement"})
    exams = sum(1 for item in active if str(item.get("type") or "") == "exam")
    counts = [
        ("موارد فعال", to_persian_digits(len(active))),
        ("تکلیف و کار", to_persian_digits(tasks)),
        ("امتحان", to_persian_digits(exams)),
        ("اطلاعیه فوری", to_persian_digits(important)),
    ]
    fallback = [
        "<b><u>📅 امور کلاس</u></b>", "",
        "برنامه‌ها، تکالیف، امتحان‌ها و اطلاعیه‌های کلاس را از همین‌جا دنبال کن.", "",
    ]
    if active:
        fallback.append(
            "<blockquote>"
            f"فعال: <b>{to_persian_digits(len(active))}</b> · "
            f"تکلیف: <b>{to_persian_digits(tasks)}</b> · "
            f"امتحان: <b>{to_persian_digits(exams)}</b>"
            + (f" · فوری: <b>{to_persian_digits(important)}</b>" if important else "")
            + "</blockquote>"
        )
    else:
        fallback.append("<blockquote>فعلاً مورد فعالی ثبت نشده است.</blockquote>")
    rich = [
        "<h2>📅 امور کلاس</h2>",
        "<p>برنامه‌ها، تکالیف، امتحان‌ها و اطلاعیه‌های کلاس را از همین‌جا دنبال کن.</p>",
    ]
    rich.append(_fact_table("خلاصه وضعیت", counts) if active else "<blockquote>فعلاً مورد فعالی ثبت نشده است.</blockquote>")
    rows = [
        [button("📅 برنامه و تغییرات", action="class-operations:list:schedule"), button("✅ تکالیف و کارها", action="class-operations:list:tasks")],
        [button("📝 امتحان‌ها", action="class-operations:list:exams"), button("🔔 اطلاعیه‌ها", action="class-operations:list:important")],
        [button("🌤 فردا", action="class-operations:tomorrow", style="primary"), button("🗓 هفته پیش رو", action="class-operations:weekly")],
        [button("همه موارد", action="class-operations:list:all")],
    ]
    if role == "owner":
        rows.append([button("⚙️ مدیریت امور کلاس", action="class-operations:owner", style="success")])
    rows.append([button("🏠 منوی اصلی", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def _item_line(item: dict[str, Any]) -> str:
    kind = _item_label(item.get("type"))
    title = _visible(item.get("title") or kind, 90)
    state = _state_label(item.get("status"))
    suffix = f" · {_visible(state)}" if state else ""
    when = _item_effective_time(item)
    when_line = f"\n  ⏱ {_visible(when)}" if when and when != "—" else ""
    return f"• <b>{title}</b>\n  {_visible(kind)}{suffix}{when_line}"


def _list_screen(items: list[dict[str, Any]], filter_name: str) -> Screen:
    allowed = _FILTERS.get(filter_name)
    filtered = [item for item in items if allowed is None or str(item.get("type") or "") in allowed]
    title = _FILTER_TITLES.get(filter_name, _FILTER_TITLES["all"])
    visible_items = filtered[:12]
    fallback = [f"<b><u>{html.escape(title)}</u></b>", ""]
    rows: list[list[dict]] = []
    for item in visible_items:
        fallback.append(_item_line(item))
        item_id = str(item.get("id") or "")
        label = _plain_visible(item.get("title") or _item_label(item.get("type")), 30)
        if item_id.startswith("cop_"):
            rows.append([button(f"مشاهده · {label}", action=f"class-operations:item:{item_id}")])
    if not filtered:
        fallback.append("موردی در این بخش ثبت نشده است.")
    elif len(filtered) > len(visible_items):
        fallback.extend(("", f"{to_persian_digits(len(filtered) - len(visible_items))} مورد دیگر در مدیریت کامل سایت قابل مشاهده است."))
    rich = [f"<h2>{html.escape(title)}</h2>"]
    if visible_items:
        rich.append("<table bordered striped compact><caption>موارد ثبت‌شده</caption><tr><th>مورد</th><th>زمان</th><th>وضعیت</th></tr>")
        for item in visible_items:
            kind = _item_label(item.get("type"))
            rich.append(
                f"<tr><td><b>{_visible(item.get('title') or kind, 100)}</b><br/>{_visible(kind)}</td>"
                f"<td>{_visible(_item_effective_time(item), 120)}</td>"
                f"<td>{_visible(_state_label(item.get('status')) or '—', 80)}</td></tr>"
            )
        rich.append("</table>")
        if len(filtered) > len(visible_items):
            rich.append(f"<footer>{to_persian_digits(len(filtered) - len(visible_items))} مورد دیگر در نمای کامل سایت موجود است.</footer>")
    else:
        rich.append("<p>موردی در این بخش ثبت نشده است.</p>")
    rows.append([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def _detail_screen(item: dict[str, Any], actions: dict[str, Any]) -> Screen:
    kind = _item_label(item.get("type"))
    status = _state_label(item.get("status"))
    title = _visible(item.get("title") or kind, 180)
    description = _visible(item.get("description"), 2600)
    facts: list[tuple[str, str]] = [("نوع", kind)]
    if status:
        facts.append(("وضعیت", status))
    course = _item_course(item)
    if course:
        facts.append(("درس", course))
    facts.extend(_timing_values(dict(item.get("timing") or {})))
    if item.get("location"):
        facts.append(("مکان", str(item.get("location") or "")))
    importance = _importance_label(item.get("importance"))
    if importance:
        facts.append(("اهمیت", importance))
    task = dict(item.get("task") or {})
    if task:
        facts.append(("وضعیت من", _state_label(task.get("state") or "pending") or "در انتظار"))
    ack = dict(item.get("ack") or {})
    ack_line = ""
    if ack:
        ack_line = "✅ این اطلاعیه را تأیید کرده‌ای." if ack.get("acked") else "⏳ این اطلاعیه هنوز نیازمند تأیید تو است."
    service = dict(item.get("service") or {})
    service_line = ""
    if service:
        local = dict(service.get("state") or {})
        facts.append(("وضعیت یادآوری", _state_label(local.get("state") or "pending") or "در انتظار"))
        service_line = "این وضعیت فقط در ربات ثبت می‌شود و انجام واقعی در صبا را تأیید نمی‌کند."
    fallback = [f"<b><u>{title}</u></b>", ""]
    for label, value in facts:
        fallback.append(f"<b>{_visible(label)}:</b> {_visible(value)}")
    if ack_line:
        fallback.extend(("", f"<blockquote>{_visible(ack_line)}</blockquote>"))
    if service_line:
        fallback.extend(("", f"<blockquote>{_visible(service_line)}</blockquote>"))
    if description:
        fallback.extend(("", f"<blockquote expandable><b>توضیحات</b>\n{description}</blockquote>"))
    rich = [f"<h2>{title}</h2>", _fact_table("جزئیات", facts)]
    if ack_line:
        rich.append(f"<blockquote>{_visible(ack_line)}</blockquote>")
    if service_line:
        rich.append(f"<blockquote>{_visible(service_line)}</blockquote>")
    if description:
        rich.append(f"<details><summary>توضیحات</summary><p>{description}</p></details>")
    labels = {
        "task_submit": "📤 ارسال شد", "task_complete": "✅ انجام شد", "ack": "✅ دیدم و تأیید می‌کنم",
        "service_completed": "✅ انجام شد (در ربات)", "service_waived": "نیاز نیست", "cancel": "لغو", "archive": "بایگانی",
    }
    rows: list[list[dict]] = []
    for key in ("task_submit", "task_complete", "ack", "service_completed", "service_waived"):
        token = str(actions.get(key) or "")
        if token.startswith("cxo_"):
            rows.append([button(labels[key], action=token, style="success")])
    for key in ("cancel", "archive"):
        token = str(actions.get(key) or "")
        if token.startswith("cxo_"):
            rows.append([button(labels[key], action=token, style="danger")])
    rows.append([button("↩️ فهرست", action="class-operations:list:all"), button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def _digest_item_time(item: dict[str, Any]) -> str:
    timing = dict(item.get("timing") or {})
    if bool(timing.get("allDay")):
        local = _format_local_date(timing.get("localDate"))
        return f"{local} · تمام‌روز" if local else "تمام‌روز"
    due = _format_time(timing.get("dueAtUtc") or timing.get("dueAt"))
    if due:
        return f"{due} · مهلت"
    start = _format_time(timing.get("startsAtUtc") or timing.get("startsAt"))
    if not start:
        return _format_time(item.get("effectiveAtUtc"))
    end = _format_time(timing.get("endsAtUtc") or timing.get("endsAt"))
    return f"{start} تا {end}" if end and end != start else start


def _digest_screen(response: dict[str, Any], *, title: str) -> Screen:
    digest = dict(response.get("digest") or {})
    sections = [section for section in digest.get("sections", []) if isinstance(section, dict)]
    budget = dict(digest.get("budget") or {})
    fallback = [f"<b><u>{html.escape(title)}</u></b>"]
    rich = [f"<h2>{html.escape(title)}</h2>"]
    any_item = False
    for section in sections:
        items = [item for item in section.get("items", []) if isinstance(item, dict)]
        if not items:
            continue
        any_item = True
        label = _plain_visible(section.get("label") or "موارد", 120)
        icon = _DIGEST_SECTION_ICONS.get(str(section.get("key") or ""), "•")
        fallback.extend(("", f"<b>{icon} {_visible(label)}</b>"))
        rich.append(f"<table bordered striped compact><caption>{html.escape(icon)} {_visible(label)}</caption><tr><th>مورد</th><th>زمان</th><th>جزئیات</th></tr>")
        for item in items:
            item_title = _plain_visible(item.get("title") or _item_label(item.get("itemType")), 120)
            change = _plain_visible(item.get("changeLabel"), 40)
            rendered_title = f"[{change}] {item_title}" if change else item_title
            when = _digest_item_time(item)
            course = str(dict(item.get("course") or {}).get("title") or "").strip() if isinstance(item.get("course"), dict) else ""
            location = str(item.get("location") or "").strip()
            detail_bits = [bit for bit in (course, location) if bit]
            fallback.append("• <b>" + _visible(rendered_title) + "</b>" + (f"\n  {_visible(when)}" if when else "") + (f"\n  {_visible(' · '.join(detail_bits))}" if detail_bits else ""))
            rich.append(f"<tr><td><b>{_visible(rendered_title)}</b></td><td>{_visible(when or '—')}</td><td>{_visible(' · '.join(detail_bits) or '—')}</td></tr>")
        rich.append("</table>")
        omitted = int(section.get("omitted") or 0)
        if omitted > 0:
            fallback.append(f"… {to_persian_digits(omitted)} مورد دیگر این بخش نمایش داده نشده است.")
            rich.append(f"<footer>{to_persian_digits(omitted)} مورد دیگر این بخش نمایش داده نشده است.</footer>")
    if not any_item:
        empty = "برای این بازه موردی ثبت نشده است."
        fallback.extend(("", empty))
        rich.append(f"<p>{empty}</p>")
    if bool(budget.get("truncated")):
        omitted = int(budget.get("omittedItems") or 0)
        notice = f"{to_persian_digits(omitted)} مورد دیگر در این خلاصه نمایش داده نشده است."
        fallback.extend(("", f"<blockquote>{notice}</blockquote>"))
        rich.append(f"<footer>{notice}</footer>")
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")]))


def _owner_screen(app: DentBotApp, capabilities: dict[str, Any]) -> Screen:
    ai_state = str(dict(capabilities.get("ai") or {}).get("state") or "unconfigured")
    lines = [
        "<b><u>⚙️ مدیریت امور کلاس</u></b>", "",
        "برای کارهای سریع از ربات استفاده کن؛ تنظیمات کامل مخاطب، زمان و جزئیات از سایت در دسترس است.",
    ]
    lines.extend(("", "🤖 ساخت پیش‌نویس با هوش مصنوعی فعال است؛ بدون تأیید تو چیزی منتشر نمی‌شود." if ai_state == "configured" else "هوش مصنوعی فعلاً فعال نیست؛ ورود دستی در دسترس است."))
    rows = [
        [button("✍️ اطلاعیه سریع", action="class-operations:new-announcement", style="success")],
        [button("🤖 پیش‌نویس با هوش مصنوعی", action="class-operations:ai")],
        [button("موارد فعال", action="class-operations:list:all")],
    ]
    site = _site_url(app)
    if site:
        rows.append([button("مدیریت کامل در سایت", url=site + "/classops/", style="primary")])
    rows.append([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")])
    return Screen("\n".join(lines), keyboard(*rows))


def _quick_announcement_help() -> Screen:
    return Screen(
        frame("✍️ اطلاعیه سریع", "عنوان و متن را با این قالب بفرست:\n/classops draft عنوان | توضیحات", "قبل از ثبت، پیش‌نمایش مخاطبان نمایش داده می‌شود و بدون تأیید تو چیزی منتشر نمی‌شود."),
        keyboard([button("↩️ مدیریت امور کلاس", action="class-operations:owner")], [button("🏠 خانه", action="home")]),
    )


def _ai_help() -> Screen:
    return Screen(
        frame("🤖 ساخت پیش‌نویس با هوش مصنوعی", "بعد از دستور زیر متن آزاد را بنویس:\n/classops ai متن موردنظر", "هوش مصنوعی فقط پیش‌نویس می‌سازد؛ بدون تأیید تو چیزی ثبت یا ارسال نمی‌شود."),
        keyboard([button("↩️ مدیریت امور کلاس", action="class-operations:owner")], [button("🏠 خانه", action="home")]),
    )


def _preview_screen(app: DentBotApp, response: dict[str, Any]) -> Screen:
    preview = dict(response.get("preview") or {})
    item = dict(preview.get("item") or {})
    audience = dict(preview.get("audience") or {})
    destinations = [value for value in preview.get("destinations", []) if isinstance(value, dict)]
    token = str(response.get("confirmToken") or "")
    facts: list[tuple[str, str]] = [("نوع", _item_label(item.get("type"))), ("عنوان", str(item.get("title") or "بدون عنوان"))]
    course = _item_course(item)
    if course:
        facts.append(("درس", course))
    facts.extend(_timing_values(dict(item.get("timing") or {})))
    if item.get("location"):
        facts.append(("مکان", str(item.get("location") or "")))
    facts.extend((("مخاطبان", f"{to_persian_digits(int(audience.get('total') or 0))} نفر"), ("مسیرهای ارسال", to_persian_digits(len(destinations)))))
    description = _visible(item.get("description"), 1600)
    warnings = [str(value) for value in audience.get("warnings", []) if value]
    unresolved_count = int(audience.get("unresolvedCount") or 0)
    fallback = ["<b><u>👁 پیش‌نمایش قبل از ثبت</u></b>", ""]
    for label, value in facts:
        fallback.append(f"<b>{_visible(label)}:</b> {_visible(value)}")
    if description:
        fallback.extend(("", f"<blockquote expandable><b>توضیحات</b>\n{description}</blockquote>"))
    if unresolved_count or warnings:
        warning_text = []
        if unresolved_count:
            warning_text.append(f"{to_persian_digits(unresolved_count)} مورد نیازمند بررسی")
        if warnings:
            warning_text.append("هشدارهای مخاطبان وجود دارد")
        fallback.extend(("", f"<blockquote>{_visible(' · '.join(warning_text))}</blockquote>"))
    rich = ["<h2>👁 پیش‌نمایش قبل از ثبت</h2>", _fact_table("جزئیات ثبت", facts)]
    if description:
        rich.append(f"<details><summary>توضیحات</summary><p>{description}</p></details>")
    if unresolved_count or warnings:
        warning_bits = []
        if unresolved_count:
            warning_bits.append(f"{to_persian_digits(unresolved_count)} مورد نیازمند بررسی")
        if warnings:
            warning_bits.append("هشدارهای مخاطبان وجود دارد")
        rich.append(f"<details><summary>موارد نیازمند بررسی</summary><p>{_visible(' · '.join(warning_bits))}</p></details>")
    rich.append("<footer>تا قبل از تأیید صریح، چیزی ثبت یا ارسال نمی‌شود.</footer>")
    rows: list[list[dict]] = []
    if token.startswith("cxo_"):
        rows.append([button("✅ تأیید و ثبت", action=token, style="success")])
    site = _site_url(app)
    if site:
        rows.append([button("ویرایش کامل در سایت", url=site + "/classops/", style="primary")])
    rows.append([button("↩️ مدیریت امور کلاس", action="class-operations:owner"), button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def _ai_draft_screen(app: DentBotApp, response: dict[str, Any]) -> Screen:
    draft = dict(response.get("draft") or {})
    fields = dict(draft.get("fields") or {})
    unresolved = [str(value) for value in draft.get("unresolved", []) if value]
    facts: list[tuple[str, str]] = []
    labels = {"type": "نوع", "title": "عنوان", "location": "مکان", "importance": "اهمیت"}
    for key in ("type", "title", "location", "importance"):
        value = fields.get(key)
        if value in (None, ""):
            continue
        rendered = _item_label(value) if key == "type" else (_importance_label(value) or str(value) if key == "importance" else str(value))
        facts.append((labels[key], rendered))
    description = _visible(fields.get("description"), 1600)
    fallback = ["<b><u>🤖 پیش‌نویس پیشنهادی</u></b>", "", "هوش مصنوعی فقط پیش‌نویس می‌سازد؛ بدون تأیید تو چیزی ثبت یا ارسال نمی‌شود."]
    for label, value in facts:
        fallback.append(f"<b>{_visible(label)}:</b> {_visible(value)}")
    if description:
        fallback.extend(("", f"<blockquote expandable><b>توضیحات</b>\n{description}</blockquote>"))
    if unresolved:
        fallback.extend(("", f"<blockquote>مواردی که باید خودت مشخص کنی: {_visible('، '.join(unresolved), 1000)}</blockquote>"))
    rich = ["<h2>🤖 پیش‌نویس پیشنهادی</h2>", "<blockquote>هوش مصنوعی فقط پیش‌نویس می‌سازد؛ بدون تأیید تو چیزی ثبت یا ارسال نمی‌شود.</blockquote>"]
    if facts:
        rich.append(_fact_table("فیلدهای پیشنهادی", facts))
    if description:
        rich.append(f"<details><summary>توضیحات</summary><p>{description}</p></details>")
    if unresolved:
        rich.append(f"<details><summary>موارد نیازمند تکمیل</summary><p>{_visible('، '.join(unresolved), 1000)}</p></details>")
    site = _site_url(app)
    rows: list[list[dict]] = []
    if site:
        rows.append([button("ویرایش و ادامه در سایت", url=site + "/classops/", style="primary")])
    rows.append([button("↩️ مدیریت امور کلاس", action="class-operations:owner"), button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def _parse_context(update: dict[str, Any]) -> tuple[dict[str, Any], dict[str, Any], dict[str, Any], int, int, str, str]:
    callback = dict(update.get("callback_query") or {})
    message = dict(callback.get("message") or update.get("message") or {})
    sender = dict(callback.get("from") or message.get("from") or {})
    chat = dict(message.get("chat") or {})
    try:
        chat_id = int(chat.get("id") or sender.get("id") or 0)
    except (TypeError, ValueError):
        chat_id = 0
    try:
        user_id = int(sender.get("id") or 0)
    except (TypeError, ValueError):
        user_id = 0
    text = str(message.get("text") or "").strip()
    raw_data = str(callback.get("data") or "").strip()
    data = raw_data[3:] if raw_data.startswith("v1:") else raw_data
    return callback, chat, sender, chat_id, user_id, text, data


def _legacy_action(data: str) -> str:
    if not data.startswith("classops:"):
        return data
    suffix = data[len("classops:"):]
    mapping = {
        "menu": "class-operations", "items": "class-operations:list:all", "tomorrow": "class-operations:tomorrow",
        "weekly": "class-operations:weekly", "draft-help": "class-operations:new-announcement", "ai-help": "class-operations:ai",
    }
    if suffix.startswith("item:"):
        return "class-operations:" + suffix
    return mapping.get(suffix, data)


def _handle_product(app: DentBotApp, update: dict[str, Any]) -> bool:
    callback, chat, _sender, chat_id, user_id, text, data = _parse_context(update)
    data = _legacy_action(data)
    lower = text.lower()
    command = lower.split(maxsplit=1)[0] if lower else ""
    is_command = command == "/classops" or command.startswith("/classops@")
    is_callback = data == "class-operations" or data.startswith("class-operations:") or data.startswith("cxo_")
    if not is_command and not is_callback:
        return False
    if chat_id == 0 or user_id == 0:
        return True
    if str(chat.get("type") or "private") != "private":
        app.api.send(chat_id, "امور کلاس فقط در گفت‌وگوی خصوصی ربات در دسترس است.", {"inline_keyboard": []})
        return True
    callback_id = str(callback.get("id") or "")
    if callback_id:
        try:
            app.api.answer_callback(callback_id, "در حال بررسی…")
        except Exception:
            pass

    def show(screen: Screen) -> None:
        _render_screen(app, chat_id, screen, callback=callback if callback else None)

    try:
        if data.startswith("cxo_"):
            app.site_api.request("classopsResolveAction", user_id, token=data)
            show(Screen("<b>✅ انجام شد.</b>\n\nوضعیت این مورد به‌روزرسانی شد.", keyboard([button("↩️ امور کلاس", action="class-operations")], [button("🏠 خانه", action="home")])))
            return True
        if is_command and lower.startswith("/classops draft "):
            body = text[len("/classops draft "):].strip()
            title, separator, description = body.partition("|")
            title = title.strip()
            description = description.strip() if separator else ""
            if not title:
                show(_quick_announcement_help())
                return True
            request = {
                "item": {"cohortKey": "dentistry-1402", "type": "announcement", "title": title[:160], "description": description[:4000], "importance": "normal", "requireAck": False, "status": "draft"},
                "audienceSpec": {"version": "classops-audience-v1", "resolutionMode": "snapshot", "expression": {"op": "whole_cohort"}, "includeStudentNumbers": [], "excludeStudentNumbers": []},
                "destinations": ["private_users"],
            }
            show(_preview_screen(app, app.site_api.request("classopsOwnerPreview", user_id, request=request)))
            return True
        if is_command and lower.startswith("/classops ai "):
            owner_text = text[len("/classops ai "):].strip()
            if not owner_text:
                show(_ai_help())
                return True
            response = app.site_api.request("classopsOwnerAiDraft", user_id, ownerText=owner_text, cohortKey="dentistry-1402")
            show(_ai_draft_screen(app, response))
            return True
        action = data or "class-operations"
        if is_command and not data:
            action = "class-operations"
        if action == "class-operations":
            capabilities = app.site_api.request("classopsCapabilities", user_id)
            listing = app.site_api.request("classopsList", user_id, limit=30)
            items = [item for item in dict(listing.get("data") or {}).get("items", []) if isinstance(item, dict)]
            show(_class_home_screen(app, role=str(capabilities.get("role") or "student"), items=items))
            return True
        if action.startswith("class-operations:list:"):
            filter_name = action.rsplit(":", 1)[-1]
            listing = app.site_api.request("classopsList", user_id, limit=50)
            items = [item for item in dict(listing.get("data") or {}).get("items", []) if isinstance(item, dict)]
            show(_list_screen(items, filter_name))
            return True
        if action.startswith("class-operations:item:cop_"):
            item_id = action[len("class-operations:item:"):]
            response = app.site_api.request("classopsGet", user_id, id=item_id)
            show(_detail_screen(dict(response.get("item") or {}), dict(response.get("actions") or {})))
            return True
        if action == "class-operations:tomorrow":
            show(_digest_screen(app.site_api.request("classopsTomorrowSummary", user_id), title="🌤 فردا"))
            return True
        if action == "class-operations:weekly":
            show(_digest_screen(app.site_api.request("classopsWeeklyDigest", user_id), title="🗓 هفته پیش رو"))
            return True
        if action == "class-operations:owner":
            capabilities = app.site_api.request("classopsCapabilities", user_id)
            if str(capabilities.get("role") or "student") != "owner":
                show(Screen("این بخش فقط برای مدیریت کلاس در دسترس است.", keyboard([button("↩️ امور کلاس", action="class-operations")], [button("🏠 خانه", action="home")])))
                return True
            show(_owner_screen(app, dict(capabilities.get("capabilities") or {})))
            return True
        if action == "class-operations:new-announcement":
            show(_quick_announcement_help())
            return True
        if action == "class-operations:ai":
            show(_ai_help())
            return True
        show(Screen("این گزینه دیگر فعال نیست؛ از منوی امور کلاس استفاده کن.", keyboard([button("↩️ امور کلاس", action="class-operations")], [button("🏠 خانه", action="home")])))
        return True
    except SiteApiError:
        show(Screen("<b>⚠️ این بخش فعلاً در دسترس نیست.</b>\n\nچند لحظه بعد دوباره امتحان کن.", keyboard([button("↩️ امور کلاس", action="class-operations")], [button("🏠 خانه", action="home")])))
        return True


def install_class_operations_product() -> None:
    """Mount ClassOps as a first-class DentBot feature while keeping backend contracts intact."""
    global _INSTALLED
    if _INSTALLED:
        return
    install_classops_runtime()
    from . import app as app_module
    original_home = app_module.home

    def home_wrapper(*args: Any, **kwargs: Any) -> Screen:
        return _home_keyboard(original_home(*args, **kwargs))

    app_module.home = home_wrapper  # type: ignore[assignment]
    original_handle: Callable[[DentBotApp, dict[str, Any]], None] = DentBotApp.handle

    def handle(self: DentBotApp, update: dict[str, Any]) -> None:
        if _handle_product(self, update):
            return
        original_handle(self, update)

    DentBotApp.handle = handle  # type: ignore[method-assign]
    _INSTALLED = True
