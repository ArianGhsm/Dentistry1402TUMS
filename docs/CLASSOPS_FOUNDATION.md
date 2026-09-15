# ClassOps Foundation and Stage 1 domain layer

## Current status

`classops-v1` remains the persisted Foundation contract. It provides the canonical generic item lifecycle, revision history, optimistic concurrency, idempotency, audit and owner-only Foundation API.

Stage 1 integration now adds **pure/versioned domain implementations** for Audience, Delivery planning, AI structured drafts, Tasks/Requirements, Exam/Critical ACK, Reminder planning and Tomorrow/Weekly digests. These modules are integrated in the repository and exposed through `public_html/api/classops_modules/domain_facade.php`, but cross-surface Website/Telegram/Bale wiring and production migration/deploy remain Stage 2/runtime work.

This distinction is intentional: Stage 1 domain availability is not a claim that a reminder was sent, that a candidate AI draft was committed, or that new domain state is already migrated into production storage.

## Canonical source-of-truth boundaries

ClassOps coordinates operational class management without replacing existing authoritative systems:

- canonical identity/roles remain in `auth_store.php`;
- official Term 7 schedule/assignments remain in `academic_term7.php`;
- existing notification feed/read state remains canonical;
- existing payment order/transaction verification remains canonical;
- Telegram and Bale remain thin adapters over shared application/domain logic;
- raw platform chat IDs are runtime/configuration facts, not ClassOps item facts.
- runtime bot presentation has one explicit product router, `bot_runtime/dent_bot/feature_router.py`, which delegates the ClassOps surface to `classops_ui.py`; shared Home/Admin presentation lives in `classops_shell.py` and owner create/edit dialogs live in `classops_owner_workflows.py`. Telegram and Bale both enter the same `run_service()` path and do not install runtime patches.
- feature modules must not replace `DentBotApp` methods, background runtime functions or imported renderers at process start. Old `classops-v2:*` callback payloads are accepted only by a bounded ingress translator so buttons already present in user chats remain usable after upgrade.
- the bot service API has one ClassOps UI dispatcher, `public_html/api/classops_bot_ui.php`. Versioned service action names remain compatible wire-protocol identifiers, not separate endpoint generations.

ClassOps may represent one-off `class_change` operational overlays. It must not create a parallel official schedule database.

## Persisted Foundation storage

The Foundation uses private atomic JSON at `storage/classops/store.json` (resolved via `DENT_STORAGE_ROOT`). The store has an independent lock/generation history and fail-closed persistence: malformed/truncated/schema-invalid state is preserved for forensics, writes use same-filesystem temp files with full verification and atomic replacement, and reads do not rewrite state.

The persisted contract remains:

- schema version `1`
- contract `classops-v1`
- timezone marker `Asia/Tehran`
- canonical stored timestamps UTC ISO-8601

Historical audience/delivery/reminder placeholders remain readable and are not silently reinterpreted.

Stage 1 performs no production-data migration. Trusted persistence of server-produced audience snapshots, per-student task/ACK state and delivery/scheduler state is reserved for Stage 2 and must remain inside the same canonical ClassOps storage family with an explicit versioned schema/migration/rollback decision. A second ClassOps database is forbidden.

## Generic item types

The Foundation supports:

- `announcement`
- `event`
- `class_change`
- `deadline`
- `task`
- `requirement`
- `exam`
- `critical_notice`
- `service_reminder`

Lifecycle:

- `draft` → `scheduled`, `cancelled`, `archived`
- `scheduled` → `draft`, `active`, `cancelled`, `archived`
- `active` → `completed`, `cancelled`, `archived`
- `completed` / `cancelled` → `archived`
- `archived` terminal

Every semantic mutation creates a full revision snapshot. Updates/lifecycle actions require `expectedRevision`. Mutations require idempotency keys; same key + same semantic request replays, same key + different request conflicts.

## Stage 1 Audience Engine

`classops-audience-v1` resolves **who is in scope**, independently from transport capability.

Supported deterministic constructs include:

- whole cohort
- explicit canonical student numbers
- include/exclude
- canonical role/group/category selectors when a canonical student-number source exists
- bounded `any` / `all` / `not`
- snapshot or live resolution policy

Resolution is canonical-student-number based, cohort isolated, bounded and deterministic. Live confirmation uses a resolution hash and fails with drift instead of silently changing recipients. Snapshot mode pins a trusted server-produced resolution. Display-name matching and bot-link identity are forbidden.

## Stage 1 Destination & Delivery planning

`classops-delivery-v1` defines symbolic destination registry and deterministic delivery intents for private/group/channel/logical routes.

It is **planning-only**:

- no direct send;
- no notification-store write;
- no parallel feed;
- no raw Telegram/Bale chat IDs in ClassOps;
- item revision and audience hash bind intents;
- retries, supersession and cancellation are explicit;
- platform capabilities are not invented—unknown Bale capabilities remain unknown until evidence exists.

Stage 2 will connect these intents to the existing notification/runtime transport boundary.

## Stage 1 AI Copilot

`classops-ai-draft-v1` supports DeepSeek through AvalAI via an injectable provider abstraction.

Rules:

- free text / forwarded text produces a strict structured draft only;
- uncertain/unknown fields become `null`;
- deterministic identity/audience/date resolution occurs outside AI;
- forwarded text is untrusted data, not authority;
- every result remains preview-only until explicit owner confirmation;
- AI cannot mutate ClassOps storage or send messages;
- ClassOps AI credentials are dedicated and cannot reuse/fallback to VoiceMatn/STT credentials;
- usage telemetry is aggregate/non-sensitive and does not persist sensitive prompt content.

## Stage 1 Tasks & Requirements

`classops-tasks-v1` was implemented by integration because the original parallel tasks branch remained empty.

Canonical per-student states:

- `pending`
- `submitted`
- `needs_revision`
- `completed`
- `waived`

Transitions are optimistic-concurrency and idempotency aware. History is non-destructive. Requirements support deterministic bounded binary/count progress. Student projections are self-only; overdue is derived and reads do not mutate state. File/submission content storage is outside this domain.

## Stage 1 Exams & Critical ACK

`classops-exam-ack-v1` treats operational exam management separately from the website assessment engine. It does not duplicate attempts, answers, timers, payment state or verified access.

Exam reminder policy can express:

- T-3
- T-1
- night before
- morning of

Critical ACK is canonical application state bound to exact item revision and canonical student identity. A delivery/read receipt can never create an ACK. A semantic new notice revision requires a new ACK.

## Stage 1 Reminder planner and Saba

`classops-reminder-v1` is a pure deterministic planner with injectable clock, bounded planning horizon, idempotent occurrence keys, supersession, catch-up policy and bounded recurrence.

Recurring rules are limited to approved `service_reminder` use cases. Saba support is reminder-only: usernames, passwords, tokens, cookies, sessions and automated login are forbidden.

The planner produces due intents; it does not send or persist transport outcomes. Stage 2 owns single-coordinator runtime wiring to prevent Telegram/Bale duplicate background effects.

## Stage 1 Tomorrow Summary / Weekly Digest

`classops-digest-v1` creates deterministic platform-neutral projections from injected canonical records. It can combine official schedule projections with operational overlays, tasks/requirements, exams, pending critical ACKs and service reminders.

It does not copy Term 7 state, use AI to invent summaries, expose another student's private state, or write during reads. Outputs are stable, bounded and suitable for later Website/Telegram/Bale renderers.

## Unified domain facade

`public_html/api/classops_modules/domain_facade.php` provides the Stage 1 pure domain boundary. It intentionally has no HTTP, Telegram or Bale dependency and no external send authority.

The machine-readable graph is `contracts/classops-domain-contracts-v1.json`.

## Stage 2 boundary

Stage 2 must:

1. merge/reconcile `classops-surface-v1` and the cross-surface workstream;
2. add trusted Website Operations Center/API confirmation flows;
3. persist new trusted domain state through one versioned canonical ClassOps storage family;
4. connect deterministic intents to the existing notification/runtime boundary;
5. wire Telegram and Bale as thin adapters with capability-tested parity/fallbacks;
6. wire one background coordinator for scheduled side effects;
7. expose personalized task/exam/ACK/digest reads safely;
8. prepare a Codex runtime migration/deploy handoff.

## Tests

Stage 1 focused tests are included in `scripts/run_static_checks.sh`, together with the pre-existing Foundation/auth/payment/notification/Term7/bot regression gates.

No Stage 1 repository test requires production credentials, Telegram/Bale network access or production storage.
