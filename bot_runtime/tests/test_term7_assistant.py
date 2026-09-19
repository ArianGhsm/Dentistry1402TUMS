import unittest

from dent_bot.academic_term7_rich import decorate_academic_notification_screen
from dent_bot.ui import account_screen, notification_detail_screen as base_notification_detail_screen


def notification_detail_screen(item, ref, *args, **kwargs):
    return decorate_academic_notification_screen(
        base_notification_detail_screen(item, ref, *args, **kwargs), item
    )


class Term7AssistantUiTests(unittest.TestCase):
    def test_account_dis_is_private_and_default_password_only_when_present(self):
        with_dis = account_screen(
            "https://dentistry1402tums.ir",
            platform="telegram",
            linked_user={"name": "دانشجو", "roleLabel": "دانشجو", "disNumber": "123456"},
        )
        self.assertIn("کد DIS", with_dis.text)
        self.assertIn("123456", with_dis.text)
        self.assertIn("111", with_dis.text)

        without_dis = account_screen(
            "https://dentistry1402tums.ir",
            platform="bale",
            linked_user={"name": "دانشجو", "roleLabel": "دانشجو", "disNumber": ""},
        )
        self.assertIn("ثبت نشده", without_dis.text)
        self.assertNotIn("111", without_dis.text)

    def test_food_notification_url_and_action_have_telegram_bale_parity(self):
        item = {
            "title": "🍽 یادآوری رزرو غذا",
            "body": "اگر رزرو کردی، تأیید کن.",
            "ctaLabel": "🍽 رزرو غذا",
            "ctaUrl": "http://foodstu.tums.ac.ir",
            "actions": [{"ref": "a1b2c3d4e5f6g7h8", "label": "✅ رزرو کردم", "style": "success"}],
        }
        telegram = notification_detail_screen(item, "ref123", platform="telegram", is_owner=False)
        bale = notification_detail_screen(item, "ref123", platform="bale", is_owner=False)
        self.assertEqual(telegram.keyboard, bale.keyboard)
        self.assertIn("http://foodstu.tums.ac.ir", str(telegram.keyboard))
        self.assertIn("notification-action:ref123:a1b2c3d4e5f6g7h8", str(telegram.keyboard))

    def test_academic_reminder_uses_native_rich_table_and_syllabus_priority_clock_times(self):
        item = {
            "source": "academic-term7",
            "title": "📅 برنامه فردا | شنبه ۱۴۰۵/۰۶/۲۸",
            "body": "📚 کلاس‌های نظری\n• پریو نظری ۱ — جلسه ۱: آناتومی انساج پریودنتال ۱ · مجازی\n  ⏰ ۰۷:۳۰ تا ۰۸:۳۰\n  📍 مجازی\n\n🦷 کارآموزی صبح\n• پروتز پارسیل عملی ۱\n  ⏰ ۰۹:۰۰ تا ۱۲:۰۰\n\n🌆 کارآموزی عصر\n• روش تحقیق ۲ — جلسه ۱: مقدمه و معرفی دوره و منابع\n  ⏰ ۱۳:۰۰ تا ۱۵:۳۰\n  📍 آمفی‌تئاتر ۹۰",
        }
        telegram = notification_detail_screen(item, "ref123", platform="telegram", is_owner=False)
        bale = notification_detail_screen(item, "ref123", platform="bale", is_owner=False)
        self.assertTrue(hasattr(telegram.text, "rich_html"))
        self.assertIn("<table bordered striped compact>", telegram.text.rich_html)
        self.assertIn("<th>زمان</th>", telegram.text.rich_html)
        self.assertIn("<th>وضعیت / مکان</th>", telegram.text.rich_html)
        self.assertIn("آناتومی انساج پریودنتال ۱ · مجازی", telegram.text.rich_html)
        self.assertIn("<td>مجازی</td>", telegram.text.rich_html)
        self.assertIn("<td>آمفی‌تئاتر ۹۰</td>", telegram.text.rich_html)
        self.assertIn("۰۹:۰۰–۱۲:۰۰", telegram.text.rich_html)
        self.assertIn("۱۳:۰۰–۱۵:۳۰", telegram.text.rich_html)
        self.assertIn("۰۹:۰۰–۱۲:۰۰", str(telegram.text))
        self.assertIn("۱۳:۰۰–۱۵:۳۰", str(bale.text))
        self.assertNotIn("کارآموزی صبح", str(telegram.text))
        self.assertNotIn("کارآموزی عصر", str(telegram.text))
        self.assertEqual(telegram.keyboard, bale.keyboard)

    def test_academic_correction_reuses_the_same_native_rich_table(self):
        item = {
            "source": "manager",
            "title": "📣 اصلاح برنامه | شنبه ۱۴۰۵/۰۶/۲۸",
            "body": "📚 کلاس‌های نظری\n• پریو نظری ۱ — جلسه ۱: آناتومی انساج پریودنتال ۱ · مجازی\n  ⏰ ۰۷:۳۰ تا ۰۸:۳۰\n  📍 مجازی\n\nℹ️ محل حضوری درج‌شده برای این کلاس معتبر نیست.",
        }
        telegram = notification_detail_screen(item, "ref123", platform="telegram", is_owner=False)
        bale = notification_detail_screen(item, "ref123", platform="bale", is_owner=False)
        self.assertIn("<table bordered striped compact>", telegram.text.rich_html)
        self.assertIn("<td>مجازی</td>", telegram.text.rich_html)
        self.assertIn("اصلاح برنامه", str(telegram.text))
        self.assertIn("محل حضوری", telegram.text.rich_html)
        self.assertEqual(telegram.keyboard, bale.keyboard)

    def test_regular_manager_notification_stays_on_generic_renderer(self):
        item = {
            "source": "manager",
            "title": "📣 اطلاعیه عمومی",
            "body": "این پیام ساختار برنامه ترم ۷ را ندارد.",
        }
        base = base_notification_detail_screen(item, "ref123", platform="telegram", is_owner=False)
        decorated = decorate_academic_notification_screen(base, item)
        self.assertFalse(hasattr(decorated.text, "rich_html"))
        self.assertEqual(str(decorated.text), str(base.text))

    def test_academic_reminder_missing_oral_health_is_explicit_without_guessing(self):
        item = {
            "source": "academic-term7",
            "title": "📅 برنامه فردا | شنبه ۱۴۰۵/۰۶/۲۸",
            "body": "📚 کلاس‌های نظری\n• کلاس نظری ثبت‌شده‌ای ندارد.\n\n🦷 کارآموزی صبح\n• برنامه‌ای برای گروه شما ثبت نشده است.\n\n🌆 کارآموزی عصر\n• برنامه‌ای برای گروه شما ثبت نشده است.\n\nℹ️ روز سلامت دهان عملی ۲ شما برای روتیشن اول هنوز ثبت نشده است؛ این بخش حدس زده نمی‌شود.",
        }
        screen = notification_detail_screen(item, "ref123", platform="telegram", is_owner=False)
        self.assertIn("روز سلامت دهان عملی ۲", str(screen.text))
        self.assertIn("حدس زده نمی‌شود", str(screen.text))

    def test_academic_wrapper_preserves_detail_optional_kwargs(self):
        item = {
            "source": "academic-term7",
            "title": "📅 برنامه فردا | شنبه ۱۴۰۵/۰۶/۲۸",
            "body": "🦷 کارآموزی صبح\n• پروتز پارسیل عملی ۱\n  ⏰ ۰۹:۰۰ تا ۱۲:۰۰",
        }
        screen = notification_detail_screen(
            item,
            "ref123",
            platform="telegram",
            is_owner=False,
            site_url="https://example.test",
            show_mark_read=False,
        )
        self.assertIn("<table bordered striped compact>", screen.text.rich_html)
        self.assertNotIn("notification-read:", str(screen.keyboard))

    def test_academic_wrapper_preserves_push_defaults(self):
        from dent_bot import runtime as runtime_module

        item = {
            "source": "academic-term7",
            "title": "📅 برنامه فردا | شنبه ۱۴۰۵/۰۶/۲۸",
            "body": "🦷 کارآموزی ۰۹:۰۰ تا ۱۲:۰۰\n• پروتز پارسیل عملی ۱\n  ⏰ ۰۹:۰۰ تا ۱۲:۰۰",
        }
        screen = decorate_academic_notification_screen(
            runtime_module.notification_push_screen(item, "ref123", platform="telegram"), item
        )
        self.assertIn("۰۹:۰۰–۱۲:۰۰", str(screen.text))

    def test_notification_persian_html_is_escaped_without_breaking_structure(self):
        screen = notification_detail_screen(
            {"title": "برنامه <فردا>", "body": "📚 نظری\n• اندو 1 & عملی"},
            "ref123",
            platform="telegram",
            is_owner=False,
        )
        self.assertIn("&lt;فردا&gt;", screen.text)
        self.assertIn("&amp;", screen.text)
        self.assertIn("📚 نظری", screen.text)


if __name__ == "__main__":
    unittest.main()
