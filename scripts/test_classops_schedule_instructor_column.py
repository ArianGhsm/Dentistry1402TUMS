from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "bot_runtime"))

from dent_bot.classops_ux_v3 import daily_screen, weekly_screen, month_screen


def assert_true(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)
    print(f"PASS: {message}")


def schedule_item(title: str, *, instructor: str = "") -> dict:
    return {
        "source": "term7",
        "ref": "t7_fixture",
        "type": "theory",
        "status": "active",
        "title": title,
        "courseTitle": title.split(" — ", 1)[0],
        "location": "آمفی‌تئاتر ۹۰",
        "localDate": "2026-09-19",
        "startsAt": "2026-09-19T07:30:00+03:30",
        "endsAt": "2026-09-19T08:30:00+03:30",
        "dueAt": "",
        "timeLabel": "",
        "sortAt": "2026-09-19T04:00:00+00:00",
        "overdue": False,
        "instructor": instructor,
    }


day = {
    "localDate": "2026-09-19",
    "items": [
        schedule_item("پریو نظری ۱ — جلسه ۱: آناتومی انساج پریودنتال ۱", instructor="دکتر همتیان"),
        schedule_item("گوش و حلق و بینی"),
    ],
}

screens = [
    ("روزانه", daily_screen(day)),
    ("هفتگی", weekly_screen([day], 0)),
    ("ماهانه", month_screen([day], 0)),
]
for label, screen in screens:
    rich = str(getattr(screen.text, "rich_html", ""))
    fallback = str(screen.text)
    assert_true("<th>استاد</th>" in rich, f"{label}: ستون استاد در جدول برنامه وجود دارد")
    assert_true("<th>وضعیت</th>" not in rich, f"{label}: ستون وضعیت از جدول برنامه حذف شده است")
    assert_true("دکتر همتیان" in rich, f"{label}: نام استاد طرح درس نمایش داده می‌شود")
    assert_true("<td></td>" in rich, f"{label}: استاد ناموجود به‌صورت سلول خالی نمایش داده می‌شود")
    assert_true("دکتر همتیان" in fallback, f"{label}: fallback نام استاد را حفظ می‌کند")
    assert_true("🟢" not in fallback and "فعال" not in fallback, f"{label}: fallback برنامه وضعیت را جای استاد نشان نمی‌دهد")

print("ClassOps schedule instructor-column checks passed.")
