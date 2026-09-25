from __future__ import annotations

from dataclasses import dataclass
from typing import FrozenSet

from .ai_booklets import AI_BOOKLET_CONTENT_KIND


PRIVATE_SOURCE_CONTENT_KINDS: FrozenSet[str] = frozenset({
    "voice",
    "booklet",
    "reference",
    AI_BOOKLET_CONTENT_KIND,
})
POWER_SOURCE_CONTENT_KINDS: FrozenSet[str] = frozenset({"power"})


@dataclass(frozen=True)
class BookletSourcePolicy:
    role: str
    channel_id: int
    title: str
    allowed_kinds: FrozenSet[str]


def source_policy_for_channel(
    source_chat_id: int,
    *,
    booklet_source_channel_id: int,
    power_source_channel_id: int,
    booklet_source_channel_title: str = "",
    power_source_channel_title: str = "",
) -> BookletSourcePolicy | None:
    source_chat_id = int(source_chat_id)
    private_id = int(booklet_source_channel_id)
    power_id = int(power_source_channel_id)
    if source_chat_id < 0 and source_chat_id == private_id:
        return BookletSourcePolicy(
            role="private",
            channel_id=private_id,
            title=str(booklet_source_channel_title or ""),
            allowed_kinds=PRIVATE_SOURCE_CONTENT_KINDS,
        )
    if source_chat_id < 0 and source_chat_id == power_id:
        return BookletSourcePolicy(
            role="power",
            channel_id=power_id,
            title=str(power_source_channel_title or ""),
            allowed_kinds=POWER_SOURCE_CONTENT_KINDS,
        )
    return None


def configured_source_policies(settings: object) -> tuple[BookletSourcePolicy, ...]:
    private_id = int(getattr(settings, "booklet_source_channel_id", 0) or 0)
    power_id = int(getattr(settings, "power_source_channel_id", 0) or 0)
    policies: list[BookletSourcePolicy] = []
    if private_id < 0:
        policy = source_policy_for_channel(
            private_id,
            booklet_source_channel_id=private_id,
            power_source_channel_id=power_id,
            booklet_source_channel_title=str(
                getattr(settings, "booklet_source_channel_title", "") or ""
            ),
            power_source_channel_title=str(
                getattr(settings, "power_source_channel_title", "") or ""
            ),
        )
        if policy is not None:
            policies.append(policy)
    if power_id < 0 and power_id != private_id:
        policy = source_policy_for_channel(
            power_id,
            booklet_source_channel_id=private_id,
            power_source_channel_id=power_id,
            booklet_source_channel_title=str(
                getattr(settings, "booklet_source_channel_title", "") or ""
            ),
            power_source_channel_title=str(
                getattr(settings, "power_source_channel_title", "") or ""
            ),
        )
        if policy is not None:
            policies.append(policy)
    return tuple(policies)
