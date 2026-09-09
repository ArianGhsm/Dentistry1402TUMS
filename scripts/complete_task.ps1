[CmdletBinding()]
param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$DeployArgs
)

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

& $powershellCommand.Source -ExecutionPolicy Bypass -File $gate -Deploy @DeployArgs
exit $LASTEXITCODE
