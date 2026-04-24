# AGENTS.md

Persistent instructions for coding agents in this repository.

## Scope
- This is a multi-section academic site, not a messenger-only app.
- Upgrade to Telegram-grade UX only for `/chat/` and chat-related settings/profile surfaces.
- Keep non-chat sections purpose-specific and do not reshape them into messenger UX.

## Shared Auth And Identity (Non-Negotiable)
- Messenger must use the shared site auth/account system as the only source of truth.
- Do not create separate messenger auth or profile identity sources.
- Identity/role/session data must come from shared APIs and session store (`/api/auth_api.php`, shared auth store/session).

## Minimum Messenger Capability
- Mandatory class group.
- Private chats.
- Additional groups.
- Polls.
- Real conversation model and real user discovery.

## Execution Workflow
1. Inspect current repo state first (`git status`, relevant files, current guidance docs).
2. Reproduce reported issues on desktop and phone-sized view.
3. Inspect real request/response and frontend state transitions (no guess-only fixes).
4. Apply scoped fixes.
5. Retest the same flows on desktop and mobile.
6. Report status explicitly as `completed`, `partial`, `blocked`.
7. Deploy by default after verified changes unless user explicitly says not to deploy.

## Operational Guardrails
- Do not assume prior chat history is available.
- Do not use proxy/VPN/filter workarounds unless explicitly requested.
- Mobile-first quality is required for messenger create flows and core chat actions.
- After UI text/CSS edits, run: `python scripts/check_text_integrity.py`.
- Avoid unsafe bidi patterns (especially `unicode-bidi: plaintext`) unless explicitly justified.
- When creating temporary chats/groups/DMs for tests, clean them up after validation.
- Prevent false-success states:
  - no success toast when end state is broken
  - created conversation/group must appear in list and open
  - UI/store/network state must remain synchronized

## Theme Contract
- Prefer semantic tokens from `public_html/assets/site/styles/core.css`.
- Avoid hardcoded reusable light-only colors and page-specific dark-mode `!important` patches.

## Deploy Runbook (Default)
- Primary command:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1
```
- Default deploy order is mandatory:
  - local validation -> host deploy -> live health-check -> GitHub sync
- Do not run pre-deploy `git pull` unless explicitly requested.
- Explicit optional pre-deploy pull override:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -PullBeforeDeploy
```