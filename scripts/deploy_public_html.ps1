param(
    [switch]$DeleteVscodeOnRemote,
    [switch]$FullSync,
    [switch]$DryRun,
    [switch]$SkipValidation,
    [switch]$SkipPostDeployVerification,
    [switch]$SkipGitHubSync,
    [switch]$SkipOwnerDeployNotification,
    [switch]$SkipVersionStamp,
    [switch]$PullBeforeDeploy,
    [switch]$SkipRemoteStorageSync,
    [switch]$AllowProxyPull,
    [string]$NetworkPath = "auto",
    [string]$HostDeployNetworkPath = "auto",
    [string]$HealthCheckNetworkPath = "auto",
    [string]$GitHubNetworkPath = "auto",
    [string]$RemoteStoragePath = "storage",
    [string]$LowBandwidthMode = "auto",
    [string]$ProxyEndpoint = "",
    [string]$OwnerStudentNumber = "",
    [string]$OwnerPassword = "",
    [string]$OwnerCredentialPath = ".codex-local\deploy_owner.json",
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
$script:SharedProjectRoot = $null

function Get-SharedProjectRoot() {
    if (-not [string]::IsNullOrWhiteSpace([string]$script:SharedProjectRoot)) {
        return [string]$script:SharedProjectRoot
    }

    $resolvedRoot = $projectRoot
    $commonDirOutput = & git -C $projectRoot rev-parse --git-common-dir 2>$null
    if ($LASTEXITCODE -eq 0) {
        $commonDir = ([string]($commonDirOutput | Select-Object -First 1)).Trim()
        if (-not [string]::IsNullOrWhiteSpace($commonDir)) {
            if (-not [System.IO.Path]::IsPathRooted($commonDir)) {
                $commonDir = Join-Path $projectRoot $commonDir
            }
            try {
                $commonDir = (Resolve-Path -LiteralPath $commonDir).Path
                if ((Split-Path -Path $commonDir -Leaf) -eq ".git") {
                    $resolvedRoot = Split-Path -Path $commonDir -Parent
                }
            } catch {
            }
        }
    }

    $script:SharedProjectRoot = $resolvedRoot
    return $resolvedRoot
}

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

function Test-PreferDirectIranianHost([string]$hostName) {
    if ([string]::IsNullOrWhiteSpace($hostName)) {
        return $false
    }

    $subject = $hostName.Trim().ToLowerInvariant()
    if ([string]::IsNullOrWhiteSpace($subject)) {
        return $false
    }

    if ($subject -eq "localhost" -or $subject -eq "127.0.0.1" -or $subject -eq "::1") {
        return $true
    }

    if ($subject.EndsWith(".ir")) {
        return $true
    }

    $directHosts = @(
        "185.94.99.231",
        "cpdl1.mihanbank.com",
        "dentistry1402tums.ir",
        "www.dentistry1402tums.ir",
        "dl.dentistry1402tums.ir"
    )

    foreach ($directHost in $directHosts) {
        $rule = $directHost.Trim().ToLowerInvariant()
        if ([string]::IsNullOrWhiteSpace($rule)) {
            continue
        }
        if ($subject -eq $rule -or $subject.EndsWith("." + $rule)) {
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
    [string]$fallback = "auto",
    [bool]$preferDirectTarget = $false
) {
    $choice = Normalize-NetworkPath -value $preferred
    if ($choice -eq "auto" -and -not [string]::IsNullOrWhiteSpace($envOverride)) {
        $choice = Normalize-NetworkPath -value $envOverride
    }
    if ($choice -in @("direct", "proxy")) {
        return $choice
    }
    if ($choice -eq "auto" -and $preferDirectTarget -and (Test-PreferDirectIranianHost -hostName $targetHost)) {
        return "direct"
    }
    if ($choice -eq "auto") {
        $choice = Normalize-NetworkPath -value $fallback
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

function Get-ActivePwaVersion() {
    $versionFile = Join-Path $projectRoot "public_html\app-version.json"
    if (-not (Test-Path -LiteralPath $versionFile -PathType Leaf)) {
        return ""
    }

    try {
        $payload = Get-Content -LiteralPath $versionFile -Raw | ConvertFrom-Json
        $version = [string]($payload.version)
        if (-not [string]::IsNullOrWhiteSpace($version)) {
            return $version.Trim()
        }
    } catch {
    }

    return ""
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

if ([string]::IsNullOrWhiteSpace($ProxyEndpoint)) {
    $ProxyEndpoint = [Environment]::GetEnvironmentVariable("DENT_PROXY_ENDPOINT")
}
if ([string]::IsNullOrWhiteSpace($ProxyEndpoint)) {
    $ProxyEndpoint = "127.0.0.1:10808"
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
    -fallback $globalPath `
    -preferDirectTarget $true

$healthCheckPath = Resolve-EffectiveNetworkPath `
    -preferred $HealthCheckNetworkPath `
    -envOverride ([Environment]::GetEnvironmentVariable("DENT_HEALTHCHECK_PATH")) `
    -targetHost $healthHost `
    -proxyConfigured $proxyConfigured `
    -fallback $globalPath `
    -preferDirectTarget $true

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
    GlobalPath           = $globalPath
    HostDeployPath       = $hostDeployPath
    HealthCheckPath      = $healthCheckPath
    GitHubPath           = $githubPath
    LowBandwidthEnabled  = $lowBandwidthEnabled
    StrictHostDeploy     = ($lowBandwidthEnabled -and $hostDeployPath -eq "proxy")
    StrictHealthCheck    = ($lowBandwidthEnabled -and $healthCheckPath -eq "proxy")
    StrictGitHub         = ($lowBandwidthEnabled -and $githubPath -eq "proxy")
}

function Get-ProxyUriForPath([string]$pathChoice) {
    if ([string]::IsNullOrWhiteSpace($pathChoice) -or $pathChoice -ne "proxy") {
        return ""
    }

    $endpoint = [string]$script:NetworkPolicy.ProxyEndpoint
    if ([string]::IsNullOrWhiteSpace($endpoint)) {
        return ""
    }

    $trimmed = $endpoint.Trim()
    if ($trimmed -match '^[a-z]+://') {
        return $trimmed
    }
    return "http://$trimmed"
}

function Invoke-WithNetworkPath(
    [string]$PathChoice,
    [scriptblock]$ScriptBlock
) {
    $proxyKeys = @(
        "HTTP_PROXY",
        "HTTPS_PROXY",
        "ALL_PROXY",
        "FTP_PROXY",
        "http_proxy",
        "https_proxy",
        "all_proxy",
        "ftp_proxy",
        "NO_PROXY",
        "no_proxy"
    )

    $saved = @{}
    foreach ($key in $proxyKeys) {
        $saved[$key] = [Environment]::GetEnvironmentVariable($key)
    }

    try {
        if ($PathChoice -eq "direct") {
            foreach ($key in @("HTTP_PROXY", "HTTPS_PROXY", "ALL_PROXY", "FTP_PROXY", "http_proxy", "https_proxy", "all_proxy", "ftp_proxy")) {
                [Environment]::SetEnvironmentVariable($key, $null)
            }
            [Environment]::SetEnvironmentVariable("NO_PROXY", "*")
            [Environment]::SetEnvironmentVariable("no_proxy", "*")
        } elseif ($PathChoice -eq "proxy") {
            $proxyUri = Get-ProxyUriForPath -pathChoice $PathChoice
            foreach ($key in @("HTTP_PROXY", "HTTPS_PROXY", "ALL_PROXY", "FTP_PROXY", "http_proxy", "https_proxy", "all_proxy", "ftp_proxy")) {
                [Environment]::SetEnvironmentVariable($key, $proxyUri)
            }
            [Environment]::SetEnvironmentVariable("NO_PROXY", $null)
            [Environment]::SetEnvironmentVariable("no_proxy", $null)
        }

        return & $ScriptBlock
    } finally {
        foreach ($key in $proxyKeys) {
            [Environment]::SetEnvironmentVariable($key, $saved[$key])
        }
    }
}

function Get-IsoNow() {
    return ([DateTimeOffset]::Now).ToString("yyyy-MM-ddTHH:mm:sszzz")
}

function Test-ProtectedPublicHtmlRelativePath([string]$relative) {
    if ([string]::IsNullOrWhiteSpace($relative)) {
        return $true
    }

    $normalized = ($relative -replace '\\', '/').TrimStart('/').ToLowerInvariant()
    if ($normalized -eq "" -or $normalized -eq ".") {
        return $true
    }

    if ($normalized -eq ".env" -or $normalized.StartsWith(".env.")) {
        return $true
    }

    if ($normalized -eq "storage" -or $normalized.StartsWith("storage/")) {
        return $true
    }

    if ($normalized -eq "server-only" -or $normalized.StartsWith("server-only/")) {
        return $true
    }

    return $false
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

    if (Test-ProtectedPublicHtmlRelativePath -relative $relative) {
        return
    }

    [void]$set.Add($relative)
}

function Test-ProtectedGitHubRelativePath([string]$relative) {
    if ([string]::IsNullOrWhiteSpace($relative)) {
        return $true
    }

    $normalized = ($relative -replace '\\', '/').TrimStart('/').ToLowerInvariant()
    if ($normalized -eq "" -or $normalized -eq ".") {
        return $true
    }

    if ($normalized -eq ".git" -or $normalized.StartsWith(".git/")) {
        return $true
    }
    if ($normalized -eq ".codex-local" -or $normalized.StartsWith(".codex-local/")) {
        return $true
    }
    if ($normalized -eq ".env" -or $normalized.StartsWith(".env.")) {
        return $true
    }
    if ($normalized -eq ".vscode" -or $normalized.StartsWith(".vscode/")) {
        return $true
    }
    if ($normalized -eq "sftp.json" -or $normalized -eq "settings.json") {
        return $true
    }
    if ($normalized -eq "storage" -or $normalized.StartsWith("storage/")) {
        return $true
    }
    if ($normalized -eq "public_html/.env" -or $normalized.StartsWith("public_html/.env.")) {
        return $true
    }
    if ($normalized -eq "public_html/storage" -or $normalized.StartsWith("public_html/storage/")) {
        return $true
    }
    if ($normalized -eq "server-only/storage" -or $normalized.StartsWith("server-only/storage/")) {
        return $true
    }
    if ($normalized -eq "server-only/sessions" -or $normalized.StartsWith("server-only/sessions/")) {
        return $true
    }
    if ($normalized -eq "server-only/backups" -or $normalized.StartsWith("server-only/backups/")) {
        return $true
    }
    if ($normalized -eq "server-only/tmp" -or $normalized.StartsWith("server-only/tmp/")) {
        return $true
    }
    if ($normalized -eq "server-only/locks" -or $normalized.StartsWith("server-only/locks/")) {
        return $true
    }

    return $false
}

function Add-GitHubRelativePath([System.Collections.Generic.HashSet[string]]$set, [string]$path) {
    if ([string]::IsNullOrWhiteSpace($path)) {
        return
    }

    $normalized = ($path -replace '\\', '/').Trim().TrimStart('/')
    if ([string]::IsNullOrWhiteSpace($normalized)) {
        return
    }

    if (Test-ProtectedGitHubRelativePath -relative $normalized) {
        return
    }

    [void]$set.Add($normalized)
}

function Assert-GitAvailable() {
    if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
        throw "Git is required for deployment and GitHub sync."
    }
}

function Invoke-GitCommandCapture([string]$repoPath, [string[]]$GitArgs) {
    $previousErrorActionPreference = $ErrorActionPreference
    $output = @()
    $exitCode = 0
    try {
        $ErrorActionPreference = "Continue"
        $output = & git -C $repoPath @GitArgs 2>&1
        $exitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }

    return [PSCustomObject]@{
        Output   = @($output)
        ExitCode = [int]$exitCode
    }
}

function Get-HostDeployStatePath() {
    return Join-Path (Get-SharedProjectRoot) ".codex-local\deploy\host_last_deploy.json"
}

function Get-HostDeployManifestPath() {
    return Join-Path (Get-SharedProjectRoot) ".codex-local\deploy\host_last_deploy_manifest.json"
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
        $gitHubHead = ""
        $gitHubStatus = ""
        if ($null -ne $parsed.PSObject.Properties["Head"]) {
            $head = [string]$parsed.Head
        }
        if ($null -ne $parsed.PSObject.Properties["Branch"]) {
            $branch = [string]$parsed.Branch
        }
        if ($null -ne $parsed.PSObject.Properties["FinishedAt"]) {
            $finishedAt = [string]$parsed.FinishedAt
        }
        if ($null -ne $parsed.PSObject.Properties["GitHubHead"]) {
            $gitHubHead = [string]$parsed.GitHubHead
        }
        if ($null -ne $parsed.PSObject.Properties["GitHubStatus"]) {
            $gitHubStatus = [string]$parsed.GitHubStatus
        }

        return [PSCustomObject]@{
            Path       = $path
            Head       = $head
            Branch     = $branch
            FinishedAt = $finishedAt
            GitHubHead = $gitHubHead
            GitHubStatus = $gitHubStatus
        }
    } catch {
        Write-Warning "Host deploy state is unreadable at $path. A fresh state will be recorded after success."
        return $null
    }
}

function Write-HostDeployState([string]$head, [string]$branch, [string]$finishedAt, [string]$gitHubHead = "", [string]$gitHubStatus = "") {
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
        GitHubHead = $gitHubHead
        GitHubStatus = $gitHubStatus
        RecordedAt = Get-IsoNow
        RemotePath = $remotePath
    }

    $json = $payload | ConvertTo-Json -Depth 5
    Set-Content -LiteralPath $path -Value $json -Encoding UTF8
    return $path
}

function Get-LocalPublicHtmlFileHash([string]$relativePath) {
    if ([string]::IsNullOrWhiteSpace($relativePath)) {
        return ""
    }

    $normalized = ($relativePath -replace '/', '\').TrimStart('\')
    $fullPath = Join-Path $localRoot $normalized
    if (-not (Test-Path -LiteralPath $fullPath -PathType Leaf)) {
        return ""
    }

    try {
        return ([string](Get-FileHash -LiteralPath $fullPath -Algorithm SHA256 -ErrorAction Stop).Hash).Trim().ToLowerInvariant()
    } catch {
        return ""
    }
}

function Read-HostDeployManifest() {
    $path = Get-HostDeployManifestPath
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
        return $null
    }

    try {
        $raw = Get-Content -LiteralPath $path -Raw -ErrorAction Stop
        if ([string]::IsNullOrWhiteSpace($raw)) {
            return $null
        }

        $parsed = $raw | ConvertFrom-Json -ErrorAction Stop
        $map = New-Object 'System.Collections.Generic.Dictionary[string,string]' ([System.StringComparer]::Ordinal)
        $filesNode = $null
        if ($null -ne $parsed.PSObject.Properties["Files"]) {
            $filesNode = $parsed.Files
        }
        if ($null -eq $filesNode) {
            return $null
        }

        foreach ($property in $filesNode.PSObject.Properties) {
            $relative = [string]$property.Name
            if ([string]::IsNullOrWhiteSpace($relative)) {
                continue
            }

            $hashValue = ""
            $entry = $property.Value
            if ($entry -is [string]) {
                $hashValue = [string]$entry
            } elseif ($null -ne $entry -and $null -ne $entry.PSObject.Properties["Hash"]) {
                $hashValue = [string]$entry.Hash
            }

            $hashValue = $hashValue.Trim().ToLowerInvariant()
            if ([string]::IsNullOrWhiteSpace($hashValue)) {
                continue
            }

            $map[$relative] = $hashValue
        }

        return [PSCustomObject]@{
            Path  = $path
            Files = $map
        }
    } catch {
        Write-Warning "Host deploy manifest is unreadable at $path. Content-based delta filtering will be skipped for this run."
        return $null
    }
}

function Write-HostDeployManifest() {
    $path = Get-HostDeployManifestPath
    $directory = Split-Path -Path $path -Parent
    if (-not (Test-Path -LiteralPath $directory)) {
        New-Item -ItemType Directory -Path $directory -Force | Out-Null
    }

    $files = [ordered]@{}
    $items = Get-ChildItem -LiteralPath $localRoot -Recurse -File
    foreach ($item in $items) {
        $relative = $item.FullName.Substring($localRoot.Length).TrimStart('\') -replace '\\', '/'
        if (Test-ProtectedPublicHtmlRelativePath -relative $relative) {
            continue
        }

        $hash = Get-LocalPublicHtmlFileHash -relativePath $relative
        if ([string]::IsNullOrWhiteSpace($hash)) {
            continue
        }

        $files[$relative] = [ordered]@{
            Hash   = $hash
            Length = [int64]$item.Length
        }
    }

    $payload = [ordered]@{
        GeneratedAt = Get-IsoNow
        SourceHead  = (Run-GitSingle -GitArgs @("rev-parse", "HEAD"))
        Files       = $files
    }

    $json = $payload | ConvertTo-Json -Depth 6
    Set-Content -LiteralPath $path -Value $json -Encoding UTF8
    return $path
}

function Filter-DeployDeltaAgainstLastManifest(
    [System.Collections.Generic.HashSet[string]]$uploadSet,
    [System.Collections.Generic.HashSet[string]]$deleteSet,
    $manifest
) {
    if ($null -eq $manifest -or $null -eq $manifest.Files) {
    return [PSCustomObject]@{
            UploadsSkipped  = 0
            DeletesSkipped  = 0
        }
    }

    $files = $manifest.Files
    $uploadsSkipped = 0
    $deletesSkipped = 0

    foreach ($relative in @($uploadSet)) {
        $localHash = Get-LocalPublicHtmlFileHash -relativePath $relative
        if ([string]::IsNullOrWhiteSpace($localHash)) {
            continue
        }

        $deployedHash = ""
        if ($files.ContainsKey($relative)) {
            $deployedHash = [string]$files[$relative]
        }

        if (-not [string]::IsNullOrWhiteSpace($deployedHash) -and $deployedHash -eq $localHash) {
            [void]$uploadSet.Remove($relative)
            $uploadsSkipped += 1
        }
    }

    foreach ($relative in @($deleteSet)) {
        $fullPath = Join-Path $localRoot ($relative -replace '/', '\')
        if (Test-Path -LiteralPath $fullPath -PathType Leaf) {
            [void]$deleteSet.Remove($relative)
            $deletesSkipped += 1
            continue
        }
        if (-not $files.ContainsKey($relative)) {
            [void]$deleteSet.Remove($relative)
            $deletesSkipped += 1
        }
    }

    return [PSCustomObject]@{
        UploadsSkipped  = $uploadsSkipped
        DeletesSkipped  = $deletesSkipped
    }
}

function Normalize-RemoteStoragePath([string]$path) {
    $normalized = (($path -replace '\\', '/').Trim()).Trim('/')
    if ([string]::IsNullOrWhiteSpace($normalized)) {
        return "storage"
    }
    return $normalized
}

function Join-RemoteRelativePath([string]$left, [string]$right) {
    $a = (($left -replace '\\', '/').Trim()).Trim('/')
    $b = (($right -replace '\\', '/').Trim()).Trim('/')
    if ([string]::IsNullOrWhiteSpace($a)) {
        return $b
    }
    if ([string]::IsNullOrWhiteSpace($b)) {
        return $a
    }
    return "$a/$b"
}

function Get-FtpUrl([string]$relative, [switch]$Directory) {
    $clean = (($relative -replace '\\', '/').Trim()).Trim('/')
    $suffix = ""
    if (-not [string]::IsNullOrWhiteSpace($clean)) {
        $suffix = "/" + $clean
    }
    if ($Directory -and -not $suffix.EndsWith("/")) {
        $suffix += "/"
    }
    return "ftp://$($config.host)$suffix"
}

function Test-CurlRetryableExitCode([int]$exitCode) {
    return $exitCode -in @(5, 6, 7, 12, 13, 18, 28, 35, 47, 52, 55, 56)
}

function Invoke-CurlCommand(
    [string[]]$Arguments,
    [string]$Operation,
    [switch]$AllowFailure
) {
    $maxAttempts = 10
    $lastOutput = @()
    $lastExitCode = 0

    for ($attempt = 1; $attempt -le $maxAttempts; $attempt += 1) {
        $effectiveArguments = @("--connect-timeout", "30", "--max-time", "1800")
        if ($script:NetworkPolicy.HostDeployPath -eq "direct") {
            $effectiveArguments += @("--noproxy", "*")
        } elseif ($script:NetworkPolicy.HostDeployPath -eq "proxy") {
            $proxyUri = Get-ProxyUriForPath -pathChoice "proxy"
            if (-not [string]::IsNullOrWhiteSpace($proxyUri)) {
                $effectiveArguments += @("--proxy", $proxyUri)
            }
        }
        $effectiveArguments += @($Arguments)
        $previousErrorActionPreference = $ErrorActionPreference
        try {
            $ErrorActionPreference = "Continue"
            $output = Invoke-WithNetworkPath -PathChoice $script:NetworkPolicy.HostDeployPath -ScriptBlock {
                & curl.exe @effectiveArguments 2>&1
            }
        } finally {
            $ErrorActionPreference = $previousErrorActionPreference
        }
        $exitCode = $LASTEXITCODE
        $lastOutput = @($output)
        $lastExitCode = $exitCode

        if ($exitCode -eq 0) {
            return [PSCustomObject]@{
                Success  = $true
                Output   = @($output)
                ExitCode = $exitCode
            }
        }

        $retryable = Test-CurlRetryableExitCode -exitCode $exitCode
        if ($retryable -and $attempt -lt $maxAttempts) {
            $delaySeconds = [Math]::Min(30, (3 * $attempt) + 2)
            Write-Warning "$Operation failed on attempt $attempt/$maxAttempts (curl exit $exitCode). Retrying in $delaySeconds second(s)."
            Start-Sleep -Seconds $delaySeconds
            continue
        }

        break
    }

    if ($AllowFailure) {
        return [PSCustomObject]@{
            Success  = $false
            Output   = $lastOutput
            ExitCode = $lastExitCode
        }
    }

    $detail = Format-CommandFailureDetail -commandOutput $lastOutput -fallback "curl exit code $lastExitCode"
    throw "$Operation failed: $detail"
}

function Get-RemoteDirectoryEntries([string]$remoteRelative) {
    $url = Get-FtpUrl -relative $remoteRelative -Directory
    $result = Invoke-CurlCommand `
        -Arguments @("--silent", "--list-only", "--user", $credentials, $url) `
        -Operation "List remote directory $remoteRelative" `
        -AllowFailure
    if (-not $result.Success) {
        return $null
    }

    $entries = New-Object System.Collections.Generic.List[string]
    foreach ($line in @($result.Output)) {
        $entry = ([string]$line).Trim()
        if ([string]::IsNullOrWhiteSpace($entry) -or $entry -eq "." -or $entry -eq "..") {
            continue
        }
        [void]$entries.Add($entry)
    }
    return $entries.ToArray()
}

function Convert-RemoteListTimestamp([string]$monthToken, [string]$dayToken, [string]$timeOrYearToken) {
    $monthMap = @{
        "jan" = 1; "feb" = 2; "mar" = 3; "apr" = 4; "may" = 5; "jun" = 6;
        "jul" = 7; "aug" = 8; "sep" = 9; "oct" = 10; "nov" = 11; "dec" = 12
    }

    $monthKey = ([string]$monthToken).Trim().ToLowerInvariant()
    if (-not $monthMap.ContainsKey($monthKey)) {
        return $null
    }
    $monthNumber = [int]$monthMap[$monthKey]

    $day = 0
    if (-not [int]::TryParse(([string]$dayToken).Trim(), [ref]$day)) {
        return $null
    }

    $year = [DateTime]::Now.Year
    $hour = 0
    $minute = 0
    $timeToken = ([string]$timeOrYearToken).Trim()
    if ($timeToken -match '^\d{1,2}:\d{2}$') {
        $parts = $timeToken.Split(':')
        $hour = [int]$parts[0]
        $minute = [int]$parts[1]
    } elseif ($timeToken -match '^\d{4}$') {
        $year = [int]$timeToken
    } else {
        return $null
    }

    try {
        $candidate = Get-Date -Year $year -Month $monthNumber -Day $day -Hour $hour -Minute $minute -Second 0
        if ($timeToken -match '^\d{1,2}:\d{2}$' -and $candidate -gt (Get-Date).AddDays(1)) {
            $candidate = $candidate.AddYears(-1)
        }
        return $candidate
    } catch {
        return $null
    }
}

function Get-RemoteDirectoryListing([string]$remoteRelative) {
    $url = Get-FtpUrl -relative $remoteRelative -Directory
    $result = Invoke-CurlCommand `
        -Arguments @("--silent", "--user", $credentials, $url) `
        -Operation "List remote directory details $remoteRelative" `
        -AllowFailure
    if (-not $result.Success) {
        return $null
    }

    $entries = New-Object System.Collections.Generic.List[object]
    foreach ($line in @($result.Output)) {
        $entry = ([string]$line).Trim()
        if ([string]::IsNullOrWhiteSpace($entry) -or $entry -eq "." -or $entry -eq "..") {
            continue
        }

        if ($entry -notmatch '^(?<type>[d\-])[\w\-]{9}\s+\d+\s+\S+\s+\S+\s+(?<size>\d+)\s+(?<month>\w{3})\s+(?<day>\d{1,2})\s+(?<timeyear>\d{1,2}:\d{2}|\d{4})\s+(?<name>.+)$') {
            continue
        }

        $name = ([string]$Matches["name"]).Trim()
        if ([string]::IsNullOrWhiteSpace($name) -or $name -eq "." -or $name -eq "..") {
            continue
        }

        $lastModified = Convert-RemoteListTimestamp `
            -monthToken $Matches["month"] `
            -dayToken $Matches["day"] `
            -timeOrYearToken $Matches["timeyear"]

        $entries.Add([PSCustomObject]@{
            Name         = $name
            IsDirectory  = ($Matches["type"] -eq "d")
            Size         = [int64]$Matches["size"]
            LastModified = $lastModified
        }) | Out-Null
    }

    if ($entries.Count -eq 0) {
        return $null
    }

    return $entries.ToArray()
}

function Test-RemoteDirectory([string]$remoteRelative) {
    $entries = Get-RemoteDirectoryEntries -remoteRelative $remoteRelative
    return $null -ne $entries
}

function Test-RemoteStorageDataFileName([string]$name) {
    if ([string]::IsNullOrWhiteSpace($name)) {
        return $false
    }

    $leaf = ([string]$name).Trim().ToLowerInvariant()
    if ($leaf.EndsWith(".lock") -or $leaf.EndsWith(".log") -or $leaf.EndsWith(".tmp")) {
        return $false
    }

    if ($leaf.Contains(".")) {
        return $true
    }

    return $false
}

function Test-RemoteStorageSkippedDirectoryName([string]$name) {
    $leaf = ([string]$name).Trim().ToLowerInvariant()
    return $leaf -in @("tmp", "sessions", "backups", "cache")
}

function Test-RemoteStorageLikelyDirectoryName([string]$name) {
    if ([string]::IsNullOrWhiteSpace($name)) {
        return $false
    }

    $leaf = ([string]$name).Trim()
    if (Test-RemoteStorageSkippedDirectoryName -name $leaf) {
        return $false
    }

    return -not $leaf.Contains(".")
}

function Test-OptionalRemoteStorageDirectory([string]$remoteRelative) {
    $normalized = (($remoteRelative -replace '\\', '/').Trim()).Trim('/').ToLowerInvariant()
    if ([string]::IsNullOrWhiteSpace($normalized)) {
        return $false
    }

    $leaf = Split-Path -Path $normalized -Leaf
    if ($leaf -in @("uploads", "previews", "originals", "thumbs", "thumbnails", "tmp", "temp")) {
        return $true
    }

    if ($leaf.EndsWith("_media")) {
        return $true
    }

    if ($normalized -eq "storage/prosthesis_1402/chat/media") {
        return $true
    }

    if ($normalized -match '^storage/chat/[^/]+_media/(previews|originals|thumbs|thumbnails)$') {
        return $true
    }

    return $false
}

function Assert-PathInside([string]$path, [string]$allowedRoot, [string]$label) {
    $fullPath = [System.IO.Path]::GetFullPath($path)
    $fullRoot = [System.IO.Path]::GetFullPath($allowedRoot).TrimEnd('\', '/')
    if (-not ($fullPath.Equals($fullRoot, [System.StringComparison]::OrdinalIgnoreCase) -or $fullPath.StartsWith($fullRoot + [System.IO.Path]::DirectorySeparatorChar, [System.StringComparison]::OrdinalIgnoreCase))) {
        throw "$label resolved outside allowed root. Path=$fullPath Root=$fullRoot"
    }
    return $fullPath
}

function Reset-DirectoryFromSource([string]$source, [string]$target, [string]$allowedRoot, [string]$label) {
    $sourceFull = [System.IO.Path]::GetFullPath($source)
    if (-not (Test-Path -LiteralPath $sourceFull -PathType Container)) {
        throw "$label source does not exist: $sourceFull"
    }

    $targetFull = Assert-PathInside -path $target -allowedRoot $allowedRoot -label $label
    if (Test-Path -LiteralPath $targetFull) {
        Remove-Item -LiteralPath $targetFull -Recurse -Force
    }
    New-Item -ItemType Directory -Path $targetFull -Force | Out-Null

    foreach ($item in @(Get-ChildItem -LiteralPath $sourceFull -Force)) {
        Copy-Item -LiteralPath $item.FullName -Destination $targetFull -Recurse -Force
    }
}

function Download-RemoteStorageFile(
    [string]$remoteRelative,
    [string]$remoteRoot,
    [string]$snapshotRoot,
    [string]$seedRoot,
    [int64]$RemoteSize = -1,
    $RemoteLastModified = $null,
    [ref]$fileCount,
    [ref]$totalBytes,
    [ref]$reusedCount
) {
    $localRelative = (($remoteRelative -replace '\\', '/').Trim()).Trim('/')
    $rootPrefix = (($remoteRoot -replace '\\', '/').Trim()).Trim('/')
    if ($localRelative.Equals($rootPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
        return
    }
    if ($localRelative.StartsWith($rootPrefix + "/", [System.StringComparison]::OrdinalIgnoreCase)) {
        $localRelative = $localRelative.Substring($rootPrefix.Length + 1)
    }
    if ([string]::IsNullOrWhiteSpace($localRelative)) {
        return
    }

    $targetPath = Join-Path $snapshotRoot ($localRelative -replace '/', '\')
    $targetDirectory = Split-Path -Path $targetPath -Parent
    if (-not (Test-Path -LiteralPath $targetDirectory)) {
        New-Item -ItemType Directory -Path $targetDirectory -Force | Out-Null
    }

    $seedPath = ""
    if (-not [string]::IsNullOrWhiteSpace($seedRoot)) {
        $seedPath = Join-Path $seedRoot ($localRelative -replace '/', '\')
    }

    $reused = $false
    if (-not [string]::IsNullOrWhiteSpace($seedPath) -and (Test-Path -LiteralPath $seedPath -PathType Leaf)) {
        try {
            $seedInfo = Get-Item -LiteralPath $seedPath -ErrorAction Stop
            $sizeMatches = ($RemoteSize -lt 0 -or [int64]$seedInfo.Length -eq $RemoteSize)
            $timeMatches = $true
            if ($null -ne $RemoteLastModified -and $RemoteLastModified -is [datetime]) {
                $deltaMinutes = [Math]::Abs(($seedInfo.LastWriteTime - [datetime]$RemoteLastModified).TotalMinutes)
                $timeMatches = $deltaMinutes -lt 1.1
            }

            if ($sizeMatches -and $timeMatches) {
                Copy-Item -LiteralPath $seedPath -Destination $targetPath -Force
                if ($null -ne $RemoteLastModified -and $RemoteLastModified -is [datetime]) {
                    (Get-Item -LiteralPath $targetPath).LastWriteTime = [datetime]$RemoteLastModified
                }
                $reused = $true
                $reusedCount.Value = [int]$reusedCount.Value + 1
            }
        } catch {
            $reused = $false
        }
    }

    $url = Get-FtpUrl -relative $remoteRelative
    if (-not $reused) {
        Invoke-CurlCommand `
            -Arguments @("--fail", "--silent", "--show-error", "--remote-time", "--user", $credentials, "-o", "$targetPath", $url) `
            -Operation "Download remote storage file $remoteRelative" | Out-Null
    }

    $fileCount.Value = [int]$fileCount.Value + 1
    if (-not $reused) {
        try {
            $totalBytes.Value = [int64]$totalBytes.Value + [int64](Get-Item -LiteralPath $targetPath).Length
        } catch {
            $totalBytes.Value = [int64]$totalBytes.Value
        }
    }
}

function Download-RemoteStorageDirectory(
    [string]$remoteRelative,
    [string]$remoteRoot,
    [string]$snapshotRoot,
    [string]$seedRoot,
    [System.Collections.Generic.HashSet[string]]$visited,
    [ref]$fileCount,
    [ref]$totalBytes,
    [ref]$reusedCount
) {
    $normalized = (($remoteRelative -replace '\\', '/').Trim()).Trim('/')
    if ($visited.Contains($normalized)) {
        return
    }
    [void]$visited.Add($normalized)

    $detailedEntries = Get-RemoteDirectoryListing -remoteRelative $normalized
    if ($null -ne $detailedEntries) {
        foreach ($entry in @($detailedEntries)) {
            $child = Join-RemoteRelativePath -left $normalized -right $entry.Name
            if ($entry.IsDirectory) {
                Download-RemoteStorageDirectory `
                    -remoteRelative $child `
                    -remoteRoot $remoteRoot `
                    -snapshotRoot $snapshotRoot `
                    -seedRoot $seedRoot `
                    -visited $visited `
                    -fileCount $fileCount `
                    -totalBytes $totalBytes `
                    -reusedCount $reusedCount
            } else {
                Download-RemoteStorageFile `
                    -remoteRelative $child `
                    -remoteRoot $remoteRoot `
                    -snapshotRoot $snapshotRoot `
                    -seedRoot $seedRoot `
                    -RemoteSize ([int64]$entry.Size) `
                    -RemoteLastModified $entry.LastModified `
                    -fileCount $fileCount `
                    -totalBytes $totalBytes `
                    -reusedCount $reusedCount
            }
        }
        return
    }

    $entries = Get-RemoteDirectoryEntries -remoteRelative $normalized
    if ($null -eq $entries) {
        if (Test-OptionalRemoteStorageDirectory -remoteRelative $normalized) {
            Write-Warning "Skip missing or unlistable optional runtime directory during host storage mirror: $normalized"
            return
        }
        throw "Unable to list remote storage directory: $normalized"
    }

    foreach ($entry in @($entries)) {
        $child = Join-RemoteRelativePath -left $normalized -right $entry
        if (Test-RemoteStorageDataFileName -name $entry) {
            Download-RemoteStorageFile `
                -remoteRelative $child `
                -remoteRoot $remoteRoot `
                -snapshotRoot $snapshotRoot `
                -seedRoot $seedRoot `
                -fileCount $fileCount `
                -totalBytes $totalBytes `
                -reusedCount $reusedCount
        } elseif (Test-RemoteStorageLikelyDirectoryName -name $entry) {
            Download-RemoteStorageDirectory `
                -remoteRelative $child `
                -remoteRoot $remoteRoot `
                -snapshotRoot $snapshotRoot `
                -seedRoot $seedRoot `
                -visited $visited `
                -fileCount $fileCount `
                -totalBytes $totalBytes `
                -reusedCount $reusedCount
        }
    }
}

function Sync-RemoteStorageFromHost() {
    $started = Get-IsoNow
    if ($SkipRemoteStorageSync) {
        Write-Warning "Remote storage sync skipped by explicit -SkipRemoteStorageSync override."
        return [PSCustomObject]@{
            Status      = "skipped-explicit"
            StartedAt   = $started
            FinishedAt  = Get-IsoNow
            RemotePath  = ""
            SnapshotPath = ""
            ActivePath  = ""
            LatestPath  = ""
            FileCount   = 0
            Bytes       = [int64]0
            ReusedFiles = 0
        }
    }

    $remoteRoot = Normalize-RemoteStoragePath -path $RemoteStoragePath
    $snapshotName = ([DateTimeOffset]::Now).ToString("yyyyMMdd-HHmmss")
    $snapshotPath = Join-Path $projectRoot ".codex-local\remote-storage\snapshots\$snapshotName"
    $latestPath = Join-Path $projectRoot ".codex-local\remote-storage\latest"
    $activePath = Join-Path $projectRoot "server-only\storage"

    Write-Host "Step 0/5: download host storage to laptop"
    Write-Host "Remote storage source: /$remoteRoot"
    Write-Host "Local storage snapshot: $snapshotPath"

    New-Item -ItemType Directory -Path $snapshotPath -Force | Out-Null
    $visited = New-Object 'System.Collections.Generic.HashSet[string]' ([System.StringComparer]::OrdinalIgnoreCase)
    $fileCountValue = 0
    $totalBytesValue = [int64]0
    $reusedCountValue = 0
    $fileCount = [ref]$fileCountValue
    $totalBytes = [ref]$totalBytesValue
    $reusedCount = [ref]$reusedCountValue

    Download-RemoteStorageDirectory `
        -remoteRelative $remoteRoot `
        -remoteRoot $remoteRoot `
        -snapshotRoot $snapshotPath `
        -seedRoot $latestPath `
        -visited $visited `
        -fileCount $fileCount `
        -totalBytes $totalBytes `
        -reusedCount $reusedCount

    $serverOnlyRoot = Join-Path $projectRoot "server-only"
    $codexLocalRoot = Join-Path $projectRoot ".codex-local"
    Reset-DirectoryFromSource -source $snapshotPath -target $activePath -allowedRoot $serverOnlyRoot -label "Active storage mirror"
    Reset-DirectoryFromSource -source $snapshotPath -target $latestPath -allowedRoot $codexLocalRoot -label "Latest storage backup"

    return [PSCustomObject]@{
        Status       = "completed"
        StartedAt    = $started
        FinishedAt   = Get-IsoNow
        RemotePath   = "/$remoteRoot"
        SnapshotPath = $snapshotPath
        ActivePath   = $activePath
        LatestPath   = $latestPath
        FileCount    = [int]$fileCount.Value
        Bytes        = [int64]$totalBytes.Value
        ReusedFiles  = [int]$reusedCount.Value
    }
}

function Run-Git([string[]]$GitArgs) {
    $gitResult = Invoke-GitCommandCapture -repoPath $projectRoot -GitArgs $GitArgs
    if ($gitResult.ExitCode -ne 0) {
        $detail = Format-CommandFailureDetail -commandOutput $gitResult.Output -fallback "No stderr output."
        throw "Git command failed: git -C $projectRoot $($GitArgs -join ' '). $detail"
    }

    $stdoutLines = New-Object System.Collections.Generic.List[string]
    foreach ($entry in @($gitResult.Output)) {
        if ($entry -is [System.Management.Automation.ErrorRecord]) {
            continue
        }

        $text = [string]$entry
        if ($null -eq $text) {
            continue
        }
        [void]$stdoutLines.Add($text)
    }

    if ($stdoutLines.Count -eq 0) {
        return @()
    }

    return @($stdoutLines.ToArray())
}

function Run-GitSingle([string[]]$GitArgs) {
    $lines = @(Run-Git -GitArgs $GitArgs)
    if ($lines.Count -eq 0) {
        return ""
    }

    return [string]$lines[0]
}

function Try-GetUpstreamBranch() {
    $currentBranch = ""
    try {
        $currentBranch = Run-GitSingle -GitArgs @("rev-parse", "--abbrev-ref", "HEAD")
    } catch {
        return ""
    }

    if ([string]::IsNullOrWhiteSpace($currentBranch) -or $currentBranch -eq "HEAD") {
        return ""
    }

    $remoteName = & git -C $projectRoot config --get "branch.$currentBranch.remote" 2>$null
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace([string]$remoteName)) {
        return ""
    }

    $mergeRef = & git -C $projectRoot config --get "branch.$currentBranch.merge" 2>$null
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace([string]$mergeRef)) {
        return ""
    }

    $mergeRefName = ([string]$mergeRef).Trim()
    if ($mergeRefName.StartsWith("refs/heads/")) {
        $mergeRefName = $mergeRefName.Substring("refs/heads/".Length)
    }
    if ([string]::IsNullOrWhiteSpace($mergeRefName)) {
        return ""
    }

    return ("{0}/{1}" -f ([string]$remoteName).Trim(), $mergeRefName)
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

function Run-GitSingleAtPath([string]$repoPath, [string[]]$GitArgs) {
    $gitResult = Invoke-GitCommandCapture -repoPath $repoPath -GitArgs $GitArgs
    if ($gitResult.ExitCode -ne 0) {
        $detail = Format-CommandFailureDetail -commandOutput $gitResult.Output -fallback "No stderr output."
        throw "Git command failed: git -C $repoPath $($GitArgs -join ' '). $detail"
    }

    $lines = @()
    foreach ($entry in @($gitResult.Output)) {
        if ($entry -is [System.Management.Automation.ErrorRecord]) {
            continue
        }
        $lines += [string]$entry
    }
    if ($lines.Count -eq 0) {
        return ""
    }

    return [string]$lines[0]
}

function Get-CommitInfoAtPath([string]$repoPath, [string]$Revision) {
    if ([string]::IsNullOrWhiteSpace($Revision)) {
        return $null
    }

    $line = Run-GitSingleAtPath -repoPath $repoPath -GitArgs @("show", "-s", "--format=%H%x09%cI%x09%aI%x09%s", $Revision)
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

function Format-CommandFailureDetail($commandOutput, [string]$fallback = "") {
    $lines = @()
    foreach ($entry in @($commandOutput)) {
        $text = ([string]$entry).Trim()
        if ([string]::IsNullOrWhiteSpace($text)) {
            continue
        }
        $lines += $text
    }

    if ($lines.Count -eq 0) {
        return ([string]$fallback).Trim()
    }

    $snippet = ($lines | Select-Object -First 8) -join " | "
    if ($lines.Count -gt 8) {
        $snippet += " | ..."
    }
    return $snippet
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

function Resolve-PhpCommand() {
    $php = Get-Command php -ErrorAction SilentlyContinue
    if ($null -ne $php) {
        return [string]$php.Source
    }

    return ""
}

function Get-PhpRequiredExtensionArgs([string]$phpPath) {
    $args = New-Object System.Collections.Generic.List[string]
    if ([string]::IsNullOrWhiteSpace($phpPath)) {
        return @($args)
    }

    $loadedModules = @{}
    $moduleOutput = & $phpPath -m 2>$null
    foreach ($line in @($moduleOutput)) {
        $module = ([string]$line).Trim().ToLowerInvariant()
        if ($module -ne '') {
            $loadedModules[$module] = $true
        }
    }

    $phpDirectory = Split-Path -Path $phpPath -Parent
    $extensionDirectory = Join-Path $phpDirectory "ext"
    if (-not (Test-Path -LiteralPath $extensionDirectory -PathType Container)) {
        return @($args)
    }

    $extensionDirectoryAdded = $false
    foreach ($extension in @("openssl", "curl")) {
        if ($loadedModules.ContainsKey($extension)) {
            continue
        }

        $dllPath = Join-Path $extensionDirectory ("php_$extension.dll")
        if (-not (Test-Path -LiteralPath $dllPath -PathType Leaf)) {
            continue
        }

        if (-not $extensionDirectoryAdded) {
            [void]$args.Add("-d")
            [void]$args.Add("extension_dir=$extensionDirectory")
            $extensionDirectoryAdded = $true
        }
        [void]$args.Add("-d")
        [void]$args.Add("extension=$extension")
    }

    return @($args)
}

function Parse-VersionStampOutput($output, [string]$fallbackVersion = "") {
    $version = ""
    $changedFiles = New-Object System.Collections.Generic.List[string]

    foreach ($line in @($output)) {
        $text = [string]$line
        if ($text -match "^STAMP_VERSION=(.+)$") {
            $version = $Matches[1].Trim()
            continue
        }
        if ($text -match "^STAMP_FILE=(.+)$") {
            $relative = $Matches[1].Trim()
            if ($relative.StartsWith("public_html/")) {
                $relative = $relative.Substring("public_html/".Length)
            }
            if (-not [string]::IsNullOrWhiteSpace($relative)) {
                [void]$changedFiles.Add($relative)
            }
        }
    }

    if ([string]::IsNullOrWhiteSpace($version)) {
        $version = $fallbackVersion
    }

    return [PSCustomObject]@{
        Version     = $version
        ChangedFiles = @($changedFiles)
    }
}

function Run-VersionStamp([bool]$ShouldRunVersionStamp = $true) {
    $started = Get-IsoNow

    $scriptPath = Join-Path $projectRoot "scripts\stamp_pwa_version.py"
    if (-not (Test-Path $scriptPath)) {
        throw "PWA version stamp script not found: $scriptPath"
    }

    $python = Resolve-PythonCommand
    if ([string]::IsNullOrWhiteSpace($python)) {
        throw "Python is required for PWA version stamping but no python/python3 command was found."
    }

    if (-not $ShouldRunVersionStamp) {
        $skipMessage = "PWA version stamp skipped because no current public_html delta requires a new cache version."
        if ($DryRun) {
            Write-Host "[DryRun] $skipMessage"
            return [PSCustomObject]@{
                Status      = "skipped-no-delta"
                StartedAt   = $started
                FinishedAt  = Get-IsoNow
                Version     = Get-ActivePwaVersion
                Command     = ""
                ChangedFiles = @()
            }
        }

        Write-Host $skipMessage
        return [PSCustomObject]@{
            Status      = "skipped-no-delta"
            StartedAt   = $started
            FinishedAt  = Get-IsoNow
            Version     = Get-ActivePwaVersion
            Command     = ""
            ChangedFiles = @()
        }
    }

    if ($DryRun) {
        Write-Host "[DryRun] Preview PWA version stamp"
        $previewOutput = & $python $scriptPath --dry-run
        if ($LASTEXITCODE -ne 0) {
            throw "PWA version stamp preview failed (scripts/stamp_pwa_version.py --dry-run)."
        }
        $previewInfo = Parse-VersionStampOutput -output $previewOutput -fallbackVersion (Get-ActivePwaVersion)
        return [PSCustomObject]@{
            Status      = "previewed-dry-run"
            StartedAt  = $started
            FinishedAt = Get-IsoNow
            Version     = $previewInfo.Version
            Command     = "$python $scriptPath --dry-run"
            ChangedFiles = @($previewInfo.ChangedFiles)
        }
    }

    if ($SkipVersionStamp) {
        Write-Warning "PWA version stamp skipped by explicit -SkipVersionStamp override."
        return [PSCustomObject]@{
            Status      = "skipped-explicit"
            StartedAt   = $started
            FinishedAt  = Get-IsoNow
            Version     = Get-ActivePwaVersion
            Command     = ""
            ChangedFiles = @()
        }
    }

    Write-Host "Refreshing PWA version stamp"
    $output = & $python $scriptPath
    if ($LASTEXITCODE -ne 0) {
        throw "PWA version stamp failed (scripts/stamp_pwa_version.py)."
    }

    $stampInfo = Parse-VersionStampOutput -output $output -fallbackVersion (Get-ActivePwaVersion)
    $version = [string]$stampInfo.Version
    if ([string]::IsNullOrWhiteSpace($version)) {
        $version = Get-ActivePwaVersion
    }

    return [PSCustomObject]@{
        Status      = "completed"
        StartedAt   = $started
        FinishedAt  = Get-IsoNow
        Version     = $version
        Command     = "$python $scriptPath"
        ChangedFiles = @($stampInfo.ChangedFiles)
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
    $instructionContractScriptPath = Join-Path $projectRoot "scripts\check_instruction_contracts.py"
    $authResilienceScriptPath = Join-Path $projectRoot "scripts\check_auth_store_resilience.php"
    $examQualityScriptPath = Join-Path $projectRoot "scripts\check_exam_content_quality.php"
    $uploadConfigScriptPath = Join-Path $projectRoot "scripts\check_upload_pipeline_config.php"
    $smokeScriptPath = Join-Path $projectRoot "scripts\smoke_multi_cohort_pages.py"
    if (-not (Test-Path $scriptPath)) {
        throw "Validation script not found: $scriptPath"
    }
    if (-not (Test-Path $instructionContractScriptPath)) {
        throw "Instruction contract validation script not found: $instructionContractScriptPath"
    }
    if (-not (Test-Path $authResilienceScriptPath)) {
        throw "Auth resilience validation script not found: $authResilienceScriptPath"
    }
    if (-not (Test-Path $examQualityScriptPath)) {
        throw "Exam quality validation script not found: $examQualityScriptPath"
    }
    if (-not (Test-Path $uploadConfigScriptPath)) {
        throw "Upload pipeline validation script not found: $uploadConfigScriptPath"
    }
    if (-not (Test-Path $smokeScriptPath)) {
        throw "Smoke validation script not found: $smokeScriptPath"
    }

    $python = Resolve-PythonCommand
    if ([string]::IsNullOrWhiteSpace($python)) {
        throw "Python is required for validation but no python/python3 command was found."
    }
    $php = Get-Command php -ErrorAction SilentlyContinue
    if ($null -eq $php -or [string]::IsNullOrWhiteSpace($php.Source)) {
        throw "PHP CLI is required for validation but no php command was found."
    }

    Write-Host "Step 1/5: local validation"
    Write-Host "Running: $python $scriptPath"
    & $python $scriptPath
    if ($LASTEXITCODE -ne 0) {
        throw "Validation failed (scripts/check_text_integrity.py). Deployment aborted before host upload."
    }

    Write-Host "Running: $python $instructionContractScriptPath"
    & $python $instructionContractScriptPath
    if ($LASTEXITCODE -ne 0) {
        throw "Validation failed (scripts/check_instruction_contracts.py). Deployment aborted before host upload."
    }

    Write-Host "Running: $($php.Source) $authResilienceScriptPath"
    & $php.Source $authResilienceScriptPath
    if ($LASTEXITCODE -ne 0) {
        throw "Validation failed (scripts/check_auth_store_resilience.php). Deployment aborted before host upload."
    }

    Write-Host "Running: $($php.Source) $examQualityScriptPath"
    & $php.Source $examQualityScriptPath
    if ($LASTEXITCODE -ne 0) {
        throw "Validation failed (scripts/check_exam_content_quality.php). Deployment aborted before host upload."
    }

    Write-Host "Running: $($php.Source) $uploadConfigScriptPath"
    & $php.Source $uploadConfigScriptPath
    if ($LASTEXITCODE -ne 0) {
        throw "Validation failed (scripts/check_upload_pipeline_config.php). Deployment aborted before host upload."
    }

    $liveCredentials = Get-DeployOwnerCredentials
    if ([string]::IsNullOrWhiteSpace($liveCredentials.StudentNumber) -or [string]::IsNullOrWhiteSpace($liveCredentials.Password)) {
        $credentialHints = @($liveCredentials.Paths | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
        $hintText = if ($credentialHints.Count -gt 0) { $credentialHints -join ", " } else { "the configured owner credential path" }
        throw "Owner credentials are required for multi-cohort smoke validation. Set DENT_DEPLOY_OWNER_STUDENT_NUMBER / DENT_DEPLOY_OWNER_PASSWORD or populate one of these files: $hintText"
    }

    $smokeCommand = @(
        $smokeScriptPath,
        "--project-root", $projectRoot,
        "--owner-student-number", $liveCredentials.StudentNumber,
        "--owner-password", $liveCredentials.Password
    )
    Write-Host "Running: $python $($smokeCommand -join ' ')"
    & $python @smokeCommand
    if ($LASTEXITCODE -ne 0) {
        throw "Validation failed (scripts/smoke_multi_cohort_pages.py). Deployment aborted before host upload."
    }

    return [PSCustomObject]@{
        Status     = "completed"
        StartedAt  = $started
        FinishedAt = Get-IsoNow
        Command    = "$python $scriptPath ; $($php.Source) $authResilienceScriptPath ; $($php.Source) $examQualityScriptPath ; $($php.Source) $uploadConfigScriptPath ; $python $($smokeCommand -join ' ')"
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
    Invoke-WithNetworkPath -PathChoice $script:NetworkPolicy.GitHubPath -ScriptBlock {
        & git -C $projectRoot pull --ff-only
    } | Out-Null
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
    $currentHead = ""
    $lastDeployManifest = $null
    $lastDeployState = $null
    $canUseHostStateDelta = $false

    Assert-GitAvailable
    $currentHead = Run-GitSingle -GitArgs @("rev-parse", "HEAD")
    $lastDeployManifest = Read-HostDeployManifest
    $lastDeployState = Read-HostDeployState

    if ($FullSync) {
        $files = Get-ChildItem -LiteralPath $localRoot -Recurse -File
        foreach ($file in $files) {
            $relative = $file.FullName.Substring($localRoot.Length).TrimStart('\\') -replace '\\', '/'
            if (Test-ProtectedPublicHtmlRelativePath -relative $relative) {
                continue
            }
            [void]$uploadSet.Add($relative)
        }
        [void]$notes.Add("Full sync uploads the laptop public_html tree except protected runtime/data files.")

        return [PSCustomObject]@{
            Mode       = "full-sync (laptop source)"
            UploadList = @($uploadSet) | Sort-Object
            DeleteList = @($deleteSet) | Sort-Object
            SourceHead = $currentHead
            Notes      = @($notes)
        }
    }

    if ($null -ne $lastDeployState -and -not [string]::IsNullOrWhiteSpace($lastDeployState.Head)) {
        $lastHead = $lastDeployState.Head
        if (-not (Test-GitCommitExists -Revision $lastHead)) {
            [void]$notes.Add("Last successful host deploy head '$lastHead' is no longer available locally.")
        } elseif ($lastHead -eq $currentHead) {
            $canUseHostStateDelta = $true
            [void]$notes.Add("Current HEAD already matches last successful host deploy state; committed history delta is skipped.")
        } elseif (Test-GitCommitAncestor -Ancestor $lastHead -Descendant $currentHead) {
            $canUseHostStateDelta = $true
            Collect-GitRangeDelta -uploadSet $uploadSet -deleteSet $deleteSet -rangeSpec "$lastHead..$currentHead"
            [void]$notes.Add("Included committed public_html delta since last successful host deploy ($lastHead..$currentHead).")
        } else {
            [void]$notes.Add("Last successful host deploy head is not an ancestor of current HEAD; skipped host-state delta inference.")
        }
    } else {
        [void]$notes.Add("No previous successful host deploy state was found; fallback deploy-state delta is skipped.")
    }

    if (-not $canUseHostStateDelta) {
        $upstream = Try-GetUpstreamBranch
        if (-not [string]::IsNullOrWhiteSpace($upstream)) {
            $counts = Run-GitSingle -GitArgs @("rev-list", "--left-right", "--count", "@{upstream}...HEAD")
            $parts = $counts -split "\s+"
            if ($parts.Count -ge 2) {
                $behind = [int]$parts[0]
                $ahead = [int]$parts[1]
                if ($ahead -gt 0) {
                    Collect-GitRangeDelta -uploadSet $uploadSet -deleteSet $deleteSet -rangeSpec "@{upstream}..HEAD"
                    [void]$notes.Add("Fallback included committed local delta ahead of $upstream (ahead=$ahead) because no usable host deploy state delta was available.")
                }
                if ($behind -gt 0) {
                    [void]$notes.Add("Local branch is behind $upstream by $behind commit(s); deploy still uses laptop state.")
                }
            }
        } else {
            [void]$notes.Add("No upstream branch configured; deploying local tracked/untracked workspace delta only.")
        }
    }

    Collect-WorkingTreeDelta -uploadSet $uploadSet -deleteSet $deleteSet
    [void]$notes.Add("Included staged/unstaged/untracked local workspace changes.")

    $manifestFilter = Filter-DeployDeltaAgainstLastManifest -uploadSet $uploadSet -deleteSet $deleteSet -manifest $lastDeployManifest
    if ($manifestFilter.UploadsSkipped -gt 0) {
        [void]$notes.Add("Skipped $($manifestFilter.UploadsSkipped) candidate upload(s) because their content already matches the last deployed host manifest.")
    }

    foreach ($path in @($deleteSet)) {
        if ($uploadSet.Contains($path)) {
            [void]$deleteSet.Remove($path)
        }
    }

    return [PSCustomObject]@{
        Mode       = "local-delta (laptop source)"
        UploadList = @($uploadSet) | Sort-Object
        DeleteList = @($deleteSet) | Sort-Object
        SourceHead = $currentHead
        Notes      = @($notes)
    }
}

function Collect-GitHubRangeDelta([System.Collections.Generic.HashSet[string]]$uploadSet, [System.Collections.Generic.HashSet[string]]$deleteSet, [string]$rangeSpec) {
    if ([string]::IsNullOrWhiteSpace($rangeSpec)) {
        return
    }

    $trackedChanges = Run-Git -GitArgs @("diff", "--name-only", "--diff-filter=ACMRTUXB", $rangeSpec)
    foreach ($path in $trackedChanges) {
        Add-GitHubRelativePath -set $uploadSet -path $path
    }

    $trackedDeletes = Run-Git -GitArgs @("diff", "--name-only", "--diff-filter=D", $rangeSpec)
    foreach ($path in $trackedDeletes) {
        Add-GitHubRelativePath -set $deleteSet -path $path
    }

    $renames = Run-Git -GitArgs @("diff", "--name-status", "--diff-filter=R", $rangeSpec)
    foreach ($line in $renames) {
        if ([string]::IsNullOrWhiteSpace($line)) {
            continue
        }

        $parts = $line -split "`t"
        if ($parts.Count -lt 3) {
            continue
        }

        Add-GitHubRelativePath -set $deleteSet -path $parts[1]
        Add-GitHubRelativePath -set $uploadSet -path $parts[2]
    }
}

function Collect-GitHubWorkingTreeDelta([System.Collections.Generic.HashSet[string]]$uploadSet, [System.Collections.Generic.HashSet[string]]$deleteSet) {
    $trackedChanges = Run-Git -GitArgs @("diff", "--name-only", "--diff-filter=ACMRTUXB", "HEAD")
    foreach ($path in $trackedChanges) {
        Add-GitHubRelativePath -set $uploadSet -path $path
    }

    $trackedDeletes = Run-Git -GitArgs @("diff", "--name-only", "--diff-filter=D", "HEAD")
    foreach ($path in $trackedDeletes) {
        Add-GitHubRelativePath -set $deleteSet -path $path
    }

    $renames = Run-Git -GitArgs @("diff", "--name-status", "--diff-filter=R", "HEAD")
    foreach ($line in $renames) {
        if ([string]::IsNullOrWhiteSpace($line)) {
            continue
        }

        $parts = $line -split "`t"
        if ($parts.Count -lt 3) {
            continue
        }

        Add-GitHubRelativePath -set $deleteSet -path $parts[1]
        Add-GitHubRelativePath -set $uploadSet -path $parts[2]
    }

    $untracked = Run-Git -GitArgs @("ls-files", "--others", "--exclude-standard")
    foreach ($path in $untracked) {
        Add-GitHubRelativePath -set $uploadSet -path $path
    }
}

function Build-GitHubSyncPlan([string]$upstream) {
    $uploadSet = New-Object 'System.Collections.Generic.HashSet[string]' ([System.StringComparer]::Ordinal)
    $deleteSet = New-Object 'System.Collections.Generic.HashSet[string]' ([System.StringComparer]::Ordinal)
    $notes = New-Object System.Collections.Generic.List[string]
    $currentHead = Run-GitSingle -GitArgs @("rev-parse", "HEAD")

    if (-not [string]::IsNullOrWhiteSpace($upstream)) {
        $divergence = Get-GitAheadBehind -upstream $upstream
        if ($divergence.Ahead -gt 0) {
            Collect-GitHubRangeDelta -uploadSet $uploadSet -deleteSet $deleteSet -rangeSpec "$upstream..HEAD"
            [void]$notes.Add("Included committed local code delta ahead of $upstream (ahead=$($divergence.Ahead)).")
        }
        if ($divergence.Behind -gt 0) {
            [void]$notes.Add("Local branch is behind $upstream by $($divergence.Behind) commit(s); GitHub sync will replay local delta on top of the latest remote branch.")
        }
    } else {
        [void]$notes.Add("No upstream branch configured; GitHub sync will stage the current local code delta only.")
    }

    Collect-GitHubWorkingTreeDelta -uploadSet $uploadSet -deleteSet $deleteSet
    [void]$notes.Add("Included staged/unstaged/untracked local code changes.")

    foreach ($path in @($deleteSet)) {
        if ($uploadSet.Contains($path)) {
            [void]$deleteSet.Remove($path)
        }
    }

    $pathScope = New-Object 'System.Collections.Generic.HashSet[string]' ([System.StringComparer]::Ordinal)
    foreach ($path in @($uploadSet)) {
        if (-not [string]::IsNullOrWhiteSpace([string]$path)) {
            [void]$pathScope.Add([string]$path)
        }
    }
    foreach ($path in @($deleteSet)) {
        if (-not [string]::IsNullOrWhiteSpace([string]$path)) {
            [void]$pathScope.Add([string]$path)
        }
    }

    return [PSCustomObject]@{
        Mode       = "repo-code-delta (laptop source)"
        UploadList = @($uploadSet) | Sort-Object
        DeleteList = @($deleteSet) | Sort-Object
        PathScope  = @($pathScope) | Sort-Object
        SourceHead = $currentHead
        Upstream   = $upstream
        Notes      = @($notes)
    }
}

function Get-GitHubStagePathList([string[]]$pathScope) {
    $stageSet = New-Object 'System.Collections.Generic.HashSet[string]' ([System.StringComparer]::Ordinal)
    foreach ($path in @($pathScope)) {
        $normalized = ([string]$path).Trim().TrimStart('/')
        if ([string]::IsNullOrWhiteSpace($normalized)) {
            continue
        }
        if (Test-ProtectedGitHubRelativePath -relative $normalized) {
            continue
        }
        [void]$stageSet.Add($normalized)
    }

    return @($stageSet) | Sort-Object
}

function Resolve-GitHubSyncTarget([string]$currentBranch, [string]$upstream) {
    $remoteName = "origin"
    $branchName = $currentBranch

    $normalizedUpstream = ([string]$upstream).Trim()
    if (-not [string]::IsNullOrWhiteSpace($normalizedUpstream) -and $normalizedUpstream.Contains("/")) {
        $parts = $normalizedUpstream.Split("/", 2)
        if ($parts.Count -ge 2) {
            $remoteName = $parts[0]
            $branchName = $parts[1]
        }
    }

    return [PSCustomObject]@{
        RemoteName = $remoteName
        BranchName = $branchName
    }
}

function Get-GitHubSyncWorktreePath([string]$branchName) {
    $safeBranch = ([string]$branchName).Trim()
    if ([string]::IsNullOrWhiteSpace($safeBranch)) {
        $safeBranch = "default"
    }
    $safeBranch = $safeBranch -replace '[^A-Za-z0-9._-]', '-'
    return Join-Path $env:TEMP ("dent1402-github-sync-" + $safeBranch)
}

function Remove-GitHubSyncWorktree([string]$path) {
    if ([string]::IsNullOrWhiteSpace($path)) {
        return
    }

    $normalizedPath = [System.IO.Path]::GetFullPath($path)
    $registered = $false
    $worktreeList = & git -C $projectRoot worktree list --porcelain 2>$null
    foreach ($line in @($worktreeList)) {
        $text = [string]$line
        if (-not $text.StartsWith("worktree ")) {
            continue
        }

        $listedPath = $text.Substring("worktree ".Length).Trim()
        if ([string]::IsNullOrWhiteSpace($listedPath)) {
            continue
        }

        if ([System.IO.Path]::GetFullPath($listedPath).Equals($normalizedPath, [System.StringComparison]::OrdinalIgnoreCase)) {
            $registered = $true
            break
        }
    }

    if ($registered) {
        & git -C $projectRoot worktree remove --force $path 2>$null
    }
    if (Test-Path -LiteralPath $path) {
        Remove-Item -LiteralPath $path -Recurse -Force
    }
}

function New-GitHubSyncWorktree([string]$remoteName, [string]$branchName, [string]$fallbackRef) {
    if ([string]::IsNullOrWhiteSpace($remoteName)) {
        throw "GitHub sync requires a remote name."
    }
    if ([string]::IsNullOrWhiteSpace($branchName)) {
        throw "GitHub sync requires a target branch name."
    }

    $worktreePath = Get-GitHubSyncWorktreePath -branchName $branchName
    $hasRemoteBranch = $false
    $baseRef = $fallbackRef

    $fetchResult = Invoke-WithNetworkPath -PathChoice $script:NetworkPolicy.GitHubPath -ScriptBlock {
        Invoke-GitCommandCapture -repoPath $projectRoot -GitArgs @("fetch", "--no-tags", "--quiet", $remoteName, $branchName)
    }
    if ($fetchResult.ExitCode -eq 0) {
        $remoteRef = "refs/remotes/$remoteName/$branchName"
        & git -C $projectRoot show-ref --verify --quiet $remoteRef 2>$null
        if ($LASTEXITCODE -eq 0) {
            $baseRef = "$remoteName/$branchName"
            $hasRemoteBranch = $true
        }
    } elseif ([string]::IsNullOrWhiteSpace([string]$fallbackRef)) {
        $fetchDetail = Format-CommandFailureDetail -commandOutput $fetchResult.Output -fallback "No stderr output."
        throw "GitHub sync fetch failed for $remoteName/$branchName. $fetchDetail"
    }

    if ([string]::IsNullOrWhiteSpace($baseRef)) {
        throw "GitHub sync could not determine a base revision for the temporary worktree."
    }

    Remove-GitHubSyncWorktree -path $worktreePath

    $worktreeAddResult = Invoke-GitCommandCapture -repoPath $projectRoot -GitArgs @("worktree", "add", "--quiet", "--force", "--detach", $worktreePath, $baseRef)
    if ($worktreeAddResult.ExitCode -ne 0) {
        $worktreeDetail = Format-CommandFailureDetail -commandOutput $worktreeAddResult.Output -fallback "No stderr output."
        throw "GitHub sync could not create the temporary worktree at $worktreePath. $worktreeDetail"
    }

    return [PSCustomObject]@{
        Path            = $worktreePath
        BaseRef         = $baseRef
        RemoteName      = $remoteName
        BranchName      = $branchName
        HasRemoteBranch = $hasRemoteBranch
    }
}

function Apply-GitHubSyncPlanToWorktree([string]$repoPath, [string[]]$uploadList, [string[]]$deleteList) {
    foreach ($relative in @($uploadList)) {
        if ([string]::IsNullOrWhiteSpace($relative)) {
            continue
        }

        $sourcePath = Join-Path $projectRoot ($relative -replace '/', '\')
        if (-not (Test-Path -LiteralPath $sourcePath -PathType Leaf)) {
            continue
        }

        $targetPath = Join-Path $repoPath ($relative -replace '/', '\')
        $targetDirectory = Split-Path -Path $targetPath -Parent
        if (-not [string]::IsNullOrWhiteSpace($targetDirectory) -and -not (Test-Path -LiteralPath $targetDirectory)) {
            New-Item -ItemType Directory -Path $targetDirectory -Force | Out-Null
        }

        Copy-Item -LiteralPath $sourcePath -Destination $targetPath -Force
    }

    foreach ($relative in @($deleteList)) {
        if ([string]::IsNullOrWhiteSpace($relative)) {
            continue
        }

        $targetPath = Join-Path $repoPath ($relative -replace '/', '\')
        if (Test-Path -LiteralPath $targetPath) {
            Remove-Item -LiteralPath $targetPath -Recurse -Force
        }
    }
}

function Upload-File([string]$relative) {
    if (Test-ProtectedPublicHtmlRelativePath -relative $relative) {
        Write-Warning "Skip protected runtime/local-only file: $relative"
        return
    }

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
    Invoke-CurlCommand `
        -Arguments @("--silent", "--show-error", "--ftp-create-dirs", "--user", $credentials, "-T", "$source", $target) `
        -Operation "Upload $relative" | Out-Null
}

function Delete-RemoteFile([string]$relative) {
    if (Test-ProtectedPublicHtmlRelativePath -relative $relative) {
        Write-Warning "Skip protected remote delete: $relative"
        return
    }

    if ($DryRun) {
        Write-Host "[DryRun] Delete remote $relative"
        return
    }

    Write-Host "Deleting remote $relative"
    Invoke-CurlCommand `
        -Arguments @("--silent", "--show-error", "--user", $credentials, "--quote", "DELE $remotePath/$relative", "ftp://$($config.host)/") `
        -Operation "Delete remote $relative" | Out-Null
}

function Invoke-HealthCheck([string]$url) {
    if ([string]::IsNullOrWhiteSpace($url)) {
        return
    }

    Write-Host "Health check: $url"

    try {
        $response = Invoke-WithNetworkPath -PathChoice $script:NetworkPolicy.HealthCheckPath -ScriptBlock {
            Invoke-WebRequest -Uri $url -Method Get -MaximumRedirection 5 -TimeoutSec 30 -UseBasicParsing
        }
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

function Invoke-ReleaseCompletionGuard([object]$DeployInfo, [object]$VerificationInfo, [object]$OwnerNoticeInfo, [object]$GitHubSyncInfo) {
    $started = Get-IsoNow

    if ($DryRun) {
        Write-Host "[DryRun] Step 6/6 skipped: release completion guard"
        return [PSCustomObject]@{
            Status     = "skipped-dry-run"
            StartedAt  = $started
            FinishedAt = Get-IsoNow
            Command    = ""
            Notes      = @()
        }
    }

    $python = Resolve-PythonCommand
    if ([string]::IsNullOrWhiteSpace($python)) {
        throw "Python is required for the release completion guard but no python/python3 command was found."
    }

    $freshnessScriptPath = Join-Path $projectRoot "scripts\check_host_deploy_freshness.py"
    if (-not (Test-Path $freshnessScriptPath)) {
        throw "Release completion guard script not found: $freshnessScriptPath"
    }

    Write-Host "Step 6/6: release completion guard"

    if ([string]$DeployInfo.Status -ne "completed") {
        throw "Release completion guard failed: host deploy status is '$([string]$DeployInfo.Status)'."
    }
    if ([string]$VerificationInfo.Status -ne "completed") {
        throw "Release completion guard failed: live post-deploy verification status is '$([string]$VerificationInfo.Status)'."
    }
    $gitHubStatus = [string]$GitHubSyncInfo.Status
    if ($gitHubStatus -notin @("completed", "completed-noop")) {
        throw "Release completion guard failed: GitHub sync status is '$gitHubStatus'."
    }

    $ownerNoticeStatus = [string]$OwnerNoticeInfo.Status
    $hasHostDelta = (($DeployInfo.UploadCount -as [int]) -gt 0) -or (($DeployInfo.DeleteCount -as [int]) -gt 0)
    if ($hasHostDelta) {
        if ($ownerNoticeStatus -ne "completed") {
            throw "Release completion guard failed: owner deploy notification status is '$ownerNoticeStatus' despite a real host delta."
        }
    } elseif ($ownerNoticeStatus -notin @("completed", "skipped-no-host-delta")) {
        throw "Release completion guard failed: owner deploy notification status is '$ownerNoticeStatus'."
    }

    $output = & $python $freshnessScriptPath
    if ($LASTEXITCODE -ne 0) {
        throw "Release completion guard failed (scripts/check_host_deploy_freshness.py)."
    }

    return [PSCustomObject]@{
        Status     = "completed"
        StartedAt  = $started
        FinishedAt = Get-IsoNow
        Command    = "$python $freshnessScriptPath"
        Notes      = @($output)
    }
}

function Sync-GitHubFromLaptop([object]$GitHubPlan) {
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
            Notes         = @()
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
            Notes         = @()
        }
    }

    Assert-GitAvailable

    $currentBranch = Run-GitSingle -GitArgs @("rev-parse", "--abbrev-ref", "HEAD")
    if ($currentBranch -eq "HEAD") {
        throw "Detached HEAD is not supported for GitHub sync. Checkout a branch first."
    }

    Write-Host "Step 5/5: sync GitHub from deployed laptop state"

    $upstream = Try-GetUpstreamBranch
    if ($null -eq $GitHubPlan) {
        $GitHubPlan = Build-GitHubSyncPlan -upstream $upstream
    }

    $uploadList = @($GitHubPlan.UploadList)
    $deleteList = @($GitHubPlan.DeleteList)
    $pathScope = @()
    if ($null -ne $GitHubPlan.PSObject.Properties["PathScope"]) {
        $pathScope = @($GitHubPlan.PathScope)
    } else {
        $pathScope = @($uploadList + $deleteList)
    }
    $notes = @($GitHubPlan.Notes)
    $estimatedPushBytes = Get-FileBytesFromRelativeList -rootPath $projectRoot -relativeList $uploadList

    $target = Resolve-GitHubSyncTarget -currentBranch $currentBranch -upstream $upstream
    $sourceHead = ""
    if ($null -ne $GitHubPlan.PSObject.Properties["SourceHead"]) {
        $sourceHead = [string]$GitHubPlan.SourceHead
    }
    if ([string]::IsNullOrWhiteSpace($sourceHead)) {
        $sourceHead = Run-GitSingle -GitArgs @("rev-parse", "HEAD")
    }

    if ($uploadList.Count -eq 0 -and $deleteList.Count -eq 0) {
        $worktree = $null
        try {
            $worktree = New-GitHubSyncWorktree -remoteName $target.RemoteName -branchName $target.BranchName -fallbackRef $sourceHead
            $headAfterSyncNoop = Run-GitSingleAtPath -repoPath $worktree.Path -GitArgs @("rev-parse", "HEAD")
            $headCommitNoop = Get-CommitInfoAtPath -repoPath $worktree.Path -Revision $headAfterSyncNoop
            Write-Host "No repo code delta detected for GitHub sync; skipping push."
            return [PSCustomObject]@{
                Status            = "completed-noop"
                StartedAt         = $started
                FinishedAt        = Get-IsoNow
                CurrentBranch     = $target.BranchName
                CreatedCommit     = ""
                HeadAfterSync     = $headAfterSyncNoop
                HeadCommit        = $headCommitNoop
                PushCommand       = "skipped(no-code-delta)"
                EstimatedPushBytes = [int64]0
                Notes             = $notes
            }
        } finally {
            if ($worktree -ne $null) {
                Remove-GitHubSyncWorktree -path $worktree.Path
            }
        }
    }

    $maxAttempts = 2
    $pushCommand = ""
    for ($attempt = 1; $attempt -le $maxAttempts; $attempt++) {
        $worktree = $null
        $pushDetail = ""
        try {
            $worktree = New-GitHubSyncWorktree -remoteName $target.RemoteName -branchName $target.BranchName -fallbackRef $sourceHead
            Apply-GitHubSyncPlanToWorktree -repoPath $worktree.Path -uploadList $uploadList -deleteList $deleteList

            $stagePaths = @(Get-GitHubStagePathList -pathScope $pathScope)
            if ($stagePaths.Count -eq 0) {
                throw "GitHub sync plan resolved to an empty stage scope after filtering protected paths."
            }

            $stagePathSpecFile = ""
            try {
                $stagePathSpecFile = [System.IO.Path]::GetTempFileName()
                $stagePathPayload = [string]::Join([char]0, $stagePaths) + [char]0
                [System.IO.File]::WriteAllText($stagePathSpecFile, $stagePathPayload, (New-Object System.Text.UTF8Encoding($false)))

                $gitAddResult = Invoke-GitCommandCapture -repoPath $worktree.Path -GitArgs @(
                    "add",
                    "-A",
                    "--pathspec-from-file=$stagePathSpecFile",
                    "--pathspec-file-nul"
                )
                if ($gitAddResult.ExitCode -ne 0) {
                    $gitAddDetail = Format-CommandFailureDetail -commandOutput $gitAddResult.Output -fallback "No stderr output."
                    throw "git add failed inside the temporary GitHub sync worktree. $gitAddDetail"
                }
            } finally {
                if (-not [string]::IsNullOrWhiteSpace($stagePathSpecFile) -and (Test-Path -LiteralPath $stagePathSpecFile)) {
                    Remove-Item -LiteralPath $stagePathSpecFile -Force -ErrorAction SilentlyContinue
                }
            }

            $diffProbeResult = Invoke-GitCommandCapture -repoPath $worktree.Path -GitArgs @("diff", "--cached", "--quiet", "--exit-code")
            $hasStagedChanges = $false
            if ($diffProbeResult.ExitCode -eq 1) {
                $hasStagedChanges = $true
            } elseif ($diffProbeResult.ExitCode -ne 0) {
                $diffProbeDetail = Format-CommandFailureDetail -commandOutput $diffProbeResult.Output -fallback "No stderr output."
                throw "Unable to determine staged changes inside the temporary GitHub sync worktree. $diffProbeDetail"
            }

            $createdCommit = ""
            if ($hasStagedChanges) {
                $gitCommitResult = Invoke-GitCommandCapture -repoPath $worktree.Path -GitArgs @("commit", "-m", $CommitMessage)
                if ($gitCommitResult.ExitCode -ne 0) {
                    $gitCommitDetail = Format-CommandFailureDetail -commandOutput $gitCommitResult.Output -fallback "No stderr output."
                    throw "git commit failed inside the temporary GitHub sync worktree. $gitCommitDetail"
                }
                $createdCommit = Run-GitSingleAtPath -repoPath $worktree.Path -GitArgs @("rev-parse", "HEAD")
                Write-Host "Created GitHub sync commit: $createdCommit"
            } else {
                Write-Host "GitHub sync worktree already matches the latest remote branch after overlay; skipping push."
            }

            $pushSpec = "HEAD:refs/heads/$($target.BranchName)"
            if ($worktree.HasRemoteBranch) {
                $pushCommand = "git push $($target.RemoteName) $pushSpec"
                if ($hasStagedChanges) {
                    $pushResult = Invoke-WithNetworkPath -PathChoice $script:NetworkPolicy.GitHubPath -ScriptBlock {
                        Invoke-GitCommandCapture -repoPath $worktree.Path -GitArgs @("push", "--quiet", $target.RemoteName, $pushSpec)
                    }
                    $pushDetail = Format-CommandFailureDetail -commandOutput $pushResult.Output -fallback "No stderr output."
                }
            } else {
                $pushCommand = "git push -u $($target.RemoteName) $pushSpec"
                if ($hasStagedChanges) {
                    $pushResult = Invoke-WithNetworkPath -PathChoice $script:NetworkPolicy.GitHubPath -ScriptBlock {
                        Invoke-GitCommandCapture -repoPath $worktree.Path -GitArgs @("push", "--quiet", "-u", $target.RemoteName, $pushSpec)
                    }
                    $pushDetail = Format-CommandFailureDetail -commandOutput $pushResult.Output -fallback "No stderr output."
                }
            }

            if ($hasStagedChanges -and $pushResult.ExitCode -ne 0) {
                if ($attempt -lt $maxAttempts) {
                    Write-Warning "GitHub sync push failed on attempt $attempt. $pushDetail Retrying on top of the latest remote branch."
                    continue
                }
                throw "GitHub sync push failed after successful host deploy/verification. $pushDetail"
            }

            $headAfterSync = Run-GitSingleAtPath -repoPath $worktree.Path -GitArgs @("rev-parse", "HEAD")
            $headCommit = Get-CommitInfoAtPath -repoPath $worktree.Path -Revision $headAfterSync

            return [PSCustomObject]@{
                Status            = if ($hasStagedChanges) { "completed" } else { "completed-noop" }
                StartedAt         = $started
                FinishedAt        = Get-IsoNow
                CurrentBranch     = $target.BranchName
                CreatedCommit     = $createdCommit
                HeadAfterSync     = $headAfterSync
                HeadCommit        = $headCommit
                PushCommand       = if ($hasStagedChanges) { $pushCommand } else { "skipped(remote-already-matched)" }
                EstimatedPushBytes = $estimatedPushBytes
                Notes             = $notes
            }
        } finally {
            if ($worktree -ne $null) {
                Remove-GitHubSyncWorktree -path $worktree.Path
            }
        }
    }

    throw "GitHub sync exhausted its automatic retry budget after successful host deploy/verification."
}

function Get-OwnerCredentialFilePaths() {
    $paths = New-Object System.Collections.Generic.List[string]
    $sharedRoot = Get-SharedProjectRoot

    if (-not [string]::IsNullOrWhiteSpace($OwnerCredentialPath)) {
        if ([System.IO.Path]::IsPathRooted($OwnerCredentialPath)) {
            [void]$paths.Add($OwnerCredentialPath)
        } else {
            [void]$paths.Add((Join-Path $projectRoot $OwnerCredentialPath))
            $sharedCredentialPath = Join-Path $sharedRoot $OwnerCredentialPath
            if (-not ($paths.Contains($sharedCredentialPath))) {
                [void]$paths.Add($sharedCredentialPath)
            }
        }
    }

    $legacyPath = Join-Path $sharedRoot ".codex-local\deploy_completion_owner.json"
    if (-not ($paths.Contains($legacyPath))) {
        [void]$paths.Add($legacyPath)
    }

    return @($paths | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
}

function Get-DeployOwnerCredentials() {
    $studentNumber = $OwnerStudentNumber
    $password = $OwnerPassword

    if ([string]::IsNullOrWhiteSpace($studentNumber)) {
        $studentNumber = [Environment]::GetEnvironmentVariable("DENT_DEPLOY_OWNER_STUDENT_NUMBER")
    }
    if ([string]::IsNullOrWhiteSpace($password)) {
        $password = [Environment]::GetEnvironmentVariable("DENT_DEPLOY_OWNER_PASSWORD")
    }

    $credentialPaths = @(Get-OwnerCredentialFilePaths)
    if ([string]::IsNullOrWhiteSpace($studentNumber) -or [string]::IsNullOrWhiteSpace($password)) {
        foreach ($credentialPath in $credentialPaths) {
            if ([string]::IsNullOrWhiteSpace($credentialPath) -or -not (Test-Path -LiteralPath $credentialPath -PathType Leaf)) {
                continue
            }

            try {
                $credential = Get-Content -LiteralPath $credentialPath -Raw -Encoding UTF8 | ConvertFrom-Json
                if ([string]::IsNullOrWhiteSpace($studentNumber) -and $credential.PSObject.Properties.Name -contains "studentNumber") {
                    $studentNumber = [string]$credential.studentNumber
                }
                if ([string]::IsNullOrWhiteSpace($password) -and $credential.PSObject.Properties.Name -contains "password") {
                    $password = [string]$credential.password
                }
            } catch {
                Write-Warning "Unable to read owner credential file: $credentialPath"
            }

            if (-not [string]::IsNullOrWhiteSpace($studentNumber) -and -not [string]::IsNullOrWhiteSpace($password)) {
                break
            }
        }
    }

    return [PSCustomObject]@{
        StudentNumber = $studentNumber
        Password      = $password
        Paths         = $credentialPaths
        PrimaryPath   = if ($credentialPaths.Count -gt 0) { $credentialPaths[0] } else { "" }
    }
}

function Resolve-PrimarySiteBaseUrl() {
    foreach ($candidateUrl in @($HealthCheckUrls)) {
        if ([string]::IsNullOrWhiteSpace($candidateUrl)) {
            continue
        }

        $uri = $null
        if ([Uri]::TryCreate($candidateUrl.Trim(), [UriKind]::Absolute, [ref]$uri)) {
            return $uri.GetLeftPart([System.UriPartial]::Authority)
        }
    }

    return "https://dentistry1402tums.ir"
}

function Get-WebExceptionResponseBody($exception) {
    if ($null -eq $exception -or $null -eq $exception.Response) {
        return ""
    }

    try {
        $stream = $exception.Response.GetResponseStream()
        if ($null -eq $stream) {
            return ""
        }

        $reader = New-Object System.IO.StreamReader($stream)
        try {
            return $reader.ReadToEnd()
        } finally {
            $reader.Dispose()
        }
    } catch {
        return ""
    }
}

function Invoke-SiteFormJsonRequest(
    [string]$Uri,
    [hashtable]$Body,
    [string]$Label,
    $Session = $null
) {
    $invokeArgs = @{
        Uri                = $Uri
        Method             = "Post"
        Body               = $Body
        MaximumRedirection = 3
        TimeoutSec         = 30
        UseBasicParsing    = $true
    }
    if ($null -ne $Session) {
        $invokeArgs.WebSession = $Session
    }

    try {
        $response = Invoke-WithNetworkPath -PathChoice $script:NetworkPolicy.HealthCheckPath -ScriptBlock {
            Invoke-WebRequest @invokeArgs
        }
    } catch {
        $responseBody = Get-WebExceptionResponseBody $_.Exception
        $message = if ([string]::IsNullOrWhiteSpace($responseBody)) {
            $_.Exception.Message
        } else {
            $responseBody
        }
        throw "$Label request failed. $message"
    }

    try {
        return ($response.Content | ConvertFrom-Json -ErrorAction Stop)
    } catch {
        throw "$Label returned non-JSON response."
    }
}

function Send-OwnerDeployNotice(
    [string]$Version,
    [string]$DeployedAt,
    [string]$Branch,
    [string]$DeployHead
) {
    $started = Get-IsoNow
    if ($DryRun) {
        Write-Host "[DryRun] Skip owner deploy notification"
        return [PSCustomObject]@{
            Status         = "skipped-dry-run"
            StartedAt      = $started
            FinishedAt     = Get-IsoNow
            SiteBaseUrl    = ""
            NotificationId = ""
            Version        = $Version
            Message        = ""
        }
    }

    if ([string]::IsNullOrWhiteSpace($Version)) {
        $Version = Get-ActivePwaVersion
    }
    if ([string]::IsNullOrWhiteSpace($Version) -and -not [string]::IsNullOrWhiteSpace($DeployHead)) {
        $shortHead = $DeployHead.Substring(0, [Math]::Min(12, $DeployHead.Length))
        $Version = "manual-$shortHead"
    }
    if ([string]::IsNullOrWhiteSpace($Version)) {
        throw "Owner deploy notification requires the active PWA version."
    }
    if ([string]::IsNullOrWhiteSpace($DeployedAt)) {
        throw "Owner deploy notification requires the exact deploy timestamp."
    }

    $liveCredentials = Get-DeployOwnerCredentials
    if ([string]::IsNullOrWhiteSpace($liveCredentials.StudentNumber) -or [string]::IsNullOrWhiteSpace($liveCredentials.Password)) {
        $credentialHints = @($liveCredentials.Paths | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
        $hintText = if ($credentialHints.Count -gt 0) { $credentialHints -join ", " } else { "the configured owner credential path" }
        throw "Owner credentials are required to send the post-deploy owner notification. Set DENT_DEPLOY_OWNER_STUDENT_NUMBER / DENT_DEPLOY_OWNER_PASSWORD or populate one of these files: $hintText"
    }

    $siteBaseUrl = Resolve-PrimarySiteBaseUrl
    $loginUrl = "$siteBaseUrl/api/auth_api.php?action=login"
    $noticeUrl = "$siteBaseUrl/api/notifications_api.php?action=deployNotice"
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

    Write-Host "Step 4.5/5: send owner deploy notification"
    $loginResponse = Invoke-SiteFormJsonRequest `
        -Uri $loginUrl `
        -Body @{
            studentNumber = [string]$liveCredentials.StudentNumber
            password      = [string]$liveCredentials.Password
        } `
        -Label "Owner login for deploy notification" `
        -Session $session
    if (-not [bool]$loginResponse.success -or -not [bool]$loginResponse.loggedIn) {
        throw "Owner login for deploy notification did not complete successfully."
    }

    $noticeResponse = Invoke-SiteFormJsonRequest `
        -Uri $noticeUrl `
        -Body @{
            version    = [string]$Version
            deployedAt = [string]$DeployedAt
            branch     = [string]$Branch
            deployHead = [string]$DeployHead
        } `
        -Label "Owner deploy notification" `
        -Session $session
    if (-not [bool]$noticeResponse.success) {
        $errorMessage = [string]$noticeResponse.error
        if ([string]::IsNullOrWhiteSpace($errorMessage)) {
            $errorMessage = "unknown error"
        }
        throw "Owner deploy notification was rejected. $errorMessage"
    }

    return [PSCustomObject]@{
        Status         = "completed"
        StartedAt      = $started
        FinishedAt     = Get-IsoNow
        SiteBaseUrl    = $siteBaseUrl
        NotificationId = [string]$noticeResponse.notification.id
        Version        = [string]$Version
        Message        = [string]$noticeResponse.message
    }
}

function Get-OwnerDeployNoticeSkipResult([string]$status, [string]$message, [string]$version = "") {
    $started = Get-IsoNow
    if (-not [string]::IsNullOrWhiteSpace($message)) {
        Write-Host $message
    }

    return [PSCustomObject]@{
        Status         = $status
        StartedAt      = $started
        FinishedAt     = Get-IsoNow
        SiteBaseUrl    = ""
        NotificationId = ""
        Version        = [string]$version
        Message        = [string]$message
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
$remoteStorageInfo = [PSCustomObject]@{
    Status       = "not-run"
    StartedAt    = ""
    FinishedAt   = ""
    RemotePath   = ""
    SnapshotPath = ""
    ActivePath   = ""
    LatestPath   = ""
    FileCount    = 0
    Bytes        = [int64]0
    ReusedFiles  = 0
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
    Notes             = @()
}
$githubPlanInfo = $null
$deployStateInfo = [PSCustomObject]@{
    Status     = "not-updated"
    Path       = ""
    Head       = ""
    Branch     = ""
    FinishedAt = ""
    GitHubHead = ""
    GitHubStatus = ""
}
$ownerNoticeInfo = [PSCustomObject]@{
    Status         = "not-run"
    StartedAt      = ""
    FinishedAt     = ""
    SiteBaseUrl    = ""
    NotificationId = ""
    Version        = ""
    Message        = ""
}
$releaseGuardInfo = [PSCustomObject]@{
    Status     = "not-run"
    StartedAt  = ""
    FinishedAt = ""
    Command    = ""
    Notes      = @()
}

$failureMessage = ""
$nonBlockingFailureMessage = ""

try {
    $remoteStorageInfo = Sync-RemoteStorageFromHost
    $pullInfo = Run-OptionalPullBeforeDeploy
    $preVersionPlan = Build-DeployPlan
    $shouldRunVersionStamp = $FullSync -or @($preVersionPlan.UploadList).Count -gt 0 -or @($preVersionPlan.DeleteList).Count -gt 0
    $versionStampInfo = Run-VersionStamp -ShouldRunVersionStamp:$shouldRunVersionStamp
    $validationInfo = Run-Validation

    $deployInfo.StartedAt = Get-IsoNow
    if ($shouldRunVersionStamp) {
        $plan = Build-DeployPlan
    } else {
        $plan = $preVersionPlan
    }
    $uploadList = @($plan.UploadList)
    $deleteList = @($plan.DeleteList)
    if ($DryRun -and $versionStampInfo.PSObject.Properties["ChangedFiles"] -ne $null) {
        $previewUploadSet = New-Object 'System.Collections.Generic.HashSet[string]' ([System.StringComparer]::Ordinal)
        foreach ($relative in @($uploadList)) {
            if (-not [string]::IsNullOrWhiteSpace([string]$relative)) {
                [void]$previewUploadSet.Add([string]$relative)
            }
        }
        foreach ($relative in @($versionStampInfo.ChangedFiles)) {
            $normalized = [string]$relative
            if ([string]::IsNullOrWhiteSpace($normalized)) {
                continue
            }
            if (Test-ProtectedPublicHtmlRelativePath -relative $normalized) {
                continue
            }
            [void]$previewUploadSet.Add($normalized)
        }

        $previewDeleteSet = New-Object 'System.Collections.Generic.HashSet[string]' ([System.StringComparer]::Ordinal)
        foreach ($relative in @($deleteList)) {
            $normalized = [string]$relative
            if ([string]::IsNullOrWhiteSpace($normalized)) {
                continue
            }
            if ($previewUploadSet.Contains($normalized)) {
                continue
            }
            [void]$previewDeleteSet.Add($normalized)
        }

        $uploadList = @($previewUploadSet) | Sort-Object
        $deleteList = @($previewDeleteSet) | Sort-Object
        if (@($versionStampInfo.ChangedFiles).Count -gt 0) {
            $dryRunNotes = @($plan.Notes)
            $dryRunNotes += "Dry run preview included $(@($versionStampInfo.ChangedFiles).Count) file(s) that the real PWA version stamp would rewrite before deploy."
            $plan | Add-Member -NotePropertyName Notes -NotePropertyValue $dryRunNotes -Force
        }
    }
    $deploySourceHead = ""
    if ($null -ne $plan.PSObject.Properties["SourceHead"]) {
        $deploySourceHead = [string]$plan.SourceHead
    }
    $deploySourceBranch = Run-GitSingle -GitArgs @("rev-parse", "--abbrev-ref", "HEAD")

    $deployInfo.Mode = [string]$plan.Mode
    $deployInfo.UploadCount = $uploadList.Count
    $deployInfo.DeleteCount = $deleteList.Count
    $deployInfo.EstimatedUploadBytes = Get-FileBytesFromRelativeList -rootPath $localRoot -relativeList $uploadList
    $deployInfo.Notes = @($plan.Notes)
    $githubPlanInfo = Build-GitHubSyncPlan -upstream (Try-GetUpstreamBranch)

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
            Invoke-CurlCommand `
                -Arguments @("--silent", "--show-error", "--user", $credentials, "--quote", "RMD $remotePath/.vscode", "ftp://$($config.host)/") `
                -Operation "Delete remote .vscode directory" | Out-Null
        }
    }

    $deployInfo.Status = "completed"
    $deployInfo.FinishedAt = Get-IsoNow

    $verificationInfo = Run-PostDeployVerification

    if (-not $DryRun -and -not [string]::IsNullOrWhiteSpace($deploySourceHead)) {
        $statePath = Write-HostDeployState `
            -head $deploySourceHead `
            -branch $deploySourceBranch `
            -finishedAt ([string]$deployInfo.FinishedAt) `
            -gitHubStatus "pending"
        $deployStateInfo.Status = "updated-pending-github"
        $deployStateInfo.Path = $statePath
        $deployStateInfo.Head = $deploySourceHead
        $deployStateInfo.Branch = $deploySourceBranch
        $deployStateInfo.FinishedAt = [string]$deployInfo.FinishedAt
        $deployStateInfo.GitHubStatus = "pending"
        Write-HostDeployManifest | Out-Null
    }

    if ($SkipOwnerDeployNotification) {
        $ownerNoticeInfo = Get-OwnerDeployNoticeSkipResult `
            -status "skipped-explicit" `
            -message "Step 4.5/5 skipped by explicit -SkipOwnerDeployNotification override." `
            -version ([string]$versionStampInfo.Version)
    } elseif ($uploadList.Count -eq 0 -and $deleteList.Count -eq 0) {
        $ownerNoticeInfo = Get-OwnerDeployNoticeSkipResult `
            -status "skipped-no-host-delta" `
            -message "Step 4.5/5 skipped because no public_html delta was deployed to host." `
            -version ([string]$versionStampInfo.Version)
    } else {
        $ownerNoticeInfo = Send-OwnerDeployNotice `
            -Version ([string]$versionStampInfo.Version) `
            -DeployedAt ([string]$deployInfo.FinishedAt) `
            -Branch ([string]$deploySourceBranch) `
            -DeployHead ([string]$deploySourceHead)
    }

    $githubSyncInfo = Sync-GitHubFromLaptop -GitHubPlan $githubPlanInfo

    if (-not $DryRun -and -not [string]::IsNullOrWhiteSpace($deploySourceHead)) {
        $gitHubHead = ""
        if ($githubSyncInfo.HeadCommit -ne $null -and -not [string]::IsNullOrWhiteSpace([string]$githubSyncInfo.HeadCommit.Hash)) {
            $gitHubHead = [string]$githubSyncInfo.HeadCommit.Hash
        }
        $statePath = Write-HostDeployState `
            -head $deploySourceHead `
            -branch $deploySourceBranch `
            -finishedAt ([string]$deployInfo.FinishedAt) `
            -gitHubHead $gitHubHead `
            -gitHubStatus ([string]$githubSyncInfo.Status)
        $deployStateInfo.Status = "updated"
        $deployStateInfo.Path = $statePath
        $deployStateInfo.Head = $deploySourceHead
        $deployStateInfo.Branch = $deploySourceBranch
        $deployStateInfo.FinishedAt = [string]$deployInfo.FinishedAt
        $deployStateInfo.GitHubHead = $gitHubHead
        $deployStateInfo.GitHubStatus = [string]$githubSyncInfo.Status
        Write-HostDeployManifest | Out-Null
    } elseif (-not $DryRun) {
        $deployStateInfo.Status = "skipped-no-head"
    }

    $releaseGuardInfo = Invoke-ReleaseCompletionGuard `
        -DeployInfo $deployInfo `
        -VerificationInfo $verificationInfo `
        -OwnerNoticeInfo $ownerNoticeInfo `
        -GitHubSyncInfo $githubSyncInfo
} catch {
    $failureMessage = $_.Exception.Message
    if ($null -ne $_.InvocationInfo -and -not [string]::IsNullOrWhiteSpace([string]$_.InvocationInfo.PositionMessage)) {
        $failureMessage += " | " + ([string]$_.InvocationInfo.PositionMessage).Trim()
    }
    if (-not [string]::IsNullOrWhiteSpace([string]$_.ScriptStackTrace)) {
        $stackPreview = (([string]$_.ScriptStackTrace) -split "(\r?\n)+" | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | Select-Object -First 3) -join " <- "
        if (-not [string]::IsNullOrWhiteSpace($stackPreview)) {
            $failureMessage += " | stack: $stackPreview"
        }
    }
    $hostDeploySucceeded = ([string]$deployInfo.Status) -eq "completed"
    $liveVerified = ([string]$verificationInfo.Status) -eq "completed"
    if (-not $remoteStorageInfo.FinishedAt) {
        $remoteStorageInfo.FinishedAt = Get-IsoNow
    }
    if (-not $deployInfo.FinishedAt) {
        $deployInfo.FinishedAt = Get-IsoNow
    }
    if (-not $verificationInfo.FinishedAt) {
        $verificationInfo.FinishedAt = Get-IsoNow
    }
    if (-not $githubSyncInfo.FinishedAt) {
        $githubSyncInfo.FinishedAt = Get-IsoNow
    }
    if (-not $ownerNoticeInfo.FinishedAt) {
        $ownerNoticeInfo.FinishedAt = Get-IsoNow
    }
    if (-not $deployStateInfo.FinishedAt) {
        $deployStateInfo.FinishedAt = Get-IsoNow
    }
    if ($deployStateInfo.Status -eq "updated-pending-github" -and -not [string]::IsNullOrWhiteSpace($deployStateInfo.Path) -and -not [string]::IsNullOrWhiteSpace($deployStateInfo.Head)) {
        $statePath = Write-HostDeployState `
            -head ([string]$deployStateInfo.Head) `
            -branch ([string]$deployStateInfo.Branch) `
            -finishedAt ([string]$deployStateInfo.FinishedAt) `
            -gitHubHead ([string]$deployStateInfo.GitHubHead) `
            -gitHubStatus "failed"
        $deployStateInfo.Path = $statePath
        $deployStateInfo.Status = "updated-github-failed"
        $deployStateInfo.GitHubStatus = "failed"
    }
    if (
        $hostDeploySucceeded `
        -and $liveVerified `
        -and -not [string]::IsNullOrWhiteSpace($failureMessage) `
        -and $failureMessage.ToLowerInvariant().Contains("github sync")
    ) {
        $nonBlockingFailureMessage = $failureMessage
        $failureMessage = ""
        $githubSyncInfo.Status = "failed-after-live-success"
        $githubNotes = @($githubSyncInfo.Notes)
        $githubNotes += "Host deploy and live health-check succeeded; GitHub sync failed and should be retried separately."
        $githubSyncInfo | Add-Member -NotePropertyName Notes -NotePropertyValue $githubNotes -Force
    }
} finally {
    $runFinishedAt = Get-IsoNow

    Write-Host "Deploy completed to $remotePath"
    Write-Host "Deployment report (host-storage-first, laptop-code deploy):"
    Write-Host " - Run started at: $($runStartedAt.ToString('yyyy-MM-ddTHH:mm:sszzz'))"
    Write-Host " - Proxy endpoint target: $($script:NetworkPolicy.ProxyEndpoint)"
    Write-Host " - Proxy env detected: $($script:NetworkPolicy.ProxyConfigured)"
    Write-Host " - Low-bandwidth mode enabled: $($script:NetworkPolicy.LowBandwidthEnabled)"
    Write-Host " - Effective network path (global/host/health/github): $($script:NetworkPolicy.GlobalPath)/$($script:NetworkPolicy.HostDeployPath)/$($script:NetworkPolicy.HealthCheckPath)/$($script:NetworkPolicy.GitHubPath)"

    Write-Host " - Remote storage sync status: $($remoteStorageInfo.Status)"
    if (-not [string]::IsNullOrWhiteSpace($remoteStorageInfo.RemotePath)) {
        Write-Host " - Remote storage source: $($remoteStorageInfo.RemotePath)"
    }
    if (-not [string]::IsNullOrWhiteSpace($remoteStorageInfo.SnapshotPath)) {
        Write-Host " - Remote storage snapshot: $($remoteStorageInfo.SnapshotPath)"
    }
    if (-not [string]::IsNullOrWhiteSpace($remoteStorageInfo.ActivePath)) {
        Write-Host " - Active local storage mirror: $($remoteStorageInfo.ActivePath)"
    }
    if (-not [string]::IsNullOrWhiteSpace($remoteStorageInfo.LatestPath)) {
        Write-Host " - Latest local storage backup: $($remoteStorageInfo.LatestPath)"
    }
    Write-Host " - Remote storage files downloaded: $($remoteStorageInfo.FileCount)"
    Write-Host " - Remote storage files reused from last mirror: $($remoteStorageInfo.ReusedFiles)"
    Write-Host " - Remote storage bytes downloaded: $(Format-Bytes -bytes $remoteStorageInfo.Bytes)"

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

    Write-Host " - Owner deploy notification status: $($ownerNoticeInfo.Status)"
    if (-not [string]::IsNullOrWhiteSpace($ownerNoticeInfo.StartedAt)) {
        Write-Host " - Owner deploy notification started at: $($ownerNoticeInfo.StartedAt)"
    }
    if (-not [string]::IsNullOrWhiteSpace($ownerNoticeInfo.FinishedAt)) {
        Write-Host " - Owner deploy notification finished at: $($ownerNoticeInfo.FinishedAt)"
    }
    if (-not [string]::IsNullOrWhiteSpace($ownerNoticeInfo.Version)) {
        Write-Host " - Owner deploy notification version: $($ownerNoticeInfo.Version)"
    }
    if (-not [string]::IsNullOrWhiteSpace($ownerNoticeInfo.NotificationId)) {
        Write-Host " - Owner deploy notification id: $($ownerNoticeInfo.NotificationId)"
    }
    if (-not [string]::IsNullOrWhiteSpace($ownerNoticeInfo.SiteBaseUrl)) {
        Write-Host " - Owner deploy notification site: $($ownerNoticeInfo.SiteBaseUrl)"
    }
    if (-not [string]::IsNullOrWhiteSpace($ownerNoticeInfo.Message)) {
        Write-Host " - Owner deploy notification message: $($ownerNoticeInfo.Message)"
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
    foreach ($note in @($githubSyncInfo.Notes)) {
        Write-Host "   * $note"
    }
    if (-not [string]::IsNullOrWhiteSpace($nonBlockingFailureMessage)) {
        Write-Warning "Live deploy succeeded but GitHub sync failed: $nonBlockingFailureMessage"
    }
    Write-Host " - Host deploy state record: $($deployStateInfo.Status)"
    if (-not [string]::IsNullOrWhiteSpace($deployStateInfo.Path)) {
        Write-Host " - Host deploy state path: $($deployStateInfo.Path)"
    }
    if (-not [string]::IsNullOrWhiteSpace($deployStateInfo.Head)) {
        Write-Host " - Last successful host deploy HEAD: $($deployStateInfo.Head)"
    }
    if (-not [string]::IsNullOrWhiteSpace($deployStateInfo.GitHubHead)) {
        Write-Host " - Last recorded GitHub sync HEAD: $($deployStateInfo.GitHubHead)"
    }
    if (-not [string]::IsNullOrWhiteSpace($deployStateInfo.GitHubStatus)) {
        Write-Host " - Last recorded GitHub sync status: $($deployStateInfo.GitHubStatus)"
    }
    Write-Host " - Release completion guard status: $($releaseGuardInfo.Status)"
    if (-not [string]::IsNullOrWhiteSpace($releaseGuardInfo.StartedAt)) {
        Write-Host " - Release completion guard started at: $($releaseGuardInfo.StartedAt)"
    }
    if (-not [string]::IsNullOrWhiteSpace($releaseGuardInfo.FinishedAt)) {
        Write-Host " - Release completion guard finished at: $($releaseGuardInfo.FinishedAt)"
    }
    if (-not [string]::IsNullOrWhiteSpace($releaseGuardInfo.Command)) {
        Write-Host " - Release completion guard command: $($releaseGuardInfo.Command)"
    }
    foreach ($note in @($releaseGuardInfo.Notes)) {
        Write-Host "   * $note"
    }

    Write-Host " - Run finished at: $runFinishedAt"

    if (-not [string]::IsNullOrWhiteSpace($failureMessage)) {
        Write-Error $failureMessage
    }
}

if (-not [string]::IsNullOrWhiteSpace($failureMessage)) {
    throw $failureMessage
}
