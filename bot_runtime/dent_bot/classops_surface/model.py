from __future__ import annotations

from dataclasses import dataclass, field
from hashlib import sha256
from typing import Any, Mapping

CONTRACT_VERSION = "classops-surface-v1"

OWNER = "owner"
STUDENT = "student"

ACTION_SPECS: dict[str, dict[str, Any]] = {
    "items.list": {"roles": {OWNER}, "mode": "read", "confirmation": False, "revision": False, "idempotency": False, "capability": "foundation.read"},
    "item.get": {"roles": {OWNER}, "mode": "read", "confirmation": False, "revision": False, "idempotency": False, "capability": "foundation.read"},
    "draft.create": {"roles": {OWNER}, "mode": "mutation", "confirmation": True, "revision": False, "idempotency": True, "capability": "foundation.create"},
    "item.preview_diff": {"roles": {OWNER}, "mode": "preview", "confirmation": False, "revision": False, "idempotency": False, "capability": "surface.preview"},
    "item.edit": {"roles": {OWNER}, "mode": "mutation", "confirmation": True, "revision": True, "idempotency": True, "capability": "foundation.update"},
    "item.schedule_intent": {"roles": {OWNER}, "mode": "mutation", "confirmation": True, "revision": True, "idempotency": True, "capability": "foundation.update"},
    "item.activate_intent": {"roles": {OWNER}, "mode": "mutation", "confirmation": True, "revision": True, "idempotency": True, "capability": "foundation.update"},
    "item.cancel": {"roles": {OWNER}, "mode": "mutation", "confirmation": True, "revision": True, "idempotency": True, "capability": "foundation.cancel"},
    "item.archive": {"roles": {OWNER}, "mode": "mutation", "confirmation": True, "revision": True, "idempotency": True, "capability": "foundation.archive"},
    "audience.preview": {"roles": {OWNER}, "mode": "preview", "confirmation": False, "revision": False, "idempotency": False, "capability": "audience.resolve"},
    "destination.preview": {"roles": {OWNER}, "mode": "preview", "confirmation": False, "revision": False, "idempotency": False, "capability": "delivery.destinations"},
    "task.requirement_view": {"roles": {OWNER, STUDENT}, "mode": "read", "confirmation": False, "revision": False, "idempotency": False, "capability": "task.requirement"},
    "exam.view": {"roles": {OWNER, STUDENT}, "mode": "read", "confirmation": False, "revision": False, "idempotency": False, "capability": "exam.view"},
    "exam.ack": {"roles": {STUDENT}, "mode": "mutation", "confirmation": True, "revision": True, "idempotency": True, "capability": "exam.critical_ack"},
    "reminder.preview": {"roles": {OWNER, STUDENT}, "mode": "preview", "confirmation": False, "revision": False, "idempotency": False, "capability": "reminder.preview"},
    "summary.tomorrow": {"roles": {OWNER, STUDENT}, "mode": "read", "confirmation": False, "revision": False, "idempotency": False, "capability": "summary.tomorrow"},
    "summary.weekly": {"roles": {OWNER, STUDENT}, "mode": "read", "confirmation": False, "revision": False, "idempotency": False, "capability": "summary.weekly"},
    "ai.draft_request": {"roles": {OWNER}, "mode": "preview", "confirmation": False, "revision": False, "idempotency": False, "capability": "ai.preview_request"},
}

FORBIDDEN_KEY_FRAGMENTS = (
    "chat_id", "chatid", "telegram_id", "telegramid", "bale_id", "baleid", "bot_token", "bottoken", "token", "secret",
    "password", "national_code", "nationalcode", "phone", "mobile", "otp",
)


class SurfaceContractError(ValueError):
    pass


@dataclass(frozen=True)
class Capability:
    enabled: bool
    reason: str = ""


@dataclass(frozen=True)
class ActionIntent:
    action: str
    actor_role: str
    item_id: str | None = None
    expected_revision: int | None = None
    idempotency_key: str | None = None
    confirmation_required: bool = False
    confirmed: bool = False
    payload: Mapping[str, Any] = field(default_factory=dict)
    contract_version: str = CONTRACT_VERSION

    def as_dict(self) -> dict[str, Any]:
        return {
            "contractVersion": self.contract_version,
            "action": self.action,
            "actorRole": self.actor_role,
            "itemId": self.item_id,
            "expectedRevision": self.expected_revision,
            "idempotencyKey": self.idempotency_key,
            "confirmationRequired": self.confirmation_required,
            "confirmed": self.confirmed,
            "payload": dict(self.payload),
        }


def _contains_forbidden_key(value: Any) -> bool:
    if isinstance(value, Mapping):
        for key, child in value.items():
            folded = str(key).replace("-", "_").lower()
            if any(fragment in folded for fragment in FORBIDDEN_KEY_FRAGMENTS):
                return True
            if _contains_forbidden_key(child):
                return True
    elif isinstance(value, (list, tuple)):
        return any(_contains_forbidden_key(child) for child in value)
    return False


def assert_safe_payload(payload: Mapping[str, Any]) -> None:
    if _contains_forbidden_key(payload):
        raise SurfaceContractError("ClassOps surface payload contains a forbidden identifier or secret field")


def idempotency_key(action: str, item_id: str | None, expected_revision: int | None, nonce: str) -> str:
    nonce = str(nonce).strip()
    if len(nonce) < 8 or len(nonce) > 128:
        raise SurfaceContractError("intent nonce must be between 8 and 128 characters")
    material = f"{CONTRACT_VERSION}|{action}|{item_id or '-'}|{expected_revision or 0}|{nonce}"
    return "surface_" + sha256(material.encode("utf-8")).hexdigest()[:32]


def build_intent(
    action: str,
    actor_role: str,
    *,
    item_id: str | None = None,
    expected_revision: int | None = None,
    payload: Mapping[str, Any] | None = None,
    nonce: str = "surface-test-nonce",
    confirmed: bool = False,
) -> ActionIntent:
    spec = ACTION_SPECS.get(action)
    if spec is None:
        raise SurfaceContractError("unknown ClassOps surface action")
    if actor_role not in spec["roles"]:
        raise SurfaceContractError("action is not allowed for this role")

    safe_payload = dict(payload or {})
    assert_safe_payload(safe_payload)

    if spec["revision"]:
        if expected_revision is None or isinstance(expected_revision, bool) or int(expected_revision) < 1:
            raise SurfaceContractError("expectedRevision is required for this action")
        expected_revision = int(expected_revision)
    else:
        expected_revision = None

    if item_id is not None:
        item_id = str(item_id).strip()
        if not item_id or len(item_id) > 96:
            raise SurfaceContractError("itemId is invalid")

    idem = idempotency_key(action, item_id, expected_revision, nonce) if spec["idempotency"] else None
    confirmation_required = bool(spec["confirmation"])
    if confirmed and not confirmation_required:
        confirmed = False

    return ActionIntent(
        action=action,
        actor_role=actor_role,
        item_id=item_id,
        expected_revision=expected_revision,
        idempotency_key=idem,
        confirmation_required=confirmation_required,
        confirmed=bool(confirmed),
        payload=safe_payload,
    )


def confirm_intent(intent: ActionIntent) -> ActionIntent:
    if not intent.confirmation_required:
        return intent
    return ActionIntent(
        action=intent.action,
        actor_role=intent.actor_role,
        item_id=intent.item_id,
        expected_revision=intent.expected_revision,
        idempotency_key=intent.idempotency_key,
        confirmation_required=True,
        confirmed=True,
        payload=intent.payload,
    )


def foundation_capabilities() -> dict[str, Capability]:
    """Capabilities proven by classops-v1 at the declared parallel base.

    This does not claim audience resolution, delivery, AI parsing, specialized
    domain logic, ACK persistence or digest scheduling.
    """
    enabled = {
        "foundation.read", "foundation.create", "foundation.update", "foundation.cancel",
        "foundation.archive", "surface.preview", "ai.preview_request",
    }
    all_names = {spec["capability"] for spec in ACTION_SPECS.values()}
    return {
        name: Capability(name in enabled, "" if name in enabled else "backend-integration-pending")
        for name in sorted(all_names)
    }
