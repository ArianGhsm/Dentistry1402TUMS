param(
    [switch]$DeleteVscodeOnRemote
)

$ErrorActionPreference = "Stop"

$configPath = Join-Path $PSScriptRoot "..\.vscode\sftp.json"
if (-not (Test-Path $configPath)) {
    throw "Missing deploy config at $configPath"
}

$config = Get-Content $configPath -Raw | ConvertFrom-Json
if ($config.remotePath -ne "public_html") {
    throw "Unsafe remotePath detected: $($config.remotePath). Deploy is locked to public_html."
}

$localRoot = (Resolve-Path (Join-Path $PSScriptRoot "..\public_html")).Path
$remotePath = "/" + ($config.remotePath.TrimStart('/'))
$ftpBase = "ftp://$($config.host)$remotePath"
$credentials = "$($config.username):$($config.password)"

$files = Get-ChildItem -LiteralPath $localRoot -Recurse -File | Where-Object {
    $_.FullName -notlike "*\.vscode\*"
}

foreach ($file in $files) {
    $relative = $file.FullName.Substring($localRoot.Length).TrimStart('\') -replace '\\','/'
    $target = "$ftpBase/$relative"
    Write-Host "Uploading $relative"
    & curl.exe --silent --show-error --ftp-create-dirs --user $credentials -T "$($file.FullName)" "$target"
    if ($LASTEXITCODE -ne 0) {
        throw "Upload failed for $relative"
    }
}

if ($DeleteVscodeOnRemote) {
    & curl.exe --silent --show-error --user $credentials --quote "RMD $remotePath/.vscode" "ftp://$($config.host)/"
}

Write-Host "Deploy completed to $remotePath"
