# Development workflow

## Canonical model

Code starts from an immutable GitHub commit in
`ArianGhsm/Dentistry1402TUMS`. Production storage remains the canonical data
source. Deploy/recovery workspaces are disposable views of a commit; they are
not independent sources of code.

The repository is agent-agnostic. ChatGPT, Codex, or another capable agent may
continue work from the same SHA, contracts, tests, and documents.

## Layered gates

1. A feature branch starts at the declared `PARALLEL_BASE_SHA`.
2. Its worker runs targeted unit/domain/contract tests and deterministic static checks.
3. GitHub CI must pass without production credentials.
4. Integration reviews and wires all completed branches, then runs the full deterministic suite.
5. Codex performs environment-dependent runtime, concurrency, restart, service, Telegram/Bale, and log checks.
6. A verified production-data backup is taken.
7. The exact integrated GitHub SHA is deployed through the canonical script.
8. Live health, logs, smoke tests, notifications, and rollback readiness are verified.

This reduces duplicate release work on branches; it does not remove any release assurance.

## Branch policy

- `main` is the integration/release baseline. Feature workers do not write directly to it.
- Branches are agent-neutral: `feature/<scope>`.
- Every worker starts from the exact immutable parallel base; it must not pull/rebase to a moving `main` mid-task.
- A worker does not merge its own branch and does not deploy production.
- Shared-contract changes require a documented change request or failing contract test and an integration-stage decision.

## Definitions of Done

### Feature branch

- Requested scope complete; no unrelated refactor.
- Targeted unit/domain/contract tests and static checks pass.
- No secret or runtime data is tracked.
- CI is green where applicable.
- Commit is reviewable and ready for integration.
- No merge and no production deploy.

### Integrated release

- Required branches are audited, integrated, and centrally wired.
- Frozen contracts and full deterministic regression suite pass.
- Runtime-only tests pass in the real environment.
- Production backup and rollback inputs are verified.
- Workspace is a clean checkout of the exact GitHub SHA.
- Canonical deploy, live health/log/smoke, lifecycle notifications, and freshness checks pass.

## Integration-only hotspots

Future workers may read but must not write these without explicit integration ownership:

- `AGENTS.md`, `DEPLOY.md`, `.env.example`, `.github/workflows/*`
- `scripts/run_static_checks.sh`, `scripts/complete_task.ps1`, `scripts/deploy_public_html.ps1`
- `public_html/api/bootstrap.php`, `auth_store.php`, `bot_store.php`, `bot_api.php`
- `public_html/api/notifications_store.php`, `academic_term7.php`
- `public_html/api/classops_api.php`, `classops_store.php`, `classops_persistence.php`
- `bot_runtime/dent_bot/app.py`, `runtime.py`, `site_api.py`, `state.py`
- runtime central scheduler/router, service units, deploy scripts, and dependency manifests

Workers needing central wiring expose a module/interface and tests. Integration owns the wiring change.

## Proposed balanced workstreams

These are plans only; no feature branch is created by this migration. Workload scores combine code volume, business logic, tests, integrations, and security/edge cases.

| Scope / branch | Owned paths | Read-only / forbidden paths | Dependencies and contracts | Test scope | Workload |
|---|---|---|---|---|---:|
| Audience & policy / `feature/classops-audience-policy` | new audience modules, schemas, domain tests | all integration-only hotspots; no transport/payment/Term7 rewrite | `classops-v1`, canonical student identity | resolver policy, cohort isolation, snapshots, authorization | 8.6 |
| Destination & delivery / `feature/classops-destination-delivery` | new destination/delivery modules and adapter-facing tests | central runtime router/site API and existing notification store are integration-only | delivery placeholder, notification boundary, service auth | idempotency, retry plans, destination isolation, adapter contracts | 9.1 |
| Tasks & requirements / `feature/classops-tasks-requirements` | new task/requirement domain modules and tests | core ClassOps, Term7, transports | structured draft, identity and audience contracts | lifecycle, submissions/requirements calculations, edge cases | 8.9 |
| Exams & critical ACK / `feature/classops-exams-critical-ack` | new exam/ACK domain modules and tests | core ClassOps, payments, notification wiring | core item/revision/idempotency, audience/delivery interfaces | exam states, access/payment boundaries, ACK correctness | 9.2 |
| Scheduler & summaries / `feature/classops-scheduler-summaries` | new planning/summary modules and deterministic scheduler tests | central scheduler/router and notification wiring | reminder placeholder, delivery interface, Term7 read contract | time zones, retry/idempotency, digest determinism, clock tests | 8.5 |

The spread is 0.7 on a 9.2 maximum (under 8%), comfortably within the 20–25%
target. Integration order is audience/destination contracts first, then central
wiring for all modules; domain implementation can still proceed in parallel
against frozen interfaces.

## Agent reversibility

If parallel ChatGPT development stops:

1. Freeze unfinished branches and record their exact SHAs.
2. Review completed branches and select an integration checkpoint or branch SHA.
3. Codex checks out that exact SHA in a clean worktree.
4. Codex reads `AGENTS.md`, contracts, architecture docs, and tests.
5. Codex continues development and later uses the same integration/release gates.

No directory reversal, repository migration, or agent-specific tooling is required.
