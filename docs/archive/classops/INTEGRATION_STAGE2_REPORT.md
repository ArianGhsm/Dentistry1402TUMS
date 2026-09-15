> **Historical record — non-normative.** This file is preserved as provenance for a completed migration/integration wave. Do not use its branch names, pre-release status, or deployment wording as current instructions. See `AGENTS.md`, `docs/DEVELOPMENT_WORKFLOW.md`, and `DEPLOY.md` for current policy.

# ClassOps Stage 2 — Final GitHub Integration Report

Date: 2026-09-08

## Decision

**GitHub integration status: READY FOR FINAL MERGE REVIEW**

**Production release status: NOT DEPLOYED / RUNTIME GATES STILL REQUIRED**

This report closes the repository-side Stage 2 integration work. It does not authorize a production deploy and does not claim runtime/provider/server health that cannot be proven inside GitHub Actions.

## Locked integration inputs

- Repository: `ArianGhsm/Dentistry1402TUMS`
- Immutable parallel base: `06042c2bf31d64d69d884528d43796c2c3b7ae5d`
- Integration branch: `integration/classops-final-unification-v1`
- Repository-side code/test candidate verified before this report-only commit: `b19644110a3c1589b71970c9d614c0c7df70e1de`
- Pull request: `#19` — `ClassOps Stage 2 final unification`

At the final divergence check, `main` still pointed to the immutable base. The integration branch was `ahead 144 / behind 0`, with the merge base equal to the declared parallel base. No moving-main rebase was used.

## Repository-side acceptance evidence

All three required GitHub checks for candidate `b19644110a3c1589b71970c9d614c0c7df70e1de` completed successfully:

1. `check` — success
2. `domain-matrix` — success
3. `static-checks` — success

The full static job additionally reported:

- PHP lint: pass
- JavaScript syntax: pass
- Persian/UTF-8 integrity: `1325` text files checked, pass
- instruction-contract audit: pass
- repository hygiene: `1773` tracked/candidate files inspected, pass
- shared-contract freeze: pass
- GitHub-first workflow contract tests: pass
- auth/session/persistence resilience: pass
- generic unit suite: `79 passed, 0 failed`
- Term 7 suite: `55` tests/checks, `0` failures
- ClassOps Foundation concurrency: `100` concurrent writers, generation `101`, no lost-update failure
- ClassOps domain graph / Stage 1 invariants / Foundation compatibility: pass
- Audience policy and strict snapshot tests: pass
- Destination/delivery contract and fail-closed tests: pass
- AI Copilot contract tests: pass
- Tasks/requirements tests: pass
- Exams/critical ACK contract and domain tests: pass
- Reminder scheduler/Saba tests: `36 checks, 0 failures`
- Digest/summary tests: pass
- signed bot integration/persistence/recovery/snapshot safety tests: pass
- payment handoff deterministic tests: pass, with no real transactions
- bot runtime deterministic suite: `224 passed, 99 subtests passed`

The Stage 2 HTTP acceptance path also passed after aligning assertions with the actual versioned ACK and Saba contracts.

## Integration bugs found and closed

The integration stage did not merely concatenate worker branches. It found and fixed cross-domain failures including:

- owner-scope shape mismatch between Stage 2 audience wiring and the pure Audience resolver;
- stale HTTP acceptance assertions that no longer matched the promoted ACK record contract;
- stale Saba acceptance terminology (`done`) that conflicted with the canonical local states (`completed` / `waived`);
- task/service state loss across benign item revisions;
- PHP numeric-string array-key coercion causing canonical student numbers to become `int` during revision state carry;
- stale bot-surface tests that still described the pre-Stage2 capability matrix instead of the promoted capability model.

Dedicated regression coverage now protects task and Saba/service state carry across revisions.

## Final architecture state

### Canonical authority boundaries

- `auth_store.php` remains identity, role, cohort and canonical account authority.
- `academic_term7.php` remains the official Term 7 schedule/assignment authority.
- the existing notification subsystem remains website notification feed/read-state authority.
- the existing payment subsystem remains payment/order authority.
- ClassOps items remain in the canonical `classops-v1` storage family; Stage 2 domain side-state stores audience snapshots, task/service state, ACK state, delivery intents, scheduler occurrences and opaque callback references without creating a second product database.

### Owner workflow

The Stage 2 management path is explicit:

`draft / AI draft -> validation -> deterministic audience resolution -> destination/reminder preview -> owner confirmation -> canonical revision -> notification/delivery scheduling`

Preview paths are zero-write. AI cannot directly mutate or send. Publication/update requires the owner-confirm path and the exact preview audience hash.

### Audience and delivery

Audience resolution and physical/logical destination resolution remain independent. Telegram and Bale are treated as separate transport lanes behind the shared application logic; raw platform identifiers are rejected from durable ClassOps domain payloads.

### Tasks / requirements

Per-student states use the promoted deterministic contract:

`pending -> submitted -> needs_revision/completed/waived`

State is revision-aware and preserved across eligible benign item revisions. Reads do not initialize mutable state.

### Exams / critical ACK

ACK is explicit-user-intent only and bound to the exact item revision and audience eligibility. Delivery/read receipts can never create ACK state. A new item revision does not inherit satisfaction from the previous revision.

### Scheduler / summaries

Exam reminder policy includes the deterministic T-3, T-1, night-before and morning-of lanes. Tomorrow Summary and Weekly Digest are deterministic projections and are wired after domain reconciliation rather than through AI.

### Saba

Saba remains a reminder-only local ClassOps feature:

- no Saba username/password is accepted or stored;
- no login automation exists;
- local state may be `pending`, `completed` or `waived`;
- `externallyVerified` remains false;
- ClassOps never claims that the action was completed in Saba itself.

### AI

The ClassOps AI credential is dedicated and separate from Voice/STT credentials. The current provider boundary is `avalai`; the model and secret are runtime configuration. Manual deterministic management remains available when AI is unconfigured. AI output is always a structured draft requiring normal validation/preview/confirmation.

## Security / data-boundary review

Repository-side checks confirm:

- no production secret is required by CI;
- `.env.example` contains names/placeholders only, not real credentials;
- ClassOps AI secret is runtime-only;
- raw Telegram/Bale identifiers are rejected from domain/durable delivery payloads;
- Saba credential material is rejected fail-closed;
- owner mutations remain CSRF-protected and revision/idempotency guarded;
- student reads/actions derive canonical identity and cohort server-side;
- audit/projection tests do not expose raw owner identity where a fingerprint/reference is sufficient;
- runtime data, sessions, backups and secrets remain outside Git.

Repository hygiene and shared-contract freeze passed on the final code candidate.

## Divergence review

Immediately before this report commit:

- `main`: `06042c2bf31d64d69d884528d43796c2c3b7ae5d`
- integration content candidate: `b19644110a3c1589b71970c9d614c0c7df70e1de`
- relation: `ahead 144`, `behind 0`
- merge base: exactly `06042c2bf31d64d69d884528d43796c2c3b7ae5d`

Therefore there was no unreviewed moving-main divergence to reconcile at this gate.

## Runtime / production handoff — mandatory after merge

The production release remains a separate environment-dependent gate. Codex or an equivalent runtime operator must perform the following against the **exact merged `main` SHA**, not an old workspace and not a dirty local tree.

### 1. Clean exact-SHA checkout

- fetch `main` after the integration merge;
- record the exact SHA;
- use a clean/disposable worktree;
- verify the release source reports that exact SHA;
- do not reconstruct code from a legacy local workspace.

### 2. Runtime configuration verification

Verify, without printing secret values:

- `DENT_STORAGE_ROOT` / server-only storage paths;
- `DENT_CLASSOPS_STORE_PATH` if overridden;
- `DENT_CLASSOPS_STAGE2_STATE_PATH` if overridden;
- `DENT_CLASSOPS_TELEGRAM_ENABLED` reflects proven Telegram runtime availability;
- `DENT_CLASSOPS_BALE_ENABLED` reflects proven Bale runtime availability;
- `DENT_CLASSOPS_BALE_CHANNEL_SUPPORTED` is true only if that exact capability is proven;
- dedicated `DENT_CLASSOPS_AI_AVALAI_API_KEY` exists only if AI is intentionally enabled;
- `DENT_CLASSOPS_AI_MODEL` is explicitly configured when AI is enabled;
- ClassOps AI does not reuse Voice/STT credentials.

### 3. Pre-deploy production backup

Take and verify a restoreable snapshot of canonical production data before any upload/restart. At minimum cover the storage families touched or read by this release, including current ClassOps, Stage 2 domain-state, notification and bot/account-link data according to the canonical backup process. Record sizes/hashes/schema validity and the restore location without committing runtime data to Git.

### 4. Runtime-only verification before deploy

Run environment-dependent checks that CI intentionally cannot prove:

- PHP/runtime extensions and filesystem write/rename/lock behavior;
- ClassOps and bot storage writeability without modifying canonical user content unnecessarily;
- signed website/bot onboarding/account-link path;
- Telegram service health and outbound capability;
- Bale service health and outbound capability;
- scheduler/coordinator single-owner behavior, restart and idempotency;
- destination capability advertisement versus actual runtime support;
- AI provider reachability/configuration only if AI is enabled;
- service restart counts / crash loops / relevant logs;
- no secret or user-content leakage in logs.

Any runtime failure blocks release; do not weaken contracts to make a health check pass.

### 5. Exact-SHA deploy

Use the canonical deployment script/source model only after the backup and runtime preflight are verified. The release artifact/worktree must correspond to the exact merged GitHub SHA. No manual production-file edits and no deployment from a dirty legacy tree.

### 6. Post-deploy live verification

Verify at minimum:

- website health and authenticated owner/student ClassOps reads;
- capabilities endpoint reflects real configured runtime state;
- preview remains zero-write;
- no spontaneous ClassOps send/mutation occurs;
- Telegram and Bale existing bot paths remain healthy;
- notification subsystem remains canonical and existing read-state is intact;
- scheduler has one effective coordinator and no duplicate reminder/digest emission;
- task/service/ACK state storage is valid and writable;
- existing payment, Term 7 and account-link flows are not regressed;
- production logs show no new repeated `5xx`, persistence, callback, scheduler or transport errors;
- deployed SHA/release ID is recorded;
- rollback inputs remain immediately usable.

Use synthetic/non-user-impacting smoke paths where possible. Do not send test broadcasts to real students and do not create fake production business records merely to prove the deploy.

### 7. Rollback rule

If storage schema/write safety, auth isolation, duplicate scheduling, transport behavior, payment regression, or repeated server errors cannot be cleared promptly, stop and roll code back to the prior known-good release. Preserve post-failure forensic state and do not overwrite valid canonical data with an empty/default store.

## Merge gate

This integration branch may be merged to `main` only when:

- its current head is still a descendant of the locked candidate with no unexpected code changes;
- GitHub CI for the report commit is green;
- `main` still has no unexpected divergence from the recorded merge base;
- the PR is mergeable.

After merge, the resulting `main` SHA becomes the **GitHub release candidate**. It is not a claim of production deployment. The production `PARALLEL_BASE_SHA` / release baseline should only be declared after the runtime backup/deploy/live-verification gate also succeeds.
