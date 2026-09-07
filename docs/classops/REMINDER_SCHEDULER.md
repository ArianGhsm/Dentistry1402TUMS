# ClassOps deterministic reminder planner

Status: **feature-branch candidate; not integrated or production-enabled**.

This module adds a pure scheduling domain for ClassOps. It consumes an immutable planner snapshot and returns reminder intents plus supersession metadata. It does not write ClassOps storage, notification storage, bot state, files, databases, queues, or network transports.

The frozen `classops-v1` Foundation and its `classops-reminder-placeholder-v1` remain unchanged. The candidate schema is `contracts/candidates/classops-reminder-v1.json`; integration must explicitly review/promote a contract before central wiring. No placeholder policy is silently reinterpreted by this branch.

## Files

- `public_html/api/classops_modules/scheduler/classops_reminder_policy.php`: strict policy validation and normalization.
- `public_html/api/classops_modules/scheduler/classops_reminder_planner.php`: deterministic occurrence planning and supersession calculation.
- `contracts/candidates/classops-reminder-v1.json`: strict candidate input/output schema.
- `scripts/test_classops_scheduler.php`: focused domain/security tests.

## Time model

Machine time is canonical UTC in `YYYY-MM-DDTHH:MM:SSZ`. Local calendar interpretation uses `Asia/Tehran` only. Jalali formatting is presentation-layer work and is intentionally absent here.

Daypart rules have deterministic Tehran wall-clock meanings:

| Daypart | Tehran time |
|---|---:|
| `morning` | 08:00 |
| `afternoon` | 15:00 |
| `evening` | 19:00 |
| `night` | 21:00 |

The planner receives a clock callable. Tests inject a fixed UTC clock; production integration should inject the runtime tick timestamp rather than hiding wall-clock reads inside business logic.

## Policy rules

Every policy is versioned as `classops-reminder-v1`, has timezone `Asia/Tehran`, an explicit catch-up policy, and a bounded rule list.

Supported deterministic rules:

- `absolute`: one canonical UTC instant.
- `relative`: signed offset from `startsAt` or `dueAt`.
- `daypart`: Tehran local day offset from an anchor, then one named daypart.
- `recurring`: approved only for `service_reminder`; daily or weekly, interval 1..4, explicit local time/start, optional end, explicit max occurrences.

The generic rule set supports exam patterns without exam-specific transport logic. T-3 and T-1 are relative offsets from `startsAt`; night-before is `night` with `dayOffset=-1`; morning-of is `morning` with `dayOffset=0`.

Task/deadline rules are computed from the snapshot and never mutate the task/deadline item.

## Saba boundary

Saba is only an opaque `serviceRef="saba"` on a ClassOps `service_reminder`. This module performs no login, navigation, credential capture, session handling, CAPTCHA work, or upstream automation.

The planner recursively rejects credential/session-shaped fields such as username, password, credential, session, cookie, token, secret, and authorization with `CLASSOPS_REMINDER_CREDENTIAL_MATERIAL_FORBIDDEN`. Audience references that look transport/chat-specific are rejected as well; raw Telegram/Bale chat IDs are not scheduler-domain identifiers.

## Planner snapshot and identity

A caller supplies canonical ClassOps item IDs/revisions/status/timing, a resolved opaque audience reference plus SHA-256 hash, an opaque delivery-policy reference, candidate reminder policy, durable `knownOccurrences`, and bounded horizon/output limits.

The planner does **not** resolve audience membership or match display names. Audience resolution is deterministic upstream work.

`occurrenceKey` hashes contract version, item ID, item revision, rule ID, and original due instant. `idempotencyKey` additionally binds audience hash and delivery-policy reference. Passing durable known occurrences back into later runs suppresses repeats.

The pure planner intentionally does not own a persistence ledger. Adding scheduler-owned JSON/SQLite here would create a parallel source of truth; integration must place occurrence state inside an approved canonical ClassOps/runtime transaction boundary.

## Supersession

For a known occurrence still in `planned` state and due in the future, the planner reports supersession when the item disappeared, was cancelled/archived/completed, changed revision, or the current revision no longer produces the occurrence.

Leased/delivered/failed rows are not retracted. Integration should lease only due occurrences under one active coordinator, so edits can supersede future rows before external side effects exist.

## Catch-up and bounds

- `skip`: missed occurrences are not replayed.
- `latest_once`: at most the newest eligible missed occurrence **per item** is emitted. It retains original `dueAt`, sets `catchUp=true`, and uses current injected time as `plannedDueAt`.

Catch-up age is capped at seven days. Planning horizon is capped at 31 days; output is capped at 128 occurrences per run; items and known-occurrence input lists are bounded. Long-lived recurrence jumps close to the active window rather than walking entire history.

## Coordination metadata

Every intent contains:

```json
{
  "requirement": "single_active_leader",
  "scope": "classops-reminder-delivery",
  "sideEffectOwner": "integration-runtime",
  "plannerSideEffects": "none"
}
```

This is interface metadata, not leader-election or distributed-queue implementation.

## Failure behavior

Validation failures return `ok=false`, zero intents/supersessions, and one machine-readable `CLASSOPS_REMINDER_*` error. Unknown fields, malformed/non-UTC timestamps, missing anchors, unsupported recurrence/timezone/version, credential-shaped material, bad audience hashes and oversized limits fail closed. Unexpected exceptions become `CLASSOPS_REMINDER_INTERNAL_ERROR` without internal details.

## Verification

```bash
php -l public_html/api/classops_modules/scheduler/classops_reminder_policy.php
php -l public_html/api/classops_modules/scheduler/classops_reminder_planner.php
php -l scripts/test_classops_scheduler.php
php scripts/test_classops_scheduler.php
python -m json.tool contracts/candidates/classops-reminder-v1.json
```

Central static-check/deploy scripts are integration hotspots and intentionally remain unchanged on this branch.
