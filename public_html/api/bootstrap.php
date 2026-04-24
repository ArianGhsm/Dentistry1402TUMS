<?php
declare(strict_types=1);

if (!defined('DENT_PROJECT_ROOT')) {
    define('DENT_PROJECT_ROOT', dirname(__DIR__, 2));
}

if (!defined('DENT_STORAGE_ROOT')) {
    define('DENT_STORAGE_ROOT', DENT_PROJECT_ROOT . DIRECTORY_SEPARATOR . 'storage');
}

date_default_timezone_set('Asia/Tehran');

function dent_load_env_files(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $paths = [];
    $explicit = getenv('DENT_ENV_FILE');
    if (is_string($explicit) && trim($explicit) !== '') {
        $paths[] = trim($explicit);
    }
    $paths[] = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    $paths[] = DENT_PROJECT_ROOT . DIRECTORY_SEPARATOR . '.env';
    $paths[] = DENT_STORAGE_ROOT . DIRECTORY_SEPARATOR . '.env';
    $paths[] = DENT_STORAGE_ROOT . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . '.env';
    $paths[] = DENT_STORAGE_ROOT . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'sms.env';

    foreach (array_unique($paths) as $path) {
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            continue;
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            if ($name === '' || preg_match('/^[A-Z_][A-Z0-9_]*$/', $name) !== 1) {
                continue;
            }
            if (getenv($name) !== false) {
                continue;
            }

            $value = trim($value);
            if (
                strlen($value) >= 2 &&
                (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

function dent_bootstrap(): void
{
    static $booted = false;

    if ($booted) {
        return;
    }

    $booted = true;
    dent_load_env_files();

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    if (session_status() !== PHP_SESSION_ACTIVE) {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        session_name('dent1402_session');
        session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 30,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    set_exception_handler(static function (Throwable $exception): void {
        if (headers_sent()) {
            return;
        }

        dent_emit_fallback_json_error('خطای داخلی سرور رخ داد.', 500);
    });

    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if (!is_array($error)) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true)) {
            return;
        }

        if (headers_sent()) {
            return;
        }

        dent_emit_fallback_json_error('خطای داخلی سرور رخ داد.', 500);
    });
}

function dent_storage_path(string $relativePath): string
{
    $trimmed = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);

    return DENT_STORAGE_ROOT . DIRECTORY_SEPARATOR . $trimmed;
}

function dent_ensure_directory(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
        dent_error('امکان آماده‌سازی فضای ذخیره‌سازی وجود ندارد.', 500);
    }
}

function dent_json_response(array $payload, int $statusCode = 200): void
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($payload, $flags);
    if ($json === false) {
        $statusCode = 500;
        $json = json_encode([
            'success' => false,
            'error' => 'خطا در تولید پاسخ JSON سرور.',
        ], $flags);

        if ($json === false) {
            $json = '{"success":false,"error":"Server JSON encoding failed."}';
        }
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo $json;
    exit;
}

function dent_emit_fallback_json_error(string $message, int $statusCode = 500): void
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $json = json_encode([
        'success' => false,
        'error' => $message,
    ], $flags);

    if ($json === false) {
        $json = '{"success":false,"error":"Server error."}';
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo $json;
    exit;
}

function dent_error(string $message, int $statusCode = 400, array $extra = []): void
{
    dent_json_response(array_merge([
        'success' => false,
        'error' => $message,
    ], $extra), $statusCode);
}

function dent_read_json_file(string $path, $default)
{
    if (!file_exists($path)) {
        return $default;
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return $default;
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $default;
    }

    return $decoded;
}

function dent_write_json_file(string $path, $payload): void
{
    dent_ensure_directory(dirname($path));

    $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $json = json_encode($payload, $flags);
    if ($json === false) {
        dent_error('خطا در تولید داده JSON.', 500);
    }

    if (file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        dent_error('خطا در ذخیره‌سازی داده‌ها.', 500);
    }
}

function dent_request_action(): string
{
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    return trim((string) $action);
}

function dent_request_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function dent_release_session_lock(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

function dent_normalize_digits(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    return str_replace(
        ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
        $value
    );
}

function dent_normalize_student_number(?string $value): string
{
    return preg_replace('/\D+/u', '', dent_normalize_digits($value)) ?? '';
}

function dent_iso_now(): string
{
    return date('c');
}

function dent_clean_text(?string $value, int $maxLength): string
{
    $value = trim(dent_force_utf8((string) $value));
    $value = preg_replace('/\r\n|\r/u', "\n", $value) ?? '';
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

    if ($value === '') {
        return '';
    }

    if (mb_strlen($value, 'UTF-8') > $maxLength) {
        $value = mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    return trim($value);
}

function dent_force_utf8(?string $value): string
{
    $value = (string) $value;
    if ($value === '') {
        return '';
    }

    if (preg_match('//u', $value) === 1) {
        return $value;
    }

    $encodings = ['Windows-1256', 'CP1256', 'Windows-1252', 'ISO-8859-1'];

    if (function_exists('iconv')) {
        foreach ($encodings as $encoding) {
            $converted = @iconv($encoding, 'UTF-8//IGNORE', $value);
            if (is_string($converted) && $converted !== '' && preg_match('//u', $converted) === 1) {
                return $converted;
            }
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }
    }

    if (function_exists('mb_convert_encoding')) {
        $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8,Windows-1256,CP1256,Windows-1252,ISO-8859-1');
        if (is_string($converted) && $converted !== '' && preg_match('//u', $converted) === 1) {
            return $converted;
        }
    }

    return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $value) ?? '';
}

dent_bootstrap();
