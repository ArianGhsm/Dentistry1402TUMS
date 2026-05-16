<?php
declare(strict_types=1);

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

require_once $projectRoot . '/public_html/api/auth_store.php';

$primaryPath = dent_auth_store_path();
$backupPath = dent_auth_store_backup_path();
$fixtureAvatar = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wn0K1sAAAAASUVORK5CYII=';

$fixtureStore = [
    'schemaVersion' => 2,
    'ownerStudentNumber' => dent_owner_student_number(),
    'cohorts' => dent_default_cohort_catalog(),
    'users' => [
        '40211272003' => [
            'studentNumber' => '40211272003',
            'name' => 'مالک سایت',
            'passwordHash' => dent_hash_password('AAbb11__'),
            'role' => 'owner',
            'cohortKey' => dent_primary_cohort_key(),
            'phoneNumber' => '+989009840305',
            'directoryPhoneNumber' => '+989009840305',
            'profile' => [
                'about' => '',
                'bio' => '',
                'contactHandle' => '',
                'focusArea' => '',
                'avatarUrl' => $fixtureAvatar,
            ],
            'createdAt' => dent_iso_now(),
            'updatedAt' => dent_iso_now(),
        ],
        '40311272041' => [
            'studentNumber' => '40311272041',
            'name' => 'محمدمهدی قمصری',
            'passwordHash' => dent_hash_password('12345678'),
            'role' => 'representative',
            'cohortKey' => 'dentistry-1403',
            'profile' => dent_default_profile(),
            'createdAt' => dent_iso_now(),
            'updatedAt' => dent_iso_now(),
        ],
        '40211272004' => [
            'studentNumber' => '40211272004',
            'name' => 'دانشجوی ۱۴۰۲',
            'passwordHash' => dent_hash_password('12345678'),
            'role' => 'student',
            'cohortKey' => dent_primary_cohort_key(),
            'profile' => dent_default_profile(),
            'createdAt' => dent_iso_now(),
            'updatedAt' => dent_iso_now(),
        ],
    ],
];

$originalPrimary = is_file($primaryPath) ? file_get_contents($primaryPath) : null;
$originalBackup = is_file($backupPath) ? file_get_contents($backupPath) : null;

$restoreFile = static function (string $path, $contents): void {
    if ($contents === null) {
        if (is_file($path)) {
            unlink($path);
        }
        return;
    }

    dent_ensure_directory(dirname($path));
    file_put_contents($path, $contents);
};

try {
    dent_ensure_directory(dirname($primaryPath));
    if (is_file($primaryPath)) {
        unlink($primaryPath);
    }
    if (is_file($backupPath)) {
        unlink($backupPath);
    }
    dent_save_user_store($fixtureStore);
    dent_save_user_store($fixtureStore);

    file_put_contents($primaryPath, "{\"broken\": ");
    $recovered = dent_load_user_store();

    $users = is_array($recovered['users'] ?? null) ? $recovered['users'] : [];
    $owner = is_array($users[dent_owner_student_number()] ?? null) ? $users[dent_owner_student_number()] : [];
    $cohorts = [];
    foreach ($users as $user) {
        if (!is_array($user)) {
            continue;
        }
        $cohortKey = (string) ($user['cohortKey'] ?? '');
        $cohorts[$cohortKey] = ($cohorts[$cohortKey] ?? 0) + 1;
    }

    $assertions = [
        !empty($users['40311272041']),
        ($owner['phoneNumber'] ?? '') === '+989009840305',
        ($owner['directoryPhoneNumber'] ?? '') === '+989009840305',
        (($owner['profile'] ?? [])['avatarUrl'] ?? '') === $fixtureAvatar,
        ($cohorts['dentistry-1403'] ?? 0) >= 1,
        dent_decode_auth_store_snapshot($primaryPath) !== null,
        dent_decode_auth_store_snapshot($backupPath) !== null,
    ];

    foreach ($assertions as $index => $passed) {
        if (!$passed) {
            fwrite(STDERR, "Auth resilience assertion failed at check #" . ($index + 1) . ".\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "Auth store resilience check passed.\n");
    exit(0);
} finally {
    $restoreFile($primaryPath, $originalPrimary);
    $restoreFile($backupPath, $originalBackup);
}
