from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
API = (ROOT / "public_html/api/bot_api.php").read_text(encoding="utf-8")
STORE = (ROOT / "public_html/api/bot_store.php").read_text(encoding="utf-8")
PAYMENTS = (ROOT / "public_html/api/bot_payments.php").read_text(encoding="utf-8")
STUDENT_ASSISTANT = (ROOT / "public_html/api/bot_student_assistant.php").read_text(encoding="utf-8")
ONBOARDING = (ROOT / "public_html/api/bot_onboarding.php").read_text(encoding="utf-8")
NOTIFICATIONS = (ROOT / "public_html/api/bot_notifications.php").read_text(encoding="utf-8")
PAGE = (ROOT / "public_html/account/bot-link/index.html").read_text(encoding="utf-8")
SCRIPT = (ROOT / "public_html/assets/site/scripts/bot-link.js").read_text(encoding="utf-8")
ACCOUNT_PAGE = (ROOT / "public_html/account/index.html").read_text(encoding="utf-8")
ACCOUNT_SCRIPT = (ROOT / "public_html/assets/site/scripts/account.js").read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


require("dent_bot_verify_service_signature" in STORE, "service HMAC verification is missing")
require("['POST', 'PUT']" in STORE, "signed bot service must support the WAF-safe PUT relay transport")
require("hash_equals" in STORE, "service signature comparison must be constant-time")
require("nonces" in STORE and "درخواست سرویس تکراری" in STORE, "nonce replay protection is missing")
require("dent_encrypt_secret_text($platformUserId)" in STORE, "platform identity must be encrypted")
require("dent_bot_token_hash($token)" in STORE, "raw account-link token must not be stored")
require("time() + 600" in STORE, "link challenge must expire")
require("dent_auth_session_require_csrf()" in API, "link confirmation must require CSRF")
require("dent_require_user()" in API, "link confirmation must require the site account")
require("dent_build_grades_payload($user)" in STORE, "bot grades must use the canonical site gradebook")
require("dent_owner_apply_grade_import" in STORE, "bot grade writes must use the canonical site gradebook")
require("ACCOUNT_LINK_REQUIRED" in STORE, "unlinked users must fail closed")
require("ACCOUNT_AUTH_REQUIRED" in STORE, "legacy links must not grant private access")
require("bot-canonical-auth-v1" in STORE and "dent_bot_link_auth_complete" in STORE, "canonical bot auth proof is missing")
require("authCompletedAt" in STORE and "authMethod" in STORE, "canonical auth completion evidence must be durable")
require("MANUAL_IDENTITY_DISABLED" in STORE, "manual identity claims must fail closed")
require(all(f"if ($action === '{action}')" in STORE for action in (
    "submitIdentityClaim", "importIdentityCandidates", "identityClaims",
    "resolveIdentityClaim", "setIdentityMapping",
)), "every legacy manual identity service action must be explicitly retired")
require("waiting_account" in STORE, "missing canonical accounts must not be synthesized")
require("claimedNameEncrypted" in STORE, "claimed names must be encrypted at rest")
require("platformProfileEncrypted" in STORE, "Telegram claim profiles must be encrypted at rest")
require("dent_bot_conflicting_link" in STORE, "identity links must enforce one-to-one permanence")
require("dent_bot_delete_identity_mapping" in STORE, "owner identity removal action is missing")
require("linked-secure-site" in STORE, "secure site confirmation must clear the pending claim queue")
require("dent_bot_store_read" in STORE, "read-only account checks must not rewrite the integration store")
require("max(6, (int) ($store['schemaVersion'] ?? 0))" in STORE, "legacy bot stores must migrate to generic-payment return-route schema v6")
require("accountDisconnectDeliveries" in STORE and "leaseUntil" in STORE, "website disconnect notices must use a durable leased queue")
require("dent_bot_disconnect_account" in STORE and "account-disconnected-from-site" in STORE, "self-service disconnect must be atomic and audited")
require("claimAccountDisconnectDeliveriesV1" in STORE and "ackAccountDisconnectDeliveryV1" in STORE, "bot adapters must claim and acknowledge disconnect notices")
require("accountConnections" in API and "disconnectAccount" in API, "authenticated website connection actions are missing")
require("dent_auth_session_require_csrf()" in API and "bot-account-connections-v1" in STORE, "website disconnect must require CSRF and a versioned contract")
require('data-surface="bots"' in ACCOUNT_PAGE and "bot-connections-list" in ACCOUNT_PAGE, "Account bot-connection surface is missing")
require("loadBotConnections" in ACCOUNT_SCRIPT and "data-disconnect-bot" in ACCOUNT_SCRIPT, "Account bot-connection UI behavior is missing")
require(all(action in STORE for action in (
    "onboardingCatalogV1", "onboardingStatusV1", "requestOnboardingOtpV1",
    "resendOnboardingOtpV1", "verifyOnboardingOtpV1",
)), "bot onboarding v1 service actions are incomplete")
require(all(action in STORE for action in (
    "classAuthOtpStartV1", "classAuthOtpVerifyV1", "requestProfileEditV1",
    "profileEditRequestsV1", "resolveProfileEditV1", "normalizeIdentityAuthV2",
    "identityAuthV2Status",
)), "unified identity and owner-approved profile-edit actions are incomplete")
require("dent_issue_otp_for_phone" in ONBOARDING and "dent_verify_otp_for_phone" in ONBOARDING, "onboarding must reuse the website OTP subsystem")
require("dent_user_phone_ready_for_otp" in ONBOARDING and "dent_primary_cohort_key" in ONBOARDING, "class OTP must require an OTP-ready canonical cohort account")
require("نیمسال اول (روزانه یا تعهدی)" in ONBOARDING and ONBOARDING.count("'نیمسال ") >= 8, "the six valid admission modes must be canonical")
require("profileEncrypted" in ONBOARDING and "phoneEncrypted" in ONBOARDING, "onboarding PII must be encrypted at rest")
require("bot-onboarding-phone:" in ONBOARDING and "onboardingIdentityProfiles" in ONBOARDING, "cross-platform profiles must join only by verified phone HMAC")
require("websiteAccountLinked' => false" in ONBOARDING, "generic onboarding must not grant website access")
require("count($institutions)" in ONBOARDING and ONBOARDING.count("['province' =>") == 106, "the verified medical-university catalog must contain 75 public and 31 Azad entries")
require("'azadAdmissionTypes' => ['نیمسال اول', 'نیمسال دوم']" in ONBOARDING, "Azad admission must be term-only")
require(ONBOARDING.count("'system' => 'azad'") == 31, "all 31 Azad medical units must be marked explicitly")
require("createBotPayment" in STORE and "paymentStatus" in STORE, "bot checkout service actions are missing")
require(
    "$genericPaymentActions = ['createBotPayment', 'paymentStatus', 'paymentProductStatesV2'];" in STORE
    and "dent_bot_verified_onboarding_payment_user" in STORE,
    "verified generic onboarding must be accepted only by the three user-commerce actions",
)
require(
    "onboardingIdentityRoutes" in STORE
    and "'platformUserIdEncrypted' => dent_encrypt_secret_text($platformUserId)" in STORE,
    "generic payment results need an encrypted same-platform return route",
)
require("paymentCatalog" not in STORE and "ownerPaymentForms" not in STORE, "website catalogs must not be mirrored into the bot")
require("bot-offer" in PAYMENTS and "bot_offer_ref" in PAYMENTS, "bot offer orders must be distinguishable")
require("payments_find_item_index_by_slug" not in PAYMENTS, "bot checkout must not depend on the website product catalog")
require("$payload['amountRials']" in PAYMENTS, "signed bot checkout amount must be validated")
require("bot_request_ref" in PAYMENTS and "hash_equals" in PAYMENTS, "payment creation must be idempotent")
require("dent_bot_payment_reservation_state" in PAYMENTS and "payments_with_store_lock" in PAYMENTS, "capacity and idempotency decisions must share the atomic payment-store lock")
require("PRODUCT_CAPACITY_REACHED" in PAYMENTS and "PRODUCT_PURCHASE_LIMIT_REACHED" in PAYMENTS, "bot checkout capacity and per-user limits are missing")
require("payments_gateway_start_payment" in PAYMENTS, "bot purchase must use the existing website gateway")
require("PAYMENT_ORDER_NOT_FOUND" in PAYMENTS and "dent_bot_payment_payer_keys" in PAYMENTS, "payment status must remain bound to the verified payer identity")
require("botPaymentPayerKey" in PAYMENTS and "profile:[a-f0-9]{40}" in PAYMENTS, "generic payer identity must be opaque and must ignore self-declared student numbers")
require("paymentResultDeliveries" in STORE and "dent_bot_queue_payment_success_deliveries" in STORE and "hash_hmac('sha256', $dedupeKey" in STORE, "verified payment delivery must be durable and idempotent")
require("claimPaymentResultDeliveriesV1" in STORE and "ackPaymentResultDeliveryV1" in STORE, "payment-result delivery must use the signed leased queue")
require("paymentTransactionV2" in STORE and "paymentUpdateTransactionStatusV2" in STORE, "owner transaction detail and audited manual status actions are missing")
require("bot_owner_status_history" in PAYMENTS and "VERIFIED_PAYMENT_IMMUTABLE" in PAYMENTS, "manual status changes must be audited and must not rewrite verified payments")
require(all(action in STORE for action in (
    "studentAssistantSummaryV1", "performIntegrationActionV1", "integrationChallengeAnswerV1"
)), "student assistant v1 service actions are missing")
require("STUDENT_ASSISTANT_CONTRACT_MISMATCH" in STUDENT_ASSISTANT, "student assistant contract version is not enforced")
require("expectedAnswerHash" in STUDENT_ASSISTANT and "imageDataUri" not in STUDENT_ASSISTANT.split("dent_student_assistant_store_default", 1)[1].split("function dent_student_assistant_store_with_lock", 1)[0], "captcha answers/images must not be stored as plaintext")
require("Consume first" in STUDENT_ASSISTANT and "usedAt" in STUDENT_ASSISTANT, "captcha must be consumed before connector continuation")
require("INTEGRATION_CHALLENGE_NOT_ACTIVE" in STUDENT_ASSISTANT, "expired/replayed/cross-identity challenges must fail closed")
require("برای اجرای نهایی" in STUDENT_ASSISTANT, "captcha completion must stop at an explicit pre-submit confirmation")
require("notifications_list_payload_for_user" in NOTIFICATIONS, "bot notifications must use the canonical website feed")
require("notifications_mark_read" in NOTIFICATIONS, "bot seen state must use the canonical website read state")
require("notifications_audience_payload" in NOTIFICATIONS, "owner audience reporting must use the canonical website audience")
require("dent_bot_notifications_require_owner" in NOTIFICATIONS, "delivery claiming and audience reporting must be owner-only")
require("notificationDeliveries" in STORE and "leaseUntil" in NOTIFICATIONS, "notification delivery must use a durable lease")
require("ctaUrl" in NOTIFICATIONS and "notifications_clean_cta_href" in NOTIFICATIONS, "notification CTA URLs must be website-validated")
require("createDeployNotification" in STORE, "signed deployment notification action is missing")
require("$action !== 'createDeployNotification'" in STORE, "deployment lifecycle must survive owner interactive reauthentication")
require("dent_bot_create_deploy_notification" in NOTIFICATIONS, "deploy notifications must require the linked owner")
require("disablePush" in NOTIFICATIONS, "central deploy notifications must not be duplicated by bot feed workers")
require('meta name="robots" content="noindex,nofollow,noarchive"' in PAGE, "link page must not be indexed")
require("X-CSRF-Token" in SCRIPT, "link UI must send the site CSRF token")
require("localStorage" not in SCRIPT and "indexedDB" not in SCRIPT, "link token must not enter persistent browser storage")

print("OK: bot account-link, retired manual identity paths, canonical grades and independent bot-offer contracts are enforced.")
