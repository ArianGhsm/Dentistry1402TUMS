[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[0-9a-f]{40}$')]
    [string]$ReleaseSha,
    [switch]$DryRun,
    [string]$ServerConfig = "bot_runtime/.codex-local/iran-server.json",
    [string]$ConfirmTargetHost = "",
    [string]$ReportRoot = ""
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$expectedRepository = 'ArianGhsm/Dentistry1402TUMS'
$runStartedAt = [DateTimeOffset]::Now
$runId = (Get-Date -Format 'yyyyMMdd-HHmmss') + '-' + [Guid]::NewGuid().ToString('N').Substring(0, 8)
$mode = if ($DryRun) { 'dry-run' } else { 'deploy' }
$productionMutation = $false
$deployPlanComplete = $false
$currentSha = ''
$targetHost = ''
$dataBackupPath = ''
$lifecycleStarted = $false
$lifecycleTerminalSent = $false
$temporaryRoot = ''
$lifecycleBaseId = "website-vps-$runId"

function Get-SharedOpsRoot {
    $common = (& git -C $projectRoot rev-parse --git-common-dir).Trim()
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($common)) {
        throw 'Unable to resolve Git common directory.'
    }
    if (-not [IO.Path]::IsPathRooted($common)) {
        $common = Join-Path $projectRoot $common
    }
    $common = [IO.Path]::GetFullPath($common)
    if ((Split-Path $common -Leaf) -eq '.git') {
        return (Split-Path $common -Parent)
    }
    return $projectRoot
}

function Resolve-SharedFile([string]$Path) {
    if ([IO.Path]::IsPathRooted($Path)) {
        return (Resolve-Path -LiteralPath $Path).Path
    }
    $local = Join-Path $projectRoot $Path
    if (Test-Path -LiteralPath $local -PathType Leaf) {
        return (Resolve-Path -LiteralPath $local).Path
    }
    $shared = Join-Path (Get-SharedOpsRoot) $Path
    return (Resolve-Path -LiteralPath $shared).Path
}

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    $encoding = New-Object System.Text.UTF8Encoding($false)
    [IO.File]::WriteAllText($Path, $Content, $encoding)
}

function Write-ReleaseReport([string]$Status, [string]$FailureCode = '', [string]$FailureMessage = '') {
    $root = if ([string]::IsNullOrWhiteSpace($ReportRoot)) {
        Join-Path (Get-SharedOpsRoot) ".codex-local\release-runs\$ReleaseSha"
    } else {
        [IO.Path]::GetFullPath($ReportRoot)
    }
    $dir = Join-Path $root $runId
    New-Item -ItemType Directory -Force -Path $dir | Out-Null
    $report = [ordered]@{
        schemaVersion = 4
        status = $Status
        mode = $mode
        releaseSha = $ReleaseSha
        target = 'vps:/srv/dentistry1402'
        targetHost = $targetHost
        startedAt = $runStartedAt.ToString('o')
        finishedAt = [DateTimeOffset]::Now.ToString('o')
        deployPlan = [ordered]@{
            complete = $deployPlanComplete
            currentSha = $currentSha
            targetSha = $ReleaseSha
            mutationRequired = (-not [string]::IsNullOrWhiteSpace($currentSha) -and $currentSha -ne $ReleaseSha)
            protectedPathViolations = @()
        }
        protectedRuntimeRoots = @('/srv/dentistry1402/shared/storage', '/srv/dentistry1402/shared/server-only')
        verifiedDataBackup = $dataBackupPath
        productionMutation = $productionMutation
        failureCode = $FailureCode
        failureMessage = $FailureMessage
    }
    $path = Join-Path $dir 'release-report.json'
    $temp = "$path.tmp"
    Write-Utf8NoBom -Path $temp -Content ($report | ConvertTo-Json -Depth 8)
    Move-Item -Force -LiteralPath $temp -Destination $path
    Write-Host "Release report: $path"
}

function Get-Sha256([string]$Path) {
    $stream = [IO.File]::OpenRead($Path)
    try {
        $sha = [Security.Cryptography.SHA256]::Create()
        try {
            return ([BitConverter]::ToString($sha.ComputeHash($stream))).Replace('-', '').ToLowerInvariant()
        } finally {
            $sha.Dispose()
        }
    } finally {
        $stream.Dispose()
    }
}

function Invoke-RemoteBash([string]$ScriptText) {
    $encoded = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($ScriptText))
    $remoteCommand = "printf '%s' '$encoded' | base64 -d | sudo bash"
    $output = @(& ssh @script:sshOptions $script:target $remoteCommand 2>&1)
    $code = $LASTEXITCODE
    if ($code -ne 0) {
        $tail = (@($output) | Select-Object -Last 20) -join "`n"
        throw "Remote command failed with exit code $code.`n$tail"
    }
    return @($output)
}

function Assert-CodeOnlyPublicHtml {
    $tracked = @(& git -C $projectRoot ls-files -- public_html 2>&1)
    if ($LASTEXITCODE -ne 0) { throw 'Unable to enumerate tracked public_html files.' }
    foreach ($raw in $tracked) {
        $relative = ([string]$raw).Replace('\\', '/').Trim()
        if ($relative -match '^public_html/(?:storage|server-only)(?:/|$)') {
            throw "Protected runtime path is tracked under public_html: $relative"
        }
        if ($relative -match '(?i)(?:^|/)(?:\.env(?:\..*)?|[^/]*\.(?:sqlite3?|db|key|pem))$') {
            throw "Secret/database-like file is tracked under public_html: $relative"
        }
    }
}

try {
    $origin = (& git -C $projectRoot remote get-url origin).Trim()
    if ($LASTEXITCODE -ne 0 -or $origin -notmatch '(?i)(?:github\.com[/:])ArianGhsm/Dentistry1402TUMS(?:\.git)?/?$') {
        throw "Repository lock failed; expected $expectedRepository."
    }

    & git -C $projectRoot fetch origin main --quiet
    if ($LASTEXITCODE -ne 0) { throw 'git fetch origin main failed.' }
    $headSha = (& git -C $projectRoot rev-parse HEAD).Trim()
    $originMainSha = (& git -C $projectRoot rev-parse origin/main).Trim()
    $dirty = (& git -C $projectRoot status --porcelain=v1 --untracked-files=all) -join "`n"
    if ($headSha -ne $ReleaseSha) { throw 'HEAD does not equal -ReleaseSha.' }
    if ($originMainSha -ne $ReleaseSha) { throw 'origin/main does not equal -ReleaseSha.' }
    if (-not [string]::IsNullOrWhiteSpace($dirty)) { throw 'Release workspace is not clean.' }
    Assert-CodeOnlyPublicHtml

    $serverConfigPath = Resolve-SharedFile -Path $ServerConfig
    $serverStateRoot = if ((Split-Path $serverConfigPath -Leaf) -eq 'iran-server.json' -and (Split-Path (Split-Path $serverConfigPath -Parent) -Leaf) -eq '.codex-local') {
        Split-Path (Split-Path $serverConfigPath -Parent) -Parent
    } else {
        $projectRoot
    }
    $server = Get-Content -Raw -Encoding UTF8 -LiteralPath $serverConfigPath | ConvertFrom-Json
    $targetHost = [string]$server.host
    if ([string]::IsNullOrWhiteSpace($targetHost)) { throw 'Iran VPS host is missing from server config.' }
    if (-not [string]::IsNullOrWhiteSpace($ConfirmTargetHost) -and $ConfirmTargetHost -ne $targetHost) {
        throw 'ConfirmTargetHost does not match the configured Iran VPS.'
    }

    $knownHosts = [string]$server.knownHostsFile
    if (-not [IO.Path]::IsPathRooted($knownHosts)) { $knownHosts = Join-Path $serverStateRoot $knownHosts }
    $knownHosts = [IO.Path]::GetFullPath($knownHosts)
    if (-not (Test-Path -LiteralPath $knownHosts -PathType Leaf)) { throw 'Pinned known_hosts file is missing.' }
    $identity = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
    if (-not (Test-Path -LiteralPath $identity -PathType Leaf)) { throw 'SSH identity file is missing.' }
    $sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
    if ($sshUser -notmatch '^[a-z_][a-z0-9_-]*$') { throw 'Configured SSH user is invalid.' }
    $script:target = "$sshUser@$targetHost"
    $script:sshOptions = @(
        '-i', $identity, '-p', [string]$server.port,
        '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes',
        '-o', 'ConnectTimeout=10', '-o', 'ConnectionAttempts=1',
        '-o', "UserKnownHostsFile=$knownHosts"
    )
    $scpOptions = @(
        '-q', '-i', $identity, '-P', [string]$server.port,
        '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes',
        '-o', 'ConnectTimeout=10', '-o', 'ConnectionAttempts=1',
        '-o', "UserKnownHostsFile=$knownHosts"
    )

    $preflight = @'
set -Eeuo pipefail
root=/srv/dentistry1402
[[ "$(readlink -f "$root")" == /srv/dentistry1402 ]]
test -d "$root/releases"
test -d "$root/shared/storage"
test -d "$root/shared/server-only"
test -f "$root/current/.release-sha"
nginx -t >/dev/null
php-fpm8.3 -t >/dev/null
nginx -T 2>&1 | grep -F 'root /srv/dentistry1402/current/public_html;' >/dev/null
systemctl is-active --quiet nginx
systemctl is-active --quiet php8.3-fpm
systemctl is-active --quiet integrated-dent-bot.service
systemctl is-active --quiet integrated-dent-bale-bot.service
python3 - "$root/shared/storage" <<'PY'
import json, pathlib, sys
for path in pathlib.Path(sys.argv[1]).rglob('*.json'):
    json.loads(path.read_text(encoding='utf-8'))
PY
cat "$root/current/.release-sha"
'@
    $preflightOutput = @(Invoke-RemoteBash -ScriptText $preflight)
    $currentSha = ([string]($preflightOutput | Select-Object -Last 1)).Trim()
    if ($currentSha -notmatch '^[0-9a-f]{40}$') { throw 'Active website release SHA is missing or invalid.' }
    $deployPlanComplete = $true

    $verification = @'
set -Eeuo pipefail
root=/srv/dentistry1402
sha='__SHA__'
test "$(cat "$root/current/.release-sha")" = "$sha"
test -d "$root/shared/storage"
test -d "$root/shared/server-only"
nginx -t >/dev/null
php-fpm8.3 -t >/dev/null
nginx -T 2>&1 | grep -F 'root /srv/dentistry1402/current/public_html;' >/dev/null
systemctl is-active --quiet nginx
systemctl is-active --quiet php8.3-fpm
systemctl is-active --quiet integrated-dent-bot.service
systemctl is-active --quiet integrated-dent-bale-bot.service
for url in \
  'https://dentistry1402tums.ir/' \
  'https://dentistry1402tums.ir/chat/' \
  'https://dentistry1402tums.ir/api/auth_api.php?action=me'; do
  code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$url")"
  [[ "$code" =~ ^[23] ]]
done
for path in '/.env' '/storage/auth/users.json' '/server-only/.env' '/.git/config' '/backups/'; do
  code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "https://dentistry1402tums.ir$path")"
  test "$code" = 403
done
python3 - "$root/shared/storage" <<'PY'
import json, pathlib, sys
for path in pathlib.Path(sys.argv[1]).rglob('*.json'):
    json.loads(path.read_text(encoding='utf-8'))
PY
set -a
source /etc/integrated-dent/dent-bot.env
set +a
cd /opt/integrated-dent/telegram/current
/opt/integrated-dent/telegram-venv/bin/python -m dent_bot.health | grep -q '"ready": true'
/opt/integrated-dent/telegram-venv/bin/python -m dent_bot.site_health | grep -q '"ready": true'
set -a
source /etc/integrated-dent/bale-bot.env
set +a
cd /opt/integrated-dent/bale/current
/usr/bin/python3 -m dent_bot.bale_health | grep -q '"ready": true'
'@
    $verification = $verification.Replace('__SHA__', $ReleaseSha)

    if ($DryRun) {
        Write-Host "VPS dry-run passed. current=$currentSha target=$ReleaseSha mutationRequired=$($currentSha -ne $ReleaseSha)"
        Write-ReleaseReport -Status 'passed'
        return
    }

    if ($currentSha -eq $ReleaseSha) {
        [void](Invoke-RemoteBash -ScriptText $verification)
        Write-Host 'Exact SHA already active; verification-only release passed with zero production mutation.'
        Write-ReleaseReport -Status 'passed'
        return
    }

    . (Join-Path $projectRoot 'bot_runtime\scripts\deploy-lifecycle.ps1')
    Publish-DentDeployLifecycle -Service website -Status started -ReleaseId $ReleaseSha -EventBaseId $lifecycleBaseId -Summary 'Exact-SHA VPS website deployment started.' -ServerConfig $serverConfigPath
    $lifecycleStarted = $true

    $temporaryRoot = Join-Path $env:TEMP ("dent-site-vps-" + [Guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
    $bundle = Join-Path $temporaryRoot 'site-code.tar.gz'
    & tar -C $projectRoot -czf $bundle public_html
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $bundle -PathType Leaf)) { throw 'Site code bundle creation failed.' }
    $bundleHash = Get-Sha256 -Path $bundle
    $remotePrefix = "/tmp/dent-site-$runId"
    & scp @scpOptions $bundle "${script:target}:${remotePrefix}.tar.gz"
    if ($LASTEXITCODE -ne 0) { throw 'Site code transfer failed.' }

    $installerPath = Join-Path $temporaryRoot 'install.sh'
    $installer = @'
#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
root=/srv/dentistry1402
sha='__SHA__'
expected='__HASH__'
prefix='__PREFIX__'
bundle="${prefix}.tar.gz"
release="$root/releases/$sha"
incoming="$root/releases/.incoming-$sha-$$"
previous="$(readlink -f "$root/current")"
activated=0
backup=''

cleanup() {
  rm -rf -- "$incoming"
  rm -f -- "$bundle" "${prefix}.install.sh"
}
rollback() {
  test -n "$previous" && test -d "$previous"
  ln -sfn "$previous" "$root/current.next"
  mv -Tf "$root/current.next" "$root/current"
  systemctl reload php8.3-fpm
}
on_exit() {
  rc=$?
  if test "$rc" -ne 0 && test "$activated" = 1; then
    if rollback; then echo SITE_ROLLED_BACK; else echo SITE_ROLLBACK_FAILED; fi
  fi
  cleanup
  exit "$rc"
}
trap on_exit EXIT

[[ "$(readlink -f "$root")" == /srv/dentistry1402 ]]
test -d "$root/shared/storage"
test -d "$root/shared/server-only"
printf '%s  %s\n' "$expected" "$bundle" | sha256sum -c -
tar -tzf "$bundle" | awk 'BEGIN{ok=1} /^\//{ok=0} /(^|\/)\.\.($|\/)/{ok=0} !/^public_html\// && $0!="public_html"{ok=0} END{exit ok?0:1}'
nginx -t >/dev/null
php-fpm8.3 -t >/dev/null
nginx -T 2>&1 | grep -F 'root /srv/dentistry1402/current/public_html;' >/dev/null
python3 - "$root/shared/storage" <<'PY'
import json, pathlib, sys
for path in pathlib.Path(sys.argv[1]).rglob('*.json'):
    json.loads(path.read_text(encoding='utf-8'))
PY

# A code release should not mutate shared state by design, but new application
# code can still expose migration-on-read bugs. Keep a verified pre-switch data
# snapshot for manual recovery; never auto-restore it over concurrent writes.
storage_bytes="$(du -sb "$root/shared/storage" | awk '{print $1}')"
avail_bytes="$(df -B1 --output=avail /var/backups | tail -1 | tr -d ' ')"
required_bytes="$((storage_bytes * 2 + 209715200))"
test "$avail_bytes" -gt "$required_bytes"
backup="/var/backups/dent-site-data-$(date -u +%Y%m%dT%H%M%SZ)-${sha:0:12}"
install -d -o root -g root -m 0700 "$backup"
printf 'previous=%s\ntarget=%s\n' "$previous" "$sha" > "$backup/runtime-pointers.txt"
tar -C "$root/shared" -czf "$backup/storage.tar.gz" storage
sha256sum "$backup/runtime-pointers.txt" "$backup/storage.tar.gz" > "$backup/SHA256SUMS"
(cd "$backup" && sha256sum -c SHA256SUMS >/dev/null)
chmod 0600 "$backup/runtime-pointers.txt" "$backup/storage.tar.gz" "$backup/SHA256SUMS"
echo "SITE_DATA_BACKUP=$backup"

# Keep the five newest verified canonical site-data backups. Validate every
# deletion candidate before removing anything, and never match forensic or
# manually named backup directories outside this exact timestamped namespace.
mapfile -t site_backups < <(find /var/backups -maxdepth 1 -mindepth 1 -type d \
  -name 'dent-site-data-????????T??????Z-????????????' -printf '%p\n' | sort -r)
if test "${#site_backups[@]}" -gt 5; then
  for candidate in "${site_backups[@]:5}"; do
    [[ "$candidate" =~ ^/var/backups/dent-site-data-[0-9]{8}T[0-9]{6}Z-[0-9a-f]{12}$ ]]
    test -f "$candidate/runtime-pointers.txt"
    test -f "$candidate/storage.tar.gz"
    test -f "$candidate/SHA256SUMS"
    (cd "$candidate" && sha256sum -c SHA256SUMS >/dev/null)
  done
  for candidate in "${site_backups[@]:5}"; do
    rm -rf --one-file-system -- "$candidate"
  done
fi
echo "SITE_DATA_BACKUPS_RETAINED=$(find /var/backups -maxdepth 1 -mindepth 1 -type d -name 'dent-site-data-????????T??????Z-????????????' | wc -l)"

validate_release() {
  candidate="$1"
  test "$(cat "$candidate/.release-sha")" = "$sha"
  test -d "$candidate/public_html"
  test ! -e "$candidate/public_html/storage"
  test ! -e "$candidate/public_html/server-only"
  ! find "$candidate/public_html" -type l -print -quit | grep -q .
  ! find "$candidate/public_html" -type f \( -name '.env' -o -name '.env.*' -o -name '*.sqlite' -o -name '*.sqlite3' -o -name '*.db' -o -name '*.key' -o -name '*.pem' \) -print -quit | grep -q .
  while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find "$candidate/public_html" -type f -name '*.php' -print0)
  if command -v node >/dev/null 2>&1; then
    while IFS= read -r -d '' file; do node --check "$file" >/dev/null; done < <(find "$candidate/public_html" -type f -name '*.js' -not -path '*/vendor/*' -print0)
  fi
}

if test -e "$release"; then
  validate_release "$release"
else
  install -d -o root -g dentweb -m 0750 "$incoming"
  tar -xzf "$bundle" -C "$incoming"
  printf '%s\n' "$sha" > "$incoming/.release-sha"
  validate_release "$incoming"
  chown -R root:dentweb "$incoming"
  find "$incoming" -type d -exec chmod 0750 {} +
  find "$incoming" -type f -exec chmod 0640 {} +
  mv "$incoming" "$release"
fi

ln -sfn "releases/$sha" "$root/current.next"
mv -Tf "$root/current.next" "$root/current"
activated=1
systemctl reload php8.3-fpm

for url in \
  'https://dentistry1402tums.ir/' \
  'https://dentistry1402tums.ir/chat/' \
  'https://dentistry1402tums.ir/api/auth_api.php?action=me'; do
  code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$url")"
  [[ "$code" =~ ^[23] ]]
done
for path in '/.env' '/storage/auth/users.json' '/server-only/.env' '/.git/config' '/backups/'; do
  code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "https://dentistry1402tums.ir$path")"
  test "$code" = 403
done
python3 - "$root/shared/storage" <<'PY'
import json, pathlib, sys
for path in pathlib.Path(sys.argv[1]).rglob('*.json'):
    json.loads(path.read_text(encoding='utf-8'))
PY
systemctl is-active --quiet integrated-dent-bot.service
systemctl is-active --quiet integrated-dent-bale-bot.service
set -a
source /etc/integrated-dent/dent-bot.env
set +a
cd /opt/integrated-dent/telegram/current
/opt/integrated-dent/telegram-venv/bin/python -m dent_bot.health | grep -q '"ready": true'
/opt/integrated-dent/telegram-venv/bin/python -m dent_bot.site_health | grep -q '"ready": true'
set -a
source /etc/integrated-dent/bale-bot.env
set +a
cd /opt/integrated-dent/bale/current
/usr/bin/python3 -m dent_bot.bale_health | grep -q '"ready": true'

echo SITE_VPS_DEPLOY_OK
'@
    $installer = $installer.Replace('__SHA__', $ReleaseSha).Replace('__HASH__', $bundleHash).Replace('__PREFIX__', $remotePrefix)
    Write-Utf8NoBom -Path $installerPath -Content $installer
    & scp @scpOptions $installerPath "${script:target}:${remotePrefix}.install.sh"
    if ($LASTEXITCODE -ne 0) { throw 'Site installer transfer failed.' }

    $remoteOutput = @(& ssh @script:sshOptions $script:target sudo bash "${remotePrefix}.install.sh" 2>&1)
    $remoteCode = $LASTEXITCODE
    foreach ($line in $remoteOutput) { Write-Output $line }
    foreach ($line in $remoteOutput) {
        if ([string]$line -match '^SITE_DATA_BACKUP=(/var/backups/[A-Za-z0-9._-]+)$') {
            $dataBackupPath = $Matches[1]
        }
    }
    if ($remoteCode -ne 0) {
        $joined = $remoteOutput -join "`n"
        if ($joined -match 'SITE_ROLLED_BACK') {
            $productionMutation = $true
            Publish-DentDeployLifecycle -Service website -Status rolled_back -ReleaseId $ReleaseSha -EventBaseId $lifecycleBaseId -Summary 'VPS website deployment failed after activation and code was rolled back.' -ServerConfig $serverConfigPath
            $lifecycleTerminalSent = $true
        }
        if ($joined -match 'SITE_ROLLBACK_FAILED') {
            $productionMutation = $true
            throw 'VPS website deploy failed and automatic code rollback failed.'
        }
        throw "VPS website deploy failed with exit code $remoteCode."
    }

    if ([string]::IsNullOrWhiteSpace($dataBackupPath)) {
        throw 'Remote deploy completed without reporting its verified pre-switch data backup.'
    }
    $productionMutation = $true
    $currentSha = $ReleaseSha
    Publish-DentDeployLifecycle -Service website -Status succeeded -ReleaseId $ReleaseSha -EventBaseId $lifecycleBaseId -Summary 'Exact-SHA VPS website deployment and live verification passed.' -ServerConfig $serverConfigPath
    $lifecycleTerminalSent = $true
    Write-ReleaseReport -Status 'passed'
} catch {
    $message = $_.Exception.Message
    Write-Warning $message
    if (-not $DryRun -and $lifecycleStarted -and -not $lifecycleTerminalSent) {
        try {
            . (Join-Path $projectRoot 'bot_runtime\scripts\deploy-lifecycle.ps1')
            Publish-DentDeployLifecycle -Service website -Status failed -ReleaseId $ReleaseSha -EventBaseId $lifecycleBaseId -Summary 'VPS website deployment failed before successful completion.' -ServerConfig $serverConfigPath
        } catch {
            Write-Warning 'Lifecycle failure notification could not be delivered or queued.'
        }
    }
    try {
        Write-ReleaseReport -Status 'failed' -FailureCode 'VPS_RELEASE_FAILED' -FailureMessage $message
    } catch {
        Write-Warning 'Durable release report could not be written.'
    }
    throw
} finally {
    if (-not [string]::IsNullOrWhiteSpace($temporaryRoot) -and (Test-Path -LiteralPath $temporaryRoot)) {
        Remove-Item -Recurse -Force -LiteralPath $temporaryRoot -ErrorAction SilentlyContinue
    }
}
