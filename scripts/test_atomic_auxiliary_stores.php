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
require_once dirname(__DIR__) . '/public_html/api/payments_store.php';
require_once dirname(__DIR__) . '/public_html/api/push_store.php';
require_once dirname(__DIR__) . '/public_html/api/notifications_store.php';
require_once dirname(__DIR__) . '/public_html/api/content_tools_store.php';
require_once dirname(__DIR__) . '/public_html/api/exams_store.php';
require_once dirname(__DIR__) . '/public_html/api/msg_store.php';
require_once dirname(__DIR__) . '/public_html/api/html_uploader_store.php';
require_once dirname(__DIR__) . '/public_html/api/navid_store.php';
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
    dent_ensure_directory(dirname(payments_store_path()));
    file_put_contents(payments_store_path(), '{}');
    $paymentsBefore = file_get_contents(payments_store_path());
    try {
        payments_with_store_lock(static fn(array &$store): array => []);
        throw new RuntimeException('schema-invalid payment store was accepted');
    } catch (DentJsonPersistenceException $exception) {
        $assert($exception->reasonCode === 'PAYMENTS_STORE_SCHEMA_INVALID', 'wrong payment schema reason code');
    }
    $assert(file_get_contents(payments_store_path()) === $paymentsBefore, 'schema-invalid payment store was overwritten');
    foreach ([
        push_store_path() => [
            static fn(): array => push_with_store_lock(static fn(array &$store): array => []),
            'PUSH_STORE_SCHEMA_INVALID',
        ],
        notifications_store_path() => [
            static fn(): array => notifications_with_store_lock(static fn(array &$store): array => []),
            'NOTIFICATIONS_STORE_SCHEMA_INVALID',
        ],
    ] as $path => [$operation, $reasonCode]) {
        dent_ensure_directory(dirname($path));
        file_put_contents($path, '{}');
        $before = file_get_contents($path);
        try {
            $operation();
            throw new RuntimeException('schema-invalid critical store was accepted');
        } catch (DentJsonPersistenceException $exception) {
            $assert($exception->reasonCode === $reasonCode, 'wrong critical-store schema reason code');
        }
        $assert(file_get_contents($path) === $before, 'schema-invalid critical store was overwritten');
    }
    foreach ([
        dent_auth_sessions_path() => [
            static fn(): array => dent_auth_sessions_with_lock(static fn(array &$store): array => []),
            'AUTH_SESSIONS_SCHEMA_INVALID',
        ],
        dent_auth_meta_path() => [
            static fn(): array => dent_load_auth_meta_store(),
            'AUTH_META_SCHEMA_INVALID',
        ],
    ] as $path => [$operation, $reasonCode]) {
        dent_ensure_directory(dirname($path));
        file_put_contents($path, '{}');
        $before = file_get_contents($path);
        try {
            $operation();
            throw new RuntimeException('schema-invalid auth store was accepted');
        } catch (DentJsonPersistenceException $exception) {
            $assert($exception->reasonCode === $reasonCode, 'wrong auth-store schema reason code');
        }
        $assert(file_get_contents($path) === $before, 'schema-invalid auth store was overwritten');
    }
    if (push_supported()) {
        dent_ensure_directory(dirname(push_vapid_path()));
        file_put_contents(push_vapid_path(), '{}');
        $before = file_get_contents(push_vapid_path());
        try {
            push_load_or_create_vapid();
            throw new RuntimeException('schema-invalid VAPID store was accepted');
        } catch (DentJsonPersistenceException $exception) {
            $assert($exception->reasonCode === 'VAPID_STORE_SCHEMA_INVALID', 'wrong VAPID schema reason code');
        }
        $assert(file_get_contents(push_vapid_path()) === $before, 'schema-invalid VAPID store was overwritten');
    }
    foreach ([
        content_store_path() => [static fn(): array => content_load_store_unlocked(), 'CONTENT_STORE_SCHEMA_INVALID'],
        dent_exams_store_path() => [static fn(): array => dent_exams_load_store_unlocked(), 'EXAMS_STORE_SCHEMA_INVALID'],
        msg_store_path() => [static fn(): array => msg_load_unlocked(), 'MSG_STORE_SCHEMA_INVALID'],
        html_uploader_store_path() => [static fn(): array => html_uploader_load_store_unlocked(), 'HTML_UPLOADER_STORE_SCHEMA_INVALID'],
        navid_store_path() => [static fn(): array => navid_load_store(), 'NAVID_STORE_SCHEMA_INVALID'],
    ] as $path => [$operation, $reasonCode]) {
        dent_ensure_directory(dirname($path));
        file_put_contents($path, '{}');
        $before = file_get_contents($path);
        try {
            $operation();
            throw new RuntimeException('schema-invalid feature store was accepted');
        } catch (DentJsonPersistenceException $exception) {
            $assert($exception->reasonCode === $reasonCode, 'wrong feature-store schema reason code');
        }
        $assert(file_get_contents($path) === $before, 'schema-invalid feature store was overwritten');
    }
    foreach ([
        dent_storage_path('dis_request/store.json') => [static fn(): array => dent_auth_load_dis_request_store(), 'DIS_REQUEST_STORE_SCHEMA_INVALID'],
        dent_grades_meta_path() => [static fn(): array => dent_read_grades_meta(), 'GRADES_META_SCHEMA_INVALID'],
    ] as $path => [$operation, $reasonCode]) {
        dent_ensure_directory(dirname($path));
        file_put_contents($path, '{}');
        $before = file_get_contents($path);
        try {
            $operation();
            throw new RuntimeException('schema-invalid identity/grade store was accepted');
        } catch (DentJsonPersistenceException $exception) {
            $assert($exception->reasonCode === $reasonCode, 'wrong identity/grade schema reason code');
        }
        $assert(file_get_contents($path) === $before, 'schema-invalid identity/grade store was overwritten');
    }
    echo "Atomic auxiliary store checks passed.\n";
} finally {
    dent_release_session_lock();
    $removeTree($root);
}
