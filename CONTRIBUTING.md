# Contributing Workflow

## Keep It Lean
- Make focused changes only for requested scope.
- Avoid unrelated refactors in the same commit.
- Keep commit messages clear and small in scope.

## Required Local Checks
- After UI text/CSS edits: run `python scripts/check_text_integrity.py`.
- Run any additional checks/tests relevant to touched files.

## Deploy
- Use canonical deploy script and flow in `DEPLOY.md`.