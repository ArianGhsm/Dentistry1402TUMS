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


def is_hamiad_captcha_reply(app, message: dict) -> bool:
    pending = app.state.hamiad_challenge()
    if not pending:
        return False
    reply = dict(message.get("reply_to_message") or {})
    return int(reply.get("message_id") or 0) == int(pending["message_id"])


def show_hamiad_overview(app, chat_id: int, owner_id: int) -> None:
    import html

    from .classops_shell import hamiad_overview_screen
    from .site_api import SiteApiError
    from .ui import Screen, button, keyboard

    try:
        result = app.site_api.request("hamiadDashboardV1", owner_id)
        screen = hamiad_overview_screen(
            dict(result.get("dashboard") or {}), site_url=app.site_url
        )
    except SiteApiError as error:
        screen = Screen(
            "<b>🎓 مرکز همیاد</b>\n\n<blockquote>"
            + html.escape(str(error)) + "</blockquote>",
            keyboard(
                [button("🌐 ورود به همیاد", url=app.site_url.rstrip("/") + "/hamiad/")],
                [button("🏠 خانه", action="home")],
            ),
        )
    app.api.send(chat_id, screen.text, screen.keyboard)


def start_hamiad_login(app, chat_id: int) -> None:
    from .classops_shell import hamiad_overview_screen
    from .message_frames import frame_error
    from .site_api import SiteApiError
    from .ui import button, keyboard

    if app.site_api is None:
        app.api.send(chat_id, frame_error("ارتباط امن با همیاد برقرار نیست."))
        return
    try:
        result = send_hamiad_login_challenge(
            api=app.api, state=app.state, site_api=app.site_api,
            owner_id=app.owner_id,
        )
        if result.get("status") == "connected":
            dashboard = app.site_api.request("hamiadDashboardV1", app.owner_id)
            screen = hamiad_overview_screen(
                dict(dashboard.get("dashboard") or {}), site_url=app.site_url,
            )
            app.api.send(chat_id, screen.text, screen.keyboard)
    except (SiteApiError, BotApiError) as error:
        app.api.send(
            chat_id,
            frame_error(str(error)),
            keyboard(
                [button("🔐 تصویر جدید همیاد", action="hamiad:login")],
                [button("🌐 پنل همیاد", url=app.site_url.rstrip("/") + "/hamiad/")],
            ),
        )


def complete_hamiad_captcha(app, chat_id: int, text: str, message: dict) -> None:
    from .message_frames import frame_error
    from .site_api import SiteApiError
    from .student_assistant import challenge_expired, clean_captcha_answer
    from .ui import button, keyboard

    pending = app.state.hamiad_challenge()
    if not pending:
        return
    reply = dict(message.get("reply_to_message") or {})
    reply_id = int(reply.get("message_id") or 0)
    if reply_id != int(pending["message_id"]):
        return
    code = clean_captcha_answer(text)
    if not code or len(code) > 10:
        app.api.send(
            chat_id,
            frame_error("کد تصویر باید بین ۴ تا ۱۰ حرف یا عدد باشد؛ با پاسخ به همان عکس ارسال کن."),
        )
        return
    if challenge_expired(str(pending["expires_at"])):
        app.state.clear_hamiad_challenge()
        app.api.send(
            chat_id,
            frame_error("این کپچا منقضی شده است."),
            keyboard([button("🔐 تصویر جدید همیاد", action="hamiad:login")]),
        )
        return
    consumed = app.state.take_hamiad_challenge(message_id=reply_id)
    if not consumed or app.site_api is None:
        return
    try:
        result = app.site_api.hamiad_login_complete(
            app.owner_id,
            challenge_ref=str(consumed["challenge_ref"]),
            captcha_code=code.upper(),
        )
        if result.get("status") != "connected":
            raise SiteApiError("ورود به همیاد تأیید نشد.")
        app.api.send(
            chat_id,
            "<b>✅ ورود به همیاد تأیید شد</b>\n\n"
            "<blockquote>نشست دانشگاه در سایت ذخیره شد و درس‌ها و تکالیف قابل بررسی‌اند.</blockquote>",
            keyboard(
                [button("📚 مشاهده همیاد", action="hamiad-center")],
                [button("🏠 خانه", action="home")],
            ),
        )
    except SiteApiError as error:
        app.api.send(
            chat_id,
            frame_error(str(error) + " برای ورود دوباره تصویر تازه بگیر."),
            keyboard(
                [button("🔐 تصویر جدید همیاد", action="hamiad:login")],
                [button("🌐 پنل همیاد", url=app.site_url.rstrip("/") + "/hamiad/")],
            ),
        )
