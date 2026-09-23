from __future__ import annotations

import hashlib


AI_BOOKLET_CONTENT_KIND = "ai_booklet"
AI_BOOKLET_PRICE_RIALS = 390_000
AI_BOOKLET_SOURCE_TAG_KEY = "جزوههوشمصنوعی"


def ai_booklet_offer_ref(term: int, course_code: str, session_no: int) -> str:
    term_value = int(term)
    course = str(course_code or "").strip()
    session = int(session_no)
    if not 1 <= term_value <= 12 or not course or not 1 <= session <= 40:
        raise ValueError("Invalid AI booklet product")
    digest = hashlib.sha256(f"{term_value}:{course}:{session}".encode("utf-8")).hexdigest()[:32]
    return f"ai-booklet-{digest}"


def ai_booklet_request_id(
    *,
    platform: str,
    platform_user_id: int,
    subject_key: str,
    term: int,
    course_code: str,
    session_no: int,
) -> str:
    platform_value = str(platform or "").strip()
    subject = str(subject_key or "").strip()
    term_value = int(term)
    course = str(course_code or "").strip()
    session = int(session_no)
    if (
        not platform_value
        or int(platform_user_id) <= 0
        or not subject
        or not 1 <= term_value <= 12
        or not course
        or not 1 <= session <= 40
    ):
        raise ValueError("Invalid AI booklet checkout identity")
    raw = (
        f"ai-booklet:{platform_value}:{int(platform_user_id)}:{subject}:"
        f"{term_value}:{course}:{session}"
    )
    return hashlib.sha256(raw.encode("utf-8")).hexdigest()
