from __future__ import annotations

from .ui import Screen, button, keyboard


def button_mode_screen(mode: str) -> Screen:
    current = "⌨️ کیبورد پایین صفحه" if mode == "reply" else "🟦 دکمه‌های شیشه‌ای"
    return Screen(
        "<b>⚙️ تنظیمات نمایش دکمه‌ها</b>\n\n"
        f"حالت فعلی: <b>{current}</b>\n\n"
        "🟦 <b>شیشه‌ای</b>: دکمه‌ها زیر هر پیام قرار می‌گیرند.\n"
        "⌨️ <b>کیبوردی</b>: دکمه‌های هر بخش پایین صفحه دیده می‌شوند.\n\n"
        "<blockquote>این انتخاب برای همین حساب در ربات ذخیره می‌شود؛ "
        "هر زمان از تنظیمات قابل تغییر است. مراحل احراز هویت تغییری نمی‌کنند.</blockquote>",
        keyboard(
            [button(("✅ " if mode == "inline" else "") + "🟦 دکمه‌های شیشه‌ای", action="ui-mode:inline")],
            [button(("✅ " if mode == "reply" else "") + "⌨️ کیبورد پایین صفحه", action="ui-mode:reply")],
            [button("🏠 خانه", action="home")],
        ),
    )


def button_mode_announcement() -> Screen:
    return Screen(
        "<b>⚙️ انتخاب حالت دکمه‌های ربات</b>\n\n"
        "از این پس می‌توانید بعد از احراز هویت، ظاهر دکمه‌های ربات را انتخاب کنید.\n\n"
        "🟦 <b>شیشه‌ای:</b> دکمه‌ها زیر پیام‌ها\n"
        "⌨️ <b>کیبوردی:</b> دکمه‌ها پایین صفحه\n\n"
        "حالت پیش‌فرض شیشه‌ای است. هر زمان از بخش «⚙️ تنظیمات» "
        "می‌توانید آن را تغییر دهید.",
        keyboard(
            [button("🟦 انتخاب حالت شیشه‌ای", action="ui-mode:inline")],
            [button("⌨️ انتخاب کیبورد پایین صفحه", action="ui-mode:reply")],
            [button("⚙️ تنظیمات", action="ui-settings")],
        ),
    )
