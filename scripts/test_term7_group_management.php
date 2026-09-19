<?php
declare(strict_types=1);

$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dent-term7-groups-' . bin2hex(random_bytes(5));
putenv('DENT_STORAGE_ROOT=' . $testRoot);
putenv('DENT_SERVER_ONLY_ROOT=' . $testRoot . DIRECTORY_SEPARATOR . 'server-only');
putenv('DENT_AUTH_SECRET_KEY=' . base64_encode(str_repeat('t', 32)));

require_once __DIR__ . '/../public_html/api/bot_store.php';
require_once __DIR__ . '/../public_html/api/academic_term7_bot_service.php';
restore_error_handler();
restore_exception_handler();

$failures = 0;
function term7_group_assert(bool $condition, string $label): void
{
    global $failures;
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$condition) $failures++;
}
function term7_group_cleanup(string $path): void
{
    if (!is_dir($path)) return;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
}

try {
    $ownerSn = '40211272003';
    $studentA = '40211272991';
    $studentB = '40211272992';
    $studentWithoutTerm7Assignment = '40211272993';
    $fixturePassword = dent_hash_password('fixture-password-only');
    dent_save_user_store([
        'cohorts' => dent_default_cohort_catalog(),
        'users' => [
            $ownerSn => ['studentNumber'=>$ownerSn,'name'=>'مالک تست','passwordHash'=>$fixturePassword,'role'=>'owner','cohortKey'=>DENT_TERM7_COHORT],
            $studentA => ['studentNumber'=>$studentA,'name'=>'دانشجوی الف','passwordHash'=>$fixturePassword,'role'=>'student','cohortKey'=>DENT_TERM7_COHORT],
            $studentB => ['studentNumber'=>$studentB,'name'=>'دانشجوی ب','passwordHash'=>$fixturePassword,'role'=>'student','cohortKey'=>DENT_TERM7_COHORT],
            $studentWithoutTerm7Assignment => ['studentNumber'=>$studentWithoutTerm7Assignment,'name'=>'خارج از فهرست ترم ۷','passwordHash'=>$fixturePassword,'role'=>'student','cohortKey'=>DENT_TERM7_COHORT],
        ],
    ]);
    $owner = dent_get_user_record($ownerSn);
    dent_term7_owner_update_assignment($owner, $ownerSn, 'group10', 5);
    dent_term7_owner_update_assignment($owner, $ownerSn, 'group8', 14);
    $first = dent_term7_owner_update_assignment($owner, $studentA, 'group10', 1);
    term7_group_assert(($first['group10Status'] ?? '') === 'member', 'Owner can assign morning group with member status');
    $leader = dent_term7_owner_set_leader($owner, $studentA, 'group10', true);
    term7_group_assert(($leader['group10Status'] ?? '') === 'leader', 'Owner can mark assigned student as leader');
    dent_term7_owner_update_assignment($owner, $studentB, 'group10', 1);
    dent_term7_owner_set_leader($owner, $studentB, 'group10', true);
    term7_group_assert((dent_term7_public_assignment_for_student($studentA)['group10Status'] ?? '') === 'member', 'One leader per group is enforced');
    dent_term7_owner_update_assignment($owner, $studentB, 'group10', 2);
    term7_group_assert((dent_term7_public_assignment_for_student($studentB)['group10Status'] ?? '') === 'member', 'Moving a leader safely invalidates stale leader state');
    term7_group_assert((dent_term7_group_leader_state_read()['group10']['1'] ?? '') === '', 'Stale old-group leader pointer is hidden on read');
    $cleared = dent_term7_owner_update_assignment($owner, $studentA, 'group10', null);
    term7_group_assert(array_key_exists('group10', $cleared) && $cleared['group10'] === null && ($cleared['group10Status'] ?? '') === 'unassigned', 'Owner can explicitly clear a group');
    dent_term7_owner_update_assignment($owner, $studentA, 'group8', 12);
    dent_term7_owner_set_leader($owner, $studentA, 'group8', true);
    $roster = dent_term7_owner_roster($owner);
    $rows = array_values(array_filter($roster, static fn(array $row): bool => ($row['studentNumber'] ?? '') === $studentA));
    term7_group_assert(count($rows) === 1 && ($rows[0]['assignment']['group8Status'] ?? '') === 'leader', 'Owner roster exposes afternoon leader status');

    dent_bot_persistence_initialize(dent_bot_store_path(), dent_bot_store_default(), 'dent_bot_store_normalize', 'term7-groups-test-init');
    dent_bot_store_with_lock(static function (array &$store) use ($ownerSn): array {
        $platform = 'telegram';
        $platformUserId = '900001';
        $identityHash = dent_bot_identity_hash($platform, $platformUserId);
        $store['links'][$identityHash] = [
            'platform'=>$platform,
            'platformUserIdEncrypted'=>dent_encrypt_secret_text($platformUserId),
            'studentNumber'=>$ownerSn,
            'authVersion'=>dent_bot_canonical_auth_version(),
            'authMethod'=>'secure-site-login',
            'authCompletedAt'=>dent_iso_now(),
        ];
        return [];
    });
    $saturdayContext = dent_term7_bot_service_schedule_context(
        ['group10' => 5, 'group8' => 14, 'oralHealthRotationAWeekday' => 6],
        new DateTimeImmutable('2026-09-19 08:00:00', new DateTimeZone(DENT_TERM7_TIMEZONE))
    );
    $perioRows = array_values(array_filter(
        $saturdayContext['currentTheory'] ?? [],
        static fn(array $row): bool => ($row['slug'] ?? '') === 'periodontology-theory-1'
    ));
    term7_group_assert(
        count($perioRows) === 1
            && str_contains((string) ($perioRows[0]['title'] ?? ''), 'جلسه 1: آناتومی انساج پریودنتال ۱ · مجازی')
            && ($perioRows[0]['sessionMode'] ?? '') === 'virtual'
            && ($perioRows[0]['location'] ?? '') === 'مجازی',
        'Term 7 bot schedule context uses syllabus-enriched Perio title and explicit virtual location'
    );
    $researchRows = array_values(array_filter(
        $saturdayContext['currentPractical'] ?? [],
        static fn(array $row): bool => ($row['slug'] ?? '') === 'research-methods-2-practical'
    ));
    term7_group_assert(
        count($researchRows) === 1
            && ($researchRows[0]['sessionMode'] ?? '') === 'in_person'
            && ($researchRows[0]['location'] ?? '') === 'آمفی‌تئاتر ۹۰',
        'In-person Research Methods keeps its own amphitheater location on the same day'
    );

    $self = dent_term7_bot_service_dispatch(['action'=>'academicTerm7Self','platform'=>'telegram','platformUserId'=>'900001']);
    term7_group_assert(!empty($self['eligible']) && isset($self['assignment']['group10Status']), 'Signed Term 7 self response carries status-aware assignment');
    $serviceRoster = dent_term7_bot_service_dispatch(['action'=>'academicTerm7Roster','platform'=>'telegram','platformUserId'=>'900001']);
    term7_group_assert(count($serviceRoster['roster'] ?? []) === 3, 'Signed owner service exposes only assignment-backed Term 7 roster');
    $serviceNumbers = array_map(static fn(array $row): string => (string) ($row['studentNumber'] ?? ''), $serviceRoster['roster'] ?? []);
    term7_group_assert(!in_array($studentWithoutTerm7Assignment, $serviceNumbers, true), 'Cohort account without Term 7 assignment is excluded from bot roster');
} finally {
    term7_group_cleanup($testRoot);
}

exit($failures === 0 ? 0 : 1);
