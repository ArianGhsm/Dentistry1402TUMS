from __future__ import annotations

from typing import TYPE_CHECKING, Any

from .class_operations import handle_class_operations_callback, handle_class_operations_message
from .classops_ui import (
    decorate_classops_dynamic_screen,
    handle_classops_ui_callback,
    handle_classops_ui_message,
)
from .term7_group_management import handle_term7_callback
from .ui import Screen

if TYPE_CHECKING:
    from .app import DentBotApp


def route_feature_callback(
    app: "DentBotApp",
    callback: dict[str, Any],
    *,
    interaction_version: int | None = None,
) -> bool:
    """Route feature callbacks in one explicit, tested priority order."""
    if handle_classops_ui_callback(app, callback, interaction_version=interaction_version):
        return True
    if handle_term7_callback(app, callback):
        return True
    return handle_class_operations_callback(app, callback)


def route_feature_message(app: "DentBotApp", message: dict[str, Any]) -> bool:
    """Route feature dialogs/commands before the base DentBot message flow."""
    chat = dict(message.get("chat") or {})
    sender = dict(message.get("from") or {})
    if (str(message.get("text") or "").strip() == "/start subscription_7"
            and chat.get("type") == "private" and isinstance(sender.get("id"), int)):
        user_id = int(sender["id"])
        app._account_cache.pop(user_id, None)
        app._product_state_cache.pop(user_id, None)
        screen = app._dynamic_screen("term-subscription", user_id)
        app.api.send(int(chat["id"]), screen.text, screen.keyboard)
        return True
    if handle_classops_ui_message(app, message):
        return True
    return handle_class_operations_message(app, message)


def decorate_feature_screen(app: "DentBotApp", name: str, user_id: int, screen: Screen) -> Screen:
    return decorate_classops_dynamic_screen(app, name, user_id, screen)
