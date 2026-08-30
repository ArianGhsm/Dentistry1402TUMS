<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/public_html/api/bot_store.php';

$studentNumber = dent_owner_student_number();
$authStore = dent_auth_store_seed_payload();
$authStore['users'][$studentNumber] = [
    'studentNumber' => $studentNumber,
    'name' => 'Test Owner',
    'passwordHash' => password_hash('test-password-not-used', PASSWORD_DEFAULT),
    'role' => 'owner',
    'cohortKey' => dent_primary_cohort_key(),
    'phoneNumber' => '09120000000',
    'phoneVerifiedAt' => dent_iso_now(),
    'phoneLoginEnabled' => true,
    'nationalCode' => '',
];
dent_write_json_file(dent_storage_path('dis_request/store.json'), [
    'schemaVersion' => 1,
    'responses' => [
        $studentNumber => [
            'studentNumber' => $studentNumber,
            'fields' => [
                'nationalCode' => '0013546789',
                'phoneNumber' => '09120000000',
            ],
        ],
    ],
]);
$regularStudentNumber = '402000001';
$authStore['users'][$regularStudentNumber] = [
    'studentNumber' => $regularStudentNumber,
    'name' => 'Synthetic Student',
    'passwordHash' => password_hash('test-password-not-used', PASSWORD_DEFAULT),
    'role' => 'student',
    'cohortKey' => dent_primary_cohort_key(),
    'phoneNumber' => '',
    'phoneVerifiedAt' => '',
    'phoneLoginEnabled' => false,
];
$claimStudentNumber = '402000002';
$authStore['users'][$claimStudentNumber] = [
    'studentNumber' => $claimStudentNumber,
    'name' => 'Claim Student',
    'passwordHash' => password_hash('test-password-not-used', PASSWORD_DEFAULT),
    'role' => 'student',
    'cohortKey' => dent_primary_cohort_key(),
    'phoneNumber' => '',
    'phoneVerifiedAt' => '',
    'phoneLoginEnabled' => false,
];
$secureStudentNumber = '402000003';
$authStore['users'][$secureStudentNumber] = [
    'studentNumber' => $secureStudentNumber,
    'name' => 'Secure Link Student',
    'passwordHash' => password_hash('test-password-not-used', PASSWORD_DEFAULT),
    'role' => 'student',
    'cohortKey' => dent_primary_cohort_key(),
    'phoneNumber' => '',
    'phoneVerifiedAt' => '',
    'phoneLoginEnabled' => false,
];
$importStudentNumber = '402000004';
$authStore['users'][$importStudentNumber] = [
    'studentNumber' => $importStudentNumber,
    'name' => 'Imported Student',
    'passwordHash' => password_hash('test-password-not-used', PASSWORD_DEFAULT),
    'role' => 'student',
    'cohortKey' => dent_primary_cohort_key(),
    'phoneNumber' => '',
    'phoneVerifiedAt' => '',
    'phoneLoginEnabled' => false,
];
$disconnectStudentNumber = '402000005';
$authStore['users'][$disconnectStudentNumber] = [
    'studentNumber' => $disconnectStudentNumber,
    'name' => 'Disconnect Student',
    'passwordHash' => password_hash('test-password-not-used', PASSWORD_DEFAULT),
    'role' => 'student',
    'cohortKey' => dent_primary_cohort_key(),
    'phoneNumber' => '',
    'phoneVerifiedAt' => '',
    'phoneLoginEnabled' => false,
];
$websiteDisconnectStudentNumber = '402000006';
$authStore['users'][$websiteDisconnectStudentNumber] = [
    'studentNumber' => $websiteDisconnectStudentNumber,
    'name' => 'Website Disconnect Student',
    'passwordHash' => password_hash('test-password-not-used', PASSWORD_DEFAULT),
    'role' => 'student',
    'cohortKey' => dent_primary_cohort_key(),
    'phoneNumber' => '',
    'phoneVerifiedAt' => '',
    'phoneLoginEnabled' => false,
];
dent_write_auth_store_payload($authStore);
$user = dent_get_user_record($studentNumber);
if (!is_array($user)) {
    throw new RuntimeException('Synthetic owner could not be created.');
}

$platformUserId = '654321';
$challenge = dent_bot_start_link('telegram', $platformUserId, ['authVersion' => dent_bot_canonical_auth_version()]);
$query = (string) parse_url((string) ($challenge['linkUrl'] ?? ''), PHP_URL_QUERY);
parse_str($query, $parameters);
$token = (string) ($parameters['token'] ?? '');
if ($token === '') {
    throw new RuntimeException('Synthetic link challenge was not created.');
}
dent_bot_confirm_link($token, $user);

$regularUser = dent_get_user_record($regularStudentNumber);
if (!is_array($regularUser)) {
    throw new RuntimeException('Synthetic regular user could not be created.');
}
$regularChallenge = dent_bot_start_link('telegram', '777001', ['authVersion' => dent_bot_canonical_auth_version()]);
$regularQuery = (string) parse_url((string) ($regularChallenge['linkUrl'] ?? ''), PHP_URL_QUERY);
parse_str($regularQuery, $regularParameters);
$regularToken = (string) ($regularParameters['token'] ?? '');
if ($regularToken === '') {
    throw new RuntimeException('Synthetic regular link challenge was not created.');
}
dent_bot_confirm_link($regularToken, $regularUser);
$regularBaleChallenge = dent_bot_start_link('bale', '777002', ['authVersion' => dent_bot_canonical_auth_version()]);
$regularBaleQuery = (string) parse_url((string) ($regularBaleChallenge['linkUrl'] ?? ''), PHP_URL_QUERY);
parse_str($regularBaleQuery, $regularBaleParameters);
dent_bot_confirm_link((string) ($regularBaleParameters['token'] ?? ''), $regularUser);
dent_student_assistant_test_seed_connector($regularStudentNumber, 'food');

$genericPaymentIdentity = '888001';
$genericPaymentIdentityHash = dent_bot_identity_hash('telegram', $genericPaymentIdentity);
dent_bot_store_with_lock(static function (array &$store) use ($genericPaymentIdentityHash): array {
    dent_bot_store_profile_for_identity($store, $genericPaymentIdentityHash, 'telegram', [
        'firstName' => 'Generic',
        'lastName' => 'Payer',
        'major' => 'پزشکی',
        'province' => 'خراسان رضوی',
        'institution' => 'دانشگاه علوم پزشکی مشهد',
        'entryYear' => '۱۴۰۲',
        'admissionType' => 'نیمسال اول (روزانه یا تعهدی)',
        'studentNumber' => '',
        'phoneNumber' => '09121112233',
        'verifiedAt' => dent_iso_now(),
        'isClassMember' => false,
    ]);
    return [];
});

$disconnectUser = dent_get_user_record($disconnectStudentNumber);
if (!is_array($disconnectUser)) {
    throw new RuntimeException('Synthetic disconnect user could not be created.');
}
$disconnectChallenge = dent_bot_start_link('telegram', '777005', [
    'authVersion' => dent_bot_canonical_auth_version(),
    'telegramProfile' => ['displayName' => 'Disconnect Account', 'username' => 'disconnect_test'],
]);
$disconnectQuery = (string) parse_url((string) ($disconnectChallenge['linkUrl'] ?? ''), PHP_URL_QUERY);
parse_str($disconnectQuery, $disconnectParameters);
dent_bot_confirm_link((string) ($disconnectParameters['token'] ?? ''), $disconnectUser);
$connectionStatus = dent_bot_account_connections($disconnectUser);
if (empty($connectionStatus['connections']['telegram']['connected'])
    || (string) ($connectionStatus['connections']['telegram']['platformDisplayName'] ?? '') !== 'Disconnect Account') {
    throw new RuntimeException('Synthetic account connection status is incomplete.');
}
$disconnectResult = dent_bot_disconnect_account($disconnectUser, 'telegram');
if (empty($disconnectResult['deliveryQueued']) || is_array(dent_bot_link_for_identity('telegram', '777005'))) {
    throw new RuntimeException('Synthetic account disconnect was not atomic.');
}

$websiteDisconnectUser = dent_get_user_record($websiteDisconnectStudentNumber);
if (!is_array($websiteDisconnectUser)) {
    throw new RuntimeException('Synthetic website disconnect user could not be created.');
}
$websiteDisconnectChallenge = dent_bot_start_link('telegram', '777006', [
    'authVersion' => dent_bot_canonical_auth_version(),
    'telegramProfile' => ['displayName' => 'Website Disconnect Account', 'username' => 'website_disconnect'],
]);
$websiteDisconnectQuery = (string) parse_url((string) ($websiteDisconnectChallenge['linkUrl'] ?? ''), PHP_URL_QUERY);
parse_str($websiteDisconnectQuery, $websiteDisconnectParameters);
dent_bot_confirm_link((string) ($websiteDisconnectParameters['token'] ?? ''), $websiteDisconnectUser);

$secureUser = dent_get_user_record($secureStudentNumber);
if (!is_array($secureUser)) {
    throw new RuntimeException('Synthetic secure-link user could not be created.');
}
dent_bot_submit_identity_claim('telegram', '777003', [
    'name' => 'Secure Link Student',
    'telegramProfile' => ['displayName' => 'Secure Student', 'username' => 'secure_student', 'languageCode' => 'fa'],
]);
$secureChallenge = dent_bot_start_link('telegram', '777003', ['authVersion' => dent_bot_canonical_auth_version()]);
$secureQuery = (string) parse_url((string) ($secureChallenge['linkUrl'] ?? ''), PHP_URL_QUERY);
parse_str($secureQuery, $secureParameters);
dent_bot_confirm_link((string) ($secureParameters['token'] ?? ''), $secureUser);
$ownerClaims = dent_bot_identity_claims($user);
foreach (($ownerClaims['claims'] ?? []) as $pendingClaim) {
    if (is_array($pendingClaim) && (string) ($pendingClaim['platformUserId'] ?? '') === '777003') {
        throw new RuntimeException('Secure site confirmation did not clear the pending identity claim.');
    }
}

// Seed one legacy approved claim and one pending claim so the auth-v2
// migration proves that approved links survive while every pending request is
// retired. These helpers are called only inside the isolated fixture; the
// public service actions are disabled in production.
dent_bot_submit_identity_claim('telegram', '888002', [
    'name' => 'Claim Student',
    'telegramProfile' => ['displayName' => 'Legacy Approved Student'],
]);
$legacyClaims = dent_bot_identity_claims($user);
$legacyApprovedRef = '';
foreach (($legacyClaims['claims'] ?? []) as $legacyClaim) {
    if (is_array($legacyClaim) && (string) ($legacyClaim['platformUserId'] ?? '') === '888002') {
        $legacyApprovedRef = (string) ($legacyClaim['ref'] ?? '');
        break;
    }
}
if ($legacyApprovedRef === '') {
    throw new RuntimeException('Synthetic approved legacy claim was not created.');
}
dent_bot_resolve_identity_claim($user, ['claimRef' => $legacyApprovedRef, 'decision' => 'approve']);
dent_bot_submit_identity_claim('telegram', '888003', [
    'name' => 'Imported Student',
    'telegramProfile' => ['displayName' => 'Legacy Pending Student'],
]);

notifications_create_broadcast($user, [
    'title' => 'Synthetic notification',
    'body' => 'Synthetic notification body',
    'targetKey' => dent_primary_cohort_key(),
    'ctaHref' => '/exams/',
    'ctaLabel' => 'Open exam',
]);

payments_with_store_lock(static function (array &$store): void {
    $now = dent_iso_now();
    $store['gatewaySettings'] = ['managed' => true, 'default' => 'mock-test'];
    $store['gateways'] = [[
        'id' => 1,
        'key' => 'mock-test',
        'provider' => PAYMENTS_GATEWAY_MOCK,
        'label' => 'Test Gateway',
        'is_enabled' => true,
        'is_default' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $store['items'] = [];
    $store['collections'] = [];
    $store['orders'] = [];
});

echo "FIXTURE_READY=true\n";
