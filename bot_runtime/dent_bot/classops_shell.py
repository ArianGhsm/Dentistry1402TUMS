from __future__ import annotations

import html
import re
from typing import Any

from .persian_datetime import to_persian_digits
from .ui import Screen, button, keyboard


CANONICAL_HOME_ROWS: tuple[tuple[tuple[str, str], ...], ...] = (
    (("🧭 مرکز همیاد", "navid-center"),),
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


def canonical_home_screen(*, is_owner: bool, cart_count: int = 0) -> Screen:
    rows = [[button(label, action=action) for label, action in row] for row in CANONICAL_HOME_ROWS]
    if cart_count > 0:
        rows.insert(2, [button(f"🛒 سبد خرید ({to_persian_digits(cart_count)})", action="cart", style="primary")])
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
            [button("🧭 مرکز همیاد", action="navid"), button("💳 پرداخت‌ها", action="admin-payments")],
            [button("📈 آمار فروش جزوات", action="booklet-sales", style="primary")],
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


def navid_center_screen(response: dict[str, Any], *, site_url: str = "https://dentistry1402tums.ir") -> Screen:
    return Screen(
        "<b>🧭 مرکز همیاد</b>\n\n"
        "📚 منابع آموزشی و درس‌های جدید از حساب همیاد دانشگاه خوانده می‌شوند.\n\n"
        "<blockquote>🔐 اتصال و مدیریت نشست فقط در سایت انجام می‌شود.</blockquote>",
        keyboard(
            [button("🔐 ورود و مشاهده درس‌ها", url=site_url.rstrip("/") + "/hamiad/", style="primary")],
            [button("↩️ بازگشت", action="home")],
        ),
    )
