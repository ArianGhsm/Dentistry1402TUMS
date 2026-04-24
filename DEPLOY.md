# Deploy

Canonical deploy entrypoint for this project:

- script (Windows/PowerShell): `scripts/deploy_public_html.ps1`
- script (Linux/macOS fallback): `scripts/deploy_public_html.sh`
- local web root: `public_html/`
- remote web root: `/public_html` (locked by script safety checks)

## Required default flow

Running the default deploy command now always enforces this order:

1. run local validation on the current laptop working copy
2. deploy laptop code to host
3. run post-deploy live health checks
4. commit and push the same deployed state to GitHub

The default flow does **not** pull from GitHub first. GitHub is synced after successful deploy and verification.
If validation fails, deployment stops before host upload.
If deploy or verification fails, GitHub sync is skipped.

The script also prints a deployment time report on every run, including:

- validation start/finish time
- optional pull override start/finish time (only when explicitly requested)
- deploy step start/finish time
- verification start/finish time + status
- GitHub sync start/finish time + synced HEAD

## Standard commands

Default deploy (recommended):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1
```

Dry-run preview:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -DryRun
```


Linux/macOS fallback (for environments without `powershell`):

```bash
bash ./scripts/deploy_public_html.sh
```

Dry-run on Linux/macOS:

```bash
bash ./scripts/deploy_public_html.sh --dry-run
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

Optional `git pull --ff-only` before deploy (only when explicitly requested):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -PullBeforeDeploy
```

Skip post-deploy health checks:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -SkipPostDeployVerification
```

Skip local validation (only for emergency/manual override):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -SkipValidation
```

Skip GitHub sync after a successful deploy (only for emergency/manual override):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -SkipGitHubSync
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

## Credentials resolution

Both deploy scripts can read credentials from `.vscode/sftp.json` when present.

For Linux/macOS fallback script, you can also pass credentials via environment variables:

```bash
export DEPLOY_FTP_HOST="dentistry1402tums.ir"
export DEPLOY_FTP_USER="<cpanel-user>"
export DEPLOY_FTP_PASS="<cpanel-password>"
# optional (defaults to public_html): export DEPLOY_REMOTE_PATH="public_html"
bash ./scripts/deploy_public_html.sh
```
