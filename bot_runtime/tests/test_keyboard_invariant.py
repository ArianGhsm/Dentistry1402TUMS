from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from dent_bot.api import BotApiError
from dent_bot.keyboard_invariant import KEYBOARD_CLEANUP_VERSION, KeyboardInvariantApi
from dent_bot.state import BotState


class CapturingApi:
    def __init__(self) -> None:
        self.removed: list[int] = []
        self.sent: list[tuple[int, str, dict]] = []
        self.fail_remove = False

    def remove_reply_keyboard(self, chat_id: int):
        if self.fail_remove:
            raise BotApiError("cleanup unavailable", transient=True)
        self.removed.append(chat_id)
        return {"message_id": len(self.removed)}

    def send(self, chat_id: int, text: str, keyboard: dict):
        self.sent.append((chat_id, text, keyboard))
        return {"message_id": len(self.sent)}

    def edit(self, chat_id: int, message_id: int, text: str, keyboard: dict):
        return {"message_id": message_id}


class KeyboardInvariantTests(unittest.TestCase):
    def test_authenticated_reply_mode_uses_the_same_actions_and_persists_mapping(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            delegate = CapturingApi()
            try:
                state.set_button_mode(20, "reply")
                api = KeyboardInvariantApi(delegate, state)
                api.begin_interaction(20, authenticated=True)
                markup = {"inline_keyboard": [
                    [{"text": "📚 جزوات", "callback_data": "v1:notes"},
                     {"text": "📚 جزوات", "callback_data": "v1:notes-other"}],
                    [{"text": "📎 فایل", "url": "https://example.test/document?id=20"}],
                ]}
                api.send(20, "جزوات من", markup)
                self.assertIn("keyboard", delegate.sent[-1][2])
                labels = [item["text"] for row in delegate.sent[-1][2]["keyboard"] for item in row]
                self.assertEqual(len(labels), len(set(labels)))
                self.assertEqual(state.reply_navigation_choice(20, "📚 جزوات"),
                                 {"kind": "callback", "data": "v1:notes"})
                self.assertEqual(state.reply_navigation_choice(20, "📚 جزوات (۲)"),
                                 {"kind": "callback", "data": "v1:notes-other"})
                self.assertEqual(state.reply_navigation_choice(20, "📎 فایل"),
                                 {"kind": "url", "url": "https://example.test/document?id=20"})
                self.assertEqual(state.reply_navigation_choice(20, "⚙️ تنظیمات ربات")["data"],
                                 "v1:ui-settings")
                api.send(20, "اعلان بدون دکمه", {"inline_keyboard": []})
                self.assertEqual(state.reply_navigation_choice(20, "📚 جزوات")["data"], "v1:notes")
                self.assertEqual(delegate.removed, [])
                api.mark_reply_choice()
                api.edit(20, 77, "صفحه دیگر", markup)
                self.assertEqual(delegate.sent[-1][1], "صفحه دیگر")
                self.assertTrue(state.reply_keyboard_needs_removal(20, KEYBOARD_CLEANUP_VERSION))
            finally:
                state.close()

    def test_disconnected_account_never_uses_custom_navigation(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            delegate = CapturingApi()
            try:
                state.set_button_mode(20, "reply")
                api = KeyboardInvariantApi(delegate, state)
                api.begin_interaction(20, authenticated=False)
                markup = {"inline_keyboard": [[{"text": "تنظیمات", "callback_data": "v1:ui-settings"}]]}
                api.send(20, "اتصال حساب لازم است", markup)
                self.assertEqual(delegate.sent[-1][2], markup)
                self.assertIsNone(state.reply_navigation_choice(20, "تنظیمات"))
            finally:
                state.close()

    def test_switching_to_inline_clears_stale_action_mapping(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            delegate = CapturingApi()
            try:
                state.set_button_mode(20, "reply")
                api = KeyboardInvariantApi(delegate, state)
                api.begin_interaction(20, authenticated=True)
                api.send(20, "صفحه", {"inline_keyboard": [[{"text": "پرداخت", "callback_data": "v1:pay"}]]})
                state.set_button_mode(20, "inline")
                api.send(20, "تنظیمات", {"inline_keyboard": [[{"text": "خانه", "callback_data": "v1:home"}]]})
                self.assertEqual(delegate.removed, [20])
                self.assertIn("inline_keyboard", delegate.sent[-1][2])
                self.assertIsNone(state.reply_navigation_choice(20, "پرداخت"))
            finally:
                state.close()

    def test_previous_deleted_cleanup_version_is_replayed_once(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            delegate = CapturingApi()
            try:
                # Previous releases marked success and then deleted the
                # keyboard-removal message before the client could process it.
                state.mark_reply_keyboard_removed(20, "reply-keyboard-v2")
                api = KeyboardInvariantApi(delegate, state)
                inline = {"inline_keyboard": [[{"text": "خانه", "callback_data": "v1:home"}]]}
                api.send(20, "خانه", inline)
                self.assertEqual(delegate.removed, [20])
                self.assertFalse(state.reply_keyboard_needs_removal(20, KEYBOARD_CLEANUP_VERSION))
                KeyboardInvariantApi(delegate, state).send(20, "کارنامه", inline)
                self.assertEqual(delegate.removed, [20])
            finally:
                state.close()

    def test_legacy_unknown_and_every_reply_to_inline_transition_are_cleaned_once(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            delegate = CapturingApi()
            try:
                api = KeyboardInvariantApi(delegate, state)
                inline = {"inline_keyboard": [[{"text": "خانه", "callback_data": "v1:home"}]]}
                reply = {"keyboard": [[{"text": "مرحله قبل"}]], "resize_keyboard": True}

                # An unknown record may be a keyboard left by an older release.
                api.send(20, "خانه", inline)
                self.assertEqual(delegate.removed, [20])
                api.send(20, "حساب", inline)
                self.assertEqual(delegate.removed, [20])

                api.send(20, "کد را وارد کن", reply)
                self.assertTrue(state.reply_keyboard_needs_removal(20, KEYBOARD_CLEANUP_VERSION))
                api.send(20, "تأیید شد", inline)
                self.assertEqual(delegate.removed, [20, 20])

                # The durable marker survives a process/app recreation.
                restarted = KeyboardInvariantApi(delegate, state)
                restarted.send(20, "منو", inline)
                self.assertEqual(delegate.removed, [20, 20])
            finally:
                state.close()

    def test_edit_with_reply_keyboard_sends_new_message_instead_of_editing(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            delegate = CapturingApi()
            try:
                api = KeyboardInvariantApi(delegate, state)
                reply = {
                    "keyboard": [[{"text": "کد یک‌بارمصرف سایت"}]],
                    "resize_keyboard": True,
                }
                result = api.edit(20, 7, "احراز هویت", reply)
                self.assertEqual(result, {"message_id": 1})
                self.assertEqual(delegate.sent, [(20, "احراز هویت", reply)])
                self.assertTrue(
                    state.reply_keyboard_needs_removal(20, KEYBOARD_CLEANUP_VERSION)
                )
            finally:
                state.close()

    def test_failed_cleanup_blocks_inline_transition_and_remains_retryable(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            delegate = CapturingApi()
            delegate.fail_remove = True
            try:
                api = KeyboardInvariantApi(delegate, state)
                with self.assertRaises(BotApiError):
                    api.send(20, "خانه", {"inline_keyboard": []})
                self.assertEqual(delegate.sent, [])
                self.assertTrue(state.reply_keyboard_needs_removal(20, KEYBOARD_CLEANUP_VERSION))
            finally:
                state.close()

    def test_explicit_cleanup_is_idempotent_after_authentication(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            delegate = CapturingApi()
            try:
                api = KeyboardInvariantApi(delegate, state)
                api.remove_reply_keyboard(20)
                api.remove_reply_keyboard(20)
                api.remove_reply_keyboard(20)
                self.assertEqual(delegate.removed, [20])

                # A real reply keyboard becoming active again legitimately
                # requires exactly one fresh cleanup, then becomes quiet again.
                api.send(20, "مرحله احراز هویت", {
                    "keyboard": [[{"text": "مرحله قبل"}]],
                    "resize_keyboard": True,
                })
                api.remove_reply_keyboard(20)
                api.remove_reply_keyboard(20)
                self.assertEqual(delegate.removed, [20, 20])
            finally:
                state.close()


if __name__ == "__main__":
    unittest.main()
