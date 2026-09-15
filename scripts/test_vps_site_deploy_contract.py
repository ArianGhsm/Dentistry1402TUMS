#!/usr/bin/env python3
import shutil
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DEPLOY_PATH = ROOT / 'scripts/deploy_site_vps.ps1'
GATE_PATH = ROOT / 'scripts/run_release_gate.ps1'
COMPLETE_PATH = ROOT / 'scripts/complete_task.ps1'
LEGACY_MAIN_SITE_FTP_EXAMPLE = ROOT / 'config/examples/sftp.example.json'
RETIRED_MAIN_SITE_DEPLOYERS = (
    ROOT / 'scripts/deploy_public_html.ps1',
    ROOT / 'scripts/deploy_public_html.sh',
)
DEPLOY = DEPLOY_PATH.read_text(encoding='utf-8')
GATE = GATE_PATH.read_text(encoding='utf-8')
COMPLETE = COMPLETE_PATH.read_text(encoding='utf-8')
AGENTS = (ROOT / 'AGENTS.md').read_text(encoding='utf-8')
DEPLOY_DOC = (ROOT / 'DEPLOY.md').read_text(encoding='utf-8')
WORKFLOW_DOC = (ROOT / 'docs/DEVELOPMENT_WORKFLOW.md').read_text(encoding='utf-8')

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
    "SITE_DATA_BACKUPS_RETAINED=",
    "${site_backups[@]:5}",
    "rm -rf --one-file-system -- \"$candidate\"",
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
assert "-name 'dent-site-data-????????T??????Z-????????????'" in DEPLOY, 'retention must only match canonical timestamped site-data backups'
assert DEPLOY.index('(cd "$candidate" && sha256sum -c SHA256SUMS >/dev/null)') < DEPLOY.index('rm -rf --one-file-system -- "$candidate"'), (
    'every old site-data backup must be verified before any retention deletion'
)
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
assert COMPLETE.index('Get-Command pwsh') < COMPLETE.index('Get-Command powershell'), (
    'task completion must prefer PowerShell 7 so native stderr does not become a terminating RemoteException'
)
assert 'deploy_public_html.ps1' not in COMPLETE, 'task completion must not invoke retired cPanel deployer'
assert DEPLOY.index("Status succeeded") > DEPLOY.index('SITE_VPS_DEPLOY_OK'), 'success lifecycle must occur only after remote live verification'
assert DEPLOY.index("$productionMutation = $true") > DEPLOY.index('SITE_VPS_DEPLOY_OK'), 'mutation reporting must occur only after the remote installer contains its live-verification boundary'

# Repository instructions must describe the same production route as the
# executable release wrappers. Normalize only path separators so semantically
# equivalent PowerShell/Markdown spellings cannot create a false failure.
for doc_name, text in (
    ('AGENTS.md', AGENTS),
    ('DEPLOY.md', DEPLOY_DOC),
    ('docs/DEVELOPMENT_WORKFLOW.md', WORKFLOW_DOC),
):
    normalized = text.replace('\\', '/')
    assert 'scripts/run_release_gate.ps1' in normalized, f'{doc_name} must name the canonical release gate'
    assert 'scripts/deploy_site_vps.ps1' in normalized, f'{doc_name} must name the canonical VPS deployer'

assert '.\\scripts\\deploy_public_html.ps1 -ReleaseSha' not in AGENTS, (
    'AGENTS.md must not present the retired cPanel/FTP deployer as a canonical command'
)
assert 'retired cPanel/FTP deployer files have been removed from the working tree' in AGENTS, (
    'AGENTS.md must keep removed cPanel/FTP deployers non-operational'
)
assert 'retired main-site cPanel/FTP deployer scripts have been removed from the working tree' in DEPLOY_DOC, (
    'DEPLOY.md must keep removed cPanel/FTP deployers non-operational'
)
assert 'cPanel/FTP deployers have been removed from' in WORKFLOW_DOC, (
    'development workflow must keep removed cPanel/FTP deployers outside production release routes'
)
for retired_path in RETIRED_MAIN_SITE_DEPLOYERS:
    assert not retired_path.exists(), f'retired main-site deployer must not be tracked: {retired_path.name}'
assert not LEGACY_MAIN_SITE_FTP_EXAMPLE.exists(), (
    'tracked Main Site FTP/uploadOnSave example must stay retired; website production uses the exact-SHA VPS release path'
)

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
