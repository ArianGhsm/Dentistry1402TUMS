from __future__ import annotations

import argparse
import base64
import json

from .api import TelegramBotApi
from .app import DentBotApp
from .booklets import source_records_from_channel_post
from .booklet_sources import configured_source_policies, source_policy_for_channel
from .config import load_settings
from .protected_media import ProtectedMediaDispatcher
from .site_api import SiteApiClient
from .state import BotState


def _decode_caption(value: str) -> str:
    try:
        return base64.b64decode(value, validate=True).decode("utf-8")
    except (ValueError, UnicodeError) as error:
        raise ValueError("Caption must be strict UTF-8 Base64") from error


def sync_existing_source_message(
    *,
    api: TelegramBotApi,
    state: BotState,
    source_channel_id: int,
    owner_id: int,
    message_id: int,
    catalog: dict,
    caption_override: str | None = None,
    allowed_kinds: set[str] | frozenset[str] | None = None,
) -> int:
    """Re-read one source post exactly as Telegram exposes it now.

    This recovers posts that were uploaded before their final caption was
    applied. User-session caption edits are not guaranteed to arrive through
    the Bot API edited-channel-post stream, so the current message must be
    explicitly re-indexable without re-uploading its media.
    """
    temporary_message_id = 0
    try:
        forwarded = dict(api.call("forwardMessage", {
            "chat_id": int(owner_id),
            "from_chat_id": int(source_channel_id),
            "message_id": int(message_id),
            "protect_content": True,
            "disable_notification": True,
        }) or {})
        temporary_message_id = int(forwarded.get("message_id") or 0)
        if temporary_message_id <= 0:
            raise RuntimeError("Source synchronization did not return a temporary message")
        if caption_override is not None:
            forwarded["caption"] = str(caption_override)
        records = source_records_from_channel_post(
            forwarded,
            catalog,
            allowed_kinds=allowed_kinds,
        )
        return state.replace_protected_media_message(
            int(source_channel_id),
            int(message_id),
            records,
        )
    finally:
        if temporary_message_id > 0:
            try:
                api.call("deleteMessage", {
                    "chat_id": int(owner_id),
                    "message_id": temporary_message_id,
                })
            except Exception:
                pass


def register_source_metadata(
    *,
    state: BotState,
    source_channel_id: int,
    message_id: int,
    catalog: dict,
    caption: str,
    media_field: str,
    file_id: str,
    file_unique_id: str = "",
    file_name: str = "",
    mime_type: str = "",
    allowed_kinds: set[str] | frozenset[str] | None = None,
) -> int:
    if media_field not in {"document", "audio", "voice"}:
        raise ValueError("Unsupported Telegram media field")
    message = {
        "caption": str(caption),
        media_field: {
            "file_id": str(file_id),
            "file_unique_id": str(file_unique_id),
            "file_name": str(file_name),
            "mime_type": str(mime_type),
        },
    }
    records = source_records_from_channel_post(
        message,
        catalog,
        allowed_kinds=allowed_kinds,
    )
    return state.replace_protected_media_message(
        int(source_channel_id),
        int(message_id),
        records,
    )


def main() -> int:
    parser = argparse.ArgumentParser(description="Administer caption-derived protected booklet sources.")
    subparsers = parser.add_subparsers(dest="command", required=True)
    subparsers.add_parser("probe")
    subparsers.add_parser("send-owner-test")
    sync = subparsers.add_parser("sync-existing")
    sync.add_argument("--message-id", required=True, type=int)
    sync.add_argument("--caption-base64", default="")
    sync.add_argument("--source-channel-id", type=int, default=0)
    metadata = subparsers.add_parser("register-metadata")
    metadata.add_argument("--message-id", required=True, type=int)
    metadata.add_argument("--source-channel-id", type=int, default=0)
    metadata.add_argument("--caption-base64", required=True)
    metadata.add_argument("--media-field", required=True, choices=("document", "audio", "voice"))
    metadata.add_argument("--file-id", required=True)
    metadata.add_argument("--file-unique-id", default="")
    metadata.add_argument("--file-name", default="")
    metadata.add_argument("--mime-type", default="")
    hydrate = subparsers.add_parser("hydrate-existing")
    hydrate.add_argument("--message-id", required=True, type=int)
    hydrate.add_argument("--source-channel-id", type=int, default=0)
    register = subparsers.add_parser("register-existing")
    register.add_argument("--message-id", required=True, type=int)
    register.add_argument("--source-channel-id", type=int, default=0)
    register.add_argument("--caption-base64", required=True)
    register.add_argument("--file-name", default="")
    register.add_argument("--mime-type", default="application/pdf")
    args = parser.parse_args()

    settings = load_settings()
    if settings.booklet_source_channel_id >= 0:
        raise ValueError("Protected booklet source channel is not configured")

    def selected_policy(raw_source_channel_id: int = 0):
        source_channel_id = int(raw_source_channel_id or settings.booklet_source_channel_id)
        policy = source_policy_for_channel(
            source_channel_id,
            booklet_source_channel_id=settings.booklet_source_channel_id,
            power_source_channel_id=settings.power_source_channel_id,
            booklet_source_channel_title=settings.booklet_source_channel_title,
            power_source_channel_title=settings.power_source_channel_title,
        )
        if policy is None:
            raise ValueError("Source channel is not configured for booklet ingestion")
        return policy

    if args.command == "probe":
        api = TelegramBotApi(settings.token, proxy_url=settings.telegram_proxy_url)
        try:
            me = dict(api.call("getMe") or {})
            sources = []
            for policy in configured_source_policies(settings):
                chat = dict(api.call("getChat", {"chat_id": policy.channel_id}) or {})
                member = dict(api.call("getChatMember", {
                    "chat_id": policy.channel_id,
                    "user_id": int(me.get("id") or 0),
                }) or {})
                item = {
                    "role": policy.role,
                    "reachable": bool(chat.get("id")),
                    "titleMatch": not policy.title or str(chat.get("title") or "") == policy.title,
                    "administrator": str(member.get("status") or "") in {"creator", "administrator"},
                }
                item["ready"] = (
                    item["reachable"]
                    and item["titleMatch"]
                    and item["administrator"]
                )
                sources.append(item)
            result = {
                "ready": bool(sources) and all(bool(item["ready"]) for item in sources),
                "sources": sources,
            }
            print(json.dumps(result, ensure_ascii=False, separators=(",", ":")))
            return 0 if result["ready"] else 2
        finally:
            api.close()

    if args.command == "send-owner-test":
        api = TelegramBotApi(settings.token, proxy_url=settings.telegram_proxy_url)
        state = BotState(settings.state_db, payment_offers_path=settings.payment_offers_db)
        dispatcher = None
        try:
            sources = state.protected_media_for_tag(
                course_tag="گوش_حلق_بینی", term=7, session_no=4, content_kind="booklet"
            )
            sources = [
                item for item in sources
                if int(item.get("sourceChatId") or 0) == settings.booklet_source_channel_id
            ]
            if not sources:
                raise ValueError("ENT session 4 test source is not registered")
            source = sources[-1]
            site_api = SiteApiClient(
                settings.site_api_url,
                settings.site_service_secret,
                platform="telegram",
                timeout=settings.site_timeout_seconds,
                relay_secret=settings.site_relay_secret,
            )
            app = DentBotApp(
                api,
                state,
                owner_id=settings.owner_id,
                site_url=settings.site_url,
                site_api=site_api,
                platform="telegram",
                bot_username=settings.bot_username,
                required_channel_username=settings.required_channel_username,
                booklet_source_channel_id=settings.booklet_source_channel_id,
                power_source_channel_id=settings.power_source_channel_id,
            )
            dispatcher = ProtectedMediaDispatcher(
                api=api,
                state=state,
                authorize=app.booklet_access_allowed,
                identity_provider=site_api.booklet_watermark_identity,
                fingerprint_key=settings.booklet_fingerprint_key,
                watermark_font=settings.booklet_watermark_font,
                temp_root=settings.booklet_temp_root,
                qpdf_binary=settings.booklet_qpdf_binary,
                max_download_bytes=settings.booklet_max_download_bytes,
                workers=1,
                max_queue=4,
                pdf_normalizer=settings.booklet_pdf_normalizer,
                raster_dpi=settings.booklet_raster_dpi,
                raster_jpeg_quality=settings.booklet_raster_jpeg_quality,
                max_output_bytes=settings.booklet_max_output_bytes,
                processing_timeout_seconds=settings.booklet_processing_timeout_seconds,
                orphan_max_age_seconds=settings.booklet_orphan_max_age_seconds,
                rate_window_seconds=settings.booklet_rate_window_seconds,
                rate_max_requests=settings.booklet_rate_max_requests,
                same_document_cooldown_seconds=settings.booklet_same_document_cooldown_seconds,
            )
            status = dispatcher.enqueue(settings.owner_id, int(source["id"]))
            if status != "queued":
                raise RuntimeError(f"Test delivery was not queued: {status}")
            dispatcher.queue.join()
            result = state.latest_protected_media_delivery(settings.owner_id, int(source["id"]))
            if not result or result.get("status") != "sent":
                raise RuntimeError("Protected owner test did not complete successfully")
            print(json.dumps({"success": True, **result}, separators=(",", ":")))
            return 0
        finally:
            if dispatcher is not None:
                dispatcher.close()
            state.close()
            api.close()

    if args.command == "register-metadata":
        policy = selected_policy(args.source_channel_id)
        caption = _decode_caption(args.caption_base64)
        site_api = SiteApiClient(
            settings.site_api_url,
            settings.site_service_secret,
            platform="telegram",
            timeout=settings.site_timeout_seconds,
            relay_secret=settings.site_relay_secret,
        )
        state = BotState(settings.state_db, payment_offers_path=settings.payment_offers_db)
        try:
            catalog = site_api.booklet_catalog(settings.owner_id)
            count = register_source_metadata(
                state=state,
                source_channel_id=policy.channel_id,
                message_id=int(args.message_id),
                catalog=catalog,
                caption=caption,
                media_field=str(args.media_field),
                file_id=str(args.file_id),
                file_unique_id=str(args.file_unique_id),
                file_name=str(args.file_name),
                mime_type=str(args.mime_type),
                allowed_kinds=policy.allowed_kinds,
            )
            print(json.dumps({
                "success": count > 0,
                "messageId": int(args.message_id),
                "routes": count,
            }, separators=(",", ":")))
            return 0 if count > 0 else 2
        finally:
            state.close()

    if args.command == "sync-existing":
        policy = selected_policy(args.source_channel_id)
        api = TelegramBotApi(settings.token, proxy_url=settings.telegram_proxy_url)
        state = BotState(settings.state_db, payment_offers_path=settings.payment_offers_db)
        site_api = SiteApiClient(
            settings.site_api_url,
            settings.site_service_secret,
            platform="telegram",
            timeout=settings.site_timeout_seconds,
            relay_secret=settings.site_relay_secret,
        )
        try:
            catalog = site_api.booklet_catalog(settings.owner_id)
            count = sync_existing_source_message(
                api=api,
                state=state,
                source_channel_id=policy.channel_id,
                owner_id=settings.owner_id,
                message_id=int(args.message_id),
                catalog=catalog,
                caption_override=(
                    _decode_caption(args.caption_base64)
                    if str(args.caption_base64 or "").strip()
                    else None
                ),
                allowed_kinds=policy.allowed_kinds,
            )
            print(json.dumps({
                "success": count > 0,
                "messageId": int(args.message_id),
                "routes": count,
            }, separators=(",", ":")))
            return 0 if count > 0 else 2
        finally:
            state.close()
            api.close()

    if args.command == "hydrate-existing":
        policy = selected_policy(args.source_channel_id)
        api = TelegramBotApi(settings.token, proxy_url=settings.telegram_proxy_url)
        state = BotState(settings.state_db, payment_offers_path=settings.payment_offers_db)
        temporary_message_id = 0
        try:
            forwarded = dict(api.call("forwardMessage", {
                "chat_id": settings.owner_id,
                "from_chat_id": policy.channel_id,
                "message_id": int(args.message_id),
                "protect_content": True,
                "disable_notification": True,
            }) or {})
            temporary_message_id = int(forwarded.get("message_id") or 0)
            media = dict(
                forwarded.get("document")
                or forwarded.get("audio")
                or forwarded.get("voice")
                or {}
            )
            file_id = str(media.get("file_id") or "")
            if not file_id:
                raise RuntimeError("Historical source did not expose reusable Bot API file metadata")
            if not temporary_message_id:
                raise RuntimeError("Historical source hydration did not return a temporary message")
            api.call("deleteMessage", {
                "chat_id": settings.owner_id,
                "message_id": temporary_message_id,
            })
            temporary_message_id = 0
            updated = state.update_protected_media_file(
                policy.channel_id,
                int(args.message_id),
                file_id=file_id,
                file_unique_id=str(media.get("file_unique_id") or ""),
                file_name=str(media.get("file_name") or ""),
                mime_type=str(media.get("mime_type") or ""),
            )
            if updated <= 0:
                raise RuntimeError("Historical source is not registered")
            print(json.dumps({"success": True, "routes": updated}, separators=(",", ":")))
            return 0
        finally:
            if temporary_message_id:
                try:
                    api.call("deleteMessage", {
                        "chat_id": settings.owner_id,
                        "message_id": temporary_message_id,
                    })
                except Exception:
                    pass
            state.close()
            api.close()

    policy = selected_policy(args.source_channel_id)
    caption = _decode_caption(args.caption_base64)
    site_api = SiteApiClient(
        settings.site_api_url,
        settings.site_service_secret,
        platform="telegram",
        timeout=settings.site_timeout_seconds,
        relay_secret=settings.site_relay_secret,
    )
    catalog = site_api.booklet_catalog(settings.owner_id)
    records = source_records_from_channel_post(
        {
            "caption": caption,
            "document": {
                "file_name": str(args.file_name),
                "mime_type": str(args.mime_type),
            },
        },
        catalog,
        allowed_kinds=policy.allowed_kinds,
    )
    if not records:
        raise ValueError("Existing source caption did not produce a valid route")
    state = BotState(settings.state_db, payment_offers_path=settings.payment_offers_db)
    try:
        count = state.replace_protected_media_message(
            policy.channel_id,
            int(args.message_id),
            records,
        )
    finally:
        state.close()
    print(json.dumps({"success": True, "routes": count}, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
