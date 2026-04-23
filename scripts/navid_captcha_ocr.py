#!/usr/bin/env python
"""
Read a base64 captcha payload from stdin and print solved text (A-Z0-9).

Requires: ddddocr
"""

from __future__ import annotations

import base64
import re
import sys


def normalize(value: str) -> str:
    return re.sub(r"[^A-Z0-9]", "", (value or "").strip().upper())


def main() -> int:
    payload = sys.stdin.read().strip()
    if not payload:
        return 2

    try:
        image_bytes = base64.b64decode(payload)
    except Exception:
        return 3

    try:
        import ddddocr  # type: ignore
    except Exception:
        return 4

    ocr = ddddocr.DdddOcr(show_ad=False)
    text = normalize(ocr.classification(image_bytes))
    if len(text) < 4:
        return 5

    sys.stdout.write(text)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
