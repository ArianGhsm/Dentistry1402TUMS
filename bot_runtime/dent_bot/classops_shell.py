from __future__ import annotations

import html
import re
from typing import Any

from .persian_datetime import to_persian_digits
from .ui import Screen, button, keyboard, native_rich_text


CANONICAL_HOME_ROWS: tuple[tuple[tuple[str, str], ...], ...] = (
    (("🧭 مرکز همیاد", "hamiad-center"),),
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
            [button("🧭 مرکز همیاد", action="hamiad-center"), button("💳 پرداخت‌ها", action="admin-payments")],
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


def _hamiad_title(raw: object) -> str:
    """Trim operational Moodle metadata without rewriting official course titles."""
    text = " ".join(str(raw or "").split())
    text = re.sub(r"^ارتدنسی نظری", "ارتودانتیکس نظری", text)
    text = re.sub(r"\s*-\s*ترم\s*[۰-۹0-9]+\s*-\s*کدگروه\s*[۰-۹0-9]+$", "", text)
    return html.escape(to_persian_digits(text)[:110])


def hamiad_overview_screen(data: dict[str, Any], *, site_url: str) -> Screen:
    connected = bool(data.get("connected"))
    courses = [x for x in data.get("courses", []) if isinstance(x, dict)]
    tasks = [x for x in data.get("assignments", []) if isinstance(x, dict)]
    resources = max(0, int(data.get("resources") or 0))
    if not connected:
        text = (
            "<b>🎓 همیاد | ترم ۷</b>\n\n"
            "<blockquote>🔐 نشست دانشگاه متصل نیست یا منقضی شده است. "
            "از سایت دوباره وارد شو تا درس‌ها و تکالیف بررسی شوند.</blockquote>"
        )
    else:
        text = (
            "<b>🎓 همیاد | ترم ۷</b>\n\n"
            f"<blockquote>📚 درس‌ها: <b>{to_persian_digits(len(courses))}</b>\n"
            f"📎 منابع: <b>{to_persian_digits(resources)}</b>\n"
            f"⏰ تکالیف: <b>{to_persian_digits(len(tasks))}</b></blockquote>\n\n"
            "اطلاعات مستقیماً از نشست متصل همیاد خوانده شده‌اند."
        )
    return Screen(
        text,
        keyboard(
            [button("📚 درس‌های ترم ۷", action="hamiad:courses:0", style="primary"),
             button("⏰ تکالیف و مهلت‌ها", action="hamiad:tasks:0")],
            [button("↻ تازه‌سازی", action="hamiad-center"),
             button("🌐 ورود به همیاد", url=site_url.rstrip("/") + "/hamiad/")],
            [button("↩️ امور کلاس", action="class-operations"),
             button("🏠 خانه", action="home")],
        ),
    )


def hamiad_courses_screen(data: dict[str, Any], *, page: int = 0) -> Screen:
    courses = [x for x in data.get("courses", []) if isinstance(x, dict)]
    page_size = 5
    page = max(0, min(page, max(0, (len(courses) - 1) // page_size)))
    rows = courses[page * page_size:(page + 1) * page_size]
    parts = [
        "<b>📚 درس‌های همیاد | ترم ۷</b>",
        f"<blockquote>{to_persian_digits(len(courses))} درس در سامانهٔ متصل</blockquote>",
    ]
    buttons = []
    for number, course in enumerate(rows, page * page_size + 1):
        title = _hamiad_title(course.get("title"))
        count = max(0, int(course.get("resourceCount") or 0))
        tasks = max(0, int(course.get("assignmentCount") or 0))
        parts.extend(("", f"<b>{to_persian_digits(number)}. {title}</b>",
                      f"<blockquote>📎 {to_persian_digits(count)} منبع · ⏰ {to_persian_digits(tasks)} تکلیف</blockquote>"))
        buttons.append([button(f"📚 درس {to_persian_digits(number)} | مشاهده", action=f"hamiad:course:{int(course.get('id') or 0)}")])
    if not courses:
        parts.append("\nهنوز درسی از ترم جاری در همیاد شناسایی نشده است.")
    navigation = []
    if page > 0:
        navigation.append(button("◀ صفحهٔ قبل", action=f"hamiad:courses:{page - 1}"))
    if (page + 1) * page_size < len(courses):
        navigation.append(button("صفحهٔ بعد ▶", action=f"hamiad:courses:{page + 1}"))
    if navigation:
        buttons.append(navigation)
    buttons.append([button("⏰ تکالیف", action="hamiad:tasks:0"),
                    button("↩️ مرکز همیاد", action="hamiad-center")])
    table = [
        "<h2>📚 درس‌های همیاد | ترم ۷</h2>",
        "<table bordered striped compact><tr><th>درس</th><th>منابع</th><th>تکالیف</th></tr>",
    ]
    for course in rows:
        table.append(
            "<tr><td>" + _hamiad_title(course.get("title")) + "</td>"
            "<td>" + to_persian_digits(max(0, int(course.get("resourceCount") or 0))) + "</td>"
            "<td>" + to_persian_digits(max(0, int(course.get("assignmentCount") or 0))) + "</td></tr>"
        )
    table.append("</table>")
    if not rows:
        table.append("<p>درسی برای ترم جاری شناسایی نشده است.</p>")
    return Screen(native_rich_text("\n".join(parts), "".join(table)), keyboard(*buttons))


def hamiad_course_screen(data: dict[str, Any]) -> Screen:
    course = dict(data.get("course") or {})
    items = [x for x in data.get("items", []) if isinstance(x, dict)]
    resources = [x for x in items if str(x.get("kind")) != "assign"]
    tasks = [x for x in items if str(x.get("kind")) == "assign"]
    parts = [
        f"<b>📚 {_hamiad_title(course.get('title'))}</b>",
        f"<blockquote>📎 {to_persian_digits(len(resources))} منبع · ⏰ {to_persian_digits(len(tasks))} تکلیف</blockquote>",
    ]
    if resources:
        for item in resources[:18]:
            name = html.escape(to_persian_digits(str(item.get("title") or "بدون عنوان"))[:125])
            marker = "📂" if item.get("kind") == "folder" else "📎"
            parts.extend(("", f"{marker} {name}"))
        if len(resources) > 18:
            parts.append(f"\nو {to_persian_digits(len(resources) - 18)} منبع دیگر در سایت.")
    else:
        parts.extend(("", "برای این درس هنوز فایل یا صفحهٔ آموزشی ثبت نشده است."))
    buttons = []
    if tasks:
        parts.extend(("", "<b>⏰ تکالیف</b>"))
        for item in tasks[:8]:
            title = html.escape(to_persian_digits(str(item.get("title") or ""))[:90])
            parts.append(f"• {title}")
            buttons.append([button("⏰ " + to_persian_digits(str(item.get("title") or ""))[:34],
                                   action=f"hamiad:assignment:{int(course.get('id') or 0)}:{int(item.get('id') or 0)}")])
    buttons.extend([
        [button("📚 فهرست درس‌ها", action="hamiad:courses:0"),
         button("↻ تازه‌سازی", action=f"hamiad:course:{int(course.get('id') or 0)}")],
        [button("↩️ مرکز همیاد", action="hamiad-center")],
    ])
    return Screen("\n".join(parts), keyboard(*buttons))


def hamiad_tasks_screen(data: dict[str, Any], *, page: int = 0) -> Screen:
    tasks = [x for x in data.get("assignments", []) if isinstance(x, dict)]
    page_size = 6
    page = max(0, min(page, max(0, (len(tasks) - 1) // page_size)))
    subset = tasks[page * page_size:(page + 1) * page_size]
    parts = [
        "<b>⏰ تکالیف همیاد | ترم ۷</b>",
        f"<blockquote>تعداد تکالیف شناسایی‌شده: <b>{to_persian_digits(len(tasks))}</b></blockquote>"
    ]
    buttons = []
    if not tasks:
        parts.extend(("", "در حال حاضر هیچ تکلیفی در دروس ترم ۷ حساب متصل ثبت نشده است.",
                      "", "↻ برای بررسی تغییرات، تازه‌سازی را انتخاب کن."))
    for task in subset:
        title = html.escape(to_persian_digits(str(task.get("title") or "تکلیف"))[:90])
        course = _hamiad_title(task.get("courseTitle"))
        due = html.escape(to_persian_digits(str(task.get("dueText") or "زمان نامشخص"))[:110])
        submission = html.escape(to_persian_digits(str(task.get("submissionStatus") or ""))[:100])
        meta = f"📚 {course}\n⏳ {due}"
        if submission:
            meta += f"\n✅ وضعیت ارسال: {submission}"
        parts.extend(("", f"📌 <b>{title}</b>", f"<blockquote>{meta}</blockquote>"))
        buttons.append([button("جزئیات تکلیف", action=f"hamiad:assignment:{int(task.get('courseId') or 0)}:{int(task.get('id') or 0)}")])
    nav = []
    if page > 0:
        nav.append(button("◀ قبل", action=f"hamiad:tasks:{page - 1}"))
    if (page + 1) * page_size < len(tasks):
        nav.append(button("بعد ▶", action=f"hamiad:tasks:{page + 1}"))
    if nav:
        buttons.append(nav)
    buttons.extend([[button("↻ تازه‌سازی", action=f"hamiad:tasks:{page}"),
                     button("📚 درس‌ها", action="hamiad:courses:0")],
                    [button("↩️ مرکز همیاد", action="hamiad-center")]])
    return Screen("\n".join(parts), keyboard(*buttons))


def hamiad_assignment_screen(data: dict[str, Any]) -> Screen:
    title = html.escape(to_persian_digits(str(data.get("title") or "تکلیف"))[:120])
    due = html.escape(to_persian_digits(str(data.get("dueText") or "در سامانه درج نشده"))[:160])
    desc = html.escape(to_persian_digits(str(data.get("description") or ""))[:950])
    submission = html.escape(to_persian_digits(str(data.get("submissionStatus") or "وضعیت نامشخص"))[:140])
    grading = html.escape(to_persian_digits(str(data.get("gradingStatus") or ""))[:140])
    text = (f"<b>⏰ {title}</b>\n\n"
            f"<blockquote>⏳ زمان باقی‌مانده: {due}\n"
            f"📤 وضعیت ارسال: {submission}"
            + (f"\n📋 وضعیت تصحیح: {grading}" if grading else "")
            + "</blockquote>"
            + (f"\n\n📝 {desc}" if desc else "\n\nتوضیحی در صفحهٔ تکلیف ثبت نشده است."))
    return Screen(text, keyboard([button("⏰ فهرست تکالیف", action="hamiad:tasks:0")],
                                 [button("↩️ مرکز همیاد", action="hamiad-center")]))
