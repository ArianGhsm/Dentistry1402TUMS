<?php
declare(strict_types=1);

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dent-atomic-stores-' . bin2hex(random_bytes(8));
putenv('DENT_STORAGE_ROOT=' . $root . DIRECTORY_SEPARATOR . 'storage');
putenv('DENT_SERVER_ONLY_ROOT=' . $root . DIRECTORY_SEPARATOR . 'server-only');
putenv('DENT_SESSION_SAVE_PATH=' . $root . DIRECTORY_SEPARATOR . 'sessions');
putenv('DENT_AUTH_SECRET_KEY=' . base64_encode(str_repeat('a', 32)));
require_once dirname(__DIR__) . '/public_html/api/auth_store.php';
require_once dirname(__DIR__) . '/public_html/api/analytics_store.php';
require_once dirname(__DIR__) . '/public_html/api/grades_store.php';
require_once dirname(__DIR__) . '/public_html/api/academic_term7.php';
require_once dirname(__DIR__) . '/public_html/api/bot_student_assistant.php';
restore_error_handler();
restore_exception_handler();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
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

try {
    analytics_update_store(static function (array $store): array {
        $store['totals']['pageViews'] = 1;
        return $store;
    });
    $assert((analytics_read_store()['totals']['pageViews'] ?? 0) === 1, 'analytics round trip failed');

    dent_auth_sessions_with_lock(static function (array &$store): array {
        $store['sessions']['fixture'] = ['status' => 'active', 'createdAt' => dent_iso_now()];
        return ['ok' => true];
    }, true);
    $sessions = dent_read_json_file(dent_auth_sessions_path(), []);
    $assert(isset($sessions['sessions']['fixture']), 'auth session round trip failed');

    dent_write_grades_source(['شماره دانشجویی', 'نام', 'نمره'], [['1', 'دانشجو', '20']]);
    $csv = file(dent_grades_store_path(), FILE_IGNORE_NEW_LINES);
    $assert(is_array($csv) && count($csv) === 2, 'grades atomic CSV write failed');

    foreach ([
        dent_term7_state_path() => static fn(): array => dent_term7_state_with_lock(static fn(array &$state): array => []),
        dent_student_assistant_store_path() => static fn(): array => dent_student_assistant_store_with_lock(static fn(array &$state): array => []),
    ] as $path => $operation) {
        dent_ensure_directory(dirname($path));
        file_put_contents($path, '{malformed');
        $before = file_get_contents($path);
        try {
            $operation();
            throw new RuntimeException('corrupt auxiliary store was accepted');
        } catch (DentJsonPersistenceException $exception) {
            $assert($exception->reasonCode === 'JSON_STORE_CORRUPT', 'wrong corruption reason code');
        }
        $assert(file_get_contents($path) === $before, 'corrupt auxiliary store was overwritten');
    }
    echo "Atomic auxiliary store checks passed.\n";
} finally {
    dent_release_session_lock();
    $removeTree($root);
}
