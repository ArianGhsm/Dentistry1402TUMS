#!/usr/bin/env python3
import hashlib
import json
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VPS_DEPLOY = (ROOT / "scripts/deploy_site_vps.ps1").read_text(encoding="utf-8")
RELEASE_GATE = (ROOT / "scripts/run_release_gate.ps1").read_text(encoding="utf-8")
SELF_HOSTED_DOC = (ROOT / "docs/SELF_HOSTED_CI.md").read_text(encoding="utf-8")

for workflow_name in ("ci.yml", "classops-stage1.yml", "persian-text-integrity.yml"):
    workflow = (ROOT / ".github/workflows" / workflow_name).read_text(encoding="utf-8")
    assert "runs-on: ubuntu-latest" in workflow, (
        f"public CI workflow must use an ephemeral GitHub-hosted runner: {workflow_name}"
    )
    assert "persist-credentials: false" in workflow, (
        f"workflow checkout must not persist the job token: {workflow_name}"
    )
    assert "timeout-minutes:" in workflow, f"public CI job must have a bounded timeout: {workflow_name}"
    if workflow_name == "ci.yml":
        assert "sudo apt-get install -y --no-install-recommends qpdf" in workflow, (
            "the hosted static workflow must install its qpdf verification dependency"
        )
    else:
        assert "sudo " not in workflow, f"public CI job must not invoke sudo: {workflow_name}"

# The general repository gate must be future-proof: adding a new top-level
# source/config/ops path must not silently bypass CI because someone forgot to
# extend a path allow-list. ClassOps remains a specialist matrix, but its
# push/PR filters must cover the current web + bot integration surface.
CI_WORKFLOW = (ROOT / ".github/workflows/ci.yml").read_text(encoding="utf-8")
assert "\n    paths:" not in CI_WORKFLOW and "\n    paths-ignore:" not in CI_WORKFLOW, (
    "general CI must run for every pull request and every push to main; path filters can create silent coverage gaps"
)
assert "push:\n    branches:\n      - main" in CI_WORKFLOW, "general CI must run after merges to main"
assert "pull_request:" in CI_WORKFLOW, "general CI must run on pull requests"

CLASSOPS_WORKFLOW = (ROOT / ".github/workflows/classops-stage1.yml").read_text(encoding="utf-8")
assert "push:\n    branches:\n      - main\n    paths:" in CLASSOPS_WORKFLOW, (
    "ClassOps domain matrix must verify relevant pushes to main as well as pull requests"
)
assert "pull_request:" in CLASSOPS_WORKFLOW, "ClassOps domain matrix must run on relevant pull requests"
for classops_path in [
    "public_html/api/classops_*",
    "public_html/api/classops_modules/**",
    "public_html/api/classops_stage2/**",
    "public_html/api/bot_api.php",
    "public_html/api/academic_term7_*",
    "public_html/classops/**",
    "public_html/assets/classops_ops/**",
    "bot_runtime/**",
    "contracts/**",
    "docs/classops/**",
    "docs/SHARED_CONTRACTS.md",
    "docs/CLASSOPS_FOUNDATION.md",
    "docs/DEVELOPMENT_WORKFLOW.md",
    "scripts/test_classops_*",
    "scripts/test_shared_contracts.py",
    "scripts/setup_classops_*",
    "scripts/run_static_checks.sh",
    ".github/workflows/classops-stage1.yml",
]:
    rendered = f'- "{classops_path}"'
    assert CLASSOPS_WORKFLOW.count(rendered) == 2, (
        f"ClassOps push and pull_request path filters must both cover {classops_path}"
    )

assert "must never turn this runner into a deployment agent" in SELF_HOSTED_DOC

for retired in ("scripts/deploy_public_html.ps1", "scripts/deploy_public_html.sh"):
    assert not (ROOT / retired).exists(), f"retired deployer must stay removed: {retired}"

assert "deploy_site_vps.ps1" in RELEASE_GATE, "canonical release gate must target the VPS deployer"
assert "deploy_public_html.ps1" not in RELEASE_GATE, "canonical release gate must not target retired cPanel/FTP"
assert "if ($DryRun) { $args.DryRun = $true }" in RELEASE_GATE, "release gate must bind DryRun as a named switch"
assert "@($(if ($DryRun)" not in RELEASE_GATE, "release gate must not pass DryRun positionally"
for needle in [
    "ArianGhsm/Dentistry1402TUMS",
    "HEAD does not equal -ReleaseSha.",
    "origin/main does not equal -ReleaseSha.",
    "Release workspace is not clean.",
    "/srv/dentistry1402/shared/storage",
    "/srv/dentistry1402/shared/server-only",
    "StrictHostKeyChecking=yes",
    "UserKnownHostsFile=",
    "release-report.json",
]:
    assert needle in VPS_DEPLOY, f"missing canonical VPS release invariant: {needle}"
assert "<<<" not in VPS_DEPLOY, "PowerShell VPS deployer must not contain Bash here-string redirection"

SMOKE = (ROOT / "scripts/smoke_multi_cohort_pages.py").read_text(encoding="utf-8")
assert 'parser.add_argument("--server-only-root", required=True)' in SMOKE, (
    "smoke harness must require an explicit isolated server-only root"
)
assert 'server_env["DENT_SERVER_ONLY_ROOT"] = server_only_root' in SMOKE, (
    "smoke PHP server must receive its isolated storage root"
)
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
