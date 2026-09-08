# Development workflow

## Canonical model

Code starts from an immutable GitHub commit in `ArianGhsm/Dentistry1402TUMS`. Production storage remains the canonical data source. Deploy/recovery workspaces are disposable views of a commit; they are not independent sources of code.

The repository is agent-agnostic. ChatGPT, Codex, or another capable agent may continue work from the same SHA, contracts, tests, and documents.

## Layered gates

1. A feature branch starts at the declared immutable `PARALLEL_BASE_SHA`.
2. Its worker runs targeted unit/domain/contract tests and deterministic static checks.
3. GitHub CI must pass without production credentials.
4. Integration locks exact feature-head SHAs, audits/reconciles them, and runs the full deterministic suite.
5. Cross-surface/central wiring is integrated after domain contracts are coherent.
6. Codex performs environment-dependent runtime, concurrency, restart, service, Telegram/Bale, provider/network and log checks.
7. A verified production-data backup is taken.
8. The exact integrated GitHub SHA is deployed through the canonical script.
9. Live health, logs, smoke tests, notifications, data invariants and rollback readiness are verified.

Feature-branch work never substitutes for integrated-release/runtime gates.

## Branch policy

- `main` is the integration/release baseline. Feature workers do not write directly to it.
- Branches are agent-neutral: `feature/<scope>`.
- Every parallel worker starts from the exact immutable base and does not pull/rebase to moving `main` mid-task.
- Workers do not merge their own branch and do not deploy production.
- Integration branches are `integration/<scope>` and may own previously frozen hotspots only for the declared integration stage.
- Shared-contract changes require explicit integration review, version discipline and tests.
- Force-overwriting `main` or feature history is forbidden.

## Definitions of Done

### Feature branch

- Requested isolated scope complete; no unrelated refactor.
- Targeted unit/domain/contract/security tests are present and executed when environment permits.
- No secret or runtime data is tracked.
- CI is green where applicable.
- Commit is reviewable and ready for integration.
- No merge to `main` and no production deploy.

### Domain integration stage

- Exact input branch heads are locked and ancestry is verified.
- Domain implementations are reconciled against canonical source-of-truth boundaries.
- Candidate contracts are reviewed and an explicit compatible contract graph is recorded.
- Missing workstreams are reported honestly and either recovered in integration or block completion.
- Cross-domain invariants and the full deterministic repository suite are green.
- Cross-surface/runtime wiring that is intentionally deferred is documented precisely.
- No production deploy.

### Final GitHub integration

- Website/API/shared application/Telegram/Bale wiring is coherent.
- Capability claims match real implementation.
- Full deterministic regression and CI pass.
- Runtime migration/deploy handoff is complete.
- The final integrated GitHub SHA may be merged to `main` only after divergence/security checks.
- Still no production deploy; runtime release remains a separate Codex/environment gate.

### Integrated production release

- Runtime-only tests pass in the real environment.
- Production backup and rollback inputs are verified.
- Workspace is a clean checkout of the exact GitHub SHA.
- Canonical deploy, live health/log/smoke, lifecycle notifications, data invariants and freshness checks pass.

## Integration-only hotspots

Feature workers may read but do not write these without explicit integration ownership:

- `AGENTS.md`, `DEPLOY.md`, `.env.example`, `.github/workflows/*`
- `scripts/run_static_checks.sh`, `scripts/complete_task.ps1`, `scripts/deploy_public_html.ps1`
- `public_html/api/bootstrap.php`, `auth_store.php`, `bot_store.php`, `bot_api.php`
- `public_html/api/notifications_store.php`, `academic_term7.php`
- `public_html/api/classops_api.php`, `classops_store.php`, `classops_persistence.php`
- `bot_runtime/dent_bot/app.py`, `runtime.py`, `site_api.py`, `state.py`
- runtime central scheduler/router, service units, deploy scripts and dependency manifests

Workers needing central wiring expose isolated modules/interfaces/tests. The appropriate integration stage owns the wiring.

## ClassOps parallel wave executed from `06042c2bf31d64d69d884528d43796c2c3b7ae5d`

The ClassOps expansion was split into eight isolated workstreams so shared hotspots stayed frozen while domain logic could proceed concurrently:

| Workstream | Branch | Stage 1 treatment |
|---|---|---|
| Audience & policy | `feature/classops-audience-policy` | integrated as pure domain |
| Destination & delivery | `feature/classops-destination-delivery` | integrated as planning-only domain |
| AI Copilot / structured draft | `feature/classops-ai-copilot` | integrated as preview-only domain |
| Tasks & requirements | `feature/classops-tasks-requirements` | branch was empty at Stage 1 lock; gap recovered explicitly in integration |
| Exams & critical ACK | `feature/classops-exams-critical-ack` | integrated as operational exam/ACK domain |
| Reminder scheduler / Saba | `feature/classops-reminder-scheduler` | integrated as pure deterministic planner |
| Tomorrow / Weekly digests | `feature/classops-digests-summaries` | integrated as deterministic projection domain |
| Cross-surface UX | `feature/classops-cross-surface-ux` | audited only in Stage 1; reserved for Stage 2 wiring |

Scheduler and digest were safe to develop concurrently only because their branches remained pure planners/projections and did not modify the central runtime scheduler, notification system or shared transport router. Their **runtime wiring remains sequential integration work** after domain reconciliation.

Stage 1 target: `integration/classops-domain-unification-v1`.
Stage 2 target: `integration/classops-final-unification-v1`.

## Source-of-truth rules for ClassOps integration

- `auth_store.php` remains identity/role authority.
- `academic_term7.php` remains official Term 7 schedule/assignment authority.
- existing notification subsystem remains notification feed/read-state authority.
- existing payment subsystem remains verified payment/order authority.
- ClassOps uses one canonical storage family; no per-platform or per-domain shadow databases.
- Telegram and Bale are thin adapters; background side effects later use one coordinator rather than duplicate schedulers.
- AI produces drafts only and cannot send/mutate directly.

## Agent reversibility

If ChatGPT integration stops:

1. Freeze the current branch and record its exact SHA.
2. Read the integration report, contracts, architecture docs and tests.
3. Codex or another agent checks out that exact SHA in a clean worktree.
4. Continue from the same contracts/test gates; do not reverse-migrate directories or reconstruct decisions from chat history.
5. Runtime deploy still follows the same exact-SHA backup/deploy/verification gates.

No agent-specific repository structure is required.
