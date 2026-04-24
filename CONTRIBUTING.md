# Contributing Workflow

## Codex PR Auto-Merge Policy

- Pull requests opened by Codex should use GitHub **auto-merge**.
- Auto-merge must not bypass repository protections.
- Merge happens only after all required conditions are satisfied:
  - required CI checks are green
  - required reviews/approvals are complete
  - any branch protection rules on `main` are satisfied

## Required CI Check Name

Branch protection should require this check for PRs:

- `Persian Text Integrity / check`

## Typical Maintainer Flow

1. Codex opens PR to `main`.
2. Reviewer confirms scope and leaves required review.
3. Maintainer enables auto-merge on the PR.
4. GitHub merges automatically when all required checks/reviews pass.
