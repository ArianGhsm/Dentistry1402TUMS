from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[2]
ACCOUNT = ROOT / "public_html/assets/site/scripts/account.js"
UTILS = ROOT / "public_html/assets/site/scripts/account-utils.js"
HTML = ROOT / "public_html/account/index.html"
NAMES = [
    "normalizeDigits", "toPersianDigits", "normalizedPhone", "isValidIranMobile",
    "escapeHtml", "toNumber", "nowSeconds", "secondsRemaining", "formatSeconds",
    "parseTimestampLike", "formatJalaliDateTime", "ltrIsolateText", "ltrMaskedPhone",
    "userDisNumber",
]

def test_account_utility_module_is_loaded_before_account_shell():
    html = HTML.read_text(encoding="utf-8")
    utils_pos = html.index("account-utils.js?v=20260916-p4-notifications1")
    analytics_pos = html.index("account-owner-analytics.js?v=20260916-p4-notifications1")
    notifications_pos = html.index("account-notifications.js?v=20260916-p4-notifications1")
    account_pos = html.index("account.js?v=20260916-p4-notifications1")
    assert utils_pos < analytics_pos < notifications_pos < account_pos

def test_pure_utilities_live_only_in_dedicated_module():
    account = ACCOUNT.read_text(encoding="utf-8-sig")
    utils = UTILS.read_text(encoding="utf-8")
    for name in NAMES:
        assert f"function {name}(" not in account
        assert f"function {name}(" in utils
        assert f"var {name} = accountUtils.{name};" in account
        assert f"{name}: {name}" in utils

def test_account_utils_runtime_contract():
    js = r'''const fs=require("fs"),vm=require("vm");const c={window:{}};vm.createContext(c);vm.runInContext(fs.readFileSync(process.argv[1],"utf8"),c);const u=c.window.Dent1402AccountUtils;if(!u)process.exit(2);const ok=[u.normalizeDigits("۰۹۱۲٣٤٥٦٧٨٩")==="09123456789",u.toPersianDigits("1405")==="۱۴۰۵",u.normalizedPhone("+989121234567")==="09121234567",u.isValidIranMobile("09121234567")===true,u.escapeHtml("<a&>")==="&lt;a&amp;&gt;",u.toNumber("12.5",0)===12.5,u.formatSeconds(125)==="02:05",u.ltrIsolateText("0912")==="\u20660912\u2069",u.userDisNumber({disNumber:" ۴۲ "})==="۴۲"];if(ok.some(v=>!v))process.exit(3);'''
    result = subprocess.run(["node", "-e", js, str(UTILS)], cwd=ROOT)
    assert result.returncode == 0
