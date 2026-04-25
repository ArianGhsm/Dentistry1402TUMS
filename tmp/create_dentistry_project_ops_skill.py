from pathlib import Path
from textwrap import dedent

base = Path(r"C:\Users\ASUS\.claude\skills\dentistry1402-project-ops")
references = base / "references"
references.mkdir(parents=True, exist_ok=True)

skill_md = dedent(
    """\
    ---
    name: dentistry1402-project-ops
    description: Project operating rules, auth/deploy conventions, UTF-8/RTL checks, and test account for Dentistry1402TUMS. Use when editing, testing, or deploying this repo, or when you need the canonical workflow and constraints for this project.
    ---

    # Dentistry1402 Project Ops

    ## Instructions

    ### Step 1: Load the project rules
    Always treat AGENTS.md, CONTRIBUTING.md, CONTRIBUTING-UTF8.md, DEPLOY.md, and audit.txt as the source of truth for this repository.

    ### Step 2: Respect the non-negotiables
    - Shared auth/session in public_html/api/auth_api.php and auth_store.php is the only source of truth.
    - /chat/ must use the shared auth model and must not grow a separate auth surface.
    - Only /chat/ should be upgraded with Telegram-grade UX; keep the rest of the site domain-specific.
    - Keep all user-facing text UTF-8 safe and Persian-first.
    - Avoid unsafe bidi patterns unless explicitly required.

    ### Step 3: Before editing
    Read the target files first, keep changes focused, and do not guess signatures or imports.

    ### Step 4: After editing
    - If text or CSS changed, run python scripts/check_text_integrity.py.
    - Run the relevant validation for the touched surface.
    - Verify on desktop and mobile if the change affects UI.

    ### Step 5: Deploy correctly
    Use the canonical deploy script from DEPLOY.md. Default order is local validation, host deploy, live health checks, then GitHub sync.

    ### Step 6: Use the test account for validation only
    - Student number: 40211272003
    - Password: 1363945807
    Use this account only for manual testing or validation in this project.

    ## Detailed Rules
    See references/project-rules.md for the complete repo-specific checklist.
    """
)

project_rules_md = dedent(
    """\
    # Dentistry1402TUMS Project Rules

    ## Scope
    - This is a multi-section academic site, not a messenger-only app.
    - Keep non-chat sections purpose-specific.
    - Upgrade Telegram-grade UX only for /chat/ and chat-related settings/profile surfaces.

    ## Shared Auth and Identity
    - Messenger must use the shared site auth/account system as the only source of truth.
    - Do not create separate messenger auth or profile identity sources.
    - Identity, role, and session data must come from shared APIs and session store.

    ## Minimum Messenger Capability
    - Mandatory class group.
    - Private chats.
    - Additional groups.
    - Polls.
    - Real conversation model and real user discovery.

    ## Execution Workflow
    1. Inspect current repo state first.
    2. Reproduce reported issues on desktop and phone-sized view.
    3. Inspect real request/response and frontend state transitions.
    4. Apply scoped fixes.
    5. Retest the same flows on desktop and mobile.
    6. Report status explicitly as completed, partial, or blocked.
    7. Deploy by default after verified changes unless the user explicitly says not to deploy.

    ## Operational Guardrails
    - Do not assume prior chat history is available.
    - Do not use proxy/VPN/filter workarounds unless explicitly requested.
    - Mobile-first quality is required for messenger create flows and core chat actions.
    - After UI text/CSS edits, run python scripts/check_text_integrity.py.
    - Avoid unsafe bidi patterns, especially unicode-bidi: plaintext, unless explicitly justified.
    - When creating temporary chats/groups/DMs for tests, clean them up after validation.
    - Prevent false-success states: created conversations must appear in list and open, and UI/store/network state must stay synchronized.

    ## Theme Contract
    - Prefer semantic tokens from public_html/assets/site/styles/core.css.
    - Avoid hardcoded reusable light-only colors and page-specific dark-mode !important patches.

    ## Deploy Runbook
    - Primary command: powershell -ExecutionPolicy Bypass -File .\\scripts\\deploy_public_html.ps1
    - Default order: local validation, host deploy, live health-check, GitHub sync.
    - Do not run pre-deploy git pull unless explicitly requested.
    - Optional pre-deploy pull override: powershell -ExecutionPolicy Bypass -File .\\scripts\\deploy_public_html.ps1 -PullBeforeDeploy
    """
)

(base / "SKILL.md").write_text(skill_md, encoding="utf-8")
(references / "project-rules.md").write_text(project_rules_md, encoding="utf-8")
print(f"Created skill at {base}")
