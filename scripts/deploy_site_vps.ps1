[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[0-9a-f]{40}$')]
    [string]$ReleaseSha,
    [Parameter(Mandatory = $true)]
    [string]$ConfirmTargetHost,
    [switch]$DryRun,
    [string]$ServerConfig = "bot_runtime/.codex-local/iran-server.json",
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
$lifecycleBaseId = "website-vps-$runId"
$lifecycleStarted = $false
$lifecycleTerminalSent = $false
$temporaryRoot = ''

function Get-SharedOpsRoot {
    $common = (& git -C $projectRoot rev-parse --git-common-dir).Trim()
    if ($LASTEXITCODE -ne 0) { throw 'Unable to resolve Git common directory.' }
    if (-not [IO.Path]::IsPathRooted($common)) { $common = Join-Path $projectRoot $common }
    $common = (Resolve-Path -LiteralPath $common).Path
    if ((Split-Path $common -Leaf) -eq '.git') { return (Split-Path $common -Parent) }
    return $projectRoot
}

function Write-ReleaseReport([string]$status, [string]$failureCode = '', [string]$failureMessage = '') {
    $root = if ([string]::IsNullOrWhiteSpace($ReportRoot)) {
        Join-Path (Get-SharedOpsRoot) ".codex-local\release-runs\$ReleaseSha"
    } else { $ReportRoot }
    $dir = Join-Path $root $runId
    New-Item -ItemType Directory -Force -Path $dir | Out-Null
    $report = [ordered]@{
        schemaVersion = 2
        status = $status
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
        productionMutation = $productionMutation
        failureCode = $failureCode
        failureMessage = $failureMessage
    }
    $path = Join-Path $dir 'release-report.json'
    $temp = "$path.tmp"
    $report | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $temp -Encoding UTF8
    Move-Item -Force -LiteralPath $temp -Destination $path
    Write-Host "Release report: $path"
}

function Get-Sha256([string]$path) {
    $stream = [IO.File]::OpenRead($path)
    try {
        $sha = [Security.Cryptography.SHA256]::Create()
        try { return ([BitConverter]::ToString($sha.ComputeHash($stream))).Replace('-', '').ToLowerInvariant() }
        finally { $sha.Dispose() }
    } finally { $stream.Dispose() }
}

function Invoke-Ssh([string[]]$commandParts) {
    $output = @(& ssh @script:sshOptions $script:target -- @commandParts 2>&1)
    $code = $LASTEXITCODE
    foreach ($line in $output) { Write-Output $line }
    if ($code -ne 0) { throw "Remote command failed with exit code $code." }
    return @($output)
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

    $serverConfigPath = if ([IO.Path]::IsPathRooted($ServerConfig)) { $ServerConfig } else { Join-Path $projectRoot $ServerConfig }
    $serverConfigPath = (Resolve-Path -LiteralPath $serverConfigPath).Path
    $serverStateRoot = if ((Split-Path $serverConfigPath -Leaf) -eq 'iran-server.json' -and (Split-Path (Split-Path $serverConfigPath -Parent) -Leaf) -eq '.codex-local') {
        Split-Path (Split-Path $serverConfigPath -Parent) -Parent
    } else { $projectRoot }
    $server = Get-Content -Raw -Encoding UTF8 -LiteralPath $serverConfigPath | ConvertFrom-Json
    $targetHost = [string]$server.host
    if ($targetHost -ne $ConfirmTargetHost) { throw 'ConfirmTargetHost does not match the configured Iran VPS.' }
    $knownHosts = [string]$server.knownHostsFile
    if (-not [IO.Path]::IsPathRooted($knownHosts)) { $knownHosts = Join-Path $serverStateRoot $knownHosts }
    $knownHosts = [IO.Path]::GetFullPath($knownHosts)
    if (-not (Test-Path -LiteralPath $knownHosts -PathType Leaf)) { throw 'Pinned known_hosts file is missing.' }
    $identity = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
    if (-not (Test-Path -LiteralPath $identity -PathType Leaf)) { throw 'SSH identity file is missing.' }
    $sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
    $script:target = "$sshUser@$targetHost"
    $script:sshOptions = @('-i', $identity, '-p', [string]$server.port, '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes', '-o', 'ConnectTimeout=10', '-o', 'ConnectionAttempts=1', '-o', "UserKnownHostsFile=$knownHosts")
    $scpOptions = @('-q', '-i', $identity, '-P', [string]$server.port, '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes', '-o', 'ConnectTimeout=10', '-o', 'ConnectionAttempts=1', '-o', "UserKnownHostsFile=$knownHosts")

    $probe = @'
set -Eeuo pipefail
root=/srv/dentistry1402
[[ "$(readlink -f "$root")" == /srv/dentistry1402 ]]
test -d "$root/shared/storage"
test -d "$root/shared/server-only"
nginx -t >/dev/null
php-fpm8.3 -t >/dev/null
systemctl is-active --quiet nginx
systemctl is-active --quiet php8.3-fpm
systemctl is-active --quiet integrated-dent-bot.service
systemctl is-active --quiet integrated-dent-bale-bot.service
python3 - "$root/shared/storage" <<'PY'
import json, pathlib, sys
for p in pathlib.Path(sys.argv[1]).rglob('*.json'):
    json.loads(p.read_text(encoding='utf-8'))
PY
if test -f "$root/current/.release-sha"; then cat "$root/current/.release-sha"; fi
'@
    $probeOutput = @(& ssh @script:sshOptions $script:target -- bash -s 2>&1 <<< $probe)
    if ($LASTEXITCODE -ne 0) { throw 'VPS release preflight failed.' }
    $currentSha = ([string]($probeOutput | Select-Object -Last 1)).Trim()
    if ($currentSha -notmatch '^[0-9a-f]{40}$') { throw 'Active website release SHA is missing or invalid.' }
    $deployPlanComplete = $true

    if ($DryRun) {
        Write-Host "VPS dry-run passed. current=$currentSha target=$ReleaseSha mutationRequired=$($currentSha -ne $ReleaseSha)"
        Write-ReleaseReport -status 'passed'
        exit 0
    }

    if ($currentSha -eq $ReleaseSha) {
        Write-Host 'Exact SHA is already active; performing verification-only zero-delta release.'
    } else {
        . (Join-Path $projectRoot 'bot_runtime\scripts\deploy-lifecycle.ps1')
        Publish-DentDeployLifecycle -Service website -Status started -ReleaseId $ReleaseSha -EventBaseId $lifecycleBaseId -Summary 'Exact-SHA VPS website deployment started.' -ServerConfig $serverConfigPath
        $lifecycleStarted = $true

        $temporaryRoot = Join-Path $env:TEMP ("dent-site-vps-" + [Guid]::NewGuid().ToString('N'))
        New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
        $bundle = Join-Path $temporaryRoot 'site-code.tar.gz'
        & tar -C $projectRoot -czf $bundle public_html
        if ($LASTEXITCODE -ne 0 -or -not (Test-Path $bundle)) { throw 'Site code bundle creation failed.' }
        $bundleHash = Get-Sha256 $bundle
        $remotePrefix = "/tmp/dent-site-$runId"
        & scp @scpOptions $bundle "${script:target}:${remotePrefix}.tar.gz"
        if ($LASTEXITCODE -ne 0) { throw 'Site bundle transfer failed.' }

        $installerPath = Join-Path $temporaryRoot 'install.sh'
        $installer = @'
#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
root=/srv/dentistry1402
sha='__SHA__'
expected='__HASH__'
bundle='__PREFIX__.tar.gz'
release="$root/releases/$sha"
incoming="$root/releases/.incoming-$sha-$$"
previous="$(readlink -f "$root/current")"
activated=0
cleanup(){ rm -rf -- "$incoming"; rm -f -- "$bundle" '__PREFIX__.install.sh'; }
rollback(){
  if test "$activated" = 1 && test -n "$previous" && test -d "$previous"; then
    ln -sfn "$previous" "$root/current.next"
    mv -Tf "$root/current.next" "$root/current"
    echo SITE_ROLLED_BACK
  fi
}
trap 'rc=$?; if test "$rc" -ne 0; then rollback || true; fi; cleanup; exit "$rc"' EXIT
[[ "$(readlink -f "$root")" == /srv/dentistry1402 ]]
printf '%s  %s\n' "$expected" "$bundle" | sha256sum -c -
tar -tzf "$bundle" | awk 'BEGIN{ok=1} /^\//{ok=0} /(^|\/)\.\.($|\/)/{ok=0} !/^public_html\// && $0!="public_html"{ok=0} END{exit ok?0:1}'
nginx -t >/dev/null
php-fpm8.3 -t >/dev/null
python3 - "$root/shared/storage" <<'PY'
import json, pathlib, sys
for p in pathlib.Path(sys.argv[1]).rglob('*.json'):
    json.loads(p.read_text(encoding='utf-8'))
PY
backup="/var/backups/dent-site-release-$(date -u +%Y%m%dT%H%M%SZ)-${sha:0:12}"
install -d -o root -g root -m 0700 "$backup"
printf 'previous=%s\ntarget=%s\n' "$previous" "$sha" > "$backup/runtime-pointers.txt"
tar -C "$root/shared" -czf "$backup/runtime-data.tar.gz" storage server-only
sha256sum "$backup/runtime-pointers.txt" "$backup/runtime-data.tar.gz" > "$backup/SHA256SUMS"
if test -e "$release"; then
  test "$(cat "$release/.release-sha")" = "$sha"
else
  install -d -o root -g dentweb -m 0750 "$incoming"
  tar -xzf "$bundle" -C "$incoming"
  printf '%s\n' "$sha" > "$incoming/.release-sha"
  test -d "$incoming/public_html"
  test ! -e "$incoming/storage"
  test ! -e "$incoming/server-only"
  ! find "$incoming/public_html" -type f \( -name '.env' -o -name '*.sqlite' -o -name '*.sqlite3' -o -name '*.key' -o -name '*.pem' \) -print -quit | grep -q .
  while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find "$incoming/public_html" -type f -name '*.php' -print0)
  if command -v node >/dev/null 2>&1; then while IFS= read -r -d '' f; do node --check "$f" >/dev/null; done < <(find "$incoming/public_html" -type f -name '*.js' -print0); fi
  chown -R root:dentweb "$incoming"
  find "$incoming" -type d -exec chmod 0750 {} +
  find "$incoming" -type f -exec chmod 0640 {} +
  mv "$incoming" "$release"
fi
ln -sfn "releases/$sha" "$root/current.next"
mv -Tf "$root/current.next" "$root/current"
activated=1
for u in 'https://dentistry1402tums.ir/' 'https://dentistry1402tums.ir/chat/' 'https://dentistry1402tums.ir/api/auth_api.php?action=me'; do
  code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$u")"
  [[ "$code" =~ ^2|3 ]]
done
for u in '/.env' '/storage/auth/users.json' '/server-only/.env' '/.git/config' '/backups/'; do
  code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "https://dentistry1402tums.ir$u")"
  test "$code" = 403
done
systemctl is-active --quiet integrated-dent-bot.service
systemctl is-active --quiet integrated-dent-bale-bot.service
set -a; source /etc/integrated-dent/dent-bot.env; set +a
cd /opt/integrated-dent/telegram/current
/opt/integrated-dent/telegram-venv/bin/python -m dent_bot.health | grep -q '"ready": true'
/opt/integrated-dent/telegram-venv/bin/python -m dent_bot.site_health | grep -q '"ready": true'
set -a; source /etc/integrated-dent/bale-bot.env; set +a
cd /opt/integrated-dent/bale/current
/usr/bin/python3 -m dent_bot.bale_health | grep -q '"ready": true'
echo SITE_VPS_DEPLOY_OK
'@
        $installer = $installer.Replace('__SHA__', $ReleaseSha).Replace('__HASH__', $bundleHash).Replace('__PREFIX__', $remotePrefix)
        Set-Content -LiteralPath $installerPath -Value $installer -Encoding UTF8
        & scp @scpOptions $installerPath "${script:target}:${remotePrefix}.install.sh"
        if ($LASTEXITCODE -ne 0) { throw 'Site installer transfer failed.' }
        $remoteOutput = @(& ssh @script:sshOptions $script:target -- bash "${remotePrefix}.install.sh" 2>&1)
        $remoteCode = $LASTEXITCODE
        foreach ($line in $remoteOutput) { Write-Output $line }
        if ($remoteCode -ne 0) {
            if (($remoteOutput -join "`n") -match 'SITE_ROLLED_BACK') {
                Publish-DentDeployLifecycle -Service website -Status rolled_back -ReleaseId $ReleaseSha -EventBaseId $lifecycleBaseId -Summary 'VPS website deployment failed post-activation and was rolled back.' -ServerConfig $serverConfigPath
                $lifecycleTerminalSent = $true
            }
            throw "VPS installer failed with exit code $remoteCode."
        }
        $productionMutation = $true
        $currentSha = $ReleaseSha
        Publish-DentDeployLifecycle -Service website -Status succeeded -ReleaseId $ReleaseSha -EventBaseId $lifecycleBaseId -Summary 'Exact-SHA VPS website deployment and live verification passed.' -ServerConfig $serverConfigPath
        $lifecycleTerminalSent = $true
    }

    $verify = @'
set -Eeuo pipefail
test "$(cat /srv/dentistry1402/current/.release-sha)" = '__SHA__'
nginx -t >/dev/null
php-fpm8.3 -t >/dev/null
systemctl is-active --quiet nginx
systemctl is-active --quiet php8.3-fpm
for u in 'https://dentistry1402tums.ir/' 'https://dentistry1402tums.ir/chat/' 'https://dentistry1402tums.ir/api/auth_api.php?action=me'; do
  code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$u")"; [[ "$code" =~ ^2|3 ]]
done
'@
    $verify = $verify.Replace('__SHA__', $ReleaseSha)
    $verifyOutput = @(& ssh @script:sshOptions $script:target -- bash -s 2>&1 <<< $verify)
    if ($LASTEXITCODE -ne 0) { throw 'Final exact-SHA VPS verification failed.' }
    Write-ReleaseReport -status 'passed'
    exit 0
} catch {
    $message = $_.Exception.Message
    Write-Warning $message
    if (-not $DryRun -and $lifecycleStarted -and -not $lifecycleTerminalSent) {
        try {
            . (Join-Path $projectRoot 'bot_runtime\scripts\deploy-lifecycle.ps1')
            Publish-DentDeployLifecycle -Service website -Status failed -ReleaseId $ReleaseSha -EventBaseId $lifecycleBaseId -Summary 'VPS website deployment failed before successful completion.' -ServerConfig $serverConfigPath
        } catch { Write-Warning 'Failed lifecycle notification could not be queued.' }
    }
    Write-ReleaseReport -status 'failed' -failureCode 'VPS_RELEASE_FAILED' -failureMessage $message
    exit 1
} finally {
    if (-not [string]::IsNullOrWhiteSpace($temporaryRoot) -and (Test-Path -LiteralPath $temporaryRoot)) {
        Remove-Item -Recurse -Force -LiteralPath $temporaryRoot -ErrorAction SilentlyContinue
    }
}
