<?php
declare(strict_types=1);

$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dent-term7-booklet-' . bin2hex(random_bytes(5));
putenv('DENT_STORAGE_ROOT=' . $testRoot);
putenv('DENT_SERVER_ONLY_ROOT=' . $testRoot . DIRECTORY_SEPARATOR . 'server-only');
putenv('DENT_AUTH_SECRET_KEY=' . base64_encode(str_repeat('b', 32)));

require_once __DIR__ . '/../public_html/api/bot_store.php';
require_once __DIR__ . '/../public_html/api/academic_term7_bot_service.php';
restore_error_handler();
restore_exception_handler();

$failures = 0;
function booklet_system_assert(bool $condition, string $label): void
{
    global $failures;
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$condition) $failures++;
}
function booklet_system_cleanup(string $path): void
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
    $memberSn = '40211272991';
    $managerSn = '40211272992';
    $specialSn = '40211272993';
    $paidSn = '40211272994';
    dent_save_user_store([
        'cohorts' => dent_default_cohort_catalog(),
        'users' => [
            $ownerSn => ['studentNumber'=>$ownerSn,'name'=>'مالک','passwordHash'=>$password,'role'=>'owner','cohortKey'=>DENT_TERM7_COHORT],
            $memberSn => ['studentNumber'=>$memberSn,'name'=>'عضو جزوه','passwordHash'=>$password,'role'=>'student','cohortKey'=>DENT_TERM7_COHORT],
            $managerSn => ['studentNumber'=>$managerSn,'name'=>'مسئول جزوه','passwordHash'=>$password,'role'=>'student','cohortKey'=>DENT_TERM7_COHORT],
            $specialSn => ['studentNumber'=>$specialSn,'name'=>'مسئول اینفوگرافیک','passwordHash'=>$password,'role'=>'student','cohortKey'=>DENT_TERM7_COHORT],
            $paidSn => ['studentNumber'=>$paidSn,'name'=>'دانشجوی پرداختی','passwordHash'=>$password,'role'=>'student','cohortKey'=>DENT_TERM7_COHORT],
        ],
    ]);
    $state = dent_term7_booklet_system_default();
    $state['groups']['1']['members'] = [$memberSn, $managerSn];
    $state['groups']['1']['leaderStudentNumber'] = $memberSn;
    $state['courseGroups']['research-methods-2'] = [1];
    $state['managers'][$managerSn] = ['studentNumber'=>$managerSn,'courseKeys'=>['research-methods-2'],'updatedAt'=>dent_iso_now()];
    $state['specialRoles'][$specialSn] = [[
        'key'=>DENT_TERM7_BOOKLET_INFOGRAPHIC_ROLE,
        'label'=>'مسئول اینفوگرافیک',
        'grantsFreeSubscription'=>true,
    ]];
    dent_term7_booklet_system_with_lock(static function (array &$locked) use ($state): array {
        $locked = $state;
        return [];
    });

    $member = dent_term7_booklet_public_context($memberSn);
    booklet_system_assert(($member['group'] ?? null) === 1 && ($member['status'] ?? '') === 'leader', 'Booklet group and leader status are projected');
    booklet_system_assert(($member['freeSubscriptionEligible'] ?? false) === true && ($member['subscriptionPriceRials'] ?? -1) === 0, 'Booklet member has zero-price automatic subscription');
    booklet_system_assert(count($member['members'] ?? []) === 2, 'Booklet group context exposes members');

    $manager = dent_term7_booklet_public_profile($managerSn);
    booklet_system_assert(($manager['isBookletManager'] ?? false) === true && ($manager['managerCourses'][0]['title'] ?? '') === 'روش تحقیق ۲', 'Booklet manager role and course are projected');

    $special = dent_term7_booklet_public_profile($specialSn);
    booklet_system_assert(($special['group'] ?? null) === null && ($special['freeSubscriptionEligible'] ?? false) === true, 'Infographic owner is free without booklet group');
    booklet_system_assert(($special['specialRoles'][0]['label'] ?? '') === 'مسئول اینفوگرافیک', 'Infographic role is explicit in profile');

    $paid = dent_term7_booklet_public_profile($paidSn);
    booklet_system_assert(($paid['freeSubscriptionEligible'] ?? true) === false && ($paid['subscriptionPriceRials'] ?? 0) === 1500000, 'Non-member remains on paid subscription path');

    $owner = dent_get_user_record($ownerSn);
    $payload = dent_term7_booklet_owner_payload($owner);
    booklet_system_assert(($payload['summary']['classCount'] ?? 0) === 5 && ($payload['summary']['classPaidMembers'] ?? -1) === 1, 'Owner summary separates class booklet members from paid path');

    $free = dent_term7_booklet_free_subscription_roster($owner);
    $freeNumbers = array_map(static fn(array $row): string => (string) ($row['studentNumber'] ?? ''), $free['eligible'] ?? []);
    booklet_system_assert(in_array($ownerSn, $freeNumbers, true), 'Owner is always included in automatic free roster');
    booklet_system_assert(in_array($memberSn, $freeNumbers, true) && in_array($managerSn, $freeNumbers, true) && in_array($specialSn, $freeNumbers, true), 'Group members and infographic owner are included in automatic free roster');
    booklet_system_assert(!in_array($paidSn, $freeNumbers, true), 'Paid-path student is excluded from free roster');

    dent_bot_persistence_initialize(dent_bot_store_path(), dent_bot_store_default(), 'dent_bot_store_normalize', 'term7-booklet-test-init');
    dent_bot_store_with_lock(static function (array &$store) use ($ownerSn): array {
        $platform = 'telegram';
        $platformUserId = '910001';
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
    $servicePayload = dent_term7_bot_service_dispatch([
        'action'=>'academicTerm7BookletRoster',
        'platform'=>'telegram',
        'platformUserId'=>'910001',
    ]);
    booklet_system_assert(($servicePayload['summary']['classPaidMembers'] ?? -1) === 1, 'Signed owner service exposes booklet-system summary');
    $serviceFree = dent_term7_bot_service_dispatch([
        'action'=>'academicTerm7BookletFreeRoster',
        'platform'=>'telegram',
        'platformUserId'=>'910001',
    ]);
    booklet_system_assert(($serviceFree['count'] ?? 0) === 4, 'Signed owner service exposes exact automatic-free roster');

    $account = dent_bot_service_dispatch([
        'action'=>'account',
        'platform'=>'telegram',
        'platformUserId'=>'910001',
    ]);
    booklet_system_assert(($account['bookletProfile']['freeSubscriptionEligible'] ?? false) === true, 'Account response carries computed booklet profile');

    dent_term7_booklet_owner_update_group($owner, $paidSn, 31);
    $updated = dent_term7_booklet_owner_set_leader($owner, $paidSn, true);
    booklet_system_assert(($updated['group'] ?? null) === 31 && ($updated['status'] ?? '') === 'leader', 'Owner can manage booklet group and leader with same domain rules');
} finally {
    booklet_system_cleanup($testRoot);
}

exit($failures === 0 ? 0 : 1);
