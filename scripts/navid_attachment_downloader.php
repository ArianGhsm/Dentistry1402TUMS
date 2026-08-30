<?php
declare(strict_types=1);

/**
 * Stream an explicit attachment manifest from Navid with the encrypted local
 * session maintained by navid_service.php. Credentials and cookies are never
 * accepted on the command line or printed.
 *
 * Usage:
 *   php scripts/navid_attachment_downloader.php manifest.json output-dir
 */

require_once __DIR__ . '/../public_html/api/navid_service.php';

function navid_dl_fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}" . PHP_EOL);
    exit(1);
}

function navid_dl_safe_segment(string $value, string $fallback): string
{
    $value = trim(str_replace(["\0", '/', '\\'], '', $value));
    $value = preg_replace('/[<>:"|?*\x00-\x1F]+/u', ' ', $value) ?? '';
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    return $value !== '' ? $value : $fallback;
}

function navid_dl_filename_from_disposition(string $header): string
{
    if (preg_match("/filename\\*=UTF-8''([^;]+)/i", $header, $match)) {
        return rawurldecode(trim($match[1], " \t\"'"));
    }
    if (preg_match('/filename="([^"]+)"/i', $header, $match)) {
        return trim($match[1]);
    }
    if (preg_match('/filename=([^;]+)/i', $header, $match)) {
        return trim($match[1], " \t\"'");
    }
    return '';
}

function navid_dl_one(array $item, array $cookies, string $outputRoot): array
{
    $id = preg_replace('/[^0-9]/', '', (string) ($item['id'] ?? '')) ?? '';
    if ($id === '') {
        throw new RuntimeException('Manifest item is missing a numeric id');
    }
    $url = trim((string) ($item['url'] ?? ''));
    if (!preg_match('~^https://navid\.tums\.ac\.ir/filemanager/download/[0-9]+/?$~i', $url)) {
        throw new RuntimeException("Attachment {$id} has an invalid Navid URL");
    }
    $course = navid_dl_safe_segment((string) ($item['course'] ?? ''), 'course');
    $courseDir = rtrim($outputRoot, '/\\') . DIRECTORY_SEPARATOR . $course;
    if (!is_dir($courseDir) && !mkdir($courseDir, 0775, true) && !is_dir($courseDir)) {
        throw new RuntimeException("Cannot create output directory: {$courseDir}");
    }
    $partPath = $courseDir . DIRECTORY_SEPARATOR . ".{$id}.part";
    $handle = fopen($partPath, 'wb');
    if ($handle === false) {
        throw new RuntimeException("Cannot open temporary output for {$id}");
    }
    $contentDisposition = '';
    $contentType = '';
    $curl = curl_init($url);
    if ($curl === false) {
        fclose($handle);
        throw new RuntimeException("curl_init failed for {$id}");
    }
    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36',
        'Accept: */*',
        'Accept-Language: fa-IR,fa;q=0.9,en-US;q=0.7,en;q=0.6',
        'Referer: https://navid.tums.ac.ir/',
    ];
    $cookieHeader = navid_build_cookie_header($cookies);
    if ($cookieHeader !== '') {
        $headers[] = 'Cookie: ' . $cookieHeader;
    }
    curl_setopt_array($curl, [
        CURLOPT_FILE => $handle,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 25,
        CURLOPT_TIMEOUT => 900,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_ENCODING => '',
        CURLOPT_PROXY => '',
        CURLOPT_IPRESOLVE => defined('CURL_IPRESOLVE_V4') ? CURL_IPRESOLVE_V4 : 1,
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$contentDisposition, &$contentType): int {
            $trimmed = trim($line);
            if (stripos($trimmed, 'Content-Disposition:') === 0) {
                $contentDisposition = trim(substr($trimmed, strlen('Content-Disposition:')));
            } elseif (stripos($trimmed, 'Content-Type:') === 0) {
                $contentType = trim(substr($trimmed, strlen('Content-Type:')));
            }
            return strlen($line);
        },
    ]);
    $resolve = navid_curl_resolve_entries($url);
    if ($resolve !== [] && defined('CURLOPT_RESOLVE')) {
        curl_setopt($curl, CURLOPT_RESOLVE, $resolve);
    }
    if (defined('CURLSSLOPT_NO_REVOKE')) {
        @curl_setopt($curl, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NO_REVOKE);
    }
    $ok = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $effectiveUrl = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    curl_close($curl);
    fclose($handle);
    $size = is_file($partPath) ? (int) filesize($partPath) : 0;
    if ($ok !== true || $error !== '' || $status !== 200 || $size <= 0) {
        @unlink($partPath);
        throw new RuntimeException("Download {$id} failed (HTTP {$status}, {$error})");
    }
    if (stripos($effectiveUrl, '/account/login') !== false || stripos($contentType, 'text/html') !== false) {
        @unlink($partPath);
        throw new RuntimeException("Download {$id} reached a login/HTML response; refresh the Navid session");
    }
    $sourceName = navid_dl_filename_from_disposition($contentDisposition);
    if ($sourceName === '') {
        $sourceName = trim((string) ($item['name'] ?? ''));
    }
    $sourceName = navid_dl_safe_segment($sourceName, $id . '.bin');
    if (pathinfo($sourceName, PATHINFO_EXTENSION) === '') {
        $sourceName .= '.bin';
    }
    $finalPath = $courseDir . DIRECTORY_SEPARATOR . $sourceName;
    if (is_file($finalPath)) {
        $stem = pathinfo($sourceName, PATHINFO_FILENAME);
        $ext = pathinfo($sourceName, PATHINFO_EXTENSION);
        $finalPath = $courseDir . DIRECTORY_SEPARATOR . $stem . '-' . $id . ($ext !== '' ? '.' . $ext : '');
    }
    if (!rename($partPath, $finalPath)) {
        @unlink($partPath);
        throw new RuntimeException("Cannot finalize download {$id}");
    }
    return [
        'id' => $id,
        'course' => $course,
        'path' => $finalPath,
        'bytes' => $size,
        'sha256' => hash_file('sha256', $finalPath),
        'contentType' => $contentType,
    ];
}

if (PHP_SAPI !== 'cli') {
    navid_dl_fail('CLI only');
}
$manifestPath = isset($argv[1]) ? (string) $argv[1] : '';
$outputRoot = isset($argv[2]) ? (string) $argv[2] : '';
if ($manifestPath === '' || $outputRoot === '') {
    navid_dl_fail('Usage: php scripts/navid_attachment_downloader.php manifest.json output-dir');
}
$raw = @file_get_contents($manifestPath);
$manifest = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($manifest) || !is_array($manifest['items'] ?? null)) {
    navid_dl_fail('Invalid attachment manifest');
}
$store = navid_load_store();
$cookies = navid_get_session_cookies($store);
if ($cookies === []) {
    navid_dl_fail('No encrypted Navid session is stored; reconnect Navid first');
}
$outputRoot = rtrim($outputRoot, '/\\');
if (!is_dir($outputRoot) && !mkdir($outputRoot, 0775, true) && !is_dir($outputRoot)) {
    navid_dl_fail("Cannot create output root: {$outputRoot}");
}
$results = [];
$failures = [];
foreach ($manifest['items'] as $item) {
    try {
        $result = navid_dl_one(is_array($item) ? $item : [], $cookies, $outputRoot);
        $results[] = $result;
        fwrite(STDOUT, "OK {$result['id']} {$result['bytes']} {$result['path']}" . PHP_EOL);
    } catch (Throwable $error) {
        $failures[] = ['id' => (string) ($item['id'] ?? ''), 'error' => $error->getMessage()];
        fwrite(STDERR, "FAIL " . (string) ($item['id'] ?? '') . ' ' . $error->getMessage() . PHP_EOL);
    }
}
$summaryPath = $outputRoot . DIRECTORY_SEPARATOR . 'download-summary.json';
file_put_contents($summaryPath, json_encode([
    'downloaded' => $results,
    'failures' => $failures,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
fwrite(STDOUT, "SUMMARY {$summaryPath}" . PHP_EOL);
exit($failures === [] ? 0 : 2);
