"""Shared navigation rules (owner rule 2026-11-12: no dead ends)."""
from __future__ import annotations

from .state import BotState

# Authentication flows keep their progress: home is gated until they finish,
# so neither the main-menu guarantee nor the home callback may disturb them.
HOME_PRESERVED_DIALOG_KINDS = frozenset({"onboarding-v1", "class-auth-v1", "phone-enroll-v1"})


def _dialog_kind(state: BotState, user_id: int) -> str:
    dialog = state.dialog(user_id)
    return str(dialog.get("kind") or "") if dialog else ""


def home_guarantee_allowed_for(state: BotState, user_id: int) -> bool:
    """True unless the user is inside an authentication/onboarding dialog."""
    return _dialog_kind(state, user_id) not in HOME_PRESERVED_DIALOG_KINDS


def close_stale_dialog_for_home(state: BotState, user_id: int) -> None:
    """'Main menu' must really leave the current flow: a stale input dialog
    (profile edit, payment wizard, search, compose…) would otherwise capture
    the next typed text."""
    kind = _dialog_kind(state, user_id)
    if kind and kind not in HOME_PRESERVED_DIALOG_KINDS:
        state.clear_dialog(user_id)
