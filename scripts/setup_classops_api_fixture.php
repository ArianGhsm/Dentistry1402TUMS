<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/public_html/api/auth_store.php';

$ownerNumber = dent_owner_student_number();
$studentNumber = '402999999';
$password = 'classops-test-password';
// This fixture tests HTTP authorization/CSRF contracts, not password-cost performance.
// Use one deliberately cheap test-only hash and pre-seed the mandatory prosthesis roster
// so auth-store normalization does not perform 18 production-cost PBKDF2 migrations on read.
$fixturePasswordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
$rosterFixtureHash = password_hash('classops-fixture-disabled', PASSWORD_BCRYPT, ['cost' => 4]);
$store = dent_auth_store_seed_payload();
$store['users'] = [
    $ownerNumber => [
        'studentNumber' => $ownerNumber,
        'name' => 'ClassOps Test Owner',
        'passwordHash' => $fixturePasswordHash,
        'role' => 'owner',
        'cohortKey' => dent_primary_cohort_key(),
        'phoneNumber' => '',
        'phoneVerifiedAt' => '',
        'phoneLoginEnabled' => false,
    ],
    $studentNumber => [
        'studentNumber' => $studentNumber,
        'name' => 'ClassOps Test Student',
        'passwordHash' => $fixturePasswordHash,
        'role' => 'student',
        'cohortKey' => dent_primary_cohort_key(),
        'phoneNumber' => '',
        'phoneVerifiedAt' => '',
        'phoneLoginEnabled' => false,
    ],
];

foreach (dent_prosthesis_1402_roster() as $rosterStudentNumber => $entry) {
    $rosterStudentNumber = dent_normalize_student_number((string) $rosterStudentNumber);
    if ($rosterStudentNumber === '' || isset($store['users'][$rosterStudentNumber])) continue;
    $name = trim((string) ($entry['firstName'] ?? '') . ' ' . (string) ($entry['lastName'] ?? ''));
    $store['users'][$rosterStudentNumber] = [
        'studentNumber' => $rosterStudentNumber,
        'name' => $name !== '' ? $name : $rosterStudentNumber,
        'passwordHash' => $rosterFixtureHash,
        'role' => !empty($entry['representative']) ? 'prosthesis_representative' : 'prosthesis_student',
        'cohortKey' => dent_prosthesis_legacy_cohort_key(),
        'phoneNumber' => '',
        'phoneVerifiedAt' => '',
        'phoneLoginEnabled' => false,
    ];
}

dent_write_auth_store_payload($store);
echo json_encode(['owner' => $ownerNumber, 'student' => $studentNumber], JSON_UNESCAPED_SLASHES), PHP_EOL;
