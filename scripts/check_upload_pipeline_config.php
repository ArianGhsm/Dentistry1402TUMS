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

function jsHasRawBodyUpload(string $contents, string $headerName, string $sendPattern): bool
{
    if (strpos($contents, $headerName) === false) {
        return false;
    }

    return preg_match($sendPattern, $contents) === 1;
}

function jsHasMultipartUpload(string $contents): bool
{
    if (strpos($contents, 'new FormData()') === false) {
        return false;
    }
    if (strpos($contents, 'formData.append("file"') === false) {
        return false;
    }

    return preg_match('/xhr\.send\(\s*uploadBody\s*\)/', $contents) === 1
        || preg_match('/return\s+formData\s*;/', $contents) === 1;
}

function jsHasNotesUploadTransport(string $contents, string $headerName, string $sendPattern): bool
{
    return jsHasRawBodyUpload($contents, $headerName, $sendPattern)
        || jsHasMultipartUpload($contents);
}

$publicIniPath = $projectRoot . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . '.user.ini';
$apiIniPath = $projectRoot . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . '.user.ini';
$storePath = $projectRoot . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'content_tools_store.php';
$downloadHostPath = $projectRoot . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'notes_download_host.php';
$contentToolsApiPath = $projectRoot . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'content_tools_api.php';
$contentToolsDownloadHostPath = $projectRoot . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'content_tools_download_host.php';
$notesApiPath = $projectRoot . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'notes_api.php';
$contentToolsFilesJsPath = $projectRoot . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'content-tools-files.js';
$notesFilesJsPath = $projectRoot . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'notes-files.js';
$notesHostPickerJsPath = $projectRoot . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'notes-host-picker.js';
$notesTermJsPath = $projectRoot . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'notes-term.js';

foreach ([
    $publicIniPath,
    $apiIniPath,
    $storePath,
    $downloadHostPath,
    $contentToolsApiPath,
    $contentToolsDownloadHostPath,
    $notesApiPath,
    $contentToolsFilesJsPath,
    $notesFilesJsPath,
    $notesHostPickerJsPath,
    $notesTermJsPath,
] as $requiredPath) {
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

    if (strpos($downloadHostContents, 'function notes_download_host_stream_upload_from_stream(') === false) {
        $errors[] = 'notes_download_host_stream_upload_from_stream helper is missing.';
    }
    if (strpos($downloadHostContents, 'function notes_download_host_stream_chunk_to_ftp(') === false
        || strpos($downloadHostContents, 'ftp_fput(') === false
        || strpos($downloadHostContents, "'action' => 'assemble'") === false
        || strpos($downloadHostContents, "'.part-'") === false) {
        $errors[] = 'bounded raw chunk to FTP streaming helper is missing.';
    }

    $contentToolsDownloadHostContents = (string) file_get_contents($contentToolsDownloadHostPath);
    if (strpos($contentToolsDownloadHostContents, 'function content_download_host_upload_stream(') === false) {
        $errors[] = 'content_download_host_upload_stream helper is missing.';
    }

    $contentToolsApiContents = (string) file_get_contents($contentToolsApiPath);
    if (strpos($contentToolsApiContents, "notes_download_host_request_header('X-Dent-Upload-Meta')") === false) {
        $errors[] = 'content_tools_api raw upload header handling is missing.';
    }

    $notesApiContents = (string) file_get_contents($notesApiPath);
    if (strpos($notesApiContents, "notes_download_host_request_header('X-Dent-Upload-Name')") === false) {
        $errors[] = 'notes_api raw upload header handling is missing.';
    }
    if (strpos($notesApiContents, "if (\$action === 'streamHostUploadChunk')") === false
        || strpos($notesApiContents, "'mode' => 'stream'") === false
        || strpos($notesApiContents, "'transport' => 'raw-chunk-to-ftp'") === false
        || strpos($notesApiContents, "notes_download_host_request_header('X-Dent-Chunk-Encoding')") === false
        || strpos($notesApiContents, "dent_base64url_decode(trim(\$encoded))") === false) {
        $errors[] = 'notes_api is not configured for bounded stream-to-FTP upload plans.';
    }

    $contentToolsFilesJsContents = (string) file_get_contents($contentToolsFilesJsPath);
    if (!jsHasRawBodyUpload($contentToolsFilesJsContents, 'X-Dent-Upload-Meta', '/xhr\.send\(\s*item\.file\s*\)/')) {
        $errors[] = 'content-tools-files.js is not configured for raw body upload.';
    }
    if (strpos($contentToolsFilesJsContents, 'return uploadItemDirect(item);') === false
        || strpos($contentToolsFilesJsContents, 'chunkStart=') === false
        || strpos($contentToolsFilesJsContents, 'chunkEnd=') === false
        || strpos($contentToolsFilesJsContents, 'X-Dent-Chunk-Encoding') === false
        || strpos($contentToolsFilesJsContents, 'base64url') === false) {
        $errors[] = 'content-tools-files.js can still bypass the bounded chunk stream transport.';
    }

    $notesFilesJsContents = (string) file_get_contents($notesFilesJsPath);
    if (!jsHasNotesUploadTransport($notesFilesJsContents, 'X-Dent-Upload-Name', '/xhr\.send\(\s*(?:item\.file|uploadBody)\s*\)/')) {
        $errors[] = 'notes-files.js is not configured for notes upload transport.';
    }
    if (strpos($notesFilesJsContents, 'planMode === "stream"') === false
        || strpos($notesFilesJsContents, 'chunkStart=') === false
        || strpos($notesFilesJsContents, 'chunkEnd=') === false
        || strpos($notesFilesJsContents, 'X-Dent-Chunk-Encoding') === false
        || strpos($notesFilesJsContents, 'base64url') === false) {
        $errors[] = 'notes-files.js is not using the bounded chunk stream plan.';
    }

    $notesHostPickerJsContents = (string) file_get_contents($notesHostPickerJsPath);
    $notesHostPickerHasUploadTransport = jsHasNotesUploadTransport(
        $notesHostPickerJsContents,
        'X-Dent-Upload-Name',
        '/xhr\.send\(\s*(?:file|task\.file|uploadBody)\s*\)/'
    );
    if (!$notesHostPickerHasUploadTransport) {
        $errors[] = 'notes-host-picker.js is not configured for notes upload transport.';
    }
    if (strpos($notesHostPickerJsContents, 'function sendStreamChunks(') === false
        || strpos($notesHostPickerJsContents, 'chunkStart=') === false
        || strpos($notesHostPickerJsContents, 'chunkEnd=') === false
        || strpos($notesHostPickerJsContents, 'X-Dent-Chunk-Encoding') === false
        || strpos($notesHostPickerJsContents, 'base64url') === false) {
        $errors[] = 'notes-host-picker.js is not using the bounded chunk stream plan.';
    }

    $notesTermJsContents = (string) file_get_contents($notesTermJsPath);
    $notesTermUsesUploadTransport = jsHasNotesUploadTransport(
        $notesTermJsContents,
        'X-Dent-Upload-Name',
        '/xhr\.send\(\s*(?:file|task\.file|uploadBody)\s*\)/'
    );
    $notesTermDelegatesToHostPicker = strpos($notesTermJsContents, 'Dent1402NotesHostPicker') !== false
        && strpos($notesTermJsContents, '.create({') !== false;
    if (!$notesTermUsesUploadTransport && !($notesTermDelegatesToHostPicker && $notesHostPickerHasUploadTransport)) {
        $errors[] = 'notes-term.js is not configured for notes upload transport.';
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
