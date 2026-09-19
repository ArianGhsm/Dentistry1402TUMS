from __future__ import annotations

import unittest

from dent_bot.ui import account_screen
from dent_bot.classops_ui import grouping_screen


class BookletProfileUiTests(unittest.TestCase):
    def test_account_profile_shows_group_role_and_free_subscription(self):
        screen = account_screen(
            "https://example.test",
            platform="telegram",
            linked_user={"name": "دانشجوی نمونه", "roleLabel": "دانشجو"},
            booklet_profile={
                "group": 24,
                "statusLabel": "سرگروه",
                "managerCourses": [{"title": "روش تحقیق ۲"}, {"title": "گوش و حلق و بینی"}],
                "specialRoles": [],
                "freeSubscriptionEligible": True,
            },
        )
        self.assertIn("📝 جزوه‌نویسی", screen.text)
        self.assertIn("گروه ۲۴ · سرگروه", screen.text)
        self.assertIn("مسئول جزوه", screen.text)
        self.assertIn("رایگان · فعال‌سازی خودکار ماهانه", screen.text)

    def test_account_profile_shows_infographic_role_without_group(self):
        screen = account_screen(
            "https://example.test",
            platform="telegram",
            linked_user={"name": "حسین شاهسواری", "roleLabel": "دانشجو"},
            booklet_profile={
                "group": None,
                "statusLabel": "بدون گروه",
                "managerCourses": [],
                "specialRoles": [{"label": "مسئول اینفوگرافیک"}],
                "freeSubscriptionEligible": True,
            },
        )
        self.assertIn("عضو گروه جزوه‌نویسی نیست", screen.text)
        self.assertIn("مسئول اینفوگرافیک", screen.text)
        self.assertIn("رایگان", screen.text)

    def test_account_profile_nonmember_is_explicitly_paid(self):
        screen = account_screen(
            "https://example.test",
            platform="telegram",
            linked_user={"name": "دانشجوی پرداختی", "roleLabel": "دانشجو"},
            booklet_profile={
                "group": None,
                "managerCourses": [],
                "specialRoles": [],
                "freeSubscriptionEligible": False,
            },
        )
        self.assertIn("۱۵۰٬۰۰۰ تومان در ماه", screen.text)

    def test_student_grouping_uses_same_table_hierarchy_for_booklet_group(self):
        screen = grouping_screen(
            {
                "eligible": True,
                "academicTerm": {"termLabel": "ترم ۷"},
                "scheduleContext": {"rotationLabel": "روتیشن اول"},
                "groups": {
                    "morning": {"group": 1, "leaderName": "", "members": ["الف"]},
                    "afternoon": {"group": 11, "leaderName": "", "members": ["ب"]},
                },
                "bookletSystem": {
                    "group": 24,
                    "statusLabel": "سرگروه",
                    "members": [{"name": "دانشجوی نمونه"}, {"name": "هم‌گروهی"}],
                    "managerCourses": [{"title": "روش تحقیق ۲"}],
                    "specialRoles": [],
                    "freeSubscriptionEligible": True,
                },
            }
        )
        self.assertTrue(hasattr(screen.text, "rich_html"))
        self.assertIn("<th>جزوه‌نویسی</th>", screen.text.rich_html)
        self.assertIn("گروه ۲۴ · سرگروه", screen.text.rich_html)
        self.assertIn("مسئول جزوه", screen.text.rich_html)
        self.assertIn("رایگان", screen.text.rich_html)


if __name__ == "__main__":
    unittest.main()
