from __future__ import annotations

import hashlib
import json
from typing import Mapping, Sequence


RETRY_UNROUTED_SECONDS = 15 * 60
RETRY_FAILED_SECONDS = 60


def source_fingerprint(
    *,
    message_id: int,
    date: str,
    edit_date: str,
    text: str,
    file_name: str,
    file_size: int,
    mime_type: str,
    grouped_id: str = "",
) -> str:
    payload = {
        "messageId": int(message_id),
        "date": str(date or ""),
        "editDate": str(edit_date or ""),
        "text": str(text or ""),
        "fileName": str(file_name or ""),
        "fileSize": int(file_size or 0),
        "mimeType": str(mime_type or ""),
        "groupedId": str(grouped_id or ""),
    }
    raw = json.dumps(
        payload,
        ensure_ascii=False,
        sort_keys=True,
        separators=(",", ":"),
    ).encode("utf-8")
    return hashlib.sha256(raw).hexdigest()


def album_caption_overrides(
    rows: Sequence[Mapping[str, object]],
) -> dict[int, str]:
    """Return safe shared captions for captionless media-album members.

    Telegram commonly attaches an album caption to only one message. Reuse it
    only when the album has exactly one distinct non-empty caption; ambiguous
    albums deliberately fail closed.
    """
    captions: dict[str, set[str]] = {}
    for row in rows:
        grouped_id = str(row.get("groupedId") or "").strip()
        text = str(row.get("text") or "").strip()
        if grouped_id and text:
            captions.setdefault(grouped_id, set()).add(text)

    unique = {
        grouped_id: next(iter(values))
        for grouped_id, values in captions.items()
        if len(values) == 1
    }
    overrides: dict[int, str] = {}
    for row in rows:
        grouped_id = str(row.get("groupedId") or "").strip()
        text = str(row.get("text") or "").strip()
        message_id = int(row.get("messageId") or 0)
        if message_id > 0 and not text and grouped_id in unique:
            overrides[message_id] = unique[grouped_id]
    return overrides


def should_reconcile(
    previous: Mapping[str, object] | None,
    fingerprint: str,
    *,
    now: int,
) -> bool:
    if not previous:
        return True
    if str(previous.get("fingerprint") or "") != str(fingerprint):
        return True
    status = str(previous.get("status") or "")
    if status == "routed":
        return False
    return int(previous.get("nextRetryAt") or 0) <= int(now)


def reconciliation_record(
    fingerprint: str,
    *,
    status: str,
    now: int,
) -> dict[str, object]:
    if status not in {"routed", "unrouted", "failed"}:
        raise ValueError("Unsupported booklet reconciliation status")
    retry = 0
    if status == "unrouted":
        retry = int(now) + RETRY_UNROUTED_SECONDS
    elif status == "failed":
        retry = int(now) + RETRY_FAILED_SECONDS
    return {
        "fingerprint": str(fingerprint),
        "status": status,
        "checkedAt": int(now),
        "nextRetryAt": retry,
    }
