#!/usr/bin/env python3
"""Fail when Git tracks runtime state or recognizable secret material."""
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
tracked = subprocess.check_output(["git", "-C", str(ROOT), "ls-files", "-z"]).decode("utf-8").split("\0")
untracked = subprocess.check_output(["git", "-C", str(ROOT), "ls-files", "--others", "--exclude-standard", "-z"]).decode("utf-8").split("\0")
tracked = sorted({p for p in tracked + untracked if p})
bad_paths = []
bad_content = []
path_patterns = [
    re.compile(r"(^|/)\.codex-local/", re.I), re.compile(r"(^|/)server-only/", re.I),
    re.compile(r"(^|/)(backups?|snapshots?|logs?|cache|sessions?|tmp|output)/", re.I),
    re.compile(r"(^|/)storage/", re.I), re.compile(r"\.(sqlite3?|db|log|pid|session)$", re.I),
    re.compile(r"(^|/)\.env($|\.)", re.I), re.compile(r"\.(pem|p12|pfx|key)$", re.I),
]
allowed_env = re.compile(r"(^|/)(\.env\.example|[^/]+\.env\.example)$", re.I)
allowed_paths = {"server-only/.gitignore", "server-only/README.md"}
hard_secret_patterns = [
    re.compile(rb"-----BEGIN (?:RSA |OPENSSH |EC )?PRIVATE KEY-----"),
    re.compile(rb"\b\d{7,12}:[A-Za-z0-9_-]{30,}\b"),
]
assignment_secret_patterns = [
    re.compile(rb"(?i)(?:api[_-]?key|bot[_-]?token|hmac[_-]?secret|password)\s*[:=]\s*['\"](?!change|replace|example|test|fake|dummy|placeholder)[^'\"\r\n]{12,}['\"]"),
]
for rel in tracked:
    normalized = rel.replace("\\", "/")
    if any(p.search(normalized) for p in path_patterns) and normalized not in allowed_paths and not allowed_env.search(normalized):
        bad_paths.append(normalized)
        continue
    path = ROOT / rel
    try:
        data = path.read_bytes()
    except OSError:
        continue
    is_fixture_or_example = allowed_env.search(normalized) or normalized.startswith("scripts/") or normalized.startswith("public_html/")
    has_hard_secret = any(p.search(data) for p in hard_secret_patterns)
    has_assignment_secret = not is_fixture_or_example and any(p.search(data) for p in assignment_secret_patterns)
    if len(data) <= 2_000_000 and (has_hard_secret or has_assignment_secret):
        bad_content.append(normalized)
if bad_paths or bad_content:
    for rel in sorted(set(bad_paths)):
        print(f"forbidden tracked runtime/sensitive path: {rel}")
    for rel in sorted(set(bad_content)):
        print(f"possible secret material (value suppressed): {rel}")
    raise SystemExit(1)
print(f"repository hygiene: ok ({len(tracked)} tracked/candidate files inspected)")
