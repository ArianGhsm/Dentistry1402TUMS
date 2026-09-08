# Shared contracts — ClassOps Stage 1 integration

The machine-readable contract graph is `contracts/classops-domain-contracts-v1.json`.
Contracts and tests define boundaries so parallel work cannot silently create a second source of truth.

## Frozen compatibility contracts

These historical contracts remain readable and are **not** reinterpreted in place:

- `classops-v1`: generic ClassOps item, lifecycle, revision, optimistic concurrency, idempotency and audit.
- `classops-audience-placeholder-v1`: historical symbolic audience placeholder.
- `classops-delivery-placeholder-v1`: historical symbolic destination placeholder.
- `classops-reminder-placeholder-v1`: historical declarative offset placeholder.
- `classops-structured-draft-v1`: producer boundary; unknown fields are `null`, deterministic resolution is outside the producer, preview/confirmation is mandatory, direct DB mutation and direct send are forbidden.
- `canonical-student-identity-v1`: canonical student/user identity; display-name matching is never identity.
- `runtime-site-service-v1`: authenticated service boundary independent of end-user bot links.
- `notification-integration-v1`: existing notification subsystem remains canonical; no parallel ClassOps feed/read-state.

## Stage 1 approved domain contracts

The integration branch has approved the following implementations as the Stage 1 domain graph:

- `classops-audience-v1` — deterministic whole-cohort/explicit/include-exclude/canonical-selector AND/OR/NOT resolution with snapshot/live policy and drift detection.
- `classops-delivery-v1` — symbolic destination registry plus deterministic, revision-bound delivery planning. It is planning-only and cannot send or write notification state.
- `classops-ai-draft-v1` — DeepSeek/AvalAI structured-draft producer/validator. It cannot mutate/send; ClassOps AI credentials are independent from Voice/STT credentials and have no fallback.
- `classops-tasks-v1` — canonical per-student task/requirement lifecycle and deterministic requirement progress rules.
- `classops-exam-ack-v1` — operational exam extension plus exact-revision critical acknowledgement domain; transport receipts are never ACKs.
- `classops-reminder-v1` — pure deterministic reminder planning, bounded recurrence and Saba reminder safety; no credential/session material and no direct send.
- `classops-digest-v1` — deterministic Tomorrow Summary / Weekly Digest projection with cohort/privacy filtering and no AI-generated facts.

Each approved contract now has a canonical file directly under `contracts/`. The original feature-branch candidate material remains under `contracts/candidates/` as provenance/review evidence. `contracts/classops-domain-contracts-v1.json` points only to the canonical paths and records the exact Git blob SHA of each candidate origin. `scripts/test_classops_domain_contract_graph.py` verifies that the promoted canonical copy is byte-identical to the locked candidate blob and fails on origin drift. This promotion does not alter the frozen `classops-v1` item schema.

## Persistence compatibility decision

Stage 1 performs **no production-data migration** and does not reinterpret the historical audience/delivery/reminder placeholder fields.

The existing ClassOps store remains schema/contract version 1 and therefore remains compatible with existing stored revisions. Stage 1 now adds `public_html/api/classops_domain_store_adapter.php` as a compatibility bridge for new Stage 1 API mutations:

- generic Foundation normalization still owns the stored `classops-v1` item;
- known `classops_tasks_v1` and `exam_ops_v1` extension namespaces are version-checked and normalized before new create/update writes;
- a type-only update validates the complete prospective known-extension set so a known extension cannot be stranded under an incompatible item type;
- unknown historical extension namespaces remain opaque/readable;
- promoted audience/delivery/reminder contracts are **not** accepted as silent replacements for frozen stored placeholders.

This is deliberately narrower than Stage 2 persistence. Trusted server-produced audience snapshots, delivery/scheduler execution state, per-student task state and critical-ACK state are not yet exposed as arbitrary Foundation item fields. When they require persistence, Stage 2 must place them inside the **same canonical ClassOps storage family** with explicit schema/version/atomic-transaction and migration/rollback tests. No second ClassOps database/store is permitted.

The Stage 1 HTTP boundary requires the store adapter for create/update and exposes only a non-sensitive read-only `domain-capabilities` action in addition to the frozen Foundation API. It does not send messages, execute reminder jobs, invoke the AI provider as a side effect, or write a parallel notification feed.

## Source-of-truth boundaries

- identity/roles: existing `auth_store.php`
- official Term 7 schedule/assignments: `academic_term7.php`
- notification feed/read state: existing notification subsystem
- payment order/transaction verification: existing payment subsystem
- ClassOps operational state: canonical ClassOps storage family
- Telegram/Bale: later thin transport adapters over shared application/domain logic

Audience membership is independent from delivery capability. A missing bot link never removes a canonical student from an audience. Raw Telegram/Bale destination IDs are runtime configuration facts and must not be persisted in ClassOps items.

## Stage 2 surface contract

`classops-surface-v1` remains Stage 2-only. Stage 1 audits it for compatibility but does not merge the website/bot cross-surface branch. Stage 2 will wire the approved domain graph to the Website Operations Center, Telegram and Bale, including trusted state persistence and canonical notification/runtime adapters.

## Change process

A semantic contract change must:

1. state the rationale and compatibility impact;
2. use a new version when meaning changes;
3. update producer and consumer tests;
4. preserve historical readers or provide a deterministic tested migration;
5. never require production secrets for repository-level tests;
6. pass integration regression before any production migration/deploy.
