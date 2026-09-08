# ClassOps cross-surface UX (`classops-surface-v1` candidate)

## Scope and authority

This branch adds a presentation/action layer for the ClassOps Operations Center across website, Telegram and Bale. It does not change `classops-v1`, does not add a second source of truth, and does not wire central runtime/API hotspots.

The machine-readable surface proposal is `contracts/candidates/classops-surface-v1.json`. Its status is **candidate-not-frozen**. Integration must review and explicitly promote/version it before another subsystem treats it as a frozen shared contract.

At the declared base SHA, only the existing owner-only ClassOps foundation capabilities are live: list/get/status, draft creation, generic update/status transitions, cancel and archive. Audience resolution, destination resolution/delivery, specialized task/requirement behavior, exam/critical ACK persistence, reminder planning, tomorrow/weekly summaries and AI provider calls are not live capabilities. Their controls are disabled or preview-only; they never report fabricated success.

`scheduled` and `active` remain generic lifecycle statuses only. They do not grant delivery authority.

## Shared action semantics

| Action | Roles | Base behavior | Confirmation | expectedRevision | Idempotency |
|---|---|---|---|---|---|
| `items.list` / `item.get` | owner | live read | no | no | no |
| `draft.create` | owner | live; status forced to `draft` | yes | no | yes |
| `item.preview_diff` | owner | local deterministic preview | no | no | no |
| `item.edit` | owner | live generic update | yes | yes | yes |
| `item.schedule_intent` | owner | live status-only update to `scheduled`; no send authority | yes | yes | yes |
| `item.activate_intent` | owner | live status-only update to `active`; no send authority | yes | yes | yes |
| `item.cancel` / `item.archive` | owner | live foundation lifecycle mutation | yes | yes | yes |
| `audience.preview` | owner | disabled until resolver integration | no | no | no |
| `destination.preview` | owner | disabled until delivery integration | no | no | no |
| `task.requirement_view` | owner, student | disabled until specialized domain integration | no | no | no |
| `exam.view` | owner, student | disabled until specialized exam authorization integration | no | no | no |
| `exam.ack` | student | disabled until critical-ACK persistence/authorization integration | yes | yes | yes |
| `reminder.preview` | owner, student | disabled until reminder integration | no | no | no |
| `summary.tomorrow` / `summary.weekly` | owner, student | disabled until authorized summary backend exists | no | no | no |
| `ai.draft_request` | owner | local request preview only; no provider call/write/send | no | no | no |

Every destructive or semantic mutation produces a typed action intent first. An intent requiring confirmation cannot be sent through the website client until it is explicitly confirmed. Revision-sensitive actions require `expectedRevision >= 1`; mutation intents carry deterministic surface idempotency metadata.

## Website Operations Center

The isolated owner surface is `/classops/`, with assets under `public_html/assets/classops_ops/`.

- Owner controls start hidden. The page uses the canonical ClassOps owner `status` read to authorize the surface; an ordinary user cannot reveal owner controls merely through cached browser identity.
- Existing live mutations call only `/api/classops_api.php` actions already implemented by `classops-v1`.
- CSRF is obtained through the existing authenticated `authSessions` response from `/api/auth_api.php?action=authSessions`; the ClassOps surface creates no token store.
- Draft creation forces `status=draft` even if caller input attempts another status.
- Edit and lifecycle operations show a confirmation model carrying item ID, expected revision and idempotency key.
- HTTP 409 is rendered as an explicit stale-revision conflict and requires a refresh/review rather than silent retry.
- AI free text is normalized into a local preview only. No AI/provider endpoint is called and the preview has no mutation or send authority.
- Pending integrations use disabled controls and `backend-integration-pending` semantics.
- RTL Persian copy is deterministic. Loading, error, empty, conflict and disabled states are explicit; keyboard focus and reduced-motion behavior are included.
- The ClassOps JS creates no `localStorage`/`indexedDB` state.

The route is intentionally not added to the central `/admin/` navigation in this feature branch. Integration may add a link after review without turning `/admin/` into a second ClassOps management implementation.

## Telegram / Bale parity

`bot_runtime/dent_bot/classops_surface/` owns platform-neutral actions and view models plus thin rendering adapters.

Both platforms receive the same action names, role gates, Persian copy, enabled/disabled state and confirmation semantics. Telegram may carry optional button style metadata; Bale omits that cosmetic field when necessary. This is the documented equivalent fallback and does not alter action identity.

A callback contains only an opaque surface action key such as `classops:item.edit`. `intent_from_callback()` converts it to an **unconfirmed typed intent** only. It performs no database mutation, ClassOps API call, message send, or bot-state mutation. Item/revision/nonce context must be supplied by the later integration layer after canonical authorization.

Disabled actions map to a non-semantic `classops:disabled` callback and cannot produce an action intent.

## Owner / student separation

Owner actions and student views are independent menus. Owner mutation controls never appear in the student menu. Student-facing task/requirement, exam/ACK, reminder and tomorrow/weekly views are modeled but remain disabled at the foundation base. Integration must enable each only from backend capability **and** canonical per-user authorization; platform linkage alone is not authorization.

Critical ACK is student-only in this candidate. It remains disabled until the exams/ACK workstream exposes authoritative eligibility, revision and persistence semantics.

## Privacy and identity

The surface contract forbids raw Telegram/Bale chat IDs and credential/private identity fields in intents. Payload validation rejects chat/platform IDs, bot/user tokens, secrets, passwords, OTPs, phone/mobile fields and national-code fields.

Canonical student identity must use the approved student-number/canonical-user boundaries of `canonical-student-identity-v1` after a downstream contract explicitly permits the relevant field. Display-name matching is never an identity mechanism.

No runtime JSON/SQLite, sessions, secrets, logs, backups, production data or private user content are added by this branch.

## Integration-only handoff

The files below are intentionally unchanged in this branch.

### `public_html/api/classops_api.php`

In the main action router inside the existing `try` block:

1. Preserve current `capabilities`, `status`, `list`, `get`, `revisions`, `create`, `update`, `cancel` and `archive` request/response shapes used by this surface.
2. After the audience workstream is integrated, add an owner-authorized **read/preview** action for resolved audience output. It must consume canonical IDs/snapshots and must not resolve by display name.
3. After destination/delivery integration, add destination preview separately from send/delivery authority. Preview must not enqueue delivery.
4. After task/requirement and exam/ACK workstreams are integrated, add authorized student reads and ACK mutation endpoints. Student endpoints must derive the current canonical user from `auth_store.php`; do not accept caller-supplied student identity as authorization.
5. ACK mutation must require authoritative expected revision and idempotency metadata and fail closed on stale/unauthorized state.
6. Reminder/tomorrow/weekly endpoints should be added only after their canonical domain/scheduler dependencies exist; reads must not create notification or digest state.
7. Do not add an AI mutation endpoint merely for this surface. The future structured-draft producer must terminate at owner preview/confirm under `classops-structured-draft-v1`.

### `public_html/api/classops_store.php`

Do not change the generic foundation merely to make pending controls look enabled. If integration needs new capability advertisement or a promoted surface/domain contract, version it explicitly and keep lifecycle/revision/idempotency semantics backward compatible. `scheduled`/`active` must continue to be non-delivery-authoritative until the delivery contract says otherwise.

### `bot_runtime/dent_bot/app.py`

At `DentBotApp.handle()` / the existing callback dispatch path:

1. Recognize `classops:*` only after normal platform/user/link authorization has run.
2. Delegate rendering and callback parsing to `dent_bot.classops_surface`; do not duplicate Telegram/Bale business rules in `app.py`.
3. Resolve item/revision/context through the authenticated site-service client, then use `intent_from_callback()` to build an unconfirmed action intent.
4. Require an explicit confirmation interaction before any semantic mutation. Do not map a callback directly to ClassOps DB/state mutation.
5. Add an owner ClassOps menu entry only for the canonical owner; add student views only when the backend returns an authorized capability for that canonical user.
6. Keep Telegram and Bale on the same view model; only transport rendering/fallback differs.

### `bot_runtime/dent_bot/site_api.py`

After matching backend endpoints are integrated, add typed ClassOps service calls here rather than transport-specific HTTP calls in adapters. Reuse `runtime-site-service-v1` HMAC/service authentication, keep service credentials independent of end-user bot links, and never send raw chat IDs as ClassOps domain data.

### Central scheduler/router and notification integration

Reminder dispatch, tomorrow summary, weekly digest and delivery scheduling belong to the later central scheduler/router integration. They must reuse `notification-integration-v1` and the existing canonical notification subsystem; no ClassOps parallel notification feed or read-state store may be created.

### Optional website navigation wiring

After integration review, `public_html/admin/index.html` may expose a navigation link to `/classops/`. The ClassOps UI tself remains isolated; `/admin/` should not duplicate its CRUD implementation.

## Dependencies before capabilities can be enabled

- Audience preview: integrated/promoted audience resolver contract.
- Destination preview/delivery: destination registry + delivery contract; existing notification boundary retained.
- Task/requirement views: specialized task/requirement domain and canonical student authorization.
- Exam/critical ACK: specialized exam/ACK domain, access policy and ACK persistence.
- Reminder/tomorrow/weekly: integrated domain reads plus central scheduler/digest design.
- AI parsing: `classops-structured-draft-v1` producer implementation with independent ClassOps AI credential and owner preview/confirm.

## Tests

Focused branch tests:

```bash
PYÓ”UX›İÜ[[YH]Ûˆ[H[š]\İ\ØÛİ™\ˆ\È›İÜ[[YKİ\İÈ\	İ\İØÛ\ÜÛÜ×Üİ\™˜XÙKœIÂœ]ÛˆØÜš\Ëİ\İØÛ\ÜÛÜ×ØÜ›ÜÜ×Üİ\™˜XÙWØÛÛ˜XİœB››ÙHØÜš\Ëİ\İØÛ\ÜÛÜ×ØÜ›ÜÜ×Üİ\™˜XÙWÜÚ]KšœÂ˜‚‘›İ[™][Ûˆ™YÜ™\ÜÚ[ÛˆÚXÚÜÈÈ[ˆ[ˆ[ˆ[YÜ˜]YÚXÚÛİ]‚‚˜˜\ÚœØÜš\Ëİ\İØÛ\ÜÛÜ×Ù›İ[™][Û‹œœ]ÛˆØÜš\Ëİ\İØÛ\ÜÛÜ×Ø\WÚœB˜˜\ÚØÜš\ËÜ[—Üİ]X×ØÚXÚÜËœÚ˜‚•\È™X]\™Hœ˜[˜ÚÙ\È›İ[ÙYHHÙ[˜[İ]XËXÚXÚÈ[›™\‹ÒHÛÜšÙ›İË\ŞHØÜš\Ë[[YHÙ\šXÙ\Ë›ÙXİ[ÛˆİÜ˜YÙHÜˆÙ\™\ˆİ]Kˆ\Ş[Y[[™]™H[[YH™\šYšXØ][Ûˆ™[XZ[ˆÙ\\˜]H[YÜ˜][Û‹Ü™[X\ÙHÛÜšË‚