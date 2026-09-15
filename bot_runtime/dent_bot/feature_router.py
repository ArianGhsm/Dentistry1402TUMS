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
    if handle_classops_ui_message(app, message):
        return True
    return handle_class_operations_message(app, message)


def decorate_feature_screen(app: "DentBotApp", name: str, user_id: int, screen: Screen) -> Screen:
    return decorate_classops_dynamic_screen(app, name, user_id, screen)
