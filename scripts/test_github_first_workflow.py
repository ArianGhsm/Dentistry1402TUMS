#!/usr/bin/env python3
import hashlib
import json
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DEPLOY = (ROOT / "scripts/deploy_public_html.ps1").read_text(encoding="utf-8")
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

assert "must never turn this runner into a deployment agent" in SELF_HOSTED_DOC

# The legacy FTP deployer remains test-covered as recovery/history evidence, but
# it is no longer a canonical production route. GitHub-first release wrappers
# must point only to the exact-SHA VPS deployer.
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
    assert needle in DEPLOY, f"missing legacy GitHub-first deploy invariant: {needle}"
assert "Sync-GitHubFromLaptop -GitHubPlan" not in DEPLOY, "legacy deploy must not invoke post-deploy GitHub sync"
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

# Preserve safety coverage for the retired FTP implementation while it remains
# in the repository as non-canonical recovery evidence.
assert "Run-OptionalPullBeforeDeploy\n" not in DEPLOY, "legacy deploy must not pull inside an immutable release"
assert "function Get-GitCommitMetadata" in DEPLOY, "release report metadata helper must be defined"
assert "function Get-FileSha256Hex" in DEPLOY, "deploy hashing must be available without Get-FileHash"
assert "Get-FileHash" not in DEPLOY, "canonical Windows PowerShell deploy must not require the optional Get-FileHash cmdlet"
assert "function Prune-VerifiedStorageSnapshots" in DEPLOY, "verified snapshot retention helper must be present"
assert "$script:VerifiedSnapshotRetentionCount = 5" in DEPLOY, "snapshot retention must keep five verified copies"
assert "eligibleForLatest -ne $true" in DEPLOY, "retention must never delete an unverified snapshot"
assert "-notmatch '^\\d{8}-\\d{6}$'" in DEPLOY, "retention must preserve partial/non-snapshot directories"
assert "SnapshotsPruned" in DEPLOY and "SnapshotsRetained" in DEPLOY, "retention result must be observable"
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
    'exitCode = if ($status -eq "passed") { 0 } else { 1 }',
]:
    assert needle in DEPLOY, f"missing durable legacy release-report invariant: {needle}"
assert 'if ($DryRun -and -not $script:DeployPlanComplete)' in DEPLOY, (
    "legacy dry-run must never return success without a complete deploy plan"
)
assert 'Where-Object { -not [string]::IsNullOrWhiteSpace([string]$_) }' in DEPLOY, (
    "zero-delta plans must discard PowerShell null pipeline entries before protected-path validation"
)
assert 'Write-Warning $failureMessage' in DEPLOY, (
    "failure reporting must not terminate inside finally before the durable report is written"
)
assert '$deployInfo.UploadCount -gt 0 -or $deployInfo.DeleteCount -gt 0' in DEPLOY, (
    "a successful zero-delta release must not be reported as a production mutation"
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
