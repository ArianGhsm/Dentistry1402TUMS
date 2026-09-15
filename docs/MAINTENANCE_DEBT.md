# Maintenance debt boundaries

This file records large **code-centric** modules that are intentionally not split during unrelated hygiene/product changes. Generated or bulk exam-bank data files are excluded because line count there is content volume, not module complexity.

The executable budget is `scripts/check_module_size_budget.py`. CI fails when one of these central modules grows beyond its bounded ceiling; splitting/removing a module is allowed. The budget is a brake on further concentration, not a reason to preserve the current architecture forever.

Current decomposition targets:

- `public_html/assets/site/scripts/chat.js` — separate transport/state, message rendering, composer/media, and navigation concerns before adding substantial new chat behavior.
- `public_html/assets/site/styles/chat.css` — split stable tokens/layout primitives from view-specific chat/list/composer rules without changing RTL behavior.
- `public_html/chat/chat_api.php` — isolate request/auth validation, conversation queries, message mutation, and attachment paths behind tested functions/endpoints.
- `public_html/assets/site/scripts/account.js` — separate authentication/OTP, profile, settings, payments, and account-linked product controllers.
- `public_html/api/auth_store.php` — separate persistence primitives from OTP/SMS, identity/session, and account-domain operations while preserving the single canonical auth store.
- `public_html/api/notes_api.php` — separate authorization/indexing from file/delivery operations without weakening private-note access rules.
- `bot_runtime/dent_bot/app.py` — continue extracting explicit feature routers/workflows rather than reintroducing installer/monkey-patch generations.

Any decomposition should be its own scoped task with before/after behavior tests. “Fewer lines” alone is not an acceptance criterion.
