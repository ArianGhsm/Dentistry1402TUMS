from __future__ import annotations

import queue
import tempfile
import threading
import unittest
from datetime import datetime, timezone
from pathlib import Path

from dent_bot.ai_booklets import AI_BOOKLET_PRICE_RIALS, ai_booklet_offer_ref
from dent_bot.cart import cart_checkout_request_id, cart_item_key, commerce_identity_from_account
from dent_bot.cart_app_workflows import CartAppWorkflows
from dent_bot.cart_ui import cart_screen
from dent_bot.classops_shell import canonical_home_screen
from dent_bot.protected_media import ProtectedMediaDispatcher
from dent_bot.state import BotState
from dent_bot.subscriptions import billing_period_for, subscription_identity_from_directory
from dent_bot.ui import Screen, payment_confirm_screen


def linked_account(student_number: str = "40211272010") -> dict:
    return {
        "linked": True,
        "authComplete": True,
        "user": {
            "studentNumber": student_number,
            "name": "دانشجوی تست",
            "cohortKey": "dentistry-1402",
        },
    }


def callbacks(screen: Screen) -> list[str]:
    return [
        str(item.get("callback_data") or "")
        for row in screen.keyboard.get("inline_keyboard", [])
        for item in row
        if isinstance(item, dict)
    ]


class PendingSite:
    def __init__(self, status: str = "pending") -> None:
        self.status = status

    def payment_status(self, _user_id: int, order_token: str) -> dict:
        return {
            "status": self.status,
            "orderToken": order_token,
            "amountRials": 500_000,
            "title": "خرید تست",
            "trackingRef": "",
        }


class CartHarness(CartAppWorkflows):
    def __init__(self, state: BotState, site_api: PendingSite) -> None:
        self.state = state
        self.site_api = site_api
        self.platform = "telegram"
        self.payment_return_v1_enabled = False
        self.owner_id = 1
        self.media_dispatcher = None

    def _screen(self, _name: str, _user_id: int) -> Screen:
        return Screen("خانه", {"inline_keyboard": []})


class BatchState:
    def __init__(self, count: int) -> None:
        self.sources = {
            index: {"id": index, "doc": f"doc-{index}"}
            for index in range(1, count + 1)
        }
        self.claim_limits: list[int] = []

    def protected_media_source(self, source_id: int) -> dict | None:
        return self.sources.get(int(source_id))

    def paid_file_asset_by_id(self, _source_id: int) -> None:
        return None

    def claim_booklet_request(
        self,
        _user_id: int,
        _document_id: str,
        *,
        now_epoch: int,
        window_seconds: int,
        max_requests: int,
        cooldown_seconds: int,
    ) -> str:
        del now_epoch, window_seconds, cooldown_seconds
        self.claim_limits.append(int(max_requests))
        return "claimed"


class CommerceCartTests(unittest.TestCase):
    def setUp(self) -> None:
        self.temp = tempfile.TemporaryDirectory()
        root = Path(self.temp.name)
        self.state = BotState(
            root / "telegram.sqlite3",
            payment_offers_path=root / "commerce.sqlite3",
        )
        identity = subscription_identity_from_directory(
            {"studentNumber": "40211272010", "name": "دانشجوی تست"}
        )
        assert identity is not None
        self.identity = identity

    def tearDown(self) -> None:
        self.state.close()
        self.temp.cleanup()

    def test_cart_identity_is_canonical_and_not_platform_scoped(self) -> None:
        first = commerce_identity_from_account(linked_account())
        second = commerce_identity_from_account(linked_account())
        self.assertIsNotNone(first)
        self.assertIsNotNone(second)
        self.assertEqual(first.subject_key, second.subject_key)
        self.assertTrue(first.subject_key.startswith("student:"))

    def test_cart_persistence_discount_and_mixed_activation_are_idempotent(self) -> None:
        now = datetime(2026, 10, 3, 8, 0, tzinfo=timezone.utc)
        period = billing_period_for(7, now)
        policy = self.state.term_access_policy(7)
        assert policy is not None

        generic = {"kind": "offer", "offerRef": "offer_generic_abcdefghijklmnop"}
        ai = {
            "kind": "ai_booklet",
            "offerRef": ai_booklet_offer_ref(7, "endo-1", 1),
            "term": 7,
            "courseCode": "endo-1",
            "courseTag": "اندو",
            "sessionNo": 1,
        }
        subscription = {
            "kind": "term_subscription",
            "offerRef": str(policy["offerRef"]),
            "term": 7,
            "billingPeriod": period.key,
        }
        for item in (generic, generic, ai, subscription):
            self.state.commerce_cart_add(
                subject_key=self.identity.subject_key,
                student_number=self.identity.student_number,
                display_name=self.identity.display_name,
                item=item,
            )
        cart = self.state.commerce_cart(self.identity.subject_key)
        self.assertEqual(len(cart["items"]), 3)

        discount = self.state.create_commerce_discount_code(
            kind="percent",
            amount=10,
            min_subtotal_rials=0,
            max_uses=2,
            actor_user_id=1,
            actor_platform="telegram",
        )
        self.state.commerce_cart_set_discount(
            self.identity.subject_key,
            str(discount["code"]),
        )
        self.assertEqual(
            self.state.commerce_cart(self.identity.subject_key)["discountCode"],
            discount["code"],
        )

        subscription_amount = int(policy["monthlyPriceRials"])
        checkout_items = [
            {
                "itemKey": cart_item_key(generic),
                "kind": "offer",
                "offerRef": generic["offerRef"],
                "title": "محصول عادی",
                "amountRials": 500_000,
                "fulfillment": {},
            },
            {
                "itemKey": cart_item_key(ai),
                **ai,
                "title": "جزوه هوش مصنوعی",
                "amountRials": AI_BOOKLET_PRICE_RIALS,
                "fulfillment": {},
            },
            {
                "itemKey": cart_item_key(subscription),
                **subscription,
                "title": "اشتراک جزوات",
                "amountRials": subscription_amount,
                "fulfillment": {
                    "kind": "term_subscription",
                    "term": 7,
                    "billingPeriod": period.key,
                },
            },
        ]
        subtotal = sum(int(item["amountRials"]) for item in checkout_items)
        order_token = "o" * 24
        self.state.record_commerce_cart_checkout(
            request_id="r" * 64,
            order_token=order_token,
            platform="telegram",
            platform_user_id=20,
            subject_key=self.identity.subject_key,
            student_number=self.identity.student_number,
            display_name=self.identity.display_name,
            items=checkout_items,
            subtotal_rials=subtotal,
            discount_code=str(discount["code"]),
            discount_amount_rials=100_000,
            amount_rials=subtotal - 100_000,
        )
        first = self.state.activate_commerce_cart_checkout(
            order_token=order_token,
            delivery_id="delivery-cart-1",
            platform="telegram",
            platform_user_id=20,
            amount_rials=subtotal - 100_000,
            verified_at="2026-10-03T08:00:00Z",
            payment_order_ref="ref-1",
        )
        second = self.state.activate_commerce_cart_checkout(
            order_token=order_token,
            delivery_id="delivery-cart-1",
            platform="telegram",
            platform_user_id=20,
            amount_rials=subtotal - 100_000,
            verified_at="2026-10-03T08:00:00Z",
            payment_order_ref="ref-1",
        )
        self.assertEqual(first["status"], "activated")
        self.assertEqual(second["status"], "activated")
        self.assertTrue(
            self.state.has_ai_booklet_access(
                self.identity.subject_key,
                term=7,
                course_code="endo-1",
                session_no=1,
            )
        )
        decision = self.state.term_access_decision(
            self.identity.subject_key,
            7,
            now=now,
        )
        self.assertTrue(decision["allowed"])
        self.assertEqual(
            self.state.commerce_cart(self.identity.subject_key)["items"],
            [],
        )
        ai_count = self.state.payment_connection.execute(
            "SELECT COUNT(*) FROM ai_booklet_entitlements WHERE subject_key=?",
            (self.identity.subject_key,),
        ).fetchone()[0]
        subscription_count = self.state.payment_connection.execute(
            "SELECT COUNT(*) FROM term_access_entitlements "
            "WHERE subject_key=? AND access_type='paid_subscription'",
            (self.identity.subject_key,),
        ).fetchone()[0]
        self.assertEqual(ai_count, 1)
        self.assertEqual(subscription_count, 1)

    def test_pending_checkout_blocks_cart_mutation(self) -> None:
        item = {"kind": "offer", "offerRef": "offer_pending_abcdefghijklmnop"}
        self.state.commerce_cart_add(
            subject_key=self.identity.subject_key,
            student_number=self.identity.student_number,
            display_name=self.identity.display_name,
            item=item,
        )
        self.state.record_commerce_cart_checkout(
            request_id="p" * 64,
            order_token="q" * 24,
            platform="telegram",
            platform_user_id=20,
            subject_key=self.identity.subject_key,
            student_number=self.identity.student_number,
            display_name=self.identity.display_name,
            items=[
                {
                    "itemKey": cart_item_key(item),
                    **item,
                    "title": "محصول",
                    "amountRials": 500_000,
                    "fulfillment": {},
                }
            ],
            subtotal_rials=500_000,
            discount_code="",
            discount_amount_rials=0,
            amount_rials=500_000,
        )
        app = CartHarness(self.state, PendingSite("pending"))
        blocked = app._cart_mutation_blocker(20, self.identity.subject_key)
        self.assertIsNotNone(blocked)
        self.assertIn("پرداخت برای این سبد در جریان است", str(blocked.text))
        app.site_api.status = "failed"
        self.assertIsNone(app._cart_mutation_blocker(20, self.identity.subject_key))

    def test_cart_ui_reuses_rich_table_and_home_is_conditional(self) -> None:
        payload = {
            "version": 4,
            "items": [
                {
                    "title": "جزوه هوش مصنوعی اندو",
                    "amountRials": 390_000,
                    "available": True,
                },
                {
                    "title": "اشتراک جزوات",
                    "amountRials": 1_500_000,
                    "available": True,
                },
            ],
            "subtotalRials": 1_890_000,
            "discountCode": "",
            "discountAmountRials": 0,
            "amountRials": 1_890_000,
        }
        screen = cart_screen(payload)
        self.assertIn("۲ محصول در سبد", str(screen.text))
        self.assertIn("<table bordered striped compact>", screen.text.rich_html)
        self.assertIn("v1:cart-checkout", callbacks(screen))

        empty_home = canonical_home_screen(is_owner=False, cart_count=0)
        full_home = canonical_home_screen(is_owner=False, cart_count=2)
        self.assertNotIn("v1:cart", callbacks(empty_home))
        self.assertIn("v1:cart", callbacks(full_home))
        self.assertIn("سبد خرید (۲)", str(full_home.keyboard))

        confirm = payment_confirm_screen(
            {
                "ref": "offer_generic_abcdefghijklmnop",
                "title": "محصول تست",
                "description": "توضیح",
                "amountRials": 500_000,
                "capacity": 0,
                "maxPurchasesPerUser": 1,
            },
            state={"successCount": 0, "reservedCount": 0},
        )
        self.assertIn(
            "v1:payment-create:offer_generic_abcdefghijklmnop",
            callbacks(confirm),
        )
        self.assertIn(
            "v1:cart-add:offer_generic_abcdefghijklmnop",
            callbacks(confirm),
        )

    def test_verified_batch_can_queue_more_than_interactive_rate_limit_in_order(self) -> None:
        fake_state = BatchState(30)
        dispatcher = object.__new__(ProtectedMediaDispatcher)
        dispatcher.state = fake_state
        dispatcher.authorize = lambda _user_id, _source: True
        dispatcher.queue = queue.Queue(maxsize=100)
        dispatcher._pending = set()
        dispatcher._lock = threading.Lock()
        dispatcher.rate_window_seconds = 60
        dispatcher.rate_max_requests = 12
        dispatcher.same_document_cooldown_seconds = 3
        dispatcher._document_id = lambda source: str(source["doc"])

        statuses = dispatcher.enqueue_batch(
            20,
            [("booklet", index) for index in range(1, 31)],
        )
        self.assertEqual(statuses, ["queued"] * 30)
        self.assertGreaterEqual(min(fake_state.claim_limits), 50)
        queued_ids = [dispatcher.queue.get_nowait().source_id for _ in range(30)]
        self.assertEqual(queued_ids, list(range(1, 31)))

    def test_crowded_cart_stays_compact_and_persian(self) -> None:
        screen = cart_screen(
            {
                "version": 9,
                "items": [
                    {
                        "title": f"محصول آموزشی شماره {index} با عنوان نسبتاً طولانی برای بررسی",
                        "amountRials": 100_000 + index * 10_000,
                        "available": True,
                    }
                    for index in range(1, 21)
                ],
                "subtotalRials": 4_110_000,
                "discountAmountRials": 0,
                "amountRials": 4_110_000,
            }
        )
        self.assertLess(len(str(screen.text)), 4096)
        self.assertLessEqual(len(screen.keyboard["inline_keyboard"]), 13)
        self.assertIn("شماره ۱۲", str(screen.text))
        self.assertNotIn("شماره 12", str(screen.text))

    def test_paid_file_prerequisites_are_rechecked_at_checkout(self) -> None:
        offer = self.state.create_payment_offer(
            "PDF شخصی",
            500_000,
            "تست",
            audience={"mode": "all"},
            paid_file_asset={
                "sourcePlatform": "telegram",
                "sourceChatId": 1,
                "sourceMessageId": 2,
                "telegramMethod": "sendDocument",
                "fileId": "paid-file-id",
                "fileUniqueId": "paid-file-unique",
                "fileName": "notes.pdf",
                "mimeType": "application/pdf",
                "fileSize": 12345,
                "mediaLabel": "فایل",
            },
        )
        descriptor = {"kind": "offer", "offerRef": str(offer["ref"])}
        self.state.commerce_cart_add(
            subject_key=self.identity.subject_key,
            student_number=self.identity.student_number,
            display_name=self.identity.display_name,
            item=descriptor,
        )

        class CheckoutHarness(CartHarness):
            def _cart_snapshot(self, _user_id: int, *, refresh_account: bool = False) -> dict:
                del refresh_account
                return {
                    "identity": self_identity,
                    "account": linked_account(),
                    "items": [
                        {
                            "itemKey": cart_item_key(descriptor),
                            "kind": "offer",
                            "offerRef": str(offer["ref"]),
                            "title": "PDF شخصی",
                            "amountRials": 500_000,
                            "productVersion": 1,
                            "availableFrom": "",
                            "expiresAt": "",
                            "capacity": 0,
                            "maxPurchasesPerUser": 1,
                            "fulfillment": dict(offer["fulfillment"]),
                            "available": True,
                            "reason": "",
                        }
                    ],
                    "version": 1,
                    "discountCode": "",
                    "discountValid": True,
                    "subtotalRials": 500_000,
                    "discountAmountRials": 0,
                    "amountRials": 500_000,
                }

            @staticmethod
            def _is_paid_file_offer(_offer: dict) -> bool:
                return True

            def _paid_file_purchase_blocker(self, _user_id: int, _offer: dict) -> Screen | None:
                return Screen("هویت واترمارک ناقص است", {"inline_keyboard": []})

        self_identity = self.identity
        app = CheckoutHarness(self.state, PendingSite())
        screen = app._cart_checkout_screen(20)
        self.assertIn("هویت واترمارک ناقص است", str(screen.text))

    def test_checkout_request_id_is_shared_across_linked_transports(self) -> None:
        items = [
            {
                "itemKey": "offer:offer_shared_abcdefghijklmnop",
                "kind": "offer",
                "offerRef": "offer_shared_abcdefghijklmnop",
                "title": "محصول مشترک",
                "amountRials": 500_000,
                "fulfillment": {},
            }
        ]
        first = cart_checkout_request_id(
            self.identity.subject_key,
            version=3,
            items=items,
            discount_code="DENTTEST",
        )
        second = cart_checkout_request_id(
            self.identity.subject_key,
            version=3,
            items=[dict(items[0])],
            discount_code="DENTTEST",
        )
        self.assertEqual(first, second)
        self.assertNotEqual(
            first,
            cart_checkout_request_id(
                self.identity.subject_key,
                version=4,
                items=items,
                discount_code="DENTTEST",
            ),
        )

    def test_verified_non_class_identity(self) -> None:
        account = {
            "linked": False,
            "authComplete": False,
            "onboardingProfileRef": "opaqueProfile1234567890",
            "onboardingProfile": {
                "firstName": "کاربر",
                "lastName": "آزمایشی",
                "studentNumber": "",
                "verifiedAt": "2026-10-03T08:00:00Z",
                "isClassMember": False,
            },
        }
        identity = commerce_identity_from_account(account)
        self.assertIsNotNone(identity)
        self.assertTrue(identity.subject_key.startswith("profile:"))
        self.assertEqual(identity.student_number, "")



if __name__ == "__main__":
    unittest.main()
