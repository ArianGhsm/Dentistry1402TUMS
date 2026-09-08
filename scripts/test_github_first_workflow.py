#!/usr/bin/env python3
import hashlib
import json
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DEPLOY = (ROOT / "scripts/deploy_public_html.ps1").read_text(encoding="utf-8")
RELEASE_GATE = (ROOT / "scripts/run_release_gate.ps1").read_text(encoding="utf-8")

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
assert "& $child -ReleaseSha $ReleaseSha -DryRun" in RELEASE_GATE, (
    "release gate must bind DryRun as a named switch"
)
assert "@($(if ($DryRun)" not in RELEASE_GATE, (
    "release gate must not pass DryRun positionally into the child parameter list"
)
assert "Run-OptionalPullBeforeDeploy\n" not in DEPLOY, "deploy must not pull inside an immutable release"
assert "function Get-GitCommitMetadata" in DEPLOY, "release report metadata helper must be defined"
assert "function Get-FileSha256Hex" in DEPLOY, "deploy hashing must be available without Get-FileHash"
assert "Get-FileHash" not in DEPLOY, "canonical Windows PowerShell deploy must not require the optional Get-FileHash cmdlet"
assert "$lastDeployManifest.Files.ContainsKey($relative)" in DEPLOY, (
    "host manifest delta must look up normalized dictionary keys, not PSObject properties"
)
assert "foreach ($relative in @($lastDeployManifest.Files.Keys))" in DEPLOY, (
    "host manifest deletion delta must enumerate file keys only"
)
assert "Capacity" not in DEPLOY[DEPLOY.index("function Build-DeployPlan"):DEPLOY.index("function Collect-GitHubRangeDelta")], (
    "manifest delta must never derive pseudo-paths from dictionary object properties"
)
assert "--config $configPath" in DEPLOY, "storage snapshot must use shared ignored deploy config"
assert '$snapshotPath = Join-Path $opsRoot ".codex-local\\remote-storage\\snapshots\\$snapshotName"' in DEPLOY, (
    "storage snapshot must not be written inside a disposable release worktree"
)
assert '$activePath = Join-Path $opsRoot "server-only\\storage"' in DEPLOY, (
    "active storage mirror must remain under shared server-only state"
)
assert "Running isolated local authenticated smoke with synthetic fixture identity." in DEPLOY, (
    "release smoke must use an isolated synthetic fixture instead of a production owner credential"
)
assert '$siteDeliveryStatus -ne "delivered" -and $Status -eq "started"' in DEPLOY, (
    "only a queued bootstrap lifecycle event may proceed before the website persistence repair is active"
)
assert 'Final lifecycle delivery remains mandatory.' in DEPLOY, (
    "the completion lifecycle notice must remain delivery-gated"
)
for needle in [
    'function Write-ReleaseReport',
    'release-report.json',
    'RELEASE_PLAN_INCOMPLETE',
    'RELEASE_PROTECTED_PATH_VIOLATION',
    '$script:DeployPlanComplete = $true',
    'protectedPathViolations',
    'productionMutation',
]:
    assert needle in DEPLOY, f"missing durable release-report invariant: {needle}"
assert 'if ($DryRun -and -not $script:DeployPlanComplete)' in DEPLOY, (
    "dry-run must never return success without a complete deploy plan"
)
assert 'Where-Object { -not [string]::IsNullOrWhiteSpace([string]$_) }' in DEPLOY, (
    "zero-delta plans must discard PowerShell null pipeline entries before protected-path validation"
)
assert 'Write-Warning $failureMessage' in DEPLOY, (
    "failure reporting must not terminate inside finally before the durable report is written"
)
assert DEPLOY.index('Write-Warning $failureMessage') < DEPLOY.index(
    'Write-ReleaseReport -status "failed" -failureCode "RELEASE_PIPELINE_FAILED"'
), "the durable failure report must be reachable after final diagnostics"
assert '"--server-only-root", $smokeServerOnlyRoot' in DEPLOY, (
    "release smoke must explicitly bind the PHP server to its isolated server-only fixture"
)
validation_section = DEPLOY[DEPLOY.index("Step 1/5: local validation"):DEPLOY.index("function Run-OptionalPullBeforeDeploy")]
assert '"--owner-student-number", $liveCredentials.StudentNumber' not in validation_section, (
    "production owner credentials must not be passed to the local smoke harness"
)
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
