from __future__ import annotations

import threading
from urllib.parse import urlsplit

from .api import _persianize_plain_visible_text
from .persian_datetime import to_persian_digits
from .state import BotState


KEYBOARD_CLEANUP_VERSION = "reply-keyboard-v3"


class KeyboardInvariantApi:
    """Persist and enforce reply-keyboard cleanup across bot releases."""

    def __init__(self, delegate, state: BotState) -> None:
        self._delegate = delegate
        self._state = state
        self._interaction = threading.local()

    def __getattr__(self, name: str):
        return getattr(self._delegate, name)

    def begin_interaction(self, user_id: int, *, authenticated: bool) -> None:
        self._interaction.user_id = int(user_id)
        self._interaction.authenticated = bool(authenticated)
        self._interaction.reply_choice = False

    def end_interaction(self) -> None:
        self._interaction.user_id = 0
        self._interaction.authenticated = False
        self._interaction.reply_choice = False

    def mark_reply_choice(self) -> None:
        self._interaction.reply_choice = True

    def answer_callback(self, callback_id: str, *args, **kwargs):
        if str(callback_id).startswith("reply-choice:"):
            return True
        return self._delegate.answer_callback(callback_id, *args, **kwargs)

    def _reply_mode(self, chat_id: int) -> bool:
        return (
            int(getattr(self._interaction, "user_id", 0)) == int(chat_id)
            and bool(getattr(self._interaction, "authenticated", False))
            and self._state.button_mode(chat_id) == "reply"
        )

    @staticmethod
    def _reply_presentation(reply_markup: dict) -> tuple[dict, dict] | None:
        rows = reply_markup.get("inline_keyboard")
        if not isinstance(rows, list) or not rows:
            return None
        rendered: list[list[dict]] = []
        choices: dict[str, dict] = {}
        for original_row in rows:
            if not isinstance(original_row, list):
                return None
            display_row: list[dict] = []
            for item in original_row:
                if not isinstance(item, dict):
                    return None
                label = _persianize_plain_visible_text(" ".join(str(item.get("text") or "").split()))
                if not label:
                    return None
                if isinstance(item.get("callback_data"), str):
                    action = {"kind": "callback", "data": item["callback_data"]}
                elif isinstance(item.get("url"), str):
                    url = item["url"]
                    parsed = urlsplit(url)
                    if parsed.scheme != "https" or not parsed.netloc or parsed.username or parsed.password:
                        return None
                    action = {"kind": "url", "url": url}
                elif isinstance(item.get("switch_inline_query"), str):
                    action = {"kind": "inline-search", "query": item["switch_inline_query"]}
                else:
                    # Unsupported buttons stay native rather than silently losing
                    # a payment/security action.
                    return None
                label = label[:55]
                if label in choices:
                    suffix = 2
                    while f"{label[:48]} ({to_persian_digits(suffix)})" in choices:
                        suffix += 1
                    label = f"{label[:48]} ({to_persian_digits(suffix)})"
                choices[label] = action
                display_row.append({"text": label})
            if display_row:
                rendered.append(display_row)
        if not choices or len(choices) > 98:
            return None
        for label, action in (("🏠 خانه", "v1:home"), ("⚙️ تنظیمات ربات", "v1:ui-settings")):
            if not any(value.get("data") == action for value in choices.values()):
                if label in choices:
                    label = "🏠 منوی اصلی" if action == "v1:home" else "⚙️ تنظیمات دنت‌یار"
                rendered.append([{"text": label}])
                choices[label] = {"kind": "callback", "data": action}
        return ({
            "keyboard": rendered,
            "resize_keyboard": True,
            "one_time_keyboard": False,
            "input_field_placeholder": "از دکمه‌های همین صفحه انتخاب کنید",
        }, choices)

    @staticmethod
    def _has_reply_keyboard(reply_markup: dict | None) -> bool:
        return isinstance(reply_markup, dict) and isinstance(reply_markup.get("keyboard"), list)

    def _ensure_removed(self, chat_id: int) -> None:
        if not self._state.reply_keyboard_needs_removal(chat_id, KEYBOARD_CLEANUP_VERSION):
            return
        remove = getattr(self._delegate, "remove_reply_keyboard", None)
        if callable(remove):
            remove(chat_id)
        # Minimal unit-test doubles may omit the transport method. Production
        # adapters both implement it and have an explicit transport test.
        self._state.mark_reply_keyboard_removed(chat_id, KEYBOARD_CLEANUP_VERSION)

    def send(self, chat_id: int, text: str, reply_markup: dict) -> dict:
        if self._has_reply_keyboard(reply_markup):
            # Authentication input and contact sharing keep their existing
            # reply keyboards; no menu action may hijack those dialogs.
            result = dict(self._delegate.send(chat_id, text, reply_markup) or {})
            self._state.mark_reply_keyboard_active(chat_id)
            self._state.clear_reply_navigation(chat_id)
            return result
        if self._reply_mode(chat_id):
            presentation = self._reply_presentation(reply_markup)
            if presentation:
                keyboard, choices = presentation
                result = dict(self._delegate.send(chat_id, text, keyboard) or {})
                self._state.remember_reply_navigation(chat_id, choices)
                self._state.mark_reply_keyboard_active(chat_id)
                return result
            if reply_markup.get("inline_keyboard") == []:
                # A text-only notification must not erase the user's active
                # bottom navigation or replace its action-to-label mapping.
                return dict(self._delegate.send(chat_id, text, reply_markup) or {})
        self._ensure_removed(chat_id)
        self._state.clear_reply_navigation(chat_id)
        return dict(self._delegate.send(chat_id, text, reply_markup) or {})

    def edit(self, chat_id: int, message_id: int, text: str, reply_markup: dict) -> dict:
        if (
            self._has_reply_keyboard(reply_markup)
            or bool(getattr(self._interaction, "reply_choice", False))
            or (self._reply_mode(chat_id) and self._reply_presentation(reply_markup) is not None)
        ):
            # A reply keyboard cannot be applied with editMessageText. A choice
            # originating from typed button text must not edit that user message.
            return self.send(chat_id, text, reply_markup)
        if self._reply_mode(chat_id) and reply_markup.get("inline_keyboard") == []:
            return dict(self._delegate.edit(chat_id, message_id, text, reply_markup) or {})
        self._ensure_removed(chat_id)
        self._state.clear_reply_navigation(chat_id)
        return dict(self._delegate.edit(chat_id, message_id, text, reply_markup) or {})

    def remove_reply_keyboard(self, chat_id: int, text: str = "") -> dict:
        # Callers may defensively request cleanup on every /start or /menu.
        # Once this cleanup version is durably recorded, repeating the transport
        # call would only create another misleading "authentication completed"
        # message. Keep the operation truly idempotent across restarts.
        if self._reply_mode(chat_id):
            return {}
        if not self._state.reply_keyboard_needs_removal(chat_id, KEYBOARD_CLEANUP_VERSION):
            return {}
        remove = getattr(self._delegate, "remove_reply_keyboard", None)
        if not callable(remove):
            result = {}
        elif text:
            result = dict(remove(chat_id, text=text) or {})
        else:
            result = dict(remove(chat_id) or {})
        self._state.mark_reply_keyboard_removed(chat_id, KEYBOARD_CLEANUP_VERSION)
        self._state.clear_reply_navigation(chat_id)
        return result
