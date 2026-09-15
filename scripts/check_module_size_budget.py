#!/usr/bin/env python3
"""Prevent known central code modules from silently accumulating more debt.

Bulk/generated content is intentionally excluded. Removing/splitting a listed file is
allowed; this guard only blocks growth beyond the reviewed ceiling.
"""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BUDGETS = {
    "public_html/assets/site/scripts/chat.js": 13600,
    "public_html/assets/site/styles/chat.css": 11900,
    "public_html/chat/chat_api.php": 9800,
    "public_html/assets/site/scripts/account.js": 8950,
    "public_html/api/auth_store.php": 5050,
    "public_html/api/notes_api.php": 4580,
    "bot_runtime/dent_bot/app.py": 3450,
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
