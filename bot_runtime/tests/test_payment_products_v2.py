from __future__ import annotations

import sqlite3
import threading
import time
import tempfile
import unittest
from datetime import datetime, timedelta, timezone
from pathlib import Path

from dent_bot.app import DentBotApp
from dent_bot.payments import identity_from_account, product_eligibility
from dent_bot.state import BotState, _migrate_offer_quantity_schema
from dent_bot.ui import (
    home,
    payment_offers_screen,
    payment_offer_admin_detail_screen,
    payment_product_report_screen,
    payment_transaction_detail_screen,
    payment_transactions_screen,
)


class ApiStub:
    def __init__(self) -> None:
        self.sent: list[tuple[int, str, dict]] = []
        self.inline: list[dict] = []

    def send(self, chat_id, text, keyboard):
        self.sent.append((int(chat_id), str(text), keyboard))
        return {"message_id": len(self.sent)}

    def edit(self, chat_id, message_id, text, keyboard):
        self.sent.append((int(chat_id), str(text), keyboard))
        return {"message_id": message_id}

    def remove_reply_keyboard(self, chat_id, text=""):
        return {}

    def answer_callback(self, *_args, **_kwargs):
        return True

    def is_chat_member(self, *_args, **_kwargs):
        return True

    def answer_inline_query(self, query_id, results, **_kwargs):
        self.inline = list(results)
        return True


class SiteStub:
    def __init__(self, accounts=None, states=None) -> None:
        self.accounts = accounts or {}
        self.states = states or {}
        self.transaction_calls: list[dict] = []
        self.create_calls: list[dict] = []

    def account(self, user_id):
        return self.accounts.get(int(user_id), linked_account(str(user_id)))

    def payment_product_states(self, _user_id, refs):
        return {"states": {ref: dict(self.states.get(ref) or {}) for ref in refs}}

    def create_bot_payment(self, user_id, **fields):
        self.create_calls.append({"userId": int(user_id), **fields})
        return {
            "success": True,
            "orderToken": "order-token-123456789012345",
            "redirectUrl": "https://example.test/gateway/continue",
            "status": "pending",
        }

    def payment_owner_dashboard(self, _user_id):
        return {"today": {}, "week": {}, "total": {}}

    def payment_transactions(self, _user_id, **filters):
        self.transaction_calls.append(dict(filters))
        page = int(filters.get("page") or 0)
        limit = int(filters.get("limit") or 20)
        total = 150
        start = page * limit
        return {
            "items": [
                {
                    "orderId": index + 1,
                    "payerName": f"کاربر {index + 1}",
                    "title": "محصول تست",
                    "amountRials": 100000,
                    "status": "pending",
                    "statusLabel": "در انتظار",
                    "createdAt": "2026-08-29T08:00:00Z",
                }
                for index in range(start, min(start + limit, total))
            ],
            "total": total,
        }

    def payment_transaction(self, _user_id, *, order_id):
        return {"order": {"orderId": order_id, "status": "pending", "statusLabel": "در انتظار"}}


def linked_account(student="40211272010", cohort="dentistry-1402"):
    return {
        "linked": True,
        "authComplete": True,
        "user": {"studentNumber": student, "cohortKey": cohort, "name": "دانشجوی تست"},
    }


def verified_generic_account():
    return {
        "linked": False,
        "authComplete": False,
        "user": None,
        "onboardingProfile": {
            "firstName": "نیما",
            "lastName": "شرقی وند",
            "major": "پزشکی",
            "institution": "دانشگاه علوم پزشکی مشهد",
            "studentNumber": "",
            "verifiedAt": "2026-08-29T12:00:00+03:30",
            "isClassMember": False,
        },
    }


class PaymentProductsV2Tests(unittest.TestCase):
    def test_verified_non_class_user_can_open_and_create_deep_link_purchase(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = ApiStub()
            site = SiteStub(accounts={20: verified_generic_account()})
            try:
                item = state.create_payment_offer(
                    "ثبت‌نام عمومی",
                    250000,
                    "برای کاربران احراز هویت‌شده",
                    audience={"mode": "open"},
                )
                app = DentBotApp(api, state, owner_id=1, site_url="https://example.test", site_api=site)
                app.handle({"message": {
                    "text": f"/start product_{item['shareToken']}",
                    "from": {"id": 20},
                    "chat": {"id": 20, "type": "private"},
                }})
                self.assertIn("ثبت‌نام عمومی", api.sent[-1][1])
                self.assertNotIn("اتصال حساب", api.sent[-1][1])

                app.handle({"callback_query": {
                    "id": "callback-generic-purchase",
                    "from": {"id": 20},
                    "data": f"v1:payment-create-link:{item['shareToken']}",
                    "message": {"message_id": 10, "chat": {"id": 20, "type": "private"}},
                }})
                self.assertEqual(len(site.create_calls), 1)
                self.assertIn("gateway/continue", str(api.sent[-1][2]))
                self.assertNotIn("اتصال حساب", api.sent[-1][1])
            finally:
                state.close()

    def test_only_owner_and_private_payment_actions_require_canonical_link(self) -> None:
        for action in (
            "payment-confirm:offer",
            "payment-create:offer",
            "payment-create-link:token",
            "payment-status:order",
            "payments-page:1",
        ):
            self.assertFalse(DentBotApp._requires_canonical_link(action), action)
        for action in ("grades", "admin-payments", "payment-transaction:41"):
            self.assertTrue(DentBotApp._requires_canonical_link(action), action)

    def test_audience_and_lifecycle_are_backend_enforced(self) -> None:
        identity = identity_from_account(linked_account())
        now = datetime.now(timezone.utc)
        base = {"status": "active", "availableFrom": "", "expiresAt": ""}
        self.assertTrue(product_eligibility({**base, "audience": {"mode": "all"}}, identity).allowed)
        self.assertFalse(product_eligibility({**base, "audience": {"mode": "open"}}, identity).allowed)
        self.assertTrue(product_eligibility({**base, "audience": {"mode": "open"}}, identity, via_link=True).allowed)
        self.assertTrue(product_eligibility({**base, "audience": {"mode": "cohorts", "cohorts": ["dentistry-1402"]}}, identity).allowed)
        self.assertFalse(product_eligibility({**base, "audience": {"mode": "users", "studentNumbers": ["40200000000"]}}, identity).allowed)
        scheduled = {**base, "audience": {"mode": "all"}, "availableFrom": (now + timedelta(hours=1)).isoformat()}
        expired = {**base, "audience": {"mode": "all"}, "expiresAt": (now - timedelta(seconds=1)).isoformat()}
        self.assertEqual(product_eligibility(scheduled, identity).reason, "scheduled")
        self.assertEqual(product_eligibility(expired, identity).reason, "expired")

    def test_non_owner_menu_only_shows_products_when_purchasable(self) -> None:
        without = home("https://example.test", is_owner=False, has_products=False)
        with_product = home("https://example.test", is_owner=False, has_products=True)
        owner = home("https://example.test", is_owner=True, has_products=True)
        self.assertNotIn("پرداخت‌ها", str(without.keyboard))
        self.assertNotIn("محصولات", str(without.keyboard))
        self.assertIn("🛍 محصولات", str(with_product.keyboard))
        self.assertNotIn("🛍 محصولات", str(owner.keyboard))
        self.assertIn("admin-payments", str(owner.keyboard))

    def test_deep_link_denial_does_not_leak_title_or_price(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = ApiStub()
            try:
                item = state.create_payment_offer(
                    "عنوان خیلی محرمانه", 9876540,
                    audience={"mode": "users", "studentNumbers": ["40299999999"]},
                )
                app = DentBotApp(api, state, owner_id=1, site_url="https://example.test", site_api=SiteStub())
                app.handle({"message": {"text": f"/start product_{item['shareToken']}", "from": {"id": 20}, "chat": {"id": 20, "type": "private"}}})
                rendered = api.sent[-1][1]
                self.assertNotIn("عنوان خیلی محرمانه", rendered)
                self.assertNotIn("987", rendered)
                self.assertIn("در دسترس این حساب نیست", rendered)
            finally:
                state.close()

    def test_quick_command_stops_at_audience_preview(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = ApiStub()
            try:
                app = DentBotApp(api, state, owner_id=1, site_url="https://example.test", site_api=SiteStub())
                offers_before = {item["ref"] for item in state.payment_offers()}
                app.handle({"message": {"text": "/product 250000 آزمون جامع", "from": {"id": 1}, "chat": {"id": 1, "type": "private"}}})
                self.assertEqual({item["ref"] for item in state.payment_offers()}, offers_before)
                dialog = state.dialog(1)
                self.assertEqual(dialog["step"], "audience")
                self.assertEqual(dialog["payload"]["amountRials"], 2_500_000)
                self.assertIn("چه کسانی", api.sent[-1][1])
            finally:
                state.close()

    def test_paid_one_time_product_has_receipt_and_no_pay_button(self) -> None:
        offer = {"ref": "x" * 20, "title": "آزمون", "amountRials": 100000, "maxPurchasesPerUser": 1}
        screen = payment_offers_screen(
            [offer],
            states={offer["ref"]: {"successCount": 1, "latestSuccessOrderToken": "o" * 24}},
        )
        self.assertIn("پرداخت شده", screen.text)
        self.assertIn("payment-status", str(screen.keyboard))
        self.assertNotIn("payment-create", str(screen.keyboard))

    def test_owner_inline_query_uses_opaque_product_token(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = ApiStub()
            try:
                item = state.create_payment_offer("آزمون اینلاین", 300000)
                app = DentBotApp(api, state, owner_id=1, site_url="https://example.test", site_api=SiteStub())
                app.handle({"inline_query": {"id": "iq-1", "query": "اینلاین", "from": {"id": 1}}})
                self.assertEqual(len(api.inline), 1)
                url = api.inline[0]["reply_markup"]["inline_keyboard"][0][0]["url"]
                self.assertIn(f"product_{item['shareToken']}", url)
                self.assertNotIn(item["ref"], url)
            finally:
                state.close()

    def test_legacy_schema_migrates_without_losing_offer(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "legacy.sqlite3"
            connection = sqlite3.connect(path)
            connection.executescript(
                "CREATE TABLE payment_offers(id INTEGER PRIMARY KEY,ref TEXT UNIQUE,title TEXT,description TEXT,amount_rials INTEGER,status TEXT,created_at TEXT,updated_at TEXT);"
                "INSERT INTO payment_offers VALUES(1,'legacy-ref-123456789','قدیمی','شرح',100000,'inactive',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP);"
            )
            connection.commit()
            connection.close()
            state = BotState(path)
            try:
                item = state.payment_offer("legacy-ref-123456789", require_active=False)
                self.assertEqual(item["title"], "قدیمی")
                self.assertEqual(item["status"], "paused")
                self.assertGreaterEqual(len(item["shareToken"]), 16)
            finally:
                state.close()

    def test_transaction_ui_has_filters_pagination_and_details(self) -> None:
        screen = payment_transactions_screen(
            {
                "items": [{
                    "orderId": 41, "payerName": "دانشجو", "title": "محصول",
                    "amountRials": 200000, "status": "pending", "statusLabel": "در انتظار",
                    "createdAt": "2026-08-29T08:00:00Z",
                }],
                "total": 12,
            },
            page=0,
            filters={"status": "pending"},
        )
        rendered = str(screen.keyboard)
        self.assertIn("payment-transaction:41", rendered)
        self.assertIn("payment-transaction-filters", rendered)
        self.assertIn("payment-transactions-page:1", rendered)
        self.assertIn("payment-tx-clear", rendered)

    def test_verified_transaction_is_immutable_in_owner_ui(self) -> None:
        pending = payment_transaction_detail_screen({"order": {"orderId": 7, "status": "pending"}})
        verified = payment_transaction_detail_screen({"order": {"orderId": 8, "status": "success"}})
        self.assertIn("payment-transaction-status:7:canceled", str(pending.keyboard))
        self.assertNotIn("payment-transaction-status", str(verified.keyboard))

    def test_product_report_exposes_period_capacity_and_conversion_context(self) -> None:
        screen = payment_product_report_screen(
            {
                "ref": "p" * 20, "title": "محصول", "amountRials": 500000,
                "capacity": 10, "expiresAt": "2026-09-10T08:00:00Z", "createdAt": "2026-08-29T08:00:00Z",
            },
            {
                "counts": {"success": 3, "uniquePayers": 3, "pending": 2, "failed": 1},
                "targetCount": 8, "unpaidCount": 5, "receivedRials": 1500000, "pendingRials": 1000000,
            },
            period="7",
        )
        self.assertIn("ظرفیت باقی", screen.text)
        self.assertIn("۷ روز", screen.text)
        self.assertIn("payment-offer-stats", str(screen.keyboard))

    def test_filtered_export_walks_all_transaction_pages(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            site = SiteStub()
            try:
                app = DentBotApp(ApiStub(), state, owner_id=1, site_url="https://example.test", site_api=site)
                app._payment_filters[1] = {"status": "success", "platform": "telegram"}
                rows = app._all_payment_transactions(1, filtered=True)
                self.assertEqual(len(rows), 150)
                self.assertEqual([call["page"] for call in site.transaction_calls], [0, 1])
                self.assertTrue(all(call["status"] == "success" for call in site.transaction_calls))
            finally:
                state.close()

    def test_owner_product_actions_fit_callback_limit_and_offer_share_fallbacks(self) -> None:
        screen = payment_offer_admin_detail_screen({
            "ref": "r" * 24, "shareToken": "s" * 22, "title": "محصول",
            "amountRials": 200000, "status": "active", "effectiveStatus": "active",
            "audience": {"mode": "users", "studentNumbers": ["40211272010"]},
        }, bot_username="Dent1402Bot", platform="telegram")
        callbacks = [
            button["callback_data"]
            for row in screen.keyboard["inline_keyboard"]
            for button in row
            if "callback_data" in button
        ]
        self.assertTrue(all(len(value.encode("utf-8")) <= 64 for value in callbacks))
        self.assertIn("payment-product-share-preview", str(screen.keyboard))
        self.assertIn("switch_inline_query", str(screen.keyboard))


    def test_multi_quantity_wizard_checkout_and_copy_across_platforms(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            site = SiteStub()
            telegram_api = ApiStub()
            app = DentBotApp(telegram_api, state, owner_id=1, site_url="https://example.test", site_api=site)

            def callback(user: int, action: str) -> None:
                app.handle({"callback_query": {
                    "id": f"callback-{user}-{action}",
                    "from": {"id": user},
                    "data": f"v1:{action}",
                    "message": {"message_id": 10, "chat": {"id": user, "type": "private"}},
                }})

            try:
                callback(1, "payment-offer-new")
                app.handle({"message": {"text": "سه محصول آزمایشی", "from": {"id": 1},
                                        "chat": {"id": 1, "type": "private"}}})
                callback(1, "payment-offer-amount:100000")
                callback(1, "payment-offer-audience:all")
                self.assertEqual(state.dialog(1)["step"], "quantity-mode")
                self.assertIn("انتخاب تعداد هنگام خرید", str(telegram_api.sent[-1][2]))
                callback(1, "payment-offer-mode:multiple")
                self.assertIn("انتخاب تعداد", telegram_api.sent[-1][1])
                callback(1, "payment-offer-publish")
                offers = [item for item in state.payment_offers() if item["title"] == "سه محصول آزمایشی"]
                self.assertEqual(len(offers), 1)
                item = offers[0]
                self.assertTrue(item["allowMultiple"])
                self.assertEqual(item["maxPurchasesPerUser"], 0)
                self.assertIn("چندتایی", app._dynamic_screen(f"payment-offer:{item['ref']}", 1).text)

                callback(20, f"payment-confirm:{item['ref']}")
                self.assertIn("انتخاب تعداد و خرید", str(telegram_api.sent[-1][2]))
                callback(20, f"payment-quantity:{item['ref']}:3")
                self.assertIn("۳", telegram_api.sent[-1][1])
                self.assertIn("۳۰۰", telegram_api.sent[-1][1])
                self.assertIn(f"payment-create:{item['ref']}:3", str(telegram_api.sent[-1][2]))
                callback(20, f"payment-create:{item['ref']}:3")
                self.assertEqual(site.create_calls[-1]["quantity"], 3)
                self.assertTrue(site.create_calls[-1]["allow_multiple"])
                self.assertEqual(site.create_calls[-1]["amount_rials"], 1000000)

                callback(1, f"payment-offer-duplicate:{item['ref']}")
                copied = [offer for offer in state.payment_offers(include_inactive=True)
                          if offer["title"].startswith("کپی سه محصول")]
                self.assertEqual(len(copied), 1)
                self.assertTrue(copied[0]["allowMultiple"])
            finally:
                state.close()

    def test_shared_payment_offer_mode_and_quantity_picker_match_in_telegram_and_bale(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            shared = root / "shared-offers.sqlite3"
            telegram = BotState(root / "tg.sqlite3", payment_offers_path=shared)
            bale = BotState(root / "bale.sqlite3", payment_offers_path=shared)
            site = SiteStub()
            try:
                product = telegram.create_payment_offer("پک ابزار مشترک", 450000, allow_multiple=True)
                self.assertTrue(bale.payment_offer(product["ref"])["allowMultiple"])
                for platform, state in (("telegram", telegram), ("bale", bale)):
                    app = DentBotApp(ApiStub(), state, owner_id=1, site_url="https://example.test",
                                     site_api=site, platform=platform)
                    screen = app._dynamic_screen(f"payment-quantity:{product['ref']}:4", 20)
                    self.assertIn("۴", screen.text)
                    self.assertIn("۱۸۰٬۰۰۰ تومان", screen.text)
                    self.assertIn(f"payment-create:{product['ref']}:4", str(screen.keyboard))
                    app._dynamic_screen(f"payment-create:{product['ref']}:4", 20)
                    self.assertEqual(site.create_calls[-1]["quantity"], 4)
                    self.assertTrue(site.create_calls[-1]["allow_multiple"])
                bale.update_payment_offer(product["ref"], {"allowMultiple": False},
                                          actor_user_id=1, actor_platform="bale")
                self.assertFalse(telegram.payment_offer(product["ref"])["allowMultiple"])
            finally:
                telegram.close()
                bale.close()

    def test_multi_unit_receipt_and_owner_report_distinguish_orders_from_units(self) -> None:
        from dent_bot.ui import (
            payment_created_screen, payment_status_screen, payment_success_push_screen
        )
        order = {"orderToken": "a" * 25, "title": "پک", "status": "success",
                 "amountRials": 1_500_000, "quantity": 3,
                 "redirectUrl": "https://example.test/pay", "trackingRef": "۱۲۳"}
        for screen in (
            payment_created_screen(order),
            payment_status_screen(order),
            payment_success_push_screen(order),
        ):
            self.assertIn("تعداد: <b>۳</b>", screen.text)
        screen = payment_product_report_screen(
            {"ref": "p" * 24, "title": "پک", "amountRials": 500000, "capacity": 8},
            {"counts": {"success": 1, "successUnits": 3, "pending": 1,
                        "pendingUnits": 2, "uniquePayers": 1},
             "reservedUnits": 5, "receivedRials": 1500000},
        )
        self.assertIn("تعداد فروخته‌شده: <b>۳</b>", screen.text)
        self.assertIn("ظرفیت باقی‌مانده: <b>۳</b> از <b>۸</b>", screen.text)

    def test_multi_quantity_deep_link_purchase_stays_in_link_only_audience(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            site = SiteStub(accounts={20: verified_generic_account()})
            api = ApiStub()
            try:
                item = state.create_payment_offer("سه واحد با لینک", 125000,
                                                  audience={"mode": "open"}, allow_multiple=True)
                app = DentBotApp(api, state, owner_id=1, site_url="https://example.test", site_api=site)
                app.handle({"message": {"text": f"/start product_{item['shareToken']}",
                                        "from": {"id": 20},
                                        "chat": {"id": 20, "type": "private"}}})
                self.assertIn("payment-quantity-link", str(api.sent[-1][2]))
                action = f"payment-quantity-link:{item['shareToken']}:3"
                screen = app._dynamic_screen(action, 20)
                self.assertIn(f"payment-create-link:{item['shareToken']}:3", str(screen.keyboard))
                app._dynamic_screen(f"payment-create-link:{item['shareToken']}:3", 20)
                self.assertEqual(site.create_calls[-1]["quantity"], 3)
                self.assertTrue(site.create_calls[-1]["allow_multiple"])
            finally:
                state.close()

    def test_shared_quantity_migration_serializes_concurrent_startup(self) -> None:
        # Regression: Telegram and Bale can initialize the same shared offers
        # database while the first process is still adding the new column.
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "shared.sqlite3"
            first = sqlite3.connect(path, timeout=5, check_same_thread=False)
            second = sqlite3.connect(path, timeout=5, check_same_thread=False)
            try:
                first.execute("PRAGMA journal_mode=WAL")
                first.execute("CREATE TABLE payment_offers(id INTEGER PRIMARY KEY, title TEXT)")
                first.commit()
                first.execute("BEGIN IMMEDIATE")
                first.execute(
                    "ALTER TABLE payment_offers ADD COLUMN allow_multiple INTEGER NOT NULL DEFAULT 0"
                )
                started = threading.Event()
                errors: list[BaseException] = []

                def concurrent_migration() -> None:
                    started.set()
                    try:
                        _migrate_offer_quantity_schema(second)
                    except BaseException as error:
                        errors.append(error)

                worker = threading.Thread(target=concurrent_migration)
                worker.start()
                self.assertTrue(started.wait(timeout=2))
                time.sleep(0.04)
                first.commit()
                worker.join(timeout=3)
                self.assertFalse(worker.is_alive())
                self.assertEqual(errors, [])
                self.assertEqual(
                    sum(1 for row in second.execute("PRAGMA table_info(payment_offers)")
                        if row[1] == "allow_multiple"), 1
                )
            finally:
                first.close()
                second.close()

    def test_modern_offer_schema_adds_quantity_flag_without_changing_prior_rows(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "modern.sqlite3"
            connection = sqlite3.connect(path)
            connection.executescript("""
                CREATE TABLE payment_offers (
                    id INTEGER PRIMARY KEY, ref TEXT UNIQUE, share_token TEXT UNIQUE, title TEXT,
                    description TEXT, amount_rials INTEGER, status TEXT, audience_json TEXT,
                    available_from TEXT, expires_at TEXT, capacity INTEGER, max_per_user INTEGER,
                    fulfillment_json TEXT, version INTEGER, created_at TEXT, updated_at TEXT
                );
                INSERT INTO payment_offers VALUES (
                    1, 'modern-ref-abcdefghijkl', 'modern-token-abcd', 'کالای قدیمی', '',
                    100000, 'active', '{"mode":"all"}', '', '', 0, 1, '{}', 4,
                    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                );
            """)
            connection.commit()
            connection.close()
            state = BotState(path)
            try:
                item = state.payment_offer("modern-ref-abcdefghijkl")
                self.assertFalse(item["allowMultiple"])
                self.assertEqual(item["version"], 4)
                self.assertEqual(item["amountRials"], 100000)
                columns = {row[1] for row in state.payment_connection.execute("PRAGMA table_info(payment_offers)")}
                self.assertIn("allow_multiple", columns)
            finally:
                state.close()

    def test_single_quantity_rejects_modified_checkout_and_legacy_defaults(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            site = SiteStub()
            api = ApiStub()
            try:
                item = state.create_payment_offer("میزبان", 300000)
                self.assertFalse(item["allowMultiple"])
                self.assertEqual(item["maxPurchasesPerUser"], 1)
                app = DentBotApp(api, state, owner_id=1, site_url="https://example.test", site_api=site)
                self.assertNotIn("انتخاب تعداد", app._dynamic_screen(f"payment-confirm:{item['ref']}", 20).text)
                bad = app._dynamic_screen(f"payment-create:{item['ref']}:3", 20)
                self.assertIn("تعداد", bad.text)
                self.assertEqual(site.create_calls, [])
                good = app._dynamic_screen(f"payment-create:{item['ref']}", 20)
                self.assertIn("سفارش", good.text)
                self.assertEqual(site.create_calls[-1]["quantity"], 1)
                self.assertFalse(site.create_calls[-1]["allow_multiple"])
                changed = state.update_payment_offer(item["ref"], {"allowMultiple": True},
                                                     actor_user_id=1, actor_platform="telegram")
                self.assertTrue(changed["allowMultiple"])
                self.assertEqual(changed["maxPurchasesPerUser"], 0)
                changed = state.update_payment_offer(item["ref"], {"allowMultiple": False},
                                                     actor_user_id=1, actor_platform="telegram")
                self.assertFalse(changed["allowMultiple"])
                self.assertEqual(changed["maxPurchasesPerUser"], 1)
            finally:
                state.close()

    def test_quantity_picker_enforces_remaining_stock_and_customer_limit(self) -> None:
        from dent_bot.ui import payment_confirm_screen
        item = {"ref": "r" * 24, "title": "پک تجهیزات", "amountRials": 750000,
                "allowMultiple": True, "maxPurchasesPerUser": 5, "capacity": 8}
        state = {"successCount": 3, "reservedCount": 5}
        screen = payment_confirm_screen(item, state=state, quantity=20)
        self.assertIn("۲", screen.text)
        self.assertIn("۱۵۰٬۰۰۰", screen.text)
        self.assertIn("payment-create:" + item["ref"] + ":2", str(screen.keyboard))
        self.assertNotIn(":3", str(screen.keyboard))
        paid = payment_confirm_screen(item, state={"successCount": 5, "reservedCount": 5}, quantity=1)
        self.assertNotIn("payment-create", str(paid.keyboard))
        self.assertIn("سقف خرید", paid.text)

    def test_paid_file_cannot_be_switched_to_multi_quantity(self) -> None:
        # The underlying delivery is a single entitlement, not physical stock.
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                item = state.create_payment_offer(
                    "فایل آموزشی", 200000,
                    fulfillment={"kind": "paid_file", "action": "paid-file-get:" + "a" * 20},
                )
                # A real file sale is identified by its saved fulfillment kind.
                with self.assertRaises(ValueError):
                    state.update_payment_offer(item["ref"], {"allowMultiple": True},
                                               actor_user_id=1, actor_platform="telegram")
            finally:
                state.close()

if __name__ == "__main__":
    unittest.main()
