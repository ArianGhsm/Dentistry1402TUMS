from __future__ import annotations

import unittest

from dent_bot.classops_owner_workflows import (
    build_create_request,
    confirm_lifecycle_screen,
    decorate_owner_detail,
    dialog_kind_supported,
    parse_jalali_datetime,
)
from dent_bot.ui import Screen, button, keyboard


class ClassOpsOwnerWorkflowTests(unittest.TestCase):
    def test_jalali_datetime_and_create_request_remain_canonical(self):
        iso = parse_jalali_datetime("۱۴۰۵/۰۶/۲۰ ۱۰:۳۰")
        self.assertTrue(iso.startswith("2026-09-11T10:30:00"))
        for item_type in ("exam", "event", "task", "deadline", "requirement"):
            request = build_create_request(item_type, "عنوان معتبر", "توضیح", iso)
            self.assertEqual(request["item"]["type"], item_type)
            self.assertEqual(request["item"]["cohortKey"], "dentistry-1402")
            self.assertEqual(request["destinations"], ["private_users"])
            self.assertEqual(request["audienceSpec"]["expression"], {"op": "whole_cohort"})
        with self.assertRaises(ValueError):
            parse_jalali_datetime("فردا ساعت ده")
        with self.assertRaises(ValueError):
            build_create_request("unknown", "x", "", iso)

    def test_active_legacy_dialog_kind_survives_runtime_upgrade(self):
        self.assertTrue(dialog_kind_supported("classops-owner-compose"))
        self.assertTrue(dialog_kind_supported("classops-ux-v2"))
        self.assertFalse(dialog_kind_supported("another-dialog"))

    def test_owner_detail_uses_only_current_callbacks(self):
        item_id = "cop_" + "a" * 24
        screen = Screen("جزئیات", keyboard([button("لغو", action="cxo_cancel"), button("آرشیو", action="cxo_archive")]))
        decorated = decorate_owner_detail(screen, {"id": item_id, "status": "active"}, {"cancel": "cxo_cancel", "archive": "cxo_archive"})
        rendered = str(decorated.keyboard)
        self.assertIn("c3:o:edit:", rendered)
        self.assertIn("c3:o:confirm-cancel:", rendered)
        self.assertIn("c3:o:confirm-archive:", rendered)
        self.assertNotIn("classops-v2:", rendered)

    def test_lifecycle_confirmation_has_predictable_navigation(self):
        screen = confirm_lifecycle_screen("cxo_123", "cancel")
        rendered = str(screen.keyboard)
        self.assertIn("cxo_123", rendered)
        self.assertIn("c3:owner", rendered)
        self.assertNotIn("classops-v2:", rendered)


if __name__ == "__main__":
    unittest.main()
