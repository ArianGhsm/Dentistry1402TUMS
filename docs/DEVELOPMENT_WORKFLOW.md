# Development workflow

## Current workflow — sequential GitHub-first development

This section is the active development policy. Older references in historical
reports to parallel workers, `PARALLEL_BASE_SHA`, integration waves or a
separate migration chat describe completed historical work only and do not
control new tasks.

Code starts from an exact GitHub commit in `ArianGhsm/Dentistry1402TUMS`.
Production storage remains the canonical data source. Deploy/recovery workspaces
are disposable views of a commit; they are not independent sources of code.
The repository is agent-agnostic: ChatGPT, Codex or another capable agent may
continue from the same SHA, contracts, tests and documents.

New development is sequential:

1. verify the exact repository identity and fetch current `origin/main`;
2. record the immutable starting `main` SHA;
3. create one task branch from that SHA;
4. read `AGENTS.md`, relevant nested instructions/contracts and existing
   implementation benchmarks before the first write;
5. implement the complete requested scope on that one branch;
6. run targeted tests, deterministic regressions and repository gates;
7. push one reviewable branch and open one PR to `main`;
8. follow GitHub CI to completion and repair failures caused by the task;
9. immediately before merge, verify the branch is not unexpectedly behind or
   divergent from current `main`;
10. after green CI and a clean divergence/security review, the task owner/agent
    may merge that PR to `main`;
11. record the exact resulting `main` SHA;
12. production release remains a separate exact-SHA backup/deploy/live-
    verification operation.

Do not create multiple workers or feature branches for one task, do not run an
integration wave after the task branch, do not force-push, and do not write
source directly to `main`. If `main` advances before the task branch is created,
use the latest `main`. If it advances during work, inspect the new commits before
merge; reconcile only when necessary and never hide a risky conflict.

## Layered gates

### Repository implementation gate

- exact repository and immutable starting SHA verified;
- relevant instructions/contracts read before writes;
- requested scope complete with no unrelated refactor;
- no secrets or runtime data tracked;
- targeted unit/domain/contract/security/UX tests pass;
- deterministic repository/static checks pass where relevant;
- GitHub CI is green;
- PR is reviewable, mergeable and not unexpectedly divergent;
- resulting `main` SHA is recorded after merge.

### Production release gate

Merging to `main` is not a production deployment. The exact merged `main` SHA
must separately pass:

1. clean immutable release-workspace verification;
2. runtime/environment checks that repository CI cannot prove;
3. verified production-data backup and rollback inputs;
4. canonical exact-SHA deploy;
5. actual website/Telegram/Bale/service/scheduler health checks as applicable;
6. post-deploy data, log, notification and freshness invariants.

Feature/task work never substitutes for the production release gate.

## Branch policy

- `main` is the release baseline and is not edited directly for normal feature
  work.
- One task uses one agent-neutral branch such as `feature/<scope>`,
  `fix/<scope>` or `docs/<scope>`.
- The branch starts from one recorded `origin/main` SHA.
- Parallel workers/branches and integration-wave branches are not part of the
  current workflow.
- Force-overwriting `main` or task-branch history is forbidden.
- Shared-contract semantic changes still require explicit compatibility/version
  review and producer/consumer tests.
- A task may merge its own PR only after the required CI, divergence and
  security checks are green. It still may not deploy production as part of the
  source-development task unless the user separately requests the release.

## Definitions of Done

### Sequential source task

- requested scope complete on one branch;
- relevant product/source-of-truth boundaries preserved;
- affected Telegram/Bale/site surfaces remain coherent;
- targeted and regression tests are present and green;
- no secret/runtime state is committed;
- GitHub CI is green;
- no unexpected moving-main divergence remains;
- PR is merged and exact final `main` SHA is reported;
- production deploy is explicitly reported as not performed unless a separate
  release instruction was given.

### Integrated production release

- runtime-only tests pass in the real environment;
- production backup and rollback inputs are verified;
- workspace is a clean checkout of the exact GitHub `main` SHA;
- canonical deploy, live health/log/smoke, lifecycle notifications, data
  invariants and freshness checks pass.

## Shared/high-risk files

The old workflow called these “integration-only hotspots.” Under the current
sequential workflow they are no longer reserved for a separate integration
worker, but they remain high-risk shared files and may be changed only when the
current task explicitly requires them, after reading their contracts and adding
appropriate regression coverage:

- `AGENTS.md`, `DEPLOY.md`, `.env.example`, `.github/workflows/*`
- `scripts/run_static_checks.sh`, `scripts/complete_task.ps1`,
  `scripts/deploy_public_html.ps1`
- `public_html/api/bootstrap.php`, `auth_store.php`, `bot_store.php`, `bot_api.php`
- `public_html/api/notifications_store.php`, `academic_term7.php`
- `public_html/api/classops_api.php`, `classops_store.php`,
  `classops_persistence.php`
- `bot_runtime/dent_bot/app.py`, `runtime.py`, `site_api.py`, `state.py`
- runtime central scheduler/router, service units, deploy scripts and dependency
  manifests.

Do not use the absence of a separate integration stage as permission for an
unrelated refactor of these files.

## ClassOps source-of-truth rules

These rules remain active regardless of workflow shape:

- `auth_store.php` remains identity/role authority;
- `academic_term7.php` remains official Term 7 schedule/assignment authority;
- the existing notification subsystem remains notification feed/read-state
  authority;
- the existing payment subsystem remains verified payment/order authority;
- ClassOps uses one canonical storage family; no per-platform or per-domain
  shadow database;
- Telegram and Bale remain thin adapters over shared application logic;
- background external side effects use one coordinator rather than duplicate
  schedulers;
- AI produces drafts only and cannot send or mutate directly.

## Historical appendix — completed ClassOps parallel wave

The following records are retained only as provenance for the already-completed
ClassOps Stage 1/Stage 2 work. They are **not** instructions for future
development.

Historical frozen base:
`06042c2bf31d64d69d884528d43796c2c3b7ae5d`

Historical workstreams:

| Workstream | Historical branch | Stage 1 treatment |
|---|---|---|
| Audience & policy | `feature/classops-audience-policy` | integrated as pure domain |
| Destination & delivery | `feature/classops-destination-delivery` | integrated as planning-only domain |
| AI Copilot / structured draft | `feature/classops-ai-copilot` | integrated as preview-only domain |
| Tasks & requirements | `feature/classops-tasks-requirements` | branch was empty; gap recovered in Stage 1 |
| Exams & critical ACK | `feature/classops-exams-critical-ack` | integrated as operational exam/ACK domain |
| Reminder scheduler / Saba | `feature/classops-reminder-scheduler` | integrated as pure deterministic planner |
| Tomorrow / Weekly digests | `feature/classops-digests-summaries` | integrated as deterministic projection domain |
| Cross-surface UX | `feature/classops-cross-surface-ux` | audited in Stage 1; wired in Stage 2 |

Historical integration branches were
`integration/classops-domain-unification-v1` and
`integration/classops-final-unification-v1`. Their reports remain under
`docs/classops/` and should not be rewritten merely to make their historical
wording match the current sequential policy.

## Agent reversibility

If one agent stops mid-task:

1. freeze and report the current task branch and exact SHA;
2. the next capable agent reads the same instructions/contracts/tests;
3. continue that same branch when safe rather than spawning a parallel worker;
4. preserve exact-SHA source and production-data boundaries;
5. keep production deployment as the same separate release gate.

No agent-specific repository structure is required.
