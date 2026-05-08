<?php
declare(strict_types=1);

require_once __DIR__ . '/content_tools_store.php';

function content_api_require_method(array $methods): void
{
    if (!in_array(dent_request_method(), $methods, true)) {
        dent_error('متد درخواست نامعتبر است.', 405);
    }
}

function content_api_json_input(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function content_api_bool($value): bool
{
    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
}

function content_api_sort_records(array $records, string $sort): array
{
    $sort = trim(strtolower($sort));
    usort($records, static function (array $left, array $right) use ($sort): int {
        if ($sort === 'oldest') {
            return strcmp((string) ($left['createdAt'] ?? ''), (string) ($right['createdAt'] ?? ''));
        }
        if ($sort === 'size') {
            return (int) ($right['size'] ?? 0) <=> (int) ($left['size'] ?? 0);
        }
        if ($sort === 'downloads') {
            return (int) ($right['downloadCount'] ?? 0) <=> (int) ($left['downloadCount'] ?? 0);
        }
        if ($sort === 'views') {
            return (int) ($right['viewCount'] ?? 0) <=> (int) ($left['viewCount'] ?? 0);
        }
        return strcmp((string) ($right['createdAt'] ?? ''), (string) ($left['createdAt'] ?? ''));
    });
    return $records;
}

function content_api_filter_files(array $files, array $params): array
{
    $query = dent_utf8_strtolower(dent_clean_text((string) ($params['query'] ?? ''), 120));
    $status = trim(strtolower((string) ($params['status'] ?? 'all')));
    $type = trim(strtolower((string) ($params['type'] ?? 'all')));
    $folder = dent_utf8_strtolower(dent_clean_text((string) ($params['folder'] ?? ''), 80));

    $filtered = [];
    foreach ($files as $file) {
        if (!is_array($file)) {
            continue;
        }
        $publicState = content_public_state($file);
        if ($status === '' || $status === 'all' || $status === 'available') {
            if ($publicState === 'deleted') {
                continue;
            }
        } elseif ($publicState !== $status && (string) ($file['status'] ?? '') !== $status) {
            continue;
        }
        if ($folder !== '' && dent_utf8_strtolower((string) ($file['folder'] ?? '')) !== $folder) {
            continue;
        }
        $mime = strtolower((string) ($file['mimeType'] ?? ''));
        if ($type !== '' && $type !== 'all') {
            $matchesType = ($type === 'image' && str_starts_with($mime, 'image/'))
                || ($type === 'video' && str_starts_with($mime, 'video/'))
                || ($type === 'audio' && str_starts_with($mime, 'audio/'))
                || ($type === 'pdf' && $mime === 'application/pdf')
                || ($type === 'text' && (str_starts_with($mime, 'text/') || $mime === 'application/json'))
                || ($type === 'other' && !str_starts_with($mime, 'image/') && !str_starts_with($mime, 'video/') && !str_starts_with($mime, 'audio/') && $mime !== 'application/pdf' && !str_starts_with($mime, 'text/'));
            if (!$matchesType) {
                continue;
            }
        }
        if ($query !== '') {
            $haystack = dent_utf8_strtolower(implode(' ', [
                (string) ($file['originalName'] ?? ''),
                (string) ($file['title'] ?? ''),
                (string) ($file['description'] ?? ''),
                (string) ($file['folder'] ?? ''),
                implode(' ', is_array($file['tags'] ?? null) ? $file['tags'] : []),
                (string) ($file['extension'] ?? ''),
                (string) ($file['mimeType'] ?? ''),
            ]));
            if (!str_contains($haystack, $query)) {
                continue;
            }
        }
        $filtered[] = $file;
    }
    return content_api_sort_records($filtered, (string) ($params['sort'] ?? 'newest'));
}

function content_api_filter_pastes(array $pastes, array $params): array
{
    $query = dent_utf8_strtolower(dent_clean_text((string) ($params['query'] ?? ''), 120));
    $status = trim(strtolower((string) ($params['status'] ?? 'all')));
    $language = content_clean_language((string) ($params['language'] ?? ''));
    if ($language === 'plain' && trim((string) ($params['language'] ?? '')) === '') {
        $language = '';
    }

    $filtered = [];
    foreach ($pastes as $paste) {
        if (!is_array($paste)) {
            continue;
        }
        $publicState = content_public_state($paste);
        if ($status === '' || $status === 'all' || $status === 'available') {
            if ($publicState === 'deleted') {
                continue;
            }
        } elseif ($publicState !== $status && (string) ($paste['status'] ?? '') !== $status) {
            continue;
        }
        if ($language !== '' && (string) ($paste['language'] ?? '') !== $language) {
            continue;
        }
        if ($query !== '') {
            $haystack = dent_utf8_strtolower(implode(' ', [
                (string) ($paste['title'] ?? ''),
                (string) ($paste['language'] ?? ''),
                (string) ($paste['body'] ?? ''),
            ]));
            if (!str_contains($haystack, $query)) {
                continue;
            }
        }
        $filtered[] = $paste;
    }
    return content_api_sort_records($filtered, (string) ($params['sort'] ?? 'newest'));
}

function content_api_paginate(array $records, array $params): array
{
    $page = max(1, (int) ($params['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($params['perPage'] ?? 20)));
    $total = count($records);
    $pages = max(1, (int) ceil($total / $perPage));
    if ($page > $pages) {
        $page = $pages;
    }
    return [
        'page' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'pages' => $pages,
        'items' => array_slice($records, ($page - 1) * $perPage, $perPage),
    ];
}

function content_api_unavailable_payload(string $kind, string $state = 'missing'): array
{
    $title = $kind === 'paste' ? 'Paste در دسترس نیست' : 'فایل در دسترس نیست';
    $message = 'لینک ممکن است حذف، منقضی یا غیرفعال شده باشد.';
    if ($state === 'password') {
        $title = 'رمز لازم است';
        $message = 'برای باز کردن این لینک، رمز تعریف‌شده را وارد کنید.';
    } elseif ($state === 'limited') {
        $message = 'سقف دانلود این فایل تکمیل شده است.';
    }
    return [
        'available' => false,
        'state' => $state,
        'title' => $title,
        'message' => $message,
    ];
}

function content_api_emit_file_bytes(array $file, string $mode): void
{
    $path = content_upload_file_path((string) ($file['storedName'] ?? ''));
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        dent_error('فایل در storage پیدا نشد.', 404);
    }
    $mime = (string) ($file['mimeType'] ?? 'application/octet-stream');
    $name = (string) ($file['originalName'] ?? 'download');
    $disposition = $mode === 'preview' && content_is_previewable_file($file) ? 'inline' : 'attachment';
    $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'download';
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header("Content-Disposition: {$disposition}; filename=\"" . addslashes($fallback) . "\"; filename*=UTF-8''" . rawurlencode($name));
    header('Cache-Control: private, max-age=0, no-cache');
    readfile($path);
    exit;
}

$action = dent_request_action();
if ($action === '' && dent_request_method() === 'POST' && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && $_POST === [] && $_FILES === []) {
    dent_error('حجم درخواست از سقف فعلی PHP/هاست بیشتر است. سقف ابزار ۲ گیگابایت تنظیم شده، اما ممکن است هاست هنوز مقدار جدید upload_max_filesize/post_max_size را اعمال نکرده باشد.', 413);
}

if ($action === 'ownerDashboard') {
    content_api_require_method(['GET']);
    dent_require_owner();
    $store = content_read_store();
    dent_json_response([
        'success' => true,
        'summary' => content_storage_summary($store),
        'recentFiles' => array_map(static fn(array $file): array => content_file_public_payload($file, true), array_slice(array_values($store['files']), 0, 8)),
        'recentPastes' => array_map(static fn(array $paste): array => content_paste_public_payload($paste, false, true), array_slice(array_values($store['pastes']), 0, 8)),
    ]);
}

if ($action === 'ownerUploadFiles') {
    content_api_require_method(['POST']);
    $owner = dent_require_owner();
    $files = $_FILES['files'] ?? ($_FILES['file'] ?? null);
    if (!is_array($files)) {
        dent_error('فایلی برای آپلود انتخاب نشده است.', 422);
    }

    $normalizedFiles = [];
    if (is_array($files['name'] ?? null)) {
        foreach ($files['name'] as $index => $name) {
            $normalizedFiles[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }
    } else {
        $normalizedFiles[] = $files;
    }
    if ($normalizedFiles === []) {
        dent_error('فایلی برای آپلود انتخاب نشده است.', 422);
    }

    $meta = [
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'tags' => $_POST['tags'] ?? '',
        'folder' => $_POST['folder'] ?? '',
        'status' => $_POST['status'] ?? 'active',
        'expiresAt' => $_POST['expiresAt'] ?? '',
        'password' => $_POST['password'] ?? '',
        'downloadLimit' => $_POST['downloadLimit'] ?? 0,
    ];
    $uploaded = [];
    try {
        $uploaded = content_with_store_lock(static function (array &$store) use ($normalizedFiles, $owner, $meta): array {
            $created = [];
            foreach ($normalizedFiles as $file) {
                if (!is_array($file)) {
                    continue;
                }
                $record = content_store_uploaded_file($file, $owner, $meta);
                $store['files'][(string) $record['id']] = $record;
                $created[] = $record;
            }
            return $created;
        });
    } catch (Throwable $error) {
        dent_error($error->getMessage(), 422);
    }
    if ($uploaded === []) {
        dent_error('هیچ فایلی ذخیره نشد.', 422);
    }
    content_audit_log('owner-upload-files', [
        'count' => count($uploaded),
        'by' => dent_normalize_student_number((string) ($owner['studentNumber'] ?? '')),
    ]);
    dent_json_response([
        'success' => true,
        'files' => array_map(static fn(array $file): array => content_file_public_payload($file, true), $uploaded),
        'message' => count($uploaded) . ' فایل آپلود شد.',
    ]);
}

if ($action === 'ownerFiles') {
    content_api_require_method(['GET']);
    dent_require_owner();
    $store = content_read_store();
    $filtered = content_api_filter_files(array_values($store['files']), $_GET);
    $page = content_api_paginate($filtered, $_GET);
    $page['items'] = array_map(static fn(array $file): array => content_file_public_payload($file, true), $page['items']);
    dent_json_response([
        'success' => true,
        'summary' => content_storage_summary($store),
        'page' => $page,
    ]);
}

if ($action === 'ownerUpdateFile') {
    content_api_require_method(['POST']);
    dent_require_owner();
    $id = content_clean_id((string) ($_POST['id'] ?? ''), CONTENT_FILE_ID_PREFIX);
    if ($id === '') {
        dent_error('شناسه فایل معتبر نیست.', 422);
    }
    $updated = content_with_store_lock(static function (array &$store) use ($id): array {
        $file = $store['files'][$id] ?? null;
        if (!is_array($file)) {
            dent_error('فایل پیدا نشد.', 404);
        }
        $file['title'] = dent_clean_text((string) ($_POST['title'] ?? ($file['title'] ?? '')), 180);
        $file['description'] = dent_clean_text((string) ($_POST['description'] ?? ($file['description'] ?? '')), 1200);
        $file['tags'] = content_clean_tag_list($_POST['tags'] ?? ($file['tags'] ?? []));
        $file['folder'] = dent_clean_text((string) ($_POST['folder'] ?? ($file['folder'] ?? '')), 80);
        $file['status'] = content_clean_status((string) ($_POST['status'] ?? ($file['status'] ?? 'active')));
        $file['expiresAt'] = content_parse_expires_at($_POST['expiresAt'] ?? ($file['expiresAt'] ?? ''));
        $file['downloadLimit'] = content_normalize_download_limit($_POST['downloadLimit'] ?? ($file['downloadLimit'] ?? 0));
        if (array_key_exists('password', $_POST)) {
            $password = trim((string) $_POST['password']);
            if ($password !== '') {
                $file['passwordHash'] = content_hash_password($password);
            }
        }
        if (content_api_bool($_POST['clearPassword'] ?? false)) {
            $file['passwordHash'] = '';
        }
        $file['updatedAt'] = dent_iso_now();
        $store['files'][$id] = content_normalize_file_record($id, $file) ?? $file;
        return $store['files'][$id];
    });
    dent_json_response([
        'success' => true,
        'file' => content_file_public_payload($updated, true),
        'message' => 'فایل به‌روزرسانی شد.',
    ]);
}

if ($action === 'ownerBulkFiles') {
    content_api_require_method(['POST']);
    dent_require_owner();
    $operation = trim(strtolower((string) ($_POST['operation'] ?? '')));
    $idsRaw = $_POST['ids'] ?? [];
    if (is_string($idsRaw)) {
        $decoded = json_decode($idsRaw, true);
        $idsRaw = is_array($decoded) ? $decoded : preg_split('/[\s,،;]+/u', $idsRaw);
    }
    $ids = [];
    foreach (is_array($idsRaw) ? $idsRaw : [] as $id) {
        $clean = content_clean_id((string) $id, CONTENT_FILE_ID_PREFIX);
        if ($clean !== '') {
            $ids[$clean] = true;
        }
    }
    if ($ids === []) {
        dent_error('حداقل یک فایل را انتخاب کنید.', 422);
    }
    if (!in_array($operation, ['delete', 'hide', 'activate', 'purge'], true)) {
        dent_error('عملیات گروهی معتبر نیست.', 422);
    }
    $result = content_with_store_lock(static function (array &$store) use ($ids, $operation): array {
        $changed = 0;
        $purged = 0;
        foreach (array_keys($ids) as $id) {
            if (!is_array($store['files'][$id] ?? null)) {
                continue;
            }
            $file = $store['files'][$id];
            if ($operation === 'activate') {
                $file['status'] = 'active';
            } elseif ($operation === 'hide') {
                $file['status'] = 'hidden';
            } else {
                $file['status'] = 'deleted';
                $file['deletedAt'] = $file['deletedAt'] ?: dent_iso_now();
            }
            if ($operation === 'purge') {
                $path = content_upload_file_path((string) ($file['storedName'] ?? ''));
                if ($path !== '' && is_file($path)) {
                    @unlink($path);
                }
                $file['purgedAt'] = dent_iso_now();
                $purged++;
            }
            $file['updatedAt'] = dent_iso_now();
            $store['files'][$id] = $file;
            $changed++;
        }
        return ['changed' => $changed, 'purged' => $purged];
    });
    content_audit_log('owner-bulk-files', ['operation' => $operation, 'count' => $result['changed']]);
    dent_json_response([
        'success' => true,
        'result' => $result,
        'message' => 'عملیات گروهی فایل‌ها انجام شد.',
    ]);
}

if ($action === 'publicFile') {
    content_api_require_method(['GET', 'POST']);
    $token = content_clean_slug_token((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
    $password = (string) ($_POST['password'] ?? $_GET['password'] ?? '');
    $store = content_read_store();
    $file = content_find_file_by_token($store, $token);
    if (!is_array($file)) {
        dent_json_response(['success' => true, 'file' => null, 'unavailable' => content_api_unavailable_payload('file', 'missing')]);
    }
    $state = content_public_state($file);
    if ($state !== 'active' || !content_file_can_download($file)) {
        dent_json_response(['success' => true, 'file' => content_file_public_payload($file), 'unavailable' => content_api_unavailable_payload('file', !content_file_can_download($file) ? 'limited' : $state)]);
    }
    if ((string) ($file['passwordHash'] ?? '') !== '' && !content_verify_record_password($file, $password)) {
        dent_json_response(['success' => true, 'file' => content_file_public_payload($file), 'unavailable' => content_api_unavailable_payload('file', 'password')]);
    }
    dent_json_response([
        'success' => true,
        'file' => content_file_public_payload($file),
        'previewUrl' => content_is_previewable_file($file) ? content_absolute_url('/api/content_tools_api.php?action=previewFile&token=' . rawurlencode($token)) : '',
    ]);
}

if ($action === 'previewFile' || $action === 'downloadFile') {
    content_api_require_method(['GET', 'POST']);
    $token = content_clean_slug_token((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
    $password = (string) ($_GET['password'] ?? $_POST['password'] ?? '');
    $file = null;
    $mode = $action === 'previewFile' ? 'preview' : 'download';
    $file = content_with_store_lock(static function (array &$store) use ($token, $password, $mode): array {
        foreach ($store['files'] as $id => $candidate) {
            if (!is_array($candidate) || (string) ($candidate['token'] ?? '') !== $token) {
                continue;
            }
            if (!content_file_can_download($candidate)) {
                dent_error('این لینک فایل در دسترس نیست.', 404);
            }
            if ((string) ($candidate['passwordHash'] ?? '') !== '' && !content_verify_record_password($candidate, $password)) {
                dent_error('رمز لینک فایل معتبر نیست.', 403);
            }
            if ($mode === 'download') {
                $candidate['downloadCount'] = max(0, (int) ($candidate['downloadCount'] ?? 0)) + 1;
                $candidate['lastDownloadedAt'] = dent_iso_now();
                $store['files'][$id] = $candidate;
            }
            return $candidate;
        }
        dent_error('فایل پیدا نشد.', 404);
    });
    content_api_emit_file_bytes($file, $mode);
}

if ($action === 'ownerPastes') {
    content_api_require_method(['GET']);
    dent_require_owner();
    $store = content_read_store();
    $filtered = content_api_filter_pastes(array_values($store['pastes']), $_GET);
    $page = content_api_paginate($filtered, $_GET);
    $includeBody = content_api_bool($_GET['includeBody'] ?? false);
    $page['items'] = array_map(static fn(array $paste): array => content_paste_public_payload($paste, $includeBody, true), $page['items']);
    dent_json_response([
        'success' => true,
        'summary' => content_storage_summary($store),
        'page' => $page,
    ]);
}

if ($action === 'ownerSavePaste') {
    content_api_require_method(['POST']);
    $owner = dent_require_owner();
    $id = content_clean_id((string) ($_POST['id'] ?? ''), CONTENT_PASTE_ID_PREFIX);
    $body = dent_force_utf8((string) ($_POST['body'] ?? ''));
    if (trim($body) === '') {
        dent_error('متن paste خالی است.', 422);
    }
    if (dent_utf8_strlen($body) > CONTENT_MAX_PASTE_CHARS) {
        dent_error('متن paste بیش از حد مجاز است.', 422);
    }
    $saved = content_with_store_lock(static function (array &$store) use ($id, $body, $owner): array {
        $now = dent_iso_now();
        if ($id !== '' && is_array($store['pastes'][$id] ?? null)) {
            $paste = $store['pastes'][$id];
        } else {
            $paste = [
                'id' => content_next_id(CONTENT_PASTE_ID_PREFIX),
                'token' => content_random_token(10),
                'createdAt' => $now,
                'createdBy' => dent_normalize_student_number((string) ($owner['studentNumber'] ?? '')),
                'viewCount' => 0,
                'rawViewCount' => 0,
                'lastViewedAt' => '',
                'deletedAt' => '',
            ];
        }
        $paste['title'] = dent_clean_text((string) ($_POST['title'] ?? ($paste['title'] ?? 'Paste')), 180) ?: 'Paste';
        $paste['body'] = $body;
        $paste['language'] = content_clean_language((string) ($_POST['language'] ?? ($paste['language'] ?? 'plain')));
        $paste['status'] = content_clean_status((string) ($_POST['status'] ?? ($paste['status'] ?? 'active')));
        $paste['expiresAt'] = content_parse_expires_at($_POST['expiresAt'] ?? ($paste['expiresAt'] ?? ''));
        if (array_key_exists('password', $_POST)) {
            $password = trim((string) $_POST['password']);
            if ($password !== '') {
                $paste['passwordHash'] = content_hash_password($password);
            }
        }
        if (content_api_bool($_POST['clearPassword'] ?? false)) {
            $paste['passwordHash'] = '';
        }
        $paste['updatedAt'] = $now;
        $normalized = content_normalize_paste_record((string) ($paste['id'] ?? ''), $paste);
        if ($normalized === null) {
            dent_error('رکورد paste معتبر نیست.', 422);
        }
        $store['pastes'][(string) $normalized['id']] = $normalized;
        return $normalized;
    });
    content_audit_log('owner-save-paste', ['id' => $saved['id'], 'by' => dent_normalize_student_number((string) ($owner['studentNumber'] ?? ''))]);
    dent_json_response([
        'success' => true,
        'paste' => content_paste_public_payload($saved, true, true),
        'message' => $id !== '' ? 'Paste به‌روزرسانی شد.' : 'Paste ساخته شد.',
    ]);
}

if ($action === 'ownerBulkPastes') {
    content_api_require_method(['POST']);
    dent_require_owner();
    $operation = trim(strtolower((string) ($_POST['operation'] ?? '')));
    $idsRaw = $_POST['ids'] ?? [];
    if (is_string($idsRaw)) {
        $decoded = json_decode($idsRaw, true);
        $idsRaw = is_array($decoded) ? $decoded : preg_split('/[\s,،;]+/u', $idsRaw);
    }
    $ids = [];
    foreach (is_array($idsRaw) ? $idsRaw : [] as $id) {
        $clean = content_clean_id((string) $id, CONTENT_PASTE_ID_PREFIX);
        if ($clean !== '') {
            $ids[$clean] = true;
        }
    }
    if ($ids === []) {
        dent_error('حداقل یک paste را انتخاب کنید.', 422);
    }
    if (!in_array($operation, ['delete', 'hide', 'activate'], true)) {
        dent_error('عملیات گروهی معتبر نیست.', 422);
    }
    $changed = content_with_store_lock(static function (array &$store) use ($ids, $operation): int {
        $count = 0;
        foreach (array_keys($ids) as $id) {
            if (!is_array($store['pastes'][$id] ?? null)) {
                continue;
            }
            $paste = $store['pastes'][$id];
            if ($operation === 'activate') {
                $paste['status'] = 'active';
            } elseif ($operation === 'hide') {
                $paste['status'] = 'hidden';
            } else {
                $paste['status'] = 'deleted';
                $paste['deletedAt'] = $paste['deletedAt'] ?: dent_iso_now();
            }
            $paste['updatedAt'] = dent_iso_now();
            $store['pastes'][$id] = $paste;
            $count++;
        }
        return $count;
    });
    content_audit_log('owner-bulk-pastes', ['operation' => $operation, 'count' => $changed]);
    dent_json_response([
        'success' => true,
        'changed' => $changed,
        'message' => 'عملیات گروهی pasteها انجام شد.',
    ]);
}

if ($action === 'publicPaste') {
    content_api_require_method(['GET', 'POST']);
    $token = content_clean_slug_token((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
    $password = (string) ($_POST['password'] ?? $_GET['password'] ?? '');
    $paste = content_with_store_lock(static function (array &$store) use ($token, $password): ?array {
        foreach ($store['pastes'] as $id => $candidate) {
            if (!is_array($candidate) || (string) ($candidate['token'] ?? '') !== $token) {
                continue;
            }
            if (content_public_state($candidate) !== 'active') {
                return $candidate;
            }
            if ((string) ($candidate['passwordHash'] ?? '') !== '' && !content_verify_record_password($candidate, $password)) {
                return $candidate;
            }
            $candidate['viewCount'] = max(0, (int) ($candidate['viewCount'] ?? 0)) + 1;
            $candidate['lastViewedAt'] = dent_iso_now();
            $store['pastes'][$id] = $candidate;
            return $candidate;
        }
        return null;
    });
    if (!is_array($paste)) {
        dent_json_response(['success' => true, 'paste' => null, 'unavailable' => content_api_unavailable_payload('paste', 'missing')]);
    }
    $state = content_public_state($paste);
    if ($state !== 'active') {
        dent_json_response(['success' => true, 'paste' => content_paste_public_payload($paste, false), 'unavailable' => content_api_unavailable_payload('paste', $state)]);
    }
    if ((string) ($paste['passwordHash'] ?? '') !== '' && !content_verify_record_password($paste, $password)) {
        dent_json_response(['success' => true, 'paste' => content_paste_public_payload($paste, false), 'unavailable' => content_api_unavailable_payload('paste', 'password')]);
    }
    dent_json_response(['success' => true, 'paste' => content_paste_public_payload($paste, true)]);
}

if ($action === 'rawPaste') {
    content_api_require_method(['GET', 'POST']);
    $token = content_clean_slug_token((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
    $password = (string) ($_GET['password'] ?? $_POST['password'] ?? '');
    $paste = content_with_store_lock(static function (array &$store) use ($token, $password): array {
        foreach ($store['pastes'] as $id => $candidate) {
            if (!is_array($candidate) || (string) ($candidate['token'] ?? '') !== $token) {
                continue;
            }
            if (content_public_state($candidate) !== 'active') {
                dent_error('این paste در دسترس نیست.', 404);
            }
            if ((string) ($candidate['passwordHash'] ?? '') !== '' && !content_verify_record_password($candidate, $password)) {
                dent_error('رمز paste معتبر نیست.', 403);
            }
            $candidate['rawViewCount'] = max(0, (int) ($candidate['rawViewCount'] ?? 0)) + 1;
            $candidate['lastViewedAt'] = dent_iso_now();
            $store['pastes'][$id] = $candidate;
            return $candidate;
        }
        dent_error('Paste پیدا نشد.', 404);
    });
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, no-cache');
    echo (string) ($paste['body'] ?? '');
    exit;
}

dent_error('درخواست نامعتبر است.', 404);
