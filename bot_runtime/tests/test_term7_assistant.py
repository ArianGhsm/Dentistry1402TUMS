import unittest

from dent_bot.academic_term7_rich import install_academic_term7_rich_notifications
install_academic_term7_rich_notifications()
from dent_bot.ui import account_screen, notification_detail_screen


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

    def test_academic_reminder_uses_native_rich_table_and_clock_times(self):
        item = {
            "source": "academic-term7",
            "title": "📅 برنامه فردا | شنبه ۱۴۰۵/۰۶/۲۸",
            "body": "📚 کلاس‌های نظری\n• پریو نظری ۱\n  ⏰ ۰۷:۳۰ تا ۰۸:۳۰\n  📍 آمفی‌تئاتر ۹۰\n\n🦷 کارآموزی ۰۹:۰۰ تا ۱۲:۰۰\n• پروتز پارسیل عملی ۱\n  ⏰ ۰۹:۰۰ تا ۱۲:۰۰\n\n🌆 کارآموزی ۱۳:۰۰ تا ۱۵:۰۰\n• ترمیمی عملی ۲\n  ⏰ ۱۳:۰۰ تا ۱۵:۰۰",
        }
        telegram = notification_detail_screen(item, "ref123", platform="telegram", is_owner=False)
        bale = notification_detail_screen(item, "ref123", platform="bale", is_owner=False)
        self.assertTrue(hasattr(telegram.text, "rich_html"))
        self.assertIn("<table bordered striped compact>", telegram.text.rich_html)
        self.assertIn("<th>زمان</th>", telegram.text.rich_html)
        self.assertIn("۰۹:۰۰–۱۲:۰۰", telegram.text.rich_html)
        self.assertIn("۱۳:۰۰–۱۵:۰۰", telegram.text.rich_html)
        self.assertIn("۰۹:۰۰–۱۲:۰۰", str(telegram.text))
        self.assertIn("۱۳:۰۰–۱۵:۰۰", str(bale.text))
        self.assertNotIn("کارآموزی صبح", str(telegram.text))
        self.assertNotIn("کارآموزی عصر", str(telegram.text))
        self.assertEqual(telegram.keyboard, bale.keyboard)

    def test_academic_reminder_missing_oral_health_is_explicit_without_guessing(self):
        item = {
            "source": "academic-term7",
            "title": "📅 برنامه فردا | شنبه ۱۴۰۵/۰۶/۲۸",
            "body": "📚 کلاس‌های نظری\n• کلاس نظری ثبت‌شده‌ای ندارد.\n\n🦷 کارآموزی ۰۹:۰۰ تا ۱۲:۰۰\n• برنامه‌ای برای گروه شما ثبت نشده است.\n\n🌆 کارآموزی ۱۳:۰۰ تا ۱۵:۰۰\n• برنامه‌ای برای گروه شما ثبت نشده است.\n\nℹ️ روز سلامت دهان عملی ۲ شما برای روتیشن اول هنوز ثبت نشده است؛ این بخش حدس زده نمی‌شود.",
        }
        screen = notification_detail_screen(item, "ref123", platform="telegram", is_owner=False)
        self.assertIn("روز سلامت دهان عملی ۲", str(screen.text))
        self.assertIn("حدس زده نمی‌شود", str(screen.text))

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
