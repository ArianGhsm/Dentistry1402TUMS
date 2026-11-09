from __future__ import annotations

import argparse
import json
import time

from .api import BaleBotApi, BotApiError, TelegramBotApi
from .bale_config import load_settings as load_bale_settings
from .button_modes import button_mode_announcement
from .config import load_settings as load_telegram_settings
from .keyboard_invariant import KEYBOARD_CLEANUP_VERSION
from .state import BotState


CAMPAIGN_ID = "button-mode-choice-20261109-v1"


def send_campaign(*, state: BotState, api, platform: str, dry_run: bool = False,
                  max_users: int = 0, pause_seconds: float = 0.12,
                  user_ids: list[int] | None = None) -> dict[str, int]:
    """Idempotent direct message to every previously known private bot account.

    Authentication dialogs are deliberately not altered. A user in signup can
    read the notice and choose a mode only after completing authentication.
    """
    users = (sorted(set(int(user_id) for user_id in user_ids if int(user_id) > 0))
             if user_ids is not None else state.known_private_bot_users())
    counts = {"eligible": len(users), "sent": 0, "already_sent": 0,
              "failed": 0, "keyboard_removed": 0, "active_dialogs": 0}
    screen = button_mode_announcement()
    for user_id in (users[:max_users] if max_users > 0 else users):
        receipt = f"{CAMPAIGN_ID}:{platform}:{user_id}"
        if state.has_notification_delivery(receipt):
            counts["already_sent"] += 1
            continue
        dialog = state.dialog(user_id)
        in_dialog = bool(dialog)
        if in_dialog:
            counts["active_dialogs"] += 1
        if dry_run:
            continue
        try:
            # Never interrupt the keyboard for an unfinished OTP/contact or
            # any other active text-input dialog.
            if not in_dialog and state.reply_keyboard_needs_removal(user_id, KEYBOARD_CLEANUP_VERSION):
                result = api.remove_reply_keyboard(user_id)
                if not int(result.get("message_id") or 0):
                    raise BotApiError("Keyboard dismissal was not acknowledged")
                state.mark_reply_keyboard_removed(user_id, KEYBOARD_CLEANUP_VERSION)
                counts["keyboard_removed"] += 1
            result = api.send(user_id, screen.text, screen.keyboard)
            if not int(result.get("message_id") or 0):
                raise BotApiError("Campaign notice had no message id")
            state.mark_notification_delivery(receipt)
            counts["sent"] += 1
        except (BotApiError, OSError, ValueError):
            counts["failed"] += 1
        if pause_seconds:
            time.sleep(pause_seconds)
    return counts


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--platform", required=True, choices=("telegram", "bale"))
    parser.add_argument("--dry-run", action="store_true")
    parser.add_argument("--max-users", type=int, default=0)
    parser.add_argument("--owner-only", action="store_true")
    args = parser.parse_args()
    settings = load_bale_settings() if args.platform == "bale" else load_telegram_settings()
    api = (BaleBotApi(settings.token) if args.platform == "bale"
           else TelegramBotApi(settings.token, proxy_url=settings.telegram_proxy_url))
    state = BotState(settings.state_db, payment_offers_path=settings.payment_offers_db)
    try:
        counts = send_campaign(state=state, api=api, platform=args.platform,
                               dry_run=args.dry_run, max_users=args.max_users,
                               user_ids=[settings.owner_id] if args.owner_only else None)
        print(json.dumps({"platform": args.platform, "dry_run": args.dry_run, **counts},
                         ensure_ascii=False, separators=(",", ":")))
        return 0 if counts["failed"] == 0 else 1
    finally:
        state.close()
        api.close()


if __name__ == "__main__":
    raise SystemExit(main())
