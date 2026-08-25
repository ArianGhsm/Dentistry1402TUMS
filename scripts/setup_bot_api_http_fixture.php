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
];
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
dent_write_auth_store_payload($authStore);
$user = dent_get_user_record($studentNumber);
if (!is_array($user)) {
    throw new RuntimeException('Synthetic owner could not be created.');
}

$platformUserId = '654321';
$challenge = dent_bot_start_link('telegram', $platformUserId);
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
$regularChallenge = dent_bot_start_link('telegram', '777001');
$regularQuery = (string) parse_url((string) ($regularChallenge['linkUrl'] ?? ''), PHP_URL_QUERY);
parse_str($regularQuery, $regularParameters);
$regularToken = (string) ($regularParameters['token'] ?? '');
if ($regularToken === '') {
    throw new RuntimeException('Synthetic regular link challenge was not created.');
}
dent_bot_confirm_link($regularToken, $regularUser);
$regularBaleChallenge = dent_bot_start_link('bale', '777002');
$regularBaleQuery = (string) parse_url((string) ($regularBaleChallenge['linkUrl'] ?? ''), PHP_URL_QUERY);
parse_str($regularBaleQuery, $regularBaleParameters);
dent_bot_confirm_link((string) ($regularBaleParameters['token'] ?? ''), $regularUser);
dent_student_assistant_test_seed_connector($regularStudentNumber, 'food');

$secureUser = dent_get_user_record($secureStudentNumber);
if (!is_array($secureUser)) {
    throw new RuntimeException('Synthetic secure-link user could not be created.');
}
dent_bot_submit_identity_claim('telegram', '777003', [
    'name' => 'Secure Link Student',
    'telegramProfile' => ['displayName' => 'Secure Student', 'username' => 'secure_student', 'languageCode' => 'fa'],
]);
$secureChallenge = dent_bot_start_link('telegram', '777003');
$secureQuery = (string) parse_url((string) ($secureChallenge['linkUrl'] ?? ''), PHP_URL_QUERY);
parse_str($secureQuery, $secureParameters);
dent_bot_confirm_link((string) ($secureParameters['token'] ?? ''), $secureUser);
$ownerClaims = dent_bot_identity_claims($user);
foreach (($ownerClaims['claims'] ?? []) as $pendingClaim) {
    if (is_array($pendingClaim) && (string) ($pendingClaim['platformUserId'] ?? '') === '777003') {
        throw new RuntimeException('Secure site confirmation did not clear the pending identity claim.');
    }
}

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
