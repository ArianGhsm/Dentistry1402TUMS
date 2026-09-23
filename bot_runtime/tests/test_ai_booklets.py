from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from dent_bot.ai_booklets import (
    AI_BOOKLET_CONTENT_KIND,
    AI_BOOKLET_PRICE_RIALS,
    ai_booklet_offer_ref,
    ai_booklet_request_id,
)
from dent_bot.app import DentBotApp
from dent_bot.booklets import (
    ai_booklet_purchase_screen,
    resources_screen,
    parse_source_caption,
    source_records_from_channel_post,
)
from dent_bot.runtime import dispatch_payment_result_batch
from dent_bot.state import BotState


CATALOG = {
    "contractVersion": "term7-booklet-catalog-v1",
    "term": 7,
    "courses": [
        {
            "courseKey": "diagnostic-dentistry-3",
            "courseTitle": "دندانپزشکی تشخیصی ۳",
            "bookletTag": "تشخیصی۳",
            "bookletTagAliases": ["تشخیصی۳"],
            "term": 7,
            "sessions": [
                {
                    "sessionNumber": 1,
                    "title": "ضایعات اگزوفیتیک خارج استخوانی",
                    "instructor": "دکتر پورشهیدی",
                    "sessionModeLabel": "حضوری",
                },
                {
                    "sessionNumber": 2,
                    "title": "ضایعات اگزوفیتیک خارج استخوانی",
                    "instructor": "دکتر پورشهیدی",
                    "sessionModeLabel": "حضوری",
                },
            ],
        }
    ],
}


def ai_caption(session: str = "اول") -> str:
    return (
        f"🤖 جزوه هوش مصنوعی جلسه {session} دندانپزشکی تشخیصی ۳ - "
        "ضایعات اگزوفیتیک خارج استخوانی\n\n"
        "📚 دندانپزشکی تشخیصی ۳\n"
        "👨‍🏫 استاد پورشهیدی\n\n"
        "#تشخیصی۳ #ترم۷ #پورشهیدی #جزوه_هوش_مصنوعی\n\n"
        "▫️ @Dent1402Booklets"
    )


class Api:
    def __init__(self) -> None:
        self.sent = []

    def send(self, chat_id, text, keyboard):
        self.sent.append((chat_id, text, keyboard))
        return {"message_id": len(self.sent)}


class Site:
    def __init__(self) -> None:
        self.payments = []

    def account(self, _user_id):
        return {
            "success": True,
            "linked": True,
            "authComplete": True,
            "user": {"studentNumber": "40211272010", "name": "دانشجوی تست"},
            "onboardingProfile": {},
            "bookletProfile": {
                "freeSubscriptionEligible": True,
                "status": "member",
            },
        }

    def booklet_catalog(self, _user_id):
        return CATALOG

    def create_bot_payment(self, user_id, **payload):
        self.payments.append((user_id, dict(payload)))
        return {
            "success": True,
            "orderToken": "aiOrderToken123456789012345",
            "amountRials": payload["amount_rials"],
            "redirectUrl": "https://example.test/pay",
            "status": "pending",
        }


def register_ai_source(state: BotState, session_no: int = 1) -> None:
    state.replace_protected_media_message(
        -1003706539157,
        100 + session_no,
        [{
            "courseCode": "diagnostic-dentistry-3",
            "courseName": "دندانپزشکی تشخیصی ۳",
            "courseTag": "تشخیصی۳",
            "term": 7,
            "sessionNo": session_no,
            "contentKind": AI_BOOKLET_CONTENT_KIND,
            "telegramMethod": "sendDocument",
            "fileId": f"ai-file-{session_no}",
            "fileUniqueId": f"ai-unique-{session_no}",
            "fileName": f"ai-{session_no}.pdf",
            "mimeType": "application/pdf",
            "caption": ai_caption("اول" if session_no == 1 else "دوم"),
        }],
    )


class AiBookletTests(unittest.TestCase):
    def test_ai_marker_is_exact_and_never_routes_as_ordinary_booklet(self) -> None:
        parsed = parse_source_caption(ai_caption(), CATALOG)
        self.assertIsNotNone(parsed)
        assert parsed is not None
        self.assertEqual(parsed.kinds, (AI_BOOKLET_CONTENT_KIND,))

        records = source_records_from_channel_post(
            {
                "caption": ai_caption(),
                "document": {
                    "file_id": "ai-pdf",
                    "file_unique_id": "ai-pdf-unique",
                    "file_name": "جلسه اول تشخیصی ۳.pdf",
                    "mime_type": "application/pdf",
                },
            },
            CATALOG,
        )
        self.assertEqual([row["contentKind"] for row in records], [AI_BOOKLET_CONTENT_KIND])

        without_marker = ai_caption().replace(" #جزوه_هوش_مصنوعی", "")
        self.assertIsNone(parse_source_caption(without_marker, CATALOG))

    def test_ai_source_accepts_only_pdf(self) -> None:
        records = source_records_from_channel_post(
            {
                "caption": ai_caption(),
                "audio": {
                    "file_id": "not-a-pdf",
                    "file_unique_id": "audio",
                    "file_name": "note.m4a",
                    "mime_type": "audio/m4a",
                },
            },
            CATALOG,
        )
        self.assertEqual(records, [])

    def test_session_keyboard_keeps_four_existing_buttons_and_full_width_ai_row(self) -> None:
        screen = resources_screen(CATALOG, "diagnostic-dentistry-3", 1)
        rows = screen.keyboard["inline_keyboard"]
        self.assertEqual([item["text"] for item in rows[0]], ["🎤 ویس", "📒 پاور"])
        self.assertEqual([item["text"] for item in rows[1]], ["📓 جزوه", "📘 رفرنس"])
        self.assertEqual(len(rows[2]), 1)
        self.assertEqual(rows[2][0]["text"], "🤖 جزوه هوش مصنوعی")

    def test_purchase_screen_is_persian_and_session_specific(self) -> None:
        screen = ai_booklet_purchase_screen(CATALOG, "diagnostic-dentistry-3", 1)
        self.assertIn("۳۹٬۰۰۰ تومان", screen.text)
        self.assertIn("جلسه ۱", screen.text)
        self.assertIn("ضایعات اگزوفیتیک خارج استخوانی", screen.text)
        self.assertNotIn("Term", screen.text)

    def test_ai_entitlement_is_canonical_and_does_not_unlock_other_session(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                subject = "student:" + "a" * 64
                offer = ai_booklet_offer_ref(7, "diagnostic-dentistry-3", 1)
                request = ai_booklet_request_id(
                    platform="telegram",
                    platform_user_id=20,
                    subject_key=subject,
                    term=7,
                    course_code="diagnostic-dentistry-3",
                    session_no=1,
                )
                state.begin_ai_booklet_checkout(
                    request_id=request,
                    platform="telegram",
                    platform_user_id=20,
                    subject_key=subject,
                    student_number="40211272010",
                    display_name="دانشجوی تست",
                    term=7,
                    course_code="diagnostic-dentistry-3",
                    course_tag="تشخیصی۳",
                    session_no=1,
                    amount_rials=AI_BOOKLET_PRICE_RIALS,
                    offer_ref=offer,
                )
                token = "aiOrderToken123456789012345"
                state.bind_ai_booklet_order(request, token)
                state.activate_paid_ai_booklet(
                    order_token=token,
                    delivery_id="delivery-ai-1",
                    platform="telegram",
                    platform_user_id=20,
                    amount_rials=AI_BOOKLET_PRICE_RIALS,
                    verified_at="2026-09-23T12:00:00Z",
                )
                self.assertTrue(state.has_ai_booklet_access(
                    subject,
                    term=7,
                    course_code="diagnostic-dentistry-3",
                    session_no=1,
                ))
                self.assertFalse(state.has_ai_booklet_access(
                    subject,
                    term=7,
                    course_code="diagnostic-dentistry-3",
                    session_no=2,
                ))
                self.assertFalse(state.term_access_decision(subject, 7)["allowed"])
            finally:
                state.close()

    def test_unpublished_ai_booklet_is_visible_but_cannot_be_purchased(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            site = Site()
            app = DentBotApp(
                Api(),
                state,
                owner_id=99,
                site_url="https://example.test",
                site_api=site,
                platform="telegram",
            )
            try:
                screen = app._buy_ai_booklet_screen(
                    "booklet-ai-buy:diagnostic-dentistry-3:1",
                    20,
                    CATALOG,
                )
                self.assertIn("هنوز منتشر نشده است", screen.text)
                self.assertEqual(site.payments, [])
            finally:
                state.close()

    def test_normal_booklet_membership_still_requires_ai_purchase(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            register_ai_source(state)
            site = Site()
            app = DentBotApp(
                Api(),
                state,
                owner_id=99,
                site_url="https://example.test",
                site_api=site,
                platform="telegram",
            )
            try:
                screen = app._booklet_resource_screen(
                    "booklet-resource:diagnostic-dentistry-3:1:ai_booklet",
                    20,
                    CATALOG,
                )
                self.assertIn("هزینهٔ این جزوه", screen.text)
                self.assertIn("۳۹٬۰۰۰ تومان", screen.text)
            finally:
                state.close()

    def test_ai_buy_uses_fixed_price_and_binds_one_session_checkout(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            register_ai_source(state)
            site = Site()
            app = DentBotApp(
                Api(),
                state,
                owner_id=99,
                site_url="https://example.test",
                site_api=site,
                platform="telegram",
                payment_return_v1_enabled=True,
            )
            try:
                app._buy_ai_booklet_screen(
                    "booklet-ai-buy:diagnostic-dentistry-3:1",
                    20,
                    CATALOG,
                )
                self.assertEqual(len(site.payments), 1)
                self.assertEqual(site.payments[0][1]["amount_rials"], 390_000)
                checkout = state.ai_booklet_checkout_by_order("aiOrderToken123456789012345")
                self.assertIsNotNone(checkout)
                assert checkout is not None
                self.assertEqual(checkout["sessionNo"], 1)
                self.assertEqual(checkout["courseCode"], "diagnostic-dentistry-3")
            finally:
                state.close()

    def test_verified_payment_push_activates_and_exposes_direct_receive_button(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                subject = "student:" + "b" * 64
                offer = ai_booklet_offer_ref(7, "diagnostic-dentistry-3", 1)
                request = ai_booklet_request_id(
                    platform="telegram",
                    platform_user_id=20,
                    subject_key=subject,
                    term=7,
                    course_code="diagnostic-dentistry-3",
                    session_no=1,
                )
                state.begin_ai_booklet_checkout(
                    request_id=request,
                    platform="telegram",
                    platform_user_id=20,
                    subject_key=subject,
                    student_number="40211272010",
                    display_name="دانشجوی تست",
                    term=7,
                    course_code="diagnostic-dentistry-3",
                    course_tag="تشخیصی۳",
                    session_no=1,
                    amount_rials=AI_BOOKLET_PRICE_RIALS,
                    offer_ref=offer,
                )
                token = "aiOrderToken123456789012345"
                state.bind_ai_booklet_order(request, token)

                class Settings:
                    owner_id = 99
                    platform = "telegram"
                    payment_result_push_enabled = True
                    payment_result_batch_size = 10

                class DeliverySite:
                    def __init__(self) -> None:
                        self.acks = []

                    def claim_payment_result_deliveries(self, _owner_id, *, limit):
                        return {
                            "deliveries": [{
                                "deliveryId": "ai-payment-delivery-1",
                                "deliveryKind": "user",
                                "platform": "telegram",
                                "chatId": "20",
                                "order": {
                                    "orderToken": token,
                                    "status": "success",
                                    "title": "جزوه هوش مصنوعی",
                                    "amountRials": AI_BOOKLET_PRICE_RIALS,
                                    "verifiedAt": "2026-09-23T12:00:00Z",
                                    "trackingRef": "AI-1",
                                },
                            }]
                        }

                    def ack_payment_result_delivery(
                        self, owner_id, delivery_id, *, delivered, reason_code=""
                    ):
                        self.acks.append((owner_id, delivery_id, delivered, reason_code))

                api = Api()
                result = dispatch_payment_result_batch(
                    settings=Settings(),
                    api=api,
                    state=state,
                    site_api=DeliverySite(),
                )
                self.assertEqual(result["activated"], 1)
                self.assertTrue(state.has_ai_booklet_access(
                    subject,
                    term=7,
                    course_code="diagnostic-dentistry-3",
                    session_no=1,
                ))
                keyboard = api.sent[0][2]["inline_keyboard"]
                actions = [
                    item.get("callback_data", "")
                    for row in keyboard
                    for item in row
                ]
                self.assertIn(
                    "v1:booklet-ai-get:diagnostic-dentistry-3:1",
                    actions,
                )
            finally:
                state.close()


if __name__ == "__main__":
    unittest.main()
