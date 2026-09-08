<?php
declare(strict_types=1);

$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dent-term7-groups-' . bin2hex(random_bytes(5));
putenv('DENT_STORAGE_ROOT=' . $testRoot);
putenv('DENT_SERVER_ONLY_ROOT=' . $testRoot . DIRECTORY_SEPARATOR . 'server-only');
putenv('DENT_AUTH_SECRET_KEY=' . base64_encode(str_repeat('t', 32)));

require_once __DIR__ . '/../public_html/api/bot_store.php';
require_once __DIR__ . '/../public_html/api/classops_bot_service.php';
restore_error_handler();
restore_exception_handler();

$failures = 0;
function term7_group_assert(bool $condition, string $label): void
{
    global $failures;
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
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
    $fixturePassword = dent_hash_password('fixture-password-only');
    dent_save_user_store([
        'cohorts' => dent_default_cohort_catalog(),
        'users' => [
            $ownerSn => ['studentNumber'=>$ownerSn,'name'=>'مالک تست','passwordHash'=>$fixturePassword,'role'=>'owner','cohortKey'=>DENT_TERM7_COHORT],
            $studentA => ['studentNumber'=>$studentA,'name'=>'دانشجوی الف','passwordHash'=>$fixturePassword,'role'=>'student','cohortKey'=>DENT_TERM7_COHORT],
            $studentB => ['studentNumber'=>$studentB,'name'=>'دانشجوی ب','passwordHash'=>$fixturePassword,'role'=>'student','cohortKey'=>DENT_TERM7_COHORT],
        ],
    ]);
    $owner = dent_get_user_record($ownerSn);
    $first = dent_term7_owner_update_assignment($owner, $studentA, 'group10', 1);
    term7_group_assert(($first['group10Status'] ?? '') === 'member', 'Owner can assign morning group with member status');
    $leader = dent_term7_owner_set_leader($owner, $studentA, 'group10', true);
    term7_group_assert(($leader['group10Status'] ?? '') === 'leader', 'Owner can mark assigned student as leader');
    dent_term7_owner_update_assignment($owner, $studentB, 'group10', 1);
    dent_term7_owner_set_leader($owner, $studentB, 'group10', true);
    term7_group_assert((dent_term7_public_assignment_for_student($studentA)['group10Status'] ?? '') === 'member', 'One leader per group is enforced');
    dent_term7_owner_update_assignment($owner, $studentB, 'group10', 2);
    term7_group_assert(!isset(dent_term7_state_read()['groupLeaders']['1']), 'Moving a leader clears stale group leader state');
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
    $capabilities = classops_bot_service_dispatch(['action'=>'classopsCapabilities','platform'=>'telegram','platformUserId'=>'900001']);
    term7_group_assert(($capabilities['role'] ?? '') === 'owner' && isset($capabilities['term7']['group10Status']), 'Signed ClassOps capability response carries Term 7 assignment');
    $serviceRoster = classops_bot_service_dispatch(['action'=>'classopsTerm7Roster','platform'=>'telegram','platformUserId'=>'900001']);
    term7_group_assert(count($serviceRoster['roster'] ?? []) === 3, 'Signed owner service exposes canonical Term 7 roster');
} finally {
    term7_group_cleanup($testRoot);
}

exit($failures === 0 ? 0 : 1);
