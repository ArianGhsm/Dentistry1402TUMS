param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[0-9a-f]{40}$')]
    [string]$ReleaseSha,
    [string]$SourceRepository = ""
)

$ErrorActionPreference = "Stop"
$expectedRepository = "ArianGhsm/Dentistry1402TUMS"
if ([string]::IsNullOrWhiteSpace($SourceRepository)) {
    $SourceRepository = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
}
$remote = (& git -C $SourceRepository remote get-url origin).Trim()
if ($LASTEXITCODE -ne 0 -or $remote -notmatch '(?i)(?:github\.com[/:])ArianGhsm/Dentistry1402TUMS(?:\.git)?/?$') {
    throw "Repository lock failed; expected $expectedRepository."
}
& git -C $SourceRepository fetch --prune origin main
if ($LASTEXITCODE -ne 0) { throw "Could not fetch origin/main." }
$originMain = (& git -C $SourceRepository rev-parse origin/main).Trim()
if ($originMain -ne $ReleaseSha) { throw "Requested SHA is not the current origin/main." }

$commonDir = (& git -C $SourceRepository rev-parse --git-common-dir).Trim()
if (-not [IO.Path]::IsPathRooted($commonDir)) { $commonDir = Join-Path $SourceRepository $commonDir }
$repoRoot = Split-Path (Resolve-Path $commonDir).Path -Parent
$workspaceRoot = Join-Path $repoRoot ".codex-local\release-workspaces"
$workspace = Join-Path $workspaceRoot $ReleaseSha
if (Test-Path -LiteralPath $workspace) {
    throw "Release workspace already exists; inspect or remove it explicitly: $workspace"
}
New-Item -ItemType Directory -Path $workspaceRoot -Force | Out-Null
& git -C $SourceRepository worktree add --detach $workspace $ReleaseSha
if ($LASTEXITCODE -ne 0) { throw "Could not create release worktree." }

$verify = & python (Join-Path $workspace "scripts\verify_release_source.py") --root $workspace --sha $ReleaseSha
if ($LASTEXITCODE -ne 0) { throw "Prepared worktree failed source verification: $verify" }
Write-Output ([ordered]@{ repositoryFullName = $expectedRepository; releaseSha = $ReleaseSha; workspace = $workspace; verification = ($verify | ConvertFrom-Json) } | ConvertTo-Json -Depth 5)
