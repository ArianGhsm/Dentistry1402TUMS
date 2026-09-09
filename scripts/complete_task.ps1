[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[0-9a-f]{40}$')]
    [string]$ReleaseSha,
    [string]$ServerConfig = "bot_runtime/.codex-local/iran-server.json",
    [string]$ConfirmTargetHost = ""
)

$ErrorActionPreference = 'Stop'
$gate = Join-Path $PSScriptRoot 'run_release_gate.ps1'
if (-not (Test-Path -LiteralPath $gate -PathType Leaf)) {
    throw "Canonical release gate not found: $gate"
}

$powershellCommand = Get-Command powershell -ErrorAction SilentlyContinue
if ($null -eq $powershellCommand -or [string]::IsNullOrWhiteSpace([string]$powershellCommand.Source)) {
    $powershellCommand = Get-Command pwsh -ErrorAction SilentlyContinue
}
if ($null -eq $powershellCommand -or [string]::IsNullOrWhiteSpace([string]$powershellCommand.Source)) {
    throw 'PowerShell executable not found for task completion wrapper.'
}

$gateArgs = @(
    '-ReleaseSha', $ReleaseSha,
    '-ServerConfig', $ServerConfig,
    '-Deploy'
)
if (-not [string]::IsNullOrWhiteSpace($ConfirmTargetHost)) {
    $gateArgs += @('-ConfirmTargetHost', $ConfirmTargetHost)
}

& $powershellCommand.Source -ExecutionPolicy Bypass -File $gate @gateArgs
exit $LASTEXITCODE
