from __future__ import annotations

from dataclasses import dataclass
from typing import Any, Iterable, Mapping

from .model import ACTION_SPECS, Capability, OWNER, STUDENT

COPY = {
    "owner_title": "مرکز عملیات کلاس",
    "student_title": "کارهای کلاس من",
    "pending": "این قابلیت هنوز به backend متصل نشده است.",
    "stale": "نسخه آیتم تغییر کرده؛ ابتدا داده تازه را بگیر و دوباره بررسی کن.",
    "confirm": "این اقدام نیاز به تأیید نهایی دارد.",
    "draft_local": "درخواست پیش‌نویس فقط برای پیش‌نمایش ساخته شد؛ چیزی ارسال یا ذخیره نشده است.",
    "delivery_off": "فعال‌سازی وضعیت به معنی ارسال پیام نیست.",
    "empty": "موردی برای نمایش وجود ندارد.",
}

ACTION_LABELS = {
    "items.list": "فهرست عملیات",
    "item.get": "مشاهده جزئیات",
    "draft.create": "ساخت پیش‌نویس",
    "item.preview_diff": "پیش‌نمایش تغییرات",
    "item.edit": "ویرایش",
    "item.schedule_intent": "زمان‌بندی وضعیت",
    "item.activate_intent": "فعال‌سازی وضعیت",
    "item.cancel": "لغو",
    "item.archive": "آرشیو",
    "audience.preview": "پیش‌نمایش مخاطب",
    "destination.preview": "پیش‌نمایش مقصد",
    "task.requirement_view": "تکلیف و الزام‌ها",
    "exam.view": "آزمون‌ها",
    "exam.ack": "تأیید اطلاعیه حیاتی",
    "reminder.preview": "پیش‌نمایش یادآوری",
    "summary.tomorrow": "فردا",
    "summary.weekly": "هفته پیش‌رو",
    "ai.draft_request": "درخواست پیش‌نویس با متن آزاد",
}


@dataclass(frozen=True)
class SurfaceButton:
    label: str
    action: str
    enabled: bool
    confirmation_required: bool
    disabled_reason: str = ""
    style: str = "default"

    def semantic_tuple(self) -> tuple[str, str, bool, bool, str]:
        return (self.label, self.action, self.enabled, self.confirmation_required, self.disabled_reason)


@dataclass(frozen=True)
class SurfaceView:
    kind: str
    role: str
    title: str
    text: str
    buttons: tuple[SurfaceButton, ...]
    state: str = "ready"


def _capability(capabilities: Mapping[str, Capability | bool], name: str) -> Capability:
    value = capabilities.get(name, False)
    if isinstance(value, Capability):
        return value
    return Capability(bool(value), "" if value else "backend-integration-pending")


def action_button(action: str, role: str, capabilities: Mapping[str, Capability | bool]) -> SurfaceButton | None:
    spec = ACTION_SPECS[action]
    if role not in spec["roles"]:
        return None
    capability = _capability(capabilities, str(spec["capability"]))
    return SurfaceButton(
        label=ACTION_LABELS[action],
        action=action,
        enabled=capability.enabled,
        confirmation_required=bool(spec["confirmation"]),
        disabled_reason="" if capability.enabled else (capability.reason or COPY["pending"]),
        style="danger" if action in {"item.cancel", "item.archive"} else "primary" if action in {"draft.create", "exam.ack"} else "default",
    )


def build_menu(role: str, capabilities: Mapping[str, Capability | bool]) -> SurfaceView:
    if role not in {OWNER, STUDENT}:
        raise ValueError("unsupported surface role")
    owner_actions = (
        "items.list", "draft.create", "ai.draft_request", "audience.preview",
        "destination.preview", "reminder.preview", "summary.tomorrow", "summary.weekly",
    )
    student_actions = (
        "task.requirement_view", "exam.view", "reminder.preview", "summary.tomorrow", "summary.weekly",
    )
    actions = owner_actions if role == OWNER else student_actions
    buttons = tuple(button for action in actions if (button := action_button(action, role, capabilities)) is not None)
    title = COPY["owner_title"] if role == OWNER else COPY["student_title"]
    return SurfaceView("menu", role, title, "اقدام‌ها بر اساس دسترسی و capability فعلی نمایش داده می‌شوند.", buttons)


def build_item_actions(role: str, status: str, capabilities: Mapping[str, Capability | bool]) -> SurfaceView:
    if role != OWNER:
        return SurfaceView("item-actions", role, "جزئیات", COPY["empty"], tuple())
    actions: list[str] = ["item.get", "item.preview_diff", "item.edit"]
    if status == "draft":
        actions.append("item.schedule_intent")
    if status == "scheduled":
        actions.extend(("item.activate_intent", "item.cancel"))
    elif status in {"draft", "active"}:
        actions.append("item.cancel")
    if status != "archived":
        actions.append("item.archive")
    buttons = tuple(button for action in actions if (button := action_button(action, role, capabilities)) is not None)
    return SurfaceView("item-actions", role, "اقدام‌های آیتم", COPY["delivery_off"], buttons)


def render_stale_revision() -> SurfaceView:
    return SurfaceView("conflict", OWNER, "تداخل نسخه", COPY["stale"], tuple(), state="conflict")


def render_ai_draft_request(text: str) -> SurfaceView:
    compact = " ".join(str(text).split())[:900]
    body = COPY["draft_local"]
    if compact:
        body += f"\n\nمتن درخواست: {compact}"
    return SurfaceView("ai-draft-request", OWNER, ACTION_LABELS["ai.draft_request"], body, tuple(), state="preview")


def render_summary(kind: str, role: str, rows: Iterable[Mapping[str, Any]], capabilities: Mapping[str, Capability | bool]) -> SurfaceView:
    action = "summary.tomorrow" if kind == "tomorrow" else "summary.weekly"
    button = action_button(action, role, capabilities)
    if button is None:
        raise ValueError("summary not authorized for role")
    title = ACTION_LABELS[action]
    if not button.enabled:
        return SurfaceView(kind, role, title, COPY["pending"], (button,), state="disabled")
    normalized = []
    for row in rows:
        label = " ".join(str(row.get("title") or "").split())[:120]
        when = " ".join(str(row.get("when") or "").split())[:80]
        if label:
            normalized.append(f"• {label}" + (f" — {when}" if when else ""))
    return SurfaceView(kind, role, title, "\n".join(normalized) if normalized else COPY["empty"], (button,), state="ready")


def render_critical_ack(role: str, title: str, expected_revision: int, capabilities: Mapping[str, Capability | bool]) -> SurfaceView:
    button = action_button("exam.ack", role, capabilities)
    if button is None:
        raise ValueError("critical ACK is student-only")
    suffix = f"\nنسخه: {expected_revision}" if expected_revision > 0 else ""
    return SurfaceView(
        "critical-ack",
        role,
        "اطلاعیه حیاتی",
        " ".join(str(title).split())[:300] + suffix,
        (button,),
        state="ready" if button.enabled else "disabled",
    )
