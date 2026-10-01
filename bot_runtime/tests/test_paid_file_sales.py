from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from dent_bot.api import TelegramBotApi
from dent_bot.app import DentBotApp
from dent_bot.paid_files import extract_paid_file_source
from dent_bot.protected_media import ProtectedMediaDispatcher
from dent_bot.site_api import SiteApiError
from dent_bot.state import BotState
from dent_bot.ui import payment_confirm_screen, payment_status_screen


def linked_account(student: str = "40211272010") -> dict:
    return {
        "linked": True,
        "authComplete": True,
        "user": {
            "studentNumber": student,
            "cohortKey": "dentistry-1402",
            "name": "دانشجوی تست",
        },
    }


class ApiStub:
    def __init__(self) -> None:
        self.sent: list[tuple[int, str, dict]] = []
        self.edited: list[tuple[int, int, str, dict]] = []

    def send(self, chat_id, text, keyboard):
        self.sent.append((int(chat_id), str(text), keyboard))
        return {"message_id": len(self.sent)}

    def edit(self, chat_id, message_id, text, keyboard):
        self.edited.append((int(chat_id), int(message_id), str(text), keyboard))
        return {"message_id": int(message_id)}

    def answer_callback(self, *_args, **_kwargs):
        return True

    def remove_reply_keyboard(self, *_args, **_kwargs):
        return {}

    def is_chat_member(self, *_args, **_kwargs):
        return True


class SiteStub:
    def __init__(self, *, paid_refs: set[str] | None = None, watermark_ready: bool = True) -> None:
        self.paid_refs = set(paid_refs or ())
        self.watermark_ready = bool(watermark_ready)
        self.create_calls: list[dict] = []

    def account(self, user_id):
        return linked_account(str(40211272000 + int(user_id)))

    def booklet_watermark_identity(self, _user_id):
        if not self.watermark_ready:
            raise SiteApiError("هویت کامل نیست.", code="BOOKLET_IDENTITY_INCOMPLETE")
        return {"success": True, "identity": {"fullName": "کاربر تست"}}

    def payment_product_states(self, _user_id, refs):
        return {
            "states": {
                str(ref): {
                    "successCount": 1 if str(ref) in self.paid_refs else 0,
                    "reservedCount": 0,
                    "latestSuccessOrderToken": "o" * 24 if str(ref) in self.paid_refs else "",
                }
                for ref in refs
            }
        }

    def create_bot_payment(self, user_id, **fields):
        self.create_calls.append({"userId": int(user_id), **fields})
        return {
            "success": True,
            "orderToken": "o" * 24,
            "redirectUrl": "https://example.test/pay",
            "status": "pending",
        }


class DispatcherStub:
    def __init__(self) -> None:
        self.jobs: list[tuple[int, int]] = []

    def enqueue_paid_file(self, user_id: int, asset_id: int) -> str:
        self.jobs.append((int(user_id), int(asset_id)))
        return "queued"


def callback(user_id: int, name: str, message_id: int = 9) -> dict:
    return {
        "callback_query": {
            "id": f"cb-{name}",
            "from": {"id": int(user_id)},
            "data": f"v1:{name}",
            "message": {
                "message_id": int(message_id),
                "chat": {"id": int(user_id), "type": "private"},
            },
        }
    }


def private_message(user_id: int, *, text: str = "", **media) -> dict:
    message = {
        "message_id": 20,
        "from": {"id": int(user_id)},
        "chat": {"id": int(user_id), "type": "private"},
    }
    if text:
        message["text"] = text
    message.update(media)
    return {"message": message}


class PaidFileSalesTests(unittest.TestCase):
    def test_extracts_document_voice_video_and_photo_without_persisting_bytes(self) -> None:
        base = {"message_id": 5, "chat": {"id": 10, "type": "private"}}
        cases = [
            ({"document": {"file_id": "d1", "file_unique_id": "u1", "file_name": "notes.zip", "mime_type": "application/zip", "file_size": 123}}, "sendDocument"),
            ({"voice": {"file_id": "v1", "file_unique_id": "uv1", "mime_type": "audio/ogg", "file_size": 456}}, "sendVoice"),
            ({"video": {"file_id": "m1", "file_unique_id": "um1", "mime_type": "video/mp4", "file_size": 789}}, "sendVideo"),
            ({"photo": [{"file_id": "p-small", "file_size": 10}, {"file_id": "p-big", "file_unique_id": "up", "file_size": 20}]}, "sendPhoto"),
        ]
        for media, method in cases:
            with self.subTest(method=method):
                asset = extract_paid_file_source({**base, **media})
                self.assertIsNotNone(asset)
                assert asset is not None
                self.assertEqual(asset["telegramMethod"], method)
                self.assertNotIn("bytes", asset)
                self.assertNotIn("path", asset)

    def test_paid_file_offer_persists_only_telegram_metadata_and_fulfillment(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                item = state.create_payment_offer(
                    "جزوه تست",
                    250000,
                    "توضیح",
                    audience={"mode": "users", "studentNumbers": ["40211272010"]},
                    paid_file_asset={
                        "sourcePlatform": "telegram",
                        "sourceChatId": 1,
                        "sourceMessageId": 2,
                        "telegramMethod": "sendDocument",
                        "fileId": "telegram-file-id",
                        "fileUniqueId": "telegram-unique-id",
                        "fileName": "notes.pdf",
                        "mimeType": "application/pdf",
                        "fileSize": 12345,
                        "mediaLabel": "فایل",
                    },
                )
                fulfillment = dict(item["fulfillment"])
                self.assertEqual(fulfillment["kind"], "paid_file")
                self.assertTrue(str(fulfillment["action"]).startswith("paid-file-get:"))
                asset = state.paid_file_asset(str(fulfillment["assetRef"]))
                self.assertEqual(asset["fileId"], "telegram-file-id")
                self.assertEqual(asset["fileName"], "notes.pdf")
                columns = {
                    str(row[1])
                    for row in state.payment_connection.execute("PRAGMA table_info(paid_file_assets)")
                }
                self.assertFalse({"bytes", "blob", "local_path", "temporary_path"} & columns)
            finally:
                state.close()

    def test_owner_file_sale_wizard_reuses_payment_product_flow(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = ApiStub()
            site = SiteStub()
            app = DentBotApp(
                api,
                state,
                owner_id=1,
                site_url="https://example.test",
                site_api=site,
                platform="telegram",
                bot_username="Dent1402Bot",
            )
            try:
                app.handle(callback(1, "payment-file-new"))
                dialog = state.dialog(1)
                self.assertEqual(dialog["step"], "file")
                self.assertEqual(dialog["payload"]["saleType"], "file")
                self.assertIn("فروش فایل", api.edited[-1][2])

                app.handle(private_message(
                    1,
                    document={
                        "file_id": "source-file",
                        "file_unique_id": "source-unique",
                        "file_name": "pathology.pdf",
                        "mime_type": "application/pdf",
                        "file_size": 2 * 1024 * 1024,
                    },
                ))
                self.assertEqual(state.dialog(1)["step"], "title")

                app.handle(private_message(1, text="جزوه پاتولوژی"))
                self.assertEqual(state.dialog(1)["step"], "amount")
                app.handle(callback(1, "payment-offer-amount:100000"))
                self.assertEqual(state.dialog(1)["step"], "audience")
                app.handle(callback(1, "payment-offer-audience:primary"))
                self.assertEqual(state.dialog(1)["step"], "preview")
                self.assertIn("pathology.pdf", api.edited[-1][2])
                self.assertIn("۱۰۰٬۰۰۰ تومان", api.edited[-1][2])
                self.assertIn("۱ ورودی/گروه", api.edited[-1][2])

                app.handle(callback(1, "payment-offer-publish"))
                self.assertIsNone(state.dialog(1))
                offers = [
                    offer
                    for offer in state.payment_offers(include_inactive=True)
                    if str(dict(offer.get("fulfillment") or {}).get("kind") or "") == "paid_file"
                ]
                self.assertEqual(len(offers), 1)
                item = offers[0]
                self.assertEqual(item["audience"]["cohorts"], ["dentistry-1402"])
                self.assertEqual(item["fulfillment"]["kind"], "paid_file")
                self.assertIn("https://t.me/Dent1402Bot", str(api.edited[-1][3]))
                self.assertIn("فروش فایل ساخته شد", api.edited[-1][2])
            finally:
                state.close()

    def test_paid_file_is_hidden_on_bale_and_points_to_telegram(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                item = state.create_payment_offer(
                    "فایل تست",
                    100000,
                    audience={"mode": "all"},
                    paid_file_asset={
                        "sourcePlatform": "telegram",
                        "sourceChatId": 1,
                        "sourceMessageId": 2,
                        "telegramMethod": "sendVoice",
                        "fileId": "voice-id",
                        "fileName": "voice.ogg",
                        "mimeType": "audio/ogg",
                        "fileSize": 1024,
                        "mediaLabel": "پیام صوتی",
                    },
                )
                app = DentBotApp(
                    ApiStub(),
                    state,
                    owner_id=1,
                    site_url="https://example.test",
                    site_api=SiteStub(),
                    platform="bale",
                    bot_username="Dent1402Bot",
                )
                self.assertEqual(app._eligible_products(20), [])
                screen = app._paid_file_telegram_screen(item)
                self.assertIn("تلگرام", screen.text)
                self.assertNotIn("https://t.me/", str(screen.keyboard))
            finally:
                state.close()

    def test_paid_buyer_can_queue_file_and_unpaid_buyer_is_denied(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                item = state.create_payment_offer(
                    "فایل تست",
                    100000,
                    audience={"mode": "all"},
                    paid_file_asset={
                        "sourcePlatform": "telegram",
                        "sourceChatId": 1,
                        "sourceMessageId": 2,
                        "telegramMethod": "sendVoice",
                        "fileId": "voice-id",
                        "fileName": "voice.ogg",
                        "mimeType": "audio/ogg",
                        "fileSize": 1024,
                        "mediaLabel": "پیام صوتی",
                    },
                )
                asset_ref = str(item["fulfillment"]["assetRef"])
                asset = state.paid_file_asset(asset_ref)
                assert asset is not None

                dispatcher = DispatcherStub()
                paid_site = SiteStub(paid_refs={str(item["ref"])})
                paid_app = DentBotApp(
                    ApiStub(),
                    state,
                    owner_id=1,
                    site_url="https://example.test",
                    site_api=paid_site,
                    platform="telegram",
                    media_dispatcher=dispatcher,
                )
                paid_app.handle(callback(20, f"paid-file-get:{asset_ref}"))
                self.assertEqual(dispatcher.jobs, [(20, int(asset["id"]))])

                unpaid_api = ApiStub()
                unpaid_app = DentBotApp(
                    unpaid_api,
                    state,
                    owner_id=1,
                    site_url="https://example.test",
                    site_api=SiteStub(),
                    platform="telegram",
                    media_dispatcher=DispatcherStub(),
                )
                unpaid_app.handle(callback(21, f"paid-file-get:{asset_ref}"))
                self.assertIn("پرداخت معتبر", unpaid_api.edited[-1][2])
            finally:
                state.close()

    def test_pdf_purchase_is_blocked_before_gateway_when_watermark_identity_is_incomplete(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                item = state.create_payment_offer(
                    "PDF تست",
                    100000,
                    audience={"mode": "all"},
                    paid_file_asset={
                        "sourcePlatform": "telegram",
                        "sourceChatId": 1,
                        "sourceMessageId": 2,
                        "telegramMethod": "sendDocument",
                        "fileId": "pdf-file-id",
                        "fileName": "test.pdf",
                        "mimeType": "application/pdf",
                        "fileSize": 1024,
                        "mediaLabel": "فایل",
                    },
                )
                api = ApiStub()
                site = SiteStub(watermark_ready=False)
                app = DentBotApp(
                    api,
                    state,
                    owner_id=1,
                    site_url="https://example.test",
                    site_api=site,
                    platform="telegram",
                )
                app.handle(callback(20, f"payment-create:{item['ref']}"))
                self.assertEqual(site.create_calls, [])
                self.assertIn("کد ملی", api.edited[-1][2])
                self.assertIn("موبایل تأییدشده", api.edited[-1][2])
            finally:
                state.close()

    def test_bale_deep_link_does_not_create_paid_file_order(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                item = state.create_payment_offer(
                    "ویس تست",
                    100000,
                    audience={"mode": "all"},
                    paid_file_asset={
                        "sourcePlatform": "telegram",
                        "sourceChatId": 1,
                        "sourceMessageId": 2,
                        "telegramMethod": "sendVoice",
                        "fileId": "voice-id",
                        "fileName": "voice.ogg",
                        "mimeType": "audio/ogg",
                        "fileSize": 1024,
                        "mediaLabel": "پیام صوتی",
                    },
                )
                api = ApiStub()
                site = SiteStub()
                app = DentBotApp(
                    api,
                    state,
                    owner_id=1,
                    site_url="https://example.test",
                    site_api=site,
                    platform="bale",
                    bot_username="BaleBot",
                )
                app.handle(private_message(20, text=f"/start product_{item['shareToken']}"))
                self.assertEqual(site.create_calls, [])
                self.assertIn("تلگرام", api.sent[-1][1])
                self.assertNotIn("t.me/BaleBot", str(api.sent[-1][2]))
            finally:
                state.close()

    def test_paid_receipt_and_confirm_screen_offer_file_delivery(self) -> None:
        action = "paid-file-get:" + ("x" * 24)
        offer = {
            "ref": "r" * 24,
            "title": "جزوه",
            "amountRials": 100000,
            "maxPurchasesPerUser": 1,
            "fulfillment": {"kind": "paid_file", "action": action},
        }
        confirm = payment_confirm_screen(
            offer,
            state={"successCount": 1, "latestSuccessOrderToken": "o" * 24},
        )
        self.assertIn("دریافت فایل", str(confirm.keyboard))
        receipt = payment_status_screen(
            {
                "status": "success",
                "title": "جزوه",
                "amountRials": 100000,
                "trackingRef": "123",
                "fulfillment": {"kind": "paid_file", "action": action},
            },
            platform="telegram",
            order_token="o" * 24,
            return_to_bot_enabled=True,
        )
        self.assertIn("دریافت فایل", str(receipt.keyboard))

    def test_dispatcher_uses_paid_file_asset_file_id_for_protected_delivery(self) -> None:
        class ProtectedApi:
            def __init__(self) -> None:
                self.deliveries = []
                self.errors = []

            def send_protected_media(
                self,
                chat_id,
                source,
                *,
                personalized_file_id="",
                caption="",
            ):
                self.deliveries.append({
                    "chatId": int(chat_id),
                    "source": dict(source),
                    "fileId": str(personalized_file_id),
                    "caption": str(caption),
                })
                return {"message_id": 88, "voice": {"file_id": personalized_file_id}}

            def send(self, chat_id, text, keyboard):
                self.errors.append((chat_id, text, keyboard))
                return {"message_id": 89}

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            dispatcher = None
            try:
                item = state.create_payment_offer(
                    "ویس فروشی",
                    100000,
                    audience={"mode": "all"},
                    paid_file_asset={
                        "sourcePlatform": "telegram",
                        "sourceChatId": 1,
                        "sourceMessageId": 2,
                        "telegramMethod": "sendVoice",
                        "fileId": "paid-voice-file-id",
                        "fileUniqueId": "paid-voice-unique-id",
                        "fileName": "voice.ogg",
                        "mimeType": "audio/ogg",
                        "fileSize": 2048,
                        "mediaLabel": "پیام صوتی",
                    },
                )
                asset = state.paid_file_asset(str(item["fulfillment"]["assetRef"]))
                assert asset is not None
                api = ProtectedApi()
                dispatcher = ProtectedMediaDispatcher(
                    api=api,
                    state=state,
                    authorize=lambda _user, _source: True,
                    temp_root=Path(directory) / "jobs",
                    workers=1,
                    max_queue=4,
                )
                self.assertEqual(dispatcher.enqueue_paid_file(20, int(asset["id"])), "queued")
                dispatcher.queue.join()
                self.assertEqual(len(api.deliveries), 1)
                self.assertEqual(api.deliveries[0]["fileId"], "paid-voice-file-id")
                self.assertIn("ویس فروشی", api.deliveries[0]["caption"])
                self.assertEqual(api.errors, [])
                delivery = state.latest_protected_media_delivery(20, int(asset["id"]))
                self.assertEqual(delivery["status"], "sent")
            finally:
                if dispatcher is not None:
                    dispatcher.close()
                state.close()

    def test_transport_protects_native_paid_media_file_ids(self) -> None:
        class CapturingApi(TelegramBotApi):
            def __init__(self) -> None:
                self.calls = []

            def call(self, method, payload=None, *, timeout=8):
                self.calls.append((method, dict(payload or {}), timeout))
                return {"message_id": 99}

        api = CapturingApi()
        for method, field in (
            ("sendDocument", "document"),
            ("sendAudio", "audio"),
            ("sendVoice", "voice"),
            ("sendVideo", "video"),
            ("sendAnimation", "animation"),
            ("sendPhoto", "photo"),
            ("sendVideoNote", "video_note"),
            ("sendSticker", "sticker"),
        ):
            with self.subTest(method=method):
                api.send_protected_media(
                    20,
                    {"telegramMethod": method},
                    personalized_file_id=f"id-{method}",
                    caption="محافظت‌شده",
                )
                sent_method, payload, _ = api.calls[-1]
                self.assertEqual(sent_method, method)
                self.assertEqual(payload[field], f"id-{method}")
                self.assertIs(payload["protect_content"], True)
                if method in {"sendVideoNote", "sendSticker"}:
                    self.assertNotIn("caption", payload)


if __name__ == "__main__":
    unittest.main()
