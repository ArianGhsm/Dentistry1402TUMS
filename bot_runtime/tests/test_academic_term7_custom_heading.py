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
