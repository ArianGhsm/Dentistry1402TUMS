# ClassOps digests and summaries (`classops-digest-v1` candidate)

## Scope

This branch implements a deterministic, read-only projection engine for **Tomorrow Summary** and **Weekly Digest**. It does not schedule, send, write ClassOps state, write notifications, call AI, resolve audiences, or copy Term 7 schedule data.

The engine is intentionally isolated at `public_html/api/classops_modules/digests/digest_engine.php`. It has no `require`/`include` dependency on ClassOps storage, `academic_term7.php`, notifications, bot runtime, or any parallel feature branch. Integration supplies strict `classops-digest-record-v1` records after upstream authorization/audience resolution.

Candidate machine-readable boundary: `contracts/candidates/classops-digest-v1.json`. It is **not frozen** until integration reviews and promotes it.

## Input boundary

Every request declares `classops-digest-v1`, `tomorrow` or `weekly`, a canonical cohort-scoped viewer, `nowUtc`, the fixed `Asia/Tehran` presentation timezone, an optional common message budget, and a list of strict projection records.

A record is an adapter boundary, not a new source of truth. Its `source` identifies the canonical producer that integration must read:

- `classops`: generic ClassOps item/revision projected from `classops-v1` after audience resolution.
- `schedule`: a schedule projection. Term 7 must be adapted from canonical `academic_term7.php`; schedule rows must never be copied into ClassOps or this module.
- `task` / `requirement`: specialized domain state from the future task/requirement implementation.
- `exam`: specialized exam state.
- `ack`: acknowledgement state.
- `service`: existing canonical service/reminder state.

The engine does **not** infer authorization. Each record must contain explicit `visibility.studentAllowed` and `visibility.ownerAllowed`. Student-specific records may carry a canonical subject (`studentNumber` or `canonicalUserId`); for a student projection, a non-null subject must match the viewer or the record is silently excluded. Cohort mismatch is also excluded. Subject/visibility identity is never emitted into the view model.

Owner/global projection is a separate request with `viewer.scope=owner`; it consumes only `ownerAllowed=true` records. Integration should generate owner digests per cohort rather than building an all-cohort aggregate implicitly.

## Time windows

Canonical timestamps are UTC ISO-8601 strings ending in `Z`. Presentation/calendar math uses `Asia/Tehran` only.

- Tomorrow: next local calendar day, `[00:00, next 00:00)`.
- Weekly: the **next Iranian academic week**, Saturday 00:00 through the following Saturday 00:00, exclusive. If generation happens on Saturday, “next week” starts seven days later rather than the current day.
- All-day records use a local `YYYY-MM-DD` plus `allDay=true` and cannot also use `localDate` as a timed record.
- Timed ranges use half-open overlap semantics. A range ending exactly at the window start does not leak into the new day/week.
- Deadlines use `dueAtUtc`.
- Pending tasks/requirements and unresolved critical ACKs are outstanding state and remain eligible even without a target-window timestamp.

## Deterministic lifecycle, dedupe and ordering

The engine selects the highest revision per `entityRef`. Two non-identical payloads with the same entity/revision fail closed with `CLASSOPS_DIGEST_CONFLICTING_REVISION`. If a surviving record has `supersedesRef`, the referenced older entity is removed.

Cancelled/superseded items are excluded from normal schedule sections and shown as labelled changes when their effective time belongs to the target window. Weekly revised items are also routed to the changes section. Tomorrow revised active items stay in their semantic section with an `اصلاح‌شده` label.

Ordering is stable: critical ACKs first, then fixed type/importance priority, then effective UTC time, title, and canonical entity ref. Input array order never changes output semantics.

## View model and message budget

The output is one platform-neutral view model with a fixed contract version, window metadata, projection scope, sections, structured items, budget metadata, and deterministic Persian `plainText`. No renderer-specific markup, chat ID, Telegram/Bale state, or send instruction exists in the module.

The same budget must be used for site, Telegram and Bale if semantic equality is required. Truncation never silently drops accounting: `totalItems`, `visibleItems`, `omittedItems`, `omittedBySection`, and `truncated` are always returned. A renderer may add platform chrome around the view model, but it must not recompute audience, priority, ordering, dedupe or semantic content.

Empty summaries retain the same section definitions and return a deterministic “برای این بازه موردی ثبت نشده است.” message.

## Integration-only handoff

No integration hotspot was changed in this branch. Integration must explicitly wire these pieces:

1. **Audience adapter** — consume the approved audience-policy implementation and transform authorized ClassOps items into `classops-digest-record-v1`. The digest engine must never receive unresolved audience placeholders as authorization evidence.
2. **Term 7 adapter** — read the canonical Term 7 resolver from `public_html/api/academic_term7.php` and create `source=schedule` records with stable `scheduleRef` values. Do not persist a copied schedule. Existing Term 7 personalized summary/scheduler code currently writes canonical notifications; migration must avoid duplicate messages before retiring or changing that path.
3. **Task/requirement/exam/ACK adapters** — map the integrated specialized domain modules into the common record contract. Do not hard-import feature branches from this module.
4. **Service reminder adapter** — project existing canonical reminder/service state. Do not create a ClassOps reminder database or parallel notification feed.
5. **Site API wiring** — integration may expose a read endpoint through an approved central API boundary. Authorization must happen before projection; student requests must pass canonical identity from existing auth.
6. **Scheduler/delivery wiring** — the future central scheduler decides *when* to build a digest. It passes a deterministic `nowUtc`, builds the view model, and then uses the existing notification/delivery boundary. The digest engine itself remains read-only and send-free.
7. **Bot wiring** — add a site-service client call in the integration-owned bot API client and render the returned view model in shared Telegram/Bale renderer logic. Both adapters must consume the same semantic view model; no per-platform audience/dedupe logic.
8. **Legacy Term 7 collision review** — before enabling ClassOps tomorrow summaries in production, integration must prove that `dent_term7_scheduler_tick()` and the new scheduler cannot produce two summaries for the same student/day.

Likely integration hotspots requiring explicit ownership include `public_html/api/classops_api.php` or another central service route, `public_html/api/academic_term7.php`, the canonical notification/runtime scheduler path, `bot_runtime/dent_bot/site_api.py`, and bot router/renderer files. This branch intentionally does not modify them.

## Tests

Focused tests:

```bash
php scripts/test_classops_digest_engine.php
python3 scripts/test_classops_digest_contract.py
```

Coverage includes empty day/week, mixed types, audience/privacy isolation, cancelled/revised/superseded records, stable ordering/dedupe, Tehran tomorrow boundary, Saturday week boundary, all-day handling, truncation metadata, ACK/task/exam aggregation, injected Term 7 fixture, site/Telegram/Bale semantic equality, strict invalid input, and zero-write/no-hard-import checks.

Full integration/static checks remain integration-owned because `scripts/run_static_checks.sh` is an integration-only hotspot and is not edited here.
