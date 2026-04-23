# Deploy

Canonical deploy entrypoint for this project:

- script: `scripts/deploy_public_html.ps1`
- local web root: `public_html/`
- remote web root: `/public_html` (locked by script safety checks)

## Required default flow

Running the default deploy command now always enforces this order:

1. `git pull --ff-only` from the current branch upstream
2. deploy to host
3. post-deploy live health checks

If sync fails (auth error, divergence, branch mismatch, pull failure, dirty working tree), deployment stops before any host upload/delete.

## Standard commands

Default deploy (recommended):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1
```

Dry-run preview:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -DryRun
```

Full sync under `public_html` (only when explicitly needed):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -FullSync
```

Delete remote `.vscode` after deploy if required:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -DeleteVscodeOnRemote
```

## Explicit overrides (use intentionally)

Deploy current local state without `git pull` (only when explicitly requested):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -SkipGitPull
```

Skip post-deploy health checks:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -SkipPostDeployVerification
```

Custom verification URLs:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -HealthCheckUrls "https://dentistry1402tums.ir/","https://dentistry1402tums.ir/chat/"
```

## Scope and safety

- `storage/` is not part of web deploy.
- `scripts/` is not part of web deploy.
- `.vscode/` must not remain on host.
- Do not use ad-hoc deploy paths; use this script as the single deploy entrypoint.
