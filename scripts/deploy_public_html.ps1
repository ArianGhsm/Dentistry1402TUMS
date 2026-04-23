param(
    [switch]$DeleteVscodeOnRemote,
    [switch]$FullSync,
    [switch]$DryRun,
    [switch]$SkipGitPull,
    [switch]$SkipPostDeployVerification,
    [string[]]$HealthCheckUrls = @(
        "https://dentistry1402tums.ir/",
        "https://dentistry1402tums.ir/chat/"
    )
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

    return @([string[]]$output)
}

function Run-GitSingle([string[]]$GitArgs) {
    $lines = @(Run-Git -GitArgs $GitArgs)
    if ($lines.Count -eq 0) {
        return ""
    }

    return [string]$lines[0]
}

function Assert-CleanWorkingTree() {
    $status = & git -C $projectRoot status --porcelain --untracked-files=all
    if ($LASTEXITCODE -ne 0) {
        throw "Unable to inspect git working tree state."
    }

    $lines = @($status) | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }
    if ($lines.Count -gt 0) {
        throw "Working tree is not clean. Commit/stash/discard local changes before deploy, or use -SkipGitPull for an explicit local-state deploy."
    }
}

function Get-UpstreamBranch() {
    $upstream = & git -C $projectRoot rev-parse --abbrev-ref --symbolic-full-name "@{upstream}" 2>$null
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace([string]$upstream)) {
        throw "No upstream branch is configured for the current branch. Configure upstream before deploy."
    }

    return [string]$upstream
}

function Get-RemoteDefaultBranch() {
    $remoteHead = & git -C $projectRoot symbolic-ref --quiet --short refs/remotes/origin/HEAD 2>$null
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace([string]$remoteHead)) {
        return ""
    }

    $value = [string]$remoteHead
    if ($value.StartsWith("origin/")) {
        return $value.Substring("origin/".Length)
    }

    return $value
}

function Get-BranchDivergence() {
    $counts = Run-GitSingle -GitArgs @("rev-list", "--left-right", "--count", "@{upstream}...HEAD")
    $parts = $counts -split "\s+"
    if ($parts.Count -lt 2) {
        throw "Unable to parse branch divergence output: $counts"
    }

    return [PSCustomObject]@{
        Behind = [int]$parts[0]
        Ahead  = [int]$parts[1]
    }
}

function Sync-RepositoryFromRemote() {
    if ($SkipGitPull) {
        Write-Warning "Git sync skipped by explicit -SkipGitPull override. Deploying current local state."
        $head = Run-GitSingle -GitArgs @("rev-parse", "HEAD")
        return [PSCustomObject]@{
            BeforeHead   = $head
            AfterHead    = $head
            CommitRange  = ""
            Synced       = $false
            CurrentBranch = (Run-GitSingle -GitArgs @("rev-parse", "--abbrev-ref", "HEAD"))
        }
    }

    if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
        throw "Git is required for deployment sync."
    }

    Assert-CleanWorkingTree

    $currentBranch = Run-GitSingle -GitArgs @("rev-parse", "--abbrev-ref", "HEAD")
    $defaultBranch = Get-RemoteDefaultBranch
    if (-not [string]::IsNullOrWhiteSpace($defaultBranch) -and $currentBranch -ne $defaultBranch) {
        throw "Current branch '$currentBranch' does not match origin default branch '$defaultBranch'. Deploy from the release branch or override intentionally."
    }

    $upstream = Get-UpstreamBranch
    $divergence = Get-BranchDivergence
    if ($divergence.Ahead -gt 0 -and $divergence.Behind -eq 0) {
        throw "Local branch is ahead of $upstream by $($divergence.Ahead) commit(s). Push first so deploy uses canonical remote state."
    }
    if ($divergence.Ahead -gt 0 -and $divergence.Behind -gt 0) {
        throw "Local and remote branches are diverged (ahead $($divergence.Ahead), behind $($divergence.Behind)). Resolve divergence before deploy."
    }

    $beforeHead = Run-GitSingle -GitArgs @("rev-parse", "HEAD")
    Write-Host "Step 1/3: git pull --ff-only from $upstream"
    & git -C $projectRoot pull --ff-only
    if ($LASTEXITCODE -ne 0) {
        throw "git pull --ff-only failed. Deployment aborted."
    }

    $afterHead = Run-GitSingle -GitArgs @("rev-parse", "HEAD")
    $range = ""
    if ($beforeHead -ne $afterHead) {
        $range = "$beforeHead..$afterHead"
        Write-Host "Git sync updated HEAD: $beforeHead -> $afterHead"
    } else {
        Write-Host "Git sync completed: already up to date."
    }

    return [PSCustomObject]@{
        BeforeHead    = $beforeHead
        AfterHead     = $afterHead
        CommitRange   = $range
        Synced        = $true
        CurrentBranch = $currentBranch
    }
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

function Invoke-HealthCheck([string]$url) {
    if ([string]::IsNullOrWhiteSpace($url)) {
        return
    }

    Write-Host "Health check: $url"

    try {
        $response = Invoke-WebRequest -Uri $url -Method Get -MaximumRedirection 5 -TimeoutSec 30 -UseBasicParsing
    } catch {
        throw "Health check request failed for '$url'. $($_.Exception.Message)"
    }

    $statusCode = [int]$response.StatusCode
    if ($statusCode -lt 200 -or $statusCode -ge 400) {
        throw "Health check failed for '$url' with status $statusCode."
    }

    Write-Host "Health check OK: $url ($statusCode)"
}

function Run-PostDeployVerification() {
    if ($DryRun) {
        Write-Host "[DryRun] Step 3/3 skipped: post-deploy verification"
        return
    }

    if ($SkipPostDeployVerification) {
        Write-Warning "Step 3/3 skipped by explicit -SkipPostDeployVerification override."
        return
    }

    $targets = @($HealthCheckUrls | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
    if ($targets.Count -eq 0) {
        throw "No health-check URL is configured. Provide -HealthCheckUrls for post-deploy verification."
    }

    Write-Host "Step 3/3: live post-deploy verification"
    foreach ($target in $targets) {
        Invoke-HealthCheck -url $target
    }
}

$syncInfo = Sync-RepositoryFromRemote

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
    $uploadSet = New-Object 'System.Collections.Generic.HashSet[string]' ([System.StringComparer]::Ordinal)
    $deleteSet = New-Object 'System.Collections.Generic.HashSet[string]' ([System.StringComparer]::Ordinal)

    if ($SkipGitPull) {
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
    } elseif (-not [string]::IsNullOrWhiteSpace($syncInfo.CommitRange)) {
        $commitRange = $syncInfo.CommitRange

        $trackedChanges = Run-Git -GitArgs @("diff", "--name-only", "--diff-filter=ACMRTUXB", $commitRange, "--", "public_html")
        foreach ($path in $trackedChanges) {
            Add-RelativePath -set $uploadSet -path $path
        }

        $trackedDeletes = Run-Git -GitArgs @("diff", "--name-only", "--diff-filter=D", $commitRange, "--", "public_html")
        foreach ($path in $trackedDeletes) {
            Add-RelativePath -set $deleteSet -path $path
        }

        $renames = Run-Git -GitArgs @("diff", "--name-status", "--diff-filter=R", $commitRange, "--", "public_html")
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
    } else {
        Write-Host "No new commits were pulled from remote. Changed-only deploy has no git delta."
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
    $modeLabel = if ($FullSync) {
        "full-sync"
    } elseif ($SkipGitPull) {
        "changed-only (local-state override)"
    } else {
        "changed-only (pulled commit range)"
    }

    Write-Host "Step 2/3: deploy to host"
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

Run-PostDeployVerification

Write-Host "Deploy completed to $remotePath"
