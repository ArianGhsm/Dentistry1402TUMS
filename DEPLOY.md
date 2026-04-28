# Deploy

## File Identity
- What: Canonical deploy runbook and command contract.
- Where: Repo root.
- Role: Defines default deploy order, failure behavior, and explicit overrides.
- Controls: how code reaches host and when GitHub sync occurs.
- Dependencies:
  - `scripts/deploy_public_html.ps1`
  - `scripts/deploy_public_html.sh`
  - `.codex-local/deploy/host_last_deploy.json`
- Read when:
  - before deploy
  - when debugging deploy/state mismatch

## Canonical Entrypoints
- PowerShell: `scripts/deploy_public_html.ps1`
- Linux/macOS fallback: `scripts/deploy_public_html.sh`

## Default Deploy Flow (Laptop-First)
`deploy` means this exact order:
1. local validation on current laptop code
2. deploy laptop code to host (`/public_html`)
3. post-deploy live health checks
4. GitHub sync (commit/push same final deployed state)

## Default Safety Behavior
- No pre-deploy `git pull` by default.
- If validation fails: stop before host deploy.
- If deploy or live verification fails: stop before GitHub sync.
- If GitHub push fails after successful deploy: host remains deployed; push failure is reported.
- Script records last successful host-synced `HEAD` in `.codex-local/deploy/host_last_deploy.json` and uses `public_html` commit delta since that point to avoid false `No local delta` skips.

## Standard Commands
Default deploy:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1
```

Dry run:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -DryRun
```

Full sync under `public_html` (explicit need only):
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -FullSync
```

Linux/macOS fallback:
```bash
bash ./scripts/deploy_public_html.sh
```

## Explicit Overrides (Use Only Intentionally)
Optional pre-deploy pull:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -PullBeforeDeploy
```

Skip local validation:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -SkipValidation
```

Skip post-deploy verification:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -SkipPostDeployVerification
```

Skip GitHub sync:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -SkipGitHubSync
```

## Scope And Data Safety
- Deploy target is locked to `/public_html`.
- Runtime state must stay under `server-only/` (`storage/`, `tmp/`, `sessions/`, `backups/`, `secrets/`) and must not be committed.
- Legacy root `storage/` and `tmp/` paths are deprecated and should remain untracked.
- `storage/`, `server-only/`, and `scripts/` are not web-deployed.
- Use canonical script; avoid ad-hoc deploy commands.
