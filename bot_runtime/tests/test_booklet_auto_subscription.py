from __future__ import annotations

from datetime import timedelta
from pathlib import Path
from tempfile import TemporaryDirectory
import unittest

from dent_bot.state import BotState
from dent_bot.subscriptions import jalali_midnight_utc, subscription_identity_from_directory
from dent_bot.ui import term_subscription_screen


class BookletAutomaticSubscriptionTests(unittest.TestCase):
    def setUp(self):
        self.tmp = TemporaryDirectory()
        root = Path(self.tmp.name)
        self.state = BotState(root / "state.sqlite3", payment_offers_path=root / "payments.sqlite3")
        self.start = jalali_midnight_utc(1405, 7, 1)
        self.after_start = self.start + timedelta(hours=1)

    def tearDown(self):
        self.state.close()
        self.tmp.cleanup()

    def test_prestart_member_stays_closed_and_zero_price_is_explained(self):
        before = self.start - timedelta(minutes=1)
        self.assertIsNone(
            self.state.ensure_automatic_booklet_entitlement(
                student_number="40211272991",
                display_name="عضو جزوه",
                now=before,
            )
        )
        identity = subscription_identity_from_directory(
            {"studentNumber": "40211272991", "name": "عضو جزوه"}
        )
        assert identity is not None
        decision = self.state.term_access_decision(identity.subject_key, 7, now=before)
        self.assertFalse(decision["allowed"])
        self.assertEqual(decision["reason"], "subscription-not-started")
        decision["freeSubscriptionEligible"] = True
        screen = term_subscription_screen(self.state.term_access_policy(7) or {}, decision, term=7)
        self.assertIn("۰ تومان", screen.text)
        self.assertIn("فعال‌سازی خودکار", screen.text)
        self.assertNotIn("خرید اشتراک", screen.text)

    def test_member_gets_current_month_automatic_entitlement(self):
        grant = self.state.ensure_automatic_booklet_entitlement(
            student_number="40211272991",
            display_name="عضو جزوه",
            now=self.after_start,
        )
        self.assertIsNotNone(grant)
        assert grant is not None
        self.assertEqual(grant["accessType"], "complimentary")
        self.assertTrue(grant["billingPeriod"].startswith("term7-1405-07"))
        self.assertEqual(grant["note"], "booklet-system:auto-monthly")
        identity = subscription_identity_from_directory(
            {"studentNumber": "40211272991", "name": "عضو جزوه"}
        )
        assert identity is not None
        decision = self.state.term_access_decision(identity.subject_key, 7, now=self.after_start)
        self.assertTrue(decision["allowed"])
        self.assertEqual(decision["accessPath"], "complimentary")
        self.assertEqual(decision["complimentarySource"], "booklet-system")
        self.assertEqual(self.state.complimentary_term_access(7), [])

    def test_immediate_revoke_closes_removed_member_without_touching_paid_or_manual(self):
        self.state.ensure_automatic_booklet_entitlement(
            student_number="40211272991",
            display_name="عضو جزوه",
            now=self.after_start,
        )
        identity = subscription_identity_from_directory(
            {"studentNumber": "40211272991", "name": "عضو جزوه"}
        )
        assert identity is not None
        self.assertTrue(
            self.state.revoke_automatic_booklet_entitlement(
                student_number="40211272991",
                now=self.after_start + timedelta(minutes=1),
            )
        )
        decision = self.state.term_access_decision(
            identity.subject_key,
            7,
            now=self.after_start + timedelta(minutes=1),
        )
        self.assertFalse(decision["allowed"])

    def test_sync_revokes_removed_member_but_preserves_manual_override(self):
        eligible = [
            {"studentNumber": "40211272991", "name": "عضو الف"},
            {"studentNumber": "40211272992", "name": "عضو ب"},
        ]
        first = self.state.sync_automatic_booklet_entitlements(
            eligible, term=7, now=self.after_start
        )
        self.assertTrue(first["effective"])
        self.assertEqual(first["eligible"], 2)

        self.state.grant_complimentary_term_access(
            term=7,
            student_number="40211272993",
            display_name="معافیت دستی",
            actor_user_id=1,
            actor_platform="telegram",
            note="تأیید مالک",
        )

        second = self.state.sync_automatic_booklet_entitlements(
            [eligible[0]], term=7, now=self.after_start + timedelta(minutes=5)
        )
        self.assertEqual(second["revoked"], 1)

        removed = subscription_identity_from_directory(eligible[1])
        manual = subscription_identity_from_directory(
            {"studentNumber": "40211272993", "name": "معافیت دستی"}
        )
        assert removed is not None and manual is not None
        self.assertFalse(
            self.state.term_access_decision(
                removed.subject_key, 7, now=self.after_start + timedelta(minutes=5)
            )["allowed"]
        )
        manual_decision = self.state.term_access_decision(
            manual.subject_key, 7, now=self.after_start + timedelta(minutes=5)
        )
        self.assertTrue(manual_decision["allowed"])
        self.assertEqual(manual_decision["complimentarySource"], "manual")
        self.assertEqual(len(self.state.complimentary_term_access(7)), 1)


if __name__ == "__main__":
    unittest.main()
