#!/usr/bin/env python3
from html.parser import HTMLParser
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PAGE = ROOT / "public_html" / "buy" / "endosim" / "index.html"
OWNER = ROOT / "public_html" / "buy" / "manage" / "endosim" / "index.html"
GENERAL = ROOT / "public_html" / "buy" / "index.html"
JS = ROOT / "public_html" / "assets" / "site" / "scripts" / "endosim-shop.js"
CSS = ROOT / "public_html" / "assets" / "site" / "styles" / "endosim.css"


class TextCollector(HTMLParser):
    def __init__(self):
        super().__init__()
        self.parts = []

    def handle_data(self, data):
        clean = " ".join(data.split())
        if clean:
            self.parts.append(clean)


def visible_text(path: Path) -> str:
    parser = TextCollector()
    parser.feed(path.read_text(encoding="utf-8"))
    return " ".join(parser.parts)


def check(condition: bool, label: str):
    if not condition:
        raise AssertionError(label)
    print(f"PASS: {label}")


page = PAGE.read_text(encoding="utf-8")
owner = OWNER.read_text(encoding="utf-8")
general = GENERAL.read_text(encoding="utf-8")
js = JS.read_text(encoding="utf-8")
css = CSS.read_text(encoding="utf-8")

check('/assets/site/styles/buy.css' in page, "dedicated page reuses mature buy stylesheet")
check(page.index('/assets/site/styles/buy.css') < page.index('/assets/site/styles/endosim.css'), "feature overrides load after shared buy primitives")
for label in ("همه", "پلاگر", "دسته بیستوری", "فرز"):
    check(f">{label}<" in page, f"dedicated filter exists: {label}")
check("dent1402_buy_cart_items" in js, "dedicated page shares the global cart source of truth")
check('CATEGORY = "endodontic_tools"' in js, "dedicated renderer reads the new endodontic-tools category")
check('apiGet("listPublicItems")' in js, "dedicated renderer consumes the public payments API")
check("quoteCart" not in js and "createCartOrder" not in js, "dedicated page does not duplicate checkout/payment logic")
check('slug.indexOf(RETIRED_PREFIX)' in js, "stale legacy tooth selections are cleaned from the cart")
check(".endo-tools-feed" in css and "grid-template-columns: 1fr !important" in css, "mobile catalog is a single readable row stream")
check('data-buy-category="endodontic_tools"' in general, "general buy catalog exposes the new category")
check("ابزارهای منتخب اندودانتیکس" in general, "general buy entry points to the replacement catalog")
check("همگام‌سازی ۸ محصول ابزار اندو" in owner, "owner view describes the eight-product catalog import")
for path in (PAGE, OWNER):
    shown = visible_text(path)
    check("اندوسیم" not in shown and "Endosim" not in shown, f"no legacy brand leakage in visible text: {path.relative_to(ROOT)}")

print("Endodontic-tools UI contract passed.")
