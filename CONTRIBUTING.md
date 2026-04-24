# Contributing Workflow

## Codex PR Auto-Merge Policy

- Pull requests opened by Codex should use GitHub **auto-merge**.
- Auto-merge must not bypass repository protections.
- Merge happens only after all required conditions are satisfied:
  - required CI checks are green
  - required reviews/approvals are complete
  - any branch protection rules on `main` are satisfied

## Codex Auto-Approval (Scoped)

- Workflow: `.github/workflows/codex-auto-approve.yml`.
- Trigger: `pull_request_target` (`opened`, `reopened`, `synchronize`, `ready_for_review`).
- It auto-approves only when all are true:
  - PR author login matches configured Codex bot/account (`CODEX_AUTHORS` list in workflow).
  - PR is not a draft.
  - No sensitive paths are changed.
- It **skips** auto-approval when sensitive paths are touched, including:
  - `.github/workflows/**`
  - deploy/infra related files (`scripts/deploy_*`, `DEPLOY.md`, `infra/**`)
  - auth/secrets/billing related files (`storage/auth/**`, `public_html/api/auth_*`, `public_html/assets/site/scripts/auth.js`, `**/secret*/**`, `**/billing/**`)

> Warning: GitHub branch protection may still require a human review, code-owner review, or disallow self-approval by bots/apps. In those cases this auto-approval may be present but not count as the required approving review.

## Required CI Check Name

Branch protection should require this check for PRs:

- `Persian Text Integrity / check`

## Typical Maintainer Flow

1. Codex opens PR to `main`.
2. Reviewer confirms scope and leaves required review (if branch rules still require a human approver).
3. Maintainer enables auto-merge on the PR.
4. GitHub merges automatically when all required checks/reviews pass.
