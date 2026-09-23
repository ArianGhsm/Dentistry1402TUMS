#!/usr/bin/env python3
from __future__ import annotations

import asyncio
import base64
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import time


CURRENT_RELEASE = Path("/opt/integrated-dent/telegram/current")
ARCHIVE_WORKER = Path("/srv/telegram/apps/archive-worker")
BOT_ENV = Path("/etc/integrated-dent/dent-bot.env")
STATE_ROOT = Path("/var/lib/integrated-dent/booklet-source-reconcile")
STATE_PATH = STATE_ROOT / "state.json"
SOURCE_LIMIT = 200

sys.path.insert(0, str(CURRENT_RELEASE))
sys.path.insert(0, str(ARCHIVE_WORKER))

from dent_bot.booklet_reconcile import (  # noqa: E402
    album_caption_overrides,
    reconciliation_record,
    should_reconcile,
    source_fingerprint,
)
import telegram_cli  # noqa: E402


def read_env(path: Path) -> dict[str, str]:
    values: dict[str, str] = {}
    for raw in path.read_text(encoding="utf-8-sig").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        values[key.strip()] = value.strip().strip('"').strip("'")
    return values


def load_state() -> dict[str, object]:
    try:
        value = json.loads(STATE_PATH.read_text(encoding="utf-8"))
    except (FileNotFoundError, json.JSONDecodeError, OSError):
        return {"version": 1, "messages": {}}
    if not isinstance(value, dict) or not isinstance(value.get("messages"), dict):
        return {"version": 1, "messages": {}}
    return value


def save_state(value: dict[str, object]) -> None:
    STATE_ROOT.mkdir(parents=True, exist_ok=True)
    temporary = STATE_PATH.with_suffix(".tmp")
    temporary.write_text(
        json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")),
        encoding="utf-8",
    )
    os.chmod(temporary, 0o600)
    temporary.replace(STATE_PATH)


async def current_media_messages(source_chat_id: int) -> list[dict[str, object]]:
    env = telegram_cli.load_env()
    with tempfile.TemporaryDirectory(prefix="booklet-reconcile-") as temporary:
        runtime_session = Path(temporary) / "arianbc"
        shutil.copy2(
            telegram_cli.SESSION_FILE,
            runtime_session.with_suffix(".session"),
        )
        os.chmod(runtime_session.with_suffix(".session"), 0o600)
        client = telegram_cli.client_from_env(env, runtime_session)
        await client.connect()
        try:
            await telegram_cli.checked_identity(client)
            entity = await telegram_cli.resolve_peer(client, str(source_chat_id))
            rows: list[dict[str, object]] = []
            async for message in client.iter_messages(entity, limit=SOURCE_LIMIT):
                file = getattr(message, "file", None)
                if file is None:
                    continue
                rows.append({
                    "messageId": int(message.id),
                    "groupedId": str(message.grouped_id or ""),
                    "date": message.date.isoformat() if message.date else "",
                    "editDate": message.edit_date.isoformat() if message.edit_date else "",
                    "text": message.raw_text or "",
                    "fileName": str(getattr(file, "name", "") or ""),
                    "fileSize": int(getattr(file, "size", 0) or 0),
                    "mimeType": str(getattr(file, "mime_type", "") or ""),
                })
            rows.sort(key=lambda item: int(item["messageId"]))
            return rows
        finally:
            await client.disconnect()


def sync_message(
    bot_env: dict[str, str],
    message_id: int,
    *,
    caption_override: str = "",
) -> tuple[str, str]:
    env = os.environ.copy()
    env.update(bot_env)
    completed = subprocess.run(
        (
            [
                "runuser", "-u", "dentbot", "--preserve-environment", "--",
                "/opt/integrated-dent/telegram-venv/bin/python",
                "-m", "dent_bot.booklet_source_admin",
                "sync-existing", "--message-id", str(int(message_id)),
            ]
            + (
                [
                    "--caption-base64",
                    base64.b64encode(caption_override.encode("utf-8")).decode("ascii"),
                ]
                if caption_override
                else []
            )
        ),
        cwd=str(CURRENT_RELEASE),
        env=env,
        check=False,
        capture_output=True,
        text=True,
        timeout=90,
    )
    output = (completed.stdout or completed.stderr or "").strip()
    if completed.returncode == 0:
        return "routed", output
    if completed.returncode == 2:
        return "unrouted", output
    return "failed", output


async def main() -> int:
    bot_env = read_env(BOT_ENV)
    source_chat_id = int(bot_env.get("DENT_BOT_BOOKLET_SOURCE_CHANNEL_ID", "0") or "0")
    if source_chat_id >= 0:
        raise RuntimeError("Protected booklet source channel is not configured")

    state = load_state()
    messages = dict(state.get("messages") or {})
    now = int(time.time())
    rows = await current_media_messages(source_chat_id)
    album_overrides = album_caption_overrides(rows)

    summary = {
        "scanned": len(rows),
        "changed": 0,
        "routed": 0,
        "unrouted": 0,
        "failed": 0,
    }
    failures: list[dict[str, object]] = []

    for row in rows:
        message_id = int(row["messageId"])
        caption_override = album_overrides.get(message_id, "")
        effective_text = str(row["text"] or "") or caption_override
        fingerprint = source_fingerprint(
            message_id=message_id,
            date=str(row["date"]),
            edit_date=str(row["editDate"]),
            text=effective_text,
            file_name=str(row["fileName"]),
            file_size=int(row["fileSize"]),
            mime_type=str(row["mimeType"]),
            grouped_id=str(row["groupedId"]),
        )
        previous = messages.get(str(message_id))
        if not should_reconcile(
            previous if isinstance(previous, dict) else None,
            fingerprint,
            now=now,
        ):
            continue

        summary["changed"] += 1
        status, detail = sync_message(
            bot_env,
            message_id,
            caption_override=caption_override,
        )
        summary[status] += 1
        messages[str(message_id)] = reconciliation_record(
            fingerprint,
            status=status,
            now=now,
        )
        if status == "failed":
            failures.append({
                "messageId": message_id,
                "detail": detail[:300],
            })

    # Keep bounded state while preserving enough history for edit detection.
    ordered = sorted(
        messages.items(),
        key=lambda item: int(item[0]) if str(item[0]).isdigit() else -1,
        reverse=True,
    )[:500]
    state = {
        "version": 1,
        "messages": dict(ordered),
        "updatedAt": now,
    }
    save_state(state)

    result = {**summary, "failures": failures[:10]}
    print(json.dumps(result, ensure_ascii=False, separators=(",", ":")))
    return 1 if summary["failed"] else 0


if __name__ == "__main__":
    raise SystemExit(asyncio.run(main()))
