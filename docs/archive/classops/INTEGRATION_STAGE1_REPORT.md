> **Historical record — non-normative.** This file is preserved as provenance for a completed migration/integration wave. Do not use its branch names, pre-release status, or deployment wording as current instructions. See `AGENTS.md`, `docs/DEVELOPMENT_WORKFLOW.md`, and `DEPLOY.md` for current policy.

# ClassOps Integration Stage 1 Report

## Status

**COMPLETE — domain/contract integration is repository-test-green.**

Stage 1 performs domain consolidation, contract promotion/unification and minimal safe Foundation store/API contract wiring only. It does not deploy production, mutate server/runtime state or merge to `main`.

Frozen base:

`06042c2bf31d64d69d884528d43796c2c3b7ae5d`

Target branch:

`integration/classops-domain-unification-v1`

CI evidence PR (intentionally draft / do-not-merge): `#17`.

The exact executable/schema head immediately before this report-only commit was `48c1cf6b1183cf56baf7c8edcc4e247e04706a5b`; on that head the Stage 1 Domain Matrix, Persian Text Integrity, deterministic static suite and full bot-runtime pytest suite all passed. The release handoff must still verify that the exact final report head is green before using the final SHA.

## A. Locked feature inputs

The feature refs were locked before integration and rechecked after integration work. They remained stable:

| Workstream | Locked SHA | Stage 1 action |
|---|---|---|
| Audience & policy | `4a0f6ce8e31369d719752b7b0249669e05052ea9` | integrated |
| Destination & delivery | `14a02efeab3744f46c53cc758013496c8095ca3a` | integrated |
| AI Copilot | `24d2b648ff8fb64e1e60b93ab2cd4f6f0610c48b` | integrated |
| Tasks & requirements | `06042c2bf31d64d69d884528d43796c2c3b7ae5d` | branch was empty; gap recovered in Stage 1 |
| Exams & critical ACK | `ac319ea63c0ee674b3f50ddcd08c31bb26538427` | integrated |
| Reminder scheduler / Saba | `8e3ed0c628acfeb5b206725233a40a9fcd33672b` | integrated |
| Digests / summaries | `ec370a088c9fd5efe2d5d4c0709a4fe7aff54f6a` | integrated |
| Cross-surface UX | `740e8ec3bd6e8dc3fd278aba3e9732cc7c5261c1` | audited only; reserved for Stage 2 |

No moving-target ref was observed after the lock. `main` remained at the frozen base throughout Stage 1.

## B. Tasks/Requirements gap recovery

`feature/classops-tasks-requirements` contained no commits beyond the frozen base and did not contain the expected tasks/requirements handoff. Stage 1 therefore did not report a false merge; it implemented and tested the missing domain directly on the integration branch.

Added/approved pieces include:

- canonical `contracts/classops-tasks-v1.json` plus candidate provenance;
- `public_html/api/classops_modules/tasks/task_domain.php`;
- `scripts/test_classops_tasks_requirements.php`;
- `docs/classops/TASKS_REQUIREMENTS.md`.

Canonical states are `pending`, `submitted`, `needs_revision`, `completed`, `waived`. Domain mutations use expected state revision plus command/idempotency identity; exact replay is zero-semantic-write; history is non-destructive; requirement progress is bounded/deterministic; overdue is derived; per-student projection fails closed for another student; file/submission content storage remains out of scope.

No user database, platform-local task store or submission-content store was created.

## C. Integrated domains

### Audience

`classops-audience-v1` provides deterministic whole-cohort, explicit canonical students, include/exclude, canonical selector and bounded AND/OR/NOT resolution. Snapshot/live policy, deterministic hashes, cohort isolation and live drift conflict behavior are explicit. Display names and bot links are not identity sources.

### Destination / Delivery

`classops-delivery-v1` provides a symbolic destination registry and deterministic item-revision/audience-snapshot-bound delivery planning. Raw Telegram/Bale identifiers are rejected from ClassOps domain state. Capability state is platform-specific and unknown fails closed. Planning emits deterministic intents/retry/supersession metadata only; it cannot send or write notification state.

### AI Copilot

`classops-ai-draft-v1` provides DeepSeek through AvalAI structured-draft production/validation with injectable transport. Unknown/uncertain values remain `null`; forwarded content is untrusted data; identity/audience/date resolution is deterministic and outside AI; preview/owner confirmation is mandatory; direct mutation/send is forbidden. Dedicated `DENT_CLASSOPS_AI_*` configuration is used with no Voice/STT credential fallback. Persisted raw sensitive prompts are not introduced.

### Tasks / Requirements

`classops-tasks-v1` supplies the canonical per-student state machine, optimistic state revision, idempotent transitions, reopen/waive semantics, bounded requirement progress, derived overdue state, audience-change policy and privacy-safe personalized projection. The generic ClassOps item remains the parent operational item; no parallel task database exists.

### Exam / Critical ACK

Operational exam state remains distinct from the website assessment/payment engine. Exam extension state is reference-only for assessment/payment boundaries. T-3, T-1, night-before and morning-of reminder semantics are represented. Critical ACK is exact item/revision + canonical student + timestamp + explicit user intent; a transport delivery/read receipt can never create ACK. A previous-revision ACK does not satisfy a revised notice.

### Reminder / Saba

`classops-reminder-v1` is a pure deterministic planner with injectable clock, absolute/relative/daypart/bounded recurrence handling, deterministic occurrence/idempotency keys, explicit catch-up policy and future supersession. Cancel/archive/completed items suppress future planning. Saba is reminder-only; username/password/token/session material is rejected and no login automation exists.

### Tomorrow Summary / Weekly Digest

`classops-digest-v1` builds deterministic, bounded, privacy-filtered platform-neutral projections for Tomorrow Summary and Weekly Digest. Official Term7 data is injected/read from its canonical source rather than copied. AI does not invent digest facts and projection reads do not write state.

## D. Contract promotion and graph

`contracts/classops-domain-contracts-v1.json` is the machine-readable Stage 1 promotion graph. Approved canonical contract files now live directly under `contracts/`:

- `contracts/classops-audience-v1.json`
- `contracts/classops-delivery-v1.json`
- `contracts/classops-ai-draft-v1.json`
- `contracts/classops-tasks-v1.json`
- `contracts/classops-exam-ack-v1.json`
- `contracts/classops-reminder-v1.json`
- `contracts/classops-digest-v1.json`

The original candidate artifacts remain under `contracts/candidates/` as provenance. The graph records each candidate-origin path and exact Git blob SHA. `scripts/test_classops_domain_contract_graph.py` verifies the canonical promotion against those locked candidate blobs. The promotion does not change the semantics of historical `classops-v1`.

`classops-surface-v1` remains explicitly Stage 2-only and the cross-surface UX files were not merged in Stage 1.

Historical `classops-v1` plus audience/delivery/reminder placeholder contracts remain readable and are not silently reinterpreted.

## E. Persistence / API decisions

The persisted Foundation remains schema/contract `classops-v1`; no production-data migration is performed in Stage 1.

A narrow compatibility bridge, `public_html/api/classops_domain_store_adapter.php`, now connects new Stage 1 item mutations to promoted domain extension validation without changing the frozen top-level item contract:

- Foundation normalization/lifecycle/revision/idempotency/audit remains authoritative;
- known `classops_tasks_v1` and `exam_ops_v1` extension namespaces are strict/version-checked before create/update writes;
- a type-only patch validates the full prospective known-extension set so a known extension cannot be stranded under an incompatible item type;
- unknown historical extension namespaces remain opaque/readable;
- promoted audience/delivery/reminder contracts are not silently substituted for frozen stored placeholder fields.

`public_html/api/classops_api.php` now uses that adapter for create/update and exposes a non-sensitive read-only `domain-capabilities` action. It does not send, execute scheduler side effects, create notification feed state, or invoke AI as a mutation side effect.

Trusted server-produced audience snapshots, delivery/scheduler execution state, per-student task state and critical-ACK state are not exposed as arbitrary client-authored item extension state. When Stage 2 persists those mutable side states, they must use the **same canonical ClassOps storage family**, explicit schema/version/atomic transaction rules, compatibility/migration tests and rollback; a second ClassOps database/store is prohibited.

## F. Cross-module invariants

Repository tests enforce the Stage 1 invariants, including:

1. AI draft cannot directly mutate/send.
2. Audience resolution is independent from delivery capability.
3. Raw platform identifiers are forbidden in ClassOps delivery/domain persistence.
4. Task/exam/ACK state uses canonical item/student/revision identities.
5. Revised critical notices invalidate prior-revision ACK satisfaction.
6. Rescheduled/revised items supersede prior future reminder/delivery lanes.
7. Cancel/archive suppress future reminder/delivery planning.
8. Same semantic input/revision produces stable hashes/intent/occurrence identities.
9. Digest/task projections fail closed against cross-student/cohort leakage.
10. Official Term7 state is injected/read, never duplicated as a ClassOps schedule database.
11. No parallel notification feed/read-state is introduced.
12. Payment success/order state is not duplicated or rewritten by ClassOps.
13. Saba credentials/session material is forbidden.
14. Historical placeholder-v1 items remain readable.
15. Read/projection/planning paths do not mutate persisted state.
16. Persistence corruption/short-write paths remain fail closed.
17. Cohort isolation remains explicit end to end.

## G. Test gates and evidence

On executable/schema head `48c1cf6b1183cf56baf7c8edcc4e247e04706a5b`:

- **Persian Text Integrity: PASS**
- **ClassOps Stage 1 Domain Matrix: PASS** across contract graph/invariants/API-store bridge, Audience, Delivery, AI, Tasks/Requirements, Exam/ACK, Scheduler/Saba, Digests and Foundation compatibility.
- **Full deterministic static checks: PASS**.
- repository hygiene scan: `1740` tracked/candidate files inspected, PASS.
- text-integrity scan: `1304` text files, PASS.
- Foundation concurrency fixture: `100` concurrent writers, generation `101`, `1000` items / `1001` revisions, no lost-update failure.
- ClassOps API HTTP: owner-only, CSRF, optimistic revision, idempotent retry zero-write and audit PII checks PASS.
- Stage 1 store adapter: `15` checks PASS.
- Delivery domain: `15` focused tests PASS.
- Exam/critical ACK: `47` contract assertions + `69` domain assertions PASS.
- Reminder scheduler/Saba: `36` checks, `0` failures.
- Term7 regression: `55` tests, `0` failures.
- existing PHP unit suite: `79` passed, `0` failed, `0` skipped.
- bot persistence concurrency: `100` writers, PASS.
- payment handoff regression for Telegram/Bale/Voice bridge: PASS; no real transactions.
- full bot runtime pytest: **`213 passed, 92 subtests passed`**.

The full CI initially exposed repository-runner dependency gaps in existing PDF/forensic tests rather than ClassOps logic: qpdf/pikepdf were absent, then OpenCV was absent, and the separate server forensic requirements pin NumPy `2.5.x` while repository CI intentionally remains Python `3.11`. CI was repaired without changing the production/admin forensic requirement contract: qpdf and pikepdf are installed for PDF tests, and the Python-3.11 CI test environment uses NumPy `2.4.6` with the same OpenCV `5.0.0.93` detector build. The separate server forensic installer/requirements remain unchanged.

No environment-dependent production/runtime smoke, live provider call or production deployment is claimed in Stage 1.

## H. Remaining Stage 2 work

Stage 2 still owns:

1. reconcile/merge `feature/classops-cross-surface-ux` against this exact Stage 1 contract graph;
2. build the real owner Website Operations Center flows and owner preview/confirm UX;
3. connect manual/AI drafts to deterministic audience/date/identity resolution and confirmed canonical commit;
4. persist trusted audience snapshots and task/ACK mutable side state inside the canonical ClassOps storage family with explicit versioning;
5. bridge confirmed delivery/reminder intents to the existing notification/runtime subsystem without a parallel feed;
6. wire Telegram and Bale as thin adapters with capability parity/fallback tests;
7. wire one runtime scheduler/coordinator for side effects and leader/idempotency behavior;
8. expose personalized task/exam/ACK/digest reads safely;
9. add safe ClassOps AI runtime configuration placeholders and perform real provider smoke only during the later runtime/release stage;
10. prepare exact-SHA migration/deploy/backup/rollback handoff and production verification.

## I. Residual risks

- The Foundation remains whole-store atomic JSON; very high-volume mutable side state should be load-tested before Stage 2 production persistence is approved.
- Stage 1 does not prove real Telegram/Bale/AvalAI/server behavior; it proves deterministic repository contracts and regressions only.
- The canonical domain contract files intentionally retain byte-identical candidate-origin content; promotion authority/status is the integration registry, which records/locks the origin blobs. A future semantic change must use explicit version discipline rather than editing meaning in place.
- CI's Python-3.11 forensic compatibility dependency is a repository test concern; the separate server/admin forensic venv continues to use its own pinned requirements and must be runtime-verified during release work.

## J. No production action

Stage 1 did not deploy, access SSH/FTP/systemd, mutate production storage, change production secrets, run a production migration or merge to `main`.

## Final Stage 1 result

`COMPLETE — subject to exact-final-head repository workflow confirmation before handoff`

The exact final `STAGE1_INTEGRATION_SHA` is reported out of band after the report-only commit itself is confirmed green; embedding a self-referential commit SHA inside this file is intentionally avoided.
