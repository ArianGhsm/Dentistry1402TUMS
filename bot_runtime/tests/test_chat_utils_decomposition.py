from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[2]
CHAT = ROOT / "public_html/assets/site/scripts/chat.js"
UTILS = ROOT / "public_html/assets/site/scripts/chat-utils.js"
HTML = ROOT / "public_html/chat/index.html"
NAMES = ['asObject', 'toText', 'escapeHtml', 'formatNavBadgeCount', 'normalizeSpace', 'normalizeDigits', 'normalizeStudentNumber', 'avatarLabel', 'snippet', 'clamp', 'toNumber', 'formatTime', 'formatClock', 'formatDate', 'formatDateTime', 'formatLastSeenLabel', 'dayKeyFromTimestamp', 'formatFileSize', 'formatDuration', 'voiceSpeedLabel', 'stableHashSeed', 'seededWaveformSamples', 'normalizeAttachmentCategory', 'attachmentCategoryLabel', 'isGeneratedMessagePlaceholder', 'meaningfulMessageText', 'normalizeAvatarUrl']

def test_chat_utility_module_is_loaded_before_chat_shell():
    html = HTML.read_text(encoding="utf-8")
    utils_pos = html.index("chat-utils.js?v=20260916-p4-realtime1")
    cache_pos = html.index("chat-cache-drafts.js?v=20260916-p4-realtime1")
    realtime_pos = html.index("chat-realtime-transport.js?v=20260916-p4-realtime1")
    chat_pos = html.index("chat.js?v=20260916-p4-realtime1")
    assert utils_pos < cache_pos < realtime_pos < chat_pos

def test_pure_chat_utilities_live_only_in_dedicated_module():
    chat = CHAT.read_text(encoding="utf-8-sig")
    utils = UTILS.read_text(encoding="utf-8")
    assert "var BLANK_AVATAR_DATA_URL = chatUtils.BLANK_AVATAR_DATA_URL;" in chat
    for name in NAMES:
        assert f"function {name}(" not in chat
        assert f"function {name}(" in utils
        assert f"var {name} = chatUtils.{name};" in chat
        assert f"{name}: {name}" in utils

def test_chat_utils_runtime_contract():
    js = r'''const fs=require("fs"),vm=require("vm");const c={window:{}};vm.createContext(c);vm.runInContext(fs.readFileSync(process.argv[1],"utf8"),c);const u=c.window.Dent1402ChatUtils;if(!u)process.exit(2);const ok=[u.normalizeDigits("۱۴۰۵")==="1405",u.normalizeStudentNumber(" ۱۴۰۲ - ۱۲۳ ")==="1402123",u.avatarLabel("علی رضایی")==="عر",u.clamp(12,0,10)===10,u.formatFileSize(2048)==="2.0 KB",u.formatDuration(125)==="02:05",u.normalizeAttachmentCategory(" PDF ")==="pdf",u.attachmentCategoryLabel("voice")==="پیام صوتی",u.meaningfulMessageText({text:"attachment",attachments:[{}]})==="",u.normalizeAvatarUrl("javascript:x")===""];if(ok.some(v=>!v))process.exit(3);'''
    result = subprocess.run(["node", "-e", js, str(UTILS)], cwd=ROOT)
    assert result.returncode == 0
