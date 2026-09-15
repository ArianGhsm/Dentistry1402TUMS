from __future__ import annotations

from datetime import datetime, time
import re
from typing import Any, Callable
from zoneinfo import ZoneInfo

from . import class_operations as classops
from .app import DentBotApp
from .persian_datetime import jalali_to_gregorian
from .site_api import SiteApiError
from .ui import Screen, button, keyboard


DIALOG_KIND = "classops-owner-compose"
LEGACY_DIALOG_KINDS = {DIALOG_KIND, "classops-ux-v2"}
CREATE_TYPES = {
    "exam": ("📝", "امتحان"),
    "event": ("📌", "رویداد"),
    "task": ("✅", "کار"),
    "deadline": ("⏳", "ددلاین"),
    "requirement": ("📎", "الزام"),
}
_PERSIAN_TO_LATIN = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")
_TEHRAN = ZoneInfo("Asia/Tehran")


def dialog_kind_supported(value: object) -> bool:
    return str(value or "") in LEGACY_DIALOG_KINDS


def parse_jalali_datetime(value: str) -> str:
    clean = value.strip().translate(_PERSIAN_TO_LATIN)
    match = re.fullmatch(r"(1[34]\d{2})[/.-](\d{1,2})[/.-](\d{1,2})\s+(\d{1,2}):(\d{2})", clean)
    if not match:
        raise ValueError("invalid jalali datetime")
    jy, jm, jd, hour, minute = (int(part) for part in match.groups())
    if hour > 23 or minute > 59:
        raise ValueError("invalid time")
    gregorian = jalali_to_gregorian(jy, jm, jd)
    local = datetime.combine(gregorian, time(hour, minute), tzinfo=_TEHRAN)
    return local.isoformat(timespec="seconds")


def _audience_spec() -> dict[str, Any]:
    return {
        "version": "classops-audience-v1",
        "resolutionMode": "snapshot",
        "expression": {"op": "whole_cohort"},
        "includeStudentNumbers": [],
        "excludeStudentNumbers": [],
    }


def _timing_for(item_type: str, iso_value: str) -> dict[str, Any]:
    if item_type in {"task", "deadline", "requirement"}:
        return {"dueAt": iso_value}
    return {"startsAt": iso_value}


def build_create_request(item_type: str, title: str, description: str, iso_value: str) -> dict[str, Any]:
    if item_type not in CREATE_TYPES:
        raise ValueError("unsupported item type")
    return {
        "item": {
            "cohortKey": "dentistry-1402",
            "type": item_type,
            "title": title[:160],
            "description": description[:4000],
            "timing": _timing_for(item_type, iso_value),
            "importance": "important" if item_type in {"exam", "deadline"} else "normal",
            "requireAck": False,
            "status": "draft",
        },
        "audienceSpec": _audience_spec(),
        "destinations": ["private_users"],
    }


def composer_prompt(item_type: str, step: str, *, editing: bool = False) -> Screen:
    icon, label = CREATE_TYPES.get(item_type, ("🗂", "آیتم"))
    prefix = "ویرایش" if editing else "افزودن"
    if step.endswith("title"):
        text = f"<b>{icon} {prefix} {label}</b>\n\nعنوان را بفرست." + ("\nبرای نگه‌داشتن عنوان فعلی «-» بفرست." if editing else "")
    elif step.endswith("description"):
        text = f"<b>{icon} {prefix} {label}</b>\n\nتوضیح را بفرست؛ برای خالی‌گذاشتن یا نگه‌داشتن مقدار فعلی «-» بفرست."
    else:
        text = f"<b>{icon} {prefix} {label}</b>\n\nتاریخ و ساعت شمسی را مثل <code>۱۴۰۵/۰۶/۲۰ ۱۰:۳۰</code> بفرست." + ("\nبرای نگه‌داشتن زمان فعلی «-» بفرست." if editing else "")
    return Screen(text, keyboard([button("انصراف", action="c3:o:compose-cancel")]))


def confirm_lifecycle_screen(token: str, verb: str) -> Screen:
    label = "لغو" if verb == "cancel" else "بایگانی"
    return Screen(
        f"<b>⚠️ تأیید {label}</b>\n\nاین تغییر روی آیتم اصلی اعمال می‌شود. برای ادامه، تأیید نهایی را بزن.",
        keyboard(
            [button(f"تأیید {label}", action=token, style="danger")],
            [button("↩️ انصراف", action="c3:owner")],
            [button("🏠 خانه", action="home")],
        ),
    )


def decorate_owner_detail(screen: Screen, item: dict[str, Any], actions: dict[str, Any]) -> Screen:
    if not any(str(actions.get(key) or "").startswith("cxo_") for key in ("cancel", "archive")):
        return screen
    rows: list[list[dict[str, Any]]] = []
    for row in dict(screen.keyboard or {}).get("inline_keyboard", []):
        new_row: list[dict[str, Any]] = []
        for entry in row:
            current = dict(entry)
            data = str(current.get("callback_data") or "")
            raw = data[3:] if data.startswith("v1:") else data
            if raw == str(actions.get("cancel") or ""):
                current["callback_data"] = "v1:c3:o:confirm-cancel:" + raw
            elif raw == str(actions.get("archive") or ""):
                current["callback_data"] = "v1:c3:o:confirm-archive:" + raw
            new_row.append(current)
        rows.append(new_row)
    item_id = str(item.get("id") or "")
    if item_id.startswith("cop_") and str(item.get("status") or "") not in {"cancelled", "archived"}:
        rows.insert(0, [button("✏️ ویرایش", action=f"c3:o:edit:{item_id}", style="primary")])
    return Screen(screen.text, {"inline_keyboard": rows})


def start_dialog(app: DentBotApp, user_id: int, item_type: str, origin_message_id: int, *, edit_item: dict[str, Any] | None = None) -> Screen:
    payload: dict[str, Any] = {"itemType": item_type, "originMessageId": origin_message_id}
    if edit_item is not None:
        payload["itemId"] = str(edit_item.get("id") or "")
        payload["expectedRevision"] = int(edit_item.get("revision") or 0)
        payload["currentTitle"] = str(edit_item.get("title") or "")
        payload["currentDescription"] = str(edit_item.get("description") or "")
        payload["currentTiming"] = dict(edit_item.get("timing") or {})
        app.state.start_dialog(user_id, DIALOG_KIND, "edit-title", payload)
        return composer_prompt(item_type, "edit-title", editing=True)
    app.state.start_dialog(user_id, DIALOG_KIND, "create-title", payload)
    return composer_prompt(item_type, "create-title")


def _render(app: DentBotApp, chat_id: int, screen: Screen, *, message_id: int = 0) -> None:
    classops._render_screen(app, chat_id, screen, message_id=message_id)


def handle_dialog_message(app: DentBotApp, message: dict[str, Any], dialog: dict[str, Any], owner_home: Callable[[], Screen]) -> bool:
    sender = dict(message.get("from") or {})
    chat = dict(message.get("chat") or {})
    try:
        user_id = int(sender.get("id") or 0)
        chat_id = int(chat.get("id") or user_id)
    except (TypeError, ValueError):
        return True
    if user_id != int(getattr(app, "owner_id", -1)):
        app.state.clear_dialog(user_id)
        return False
    text = str(message.get("text") or "").strip()
    if not text:
        return True
    payload = dict(dialog.get("payload") or {})
    item_type = str(payload.get("itemType") or "")
    step = str(dialog.get("step") or "")
    origin = int(payload.get("originMessageId") or 0)

    def show(screen: Screen) -> None:
        _render(app, chat_id, screen, message_id=origin)

    try:
        if step in {"create-title", "edit-title"}:
            if text != "-" and len(text) < 3:
                show(composer_prompt(item_type, step, editing=step.startswith("edit")))
                return True
            if text != "-":
                payload["title"] = text[:160]
            next_step = "edit-description" if step.startswith("edit") else "create-description"
            app.state.update_dialog(user_id, step=next_step, payload=payload)
            show(composer_prompt(item_type, next_step, editing=next_step.startswith("edit")))
            return True

        if step in {"create-description", "edit-description"}:
            if text != "-":
                payload["description"] = text[:4000]
            next_step = "edit-time" if step.startswith("edit") else "create-time"
            app.state.update_dialog(user_id, step=next_step, payload=payload)
            show(composer_prompt(item_type, next_step, editing=next_step.startswith("edit")))
            return True

        if step in {"create-time", "edit-time"}:
            editing = step.startswith("edit")
            iso_value = ""
            if text != "-":
                try:
                    iso_value = parse_jalali_datetime(text)
                except ValueError:
                    show(Screen(
                        "<b>⚠️ تاریخ معتبر نیست</b>\n\nمثل <code>۱۴۰۵/۰۶/۲۰ ۱۰:۳۰</code> بفرست.",
                        composer_prompt(item_type, step, editing=editing).keyboard,
                    ))
                    return True
            app.state.clear_dialog(user_id)
            if editing:
                patch: dict[str, Any] = {}
                if "title" in payload:
                    patch["title"] = str(payload["title"])
                if "description" in payload:
                    patch["description"] = str(payload["description"])
                if iso_value:
                    patch["timing"] = _timing_for(item_type, iso_value)
                if not patch:
                    show(Screen("<b>✏️ ویرایش آیتم</b>\n\nهیچ تغییری وارد نشد.", keyboard([button("↩️ مدیریت امور کلاس", action="c3:owner")])))
                    return True
                response = app.site_api.request(
                    "classopsOwnerPreview", user_id, mode="update",
                    id=str(payload.get("itemId") or ""),
                    expectedRevision=int(payload.get("expectedRevision") or 0),
                    request={"item": patch},
                )
            else:
                if not iso_value:
                    show(Screen("<b>⚠️ زمان لازم است</b>\n\nبرای این نوع آیتم تاریخ و ساعت را وارد کن.", keyboard([button("↩️ مدیریت امور کلاس", action="c3:owner")])))
                    return True
                response = app.site_api.request(
                    "classopsOwnerPreview", user_id,
                    request=build_create_request(item_type, str(payload.get("title") or ""), str(payload.get("description") or ""), iso_value),
                )
            show(classops._preview_screen(app, response))
            return True
    except SiteApiError as error:
        app.state.clear_dialog(user_id)
        message_text = "نسخه یا داده تغییر کرده؛ صفحه را تازه کن و دوباره پیش‌نمایش بگیر." if error.status == 409 else "درخواست کامل نشد؛ داده‌ای ثبت نشد."
        show(Screen(f"<b>⚠️ امور کلاس</b>\n\n{message_text}", keyboard([button("↩️ مدیریت امور کلاس", action="c3:owner")])))
        return True

    app.state.clear_dialog(user_id)
    show(owner_home())
    return True


def owner_action_screen(app: DentBotApp, callback: dict[str, Any], data: str, owner_home: Callable[[], Screen]) -> tuple[bool, Screen | None]:
    if data == "c3:o:compose-cancel":
        sender = dict(callback.get("from") or {})
        user_id = int(sender.get("id") or 0)
        if user_id:
            app.state.clear_dialog(user_id)
        return True, owner_home()

    if data.startswith("c3:o:add:"):
        item_type = data.rsplit(":", 1)[-1]
        if item_type not in CREATE_TYPES:
            return True, owner_home()
        sender = dict(callback.get("from") or {})
        user_id = int(sender.get("id") or 0)
        origin = int(dict(callback.get("message") or {}).get("message_id") or 0)
        return True, start_dialog(app, user_id, item_type, origin)

    if data.startswith("c3:o:edit:cop_"):
        sender = dict(callback.get("from") or {})
        user_id = int(sender.get("id") or 0)
        item_id = data[len("c3:o:edit:"):]
        response = app.site_api.request("classopsGet", user_id, id=item_id)
        item = dict(response.get("item") or {})
        item_type = str(item.get("type") or "")
        if item_type not in CREATE_TYPES:
            return True, Screen(
                "<b>✏️ ویرایش</b>\n\nویرایش این نوع آیتم در رابط فعلی پشتیبانی نمی‌شود.",
                keyboard([button("↩️ مدیریت امور کلاس", action="c3:owner")]),
            )
        origin = int(dict(callback.get("message") or {}).get("message_id") or 0)
        return True, start_dialog(app, user_id, item_type, origin, edit_item=item)

    if data.startswith("c3:o:confirm-cancel:cxo_") or data.startswith("c3:o:confirm-archive:cxo_"):
        verb = "cancel" if ":confirm-cancel:" in data else "archive"
        token = data.rsplit(":", 1)[-1]
        return True, confirm_lifecycle_screen(token, verb)

    return False, None
