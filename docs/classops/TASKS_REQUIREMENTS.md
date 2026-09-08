# ClassOps Tasks & Requirements (`classops-tasks-v1`)

## Stage 1 status

The original parallel branch `feature/classops-tasks-requirements` remained at the frozen base SHA and contained no implementation. Stage 1 integration therefore closes that gap here rather than pretending the workstream completed.

This domain is layered on canonical ClassOps items of type `task` or `requirement`. It does not create a user database, a transport-specific task store, or a submission-content store.

## Canonical states

Per-student state uses canonical student numbers and the lifecycle:

- `pending`
- `submitted`
- `needs_revision`
- `completed`
- `waived`

Completed or waived work can be explicitly reopened to `pending`; history is never deleted. Mutations use an expected state revision and a canonical command/idempotency identifier. Reusing a command for another semantic transition fails closed.

## Requirement rules

Requirements support deterministic `binary` or bounded `count` targets. Progress is numeric metadata only. Uploaded files, free-form submission bodies and other private content are outside this domain and must use an explicitly approved storage workflow later if required.

## Audience and identity

Assignment is bound to the canonical ClassOps audience resolution/snapshot. Display names and bot links are never identity facts. Audience changes are either frozen by snapshot or require explicit re-evaluation; silent recipient drift is forbidden.

## Privacy and projections

Student projections require the exact canonical student number. A student cannot read another student's state. Overdue is derived from the canonical due time and current clock; reads do not write state.

## Persistence handoff

Stage 1 intentionally defines the domain state contract but does not introduce a second database or silently change production storage schema. Stage 2 must persist per-student task state only inside the canonical ClassOps storage family, with atomic/fail-closed semantics, schema/version checks, backup compatibility and migration/rollback tests.

## Tests

```bash
php scripts/test_classops_tasks_requirements.php
```
