#!/usr/bin/env python3
import argparse
import json
import re
import subprocess
from pathlib import Path

EXPECTED = "ArianGhsm/Dentistry1402TUMS"


def git(root: Path, *args: str) -> str:
    result = subprocess.run(["git", "-C", str(root), *args], text=True, capture_output=True)
    if result.returncode:
        raise RuntimeError(result.stderr.strip() or f"git {' '.join(args)} failed")
    return result.stdout.strip()


def repo_name(url: str) -> str:
    value = url.strip().rstrip("/")
    value = re.sub(r"\.git$", "", value, flags=re.I)
    if value.startswith("git@github.com:"):
        return value.split(":", 1)[1]
    match = re.match(r"https?://github\.com/(.+)$", value, re.I)
    return match.group(1) if match else ""


def verify(root: Path, sha: str, require_origin_main: bool = True) -> dict:
    if not re.fullmatch(r"[0-9a-f]{40}", sha):
        raise RuntimeError("release SHA must be an exact lowercase 40-character commit")
    remote = git(root, "remote", "get-url", "origin")
    if repo_name(remote).lower() != EXPECTED.lower():
        raise RuntimeError(f"repository lock failed: expected {EXPECTED}")
    head = git(root, "rev-parse", "HEAD")
    if head != sha:
        raise RuntimeError("HEAD does not equal the requested release SHA")
    status = git(root, "status", "--porcelain=v1", "--untracked-files=all")
    if status:
        raise RuntimeError("release worktree is not clean")
    origin_main = git(root, "rev-parse", "origin/main") if require_origin_main else ""
    if require_origin_main and origin_main != sha:
        raise RuntimeError("origin/main does not equal the requested release SHA")
    return {"ok": True, "repositoryFullName": EXPECTED, "head": head, "originMain": origin_main, "clean": True}


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", default=str(Path(__file__).resolve().parents[1]))
    parser.add_argument("--sha", required=True)
    parser.add_argument("--allow-non-main", action="store_true")
    args = parser.parse_args()
    try:
        print(json.dumps(verify(Path(args.root).resolve(), args.sha, not args.allow_non_main), separators=(",", ":")))
    except Exception as exc:
        print(json.dumps({"ok": False, "error": str(exc)}, separators=(",", ":")))
        raise SystemExit(2)
