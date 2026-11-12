from __future__ import annotations

import base64
import tempfile
import unittest
from datetime import datetime, timedelta, timezone
from pathlib import Path

from dent_bot.app import DentBotApp
from dent_bot.hamiad_login import hamiad_captcha_caption, send_hamiad_login_challenge
from dent_bot.state import BotState


class MockApi:
    def __init__(self):
        self.photos = []
        self.sent = []

    def send_photo_bytes(self, user_id, image, *, caption, reply_markup, filename=""):
        self.photos.append((user_id, image, caption, reply_markup, filename))
        return {"message_id": 77}

    def send(self, chat_id, text, keyboard=None):
        self.sent.append((chat_id, str(text), keyboard))
        return {"message_id": 90}


class MockSite:
    def __init__(self):
        self.login_calls = []
        self.completed = []
        self.connected = False

    def account(self, uid):
        return {"linked": True, "authComplete": True, "success": True, "user": {}, "onboardingProfile": {}}

    def hamiad_login_start(self, uid):
        self.login_calls.append(uid)
        if self.connected:
            return {"success": True, "status": "connected"}
        return {
            "success": True,
            "status": "challenge",
            "challengeRef": "A" * 32,
            "captchaDataUri": "data:image/png;base64," + base64.b64encode(b"\x89PNG\r\n\x1a\nFAKE").decode(),
            "expiresAt": (datetime.now(timezone.utc) + timedelta(minutes=4)).isoformat(),
        }

    def hamiad_login_complete(self, uid, *, challenge_ref, captcha_code):
        self.completed.append((uid, challenge_ref, captcha_code))
        self.connected = True
        return {"success": True, "status": "connected"}

    def request(self, action, uid, **kwargs):
        if action == "hamiadDashboardV1":
            return {"success": True, "dashboard": {"connected": True, "courses": [], "assignments": []}}
        raise RuntimeError("unexpected action " + action)


def msg(uid, text, reply_to=None):
    body = {"message_id": 82, "text": text, "from": {"id": uid},
            "chat": {"id": uid, "type": "private"}}
    if reply_to:
        body["reply_to_message"] = {"message_id": reply_to}
    return {"message": body}


class HamiadHandoffTests(unittest.TestCase):
    def test_caption_is_farsi_and_clear(self):
        text = hamiad_captcha_caption("2026-11-12T12:00:00+03:30")
        self.assertIn("همیاد", text)
        self.assertIn("پاسخ به همین پیام", text)
        self.assertIn("اعتبار تصویر", text)

    def test_single_use_reply_bound_to_owner_and_photo(self):
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api, site = MockApi(), MockSite()
            app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=site)
            try:
                result = send_hamiad_login_challenge(
                    api=api, state=state, site_api=site, owner_id=10
                )
                self.assertEqual(result["status"], "challenge")
                self.assertEqual(len(api.photos), 1)
                self.assertEqual(api.photos[0][0], 10)
                self.assertTrue(api.photos[0][3]["force_reply"])
                self.assertEqual(state.hamiad_challenge()["message_id"], 77)
                app.handle(msg(20, "12345", 77))
                app.handle(msg(10, "12345", 76))
                self.assertEqual(site.completed, [])
                app.handle(msg(10, "۱۲۳۴۵", 77))
                self.assertEqual(site.completed, [(10, "A" * 32, "12345")])
                self.assertIsNone(state.hamiad_challenge())
                self.assertTrue(any("ورود به همیاد تأیید شد" in text for _, text, _ in api.sent))
                app.handle(msg(10, "12345", 77))
                self.assertEqual(len(site.completed), 1)
            finally:
                state.close()

    def test_owner_hamiad_command_sends_captcha_and_nonowner_cannot(self):
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api, site = MockApi(), MockSite()
            app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=site)
            try:
                app.handle(msg(10, "/hamiad"))
                self.assertEqual(len(api.photos), 1)
                self.assertEqual(site.login_calls, [10])
                self.assertEqual(state.hamiad_challenge()["message_id"], 77)
                app.handle(msg(20, "/hamiad"))
                self.assertEqual(len(api.photos), 1)
                self.assertEqual(site.login_calls, [10])
            finally:
                state.close()

    def test_expired_reply_does_not_submit_and_connected_skips_photo(self):
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api, site = MockApi(), MockSite()
            app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=site)
            try:
                state.set_hamiad_challenge("B"*32, 77, "2025-01-01T00:00:00+03:30")
                app.handle(msg(10, "AB123", 77))
                self.assertEqual(site.completed, [])
                self.assertIsNone(state.hamiad_challenge())
                site.connected = True
                result = send_hamiad_login_challenge(api=api, state=state, site_api=site, owner_id=10)
                self.assertEqual(result["status"], "connected")
                self.assertEqual(api.photos, [])
            finally:
                state.close()


if __name__ == "__main__":
    unittest.main()
