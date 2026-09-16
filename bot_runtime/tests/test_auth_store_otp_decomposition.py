from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
AUTH = ROOT / "public_html/api/auth_store.php"
OTP = ROOT / "public_html/api/auth_store_otp.php"


def test_auth_store_loads_otp_engine_once():
    source = AUTH.read_text(encoding="utf-8")
    assert "require_once __DIR__ . '/auth_store_otp.php';" in source
    assert source.count("auth_store_otp.php") == 1


def test_provider_neutral_otp_engine_is_extracted():
    central = AUTH.read_text(encoding="utf-8")
    module = OTP.read_text(encoding="utf-8")
    names = [
        "dent_otp_ttl_seconds",
        "dent_otp_cooldown_seconds",
        "dent_otp_cleanup_records",
        "dent_issue_otp_for_phone",
        "dent_verify_otp_for_phone",
    ]
    for name in names:
        assert f"function {name}(" not in central
        assert f"function {name}(" in module


def test_account_specific_otp_wrappers_stay_in_central_store():
    central = AUTH.read_text(encoding="utf-8")
    for name in [
        "dent_request_phone_enrollment_otp",
        "dent_request_external_signup_otp",
        "dent_request_login_otp",
        "dent_request_password_reset_otp",
        "dent_clear_phone_related_otp_records",
    ]:
        assert f"function {name}(" in central
