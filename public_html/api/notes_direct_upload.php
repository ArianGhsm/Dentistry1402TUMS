<?php
declare(strict_types=1);

// Direct-upload session and host-upload orchestration for notes resources.

function notes_direct_upload_store_path(): string
{
    return dent_storage_path('notes/direct_upload_sessions.json');
}

function notes_direct_upload_lock_path(): string
{
    return dent_storage_path('notes/direct_upload_sessions.lock');
}

function notes_direct_upload_default_store(): array
{
    return [
        'schemaVersion' => NOTES_DIRECT_UPLOAD_SCHEMA_VERSION,
        'sessions' => [],
    ];
}

function notes_direct_upload_clean_token($value): string
{
    $token = trim(strtolower((string) $value));
    return preg_match('/^[a-z0-9_-]{16,200}$/', $token) === 1 ? $token : '';
}

function notes_direct_upload_next_token(int $bytes = 18): string
{
    try {
        return strtolower(dent_base64url_encode(random_bytes($bytes)));
    } catch (Throwable $error) {
        return strtolower(hash('sha256', microtime(true) . '|' . mt_rand() . '|' . uniqid('', true)));
    }
}

function notes_direct_upload_clean_status($value): string
{
    $status = trim(strtolower((string) $value));
    return in_array($status, ['prepared', 'resolved', 'completed'], true) ? $status : 'prepared';
}

function notes_direct_upload_ensure_storage(): void
{
    dent_ensure_directory(dirname(notes_direct_upload_store_path()));
    if (!is_file(notes_direct_upload_store_path())) {
        dent_write_json_file(notes_direct_upload_store_path(), notes_direct_upload_default_store());
    }
}

function notes_direct_upload_normalize_store(array $store): array
{
    $now = time();
    $sessions = [];
    foreach (($store['sessions'] ?? []) as $key => $value) {
        if (!is_array($value)) {
            continue;
        }

        $token = notes_direct_upload_clean_token($value['token'] ?? $key);
        if ($token === '') {
            continue;
        }

        $status = notes_direct_upload_clean_status($value['status'] ?? 'prepared');
        $createdAt = trim((string) ($value['createdAt'] ?? ''));
        $updatedAt = trim((string) ($value['updatedAt'] ?? $createdAt));
        $expiresAt = trim((string) ($value['expiresAt'] ?? ''));
        $expiresUnix = $expiresAt !== '' ? (int) strtotime($expiresAt) : 0;
        $updatedUnix = $updatedAt !== '' ? (int) strtotime($updatedAt) : 0;

        if ($expiresUnix > 0 && $expiresUnix < ($now - 300)) {
            continue;
        }
        if ($status === 'completed' && $updatedUnix > 0 && ($now - $updatedUnix) > NOTES_DIRECT_UPLOAD_COMPLETED_TTL_SECONDS) {
            continue;
        }

        $sessions[$token] = [
            'token' => $token,
            'status' => $status,
            'cohort' => trim((string) ($value['cohort'] ?? '1402')),
            'scopeRoot' => trim((string) ($value['scopeRoot'] ?? '')),
            'relativeDir' => trim((string) ($value['relativeDir'] ?? '')),
            'relativePath' => trim((string) ($value['relativePath'] ?? '')),
            'finalName' => trim((string) ($value['finalName'] ?? '')),
            'mimeType' => trim((string) ($value['mimeType'] ?? '')),
            'expectedSize' => max(0, (int) ($value['expectedSize'] ?? 0)),
            'createdAt' => $createdAt !== '' ? $createdAt : dent_iso_now(),
            'updatedAt' => $updatedAt !== '' ? $updatedAt : dent_iso_now(),
            'expiresAt' => $expiresAt !== '' ? $expiresAt : date('c', $now + NOTES_DIRECT_UPLOAD_SESSION_TTL_SECONDS),
            'sessionKey' => notes_direct_upload_clean_token($value['sessionKey'] ?? ''),
            'origin' => trim((string) ($value['origin'] ?? '')),
            'gatewayVersion' => trim((string) ($value['gatewayVersion'] ?? '')),
            'contentType' => trim((string) ($value['contentType'] ?? '')),
            'contentLength' => max(0, (int) ($value['contentLength'] ?? 0)),
            'completedAt' => trim((string) ($value['completedAt'] ?? '')),
            'file' => is_array($value['file'] ?? null) ? $value['file'] : null,
        ];
    }

    return [
        'schemaVersion' => NOTES_DIRECT_UPLOAD_SCHEMA_VERSION,
        'sessions' => $sessions,
    ];
}

function notes_direct_upload_load_store_unlocked(): array
{
    notes_direct_upload_ensure_storage();
    $raw = dent_read_json_file(notes_direct_upload_store_path(), notes_direct_upload_default_store());
    if (!isset($raw['sessions']) || !is_array($raw['sessions'])) {
        throw new DentJsonPersistenceException(
            'NOTES_DIRECT_UPLOAD_STORE_SCHEMA_INVALID',
            'Existing direct-upload session store has an invalid schema'
        );
    }

    return notes_direct_upload_normalize_store($raw);
}

/**
 * @template T
 * @param callable(array):T $callback
 * @return T
 */
function notes_direct_upload_with_store_lock(callable $callback)
{
    notes_direct_upload_ensure_storage();
    $lock = fopen(notes_direct_upload_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('قفل آپلود مستقیم منابع در دسترس نیست.', 500);
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            dent_error('قفل آپلود مستقیم منابع آماده نشد.', 500);
        }
        $store = notes_direct_upload_load_store_unlocked();
        $result = $callback($store);
        dent_write_json_file(notes_direct_upload_store_path(), notes_direct_upload_normalize_store($store));
        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function notes_direct_upload_reserved_names_for_dir(array $store, string $relativeDir): array
{
    $reserved = [];
    foreach (($store['sessions'] ?? []) as $session) {
        if (!is_array($session)) {
            continue;
        }
        if ((string) ($session['status'] ?? '') === 'completed') {
            continue;
        }
        if (trim((string) ($session['relativeDir'] ?? '')) !== $relativeDir) {
            continue;
        }
        $name = trim((string) ($session['finalName'] ?? ''));
        if ($name === '') {
            continue;
        }
        $reserved[] = $name;
    }

    return $reserved;
}

function notes_direct_upload_limit_bytes(array $gateway): ?int
{
    $limits = [];
    foreach (['postMaxBytes', 'uploadMaxBytes'] as $key) {
        $value = $gateway[$key] ?? null;
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $bytes = max(0, (int) round((float) $value));
            if ($bytes > 0) {
                $limits[] = $bytes;
            }
        }
    }

    if ($limits === []) {
        return null;
    }

    return min($limits);
}

function notes_direct_upload_build_file_payload(array $session, ?int $sizeBytes = null, ?string $mimeType = null): array
{
    $bytes = $sizeBytes !== null ? max(0, $sizeBytes) : max(0, (int) ($session['expectedSize'] ?? 0));
    $finalMimeType = trim((string) ($mimeType !== null ? $mimeType : ($session['mimeType'] ?? '')));
    $relativeDir = trim((string) ($session['relativeDir'] ?? ''));
    $relativePath = trim((string) ($session['relativePath'] ?? ''));
    $finalName = trim((string) ($session['finalName'] ?? basename($relativePath)));

    return [
        'name' => $finalName,
        'relativeDir' => $relativeDir,
        'relativePath' => $relativePath,
        'sizeBytes' => $bytes,
        'sizeLabel' => notes_download_host_human_size($bytes),
        'mimeType' => $finalMimeType,
        'publicUrl' => notes_download_host_public_url($relativePath),
        'message' => 'فایل روی هاست دانلود ذخیره شد.',
    ];
}

function notes_build_host_upload_url(string $relativeDir, string $cohort): string
{
    $query = [
        'action' => 'hostUploadFile',
        'path' => $relativeDir,
    ];
    if ($cohort !== '') {
        $query['cohort'] = $cohort;
    }

    return '/api/notes_api.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function notes_direct_upload_main_site_origin(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }

    $parsedHost = parse_url('http://' . $host, PHP_URL_HOST);
    $hostname = strtolower(trim((string) $parsedHost));
    if ($hostname === '' || in_array($hostname, ['localhost', '127.0.0.1', '::1'], true)) {
        return '';
    }
    if (str_ends_with($hostname, '.local') || str_ends_with($hostname, '.test') || str_ends_with($hostname, '.invalid') || str_ends_with($hostname, '.localhost')) {
        return '';
    }
    if (filter_var($hostname, FILTER_VALIDATE_IP) && filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return '';
    }
    if (!filter_var($hostname, FILTER_VALIDATE_IP) && !str_contains($hostname, '.')) {
        return '';
    }

    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $forwardedSsl = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')));
    $secure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || $forwardedProto === 'https'
        || $forwardedSsl === 'on';

    return ($secure ? 'https://' : 'http://') . $host;
}

function notes_download_host_target_context(string $cohort, array $viewer, array $params): array
{
    $scopeRoot = notes_download_host_scope_for_viewer($cohort, $viewer);
    $relativeDir = trim((string) ($params['path'] ?? $params['relativeDir'] ?? ''));
    if ($relativeDir === '') {
        $term = $cohort === 'prosthesis-1402'
            ? notes_prosthesis_1402_parse_term_id($params['term'] ?? '1')
            : notes_require_term_for_cohort($cohort, $params['term'] ?? '');
        $store = notes_curriculum_store_for_cohort($cohort);
        $relativeDir = notes_download_host_default_relative_dir_from_request($cohort, $term, $params, $store);
    }

    return [
        'scopeRoot' => $scopeRoot,
        'relativeDir' => notes_download_host_assert_allowed_relative_path($relativeDir, $scopeRoot, false),
    ];
}

function notes_prepare_host_upload_plan(string $cohort, array $viewer, array $params): array
{
    $target = notes_download_host_target_context($cohort, $viewer, $params);
    $expectedSize = max(0, (int) ($params['fileSize'] ?? 0));
    if ($expectedSize <= 0) {
        dent_error('حجم فایل برای آپلود معتبر نیست.', 422);
    }

    $desiredName = trim((string) ($params['fileName'] ?? ''));
    if ($desiredName === '') {
        dent_error('نام فایل برای آپلود معتبر نیست.', 422);
    }

    $mimeType = trim((string) ($params['mimeType'] ?? ''));

    // Create only the destination directory. File bytes are sent later as
    // bounded raw-body chunks and streamed over FTP into a token-scoped partial
    // file on the download host. No complete-file staging is created here or on
    // the main host.
    notes_download_host_ensure_dir($target['relativeDir'], $target['scopeRoot']);
    $mainSiteOrigin = notes_direct_upload_main_site_origin();
    if ($mainSiteOrigin !== '') {
        notes_download_host_ensure_direct_upload_gateway($mainSiteOrigin);
    }

    return notes_direct_upload_with_store_lock(static function (array &$store) use ($cohort, $target, $desiredName, $mimeType, $expectedSize): array {
        $finalName = notes_download_host_unique_file_name_with_reserved(
            $target['relativeDir'],
            $desiredName,
            notes_direct_upload_reserved_names_for_dir($store, $target['relativeDir'])
        );
        $relativePath = trim($target['relativeDir'] . '/' . $finalName, '/');
        $token = notes_direct_upload_next_token();
        $sessionKey = notes_direct_upload_next_token();
        $now = dent_iso_now();

        $session = [
            'token' => $token,
            'status' => 'prepared',
            'cohort' => $cohort,
            'scopeRoot' => (string) ($target['scopeRoot'] ?? ''),
            'relativeDir' => $target['relativeDir'],
            'relativePath' => $relativePath,
            'finalName' => $finalName,
            'mimeType' => $mimeType,
            'expectedSize' => $expectedSize,
            'createdAt' => $now,
            'updatedAt' => $now,
            'expiresAt' => date('c', time() + NOTES_DIRECT_UPLOAD_SESSION_TTL_SECONDS),
            'sessionKey' => $sessionKey,
            'origin' => notes_direct_upload_main_site_origin(),
            'gatewayVersion' => 'main-ftp-stream-v1',
            'contentType' => '',
            'contentLength' => 0,
            'completedAt' => '',
            'file' => null,
        ];
        $store['sessions'][$token] = $session;

        return [
            'mode' => 'stream',
            'url' => '/api/notes_api.php?action=streamHostUploadChunk&cohort=' . rawurlencode($cohort) . '&token=' . rawurlencode($token),
            'relativeDir' => $target['relativeDir'],
            'relativePath' => $relativePath,
            'fileName' => $finalName,
            'scopeRoot' => $target['scopeRoot'],
            'transport' => 'raw-chunk-to-ftp',
            'chunkBytes' => 4 * 1024 * 1024,
        ];
    });
}
