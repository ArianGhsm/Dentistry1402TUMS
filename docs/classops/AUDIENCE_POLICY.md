# ClassOps Audience Policy (`classops-audience-v1` candidate)

## Status and boundary

This document describes the Audience Engine implemented on
`feature/classops-audience-policy`. The machine-readable contract is
`contracts/candidates/classops-audience-v1.json` and is **candidate-only** until
an integration-stage review explicitly promotes it. This branch does not change
the frozen `classops-v1` item contract or its
`classops-audience-placeholder-v1` field.

Audience resolution answers only **which canonical students are in scope**. It
never chooses Telegram/Bale/site destinations, checks bot links, queues a
notification, sends a message, writes ClassOps state, or creates a parallel
persistent cache. Delivery capability remains a later concern.

## Canonical dependencies

`DentClassOpsAudienceSourceV1` is a read-only dependency. A source returns:

- the exact canonical `cohortKey` requested;
- roster rows containing canonical student number + cohort only;
- an identity-to-cohort index used to reject known cross-cohort references;
- optional canonical selectors (`role`, `group`, `category`) whose memberships
  are already expressed as canonical student numbers;
- opaque source provenance (`type`, `version`).

`DentClassOpsAuthStoreAudienceSource` is the concrete adapter supplied by this
branch. It reads the existing auth store through `dent_load_user_store()`,
`dent_user_cohort_key()`, `dent_normalize_student_number()` and
`dent_normalize_role()`. It exposes the canonical roster and canonical role
selectors only.

The existing rotation/group helpers in `auth_store.php` currently contain
name-based matching. They are therefore **not authorization sources** for this
Audience Engine. Group/category selectors may be supplied only when integration
has a canonical student-number-based source. Display name matching is forbidden.

The adapter does not read `bot_store.php`, Telegram/Bale links, chat IDs,
notification queues, payment state or Term 7 schedule state. A bot link can be a
future delivery-capability fact, never an identity fact.

## Candidate audience model

A normalized spec is:

```json
{
  "version": "classops-audience-v1",
  "resolutionMode": "snapshot",
  "expression": {"op": "whole_cohort"},
  "includeStudentNumbers": [],
  "excludeStudentNumbers": []
}
```

Supported deterministic expression nodes are:

- `whole_cohort`
- `students` with canonical student numbers
- `selector` with `kind = role | group | category` and a canonical selector key
- `any` (OR)
- `all` (AND)
- `not` (complement inside the target cohort roster only)

Top-level `includeStudentNumbers` is applied after the expression and
`excludeStudentNumbers` is applied last. Therefore an ID present in both include
and exclude ends excluded and produces machine-readable warning
`AUDIENCE_INCLUDE_EXCLUDE_CONFLICT`.

### Bounds

Runtime bounds are intentionally strict and deterministic:

| Limit | Value |
| --- | ---: |
| Expression depth | 6 |
| Expression nodes | 64 |
| Children of one `any` / `all` | 16 |
| Student refs in one list | 500 |
| Total explicit student-ref budget | 1000 |
| Canonical cohort roster | 5000 |
| Canonical selectors | 200 |
| Members in one canonical selector | 5000 |

JSON Schema captures structural/list bounds where possible. Depth and aggregate
node/reference budgets are runtime rules in `core.php` / `spec.php` and must
remain covered by domain tests.

## Resolution semantics

`classops_audience_resolve()` always:

1. normalizes and bounds the spec;
2. validates target cohort and owner scope;
3. validates the canonical source context;
4. evaluates the expression deterministically;
5. applies include then exclude sets;
6. sorts recipients, warnings, unresolved references and provenance;
7. computes a deterministic SHA-256 over the canonical resolution payload.

The output contains:

- normalized spec + `specHash`;
- sorted canonical `recipientStudentNumbers`;
- reason/provenance for each recipient;
- unresolved references;
- bounded machine-readable warning counts;
- canonical source provenance;
- `deterministicHash`.

No display names, phone numbers, national codes, Telegram/Bale IDs or other
private profile fields are required or returned.

### Missing and duplicate references

Duplicate student IDs are normalized away and reported as
`AUDIENCE_DUPLICATE_STUDENT_REF`. Duplicate equivalent expression children are
removed; if a boolean node becomes meaningless after deduplication,
normalization rejects it.

An unknown student/selector is not silently treated as a valid member. It is
returned in `unresolvedReferences`. A result may therefore be empty and carries
`AUDIENCE_EMPTY_RESULT`; an empty audience is a valid preview outcome, not
permission to broaden the audience.

Known cross-cohort identity or selector references raise a ClassOps Audience
exception and fail closed. The resolver never falls back to name matching or to
a different cohort.

### NOT safety

A complement is always relative to the canonical roster of the target cohort.
If evaluating the child of `not` creates any unresolved reference, the negation
returns an empty set and warning `AUDIENCE_NEGATION_UNRESOLVED`. This prevents
`NOT(unknown selector)` from accidentally expanding to the entire cohort.

## Snapshot vs live policy

### `resolutionMode = snapshot`

The first resolution is performed against the current canonical source and
returns `resolutionSource = live_initial_snapshot` plus
`classops-audience-snapshot-v1`. The snapshot contains the recipient set,
recipient reasons, unresolved references, warnings and source provenance,
bound to both `specHash` and `deterministicHash`.

A later read using that snapshot reconstructs the same recipient set without
silently adopting later roster/selector membership changes. The snapshot is
validated and its deterministic hash is recomputed. A known identity that now
belongs to another cohort still fails closed; cohort isolation is never frozen
away.

The hash is an integrity/determinism check, **not a cryptographic authorization
signature**. Integration must never trust an arbitrary client-authored snapshot.
Only a server-produced snapshot obtained during owner preview/confirmation may
be persisted as canonical revision data.

### `resolutionMode = live`

Every operation resolves against the current canonical source. Owner preview
must return the resolution hash. Confirmation must re-resolve from canonical
state with that hash as `expectedResolutionHash`. A mismatch raises
`CLASSOPS_AUDIENCE_DRIFT` (HTTP 409), requiring a new preview and explicit owner
confirmation. Silent recipient drift is forbidden.

For consistency, integration should apply the same re-resolution/hash check at
confirmation for initial snapshot creation as well; the snapshot is then pinned
only after that successful confirmation.

## Preview and privacy

`classops_audience_preview()` returns by default only:

- `total`
- `added`
- `removed`
- unresolved count
- warning counts
- cohort, resolution mode and resolution hash

This is the default owner preview and avoids unnecessary identity disclosure.
`includeIdentifiers=true` additionally returns added/removed student numbers and
unresolved references. That detailed variant must remain owner-only and should
be used only where the UI explicitly needs the diff.

`classops_audience_preview()` is an internal/domain helper. API integration must
feed it only resolver results produced server-side; it must not accept an
arbitrary client-supplied object and treat that object as a trusted resolution.

Audience is independent from Destination. Preview must not add platform or bot
capability data.

## Files and dependency chain

Owned module chain:

```text
resolver.php
  -> snapshot.php
    -> resolution.php
      -> context.php
        -> contract.php
          -> spec.php
            -> core.php
              -> source.php
```

`auth_store_source.php` implements the source interface separately so pure
resolver tests can use deterministic fixtures without loading the production
canonical auth store.

## Integration-only handoff

The following wiring is intentionally **not** performed on this feature branch
because each target is an integration-only hotspot.

### 1. `public_html/api/classops_store.php`

At function `classops_normalize_audience()`:

- retain existing `classops-audience-placeholder-v1` compatibility for old
  stored revisions;
- after integration approves/promotes the candidate, explicitly accept
  `classops-audience-v1` and delegate normalization to
  `classops_audience_normalize_spec()`;
- do not reinterpret legacy `academic_group`, `snapshot` or `dynamic` refs by
  guessing what they mean. `classops_audience_upgrade_placeholder()` only
  auto-upgrades `entire_cohort`, `single_student` and `explicit_students`;
  unsupported legacy modes require an explicit migration decision.

Do not weaken revision, optimistic-concurrency or idempotency semantics in the
Foundation.

### 2. `contracts/classops-v1.schema.json`

The current `$defs.audience` is frozen to the placeholder. Integration must
choose one explicit compatibility strategy before modification, for example a
versioned `oneOf` containing the existing placeholder and the approved
`classops-audience-v1` schema, or a new ClassOps item contract version. Do not
silently replace the placeholder definition.

Promote/copy the reviewed candidate schema from
`contracts/candidates/classops-audience-v1.json` only as part of that decision.

### 3. `public_html/api/classops_api.php`

After existing canonical auth/ClassOps includes, load:

```php
require_once __DIR__ . '/classops_modules/audience/resolver.php';
require_once __DIR__ . '/classops_modules/audience/auth_store_source.php';
```

Add owner-only preview/confirm integration rather than exposing a public
resolver action. Reuse the existing `classops_api_owner_for_read()` /
`classops_api_owner_for_mutation()` authorization and CSRF boundary.

Before constructing an Audience source, pass the requested cohort through the
existing `classops_api_validate_cohort($targetCohort)` helper. The domain module
validates cohort format/isolation, but the API remains responsible for proving
that the requested cohort exists in the canonical cohort catalog.

Small adapter flow:

```php
classops_api_validate_cohort($targetCohort);
$source = new DentClassOpsAuthStoreAudienceSource();
$ownerScope = ['role' => 'owner', 'cohortKeys' => [$targetCohort]];
$result = classops_audience_resolve_from_source(
    $source,
    $audienceSpec,
    $targetCohort,
    $ownerScope,
    $trustedServerSnapshot,
    $expectedResolutionHash
);
```

The actual owner scope must be derived from canonical owner authorization; do
not accept arbitrary owner scope from request JSON. Likewise, snapshot input
must come from trusted server-side ClassOps revision state, not raw request JSON.

Preview should call `classops_audience_preview()` only with resolver output.
Confirmation must supply the preview hash as `expectedResolutionHash`; drift is
409 and requires re-preview. Do not create a mutation/send path that bypasses
confirmation.

### 4. Canonical identity/selector adapter

Integration may instantiate `DentClassOpsAuthStoreAudienceSource` only after
`auth_store.php` is loaded. The adapter deliberately exposes only canonical
roster + role selectors.

If future group/category resolution is required, add a small
`DentClassOpsAudienceSourceV1` implementation (or enrich the canonical adapter)
only after a canonical student-number membership source exists. Do not wire
`dent_rotation_assignment_for_name()` or any display-name catalog into Audience
authorization.

### 5. Delivery / notifications / bots

Delivery code should consume only the confirmed canonical recipient student
numbers plus the resolution/snapshot identity required by its own contract.
Then, and only then, transport adapters may determine Telegram/Bale/site
capability. A missing bot link must not remove a person from the Audience
resolution; it is a delivery-capability result.

The existing notification subsystem remains the only notification source of
truth. No ClassOps audience feed/queue should be added.

### 6. Term 7

`academic_term7.php` remains canonical for the official Term 7 schedule and
assignments. Audience does not copy that state. If an eventual Term 7 selector
is required, integration must consume a canonical student-number-based Term 7
interface and keep ClassOps as an operational overlay only.

### 7. `scripts/run_static_checks.sh`

This script is integration-only. Its existing PHP-lint section already lints
all new Audience PHP files, but it does not automatically execute newly named
tests. In the `Unit tests` / ClassOps area, integration should add exactly:

```bash
"$PHP_BIN" scripts/test_classops_audience_policy.php || fail "test_classops_audience_policy.php"
"$PYTHON_BIN" scripts/test_classops_audience_contract.py || fail "test_classops_audience_contract.py"
```

Do not edit the central static-check runner on this feature branch.

## Test commands

Focused feature tests:

```bash
php scripts/test_classops_audience_policy.php
python scripts/test_classops_audience_contract.py
```

Foundation regression tests that integration should also run:

```bash
php scripts/test_classops_foundation.php
python scripts/test_classops_api_http.py
bash scripts/run_static_checks.sh
```

The feature tests contain no production identity/PII and do not require
production storage, secrets, network access, Telegram or Bale.
