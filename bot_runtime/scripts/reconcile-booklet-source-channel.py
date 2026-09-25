#!/usr/bin/env python3
from __future__ import annotations

import asyncio
import base64
import json
import os
from pathlib import Path
import re
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
SOURCE_LIMIT_BY_ROLE = {"private": 200, "power": 250}

sys.path.insert(0, str(CURRENT_RELEASE))
sys.path.insert(0, str(ARCHIVE_WORKER))

from dent_bot.booklet_reconcile import (  # noqa: E402
    album_caption_overrides,
    reconciliation_record,
    should_reconcile,
    source_fingerprint,
    source_media_field,
    source_requires_reusable_file_id,
)
import telegram_cli  # noqa: E402
from telethon import utils as telethon_utils  # noqa: E402


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


async def current_media_messages(
    source_chat_id: int,
    *,
    limit: int,
) -> list[dict[str, object]]:
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
            async for message in client.iter_messages(entity, limit=int(limit)):
                file = getattr(message, "file", None)
                if file is None:
                    continue
                media_field = source_media_field(message)
                if not media_field:
                    continue
                try:
                    bot_file_id = str(telethon_utils.pack_bot_file_id(message.media) or "")
                except Exception:
                    bot_file_id = ""
                rows.append({
                    "messageId": int(message.id),
                    "groupedId": str(message.grouped_id or ""),
                    "date": message.date.isoformat() if message.date else "",
                    "editDate": message.edit_date.isoformat() if message.edit_date else "",
                    "text": message.raw_text or "",
                    "fileName": str(getattr(file, "name", "") or ""),
                    "fileSize": int(getattr(file, "size", 0) or 0),
                    "mimeType": str(getattr(file, "mime_type", "") or ""),
                    "mediaField": media_field,
                    "fileId": bot_file_id,
                })
            rows.sort(key=lambda item: int(item["messageId"]))
            return rows
        finally:
            await client.disconnect()


def looks_like_power_caption(value: object) -> bool:
    normalized = (
        str(value or "")
        .replace("ي", "ی")
        .replace("ى", "ی")
        .replace("ك", "ک")
        .replace("‌", " ")
    )
    return re.search(r"(?<!\w)پاور(?:پوینت)?(?!\w)", normalized) is not None


def register_message(
    bot_env: dict[str, str],
    row: dict[str, object],
    *,
    source_chat_id: int,
    caption: str,
    require_file_id: bool = True,
) -> tuple[str, str]:
    file_id = str(row.get("fileId") or "")
    if require_file_id and not file_id:
        return "failed", "MTProto media did not expose a Bot API-compatible file_id"
    env = os.environ.copy()
    env.update(bot_env)
    completed = subprocess.run(
        [
            "runuser", "-u", "dentbot", "--preserve-environment", "--",
            "/opt/integrated-dent/telegram-venv/bin/python",
            "-m", "dent_bot.booklet_source_admin",
            "register-metadata",
            "--source-channel-id", str(int(source_chat_id)),
            "--message-id", str(int(row["messageId"])),
            "--caption-base64", base64.b64encode(caption.encode("utf-8")).decode("ascii"),
            "--media-field", str(row["mediaField"]),
            "--file-id", file_id,
            "--file-name", str(row["fileName"]),
            "--mime-type", str(row["mimeType"]),
        ],
        cwd=str(CURRENT_RELEASE),
        env=env,
        check=False,
        capture_output=True,
        text=True,
        timeout=90,
    )
    output = (completed.stdout or completed.stderr or "").strip()
    if completed.returncode == 0:
        if not require_file_id:
            return "routed", output
        hydrated = subprocess.run(
            [
                "runuser", "-u", "dentbot", "--preserve-environment", "--",
                "/opt/integrated-dent/telegram-venv/bin/python",
                "-m", "dent_bot.booklet_source_admin",
                "hydrate-existing",
                "--source-channel-id", str(int(source_chat_id)),
                "--message-id", str(int(row["messageId"])),
            ],
            cwd=str(CURRENT_RELEASE),
            env=env,
            check=False,
            capture_output=True,
            text=True,
            timeout=90,
        )
        hydrated_output = (hydrated.stdout or hydrated.stderr or "").strip()
        if hydrated.returncode == 0:
            return "routed", hydrated_output or output
        return "failed", hydrated_output or "Bot API source hydration failed"
    if completed.returncode == 2:
        return "unrouted", output
    return "failed", output


async def main() -> int:
    bot_env = read_env(BOT_ENV)
    private_source_id = int(bot_env.get("DENT_BOT_BOOKLET_SOURCE_CHANNEL_ID", "0") or "0")
    power_source_id = int(bot_env.get("DENT_BOT_POWER_SOURCE_CHANNEL_ID", "0") or "0")
    if private_source_id >= 0:
        raise RuntimeError("Protected booklet source channel is not configured")
    if power_source_id < 0 and power_source_id == private_source_id:
        raise RuntimeError("Protected and power source channels must be distinct")

    sources: list[tuple[str, int, int]] = [
        ("private", private_source_id, SOURCE_LIMIT_BY_ROLE["private"])
    ]
    if power_source_id < 0:
        sources.append(("power", power_source_id, SOURCE_LIMIT_BY_ROLE["power"]))

    state = load_state()
    messages = dict(state.get("messages") or {})
    if int(state.get("version") or 1) < 2:
        messages = {
            (
                f"{private_source_id}:{key}"
                if str(key).isdigit()
                else str(key)
            ): value
            for key, value in messages.items()
        }

    now = int(time.time())
    summary = {
        "scanned": 0,
        "changed": 0,
        "routed": 0,
        "ignored": 0,
        "unrouted": 0,
        "failed": 0,
    }
    source_summaries: list[dict[str, object]] = []
    failures: list[dict[str, object]] = []

    for role, source_chat_id, limit in sources:
        rows = await current_media_messages(source_chat_id, limit=limit)
        album_overrides = album_caption_overrides(rows)
        local = {
            "role": role,
            "chatId": source_chat_id,
            "scanned": len(rows),
            "changed": 0,
            "routed": 0,
            "ignored": 0,
            "unrouted": 0,
            "failed": 0,
        }
        summary["scanned"] += len(rows)

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
            message_key = f"{source_chat_id}:{message_id}"
            previous = messages.get(message_key)
            if not should_reconcile(
                previous if isinstance(previous, dict) else None,
                fingerprint,
                now=now,
            ):
                continue

            summary["changed"] += 1
            local["changed"] = int(local["changed"]) + 1
            metric_status = ""
            if role == "power" and not looks_like_power_caption(effective_text):
                status, detail = "routed", "source-policy-skip"
                metric_status = "ignored"
            else:
                status, detail = register_message(
                    bot_env,
                    row,
                    source_chat_id=source_chat_id,
                    caption=effective_text,
                    require_file_id=source_requires_reusable_file_id(role),
                )
                if role == "power" and status == "unrouted":
                    status = "routed"
                    metric_status = "ignored"
            metric_status = metric_status or status
            summary[metric_status] += 1
            local[metric_status] = int(local[metric_status]) + 1
            messages[message_key] = reconciliation_record(
                fingerprint,
                status=status,
                now=now,
            )
            if status == "failed":
                failures.append({
                    "role": role,
                    "messageId": message_id,
                    "detail": detail[:300],
                })

        source_summaries.append(local)

    ordered = sorted(
        messages.items(),
        key=lambda item: int(dict(item[1]).get("checkedAt") or 0)
        if isinstance(item[1], dict)
        else 0,
        reverse=True,
    )[:1200]
    state = {
        "version": 2,
        "messages": dict(ordered),
        "updatedAt": now,
    }
    save_state(state)

    result = {
        **summary,
        "sources": source_summaries,
        "failures": failures[:10],
    }
    print(json.dumps(result, ensure_ascii=False, separators=(",", ":")))
    return 1 if summary["failed"] else 0


if __name__ == "__main__":
    raise SystemExit(asyncio.run(main()))
