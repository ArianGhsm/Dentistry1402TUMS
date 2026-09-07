<?php
declare(strict_types=1);

if (!function_exists('dent_ensure_directory')) {
    function dent_ensure_directory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) {
            throw new RuntimeException('mkdir failed');
        }
    }
}
if (!function_exists('dent_request_method')) {
    function dent_request_method(): string { return 'CLI'; }
}
if (!function_exists('dent_clean_text')) {
    function dent_clean_text(string $value, int $maxLength = 200): string
    {
        return substr(trim($value), 0, $maxLength);
    }
}

require_once dirname(__DIR__) . '/public_html/api/bot_persistence.php';

$persistenceSource = (string) file_get_contents(dirname(__DIR__) . '/public_html/api/bot_persistence.php');
if (
    strpos($persistenceSource, "min(65536, \$expected - \$written)") === false
    || strpos($persistenceSource, 'filesize($path)') !== false
    || strpos($persistenceSource, 'stream_get_contents($handle)') === false
) {
    throw new RuntimeException('bot persistence must use bounded writes and descriptor readback validation, not path filesize validation');
}

function test_normalize(array $store): array
{
    if (isset($store['records']) && !is_array($store['records'])) {
        throw new DentBotPersistenceException('TEST_SCHEMA_INVALID', 'records invalid');
    }
    $store = array_merge(['schemaVersion' => 1, 'records' => []], $store);
    return $store;
}

function test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function test_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $items = scandir($path);
    if (!is_array($items)) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($child)) {
            test_remove_tree($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
}

if (($argv[1] ?? '') === '--worker') {
    $path = (string) ($argv[2] ?? '');
    $key = (string) ($argv[3] ?? '');
    dent_bot_persistence_update(
        $path,
        ['schemaVersion' => 1, 'records' => []],
        'test_normalize',
        static function (array &$store) use ($key): array {
            $store['records'][$key] = true;
            return [];
        },
        false,
        'concurrent-writer'
    );
    exit(0);
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dent-bot-persistence-' . bin2hex(random_bytes(6));
dent_ensure_directory($root);
$path = $root . DIRECTORY_SEPARATOR . 'store.json';
$default = ['schemaVersion' => 1, 'records' => []];
$tests = 0;

try {
    // 1 + 2: explicit initialization of a truly missing store, then valid R/W.
    dent_bot_persistence_initialize($path, $default, 'test_normalize', 'test-initialize');
    test_assert(is_file($path), 'explicit initialization did not create the store');

    // A completed temp write is verified by descriptor readback, rather than
    // an unreliable path stat from a shared-host PHP runtime.
    $statProbe = $root . DIRECTORY_SEPARATOR . 'stat-probe.json';
    file_put_contents($statProbe, 'old', LOCK_EX);
    test_assert(filesize($statProbe) === 3, 'unable to prime stat cache');
    @unlink($statProbe);
    dent_bot_persistence_write_full($statProbe, "fresh\n", []);
    clearstatcache(true, $statProbe);
    test_assert(filesize($statProbe) === 6, 'fresh temp write size was not verified correctly');
    @unlink($statProbe);
    dent_bot_persistence_update(
        $path,
        $default,
        'test_normalize',
        static function (array &$store): array {
            $store['records']['first'] = true;
            return ['ok' => true];
        },
        false,
        'test-valid-write'
    );
    $read = dent_bot_persistence_read(
        $path,
        $default,
        'test_normalize',
        static fn(array $store): array => ['records' => $store['records']],
        'test-valid-read'
    );
    test_assert(($read['records']['first'] ?? false) === true, 'valid read/write lost data');
    $tests += 3;

    // 3: an existing malformed store fails closed and remains byte-identical.
    $malformed = $root . DIRECTORY_SEPARATOR . 'malformed.json';
    file_put_contents($malformed, "{\"schemaVersion\":1,broken", LOCK_EX);
    $malformedHash = hash_file('sha256', $malformed);
    $failedClosed = false;
    try {
        dent_bot_persistence_update(
            $malformed,
            $default,
            'test_normalize',
            static function (array &$store): array { $store['records'] = []; return []; },
            false,
            'test-malformed'
        );
    } catch (DentBotPersistenceException $exception) {
        $failedClosed = $exception->reasonCode === 'BOT_STORE_CORRUPT';
    }
    test_assert($failedClosed, 'malformed existing JSON did not fail closed');
    test_assert(hash_file('sha256', $malformed) === $malformedHash, 'malformed source was overwritten');
    test_assert(count(glob($root . DIRECTORY_SEPARATOR . '.corrupt' . DIRECTORY_SEPARATOR . 'malformed.json.*.json') ?: []) === 1, 'corrupt forensic copy was not preserved');
    $tests++;

    // A syntactically-valid but schema-invalid store is corruption too.
    $schemaInvalid = $root . DIRECTORY_SEPARATOR . 'schema-invalid.json';
    file_put_contents($schemaInvalid, "{\"schemaVersion\":1,\"records\":false}\n", LOCK_EX);
    $schemaInvalidHash = hash_file('sha256', $schemaInvalid);
    try {
        dent_bot_persistence_read(
            $schemaInvalid,
            $default,
            'test_normalize',
            static fn(array $store): array => $store,
            'test-schema-invalid'
        );
        throw new RuntimeException('schema-invalid store unexpectedly opened');
    } catch (DentBotPersistenceException $exception) {
        test_assert($exception->reasonCode === 'TEST_SCHEMA_INVALID', 'wrong schema-invalid error');
    }
    test_assert(hash_file('sha256', $schemaInvalid) === $schemaInvalidHash, 'schema-invalid source was overwritten');
    test_assert(count(glob($root . DIRECTORY_SEPARATOR . '.corrupt' . DIRECTORY_SEPARATOR . 'schema-invalid.json.*.json') ?: []) === 1, 'schema-invalid forensic copy was not preserved');
    $tests++;

    $survivalHash = hash_file('sha256', $path);
    // 4: interruption before commit preserves the prior generation.
    try {
        dent_bot_persistence_update(
            $path,
            $default,
            'test_normalize',
            static function (array &$store): array { $store['records']['interrupt'] = true; return []; },
            false,
            'test-interrupt',
            ['interruptBeforeCommit' => true]
        );
        throw new RuntimeException('interruption simulation unexpectedly committed');
    } catch (DentBotPersistenceException $exception) {
        test_assert($exception->reasonCode === 'BOT_STORE_INTERRUPTED', 'wrong interruption error');
    }
    test_assert(hash_file('sha256', $path) === $survivalHash, 'interruption changed active generation');
    $tests++;

    // 5: short write preserves the prior generation.
    try {
        dent_bot_persistence_update(
            $path,
            $default,
            'test_normalize',
            static function (array &$store): array { $store['records']['short'] = str_repeat('x', 1000); return []; },
            false,
            'test-short-write',
            ['shortWriteAfterBytes' => 20]
        );
        throw new RuntimeException('short-write simulation unexpectedly committed');
    } catch (DentBotPersistenceException $exception) {
        test_assert($exception->reasonCode === 'BOT_STORE_SHORT_WRITE', 'wrong short-write error');
    }
    test_assert(hash_file('sha256', $path) === $survivalHash, 'short write changed active generation');
    $tests++;

    // 6: invalid temp JSON never reaches atomic rename.
    try {
        dent_bot_persistence_update(
            $path,
            $default,
            'test_normalize',
            static function (array &$store): array { $store['records']['invalid'] = true; return []; },
            false,
            'test-invalid-temp',
            ['invalidTempJson' => true]
        );
        throw new RuntimeException('invalid-temp simulation unexpectedly committed');
    } catch (DentBotPersistenceException $exception) {
        test_assert($exception->reasonCode === 'BOT_STORE_TEMP_INVALID', 'wrong invalid-temp error');
    }
    test_assert(hash_file('sha256', $path) === $survivalHash, 'invalid temp changed active generation');
    $tests++;

    // Recovery maintenance closes both read and write paths without touching data.
    $maintenanceHash = hash_file('sha256', $path);
    file_put_contents(dent_bot_persistence_maintenance_path($path), "incident-recovery\n", LOCK_EX);
    try {
        dent_bot_persistence_update(
            $path,
            $default,
            'test_normalize',
            static function (array &$store): array { $store['records']['maintenance'] = true; return []; },
            false,
            'test-maintenance'
        );
        throw new RuntimeException('maintenance write unexpectedly opened');
    } catch (DentBotPersistenceException $exception) {
        test_assert($exception->reasonCode === 'BOT_STORE_MAINTENANCE', 'wrong maintenance error');
    }
    @unlink(dent_bot_persistence_maintenance_path($path));
    test_assert(hash_file('sha256', $path) === $maintenanceHash, 'maintenance attempt changed active generation');
    $tests++;

    // 7: 100 independent concurrent writers serialize without lost commits.
    $processes = [];
    $php = PHP_BINARY;
    for ($index = 0; $index < 100; $index++) {
        $command = escapeshellarg($php) . ' ' . escapeshellarg(__FILE__) . ' --worker ' . escapeshellarg($path) . ' ' . escapeshellarg('writer-' . $index);
        $pipes = [];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        test_assert(is_resource($process), 'unable to start concurrent writer');
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $processes[] = [$process, $pipes];
    }
    foreach ($processes as [$process, $pipes]) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        test_assert($exitCode === 0, 'concurrent writer failed: ' . trim((string) $stdout . ' ' . (string) $stderr));
    }
    $final = json_decode((string) file_get_contents($path), true);
    test_assert(is_array($final), 'concurrent result is invalid JSON');
    for ($index = 0; $index < 100; $index++) {
        test_assert(($final['records']['writer-' . $index] ?? false) === true, 'lost concurrent writer record');
    }
    test_assert(count(glob($path . '.tmp.*') ?: []) === 0, 'orphan temp files remain');
    test_assert(count(glob($root . DIRECTORY_SEPARATOR . '.generations' . DIRECTORY_SEPARATOR . '*.json*') ?: []) >= 1, 'previous generation backup missing');
    $tests++;

    echo json_encode(['status' => 'passed', 'tests' => $tests, 'writers' => 100], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    test_remove_tree($root);
}
