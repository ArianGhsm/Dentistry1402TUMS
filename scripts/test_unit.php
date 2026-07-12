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

require_once __DIR__ . '/../public_html/api/search_store.php';
require_once __DIR__ . '/../public_html/api/push_store.php';
require_once __DIR__ . '/../public_html/api/analytics_store.php';
require_once __DIR__ . '/../public_html/api/exams_store.php';

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
// Global search: text normalization + matching
// ---------------------------------------------------------------------------
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

echo "\n";
echo sprintf(
    "Unit tests: %d passed, %d failed, %d skipped (of %d).\n",
    $GLOBALS['unit_total'] - $GLOBALS['unit_failures'],
    $GLOBALS['unit_failures'],
    $GLOBALS['unit_skipped'],
    $GLOBALS['unit_total']
);

exit($GLOBALS['unit_failures'] > 0 ? 1 : 0);
