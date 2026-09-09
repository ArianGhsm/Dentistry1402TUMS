#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DEPLOY = (ROOT / 'scripts/deploy_site_vps.ps1').read_text(encoding='utf-8')
GATE = (ROOT / 'scripts/run_release_gate.ps1').read_text(encoding='utf-8')
COMPLETE = (ROOT / 'scripts/complete_task.ps1').read_text(encoding='utf-8')

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
    'SITE_ROLLED_BACK',
    'SITE_ROLLBACK_FAILED',
    "Publish-DentDeployLifecycle -Service website -Status started",
    "Publish-DentDeployLifecycle -Service website -Status succeeded",
    'release-report.json',
    'protectedPathViolations',
    'productionMutation',
    'Exact SHA already active; verification-only release passed with zero production mutation.',
]:
    assert needle in DEPLOY, f'missing VPS deploy invariant: {needle}'

assert '<<<' not in DEPLOY, 'PowerShell deployer must not use Bash here-strings/redirection syntax'
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
assert DEPLOY.index("$productionMutation = $true") > DEPLOY.index('SITE_VPS_DEPLOY_OK'), 'mutation report must be set only after verified activation'

print('Canonical VPS website deploy contracts: ok')
