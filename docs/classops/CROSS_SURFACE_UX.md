# ClassOps cross-surface UX — Stage 2 integration

## Status

This document describes the integrated Stage 2 branch `integration/classops-final-unification-v1`.
It supersedes the earlier foundation-only/candidate handoff text for this branch.

Stage 2 remains **unreleased** until the acceptance matrix, repository CI, runtime verification, backup and exact-SHA deployment gates are green. The integration branch must not be treated as production state merely because a capability is implemented in source.

## Authority model

ClassOps uses one business workflow across website, Telegram and Bale:

1. owner input or AI structured draft;
2. deterministic validation;
3. canonical audience resolution;
4. destination/reminder preview;
5. explicit owner confirmation;
6. audience hash re-check;
7. canonical revision commit;
8. notification/delivery intent creation;
9. server coordinator / thin transport adapters.

AI, website JavaScript, Telegram and Bale do not have independent publication authority.

Canonical boundaries remain:

- GitHub: code source of truth;
- production storage: data source of truth;
- `auth_store.php`: canonical user/student/cohort identity source;
- existing website notification subsystem: canonical website notification/feed state;
- ClassOps Stage 2 state: audience snapshots, task/service state, ACK state, delivery intents, scheduler occurrences and opaque callback references;
- Telegram/Bale: transport adapters, not parallel business databases.

## Website Operations Center

The owner surface is `/classops/`.

The page starts with all owner controls hidden. It calls the owner-only ClassOps `status` endpoint before exposing the management surface. No role decision is cached in local storage, IndexedDB or another client-side authorization store.

### Create workflow

The composer supports:

- canonical cohort key;
- ClassOps item type;
- title, description, course title, location and importance;
- Tehran-local start/due time converted to ISO-8601 with explicit offset;
- version-aware ACK requirement for `critical_notice`;
- canonical audience modes: whole cohort, explicit student numbers or selector (`role`, `group`, `category`);
- include/exclude canonical student-number overrides;
- symbolic destinations: `private_users`, `class_group`, `information_channel`;
- reminder-only Saba service metadata for `service_reminder`.

Submitting the form calls the Stage 2 `preview` action. Preview performs audience resolution and delivery/reminder planning but performs no mutation.

The confirmation panel displays audience count/warnings, delivery route outcomes and reminder/service information. A confirm request contains the exact preview `audienceHash`. The backend re-resolves the audience and fails with `CLASSOPS_AUDIENCE_DRIFT` if it changed.

Only the Stage 2 `confirm` action can turn that preview into a canonical commit and side effects.

### Existing item revisions

Items carrying `extensions.classops_stage2_v1` are edited through the same preview/confirm workflow. Revision-sensitive updates carry `expectedRevision`. A stale revision or changed audience returns HTTP 409 and forces a fresh review.

Historical Foundation-only items are not silently promoted to Stage 2 by the website. This prevents an ordinary legacy edit from unexpectedly creating audience/delivery side effects.

Cancel/archive use the Stage 2 lifecycle path and supersede future delivery work/notifications as defined by the backend.

### Owner projections

The website can read:

- task/requirement per-student state;
- critical-notice ACK statistics;
- deterministic tomorrow summary;
- deterministic weekly digest.

These reads do not initialize task state or create notification/digest state.

## AI Copilot

The AI endpoint uses `classops-structured-draft-v1` and a dedicated ClassOps credential.

Supported runtime provider for this release is AvalAI. The model is runtime-configured; the code does not hard-code or silently fall back to a Voice/STT credential.

AI rules:

- owner-only;
- output is structured draft only;
- no direct mutation;
- no direct send;
- no identity resolution;
- no audience resolution;
- no course/date resolution authority;
- forwarded text is untrusted data;
- unresolved values remain null/unresolved;
- manual composer remains available when AI is unconfigured or unavailable.

The website can create and edit an AI draft, then copy only concrete safe fields into the manual composer. Timing and audience clues are deliberately not guessed.

Runtime configuration is documented in `.env.example`:

- `DENT_CLASSOPS_AI_PROVIDER`
- `DENT_CLASSOPS_AI_BASE_URL`
- `DENT_CLASSOPS_AI_AVALAI_API_KEY`
- `DENT_CLASSOPS_AI_MODEL`
- `DENT_CLASSOPS_AI_TIMEOUT_MS`
- `DENT_CLASSOPS_AI_MAX_RETRIES`
- optional input/output cost rates.

The endpoint must be clean HTTPS. Literal localhost/private endpoints are rejected. Runtime retries are bounded to 0..2 and apply only to transient transport failures, 429 and 5xx responses. Direct test/client construction keeps a no-retry default for deterministic compatibility.

## Audience and delivery

The integrated audience contract is `classops-audience-v1`.

Audience resolution derives from canonical server context. Display names are never identity keys. Supported expression primitives include:

- `whole_cohort`;
- explicit canonical `students`;
- canonical `selector` by role/group/category;
- `not`, `any`, `all` combinations at the domain contract level;
- include/exclude student-number overrides.

The website currently exposes the common whole-cohort / explicit-students / selector cases. More complex boolean expressions remain available at the API/domain contract boundary rather than being represented by an unsafe free-form identity UI.

Delivery uses symbolic destination aliases and capability planning. Raw Telegram/Bale chat IDs and bot tokens are not ClassOps domain fields.

A platform state of `unknown`, `unavailable` or unsupported capability is shown as blocked rather than reported as success. Telegram and Bale are independent routes; a platform outage does not silently rewrite business semantics.

## Tasks / Requirements

Task states are:

- `pending`
- `submitted`
- `needs_revision`
- `completed`
- `waived`

Per-student task state is revision-aware and uses optimistic state revisions/idempotent commands. Eligible state can be carried to a newer ClassOps revision according to the integrated domain policy; unauthorized students are rejected against the stored audience snapshot.

## Exams / Critical ACK

Critical ACK is explicit application state. A Telegram/Bale delivery or read receipt is not an ACK.

ACK is bound to item revision and audience eligibility. A newer critical-notice revision requires a new ACK; satisfaction is not carried from an older revision.

Owner statistics are computed against the authoritative audience snapshot for that revision.

## Scheduler, reminders and Saba

The coordinator is server-canonical. Telegram and Bale do not independently schedule the same ClassOps occurrence.

Exam defaults include deterministic T-3, T-1, night-before and morning-of rules in Asia/Tehran.

Reminder occurrence keys and delivery intents are idempotent/revision-aware; future work is superseded on relevant update/cancel/archive transitions.

Saba support is reminder-only. The ClassOps model rejects Saba credentials/session material and performs no Saba login automation.

Digest schedule defaults are documented in `.env.example` and are interpreted in Asia/Tehran.

## Telegram / Bale

`bot_runtime/dent_bot/classops_surface/` contains shared semantic models/rendering. Transport-specific adapters may differ cosmetically but not in authorization or business action identity.

The integrated callback/service boundary uses opaque, actor-bound callback references where a mutation needs server context. Raw platform user/chat IDs are not persisted in ClassOps domain payloads.

The canonical website/service layer resolves item, audience and authorization. Bot code remains a thin adapter over that business logic.

## Security and privacy invariants

- owner management endpoints require canonical owner authorization;
- browser mutations require existing CSRF protection;
- student endpoints derive the current canonical user server-side;
- no caller-supplied student identity grants authorization;
- no ClassOps AI secret is exposed to browser/bot payloads;
- no Voice/STT credential fallback;
- no raw platform destination IDs in domain state;
- no Saba credentials;
- no runtime JSON/SQLite, sessions, secrets, logs or backups committed to Git;
- unknown/unsupported capability fails closed;
- 409 conflicts require refresh/review, never blind retry.

## Test gates

The integration branch adds `scripts/test_classops_stage2_web_surface.py` and wires it into `.github/workflows/classops-stage1.yml` alongside the domain matrix.

The repository-wide static workflow also checks JavaScript syntax, PHP lint, UTF-8 integrity, repository hygiene, Foundation compatibility, persistence/concurrency and existing product regressions.

Stage 2 is not releasable until all of the following are true:

1. domain matrix green;
2. repository static checks green;
3. Stage 2 web surface contract green;
4. Foundation HTTP/backward-compatibility green;
5. bot/runtime deterministic tests green;
6. runtime configuration verified without exposing secrets;
7. verified production backup exists;
8. exact integrated GitHub SHA is deployed;
9. post-deploy health/log/smoke checks pass;
10. rollback remains available.
