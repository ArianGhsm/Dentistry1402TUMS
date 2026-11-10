"""Deliver requested ordinary booklets only after canonical paid entitlement.

Requests live in the bot's durable runtime state. The syllabus and media DB
remain the sole sources of filenames, session membership and delivery access.
"""
from __future__ import annotations

import json
import logging

from .regular_booklet_sources import course_regular_booklets
from .subscriptions import subscription_identity_from_account
from .persian_datetime import to_persian_digits


REQUEST_PREFIX = "subscription-auto-booklets:"


def dispatch_verified_subscription_booklets(*, settings, api, state, site_api, media_dispatcher) -> dict[str, int]:
    counts = {"pending": 0, "queued": 0, "deferred": 0}
    if str(settings.platform) != "telegram" or media_dispatcher is None:
        return counts
    for key, raw in state.runtime_values_with_prefix(REQUEST_PREFIX).items():
        try:
            request = json.loads(raw)
            if not isinstance(request, dict) or request.get("status") != "pending":
                continue
            user_id = int(request.get("userId") or 0)
            term = int(request.get("term") or 0)
            max_session = int(request.get("maxSession") or 0)
            course_key = str(request.get("courseKey") or "")
            if (user_id <= 0 or str(request.get("platform")) != settings.platform
                    or not 1 <= term <= 12 or not 1 <= max_session <= 40
                    or not course_key or key != f"{REQUEST_PREFIX}{settings.platform}:{user_id}"):
                continue
        except (ValueError, TypeError, OverflowError):
            logging.warning("invalid subscription booklet request; ignored")
            continue
        counts["pending"] += 1
        account = site_api.account(user_id)
        identity = subscription_identity_from_account(account)
        if identity is None or identity.subject_key != str(request.get("subjectKey") or ""):
            counts["deferred"] += 1
            continue
        decision = state.term_access_decision(identity.subject_key, term)
        if decision.get("paidValid") is not True:
            # A checkout, gateway redirect or free gift is not a paid entitlement.
            counts["deferred"] += 1
            continue
        catalog = site_api.booklet_catalog(user_id)
        if int(catalog.get("term") or 0) != term:
            counts["deferred"] += 1
            continue
        sources = [
            s for s in course_regular_booklets(state, catalog, course_key)
            if 1 <= int(s.get("sessionNo") or 0) <= max_session
        ]
        covered = {int(s["sessionNo"]) for s in sources}
        if len(covered) != max_session:
            counts["deferred"] += 1
            continue
        statuses = media_dispatcher.enqueue_batch(
            user_id, [("booklet", int(s["id"])) for s in sources]
        )
        if not statuses or not all(x in {"queued", "duplicate"} for x in statuses):
            counts["deferred"] += 1
            continue
        # Persist before the informational message; delivery is deduplicated by
        # the existing media dispatcher and survives an informational send error.
        state.set_runtime_value(key, json.dumps({
            **request, "status": "queued", "sourceCount": len(sources),
        }, ensure_ascii=False))
        counts["queued"] += len(sources)
        try:
            api.send(
                user_id,
                "<b>✅ ارسال جزوه‌های میان‌ترم آغاز شد</b>\n\n"
                f"{to_persian_digits(len(sources))} جزوهٔ عادی مبانی پروتز پارسیل، "
                "پس از تأیید پرداخت و فعال‌شدن اشتراک، به صف ارسال امن اضافه شد. "
                "فایل‌ها به‌ترتیب جلسات در همین گفت‌وگو ارسال می‌شوند.",
                {"inline_keyboard": [[{
                    "text": "📚 مشاهدهٔ جلسات پارسیل",
                    "callback_data": f"v1:booklet-course:{course_key}",
                }]]},
            )
        except Exception:
            logging.warning("subscription booklet queued; informational message could not be sent")
    return counts
