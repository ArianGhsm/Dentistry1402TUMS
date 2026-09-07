<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/public_html/api/auth_store.php';

$ownerNumber = dent_owner_student_number();
$studentNumber = '402999999';
$password = 'classops-test-password';
$store = dent_auth_store_seed_payload();
$store['users'] = [
    $ownerNumber => [
        'studentNumber' => $ownerNumber,
        'name' => 'ClassOps Test Owner',
        'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'owner',
        'cohortKey' => dent_primary_cohort_key(),
        'phoneNumber' => '',
        'phoneVerifiedAt' => '',
        'phoneLoginEnabled' => false,
    ],
    $studentNumber => [
        'studentNumber' => $studentNumber,
        'name' => 'ClassOps Test Student',
        'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'student',
        'cohortKey' => dent_primary_cohort_key(),
        'phoneNumber' => '',
        'phoneVerifiedAt' => '',
        'phoneLoginEnabled' => false,
    ],
];
dent_write_auth_store_payload($store);
echo json_encode(['owner' => $ownerNumber, 'student' => $studentNumber], JSON_UNESCAPED_SLASHES), PHP_EOL;
