param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$DeployArgs
)

$deployScript = Join-Path $PSScriptRoot "deploy_public_html.ps1"
if (-not (Test-Path -LiteralPath $deployScript -PathType Leaf)) {
    throw "Canonical deploy script not found: $deployScript"
}

$powershellCommand = Get-Command powershell -ErrorAction SilentlyContinue
if ($null -eq $powershellCommand -or [string]::IsNullOrWhiteSpace([string]$powershellCommand.Source)) {
    throw "PowerShell executable not found for task completion wrapper."
}

& $powershellCommand.Source -ExecutionPolicy Bypass -File $deployScript @DeployArgs
exit $LASTEXITCODE
