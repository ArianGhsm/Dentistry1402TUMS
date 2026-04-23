# AGENTS.md

Persistent instructions for coding agents working in this repository.

## Scope And Product Framing
- This is a multi-section academic site, not a messenger-only app.
- Only `/chat/` and chat-related profile/settings/info surfaces should be upgraded to Telegram-grade UX.
- Other site sections must remain purpose-specific and should not be reshaped into messenger-style UX.

## Auth And Identity Rules (Non-Negotiable)
- Messenger must use the existing shared site auth/account system as the only source of truth.
- Do not create or keep separate messenger auth.
- Current user identity, avatar, bio, permissions, and role state must come from shared account/session APIs (`/api/auth_api.php`, shared auth store/session).

## Messenger Requirements
- Messenger must be a real messenger system, not just class chat.
- Required capabilities include:
  - mandatory class group
  - private chats
  - additional groups
  - polls
  - real conversation model
  - real user discovery/selection

## Execution Workflow (Always)
1. Inspect current repo state first (`git status`, relevant files, existing guidance docs).
2. Reproduce reported live-site bugs before patching (desktop + mobile/phone-sized).
3. Inspect real requests/responses and frontend state transitions (no code-only guessing).
4. Apply fixes.
5. Retest the same flows after fixes (desktop + mobile).
6. Run a completion audit before claiming success.
7. Deploy by default after successful verified changes unless the user explicitly says not to deploy.

## Operational Constraints
- Do not assume prior chat history is available.
- Do not use proxy/VPN/filter workarounds unless explicitly required by the user for that specific step.
- Mobile-first quality is mandatory for messenger creation flows and core chat actions.
- Run `python scripts/check_text_integrity.py` after UI text/CSS edits; avoid unsafe bidi patterns (for example `unicode-bidi: plaintext`) unless explicitly justified.
- When creating chats/groups/DMs for testing, debugging, seeding, or temporary checks, always clean them up afterward. Use temporary markers (`temporary=1` API flag or `grp-e2e-*`/`[tmp-chat]` title prefixes) and do not leave non-classroom test chats behind.
- Prevent false-success states:
  - no success toast when end state is broken
  - created conversation/group must actually appear in list and open
  - UI/store/network states must remain synchronized

## Theme Contract (Dark/Light)
- Use semantic theme tokens from `public_html/assets/site/styles/core.css` for surfaces, text, borders, focus rings, overlays, and buttons.
- Do not add new hardcoded light-only colors for reusable UI states (cards, modals, inputs, dropdowns, empty states, toasts).
- Do not patch dark mode with page-specific `!important` overrides; extend shared tokens in `core.css` and consume them in feature styles.

## Completion Audit Checklist (Required)
- Private-message creation works end-to-end.
- Group creation works end-to-end.
- New conversation/group appears in list and opens as active chat.
- No false-success UX remains.
- Mobile create-confirm actions are reachable and safe-area aware.
- Shared auth/account model is still the sole identity source.
- Owner/admin-only user-management additions (if touched) are permission-gated and validated.
- Retest evidence captured from live behavior.
- Verify each requested feature exists in both code and observed UI behavior (not screenshot/user reminder only).
- Final report must explicitly separate: `completed`, `partial`, and `blocked`.
- Deployment status explicitly reported.

## Deploy Runbook
- Primary deploy command (default behavior is mandatory: `git pull --ff-only` -> host deploy -> live health-check):
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1
```
- Dry run:
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -DryRun
```
- Full sync (only when explicitly needed):
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -FullSync
```
- Explicit local-state override (only when explicitly requested):
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy_public_html.ps1 -SkipGitPull
```
