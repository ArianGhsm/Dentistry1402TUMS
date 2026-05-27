<?php
declare(strict_types=1);

const NOTES_DOWNLOAD_HOST_ALLOWED_ROOTS = ['1402', '1403', '1404', 'prosthesis-1402'];
const NOTES_DOWNLOAD_HOST_SECRET_FILE = 'mihan_download_host.json';
const NOTES_DOWNLOAD_HOST_STREAM_CONNECT_TIMEOUT_SECONDS = 300;
const NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS = 14400;
const NOTES_DOWNLOAD_HOST_STREAM_CHUNK_BYTES = 4 * 1024 * 1024;

function notes_download_host_request_header(string $name): string
{
    $normalized = strtoupper(str_replace('-', '_', trim($name)));
    if ($normalized === '') {
        return '';
    }

    if ($normalized === 'CONTENT_TYPE' && isset($_SERVER['CONTENT_TYPE'])) {
        return trim((string) $_SERVER['CONTENT_TYPE']);
    }
    if ($normalized === 'CONTENT_LENGTH' && isset($_SERVER['CONTENT_LENGTH'])) {
        return trim((string) $_SERVER['CONTENT_LENGTH']);
    }

    $serverKey = str_starts_with($normalized, 'HTTP_') ? $normalized : ('HTTP_' . $normalized);
    if (isset($_SERVER[$serverKey])) {
        return trim((string) $_SERVER[$serverKey]);
    }

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $headerName => $headerValue) {
                if (strtoupper(str_replace('-', '_', (string) $headerName)) !== $normalized) {
                    continue;
                }
                return trim((string) $headerValue);
            }
        }
    }

    return '';
}

function notes_download_host_decode_header_value(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }

    return dent_force_utf8(rawurldecode($trimmed));
}

function notes_download_host_allowed_roots(): array
{
    return NOTES_DOWNLOAD_HOST_ALLOWED_ROOTS;
}

function notes_download_host_secret_candidates(): array
{
    $candidates = [];

    $explicit = trim(dent_env_value('NOTES_DOWNLOAD_HOST_SECRET'));
    if ($explicit !== '') {
        $candidates[] = dent_resolve_path($explicit, DENT_PROJECT_ROOT);
    }

    $candidates[] = DENT_SECRETS_ROOT . DIRECTORY_SEPARATOR . NOTES_DOWNLOAD_HOST_SECRET_FILE;
    $candidates[] = DENT_PROJECT_ROOT . DIRECTORY_SEPARATOR . '.codex-local' . DIRECTORY_SEPARATOR . 'mihan-download-host.json';

    return array_values(array_unique(array_filter($candidates, static function ($path): bool {
        return is_string($path) && trim($path) !== '';
    })));
}

function notes_download_host_load_secret(): ?array
{
    static $cached = false;
    static $secret = null;

    if ($cached) {
        return $secret;
    }
    $cached = true;

    foreach (notes_download_host_secret_candidates() as $path) {
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            continue;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            continue;
        }

        $host = trim((string) ($decoded['cpanelHost'] ?? $decoded['ftpHost'] ?? $decoded['domain'] ?? ''));
        $username = trim((string) ($decoded['username'] ?? ''));
        $password = trim((string) ($decoded['password'] ?? ''));
        $publicDomain = trim((string) ($decoded['publicDomain'] ?? ''));
        $remoteBaseDir = trim((string) ($decoded['remoteBaseDir'] ?? 'public_html'));
        if ($host === '' || $username === '' || $password === '' || $publicDomain === '' || $remoteBaseDir === '') {
            continue;
        }

        $secret = [
            'host' => $host,
            'username' => $username,
            'password' => $password,
            'publicDomain' => $publicDomain,
            'remoteBaseDir' => trim(str_replace('\\', '/', $remoteBaseDir), '/'),
            'scheme' => str_starts_with((string) ($decoded['cpanelBaseUrl'] ?? ''), 'https://') || !empty($decoded['cpanelSchemeHttps']) ? 'https' : 'http',
            'cpanelSecurePort' => max(1, (int) ($decoded['cpanelSecurePort'] ?? 2083)),
            'cpanelPort' => max(1, (int) ($decoded['cpanelPort'] ?? 2082)),
            'absoluteBaseDir' => trim(str_replace('\\', '/', (string) ($decoded['absoluteBaseDir'] ?? ''))),
        ];
        return $secret;
    }

    $host = trim(dent_env_value('NOTES_DOWNLOAD_HOST'));
    $username = trim(dent_env_value('NOTES_DOWNLOAD_HOST_USER'));
    $password = trim(dent_env_value('NOTES_DOWNLOAD_HOST_PASS'));
    $publicDomain = trim(dent_env_value('NOTES_DOWNLOAD_HOST_PUBLIC_DOMAIN'));
    $remoteBaseDir = trim(dent_env_value('NOTES_DOWNLOAD_HOST_REMOTE_BASE'));
    if ($remoteBaseDir === '') {
        $remoteBaseDir = 'public_html';
    }

    if ($host === '' || $username === '' || $password === '' || $publicDomain === '') {
        $secret = null;
        return null;
    }

    $secret = [
        'host' => $host,
        'username' => $username,
        'password' => $password,
        'publicDomain' => $publicDomain,
        'remoteBaseDir' => trim(str_replace('\\', '/', $remoteBaseDir), '/'),
        'scheme' => dent_env_value('NOTES_DOWNLOAD_HOST_SCHEME') === 'https' ? 'https' : 'http',
        'cpanelSecurePort' => max(1, (int) dent_env_value('NOTES_DOWNLOAD_HOST_SECURE_PORT') ?: 2083),
        'cpanelPort' => max(1, (int) dent_env_value('NOTES_DOWNLOAD_HOST_PORT') ?: 2082),
        'absoluteBaseDir' => trim(str_replace('\\', '/', dent_env_value('NOTES_DOWNLOAD_HOST_ABSOLUTE_BASE'))),
    ];

    return $secret;
}

function notes_download_host_is_enabled(): bool
{
    return is_array(notes_download_host_load_secret());
}

function notes_download_host_public_base_url(): string
{
    $secret = notes_download_host_load_secret();
    if (!is_array($secret)) {
        return '';
    }

    return 'https://' . trim((string) $secret['publicDomain'], '/');
}

function notes_download_host_prepare_long_transfer(): void
{
    @ignore_user_abort(true);
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    @ini_set('max_execution_time', '0');
    @ini_set('default_socket_timeout', (string) NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS);
    if (function_exists('dent_release_session_lock')) {
        dent_release_session_lock();
    }
}

function notes_download_host_socket_write_all($socket, string $payload, string $phaseLabel): void
{
    $offset = 0;
    $length = strlen($payload);
    while ($offset < $length) {
        $written = @fwrite($socket, substr($payload, $offset));
        if (!is_int($written) || $written <= 0) {
            $meta = is_resource($socket) ? stream_get_meta_data($socket) : [];
            if (!empty($meta['timed_out'])) {
                dent_error($phaseLabel . ' به‌خاطر timeout شبکه کامل نشد.', 504);
            }
            dent_error($phaseLabel . ' به‌خاطر قطع ارتباط شبکه کامل نشد.', 502);
        }
        $offset += $written;
    }
}

function notes_download_host_normalize_relative_path(string $path): string
{
    $normalized = trim(str_replace('\\', '/', $path));
    $normalized = preg_replace('#/+#', '/', $normalized) ?? '';
    $normalized = trim($normalized, '/');
    if ($normalized === '') {
        return '';
    }

    $segments = [];
    foreach (explode('/', $normalized) as $segment) {
        $segment = trim($segment);
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            dent_error('مسیر پوشه یا فایل معتبر نیست.', 422);
        }
        if (preg_match('/[\x00-\x1f]/u', $segment) === 1) {
            dent_error('نام پوشه یا فایل معتبر نیست.', 422);
        }
        $segments[] = $segment;
    }

    return implode('/', $segments);
}

function notes_download_host_root_of_relative_path(string $relativePath): string
{
    $normalized = notes_download_host_normalize_relative_path($relativePath);
    if ($normalized === '') {
        return '';
    }

    $parts = explode('/', $normalized, 2);
    return (string) ($parts[0] ?? '');
}

function notes_download_host_is_allowed_relative_path(string $relativePath, ?string $scopeRoot = null, bool $allowRoot = true): bool
{
    $normalized = notes_download_host_normalize_relative_path($relativePath);
    if ($normalized === '') {
        return $allowRoot && ($scopeRoot === null || $scopeRoot === '');
    }

    $root = notes_download_host_root_of_relative_path($normalized);
    if ($scopeRoot !== null && $scopeRoot !== '') {
        return $root === $scopeRoot;
    }

    return in_array($root, notes_download_host_allowed_roots(), true);
}

function notes_download_host_assert_allowed_relative_path(string $relativePath, ?string $scopeRoot = null, bool $allowRoot = true): string
{
    $normalized = notes_download_host_normalize_relative_path($relativePath);
    if (!notes_download_host_is_allowed_relative_path($normalized, $scopeRoot, $allowRoot)) {
        dent_error('این مسیر برای مدیریت منابع مجاز نیست.', 403);
    }

    return $normalized;
}

function notes_download_host_public_url(string $relativePath): string
{
    $base = notes_download_host_public_base_url();
    if ($base === '') {
        return '';
    }

    $normalized = notes_download_host_normalize_relative_path($relativePath);
    if ($normalized === '') {
        return $base . '/';
    }

    $segments = array_map(static function (string $segment): string {
        return rawurlencode($segment);
    }, explode('/', $normalized));

    return $base . '/' . implode('/', $segments);
}

function notes_download_host_visible_term_number_from_title(string $title, int $fallback): int
{
    $normalized = dent_normalize_digits($title);
    if (preg_match('/(\d+)/u', $normalized, $matches) === 1) {
        $value = (int) ($matches[1] ?? 0);
        if ($value > 0) {
            return $value;
        }
    }

    return max(1, $fallback);
}

function notes_download_host_default_relative_dir(string $cohort, int $term = 0, string $termTitle = ''): string
{
    if ($cohort === '1403' || $cohort === '1404') {
        return $cohort . '/term-' . str_pad((string) max(1, $term), 2, '0', STR_PAD_LEFT);
    }

    if ($cohort === 'prosthesis-1402') {
        $visibleTerm = notes_download_host_visible_term_number_from_title($termTitle, $term);
        return 'prosthesis-1402/term-' . str_pad((string) max(1, $visibleTerm), 2, '0', STR_PAD_LEFT);
    }

    return '1402/term-' . str_pad((string) max(1, $term), 2, '0', STR_PAD_LEFT);
}

function notes_download_host_http_headers(array $secret): array
{
    return [
        'Authorization: Basic ' . base64_encode((string) $secret['username'] . ':' . (string) $secret['password']),
        'Accept: application/json',
        'User-Agent: Dentistry1402TUMS-NotesDownloadHost/1.0',
        'Connection: close',
    ];
}

function notes_download_host_base_url(array $secret): string
{
    $scheme = (string) ($secret['scheme'] ?? 'http') === 'https' ? 'https' : 'http';
    $port = $scheme === 'https'
        ? (int) ($secret['cpanelSecurePort'] ?? 2083)
        : (int) ($secret['cpanelPort'] ?? 2082);

    return $scheme . '://' . $secret['host'] . ':' . $port;
}

function notes_download_host_http_request(string $method, string $url, array $headers = [], ?string $body = null): array
{
    $baseHeaders = [
        'ignore_errors' => true,
        'timeout' => 120,
        'protocol_version' => 1.1,
        'header' => implode("\r\n", $headers) . "\r\n",
        'method' => strtoupper($method),
    ];
    if ($body !== null) {
        $baseHeaders['content'] = $body;
    }

    $context = stream_context_create([
        'http' => $baseHeaders,
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'SNI_enabled' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    $responseHeaders = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];
    $status = 0;
    foreach ($responseHeaders as $line) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/i', (string) $line, $matches) === 1) {
            $status = (int) ($matches[1] ?? 0);
            break;
        }
    }

    if (!is_string($raw)) {
        $error = error_get_last();
        dent_error('اتصال به هاست دانلود برقرار نشد: ' . trim((string) ($error['message'] ?? 'خطای نامشخص')), 502);
    }

    return [
        'status' => $status,
        'body' => $raw,
        'headers' => $responseHeaders,
    ];
}

function notes_download_host_decode_json_response(array $response, string $fallbackMessage): array
{
    $decoded = json_decode((string) ($response['body'] ?? ''), true);
    if (!is_array($decoded)) {
        dent_error($fallbackMessage, 502);
    }

    return $decoded;
}

function notes_download_host_error_message_from_response(array $decoded, string $fallbackMessage = 'عملیات هاست دانلود ناموفق بود.'): string
{
    $errors = $decoded['errors'] ?? [];
    if (is_array($errors) && $errors !== []) {
        $parts = array_values(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, $errors), static function (string $value): bool {
            return $value !== '';
        }));
        if ($parts !== []) {
            return implode(' | ', $parts);
        }
    }

    $messages = $decoded['messages'] ?? [];
    if (is_array($messages) && $messages !== []) {
        $parts = array_values(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, $messages), static function (string $value): bool {
            return $value !== '';
        }));
        if ($parts !== []) {
            return implode(' | ', $parts);
        }
    }

    return $fallbackMessage;
}

function notes_download_host_is_missing_directory_error_message(string $message): bool
{
    $normalized = dent_utf8_strtolower(trim($message));
    if ($normalized === '') {
        return false;
    }

    $mentionsDirectory = strpos($normalized, 'directory') !== false
        || strpos($normalized, 'folder') !== false
        || strpos($normalized, 'path') !== false;
    if (!$mentionsDirectory) {
        return false;
    }

    return strpos($normalized, 'does not exist') !== false
        || strpos($normalized, 'not exist') !== false
        || strpos($normalized, 'not found') !== false
        || strpos($normalized, 'no such file') !== false
        || strpos($normalized, 'failed to opendir') !== false
        || strpos($normalized, 'cannot access') !== false
        || strpos($normalized, "can't access") !== false;
}

function notes_download_host_is_missing_directory_response(array $decoded): bool
{
    return notes_download_host_is_missing_directory_error_message(
        notes_download_host_error_message_from_response($decoded, '')
    );
}

function notes_download_host_execute_uapi(string $operation, array $query = [], bool $allowFailure = false): array
{
    $secret = notes_download_host_load_secret();
    if (!is_array($secret)) {
        dent_error('تنظیمات هاست دانلود روی سرور فعال نیست.', 503);
    }

    $url = notes_download_host_base_url($secret) . '/execute/' . ltrim($operation, '/');
    if ($query !== []) {
        $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    $response = notes_download_host_http_request('GET', $url, notes_download_host_http_headers($secret));
    $decoded = notes_download_host_decode_json_response($response, 'پاسخ نامعتبر از هاست دانلود دریافت شد.');
    if ((int) ($decoded['status'] ?? 0) !== 1 && !$allowFailure) {
        $message = notes_download_host_error_message_from_response($decoded);
        dent_error($message, 502);
    }

    return $decoded;
}

function notes_download_host_execute_api2(string $func, array $params = []): array
{
    $secret = notes_download_host_load_secret();
    if (!is_array($secret)) {
        dent_error('تنظیمات هاست دانلود روی سرور فعال نیست.', 503);
    }

    $query = array_merge([
        'cpanel_jsonapi_user' => (string) $secret['username'],
        'cpanel_jsonapi_apiversion' => '2',
        'cpanel_jsonapi_module' => 'Fileman',
        'cpanel_jsonapi_func' => $func,
    ], $params);

    $url = notes_download_host_base_url($secret) . '/json-api/cpanel?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $response = notes_download_host_http_request('GET', $url, notes_download_host_http_headers($secret));
    $decoded = notes_download_host_decode_json_response($response, 'پاسخ نامعتبر از پنل فایل هاست دانلود دریافت شد.');
    $result = is_array($decoded['cpanelresult'] ?? null) ? $decoded['cpanelresult'] : [];

    return $result;
}

function notes_download_host_absolute_base_dir(): string
{
    static $cached = null;
    if (is_string($cached) && $cached !== '') {
        return $cached;
    }

    $secret = notes_download_host_load_secret();
    if (!is_array($secret)) {
        dent_error('تنظیمات هاست دانلود روی سرور فعال نیست.', 503);
    }

    $configured = trim((string) ($secret['absoluteBaseDir'] ?? ''));
    if ($configured !== '') {
        $cached = rtrim(str_replace('\\', '/', $configured), '/');
        return $cached;
    }

    $response = notes_download_host_execute_uapi('Fileman/list_files', [
        'dir' => (string) $secret['remoteBaseDir'],
        'include_mime' => '0',
    ]);
    $items = is_array($response['data'] ?? null) ? $response['data'] : [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $absDir = trim((string) ($item['absdir'] ?? ''));
        if ($absDir !== '') {
            $cached = rtrim(str_replace('\\', '/', $absDir), '/');
            return $cached;
        }
    }

    dent_error('ریشه مطلق هاست دانلود قابل تشخیص نیست.', 502);
}

function notes_download_host_abs_path_from_relative(string $relativePath): string
{
    $base = notes_download_host_absolute_base_dir();
    $normalized = notes_download_host_normalize_relative_path($relativePath);
    if ($normalized === '') {
        return $base;
    }

    return $base . '/' . $normalized;
}

function notes_download_host_relative_from_abs_path(string $absolutePath): string
{
    $base = notes_download_host_absolute_base_dir();
    $normalized = rtrim(str_replace('\\', '/', trim($absolutePath)), '/');
    if ($normalized === $base) {
        return '';
    }
    if (!str_starts_with($normalized . '/', $base . '/')) {
        return '';
    }

    return notes_download_host_normalize_relative_path(substr($normalized, strlen($base) + 1));
}

function notes_download_host_sort_entries(array &$entries): void
{
    usort($entries, static function (array $left, array $right): int {
        $leftDir = (($left['type'] ?? '') === 'dir') ? 0 : 1;
        $rightDir = (($right['type'] ?? '') === 'dir') ? 0 : 1;
        if ($leftDir !== $rightDir) {
            return $leftDir <=> $rightDir;
        }

        return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
    });
}

function notes_download_host_human_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    $units = ['KB', 'MB', 'GB', 'TB'];
    $value = $bytes / 1024;
    foreach ($units as $index => $unit) {
        if ($value < 1024 || $index === count($units) - 1) {
            return number_format($value, $value < 10 ? 1 : 0, '.', ',') . ' ' . $unit;
        }
        $value /= 1024;
    }

    return number_format($value, 1, '.', ',') . ' TB';
}

function notes_download_host_entry_payload(array $item): array
{
    $name = trim((string) ($item['file'] ?? ''));
    $type = trim((string) ($item['type'] ?? 'file')) === 'dir' ? 'dir' : 'file';
    $parentAbs = rtrim(str_replace('\\', '/', trim((string) ($item['path'] ?? $item['absdir'] ?? ''))), '/');
    $parentRelative = notes_download_host_relative_from_abs_path($parentAbs);
    $relativePath = $parentRelative === '' ? $name : $parentRelative . '/' . $name;
    $bytes = max(0, (int) ($item['size'] ?? 0));
    $modifiedAt = (int) ($item['mtime'] ?? 0);

    return [
        'name' => $name,
        'type' => $type,
        'relativePath' => $relativePath,
        'parentPath' => $parentRelative,
        'sizeBytes' => $bytes,
        'sizeLabel' => $type === 'dir' ? 'پوشه' : notes_download_host_human_size($bytes),
        'mimeType' => (string) ($item['mimetype'] ?? ''),
        'modifiedAt' => $modifiedAt > 0 ? date(DATE_ATOM, $modifiedAt) : '',
        'publicUrl' => $type === 'file' ? notes_download_host_public_url($relativePath) : '',
    ];
}

function notes_download_host_root_listing(): array
{
    $response = notes_download_host_execute_uapi('Fileman/list_files', [
        'dir' => notes_download_host_absolute_base_dir(),
        'include_mime' => '0',
    ]);
    $items = is_array($response['data'] ?? null) ? $response['data'] : [];
    $byName = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim((string) ($item['file'] ?? ''));
        if ($name === '') {
            continue;
        }
        $byName[$name] = $item;
    }

    $entries = [];
    foreach (notes_download_host_allowed_roots() as $root) {
        if (isset($byName[$root]) && is_array($byName[$root])) {
            $entries[] = notes_download_host_entry_payload($byName[$root]);
            continue;
        }

        $entries[] = [
            'name' => $root,
            'type' => 'dir',
            'relativePath' => $root,
            'parentPath' => '',
            'sizeBytes' => 0,
            'sizeLabel' => 'پوشه آماده‌سازی نشده',
            'mimeType' => '',
            'modifiedAt' => '',
            'publicUrl' => '',
            'missing' => true,
        ];
    }

    return $entries;
}

function notes_download_host_breadcrumbs(string $relativePath): array
{
    $breadcrumbs = [
        [
            'label' => 'ریشه منابع',
            'path' => '',
        ],
    ];
    if ($relativePath === '') {
        return $breadcrumbs;
    }

    $parts = explode('/', $relativePath);
    $current = '';
    foreach ($parts as $part) {
        $current = $current === '' ? $part : $current . '/' . $part;
        $breadcrumbs[] = [
            'label' => $part,
            'path' => $current,
        ];
    }

    return $breadcrumbs;
}

function notes_download_host_browse(string $relativePath, ?string $scopeRoot = null): array
{
    $normalized = notes_download_host_assert_allowed_relative_path($relativePath, $scopeRoot, true);
    if ($normalized === '') {
        $entries = notes_download_host_root_listing();
        notes_download_host_sort_entries($entries);
        return [
            'currentPath' => '',
            'entries' => $entries,
            'breadcrumbs' => notes_download_host_breadcrumbs(''),
            'missingDirectory' => false,
        ];
    }

    $response = notes_download_host_execute_uapi('Fileman/list_files', [
        'dir' => notes_download_host_abs_path_from_relative($normalized),
        'include_mime' => '1',
    ], true);
    if ((int) ($response['status'] ?? 0) !== 1) {
        if (notes_download_host_is_missing_directory_response($response)) {
            return [
                'currentPath' => $normalized,
                'entries' => [],
                'breadcrumbs' => notes_download_host_breadcrumbs($normalized),
                'missingDirectory' => true,
                'notice' => 'این پوشه هنوز روی هاست دانلود ساخته نشده است. با اولین آپلود فایل یا ساخت زیرپوشه، مسیر به‌صورت خودکار ایجاد می‌شود.',
            ];
        }
        dent_error(notes_download_host_error_message_from_response($response), 502);
    }
    $items = is_array($response['data'] ?? null) ? $response['data'] : [];
    $entries = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $entries[] = notes_download_host_entry_payload($item);
    }
    notes_download_host_sort_entries($entries);

    return [
        'currentPath' => $normalized,
        'entries' => $entries,
        'breadcrumbs' => notes_download_host_breadcrumbs($normalized),
        'missingDirectory' => false,
    ];
}

function notes_download_host_sanitize_leaf_name(string $name, string $fallback): string
{
    $clean = preg_replace('/[\x00-\x1f<>:"\/\\\\|?*]+/u', ' ', trim($name)) ?? '';
    $clean = preg_replace('/\s+/u', ' ', $clean) ?? '';
    $clean = trim((string) $clean, ". \t\n\r\0\x0B");
    if ($clean === '' || $clean === '.' || $clean === '..') {
        return $fallback;
    }

    return $clean;
}

function notes_download_host_ensure_dir(string $relativeDir, ?string $scopeRoot = null): string
{
    $normalized = notes_download_host_assert_allowed_relative_path($relativeDir, $scopeRoot, false);
    $parts = explode('/', $normalized);
    $currentRelative = '';
    $currentAbs = notes_download_host_absolute_base_dir();
    foreach ($parts as $part) {
        $part = notes_download_host_sanitize_leaf_name($part, 'folder');
        $result = notes_download_host_execute_api2('mkdir', [
            'path' => $currentAbs,
            'name' => $part,
            'permissions' => '0755',
        ]);
        $error = trim((string) ($result['error'] ?? ''));
        if ($error !== '' && stripos($error, 'file exists') === false) {
            dent_error('ساخت پوشه روی هاست دانلود انجام نشد: ' . $error, 502);
        }
        $currentRelative = $currentRelative === '' ? $part : $currentRelative . '/' . $part;
        $currentAbs = notes_download_host_abs_path_from_relative($currentRelative);
    }

    return notes_download_host_abs_path_from_relative($normalized);
}

function notes_download_host_file_names_in_dir(string $relativeDir): array
{
    $browse = notes_download_host_browse($relativeDir);
    $names = [];
    foreach (($browse['entries'] ?? []) as $entry) {
        if (($entry['type'] ?? '') !== 'file') {
            continue;
        }
        $names[dent_utf8_strtolower((string) ($entry['name'] ?? ''))] = true;
    }

    return $names;
}

function notes_download_host_unique_file_name(string $relativeDir, string $name): string
{
    $name = notes_download_host_sanitize_leaf_name($name, 'file');
    $extension = pathinfo($name, PATHINFO_EXTENSION);
    $baseName = $extension !== '' ? substr($name, 0, -1 * (strlen($extension) + 1)) : $name;
    $baseName = notes_download_host_sanitize_leaf_name($baseName, 'file');
    $candidate = $extension !== '' ? ($baseName . '.' . $extension) : $baseName;

    $existing = notes_download_host_file_names_in_dir($relativeDir);
    $index = 2;
    $lower = dent_utf8_strtolower($candidate);
    while (isset($existing[$lower])) {
        $candidate = $extension !== ''
            ? ($baseName . ' (' . $index . ').' . $extension)
            : ($baseName . ' (' . $index . ')');
        $lower = dent_utf8_strtolower($candidate);
        $index++;
    }

    return $candidate;
}

function notes_download_host_parse_upload_response(string $raw): array
{
    if (preg_match("/\r\n\r\n(.*)\$/s", $raw, $matches) !== 1) {
        dent_error('پاسخ آپلود از هاست دانلود معتبر نبود.', 502);
    }

    $body = trim((string) ($matches[1] ?? ''));
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        dent_error('پاسخ JSON آپلود از هاست دانلود معتبر نبود.', 502);
    }
    if ((int) ($decoded['status'] ?? 0) !== 1) {
        $errors = $decoded['errors'] ?? [];
        $message = is_array($errors) && $errors !== [] ? implode(' | ', array_map('strval', $errors)) : 'آپلود فایل روی هاست دانلود ناموفق بود.';
        dent_error($message, 502);
    }

    $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
    $uploads = is_array($data['uploads'] ?? null) ? $data['uploads'] : [];
    if ($uploads === []) {
        dent_error('اطلاعات فایل آپلودشده از هاست دانلود دریافت نشد.', 502);
    }
    $upload = is_array($uploads[0] ?? null) ? $uploads[0] : [];
    if ((int) ($upload['status'] ?? 0) !== 1) {
        dent_error(trim((string) ($upload['reason'] ?? 'آپلود فایل روی هاست دانلود ناموفق بود.')), 502);
    }

    return $upload;
}

function notes_download_host_stream_upload_from_stream(string $targetAbsDir, $sourceStream, int $sourceSize, string $remoteName, string $mimeType): array
{
    notes_download_host_prepare_long_transfer();
    if (!is_resource($sourceStream)) {
        dent_error('جریان فایل برای آپلود روی هاست دانلود معتبر نیست.', 422);
    }
    if ($sourceSize <= 0) {
        dent_error('حجم فایل برای آپلود روی هاست دانلود معتبر نیست.', 422);
    }

    $secret = notes_download_host_load_secret();
    if (!is_array($secret)) {
        dent_error('تنظیمات هاست دانلود روی سرور فعال نیست.', 503);
    }

    $scheme = (string) ($secret['scheme'] ?? 'http') === 'https' ? 'https' : 'http';
    $socketPrefix = $scheme === 'https' ? 'ssl://' : 'tcp://';
    $socketPort = $scheme === 'https'
        ? (int) ($secret['cpanelSecurePort'] ?? 2083)
        : (int) ($secret['cpanelPort'] ?? 2082);

    $socket = @stream_socket_client(
        $socketPrefix . $secret['host'] . ':' . $socketPort,
        $errno,
        $errstr,
        NOTES_DOWNLOAD_HOST_STREAM_CONNECT_TIMEOUT_SECONDS,
        STREAM_CLIENT_CONNECT,
        stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'SNI_enabled' => true,
            ],
        ])
    );
    if (!is_resource($socket)) {
        dent_error('اتصال امن به هاست دانلود برقرار نشد: ' . trim($errstr), 502);
    }
    stream_set_timeout($socket, NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS);
    @stream_set_write_buffer($socket, 0);

    $boundary = '----DentNotesBoundary' . bin2hex(random_bytes(12));
    $prefix = '';
    $prefix .= '--' . $boundary . "\r\n";
    $prefix .= 'Content-Disposition: form-data; name="dir"' . "\r\n\r\n";
    $prefix .= $targetAbsDir . "\r\n";
    $prefix .= '--' . $boundary . "\r\n";
    $prefix .= 'Content-Disposition: form-data; name="file-1"; filename="' . addslashes($remoteName) . '"' . "\r\n";
    $prefix .= 'Content-Type: ' . ($mimeType !== '' ? $mimeType : 'application/octet-stream') . "\r\n\r\n";
    $suffix = "\r\n--" . $boundary . "--\r\n";
    $contentLength = strlen($prefix) + $sourceSize + strlen($suffix);

    $headers = [
        'POST /execute/Fileman/upload_files HTTP/1.1',
        'Host: ' . $secret['host'],
        'Authorization: Basic ' . base64_encode((string) $secret['username'] . ':' . (string) $secret['password']),
        'User-Agent: Dentistry1402TUMS-NotesDownloadHost/1.0',
        'Accept: application/json',
        'Content-Type: multipart/form-data; boundary=' . $boundary,
        'Content-Length: ' . $contentLength,
        'Connection: close',
        '',
        '',
    ];

    notes_download_host_socket_write_all($socket, implode("\r\n", $headers), 'ارسال هدر آپلود به هاست دانلود');
    notes_download_host_socket_write_all($socket, $prefix, 'شروع انتقال فایل به هاست دانلود');

    try {
        $remaining = $sourceSize;
        while ($remaining > 0) {
            $chunk = fread($sourceStream, min(NOTES_DOWNLOAD_HOST_STREAM_CHUNK_BYTES, $remaining));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('stream-read-failed');
            }
            $remaining -= strlen($chunk);
            notes_download_host_socket_write_all($socket, $chunk, 'ارسال فایل به هاست دانلود');
        }
    } catch (RuntimeException $error) {
        fclose($socket);
        dent_error('ارسال فایل به هاست دانلود کامل نشد.', 502);
    }

    notes_download_host_socket_write_all($socket, $suffix, 'پایان‌بندی آپلود روی هاست دانلود');

    stream_set_timeout($socket, NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS);
    $response = stream_get_contents($socket);
    $meta = stream_get_meta_data($socket);
    fclose($socket);
    if (!empty($meta['timed_out'])) {
        dent_error('پاسخ نهایی هاست دانلود برای این فایل در زمان مجاز نرسید. timeout سمت سرور یا شبکه را بررسی کنید.', 504);
    }
    if (!is_string($response) || trim($response) === '') {
        dent_error('پاسخ آپلود از هاست دانلود دریافت نشد.', 502);
    }

    return notes_download_host_parse_upload_response($response);
}

function notes_download_host_stream_upload(string $targetAbsDir, string $tmpPath, string $remoteName, string $mimeType): array
{
    $file = fopen($tmpPath, 'rb');
    if ($file === false) {
        dent_error('خواندن فایل آپلودی امکان‌پذیر نیست.', 422);
    }

    try {
        return notes_download_host_stream_upload_from_stream(
            $targetAbsDir,
            $file,
            max(0, (int) (filesize($tmpPath) ?: 0)),
            $remoteName,
            $mimeType
        );
    } finally {
        fclose($file);
    }

    notes_download_host_prepare_long_transfer();

    $secret = notes_download_host_load_secret();
    if (!is_array($secret)) {
        dent_error('تنظیمات هاست دانلود روی سرور فعال نیست.', 503);
    }

    $scheme = (string) ($secret['scheme'] ?? 'http') === 'https' ? 'https' : 'http';
    $socketPrefix = $scheme === 'https' ? 'ssl://' : 'tcp://';
    $socketPort = $scheme === 'https'
        ? (int) ($secret['cpanelSecurePort'] ?? 2083)
        : (int) ($secret['cpanelPort'] ?? 2082);

    $socket = @stream_socket_client(
        $socketPrefix . $secret['host'] . ':' . $socketPort,
        $errno,
        $errstr,
        NOTES_DOWNLOAD_HOST_STREAM_CONNECT_TIMEOUT_SECONDS,
        STREAM_CLIENT_CONNECT,
        stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'SNI_enabled' => true,
            ],
        ])
    );
    if (!is_resource($socket)) {
        dent_error('اتصال امن به هاست دانلود برقرار نشد: ' . trim($errstr), 502);
    }
    stream_set_timeout($socket, NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS);
    @stream_set_write_buffer($socket, 0);

    $boundary = '----DentNotesBoundary' . bin2hex(random_bytes(12));
    $prefix = '';
    $prefix .= '--' . $boundary . "\r\n";
    $prefix .= 'Content-Disposition: form-data; name="dir"' . "\r\n\r\n";
    $prefix .= $targetAbsDir . "\r\n";
    $prefix .= '--' . $boundary . "\r\n";
    $prefix .= 'Content-Disposition: form-data; name="file-1"; filename="' . addslashes($remoteName) . '"' . "\r\n";
    $prefix .= 'Content-Type: ' . ($mimeType !== '' ? $mimeType : 'application/octet-stream') . "\r\n\r\n";
    $suffix = "\r\n--" . $boundary . "--\r\n";
    $contentLength = strlen($prefix) + filesize($tmpPath) + strlen($suffix);

    $headers = [
        'POST /execute/Fileman/upload_files HTTP/1.1',
        'Host: ' . $secret['host'],
        'Authorization: Basic ' . base64_encode((string) $secret['username'] . ':' . (string) $secret['password']),
        'User-Agent: Dentistry1402TUMS-NotesDownloadHost/1.0',
        'Accept: application/json',
        'Content-Type: multipart/form-data; boundary=' . $boundary,
        'Content-Length: ' . $contentLength,
        'Connection: close',
        '',
        '',
    ];

    notes_download_host_socket_write_all($socket, implode("\r\n", $headers), 'ارسال هدر آپلود به هاست دانلود');
    notes_download_host_socket_write_all($socket, $prefix, 'شروع انتقال فایل به هاست دانلود');

    $file = fopen($tmpPath, 'rb');
    if ($file === false) {
        fclose($socket);
        dent_error('خواندن فایل آپلودی امکان‌پذیر نیست.', 422);
    }

    try {
        while (!feof($file)) {
            $chunk = fread($file, NOTES_DOWNLOAD_HOST_STREAM_CHUNK_BYTES);
            if ($chunk === false) {
                throw new RuntimeException('stream-read-failed');
            }
            if ($chunk !== '') {
                notes_download_host_socket_write_all($socket, $chunk, 'ارسال فایل به هاست دانلود');
            }
        }
    } catch (RuntimeException $error) {
        fclose($file);
        fclose($socket);
        dent_error('ارسال فایل به هاست دانلود کامل نشد.', 502);
    }

    fclose($file);
    notes_download_host_socket_write_all($socket, $suffix, 'پایان‌بندی آپلود روی هاست دانلود');

    stream_set_timeout($socket, NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS);
    $response = stream_get_contents($socket);
    $meta = stream_get_meta_data($socket);
    fclose($socket);
    if (!empty($meta['timed_out'])) {
        dent_error('پاسخ نهایی هاست دانلود برای این فایل در زمان مجاز نرسید. timeout سمت سرور یا شبکه را بررسی کنید.', 504);
    }
    if (!is_string($response) || trim($response) === '') {
        dent_error('پاسخ آپلود از هاست دانلود دریافت نشد.', 502);
    }

    return notes_download_host_parse_upload_response($response);
}

function notes_download_host_upload_stream(string $relativeDir, $sourceStream, int $sourceSize, string $desiredName = '', string $mimeType = '', ?string $scopeRoot = null): array
{
    if (!is_resource($sourceStream)) {
        dent_error('جریان فایل برای آپلود معتبر نیست.', 422);
    }
    if ($sourceSize <= 0) {
        dent_error('حجم فایل برای آپلود معتبر نیست.', 422);
    }

    $targetAbsDir = notes_download_host_ensure_dir($relativeDir, $scopeRoot);
    $finalName = notes_download_host_unique_file_name($relativeDir, $desiredName);
    $upload = notes_download_host_stream_upload_from_stream($targetAbsDir, $sourceStream, $sourceSize, $finalName, $mimeType);

    $relativePath = trim($relativeDir, '/') . '/' . $finalName;
    $relativePath = trim($relativePath, '/');
    $bytes = max(0, (int) ($upload['size'] ?? $sourceSize));

    return [
        'name' => $finalName,
        'relativeDir' => notes_download_host_normalize_relative_path($relativeDir),
        'relativePath' => $relativePath,
        'sizeBytes' => $bytes,
        'sizeLabel' => notes_download_host_human_size($bytes),
        'mimeType' => $mimeType,
        'publicUrl' => notes_download_host_public_url($relativePath),
        'message' => trim((string) ($upload['reason'] ?? 'فایل روی هاست دانلود ذخیره شد.')),
    ];
}

function notes_download_host_upload_file(string $relativeDir, array $file, string $desiredName = '', ?string $scopeRoot = null): array
{
    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        dent_error('فایل ارسالی معتبر نیست.', 422);
    }

    $originalName = trim((string) ($file['name'] ?? ''));
    $desiredName = $desiredName !== '' ? $desiredName : $originalName;
    $targetAbsDir = notes_download_host_ensure_dir($relativeDir, $scopeRoot);
    $finalName = notes_download_host_unique_file_name($relativeDir, $desiredName);
    $mimeType = trim((string) ($file['type'] ?? ''));
    $upload = notes_download_host_stream_upload($targetAbsDir, $tmpPath, $finalName, $mimeType);

    $relativePath = trim($relativeDir, '/') . '/' . $finalName;
    $relativePath = trim($relativePath, '/');
    $bytes = max(0, (int) ($upload['size'] ?? filesize($tmpPath) ?: 0));

    return [
        'name' => $finalName,
        'relativeDir' => notes_download_host_normalize_relative_path($relativeDir),
        'relativePath' => $relativePath,
        'sizeBytes' => $bytes,
        'sizeLabel' => notes_download_host_human_size($bytes),
        'mimeType' => $mimeType,
        'publicUrl' => notes_download_host_public_url($relativePath),
        'message' => trim((string) ($upload['reason'] ?? 'فایل روی هاست دانلود ذخیره شد.')),
    ];
}

function notes_download_host_create_dir(string $parentRelativePath, string $directoryName, ?string $scopeRoot = null): array
{
    $parentRelativePath = notes_download_host_assert_allowed_relative_path($parentRelativePath, $scopeRoot, true);
    $directoryName = notes_download_host_sanitize_leaf_name($directoryName, '');
    if ($directoryName === '') {
        dent_error('نام پوشه جدید معتبر نیست.', 422);
    }

    $parentAbs = $parentRelativePath === ''
        ? notes_download_host_absolute_base_dir()
        : notes_download_host_ensure_dir($parentRelativePath, $scopeRoot);
    $result = notes_download_host_execute_api2('mkdir', [
        'path' => $parentAbs,
        'name' => $directoryName,
        'permissions' => '0755',
    ]);
    $error = trim((string) ($result['error'] ?? ''));
    if ($error !== '' && stripos($error, 'file exists') === false) {
        dent_error('ساخت پوشه روی هاست دانلود انجام نشد: ' . $error, 502);
    }

    $relativePath = $parentRelativePath === '' ? $directoryName : $parentRelativePath . '/' . $directoryName;
    return [
        'name' => $directoryName,
        'type' => 'dir',
        'relativePath' => $relativePath,
        'parentPath' => $parentRelativePath,
    ];
}

function notes_download_host_rename_entry(string $relativePath, string $newName, ?string $scopeRoot = null): array
{
    $relativePath = notes_download_host_assert_allowed_relative_path($relativePath, $scopeRoot, false);
    $newName = notes_download_host_sanitize_leaf_name($newName, '');
    if ($newName === '') {
        dent_error('نام جدید معتبر نیست.', 422);
    }

    $parent = dirname($relativePath);
    $parent = $parent === '.' ? '' : notes_download_host_normalize_relative_path($parent);
    $destination = $parent === '' ? $newName : ($parent . '/' . $newName);
    $result = notes_download_host_execute_api2('fileop', [
        'op' => 'rename',
        'sourcefiles' => notes_download_host_abs_path_from_relative($relativePath),
        'destfiles' => notes_download_host_abs_path_from_relative($destination),
        'doubledecode' => '1',
    ]);

    $rows = is_array($result['data'] ?? null) ? $result['data'] : [];
    $row = is_array($rows[0] ?? null) ? $rows[0] : [];
    if ((int) ($row['result'] ?? 0) !== 1) {
        $error = trim((string) ($row['err'] ?? $result['error'] ?? 'تغییر نام انجام نشد.'));
        dent_error($error, 502);
    }

    return [
        'previousPath' => $relativePath,
        'relativePath' => $destination,
        'name' => $newName,
        'parentPath' => $parent,
    ];
}

function notes_download_host_delete_entry(string $relativePath, string $entryType, ?string $scopeRoot = null): array
{
    $relativePath = notes_download_host_assert_allowed_relative_path($relativePath, $scopeRoot, false);
    $type = $entryType === 'dir' ? 'dir' : 'file';
    $operation = $type === 'dir' ? 'trash' : 'unlink';

    $result = notes_download_host_execute_api2('fileop', [
        'op' => $operation,
        'sourcefiles' => notes_download_host_abs_path_from_relative($relativePath),
        'doubledecode' => '1',
    ]);

    $rows = is_array($result['data'] ?? null) ? $result['data'] : [];
    $row = is_array($rows[0] ?? null) ? $rows[0] : [];
    if ((int) ($row['result'] ?? 0) !== 1) {
        $error = trim((string) ($row['err'] ?? $result['error'] ?? 'حذف انجام نشد.'));
        dent_error($error, 502);
    }

    return [
        'relativePath' => $relativePath,
        'type' => $type,
        'deleteMode' => $operation,
    ];
}
