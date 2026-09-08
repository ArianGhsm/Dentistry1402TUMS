# ClassOps Exam Operations + Version-Aware Critical ACK

Status: **feature-domain implementation / candidate contract; runtime wiring intentionally not enabled**.

Development base: `06042c2bf31d64d69d884528d43796c2c3b7ae5d`.
Candidate contract: `contracts/candidates/classops-exam-ack-v1.json`.

## Scope and source-of-truth boundaries

This workstream adds domain semantics for operational exams and critical-notice acknowledgements without replacing existing canonical systems.

- `classops-v1` remains the generic item lifecycle/revision/idempotency/audit foundation.
- The existing website exam/assessment subsystem remains authoritative for attempts, answers, reports, study state, timer/access behavior, and paid-course access. `exams_store.php` already owns per-user assessment state; `exams_api.php` already resolves access against the payment subsystem.
- ClassOps exam records contain operational schedule data and reference-only pointers. They never contain copied attempt/answer/timer/access/payment-success state.
- The existing notification subsystem remains authoritative for notification feed/state. This module does not create a second notification feed.
- Existing auth/identity remains authoritative. ACK uses canonical `studentNumber`; transport chat/user ids are not ClassOps ACK identities.
- Existing Telegram/Bale delivery state remains delivery telemetry. Delivery or read receipt is never equivalent to ACK.
- Existing payment architecture remains authoritative. `paymentAccessRef` is explicitly `reference_only`; ClassOps cannot create or assert verified payment success.

## Exam operation model

`public_html/api/classops_modules/exams/exam_ops.php` builds and validates platform-neutral commands/projections. It does not write Foundation storage itself.

Operational fields map as follows:

| Concern | Canonical representation |
| --- | --- |
| item/course/title/description | existing `classops-v1` fields |
| exam start/end | `timing.startsAt` / `timing.endsAt`, canonical UTC timestamps |
| location | existing ClassOps `location` |
| importance | existing ClassOps `importance` |
| audience declaration | frozen `classops-audience-placeholder-v1` structure |
| external/calendar ref | `extensions.exam_ops_v1.reference` |
| scope/notes | `extensions.exam_ops_v1.scope` / `notes` |
| assessment pointer | `extensions.exam_ops_v1.assessmentRef` (`website_assessment`) |
| resource pointers | `extensions.exam_ops_v1.resourceRefs` |
| payment/access pointer | `extensions.exam_ops_v1.paymentAccessRef`, reference-only |
| operational reminder intent | `extensions.exam_ops_v1.reminderPolicy` |

The module explicitly rejects known parallel assessment/payment state fields such as `attempts`, `answers`, `timer`, `access`, `paymentStatus`, `verifiedPayment`, and `verifiedSuccess`.

### Revision-aware reschedule/cancel

Reschedule requires the exact current Foundation revision. The command records `lastScheduleChange.supersedesRevision` and preserves every foreign extension namespace before returning the Foundation update patch. Stale revision fails with `CLASSOPS_EXAM_STALE_REVISION`.

Cancel also requires the exact current revision and returns a Foundation lifecycle `cancel` command. It does not invent a second cancellation state. The existing immutable ClassOps revision history remains the history source.

Neither command writes directly to `classops_store.php`; integration must execute the returned command through the existing Foundation mutation semantics so Foundation idempotency/revision handling is retained.

## Reminder policy

The default policy is declarative only:

1. T-3 days (`259200` seconds before start)
2. T-1 day (`86400` seconds before start)
3. `night_before` calendar marker
4. `morning_of` calendar marker

No due timestamp is calculated in this branch. `night_before` and `morning_of` are semantic markers rather than hard-coded local clocks so the scheduler integration can apply the canonical cohort/calendar policy without introducing a second timezone/source of truth.

The frozen Foundation reminder placeholder remains `classops-reminder-placeholder-v1` with no executable rules. Scheduler integration must consume/promote `classops-exam-reminder-v1`; this feature branch does not pretend the current central scheduler already supports it.

All stored concrete timestamps emitted by this module are normalized to UTC ISO-8601 (`...Z`). The existing frozen `classops-v1` timing object still requires its `timezone: Asia/Tehran` marker; the marker is retained strictly for Foundation compatibility and is not used to store a non-UTC instant.

## Critical notice ACK model

`public_html/api/classops_modules/ack/critical_ack.php` defines the persistence-neutral canonical state transition for ACK.

A canonical ACK is unique for the exact tuple:

`itemId + revision + studentNumber`

The private ACK record contains:

- exact ClassOps item id;
- exact revision;
- canonical student number;
- server-supplied acknowledgement timestamp normalized to UTC;
- idempotency key;
- fixed intent `explicit_user_ack`.

A later notice revision has a different tuple. Therefore an ACK for revision N does not satisfy revision N+1. This applies to semantic notice revisions and deliberately uses exact Foundation revision semantics.

### Idempotency

The state maintains a hashed idempotency index scoped to the canonical student. Exact replay returns the existing ACK without new history. A second key for an already-ACKed tuple may be registered as an alias without creating a second ACK/history event. Reusing one idempotency key for a different ACK payload fails closed with `CLASSOPS_ACK_IDEMPOTENCY_CONFLICT`.

### Audience/visibility enforcement

ACK requires an exact-version eligibility proof with:

- notice `itemId`;
- notice `revision`;
- canonical `studentNumber`;
- `eligible: true`;
- audience fingerprint.

The proof is an interface for the future deterministic audience resolver; this branch does **not** resolve audiences itself. Missing, false, stale, cross-item, cross-revision, or cross-student proof fails closed. Display-name matching is not supported.

Owner aggregate statistics accept the eligible canonical student set at read time and return counts only: `eligible`, `acked`, `pending`. They do not return the student list.

### Privacy-safe audit

Canonical private records necessarily contain the canonical student number and idempotency key. Audit history does not. It stores only one-way actor/idempotency fingerprints plus item id, revision, ACK id and UTC timestamp. Transport ids are not accepted into ACK state.

ACK history is append-only in the domain model; no destructive history compaction is implemented here.

## Transport is not ACK

`classops_ack_from_transport_receipt()` always rejects. Telegram/Bale send success, delivery, or read state must remain delivery telemetry.

An eventual bot/site action may create ACK only when the user performs an explicit acknowledgement action mapped to `intent=explicit_user_ack`. The adapter must authenticate the actor through canonical identity first; it must not pass a raw platform chat/user id as the ACK actor.

## Integration-only handoff

No integration hotspot was changed in this branch. Runtime activation requires a separate integration-owned change set.

1. **Contract promotion** — review `contracts/candidates/classops-exam-ack-v1.json`; if approved, promote it through the shared-contract process. Do not silently modify frozen `classops-v1`.
2. **Exam API wiring** — owner exam create/reschedule/cancel operations may call the exam module and then execute returned mutations through existing `classops_store.php`/`classops_api.php` revision and idempotency semantics. These hotspot files were intentionally untouched here.
3. **ACK private storage** — wire `classops-critical-ack-state-v1` to an approved private runtime store outside Git. Persistence must use exclusive locking for mutation, atomic commit/generation semantics, fail closed on malformed state, preserve ACK history, and never log raw private records. Reuse the established ClassOps persistence quality model rather than writing truncate-in-place JSON.
4. **Student ACK endpoint/auth** — current `classops_api.php` is owner-only. Integration must add an explicitly reviewed student-authenticated ACK route (candidate shape: `POST /api/classops-ack/items/{itemId}/ack`) or deliberately extend central routing. Client body supplies only revision, idempotency key, and explicit intent; canonical student identity and `ackedAt` are server-derived.
5. **Audience resolver** — deterministic audience integration must issue/compute `classops-audience-eligibility-v1` proof for the exact item revision using canonical ids. Dynamic/display-name resolution does not belong in ACK code or AI.
6. **Scheduler** — central scheduler integration may consume `classops-exam-reminder-v1` and calculate actual due instants for T-3, T-1, night-before, and morning-of. This branch performs no due calculation and creates no scheduler state.
7. **Notifications** — use the existing notification subsystem for ClassOps delivery; do not create a ClassOps feed/store in parallel.
8. **Telegram/Bale mapping** — thin adapters may map an explicit ACK button/callback to the student ACK endpoint after canonical identity resolution. Delivery/read receipts must never call ACK mutation.
9. **Assessment pointer** — resolve `assessmentRef` into the existing website assessment engine. Do not copy question, attempt, answer, timer, report or access rows into ClassOps.
10. **Payment pointer** — resolve `paymentAccessRef` only through existing assessment/payment access logic. ClassOps must not emit a `success`, `paid`, verified-order, transaction or gateway state.
11. **AI boundary** — if AI later drafts exam/notice fields, it remains `classops-structured-draft-v1`: unknown values are `null`, deterministic identity/audience/ref resolution occurs outside AI, owner preview/confirm is required, and AI cannot write/send directly. ClassOps AI credentials remain isolated from Voice/STT credentials with no fallback.

### Hotspots intentionally untouched

- `public_html/api/classops_api.php`
- `public_html/api/classops_store.php`
- `public_html/api/classops_persistence.php`
- `public_html/api/auth_store.php`
- `public_html/api/notifications_store.php`
- `public_html/api/academic_term7.php`
- `public_html/api/bot_store.php`
- `public_html/api/bot_api.php`
- bot runtime central adapter/runtime/state files
- central scheduler/router, service units, deploy scripts, manifests, workflows

## Tests

Focused domain/negative/privacy tests:

```bash
php scripts/test_classops_exam_ack_domain.php
php scripts/test_classops_exam_ack_contract.php
```

The tests use synthetic student numbers and synthetic item ids only. They cover validation, reschedule/cancel revision guards, reminder serialization, reference-only assessment/payment boundaries, exact-revision ACK, duplicates/idempotency, revised-notice invalidation, stale/cross-audience rejection, aggregate counts, transport-receipt rejection, deterministic projections, and privacy-safe audit.

## Runtime capability statement

This branch makes the **domain modules and candidate contract testable**, but does not make ACK or exam scheduling reachable from production HTTP/bot/scheduler paths. Runtime support must not be advertised until the integration handoff above is completed and verified in the later integration/deploy stage.
