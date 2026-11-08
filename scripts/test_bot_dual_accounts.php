<?php
declare(strict_types=1);

$testRoot = sys_get_temp_dir() . '/dent-bot-dual-accounts-' . bin2hex(random_bytes(5));
putenv('DENT_STORAGE_ROOT=' . $testRoot);
putenv('DENT_SERVER_ONLY_ROOT=' . $testRoot . '/server-only');
putenv('DENT_AUTH_SECRET_KEY=' . base64_encode(str_repeat('t', 32)));
require_once __DIR__ . '/../public_html/api/bot_store.php';
restore_error_handler();
restore_exception_handler();

function dual_assert(bool $value, string $label): void {
    if (!$value) throw new RuntimeException('FAIL: ' . $label);
    echo 'PASS: ' . $label . PHP_EOL;
}
function dual_cleanup(string $path): void {
    if (!is_dir($path)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) @rmdir($item->getPathname());
        else @unlink($item->getPathname());
    }
    @rmdir($path);
}

try {
    $password = dent_hash_password('dual-account-test-password');
    $owner = ['studentNumber' => '40211272003', 'name' => 'مالک آزمایشی',
        'passwordHash' => $password, 'role' => 'owner', 'cohortKey' => 'dentistry-1402'];
    $student = ['studentNumber' => '40211272991', 'name' => 'دانشجوی آزمایشی',
        'passwordHash' => $password, 'role' => 'student', 'cohortKey' => 'dentistry-1402'];
    $other = ['studentNumber' => '40211272992', 'name' => 'دانشجوی دیگر',
        'passwordHash' => $password, 'role' => 'student', 'cohortKey' => 'dentistry-1402'];
    dent_save_user_store([
        'cohorts' => dent_default_cohort_catalog(),
        'users' => [$owner['studentNumber'] => $owner, $student['studentNumber'] => $student, $other['studentNumber'] => $other],
    ]);
    dent_bot_persistence_initialize(
        dent_bot_store_path(), dent_bot_store_default(), 'dent_bot_store_normalize', 'dual-account-test-init'
    );
    $reason = 'اجازه صریح مالک برای اتصال دوم';
    $link = static function(string $platform, string $id, string $studentNumber, string $username = '') use ($owner, $reason): array {
        return dent_bot_set_identity_mapping($owner, $platform, [
            'targetPlatformUserId' => $id, 'studentNumber' => $studentNumber,
            'telegramProfile' => ['displayName' => 'دانشجوی تست', 'username' => $username],
            'reason' => $reason,
        ]);
    };
    dual_assert($link('telegram', '111111111', $student['studentNumber'], 'testalpha01')['status'] === 'linked', 'First telegram account linked');
    dual_assert($link('telegram', '222222222', $student['studentNumber'], 'testalpha02')['status'] === 'linked', 'Second telegram account added without replacement');
    dual_assert($link('telegram', '222222222', $student['studentNumber'])['status'] === 'already-linked', 'Repeated add is idempotent');
    $connections = dent_bot_account_connections($student);
    $telegram = $connections['connections']['telegram'];
    dual_assert(count($telegram['accounts']) === 2 && array_column($telegram['accounts'], 'platformUserId') === ['111111111', '222222222'], 'Website lists both telegram accounts in order');
    dual_assert($telegram['platformUserId'] === '111111111', 'Legacy single-account response keeps original as primary');
    dual_assert($telegram['accounts'][1]['platformUsername'] === 'testalpha02', 'Secondary username is retained');
    dual_assert($link('bale', '333333333', $student['studentNumber'])['status'] === 'linked', 'Bale mapping remains independent');
    $snapshot = dent_bot_store_read(static fn(array $s): array => $s);
    $hash1 = dent_bot_identity_hash('telegram', '111111111');
    $hash2 = dent_bot_identity_hash('telegram', '222222222');
    $hash3 = dent_bot_identity_hash('telegram', '444444444');
    dual_assert(dent_bot_conflicting_link($snapshot, $hash3, 'telegram', $student['studentNumber'])['type'] === 'limit', 'Third telegram account rejected at capacity');
    dual_assert(dent_bot_conflicting_link($snapshot, $hash1, 'telegram', $other['studentNumber'])['type'] === 'identity', 'Account cannot be assigned to another student');
    dual_assert(dent_bot_conflicting_link($snapshot, $hash1, 'telegram', $student['studentNumber']) === null, 'Existing canonical mapping stays valid');
    dual_assert(dent_bot_link_auth_complete($snapshot['links'][$hash2]) && dent_bot_linked_user('telegram', '222222222')['studentNumber'] === $student['studentNumber'], 'Secondary account resolves to same authorized student');
    $disconnect = dent_bot_disconnect_account($student, 'telegram', '222222222');
    dual_assert($disconnect['success'] === true && $disconnect['deliveryQueued'] === true, 'Selective website disconnect queues receipt');
    $remaining = dent_bot_account_connections($student)['connections'];
    dual_assert(count($remaining['telegram']['accounts']) === 1 && $remaining['telegram']['platformUserId'] === '111111111', 'First telegram account preserved after removing second');
    dual_assert(count($remaining['bale']['accounts']) === 1, 'Bale account unaffected by telegram removal');
    echo "Dual account regression suite passed.\n";
} finally {
    dual_cleanup($testRoot);
}
