#!/usr/bin/env python3
import shutil
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DEPLOY_PATH = ROOT / 'scripts/deploy_site_vps.ps1'
GATE_PATH = ROOT / 'scripts/run_release_gate.ps1'
COMPLETE_PATH = ROOT / 'scripts/complete_task.ps1'
DEPLOY = DEPLOY_PATH.read_text(encoding='utf-8')
GATE = GATE_PATH.read_text(encoding='utf-8')
COMPLETE = COMPLETE_PATH.read_text(encoding='utf-8')

for needle in [
    'ArianGhsm/Dentistry1402TUMS',
    'HEAD does not equal -ReleaseSha.',
    'origin/main does not equal -ReleaseSha.',
    'Release workspace is not clean.',
    '/srv/dentistry1402',
    '/srv/dentistry1402/shared/storage',
    '/srv/dentistry1402/shared/server-only',
    'StrictHostKeyChecking=yes',
    'UserKnownHostsFile=',
    'Write-Utf8NoBom',
    'Assert-CodeOnlyPublicHtml',
    "sudo bash",
    "nginx -T 2>&1 | grep -F 'root /srv/dentistry1402/current/public_html;' >/dev/null",
    "SITE_DATA_BACKUP=",
    "storage.tar.gz",
    "sha256sum -c SHA256SUMS",
    "test ! -e \"$candidate/public_html/storage\"",
    "test ! -e \"$candidate/public_html/server-only\"",
    'SITE_ROLLED_BACK',
    'SITE_ROLLBACK_FAILED',
    "Publish-DentDeployLifecycle -Service website -Status started",
    "Publish-DentDeployLifecycle -Service website -Status succeeded",
    'release-report.json',
    'protectedPathViolations',
    'verifiedDataBackup',
    'productionMutation',
    'Exact SHA already active; verification-only release passed with zero production mutation.',
]:
    assert needle in DEPLOY, f'missing VPS deploy invariant: {needle}'

assert '<<<' not in DEPLOY, 'PowerShell deployer must not use Bash here-strings/redirection syntax'
assert "nginx -T 2>&1 | grep -Fq" not in DEPLOY, 'pipefail-safe nginx validation must consume the complete producer output'
assert 'Set-Content -LiteralPath $installerPath -Value $installer -Encoding UTF8' not in DEPLOY, 'remote shell installer must be UTF-8 without BOM'
assert 'deploy_public_html.ps1' not in GATE, 'release gate must not route production through retired cPanel/FTP deployer'
assert 'deploy_site_vps.ps1' in GATE, 'release gate must route website production to VPS deployer'
for needle in [
    '[string]$ReleaseSha',
    '[string]$ServerConfig',
    '[string]$ConfirmTargetHost',
    "'-Deploy'",
    '& $powershellCommand.Source -ExecutionPolicy Bypass -File $gate @gateArgs',
]:
    assert needle in COMPLETE, f'missing explicit task-completion contract: {needle}'
assert 'ValueFromRemainingArguments' not in COMPLETE, 'task completion must not rely on ambiguous passthrough parsing'
assert 'deploy_public_html.ps1' not in COMPLETE, 'task completion must not invoke retired cPanel deployer'
assert DEPLOY.index("Status succeeded") > DEPLOY.index('SITE_VPS_DEPLOY_OK'), 'success lifecycle must occur only after remote live verification'
assert DEPLOY.index("$productionMutation = $true") > DEPLOY.index('SITE_VPS_DEPLOY_OK'), 'mutation reporting must occur only after the remote installer contains its live-verification boundary'

# Hosted Ubuntu runners include PowerShell. Parse the scripts using the real
# PowerShell AST when available so text-contract checks cannot hide syntax bugs.
pwsh = shutil.which('pwsh') or shutil.which('powershell')
if pwsh:
    for script_path in (DEPLOY_PATH, GATE_PATH, COMPLETE_PATH):
        escaped_script_path = script_path.as_posix().replace("'", "''")
        command = (
            "$tokens=$null; $errors=$null; "
            f"[System.Management.Automation.Language.Parser]::ParseFile('{escaped_script_path}', [ref]$tokens, [ref]$errors) | Out-Null; "
            "if ($errors.Count -gt 0) { $errors | ForEach-Object { Write-Error $_.Message }; exit 1 }"
        )
        result = subprocess.run([pwsh, '-NoProfile', '-Command', command], text=True, capture_output=True)
        assert result.returncode == 0, f'PowerShell parse failed for {script_path.name}: {result.stdout}{result.stderr}'

print('Canonical VPS website deploy contracts: ok')
