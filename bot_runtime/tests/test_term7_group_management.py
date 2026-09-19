from __future__ import annotations

import unittest

from dent_bot.term7_group_management import (
    _assignment_summary,
    _booklet_group_index,
    _booklet_group_screen,
    _booklet_member_screen,
    _choose_booklet_group_screen,
    _choose_group_screen,
    _group_index,
    _group_screen,
    _home_screen,
    _student_screen,
)


ROSTER = [
    {
        "studentNumber": "40211272991",
        "name": "دانشجوی الف",
        "assignment": {
            "group10": 1,
            "group8": 12,
            "group10Status": "leader",
            "group10StatusLabel": "سرگروه",
            "group8Status": "member",
            "group8StatusLabel": "عضو",
        },
        "bookletSystem": {
            "group": 17,
            "status": "leader",
            "statusLabel": "سرگروه",
            "managerCourses": [{"title": "روش تحقیق ۲"}],
            "specialRoles": [],
            "freeSubscriptionEligible": True,
        },
    },
    {
        "studentNumber": "40211272992",
        "name": "دانشجوی ب",
        "assignment": {
            "group10": 1,
            "group8": None,
            "group10Status": "member",
            "group10StatusLabel": "عضو",
            "group8Status": "unassigned",
            "group8StatusLabel": "بدون گروه",
        },
        "bookletSystem": {
            "group": None,
            "status": "unassigned",
            "statusLabel": "بدون گروه",
            "managerCourses": [],
            "specialRoles": [],
            "freeSubscriptionEligible": False,
        },
    },
]


BOOKLET_PAYLOAD = {
    "summary": {
        "classCount": 2,
        "classBookletMembers": 1,
        "classFreeEligible": 1,
        "classPaidMembers": 1,
        "classOutsideBookletGroups": 1,
        "bookletMembers": 1,
        "freeEligible": 2,
    },
    "groups": [
        {
            "group": 17,
            "leaderName": "دانشجوی الف",
            "memberCount": 1,
            "courses": [{"title": "روش تحقیق ۲"}],
            "members": [
                {
                    "studentNumber": "40211272991",
                    "name": "دانشجوی الف",
                    "status": "leader",
                    "statusLabel": "سرگروه",
                }
            ],
        }
    ],
    "members": [ROSTER[0]],
    "classRoster": ROSTER,
}



class Term7GroupManagementTests(unittest.TestCase):
    @staticmethod
    def callbacks(screen):
        return [
            item["callback_data"]
            for row in screen.keyboard.get("inline_keyboard", [])
            for item in row
            if isinstance(item, dict) and "callback_data" in item
        ]

    def test_student_summary_is_persian_and_status_aware(self):
        text = _assignment_summary(ROSTER[0]["assignment"])
        self.assertIn("صبح: گروه ۱ · سرگروه", text)
        self.assertIn("عصر: گروه ۱۲ · عضو", text)

    def test_group_index_is_native_rich_and_has_no_fake_table(self):
        screen = _group_index(ROSTER, "group10")
        self.assertTrue(hasattr(screen.text, "rich_html"))
        self.assertIn("<table bordered striped compact>", screen.text.rich_html)
        self.assertNotIn("<pre>", screen.text.rich_html)
        self.assertIn("دانشجوی الف", screen.text.rich_html)

    def test_callbacks_remain_within_transport_limit(self):
        screens = [
            _home_screen(ROSTER),
            _group_screen(ROSTER, "group10", 1),
            _student_screen(ROSTER[0]),
            _choose_group_screen(ROSTER[0], "group8"),
        ]
        for screen in screens:
            for callback in self.callbacks(screen):
                self.assertLessEqual(len(callback.encode("utf-8")), 64, callback)

    def test_leader_toggle_and_clear_group_are_explicit(self):
        student = _student_screen(ROSTER[0])
        labels = [item.get("text") for row in student.keyboard["inline_keyboard"] for item in row]
        self.assertIn("برداشتن سرگروهی صبح", labels)
        choose = _choose_group_screen(ROSTER[0], "group10")
        labels = [item.get("text") for row in choose.keyboard["inline_keyboard"] for item in row]
        self.assertIn("پاک‌کردن گروه", labels)

    def test_student_screen_reuses_grouping_hierarchy_for_booklet_system(self):
        screen = _student_screen(ROSTER[0])
        self.assertTrue(hasattr(screen.text, "rich_html"))
        self.assertIn("جزوه‌نویسی", screen.text.rich_html)
        self.assertIn("گروه ۱۷", screen.text.rich_html)
        self.assertIn("مسئول جزوه", screen.text.rich_html)
        self.assertIn("رایگان", screen.text.rich_html)
        labels = [item.get("text") for row in screen.keyboard["inline_keyboard"] for item in row]
        self.assertIn("تغییر گروه جزوه‌نویسی", labels)

    def test_booklet_group_index_is_native_rich_and_reports_paid_class_count(self):
        screen = _booklet_group_index(BOOKLET_PAYLOAD)
        self.assertTrue(hasattr(screen.text, "rich_html"))
        self.assertIn("<table bordered striped compact>", screen.text.rich_html)
        self.assertIn("روش تحقیق ۲", screen.text.rich_html)
        self.assertIn("خارج از گروه‌ها: ۱ نفر", screen.text.rich_html)

    def test_booklet_group_and_member_views_are_navigable(self):
        group = _booklet_group_screen(BOOKLET_PAYLOAD, 17)
        member = _booklet_member_screen(ROSTER[0])
        choose = _choose_booklet_group_screen(ROSTER[0])
        self.assertIn("دانشجوی الف", group.text)
        self.assertIn("مسئول جزوه", member.text)
        labels = [item.get("text") for row in choose.keyboard["inline_keyboard"] for item in row]
        self.assertIn("گروه ۳۱", labels)
        for screen in (group, member, choose):
            for callback in self.callbacks(screen):
                self.assertLessEqual(len(callback.encode("utf-8")), 64, callback)

    def test_home_surfaces_booklet_groups_and_paid_path_count(self):
        screen = _home_screen(ROSTER, BOOKLET_PAYLOAD)
        self.assertIn("خارج از گروه: ۱", screen.text)
        labels = [item.get("text") for row in screen.keyboard["inline_keyboard"] for item in row]
        self.assertIn("📝 گروه‌های جزوه‌نویسی", labels)

    def test_owner_surfaces_have_single_term7_management_entry(self):
        from dent_bot.class_operations import _owner_screen
        from dent_bot.classops_ui import owner_home_screen

        class App:
            site_url = "https://example.test"

        for screen in (_owner_screen(App(), {}), owner_home_screen()):
            callbacks = [
                str(item.get("callback_data") or "")
                for row in screen.keyboard["inline_keyboard"]
                for item in row
            ]
            self.assertEqual(callbacks.count("v1:t7"), 1)


if __name__ == "__main__":
    unittest.main()
