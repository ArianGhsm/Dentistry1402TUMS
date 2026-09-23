from __future__ import annotations

import tempfile
import unittest
from datetime import datetime, timezone
from pathlib import Path

from dent_bot.classops_shell import owner_management_screen
from dent_bot.state import BotState
from dent_bot.ui import (
    ai_booklet_sales_screen,
    booklet_sales_overview_screen,
    booklet_subscription_sales_screen,
)


CATALOG = {
    "term": 7,
    "courses": [{
        "courseKey": "diagnostic-dentistry-3",
        "courseTitle": "دندانپزشکی تشخیصی ۳",
        "bookletTag": "تشخیصی۳",
        "sessions": [
            {"sessionNumber": 1, "title": "ضایعات اگزوفیتیک خارج استخوانی"},
            {"sessionNumber": 2, "title": "ضایعات سفید"},
        ],
    }],
}


class BookletSalesAdminTests(unittest.TestCase):
    def _seed(self, state: BotState) -> None:
        ai_rows = [
            ("student:a", "1", "الف", 7, "diagnostic-dentistry-3", "تشخیصی۳", 1, 390000, "2026-09-23T12:00:00Z", "ai-order-1"),
            ("student:b", "2", "ب", 7, "diagnostic-dentistry-3", "تشخیصی۳", 1, 390000, "2026-09-23T13:00:00Z", "ai-order-2"),
            ("student:a", "1", "الف", 7, "diagnostic-dentistry-3", "تشخیصی۳", 2, 390000, "2026-09-10T12:00:00Z", "ai-order-3"),
        ]
        state.payment_connection.executemany(
            "INSERT INTO ai_booklet_entitlements("
            "subject_key,student_number,display_name,term,course_code,course_tag,session_no,"
            "amount_rials,granted_at,payment_order_token) VALUES(?,?,?,?,?,?,?,?,?,?)",
            ai_rows,
        )
        subscription_rows = [
            ("sub-r1", "telegram", 11, "student:a", "1", "الف", 7, "term7-1405-07", 1500000, "sub", "activated", "sub-o1", "sub-d1", "2026-09-23T10:00:00Z"),
            ("sub-r2", "telegram", 12, "student:b", "2", "ب", 7, "term7-1405-07", 1500000, "sub", "activated", "sub-o2", "sub-d2", "2026-09-23T11:00:00Z"),
            ("sub-r3", "telegram", 11, "student:a", "1", "الف", 7, "term7-1405-06", 1500000, "sub", "activated", "sub-o3", "sub-d3", "2026-08-23T11:00:00Z"),
        ]
        state.payment_connection.executemany(
            "INSERT INTO term_subscription_checkouts("
            "request_id,platform,platform_user_id,subject_key,student_number,display_name,term,"
            "billing_period,amount_rials,offer_ref,status,order_token,verified_delivery_id,verified_at) "
            "VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            subscription_rows,
        )
        state.payment_connection.commit()

    def test_report_counts_only_confirmed_sales_and_separates_revenue_streams(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                self._seed(state)
                report = state.booklet_sales_report(
                    7, now=datetime(2026, 9, 23, 12, tzinfo=timezone.utc)
                )
                self.assertEqual(report["aiBooklets"]["totalSales"], 3)
                self.assertEqual(report["aiBooklets"]["uniqueBuyers"], 2)
                self.assertEqual(report["aiBooklets"]["revenueRials"], 1_170_000)
                self.assertEqual(report["aiBooklets"]["currentSales"], 2)
                self.assertEqual(report["subscriptions"]["totalSales"], 3)
                self.assertEqual(report["subscriptions"]["uniqueBuyers"], 2)
                self.assertEqual(report["subscriptions"]["revenueRials"], 4_500_000)
                self.assertEqual(report["subscriptions"]["currentSales"], 2)
                self.assertEqual(report["subscriptions"]["periodsSold"], 2)
            finally:
                state.close()

    def test_admin_screens_use_persian_hierarchy_and_real_course_titles(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                self._seed(state)
                report = state.booklet_sales_report(
                    7, now=datetime(2026, 9, 23, 12, tzinfo=timezone.utc)
                )
                overview = booklet_sales_overview_screen(report, CATALOG)
                self.assertIn("آمار فروش جزوات", overview.text)
                self.assertIn("مهر ۱۴۰۵", overview.text)
                self.assertIn("۱۱۷٬۰۰۰ تومان", overview.text)
                self.assertIn("۴۵۰٬۰۰۰ تومان", overview.text)

                ai = ai_booklet_sales_screen(report, CATALOG)
                self.assertIn("دندانپزشکی تشخیصی ۳", ai.text)
                self.assertIn("جلسه ۱", ai.text)
                self.assertIn("ضایعات اگزوفیتیک خارج استخوانی", ai.text)
                self.assertNotIn("diagnostic-dentistry-3", ai.text)

                subscriptions = booklet_subscription_sales_screen(report)
                self.assertIn("فروش اشتراک جزوات", subscriptions.text)
                self.assertIn("مهر ۱۴۰۵", subscriptions.text)
                self.assertIn("شهریور ۱۴۰۵", subscriptions.text)
            finally:
                state.close()

    def test_owner_management_links_to_sales_dashboard(self) -> None:
        screen = owner_management_screen()
        labels = [
            item["text"]
            for row in screen.keyboard["inline_keyboard"]
            for item in row
        ]
        actions = [
            str(item.get("callback_data") or "")
            for row in screen.keyboard["inline_keyboard"]
            for item in row
        ]
        self.assertIn("📈 آمار فروش جزوات", labels)
        self.assertIn("v1:booklet-sales", actions)


if __name__ == "__main__":
    unittest.main()
