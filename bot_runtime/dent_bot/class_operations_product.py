from __future__ import annotations

import html
from typing import Any, Callable

from .app import DentBotApp
from .classops_runtime import install_classops_runtime
from .site_api import SiteApiError
from .ui import Screen, button, frame, keyboard

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
}

_FILTERS: dict[str, set[str]] = {
    "schedule": {"event", "class_change", "deadline"},
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


def _safe(value: object, limit: int = 1800) -> str:
    return html.escape(str(value or "").replace("\x00", "").strip()[:limit])


def _site_url(app: DentBotApp) -> str:
    value = str(getattr(app, "site_url", "") or "").rstrip("/")
    return value if value.startswith("https://") else ""


def _send(app: DentBotApp, chat_id: int, screen: Screen) -> None:
    app.api.send(chat_id, screen.text, screen.keyboard)


def _home_keyboard(screen: Screen) -> Screen:
    rows = [list(row) for row in screen.keyboard.get("inline_keyboard", [])]
    if any(
        str(item.get("callback_data") or "").endswith(":class-operations")
        for row in rows
        for item in row
        if isinstance(item, dict)
    ):
        return screen
    entry = [button("📚 امور کلاس", action="class-operations", style="primary")]
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
    lines = [
        "<b><u>📚 امور کلاس</u></b>",
        "",
        "برنامه‌ها، تکالیف، امتحان‌ها و اطلاعیه‌های کلاس را از همین‌جا دنبال کن.",
    ]
    if active:
        lines.extend((
            "",
            f"<blockquote>فعال: <b>{len(active)}</b> · تکلیف: <b>{tasks}</b> · امتحان: <b>{exams}</b>" +
            (f" · مهم: <b>{important}</b>" if important else "") + "</blockquote>",
        ))
    else:
        lines.extend(("", "<blockquote>فعلاً مورد فعالی ثبت نشده است.</blockquote>"))

    rows = [
        [button("📅 برنامه و تغییرات", action="class-operations:list:schedule"), button("✅ تکالیف و کارها", action="class-operations:list:tasks")],
        [button("📝 امتحان‌ها", action="class-operations:list:exams"), button("🔔 اطلاعیه‌ها", action="class-operations:list:important")],
        [button("🌤 فردا", action="class-operations:tomorrow", style="primary"), button("🗓 هفته پیش رو", action="class-operations:weekly")],
        [button("📋 همه موارد", action="class-operations:list:all")],
    ]
    if role == "owner":
        rows.append([button("⚙️ مدیریت امور کلاس", action="class-operations:owner", style="success")])
    rows.append([button("🏠 منوی اصلی", action="home")])
    return Screen("\n".join(lines), keyboard(*rows))


def _item_line(item: dict[str, Any]) -> str:
    kind = _ITEM_LABELS.get(str(item.get("type") or ""), "مورد کلاس")
    title = _safe(item.get("title") or kind, 90)
    state = _STATE_LABELS.get(str(item.get("status") or ""), "")
    suffix = f" · {state}" if state else ""
    return f"• <b>{title}</b>\n  {kind}{suffix}"


def _list_screen(items: list[dict[str, Any]], filter_name: str) -> Screen:
    allowed = _FILTERS.get(filter_name)
    filtered = [item for item in items if allowed is None or str(item.get("type") or "") in allowed]
    title = _FILTER_TITLES.get(filter_name, _FILTER_TITLES["all"])
    lines = [f"<b><u>{html.escape(title)}</u></b>", ""]
    rows: list[list[dict]] = []
    for item in filtered[:12]:
        lines.append(_item_line(item))
        item_id = str(item.get("id") or "")
        label = str(item.get("title") or _ITEM_LABELS.get(str(item.get("type") or ""), "مشاهده"))
        if item_id.startswith("cop_"):
            rows.append([button(f"مشاهده · {label[:30]}", action=f"class-operations:item:{item_id}")])
    if not filtered:
        lines.append("موردی در این بخش ثبت نشده است.")
    elif len(filtered) > 12:
        lines.extend(("", f"{len(filtered) - 12} مورد دیگر در مدیریت کامل سایت قابل مشاهده است."))
    rows.append([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")])
    return Screen("\n".join(lines), keyboard(*rows))


def _detail_screen(item: dict[str, Any], actions: dict[str, Any]) -> Screen:
    kind = _ITEM_LABELS.get(str(item.get("type") or ""), "مورد کلاس")
    status = _STATE_LABELS.get(str(item.get("status") or ""), "")
    lines = [f"<b><u>{_safe(item.get('title') or kind, 180)}</u></b>", "", f"{kind}" + (f" · {status}" if status else "")]
    description = _safe(item.get("description"), 2600)
    if description:
        lines.extend(("", description))
    timing = dict(item.get("timing") or {})
    for key, label in (("startsAt", "شروع"), ("endsAt", "پایان"), ("dueAt", "مهلت")):
        value = timing.get(key)
        if value:
            lines.append(f"{label}: <code>{_safe(value, 100)}</code>")
    if item.get("location"):
        lines.append("مکان: " + _safe(item.get("location"), 240))

    task = dict(item.get("task") or {})
    if task:
        state = _STATE_LABELS.get(str(task.get("state") or "pending"), "در انتظار")
        lines.extend(("", f"وضعیت من: <b>{state}</b>"))
    ack = dict(item.get("ack") or {})
    if ack:
        lines.extend(("", "✅ این اطلاعیه را تأیید کرده‌ای." if ack.get("acked") else "⏳ این اطلاعیه هنوز نیازمند تأیید تو است."))
    service = dict(item.get("service") or {})
    if service:
        local = dict(service.get("state") or {})
        state = _STATE_LABELS.get(str(local.get("state") or "pending"), "در انتظار")
        lines.extend(("", f"وضعیت یادآوری: <b>{state}</b>", "<i>این وضعیت فقط در ربات ثبت می‌شود و انجام واقعی در صبا را تأیید نمی‌کند.</i>"))

    labels = {
        "task_submit": "📤 ارسال شد",
        "task_complete": "✅ انجام شد",
        "ack": "✅ دیدم و تأیید می‌کنم",
        "service_completed": "✅ انجام شد (در ربات)",
        "service_waived": "نیاز نیست",
        "cancel": "لغو",
        "archive": "بایگانی",
    }
    rows: list[list[dict]] = []
    for key in ("task_submit", "task_complete", "ack", "service_completed", "service_waived"):
        token = str(actions.get(key) or "")
        if token.startswith("cxo_"):
            rows.append([button(labels[key], action=token, style="success" if key in {"task_complete", "ack", "service_completed"} else "")])
    for key in ("cancel", "archive"):
        token = str(actions.get(key) or "")
        if token.startswith("cxo_"):
            rows.append([button(labels[key], action=token, style="danger")])
    rows.append([button("↩️ فهرست", action="class-operations:list:all"), button("🏠 خانه", action="home")])
    return Screen("\n".join(lines), keyboard(*rows))


def _digest_screen(response: dict[str, Any], *, title: str) -> Screen:
    digest = dict(response.get("digest") or {})
    plain = str(digest.get("plainText") or "").strip()
    body = _safe(plain or "موردی ثبت نشده است.", 3500)
    return Screen(
        f"<b><u>{html.escape(title)}</u></b>\n\n{body}",
        keyboard([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")]),
    )


def _owner_screen(app: DentBotApp, capabilities: dict[str, Any]) -> Screen:
    ai_state = str(dict(capabilities.get("ai") or {}).get("state") or "unconfigured")
    lines = [
        "<b><u>⚙️ مدیریت امور کلاس</u></b>",
        "",
        "برای کارهای سریع از ربات استفاده کن؛ تنظیمات کامل مخاطب، زمان و جزئیات از سایت در دسترس است.",
    ]
    if ai_state == "configured":
        lines.extend(("", "🤖 ساخت پیش‌نویس با هوش مصنوعی فعال است؛ بدون تأیید تو چیزی منتشر نمی‌شود."))
    else:
        lines.extend(("", "هوش مصنوعی فعلاً فعال نیست؛ ورود دستی در دسترس است."))
    rows = [
        [button("✍️ اطلاعیه سریع", action="class-operations:new-announcement", style="success")],
        [button("🤖 پیش‌نویس با هوش مصنوعی", action="class-operations:ai")],
        [button("📋 موارد فعال", action="class-operations:list:all")],
    ]
    site = _site_url(app)
    if site:
        rows.append([button("مدیریت کامل در سایت", url=site + "/classops/", style="primary")])
    rows.append([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")])
    return Screen("\n".join(lines), keyboard(*rows))


def _quick_announcement_help() -> Screen:
    return Screen(
        frame(
            "✍️ اطلاعیه سریع",
            "عنوان و متن را با این قالب بفرست:\n/classops draft عنوان | توضیحات",
            "قبل از ثبت، پیش‌نمایش مخاطبان نمایش داده می‌شود و بدون تأیید تو چیزی منتشر نمی‌شود.",
        ),
        keyboard([button("↩️ مدیریت امور کلاس", action="class-operations:owner")], [button("🏠 خانه", action="home")]),
    )


def _ai_help() -> Screen:
    return Screen(
        frame(
            "🤖 ساخت پیش‌نویس با هوش مصنوعی",
            "بعد از دستور زیر متن آزاد را بنویس:\n/classops ai متن موردنظر",
            "هوش مصنوعی فقط پیش‌نویس می‌سازد؛ بدون تأیید تو چیزی ثبت یا ارسال نمی‌شود.",
        ),
        keyboard([button("↩️ مدیریت امور کلاس", action="class-operations:owner")], [button("🏠 خانه", action="home")]),
    )


def _preview_screen(app: DentBotApp, response: dict[str, Any]) -> Screen:
    preview = dict(response.get("preview") or {})
    item = dict(preview.get("item") or {})
    audience = dict(preview.get("audience") or {})
    destinations = preview.get("destinations") or []
    token = str(response.get("confirmToken") or "")
    kind = _ITEM_LABELS.get(str(item.get("type") or ""), "مورد کلاس")
    lines = [
        "<b><u>👁 پیش‌نمایش قبل از ثبت</u></b>",
        "",
        f"نوع: <b>{kind}</b>",
        f"عنوان: <b>{_safe(item.get('title') or 'بدون عنوان', 180)}</b>",
    ]
    if item.get("description"):
        lines.extend(("", _safe(item.get("description"), 1600)))
    lines.extend(("", f"مخاطبان: <b>{int(audience.get('total') or 0)}</b> نفر", f"مقصدهای انتخاب‌شده: <b>{len(destinations)}</b>"))
    rows: list[list[dict]] = []
    if token.startswith("cxo_"):
        rows.append([button("✅ تأیید و ثبت", action=token, style="success")])
    site = _site_url(app)
    if site:
        rows.append([button("ویرایش کامل در سایت", url=site + "/classops/")])
    rows.append([button("↩️ مدیریت امور کلاس", action="class-operations:owner"), button("🏠 خانه", action="home")])
    return Screen("\n".join(lines), keyboard(*rows))


def _ai_draft_screen(app: DentBotApp, response: dict[str, Any]) -> Screen:
    draft = dict(response.get("draft") or {})
    fields = dict(draft.get("fields") or {})
    unresolved = [str(value) for value in draft.get("unresolved", []) if value]
    lines = ["<b><u>🤖 پیش‌نویس پیشنهادی</u></b>", "", "این پیش‌نویس هنوز چیزی را ثبت یا ارسال نکرده است."]
    labels = {"type": "نوع", "title": "عنوان", "description": "توضیحات", "location": "مکان", "importance": "اهمیت"}
    for key in ("type", "title", "description", "location", "importance"):
        value = fields.get(key)
        if value not in (None, ""):
            rendered = _ITEM_LABELS.get(str(value), str(value)) if key == "type" else str(value)
            lines.append(f"<b>{labels[key]}:</b> {_safe(rendered, 1000)}")
    if unresolved:
        lines.extend(("", "مواردی که باید خودت مشخص کنی: " + _safe("، ".join(unresolved), 1000)))
    site = _site_url(app)
    rows: list[list[dict]] = []
    if site:
        rows.append([button("ویرایش و ادامه در سایت", url=site + "/classops/", style="primary")])
    rows.append([button("↩️ مدیریت امور کلاس", action="class-operations:owner"), button("🏠 خانه", action="home")])
    return Screen("\n".join(lines), keyboard(*rows))


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
        "menu": "class-operations",
        "items": "class-operations:list:all",
        "tomorrow": "class-operations:tomorrow",
        "weekly": "class-operations:weekly",
        "draft-help": "class-operations:new-announcement",
        "ai-help": "class-operations:ai",
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
    try:
        if data.startswith("cxo_"):
            app.site_api.request("classopsResolveAction", user_id, token=data)
            _send(app, chat_id, Screen("<b>✅ انجام شد.</b>\n\nوضعیت این مورد به‌روزرسانی شد.", keyboard([button("↩️ امور کلاس", action="class-operations")], [button("🏠 خانه", action="home")])))
            return True

        if is_command and lower.startswith("/classops draft "):
            body = text[len("/classops draft "):].strip()
            title, separator, description = body.partition("|")
            title = title.strip()
            description = description.strip() if separator else ""
            if not title:
                _send(app, chat_id, _quick_announcement_help())
                return True
            request = {
                "item": {
                    "cohortKey": "dentistry-1402",
                    "type": "announcement",
                    "title": title[:160],
                    "description": description[:4000],
                    "importance": "normal",
                    "requireAck": False,
                    "status": "draft",
                },
                "audienceSpec": {
                    "version": "classops-audience-v1",
                    "resolutionMode": "snapshot",
                    "expression": {"op": "whole_cohort"},
                    "includeStudentNumbers": [],
                    "excludeStudentNumbers": [],
                },
                "destinations": ["private_users"],
            }
            _send(app, chat_id, _preview_screen(app, app.site_api.request("classopsOwnerPreview", user_id, request=request)))
            return True

        if is_command and lower.startswith("/classops ai "):
            owner_text = text[len("/classops ai "):].strip()
            if not owner_text:
                _send(app, chat_id, _ai_help())
                return True
            response = app.site_api.request("classopsOwnerAiDraft", user_id, ownerText=owner_text, cohortKey="dentistry-1402")
            _send(app, chat_id, _ai_draft_screen(app, response))
            return True

        action = data or "class-operations"
        if is_command and action == "":
            action = "class-operations"
        if is_command and not data and lower.strip().split(maxsplit=1)[0].startswith("/classops"):
            action = "class-operations"

        if action == "class-operations":
            capabilities = app.site_api.request("classopsCapabilities", user_id)
            listing = app.site_api.request("classopsList", user_id, limit=30)
            items = [item for item in dict(listing.get("data") or {}).get("items", []) if isinstance(item, dict)]
            _send(app, chat_id, _class_home_screen(app, role=str(capabilities.get("role") or "student"), items=items))
            return True
        if action.startswith("class-operations:list:"):
            filter_name = action.rsplit(":", 1)[-1]
            listing = app.site_api.request("classopsList", user_id, limit=50)
            items = [item for item in dict(listing.get("data") or {}).get("items", []) if isinstance(item, dict)]
            _send(app, chat_id, _list_screen(items, filter_name))
            return True
        if action.startswith("class-operations:item:cop_"):
            item_id = action[len("class-operations:item:"):]
            response = app.site_api.request("classopsGet", user_id, id=item_id)
            _send(app, chat_id, _detail_screen(dict(response.get("item") or {}), dict(response.get("actions") or {})))
            return True
        if action == "class-operations:tomorrow":
            _send(app, chat_id, _digest_screen(app.site_api.request("classopsTomorrowSummary", user_id), title="🌤 فردا"))
            return True
        if action == "class-operations:weekly":
            _send(app, chat_id, _digest_screen(app.site_api.request("classopsWeeklyDigest", user_id), title="🗓 هفته پیش رو"))
            return True
        if action == "class-operations:owner":
            capabilities = app.site_api.request("classopsCapabilities", user_id)
            if str(capabilities.get("role") or "student") != "owner":
                _send(app, chat_id, Screen("این بخش فقط برای مدیریت کلاس در دسترس است.", keyboard([button("↩️ امور کلاس", action="class-operations")], [button("🏠 خانه", action="home")])))
                return True
            _send(app, chat_id, _owner_screen(app, dict(capabilities.get("capabilities") or {})))
            return True
        if action == "class-operations:new-announcement":
            _send(app, chat_id, _quick_announcement_help())
            return True
        if action == "class-operations:ai":
            _send(app, chat_id, _ai_help())
            return True

        _send(app, chat_id, Screen("این گزینه دیگر فعال نیست؛ از منوی امور کلاس استفاده کن.", keyboard([button("↩️ امور کلاس", action="class-operations")], [button("🏠 خانه", action="home")])))
        return True
    except SiteApiError:
        _send(app, chat_id, Screen("<b>⚠️ این بخش فعلاً در دسترس نیست.</b>\n\nچند لحظه بعد دوباره امتحان کن.", keyboard([button("↩️ امور کلاس", action="class-operations")], [button("🏠 خانه", action="home")])))
        return True


def install_class_operations_product() -> None:
    """Mount ClassOps as a first-class DentBot feature while keeping its backend contracts intact."""
    global _INSTALLED
    if _INSTALLED:
        return

    # Keep the existing ClassOps background delivery/scheduler adapter, then wrap
    # its user-facing handler with the native DentBot product presentation.
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
