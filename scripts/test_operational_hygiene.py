#!/usr/bin/env python3
"""Deterministic guards for repository-level operational hygiene."""

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

# Retired cPanel/FTP production entry points must not silently return under
# ambiguous names. The PowerShell legacy implementation is intentionally kept
# as test-covered historical/recovery evidence; the unreferenced Bash deployer
# and auto-upload-style example are not.
forbidden_paths = (
    ROOT / "scripts" / "deploy_public_html.sh",
    ROOT / "config" / "examples" / "sftp.example.json",
)
for path in forbidden_paths:
    assert not path.exists(), f"retired operational artifact returned: {path.relative_to(ROOT)}"

legacy_example = ROOT / "config" / "examples" / "legacy-ftp-recovery.example.json"
assert legacy_example.is_file(), "explicit retired FTP recovery example is missing"
legacy_text = legacy_example.read_text(encoding="utf-8")
assert '"uploadOnSave": false' in legacy_text, "retired FTP example must never enable upload-on-save"
assert '"name": "Retired cPanel FTP recovery only"' in legacy_text, "retired FTP example must remain unambiguous"

ci_text = (ROOT / ".github" / "workflows" / "ci.yml").read_text(encoding="utf-8")
for required_path in (
    '"ops/**"',
    '"config/**"',
    '"server-only/**"',
    '".env.example"',
    '".gitignore"',
    '"README.md"',
    '".github/workflows/**"',
):
    assert ci_text.count(required_path) >= 2, f"CI must cover {required_path} on push and pull_request"

classops_ci = (ROOT / ".github" / "workflows" / "classops-stage1.yml").read_text(encoding="utf-8")
assert "  push:\n" in classops_ci, "ClassOps domain matrix must run after integration pushes"
assert "      - main\n" in classops_ci, "ClassOps push validation must target main"
assert "  pull_request:\n" in classops_ci, "ClassOps domain matrix must remain a PR gate"

print("Operational repository hygiene contracts: ok")
