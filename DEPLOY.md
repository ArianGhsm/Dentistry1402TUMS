# DEPLOY — GitHub-first exact-SHA release

## Sources of truth

- **Code:** exact commit on `ArianGhsm/Dentistry1402TUMS`.
- **Website production data:** `/srv/dentistry1402/shared/storage` on the Iran VPS.
- **Website secrets/runtime:** `/srv/dentistry1402/shared/server-only` on the Iran VPS.
- **Telegram/Bale runtime state:** server-only state under `/var/lib/integrated-dent` and `/etc/integrated-dent`.
- **Recovery:** immutable previous code releases plus verified VPS/runtime backups.
- **Retention:** each successful VPS release keeps only the five newest verified
  timestamped `dent-site-data-*` backups; forensic/manual backup namespaces are
  not part of this automatic cleanup.

The website no longer deploys to cPanel/FTP. The retired main-site cPanel/FTP deployer scripts have been removed from the working tree; Git history is forensic evidence only and must not be restored as an operational release path.

Deploy must never push to GitHub, create a commit, stamp/mutate source, upload local production data, or treat a dirty workspace as a release source.

## Development versus release

Development is GitHub-first. A task branch starts from an exact current `main` SHA, runs relevant tests/CI, and merges only after review. Feature branches do **not** deploy production.

Only an exact merged `main` SHA can enter the production release gate. A docs/tests/workflow-only change with no production code change does not require an empty production deploy; it still requires CI plus live read-only production verification when it changes deployment tooling.

## Prepare an immutable release workspace

```powershell
git fetch origin main
$sha = git rev-parse origin/main
powershell -ExecutionPolicy Bypass -File .\scripts\prepare_release_workspace.ps1 -ReleaseSha $sha
```

The release worktree must satisfy:

- repository identity exactly `ArianGhsm/Dentistry1402TUMS`;
- `HEAD == ReleaseSha`;
- `origin/main == ReleaseSha`;
- empty Git status.

## Canonical website release gate

Dry-run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\run_release_gate.ps1 -ReleaseSha <exact-sha> -DryRun
```

Deploy:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\run_release_gate.ps1 -ReleaseSha <exact-sha> -Deploy
```

Task-completion alias:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\complete_task.ps1 -ReleaseSha <exact-sha>
```

`complete_task.ps1` enters the same validated release gate; it is not a separate deployment path.

The gate calls `scripts/deploy_site_vps.ps1`. Server connection data remains ignored/server-only in `bot_runtime/.codex-local/iran-server.json` (or an explicitly supplied absolute `-ServerConfig`). SSH uses the pinned known-hosts file and the configured identity. `-ConfirmTargetHost` is optional defense-in-depth and, when supplied, must exactly match the configured VPS.

## VPS website layout

Production website layout is fixed:

```text
/srv/dentistry1402/
  current -> releases/<exact-git-sha>
  releases/<exact-git-sha>/
    .release-sha
    public_html/
  shared/
    storage/       # mutable production data; never replaced by code deploy
    server-only/   # secrets/sessions/runtime; never replaced by code deploy
```

Nginx serves `/srv/dentistry1402/current/public_html`. The dedicated PHP-FPM pool binds `DENT_STORAGE_ROOT`, `DENT_SERVER_ONLY_ROOT`, `DENT_SESSION_SAVE_PATH`, and `DENT_ENV_FILE` to the shared VPS paths. A website code release must therefore contain code only.

## Required release order

The canonical VPS deployer is fail-closed and performs:

1. exact repository/SHA/clean-worktree verification;
2. pinned-SSH VPS preflight;
3. verification that Nginx, PHP-FPM, Telegram and Bale services are active;
4. JSON parse validation of shared website storage without rewriting it;
5. a complete dry-run plan and durable report before any mutation;
6. code-only `public_html` bundle creation and SHA-256 verification;
7. immutable staging under `/srv/dentistry1402/releases/<sha>`;
8. PHP/JavaScript syntax checks and secret/database-file exclusion on staged code;
9. verified rollback-pointer backup before activation;
10. atomic `current` symlink switch;
11. live website, protected-path, Telegram, Bale and storage validation;
12. automatic rollback to the previous immutable release if post-activation verification fails;
13. terminal lifecycle notification only after the final live result is known;
14. durable `release-report.json` recording exact SHA, plan and mutation state.

The deployer must never copy, delete, synchronize, or replace `/srv/dentistry1402/shared/storage` or `/srv/dentistry1402/shared/server-only`.

If the exact SHA is already active, deploy mode becomes **verification-only** and performs zero production mutation.

## Durable report contract

Reports are written under the shared ignored ops root:

```text
.codex-local/release-runs/<sha>/<run-id>/release-report.json
```

A dry-run passes only when:

- process succeeds;
- `status == "passed"`;
- `mode == "dry-run"`;
- `releaseSha` equals the requested SHA;
- `deployPlan.complete == true`;
- `protectedPathViolations` is empty;
- `productionMutation == false`.

A deploy passes only after remote live verification and successful terminal lifecycle handling.

## Runtime services

`bot_runtime/` remains Git-canonical source for Telegram/Bale. Production bot code releases stay under `/opt/integrated-dent`; their credentials, SQLite state, logs and service configuration stay outside Git.

Bot/runtime changes use the relevant scripts under `bot_runtime/scripts/`, verified runtime backups, and actual post-deploy health checks. Website, Telegram and Bale must continue to satisfy their shared API/auth/payment contracts after an integrated product release.

## Prohibited paths

- cPanel/FTP as production deploy target;
- ad-hoc upload directly into the active website directory;
- direct edits inside `/srv/dentistry1402/current`;
- overwriting shared production `storage` or `server-only` from Git/laptop snapshots;
- force-push for release work;
- post-deploy GitHub synchronization;
- claiming release success from HTTP 200 alone without the full health/protection checks and durable report.
