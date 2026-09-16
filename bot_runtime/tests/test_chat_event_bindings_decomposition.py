from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
CHAT = ROOT / "public_html/assets/site/scripts/chat.js"
BINDERS = [
    "bindConversationListEvents",
    "bindConversationDialogEvents",
    "bindComposerEvents",
    "bindEditorAndSearchEvents",
    "bindVoiceAndMediaEvents",
    "bindGlobalChatEvents",
]


def _function_block(source, name):
    marker = "function " + name + "() {"
    start = source.index(marker)
    brace = source.index("{", start)
    depth = 0
    quote = None
    escaped = False
    line_comment = False
    block_comment = False
    i = brace
    while i < len(source):
        char = source[i]
        nxt = source[i + 1] if i + 1 < len(source) else ""
        if line_comment:
            if char == "\n":
                line_comment = False
        elif block_comment:
            if char == "*" and nxt == "/":
                block_comment = False
                i += 1
        elif quote:
            if escaped:
                escaped = False
            elif char == "\\":
                escaped = True
            elif char == quote:
                quote = None
        else:
            if char in ('"', "'", '`'):
                quote = char
            elif char == "/" and nxt == "/":
                line_comment = True
                i += 1
            elif char == "/" and nxt == "*":
                block_comment = True
                i += 1
            elif char == "{":
                depth += 1
            elif char == "}":
                depth -= 1
                if depth == 0:
                    return source[start:i + 1]
        i += 1
    raise AssertionError("unterminated function: " + name)


def test_chat_event_binding_orchestrator_is_small_and_ordered():
    source = CHAT.read_text(encoding="utf-8-sig")
    block = _function_block(source, "bindEvents")
    calls = re.findall(r"\b(bind[A-Za-z]+Events)\(\);", block)
    assert calls == BINDERS
    assert len(block.splitlines()) <= 10


def test_chat_event_binders_stay_bounded():
    source = CHAT.read_text(encoding="utf-8-sig")
    for name in BINDERS:
        block = _function_block(source, name)
        assert len(block.splitlines()) <= 300, (name, len(block.splitlines()))


def test_chat_event_listener_baseline_is_preserved():
    source = CHAT.read_text(encoding="utf-8-sig")
    assert source.count(".addEventListener(") == 245
