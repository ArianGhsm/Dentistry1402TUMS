"""Owner rule 2026-11-12: no bot screen may be a dead end.

Every inline screen sent to a private chat must offer the main menu, without
touching groups, pre-auth flows or screens that already link home.
"""
from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from dent_bot.keyboard_invariant import KeyboardInvariantApi
from dent_bot.state import BotState
from dent_bot import ui
from dent_bot.app import DentBotApp


class FakeBotApi:
    def __init__(self) -> None:
        self.sent: list[tuple] = []
        self.edited: list[tuple] = []

    def send(self, chat_id, text, keyboard):
        self.sent.append((chat_id, text, keyboard))
        return {"message_id": len(self.sent)}

    def edit(self, chat_id, message_id, text, keyboard):
        self.edited.append((chat_id, message_id, text, keyboard))
        return {"message_id": message_id}

    def answer_callback(self, *args, **kwargs):
        return True

    def remove_reply_keyboard(self, *args, **kwargs):
        return {}


class LinkedSite:
    """Site stub for a user who already passed the entry gate."""

    def account(self, _user_id):
        return {"success": True, "linked": True, "authComplete": True, "identity": {}, "onboardingProfile": {}}


class CapturingApi:
    def __init__(self) -> None:
        self.sent: list[tuple[int, str, dict]] = []
        self.edited: list[tuple[int, int, str, dict]] = []

    def remove_reply_keyboard(self, chat_id: int, text: str = ""):
        return {}

    def send(self, chat_id: int, text: str, keyboard: dict):
        self.sent.append((chat_id, text, keyboard))
        return {"message_id": len(self.sent)}

    def edit(self, chat_id: int, message_id: int, text: str, keyboard: dict):
        self.edited.append((chat_id, message_id, text, keyboard))
        return {"message_id": message_id}


def _actions(markup: dict) -> list[str]:
    return [item.get("callback_data") for row in markup.get("inline_keyboard", []) for item in row]


DEAD_END = {"inline_keyboard": [[{"text": "اتصال دوباره", "callback_data": "v1:account-relink"}]]}


class NoDeadEndTests(unittest.TestCase):
    def _api(self, directory: str):
        state = BotState(Path(directory) / "state.sqlite3")
        delegate = CapturingApi()
        return state, delegate, KeyboardInvariantApi(delegate, state)

    def test_authenticated_inline_screen_gets_main_menu_once(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state, delegate, api = self._api(directory)
            try:
                api.begin_interaction(30, authenticated=True)
                api.send(30, "صفحه", DEAD_END)
                self.assertEqual(_actions(delegate.sent[-1][2]), ["v1:account-relink", "v1:home"])
                api.edit(30, 7, "صفحه", DEAD_END)
                self.assertEqual(_actions(delegate.edited[-1][3]), ["v1:account-relink", "v1:home"])
                # A screen that already links home is left untouched.
                with_home = {"inline_keyboard": [[{"text": "🏠 خانه", "callback_data": "v1:home"}]]}
                api.send(30, "خانه", with_home)
                self.assertEqual(_actions(delegate.sent[-1][2]), ["v1:home"])
                # The caller's markup object is never mutated.
                self.assertEqual(_actions(DEAD_END), ["v1:account-relink"])
            finally:
                state.close()

    def test_background_private_push_gets_main_menu(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state, delegate, api = self._api(directory)
            try:
                api.send(31, "اعلان", DEAD_END)
                self.assertIn("v1:home", _actions(delegate.sent[-1][2]))
            finally:
                state.close()

    def test_groups_preauth_and_text_only_messages_are_untouched(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state, delegate, api = self._api(directory)
            try:
                api.send(-100123, "گروه", DEAD_END)
                self.assertEqual(_actions(delegate.sent[-1][2]), ["v1:account-relink"])
                api.begin_interaction(32, authenticated=False)
                api.send(32, "ورود", DEAD_END)
                self.assertEqual(_actions(delegate.sent[-1][2]), ["v1:account-relink"])
                api.end_interaction()
                api.send(33, "متن", {"inline_keyboard": []})
                self.assertEqual(delegate.sent[-1][2], {"inline_keyboard": []})
            finally:
                state.close()

    def test_transaction_filters_and_payment_center_link_back(self) -> None:
        filters = ui.payment_transaction_filters_screen({}, [])
        self.assertIn("v1:payment-transactions", _actions(filters.keyboard))
        self.assertIn("v1:home", _actions(filters.keyboard))


    def test_main_menu_callback_closes_stale_input_dialogs_but_not_auth(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                app = DentBotApp(FakeBotApi(), state, owner_id=10, site_url="https://example.test", platform="telegram")
                home = lambda: app.handle({"callback_query": {
                    "id": "cb", "from": {"id": 20},
                    "message": {"message_id": 5, "chat": {"id": 20, "type": "private"}},
                    "data": "v1:home",
                }})
                state.start_dialog(20, "profile-edit-v1", "field", {"field": "name"})
                home()
                self.assertIsNone(state.dialog(20))
                state.start_dialog(20, "onboarding-v1", "phone", {})
                home()
                self.assertEqual((state.dialog(20) or {}).get("kind"), "onboarding-v1")
            finally:
                state.close()

    def test_auth_dialog_started_mid_interaction_gets_no_injected_home(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state, delegate, api = self._api(directory)
            try:
                api.begin_interaction(34, authenticated=False, home_allowed=True)
                state.start_dialog(34, "phone-enroll-v1", "phone", {})
                api.send(34, "شماره", DEAD_END)
                self.assertEqual(_actions(delegate.sent[-1][2]), ["v1:account-relink"])
            finally:
                state.close()

    def test_production_path_inline_mode_user_gets_main_menu(self) -> None:
        """Regression: the guarantee must not depend on reply-button mode."""
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                api = FakeBotApi()
                app = DentBotApp(api, state, owner_id=10, site_url="https://example.test",
                                 site_api=LinkedSite(), platform="telegram")
                self.assertEqual(state.button_mode(21), "inline")
                # Any routed screen that forgets its home row must still get one.
                app._dynamic_screen = lambda *_args, **_kwargs: ui.Screen("صفحهٔ بدون خانه", DEAD_END)
                app.handle({"callback_query": {
                    "id": "cb2", "from": {"id": 21},
                    "message": {"message_id": 9, "chat": {"id": 21, "type": "private"}},
                    "data": "v1:help",
                }})
                markups = [entry[-1] for entry in api.sent + api.edited]
                inline = [m for m in markups if isinstance(m, dict) and m.get("inline_keyboard")]
                self.assertTrue(inline)
                for markup in inline:
                    self.assertIn("v1:home", _actions(markup))
            finally:
                state.close()


if __name__ == "__main__":
    unittest.main()
