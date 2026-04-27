param(
    [switch]$DeleteVscodeOnRemote,
    [switch]$FullSync,
    [switch]$DryRun,
    [switch]$SkipValidation,
    [switch]$SkipPostDeployVerification,
    [switch]$SkipGitHubSync,
    [switch]$PullBeforeDeploy,
    [switch]$AllowProxyPull,
    [switch]$AllowProxyOverBudget,
    [string]$NetworkPath = "auto",
    [string]$HostDeployNetworkPath = "auto",
    [string]$HealthCheckNetworkPath = "auto",
    [string]$GitHubNetworkPath = "auto",
    [string]$LowBandwidthMode = "auto",
    [string]$ProxyEndpoint = "",
    [int]$ProxyBudgetMb = 0,
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

function Normalize-NetworkPath([string]$value) {
    $normalized = ([string]$value).Trim().ToLowerInvariant()
    if ($normalized -in @("auto", "direct", "proxy")) {
        return $normalized
    }
    return "auto"
}

function Normalize-LowBandwidthMode([string]$value) {
    $normalized = ([string]$value).Trim().ToLowerInvariant()
    if ($normalized -in @("auto", "on", "off")) {
        return $normalized
    }
    return "auto"
}

function Parse-PositiveInt([string]$value, [int]$fallback) {
    if ([string]::IsNullOrWhiteSpace($value)) {
        return $fallback
    }

    $parsed = 0
    if ([int]::TryParse($value.Trim(), [ref]$parsed) -and $parsed -gt 0) {
        return $parsed
    }
    return $fallback
}

function Get-ProxyEnvValues() {
    $keys = @(
        "HTTP_PROXY",
        "HTTPS_PROXY",
        "ALL_PROXY",
        "FTP_PROXY",
        "http_proxy",
        "https_proxy",
        "all_proxy",
        "ftp_proxy"
    )

    $values = New-Object System.Collections.Generic.List[string]
    foreach ($key in $keys) {
        $value = [Environment]::GetEnvironmentVariable($key)
        if (-not [string]::IsNullOrWhiteSpace($value)) {
            [void]$values.Add($value.Trim())
        }
    }
    return @($values)
}

function Test-ProxyEndpointConfigured([string]$endpoint) {
    if ([string]::IsNullOrWhiteSpace($endpoint)) {
        return $false
    }

    $needle = $endpoint.Trim().ToLowerInvariant()
    foreach ($value in @(Get-ProxyEnvValues)) {
        if ([string]::IsNullOrWhiteSpace($value)) {
            continue
        }

        $normalized = $value.Trim().ToLowerInvariant()
        if ($normalized.Contains($needle)) {
            return $true
        }
    }

    return $false
}

function Get-HostNameFromTarget([string]$target) {
    if ([string]::IsNullOrWhiteSpace($target)) {
        return ""
    }

    $raw = $target.Trim()
    $uri = $null
    if ([Uri]::TryCreate($raw, [UriKind]::Absolute, [ref]$uri)) {
        return $uri.Host.ToLowerInvariant()
    }

    $candidate = $raw
    if ($candidate.Contains("/")) {
        $candidate = $candidate.Split('/')[0]
    }
    if ($candidate.Contains("@")) {
        $candidate = $candidate.Split('@')[-1]
    }
    if ($candidate.StartsWith("[")) {
        $closing = $candidate.IndexOf("]")
        if ($closing -gt 0) {
            return $candidate.Substring(1, $closing - 1).ToLowerInvariant()
        }
    }
    if ($candidate.Contains(":")) {
        $candidate = $candidate.Split(':')[0]
    }
    return $candidate.Trim().ToLowerInvariant()
}

function Test-NoProxyBypass([string]$hostName) {
    if ([string]::IsNullOrWhiteSpace($hostName)) {
        return $false
    }

    $tokens = @()
    foreach ($key in @("NO_PROXY", "no_proxy")) {
        $value = [Environment]::GetEnvironmentVariable($key)
        if ([string]::IsNullOrWhiteSpace($value)) {
            continue
        }
        $tokens += ($value -split ",")
    }

    if ($tokens.Count -eq 0) {
        return $false
    }

    $subject = $hostName.Trim().ToLowerInvariant()
    foreach ($token in $tokens) {
        $rule = ([string]$token).Trim().ToLowerInvariant()
        if ([string]::IsNullOrWhiteSpace($rule)) {
            continue
        }
        if ($rule -eq "*") {
            return $true
        }
        if ($rule.StartsWith(".")) {
            $rule = $rule.TrimStart(".")
        }
        if ([string]::IsNullOrWhiteSpace($rule)) {
            continue
        }
        if ($subject -eq $rule) {
            return $true
        }
        if ($subject.EndsWith("." + $rule)) {
            return $true
        }
    }

    return $false
}

function Resolve-EffectiveNetworkPath(
    [string]$preferred,
    [string]$envOverride,
    [string]$targetHost,
    [bool]$proxyConfigured,
    [string]$fallback = "auto"
) {
    $choice = Normalize-NetworkPath -value $preferred
    if ($choice -eq "auto" -and -not [string]::IsNullOrWhiteSpace($envOverride)) {
        $choice = Normalize-NetworkPath -value $envOverride
    }
    if ($choice -eq "auto") {
        $choice = Normalize-NetworkPath -value $fallback
    }
    if ($choice -in @("direct", "proxy")) {
        return $choice
    }
    if (-not $proxyConfigured) {
        return "direct"
    }
    if (Test-NoProxyBypass -hostName $targetHost) {
        return "direct"
    }
    return "proxy"
}

function Format-Bytes([int64]$bytes) {
    if ($bytes -lt 1024) {
        return "$bytes B"
    }
    if ($bytes -lt 1MB) {
        return ("{0:N2} KB" -f ($bytes / 1KB))
    }
    return ("{0:N2} MB" -f ($bytes / 1MB))
}

function Get-FileBytesFromRelativeList([string]$rootPath, [string[]]$relativeList) {
    $total = [int64]0
    foreach ($relative in @($relativeList)) {
        if ([string]::IsNullOrWhiteSpace($relative)) {
            continue
        }

        $fullPath = Join-Path $rootPath ($relative -replace '/', '\')
        if (-not (Test-Path -LiteralPath $fullPath -PathType Leaf)) {
            continue
        }

        try {
            $fileInfo = Get-Item -LiteralPath $fullPath -ErrorAction Stop
            $total += [int64]$fileInfo.Length
        } catch {
            continue
        }
    }
    return $total
}

function Get-ProxyBudgetBytes([int]$budgetMb) {
    return [int64]$budgetMb * 1MB
}

function Assert-ProxyBudget([string]$stepName, [int64]$estimatedBytes, [int]$budgetMb, [switch]$allowOverBudget) {
    $budgetBytes = Get-ProxyBudgetBytes -budgetMb $budgetMb
    if ($estimatedBytes -le $budgetBytes) {
        return
    }

    $estimatedReadable = Format-Bytes -bytes $estimatedBytes
    $budgetReadable = Format-Bytes -bytes $budgetBytes
    if ($allowOverBudget) {
        Write-Warning "$stepName exceeds proxy budget ($estimatedReadable > $budgetReadable) but continuing due to explicit override."
        return
    }

    throw "$stepName exceeds proxy budget ($estimatedReadable > $budgetReadable). Use direct path, reduce scope, or rerun with -AllowProxyOverBudget."
}

if ([string]::IsNullOrWhiteSpace($ProxyEndpoint)) {
    $ProxyEndpoint = [Environment]::GetEnvironmentVariable("DENT_PROXY_ENDPOINT")
}
if ([string]::IsNullOrWhiteSpace($ProxyEndpoint)) {
    $ProxyEndpoint = "127.0.0.1:10808"
}

if ($ProxyBudgetMb -le 0) {
    $ProxyBudgetMb = Parse-PositiveInt -value ([Environment]::GetEnvironmentVariable("DENT_PROXY_BUDGET_MB")) -fallback 10
}
if ($ProxyBudgetMb -le 0) {
    $ProxyBudgetMb = 10
}

$remoteHost = Get-HostNameFromTarget -target $config.host
$healthHost = $remoteHost
foreach ($candidateUrl in $HealthCheckUrls) {
    if ([string]::IsNullOrWhiteSpace($candidateUrl)) {
        continue
    }

    $uri = $null
    if ([Uri]::TryCreate($candidateUrl.Trim(), [UriKind]::Absolute, [ref]$uri)) {
        $healthHost = $uri.Host.ToLowerInvariant()
        break
    }
}

$proxyConfigured = Test-ProxyEndpointConfigured -endpoint $ProxyEndpoint
$globalPath = Resolve-EffectiveNetworkPath `
    -preferred $NetworkPath `
    -envOverride ([Environment]::GetEnvironmentVariable("DENT_NETWORK_PATH")) `
    -targetHost $remoteHost `
    -proxyConfigured $proxyConfigured

$hostDeployPath = Resolve-EffectiveNetworkPath `
    -preferred $HostDeployNetworkPath `
    -envOverride ([Environment]::GetEnvironmentVariable("DENT_HOST_DEPLOY_PATH")) `
    -targetHost $remoteHost `
    -proxyConfigured $proxyConfigured `
    -fallback $globalPath

$healthCheckPath = Resolve-EffectiveNetworkPath `
    -preferred $HealthCheckNetworkPath `
    -envOverride ([Environment]::GetEnvironmentVariable("DENT_HEALTHCHECK_PATH")) `
    -targetHost $healthHost `
    -proxyConfigured $proxyConfigured `
    -fallback $globalPath

$githubPath = Resolve-EffectiveNetworkPath `
    -preferred $GitHubNetworkPath `
    -envOverride ([Environment]::GetEnvironmentVariable("DENT_GITHUB_PATH")) `
    -targetHost "github.com" `
    -proxyConfigured $proxyConfigured `
    -fallback $globalPath

$lowBandwidthMode = Normalize-LowBandwidthMode -value $LowBandwidthMode
$envLowBandwidthMode = Normalize-LowBandwidthMode -value ([Environment]::GetEnvironmentVariable("DENT_LOW_BANDWIDTH_MODE"))
if ($lowBandwidthMode -eq "auto") {
    $lowBandwidthMode = $envLowBandwidthMode
}

$lowBandwidthEnabled = $true
if ($lowBandwidthMode -eq "off") {
    $lowBandwidthEnabled = $false
} elseif ($lowBandwidthMode -eq "auto") {
    $lowBandwidthEnabled = $proxyConfigured
}

$script:NetworkPolicy = [PSCustomObject]@{
    ProxyEndpoint        = $ProxyEndpoint
    ProxyConfigured      = $proxyConfigured
    BudgetMb             = $ProxyBudgetMb
    GlobalPath           = $globalPath
    HostDeployPath       = $hostDeployPath
    HealthCheckPath      = $healthCheckPath
    GitHubPath           = $githubPath
    LowBandwidthEnabled  = $lowBandwidthEnabled
    StrictHostDeploy     = ($lowBandwidthEnabled -and $hostDeployPath -eq "proxy")
    StrictHealthCheck    = ($lowBandwidthEnabled -and $healthCheckPath -eq "proxy")
    StrictGitHub         = ($lowBandwidthEnabled -and $githubPath -eq "proxy")
}

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

function Get-HostDeployStatePath() {
    return Join-Path $projectRoot ".codex-local\deploy\host_last_deploy.json"
}

function Read-HostDeployState() {
    $path = Get-HostDeployStatePath
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
        return $null
    }

    try {
        $raw = Get-Content -LiteralPath $path -Raw -ErrorAction Stop
        if ([string]::IsNullOrWhiteSpace($raw)) {
            return $null
        }

        $parsed = $raw | ConvertFrom-Json -ErrorAction Stop
        $head = ""
        $branch = ""
        $finishedAt = ""
        if ($null -ne $parsed.PSObject.Properties["Head"]) {
            $head = [string]$parsed.Head
        }
        if ($null -ne $parsed.PSObject.Properties["Branch"]) {
            $branch = [string]$parsed.Branch
        }
        if ($null -ne $parsed.PSObject.Properties["FinishedAt"]) {
            $finishedAt = [string]$parsed.FinishedAt
        }

        return [PSCustomObject]@{
            Path       = $path
            Head       = $head
            Branch     = $branch
            FinishedAt = $finishedAt
        }
    } catch {
        Write-Warning "Host deploy state is unreadable at $path. A fresh state will be recorded after success."
        return $null
    }
}

function Write-HostDeployState([string]$head, [string]$branch, [string]$finishedAt) {
    if ([string]::IsNullOrWhiteSpace($head)) {
        return ""
    }

    $path = Get-HostDeployStatePath
    $directory = Split-Path -Path $path -Parent
    if (-not (Test-Path -LiteralPath $directory)) {
        New-Item -ItemType Directory -Path $directory -Force | Out-Null
    }

    $payload = [ordered]@{
        Head       = $head
        Branch     = $branch
        FinishedAt = $finishedAt
        RecordedAt = Get-IsoNow
        RemotePath = $remotePath
    }

    $json = $payload | ConvertTo-Json -Depth 5
    Set-Content -LiteralPath $path -Value $json -Encoding UTF8
    return $path
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

function Test-GitCommitExists([string]$Revision) {
    if ([string]::IsNullOrWhiteSpace($Revision)) {
        return $false
    }

    & git -C $projectRoot cat-file -e "$Revision`^{commit}" 2>$null
    return ($LASTEXITCODE -eq 0)
}

function Test-GitCommitAncestor([string]$Ancestor, [string]$Descendant) {
    if ([string]::IsNullOrWhiteSpace($Ancestor) -or [string]::IsNullOrWhiteSpace($Descendant)) {
        return $false
    }

    & git -C $projectRoot merge-base --is-ancestor $Ancestor $Descendant 2>$null
    if ($LASTEXITCODE -eq 0) {
        return $true
    }
    if ($LASTEXITCODE -eq 1) {
        return $false
    }

    throw "Unable to compare commit ancestry for deploy-state fallback."
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

function Run-VersionStamp() {
    $started = Get-IsoNow

    if ($DryRun) {
        Write-Host "[DryRun] Skip PWA version stamp"
        return [PSCustomObject]@{
            Status     = "skipped-dry-run"
            StartedAt  = $started
            FinishedAt = Get-IsoNow
            Version    = ""
            Command    = ""
        }
    }

    $scriptPath = Join-Path $projectRoot "scripts\stamp_pwa_version.py"
    if (-not (Test-Path $scriptPath)) {
        throw "PWA version stamp script not found: $scriptPath"
    }

    $python = Resolve-PythonCommand
    if ([string]::IsNullOrWhiteSpace($python)) {
        throw "Python is required for PWA version stamping but no python/python3 command was found."
    }

    Write-Host "Refreshing PWA version stamp"
    $output = & $python $scriptPath
    if ($LASTEXITCODE -ne 0) {
        throw "PWA version stamp failed (scripts/stamp_pwa_version.py)."
    }

    $version = ""
    foreach ($line in @($output)) {
        if ([string]$line -match "^STAMP_VERSION=(.+)$") {
            $version = $Matches[1].Trim()
            break
        }
    }

    return [PSCustomObject]@{
        Status     = "completed"
        StartedAt  = $started
        FinishedAt = Get-IsoNow
        Version    = $version
        Command    = "$python $scriptPath"
    }
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

    if ($script:NetworkPolicy.StrictGitHub -and -not $AllowProxyPull) {
        throw "Optional git pull is blocked in proxy low-bandwidth mode. Use direct path or rerun with -AllowProxyPull."
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

function Get-GitAheadBehind([string]$upstream) {
    if ([string]::IsNullOrWhiteSpace($upstream)) {
        return [PSCustomObject]@{
            Ahead  = 0
            Behind = 0
        }
    }

    $counts = Run-GitSingle -GitArgs @("rev-list", "--left-right", "--count", "$upstream...HEAD")
    $parts = ($counts -split "\s+") | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }
    if ($parts.Count -lt 2) {
        return [PSCustomObject]@{
            Ahead  = 0
            Behind = 0
        }
    }

    return [PSCustomObject]@{
        Behind = [int]$parts[0]
        Ahead  = [int]$parts[1]
    }
}

function Get-EstimatedBytesForGitRange([string]$rangeSpec) {
    if ([string]::IsNullOrWhiteSpace($rangeSpec)) {
        return [int64]0
    }

    $paths = Run-Git -GitArgs @("diff", "--name-only", "--diff-filter=ACMRTUXB", $rangeSpec)
    return Get-FileBytesFromRelativeList -rootPath $projectRoot -relativeList @($paths)
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
    $currentHead = Run-GitSingle -GitArgs @("rev-parse", "HEAD")
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

    $lastDeployState = Read-HostDeployState
    if ($null -ne $lastDeployState -and -not [string]::IsNullOrWhiteSpace($lastDeployState.Head)) {
        $lastHead = $lastDeployState.Head
        if (-not (Test-GitCommitExists -Revision $lastHead)) {
            [void]$notes.Add("Last successful host deploy head '$lastHead' is no longer available locally.")
        } elseif ($lastHead -eq $currentHead) {
            [void]$notes.Add("Current HEAD already matches last successful host deploy state.")
        } elseif (Test-GitCommitAncestor -Ancestor $lastHead -Descendant $currentHead) {
            Collect-GitRangeDelta -uploadSet $uploadSet -deleteSet $deleteSet -rangeSpec "$lastHead..$currentHead"
            [void]$notes.Add("Included committed public_html delta since last successful host deploy ($lastHead..$currentHead).")
        } else {
            [void]$notes.Add("Last successful host deploy head is not an ancestor of current HEAD; skipped host-state delta inference.")
        }
    } else {
        [void]$notes.Add("No previous successful host deploy state was found; fallback deploy-state delta is skipped.")
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

    if ($script:NetworkPolicy.StrictHealthCheck -and $targets.Count -gt 1) {
        Write-Warning "Proxy low-bandwidth mode active for health checks. Limiting verification to the first target."
        $targets = @($targets[0])
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
            EstimatedPushBytes = [int64]0
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
            EstimatedPushBytes = [int64]0
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
    $pushCommand = ""
    $estimatedPushBytes = [int64]0

    if ([string]::IsNullOrWhiteSpace($upstream)) {
        if ($script:NetworkPolicy.StrictGitHub -and -not $AllowProxyOverBudget) {
            throw "Proxy low-bandwidth mode cannot estimate first push size (no upstream). Use direct path or rerun with -AllowProxyOverBudget."
        }
        if ($script:NetworkPolicy.StrictGitHub -and $AllowProxyOverBudget) {
            Write-Warning "Proxy low-bandwidth mode: first push size is unknown, continuing due to explicit override."
        }
        $pushCommand = "git push -u origin $currentBranch"
        & git -C $projectRoot push -u origin $currentBranch
    } else {
        $divergence = Get-GitAheadBehind -upstream $upstream
        if ($divergence.Ahead -le 0) {
            Write-Host "No local commits ahead of $upstream; skipping push."
            $headAfterSyncNoop = Run-GitSingle -GitArgs @("rev-parse", "HEAD")
            $headCommitNoop = Get-CommitInfo -Revision $headAfterSyncNoop
            return [PSCustomObject]@{
                Status            = "completed-noop"
                StartedAt         = $started
                FinishedAt        = Get-IsoNow
                CurrentBranch     = $currentBranch
                CreatedCommit     = $createdCommit
                HeadAfterSync     = $headAfterSyncNoop
                HeadCommit        = $headCommitNoop
                PushCommand       = "skipped(no-ahead)"
                EstimatedPushBytes = [int64]0
            }
        }

        $estimatedPushBytes = Get-EstimatedBytesForGitRange -rangeSpec "$upstream..HEAD"
        if ($script:NetworkPolicy.StrictGitHub) {
            Assert-ProxyBudget -stepName "GitHub sync push" -estimatedBytes $estimatedPushBytes -budgetMb $script:NetworkPolicy.BudgetMb -allowOverBudget:$AllowProxyOverBudget
        }

        $pushCommand = "git push"
        & git -C $projectRoot push
    }

    if ($LASTEXITCODE -ne 0) {
        throw "GitHub push failed after successful host deploy/verification. Deployed host state is intact; sync to GitHub must be resolved manually."
    }

    $headAfterSync = Run-GitSingle -GitArgs @("rev-parse", "HEAD")
    $headCommit = Get-CommitInfo -Revision $headAfterSync

    return [PSCustomObject]@{
        Status            = "completed"
        StartedAt         = $started
        FinishedAt        = Get-IsoNow
        CurrentBranch     = $currentBranch
        CreatedCommit     = $createdCommit
        HeadAfterSync     = $headAfterSync
        HeadCommit        = $headCommit
        PushCommand       = $pushCommand
        EstimatedPushBytes = $estimatedPushBytes
    }
}

$validationInfo = [PSCustomObject]@{
    Status     = "not-run"
    StartedAt  = ""
    FinishedAt = ""
    Command    = ""
}
$versionStampInfo = [PSCustomObject]@{
    Status     = "not-run"
    StartedAt  = ""
    FinishedAt = ""
    Version    = ""
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
    Status               = "not-run"
    StartedAt            = ""
    FinishedAt           = ""
    Mode                 = ""
    UploadCount          = 0
    DeleteCount          = 0
    EstimatedUploadBytes = [int64]0
    Notes                = @()
}
$verificationInfo = [PSCustomObject]@{
    Status     = "not-run"
    StartedAt  = ""
    FinishedAt = ""
    Targets    = @()
}
$githubSyncInfo = [PSCustomObject]@{
    Status            = "not-run"
    StartedAt         = ""
    FinishedAt        = ""
    CurrentBranch     = ""
    CreatedCommit     = ""
    HeadAfterSync     = ""
    HeadCommit        = $null
    PushCommand       = ""
    EstimatedPushBytes = [int64]0
}
$deployStateInfo = [PSCustomObject]@{
    Status     = "not-updated"
    Path       = ""
    Head       = ""
    Branch     = ""
    FinishedAt = ""
}

$failureMessage = ""

try {
    $pullInfo = Run-OptionalPullBeforeDeploy
    $versionStampInfo = Run-VersionStamp
    $validationInfo = Run-Validation

    $deployInfo.StartedAt = Get-IsoNow
    $plan = Build-DeployPlan
    $uploadList = @($plan.UploadList)
    $deleteList = @($plan.DeleteList)

    $deployInfo.Mode = [string]$plan.Mode
    $deployInfo.UploadCount = $uploadList.Count
    $deployInfo.DeleteCount = $deleteList.Count
    $deployInfo.EstimatedUploadBytes = Get-FileBytesFromRelativeList -rootPath $localRoot -relativeList $uploadList
    $deployInfo.Notes = @($plan.Notes)

    if ($script:NetworkPolicy.StrictHostDeploy -and $deployInfo.EstimatedUploadBytes -gt 0) {
        Assert-ProxyBudget `
            -stepName "Host deploy upload" `
            -estimatedBytes $deployInfo.EstimatedUploadBytes `
            -budgetMb $script:NetworkPolicy.BudgetMb `
            -allowOverBudget:$AllowProxyOverBudget
    }

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

    if ($githubSyncInfo.HeadCommit -ne $null -and -not [string]::IsNullOrWhiteSpace([string]$githubSyncInfo.HeadCommit.Hash)) {
        $statePath = Write-HostDeployState `
            -head ([string]$githubSyncInfo.HeadCommit.Hash) `
            -branch ([string]$githubSyncInfo.CurrentBranch) `
            -finishedAt ([string]$deployInfo.FinishedAt)
        $deployStateInfo.Status = "updated"
        $deployStateInfo.Path = $statePath
        $deployStateInfo.Head = [string]$githubSyncInfo.HeadCommit.Hash
        $deployStateInfo.Branch = [string]$githubSyncInfo.CurrentBranch
        $deployStateInfo.FinishedAt = [string]$deployInfo.FinishedAt
    } else {
        $deployStateInfo.Status = "skipped-no-head"
    }
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
    if (-not $deployStateInfo.FinishedAt) {
        $deployStateInfo.FinishedAt = Get-IsoNow
    }
} finally {
    $runFinishedAt = Get-IsoNow

    Write-Host "Deploy completed to $remotePath"
    Write-Host "Deployment report (laptop-first):"
    Write-Host " - Run started at: $($runStartedAt.ToString('yyyy-MM-ddTHH:mm:sszzz'))"
    Write-Host " - Proxy endpoint target: $($script:NetworkPolicy.ProxyEndpoint)"
    Write-Host " - Proxy env detected: $($script:NetworkPolicy.ProxyConfigured)"
    Write-Host " - Low-bandwidth mode enabled: $($script:NetworkPolicy.LowBandwidthEnabled)"
    Write-Host " - Effective network path (global/host/health/github): $($script:NetworkPolicy.GlobalPath)/$($script:NetworkPolicy.HostDeployPath)/$($script:NetworkPolicy.HealthCheckPath)/$($script:NetworkPolicy.GitHubPath)"
    Write-Host " - Proxy budget: $($script:NetworkPolicy.BudgetMb) MB"

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

    Write-Host " - Version stamp status: $($versionStampInfo.Status)"
    if (-not [string]::IsNullOrWhiteSpace($versionStampInfo.StartedAt)) {
        Write-Host " - Version stamp started at: $($versionStampInfo.StartedAt)"
    }
    if (-not [string]::IsNullOrWhiteSpace($versionStampInfo.FinishedAt)) {
        Write-Host " - Version stamp finished at: $($versionStampInfo.FinishedAt)"
    }
    if (-not [string]::IsNullOrWhiteSpace($versionStampInfo.Version)) {
        Write-Host " - Active PWA version: $($versionStampInfo.Version)"
    }
    if (-not [string]::IsNullOrWhiteSpace($versionStampInfo.Command)) {
        Write-Host " - Version stamp command: $($versionStampInfo.Command)"
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
    Write-Host " - Estimated upload bytes: $(Format-Bytes -bytes $deployInfo.EstimatedUploadBytes)"
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
    Write-Host " - Estimated push bytes: $(Format-Bytes -bytes $githubSyncInfo.EstimatedPushBytes)"
    Write-Host " - Host deploy state record: $($deployStateInfo.Status)"
    if (-not [string]::IsNullOrWhiteSpace($deployStateInfo.Path)) {
        Write-Host " - Host deploy state path: $($deployStateInfo.Path)"
    }
    if (-not [string]::IsNullOrWhiteSpace($deployStateInfo.Head)) {
        Write-Host " - Last successful host deploy HEAD: $($deployStateInfo.Head)"
    }

    Write-Host " - Run finished at: $runFinishedAt"

    if (-not [string]::IsNullOrWhiteSpace($failureMessage)) {
        Write-Error $failureMessage
    }
}

if (-not [string]::IsNullOrWhiteSpace($failureMessage)) {
    throw $failureMessage
}
