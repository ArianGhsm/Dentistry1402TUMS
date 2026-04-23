param(
    [switch]$DeleteVscodeOnRemote,
    [switch]$FullSync,
    [switch]$DryRun
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
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$remotePath = "/" + ($config.remotePath.TrimStart('/'))
$ftpBase = "ftp://$($config.host)$remotePath"
$credentials = "$($config.username):$($config.password)"

function Add-RelativePath([System.Collections.Generic.HashSet[string]]$set, [string]$path) {
    if ([string]::IsNullOrWhiteSpace($path)) {
        return
    }

    $normalized = ($path -replace '\\', '/').Trim()
    if (-not $normalized.StartsWith("public_html/")) {
        return
    }

    $relative = $normalized.Substring("public_html/".Length)
    if ([string]::IsNullOrWhiteSpace($relative)) {
        return
    }

    [void]$set.Add($relative)
}

function Run-Git([string[]]$GitArgs) {
    $output = & git -C $projectRoot @GitArgs
    if ($LASTEXITCODE -ne 0) {
        throw "Git command failed: git -C $projectRoot $($GitArgs -join ' ')"
    }

    if ($null -eq $output) {
        return @()
    }

    if ($output -is [System.Array]) {
        return @($output)
    }

    return @([string]$output)
}

function Upload-File([string]$relative) {
    $source = Join-Path $localRoot ($relative -replace '/', '\')
    if (-not (Test-Path -LiteralPath $source -PathType Leaf)) {
        Write-Warning "Skip upload, file not found locally: $relative"
        return
    }

    $target = "$ftpBase/$relative"

    if ($DryRun) {
        Write-Host "[DryRun] Upload $relative"
        return
    }

    Write-Host "Uploading $relative"
    & curl.exe --silent --show-error --ftp-create-dirs --user $credentials -T "$source" "$target"
    if ($LASTEXITCODE -ne 0) {
        throw "Upload failed for $relative"
    }
}

function Delete-RemoteFile([string]$relative) {
    if ($DryRun) {
        Write-Host "[DryRun] Delete remote $relative"
        return
    }

    Write-Host "Deleting remote $relative"
    & curl.exe --silent --show-error --user $credentials --quote "DELE $remotePath/$relative" "ftp://$($config.host)/"
    if ($LASTEXITCODE -ne 0) {
        throw "Remote delete failed for $relative"
    }
}

if ($FullSync) {
    $uploadSet = New-Object 'System.Collections.Generic.HashSet[string]' ([System.StringComparer]::Ordinal)
    $deleteSet = New-Object 'System.Collections.Generic.HashSet[string]' ([System.StringComparer]::Ordinal)

    $files = Get-ChildItem -LiteralPath $localRoot -Recurse -File | Where-Object {
        $_.FullName -notlike "*\.vscode\*"
    }

    foreach ($file in $files) {
        $relative = $file.FullName.Substring($localRoot.Length).TrimStart('\') -replace '\\', '/'
        [void]$uploadSet.Add($relative)
    }
} else {
    if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
        throw "Git is required for changed-only deploy. Use -FullSync if git is unavailable."
    }

    $headCheck = & git -C $projectRoot rev-parse --verify HEAD 2>$null
    if ($LASTEXITCODE -ne 0) {
        throw "No HEAD commit found. Use -FullSync for the first deploy."
    }

    $uploadSet = New-Object 'System.Collections.Generic.HashSet[string]' ([System.StringComparer]::Ordinal)
    $deleteSet = New-Object 'System.Collections.Generic.HashSet[string]' ([System.StringComparer]::Ordinal)

    $trackedChanges = Run-Git -GitArgs @("diff", "--name-only", "--diff-filter=ACMRTUXB", "HEAD", "--", "public_html")
    foreach ($path in $trackedChanges) {
        Add-RelativePath -set $uploadSet -path $path
    }

    $untracked = Run-Git -GitArgs @("ls-files", "--others", "--exclude-standard", "--", "public_html")
    foreach ($path in $untracked) {
        Add-RelativePath -set $uploadSet -path $path
    }

    $trackedDeletes = Run-Git -GitArgs @("diff", "--name-only", "--diff-filter=D", "HEAD", "--", "public_html")
    foreach ($path in $trackedDeletes) {
        Add-RelativePath -set $deleteSet -path $path
    }

    $renames = Run-Git -GitArgs @("diff", "--name-status", "--diff-filter=R", "HEAD", "--", "public_html")
    foreach ($line in $renames) {
        if ([string]::IsNullOrWhiteSpace($line)) {
            continue
        }

        $parts = $line -split "`t"
        if ($parts.Count -lt 3) {
            continue
        }

        $oldPath = $parts[1]
        $newPath = $parts[2]
        Add-RelativePath -set $deleteSet -path $oldPath
        Add-RelativePath -set $uploadSet -path $newPath
    }

    foreach ($path in @($deleteSet)) {
        if ($uploadSet.Contains($path)) {
            [void]$deleteSet.Remove($path)
        }
    }
}

$uploadList = @($uploadSet) | Sort-Object
$deleteList = @($deleteSet) | Sort-Object

if ($uploadList.Count -eq 0 -and $deleteList.Count -eq 0) {
    Write-Host "No changes detected under public_html. Nothing to deploy."
} else {
    $modeLabel = if ($FullSync) { "full-sync" } else { "changed-only" }
    Write-Host "Deploy mode: $modeLabel"
    Write-Host "Upload count: $($uploadList.Count)"
    Write-Host "Delete count: $($deleteList.Count)"

    foreach ($relative in $uploadList) {
        Upload-File -relative $relative
    }

    foreach ($relative in $deleteList) {
        Delete-RemoteFile -relative $relative
    }
}

if ($DeleteVscodeOnRemote) {
    if ($DryRun) {
        Write-Host "[DryRun] Delete remote .vscode directory"
    } else {
        & curl.exe --silent --show-error --user $credentials --quote "RMD $remotePath/.vscode" "ftp://$($config.host)/"
    }
}

Write-Host "Deploy completed to $remotePath"
