# AGENTS.md

## File Identity
- What: Persistent execution contract for coding agents in this repository.
- Where: Repo root.
- Role: Source of truth for scope, constraints, workflow, and reporting.
- Controls: product boundary, auth boundary, data continuity, QA/deploy flow.
- Primary dependencies:
  - `public_html/api/auth_api.php`
  - shared auth/session store
  - `scripts/check_text_integrity.py`
  - `scripts/deploy_public_html.ps1`
- Read when:
  - before starting any task
  - before modifying chat/auth/storage/deploy
  - before final status reporting

## Scope Boundary
- This is a multi-section academic site, not a messenger-only app.
- Telegram-grade UX upgrades are allowed only for `/chat/` and chat-related profile/settings surfaces.
- Non-chat sections must stay domain-specific and must not be reshaped into messenger UX.

## Shared Auth And Identity (Non-Negotiable)
- Messenger must use shared site auth/account as the only source of truth.
- Do not create separate messenger auth or profile identity stores.
- Identity/role/session must come from shared APIs/session (`/api/auth_api.php`, shared auth store/session).

## Minimum Messenger Capability (Required)
- Mandatory class group.
- Private chats.
- Additional groups.
- Polls.
- Real conversation model and real user discovery.

## Persistent Data And Sync (Non-Negotiable)
- User memory, grades, messages, and any stateful data must remain synchronized across:
  - live site
  - local project folders
  - deploy targets
- Deployments must not wipe, reset, fork, or desynchronize persistent data.
- Never keep the only copy of stateful data in deploy-replaced files or temporary runtime storage.
- Storage/sync/backup/restore/migration/deploy changes must preserve history/message continuity.
- Prevent false persistence claims: do not report success when data is only local, only cached, or not synced to canonical shared storage.

## Execution Workflow (Mandatory)
1. Inspect current repo state (`git status`, relevant files, guidance docs).
2. Reproduce reported issue on desktop and phone-sized view.
3. Inspect real request/response and frontend state transitions (no guess-only fixes).
4. Apply scoped fixes.
5. Retest the same flows on desktop and mobile.
6. Report status explicitly as `completed`, `partial`, or `blocked`.
7. Deploy by default after verified changes unless user explicitly says not to deploy.

## Operational Guardrails
- Do not assume prior chat history is available.
- Do not use proxy/VPN/filter workarounds unless explicitly requested.
- Mobile-first quality is required for messenger create flows and core chat actions.
- After UI text/CSS edits, run:
  - `python scripts/check_text_integrity.py`
- Avoid unsafe bidi patterns (especially `unicode-bidi: plaintext`) unless explicitly justified.
- Clean temporary test chats/groups/DMs after validation.
- Prevent false-success states:
  - no success toast when final state is broken
  - created conversation/group must appear in list and open
  - UI/store/network state must remain synchronized
  - messages must not become mixed/corrupted (no "گاتی" states in ordering/content)

## Persian/RTL And Locale Integrity
- User-facing Persian text must stay UTF-8 safe.
- User-facing numeric/date/time rendering must stay Persian-first unless a machine-only field explicitly requires Latin digits.
- RTL directionality must stay stable and readable across chat and non-chat pages.

## Theme Contract
- Prefer semantic tokens from `public_html/assets/site/styles/core.css`.
- Avoid reusable hardcoded light-only colors.
- Avoid page-specific dark-mode `!important` patching.

## Deploy Runbook (Default)
Primary command:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1
```

Default deploy order (mandatory):
- local validation -> host deploy -> live health-check -> GitHub sync

Rules:
- Do not run pre-deploy `git pull` unless explicitly requested.
- Optional override when explicitly requested:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -PullBeforeDeploy
```
