# Contributing Workflow

## File Identity
- What: Lightweight contribution rules.
- Where: Repo root.
- Role: Keeps implementation focused and verifiable.
- Controls: scope discipline, checks before handoff/deploy.
- Dependencies:
  - `AGENTS.md`
  - `CONTRIBUTING-UTF8.md`
  - `DEPLOY.md`
- Read when:
  - starting implementation
  - preparing final handoff

## Scope Discipline
- Keep changes focused on requested scope.
- Avoid unrelated refactors in the same change.
- Keep commit messages clear and narrow.

## Required Local Checks
- After UI text/CSS edits, run:
  - `python scripts/check_text_integrity.py`
- Run additional checks/tests relevant to touched files.

## Deploy Link
- Use canonical deploy process from `DEPLOY.md`.
