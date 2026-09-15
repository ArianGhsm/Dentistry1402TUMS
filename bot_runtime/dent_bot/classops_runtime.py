from __future__ import annotations

import html
import logging
import os
import threading
import time
from typing import Any

from .site_api import SiteApiError



def _env_bool(name: str, default: bool = False) -> bool:
    raw = os.getenv(name)
    if raw is None or not raw.strip():
        return default
    return raw.strip().lower() in {"1", "true", "yes", "on"}


def _platform_enabled(platform: str) -> bool:
    return _env_bool(f"DENT_CLASSOPS_{platform.upper()}_ENABLED", False)


def _destination_chat_id(platform: str, binding_ref: str) -> int | None:
    names = {
        "class_group": f"DENT_CLASSOPS_{platform.upper()}_CLASS_GROUP_ID",
        "information_channel": f"DENT_CLASSOPS_{platform.upper()}_INFORMATION_CHANNEL_ID",
    }
    env_name = names.get(binding_ref)
    if not env_name:
        return None
    raw = (os.getenv(env_name) or "").strip()
    try:
        value = int(raw)
    except (TypeError, ValueError):
        return None
    return value if value != 0 else None


def _safe_text(value: object, limit: int = 3500) -> str:
    text = str(value or "").replace("\x00", "").strip()
    return html.escape(text[:limit])


def _message_text(message: dict[str, Any]) -> str:
    title = _safe_text(message.get("title"), 180)
    body = _safe_text(message.get("body"), 3300)
    parts: list[str] = []
    if title:
        parts.append(f"<b>{title}</b>")
    if body:
        parts.append(body)
    return "\n\n".join(parts) or "<b>امور کلاس</b>"


def dispatch_classops_delivery_batch(*, settings, api, state, site_api) -> dict[str, int]:
    counts = {"claimed": 0, "sent": 0, "acknowledged": 0, "failed": 0}
    platform = str(getattr(settings, "platform", "")).strip().lower()
    if platform not in {"telegram", "bale"} or not _platform_enabled(platform):
        return counts
    owner_id = int(getattr(settings, "owner_id", 0) or 0)
    if owner_id == 0:
        return counts
    result = site_api.request(
        "classopsDeliveryClaim",
        owner_id,
        limit=max(1, min(20, int(os.getenv("DENT_CLASSOPS_DELIVERY_BATCH_SIZE", "10") or "10"))),
    )
    deliveries = [row for row in result.get("deliveries", []) if isinstance(row, dict)]
    counts["claimed"] = len(deliveries)
    for delivery in deliveries:
        intent_id = str(delivery.get("intentId") or "")
        destination = dict(delivery.get("destination") or {})
        binding_ref = str(destination.get("bindingRef") or "")
        destination_platform = str(destination.get("platform") or "")
        receipt_id = f"classops-direct:{intent_id}"
        if (
            not intent_id.startswith("cdi_")
            or destination_platform != platform
            or binding_ref not in {"class_group", "information_channel"}
        ):
            counts["failed"] += 1
            if intent_id:
                try:
                    site_api.request(
                        "classopsDeliveryAck", owner_id, intentId=intent_id,
                        delivered=False, reasonCode="INVALID_DELIVERY",
                    )
                except SiteApiError:
                    pass
            continue
        if state.has_notification_delivery(receipt_id):
            site_api.request("classopsDeliveryAck", owner_id, intentId=intent_id, delivered=True, reasonCode="")
            counts["acknowledged"] += 1
            continue
        chat_id = _destination_chat_id(platform, binding_ref)
        if chat_id is None:
            counts["failed"] += 1
            try:
                site_api.request(
                    "classopsDeliveryAck", owner_id, intentId=intent_id,
                    delivered=False, reasonCode="DESTINATION_UNCONFIGURED",
                )
            except SiteApiError:
                pass
            continue
        try:
            api.send(chat_id, _message_text(dict(delivery.get("message") or {})), {"inline_keyboard": []})
            # Persist locally before server ACK. If ACK transport fails, a lease
            # retry is acknowledged without duplicating the external message.
            state.mark_notification_delivery(receipt_id)
            counts["sent"] += 1
            site_api.request("classopsDeliveryAck", owner_id, intentId=intent_id, delivered=True, reasonCode="")
            counts["acknowledged"] += 1
        except Exception as error:
            counts["failed"] += 1
            if error.__class__.__name__ not in {"BotApiError", "SiteApiError"}:
                logging.exception("ClassOps direct delivery failed unexpectedly")
            try:
                site_api.request(
                    "classopsDeliveryAck", owner_id, intentId=intent_id,
                    delivered=False, reasonCode="BOT_SEND_FAILED",
                )
            except SiteApiError:
                pass
    return counts


def run_classops_background_loop(*, settings, api, state, site_api, platform_name: str, stop_event: threading.Event) -> None:
    platform = str(getattr(settings, "platform", "")).strip().lower()
    owner_id = int(getattr(settings, "owner_id", 0) or 0)
    next_delivery = 0.0
    next_scheduler = 0.0
    poll_seconds = max(5, min(300, int(os.getenv("DENT_CLASSOPS_RUNTIME_POLL_SECONDS", "15") or "15")))
    scheduler_seconds = max(15, min(300, int(os.getenv("DENT_CLASSOPS_SCHEDULER_POLL_SECONDS", "30") or "30")))
    while not stop_event.is_set():
        now = time.monotonic()
        if now >= next_delivery:
            try:
                counts = dispatch_classops_delivery_batch(settings=settings, api=api, state=state, site_api=site_api)
                if counts["claimed"]:
                    logging.info(
                        "%s ClassOps direct claimed=%s sent=%s acknowledged=%s failed=%s",
                        platform_name, counts["claimed"], counts["sent"], counts["acknowledged"], counts["failed"],
                    )
            except SiteApiError as error:
                logging.warning("%s ClassOps direct delivery unavailable code=%s", platform_name, error.code)
            next_delivery = time.monotonic() + poll_seconds
        now = time.monotonic()
        if now >= next_scheduler and owner_id != 0 and _platform_enabled(platform):
            try:
                # Both transports may request a tick; the website coordinator
                # lease elects exactly one execution path for external effects.
                result = site_api.request("classopsSchedulerTick", owner_id)
                if bool(result.get("claimed")):
                    logging.info("%s ClassOps scheduler coordinator tick completed", platform_name)
            except SiteApiError as error:
                logging.warning("%s ClassOps scheduler unavailable code=%s", platform_name, error.code)
            next_scheduler = time.monotonic() + scheduler_seconds
        stop_event.wait(1)
