param(
    [switch]$DeleteVscodeOnRemote,
    [switch]$FullSync,
    [switch]$DryRun,
    [switch]$SkipValidation,
    [switch]$SkipPostDeployVerification,
    [switch]$SkipGitHubSync,
    [switch]$PullBeforeDeploy,
    [string]$CommitMessage = "chore: sync deployed laptop state to github",
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
$runStartedAt = [DateTimeOffset]::Now

function Get-IsoNow() {
    return ([DateTimeOffset]::Now).ToString("yyyy-MM-ddTHH:mm:sszzz")
}

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

function Assert-GitAvailable() {
    if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
        throw "Git is required for deployment and GitHub sync."
    }
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

function Try-GetUpstreamBranch() {
    $upstream = & git -C $projectRoot rev-parse --abbrev-ref --symbolic-full-name "@{upstream}" 2>$null
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace([string]$upstream)) {
        return ""
    }

    return [string]$upstream
}

function Get-CommitInfo([string]$Revision) {
    if ([string]::IsNullOrWhiteSpace($Revision)) {
        return $null
    }

    $line = Run-GitSingle -GitArgs @("show", "-s", "--format=%H%x09%cI%x09%aI%x09%s", $Revision)
    if ([string]::IsNullOrWhiteSpace($line)) {
        return $null
    }

    $parts = $line -split "`t", 4
    if ($parts.Count -lt 3) {
        return $null
    }

    $subject = ""
    if ($parts.Count -ge 4) {
        $subject = [string]$parts[3]
    }

    return [PSCustomObject]@{
        Hash       = [string]$parts[0]
        CommitTime = [string]$parts[1]
        AuthorTime = [string]$parts[2]
        Subject    = $subject
    }
}

function Assert-CleanWorkingTree() {
    $status = & git -C $projectRoot status --porcelain --untracked-files=all
    if ($LASTEXITCODE -ne 0) {
        throw "Unable to inspect git working tree state."
    }

    $lines = @($status) | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }
    if ($lines.Count -gt 0) {
        throw "Working tree is not clean. Commit/stash/discard local changes before using -PullBeforeDeploy."
    }
}

function Resolve-PythonCommand() {
    $py = Get-Command python -ErrorAction SilentlyContinue
    if ($null -ne $py) {
        return [string]$py.Source
    }

    $py3 = Get-Command python3 -ErrorAction SilentlyContinue
    if ($null -ne $py3) {
        return [string]$py3.Source
    }

    return ""
}

function Run-Validation() {
    $started = Get-IsoNow
    if ($SkipValidation) {
        Write-Warning "Step 1/5 skipped by explicit -SkipValidation override."
        return [PSCustomObject]@{
            Status     = "skipped-explicit"
            StartedAt  = $started
            FinishedAt = Get-IsoNow
            Command    = ""
        }
    }

    $scriptPath = Join-Path $projectRoot "scripts\check_text_integrity.py"
    if (-not (Test-Path $scriptPath)) {
        throw "Validation script not found: $scriptPath"
    }

    $python = Resolve-PythonCommand
    if ([string]::IsNullOrWhiteSpace($python)) {
        throw "Python is required for validation but no python/python3 command was found."
    }

    Write-Host "Step 1/5: local validation"
    Write-Host "Running: $python $scriptPath"
    & $python $scriptPath
    if ($LASTEXITCODE -ne 0) {
        throw "Validation failed (scripts/check_text_integrity.py). Deployment aborted before host upload."
    }

    return [PSCustomObject]@{
        Status     = "completed"
        StartedAt  = $started
        FinishedAt = Get-IsoNow
        Command    = "$python $scriptPath"
    }
}

function Run-OptionalPullBeforeDeploy() {
    $started = Get-IsoNow
    if (-not $PullBeforeDeploy) {
        return [PSCustomObject]@{
            Status         = "skipped-default"
            StartedAt      = $started
            FinishedAt     = Get-IsoNow
            Upstream       = ""
            BeforeHead     = ""
            AfterHead      = ""
            BeforeCommit   = $null
            AfterCommit    = $null
            CommitRange    = ""
        }
    }

    Assert-GitAvailable
    Assert-CleanWorkingTree

    $upstream = Try-GetUpstreamBranch
    if ([string]::IsNullOrWhiteSpace($upstream)) {
        throw "No upstream branch is configured for the current branch. Configure upstream before using -PullBeforeDeploy."
    }

    $beforeHead = Run-GitSingle -GitArgs @("rev-parse", "HEAD")
    $beforeCommit = Get-CommitInfo -Revision $beforeHead

    Write-Host "Step 2/5: optional git pull --ff-only (explicit override)"
    Write-Host "Git pull started at: $started"
    & git -C $projectRoot pull --ff-only
    if ($LASTEXITCODE -ne 0) {
        throw "git pull --ff-only failed under -PullBeforeDeploy override."
    }

    $finished = Get-IsoNow
    $afterHead = Run-GitSingle -GitArgs @("rev-parse", "HEAD")
    $afterCommit = Get-CommitInfo -Revision $afterHead

    if ($beforeHead -ne $afterHead) {
        Write-Host "Git sync updated HEAD: $beforeHead -> $afterHead"
    } else {
        Write-Host "Git sync completed: already up to date."
    }

    return [PSCustomObject]@{
        Status         = "completed"
        StartedAt      = $started
        FinishedAt     = $finished
        Upstream       = $upstream
        BeforeHead     = $beforeHead
        AfterHead      = $afterHead
        BeforeCommit   = $beforeCommit
        AfterCommit    = $afterCommit
        CommitRange    = if ($beforeHead -ne $afterHead) { "$beforeHead..$afterHead" } else { "" }
    }
}

function Collect-GitRangeDelta([System.Collections.Generic.HashSet[string]]$uploadSet, [System.Collections.Generic.HashSet[string]]$deleteSet, [string]$rangeSpec) {
    if ([string]::IsNullOrWhiteSpace($rangeSpec)) {
        return
    }

    $trackedChanges = Run-Git -GitArgs @("diff", "--name-only", "--diff-filter=ACMRTUXB", $rangeSpec, "--", "public_html")
    foreach ($path in $trackedChanges) {
        Add-RelativePath -set $uploadSet -path $path
    }

    $trackedDeletes = Run-Git -GitArgs @("diff", "--name-only", "--diff-filter=D", $rangeSpec, "--", "public_html")
    foreach ($path in $trackedDeletes) {
        Add-RelativePath -set $deleteSet -path $path
    }

    $renames = Run-Git -GitArgs @("diff", "--name-status", "--diff-filter=R", $rangeSpec, "--", "public_html")
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
}

function Collect-WorkingTreeDelta([System.Collections.Generic.HashSet[string]]$uploadSet, [System.Collections.Generic.HashSet[string]]$deleteSet) {
    $trackedChanges = Run-Git -GitArgs @("diff", "--name-only", "--diff-filter=ACMRTUXB", "HEAD", "--", "public_html")
    foreach ($path in $trackedChanges) {
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

    $untracked = Run-Git -GitArgs @("ls-files", "--others", "--exclude-standard", "--", "public_html")
    foreach ($path in $untracked) {
        Add-RelativePath -set $uploadSet -path $path
    }
}

function Build-DeployPlan() {
    $uploadSet = New-Object 'System.Collections.Generic.HashSet[string]' ([System.StringComparer]::Ordinal)
    $deleteSet = New-Object 'System.Collections.Generic.HashSet[string]' ([System.StringComparer]::Ordinal)
    $notes = New-Object System.Collections.Generic.List[string]

    if ($FullSync) {
        $files = Get-ChildItem -LiteralPath $localRoot -Recurse -File
        foreach ($file in $files) {
            $relative = $file.FullName.Substring($localRoot.Length).TrimStart('\\') -replace '\\', '/'
            [void]$uploadSet.Add($relative)
        }
        [void]$notes.Add("Full sync uploads the full laptop public_html tree.")

        return [PSCustomObject]@{
            Mode       = "full-sync (laptop source)"
            UploadList = @($uploadSet) | Sort-Object
            DeleteList = @($deleteSet) | Sort-Object
            Notes      = @($notes)
        }
    }

    Assert-GitAvailable

    $upstream = Try-GetUpstreamBranch
    if (-not [string]::IsNullOrWhiteSpace($upstream)) {
        $counts = Run-GitSingle -GitArgs @("rev-list", "--left-right", "--count", "@{upstream}...HEAD")
        $parts = $counts -split "\s+"
        if ($parts.Count -ge 2) {
            $behind = [int]$parts[0]
            $ahead = [int]$parts[1]
            if ($ahead -gt 0) {
                Collect-GitRangeDelta -uploadSet $uploadSet -deleteSet $deleteSet -rangeSpec "@{upstream}..HEAD"
                [void]$notes.Add("Included committed local delta ahead of $upstream (ahead=$ahead).")
            }
            if ($behind -gt 0) {
                [void]$notes.Add("Local branch is behind $upstream by $behind commit(s); deploy still uses laptop state.")
            }
        }
    } else {
        [void]$notes.Add("No upstream branch configured; deploying local tracked/untracked workspace delta only.")
    }

    Collect-WorkingTreeDelta -uploadSet $uploadSet -deleteSet $deleteSet
    [void]$notes.Add("Included staged/unstaged/untracked local workspace changes.")

    foreach ($path in @($deleteSet)) {
        if ($uploadSet.Contains($path)) {
            [void]$deleteSet.Remove($path)
        }
    }

    return [PSCustomObject]@{
        Mode       = "local-delta (laptop source)"
        UploadList = @($uploadSet) | Sort-Object
        DeleteList = @($deleteSet) | Sort-Object
        Notes      = @($notes)
    }
}

function Upload-File([string]$relative) {
    $source = Join-Path $localRoot ($relative -replace '/', '\\')
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
        Write-Host "[DryRun] Step 4/5 skipped: post-deploy verification"
        return [PSCustomObject]@{
            Status     = "skipped-dry-run"
            StartedAt  = Get-IsoNow
            FinishedAt = Get-IsoNow
            Targets    = @()
        }
    }

    if ($SkipPostDeployVerification) {
        Write-Warning "Step 4/5 skipped by explicit -SkipPostDeployVerification override."
        return [PSCustomObject]@{
            Status     = "skipped-explicit"
            StartedAt  = Get-IsoNow
            FinishedAt = Get-IsoNow
            Targets    = @()
        }
    }

    $targets = @($HealthCheckUrls | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
    if ($targets.Count -eq 0) {
        throw "No health-check URL is configured. Provide -HealthCheckUrls for post-deploy verification."
    }

    $verificationStartedAt = Get-IsoNow
    Write-Host "Step 4/5: live post-deploy verification"
    foreach ($target in $targets) {
        Invoke-HealthCheck -url $target
    }
    $verificationFinishedAt = Get-IsoNow

    return [PSCustomObject]@{
        Status     = "completed"
        StartedAt  = $verificationStartedAt
        FinishedAt = $verificationFinishedAt
        Targets    = @($targets)
    }
}

function Sync-GitHubFromLaptop() {
    $started = Get-IsoNow

    if ($DryRun) {
        Write-Host "[DryRun] Step 5/5 skipped: GitHub sync"
        return [PSCustomObject]@{
            Status        = "skipped-dry-run"
            StartedAt     = $started
            FinishedAt    = Get-IsoNow
            CurrentBranch = ""
            CreatedCommit = ""
            HeadAfterSync = ""
            HeadCommit    = $null
            PushCommand   = ""
        }
    }

    if ($SkipGitHubSync) {
        Write-Warning "Step 5/5 skipped by explicit -SkipGitHubSync override."
        return [PSCustomObject]@{
            Status        = "skipped-explicit"
            StartedAt     = $started
            FinishedAt    = Get-IsoNow
            CurrentBranch = ""
            CreatedCommit = ""
            HeadAfterSync = ""
            HeadCommit    = $null
            PushCommand   = ""
        }
    }

    Assert-GitAvailable

    $currentBranch = Run-GitSingle -GitArgs @("rev-parse", "--abbrev-ref", "HEAD")
    if ($currentBranch -eq "HEAD") {
        throw "Detached HEAD is not supported for GitHub sync. Checkout a branch first."
    }

    Write-Host "Step 5/5: sync GitHub from deployed laptop state"

    & git -C $projectRoot add -A
    if ($LASTEXITCODE -ne 0) {
        throw "git add -A failed before GitHub sync."
    }

    & git -C $projectRoot diff --cached --quiet --exit-code
    $hasStagedChanges = $false
    if ($LASTEXITCODE -eq 1) {
        $hasStagedChanges = $true
    } elseif ($LASTEXITCODE -ne 0) {
        throw "Unable to determine staged changes before commit."
    }

    $createdCommit = ""
    if ($hasStagedChanges) {
        & git -C $projectRoot commit -m $CommitMessage
        if ($LASTEXITCODE -ne 0) {
            throw "git commit failed during GitHub sync."
        }
        $createdCommit = Run-GitSingle -GitArgs @("rev-parse", "HEAD")
        Write-Host "Created commit: $createdCommit"
    } else {
        Write-Host "No new local changes to commit; push will sync existing local commits if needed."
    }

    $upstream = Try-GetUpstreamBranch
    $pushCommand = "git push"
    if ([string]::IsNullOrWhiteSpace($upstream)) {
        $pushCommand = "git push -u origin $currentBranch"
        & git -C $projectRoot push -u origin $currentBranch
    } else {
        & git -C $projectRoot push
    }

    if ($LASTEXITCODE -ne 0) {
        throw "GitHub push failed after successful host deploy/verification. Deployed host state is intact; sync to GitHub must be resolved manually."
    }

    $headAfterSync = Run-GitSingle -GitArgs @("rev-parse", "HEAD")
    $headCommit = Get-CommitInfo -Revision $headAfterSync

    return [PSCustomObject]@{
        Status        = "completed"
        StartedAt     = $started
        FinishedAt    = Get-IsoNow
        CurrentBranch = $currentBranch
        CreatedCommit = $createdCommit
        HeadAfterSync = $headAfterSync
        HeadCommit    = $headCommit
        PushCommand   = $pushCommand
    }
}

$validationInfo = [PSCustomObject]@{
    Status     = "not-run"
    StartedAt  = ""
    FinishedAt = ""
    Command    = ""
}
$pullInfo = [PSCustomObject]@{
    Status      = "not-run"
    StartedAt   = ""
    FinishedAt  = ""
    Upstream    = ""
    BeforeHead  = ""
    AfterHead   = ""
    BeforeCommit = $null
    AfterCommit  = $null
    CommitRange = ""
}
$deployInfo = [PSCustomObject]@{
    Status      = "not-run"
    StartedAt   = ""
    FinishedAt  = ""
    Mode        = ""
    UploadCount = 0
    DeleteCount = 0
    Notes       = @()
}
$verificationInfo = [PSCustomObject]@{
    Status     = "not-run"
    StartedAt  = ""
    FinishedAt = ""
    Targets    = @()
}
$githubSyncInfo = [PSCustomObject]@{
    Status        = "not-run"
    StartedAt     = ""
    FinishedAt    = ""
    CurrentBranch = ""
    CreatedCommit = ""
    HeadAfterSync = ""
    HeadCommit    = $null
    PushCommand   = ""
}

$failureMessage = ""

try {
    $validationInfo = Run-Validation
    $pullInfo = Run-OptionalPullBeforeDeploy

    $deployInfo.StartedAt = Get-IsoNow
    $plan = Build-DeployPlan
    $uploadList = @($plan.UploadList)
    $deleteList = @($plan.DeleteList)

    $deployInfo.Mode = [string]$plan.Mode
    $deployInfo.UploadCount = $uploadList.Count
    $deployInfo.DeleteCount = $deleteList.Count
    $deployInfo.Notes = @($plan.Notes)

    if ($uploadList.Count -eq 0 -and $deleteList.Count -eq 0) {
        Write-Host "Step 3/5: deploy to host"
        Write-Host "No local delta detected under public_html. Nothing to deploy."
    } else {
        Write-Host "Step 3/5: deploy to host"
        Write-Host "Deploy mode: $($deployInfo.Mode)"
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

    $deployInfo.Status = "completed"
    $deployInfo.FinishedAt = Get-IsoNow

    $verificationInfo = Run-PostDeployVerification
    $githubSyncInfo = Sync-GitHubFromLaptop
} catch {
    $failureMessage = $_.Exception.Message
    if (-not $deployInfo.FinishedAt) {
        $deployInfo.FinishedAt = Get-IsoNow
    }
    if (-not $verificationInfo.FinishedAt) {
        $verificationInfo.FinishedAt = Get-IsoNow
    }
    if (-not $githubSyncInfo.FinishedAt) {
        $githubSyncInfo.FinishedAt = Get-IsoNow
    }
} finally {
    $runFinishedAt = Get-IsoNow

    Write-Host "Deploy completed to $remotePath"
    Write-Host "Deployment report (laptop-first):"
    Write-Host " - Run started at: $($runStartedAt.ToString('yyyy-MM-ddTHH:mm:sszzz'))"

    Write-Host " - Validation status: $($validationInfo.Status)"
    if (-not [string]::IsNullOrWhiteSpace($validationInfo.StartedAt)) {
        Write-Host " - Validation started at: $($validationInfo.StartedAt)"
    }
    if (-not [string]::IsNullOrWhiteSpace($validationInfo.FinishedAt)) {
        Write-Host " - Validation finished at: $($validationInfo.FinishedAt)"
    }
    if (-not [string]::IsNullOrWhiteSpace($validationInfo.Command)) {
        Write-Host " - Validation command: $($validationInfo.Command)"
    }

    Write-Host " - Optional pull status: $($pullInfo.Status)"
    if (-not [string]::IsNullOrWhiteSpace($pullInfo.StartedAt)) {
        Write-Host " - Optional pull started at: $($pullInfo.StartedAt)"
    }
    if (-not [string]::IsNullOrWhiteSpace($pullInfo.FinishedAt)) {
        Write-Host " - Optional pull finished at: $($pullInfo.FinishedAt)"
    }
    if ($pullInfo.AfterCommit -ne $null) {
        Write-Host " - HEAD after optional pull: $($pullInfo.AfterCommit.Hash)"
        Write-Host " - HEAD commit time after optional pull: $($pullInfo.AfterCommit.CommitTime)"
    }

    Write-Host " - Deploy status: $($deployInfo.Status)"
    if (-not [string]::IsNullOrWhiteSpace($deployInfo.StartedAt)) {
        Write-Host " - Deploy step started at: $($deployInfo.StartedAt)"
    }
    if (-not [string]::IsNullOrWhiteSpace($deployInfo.FinishedAt)) {
        Write-Host " - Deploy step finished at: $($deployInfo.FinishedAt)"
    }
    if (-not [string]::IsNullOrWhiteSpace($deployInfo.Mode)) {
        Write-Host " - Deploy mode: $($deployInfo.Mode)"
    }
    Write-Host " - Upload count: $($deployInfo.UploadCount)"
    Write-Host " - Delete count: $($deployInfo.DeleteCount)"
    foreach ($note in @($deployInfo.Notes)) {
        Write-Host "   * $note"
    }

    Write-Host " - Verification status: $($verificationInfo.Status)"
    if (-not [string]::IsNullOrWhiteSpace($verificationInfo.StartedAt)) {
        Write-Host " - Verification started at: $($verificationInfo.StartedAt)"
    }
    if (-not [string]::IsNullOrWhiteSpace($verificationInfo.FinishedAt)) {
        Write-Host " - Verification finished at: $($verificationInfo.FinishedAt)"
    }

    Write-Host " - GitHub sync status: $($githubSyncInfo.Status)"
    if (-not [string]::IsNullOrWhiteSpace($githubSyncInfo.StartedAt)) {
        Write-Host " - GitHub sync started at: $($githubSyncInfo.StartedAt)"
    }
    if (-not [string]::IsNullOrWhiteSpace($githubSyncInfo.FinishedAt)) {
        Write-Host " - GitHub sync finished at: $($githubSyncInfo.FinishedAt)"
    }
    if (-not [string]::IsNullOrWhiteSpace($githubSyncInfo.CurrentBranch)) {
        Write-Host " - GitHub sync branch: $($githubSyncInfo.CurrentBranch)"
    }
    if ($githubSyncInfo.HeadCommit -ne $null) {
        Write-Host " - GitHub sync HEAD: $($githubSyncInfo.HeadCommit.Hash)"
        Write-Host " - GitHub sync HEAD commit time: $($githubSyncInfo.HeadCommit.CommitTime)"
        Write-Host " - GitHub sync HEAD subject: $($githubSyncInfo.HeadCommit.Subject)"
    }
    if (-not [string]::IsNullOrWhiteSpace($githubSyncInfo.CreatedCommit)) {
        Write-Host " - Created commit in this deploy run: $($githubSyncInfo.CreatedCommit)"
    }
    if (-not [string]::IsNullOrWhiteSpace($githubSyncInfo.PushCommand)) {
        Write-Host " - Push command: $($githubSyncInfo.PushCommand)"
    }

    Write-Host " - Run finished at: $runFinishedAt"

    if (-not [string]::IsNullOrWhiteSpace($failureMessage)) {
        Write-Error $failureMessage
    }
}

if (-not [string]::IsNullOrWhiteSpace($failureMessage)) {
    throw $failureMessage
}