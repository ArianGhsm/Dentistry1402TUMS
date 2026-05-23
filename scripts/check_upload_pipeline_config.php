<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$errors = [];

function parseIniValue(string $path): array
{
    $data = parse_ini_file($path, false, INI_SCANNER_RAW);
    return is_array($data) ? $data : [];
}

function parseSizeToBytes(string $raw): ?int
{
    $value = trim($raw);
    if ($value === '') {
        return null;
    }
    if (!preg_match('/^(\d+)([KMG])?$/i', $value, $matches)) {
        return null;
    }
    $number = (int) $matches[1];
    $unit = strtoupper((string) ($matches[2] ?? ''));
    if ($unit === 'K') {
        return $number * 1024;
    }
    if ($unit === 'M') {
        return $number * 1024 * 1024;
    }
    if ($unit === 'G') {
        return $number * 1024 * 1024 * 1024;
    }
    return $number;
}

function requireRegexValue(string $contents, string $pattern, string $label, array &$errors): ?int
{
    if (preg_match($pattern, $contents, $matches) !== 1) {
        $errors[] = $label . ' not found.';
        return null;
    }
    return isset($matches[1]) ? (int) $matches[1] : null;
}

$publicIniPath = $projectRoot . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . '.user.ini';
$apiIniPath = $projectRoot . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . '.user.ini';
$storePath = $projectRoot . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'content_tools_store.php';
$downloadHostPath = $projectRoot . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'notes_download_host.php';

foreach ([$publicIniPath, $apiIniPath, $storePath, $downloadHostPath] as $requiredPath) {
    if (!is_file($requiredPath)) {
        $errors[] = 'Missing required file: ' . $requiredPath;
    }
}

if ($errors === []) {
    $publicIni = parseIniValue($publicIniPath);
    $apiIni = parseIniValue($apiIniPath);

    $minimumUploadBytes = 20 * 1024 * 1024 * 1024;
    $minimumLongTimeout = 14400;

    $publicUploadMax = parseSizeToBytes((string) ($publicIni['upload_max_filesize'] ?? ''));
    $publicPostMax = parseSizeToBytes((string) ($publicIni['post_max_size'] ?? ''));
    if (($publicUploadMax ?? 0) < $minimumUploadBytes) {
        $errors[] = 'public_html/.user.ini upload_max_filesize is below 20G.';
    }
    if (($publicPostMax ?? 0) < $minimumUploadBytes) {
        $errors[] = 'public_html/.user.ini post_max_size is below 20G.';
    }
    if (($publicPostMax ?? 0) < ($publicUploadMax ?? 0)) {
        $errors[] = 'public_html/.user.ini post_max_size is smaller than upload_max_filesize.';
    }

    foreach (['max_input_time', 'max_execution_time', 'default_socket_timeout'] as $timeoutKey) {
        $rawValue = trim((string) ($publicIni[$timeoutKey] ?? ''));
        $value = ctype_digit($rawValue) ? (int) $rawValue : -1;
        if ($value !== 0 && $value < $minimumLongTimeout) {
            $errors[] = 'public_html/.user.ini ' . $timeoutKey . ' is below ' . $minimumLongTimeout . '.';
        }
    }

    foreach (['upload_max_filesize', 'post_max_size', 'max_input_time', 'max_execution_time', 'default_socket_timeout'] as $requiredKey) {
        if (!array_key_exists($requiredKey, $apiIni)) {
            $errors[] = 'public_html/api/.user.ini is missing ' . $requiredKey . '.';
        }
    }

    $storeContents = (string) file_get_contents($storePath);
    $uploadGigabytes = requireRegexValue(
        $storeContents,
        '/const\s+CONTENT_MAX_UPLOAD_BYTES\s*=\s*(\d+)\s*\*\s*1024\s*\*\s*1024\s*\*\s*1024\s*;/',
        'CONTENT_MAX_UPLOAD_BYTES',
        $errors
    );
    if (($uploadGigabytes ?? 0) < 20) {
        $errors[] = 'CONTENT_MAX_UPLOAD_BYTES is below 20 GiB.';
    }
    if (strpos($storeContents, "const CONTENT_MAX_UPLOAD_LABEL = '۲۰ گیگابایت';") === false) {
        $errors[] = 'CONTENT_MAX_UPLOAD_LABEL is not aligned with 20 GiB.';
    }

    $downloadHostContents = (string) file_get_contents($downloadHostPath);
    $streamTimeout = requireRegexValue(
        $downloadHostContents,
        '/const\s+NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS\s*=\s*(\d+)\s*;/',
        'NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS',
        $errors
    );
    if (($streamTimeout ?? 0) < $minimumLongTimeout) {
        $errors[] = 'NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS is below ' . $minimumLongTimeout . '.';
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Upload pipeline config check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, " - " . $error . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Upload pipeline config OK.\n");
