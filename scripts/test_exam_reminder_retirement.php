<?php
declare(strict_types=1);

$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dent-exam-reminder-retirement-' . bin2hex(random_bytes(6));
$storageRoot = $testRoot . DIRECTORY_SEPARATOR . 'storage';
$serverRoot = $testRoot . DIRECTORY_SEPARATOR . 'server-only';
$tmpRoot = $testRoot . DIRECTORY_SEPARATOR . 'tmp';
foreach ([$storageRoot, $serverRoot, $tmpRoot] as $path) {
    if (!mkdir($path, 0700, true) && !is_dir($path)) {
        throw new RuntimeException('Could not create isolated test directory.');
    }
}

putenv('DENT_STORAGE_ROOT=' . $storageRoot);
putenv('DENT_SERVER_ONLY_ROOT=' . $serverRoot);
putenv('DENT_TMP_ROOT=' . $tmpRoot);
putenv('DENT_APP_ENV=test');
putenv('DENT_AUTH_SECRET_KEY=' . base64_encode(str_repeat("\x01", 32)));

$removeTree = static function (string $root) use (&$removeTree): void {
    if (!is_dir($root)) {
        return;
    }
    foreach (scandir($root) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = $root . DIRECTORY_SEPARATOR . $name;
        if (is_dir($path) && !is_link($path)) {
            $removeTree($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($root);
};
register_shutdown_function(static function () use ($removeTree, $testRoot): void {
    $removeTree($testRoot);
});

require_once __DIR__ . '/../public_html/api/notifications_store.php';
require_once __DIR__ . '/../public_html/api/exams_store.php';

$failures = 0;
$total = 0;
$assert = static function (bool $condition, string $label) use (&$failures, &$total): void {
    $total++;
    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }
    $failures++;
    echo "FAIL: {$label}\n";
};

$ownerNumber = dent_owner_student_number();
$studentNumber = '402000099';
$auth = dent_auth_store_seed_payload();
$primaryCohort = dent_primary_cohort_key();
$auth['users'][$ownerNumber] = [
    'studentNumber' => $ownerNumber,
    'name' => 'Test Owner',
    'passwordHash' => password_hash('not-used', PASSWORD_DEFAULT),
    'role' => 'owner',
    'cohortKey' => $primaryCohort,
];
$auth['users'][$studentNumber] = [
    'studentNumber' => $studentNumber,
    'name' => 'Test Student',
    'passwordHash' => password_hash('not-used', PASSWORD_DEFAULT),
    'role' => 'student',
    'cohortKey' => $primaryCohort,
];
dent_write_auth_store_payload($auth);
$owner = dent_get_user_record($ownerNumber);
$student = dent_get_user_record($studentNumber);
if (!is_array($owner) || !is_array($student)) {
    throw new RuntimeException('Isolated users were not created.');
}

$now = dent_iso_now();
$legacyStore = [
    'schemaVersion' => 5,
    'notifications' => [
        'nt-exam-active' => [
            'id' => 'nt-exam-active',
            'title' => 'ادامه آزمون نیمه‌کاره',
            'body' => 'آخرین فعالیت قدیمی است.',
            'target' => 'user',
            'targetStudentNumber' => $studentNumber,
            'source' => 'exams',
            'sourceKey' => 'exam-resume-1d:' . $studentNumber . ':shared:course:exam',
            'createdAt' => $now,
            'publishAt' => $now,
            'status' => 'active',
        ],
        'nt-exam-scheduled' => [
            'id' => 'nt-exam-scheduled',
            'title' => 'مرور سوال‌های نشان‌دار',
            'body' => 'یادآور زمان‌بندی‌شده',
            'target' => 'user',
            'targetStudentNumber' => $studentNumber,
            'source' => 'exams',
            'sourceKey' => 'exam-resume-1d:' . $studentNumber . ':shared:course:scheduled',
            'createdAt' => $now,
            'publishAt' => $now,
            'status' => 'scheduled',
        ],
        'nt-digest-mixed' => [
            'id' => 'nt-digest-mixed',
            'title' => 'خلاصه روزانه اعلان‌ها',
            'body' => "۱ فرم به مهلت پاسخ نزدیک شده است.\n۱ آزمون نیمه‌کاره برای ادامه داری.",
            'target' => 'user',
            'targetStudentNumber' => $studentNumber,
            'source' => 'digest',
            'sourceKey' => 'digest:mixed:' . $studentNumber,
            'createdAt' => $now,
            'publishAt' => $now,
            'status' => 'active',
        ],
        'nt-digest-exam-only' => [
            'id' => 'nt-digest-exam-only',
            'title' => 'خلاصه روزانه اعلان‌ها',
            'body' => '۱ آزمون نیمه‌کاره برای ادامه داری.',
            'target' => 'user',
            'targetStudentNumber' => $studentNumber,
            'source' => 'digest',
            'sourceKey' => 'digest:exam-only:' . $studentNumber,
            'createdAt' => $now,
            'publishAt' => $now,
            'status' => 'active',
        ],
    ],
    'userStates' => [
        $studentNumber => [
            'studentNumber' => $studentNumber,
            'preferences' => [
                'formReminders' => true,
                'paymentReminders' => true,
                'examReminders' => true,
                'dailyDigestEnabled' => true,
            ],
        ],
    ],
    'suppressedSources' => [],
];
dent_write_json_file(notifications_store_path(), $legacyStore);

$normalized = notifications_read_store();
$assert(!isset($normalized['notifications']['nt-exam-active']), 'active legacy exam reminder is removed from the website feed');
$assert(!isset($normalized['notifications']['nt-exam-scheduled']), 'scheduled legacy exam reminder is removed before activation');
$assert(!isset($normalized['notifications']['nt-digest-exam-only']), 'exam-only legacy digest is removed');
$assert(
    (string) ($normalized['notifications']['nt-digest-mixed']['body'] ?? '') === '۱ فرم به مهلت پاسخ نزدیک شده است.',
    'mixed digest keeps non-exam reminder content'
);
$assert(
    !array_key_exists('examReminders', $normalized['userStates'][$studentNumber]['preferences'] ?? []),
    'legacy examReminders preference is ignored and retired'
);
$retiredIds = array_fill_keys($normalized['retiredNotificationIds'] ?? [], true);
$assert(isset($retiredIds['nt-exam-active']) && isset($retiredIds['nt-exam-scheduled']), 'retired IDs are retained for delivery cancellation');

require_once __DIR__ . '/../public_html/api/bot_store.php';
$botStore = dent_bot_store_default();
$botStore['notificationDeliveries']['legacy-exam-pending'] = [
    'deliveryId' => 'nd-11111111111111111111111111111111',
    'notificationId' => 'nt-exam-active',
    'identityHash' => 'test-identity',
    'platform' => 'telegram',
    'status' => 'pending',
    'attempts' => 1,
    'leaseUntil' => 0,
    'lastAttemptAt' => $now,
    'deliveredAt' => '',
    'reasonCode' => '',
];
$botStore['notificationDeliveries']['adapter-defense'] = [
    'deliveryId' => 'nd-22222222222222222222222222222222',
    'notificationId' => 'nt-non-exam-fixture',
    'identityHash' => 'test-identity',
    'platform' => 'telegram',
    'status' => 'leased',
    'attempts' => 1,
    'leaseUntil' => time() + 120,
    'lastAttemptAt' => $now,
    'deliveredAt' => '',
    'reasonCode' => '',
];
dent_write_json_file(dent_bot_store_path(), $botStore);
dent_bot_claim_notification_deliveries($owner, 'telegram', ['limit' => 10]);
dent_bot_ack_notification_delivery($owner, 'telegram', [
    'deliveryId' => 'nd-22222222222222222222222222222222',
    'delivered' => false,
    'reasonCode' => 'EXAM_REMINDER_RETIRED',
]);
$deliveryStates = dent_bot_store_read(static function (array $store): array {
    return $store['notificationDeliveries'] ?? [];
});
$assert(
    (string) ($deliveryStates['legacy-exam-pending']['status'] ?? '') === 'failed'
        && (string) ($deliveryStates['legacy-exam-pending']['reasonCode'] ?? '') === 'EXAM_REMINDER_RETIRED',
    'pending Telegram/Bale delivery for a retired reminder is terminally canceled'
);
$assert(
    (string) ($deliveryStates['adapter-defense']['status'] ?? '') === 'failed',
    'transport defense can terminally reject an unexpected legacy exam reminder'
);

$examKey = dent_exams_exam_key('shared', 'retirement-test-course', 'retirement-test-exam');
dent_exams_with_store_lock(static function (array &$store) use ($examKey, $studentNumber): void {
    $store['examRecords'][$examKey] = [
        'flagsByUser' => [$studentNumber => [1, 3]],
        'reportsByUser' => [],
        'attemptsByUser' => [],
        'studyStateByUser' => [],
        'activityByUser' => [
            $studentNumber => [
                'lastMode' => 'assessment',
                'updatedAt' => gmdate('c', time() - (48 * 3600)),
            ],
        ],
    ];
});

notifications_process_due_queue($student);
notifications_process_due_queue($student);
$afterSummaryAndList = notifications_read_store();
$examRecords = array_filter(
    $afterSummaryAndList['notifications'] ?? [],
    static fn($record): bool => is_array($record) && notifications_record_is_retired_exam_reminder($record)
);
$assert($examRecords === [], 'summary/list due processing creates no exam reminder after more than 24 hours');
$assert(notifications_summary_for_user($afterSummaryAndList, $student)['visibleCount'] === 1, 'summary exposes only the remaining non-exam digest');
$assert(count(notifications_list_payload_for_user($afterSummaryAndList, $student)['items'] ?? []) === 1, 'list exposes no retired exam reminder');

$examStore = dent_exams_read_store();
$savedRecord = dent_exams_record($examStore, 'shared', 'retirement-test-course', 'retirement-test-exam');
$assert(($savedRecord['flagsByUser'][$studentNumber] ?? []) === [1, 3], 'flagged-question state remains intact');
$assert(isset($savedRecord['activityByUser'][$studentNumber]), 'exam progress/activity state remains intact');

$blocked = notifications_ensure_user_candidate($student, [
    'source' => 'exams',
    'sourceKey' => 'exam-resume-1d:' . $studentNumber . ':shared:course:new',
    'title' => 'ادامه آزمون نیمه‌کاره',
    'body' => 'نباید ساخته شود.',
]);
$assert(empty($blocked['created']) && ($blocked['record'] ?? null) === null, 'generic enqueue rejects retired exam reminder candidates');

$formCandidate = notifications_ensure_user_candidate($student, [
    'source' => 'forms',
    'sourceKey' => 'form-remind:test:' . $studentNumber,
    'title' => 'یادآور فرم تست',
    'body' => 'مسیر اعلان‌های دیگر باید سالم بماند.',
    'dispatchPush' => false,
]);
$assert(!empty($formCandidate['created']), 'non-exam automatic notification enqueue remains available');

$broadcast = notifications_create_broadcast($owner, [
    'targetKey' => 'all',
    'title' => 'اعلان مدیریتی تست',
    'body' => 'اعلان عمومی مشترک سالم است.',
]);
$assert((string) ($broadcast['source'] ?? '') === 'manager', 'admin/general notification creation remains available');

$authApiSource = (string) file_get_contents(__DIR__ . '/../public_html/api/auth_api.php');
$notificationsSource = (string) file_get_contents(__DIR__ . '/../public_html/api/notifications_store.php');
$notificationsApiSource = (string) file_get_contents(__DIR__ . '/../public_html/api/notifications_api.php');
$assert(!str_contains($authApiSource, 'notifications_process_due_queue'), 'login path does not create notification candidates');
$assert(!str_contains($notificationsSource, 'notifications_exam_candidate_from_record'), 'exam candidate generator is removed');
$assert(!str_contains($notificationsApiSource, "'examReminders'"), 'notification API no longer accepts examReminders');

echo "Exam reminder retirement tests: {$total} checks, {$failures} failures.\n";
exit($failures === 0 ? 0 : 1);
