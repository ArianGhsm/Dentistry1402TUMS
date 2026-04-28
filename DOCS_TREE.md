# Documentation Tree

## Purpose
This file is the canonical map of non-code and non-site-content documentation in this repository.
It is optimized for fast AI onboarding and low ambiguity.

## Scope
Included:
- Versioned docs/instructions in Git.
- Local AI context notes that affect assistant execution.

Excluded from rewrite-by-default:
- Machine-generated logs, cookies, captcha dumps, and temporary artifacts under `.codex-local/`.
- Raw diagnostic snapshots prefixed `_remote_*`.
- Git internal files under `.git/`.

## Tree
- `/AGENTS.md`
- `/DEPLOY.md`
- `/CONTRIBUTING.md`
- `/CONTRIBUTING-UTF8.md`
- `/audit.txt`
- `/server-only/README.md`
- `/.codex-local/site-refactor-context.md` (local, not versioned)

## File Registry

### 1) `AGENTS.md`
- What: Master operational policy for coding agents.
- Where: Repo root.
- Role: Defines product boundaries, hard constraints, execution flow, QA criteria, and deploy default.
- Controls:
  - chat-vs-non-chat UX boundary
  - shared auth/identity source of truth
  - mandatory messenger capability baseline
  - persistence/sync continuity rules
  - anti-false-success behavior
- Dependencies:
  - `public_html/api/auth_api.php`
  - shared session/auth store
  - `scripts/deploy_public_html.ps1`
  - `scripts/check_text_integrity.py`
- Read when:
  - before any implementation
  - before changing chat/auth/storage/deploy
  - before final reporting

### 2) `DEPLOY.md`
- What: Canonical deploy contract and command reference.
- Where: Repo root.
- Role: Defines exact deploy order and safe overrides.
- Controls:
  - default order: local validation -> host deploy -> live health-check -> GitHub sync
  - failure-stop behavior by stage
  - deploy path/scope safety
- Dependencies:
  - `scripts/deploy_public_html.ps1`
  - `scripts/deploy_public_html.sh`
  - `.codex-local/deploy/host_last_deploy.json`
- Read when:
  - before any deploy
  - when diagnosing deploy/state mismatch

### 3) `CONTRIBUTING.md`
- What: Lightweight contribution workflow.
- Where: Repo root.
- Role: Keeps change scope focused and quality checks explicit.
- Controls:
  - small-scoped changes
  - required local checks
  - deploy linkage to canonical runbook
- Dependencies:
  - `CONTRIBUTING-UTF8.md`
  - `DEPLOY.md`
- Read when:
  - starting a task
  - preparing to commit/deploy

### 4) `CONTRIBUTING-UTF8.md`
- What: Persian/UTF-8 and text-integrity policy.
- Where: Repo root.
- Role: Prevents text corruption and locale regressions.
- Controls:
  - UTF-8 persistence
  - no mojibake/placeholder corruption
  - Persian display defaults for user-facing numbers/date/time
  - safe bidi policy
  - required integrity check command
- Dependencies:
  - `scripts/check_text_integrity.py`
  - styles under `public_html/assets/site/styles/`
- Read when:
  - editing UI text, locale formatting, CSS
  - reviewing Persian rendering bugs

### 5) `audit.txt`
- What: Current architecture and gap snapshot.
- Where: Repo root.
- Role: Practical technical context for planning/refactor decisions.
- Controls:
  - current system map
  - what exists vs what is missing
  - recommended implementation order
- Dependencies:
  - frontend files under `public_html/assets/site/`
  - backend APIs under `public_html/api/` and `public_html/chat/`
- Read when:
  - before major architecture changes
  - before chat modernization decisions

### 6) `server-only/README.md`
- What: Runtime data boundary definition.
- Where: `server-only/`.
- Role: Prevents committing runtime/secrets and documents expected layout.
- Controls:
  - location of storage/tmp/sessions/backups/secrets
  - required environment variables for runtime path mapping
- Dependencies:
  - `.gitignore`
  - runtime bootstrap/env wiring
- Read when:
  - changing storage/session paths
  - setting up runtime on a new machine/host

### 7) `.codex-local/site-refactor-context.md` (local)
- What: Condensed AI working context for ongoing refactor.
- Where: `.codex-local/`.
- Role: Fast local memory for assistant runs without scanning long history.
- Controls:
  - active boundaries and hard rules
  - architecture map and known limits
  - completion/deploy reporting expectations
- Dependencies:
  - `AGENTS.md`
  - `audit.txt`
- Read when:
  - starting local AI sessions
  - resuming interrupted refactor work

## Maintenance Rules
- If any policy changes in `AGENTS.md`, update this file in the same change.
- Keep one source of truth per concern:
  - behavior policy -> `AGENTS.md`
  - deploy process -> `DEPLOY.md`
  - text integrity -> `CONTRIBUTING-UTF8.md`
  - architecture snapshot -> `audit.txt`
- Avoid duplicating long policy paragraphs across files; link instead.
