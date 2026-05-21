<?php
declare(strict_types=1);

require_once __DIR__ . '/notes_download_host.php';

function content_download_host_is_enabled(): bool
{
    return notes_download_host_is_enabled();
}

function content_download_host_public_base_url(): string
{
    return notes_download_host_public_base_url();
}

function content_download_host_normalize_relative_path(string $path): string
{
    return notes_download_host_normalize_relative_path($path);
}

function content_download_host_sanitize_leaf_name(string $name, string $fallback): string
{
    return notes_download_host_sanitize_leaf_name($name, $fallback);
}

function content_download_host_absolute_base_dir(): string
{
    return notes_download_host_absolute_base_dir();
}

function content_download_host_abs_path_from_relative(string $relativePath): string
{
    return notes_download_host_abs_path_from_relative($relativePath);
}

function content_download_host_relative_from_abs_path(string $absolutePath): string
{
    return notes_download_host_relative_from_abs_path($absolutePath);
}

function content_download_host_public_url(string $relativePath): string
{
    return notes_download_host_public_url($relativePath);
}

function content_download_host_breadcrumbs(string $relativePath): array
{
    $items = [[
        'label' => 'ریشه آپلودسنتر',
        'path' => '',
    ]];
    if ($relativePath === '') {
        return $items;
    }

    $current = '';
    foreach (explode('/', $relativePath) as $part) {
        $current = $current === '' ? $part : ($current . '/' . $part);
        $items[] = [
            'label' => $part,
            'path' => $current,
        ];
    }

    return $items;
}

function content_download_host_sort_entries(array &$entries): void
{
    notes_download_host_sort_entries($entries);
}

function content_download_host_entry_payload(array $item): array
{
    return notes_download_host_entry_payload($item);
}

function content_download_host_list_dir(string $relativePath): array
{
    $absoluteDir = $relativePath === ''
        ? content_download_host_absolute_base_dir()
        : content_download_host_abs_path_from_relative($relativePath);

    $response = notes_download_host_execute_uapi('Fileman/list_files', [
        'dir' => $absoluteDir,
        'include_mime' => '1',
    ]);
    $items = is_array($response['data'] ?? null) ? $response['data'] : [];
    $entries = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $entries[] = content_download_host_entry_payload($item);
    }
    content_download_host_sort_entries($entries);

    return $entries;
}

function content_download_host_browse(string $relativePath): array
{
    $normalized = content_download_host_normalize_relative_path($relativePath);

    return [
        'currentPath' => $normalized,
        'entries' => content_download_host_list_dir($normalized),
        'breadcrumbs' => content_download_host_breadcrumbs($normalized),
    ];
}

function content_download_host_ensure_dir(string $relativeDir): string
{
    $normalized = content_download_host_normalize_relative_path($relativeDir);
    if ($normalized === '') {
        return content_download_host_absolute_base_dir();
    }

    $currentRelative = '';
    $currentAbs = content_download_host_absolute_base_dir();
    foreach (explode('/', $normalized) as $part) {
        $part = content_download_host_sanitize_leaf_name($part, 'folder');
        $result = notes_download_host_execute_api2('mkdir', [
            'path' => $currentAbs,
            'name' => $part,
            'permissions' => '0755',
        ]);
        $error = trim((string) ($result['error'] ?? ''));
        if ($error !== '' && stripos($error, 'file exists') === false) {
            dent_error('ساخت پوشه روی هاست دانلود انجام نشد: ' . $error, 502);
        }
        $currentRelative = $currentRelative === '' ? $part : ($currentRelative . '/' . $part);
        $currentAbs = content_download_host_abs_path_from_relative($currentRelative);
    }

    return $currentAbs;
}

function content_download_host_file_names_in_dir(string $relativeDir): array
{
    $entries = content_download_host_list_dir(content_download_host_normalize_relative_path($relativeDir));
    $names = [];
    foreach ($entries as $entry) {
        if (($entry['type'] ?? '') !== 'file') {
            continue;
        }
        $names[dent_utf8_strtolower((string) ($entry['name'] ?? ''))] = true;
    }

    return $names;
}

function content_download_host_unique_file_name(string $relativeDir, string $name): string
{
    $name = content_download_host_sanitize_leaf_name($name, 'file');
    $extension = pathinfo($name, PATHINFO_EXTENSION);
    $baseName = $extension !== '' ? substr($name, 0, -1 * (strlen($extension) + 1)) : $name;
    $baseName = content_download_host_sanitize_leaf_name($baseName, 'file');
    $candidate = $extension !== '' ? ($baseName . '.' . $extension) : $baseName;

    $existing = content_download_host_file_names_in_dir($relativeDir);
    $index = 2;
    while (isset($existing[dent_utf8_strtolower($candidate)])) {
        $candidate = $extension !== ''
            ? ($baseName . ' (' . $index . ').' . $extension)
            : ($baseName . ' (' . $index . ')');
        $index++;
    }

    return $candidate;
}

function content_download_host_upload_file(string $relativeDir, array $file, string $desiredName = ''): array
{
    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        dent_error('فایل ارسالی معتبر نیست.', 422);
    }

    $normalizedDir = content_download_host_normalize_relative_path($relativeDir);
    $desiredName = trim($desiredName) !== '' ? $desiredName : (string) ($file['name'] ?? 'file');
    $targetAbsDir = content_download_host_ensure_dir($normalizedDir);
    $finalName = content_download_host_unique_file_name($normalizedDir, $desiredName);
    $mimeType = trim((string) ($file['type'] ?? ''));
    $upload = notes_download_host_stream_upload($targetAbsDir, $tmpPath, $finalName, $mimeType);

    $finalRelativePath = trim(($normalizedDir === '' ? '' : ($normalizedDir . '/')) . $finalName, '/');
    $bytes = max(0, (int) ($upload['size'] ?? filesize($tmpPath) ?: 0));

    return [
        'name' => $finalName,
        'relativeDir' => $normalizedDir,
        'relativePath' => $finalRelativePath,
        'sizeBytes' => $bytes,
        'sizeLabel' => notes_download_host_human_size($bytes),
        'mimeType' => $mimeType,
        'publicUrl' => content_download_host_public_url($finalRelativePath),
        'message' => trim((string) ($upload['reason'] ?? 'فایل روی هاست دانلود ذخیره شد.')),
    ];
}

function content_download_host_create_dir(string $parentRelativePath, string $directoryName): array
{
    $parent = content_download_host_normalize_relative_path($parentRelativePath);
    $directoryName = content_download_host_sanitize_leaf_name($directoryName, '');
    if ($directoryName === '') {
        dent_error('نام پوشه جدید معتبر نیست.', 422);
    }

    $parentAbs = $parent === ''
        ? content_download_host_absolute_base_dir()
        : content_download_host_ensure_dir($parent);
    $result = notes_download_host_execute_api2('mkdir', [
        'path' => $parentAbs,
        'name' => $directoryName,
        'permissions' => '0755',
    ]);
    $error = trim((string) ($result['error'] ?? ''));
    if ($error !== '' && stripos($error, 'file exists') === false) {
        dent_error('ساخت پوشه روی هاست دانلود انجام نشد: ' . $error, 502);
    }

    $relativePath = $parent === '' ? $directoryName : ($parent . '/' . $directoryName);
    return [
        'name' => $directoryName,
        'type' => 'dir',
        'relativePath' => $relativePath,
        'parentPath' => $parent,
    ];
}

function content_download_host_rename_entry(string $relativePath, string $newName): array
{
    $relativePath = content_download_host_normalize_relative_path($relativePath);
    if ($relativePath === '') {
        dent_error('تغییر نام ریشه آپلودسنتر مجاز نیست.', 422);
    }
    $newName = content_download_host_sanitize_leaf_name($newName, '');
    if ($newName === '') {
        dent_error('نام جدید معتبر نیست.', 422);
    }

    $parent = dirname($relativePath);
    $parent = $parent === '.' ? '' : content_download_host_normalize_relative_path($parent);
    $destination = $parent === '' ? $newName : ($parent . '/' . $newName);
    $result = notes_download_host_execute_api2('fileop', [
        'op' => 'rename',
        'sourcefiles' => content_download_host_abs_path_from_relative($relativePath),
        'destfiles' => content_download_host_abs_path_from_relative($destination),
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

function content_download_host_delete_entry(string $relativePath, string $entryType, bool $ignoreMissing = false): array
{
    $relativePath = content_download_host_normalize_relative_path($relativePath);
    if ($relativePath === '') {
        dent_error('حذف ریشه آپلودسنتر مجاز نیست.', 422);
    }

    $type = $entryType === 'dir' ? 'dir' : 'file';
    $operation = $type === 'dir' ? 'trash' : 'unlink';
    $result = notes_download_host_execute_api2('fileop', [
        'op' => $operation,
        'sourcefiles' => content_download_host_abs_path_from_relative($relativePath),
        'doubledecode' => '1',
    ]);

    $rows = is_array($result['data'] ?? null) ? $result['data'] : [];
    $row = is_array($rows[0] ?? null) ? $rows[0] : [];
    if ((int) ($row['result'] ?? 0) !== 1) {
        $error = trim((string) ($row['err'] ?? $result['error'] ?? 'حذف انجام نشد.'));
        $missing = stripos($error, 'No such file') !== false
            || stripos($error, 'No such file or directory') !== false
            || stripos($error, 'does not exist') !== false;
        if (!$ignoreMissing || !$missing) {
            dent_error($error, 502);
        }
    }

    return [
        'relativePath' => $relativePath,
        'type' => $type,
        'deleteMode' => $operation,
    ];
}

function content_download_host_proxy_preview(array $file): void
{
    $remoteUrl = trim((string) ($file['remotePublicUrl'] ?? ''));
    if ($remoteUrl === '') {
        dent_error('لینک مستقیم فایل روی هاست دانلود موجود نیست.', 404);
    }

    $mime = strtolower((string) ($file['mimeType'] ?? 'application/octet-stream'));
    $shouldProxy = str_starts_with($mime, 'text/') || $mime === 'application/json' || $mime === 'application/pdf';
    if (!$shouldProxy) {
        header('Location: ' . $remoteUrl, true, 302);
        exit;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 120,
            'ignore_errors' => true,
            'follow_location' => 1,
            'user_agent' => 'Dentistry1402TUMS-ContentTools/1.0',
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ]);
    $raw = @file_get_contents($remoteUrl, false, $context);
    if (!is_string($raw)) {
        dent_error('پیش‌نمایش فایل از هاست دانلود دریافت نشد.', 502);
    }

    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: private, max-age=0, no-cache');
    header('Content-Type: ' . $mime . '; charset=UTF-8');
    echo $raw;
    exit;
}
