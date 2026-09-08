# ClassOps Destination Registry & Delivery Planning

Status: **candidate / planning-only**. This module extends the existing `classops-v1` foundation without changing its frozen contracts or the canonical notification/runtime stores.

## Scope and boundaries

This workstream owns symbolic destination registration, platform capability modelling, deterministic delivery intent planning, bounded retry metadata, and future-intent reconciliation. It does **not** send Telegram/Bale messages, resolve audience membership, authorize identities, mutate the notification store, create a parallel feed/read state, run a worker, or wire the central scheduler/router.

Audience and destination remain independent. The planner accepts an opaque versioned audience reference/hash and a destination alias; it never expands audience members. Private-delivery capability is only a transport capability check. Canonical identity authorization must still be rechecked by the future runtime consumer using the existing identity system.

## Destination Registry

Registry version: `classops-destination-registry-v1`.

Supported destination kinds are `private_recipient`, `class_group`, `channel`, and platform-neutral `logical`. Concrete destinations contain a symbolic `bindingRef`; raw Telegram/Bale chat/user IDs are forbidden in ClassOps domain persistence. A future runtime adapter resolves `bindingRef` against canonical runtime configuration outside ClassOps.

Logical destinations contain concrete routes. Cross-cohort routes and nested logical routes are rejected. A fallback route is accepted only when it explicitly declares `semanticEquivalent=true`; this prevents silent substitution of a semantically different destination.

## Capability evidence model

Capability states are `supported`, `unsupported`, and `unknown`. `unknown` is fail-closed. Capability overrides require an evidence string.

Repository evidence currently supports Telegram text/private/group/channel/silent/protected-content behavior. Bale text/private/group behavior is represented, while Bale channel/silent/protected-content remain `unknown` until integration ownership supplies evidence. The planner never infers Bale parity from Telegram.

## Delivery intent semantics

Contract candidate: `contracts/candidates/classops-delivery-v1.json`.

An intent is reference-based and deterministic. Its semantic hash binds item ID + revision + snapshot hash, audience ref/version/hash, registry ID/version, requested and resolved destination aliases, symbolic bindingRef, platform, occurrence, required capabilities, and fallback origin. Replanning the same semantic occurrence produces the same `intentId` and `dedupeKey`. A newer item revision produces a different intent and, for the same requested/resolved/platform/occurrence lane, points to the previous intent with `supersedesIntentId`.

`planned` never means `sent`. Platform outcomes are independent: a Bale outage does not mark Telegram failed, and a Telegram success does not imply Bale success. `platformMode=require_all` withholds intents when a required primary route is unavailable. Explicit equivalent fallback may reuse a healthy already-planned route while retaining the failed primary outcome in the plan.

## Cancellation, archive, reschedule, retry

For future intents, cancelled/archived items reconcile to `cancel`; a newer item revision reconciles to `supersede`. Due/past intents return `runtime_reconcile` because final claim/send state belongs to the future runtime consumer. A changed occurrence key/time produces a new deterministic intent rather than rewriting the old semantic occurrence.

Retry metadata is bounded: maximum 5 attempts, base backoff 30 seconds, maximum backoff 900 seconds. This module defines metadata only; it contains no worker or sleep/network loop.

## Privacy and notification boundary

Delivery intents and audit projections contain references/hashes, not message title/body/content, raw recipient IDs, bot tokens, or raw platform chat IDs. User-visible notification delivery remains exclusively in the existing notification subsystem. These modules contain no notification enqueue/store mutation call and no Telegram/Bale send/network call.

## Integration handoff — exact interfaces

The future **bot/runtime integration owner** should consume `classops_delivery_adapter_envelope($intent)` (`classops-delivery-adapter-v1`) and implement these steps outside this module:

1. Resolve `destination.bindingRef` using canonical runtime configuration; never copy the resolved raw platform ID back into ClassOps persistence.
2. For `private_recipient`, resolve the actual recipient from canonical identity + audience references and recheck current authorization before send.
3. Atomically claim/dedupe by `dedupeKey`; `planned` is not a send receipt.
4. Recheck item revision/status and future-intent reconciliation immediately before transport.
5. Execute the platform-specific send through the existing thin Telegram/Bale adapters.
6. Record Telegram and Bale results independently; never collapse a partial platform failure into a false shared semantic state.
7. If a user-visible notification/feed event is required, invoke the **existing canonical notification subsystem**; do not add ClassOps notification/read-state storage.

The future **notification integration owner** needs only a reference/result bridge: `intentId`, item reference, audience hash/reference, destination alias/platform, and a normalized result code. Message/feed content and read state remain owned by the existing notification subsystem.

No changes are required or authorized in `classops_api.php`, `classops_store.php`, `classops_persistence.php`, `notifications_store.php`, bot runtime central router/scheduler, service units, deploy scripts, or dependency manifests in this branch.

## Tests

Focused commands:

```bash
php -l public_html/api/classops_modules/delivery/destination_registry.php
php -l public_html/api/classops_modules/delivery/delivery_planner.php
php -l scripts/test_classops_delivery_domain.php
php scripts/test_classops_delivery_domain.php
python3 scripts/test_classops_delivery_contract.py
```

The candidate contract must be promoted only by integration ownership after runtime/notification wiring and platform capability evidence are reviewed. Promotion must not silently mutate a frozen contract.
