<?php
declare(strict_types=1);

function classops_test_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        if (is_file($path)) {
            @unlink($path);
        }
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        classops_test_remove_tree($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}

function classops_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function classops_test_expect(string $code, callable $callback): void
{
    try {
        $callback();
    } catch (DentClassOpsDomainException|DentClassOpsPersistenceException $exception) {
        classops_test_assert($exception->reasonCode === $code, "Expected {$code}, got {$exception->reasonCode}");
        return;
    }
    throw new RuntimeException("Expected exception {$code}");
}

if (($argv[1] ?? '') === '--worker') {
    $path = (string) ($argv[2] ?? '');
    $index = max(0, (int) ($argv[3] ?? 0));
    putenv('DENT_CLASSOPS_STORE_PATH=' . $path);
    ini_set('error_log', dirname($path) . DIRECTORY_SEPARATOR . 'worker-errors.log');
    require_once dirname(__DIR__) . '/public_html/api/classops_store.php';
    try {
        classops_create_item([
            'cohortKey' => 'dentistry-1402',
            'type' => 'announcement',
            'title' => 'Concurrent item ' . $index,
        ], ['studentNumber' => '402000000', 'role' => 'owner'], 'worker-request-' . str_pad((string) $index, 5, '0', STR_PAD_LEFT));
        exit(0);
    } catch (Throwable $exception) {
        file_put_contents('php://stderr', get_class($exception) . ':' . $exception->getMessage());
        exit(1);
    }
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dent-classops-' . bin2hex(random_bytes(6));
if (!mkdir($root, 0700, true) && !is_dir($root)) {
    throw new RuntimeException('Unable to create ClassOps test root');
}
$storePath = $root . DIRECTORY_SEPARATOR . 'classops' . DIRECTORY_SEPARATOR . 'store.json';
putenv('DENT_CLASSOPS_STORE_PATH=' . $storePath);
ini_set('error_log', $root . DIRECTORY_SEPARATOR . 'classops-errors.log');
require_once dirname(__DIR__) . '/public_html/api/classops_store.php';

$owner = ['studentNumber' => '402000000', 'role' => 'owner'];
$metrics = [];

try {
    // Missing storage is readable as an uninitialized empty view, never written implicitly.
    $missing = classops_status();
    classops_test_assert($missing['initialized'] === false && !is_file($storePath), 'Read initialized missing storage');

    // 1/2: create/read and explicit first initialization.
    $created = classops_create_item([
        'cohortKey' => 'dentistry-1402',
        'type' => 'event',
        'title' => 'جلسه آزمایشی',
        'description' => 'فقط fixture محلی',
        'timing' => ['startsAt' => '2026-09-08T08:30:00+03:30', 'endsAt' => '2026-09-08T10:30:00+03:30'],
        'audienceSpec' => ['mode' => 'entire_cohort', 'refs' => []],
        'deliveryPlan' => ['initialDestinations' => ['class_group'], 'reminderDestinations' => ['private_users']],
    ], $owner, 'create-request-0001', 'fixture create');
    $item = $created['item'];
    $id = (string) $item['id'];
    classops_test_assert(is_file($storePath) && $item['revision'] === 1, 'Create did not initialize store');
    classops_test_assert($item['timing']['startsAt'] === '2026-09-08T05:00:00Z', 'Timestamp was not canonicalized');
    classops_test_assert(classops_get_item($id)['title'] === 'جلسه آزمایشی', 'Read after create failed');

    // 2/3: update/revision and stale optimistic concurrency conflict.
    $updated = classops_update_item($id, 1, ['title' => 'جلسه اصلاح‌شده'], $owner, 'update-request-0001', 'اصلاح عنوان');
    classops_test_assert($updated['item']['revision'] === 2, 'Revision did not increment');
    $history = classops_revision_history($id);
    classops_test_assert($history['total'] === 2 && $history['revisions'][0]['revision'] === 2, 'Revision history invalid');
    classops_test_expect('CLASSOPS_REVISION_CONFLICT', static fn() => classops_update_item(
        $id, 1, ['location' => 'دانشکده'], $owner, 'stale-request-0001', 'stale update'
    ));

    // 4/5: same retry is read-only; reused key with a different payload conflicts.
    $beforeReplay = classops_read_snapshot(false);
    $replay = classops_update_item($id, 1, ['title' => 'جلسه اصلاح‌شده'], $owner, 'update-request-0001', 'اصلاح عنوان');
    $afterReplay = classops_read_snapshot(false);
    classops_test_assert($replay['idempotentReplay'] === true && $replay['writePerformed'] === false, 'Retry was not idempotent');
    classops_test_assert($beforeReplay['hash'] === $afterReplay['hash'], 'Idempotent retry rewrote store');
    classops_test_expect('CLASSOPS_IDEMPOTENCY_CONFLICT', static fn() => classops_update_item(
        $id, 2, ['title' => 'payload conflict'], $owner, 'update-request-0001', 'different payload'
    ));

    // 6: lifecycle cancellation then archive, with distinct idempotency namespaces.
    $cancelled = classops_transition_item('cancel', $id, 2, $owner, 'cancel-request-0001', 'لغو fixture');
    classops_test_assert($cancelled['item']['status'] === 'cancelled' && $cancelled['item']['revision'] === 3, 'Cancel failed');
    $archived = classops_transition_item('archive', $id, 3, $owner, 'archive-request-0001', 'بایگانی fixture');
    classops_test_assert($archived['item']['status'] === 'archived' && $archived['item']['revision'] === 4, 'Archive failed');
    classops_test_expect('CLASSOPS_ITEM_ARCHIVED', static fn() => classops_update_item(
        $id, 4, ['title' => 'forbidden'], $owner, 'archived-request-0001', 'forbidden edit'
    ));

    // Input/contract validation.
    classops_test_expect('CLASSOPS_INVALID_TYPE', static fn() => classops_create_item([
        'cohortKey' => 'dentistry-1402', 'type' => 'arbitrary', 'title' => 'bad',
    ], $owner, 'invalid-type-0001'));
    classops_test_expect('CLASSOPS_INVALID_TIMESTAMP', static fn() => classops_create_item([
        'cohortKey' => 'dentistry-1402', 'type' => 'event', 'title' => 'bad time',
        'timing' => ['startsAt' => '2026-09-08 08:30'],
    ], $owner, 'invalid-time-0001'));
    classops_test_expect('CLASSOPS_FIELD_TOO_LONG', static fn() => classops_create_item([
        'cohortKey' => 'dentistry-1402', 'type' => 'event', 'title' => str_repeat('x', 161),
    ], $owner, 'oversized-title-0001'));
    classops_test_expect('CLASSOPS_UNKNOWN_FIELD', static fn() => classops_create_item([
        'cohortKey' => 'dentistry-1402', 'type' => 'event', 'title' => 'bad', 'unknown' => true,
    ], $owner, 'unknown-field-0001'));

    // 7/8/11: malformed, empty and invalid-schema existing files fail closed and remain byte-identical.
    foreach ([
        'malformed' => '{bad json',
        'empty' => '',
        'schema' => json_encode(['schemaVersion' => 999], JSON_UNESCAPED_SLASHES),
    ] as $case => $payload) {
        $casePath = $root . DIRECTORY_SEPARATOR . $case . DIRECTORY_SEPARATOR . 'store.json';
        mkdir(dirname($casePath), 0700, true);
        file_put_contents($casePath, $payload);
        putenv('DENT_CLASSOPS_STORE_PATH=' . $casePath);
        $before = file_get_contents($casePath);
        $expectedCode = $case === 'schema' ? 'CLASSOPS_SCHEMA_INVALID' : 'CLASSOPS_STORE_CORRUPT';
        classops_test_expect($expectedCode, static fn() => classops_read_store(false));
        classops_test_assert(file_get_contents($casePath) === $before, "{$case} store was overwritten");
        classops_test_assert(count(glob(dirname($casePath) . DIRECTORY_SEPARATOR . '.corrupt' . DIRECTORY_SEPARATOR . '*.json') ?: []) >= 1, "{$case} forensic copy missing");
    }
    putenv('DENT_CLASSOPS_STORE_PATH=' . $storePath);

    // 9: interruption, short write and invalid temp all preserve the prior active generation.
    foreach ([
        'interrupt' => ['interruptBeforeCommit' => true, 'code' => 'CLASSOPS_TEST_INTERRUPTED'],
        'short' => ['shortWriteAfterBytes' => 8, 'code' => 'CLASSOPS_SHORT_WRITE'],
        'invalid' => ['invalidTempJson' => true, 'code' => 'CLASSOPS_TEMP_INVALID'],
    ] as $case => $options) {
        $before = file_get_contents($storePath);
        $code = (string) $options['code'];
        unset($options['code']);
        classops_test_expect($code, static fn() => classops_transaction(static function (array &$store) use ($case): array {
            $store['updatedAt'] = '2099-01-01T00:00:' . str_pad((string) strlen($case), 2, '0', STR_PAD_LEFT) . '+00:00';
            return [];
        }, 'failure-' . $case, false, $options));
        classops_test_assert(file_get_contents($storePath) === $before, "{$case} changed active generation");
    }
    classops_test_assert(count(glob(dirname($storePath) . DIRECTORY_SEPARATOR . '.generations' . DIRECTORY_SEPARATOR . '*.json') ?: []) >= 1, 'Previous generation backup missing');

    // 12: reads/list/status must cause zero writes.
    $beforeRead = classops_read_snapshot(false);
    classops_list_items(['limit' => 10]);
    classops_status();
    classops_get_item($id);
    $afterRead = classops_read_snapshot(false);
    classops_test_assert($beforeRead['hash'] === $afterRead['hash'], 'Read-only operation rewrote store');

    // 10: 100 concurrent writers, no corruption and no lost committed records.
    $concurrentPath = $root . DIRECTORY_SEPARATOR . 'concurrent' . DIRECTORY_SEPARATOR . 'store.json';
    putenv('DENT_CLASSOPS_STORE_PATH=' . $concurrentPath);
    classops_create_item([
        'cohortKey' => 'dentistry-1402', 'type' => 'announcement', 'title' => 'seed',
    ], $owner, 'concurrent-seed-0001');
    $processes = [];
    $concurrencyStarted = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $concurrentPath, (string) $i], [
            0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
        ], $pipes);
        classops_test_assert(is_resource($process), 'Unable to spawn concurrent writer');
        fclose($pipes[0]);
        $processes[] = [$process, $pipes];
    }
    foreach ($processes as [$process, $pipes]) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        classops_test_assert($exit === 0, 'Concurrent writer failed: ' . $stdout . $stderr);
    }
    $concurrentElapsed = (microtime(true) - $concurrencyStarted) * 1000;
    $concurrent = classops_read_snapshot(false);
    classops_test_assert(count($concurrent['store']['items']) === 101, 'Concurrent writers lost records');
    classops_test_assert((int) $concurrent['store']['_storage']['generation'] === 101, 'Concurrent generation count invalid');

    // 1000-item/revision fixture and pagination/load sanity.
    $loadPath = $root . DIRECTORY_SEPARATOR . 'load' . DIRECTORY_SEPARATOR . 'store.json';
    putenv('DENT_CLASSOPS_STORE_PATH=' . $loadPath);
    $actor = classops_actor($owner);
    classops_transaction(static function (array &$store) use ($actor): array {
        $now = dent_iso_now();
        for ($i = 1; $i <= 1000; $i++) {
            $id = 'cop_fixture_' . str_pad((string) $i, 6, '0', STR_PAD_LEFT);
            $item = [
                'id' => $id,
                'contractVersion' => CLASSOPS_CONTRACT_VERSION,
                'cohortKey' => 'dentistry-1402',
                'type' => 'announcement',
                'title' => 'Fixture ' . $i,
                'description' => '',
                'course' => null,
                'timing' => classops_normalize_timing(null),
                'location' => '',
                'importance' => 'normal',
                'requireAck' => false,
                'audienceSpec' => classops_normalize_audience(null),
                'deliveryPlan' => classops_normalize_delivery_plan(null),
                'reminderPolicy' => classops_normalize_reminder_policy(null),
                'status' => 'draft',
                'source' => classops_normalize_source(null),
                'metadata' => [],
                'extensions' => [],
                'createdBy' => $actor,
                'createdAt' => $now,
                'updatedAt' => $now,
                'revision' => 1,
            ];
            $store['items'][$id] = $item;
            classops_append_revision($store, $item, null, $actor, 'load fixture', 'created');
        }
        $store['updatedAt'] = $now;
        return [];
    }, 'load-fixture', true);
    $readStarted = microtime(true);
    $page = classops_list_items(['limit' => 50]);
    $readMs = (microtime(true) - $readStarted) * 1000;
    classops_test_assert($page['count'] === 50 && $page['total'] === 1000 && is_string($page['nextCursor']), 'Pagination failed');
    $updateStarted = microtime(true);
    classops_update_item('cop_fixture_000500', 1, ['location' => 'دانشکده'], $owner, 'load-update-0001', 'load test');
    $updateMs = (microtime(true) - $updateStarted) * 1000;
    $loadSnapshot = classops_read_snapshot(false);
    $serialized = (string) $loadSnapshot['raw'];
    classops_test_assert(
        !str_contains($serialized, '402000000')
        && !str_contains($serialized, '+989120000000')
        && !str_contains($serialized, '0013546789'),
        'Sensitive PII leaked into ClassOps store'
    );

    $metrics = [
        'concurrentWriters' => 100,
        'concurrentElapsedMs' => round($concurrentElapsed, 2),
        'concurrentGeneration' => (int) $concurrent['store']['_storage']['generation'],
        'fixtureItems' => 1000,
        'fixtureRevisions' => array_sum(array_map('count', $loadSnapshot['store']['revisions'])),
        'storeBytes' => strlen($serialized),
        'list50Ms' => round($readMs, 2),
        'updateMs' => round($updateMs, 2),
        'peakMemoryBytes' => memory_get_peak_usage(true),
    ];
    echo json_encode(['status' => 'ok', 'metrics' => $metrics], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
} catch (Throwable $exception) {
    file_put_contents('php://stderr', get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL . $exception->getTraceAsString() . PHP_EOL);
    exit(1);
} finally {
    putenv('DENT_CLASSOPS_STORE_PATH');
    classops_test_remove_tree($root);
}
