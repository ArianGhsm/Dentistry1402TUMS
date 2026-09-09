[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[0-9a-f]{40}$')]
    [string]$ReleaseSha,
    [switch]$DryRun,
    [switch]$Deploy,
    [string]$ServerConfig = "bot_runtime/.codex-local/iran-server.json",
    [string]$ConfirmTargetHost = ""
)

$ErrorActionPreference = 'Stop'
if ($DryRun -eq $Deploy) { throw 'Specify exactly one of -DryRun or -Deploy.' }
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$child = Join-Path $root 'scripts\deploy_site_vps.ps1'
if (-not (Test-Path -LiteralPath $child -PathType Leaf)) { throw 'Canonical VPS deploy script is missing.' }
$mode = if ($DryRun) { 'dry-run' } else { 'deploy' }
$args = @{
    ReleaseSha = $ReleaseSha
    ServerConfig = $ServerConfig
}
if ($DryRun) { $args.DryRun = $true }
if (-not [string]::IsNullOrWhiteSpace($ConfirmTargetHost)) { $args.ConfirmTargetHost = $ConfirmTargetHost }

$started = Get-Date
& $child @args

$common = (& git -C $root rev-parse --git-common-dir).Trim()
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($common)) { throw 'Unable to resolve Git common directory.' }
if (-not [IO.Path]::IsPathRooted($common)) { $common = Join-Path $root $common }
$common = [IO.Path]::GetFullPath($common)
$ops = if ((Split-Path $common -Leaf) -eq '.git') { Split-Path $common -Parent } else { $root }
$reportRoot = Join-Path $ops ".codex-local\release-runs\$ReleaseSha"
$report = Get-ChildItem $reportRoot -Recurse -Filter release-report.json -ErrorAction SilentlyContinue |
    Where-Object { $_.LastWriteTime -ge $started.AddSeconds(-2) } |
    Sort-Object LastWriteTime -Descending | Select-Object -First 1
if ($null -eq $report) { throw 'RELEASE_REPORT_MISSING' }
$value = Get-Content -Raw -Encoding UTF8 $report.FullName | ConvertFrom-Json
if ($value.status -ne 'passed' -or $value.mode -ne $mode -or $value.releaseSha -ne $ReleaseSha) { throw 'RELEASE_GATE_FAILED' }
if (-not $value.deployPlan.complete) { throw 'RELEASE_PLAN_INCOMPLETE' }
if (@($value.deployPlan.protectedPathViolations).Count -ne 0) { throw 'RELEASE_PROTECTED_PATH_VIOLATION' }
if ($DryRun -and $value.productionMutation) { throw 'RELEASE_DRY_RUN_MUTATED_PRODUCTION' }
Write-Host "Release gate passed. Report: $($report.FullName)"
