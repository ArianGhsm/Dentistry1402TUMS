from __future__ import annotations

import html


def frame_error(message: str) -> str:
    return f"<b>🔴 انجام نشد</b>\n\n{html.escape(message)}"
