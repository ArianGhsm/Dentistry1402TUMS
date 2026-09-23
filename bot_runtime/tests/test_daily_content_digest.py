from __future__ import annotations

import tempfile
import unittest
from datetime import datetime, timezone
from pathlib import Path
from types import SimpleNamespace

from dent_bot.daily_content_digest import (
    eligible_digest_recipients,
    render_daily_content_digest,
)
from dent_bot.runtime import dispatch_daily_content_digest
from dent_bot.state import BotState


CATALOG = {
    "courses": [
        {
            "courseKey": "diagnosis-3",
            "courseTitle": "دندانپزشکی تشخیصی ۳",
            "bookletTag": "تشخیصی۳",
            "term": 7,
            "sessions": [
                {"sessionNumber": 1, "title": "ضایعات اگزوفیتیک خارج استخوانی"},
                {"sessionNumber": 2, "title": "ضایعات سفید دهان"},
            ],
        }
    ]
}


class FakeApi:
    def __init__(self) -> None:
        self.sent: list[tuple[int, str, dict]] = []

    def send(self, chat_id, text, keyboard):
        self.sent.append((int(chat_id), str(text), keyboard))
        return {"message_id": len(self.sent)}


class FakeSiteApi:
    def __init__(self, platform_user_id: int) -> None:
        self.platform_user_id = platform_user_id

    def booklet_catalog(self, _owner_id: int) -> dict:
        return CATALOG

    def payment_directory(self, _owner_id: int, *, query: str = "", limit: int = 100) -> dict:
        return {
            "items": [
                {
                    "name": "دانشجوی ورودی ۱۴۰۲",
                    "studentNumber": "1402001",
                    "cohortKey": "dentistry-1402",
                    "platformUserId": str(self.platform_user_id),
                },
                {
                    "name": "دانشجوی دیگر",
                    "studentNumber": "1401001",
                    "cohortKey": "dentistry-1401",
                    "platformUserId": "999999",
                },
            ]
        }


class DailyContentDigestTests(unittest.TestCase):
    def test_renderer_uses_one_summary_table_and_groups_duplicate_sessions(self) -> None:
        payload = {
            "items": [
                {"courseName": "دندانپزشکی تشخیصی ۳", "sessionNo": 1, "sessionTitle": "ضایعات اگزوفیتیک خارج استخوانی", "contentKind": "voice"},
                {"courseName": "دندانپزشکی تشخیصی ۳", "sessionNo": 1, "sessionTitle": "ضایعات اگزوفیتیک خارج استخوانی", "contentKind": "voice"},
                {"courseName": "دندانپزشکی تشخیصی ۳", "sessionNo": 2, "sessionTitle": "ضایعات سفید دهان", "contentKind": "voice"},
                {"courseName": "دندانپزشکی تشخیصی ۳", "sessionNo": 1, "sessionTitle": "ضایعات اگزوفیتیک خارج استخوانی", "contentKind": "ai_booklet"},
            ]
        }
        screen = render_daily_content_digest(payload)
        self.assertIn("🎤 ویس · ۳", screen.text)
        self.assertIn("🤖 جزوه هوش مصنوعی · ۱", screen.text)
        self.assertIn("جلسه ۱ · ×۲", screen.text)
        self.assertIn("ضایعات اگزوفیتیک خارج استخوانی", screen.text)
        self.assertEqual(getattr(screen.text, "rich_html", "").count("<table"), 1)

    def test_recipient_filter_is_fail_closed_to_dentistry_1402(self) -> None:
        payload = {
            "items": [
                {"cohortKey": "dentistry-1402", "platformUserId": "123"},
                {"cohortKey": "dentistry-1401", "platformUserId": "456"},
                {"cohortKey": "dentistry-1402", "platformUserId": ""},
                {"cohortKey": "dentistry-1402", "platformUserId": "abc"},
            ]
        }
        self.assertEqual(eligible_digest_recipients(payload), [123])

    def test_dispatch_prepares_once_and_is_idempotent_across_telegram_and_bale(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            shared = root / "shared.sqlite3"
            telegram_state = BotState(root / "telegram.sqlite3", payment_offers_path=shared)
            bale_state = BotState(root / "bale.sqlite3", payment_offers_path=shared)
            try:
                telegram_state.replace_protected_media_message(
                    -100123,
                    41,
                    [{
                        "courseCode": "diagnosis-3",
                        "courseName": "دندانپزشکی تشخیصی ۳",
                        "courseTag": "تشخیصی۳",
                        "term": 7,
                        "sessionNo": 1,
                        "contentKind": "voice",
                        "telegramMethod": "sendAudio",
                        "fileId": "file",
                        "fileUniqueId": "unique",
                        "fileName": "voice.mp3",
                        "mimeType": "audio/mpeg",
                        "caption": "ویس جلسه اول",
                    }],
                )
                telegram_state.connection.execute(
                    "UPDATE protected_media_sources SET created_at='2026-09-23 10:00:00'"
                )
                telegram_state.connection.commit()

                now = datetime(2026, 9, 23, 18, 35, tzinfo=timezone.utc)
                telegram_api = FakeApi()
                settings = SimpleNamespace(platform="telegram", owner_id=1)
                first = dispatch_daily_content_digest(
                    settings=settings,
                    api=telegram_api,
                    state=telegram_state,
                    site_api=FakeSiteApi(111),
                    now=now,
                )
                self.assertEqual(first["status"], "completed")
                self.assertEqual(first["sent"], 1)
                self.assertEqual(len(telegram_api.sent), 1)

                second = dispatch_daily_content_digest(
                    settings=settings,
                    api=telegram_api,
                    state=telegram_state,
                    site_api=FakeSiteApi(111),
                    now=now,
                )
                self.assertEqual(second["sent"], 0)
                self.assertEqual(second["skipped"], 1)
                self.assertEqual(len(telegram_api.sent), 1)

                bale_api = FakeApi()
                bale = dispatch_daily_content_digest(
                    settings=SimpleNamespace(platform="bale", owner_id=1),
                    api=bale_api,
                    state=bale_state,
                    site_api=FakeSiteApi(222),
                    now=now,
                )
                self.assertEqual(bale["sent"], 1)
                self.assertEqual(len(bale_api.sent), 1)
            finally:
                telegram_state.close()
                bale_state.close()


if __name__ == "__main__":
    unittest.main()
