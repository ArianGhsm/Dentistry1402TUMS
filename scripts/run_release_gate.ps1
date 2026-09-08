param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[0-9a-f]{40}$')]
    [string]$ReleaseSha,
    [switch]$DryRun,
    [switch]$Deploy
)

$ErrorActionPreference = 'Stop'
if ($DryRun -eq $Deploy) { throw 'Specify exactly one of -DryRun or -Deploy.' }
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$child = Join-Path $root 'scripts\deploy_public_html.ps1'
$mode = if ($DryRun) { 'dry-run' } else { 'deploy' }
if ($DryRun) {
    & $child -ReleaseSha $ReleaseSha -DryRun
} else {
    & $child -ReleaseSha $ReleaseSha
}
$code = $LASTEXITCODE
$ops = Split-Path ((Resolve-Path (& git -C $root rev-parse --git-common-dir)).Path) -Parent
$reportRoot = Join-Path $ops ".codex-local\release-runs\$ReleaseSha"
$report = Get-ChildItem $reportRoot -Recurse -Filter release-report.json -ErrorAction SilentlyContinue |
    Sort-Object LastWriteTime -Descending | Select-Object -First 1
if ($null -eq $report) { throw 'RELEASE_REPORT_MISSING' }
$value = Get-Content -Raw $report.FullName | ConvertFrom-Json
if ($code -ne 0 -or $value.status -ne 'passed' -or $value.mode -ne $mode) { throw 'RELEASE_GATE_FAILED' }
if ($DryRun -and (-not $value.deployPlan.complete -or $value.productionMutation)) { throw 'RELEASE_PLAN_INCOMPLETE' }
Write-Host "Release gate passed. Report: $($report.FullName)"
