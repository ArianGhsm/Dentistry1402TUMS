from __future__ import annotations

import unittest

from dent_bot.classops_shell import (
    CANONICAL_HOME_ROWS,
    canonical_home_screen,
    navid_center_screen,
    owner_management_screen,
    service_status_screen,
    status_marker,
)


class ClassOpsShellTests(unittest.TestCase):
    @staticmethod
    def _labels(screen):
        return [[entry["text"] for entry in row] for row in screen.keyboard["inline_keyboard"]]

    @staticmethod
    def _actions(screen):
        return [[str(entry.get("callback_data") or "").removeprefix("v1:") for entry in row] for row in screen.keyboard["inline_keyboard"]]

    def test_home_preserves_existing_best_layout(self):
        screen = canonical_home_screen(is_owner=False)
        self.assertEqual(
            self._labels(screen),
            [
                ["🧭 مرکز همیاد"],
                ["📚 جزوات", "💳 اشتراک جزوات"],
                ["📊 نمرات", "🗂 امور کلاس"],
                ["👤 حساب من", "🔔 اعلان‌ها", "❓ راهنما"],
            ],
        )
        self.assertEqual(self._actions(screen), [[action for _label, action in row] for row in CANONICAL_HOME_ROWS])

    def test_owner_management_preserves_layout_and_uses_current_classops_route(self):
        screen = owner_management_screen()
        rendered = str(screen.keyboard)
        self.assertIn("🛠 مدیریت ربات", str(screen.text))
        self.assertIn("c3:owner", rendered)
        self.assertNotIn("classops-v2:", rendered)
        self.assertNotIn("/admin/", rendered)

    def test_service_status_is_truthful_and_persian(self):
        self.assertEqual(status_marker("ready")[0], "🟢")
        self.assertEqual(status_marker("failed")[0], "🔴")
        screen = service_status_screen({"services": [{"label": "API", "state": "failed"}]})
        self.assertIn("🔴", str(screen.text))
        self.assertIn("خطا", str(screen.text))
        self.assertNotIn("failed", str(screen.text))

    def test_legacy_center_routes_to_hamiad_without_old_data(self):
        empty = navid_center_screen({"view": {"connectors": []}}, site_url="https://example.test")
        self.assertIn("مرکز همیاد", str(empty.text))
        self.assertNotIn("نوید", str(empty.text))
        self.assertIn("https://example.test/hamiad/", str(empty.keyboard))
        old = navid_center_screen({
            "view": {
                "connectors": [{"connector": "navid", "status": "ready", "statusLabel": "آماده"}],
                "actions": [{"label": "ورود نوید", "ref": "abcdefghijkl"}],
            }
        }, site_url="https://example.test")
        self.assertEqual(str(empty.text), str(old.text))
        self.assertNotIn("ادامه در نوید", str(old.keyboard))


if __name__ == "__main__":
    unittest.main()
