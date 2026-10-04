from __future__ import annotations

from pathlib import Path

from dent_bot.classops_ui import daily_screen, month_screen, weekly_screen

ROOT = Path(__file__).resolve().parents[2]


def _partial_item(number: int, title: str, *, virtual: bool = False) -> dict[str, object]:
    suffix = " · مجازی" if virtual else ""
    return {
        "source": "term7",
        "ref": f"t7_20261125_partial_{number}",
        "type": "theory",
        "status": "active",
        "title": f"مبانی پارسیل نظری — جلسه {number}: {title}{suffix}",
        "courseTitle": "مبانی پارسیل نظری",
        "sessionNumber": number,
        "sessionMode": "virtual" if virtual else "in_person",
        "sessionModeLabel": "مجازی" if virtual else "حضوری",
        "localDate": "2026-11-25",
        "startsAt": "2026-11-25T07:30:00+03:30",
        "endsAt": "2026-11-25T08:30:00+03:30",
        "sortAt": "2026-11-25T04:00:00+00:00",
        "timeLabel": "",
        "overdue": False,
    }


def test_partial_titles_render_in_daily_weekly_monthly_with_persian_digits_and_order() -> None:
    day = {
        "localDate": "2026-11-25",
        "items": [
            _partial_item(11, "آماده‌سازی دهان برای پروتز پارسیل", virtual=True),
            _partial_item(9, "نگهدارنده مستقیم (۲)"),
            _partial_item(10, "ملاحظات بیس پروتز", virtual=True),
        ],
    }
    daily = daily_screen(day)
    weekly = weekly_screen([day], 0)
    monthly = month_screen([day], 0)
    for screen in (daily, weekly, monthly):
        rendered = str(screen.text)
        assert "مبانی پارسیل نظری" in rendered
        assert "جلسه ۹" in rendered
        assert "جلسه ۱۰" in rendered
        assert "مجازی" in rendered
        assert rendered.find("جلسه ۹") < rendered.find("جلسه ۱۰") < rendered.find("جلسه ۱۱")
        assert "جلسه 9" not in rendered
        assert "جلسه 10" not in rendered


def test_classops_ui_stays_shared_between_telegram_and_bale() -> None:
    telegram = (ROOT / "bot_runtime/dent_bot/service.py").read_text(encoding="utf-8")
    bale = (ROOT / "bot_runtime/dent_bot/bale_service.py").read_text(encoding="utf-8")
    assert "run_service(settings=settings, api=api" in telegram
    assert "run_service(settings=settings, api=api" in bale
    assert "install_classops" not in telegram
    assert "install_classops" not in bale


def test_virtual_mode_labels_survive_daily_weekly_monthly_renderers() -> None:
    items = [
        {
            **_partial_item(6, "ضایعات واکنشی"),
            "title": "دندانپزشکی تشخیصی ۳ — جلسه ۶: ضایعات واکنشی · مجازی (آفلاین)",
            "courseTitle": "دندانپزشکی تشخیصی ۳",
            "sessionMode": "offline",
            "sessionModeLabel": "مجازی (آفلاین)",
        },
        {
            **_partial_item(24, "ضایعات بدخیم اپیتلیالی"),
            "title": "دندانپزشکی تشخیصی ۳ — جلسه ۲۴: ضایعات بدخیم اپیتلیالی · مجازی (آنلاین)",
            "courseTitle": "دندانپزشکی تشخیصی ۳",
            "sessionMode": "virtual",
            "sessionModeLabel": "مجازی (آنلاین)",
        },
        {
            **_partial_item(15, "اندودانتیکس در بیماران مسن"),
            "title": "اندودانتیکس نظری ۱ — جلسه ۱۵: اندودانتیکس در بیماران مسن · مجازی (غیرحضوری ـ همیاد)",
            "courseTitle": "اندودانتیکس نظری ۱",
            "sessionMode": "virtual",
            "sessionModeLabel": "مجازی (غیرحضوری ـ همیاد)",
        },
    ]
    day = {"localDate": "2026-12-07", "items": items}
    for screen in (daily_screen(day), weekly_screen([day], 0), month_screen([day], 0)):
        rendered = str(screen.text)
        assert "مجازی (آفلاین)" in rendered
        assert "مجازی (آنلاین)" in rendered
        assert "مجازی (غیرحضوری ـ همیاد)" in rendered
        assert "آمفی" not in rendered
