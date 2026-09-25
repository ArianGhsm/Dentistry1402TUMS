from __future__ import annotations

import tempfile
import threading
import time
import unittest
import os
from pathlib import Path

from dent_bot.api import BaleBotApi, BotApiError, TelegramBotApi
from dent_bot.app import DentBotApp
from dent_bot.booklets import (
    RESOURCE_LABELS,
    ordinal,
    parse_session_number,
    parse_session_numbers,
    parse_source_caption,
    source_records_from_channel_post,
)
from dent_bot.booklet_source_admin import register_source_metadata, sync_existing_source_message
from dent_bot.booklet_sources import PRIVATE_SOURCE_CONTENT_KINDS, POWER_SOURCE_CONTENT_KINDS
from dent_bot.state import BotState
from dent_bot.protected_media import ProtectedMediaDispatcher, _protected_delivery_caption


SOURCE_CHAT_ID = -1003706539157
POWER_SOURCE_CHAT_ID = -1002016459508
SOURCE_CAPTION = (
    "📓 جزوه رفرنس جلسه چهارم ورودی ۱۳۹۹ - تومورهای سینوس\n\n"
    "📚 گوش و حلق و بینی\n👨‍🏫 استاد ایرانی\n\n"
    "#گوش_حلق_بینی #ایرانی #ترم۷ #ورودی_۱۳۹۹"
)


BOOKLET_CATALOG = {
    "contractVersion": "term7-booklet-catalog-v1",
    "term": 7,
    "courses": [
        {
            "courseKey": "ent",
            "courseTitle": "گوش و حلق و بینی",
            "bookletTag": "گوش_حلق_بینی",
            "bookletTagAliases": ["گوش_حلق_بینی", "گوش_حلق_و_بینی"],
            "term": 7,
            "sessions": [
                {"sessionNumber": 4, "title": "تومورهای سینوس", "instructor": "دکتر ایرانی", "sessionModeLabel": "حضوری"},
            ],
        },
        {
            "courseKey": "research-methods-2",
            "courseTitle": "روش تحقیق ۲",
            "bookletTag": "روش_تحقیق۲",
            "bookletTagAliases": ["روش_تحقیق۲", "روش_شناسی_تحقیق۲"],
            "term": 7,
            "sessions": [
                {"sessionNumber": 1, "title": "مقدمه و معرفی دوره و منابع", "instructor": "دکتر یونس‌پور", "sessionModeLabel": "حضوری"},
                {"sessionNumber": 2, "title": "جست‌وجوی منابع", "instructor": "دکتر یونس‌پور", "sessionModeLabel": "حضوری"},
            ],
        },
        {
            "courseKey": "oral-health-theory-2",
            "courseTitle": "سلامت دهان نظری ۲",
            "bookletTag": "سلامت_دهان_نظری۲",
            "bookletTagAliases": ["سلامت_دهان_نظری۲", "سلامت_نظری۲"],
            "term": 7,
            "sessions": [
                {"sessionNumber": 1, "title": "اپیدمیولوژی", "instructor": "دکتر سمانه رازقی", "sessionModeLabel": "حضوری"},
                {"sessionNumber": 2, "title": "دندانپزشکی مبتنی بر شواهد", "instructor": "دکتر رضا یزدانی", "sessionModeLabel": "حضوری"},
            ],
        },
    ],
}


class FakeApi:
    def __init__(self) -> None:
        self.sent = []
        self.edited = []
        self.answered = []
        self.reply_keyboards_removed = []

    def send(self, chat_id, text, keyboard):
        self.sent.append((chat_id, text, keyboard))
        return {"message_id": len(self.sent)}

    def edit(self, chat_id, message_id, text, keyboard):
        self.edited.append((chat_id, message_id, text, keyboard))
        return {"message_id": message_id}

    def answer_callback(self, callback_id, text="", *, show_alert=False):
        self.answered.append((callback_id, text, show_alert))
        return True

    def remove_reply_keyboard(self, chat_id):
        self.reply_keyboards_removed.append(chat_id)
        return {"message_id": 500}


class LinkedSite:
    def account(self, _user_id):
        return {
            "success": True,
            "linked": True,
            "authComplete": True,
            "user": {"studentNumber": "40211272010", "name": "دانشجوی تست"},
            "onboardingProfile": {},
        }

    def booklet_catalog(self, _user_id):
        return BOOKLET_CATALOG


class FreeBookletMemberSite:
    def account(self, _user_id):
        return {
            "success": True,
            "linked": True,
            "authComplete": True,
            "user": {"studentNumber": "40211272011", "name": "عضو جزوه‌نویسی"},
            "onboardingProfile": {},
            "bookletProfile": {
                "group": 15,
                "status": "member",
                "statusLabel": "عضو",
                "freeSubscriptionEligible": True,
                "specialRoles": [],
            },
        }

    def booklet_catalog(self, _user_id):
        return BOOKLET_CATALOG


class PaidBookletMemberSite:
    def account(self, _user_id):
        return {
            "success": True,
            "linked": True,
            "authComplete": True,
            "user": {"studentNumber": "40211272012", "name": "دانشجوی پرداختی"},
            "onboardingProfile": {},
            "bookletProfile": {
                "group": None,
                "status": "unassigned",
                "statusLabel": "بدون گروه",
                "freeSubscriptionEligible": False,
                "specialRoles": [],
            },
        }

    def booklet_catalog(self, _user_id):
        return BOOKLET_CATALOG


class Dispatcher:
    def __init__(self) -> None:
        self.jobs = []

    def enqueue(self, user_id, source_id):
        self.jobs.append((user_id, source_id))
        return "queued"


def message(user_id: int, text: str) -> dict:
    return {
        "message": {
            "message_id": 1,
            "text": text,
            "chat": {"id": user_id, "type": "private"},
            "from": {"id": user_id},
        }
    }


class BookletDeliveryTests(unittest.TestCase):
    def test_session_parser_supports_every_ordinal_from_one_to_forty(self) -> None:
        for number in range(1, 41):
            with self.subTest(number=number):
                self.assertEqual(parse_session_number(f"جزوه جلسه {ordinal(number)} - تست"), number)
                self.assertEqual(parse_session_number(f"جزوه جلسه {number} - تست"), number)
        self.assertIsNone(parse_session_number("جزوه جلسه چهل و یکم"))

    def test_multi_session_power_caption_routes_one_file_to_each_session(self) -> None:
        caption = (
            "📒 پاور جلسات اول و دوم روش تحقیق ۲ - مرور مباحث\n"
            "#روش_تحقیق۲ #ترم۷"
        )
        self.assertEqual(parse_session_numbers(caption), (1, 2))
        parsed = parse_source_caption(caption, BOOKLET_CATALOG)
        self.assertIsNotNone(parsed)
        assert parsed is not None
        self.assertEqual(parsed.session_nos, (1, 2))
        self.assertEqual(parsed.kinds, ("power",))
        records = source_records_from_channel_post(
            {
                "caption": caption,
                "document": {
                    "file_id": "power-file",
                    "file_unique_id": "power-unique",
                    "file_name": "جلسات ۱ و ۲.pdf",
                    "mime_type": "application/pdf",
                },
            },
            BOOKLET_CATALOG,
            allowed_kinds=POWER_SOURCE_CONTENT_KINDS,
        )
        self.assertEqual([item["sessionNo"] for item in records], [1, 2])
        self.assertEqual({item["contentKind"] for item in records}, {"power"})

    def test_source_policy_keeps_power_public_and_other_content_private(self) -> None:
        power_caption = "📒 پاور جلسه اول روش تحقیق ۲\n#روش_تحقیق۲ #ترم۷"
        power_message = {
            "caption": power_caption,
            "document": {"file_id": "power-file", "file_name": "power.pdf"},
        }
        self.assertEqual(
            source_records_from_channel_post(
                power_message,
                BOOKLET_CATALOG,
                allowed_kinds=PRIVATE_SOURCE_CONTENT_KINDS,
            ),
            [],
        )
        self.assertEqual(
            [item["contentKind"] for item in source_records_from_channel_post(
                power_message,
                BOOKLET_CATALOG,
                allowed_kinds=POWER_SOURCE_CONTENT_KINDS,
            )],
            ["power"],
        )

        booklet_message = {
            "caption": "📓 جزوه جلسه اول روش تحقیق ۲\n#روش_تحقیق۲ #ترم۷",
            "document": {"file_id": "booklet-file", "file_name": "booklet.pdf"},
        }
        self.assertEqual(
            source_records_from_channel_post(
                booklet_message,
                BOOKLET_CATALOG,
                allowed_kinds=POWER_SOURCE_CONTENT_KINDS,
            ),
            [],
        )
        self.assertEqual(
            [item["contentKind"] for item in source_records_from_channel_post(
                booklet_message,
                BOOKLET_CATALOG,
                allowed_kinds=PRIVATE_SOURCE_CONTENT_KINDS,
            )],
            ["booklet"],
        )

    def test_caption_routes_booklet_reference_to_both_sections(self) -> None:
        parsed = parse_source_caption(SOURCE_CAPTION, BOOKLET_CATALOG)
        self.assertIsNotNone(parsed)
        assert parsed is not None
        self.assertEqual(parsed.course_code, "ent")
        self.assertEqual(parsed.term, 7)
        self.assertEqual(parsed.session_no, 4)
        self.assertEqual(parsed.kinds, ("booklet", "reference"))
        records = source_records_from_channel_post({
            "caption": SOURCE_CAPTION,
            "document": {
                "file_id": "telegram-file-id",
                "file_unique_id": "unique-id",
                "file_name": "tumors-ent.pdf",
                "mime_type": "application/pdf",
            },
        }, BOOKLET_CATALOG)
        self.assertEqual({item["contentKind"] for item in records}, {"booklet", "reference"})
        self.assertTrue(all(item["telegramMethod"] == "sendDocument" for item in records))

    def test_global_booklet_tag_aliases_route_to_canonical_course(self) -> None:
        cases = (
            ("#گوش_حلق_و_بینی #ترم۷ ویس جلسه چهارم", "ent", "گوش_حلق_بینی", 4),
            ("#روش_شناسی_تحقیق۲ #ترم_۷ ویس جلسه اول", "research-methods-2", "روش_تحقیق۲", 1),
            ("#سلامت_نظری_۲ #ترم۷ ویس جلسه اول", "oral-health-theory-2", "سلامت_دهان_نظری۲", 1),
        )
        for caption, expected_code, expected_tag, expected_session in cases:
            with self.subTest(caption=caption):
                parsed = parse_source_caption(caption, BOOKLET_CATALOG)
                self.assertIsNotNone(parsed)
                assert parsed is not None
                self.assertEqual(parsed.course_code, expected_code)
                self.assertEqual(parsed.course_tag, expected_tag)
                self.assertEqual(parsed.session_no, expected_session)

    def test_booklet_tag_alias_collision_fails_closed(self) -> None:
        catalog = {
            "courses": [
                {
                    "courseKey": "course-a",
                    "courseTitle": "الف",
                    "bookletTag": "الف",
                    "bookletTagAliases": ["مشترک"],
                    "term": 7,
                    "sessions": [{"sessionNumber": 1}],
                },
                {
                    "courseKey": "course-b",
                    "courseTitle": "ب",
                    "bookletTag": "ب",
                    "bookletTagAliases": ["مشترک"],
                    "term": 7,
                    "sessions": [{"sessionNumber": 1}],
                },
            ]
        }
        self.assertIsNone(parse_source_caption("#مشترک #ترم۷ ویس جلسه اول", catalog))

    def test_persian_voice_caption_routes_against_shared_research_syllabus(self) -> None:
        caption = "🎤 ویس جلسه اول\n#روش_تحقیق۲ #ترم۷"
        parsed = parse_source_caption(caption, BOOKLET_CATALOG)
        self.assertIsNotNone(parsed)
        assert parsed is not None
        self.assertEqual(parsed.course_code, "research-methods-2")
        self.assertEqual(parsed.session_no, 1)
        self.assertEqual(parsed.kinds, ("voice",))
        records = source_records_from_channel_post({
            "caption": caption,
            "audio": {"file_id": "research-audio", "file_unique_id": "research-1"},
        }, BOOKLET_CATALOG)
        self.assertEqual(records[0]["telegramMethod"], "sendAudio")

    def test_register_source_metadata_routes_without_any_telegram_send(self) -> None:
        caption = (
            "🎤 ویس جلسه اول سلامت دهان نظری ۲ - اپیدمیولوژی\n"
            "#سلامت_دهان_نظری۲ #ترم۷"
        )
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                count = register_source_metadata(
                    state=state,
                    source_channel_id=SOURCE_CHAT_ID,
                    message_id=20,
                    catalog=BOOKLET_CATALOG,
                    caption=caption,
                    media_field="audio",
                    file_id="mtproto-packed-bot-file-id",
                    file_name="سلامت دهان نظری۲ ج۱.m4a",
                    mime_type="audio/m4a",
                )
                self.assertEqual(count, 1)
                rows = state.protected_media_for_tag(
                    course_tag="سلامت_دهان_نظری۲",
                    term=7,
                    session_no=1,
                    content_kind="voice",
                )
                self.assertEqual(len(rows), 1)
                self.assertEqual(rows[0]["sourceMessageId"], 20)
                self.assertEqual(rows[0]["fileId"], "mtproto-packed-bot-file-id")
                self.assertEqual(rows[0]["telegramMethod"], "sendAudio")
            finally:
                state.close()

    def test_sync_existing_recovers_caption_added_after_initial_channel_post(self) -> None:
        caption = (
            "🎤 ویس جلسه اول سلامت دهان نظری ۲ - اپیدمیولوژی (نسخه اول)\n\n"
            "📚 سلامت دهان نظری ۲\n👨‍🏫 استاد رازقی\n\n"
            "#سلامت_دهان_نظری۲ #ترم۷ #رازقی"
        )

        class SyncApi:
            def __init__(self) -> None:
                self.calls = []

            def call(self, method, payload=None, *, timeout=8):
                data = dict(payload or {})
                self.calls.append((method, data))
                if method == "forwardMessage":
                    return {
                        "message_id": 900,
                        "caption": caption,
                        "audio": {
                            "file_id": "oral-health-audio",
                            "file_unique_id": "oral-health-unique",
                            "file_name": "سلامت دهان نظری ۲ جلسه ۱.m4a",
                            "mime_type": "audio/m4a",
                        },
                    }
                if method == "deleteMessage":
                    return True
                raise AssertionError(f"Unexpected method: {method}")

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = SyncApi()
            try:
                # The original channel_post had no final caption, so ingestion
                # produced zero routes. Re-reading the same message after its
                # caption edit must create the route without re-uploading it.
                self.assertEqual(
                    state.replace_protected_media_message(SOURCE_CHAT_ID, 18, []),
                    0,
                )
                count = sync_existing_source_message(
                    api=api,
                    state=state,
                    source_channel_id=SOURCE_CHAT_ID,
                    owner_id=10,
                    message_id=18,
                    catalog=BOOKLET_CATALOG,
                )
                self.assertEqual(count, 1)
                rows = state.protected_media_for_tag(
                    course_tag="سلامت_دهان_نظری۲",
                    term=7,
                    session_no=1,
                    content_kind="voice",
                )
                self.assertEqual(len(rows), 1)
                self.assertEqual(rows[0]["sourceMessageId"], 18)
                self.assertEqual(rows[0]["fileId"], "oral-health-audio")
                self.assertEqual(rows[0]["telegramMethod"], "sendAudio")
                self.assertEqual(
                    [method for method, _payload in api.calls],
                    ["forwardMessage", "deleteMessage"],
                )
            finally:
                state.close()

    def test_sync_existing_can_route_captionless_album_member_with_shared_caption(self) -> None:
        shared_caption = "🎤 ویس جلسه اول\n#روش_تحقیق۲ #ترم۷"

        class AlbumApi:
            def __init__(self) -> None:
                self.calls = []

            def call(self, method, payload=None, *, timeout=8):
                self.calls.append((method, dict(payload or {})))
                if method == "forwardMessage":
                    return {
                        "message_id": 901,
                        "audio": {
                            "file_id": "album-part-one",
                            "file_unique_id": "album-part-one-unique",
                            "file_name": "روش تحقیق جلسه ۱ بخش اول.m4a",
                            "mime_type": "audio/m4a",
                        },
                    }
                if method == "deleteMessage":
                    return True
                raise AssertionError(f"Unexpected method: {method}")

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                count = sync_existing_source_message(
                    api=AlbumApi(),
                    state=state,
                    source_channel_id=SOURCE_CHAT_ID,
                    owner_id=10,
                    message_id=6,
                    catalog=BOOKLET_CATALOG,
                    caption_override=shared_caption,
                )
                self.assertEqual(count, 1)
                rows = state.protected_media_for_tag(
                    course_tag="روش_تحقیق۲",
                    term=7,
                    session_no=1,
                    content_kind="voice",
                )
                self.assertEqual(len(rows), 1)
                self.assertEqual(rows[0]["sourceMessageId"], 6)
                self.assertEqual(rows[0]["fileId"], "album-part-one")
            finally:
                state.close()

    def test_source_catalog_stores_only_metadata_and_edit_can_deactivate_routes(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                records = source_records_from_channel_post({
                    "caption": SOURCE_CAPTION,
                    "document": {"file_id": "file-id", "file_name": "test.pdf"},
                }, BOOKLET_CATALOG)
                self.assertEqual(state.replace_protected_media_message(SOURCE_CHAT_ID, 4, records), 2)
                booklet = state.protected_media_for_tag(
                    course_tag="گوش_حلق_بینی", term=7, session_no=4, content_kind="booklet"
                )
                self.assertEqual(len(booklet), 1)
                metadata_only = source_records_from_channel_post({
                    "caption": SOURCE_CAPTION,
                    "document": {"file_name": "test.pdf", "mime_type": "application/pdf"},
                }, BOOKLET_CATALOG)
                state.replace_protected_media_message(SOURCE_CHAT_ID, 4, metadata_only)
                preserved = state.protected_media_for_tag(
                    course_tag="گوش_حلق_بینی", term=7, session_no=4, content_kind="booklet"
                )
                self.assertEqual(preserved[0]["fileId"], "file-id")
                self.assertEqual(
                    state.update_protected_media_file(
                        SOURCE_CHAT_ID, 4, file_id="hydrated-id", file_unique_id="hydrated-unique"
                    ),
                    2,
                )
                hydrated = state.protected_media_for_tag(
                    course_tag="گوش_حلق_بینی", term=7, session_no=4, content_kind="booklet"
                )
                self.assertEqual(hydrated[0]["fileId"], "hydrated-id")
                columns = {
                    row[1] for row in state.connection.execute("PRAGMA table_info(protected_media_sources)")
                }
                self.assertFalse({"bytes", "blob", "local_path", "temporary_path"} & columns)
                state.replace_protected_media_message(SOURCE_CHAT_ID, 4, [])
                self.assertEqual(state.protected_media_for_tag(
                    course_tag="گوش_حلق_بینی", term=7, session_no=4, content_kind="booklet"
                ), [])
            finally:
                state.close()

    def test_issuance_attribution_survives_source_catalog_removal(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                foreign_keys = state.connection.execute(
                    "PRAGMA foreign_key_list(booklet_issuances)"
                ).fetchall()
                self.assertEqual(foreign_keys, [])
            finally:
                state.close()

    def test_only_exact_private_source_channel_updates_catalog(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                app = DentBotApp(
                    FakeApi(), state, owner_id=10, site_url="https://example.test",
                    site_api=LinkedSite(), booklet_source_channel_id=SOURCE_CHAT_ID,
                )
                post = {
                    "message_id": 4,
                    "chat": {"id": SOURCE_CHAT_ID, "type": "channel"},
                    "caption": SOURCE_CAPTION,
                    "document": {"file_id": "file-id", "file_name": "test.pdf"},
                }
                app.handle({"channel_post": dict(post)})
                self.assertEqual(len(state.protected_media_for_tag(
                    course_tag="گوش_حلق_بینی", term=7, session_no=4, content_kind="booklet"
                )), 1)
                post["message_id"] = 5
                post["chat"] = {"id": -1009999999999, "type": "channel"}
                app.handle({"channel_post": post})
                count = state.connection.execute("SELECT COUNT(*) FROM protected_media_sources").fetchone()[0]
                self.assertEqual(count, 2)
            finally:
                state.close()

    def test_public_power_source_routes_only_power_and_supports_multi_session(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                app = DentBotApp(
                    FakeApi(),
                    state,
                    owner_id=10,
                    site_url="https://example.test",
                    site_api=LinkedSite(),
                    booklet_source_channel_id=SOURCE_CHAT_ID,
                    power_source_channel_id=POWER_SOURCE_CHAT_ID,
                )
                power_post = {
                    "message_id": 41,
                    "chat": {"id": POWER_SOURCE_CHAT_ID, "type": "channel"},
                    "caption": (
                        "📒 پاور جلسات اول و دوم روش تحقیق ۲ - مرور مباحث\n"
                        "#روش_تحقیق۲ #ترم۷"
                    ),
                    "document": {"file_id": "public-power", "file_name": "power.pdf"},
                }
                app.handle({"channel_post": power_post})
                session_one = state.protected_media_for_tag(
                    course_tag="روش_تحقیق۲",
                    term=7,
                    session_no=1,
                    content_kind="power",
                )
                session_two = state.protected_media_for_tag(
                    course_tag="روش_تحقیق۲",
                    term=7,
                    session_no=2,
                    content_kind="power",
                )
                self.assertEqual(len(session_one), 1)
                self.assertEqual(len(session_two), 1)
                self.assertEqual(session_one[0]["sourceChatId"], POWER_SOURCE_CHAT_ID)
                self.assertEqual(session_two[0]["sourceMessageId"], 41)

                private_power = dict(power_post)
                private_power["message_id"] = 42
                private_power["chat"] = {"id": SOURCE_CHAT_ID, "type": "channel"}
                app.handle({"channel_post": private_power})
                self.assertEqual(
                    state.connection.execute(
                        "SELECT COUNT(*) FROM protected_media_sources "
                        "WHERE source_chat_id=? AND source_message_id=?",
                        (SOURCE_CHAT_ID, 42),
                    ).fetchone()[0],
                    0,
                )

                public_booklet = {
                    "message_id": 43,
                    "chat": {"id": POWER_SOURCE_CHAT_ID, "type": "channel"},
                    "caption": "📓 جزوه جلسه اول روش تحقیق ۲\n#روش_تحقیق۲ #ترم۷",
                    "document": {"file_id": "public-booklet", "file_name": "booklet.pdf"},
                }
                app.handle({"channel_post": public_booklet})
                self.assertEqual(
                    state.connection.execute(
                        "SELECT COUNT(*) FROM protected_media_sources "
                        "WHERE source_chat_id=? AND source_message_id=?",
                        (POWER_SOURCE_CHAT_ID, 43),
                    ).fetchone()[0],
                    0,
                )
            finally:
                state.close()

    def test_notes_flow_uses_inline_shared_syllabus_and_enqueues_registered_source(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            dispatcher = Dispatcher()
            try:
                records = source_records_from_channel_post({
                    "caption": SOURCE_CAPTION.replace("جزوه رفرنس", "جزوه"),
                    "document": {"file_id": "file-id", "file_name": "test.pdf"},
                }, BOOKLET_CATALOG)
                state.replace_protected_media_message(SOURCE_CHAT_ID, 4, records)
                app = DentBotApp(
                    api, state, owner_id=10, site_url="https://example.test",
                    site_api=LinkedSite(), media_dispatcher=dispatcher,
                )

                def callback(data: str) -> dict:
                    return {"callback_query": {
                        "id": data, "from": {"id": 10}, "data": f"v1:{data}",
                        "message": {"message_id": 7, "chat": {"id": 10, "type": "private"}},
                    }}

                app.handle(callback("notes"))
                self.assertIsNone(state.dialog(10))
                self.assertIn("inline_keyboard", api.edited[-1][3])
                course_labels = {
                    item["text"] for row in api.edited[-1][3]["inline_keyboard"] for item in row
                }
                self.assertTrue(any("روش تحقیق" in label for label in course_labels))
                self.assertTrue(any("گوش و حلق و بینی" in label for label in course_labels))

                app.handle(callback("booklet-course:research-methods-2"))
                session_labels = {
                    item["text"] for row in api.edited[-1][3]["inline_keyboard"] for item in row
                }
                self.assertTrue(any("۱ · مقدمه و معرفی دوره" in label for label in session_labels))

                app.handle(callback("booklet-course:ent"))
                app.handle(callback("booklet-session:ent:4"))
                resource_labels = {
                    item["text"] for row in api.edited[-1][3]["inline_keyboard"] for item in row
                }
                self.assertTrue(set(RESOURCE_LABELS.values()).issubset(resource_labels))

                app.handle(callback("booklet-resource:ent:4:booklet"))
                self.assertEqual(len(dispatcher.jobs), 1)
                self.assertIn("فایل در صف امن", api.edited[-1][2])
                self.assertIn("inline_keyboard", api.edited[-1][3])
                self.assertNotIn("keyboard", api.edited[-1][3])
            finally:
                state.close()

    def test_free_booklet_member_can_open_catalog_before_public_subscription_start(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            try:
                app = DentBotApp(
                    api,
                    state,
                    owner_id=99,
                    site_url="https://example.test",
                    site_api=FreeBookletMemberSite(),
                )
                app.handle({"callback_query": {
                    "id": "member-notes",
                    "from": {"id": 20},
                    "data": "v1:notes",
                    "message": {
                        "message_id": 7,
                        "chat": {"id": 20, "type": "private"},
                    },
                }})
                self.assertTrue(api.edited)
                self.assertIn("آرشیو امن جزوات", api.edited[-1][2])
                self.assertNotIn("دسترسی عمومی جزوات تا شروع دوره بسته است", api.edited[-1][2])
                labels = {
                    item["text"]
                    for row in api.edited[-1][3]["inline_keyboard"]
                    for item in row
                }
                self.assertTrue(any("روش تحقیق" in label for label in labels))

                source = {
                    "term": 7,
                    "courseTag": "روش_تحقیق۲",
                }
                self.assertTrue(app.booklet_access_allowed(20, source))
            finally:
                state.close()

    def test_paid_student_remains_blocked_before_public_subscription_start(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            try:
                app = DentBotApp(
                    api,
                    state,
                    owner_id=99,
                    site_url="https://example.test",
                    site_api=PaidBookletMemberSite(),
                )
                app.handle({"callback_query": {
                    "id": "paid-notes",
                    "from": {"id": 21},
                    "data": "v1:notes",
                    "message": {
                        "message_id": 7,
                        "chat": {"id": 21, "type": "private"},
                    },
                }})
                self.assertTrue(api.edited)
                self.assertIn("آرشیو امن جزوات", api.edited[-1][2])

                app.handle({"callback_query": {
                    "id": "paid-regular-booklet",
                    "from": {"id": 21},
                    "data": "v1:booklet-resource:ent:4:booklet",
                    "message": {
                        "message_id": 7,
                        "chat": {"id": 21, "type": "private"},
                    },
                }})
                self.assertIn("دسترسی جزوات ترم ۷", api.edited[-1][2])
                self.assertFalse(app.booklet_access_allowed(
                    21,
                    {"term": 7, "courseTag": "روش_تحقیق۲"},
                ))
            finally:
                state.close()

    def test_owner_booklet_callbacks_bypass_membership_and_account_gates(self) -> None:
        class OwnerApi(FakeApi):
            @staticmethod
            def is_chat_member(_channel, _user_id):
                raise AssertionError("Booklets owner callback must bypass membership gate")

        class CatalogOnlySite:
            @staticmethod
            def account(_user_id):
                raise AssertionError("Booklets owner callback must bypass account gate")

            @staticmethod
            def booklet_catalog(_user_id):
                return BOOKLET_CATALOG

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = OwnerApi()
            try:
                app = DentBotApp(
                    api, state, owner_id=10, site_url="https://example.test",
                    site_api=CatalogOnlySite(), required_channel_username="Dent1402Booklets",
                )
                app.handle({"callback_query": {
                    "id": "owner-notes", "from": {"id": 10}, "data": "v1:notes",
                    "message": {"message_id": 7, "chat": {"id": 10, "type": "private"}},
                }})
                self.assertTrue(api.edited)
                self.assertIn("آرشیو امن جزوات", api.edited[-1][2])
                self.assertIn("inline_keyboard", api.edited[-1][3])
            finally:
                state.close()

    def test_booklet_delivery_caption_preserves_private_channel_caption(self) -> None:
        for kind in ("booklet", "ai_booklet"):
            caption = _protected_delivery_caption(
                {"contentKind": kind, "caption": SOURCE_CAPTION},
                "TRC-ABCDE-FGHIJ",
            )
            self.assertIn(SOURCE_CAPTION, caption)
            self.assertIn("🔐 نسخهٔ شخصی‌سازی‌شده", caption)
            self.assertIn("TRC-ABCDE-FGHIJ", caption)

        reference_caption = _protected_delivery_caption(
            {"contentKind": "reference", "caption": SOURCE_CAPTION},
            "TRC-ABCDE-FGHIJ",
        )
        self.assertNotIn(SOURCE_CAPTION, reference_caption)
        self.assertIn("🔐 نسخهٔ شخصی‌سازی‌شده", reference_caption)

        escaped_caption = _protected_delivery_caption(
            {"contentKind": "booklet", "caption": "جلسه <۱> & نکته"},
            "TRC-ABCDE-FGHIJ",
        )
        self.assertIn("جلسه &lt;۱&gt; &amp; نکته", escaped_caption)

        long_caption = _protected_delivery_caption(
            {"contentKind": "booklet", "caption": "الف" * 1400},
            "TRC-ABCDE-FGHIJ",
        )
        self.assertLess(len(long_caption), 1024)
        self.assertIn("…\n\n🔐 نسخهٔ شخصی‌سازی‌شده", long_caption)

    def test_transport_always_protects_source_copy_and_personalized_file_id(self) -> None:
        class CapturingApi(TelegramBotApi):
            def __init__(self) -> None:
                self.calls = []

            def call(self, method, payload=None, *, timeout=8):
                self.calls.append((method, dict(payload or {}), timeout))
                return {"message_id": 9}

        api = CapturingApi()
        source = {
            "sourceChatId": SOURCE_CHAT_ID,
            "sourceMessageId": 4,
            "telegramMethod": "sendDocument",
        }
        api.send_protected_media(20, source)
        self.assertEqual(api.calls[-1][0], "copyMessage")
        self.assertIs(api.calls[-1][1]["protect_content"], True)
        self.assertNotIn("file", str(api.calls[-1][1]).lower())

        api.send_protected_media(
            20,
            source,
            caption="کپشن منبع\n@Dent1402Booklets\n\n🔐 فایل محافظت‌شده",
        )
        self.assertEqual(api.calls[-1][0], "copyMessage")
        self.assertIn("کپشن منبع", api.calls[-1][1]["caption"])
        self.assertIn("@Dent1402Booklets", api.calls[-1][1]["caption"])
        self.assertNotIn("@Dent۱۴۰۲Booklets", api.calls[-1][1]["caption"])
        self.assertIs(api.calls[-1][1]["protect_content"], True)
        for method, field in (
            ("sendDocument", "document"),
            ("sendAudio", "audio"),
            ("sendVoice", "voice"),
        ):
            routed = dict(source)
            routed["telegramMethod"] = method
            api.send_protected_media(20, routed, personalized_file_id=f"personal-{field}-id")
            self.assertEqual(api.calls[-1][0], method)
            self.assertEqual(api.calls[-1][1][field], f"personal-{field}-id")
            self.assertIs(api.calls[-1][1]["protect_content"], True)

        bale = object.__new__(BaleBotApi)
        with self.assertRaises(BotApiError):
            bale.send_protected_media(20, source)

    def test_initial_personalized_upload_sets_multipart_protect_content(self) -> None:
        class MultipartTransport:
            def post_multipart_file(self, path, **kwargs):
                self.path = path
                self.kwargs = kwargs
                return 200, b'{"ok":true,"result":{"message_id":11}}'

        with tempfile.TemporaryDirectory() as directory:
            document = Path(directory) / "personalized.pdf"
            document.write_bytes(b"%PDF-1.4\n%%EOF\n")
            api = object.__new__(TelegramBotApi)
            api._path = "/bot-test"
            api._transport = MultipartTransport()
            result = api.send_protected_document_path(20, document)
            self.assertEqual(result["message_id"], 11)
            self.assertEqual(api._transport.kwargs["fields"]["protect_content"], "true")
            self.assertEqual(api._transport.kwargs["content_type"], "application/pdf")

    def test_legacy_booklet_reply_dialog_is_migrated_to_inline_ui(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            try:
                app = DentBotApp(
                    api, state, owner_id=10, site_url="https://example.test",
                    site_api=LinkedSite(),
                )
                state.start_dialog(10, "booklets-v1", "session", {"courseCode": "ENT"})
                state.mark_reply_keyboard_active(10)
                app.handle(message(10, "دکمهٔ قدیمی"))
                self.assertIsNone(state.dialog(10))
                self.assertEqual(api.reply_keyboards_removed, [10])
                self.assertIn("inline_keyboard", api.sent[-1][2])
                self.assertNotIn("keyboard", api.sent[-1][2])
                self.assertIn("آرشیو امن جزوات", api.sent[-1][1])
            finally:
                state.close()

    @unittest.skipUnless(__import__("importlib").util.find_spec("pymupdf"), "PyMuPDF unavailable")
    def test_pdf_dispatcher_personalizes_once_caches_file_id_and_cleans_temp(self) -> None:
        import shutil
        import pymupdf

        class PdfApi:
            def __init__(self, source_path: Path) -> None:
                self.source_path = source_path
                self.downloads = 0
                self.uploads = 0
                self.cached_sends = []
                self.sent = []

            def download_file(self, _file_id, destination, *, max_bytes):
                self.downloads += 1
                shutil.copyfile(self.source_path, destination)
                return {"bytes": destination.stat().st_size}

            def send_protected_document_path(self, chat_id, document_path, *, caption, filename):
                self.uploads += 1
                self.assert_path = document_path
                self.assert_bytes = document_path.stat().st_size
                self.assert_filename = filename
                self.upload_caption = caption
                return {
                    "message_id": 700 + self.uploads,
                    "document": {"file_id": "personalized-file", "file_unique_id": "personalized-unique"},
                }

            def send_protected_media(self, chat_id, source, *, personalized_file_id="", caption=""):
                self.cached_sends.append((personalized_file_id, caption))
                return {"message_id": 800 + len(self.cached_sends)}

            def send(self, chat_id, text, keyboard):
                self.sent.append((chat_id, text, keyboard))
                return {"message_id": 900}

        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source_pdf = root / "source.pdf"
            document = pymupdf.open()
            page = document.new_page(width=595, height=842)
            page.insert_text((72, 100), "Protected delivery fixture")
            document.save(source_pdf)
            document.close()
            state = BotState(root / "state.sqlite3")
            dispatcher = None
            try:
                state.replace_protected_media_message(SOURCE_CHAT_ID, 9, [{
                    "contentKind": "booklet", "courseCode": "ENT", "courseName": "ENT",
                    "courseTag": "ent", "term": 7, "sessionNo": 4,
                    "telegramMethod": "sendDocument", "fileId": "source-file-id",
                    "fileUniqueId": "source-unique-id", "fileName": "source.pdf",
                    "mimeType": "application/pdf", "caption": SOURCE_CAPTION,
                }])
                source_id = int(state.protected_media_for(
                    course_code="ENT", term=7, session_no=4, content_kind="booklet"
                )[0]["id"])
                font = next(path for path in (
                    Path("dent_bot/assets/fonts/B_Nazanin_Bold.ttf"),
                    Path("C:/Windows/Fonts/tahoma.ttf"),
                    Path("/usr/share/fonts/truetype/noto/NotoNaskhArabic-Regular.ttf"),
                    Path("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"),
                ) if path.is_file())
                api = PdfApi(source_pdf)
                temp_root = root / "jobs"
                dispatcher = ProtectedMediaDispatcher(
                    api=api,
                    state=state,
                    authorize=lambda _user, _source: True,
                    identity_provider=lambda _user: {"identity": {
                        "fullName": "کاربر آزمایشی", "nationalCode": "0012345678",
                        "phoneNumber": "09123456789",
                    }},
                    fingerprint_key=b"k" * 32,
                    watermark_font=font,
                    temp_root=temp_root,
                    qpdf_binary="/usr/bin/qpdf",
                    workers=1,
                    max_queue=24,
                    same_document_cooldown_seconds=1,
                )
                self.assertEqual(dispatcher.enqueue(20, source_id), "queued")
                dispatcher.queue.join()
                self.assertEqual(api.downloads, 1)
                self.assertEqual(api.uploads, 1)
                self.assertGreater(api.assert_bytes, source_pdf.stat().st_size)
                self.assertEqual(api.assert_filename, "dent1402-personalized.pdf")
                self.assertIn(SOURCE_CAPTION, api.upload_caption)
                self.assertIn("🔐 نسخهٔ شخصی‌سازی‌شده", api.upload_caption)
                self.assertIn("کد رهگیری:", api.upload_caption)
                self.assertEqual(list(temp_root.glob("job-*")), [])
                candidates = state.forensic_booklet_candidates()
                self.assertEqual(len(candidates), 1)
                self.assertEqual(candidates[0]["telegramFileId"], "personalized-file")
                time.sleep(1.05)
                self.assertEqual(dispatcher.enqueue(20, source_id), "queued")
                dispatcher.queue.join()
                self.assertEqual(api.downloads, 1)
                self.assertEqual(api.uploads, 1)
                self.assertEqual(api.cached_sends[0][0], "personalized-file")
                self.assertIn(SOURCE_CAPTION, api.cached_sends[0][1])
                self.assertIn("🔐 نسخهٔ شخصی‌سازی‌شده", api.cached_sends[0][1])
                self.assertIn("کد رهگیری:", api.cached_sends[0][1])
            finally:
                if dispatcher is not None:
                    dispatcher.close()
                state.close()

    def test_dispatcher_accepts_twenty_simultaneous_requests_without_spawning_heavy_workers(self) -> None:
        class QueueApi:
            def __init__(self) -> None:
                self.count = 0

            def send_protected_media(self, _chat_id, _source, *, personalized_file_id=""):
                gate.wait(3)
                self.count += 1
                return {"message_id": self.count}

            def send(self, *_args):
                return {"message_id": 999}

        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            state = BotState(root / "state.sqlite3")
            dispatcher = None
            gate = threading.Event()
            try:
                state.replace_protected_media_message(SOURCE_CHAT_ID, 11, [{
                    "contentKind": "voice", "courseCode": "ENT", "courseName": "ENT",
                    "courseTag": "ent", "term": 7, "sessionNo": 4,
                    "telegramMethod": "sendVoice", "fileId": "voice-id",
                    "fileUniqueId": "voice-unique", "fileName": "voice.ogg",
                    "mimeType": "audio/ogg", "caption": "ویس جلسه چهارم #گوش_حلق_بینی #ترم۷",
                }])
                source_id = int(state.protected_media_for(
                    course_code="ENT", term=7, session_no=4, content_kind="voice"
                )[0]["id"])
                api = QueueApi()
                dispatcher = ProtectedMediaDispatcher(
                    api=api,
                    state=state,
                    authorize=lambda _user, _source: True,
                    temp_root=root / "jobs",
                    workers=1,
                    max_queue=24,
                )
                statuses = [dispatcher.enqueue(user_id, source_id) for user_id in range(100, 120)]
                self.assertEqual(statuses, ["queued"] * 20)
                self.assertEqual(len(dispatcher._threads), 1)
                gate.set()
                dispatcher.queue.join()
                self.assertEqual(api.count, 20)
            finally:
                gate.set()
                if dispatcher is not None:
                    dispatcher.close()
                state.close()

    def test_document_level_dedupe_covers_different_source_routes(self) -> None:
        gate = threading.Event()

        class QueueApi:
            def send_protected_media(self, _chat_id, _source, *, personalized_file_id=""):
                gate.wait(3)
                return {"message_id": 1}

            def send(self, *_args):
                return {"message_id": 2}

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            dispatcher = None
            try:
                for message_id, kind in ((31, "voice"), (32, "reference")):
                    state.replace_protected_media_message(SOURCE_CHAT_ID, message_id, [{
                        "contentKind": kind, "courseCode": "ENT", "courseName": "ENT",
                        "courseTag": "ent", "term": 7, "sessionNo": 4,
                        "telegramMethod": "sendVoice" if kind == "voice" else "sendDocument",
                        "fileId": f"route-{message_id}", "fileUniqueId": "same-logical-document",
                        "fileName": "same.ogg" if kind == "voice" else "same.bin",
                        "mimeType": "audio/ogg" if kind == "voice" else "application/octet-stream",
                        "caption": "test",
                    }])
                first = int(state.protected_media_source(1)["id"])
                second = int(state.protected_media_source(2)["id"])
                dispatcher = ProtectedMediaDispatcher(
                    api=QueueApi(), state=state, authorize=lambda _user, _source: True,
                    temp_root=Path(directory) / "jobs", workers=1, max_queue=20,
                )
                self.assertEqual(dispatcher.enqueue(90, first), "queued")
                self.assertEqual(dispatcher.enqueue(90, second), "duplicate")
                gate.set()
                dispatcher.queue.join()
            finally:
                gate.set()
                if dispatcher is not None:
                    dispatcher.close()
                state.close()

    def test_rate_limit_atomic_completion_and_stale_recovery(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            state = BotState(root / "state.sqlite3")
            try:
                self.assertEqual(state.claim_booklet_request(
                    77, "doc", now_epoch=1000, window_seconds=60,
                    max_requests=2, cooldown_seconds=3,
                ), "claimed")
                self.assertEqual(state.claim_booklet_request(
                    77, "doc", now_epoch=1001, window_seconds=60,
                    max_requests=2, cooldown_seconds=3,
                ), "cooldown")
                self.assertEqual(state.claim_booklet_request(
                    77, "doc-2", now_epoch=1004, window_seconds=60,
                    max_requests=2, cooldown_seconds=3,
                ), "claimed")
                self.assertEqual(state.claim_booklet_request(
                    77, "doc-3", now_epoch=1005, window_seconds=60,
                    max_requests=2, cooldown_seconds=3,
                ), "rate-limited")
                state.replace_protected_media_message(SOURCE_CHAT_ID, 41, [{
                    "contentKind": "booklet", "courseCode": "ENT", "courseName": "ENT",
                    "courseTag": "ent", "term": 7, "sessionNo": 4,
                    "telegramMethod": "sendDocument", "fileId": "source",
                    "fileUniqueId": "atomic-document", "fileName": "source.pdf",
                    "mimeType": "application/pdf", "caption": "test",
                }])
                source_id = int(state.protected_media_for(
                    course_code="ENT", term=7, session_no=4, content_kind="booklet"
                )[0]["id"])
                issuance = state.create_booklet_issuance(
                    issuance_id="iss_atomiccompletion001", user_id=77, source_id=source_id,
                    document_id="tgdoc_atomic", trace_code="TRC-ABCDE-FGHIJ",
                    fingerprint_hash="a" * 64, watermark_version="recipient-pdf-v2",
                    source_hash="b" * 64,
                )
                self.assertTrue(state.mark_booklet_issuance_processing(issuance["issuanceId"]))
                state.complete_booklet_issuance(
                    issuance["issuanceId"], telegram_file_id="atomic-file",
                    telegram_file_unique_id="atomic-unique",
                )
                self.assertEqual(state.sent_booklet_issuance(
                    77, source_id, "tgdoc_atomic", "recipient-pdf-v2"
                )["telegramFileId"], "atomic-file")
                self.assertEqual(state.personalized_media_file(77, source_id), "atomic-file")
                stale = state.create_booklet_issuance(
                    issuance_id="iss_staleprocessing001", user_id=78, source_id=source_id,
                    document_id="tgdoc_stale", trace_code="TRC-KLMNO-PQRST",
                    fingerprint_hash="c" * 64, watermark_version="recipient-pdf-v2",
                    source_hash="d" * 64,
                )
                self.assertTrue(state.mark_booklet_issuance_processing(stale["issuanceId"]))
                state.connection.execute(
                    "UPDATE booklet_issuances SET updated_at='2000-01-01 00:00:00' WHERE issuance_id=?",
                    (stale["issuanceId"],),
                )
                state.connection.commit()
                self.assertEqual(state.recover_stale_booklet_issuances(60), 1)
                self.assertEqual(state.booklet_issuance_for_source_hash(
                    78, source_id, "tgdoc_stale", "d" * 64, "recipient-pdf-v2"
                )["status"], "failed")
            finally:
                state.close()

    def test_orphan_cleanup_is_age_based(self) -> None:
        class NoopApi:
            pass

        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            temp_root = root / "jobs"
            temp_root.mkdir()
            old = temp_root / "job-old"
            fresh = temp_root / "job-fresh"
            old.mkdir()
            fresh.mkdir()
            old_epoch = time.time() - 7200
            os.utime(old, (old_epoch, old_epoch))
            state = BotState(root / "state.sqlite3")
            dispatcher = None
            try:
                dispatcher = ProtectedMediaDispatcher(
                    api=NoopApi(), state=state, authorize=lambda _user, _source: True,
                    temp_root=temp_root, orphan_max_age_seconds=3600,
                )
                self.assertFalse(old.exists())
                self.assertTrue(fresh.exists())
            finally:
                if dispatcher is not None:
                    dispatcher.close()
                state.close()


if __name__ == "__main__":
    unittest.main()
