from __future__ import annotations

from dataclasses import dataclass
from typing import Any, Mapping

from .model import ActionIntent, build_intent
from .render import SurfaceButton, SurfaceView


@dataclass(frozen=True)
class RenderedSurface:
    platform: str
    text: str
    keyboard: dict[str, Any]
    semantic_actions: tuple[str, ...]


def _callback_key(action: str) -> str:
    # Context (item/revision/nonce) is supplied by integration when the callback
    # is resolved. The callback itself therefore cannot mutate ClassOps storage.
    key = "classops:" + action
    if len(key.encode("utf-8")) > 64:
        raise ValueError("ClassOps callback key exceeds transport-safe length")
    return key


class BaseAdapter:
    platform = "base"
    supports_button_style = False

    def render(self, view: SurfaceView) -> RenderedSurface:
        rows = []
        semantic = []
        for button in view.buttons:
            payload: dict[str, Any] = {"text": button.label}
            if button.enabled:
                payload["callback_data"] = _callback_key(button.action)
                semantic.append(button.action)
            else:
                payload["calllback_data"] = _callback_key("disabled")
                payload["disabled_reason"] = button.disabled_reason
            if self.supports_button_style and button.style != "default":
                payload["style"] = button.style
            rows.append([payload])
        return RenderedSurface(
            platform=self.platform,
            text=f"{view.title}\n\n{view.text}".strip(),
            keyboard={"inline_keyboard": rows},
            semantic_actions=tuple(semantic),
        )


class TelegramAdapter(BaseAdapter):
    platform = "telegram"
    supports_button_style = True


class BaleAdapter(BaseAdapter):
    platform = "bale"
    # Bale compatibility path deliberately omits optional style metadata; copy,
    # action identity and confirmation semantics stay identical.
    supports_button_style = False


def intent_from_callback(
    callback_data: str,
    *,
    actor_role: str,
    item_id: str | None = None,
    expected_revision: int | None = None,
    payload: Mapping[str, Any] | None = None,
    nonce: str,
) -> ActionIntent:
    prefix = "classops:"
    if not callback_data.startswith(prefix):
        raise ValueError("not a ClassOps callback")
    action = callback_data[len(prefix):]
    if action == "disabled":
        raise ValueError("disabled ClassOps action cannot produce an intent")
    return build_intent(
        action,
        actor_role,
        item_id=item_id,
        expected_revision=expected_revision,
        payload=payload,
        nonce=nonce,
        confirmed=False,
    )
