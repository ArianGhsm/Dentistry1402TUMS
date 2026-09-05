# Zibal first-party payment launch

Official requirement: [Zibal Referer guidance](https://help.zibal.ir/article/why-referrer-header-is-required/),
checked 2026-09-05, plus merchant notice dated 1405/06/09 12:16 supplied by the
owner. Enforcement begins 20 Shahrivar and applies to the browser `/start`
request. The registered merchant domain, callback domain and Referer must match
at the main-domain level. It is not a new `/v1/request` or `/v1/verify` header.

## Boundary

`bot → HTTPS site HTML → browser navigation → Zibal`

`payments_gateway.php` continues generating the internal provider URL. Only the
public bot response wraps it. New and idempotent Dent Telegram/Bale checkouts
and the stateless `voice-payment-bridge-v1` response use
`https://dentistry1402tums.ir/payment/start/zibal/?trackId=…&exp=…&sig=…`.
Legacy raw snapshots remain untouched and get a fresh signed URL when reopened.
Opening this document creates no order, verifies no payment and changes no wallet.

The site signs a 900-second URL with the existing auth key, a separate
`zibal-payment-handoff-v1:` HMAC domain and constant-time verification. The exact
query grammar rejects duplicate/unknown keys, arrays, alternate encoding and
arbitrary destinations. The document sets `Referrer-Policy: origin` in both
headers and HTML, has nonce-only script/style CSP, no external resources,
no-store, nosniff, noindex and frame denial. Normal JS navigation and the manual
fallback both use the fixed provider host. Only reason/gateway/truncated tracking
hash enter the handoff operational log. This is not a new authorization token.

The PWA worker bypasses `/payment/start/`; payment launch documents must never
be put into its page cache. No production browser dependency is needed.

Website-native buy/cart/item, forms/fill, exams/pay and payment collections use
`window.location.href = redirectUrl` from their existing site document. Source
audit found no suppressing Referrer policy on those pages or their parent
`.htaccess`. Their provider adapters, callback, return relay and accounting are
outside this change. The post-payment Voice relay retains `no-referrer`.

## Tests

- Existing `scripts/test_unit.php` includes handoff signing/expiry/tamper tests.
- `python scripts/test_payment_handoff_http.py` uses isolated PHP storage, a
  fake request/verify provider, signed HTTP dispatch for both platforms, legacy
  raw snapshots, same-platform callback return and the Voice bridge.
- Add `--browser` with test-only Playwright and installed Chrome/Chromium for two
  actual loopback origins. The production PHP-rendered body, nonce and headers
  are served unchanged except that the fixed gateway origin points to the test
  receiver. No Referer is manually injected. JS and no-JS navigation must send
  exactly the source origin, never the query. `HANDOFF_TEST_CHROME` can select
  an installed browser. Windows test environment: `.codex-local/handoff-test-venv`.

## Release and rollback

1. Deploy the Voice client accepting the exact old and new URL shapes.
2. Publish the launch helper/document and PWA bypass through `complete_task.ps1`
   before switching either bot response producer.
3. Publish the two PHP response changes through the same scoped workflow.
4. Verify live unpaid requests and browser navigation, then deploy the Voice
   first-party-only validator. Retain the compatible Voice release for rollback.

Use explicit `-PathScope` and preserve existing unrelated working-tree changes.
For the infrastructure-only stage keep the two producer source files at their
pre-switch revision: the current deploy manifest hashes the whole local tree.
Do not record a pending producer change as already deployed. The service-worker
byte change activates the bypass; avoid a global HTML version-stamp rewrite for
this backend change by using the canonical workflow's `-SkipVersionStamp`.

Rollback the Voice client to its compatible release before restoring the site
producer source from the pre-change copy using the canonical deploy workflow.
Keep the launch route available for already-issued buttons. No payment database,
auth state or encryption key should be restored as part of this code rollback.

Old messages already delivered before rollout may still contain their original
button URLs; reopening checkout produces the new URL. Browser extensions or
nonstandard embedded clients can suppress Referer; this cannot be overridden by
server-side headers on a different request. No header spoofing is used.
