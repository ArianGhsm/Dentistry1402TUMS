from __future__ import annotations

import unittest

from dent_bot.class_operations import (
    _term7_assignment_summary,
    _term7_choose_group_screen,
    _term7_group_index,
    _term7_group_screen,
    _term7_student_screen,
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
    },
]


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
        text = _term7_assignment_summary(ROSTER[0]["assignment"])
        self.assertIn("صبح: گروه ۱ · سرگروه", text)
        self.assertIn("عصر: گروه ۱۲ · عضو", text)

    def test_group_index_is_native_rich_and_has_no_fake_table(self):
        screen = _term7_group_index(ROSTER, "group10")
        self.assertTrue(hasattr(screen.text, "rich_html"))
        self.assertIn("<table bordered striped compact>", screen.text.rich_html)
        self.assertNotIn("<pre>", screen.text.rich_html)
        self.assertIn("دانشجوی الف", screen.text.rich_html)

    def test_callbacks_remain_within_transport_limit(self):
        screens = [
            _term7_group_screen(ROSTER, "group10", 1),
            _term7_student_screen(ROSTER[0]),
            _term7_choose_group_screen(ROSTER[0], "group8"),
        ]
        for screen in screens:
            for callback in self.callbacks(screen):
                self.assertLessEqual(len(callback.encode("utf-8")), 64, callback)

    def test_leader_toggle_and_clear_group_are_explicit(self):
        student = _term7_student_screen(ROSTER[0])
        labels = [item.get("text") for row in student.keyboard["inline_keyboard"] for item in row]
        self.assertIn("برداشتن سرگروهی صبح", labels)
        choose = _term7_choose_group_screen(ROSTER[0], "group10")
        labels = [item.get("text") for row in choose.keyboard["inline_keyboard"] for item in row]
        self.assertIn("پاک‌کردن گروه", labels)


if __name__ == "__main__":
    unittest.main()
