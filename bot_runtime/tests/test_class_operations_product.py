from __future__ import annotations

import unittest

from dent_bot.class_operations_product import (
    _class_home_screen,
    _detail_screen,
    _home_keyboard,
    _legacy_action,
    _list_screen,
    _preview_screen,
)
from dent_bot.ui import Screen, button, keyboard


class _App:
    site_url = "https://example.test"


class ClassOperationsProductTests(unittest.TestCase):
    def test_main_menu_gets_native_class_operations_entry(self) -> None:
        original = Screen(
            "<b>خانه</b>",
            keyboard(
                [button("📝 آزمون‌ها", action="exams")],
                [button("🔔 اعلان‌ها", action="notifications"), button("🛟 راهنما", action="help")],
            ),
        )
        updated = _home_keyboard(original)
        callbacks = [
            item.get("callback_data")
            for row in updated.keyboard["inline_keyboard"]
            for item in row
        ]
        self.assertIn("v1:class-operations", callbacks)
        self.assertIn("v1:notifications", callbacks)
        self.assertLess(callbacks.index("v1:class-operations"), callbacks.index("v1:notifications"))

    def test_student_class_home_is_product_facing(self) -> None:
        screen = _class_home_screen(
            _App(),
            role="student",
            items=[{"type": "task", "status": "active", "title": "تحویل تمرین"}],
        )
        rendered = screen.text + str(screen.keyboard)
        self.assertIn("امور کلاس", rendered)
        self.assertIn("تکالیف و کارها", rendered)
        self.assertNotIn("canonical", rendered.lower())
        self.assertNotIn("ClassOps", rendered)
        self.assertNotIn("مرکز عملیات وب", rendered)

    def test_owner_class_home_has_management_entry(self) -> None:
        screen = _class_home_screen(_App(), role="owner", items=[])
        self.assertIn("مدیریت امور کلاس", str(screen.keyboard))

    def test_legacy_classops_callback_opens_new_presentation(self) -> None:
        self.assertEqual(_legacy_action("classops:menu"), "class-operations")
        self.assertEqual(_legacy_action("classops:items"), "class-operations:list:all")
        self.assertEqual(_legacy_action("classops:tomorrow"), "class-operations:tomorrow")
        self.assertEqual(_legacy_action("classops:item:cop_123"), "class-operations:item:cop_123")

    def test_list_translates_internal_item_types(self) -> None:
        screen = _list_screen(
            [{"id": "cop_123456789012345678901234", "type": "class_change", "status": "active", "title": "جابجایی ترمیمی"}],
            "schedule",
        )
        self.assertIn("تغییر کلاس", screen.text)
        self.assertNotIn("class_change", screen.text)

    def test_student_detail_translates_task_state_and_ack(self) -> None:
        screen = _detail_screen(
            {
                "type": "critical_notice",
                "title": "اطلاعیه مهم",
                "status": "active",
                "description": "متن",
                "ack": {"acked": False},
            },
            {"ack": "cxo_12345678901234567890"},
        )
        button_texts = [
            str(item.get("text") or "")
            for row in screen.keyboard["inline_keyboard"]
            for item in row
        ]
        self.assertIn("نیازمند تأیید", screen.text)
        self.assertTrue(any("دیدم و تأیید می‌کنم" in text for text in button_texts))
        self.assertNotIn("revision", screen.text.lower())
        self.assertFalse(any("revision" in text.lower() for text in button_texts))

    def test_preview_hides_internal_hash_and_revision(self) -> None:
        screen = _preview_screen(
            _App(),
            {
                "preview": {
                    "item": {"type": "announcement", "title": "اطلاعیه", "description": "متن"},
                    "audience": {"total": 12, "resolutionHash": "secret-ish-hash"},
                    "destinations": [{"bindingRef": "private_users"}],
                },
                "confirmToken": "cxo_12345678901234567890",
            },
        )
        self.assertIn("۱۲", screen.text.replace("12", "۱۲") if "12" in screen.text else screen.text)
        self.assertNotIn("secret-ish-hash", screen.text)
        self.assertNotIn("revision", screen.text.lower())


if __name__ == "__main__":
    unittest.main()
