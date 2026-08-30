from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]


def require(path: str, *needles: str) -> None:
    text = (ROOT / path).read_text(encoding="utf-8")
    for needle in needles:
        if needle not in text:
            raise AssertionError(f"{path}: missing {needle!r}")


require(
    "public_html/api/navid_service.php",
    "function navid_daily_start(",
    "function navid_daily_complete(",
    "$hasAnnouncementBaseline",
    "notifications_enqueue_navid_assignment($assignment)",
    "NAVID_DAILY_DATE_MISMATCH",
)
require(
    "public_html/api/bot_navid.php",
    "dent_bot_navid_require_owner",
    "NAVID_CAPTCHA_TELEGRAM_ONLY",
    "navid_daily_complete(",
)
require(
    "public_html/api/bot_store.php",
    "navidDailyStart",
    "navidDailyComplete",
)

print("PASS: Navid daily bot contract is owner-only, date-bound, Telegram-captcha-only, and baseline-safe.")
