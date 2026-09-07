<?php
declare(strict_types=1);

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dent-json-persistence-' . bin2hex(random_bytes(8));
putenv('DENT_SERVER_ONLY_ROOT=' . $root . DIRECTORY_SEPARATOR . 'server-only');
putenv('DENT_STORAGE_ROOT=' . $root . DIRECTORY_SEPARATOR . 'storage');
putenv('DENT_SESSION_SAVE_PATH=' . $root . DIRECTORY_SEPARATOR . 'sessions');
require_once dirname(__DIR__) . '/public_html/api/bootstrap.php';

$path = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'fixture.json';
dent_write_json_file($path, ['schemaVersion' => 1, 'items' => []]);
$first = file_get_contents($path);
if (!is_string($first) || dent_read_json_file($path, null)['schemaVersion'] !== 1) {
    throw new RuntimeException('valid JSON round trip failed');
}
dent_write_json_file($path, ['schemaVersion' => 1, 'items' => []]);
if (file_get_contents($path) !== $first) {
    throw new RuntimeException('no-op JSON write changed bytes');
}
file_put_contents($path, '{malformed');
$malformed = file_get_contents($path);
try {
    dent_write_json_file($path, ['schemaVersion' => 2]);
    throw new RuntimeException('malformed existing JSON was overwritten');
} catch (DentJsonPersistenceException $exception) {
    if ($exception->reasonCode !== 'JSON_STORE_CORRUPT') {
        throw $exception;
    }
}
if (file_get_contents($path) !== $malformed) {
    throw new RuntimeException('malformed source changed after rejected write');
}

file_put_contents($path, '"scalar"');
$scalar = file_get_contents($path);
try {
    dent_read_json_file($path, []);
    throw new RuntimeException('scalar JSON root unexpectedly accepted');
} catch (DentJsonPersistenceException $exception) {
    if ($exception->reasonCode !== 'JSON_STORE_SCHEMA_INVALID') {
        throw $exception;
    }
}
try {
    dent_write_json_file($path, ['schemaVersion' => 2]);
    throw new RuntimeException('scalar JSON source unexpectedly overwritten');
} catch (DentJsonPersistenceException $exception) {
    if ($exception->reasonCode !== 'JSON_STORE_SCHEMA_INVALID') {
        throw $exception;
    }
}
if (file_get_contents($path) !== $scalar) {
    throw new RuntimeException('scalar JSON source changed after rejected write');
}

dent_release_session_lock();
$removeTree = static function (string $target) use (&$removeTree): void {
    if (is_file($target) || is_link($target)) {
        @unlink($target);
        return;
    }
    foreach (is_dir($target) ? (scandir($target) ?: []) : [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            $removeTree($target . DIRECTORY_SEPARATOR . $entry);
        }
    }
    if (is_dir($target)) {
        @rmdir($target);
    }
};
$removeTree($root);
echo "JSON persistence checks passed.\n";
