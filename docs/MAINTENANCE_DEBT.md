# Maintenance debt boundaries

This file records large **code-centric** modules that are intentionally not split during unrelated hygiene/product changes. Generated or bulk exam-bank data files are excluded because line count there is content volume, not module complexity.

The executable budget is `scripts/check_module_size_budget.py`. CI fails when one of these central modules grows beyond its bounded ceiling; splitting/removing a module is allowed. The budget is a brake on further concentration, not a reason to preserve the current architecture forever.

Current decomposition targets:

- `public_html/assets/site/scripts/chat.js` (pure helpers in `chat-utils.js`; cache/draft persistence in `chat-cache-drafts.js`) — separate transport/state, message rendering, composer/media, and navigation concerns before adding substantial new chat behavior.
- Chat CSS — the former `chat.css` monolith is split in original cascade order into `chat.css` (foundation), `chat-messenger.css` (messenger/mobile refinement), and `chat-enhancements.css` (final feature/polish layers); preserve this layered boundary and RTL behavior.
- `public_html/chat/chat_api.php` — attachment/media storage, preview, access and upload primitives have been extracted into `chat_media.php`; continue isolating request/auth validation, conversation queries and message mutation behind tested modules/endpoints.
- `public_html/assets/site/scripts/account.js` (pure helpers in `account-utils.js`; owner analytics in `account-owner-analytics.js`) — separate authentication/OTP, profile, settings, payments, and account-linked product controllers.
- `public_html/api/auth_store.php` — persistence primitives are in `auth_store_persistence.php`, SMS provider/configuration primitives are in `auth_store_sms.php`, and the provider-neutral OTP engine is in `auth_store_otp.php`; continue separating identity/session and account-domain operations while preserving the single canonical auth store.
- `public_html/api/notes_api.php` — direct-upload/session orchestration has been extracted into `notes_direct_upload.php`; continue separating authorization/indexing from remaining file/delivery operations without weakening private-note access rules.
- `bot_runtime/dent_bot/app.py` — payment/report, dialog/onboarding and dynamic-screen routing are split across dedicated mixins (`payment_app_workflows.py`, `dialog_app_workflows.py`, `dynamic_screen_workflows.py`), with shared home/section primitives in `app_shell_screens.py`; keep new feature routing out of the central app shell.

Any decomposition should be its own scoped task with before/after behavior tests. “Fewer lines” alone is not an acceptance criterion.
