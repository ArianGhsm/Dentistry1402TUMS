# ClassOps Foundation (`classops-v1`)

## Purpose and boundary

ClassOps is the canonical website-side source of truth for future class
operations. Phase 1 contains only a generic item lifecycle, revision history,
idempotency, audit and an owner-only API. It does **not** resolve audiences,
schedule or send Telegram/Bale messages, create notification deliveries, call
AI, or implement specialized task/exam/requirement behavior.

ClassOps is independent of `bot_links.json`, bot delivery queues, payments,
Term 7 assignments and platform-local SQLite. Telegram and Bale will remain
adapters when delivery is introduced in a later phase.

## Storage choice

Phase 1 uses private atomic JSON at `storage/classops/store.json` (resolved via
`DENT_STORAGE_ROOT`). Evidence collected before implementation showed that the
current PHP CLI has no PDO drivers or SQLite extension, while the production
hosting backup/deploy contract is JSON-based. Introducing SQLite without a
verified production extension, restore path and deployment support would make
this foundation less reliable.

The ClassOps store has a separate lock and generation history. Existing empty,
truncated, malformed or schema-invalid content fails closed and is preserved
for forensics. Writes use a same-filesystem temp file, full-byte verification,
`fflush`, `fsync` where available, decode/schema validation, previous-generation
backup and atomic rename. Reads do not rewrite state. Once the optional store
exists, verified production snapshots treat it as a critical double-read store.

## Contract and item model

The current contract is `classops-v1`, schema version `1`, timezone
`Asia/Tehran`. Canonical timestamps are stored as UTC ISO-8601 strings; Jalali
formatting is presentation-only.

Supported generic types are:

- `announcement`
- `event`
- `class_change`
- `deadline`
- `task`
- `requirement`
- `exam`
- `critical_notice`
- `service_reminder`

An item stores cohort, type, title/description, optional course reference,
timing, location, importance, acknowledgement intent, source, metadata and
extension maps. `audienceSpec`, `deliveryPlan` and `reminderPolicy` are validated
versioned placeholders only; they cause no resolution, scheduling or delivery.
Student audience references are canonical student numbers, never names.
Destination values are symbolic and contain no Telegram/Bale chat IDs.

## Lifecycle

The lifecycle is:

- `draft` → `scheduled`, `cancelled`, or `archived`
- `scheduled` → `draft`, `active`, `cancelled`, or `archived`
- `active` → `completed`, `cancelled`, or `archived`
- `completed` or `cancelled` → `archived`
- `archived` is terminal

Creating a record always starts in `draft`. Phase 1 never interprets
`scheduled` or `active` as permission to publish. Normal hard-delete is absent;
cancellation/archive preserve history.

## Revision, concurrency and idempotency

Every semantic mutation appends a full canonical revision snapshot with a
monotonic revision number, actor reference, reason, hashes and changed fields.
Updates, cancellation and archive require `expectedRevision`; stale writes
return `CLASSOPS_REVISION_CONFLICT` with HTTP 409.

Every mutation requires an idempotency key. A retry with the same actor,
operation, key and payload returns the original result without a write. Reusing
the key with another payload returns `CLASSOPS_IDEMPOTENCY_CONFLICT` (409).
Idempotency records are TTL- and count-bounded.

Audit records contain only opaque canonical actor references, item/revision,
action, timestamp and result code. Phone, national code, OTP, password, bot
token and secrets are never part of this subsystem.

## Authorization and API

`public_html/api/classops_api.php` exposes:

- public, data-free `capabilities`
- owner-only `status`, `list`, `get`, `revisions`
- owner-only POST+CSRF `create`, `update`, `cancel`, `archive`

Authorization reuses `auth_store.php` and `dent_require_owner()`. There is no
ClassOps user, role, token or owner database. List and revision endpoints are
cursor-paginated and bounded to 100 rows.

## Future extension points

Later phases may add Audience resolution, Destination/Delivery planning, an AI
draft producer, specialized Tasks/Requirements, Exams, Digest and Saba logic.
The planned AI workstream is `feature/classops-ai-copilot`: a DeepSeek parser
may produce only a strict structured draft, unknown fields become `null`, and
deterministic resolution remains outside AI. It has no direct storage mutation
or send authority and always ends at owner preview/confirmation. Its credential
must be feature-scoped and independent of all VoiceMatn/speech credentials;
usage telemetry is aggregate and does not retain sensitive prompts.

`academic_term7.php` remains the official Term 7 schedule and assignment source.
ClassOps must only overlay later operational exceptions; it must not copy that
state. Existing notifications remain the future delivery integration boundary.

## Backup, recovery and tests

The canonical deploy snapshot includes `storage/classops/store.json` after its
first owner mutation. The snapshot validates JSON/schema/checksum and requires
two byte-stable reads before promotion. `.generations`, `.corrupt`, locks and
temps are runtime evidence and are excluded from the canonical mirror.

Run focused tests with:

```powershell
php scripts/test_classops_foundation.php
python scripts/test_classops_api_http.py
python scripts/test_bot_snapshot_safety.py
```

The full deterministic regression suite remains:

```bash
bash scripts/run_static_checks.sh
```
