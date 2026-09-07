# ClassOps reminder scheduler — integration handoff

Branch scope ends at the pure planner. **No central scheduler, API hotspot, notification store, bot runtime, deployment file, service unit, or runtime data was changed.**

## Candidate to review/promote

- Candidate contract: `contracts/candidates/classops-reminder-v1.json`
- Pure PHP entry point: `classops_reminder_plan(array $snapshot, ?callable $clock = null): array`
- Current frozen contracts remain unchanged, especially `classops-v1`, `classops-reminder-placeholder-v1`, `notification-integration-v1`, audience/delivery placeholders, and canonical student identity.

Integration must not make this candidate appear frozen merely because the feature branch exists. Contract promotion belongs to the integration stage.

## Required integration dependencies

Before enabling reminder delivery, integration needs all of the following authoritative pieces:

1. **Canonical ClassOps read adapter.** Read current Foundation item/revision/lifecycle data from the existing ClassOps authority. Do not copy items into a scheduler-owned store.
2. **Approved policy adapter/migration.** Convert only an explicitly approved reminder contract into `classops-reminder-v1`. The current Foundation placeholder must not be silently assigned new semantics.
3. **Deterministic audience resolver.** Resolve canonical student identities upstream and freeze an opaque `audience.ref` plus SHA-256 `audience.hash`. Never feed display-name matching or raw Telegram/Bale chat IDs into the planner.
4. **Delivery-policy resolver.** Supply an opaque reference to the approved notification/delivery policy rather than transport instructions or chat IDs.
5. **Canonical occurrence ledger.** Persist occurrence/idempotency/state metadata inside an integration-approved canonical transaction boundary. It must feed `knownOccurrences` into every later planner run. This branch deliberately creates no parallel JSON/SQLite ledger.
6. **Single active coordinator.** Only one runtime leader may materialize external delivery side effects for `classops-reminder-delivery`. The planner only declares this metadata; it does not elect or lease a leader.
7. **Notification integration adapter.** Materialize planner intents through the canonical notification subsystem / `notification-integration-v1`; do not create a ClassOps feed and do not resurrect retired legacy exam-reminder generation.

## Exact tick flow

The integration-owned central scheduler should execute this order on each bounded tick:

1. Capture one UTC `tick_now` and use it as the injected planner clock for the entire run.
2. Under a consistent-read boundary, obtain current ClassOps items/revisions/statuses plus the current reminder occurrence ledger.
3. Resolve/freeze audience and delivery-policy references using canonical IDs. Resolution failure is fail-closed for that item; never substitute broad cohort delivery.
4. Build a strict `classops-reminder-v1` planner snapshot with bounded horizon and max occurrences.
5. Call `classops_reminder_plan(snapshot, clockReturningTickNow)` with no transport/network work inside the call.
6. If `ok=false`, record only bounded operational error telemetry; do not materialize partial intents.
7. In one integration-owned transaction, compare-and-record new `occurrenceKey`/`idempotencyKey` rows and mark eligible `planned` rows named by `supersessions` as superseded. Enforce uniqueness so concurrent scheduler invocations cannot both admit one occurrence.
8. Only after durable occurrence admission, hand due records (`plannedDueAt <= tick_now`) to the canonical notification integration. Notification content remains notification-subsystem-owned.
9. Lease/send/ACK through the existing delivery architecture. Telegram and Bale remain thin adapters. Transport retries reuse admitted idempotency identity instead of rerunning audience guessing.
10. Persist terminal occurrence state through the same canonical integration boundary. Planning success is never delivery success.

## Supersession wiring

Apply supersession only to durable `planned` future rows that still match the returned occurrence key. Do not undo terminal deliveries. Integration must avoid leasing far-future rows.

A ClassOps edit changes the Foundation revision. The new revision naturally creates new occurrence keys while the planner reports old future planned keys as `item_revision_changed`. Cancellation/archive/completion emits no new intent and supersedes old future planned keys.

## Catch-up wiring

Use `plannedDueAt` for scheduler admission time and retain original `dueAt` for audit/idempotency. `latest_once` already limits replay to one newest missed occurrence per item; integration must not independently expand missed occurrences.

## Saba wiring

`serviceRef=saba` means only “remind the user to perform the task in Saba.” Delivery may render an ordinary reminder through the canonical notification layer. It must not request/store username, password, token, cookie, session or authorization material and must not start Saba login automation from this contract.

Any future authenticated Saba connector is a separate feature/security boundary and cannot be inferred from this service reminder.

## Integration hotspots that may need owner-approved edits later

No file below is changed by this branch. Integration may later need to wire the approved design into some of them:

- `public_html/api/classops_store.php` / `classops_persistence.php`: only if the canonical occurrence ledger is approved as part of ClassOps persistence.
- `public_html/api/classops_api.php`: only if an internal/signed runtime read or occurrence mutation contract is approved; do not weaken current owner-only routes.
- `public_html/api/notifications_store.php`: canonical notification materialization/idempotent source mapping, without a parallel feed.
- central runtime scheduler/router: bounded tick, coordinator ownership, planner invocation and due admission.
- `bot_runtime/dent_bot/runtime.py` / `site_api.py`: only if runtime consumes an approved signed site-side claim contract; keep adapters transport-thin.
- `scripts/run_static_checks.sh` and deployment checks: add the focused test only under integration ownership of those hotspots.

Do not wire through `academic_term7.php`; Term 7 remains canonical academic data and ClassOps reminders are operational overlays/exceptions only.

## Required integration tests

Integration should prove at minimum:

- two concurrent scheduler ticks admit one occurrence only;
- restart/replay with the durable ledger emits no duplicate notification;
- edit/reschedule/cancel/archive supersession is atomic with occurrence admission;
- audience resolver failure never broadens recipients;
- no raw Telegram/Bale chat ID enters ClassOps reminder persistence;
- notification materialization uses the canonical notification subsystem and no parallel feed;
- legacy exam-reminder retirement remains intact while ClassOps exam intents use the new integration path;
- only the active coordinator can produce external side effects;
- send-success/ACK-retry does not resend;
- Saba service reminders never contain credentials/session data;
- runtime timestamps remain UTC and presentation formatting remains Tehran/Jalali outside the planner.

## Feature-branch verification commands

```bash
php -l public_html/api/classops_modules/scheduler/classops_reminder_policy.php
php -l public_html/api/classops_modules/scheduler/classops_reminder_planner.php
php -l scripts/test_classops_scheduler.php
php scripts/test_classops_scheduler.php
python -m json.tool contracts/candidates/classops-reminder-v1.json
```

Focused result produced while developing this branch: `ClassOps scheduler tests: 36 checks, 0 failures.` Re-run after integration; this is not production/runtime verification.
