#!/usr/bin/env python3
import hashlib
import json
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DEPLOY = (ROOT / "scripts/deploy_public_html.ps1").read_text(encoding="utf-8")

required = [
    "[Parameter(Mandatory = $true)]",
    "[string]$ReleaseSha",
    "ArianGhsm/Dentistry1402TUMS",
    "HEAD does not equal -ReleaseSha",
    "origin/main does not equal -ReleaseSha",
    "Release workspace is not clean",
    "exact-sha-manifest-delta",
    "post-deploy GitHub mutation is disabled",
]
for needle in required:
    assert needle in DEPLOY, f"missing GitHub-first deploy invariant: {needle}"
assert "Sync-GitHubFromLaptop -GitHubPlan" not in DEPLOY, "deploy must not invoke post-deploy GitHub sync"
assert "Run-OptionalPullBeforeDeploy\n" not in DEPLOY, "deploy must not pull inside an immutable release"
for retired in ROOT.glob("public_html/api/private_notes_*.php"):
    raise AssertionError(f"retired undeployed private-notes source remains: {retired.name}")

# Runtime state stays server-only and is deliberately outside this repository.
# Checks must therefore accept an explicitly supplied absolute config path instead
# of accidentally treating it as a path relative to the Git checkout.
for runtime_check in [
    "bot_runtime/scripts/check-iran-bot-runtime.ps1",
    "bot_runtime/scripts/check-term-subscription-iran.ps1",
    "bot_runtime/scripts/emit-deploy-status.ps1",
]:
    runtime_text = (ROOT / runtime_check).read_text(encoding="utf-8")
    assert "[IO.Path]::IsPathRooted($ServerConfig)" in runtime_text, (
        f"absolute server-only config support missing: {runtime_check}"
    )
    assert "$serverStateRoot" in runtime_text, (
        f"server-only known-host resolution missing: {runtime_check}"
    )

runtime_diagnostic = (ROOT / "bot_runtime/scripts/check-iran-bot-runtime.ps1").read_text(encoding="utf-8")
assert "ONBOARDING_CATALOG_ERROR status={error.status} code={error.code}" in runtime_diagnostic, (
    "runtime verification must retain safe onboarding failure diagnostics"
)

with tempfile.TemporaryDirectory() as temporary:
    base = Path(temporary)
    metadata = base / "metadata"
    public = base / "release" / "public_html"
    deploy_dir = metadata / ".codex-local" / "deploy"
    deploy_dir.mkdir(parents=True)
    public.mkdir(parents=True)
    content = b"exact-release\n"
    (public / "health.txt").write_bytes(content)
    (deploy_dir / "host_last_deploy.json").write_text(json.dumps({"FinishedAt": "2026-09-07T00:00:00+03:30"}), encoding="utf-8")
    (deploy_dir / "host_last_deploy_manifest.json").write_text(json.dumps({
        "GeneratedAt": "2026-09-07T00:00:01+03:30",
        "Files": {"health.txt": {"Hash": hashlib.sha256(content).hexdigest(), "Length": len(content)}},
    }), encoding="utf-8")
    result = subprocess.run([
        sys.executable, str(ROOT / "scripts/check_host_deploy_freshness.py"),
        "--project-root", str(base / "release"), "--metadata-root", str(metadata),
        "--public-root", str(public),
    ], text=True, capture_output=True)
    assert result.returncode == 0, result.stdout + result.stderr

print("GitHub-first workflow contracts: ok")
