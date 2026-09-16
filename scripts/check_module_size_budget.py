#!/usr/bin/env python3
"""Prevent known central code modules from silently accumulating more debt.

Bulk/generated content is intentionally excluded. Removing/splitting a listed file is
allowed; this guard only blocks growth beyond the reviewed ceiling.
"""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BUDGETS = {
    "public_html/assets/site/scripts/chat.js": 13020,
    "public_html/assets/site/scripts/chat-cache-drafts.js": 450,
    "public_html/assets/site/scripts/chat-utils.js": 330,
    "public_html/assets/site/styles/chat.css": 5460,
    "public_html/assets/site/styles/chat-messenger.css": 2540,
    "public_html/assets/site/styles/chat-enhancements.css": 3980,
    "public_html/chat/chat_api.php": 8520,
    "public_html/assets/site/scripts/account.js": 8200,
    "public_html/assets/site/scripts/account-owner-analytics.js": 650,
    "public_html/assets/site/scripts/account-utils.js": 190,
    "public_html/api/auth_store.php": 3984,
    "public_html/api/notes_api.php": 4180,
    "bot_runtime/dent_bot/app.py": 1286,
    "bot_runtime/dent_bot/dialog_app_workflows.py": 881,
    "bot_runtime/dent_bot/dynamic_screen_workflows.py": 1085,
}

failures = []
for rel, limit in BUDGETS.items():
    path = ROOT / rel
    if not path.exists():
        print(f"module size budget: retired/split: {rel}")
        continue
    with path.open("r", encoding="utf-8", errors="strict") as handle:
        lines = sum(1 for _ in handle)
    print(f"module size budget: {rel}: {lines}/{limit} lines")
    if lines > limit:
        failures.append((rel, lines, limit))

if failures:
    for rel, lines, limit in failures:
        print(f"oversized central module: {rel}: {lines} lines exceeds budget {limit}")
    raise SystemExit(1)

print("central module size budgets: ok")
