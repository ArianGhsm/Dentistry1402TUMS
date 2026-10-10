#!/usr/bin/env python3
import shutil
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DEPLOY_PATH = ROOT / 'scripts/deploy_site_vps.ps1'
INSTALL_PATH = ROOT / 'ops/site-vps/install-site.sh'
RESTORE_DRILL_PATH = ROOT / 'ops/site-vps/restore-drill.sh'
GATE_PATH = ROOT / 'scripts/run_release_gate.ps1'
COMPLETE_PATH = ROOT / 'scripts/complete_task.ps1'
LEGACY_MAIN_SITE_FTP_EXAMPLE = ROOT / 'config/examples/sftp.example.json'
RETIRED_MAIN_SITE_DEPLOYERS = (
    ROOT / 'scripts/deploy_public_html.ps1',
    ROOT / 'scripts/deploy_public_html.sh',
)
DEPLOY = DEPLOY_PATH.read_text(encoding='utf-8')
INSTALL = INSTALL_PATH.read_text(encoding='utf-8')
RESTORE_DRILL = RESTORE_DRILL_PATH.read_text(encoding='utf-8')
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
    "sha256sum runtime-pointers.txt storage.tar.gz > SHA256SUMS",
    "backup-retention --migrate-legacy --migrate-only",
    "'ops/site-vps/backup_retention.py'",
    "'ops/site-vps/dentistry1402-backup-retention.service'",
    "'ops/site-vps/dentistry1402-backup-retention.timer'",
    "'ops/site-vps/send-bale-database-backup.py'",
    "'ops/site-vps/dentistry1402-bale-database-backup.service'",
    "'ops/site-vps/dentistry1402-bale-database-backup.timer'",
    "ops_expected='__OPS_HASH__'",
    "grep -Eq '^[[:space:]]*DENT_BALE_OWNER_ID=' /etc/integrated-dent/bale-bot.env",
    "systemctl enable --now dentistry1402-backup.timer dentistry1402-backup-retention.timer dentistry1402-restore-drill.timer dentistry1402-bale-database-backup.timer",
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
assert "systemctl is-active --quiet dentistry1402-bale-database-backup.timer" in DEPLOY
for timer in [
    'dentistry1402-backup.timer',
    'dentistry1402-backup-retention.timer',
    'dentistry1402-restore-drill.timer',
    'dentistry1402-bale-database-backup.timer',
]:
    assert timer in INSTALL, f'bootstrap migration must pause {timer}'
assert 'systemctl stop "$timer"' in INSTALL, 'bootstrap migration must stop each active backup timer'
for service in [
    'dentistry1402-backup.service',
    'dentistry1402-backup-retention.service',
    'dentistry1402-restore-drill.service',
    'dentistry1402-bale-database-backup.service',
]:
    assert service in INSTALL, f'bootstrap migration must check {service}'
assert 'systemctl is-active --quiet "$service"' in INSTALL, 'bootstrap migration must check every backup service'
assert 'trap restore_paused_backup_timers EXIT' in INSTALL
assert 'paused_backup_timers=()\ntrap - EXIT' in INSTALL, 'successful bootstrap must leave the newly enabled timers active'
assert INSTALL.index('dentistry1402-restore-drill.timer \\\n') < INSTALL.index(
    'backup-retention --migrate-legacy --migrate-only'
), 'bootstrap migration must pause restore drills before moving legacy archives'
assert 'for binary in ' in RESTORE_DRILL and ' flock; do' in RESTORE_DRILL, (
    'restore drills must require the shared lock utility'
)
restore_lock = 'exec 9>"/var/backups/dentistry1402/.retention.lock"\nflock -x 9\n'
assert restore_lock in RESTORE_DRILL, 'restore drills must acquire the retention lock'
assert RESTORE_DRILL.index(restore_lock) < RESTORE_DRILL.index('latest="$(find "$backup_root"'), (
    'restore drills must hold the retention lock before selecting an archive'
)
verification = DEPLOY.split("$verification = @'", 1)[1].split("'@", 1)[0]
for needle in [
    'dentistry1402-backup.timer',
    'dentistry1402-backup-retention.timer',
    'dentistry1402-restore-drill.timer',
    'dentistry1402-bale-database-backup.timer',
    '/usr/local/lib/dentistry1402/backup-runtime',
    '/usr/local/lib/dentistry1402/backup-retention',
    '/usr/local/lib/dentistry1402/restore-drill',
    '/usr/local/lib/dentistry1402/send-bale-database-backup',
    '/var/backups/dentistry1402/runtime',
    '/var/backups/dentistry1402/site-data',
    '/var/backups/dentistry1402/bale-database',
    '/var/backups/dentistry1402/restore-drills',
    '/srv/dentistry1402/shared/server-only/backups',
]:
    assert needle in verification, f'same-SHA verification must check backup policy invariant: {needle}'
installer = DEPLOY.split("$installer = @'", 1)[1].split("'@", 1)[0]
for needle in [
    '/usr/local/lib/dentistry1402/backup-runtime',
    '/usr/local/lib/dentistry1402/backup-retention',
    '/usr/local/lib/dentistry1402/restore-drill',
    '/usr/local/lib/dentistry1402/send-bale-database-backup',
    '/etc/systemd/system/dentistry1402-backup.service',
    '/etc/systemd/system/dentistry1402-backup.timer',
    '/etc/systemd/system/dentistry1402-backup-retention.service',
    '/etc/systemd/system/dentistry1402-backup-retention.timer',
    '/etc/systemd/system/dentistry1402-restore-drill.service',
    '/etc/systemd/system/dentistry1402-restore-drill.timer',
    '/etc/systemd/system/dentistry1402-bale-database-backup.service',
    '/etc/systemd/system/dentistry1402-bale-database-backup.timer',
]:
    assert needle in installer, f'backup-tooling rollback snapshot must cover {needle}'
assert installer.index('\nsnapshot_backup_tooling\n') < installer.index(
    'install -o root -g root -m 0755 "$ops_stage/ops/site-vps/backup_retention.py"'
), 'backup tooling must be snapshotted before the first live install'
assert 'elif ! restore_backup_tooling; then' in installer
assert 'if test "$backup_migration_started" = 1; then' in installer
assert installer.index('backup_migration_started=1\n/usr/local/lib/dentistry1402/backup-retention --migrate-legacy --migrate-only') < installer.index(
    'systemctl enable --now dentistry1402-backup.timer'
), 'after archive migration starts, rollback must retain consumers for the new archive layout'
assert 'systemctl stop dentistry1402-restore-drill.timer' in installer, (
    'legacy archive migration must pause the scheduled restore drill'
)
assert 'systemctl is-active --quiet dentistry1402-restore-drill.service' in installer, (
    'legacy archive migration must wait until an in-flight restore drill completes'
)
assert 'if test "$restore_drill_timer_was_active" = 1; then systemctl start dentistry1402-restore-drill.timer || true; fi' in installer, (
    'the scheduled restore drill must resume after deployment cleanup when previously active'
)
assert 'test "$activated" = 1 || test "$tooling_changed" = 1' in installer
assert 'rollback_failed=1' in installer
assert 'if test "$rollback_failed" = 0; then' in installer, 'backup timers must stay stopped if tooling rollback fails'
assert 'if test "$rollback_failed" = 0 && test -n "$tooling_snapshot"; then' in installer, (
    'failed rollback must preserve the prior tooling snapshot for recovery'
)
assert "nginx -T 2>&1 | grep -Fq" not in DEPLOY, 'pipefail-safe nginx validation must consume the complete producer output'
assert "backup=\"/var/backups/dentistry1402/site-data/dent-site-data-" in DEPLOY, 'site-data backups must use Dentistry-isolated storage'
assert "^SITE_DATA_BACKUP=(/var/backups/dentistry1402/site-data/dent-site-data-[0-9]{8}T[0-9]{6}Z-[0-9a-f]{12})$" in DEPLOY, 'backup output parser must accept only canonical new site-data paths'
assert "count==12" in DEPLOY, 'backup service bundle must have a fixed, reviewed allowlist'
assert DEPLOY.index('(cd "$backup" && sha256sum -c SHA256SUMS >/dev/null)') < DEPLOY.index('backup-retention --migrate-legacy'), (
    'the verified pre-switch site-data snapshot must exist before legacy backups are migrated'
)
retention_install = 'install -o root -g root -m 0755 "$ops_stage/ops/site-vps/backup_retention.py" /usr/local/lib/dentistry1402/backup-retention'
assert DEPLOY.index('systemctl stop dentistry1402-backup-retention.timer') < DEPLOY.index(retention_install), (
    'the retention timer must be quiesced before replacing its live executable'
)
assert DEPLOY.index('(cd "$backup" && sha256sum -c SHA256SUMS >/dev/null)') < DEPLOY.index(retention_install), (
    'the verified site-data recovery snapshot must exist before replacing the live retention executable'
)
assert DEPLOY.index('A Dentistry backup service is still active; retry after it completes.') < DEPLOY.index(retention_install), (
    'all backup services must be quiescent before replacing the live retention executable'
)
assert DEPLOY.index('install -o root -g root -m 0755 "$ops_stage/ops/site-vps/backup-runtime.sh"') < DEPLOY.index(
    'backup-retention --migrate-legacy --migrate-only'
), 'new backup consumers must be installed before legacy archives move'
assert 'systemctl start dentistry1402-backup-retention.service' not in DEPLOY, (
    'the canonical website deploy must not trigger pruning of protected server-only recovery data'
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
