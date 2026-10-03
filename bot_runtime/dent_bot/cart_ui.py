from __future__ import annotations

import html

from .cart import ai_bulk_discount_progress
from .persian_datetime import to_persian_digits
from .ui import Screen, button, format_rials, keyboard, native_rich_text


def _money(value: object) -> str:
    try:
        amount = max(0, int(value or 0))
    except (TypeError, ValueError):
        amount = 0
    return format_rials(amount)


def _compact_title(value: object, limit: int = 72) -> str:
    text = to_persian_digits(" ".join(str(value or "محصول").split()))
    if len(text) <= limit:
        return text
    return text[: max(1, limit - 1)].rstrip() + "…"


def cart_screen(payload: dict, *, notice: str = "") -> Screen:
    items = [dict(item) for item in payload.get("items", []) if isinstance(item, dict)]
    version = max(0, int(payload.get("version") or 0))
    subtotal = max(0, int(payload.get("subtotalRials") or 0))
    discount_amount = max(0, int(payload.get("discountAmountRials") or 0))
    automatic_discount = max(0, int(payload.get("automaticDiscountAmountRials") or 0))
    automatic_percent = max(0, int(payload.get("automaticDiscountPercent") or 0))
    coupon_discount = max(0, int(payload.get("couponDiscountAmountRials") or 0))
    ai_count = max(0, int(payload.get("aiBookletCount") or 0))
    total = max(0, int(payload.get("amountRials") or max(0, subtotal - discount_amount)))
    discount_code = str(payload.get("discountCode") or "").strip()
    can_checkout = bool(items) and all(bool(item.get("available")) for item in items)

    lines = ["<b><u>🛒 سبد خرید</u></b>"]
    if notice:
        lines.extend(("", f"<blockquote>{html.escape(notice)}</blockquote>"))
    if not items:
        lines.extend(("", "سبد خریدت خالی است.", "", "<blockquote>محصول‌ها را از همان صفحهٔ خرید به سبد اضافه کن.</blockquote>"))
        return Screen(
            "\n".join(lines),
            keyboard(
                [button("🛍 مشاهده محصولات", action="payments")],
                [button("🏠 منوی اصلی", action="home")],
            ),
        )

    lines.extend(("", f"{to_persian_digits(len(items))} محصول در سبد"))
    if ai_count:
        progress = ai_bulk_discount_progress(ai_count)
        if automatic_percent >= 30:
            bulk_notice = (
                f"🤖 تخفیف خودکار <b>{to_persian_digits(automatic_percent)}٪</b> "
                f"برای {to_persian_digits(ai_count)} جزوه هوش مصنوعی فعال است."
            )
        elif automatic_percent:
            bulk_notice = (
                f"🤖 تخفیف خودکار <b>{to_persian_digits(automatic_percent)}٪</b> فعال است. "
                f"با افزودن {to_persian_digits(progress['remaining'])} جزوه دیگر، "
                f"تخفیف به <b>{to_persian_digits(progress['nextPercent'])}٪</b> می‌رسد."
            )
        else:
            bulk_notice = (
                f"🤖 با خرید هم‌زمان <b>۱۱</b> جزوه هوش مصنوعی، <b>۲۰٪</b> و "
                f"از <b>۱۶</b> جزوه به بالا، <b>۳۰٪</b> تخفیف خودکار اعمال می‌شود. "
                f"تا تخفیف اول: {to_persian_digits(progress['remaining'])} جزوه دیگر."
            )
        lines.extend(("", f"<blockquote>{bulk_notice}</blockquote>"))
    remove_buttons: list[dict] = []
    for index, item in enumerate(items, start=1):
        title = html.escape(_compact_title(item.get("title")))
        available = bool(item.get("available"))
        status = to_persian_digits("آماده برای پرداخت" if available else str(item.get("reason") or "نیازمند بازبینی"))
        marker = "🟢" if available else "🔴"
        price = _money(item.get("amountRials"))
        lines.extend(
            (
                "",
                f"<b>{to_persian_digits(index)}. {title}</b>",
                f"<blockquote>💳 {price}\n{marker} {html.escape(status)}</blockquote>",
            )
        )
        remove_buttons.append(
            button(
                f"✕ حذف {to_persian_digits(index)}",
                action=f"cart-remove:{index - 1}:{version}",
            )
        )

    lines.extend(("", "────────────"))
    lines.append(f"جمع محصولات: <code>{html.escape(_money(subtotal))}</code>")
    if automatic_discount:
        lines.append(
            f"تخفیف خودکار جزوات هوش مصنوعی ({to_persian_digits(automatic_percent)}٪): "
            f"<code>{html.escape(_money(automatic_discount))}</code>"
        )
    if discount_code:
        lines.append(
            f"کد تخفیف <code>{html.escape(discount_code)}</code>: "
            f"<code>{html.escape(_money(coupon_discount))}</code>"
        )
    lines.append(f"<b>مبلغ نهایی: <code>{html.escape(_money(total))}</code></b>")
    if not can_checkout:
        lines.extend(("", "<blockquote>برای پرداخت، محصول‌های نامعتبر را از سبد حذف کن.</blockquote>"))

    rich = [
        "<h2>🛒 سبد خرید</h2>",
        f"<p>تعداد محصول‌ها: <b>{to_persian_digits(len(items))}</b></p>",
        "<table bordered striped compact><caption>محصول‌های سبد</caption>",
        "<tr><th>محصول</th><th>مبلغ</th><th>وضعیت</th></tr>",
    ]
    for item in items:
        title = html.escape(_compact_title(item.get("title"), 96))
        price = html.escape(_money(item.get("amountRials")))
        status = "آماده" if item.get("available") else html.escape(to_persian_digits(str(item.get("reason") or "نیازمند بازبینی")))
        rich.append(f"<tr><td>{title}</td><td>{price}</td><td>{status}</td></tr>")
    rich.append("</table>")
    rich_summary = ["<p>", f"جمع: <b>{html.escape(_money(subtotal))}</b>"]
    if automatic_discount:
        rich_summary.append(
            f"<br/>تخفیف خودکار جزوات هوش مصنوعی "
            f"({to_persian_digits(automatic_percent)}٪): "
            f"<b>{html.escape(_money(automatic_discount))}</b>"
        )
    if discount_code:
        rich_summary.append(
            f"<br/>کد تخفیف <code>{html.escape(discount_code)}</code>: "
            f"<b>{html.escape(_money(coupon_discount))}</b>"
        )
    rich_summary.append(f"<br/>مبلغ نهایی: <b>{html.escape(_money(total))}</b></p>")
    rich.append("".join(rich_summary))
    if not can_checkout:
        rich.append("<footer>یکی از محصولات نیازمند بازبینی است و تا حذف آن پرداخت انجام نمی‌شود.</footer>")

    action_rows: list[list[dict]] = []
    if can_checkout:
        action_rows.append([button("💳 پرداخت یک‌جای سبد", action="cart-checkout", style="success")])
    action_rows.append(
        [
            button(
                "🏷 تغییر کد تخفیف" if discount_code else "🏷 ثبت کد تخفیف",
                action="cart-discount",
            ),
            button("🗑 خالی‌کردن", action=f"cart-clear:{version}"),
        ]
    )
    action_rows.extend([remove_buttons[index:index + 2] for index in range(0, len(remove_buttons), 2)])
    action_rows.append([button("🛍 ادامه خرید", action="payments"), button("🏠 خانه", action="home")])
    return Screen(
        native_rich_text("\n".join(lines), "".join(rich)),
        keyboard(*action_rows),
    )


def cart_discount_prompt_screen(*, current_code: str = "") -> Screen:
    current = str(current_code or "").strip()
    lines = [
        "<b><u>🏷 کد تخفیف سبد</u></b>",
        "",
        "کد تخفیف را در یک پیام ارسال کن.",
    ]
    if current:
        lines.extend(("", f"کد فعلی: <code>{html.escape(current)}</code>"))
    lines.extend(("", "<blockquote>مبلغ نهایی بعد از اعتبارسنجی دوبارهٔ کد محاسبه می‌شود.</blockquote>"))
    rows: list[list[dict]] = []
    if current:
        rows.append([button("حذف کد فعلی", action="cart-discount-remove")])
    rows.append([button("↩️ سبد خرید", action="cart")])
    return Screen("\n".join(lines), keyboard(*rows))


def cart_added_screen(payload: dict, *, title: str) -> Screen:
    return cart_screen(payload, notice=f"«{to_persian_digits(title)}» به سبد خرید اضافه شد.")


def discount_codes_screen(items: list[dict]) -> Screen:
    codes = [dict(item) for item in items if isinstance(item, dict)]
    lines = ["<b><u>🏷 کدهای تخفیف</u></b>"]
    rows: list[list[dict]] = []
    if not codes:
        lines.extend(("", "هنوز کد تخفیفی ساخته نشده است."))
    for item in codes[:30]:
        code = html.escape(str(item.get("code") or ""))
        kind = str(item.get("kind") or "")
        amount = int(item.get("amount") or 0)
        value = (
            f"{to_persian_digits(amount)}٪"
            if kind == "percent"
            else _money(amount)
        )
        state = "🟢 فعال" if item.get("active") else "⚪️ غیرفعال"
        lines.extend(
            (
                "",
                f"<b><code>{code}</code></b> · {state}",
                f"<blockquote>تخفیف: {value}\n"
                f"حداقل خرید: {_money(item.get('minSubtotalRials'))}\n"
                f"سقف استفاده: {to_persian_digits(item.get('maxUses') or 0) if item.get('maxUses') else 'نامحدود'}</blockquote>",
            )
        )
        rows.append(
            [
                button(
                    "غیرفعال‌کردن" if item.get("configuredActive") else "فعال‌کردن",
                    action=f"discount-toggle:{str(item.get('code') or '')}",
                )
            ]
        )
    rows.append([button("＋ ساخت کد جدید", action="discount-new", style="primary")])
    rows.append([button("↩️ پرداخت‌ها", action="admin-payments"), button("🏠 خانه", action="home")])
    return Screen("\n".join(lines), keyboard(*rows))


def discount_new_kind_screen() -> Screen:
    return Screen(
        "<b><u>＋ ساخت کد تخفیف</u></b>\n\nنوع تخفیف را انتخاب کن.",
        keyboard(
            [
                button("درصدی", action="discount-new-kind:percent", style="primary"),
                button("مبلغ ثابت", action="discount-new-kind:fixed"),
            ],
            [button("↩️ کدهای تخفیف", action="discount-codes")],
        ),
    )


def discount_new_prompt_screen(step: str, payload: dict) -> Screen:
    prompts = {
        "amount": "مقدار تخفیف را بفرست؛ برای درصدی عدد ۱ تا ۹۰، برای مبلغ ثابت مبلغ را به تومان وارد کن.",
        "minimum": "حداقل مبلغ سبد برای استفاده از کد را به تومان بفرست. برای بدون حداقل، ۰ بفرست.",
        "uses": "حداکثر تعداد استفاده را بفرست. برای نامحدود، ۰ بفرست.",
        "expiry": "تاریخ انقضا را به شکل ۱۴۰۵/۰۸/۳۰ بفرست. برای بدون انقضا، «-» بفرست.",
    }
    return Screen(
        "<b><u>＋ ساخت کد تخفیف</u></b>\n\n"
        + html.escape(prompts.get(step, "مقدار را وارد کن.")),
        keyboard([button("لغو", action="discount-codes")]),
    )


def discount_created_screen(item: dict) -> Screen:
    code = html.escape(str(item.get("code") or ""))
    return Screen(
        "<b>✅ کد تخفیف ساخته شد</b>\n\n"
        f"<code>{code}</code>\n\n"
        "<blockquote>کد از همین لحظه در checkout سبد قابل اعتبارسنجی است.</blockquote>",
        keyboard(
            [button("🏷 همه کدها", action="discount-codes", style="primary")],
            [button("🏠 خانه", action="home")],
        ),
    )


def cart_delivery_screen(payload: dict) -> Screen:
    queued = max(0, int(payload.get("queued") or 0))
    duplicate = max(0, int(payload.get("duplicate") or 0))
    deferred = max(0, int(payload.get("deferred") or 0))
    failed = max(0, int(payload.get("failed") or 0))
    complete = max(0, int(payload.get("complete") or 0))
    actions = [dict(item) for item in payload.get("actions", []) if isinstance(item, dict)]
    order_token = str(payload.get("orderToken") or "")
    lines = [
        "<b><u>📥 دریافت فایل‌های خرید</u></b>",
        "",
        f"🟢 وارد صف ارسال: <b>{to_persian_digits(queued)}</b>",
    ]
    if duplicate:
        lines.append(f"🟡 از قبل در صف: <b>{to_persian_digits(duplicate)}</b>")
    if complete:
        lines.append(f"✅ بدون فایل جداگانه: <b>{to_persian_digits(complete)}</b>")
    if deferred:
        lines.append(f"⏳ در انتظار ظرفیت صف: <b>{to_persian_digits(deferred)}</b>")
    if failed:
        lines.append(f"🔴 نیازمند بررسی: <b>{to_persian_digits(failed)}</b>")
    lines.extend(
        (
            "",
            "<blockquote>فایل‌ها به ترتیب سبد وارد همان صف محافظت‌شدهٔ ربات می‌شوند. "
            "اگر موردی همین حالا در صف باشد دوباره صف‌بندی نمی‌شود.</blockquote>",
        )
    )
    rows: list[list[dict]] = []
    for item in actions[:10]:
        label = " ".join(str(item.get("label") or "دریافت محصول").split())[:48]
        action = str(item.get("action") or "")
        url = str(item.get("url") or "")
        if action:
            rows.append([button(f"🎁 {label}", action=action, style="success")])
        elif url:
            rows.append([button(f"🎁 {label}", url=url, style="success")])
    if deferred or failed:
        rows.append(
            [
                button(
                    "↻ تلاش دوباره برای موارد باقی‌مانده",
                    action=f"cart-deliver:{order_token}",
                    style="primary",
                )
            ]
        )
    rows.extend(
        (
            [button("🛒 سبد خرید", action="cart")],
            [button("🏠 منوی اصلی", action="home")],
        )
    )
    return Screen("\n".join(lines), keyboard(*rows))
