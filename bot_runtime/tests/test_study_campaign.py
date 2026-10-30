from __future__ import annotations

import copy
import tempfile
import unittest
from pathlib import Path

from dent_bot.app import DentBotApp
from dent_bot.state import BotState
from dent_bot.ui import button
from dent_bot.study_campaign import BUNDLES
from bot_runtime.tests.test_booklet_delivery import BOOKLET_CATALOG, FakeApi, LinkedSite


class CampaignSite(LinkedSite):
    def __init__(self, catalog):
        self.catalog = catalog
        self.submitted = []

    def booklet_catalog(self, _user_id):
        return self.catalog

    def payment_product_states(self, _user_id, _offer_refs):
        return {"states": {}}

    def booklet_watermark_identity(self, _user_id):
        return {"identity": {"fullName": "آزمون", "nationalCode": "0000000000",
                             "phoneNumber": "09120000000"}}

    def create_bot_cart_payment(self, _user_id, *, items, request_id, discount):
        self.submitted.append((copy.deepcopy(items), request_id, copy.deepcopy(discount)))
        subtotal = sum(int(item["amountRials"]) for item in items)
        value = int(discount.get("amount") or 0)
        return {"orderToken": "x" * 28, "amountRials": subtotal - value,
                "subtotalRials": subtotal, "discountAmountRials": value,
                "discountCode": discount.get("code", ""), "cartItems": items,
                "redirectUrl": "https://example.org/mock-payment"}

class StudyCampaignTests(unittest.TestCase):
    def build(self, with_paid_file: bool = True):
        sandbox = tempfile.TemporaryDirectory()
        self.addCleanup(sandbox.cleanup)
        state = BotState(Path(sandbox.name) / "state.sqlite3")
        self.addCleanup(state.close)
        catalog = copy.deepcopy(BOOKLET_CATALOG)
        endo = {"courseKey": "endodontics-basics-2", "term": 7,
                "courseTitle": "مبانی اندودانتیکس ۲", "bookletTag": "مبانی_اندودانتیکس۲",
                "sessions": [{"sessionNumber": 9, "title": "اپکس‌لوکیتورها"},
                             {"sessionNumber": 13, "title": "داروها"}]}
        catalog["courses"].append(endo)
        for number in [9, 13]:
            state.replace_protected_media_message(
                -1003706539157, 100 + number, [{
                    "courseCode": "endodontics-basics-2", "courseName": "مبانی اندودانتیکس ۲",
                    "courseTag": endo["bookletTag"], "term": 7, "sessionNo": number,
                    "contentKind": "ai_booklet", "telegramMethod": "sendDocument",
                    "fileId": f"endo-file-{number}", "fileUniqueId": f"unique-{number}",
                    "fileName": f"اندو {number}.pdf", "mimeType": "application/pdf",
                }]
            )
        pathology = next(c for c in catalog["courses"]
                         if c["courseKey"] == "pathology-practical-1")
        path_tag = pathology["bookletTag"]
        state.replace_protected_media_message(
            -1003706539157, 172, [{
                "courseCode": pathology["courseKey"], "courseName": pathology["courseTitle"],
                "courseTag": path_tag, "term": 7, "sessionNo": 1,
                "contentKind": "dentnote", "telegramMethod": "sendDocument",
                "fileId": "pathology-file", "fileUniqueId": "unique-dentnote",
                "fileName": "دنت‌نوت پاتولوژی عملی.pdf", "mimeType": "application/pdf",
            }]
        )
        if with_paid_file:
            state.create_payment_offer(
                "دنت‌نوت پاتولوژی عملی ۱", 390000,
                paid_file_asset={"sourcePlatform": "telegram", "sourceChatId": -1003706539157,
                                 "sourceMessageId": 172, "telegramMethod": "sendDocument",
                                 "fileId": "pathology-file", "fileUniqueId": "unique-dentnote",
                                 "fileName": "دنت‌نوت پاتولوژی عملی.pdf",
                                 "mimeType": "application/pdf", "fileSize": 20021119,
                                 "mediaLabel": "دنت‌نوت"},
                actor_user_id=10,
            )
        site = CampaignSite(catalog)
        app = DentBotApp(FakeApi(), state, owner_id=10,
                         site_url="https://example.org", site_api=site)
        return app, state, site, catalog

    def test_pair_discount_70k_and_backend_items(self):
        app, state, site, catalog = self.build()
        screen = app._study_bundle_checkout_screen("study-pack:endo", 20, catalog)
        self.assertIn("سفارش آماده پرداخت است", screen.text)
        items, _id, discount = site.submitted[-1]
        self.assertEqual([item["sessionNo"] for item in items], [9, 13])
        self.assertEqual(discount["amount"], 80000)
        self.assertEqual(sum(int(x["amountRials"]) for x in items) - discount["amount"], 700000)

    def test_three_file_discount_90k_and_single_dentnote_entitlement(self):
        app, state, site, catalog = self.build()
        screen = app._study_bundle_checkout_screen("study-pack:complete", 20, catalog)
        self.assertIn("سفارش آماده پرداخت است", screen.text)
        items, _id, discount = site.submitted[-1]
        self.assertEqual(len(items), 3)
        self.assertEqual([x["kind"] for x in items], ["ai_booklet", "ai_booklet", "offer"])
        self.assertEqual(discount["amount"], 270000)
        self.assertEqual(discount["code"], BUNDLES["complete"].discount_code)
        self.assertEqual(sum(int(x["amountRials"]) for x in items) - discount["amount"], 900000)

    def test_missing_file_offer_fails_closed_without_checkout(self):
        app, state, site, catalog = self.build(with_paid_file=False)
        screen = app._study_bundle_checkout_screen("study-pack:complete", 20, catalog)
        self.assertIn("آماده نیست", screen.text)
        self.assertEqual(site.submitted, [])

    def test_dentnote_source_without_paid_offer_is_not_delivered(self):
        app, _state, _site, catalog = self.build(with_paid_file=False)
        screen = app._booklet_resource_screen(
            "booklet-resource:pathology-practical-1:1:dentnote", 20, catalog
        )
        self.assertIn("هنوز فعال نشده", screen.text)
        self.assertNotIn("در صف امن قرار گرفت", screen.text)

    def test_one_dentnote_file_maps_to_nine_session_buttons_but_one_purchase(self):
        app, state, site, catalog = self.build()
        state.replace_protected_media_message(
                -1003706539157, 172, [
                    {
                        "courseCode": "pathology-practical-1",
                        "courseName": "آسیب‌شناسی عملی ۱",
                        "courseTag": "آسیب_شناسی_عملی۱",
                        "term": 7, "sessionNo": session,
                        "contentKind": "dentnote", "telegramMethod": "sendDocument",
                        "fileId": "pathology-file", "fileUniqueId": "unique-dentnote",
                        "fileName": "دنت‌نوت پاتولوژی عملی.pdf", "mimeType": "application/pdf",
                    }
                    for session in (1, 2, 3, 4, 5, 7, 8, 9, 10)
                ],
            )
        refs = set()
        for number in (1, 2, 3, 4, 5, 7, 8, 9, 10):
            sources = state.protected_media_for_tag(
                course_tag="آسیب_شناسی_عملی۱", term=7,
                session_no=number, content_kind="dentnote",
            )
            self.assertEqual(len(sources), 1)
            asset = state.paid_file_asset_for_source(sources[0])
            self.assertIsNotNone(asset)
            refs.add(asset["assetRef"])
        self.assertEqual(len(refs), 1)

    def test_dentnote_resource_routes_through_paid_offer(self):
        app, _state, _site, catalog = self.build()
        screen = app._booklet_resource_screen(
            "booklet-resource:pathology-practical-1:1:dentnote", 20, catalog
        )
        self.assertIn("۳۹", screen.text)
        actions = [cell.get("callback_data","")
                   for row in screen.keyboard.get("inline_keyboard",[]) for cell in row]
        self.assertTrue(any("payment-create:" in action for action in actions))


if __name__ == "__main__":
    unittest.main()
