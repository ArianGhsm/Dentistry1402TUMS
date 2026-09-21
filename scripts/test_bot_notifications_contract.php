<?php
declare(strict_types=1);

$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dent-bot-notification-contract-' . bin2hex(random_bytes(5));
putenv('DENT_STORAGE_ROOT=' . $testRoot);
putenv('DENT_SERVER_ONLY_ROOT=' . $testRoot . DIRECTORY_SEPARATOR . 'server-only');
putenv('DENT_AUTH_SECRET_KEY=' . base64_encode(str_repeat('n', 32)));

require_once __DIR__ . '/../public_html/api/bot_store.php';
restore_error_handler();
restore_exception_handler();

$failures = 0;
function notification_contract_assert(bool $condition, string $label): void
{
    global $failures;
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$condition) $failures++;
}
function notification_contract_cleanup(string $path): void
{
    if (!is_dir($path)) return;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
}

try {
    $password = dent_hash_password('fixture-password-only');
    $ownerSn = '40211272003';
    $studentSn = '40211272991';
    $owner = ['studentNumber'=>$ownerSn,'name'=>'مالک تست','passwordHash'=>$password,'role'=>'owner','cohortKey'=>'dentistry-1402'];
    $student = ['studentNumber'=>$studentSn,'name'=>'دانشجوی تست','passwordHash'=>$password,'role'=>'student','cohortKey'=>'dentistry-1402'];
    dent_save_user_store([
        'cohorts' => dent_default_cohort_catalog(),
        'users' => [$ownerSn => $owner, $studentSn => $student],
    ]);

    $studentCreated = notifications_ensure_user_candidate($student, [
        'source' => 'academic-term7',
        'sourceKey' => 'academic:test:student',
        'title' => 'برنامه فردا',
        'body' => 'برنامه شخصی دانشجو',
        'tone' => 'accent',
        'meta' => ['important' => false, 'disablePush' => true],
    ]);
    $ownerCreated = notifications_ensure_user_candidate($owner, [
        'source' => 'academic-term7',
        'sourceKey' => 'academic:test:owner',
        'title' => 'برنامه فردا',
        'body' => 'برنامه شخصی مالک',
        'tone' => 'accent',
        'meta' => ['important' => false, 'disablePush' => true],
    ]);

    $studentId = (string) (($studentCreated['record']['id'] ?? ''));
    $ownerId = (string) (($ownerCreated['record']['id'] ?? ''));
    notification_contract_assert($studentId !== '' && $ownerId !== '', 'Academic fixture notifications are created');

    $store = notifications_read_store();
    $studentFeedIds = array_column(notifications_list_payload_for_user($store, $student, 20)['items'] ?? [], 'id');
    $ownerFeedIds = array_column(notifications_list_payload_for_user($store, $owner, 20)['items'] ?? [], 'id');
    notification_contract_assert(in_array($studentId, $studentFeedIds, true), 'Student sees own personal academic notification');
    notification_contract_assert(!in_array($studentId, $ownerFeedIds, true), 'Owner feed excludes another student personal academic notification');
    notification_contract_assert(in_array($ownerId, $ownerFeedIds, true), 'Owner still sees own personal academic notification');

    $detail = dent_bot_notification_detail($student, ['notificationId' => $studentId]);
    notification_contract_assert(
        ($detail['success'] ?? false) === true
            && ($detail['notification']['id'] ?? '') === $studentId
            && ($detail['notification']['unread'] ?? false) === true,
        'Notification detail returns the visible canonical record'
    );

    $marked = dent_bot_mark_notification_read($student, ['notificationId' => $studentId]);
    notification_contract_assert(
        ($marked['success'] ?? false) === true
            && ($marked['notification']['id'] ?? '') === $studentId
            && ($marked['notification']['unread'] ?? true) === false,
        'Mark-read returns the same notification as read'
    );

    $cleanMeta = notifications_clean_meta([
        'academicScheduleRows' => [
            [
                'kind' => 'practical',
                'period' => 'morning',
                'title' => 'سلامت دهان عملی ۲',
                'start' => '09:00',
                'end' => '12:00',
                'location' => '',
                'instructor' => 'دکتر سرگران / دکتر پاکدامن',
            ],
        ],
    ]);
    notification_contract_assert(
        ($cleanMeta['academicScheduleRows'][0]['start'] ?? '') === '09:00'
            && ($cleanMeta['academicScheduleRows'][0]['instructor'] ?? '') === 'دکتر سرگران / دکتر پاکدامن',
        'Notification metadata preserves sanitized academic schedule instructor rows'
    );
    $presentationMeta = notifications_clean_meta([
        'oralDiseasePresentationRows' => [[
            'group' => 8,
            'jalaliDate' => '1405/07/13',
            'weekdayLabel' => 'دوشنبه',
            'topic' => 'زخم‌های متعدد و مزمن',
            'partners' => ['رضوانه کاظمی مقدم'],
            'partnerLabel' => 'رضوانه کاظمی مقدم',
        ]],
    ]);
    notification_contract_assert(
        ($presentationMeta['oralDiseasePresentationRows'][0]['jalaliDate'] ?? '') === '1405/07/13'
            && ($presentationMeta['oralDiseasePresentationRows'][0]['partners'] ?? []) === ['رضوانه کاظمی مقدم'],
        'Notification metadata preserves personalized Oral Disease presentation table rows'
    );
    $deliverySource = file_get_contents(__DIR__ . '/../public_html/api/bot_notifications.php') ?: '';
    notification_contract_assert(
        str_contains($deliverySource, "'meta' => \$recordMeta"),
        'Push-delivery payload carries sanitized notification metadata'
    );

    $dispatchSource = file_get_contents(__DIR__ . '/../public_html/api/bot_store.php') ?: '';
    notification_contract_assert(
        str_contains($dispatchSource, "if (\$action === 'notificationDetail')")
            && str_contains($dispatchSource, 'return dent_bot_notification_detail($user, $payload);'),
        'Signed bot dispatcher exposes notificationDetail'
    );
} finally {
    notification_contract_cleanup($testRoot);
}

exit($failures === 0 ? 0 : 1);
