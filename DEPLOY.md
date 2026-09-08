# DEPLOY — GitHub-first exact-SHA release

## Sources of truth

- Code: exact commit on `ArianGhsm/Dentistry1402TUMS`.
- Data: production `storage/` and service runtime state.
- Recovery: verified ignored laptop/server-only mirrors.

Deploy must never push to GitHub, create a commit, stamp/mutate source, upload
local data, or treat a dirty workspace as a release source.

## Sequential source task versus release

Current development is sequential: one task branch starts from an exact current
`main` SHA, runs its targeted/full relevant tests and CI, and after a clean
divergence/security review may merge its PR to `main`. Source-development tasks
do **not** deploy production. Only the resulting exact `main` SHA can enter the
separate release gate with full regression, runtime checks, verified backup,
deploy and live verification. Full rules are in `docs/DEVELOPMENT_WORKFLOW.md`.

## Prepare an immutable release workspace

```powershell
git fetch origin main
$sha = git rev-parse origin/main
powershell -ExecutionPolicy Bypass -File .\scripts\prepare_release_workspace.ps1 -ReleaseSha $sha
```

The prepared worktree must be clean and satisfy the exact repository lock,
`HEAD == ReleaseSha`, `origin/main == ReleaseSha`, and an empty Git status.

## Canonical deploy

Run from that prepared worktree:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\complete_task.ps1 -ReleaseSha <exact-sha>
```

Dry-run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -ReleaseSha <exact-sha> -DryRun
```

For an operator-facing gate that validates the child exit code and its durable
report, use:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\run_release_gate.ps1 -ReleaseSha <exact-sha> -DryRun
```

The command writes an atomic, ignored machine-readable report under the shared
ops root at `.codex-local/release-runs/<sha>/<run-id>/release-report.json`.
Treat a dry-run as successful only when its exit code is zero **and** the report
has `status: passed`, `deployPlan.complete: true`, no protected-path violations,
and `productionMutation: false`. The final console summary prints that report
path; operators must not infer completion from buffered console output alone.
The isolated local smoke uses a synthetic owner in a copied snapshot and never
uses a real owner credential. The only bootstrap exception is a queued website
`started` lifecycle event; final `succeeded` or `failed` lifecycle delivery is
still mandatory.

Broad deploy is exceptional and follows reviewed dry-run output:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -ReleaseSha <exact-sha> -FullSync -AllowLargeDeploy
```

`-PullBeforeDeploy`, `-PathScope`, `-SkipGitHubSync`, and deploy-time version
stamping are incompatible with immutable releases. Cache/PWA version changes
must be prepared, tested and committed before integration.

## Preserved release safety

The canonical script still performs, in order:

1. exact repository/SHA/clean-worktree verification;
2. one-way production `storage/` download;
3. verified snapshot and laptop/server-only mirror promotion;
4. local deterministic validation;
5. manifest-based delta deploy to `/public_html`;
6. live health and multi-cohort smoke checks;
7. durable owner lifecycle notifications when a real host delta exists;
8. host manifest/state recording with the exact GitHub SHA;
9. freshness/coherence completion guard.

Snapshot promotion remains conditional on JSON/schema/size/hash validation and
double-read stability of critical stores. A malformed/transitional snapshot
never becomes `latest`. Production data, `.env`, sessions, locks, backups,
cache and runtime uploads are protected even during full sync.

Verified website-storage snapshots have bounded retention: after a successful
verified promotion, the deploy keeps the five newest complete snapshots. Partial,
malformed, or otherwise ineligible directories are never promoted or removed by
this cleanup and remain available for forensic inspection. This policy applies
only to the website-storage mirror under `.codex-local/remote-storage`; encrypted
VPS runtime backups keep their separate disaster-recovery retention policy.

Normal deploy guards remain 80 uploads and 25 deletes. Bypass requires explicit
`-AllowLargeDeploy` or `-FullSync` after review. FTP retry, rollback inputs,
notifications and live verification remain mandatory for an integrated release.

## Runtime services

`bot_runtime/` is Git-canonical source. Production service releases remain
under `/opt/integrated-dent` and use server-only environment/state. Runtime
changes require the relevant deployment script plus actual Telegram/Bale
health, NRestarts, logs, site API, scheduler/notifier and smoke verification.

## Manual audit

```powershell
python .\scripts\check_host_deploy_freshness.py --metadata-root <shared-ops-root> --public-root .\public_html
python .\scripts\verify_release_source.py --sha <exact-sha>
```

Ad-hoc FTP upload and post-deploy GitHub synchronization are prohibited.
