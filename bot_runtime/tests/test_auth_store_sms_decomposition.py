from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
AUTH = ROOT / "public_html/api/auth_store.php"
SMS = ROOT / "public_html/api/auth_store_sms.php"


def test_auth_store_loads_sms_module_once():
    source = AUTH.read_text(encoding="utf-8")
    assert "require_once __DIR__ . '/auth_store_sms.php';" in source
    assert source.count("auth_store_sms.php") == 1


def test_sms_provider_functions_live_in_sms_module_not_central_store():
    central = AUTH.read_text(encoding="utf-8")
    module = SMS.read_text(encoding="utf-8")
    names = [
        "dent_sms_env_bool",
        "dent_sms_resolved_config",
        "dent_sms_send_pattern",
        "dent_sms_parse_pattern_response",
        "dent_sms_send_simple",
        "dent_sms_health_check",
    ]
    for name in names:
        assert f"function {name}(" not in central
        assert f"function {name}(" in module


def test_sms_module_coexists_with_dedicated_otp_engine():
    central = AUTH.read_text(encoding="utf-8")
    otp = (ROOT / "public_html/api/auth_store_otp.php").read_text(encoding="utf-8")
    assert "require_once __DIR__ . '/auth_store_otp.php';" in central
    assert "function dent_issue_otp_for_phone(" not in central
    assert "function dent_verify_otp_for_phone(" not in central
    assert "function dent_issue_otp_for_phone(" in otp
    assert "function dent_verify_otp_for_phone(" in otp
    assert "function dent_request_login_otp(" in central
