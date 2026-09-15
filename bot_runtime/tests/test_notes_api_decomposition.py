from __future__ import annotations

from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]


def test_notes_direct_upload_workflow_is_extracted_once() -> None:
    notes = (ROOT / "public_html/api/notes_api.php").read_text(encoding="utf-8")
    direct = (ROOT / "public_html/api/notes_direct_upload.php").read_text(encoding="utf-8")

    include = "require_once __DIR__ . '/notes_direct_upload.php';"
    assert include in notes
    assert notes.index(include) < notes.index("function notes_1402_term_template")
    for name in (
        "notes_direct_upload_store_path",
        "notes_direct_upload_normalize_store",
        "notes_direct_upload_with_store_lock",
        "notes_direct_upload_build_file_payload",
        "notes_download_host_target_context",
        "notes_prepare_host_upload_plan",
    ):
        signature = f"function {name}"
        assert signature in direct
        assert signature not in notes
