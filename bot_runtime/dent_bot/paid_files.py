from __future__ import annotations

from pathlib import Path


_MEDIA_SPECS = (
    ("document", "sendDocument", "فایل"),
    ("audio", "sendAudio", "فایل صوتی"),
    ("voice", "sendVoice", "پیام صوتی"),
    ("video", "sendVideo", "ویدئو"),
    ("animation", "sendAnimation", "انیمیشن"),
    ("video_note", "sendVideoNote", "ویدئوی دایره‌ای"),
    ("sticker", "sendSticker", "استیکر"),
)

_DEFAULT_FILENAMES = {
    "sendAudio": "audio",
    "sendVoice": "voice.ogg",
    "sendVideo": "video.mp4",
    "sendAnimation": "animation.mp4",
    "sendPhoto": "photo.jpg",
    "sendVideoNote": "video-note.mp4",
    "sendSticker": "sticker.webp",
}


def _clean_text(value: object, limit: int) -> str:
    return " ".join(str(value or "").split())[:limit]


def _photo_source(message: dict) -> tuple[dict, str, str] | None:
    photos = [item for item in (message.get("photo") or []) if isinstance(item, dict)]
    if not photos:
        return None
    media = max(
        photos,
        key=lambda item: (
            int(item.get("file_size") or 0),
            int(item.get("width") or 0) * int(item.get("height") or 0),
        ),
    )
    return media, "sendPhoto", "تصویر"


def extract_paid_file_source(message: dict) -> dict | None:
    """Extract bounded Telegram metadata for one owner-uploaded sale asset."""
    selected: tuple[dict, str, str] | None = None
    for field, method, label in _MEDIA_SPECS:
        raw = message.get(field)
        if isinstance(raw, dict):
            selected = raw, method, label
            break
    if selected is None:
        selected = _photo_source(message)
    if selected is None:
        return None

    media, method, label = selected
    file_id = str(media.get("file_id") or "").strip()
    if not file_id:
        return None
    file_unique_id = str(media.get("file_unique_id") or "").strip()[:160]
    file_size = max(0, int(media.get("file_size") or 0))
    file_name = _clean_text(media.get("file_name"), 180)
    if not file_name:
        file_name = _DEFAULT_FILENAMES.get(method, "file")
    mime_type = _clean_text(media.get("mime_type"), 120)
    if not mime_type and method == "sendPhoto":
        mime_type = "image/jpeg"

    chat = message.get("chat") if isinstance(message.get("chat"), dict) else {}
    if str(chat.get("type") or "") != "private":
        return None
    source_chat_id = int(chat.get("id") or 0)
    source_message_id = int(message.get("message_id") or 0)
    if source_chat_id <= 0 or source_message_id <= 0:
        return None

    return {
        "sourcePlatform": "telegram",
        "sourceChatId": source_chat_id,
        "sourceMessageId": source_message_id,
        "telegramMethod": method,
        "fileId": file_id,
        "fileUniqueId": file_unique_id,
        "fileName": file_name,
        "mimeType": mime_type,
        "fileSize": file_size,
        "caption": _clean_text(message.get("caption"), 900),
        "mediaLabel": label,
        "isPdf": (
            method == "sendDocument"
            and (
                mime_type.lower() == "application/pdf"
                or Path(file_name).suffix.lower() == ".pdf"
            )
        ),
    }


def paid_file_display_name(asset: dict) -> str:
    name = _clean_text(asset.get("fileName"), 180)
    return name or _clean_text(asset.get("mediaLabel"), 80) or "فایل"
