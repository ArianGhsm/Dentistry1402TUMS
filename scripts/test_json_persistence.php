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
$largePath = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'large-fixture.json';
$largePayload = ['schemaVersion' => 1, 'body' => str_repeat('x', 4 * 1024 * 1024)];
dent_write_json_file($largePath, $largePayload);
$largeDecoded = dent_read_json_file($largePath, null);
if (!is_array($largeDecoded) || !isset($largeDecoded['body']) || strlen((string) $largeDecoded['body']) !== 4 * 1024 * 1024) {
    throw new RuntimeException('large JSON generation did not round trip');
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

$secretPath = $root . DIRECTORY_SEPARATOR . 'secret.key';
$secret = dent_load_or_create_base64_secret_file($secretPath);
if (strlen($secret) !== 32 || dent_load_or_create_base64_secret_file($secretPath) !== $secret) {
    throw new RuntimeException('secret key creation is not stable');
}
$corruptSecret = 'not-a-valid-key' . PHP_EOL;
file_put_contents($secretPath, $corruptSecret, LOCK_EX);
try {
    dent_load_or_create_base64_secret_file($secretPath);
    throw new RuntimeException('corrupt existing secret key unexpectedly regenerated');
} catch (DentJsonPersistenceException $exception) {
    if ($exception->reasonCode !== 'SECRET_KEY_CORRUPT') {
        throw $exception;
    }
}
if (file_get_contents($secretPath) !== $corruptSecret) {
    throw new RuntimeException('corrupt secret key was overwritten');
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
$chatSource = (string) file_get_contents(dirname(__DIR__) . '/public_html/chat/chat_api.php');
$chatWriterStart = strpos($chatSource, 'function chat_write_store_file');
$chatWriterEnd = $chatWriterStart === false
    ? false
    : strpos($chatSource, 'function chat_migrate_from_legacy_files', $chatWriterStart);
$chatWriter = ($chatWriterStart !== false && $chatWriterEnd !== false)
    ? substr($chatSource, $chatWriterStart, $chatWriterEnd - $chatWriterStart)
    : '';
if (
    strpos($chatSource, 'dent_write_json_file(chat_store_path(), $store);') === false
    || strpos($chatSource, 'dent_write_json_file(chat_presence_path(), $store);') === false
    || strpos($chatSource, "'CHAT_STORE_SCHEMA_INVALID'") === false
    || strpos($chatWriter, '@unlink($path)') !== false
) {
    fwrite(STDERR, "Chat JSON persistence contract failed.\n");
    exit(1);
}
$pushSource = (string) file_get_contents(dirname(__DIR__) . '/public_html/api/push_store.php');
if (
    strpos($pushSource, 'if (!flock($lock, LOCK_SH))') === false
    || strpos($pushSource, 'if (!flock($lock, LOCK_EX))') === false
    || strpos($pushSource, "if (\$lock === false) {\n        return push_default_store();") !== false
) {
    fwrite(STDERR, "Push/VAPID lock contract failed.\n");
    exit(1);
}
echo "JSON and chat persistence checks passed.\n";
