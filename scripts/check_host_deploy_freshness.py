#!/usr/bin/env python3
"""Audit a legacy host manifest against an exact public_html tree.

The manifest format remains useful for historical/recovery inspection, but a
failure must never direct an operator to the retired cPanel/FTP deployment path.
Production releases go through the exact-SHA VPS release gate.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import sys
from pathlib import Path

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")


def is_protected_public_html_relative_path(relative_path: str) -> bool:
    normalized = str(relative_path or "").replace("\\", "/").lstrip("/").strip().lower()
    if normalized in {"", "."}:
        return True
    if normalized == ".env" or normalized.startswith(".env."):
        return True
    if normalized == "storage" or normalized.startswith("storage/"):
        return True
    if normalized == "server-only" or normalized.startswith("server-only/"):
        return True
    return False


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def read_json(path: Path) -> dict:
    try:
        return json.loads(path.read_text(encoding="utf-8-sig"))
    except FileNotFoundError as exc:
        raise SystemExit(f"Missing deploy metadata: {path}") from exc
    except json.JSONDecodeError as exc:
        raise SystemExit(f"Unreadable deploy metadata: {path}: {exc}") from exc


def build_current_public_html_map(public_root: Path) -> dict[str, str]:
    if not public_root.is_dir():
        raise SystemExit(f"Missing public_html directory: {public_root}")

    current: dict[str, str] = {}
    for path in sorted(public_root.rglob("*")):
        if not path.is_file():
            continue
        relative = path.relative_to(public_root).as_posix()
        if is_protected_public_html_relative_path(relative):
            continue
        current[relative] = sha256_file(path)
    return current


def manifest_hash_map(manifest_payload: dict) -> dict[str, str]:
    files_node = manifest_payload.get("Files")
    if not isinstance(files_node, dict):
        raise SystemExit("Host deploy manifest is missing its Files map.")

    hashes: dict[str, str] = {}
    for relative, raw_entry in files_node.items():
        if is_protected_public_html_relative_path(relative):
            continue
        if isinstance(raw_entry, str):
            hash_value = raw_entry.strip().lower()
        elif isinstance(raw_entry, dict):
            hash_value = str(raw_entry.get("Hash", "")).strip().lower()
        else:
            hash_value = ""
        if hash_value:
            hashes[str(relative).replace("\\", "/").lstrip("/")] = hash_value
    return hashes


def summarize_paths(label: str, items: list[str], limit: int) -> list[str]:
    if not items:
        return []
    lines = [f"{label}: {len(items)}"]
    for item in items[:limit]:
        lines.append(f"  - {item}")
    remainder = len(items) - min(len(items), limit)
    if remainder > 0:
        lines.append(f"  - ... و {remainder} مورد دیگر")
    return lines


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Verify that an exact public_html tree matches legacy host-deploy metadata.")
    parser.add_argument("--project-root", type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument("--metadata-root", type=Path, help="Repository root holding ignored legacy deploy metadata (defaults to project root).")
    parser.add_argument("--public-root", type=Path, help="Exact release public_html directory (defaults to project-root/public_html).")
    parser.add_argument("--limit", type=int, default=20, help="Maximum sample paths to print per drift category.")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    repo_root = args.project_root.resolve()
    metadata_root = (args.metadata_root or repo_root).resolve()
    deploy_dir = metadata_root / ".codex-local" / "deploy"
    state_path = deploy_dir / "host_last_deploy.json"
    manifest_path = deploy_dir / "host_last_deploy_manifest.json"
    public_root = (args.public_root or (repo_root / "public_html")).resolve()

    state_payload = read_json(state_path)
    manifest_payload = read_json(manifest_path)

    current_files = build_current_public_html_map(public_root)
    manifest_files = manifest_hash_map(manifest_payload)

    current_paths = set(current_files)
    manifest_paths = set(manifest_files)

    added_or_modified = sorted(
        path
        for path in current_paths
        if manifest_files.get(path) != current_files.get(path)
    )
    deleted = sorted(manifest_paths - current_paths)

    added = [path for path in added_or_modified if path not in manifest_files]
    modified = [path for path in added_or_modified if path in manifest_files]

    if added or modified or deleted:
        print("Legacy host-manifest freshness check failed.")
        print(f"Last successful host deploy FinishedAt: {state_payload.get('FinishedAt', '')}")
        print(f"Last recorded deploy metadata update: {state_payload.get('RecordedAt', '')}")
        print(f"Manifest generated at: {manifest_payload.get('GeneratedAt', '')}")
        for line in summarize_paths("Files newer/different from the legacy manifest", added + modified, args.limit):
            print(line)
        for line in summarize_paths("Files absent from the exact tree", deleted, args.limit):
            print(line)
        print("Do not deploy a working-tree drift. Commit/merge the intended code first, then run:")
        print(r"powershell -ExecutionPolicy Bypass -File .\scripts\run_release_gate.ps1 -ReleaseSha <exact-origin-main-sha> -DryRun")
        return 1

    print(
        "OK: exact public_html matches the legacy host deploy manifest "
        f"({len(current_files)} files, FinishedAt={state_payload.get('FinishedAt', '')})."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
