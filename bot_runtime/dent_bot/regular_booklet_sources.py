"""Canonical ordinary booklet selection shared by interactive and paid delivery."""
from __future__ import annotations

from .booklets import course_by_key

def course_regular_booklets(state, catalog: dict, course_key: str) -> list[dict]:
    """Read current ordinary booklets only, in syllabus order, without repeats."""
    course = course_by_key(catalog, course_key)
    if course is None:
        return []
    course_tag = str(course.get("bookletTag") or "").strip()
    term = int(catalog.get("term") or 0)
    if not course_tag or term != 7:
        return []
    valid_sessions = {
        int(item.get("sessionNumber") or 0)
        for item in course.get("sessions", [])
        if isinstance(item, dict) and 1 <= int(item.get("sessionNumber") or 0) <= 40
    }
    available = state.protected_media_session_counts_for_tag(
        course_tag=course_tag, term=term, content_kind="booklet",
    )
    sources: list[dict] = []
    seen: set[str] = set()
    for session_no in sorted(valid_sessions & set(available)):
        for source in state.protected_media_for_tag(
            course_tag=course_tag, term=term,
            session_no=session_no, content_kind="booklet",
        ):
            if not isinstance(source, dict):
                continue
            if (
                str(source.get("contentKind") or "") != "booklet"
                or str(source.get("courseTag") or "") != course_tag
                or int(source.get("term") or 0) != term
                or int(source.get("sessionNo") or 0) != session_no
                or int(source.get("id") or 0) <= 0
                or int(source.get("sourceChatId") or 0) >= 0
                or int(source.get("sourceMessageId") or 0) <= 0
                or str(source.get("telegramMethod") or "") != "sendDocument"
                or not str(source.get("fileId") or "").strip()
                or not (
                    str(source.get("mimeType") or "").lower() == "application/pdf"
                    or str(source.get("fileName") or "").lower().endswith(".pdf")
                )
            ):
                continue
            unique = str(source.get("fileUniqueId") or "").strip()
            key = unique or (
                f"{int(source['sourceChatId'])}:{int(source['sourceMessageId'])}"
            )
            if key in seen:
                continue
            seen.add(key)
            sources.append(source)
    return sources
