from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from dent_bot.button_mode_campaign import send_campaign
from dent_bot.button_modes import button_mode_announcement, button_mode_screen
from dent_bot.keyboard_invariant import KEYBOARD_CLEANUP_VERSION, KeyboardInvariantApi
from dent_bot.state import BotState


class FakeCampaignApi:
    def __init__(self) -> None:
        self.removed = []
        self.sent = []

    def remove_reply_keyboard(self, chat_id):
        self.removed.append(chat_id)
        return {"message_id": 1}

    def send(self, chat_id, text, keyboard):
        self.sent.append((chat_id, text, keyboard))
        return {"message_id": len(self.sent)}


class ButtonModesTests(unittest.TestCase):
    def test_ui_is_persian_and_has_no_dead_end(self) -> None:
        for mode in ("inline", "reply"):
            screen = button_mode_screen(mode)
            self.assertIn("حالت فعلی:", screen.text)
            self.assertIn("⚙️ تنظیمات", screen.text)
            self.assertIn("v1:ui-mode:inline", str(screen.keyboard))
            self.assertIn("v1:ui-mode:reply", str(screen.keyboard))
            self.assertIn("v1:home", str(screen.keyboard))
        notice = button_mode_announcement()
        self.assertIn("بعد از احراز هویت", notice.text)
        self.assertIn("v1:ui-settings", str(notice.keyboard))

    def test_bulk_notice_deduplicated_and_respects_open_dialog(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeCampaignApi()
            try:
                state.touch_user(20)
                state.mark_reply_keyboard_active(21)
                state.start_dialog(21, "class-auth-v1", "otp", {})
                self.assertEqual(state.known_private_bot_users(), [20, 21])
                preview = send_campaign(state=state, api=api, platform="telegram", dry_run=True)
                self.assertEqual(preview["eligible"], 2)
                self.assertEqual(api.sent, [])
                self.assertEqual(api.removed, [])
                result = send_campaign(state=state, api=api, platform="telegram", pause_seconds=0)
                self.assertEqual(result["sent"], 2)
                self.assertEqual(result["keyboard_removed"], 1)
                self.assertEqual(result["active_dialogs"], 1)
                self.assertEqual(api.removed, [20])
                self.assertTrue(state.reply_keyboard_needs_removal(21, KEYBOARD_CLEANUP_VERSION))
                repeat = send_campaign(state=state, api=api, platform="telegram", pause_seconds=0)
                self.assertEqual(repeat["sent"], 0)
                self.assertEqual(repeat["already_sent"], 2)
                self.assertEqual(len(api.sent), 2)
            finally:
                state.close()

    def test_persian_numbered_labels_match_bot_api_presentation(self) -> None:
        class FakeApi(FakeCampaignApi):
            pass
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            try:
                state.set_button_mode(20, "reply")
                wrapper = KeyboardInvariantApi(api, state)
                wrapper.begin_interaction(20, authenticated=True)
                wrapper.send(20, "صفحهٔ دروس", {
                    "inline_keyboard": [[{"text": "درس 7", "callback_data": "v1:lesson-7"}]],
                })
                self.assertEqual(api.sent[-1][2]["keyboard"][0][0]["text"], "درس ۷")
                self.assertEqual(state.reply_navigation_choice(20, "درس ۷")["data"], "v1:lesson-7")
            finally:
                state.close()


if __name__ == "__main__":
    unittest.main()
