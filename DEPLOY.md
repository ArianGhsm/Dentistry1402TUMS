# Deploy

Canonical deploy entrypoints:
- PowerShell: `scripts/deploy_public_html.ps1`
- Linux/macOS fallback: `scripts/deploy_public_html.sh`

## Default Deploy Flow (Laptop-First)

`deploy` means this exact order by default:
1. local validation on current laptop code
2. deploy laptop code to host (`/public_html`)
3. post-deploy live health checks
4. GitHub sync (commit/push same final deployed state)

Important defaults:
- No pre-deploy `git pull` by default.
- If validation fails: stop before host deploy.
- If deploy or live verification fails: stop before GitHub sync.
- If GitHub push fails after successful deploy: host state stays deployed and push failure is reported.
- Deploy script records last successful host-synced `HEAD` in `.codex-local/deploy/host_last_deploy.json` and includes `public_html` commit delta since that point to prevent false `No local delta` skips.

## Standard Commands

Default deploy:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1
```

Dry run:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -DryRun
```

Full sync under `public_html` (only when explicitly needed):
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -FullSync
```

Linux/macOS fallback:
```bash
bash ./scripts/deploy_public_html.sh
```

## Explicit Overrides

Optional pre-deploy pull (only when explicitly requested):
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -PullBeforeDeploy
```

Skip local validation (emergency/manual override):
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -SkipValidation
```

Skip post-deploy verification (emergency/manual override):
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -SkipPostDeployVerification
```

Skip GitHub sync (emergency/manual override):
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -SkipGitHubSync
```

## Scope/Safety
- Deploy is locked to remote path `/public_html`.
- Runtime state must stay under `server-only/` (`storage/`, `tmp/`, `sessions/`, `backups/`, `secrets/`) and must not be committed.
- Legacy root `storage/`/`tmp/` paths are deprecated and should remain untracked.
- `storage/`, `server-only/`, and `scripts/` are not web-deployed.
- Prefer this canonical script; avoid ad-hoc deploy commands.
