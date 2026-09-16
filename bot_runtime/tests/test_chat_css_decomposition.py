from hashlib import sha256
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
STYLES = ROOT / "public_html/assets/site/styles"
HTML = ROOT / "public_html/chat/index.html"
EXPECTED_COMBINED_SHA256 = "05d4849afb2ffe99660f364dc4c6b648c7dbe9c19ef8f8247a3ea8e73b50bc44"
LAYERS = ["chat.css", "chat-messenger.css", "chat-enhancements.css"]


def test_split_chat_css_preserves_original_bytes_and_order():
    combined = b"".join((STYLES / name).read_bytes() for name in LAYERS)
    assert sha256(combined).hexdigest() == EXPECTED_COMBINED_SHA256


def test_chat_page_loads_all_layers_in_original_cascade_order_with_one_version():
    html = HTML.read_text(encoding="utf-8")
    needles = [f"/assets/site/styles/{name}?v=20260916-p4-css1" for name in LAYERS]
    positions = [html.index(needle) for needle in needles]
    assert positions == sorted(positions)
    for needle in needles:
        assert html.count(needle) == 1
