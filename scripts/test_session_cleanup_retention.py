#!/usr/bin/env python3
"""Regression for disappearing logins caused by the 24-minute CLI cleanup."""
from pathlib import Path
from subprocess import run
from tempfile import TemporaryDirectory
import re

SOURCE = Path(__file__).resolve().parents[1] / "ops/site-vps/session-clean.sh"
script = SOURCE.read_text()
assert "authenticated_gc_minutes=$(( 30 * 24 * 60 ))" in script
assert "anonymous_gc_minutes=$(( (gc_seconds + 59) / 60 ))" in script
assert "find -O3" in script and "-cmin" in script
assert "student_number|s:" in script
match = re.search(
    r"(?ms)^dent_session_retention_minutes\(\) \{\n.*?^\}\n",
    script,
)
assert match is not None, "Missing reusable PHP-session classification function"
with TemporaryDirectory() as folder:
    root = Path(folder)
    anon = root / "anon"
    logged_in = root / "authenticated"
    anon.write_bytes(b"test_visit|s:5:\"hello\";")
    logged_in.write_bytes(b"student_number|s:11:\"40211272000\";auth_at|i:123;")
    snippet = (
        "set -euo pipefail\n"
        "anonymous_gc_minutes=24\n"
        "authenticated_gc_minutes=43200\n"
        + match.group(0)
        + "\n"
        + f"test $(dent_session_retention_minutes '{anon}') -eq 24\n"
        + f"test $(dent_session_retention_minutes '{logged_in}') -eq 43200\n"
    )
    check = run(["bash", "-c", snippet], capture_output=True, text=True)
    assert check.returncode == 0, check.stderr
print("Session-clean regression: guest 24 minutes, authenticated 30 days — passed")
