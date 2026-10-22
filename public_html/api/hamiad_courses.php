<?php
declare(strict_types=1);
require_once __DIR__ . '/hamiad_http.php';
require_once __DIR__ . '/hamiad_storage.php';

function hamiad_course_list(): array
{
    $cookies = hamiad_load_cookies();
    if (!$cookies) {
        return [];
    }
    $result = [];
    foreach (['https://lms.tums.ac.ir/my/', 'https://lms.tums.ac.ir/my/courses.php'] as $url) {
        $response = hamiad_http_request('GET', $url, ['cookies' => $cookies, 'timeoutSec' => 16]);
        if (($response['status'] ?? 0) !== 200) {
            continue;
        }
        $document = new DOMDocument();
        $state = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"?>' . (string) ($response['body'] ?? ''));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($state);
        }
        foreach ($document->getElementsByTagName('a') as $a) {
            $href = (string) $a->getAttribute('href');
            $parsed = parse_url($href);
            if (!is_array($parsed) || ($parsed['path'] ?? '') !== '/course/view.php') {
                continue;
            }
            parse_str((string) ($parsed['query'] ?? ''), $params);
            $id = filter_var($params['id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                continue;
            }
            $host = (string) ($parsed['host'] ?? 'lms.tums.ac.ir');
            if ($host !== 'lms.tums.ac.ir') {
                continue;
            }
            $name = trim((string) $a->textContent);
            if ($name !== '') {
                $result[$id] = ['id' => $id, 'title' => mb_substr(preg_replace('/\s+/u', ' ', $name), 0, 150)];
            }
        }
    }
    return array_values($result);
}

function hamiad_course_items(int $courseId): array
{
    if ($courseId < 1) {
        throw new RuntimeException('شناسه درس نامعتبر است.');
    }
    $allowed = array_filter(hamiad_course_list(), static fn (array $entry): bool => $entry['id'] === $courseId);
    if (!$allowed) {
        throw new RuntimeException('درس در فهرست حساب متصل یافت نشد.');
    }
    $response = hamiad_http_request('GET', 'https://lms.tums.ac.ir/course/view.php?id=' . $courseId, ['cookies' => hamiad_load_cookies(), 'timeoutSec' => 18]);
    if (($response['status'] ?? 0) !== 200) {
        throw new RuntimeException('دریافت درس ممکن نشد.');
    }
    $document = new DOMDocument();
    $old = libxml_use_internal_errors(true);
    try { $document->loadHTML('<?xml encoding="UTF-8"?>' . (string) ($response['body'] ?? '')); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
    $items = [];
    foreach ($document->getElementsByTagName('a') as $link) {
        $href = (string) $link->getAttribute('href');
        $path = (string) (parse_url($href, PHP_URL_PATH) ?: '');
        $type = '';
        if (str_starts_with($path, '/mod/resource/')) $type = 'فایل';
        elseif (str_starts_with($path, '/mod/folder/')) $type = 'پوشه';
        elseif (str_starts_with($path, '/mod/assign/')) $type = 'تکلیف';
        elseif (str_starts_with($path, '/mod/page/')) $type = 'محتوای آموزشی';
        if (!$type) continue;
        $name = trim((string) $link->textContent);
        if ($name !== '') $items[] = ['type' => $type, 'title' => mb_substr($name, 0, 180)];
    }
    return $items;
}
