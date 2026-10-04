from dent_bot.academic_term7_rich import academic_notification_text


def test_custom_academic_heading_is_preserved():
    rich = academic_notification_text({
        "source": "academic-term7",
        "title": "📅 برنامه روز شنبه ۲۸ شهریور ماه | شنبه ۱۴۰۵/۰۶/۲۸",
        "body": "📚 کلاس‌های نظری\n• پریو نظری ۱\n  ⏰ ۰۷:۳۰ تا ۰۸:۳۰",
    })
    assert rich is not None
    assert "برنامه روز شنبه ۲۸ شهریور ماه" in str(rich)
    assert "<h2>📅 برنامه روز شنبه ۲۸ شهریور ماه</h2>" in rich.rich_html
    assert "برنامه فردا" not in str(rich)


def test_standard_tomorrow_heading_still_works():
    rich = academic_notification_text({
        "source": "academic-term7",
        "title": "📅 برنامه فردا | شنبه ۱۴۰۵/۰۶/۲۸",
        "body": "📚 کلاس‌های نظری\n• پریو نظری ۱\n  ⏰ ۰۷:۳۰ تا ۰۸:۳۰",
    })
    assert rich is not None
    assert "برنامه فردا" in str(rich)
    assert "<h2>📅 برنامه فردا</h2>" in rich.rich_html

def test_structured_academic_row_keeps_pathology_resident_visible():
    rich = academic_notification_text({
        "source": "academic-term7",
        "title": "📅 برنامه فردا | سه‌شنبه ۱۴۰۵/۰۷/۰۷",
        "body": "",
        "meta": {
            "academicScheduleRows": [{
                "kind": "practical",
                "title": "آسیب‌شناسی عملی ۱ — جلسه ۲: گرانولوم نوک ریشه – کیست رادیکولار",
                "start": "09:00",
                "end": "12:00",
                "location": "بخش پاتولوژی",
                "instructor": "دکتر مرادزاده",
                "resident": "دکتر صبوری",
            }]
        },
    })
    assert rich is not None
    assert "🩺 رزیدنت: دکتر صبوری" in str(rich)
    assert "🩺 رزیدنت: دکتر صبوری" in rich.rich_html
    assert "دکتر مرادزاده" in rich.rich_html


def test_virtual_class_correction_uses_dedicated_rich_table() -> None:
    rich = academic_notification_text({
        "source": "academic-term7-virtual-correction",
        "title": "📣 اصلاحیه نهایی برنامه کلاس‌های مجازی",
        "body": "",
        "meta": {
            "virtualClassSummaryRows": [
                {
                    "courseTitle": "دندانپزشکی تشخیصی ۳",
                    "virtualCount": 16,
                    "detail": "۱۱ آفلاین · ۵ آنلاین",
                },
                {
                    "courseTitle": "اندودانتیکس نظری ۱",
                    "virtualCount": 1,
                    "detail": "جلسه ۱۵ · غیرحضوری در همیاد",
                },
                {
                    "courseTitle": "مبانی پارسیل نظری",
                    "virtualCount": 7,
                    "detail": "جلسات ۳، ۴، ۵، ۱۰، ۱۱، ۱۴ و ۱۵",
                },
            ]
        },
    })
    assert rich is not None
    assert "این پیام جایگزین اعلان قبلی است." in str(rich)
    assert "۱۶ جلسه مجازی" in str(rich)
    assert "۷ جلسه مجازی" in str(rich)
    assert "<table bordered striped compact>" in rich.rich_html
    assert "<th>درس</th><th>جلسات مجازی</th><th>جزئیات</th>" in rich.rich_html
    assert "<b>دندانپزشکی تشخیصی ۳</b>" in rich.rich_html
    assert "روزانه، هفتگی و ماهانه" in rich.rich_html
