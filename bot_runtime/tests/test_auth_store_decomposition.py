from __future__ import annotations

from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]


def test_auth_store_persistence_is_extracted_without_duplicate_definitions() -> None:
    auth = (ROOT / "public_html/api/auth_store.php").read_text(encoding="utf-8")
    persistence = (ROOT / "public_html/api/auth_store_persistence.php").read_text(encoding="utf-8")

    include = "require_once __DIR__ . '/auth_store_persistence.php';"
    assert include in auth
    assert auth.index(include) < auth.index("function dent_public_html_path")
    for name in (
        "dent_auth_store_path",
        "dent_auth_store_backup_path",
        "dent_auth_store_seed_payload",
        "dent_auth_store_runtime_cache",
        "dent_decode_auth_store_snapshot",
        "dent_write_auth_store_payload",
    ):
        signature = f"function {name}"
        assert signature in persistence
        assert signature not in auth
