from __future__ import annotations

from datetime import datetime, timedelta, timezone
from zoneinfo import ZoneInfo

from .academic_term7_rich import academic_notification_text
from .classops_runtime import _destination_chat_id
from .final_exam_schedule import final_exam_schedule_screen


def dispatch_theory_channel_digest(*, settings, api, state, site_api, now=None) -> str:
    """Publish once per date, at 16:00 Tehran or same-day catch-up."""
    if settings.platform != "telegram" or not settings.owner_id:
        return "disabled"
    channel = _destination_chat_id("telegram", "information_channel")
    if channel is None or channel >= 0:
        return "unconfigured"
    local = (now or datetime.now(timezone.utc)).astimezone(ZoneInfo("Asia/Tehran"))
    if local.hour < 16:
        return "not-due"
    tomorrow = (local.date() + timedelta(days=1)).isoformat()
    receipt = "term7-theory-channel:" + tomorrow
    if state.has_notification_delivery(receipt):
        return "already-sent"
    payload = site_api.request("academicTerm7TheoryTomorrow", settings.owner_id)
    if payload.get("success") is not True or payload.get("date") != tomorrow:
        raise ValueError("Invalid theory schedule date")
    if payload.get("inSchedule") is not True:
        return "outside-term"
    rows = payload.get("rows")
    if not isinstance(rows, list) or any(not isinstance(row, dict) or row.get("kind") != "theory" for row in rows):
        raise ValueError("Invalid public theory rows")
    title = payload.get("title")
    if not isinstance(title, str) or not title.startswith("📅 برنامه کلاس‌های نظری فردا | "):
        raise ValueError("Invalid public theory title")
    message = academic_notification_text({
        "source": "academic-term7-theory-channel", "title": title,
        "meta": {"academicScheduleRows": rows},
    })
    if message is None:
        raise ValueError("Theory renderer unavailable")
    chat = api.call("getChat", {"chat_id": channel})
    if not isinstance(chat, dict) or chat.get("type") != "channel":
        raise ValueError("Configured destination is not a channel")
    result = api.send(channel, message, {"inline_keyboard": []})
    if not result.get("message_id"):
        raise ValueError("No channel message receipt")
    state.mark_notification_delivery(receipt)
    return "sent"

def dispatch_final_exam_channel(*, settings, api, state, site_api) -> str:
    """Announce each official final timetable revision once in the information channel."""
    if settings.platform != "telegram" or not settings.owner_id:
        return "disabled"
    channel = _destination_chat_id("telegram", "information_channel")
    if channel is None or channel >= 0:
        return "unconfigured"
    payload = site_api.request("academicTerm7FinalExams", settings.owner_id)
    if payload.get("success") is not True or payload.get("eligible") is not True:
        raise ValueError("Invalid exam timetable")
    sent = False
    for category, revision_key, rows_key, receipt_prefix in (
        ("final", "revision", "rows", "term7-final-exam-channel:"),
        ("midterm", "midtermRevision", "midtermRows", "term7-midterm-exam-channel:"),
    ):
        if category == "midterm" and revision_key not in payload:
            continue
        revision = str(payload.get(revision_key) or "")
        rows = payload.get(rows_key)
        if (len(revision) != 24 or any(c not in "0123456789abcdef" for c in revision)
                or not isinstance(rows, list) or not rows):
            raise ValueError("Invalid exam timetable revision")
        receipt = receipt_prefix + revision
        if state.has_notification_delivery(receipt):
            continue
        chat = api.call("getChat", {"chat_id": channel})
        if not isinstance(chat, dict) or chat.get("type") != "channel":
            raise ValueError("Configured destination is not a channel")
        screen = final_exam_schedule_screen(payload, category=category)
        result = api.send(channel, screen.text, {"inline_keyboard": []})
        if not result.get("message_id"):
            raise ValueError("No exam channel receipt")
        state.mark_notification_delivery(receipt)
        sent = True
    return "sent" if sent else "already-sent"
