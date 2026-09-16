#!/usr/bin/env python3
from __future__ import annotations

import re
from html.parser import HTMLParser
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

class VisibleText(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.parts: list[str] = []
        self.hidden_depth = 0
        self.skip_depth = 0
    def handle_starttag(self, tag: str, attrs) -> None:
        attr = dict(attrs)
        if tag in {"script", "style"}:
            self.skip_depth += 1
        if "data-technical-only" in attr:
            self.hidden_depth += 1
    def handle_endtag(self, tag: str) -> None:
        if tag in {"script", "style"} and self.skip_depth:
            self.skip_depth -= 1
    def handle_data(self, data: str) -> None:
        if not self.skip_depth and not self.hidden_depth:
            value = " ".join(data.split())
            if value:
                self.parts.append(value)


def require(condition: bool, message: str) -> None:
    if not condition:
        raise SystemExit("FAIL: " + message)


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")

classops_html = read("public_html/classops/index.html")
classops_product = read("public_html/assets/classops_ops/class_operations_product.js")
classops_stage2 = read("public_html/assets/classops_ops/classops_stage2_dom.js")
classops_core = read("public_html/assets/classops_ops/classops_ops.js")
classops_css = read("public_html/assets/classops_ops/class_operations_product.css")
grades_html = read("public_html/grades/index.html")
grades_js = read("public_html/assets/site/scripts/grades.js")
grades_api = read("public_html/grades/grades_api.php")

# Existing-best-UI-first: user outputs are semantic surfaces, not preformatted text dumps.
for element_id in (
    "classops-student-digest", "classops-ai-preview", "classops-stage2-preview",
    "classops-selected-output", "classops-digest-output", "classops-confirm-text",
):
    require(f'<pre id="{element_id}"' not in classops_html, f"{element_id} regressed to <pre> text dump")

require("productizeText" not in classops_product, "ClassOps must not repair English leakage after rendering")
require("MutationObserver" not in classops_product, "ClassOps must not translate dynamic UI through a MutationObserver")
require('createElement("style")' not in classops_product and "createElement('style')" not in classops_product,
        "ClassOps styles must live in the stylesheet, not runtime JS")
require("renderDigest" in classops_product and "digest.sections" in classops_product,
        "ClassOps summaries must render structured digest sections")
require("is-weekly" in classops_product and "is-tomorrow" in classops_product,
        "daily and weekly summaries need distinct presentation modes")
require("UI_LABELS" in classops_core and "uiLabel" in classops_core,
        "ClassOps visible labels must have one shared source of truth")
require("classops-readable" in classops_css and "classops-digest-item" in classops_css,
        "ClassOps structured readable surfaces must be styled in CSS")
require("JSON.stringify(payload.stats" not in classops_stage2 and "JSON.stringify(payload.state" not in classops_stage2,
        "owner projections must not expose raw JSON")

# Visible HTML copy may contain proper nouns such as Excel, but not internal product vocabulary.
for path, html in (("ClassOps", classops_html), ("Grades", grades_html)):
    parser = VisibleText(); parser.feed(html)
    visible = " ".join(parser.parts)
    forbidden = re.findall(r"\b(?:ClassOps|Stage2|Foundation|canonical|revision|commit|runtime|login|Import|RESET|Status|Term|Group|Rotation)\b", visible, flags=re.I)
    require(not forbidden, f"{path} visible English leakage: {forbidden}")

for token in ("login مستقل", "Import نمرات", "Import دستی", "در حال import", "عبارت RESET", "RESET را وارد", "ریست"):
    require(token not in grades_html + grades_js + grades_api, f"Grades user copy leaked legacy wording: {token}")

# Persian digits are required in the owner import example shown to users.
require("۴۰۲۱۱۲۷۲۰۰۳" in grades_html and "۶٫۵" in grades_html,
        "Grades owner example must use Persian digits")

print("Product UI Persian/structure contract: ok")
