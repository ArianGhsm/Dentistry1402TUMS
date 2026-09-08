# ClassOps Integration Stage 1 Report

## Status

**PROVISIONAL — repository CI pending.**

Stage 1 performs domain consolidation and contract unification only. It does not deploy production, mutate server/runtime state or merge to `main`.

Frozen base:

`06042c2bf31d64d69d884528d43796c2c3b7ae5d`

Target branch:

`integration/classops-domain-unification-v1`

CI evidence PR (intentionally draft / do-not-merge): `#17`.

## A. Locked feature inputs

The feature refs were read twice (initial lock and post-integration recheck) and remained stable:

| Workstream | Locked SHA | Stage 1 action |
|---|---|---|
| Audience & policy | `4a0f6ce8e31369d719752b7b0249669e05052ea9` | merged through PR #11 |
| Destination & delivery | `14a02efeab3744f46c53cc758013496c8095ca3a` | merged through PR #12 |
| AI Copilot | `24d2b648ff8fb64e1e60b93ab2cd4f6f0610c48b` | merged through PR #13 |
| Tasks & requirements | `06042c2bf31d64d69d884528d43796c2c3b7ae5d` | branch was empty; gap recovered in Stage 1 |
| Exams & critical ACK | `ac319ea63c0ee674b3f50ddcd08c31bb26538427` | merged through PR #14 |
| Reminder scheduler / Saba | `8e3ed0c628acfeb5b206725233a40a9fcd33672b` | merged through PR #15 |
| Digests / summaries | `ec370a088c9fd5efe2d5d4c0709a4fe7aff54f6a` | merged through PR #16 |
| Cross-surface UX | `740e8ec3bd6e8dc3fd278aba3e9732cc7c5261c1` | audited only; reserved for Stage 2 |

No moving-target ref was observed after the lock.

## B. Tasks/Requirements gap recovery

The tasks branch contained no commits beyond the frozen base and no expected tasks domain files. Stage 1 therefore did not report a false merge.

The integration branch adds:

- `contracts/candidates/classops-tasks-v1.json`
- `public_html/api/classops_modules/tasks/task_domain.php`
- `scripts/test_classops_tasks_requirements.php`
- `docs/classops/TASKS_REQUIREMENTS.md`

Canonical states are `pending`, `submitted`, `needs_revision`, `completed`, `waived`. Mutations use expected state revision plus command/idempotency identity, history is non-destructive, requirement progress is bounded/deterministic, overdue is derived, and student projection fails closed for another student.

No user database, platform-local task store or submission-content store was created.

## C. Merged domains

### Audience

`classops-audience-v1` provides deterministic whole-cohort/explicit/include-exclude/canonical-selector and bounded AND/OR/NOT resolution. It supports snapshot/live policy, deterministic hashes, fail-closed cross-cohort rules and live drift detection. Display names and bot links are not identity sources.

### Destination / Delivery

`classops-delivery-v1` provides a symbolic destination registry and deterministic revision/audience-bound delivery planning. It is planning-only: no direct send, notification-store mutation or raw platform identifiers in ClassOps state.

### AI Copilot

`classops-ai-draft-v1` provides DeepSeek/AvalAI structured draft production and validation. Unknown/uncertain values stay `null`; forwarded material is untrusted data; deterministic resolution is outside AI; owner confirmation remains mandatory; direct mutation/send is forbidden. Provider configuration uses dedicated ClassOps AI environment keys with no Voice/STT fallback.

### Exam / Critical ACK

Operational exam logic remains distinct from the assessment/payment engine. Default exam reminder metadata includes T-3, T-1, night-before and morning-of semantics. Critical ACK is exact-revision canonical application state and cannot be created from a transport/read receipt.

### Reminder / Saba

`classops-reminder-v1` is a pure deterministic planner with injectable clock, bounded horizon/recurrence, deterministic occurrence/idempotency keys, catch-up/supersession and single-leader coordination metadata. Saba is reminder-only and credential/session material is rejected.

### Tomorrow Summary / Weekly Digest

`classops-digest-v1` builds deterministic, bounded, privacy-filtered platform-neutral projections. Official schedule data is injected as a projection; Term7 is not copied. AI is not used to invent digest facts.

## D. Unified contract graph

`contracts/classops-domain-contracts-v1.json` records the approved Stage 1 graph:

- `classops-audience-v1`
- `classops-delivery-v1`
- `classops-ai-draft-v1`
- `classops-tasks-v1`
- `classops-exam-ack-v1`
- `classops-reminder-v1`
- `classops-digest-v1`

`classops-surface-v1` remains explicitly Stage 2-only.

Historical `classops-v1` and audience/delivery/reminder placeholder contracts remain readable and are not silently reinterpreted.

## E. Persistence / API decision

The persisted Foundation remains schema/contract `classops-v1`. Stage 1 performs **no production-data migration** and intentionally does not trust client-authored domain snapshots/intents/state.

This is a security/versioning decision rather than an omitted accidental migration:

- audience snapshots must be trusted server-produced confirmation artifacts;
- delivery intents are derived/planned facts, not arbitrary item fields;
- per-student task/ACK state requires canonical authorization;
- silently changing the meaning of frozen v1 placeholders would violate historical contract compatibility.

Stage 1 therefore integrates the pure domain graph and facade while preserving the existing store reader/writer. Stage 2 owns the explicit trusted persistence/API wiring inside the **same canonical ClassOps storage family**. A second ClassOps database/store is prohibited.

## F. Unified pure facade

`public_html/api/classops_modules/domain_facade.php` is the Stage 1 application/domain boundary. It exposes pure wrappers for:

- audience normalization/resolution/preview;
- destination registry and delivery planning;
- AI structured-draft validation;
- task/requirement extension/projection;
- exam projection and critical ACK queries;
- reminder planning;
- Tomorrow/Weekly digest generation.

It has no HTTP, Telegram/Bale or external-send authority.

## G. Source-of-truth invariants

Stage 1 preserves:

- identity/roles → `auth_store.php`
- official Term7 schedule/assignments → `academic_term7.php`
- notification feed/read-state → existing notification subsystem
- verified payment/order state → existing payment subsystem
- ClassOps operational state → canonical ClassOps storage family

No parallel identity, Term7, notification or payment store was introduced.

Audience membership remains independent of delivery capability. Raw Telegram/Bale identifiers remain outside the ClassOps domain. Platform delivery/read receipts are not critical ACKs.

## H. Test integration

`scripts/run_static_checks.sh` now includes:

- Stage 1 contract-graph test
- cross-domain invariant matrix
- Audience domain/contract/snapshot tests
- Delivery domain/contract tests
- AI domain/contract tests
- Tasks/Requirements tests
- Exam/ACK contract/domain tests
- Reminder scheduler tests
- Digest contract/domain tests

It still includes the existing Foundation, auth/persistence, Term7, bot integration/persistence, snapshot safety and payment regression gates. GitHub CI additionally runs the full deterministic bot runtime pytest suite.

Current evidence at time of this report commit:

- Persian Text Integrity on the pre-report Stage 1 head: **PASS**
- CI Static Checks on the pre-report Stage 1 head: **PENDING / RUNNING**
- no production/runtime tests were claimed or executed by Stage 1

The report must be updated to `COMPLETE` only after the final Stage 1 head is green in repository CI.

## I. Stage 2 required wiring

Stage 2 must still:

1. merge/reconcile `feature/classops-cross-surface-ux`;
2. build the real owner Website Operations Center/API flows;
3. implement trusted owner preview/confirm persistence for approved domain contracts;
4. persist task/ACK state only through the canonical ClassOps storage family;
5. bridge confirmed delivery intents to the existing notification/runtime subsystem;
6. wire Telegram/Bale as thin adapters with tested capability parity/fallbacks;
7. wire one runtime scheduler/coordinator for background side effects;
8. expose personalized task/exam/ACK/digest reads safely;
9. add safe ClassOps AI runtime config placeholders and later real provider smoke through Codex;
10. prepare exact-SHA runtime migration/deploy/rollback handoff.

## J. No production action

Stage 1 did not deploy, access SSH/FTP/systemd, mutate production storage, change production secrets, run a production migration or merge to `main`.

## Final Stage 1 result

`PROVISIONAL — CI pending`

`STAGE1_INTEGRATION_SHA = PENDING_FINAL_GREEN_HEAD`
