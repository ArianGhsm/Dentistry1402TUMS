<?php
declare(strict_types=1);

if (!defined('DENT_PROJECT_ROOT')) {
    define('DENT_PROJECT_ROOT', dirname(__DIR__, 2));
}

if (!defined('DENT_STORAGE_ROOT')) {
    define('DENT_STORAGE_ROOT', DENT_PROJECT_ROOT . DIRECTORY_SEPARATOR . 'storage');
}

date_default_timezone_set('Asia/Tehran');

function dent_bootstrap(): void
{
    static $booted = false;

    if ($booted) {
        return;
    }

    $booted = true;

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
        dent_error('????? ?????????? ???? ?????????? ???? ?????.', 500);
    }
}

function dent_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        dent_error('??? ?? ????? ???? JSON.', 500);
    }

    if (file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        dent_error('??? ?? ?????????? ???????.', 500);
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

function dent_normalize_digits(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    return str_replace(
        ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'],
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
    $value = trim((string) $value);
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

dent_bootstrap();
