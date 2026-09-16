from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
API = ROOT / "public_html/chat/chat_api.php"
MEDIA = ROOT / "public_html/chat/chat_media.php"

MEDIA_FUNCTIONS = ['chat_media_root_path', 'chat_media_originals_path', 'chat_media_previews_path', 'chat_media_cleanup_log_path', 'chat_media_target_bytes', 'chat_public_media_url', 'chat_clean_attachment_id', 'chat_clean_media_variant', 'chat_parse_attachment_ids_input', 'chat_safe_extension', 'chat_is_blocked_upload_extension', 'chat_attachment_category_for_mime', 'chat_clean_media_relative_path', 'chat_attachment_category_label', 'chat_normalize_attachment_record', 'chat_get_attachment', 'chat_put_attachment', 'chat_log_media_cleanup', 'chat_mark_attachment_expired', 'chat_attachment_size_bytes', 'chat_sync_attachment_file_flags', 'chat_delete_attachment_original_file', 'chat_delete_attachment_artifacts', 'chat_sync_media_health', 'chat_media_status_payload', 'chat_attachment_absolute_path', 'chat_attachment_payload', 'chat_message_attachments_payload', 'chat_attachment_access_allowed', 'chat_safe_download_filename', 'chat_stream_attachment_file', 'chat_collect_attachable_ids', 'chat_clone_attachment_for_forward', 'chat_uploaded_file_error_message', 'chat_detect_file_mime', 'chat_voice_audio_mime', 'chat_generate_image_preview', 'chat_exec_available', 'chat_ffmpeg_binary', 'chat_generate_video_preview', 'chat_store_uploaded_attachment']


def test_chat_api_loads_media_module_once():
    source = API.read_text(encoding="utf-8")
    assert "require_once __DIR__ . '/chat_media.php';" in source
    assert source.count("chat_media.php") == 1


def test_media_functions_live_in_dedicated_module():
    central = API.read_text(encoding="utf-8")
    module = MEDIA.read_text(encoding="utf-8")
    for name in MEDIA_FUNCTIONS:
        assert f"function {name}(" not in central
        assert f"function {name}(" in module


def test_media_actions_remain_explicit_in_api_dispatch():
    source = API.read_text(encoding="utf-8")
    for action in ["media", "uploadAttachment", "mediaStatus", "mediaCleanupNow"]:
        assert f"$action === '{action}'" in source
