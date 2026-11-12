"""Owner-only Moodle CAPTCHA handoff. The website remains sole session owner."""
from __future__ import annotations

from .api import BotApiError
from .navid import decode_captcha_data_uri
from .persian_datetime import format_jalali_datetime


def hamiad_captcha_caption(expires_at: str) -> str:
    rendered = format_jalali_datetime(expires_at)
    expiry = f"\nاعتبار تصویر: <b>{rendered}</b>" if rendered else ""
    return (
        "<b>🎓 ورود به همیاد</b>\n\n"
        "<blockquote>🔐 مشخصات ورود از قبل به‌صورت امن در سایت ذخیره شده‌اند.</blockquote>\n\n"
        "کد پنج‌رقمی یا حروف و اعداد داخل تصویر را با "
        "<b>پاسخ به همین پیام</b> ارسال کن.\n"
        "پس از تأیید، اتصال همیاد بررسی و نتیجه اعلام می‌شود."
        f"{expiry}\n\n"
        "<blockquote>برای دریافت تصویر تازه، دستور /hamiad را بفرست.</blockquote>"
    )


def send_hamiad_login_challenge(*, api, state, site_api, owner_id: int) -> dict:
    result = site_api.hamiad_login_start(owner_id)
    status = str(result.get("status") or "")
    if status == "connected":
        state.clear_hamiad_challenge()
        return result
    if status != "challenge":
        raise BotApiError("تصویر ورود همیاد در دسترس نیست.")
    image = decode_captcha_data_uri(result.get("captchaDataUri"))
    challenge_ref = str(result.get("challengeRef") or "")
    expires_at = str(result.get("expiresAt") or "")
    if len(challenge_ref) != 32 or not expires_at:
        raise BotApiError("شناسه تصویر همیاد معتبر نیست.")
    sent = api.send_photo_bytes(
        owner_id, image,
        caption=hamiad_captcha_caption(expires_at),
        reply_markup={
            "force_reply": True,
            "selective": True,
            "input_field_placeholder": "کد کپچای همیاد",
        },
        filename="hamiad-captcha.png",
    )
    message_id = int(sent.get("message_id") or 0)
    if message_id <= 0:
        raise BotApiError("شناسه پیام تصویر همیاد دریافت نشد.")
    state.set_hamiad_challenge(challenge_ref, message_id, expires_at)
    return result
