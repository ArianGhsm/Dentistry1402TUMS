from __future__ import annotations

import html
import re
from typing import Any

from .ui import Screen, button, keyboard


CANONICAL_HOME_ROWS: tuple[tuple[tuple[str, str], ...], ...] = (
    (("🧭 مرکز نوید", "navid-center"),),
    (("📚 جزوات", "notes"), ("💳 اشتراک جزوات", "term-subscription:7")),
    (("📊 نمرات", "grades"), ("🗂 امور کلاس", "class-operations")),
    (("👤 حساب من", "account"), ("🔔 اعلان‌ها", "notifications"), ("❓ راهنما", "help")),
)

STATUS_MARKERS = {
    "ready": ("🟢", "سالم"),
    "healthy": ("🟢", "سالم"),
    "active": ("🟢", "فعال"),
    "delivered": ("🟢", "تحویل‌شده"),
    "degraded": ("🟡", "نیازمند توجه"),
    "warning": ("🟡", "نیازمند توجه"),
    "planned": ("🟡", "برنامه‌ریزی‌شده"),
    "scheduled": ("🟡", "زمان‌بندی‌شده"),
    "leased": ("🟡", "در حال ارسال"),
    "retry": ("🟡", "در انتظار تلاش مجدد"),
    "pending": ("🟡", "در انتظار"),
    "failed": ("🔴", "خطا"),
    "unavailable": ("🔴", "در دسترس نیست"),
    "error": ("🔴", "خطا"),
    "unknown": ("⚪️", "بررسی‌نشده"),
    "not_checked": ("⚪️", "بررسی‌نشده"),
    "superseded": ("⚪️", "جایگزین‌شده"),
    "cancelled": ("⚪️", "لغوشده"),
    "canceled": ("⚪️", "لغوشده"),
}


def status_marker(state: object) -> tuple[str, str]:
    return STATUS_MARKERS.get(str(state or "unknown").strip().lower(), STATUS_MARKERS["unknown"])


def canonical_home_screen(*, is_owner: bool) -> Screen:
    rows = [[button(label, action=action) for label, action in row] for row in CANONICAL_HOME_ROWS]
    if is_owner:
        rows.append([button("🛠 مدیریت ربات", action="admin", style="primary")])
    return Screen(
        "<b>دنت‌یار | ورودی ۱۴۰۲</b>\n\n"
        "سرویس موردنظرت را از منوی زیر انتخاب کن.\n"
        "اطلاعات شخصی فقط از حساب متصل و منبع رسمی نمایش داده می‌شود.",
        keyboard(*rows),
    )


def owner_management_screen() -> Screen:
    return Screen(
        "<b>🛠 مدیریت ربات</b>\n\n"
        "مدیریت سرویس‌های ربات، عملیات کلاس و مسیرهای داخلی ربات.",
        keyboard(
            [button("🖥 وضعیت سرویس‌ها", action="system-status"), button("🗂 مدیریت امور کلاس", action="c3:owner")],
            [button("🧭 مرکز نوید", action="navid"), button("💳 پرداخت‌ها", action="admin-payments")],
            [button("📊 مدیریت نمرات", action="admin-grades"), button("✏️ درخواست‌های مشخصات", action="profile-edit-requests")],
            [button("🔗 اتصال حساب‌ها", action="identity-mappings")],
            [button("↩️ بازگشت", action="home")],
        ),
    )


def service_status_screen(payload: dict[str, Any] | None, *, api_failed: bool = False) -> Screen:
    services = [item for item in dict(payload or {}).get("services", []) if isinstance(item, dict)]
    if api_failed:
        services = [
            {"label": "اتصال API سایت", "state": "unavailable"},
            {"label": "امور کلاس", "state": "unknown"},
            {"label": "اعلان‌ها", "state": "unknown"},
            {"label": "تلگرام", "state": "unknown"},
            {"label": "بله", "state": "unknown"},
        ]
    lines = ["<b>🖥 وضعیت سرویس‌ها</b>", ""]
    if not services:
        services = [{"label": "وضعیت سرویس‌ها", "state": "unknown"}]
    for item in services:
        marker, label = status_marker(item.get("state"))
        name = html.escape(str(item.get("label") or "سرویس"))
        lines.append(f"{marker} <b>{name}</b> · {label}")
    lines.extend(("", "وضعیت‌ها فقط بر اساس بررسی واقعی همین درخواست نمایش داده می‌شوند."))
    return Screen(
        "\n".join(lines),
        keyboard(
            [button("↻ تازه‌سازی", action="system-status", style="primary")],
            [button("↩️ مدیریت ربات", action="admin"), button("🏠 خانه", action="home")],
        ),
    )


def navid_center_screen(response: dict[str, Any]) -> Screen:
    view = dict(response.get("view") or {})
    connector = next(
        (item for item in view.get("connectors", []) if isinstance(item, dict) and item.get("connector") == "navid"),
        None,
    )
    lines = ["<b>🧭 مرکز نوید</b>", ""]
    rows: list[list[dict[str, Any]]] = []
    if connector is None:
        lines.append("وضعیت اتصال نوید در این لحظه قابل تشخیص نیست.")
    else:
        state = str(connector.get("status") or "unknown")
        mapped = {"ready": "ready", "unavailable": "unavailable", "not-configured": "unknown"}.get(state, "unknown")
        marker, label = status_marker(mapped)
        lines.append(f"{marker} وضعیت اتصال: <b>{html.escape(str(connector.get('statusLabel') or label))}</b>")
        masked = str(connector.get("maskedAccountLabel") or "").strip()
        if masked:
            lines.append(f"حساب: <code>{html.escape(masked)}</code>")
        for action in view.get("actions", []):
            if not isinstance(action, dict) or "نوید" not in str(action.get("label") or ""):
                continue
            ref = str(action.get("ref") or "")
            if re.fullmatch(r"[A-Za-z0-9_-]{12,20}", ref):
                rows.append([button("ادامه در نوید", action=f"assistant-action:{ref}", style="primary")])
    rows.append([button("↩️ بازگشت", action="home")])
    return Screen("\n".join(lines), keyboard(*rows))
