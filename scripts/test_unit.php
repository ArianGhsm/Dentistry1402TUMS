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
require_once __DIR__ . '/../public_html/api/auth_store.php';
require_once __DIR__ . '/../public_html/api/exams_home_highlights.php';
require_once __DIR__ . '/../public_html/api/private_notes_delivery.php';

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

$homeHighlightsBeforeExpiry = dent_exams_home_highlights_courses_for_cohort(
    'dentistry-1402',
    strtotime('2026-07-14T20:00:00+03:30')
);
$homeHighlightsAfterExpiry = dent_exams_home_highlights_courses_for_cohort(
    'dentistry-1402',
    strtotime('2026-07-23T00:00:00+03:30')
);
unit_assert(
    count($homeHighlightsBeforeExpiry) === 2 && count($homeHighlightsAfterExpiry) === 0,
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

// ---------------------------------------------------------------------------
// Private notes: access foundation
// ---------------------------------------------------------------------------
$privateNotesBaseStore = private_notes_normalize_store([
    'schemaVersion' => 1,
    'semesters' => [
        'pnsem-unit1402t6' => [
            'id' => 'pnsem-unit1402t6',
            'cohortKey' => 'dentistry-1402',
            'title' => 'Unit test semester',
            'termNumber' => 6,
        ],
    ],
    'courses' => [
        'pncrs-unitoralpath' => [
            'id' => 'pncrs-unitoralpath',
            'cohortKey' => 'dentistry-1402',
            'semesterId' => 'pnsem-unit1402t6',
            'title' => 'Unit Test Oral Pathology',
        ],
    ],
    'documents' => [
        'pndoc-unitatlas' => [
            'id' => 'pndoc-unitatlas',
            'cohortKey' => 'dentistry-1402',
            'title' => 'Unit Test Atlas',
            'courseId' => 'pncrs-unitoralpath',
            'semesterId' => 'pnsem-unit1402t6',
            'originalFileRef' => 'dentistry-1402/unit-atlas.pdf',
            'processingStatus' => 'ready',
            'pageCount' => 12,
            'uploaderUserKey' => '40211272003',
            'publicationStatus' => 'published',
        ],
    ],
]);
$privateNotesStudent = ['studentNumber' => '4020001', 'role' => 'student', 'cohortKey' => 'dentistry-1402'];
$privateNotesOwner = ['studentNumber' => dent_owner_student_number(), 'role' => 'owner', 'cohortKey' => 'dentistry-1402'];

$privateNotesDecision = private_notes_user_can_view_document($privateNotesBaseStore, $privateNotesStudent, 'pndoc-unitatlas', '2026-07-16T12:00:00+03:30');
unit_assert(
    $privateNotesDecision['allowed'] === false && $privateNotesDecision['reason'] === 'active-membership-required',
    'private notes: login without active course membership is denied'
);

$privateNotesWithMembership = $privateNotesBaseStore;
$privateNotesWithMembership['courseMemberships']['pnmem-unitstudent'] = [
    'id' => 'pnmem-unitstudent',
    'userKey' => '4020001',
    'courseId' => 'pncrs-unitoralpath',
    'semesterId' => 'pnsem-unit1402t6',
    'role' => 'writer',
    'contributionStatus' => 'approved',
    'accessStatus' => 'active',
];
$privateNotesDecision = private_notes_user_can_view_document($privateNotesWithMembership, $privateNotesStudent, 'pndoc-unitatlas', '2026-07-16T12:00:00+03:30');
unit_assert(
    $privateNotesDecision['allowed'] === false && $privateNotesDecision['reason'] === 'document-permission-required',
    'private notes: course membership without document permission is denied'
);

$privateNotesAllowedStore = $privateNotesWithMembership;
$privateNotesAllowedStore['documentPermissions']['pnperm-unitstudent'] = [
    'id' => 'pnperm-unitstudent',
    'documentId' => 'pndoc-unitatlas',
    'userKey' => '4020001',
    'membershipId' => 'pnmem-unitstudent',
    'permission' => 'view',
    'status' => 'active',
];
$privateNotesDecision = private_notes_user_can_view_document($privateNotesAllowedStore, $privateNotesStudent, 'pndoc-unitatlas', '2026-07-16T12:00:00+03:30');
unit_assert(
    $privateNotesDecision['allowed'] === true && $privateNotesDecision['reason'] === 'allowed',
    'private notes: approved membership plus active document permission is allowed'
);

$privateNotesWarningStore = $privateNotesAllowedStore;
$privateNotesWarningStore['courseMemberships']['pnmem-unitstudent']['accessStatus'] = 'warning';
$privateNotesWarningStore['documentPermissions']['pnperm-unitstudent']['status'] = 'warning';
$privateNotesDecision = private_notes_user_can_view_document($privateNotesWarningStore, $privateNotesStudent, 'pndoc-unitatlas', '2026-07-16T12:00:00+03:30');
unit_assert(
    $privateNotesDecision['allowed'] === true,
    'private notes: warning access remains viewable but traceable'
);

$privateNotesSuspendedStore = $privateNotesAllowedStore;
$privateNotesSuspendedStore['courseMemberships']['pnmem-unitstudent']['accessStatus'] = 'suspended';
$privateNotesDecision = private_notes_user_can_view_document($privateNotesSuspendedStore, $privateNotesStudent, 'pndoc-unitatlas', '2026-07-16T12:00:00+03:30');
unit_assert(
    $privateNotesDecision['allowed'] === false && $privateNotesDecision['reason'] === 'active-membership-required',
    'private notes: suspended course membership is denied'
);

$privateNotesRevokedPermissionStore = $privateNotesAllowedStore;
$privateNotesRevokedPermissionStore['documentPermissions']['pnperm-unitstudent']['status'] = 'revoked';
$privateNotesDecision = private_notes_user_can_view_document($privateNotesRevokedPermissionStore, $privateNotesStudent, 'pndoc-unitatlas', '2026-07-16T12:00:00+03:30');
unit_assert(
    $privateNotesDecision['allowed'] === false && $privateNotesDecision['reason'] === 'document-permission-required',
    'private notes: revoked document permission is denied'
);

$privateNotesExpiredStore = $privateNotesAllowedStore;
$privateNotesExpiredStore['courseMemberships']['pnmem-unitstudent']['accessExpiresAt'] = '2026-07-15T23:59:00+03:30';
$privateNotesDecision = private_notes_user_can_view_document($privateNotesExpiredStore, $privateNotesStudent, 'pndoc-unitatlas', '2026-07-16T12:00:00+03:30');
unit_assert(
    $privateNotesDecision['allowed'] === false && $privateNotesDecision['reason'] === 'active-membership-required',
    'private notes: expired membership is denied'
);

$privateNotesTempSuspensionStore = $privateNotesAllowedStore;
$privateNotesTempSuspensionStore['temporarySuspensions']['pnsus-unitstudent'] = [
    'id' => 'pnsus-unitstudent',
    'userKey' => '4020001',
    'documentId' => 'pndoc-unitatlas',
    'status' => 'active',
    'startsAt' => '2026-07-16T00:00:00+03:30',
    'expiresAt' => '2026-07-17T00:00:00+03:30',
];
$privateNotesDecision = private_notes_user_can_view_document($privateNotesTempSuspensionStore, $privateNotesStudent, 'pndoc-unitatlas', '2026-07-16T12:00:00+03:30');
unit_assert(
    $privateNotesDecision['allowed'] === false && $privateNotesDecision['reason'] === 'temporarily-suspended',
    'private notes: active temporary suspension is denied'
);

$privateNotesOwnerDecision = private_notes_user_can_view_document($privateNotesBaseStore, $privateNotesOwner, 'pndoc-unitatlas', '2026-07-16T12:00:00+03:30');
unit_assert(
    $privateNotesOwnerDecision['allowed'] === true && $privateNotesOwnerDecision['reason'] === 'owner',
    'private notes: owner access uses the existing site role system'
);

$privateNotesManagerStore = $privateNotesAllowedStore;
$privateNotesManagerStore['courseMemberships']['pnmem-manager'] = [
    'id' => 'pnmem-manager',
    'userKey' => '4020002',
    'courseId' => 'pncrs-unitoralpath',
    'semesterId' => 'pnsem-unit1402t6',
    'role' => 'manager',
    'contributionStatus' => 'approved',
    'accessStatus' => 'active',
];
unit_assert(
    private_notes_user_can_manage_course(
        $privateNotesManagerStore,
        ['studentNumber' => '4020002', 'role' => 'student', 'cohortKey' => 'dentistry-1402'],
        'pncrs-unitoralpath',
        'pnsem-unit1402t6'
    ) === true,
    'private notes: approved private course manager may manage uploads'
);
unit_assert(
    private_notes_user_can_manage_course(
        $privateNotesManagerStore,
        ['studentNumber' => '4020001', 'role' => 'student', 'cohortKey' => 'dentistry-1402'],
        'pncrs-unitoralpath',
        'pnsem-unit1402t6'
    ) === false,
    'private notes: writer membership alone does not grant upload management'
);

$privateNotesPayload = private_notes_document_admin_payload([
    'id' => 'pndoc-unitatlas',
    'title' => 'Unit Test Atlas',
    'originalFileRef' => '2026/07/private.pdf',
    'originalStorageKey' => '2026/07/private.pdf',
    'processingStatus' => 'ready',
]);
unit_assert(
    !array_key_exists('originalFileRef', $privateNotesPayload)
        && !array_key_exists('originalStorageKey', $privateNotesPayload)
        && ($privateNotesPayload['hasOriginalFile'] ?? false) === true,
    'private notes: admin document payload does not expose the original PDF path'
);

putenv('DENT_PRIVATE_NOTES_TILE_SIGNING_SECRET=unit-private-notes-tile-secret');
$_SERVER['HTTP_USER_AGENT'] = 'DentPrivateNotesUnit/1.0';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$privateNotesTileStore = $privateNotesAllowedStore;
$privateNotesTileStore['documents']['pndoc-unitatlas']['assetsStorageKey'] = 'documents/pndoc-unitatlas';
$privateNotesTileStore['documents']['pndoc-unitatlas']['pages'] = [
    '1' => [
        'pageNumber' => 1,
        'width' => 1700,
        'height' => 2200,
        'levels' => [
            [
                'level' => 2,
                'scale' => 0.25,
                'width' => 425,
                'height' => 550,
                'tileSize' => 512,
                'tiles' => [
                    [
                        'x' => 512,
                        'y' => 1024,
                        'width' => 512,
                        'height' => 512,
                        'storageKey' => 'documents/pndoc-unitatlas/p1/z2/tile-512-1024.png',
                        'bytes' => 1234,
                    ],
                ],
            ],
        ],
    ],
];
$privateNotesTileStore['registeredDevices']['pndev-unitactive'] = [
    'id' => 'pndev-unitactive',
    'userKey' => '4020001',
    'label' => 'Unit device',
    'tokenHash' => private_notes_device_token_hash('4020001', 'unit-device-token-abcdefghijklmnopqrstuvwxyz'),
    'userAgentHash' => private_notes_request_user_agent_hash(),
    'status' => 'active',
    'firstSeenAt' => '2026-07-16T12:00:00+03:30',
    'lastSeenAt' => '2026-07-16T12:00:00+03:30',
];
$privateNotesTileStore['activeViewingSessions']['pnses-unitactive'] = [
    'id' => 'pnses-unitactive',
    'userKey' => '4020001',
    'documentId' => 'pndoc-unitatlas',
    'deviceId' => 'pndev-unitactive',
    'status' => 'active',
    'startedAt' => '2026-07-16T12:00:00+03:30',
    'lastSeenAt' => '2026-07-16T12:00:00+03:30',
    'ipHash' => private_notes_request_ip_hash(),
    'userAgentHash' => private_notes_request_user_agent_hash(),
];
$privateNotesTileStore = private_notes_normalize_store($privateNotesTileStore);
$privateNotesTileRequest = [
    'documentId' => 'pndoc-unitatlas',
    'sessionId' => 'pnses-unitactive',
    'pageNumber' => 1,
    'zoomLevel' => 2,
    'tileX' => 512,
    'tileY' => 1024,
];
$privateNotesTileAuth = private_notes_authorize_tile_request(
    $privateNotesTileStore,
    $privateNotesStudent,
    $privateNotesTileRequest,
    '2026-07-16T12:01:00+03:30'
);
unit_assert(
    $privateNotesTileAuth['ok'] === true
        && ($privateNotesTileAuth['tile']['storageKey'] ?? '') === 'documents/pndoc-unitatlas/p1/z2/tile-512-1024.png',
    'private notes: tile request requires active access, session, device and tile metadata'
);
$privateNotesRevokedDeviceStore = $privateNotesTileStore;
$privateNotesRevokedDeviceStore['registeredDevices']['pndev-unitactive']['status'] = 'revoked';
$privateNotesTileAuth = private_notes_authorize_tile_request(
    $privateNotesRevokedDeviceStore,
    $privateNotesStudent,
    $privateNotesTileRequest,
    '2026-07-16T12:01:00+03:30'
);
unit_assert(
    $privateNotesTileAuth['ok'] === false && $privateNotesTileAuth['reason'] === 'registered-device-not-allowed',
    'private notes: revoked registered device cannot receive tiles'
);
$privateNotesWrongTileRequest = $privateNotesTileRequest;
$privateNotesWrongTileRequest['tileX'] = 0;
$privateNotesTileAuth = private_notes_authorize_tile_request(
    $privateNotesTileStore,
    $privateNotesStudent,
    $privateNotesWrongTileRequest,
    '2026-07-16T12:01:00+03:30'
);
unit_assert(
    $privateNotesTileAuth['ok'] === false && $privateNotesTileAuth['reason'] === 'tile-not-found',
    'private notes: unknown tile coordinates are denied'
);
$privateNotesChangedUaStore = $privateNotesTileStore;
$_SERVER['HTTP_USER_AGENT'] = 'DentPrivateNotesUnit/changed';
$privateNotesTileAuth = private_notes_authorize_tile_request(
    $privateNotesChangedUaStore,
    $privateNotesStudent,
    $privateNotesTileRequest,
    '2026-07-16T12:01:00+03:30'
);
unit_assert(
    $privateNotesTileAuth['ok'] === false && $privateNotesTileAuth['reason'] === 'viewing-session-user-agent-mismatch',
    'private notes: viewing session is bound to the registered device user agent'
);
$_SERVER['HTTP_USER_AGENT'] = 'DentPrivateNotesUnit/1.0';

$privateNotesTokenNow = strtotime('2026-07-16T12:01:00+03:30');
$privateNotesToken = private_notes_sign_tile_token([
    'v' => 1,
    'uid' => '4020001',
    'doc' => 'pndoc-unitatlas',
    'sid' => 'pnses-unitactive',
    'page' => 1,
    'z' => 2,
    'x' => 512,
    'y' => 1024,
    'exp' => $privateNotesTokenNow + 60,
    'nonce' => 'unit',
]);
$privateNotesTokenCheck = private_notes_validate_tile_token(
    $privateNotesToken,
    array_merge($privateNotesTileRequest, ['uid' => '4020001']),
    $privateNotesTokenNow
);
unit_assert(
    $privateNotesTokenCheck['ok'] === true,
    'private notes: signed tile token validates for exact user, document, session, page, zoom and tile'
);
$privateNotesTokenMismatch = private_notes_validate_tile_token(
    $privateNotesToken,
    array_merge($privateNotesTileRequest, ['uid' => '4020001', 'pageNumber' => 2]),
    $privateNotesTokenNow
);
unit_assert(
    $privateNotesTokenMismatch['ok'] === false
        && $privateNotesTokenMismatch['reason'] === 'tile-token-claim-mismatch'
        && $privateNotesTokenMismatch['claim'] === 'page',
    'private notes: tile token cannot be reused for another page'
);
$privateNotesTokenExpired = private_notes_validate_tile_token(
    $privateNotesToken,
    array_merge($privateNotesTileRequest, ['uid' => '4020001']),
    $privateNotesTokenNow + 61
);
unit_assert(
    $privateNotesTokenExpired['ok'] === false && $privateNotesTokenExpired['reason'] === 'tile-token-expired',
    'private notes: expired tile token is rejected'
);
$privateNotesTokenTampered = substr($privateNotesToken, 0, -1) . (substr($privateNotesToken, -1) === 'A' ? 'B' : 'A');
$privateNotesTokenBadSignature = private_notes_validate_tile_token(
    $privateNotesTokenTampered,
    array_merge($privateNotesTileRequest, ['uid' => '4020001']),
    $privateNotesTokenNow
);
unit_assert(
    $privateNotesTokenBadSignature['ok'] === false && $privateNotesTokenBadSignature['reason'] === 'tile-token-bad-signature',
    'private notes: tile token signature uses constant-time verification and rejects tampering'
);

$unitTmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dent-private-notes-unit-' . bin2hex(random_bytes(4));
mkdir($unitTmpDir, 0755, true);
$invalidPdfPath = $unitTmpDir . DIRECTORY_SEPARATOR . 'not-a-pdf.pdf';
file_put_contents($invalidPdfPath, "not a pdf\n");
$invalidPdfValidation = private_notes_validate_pdf_file($invalidPdfPath, 'not-a-pdf.pdf', 'application/pdf');
unit_assert(
    $invalidPdfValidation['ok'] === false,
    'private notes: invalid PDF signature is rejected'
);

$wrongMimePath = $unitTmpDir . DIRECTORY_SEPARATOR . 'wrong-mime.pdf';
file_put_contents($wrongMimePath, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");
$wrongMimeValidation = private_notes_validate_pdf_file($wrongMimePath, 'wrong-mime.pdf', 'text/plain');
if (private_notes_detect_mime_type($wrongMimePath) === '') {
    unit_assert(
        $wrongMimeValidation['ok'] === false && str_contains((string) ($wrongMimeValidation['error'] ?? ''), 'MIME'),
        'private notes: unsupported reported MIME type is rejected when fileinfo is unavailable'
    );
} else {
    unit_assert(
        array_key_exists('ok', $wrongMimeValidation),
        'private notes: server-side MIME detector is used when fileinfo is available'
    );
}

$encryptedPdfPath = $unitTmpDir . DIRECTORY_SEPARATOR . 'encrypted.pdf';
file_put_contents($encryptedPdfPath, "%PDF-1.4\n1 0 obj\n<< /Encrypt 2 0 R >>\nendobj\n%%EOF\n");
$encryptedPdfValidation = private_notes_validate_pdf_file($encryptedPdfPath, 'encrypted.pdf', 'application/pdf');
unit_assert(
    $encryptedPdfValidation['ok'] === false && str_contains((string) ($encryptedPdfValidation['error'] ?? ''), 'Encrypted'),
    'private notes: encrypted PDFs are rejected before processing'
);

$samplePdfPath = 'C:\\Users\\ASUS\\Downloads\\Telegram Desktop\\جلسه ۱ مبانی کامل نظری.pdf';
if (is_file($samplePdfPath)) {
    $sampleValidation = private_notes_validate_pdf_file($samplePdfPath, basename($samplePdfPath), 'application/pdf');
    unit_assert(
        $sampleValidation['ok'] === true && ($sampleValidation['sizeBytes'] ?? 0) > 0 && ($sampleValidation['sha256'] ?? '') !== '',
        'private notes: provided sample PDF passes upload validation'
    );
} else {
    unit_skip('private notes: provided sample PDF is not present on this machine');
}

$pdfInfoResult = private_notes_pdf_info($wrongMimePath);
unit_assert(
    $pdfInfoResult['ok'] === false,
    'private notes: processing fails clearly when PDF metadata cannot be read'
);

@unlink($invalidPdfPath);
@unlink($wrongMimePath);
@unlink($encryptedPdfPath);
@rmdir($unitTmpDir);

echo "\n";
echo sprintf(
    "Unit tests: %d passed, %d failed, %d skipped (of %d).\n",
    $GLOBALS['unit_total'] - $GLOBALS['unit_failures'],
    $GLOBALS['unit_failures'],
    $GLOBALS['unit_skipped'],
    $GLOBALS['unit_total']
);

exit($GLOBALS['unit_failures'] > 0 ? 1 : 0);
