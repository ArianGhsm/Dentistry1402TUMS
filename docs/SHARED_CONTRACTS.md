# Shared contract freeze

The machine-readable freeze is in `contracts/`. Implementations remain the
source of behavior; schemas/tests prevent parallel branches from silently
changing shared boundaries.

## Frozen contracts

- `classops-v1`: generic ClassOps item, lifecycle, revision and optimistic-concurrency semantics.
- `classops-audience-placeholder-v1`: symbolic audience only; no resolver behavior yet.
- `classops-delivery-placeholder-v1`: symbolic destinations only; no destination registry or send behavior.
- `classops-reminder-placeholder-v1`: declarative offsets only; no scheduler behavior.
- `classops-structured-draft-v1`: future manual/AI producer boundary. Unknown
  fields resolve to `null`; deterministic identity/audience resolution occurs
  outside the producer; every draft passes ClassOps validation, owner preview
  and owner confirmation. It cannot write a database or send directly.
  A future DeepSeek/ClassOps credential is feature-scoped, may not reuse or
  fall back to VoiceMatn/STT credentials, and only non-sensitive aggregate
  usage/cost telemetry is permitted.
- `canonical-student-identity-v1`: identity uses canonical student number/user identity, never display-name matching.
- `runtime-site-service-v1`: authenticated service boundary; service credentials are independent of end-user bot links.
- `notification-integration-v1`: the existing notification subsystem stays canonical; ClassOps must not create a parallel feed.

## Change process

A feature worker consumes these contracts but does not silently edit them. A
needed change is proposed with rationale and a failing/updated contract test.
The integration stage reviews compatibility, versions the contract when
necessary, updates producers and consumers, and runs the full suite.
