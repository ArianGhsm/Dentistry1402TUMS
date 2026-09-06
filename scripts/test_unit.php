<?php
declare(strict_types=1);

/**
 * Deterministic, offline unit tests for pure helper functions.
 *
 * Runs in CI without a live server, database, network or credentials. Covers
 * the global-search text pipeline and the Web Push crypto primitives (which are
 * easy to break silently and impossible to debug from the browser side).
 *
 * Usage: php scripts/test_unit.php
 */

// Never let cryptographic unit fixtures read or create a production secret.
putenv('DENT_AUTH_SECRET_KEY=' . base64_encode(str_repeat('unit-fixture-', 3)));
require_once __DIR__ . '/../public_html/api/search_store.php';
require_once __DIR__ . '/../public_html/api/push_store.php';
require_once __DIR__ . '/../public_html/api/analytics_store.php';
require_once __DIR__ . '/../public_html/api/exams_store.php';
require_once __DIR__ . '/../public_html/api/auth_store.php';
require_once __DIR__ . '/../public_html/api/exams_home_highlights.php';
require_once __DIR__ . '/../public_html/api/navid_service.php';
require_once __DIR__ . '/../public_html/api/bot_payments.php';
require_once __DIR__ . '/../public_html/api/bot_voice_payment_bridge.php';
require_once __DIR__ . '/../public_html/api/payment_handoff.php';

if (!function_exists('dent_bot_site_origin')) {
    function dent_bot_site_origin(): string
    {
        return 'https://dentistry1402tums.ir';
    }
}

// Order-status constants live in payments_store.php (not loaded here); define the
// stable values the funnel relies on so the test stays self-contained.
if (!defined('PAYMENTS_ORDER_STATUS_SUCCESS')) {
    define('PAYMENTS_ORDER_STATUS_SUCCESS', 'success');
}
if (!defined('PAYMENTS_ORDER_STATUS_PENDING')) {
    define('PAYMENTS_ORDER_STATUS_PENDING', 'pending');
}

// The bootstrap installs JSON error/exception handlers meant for HTTP; restore
// defaults so a real failure surfaces as a normal CLI error instead of a 200.
restore_error_handler();
restore_exception_handler();
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$GLOBALS['unit_failures'] = 0;
$GLOBALS['unit_total'] = 0;
$GLOBALS['unit_skipped'] = 0;

function unit_assert(bool $condition, string $label): void
{
    $GLOBALS['unit_total']++;
    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }
    $GLOBALS['unit_failures']++;
    echo "FAIL: {$label}\n";
}

function unit_skip(string $label): void
{
    $GLOBALS['unit_skipped']++;
    echo "SKIP: {$label}\n";
}

// ---------------------------------------------------------------------------
// Bot commerce v2: immutable order snapshot and bounded public payload
// ---------------------------------------------------------------------------
$voicePaymentContract = dent_voice_payment_contract_payload([
    'action' => 'voicePaymentVerifyV1',
    'contractVersion' => DENT_VOICE_PAYMENT_CONTRACT,
    'platform' => 'telegram',
    'platformUserId' => '123456789',
    'orderId' => 'VT-20260829-AbCdEf_123',
    'amountRials' => 200000,
], 'voicePaymentVerifyV1');
unit_assert(
    $voicePaymentContract === [
        'platform' => 'telegram',
        'platformUserId' => '123456789',
        'orderId' => 'VT-20260829-AbCdEf_123',
        'amountRials' => 200000,
    ],
    'voice payment bridge normalizes its signed stateless contract'
);
$voicePaymentProviderFixture = [
    'raw' => [
        'request' => ['merchant' => 'must-never-leave-the-website'],
        'response' => ['json' => [
            'result' => 100,
            'amount' => 200000,
            'orderId' => 'VT-20260829-AbCdEf_123',
        ]],
    ],
];
unit_assert(
    dent_voice_payment_result_code($voicePaymentProviderFixture) === 100
        && dent_voice_payment_verified_identity($voicePaymentProviderFixture) === [
            'amountRials' => 200000,
            'orderId' => 'VT-20260829-AbCdEf_123',
        ],
    'voice payment bridge extracts amount and order identity before wallet credit'
);
unit_assert(
    dent_voice_payment_callback_url('abcdefghijklmnopqrstuvwxyz_12345', 'bale')
        === 'https://dentistry1402tums.ir/api/voice_payment_return.php?token=abcdefghijklmnopqrstuvwxyz_12345&platform=bale',
    'voice payment bridge uses the fixed HTTPS return relay accepted by the gateway'
);
$voiceBaleContract = dent_voice_payment_contract_payload([
    'action' => 'voicePaymentVerifyV1',
    'contractVersion' => DENT_VOICE_PAYMENT_CONTRACT,
    'platform' => 'bale',
    'platformUserId' => '123456789',
    'orderId' => 'VB-20260906-AbCdEf_123',
    'amountRials' => 200000,
], 'voicePaymentVerifyV1');
unit_assert(
    $voiceBaleContract['platform'] === 'bale' && $voiceBaleContract['orderId'] === 'VB-20260906-AbCdEf_123',
    'voice payment bridge keeps Bale identity separate from Telegram orders'
);
$botOrderFixture = payments_normalize_order_record([
    'id' => 901,
    'item_id' => 0,
    'user_id' => '40211272010',
    'payer_name' => 'کاربر تست',
    'payer_phone' => '09120000000',
    'payer_student_number' => '40211272010',
    'extra_form_data' => [
        'source' => 'bot-offer',
        'bot_offer_ref' => 'offer-ref-1234567890',
        'bot_offer_title' => 'عنوان snapshot',
        'bot_origin_platform' => 'telegram',
        'bot_fulfillment_json' => '{"text":"دسترسی فعال شد","url":"https://example.test/file"}',
    ],
    'amount' => 750000,
    'unit_price' => 750000,
    'subtotal' => 750000,
    'status' => 'success',
    'gateway' => 'mock',
    'ref_id' => 'track-1',
    'created_at' => '2026-08-29T08:00:00Z',
    'payment_started_at' => '2026-08-29T08:01:00Z',
    'paid_at' => '2026-08-29T08:02:00Z',
    'verified_at' => '2026-08-29T08:03:00Z',
    'updated_at' => '2026-08-29T08:03:00Z',
    'public_token' => 'order-token-123456789012345',
]);
unit_assert(is_array($botOrderFixture), 'Bot order fixture normalizes');
if (is_array($botOrderFixture)) {
    $botPayload = dent_bot_payment_order_payload($botOrderFixture, false);
    unit_assert(
        ($botPayload['title'] ?? '') === 'عنوان snapshot'
            && (int) ($botPayload['amountRials'] ?? 0) === 750000
            && ($botPayload['paymentStartedAt'] ?? '') !== ''
            && ($botPayload['verifiedAt'] ?? '') !== '',
        'Bot receipt uses immutable title/amount snapshot and standard lifecycle timestamps'
    );
    unit_assert(
        !array_key_exists('payerPhone', $botPayload)
            && !array_key_exists('studentNumber', $botPayload)
            && (($botPayload['fulfillment']['url'] ?? '') === 'https://example.test/file'),
        'User bot receipt omits owner PII while preserving allowlisted fulfillment'
    );
    unit_assert(dent_bot_payment_is_offer_order($botOrderFixture, 'offer-ref-1234567890'), 'Bot offer order provenance is recognized');
}

$genericPayerKey = dent_bot_payment_profile_payer_key('09120000000');
unit_assert(
    preg_match('/^profile:[a-f0-9]{40}$/', $genericPayerKey) === 1
        && !str_contains($genericPayerKey, '09120000000'),
    'Generic checkout uses a stable opaque verified-phone payer key without exposing the phone'
);
unit_assert(
    dent_bot_payment_payer_keys([
        'botPaymentPayerKey' => $genericPayerKey,
        'studentNumber' => '40299999999',
        'phoneNumber' => '09120000000',
    ]) === [$genericPayerKey],
    'A generic self-declared student number cannot grant access to canonical student orders'
);
$linkedPayerKeys = dent_bot_payment_payer_keys([
    'studentNumber' => '40211272010',
    'phoneNumber' => '09120000000',
]);
unit_assert(
    $linkedPayerKeys === ['40211272010', $genericPayerKey],
    'A later canonical link retains access to purchases made with the same verified phone'
);
$genericOwnerPayload = dent_bot_payment_order_payload([
    'id' => 902,
    'user_id' => $genericPayerKey,
    'payer_name' => 'کاربر عمومی',
    'payer_phone' => '09120000000',
    'payer_student_number' => '',
    'extra_form_data' => ['source' => 'bot-offer'],
    'status' => PAYMENTS_ORDER_STATUS_PENDING,
], true);
unit_assert(
    ($genericOwnerPayload['studentNumber'] ?? 'invalid') === '',
    'Owner payment reports never expose the opaque payer key as a student number'
);

$reservationNow = strtotime('2026-08-29T12:00:00Z');
$reservationFixture = [
    [
        'id' => 1,
        'user_id' => '40211272010',
        'status' => PAYMENTS_ORDER_STATUS_PENDING,
        'expires_at' => '2026-08-29T13:00:00Z',
        'extra_form_data' => [
            'source' => 'bot-offer',
            'bot_offer_ref' => 'offer-ref-1234567890',
            'bot_request_ref' => 'request-ref-aaaaaaaa',
        ],
    ],
    [
        'id' => 2,
        'user_id' => '40211272011',
        'status' => PAYMENTS_ORDER_STATUS_SUCCESS,
        'expires_at' => '',
        'extra_form_data' => [
            'source' => 'bot-offer',
            'bot_offer_ref' => 'offer-ref-1234567890',
            'bot_request_ref' => 'request-ref-bbbbbbbb',
        ],
    ],
    [
        'id' => 3,
        'user_id' => '40211272012',
        'status' => PAYMENTS_ORDER_STATUS_PENDING,
        'expires_at' => '2026-08-29T11:00:00Z',
        'extra_form_data' => [
            'source' => 'bot-offer',
            'bot_offer_ref' => 'offer-ref-1234567890',
            'bot_request_ref' => 'request-ref-cccccccc',
        ],
    ],
    [
        'id' => 4,
        'user_id' => '40211272010',
        'status' => PAYMENTS_ORDER_STATUS_FAILED,
        'expires_at' => '',
        'extra_form_data' => [
            'source' => 'bot-offer',
            'bot_offer_ref' => 'offer-ref-1234567890',
            'bot_request_ref' => 'request-ref-dddddddd',
        ],
    ],
];
$reservationState = dent_bot_payment_reservation_state(
    $reservationFixture,
    'offer-ref-1234567890',
    '40211272010',
    'request-ref-aaaaaaaa',
    $reservationNow === false ? 0 : $reservationNow
);
unit_assert(
    (int) ($reservationState['existing']['id'] ?? 0) === 1,
    'Bot checkout idempotency resolves the original same-user request before creating another order'
);
unit_assert(
    (int) ($reservationState['reserved'] ?? -1) === 2
        && (int) ($reservationState['userReserved'] ?? -1) === 1,
    'Bot capacity counts active pending and successful orders but ignores expired and failed attempts'
);
unit_assert(
    dent_bot_payment_reservation_error($reservationState, 2, 0) === 'PRODUCT_CAPACITY_REACHED',
    'Bot checkout enforces global product capacity under the store lock'
);
unit_assert(
    dent_bot_payment_reservation_error($reservationState, 0, 1) === 'PRODUCT_PURCHASE_LIMIT_REACHED',
    'Bot checkout enforces the per-user purchase limit independently of global capacity'
);
$crossIdentityReservation = dent_bot_payment_reservation_state(
    [[
        'id' => 9,
        'user_id' => $genericPayerKey,
        'status' => PAYMENTS_ORDER_STATUS_SUCCESS,
        'expires_at' => '',
        'extra_form_data' => [
            'source' => 'bot-offer',
            'bot_offer_ref' => 'offer-ref-1234567890',
            'bot_request_ref' => 'request-ref-profile',
        ],
    ]],
    'offer-ref-1234567890',
    '40211272010',
    'request-ref-new',
    $reservationNow === false ? 0 : $reservationNow,
    ['40211272010', $genericPayerKey]
);
unit_assert(
    (int) ($crossIdentityReservation['userReserved'] ?? 0) === 1,
    'Per-user purchase limits survive a generic-profile to canonical-account transition'
);
$summaryBucket = dent_bot_payment_summary_bucket([
    [
        'status' => PAYMENTS_ORDER_STATUS_SUCCESS,
        'amount' => 250000,
        'created_at' => '2026-01-01T00:00:00Z',
        'verified_at' => '2026-08-29T10:00:00Z',
    ],
    [
        'status' => PAYMENTS_ORDER_STATUS_PENDING,
        'amount' => 500000,
        'created_at' => '2026-08-29T10:00:00Z',
        'expires_at' => '2000-01-01T00:00:00Z',
    ],
], strtotime('2026-08-29T09:00:00Z') ?: 0);
unit_assert(
    (int) ($summaryBucket['successCount'] ?? 0) === 1
        && (int) ($summaryBucket['receivedRials'] ?? 0) === 250000
        && (int) ($summaryBucket['pendingCount'] ?? 0) === 0,
    'Bot dashboard buckets successful orders by verification time and excludes expired pending reservations'
);
$paymentsApiSource = file_get_contents(__DIR__ . '/../public_html/api/payments_api.php') ?: '';
unit_assert(
    str_contains($paymentsApiSource, "'Dent1402Bot'")
        && str_contains($paymentsApiSource, "'dent1402bot'")
        && str_contains($paymentsApiSource, "receipt_"),
    'Bot-origin checkout has deterministic same-platform Telegram and Bale return deep links'
);

// ---------------------------------------------------------------------------
// SMS provider response: accepted request is not proof of handset delivery
// ---------------------------------------------------------------------------
$smsAccepted = dent_sms_parse_pattern_response([
    'status' => 'success',
    'data' => 0,
    'messages' => 'درخواست ثبت شد.',
], 201);
unit_assert(
    ($smsAccepted['success'] ?? false) === true
        && ($smsAccepted['acceptanceOnly'] ?? false) === true
        && ($smsAccepted['message'] ?? '') === 'درخواست ثبت شد.'
        && ($smsAccepted['providerRequestId'] ?? '') === '0',
    'SMS pattern parser accepts the documented 201/messages response without claiming delivery'
);
$smsRejected = dent_sms_parse_pattern_response([
    'status' => 'error',
    'messages' => ['recipient' => ['شماره مقصد نامعتبر است.']],
], 422);
unit_assert(
    ($smsRejected['success'] ?? true) === false
        && ($smsRejected['acceptanceOnly'] ?? true) === false
        && ($smsRejected['message'] ?? '') === 'شماره مقصد نامعتبر است.',
    'SMS pattern parser preserves provider rejection details'
);
$smsFalseSuccess = dent_sms_parse_pattern_response([
    'status' => 'success',
    'message' => 'legacy response',
], 500);
unit_assert(
    ($smsFalseSuccess['success'] ?? true) === false
        && ($smsFalseSuccess['message'] ?? '') === 'سرویس پیامکی موقتاً در دسترس نیست.',
    'SMS pattern parser rejects success-shaped bodies on failed HTTP responses'
);

// ---------------------------------------------------------------------------
// Global search: text normalization + matching
// ---------------------------------------------------------------------------
unit_assert(
    !navid_should_announce_new_assignment(null, false),
    'navid: initial snapshot is a silent baseline'
);
unit_assert(
    navid_should_announce_new_assignment(null, true),
    'navid: a new assignment after baseline is announced'
);
unit_assert(
    !navid_should_announce_new_assignment(['fingerprint' => 'old'], true),
    'navid: an existing assignment is not announced as newly created'
);

unit_assert(
    search_normalize_text("\u{0643}\u{062A}\u{0627}\u{0628}") === search_normalize_text('کتاب'),
    'search: Arabic kaf folds to Persian keheh'
);
unit_assert(
    search_normalize_text("\u{064A}\u{0627}") === search_normalize_text("\u{06CC}\u{0627}"),
    'search: Arabic yeh folds to Persian yeh'
);
unit_assert(
    search_normalize_text("می\u{200C}شود") === search_normalize_text('میشود'),
    'search: ZWNJ is stripped before matching'
);
unit_assert(
    search_normalize_text('۱۲۳ABC') === '123abc',
    'search: Persian digits folded to Latin and text lowercased'
);
unit_assert(
    search_text_matches('جزوات ترمیمی پایان‌ترم', 'ترمیم'),
    'search: substring match succeeds'
);
unit_assert(
    !search_text_matches('سلام دنیا', 'xyz'),
    'search: unrelated needle does not match'
);
unit_assert(
    search_run_query('a', 'dentistry-1402')['tooShort'] === true,
    'search: single-character query is flagged too short'
);
unit_assert(
    search_notes_token_for_cohort('dentistry-1403') === '1403'
        && search_notes_token_for_cohort('dentistry-1402') === '1402'
        && search_notes_token_for_cohort('prosthesis-1402') === 'prosthesis-1402',
    'search: auth cohort keys map to notes tokens'
);

// ---------------------------------------------------------------------------
// Web Push: base64url helpers
// ---------------------------------------------------------------------------
$randomBytes = random_bytes(40);
unit_assert(
    push_base64url_decode(push_base64url_encode($randomBytes)) === $randomBytes,
    'push: base64url encode/decode round-trips'
);
unit_assert(
    strpos(push_base64url_encode($randomBytes), '=') === false
        && strpos(push_base64url_encode($randomBytes), '+') === false
        && strpos(push_base64url_encode($randomBytes), '/') === false,
    'push: base64url output is URL-safe and unpadded'
);

// ---------------------------------------------------------------------------
// Web Push: VAPID JWT + aes128gcm payload encryption
// ---------------------------------------------------------------------------
$cryptoReady = extension_loaded('openssl')
    && function_exists('openssl_pkey_new')
    && function_exists('openssl_pkey_derive')
    && function_exists('hash_hkdf');

if (!$cryptoReady) {
    unit_skip('push: crypto suite (openssl EC / hkdf not available in this runtime)');
} else {
    $serverKey = @openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ]);

    if (!$serverKey) {
        unit_skip('push: crypto suite (EC key generation unavailable; missing openssl.cnf)');
    } else {
        // --- VAPID JWT signs and self-verifies ---
        $serverDetails = openssl_pkey_get_details($serverKey);
        $vapid = [
            'privateKeyPem' => '',
            'publicKey' => push_base64url_encode("\x04" . $serverDetails['ec']['x'] . $serverDetails['ec']['y']),
        ];
        openssl_pkey_export($serverKey, $vapid['privateKeyPem']);

        $jwt = push_vapid_jwt('https://push.example.com', $vapid);
        $jwtParts = explode('.', $jwt);
        unit_assert(count($jwtParts) === 3, 'push: VAPID JWT has three segments');

        if (count($jwtParts) === 3) {
            $signingInput = $jwtParts[0] . '.' . $jwtParts[1];
            $rawSignature = push_base64url_decode($jwtParts[2]);
            unit_assert(strlen($rawSignature) === 64, 'push: VAPID JWT signature is 64 raw bytes');

            $r = ltrim(substr($rawSignature, 0, 32), "\x00");
            $s = ltrim(substr($rawSignature, 32, 32), "\x00");
            if ($r === '' || (ord($r[0]) & 0x80)) {
                $r = "\x00" . $r;
            }
            if ($s === '' || (ord($s[0]) & 0x80)) {
                $s = "\x00" . $s;
            }
            $der = "\x30" . chr(4 + strlen($r) + strlen($s))
                . "\x02" . chr(strlen($r)) . $r
                . "\x02" . chr(strlen($s)) . $s;
            $publicPem = openssl_pkey_get_details(openssl_pkey_get_private($vapid['privateKeyPem']))['key'];
            unit_assert(
                openssl_verify($signingInput, $der, $publicPem, OPENSSL_ALGO_SHA256) === 1,
                'push: VAPID JWT signature verifies with its public key'
            );
        }

        // --- aes128gcm payload encrypts and decrypts back (RFC 8291) ---
        $uaKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        $uaDetails = openssl_pkey_get_details($uaKey);
        $uaPublicRaw = "\x04" . $uaDetails['ec']['x'] . $uaDetails['ec']['y'];
        $authSecret = random_bytes(16);
        $message = json_encode(['title' => 'تست', 'body' => 'پیام آزمایشی', 'url' => '/account/#notifications'], JSON_UNESCAPED_UNICODE);

        $encrypted = push_encrypt_payload($message, $uaPublicRaw, $authSecret);
        unit_assert(is_array($encrypted) && isset($encrypted['body']), 'push: payload encryption returns a body');

        if (is_array($encrypted) && isset($encrypted['body'])) {
            $body = $encrypted['body'];
            $salt = substr($body, 0, 16);
            $idLength = ord($body[20]);
            $serverPublicRaw = substr($body, 21, $idLength);
            $cipherWithTag = substr($body, 21 + $idLength);

            $serverPublicKey = openssl_pkey_get_public(push_p256_public_pem_from_raw($serverPublicRaw));
            $shared = openssl_pkey_derive($serverPublicKey, $uaKey, 32);
            $keyInfo = "WebPush: info\x00" . $uaPublicRaw . $serverPublicRaw;
            $ikm = hash_hkdf('sha256', $shared, 32, $keyInfo, $authSecret);
            $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
            $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

            $tag = substr($cipherWithTag, -16);
            $cipher = substr($cipherWithTag, 0, -16);
            $plain = openssl_decrypt($cipher, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '');
            $plain = $plain === false ? '' : rtrim($plain, "\x02");

            unit_assert($plain === $message, 'push: aes128gcm payload decrypts back to the original message');
        }
    }
}

// ---------------------------------------------------------------------------
// Exams: schema-v5 continuity + learning-state normalization
// ---------------------------------------------------------------------------
$legacyExamStore = [
    'schemaVersion' => 4,
    'examRecords' => [
        'shared:unit-test:1' => [
            'reportsByUser' => [
                '4020001' => [
                    'answers' => [0, 1, null],
                    'totalQuestions' => 3,
                    'correct' => 1,
                    'wrong' => 1,
                    'unanswered' => 1,
                    'percent' => 33.3,
                    'startedAt' => '2026-01-01T10:00:00+03:30',
                    'submittedAt' => '2026-01-01T10:10:00+03:30',
                ],
            ],
        ],
    ],
];
$normalizedExamStore = dent_exams_normalize_store($legacyExamStore);
$normalizedExamRecord = $normalizedExamStore['examRecords']['shared:unit-test:1'] ?? [];
unit_assert(
    ($normalizedExamStore['schemaVersion'] ?? 0) === 5,
    'exams: legacy store normalizes to schema v5'
);
unit_assert(
    count($normalizedExamRecord['attemptsByUser']['4020001'] ?? []) === 1,
    'exams: legacy current report is backfilled into attempt history'
);
$normalizedExamRecord['studyStateByUser']['4020001'] = dent_exams_normalize_study_state([
    'notesByQuestion' => ['1' => 'نکته تست'],
    'highlightsByQuestion' => ['1' => [['start' => 0, 'end' => 4]]],
    'struckOptionsByQuestion' => ['1' => [2]],
    'mistakeQuestionIndexes' => [1],
]);
$normalizedExamStore['examRecords']['shared:unit-test:1'] = $normalizedExamRecord;
$learningSummary = dent_exams_user_learning_summary($normalizedExamStore, '4020001');
unit_assert(
    ($learningSummary['attemptCount'] ?? 0) === 1
        && ($learningSummary['noteCount'] ?? 0) === 1
        && ($learningSummary['highlightCount'] ?? 0) === 1
        && ($learningSummary['struckOptionCount'] ?? 0) === 1
        && ($learningSummary['mistakeQuestionCount'] ?? 0) === 1,
    'exams: owner learning summary counts attempts and study tools'
);

$legacyPurchaseOrders = [
    [
        'id' => 1,
        'status' => PAYMENTS_ORDER_STATUS_SUCCESS,
        'user_id' => '4020001',
        'paid_at' => '2026-07-24T20:00:00+03:30',
    ],
    [
        'id' => 2,
        'status' => PAYMENTS_ORDER_STATUS_SUCCESS,
        'payer_student_number' => '4020001',
        'paid_at' => '2026-07-24T22:00:00+03:30',
    ],
    [
        'id' => 3,
        'status' => PAYMENTS_ORDER_STATUS_PENDING,
        'payer_student_number' => '4020001',
        'created_at' => '2026-07-24T19:00:00+03:30',
    ],
];
$eligibleLegacyPurchase = dent_exams_find_eligible_legacy_purchase(
    $legacyPurchaseOrders,
    '۴۰۲۰۰۰۱',
    '2026-07-24T21:53:02+03:30',
    PAYMENTS_ORDER_STATUS_SUCCESS
);
unit_assert(
    (int) ($eligibleLegacyPurchase['id'] ?? 0) === 1,
    'exams: legacy purchase grant accepts only successful purchases completed before the cutoff'
);

$examQuizSource = (string) file_get_contents(__DIR__ . '/../public_html/assets/site/scripts/exam-quiz.js');
unit_assert(
    str_contains($examQuizSource, 'normalizeQuestionMedia')
        && str_contains($examQuizSource, 'normalizeCaseContext')
        && str_contains($examQuizSource, 'normalizeQuestionDifficulty')
        && str_contains($examQuizSource, 'buildCustomPractice')
        && str_contains($examQuizSource, 'review-weak-topic'),
    'exams: phase-3 shared client keeps media, clinical-case, difficulty, custom-practice and recommendation contracts'
);
unit_assert(
    str_contains($examQuizSource, '/^(?:javascript|data|vbscript):/iu')
        && str_contains($examQuizSource, '/^https:\\/\\//iu.test(url)'),
    'exams: question media URL normalization rejects active-content schemes and only allows local or HTTPS media'
);

$diagnostics2Course = function_exists('dent_exams_diagnostics2_term6_course')
    ? dent_exams_diagnostics2_term6_course(true)
    : [];
$diagnostics2Exams = is_array($diagnostics2Course['exams'] ?? null) ? $diagnostics2Course['exams'] : [];
$diagnostics2QuestionCounts = array_map(
    static fn($exam): int => is_array($exam) ? count(is_array($exam['questions'] ?? null) ? $exam['questions'] : []) : 0,
    $diagnostics2Exams
);
$diagnostics2FirstQuestion = is_array($diagnostics2Exams[0]['questions'][0] ?? null)
    ? $diagnostics2Exams[0]['questions'][0]
    : [];
unit_assert(
    count($diagnostics2Exams) === 16
        && array_sum($diagnostics2QuestionCounts) === 660
        && count(array_filter($diagnostics2QuestionCounts, static fn(int $count): bool => $count > 0)) === 16,
    'exams: diagnostics-2 term-6 imports 16 active practice exams with 660 questions'
);
unit_assert(
    in_array('محل پاسخ در منبع', array_column($diagnostics2FirstQuestion['answerMeta'] ?? [], 'label'), true)
        && in_array('دلیل درست‌بودن', array_column($diagnostics2FirstQuestion['answerSections'] ?? [], 'label'), true)
        && in_array('بررسی گزینه‌ها', array_column($diagnostics2FirstQuestion['answerSections'] ?? [], 'label'), true),
    'exams: diagnostics-2 term-6 preserves answer-sheet sections in structured response cards'
);

if (function_exists('dent_exams_bootstrap_modules')) {
    dent_exams_bootstrap_modules();
}
$restorativeFinalSampleCourse = function_exists('dent_exams_restorative_theory1_final_sample_course')
    ? dent_exams_restorative_theory1_final_sample_course()
    : [];
$restorativeFinalSampleExams = is_array($restorativeFinalSampleCourse['exams'] ?? null)
    ? $restorativeFinalSampleCourse['exams']
    : [];
$restorativeFinalSampleQuestionCounts = array_map(
    static fn($exam): int => is_array($exam) ? count(is_array($exam['questions'] ?? null) ? $exam['questions'] : []) : 0,
    $restorativeFinalSampleExams
);
$restorativeFinalSampleFirstQuestion = is_array($restorativeFinalSampleExams[0]['questions'][0] ?? null)
    ? $restorativeFinalSampleExams[0]['questions'][0]
    : [];
$restorativeFinalSampleQuestions = [];
foreach ($restorativeFinalSampleExams as $exam) {
    foreach ((is_array($exam['questions'] ?? null) ? $exam['questions'] : []) as $question) {
        if (is_array($question)) {
            $restorativeFinalSampleQuestions[] = $question;
        }
    }
}
unit_assert(
    $restorativeFinalSampleQuestionCounts === [62, 32, 32],
    'exams: restorative theory 1 final sample imports all three supplied exams with 126 questions'
);
unit_assert(
    in_array('پاسخ تشریحی و نکات آموزشی', array_column($restorativeFinalSampleFirstQuestion['answerSections'] ?? [], 'label'), true)
        && in_array('منبع / رفرنس', array_column($restorativeFinalSampleFirstQuestion['answerSections'] ?? [], 'label'), true)
        && in_array('بررسی تک‌تک گزینه‌ها', array_column($restorativeFinalSampleFirstQuestion['answerSections'] ?? [], 'label'), true)
        && count($restorativeFinalSampleQuestions) === 126
        && count(array_filter($restorativeFinalSampleQuestions, static function (array $question): bool {
            return count($question['options'] ?? []) === 4
                && count($question['answerSections'] ?? []) >= 3
                && count(array_filter($question['optionRationales'] ?? [])) === 4;
        })) === 126,
    'exams: restorative final sample keeps explanatory answer sections and four option rationales'
);
$restorativeFinalSampleQuestionsByNumber = [];
foreach ((is_array($restorativeFinalSampleExams[0]['questions'] ?? null) ? $restorativeFinalSampleExams[0]['questions'] : []) as $question) {
    if (is_array($question)) {
        $restorativeFinalSampleQuestionsByNumber[(int) ($question['number'] ?? 0)] = $question;
    }
}
$restorativeFinalSampleNumberedPromptFragments = [
    9 => ['۱- تغییر رنگ قهوه ای', '۳- پوسیدگی در نوک کاسپ'],
    36 => ['۱- آموزش رعایت بهداشت', '۷- follow up'],
    57 => ['۱- راحتی بیمار', '۶- ثبات اکلوزالی'],
    59 => ['۱- سایش سطوح اکلوزال', '۴- لزوم درمان اندو'],
];
$restorativeFinalSampleCompleteNumberedPrompts = true;
foreach ($restorativeFinalSampleNumberedPromptFragments as $number => $fragments) {
    $questionText = (string) ($restorativeFinalSampleQuestionsByNumber[$number]['question'] ?? '');
    foreach ($fragments as $fragment) {
        if (!str_contains($questionText, $fragment)) {
            $restorativeFinalSampleCompleteNumberedPrompts = false;
        }
    }
}
unit_assert(
    $restorativeFinalSampleCompleteNumberedPrompts,
    'exams: restorative final sample preserves numbered statements inside questions 9, 36, 57, and 59'
);

$homeHighlightsSeed = dent_exams_home_highlights_courses_for_cohort('dentistry-1402', 0);
$homeHighlightExpiryTimes = array_values(array_filter(array_map(
    static fn(array $course): int|false => strtotime((string) ($course['curriculum']['finalExam']['expiresAt'] ?? '')),
    $homeHighlightsSeed
), static fn(int|false $timestamp): bool => $timestamp !== false));
$homeHighlightFirstExpiry = $homeHighlightExpiryTimes !== [] ? min($homeHighlightExpiryTimes) : 0;
$homeHighlightsBeforeExpiry = dent_exams_home_highlights_courses_for_cohort(
    'dentistry-1402',
    max(0, $homeHighlightFirstExpiry - 1)
);
$homeHighlightsAfterExpiry = dent_exams_home_highlights_courses_for_cohort(
    'dentistry-1402',
    $homeHighlightFirstExpiry
);
unit_assert(
    $homeHighlightFirstExpiry > 0
        && count($homeHighlightsBeforeExpiry) === 2
        && count($homeHighlightsAfterExpiry) < 2
        && array_diff(
            array_column($homeHighlightsAfterExpiry, 'slug'),
            array_column($homeHighlightsBeforeExpiry, 'slug')
        ) === [],
    'exams: home highlights use the two-entry index and never backfill an expired item'
);
$homeHighlightsApiSource = (string) file_get_contents(__DIR__ . '/../public_html/api/exams_home_highlights_api.php');
$appHomeSource = (string) file_get_contents(__DIR__ . '/../public_html/assets/site/scripts/app-home.js');
unit_assert(
    str_contains($homeHighlightsApiSource, "cohortKey === dent_external_site_users_cohort_key()")
        && str_contains($appHomeSource, 'cohortKey === "site-users"'),
    'exams: ordinary site users receive home highlights without changing auth cohort storage'
);
unit_assert(
    !str_contains($homeHighlightsApiSource, 'exams_store.php')
        && !str_contains($homeHighlightsApiSource, 'payments_store.php')
        && !str_contains($homeHighlightsApiSource, 'exams_modules.php'),
    'exams: home highlights endpoint avoids the full exam, payment and module stores'
);

$handoffNow = 1788600000;
$providerUrl = 'https://gateway.zibal.ir/start/123456789';
$handoffUrl = dent_zibal_handoff_url($providerUrl, '123456789', $handoffNow);
$handoffQuery = (string) parse_url($handoffUrl, PHP_URL_QUERY);
unit_assert(parse_url($handoffUrl, PHP_URL_HOST) === 'dentistry1402tums.ir'
    && !str_contains($handoffUrl, 'gateway.zibal.ir/start/'), 'handoff: canonical first-party public URL');
$validHandoff = dent_zibal_handoff_validate($handoffQuery, $handoffNow);
unit_assert($validHandoff['valid'] && $validHandoff['trackId'] === '123456789', 'handoff: valid signature');
unit_assert(dent_zibal_handoff_validate($handoffQuery, $handoffNow + 900)['reason'] === 'expired', 'handoff: expiry boundary');
foreach ([
    'track mutation' => str_replace('123456789', '123456788', $handoffQuery),
    'expiry mutation' => str_replace((string) ($handoffNow + 900), (string) ($handoffNow + 901), $handoffQuery),
    'signature mutation' => substr($handoffQuery, 0, -1) . (str_ends_with($handoffQuery, '0') ? '1' : '0'),
    'unknown key' => $handoffQuery . '&other=1',
    'open redirect' => $handoffQuery . '&url=https://evil.test',
    'duplicate' => $handoffQuery . '&trackId=2',
    'invalid characters' => str_replace('123456789', '../bad', $handoffQuery),
    'array key' => str_replace('trackId=', 'trackId%5B%5D=', $handoffQuery),
    'newline' => $handoffQuery . "\n",
] as $label => $query) {
    unit_assert(!dent_zibal_handoff_validate($query, $handoffNow)['valid'], 'handoff: reject ' . $label);
}
foreach (['http://gateway.zibal.ir/start/123456789', 'https://gateway.zibal.ir.evil.test/start/123456789',
    $providerUrl . '?url=https://evil.test', $providerUrl . '#', 'https://user@gateway.zibal.ir/start/123456789',
    'https://gateway.zibal.ir:443/start/123456789', 'https://gateway.zibal.ir/start/0123'] as $badUrl) {
    $rejected = false;
    try { dent_zibal_handoff_url($badUrl, '123456789', $handoffNow); }
    catch (InvalidArgumentException $error) { $rejected = true; }
    unit_assert($rejected, 'handoff: provider URL rejected');
}
$wrongTrackRejected = false;
try { dent_zibal_handoff_url($providerUrl, '123456788', $handoffNow); }
catch (InvalidArgumentException $error) { $wrongTrackRejected = true; }
unit_assert($wrongTrackRejected, 'handoff: provider track matches expected transaction');
$handoffHtml = dent_zibal_handoff_document($validHandoff, 'unit-nonce');
unit_assert(str_contains($handoffHtml, '<meta name="referrer" content="origin">')
    && str_contains($handoffHtml, 'referrerpolicy="origin"')
    && str_contains($handoffHtml, 'window.location.replace('), 'handoff: document navigation and no-JS fallback');
unit_assert(!str_contains(dent_zibal_handoff_document(['valid' => false], 'unit-nonce'), 'gateway.zibal.ir'),
    'handoff: invalid link cannot navigate to provider');
$botHandoff = dent_bot_payment_public_redirect([
    'gateway' => 'zibal', 'trackId' => '123456789', 'redirectUrl' => $providerUrl,
], 'zibal');
unit_assert(parse_url($botHandoff, PHP_URL_HOST) === 'dentistry1402tums.ir'
    && !str_contains($botHandoff, 'gateway.zibal.ir/start/'), 'handoff: bot-facing Zibal URL is wrapped');
$legacyResponse = dent_bot_payment_existing_response([
    'public_token' => str_repeat('t', 24), 'amount' => 20000, 'status' => PAYMENTS_ORDER_STATUS_PENDING,
    'gateway' => 'zibal', 'authority' => '123456789',
    'gateway_response_snapshot' => ['start' => ['redirectUrl' => $providerUrl]],
]);
unit_assert(is_array($legacyResponse)
    && parse_url((string) $legacyResponse['redirectUrl'], PHP_URL_HOST) === 'dentistry1402tums.ir',
    'handoff: legacy pending bot order is wrapped at response time');
$otherProviderUrl = 'https://www.zarinpal.com/pg/StartPay/unit-fixture';
unit_assert(dent_bot_payment_public_redirect([
    'gateway' => 'zarinpal', 'redirectUrl' => $otherProviderUrl,
], 'zarinpal') === $otherProviderUrl, 'handoff: non-Zibal provider behavior is preserved');

echo "\n";
echo sprintf(
    "Unit tests: %d passed, %d failed, %d skipped (of %d).\n",
    $GLOBALS['unit_total'] - $GLOBALS['unit_failures'],
    $GLOBALS['unit_failures'],
    $GLOBALS['unit_skipped'],
    $GLOBALS['unit_total']
);

exit($GLOBALS['unit_failures'] > 0 ? 1 : 0);
