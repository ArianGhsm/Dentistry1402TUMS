<?php
declare(strict_types=1);

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

$fixtureRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR . 'dent-auth-resilience-' . bin2hex(random_bytes(8));
$fixtureStorage = $fixtureRoot . DIRECTORY_SEPARATOR . 'storage';
$fixtureServerOnly = $fixtureRoot . DIRECTORY_SEPARATOR . 'server-only';
if (!mkdir($fixtureStorage, 0700, true) && !is_dir($fixtureStorage)) {
    fwrite(STDERR, "Unable to create isolated auth fixture root.\n");
    exit(1);
}
putenv('DENT_STORAGE_ROOT=' . $fixtureStorage);
putenv('DENT_SERVER_ONLY_ROOT=' . $fixtureServerOnly);
putenv('DENT_SESSION_SAVE_PATH=' . $fixtureRoot . DIRECTORY_SEPARATOR . 'sessions');

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
            'passwordHash' => dent_hash_password('fixture-owner-password'),
            'role' => 'owner',
            'cohortKey' => dent_primary_cohort_key(),
            'phoneNumber' => '+989999999999',
            'directoryPhoneNumber' => '+989999999999',
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

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!file_exists($path)) {
        return;
    }
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $removeTree($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
};

$exitCode = 1;
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
        ($owner['phoneNumber'] ?? '') === '+989999999999',
        ($owner['directoryPhoneNumber'] ?? '') === '+989999999999',
        (($owner['profile'] ?? [])['avatarUrl'] ?? '') === $fixtureAvatar,
        ($cohorts['dentistry-1403'] ?? 0) >= 1,
        dent_decode_auth_store_snapshot($primaryPath) !== null,
        dent_decode_auth_store_snapshot($backupPath) !== null,
    ];

    foreach ($assertions as $index => $passed) {
        if (!$passed) {
            fwrite(STDERR, "Auth resilience assertion failed at check #" . ($index + 1) . ".\n");
            throw new RuntimeException('Auth resilience assertion failed.');
        }
    }

    fwrite(STDOUT, "Auth store resilience check passed.\n");
    $exitCode = 0;
} finally {
    dent_release_session_lock();
    $expectedPrefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dent-auth-resilience-';
    if (str_starts_with($fixtureRoot, $expectedPrefix)) {
        $removeTree($fixtureRoot);
    }
}
exit($exitCode);
