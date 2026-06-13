<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const ANALYTICS_SCHEMA_VERSION = 1;
const ANALYTICS_KEEP_DAILY_DAYS = 120;
const ANALYTICS_RECENT_LOGINS_LIMIT = 200;

function analytics_store_path(): string
{
    return dent_storage_path('analytics/store.json');
}

function analytics_default_store(): array
{
    return [
        'schemaVersion' => ANALYTICS_SCHEMA_VERSION,
        'totals' => [
            'pageViews' => 0,
            'logins' => 0,
            'downloads' => 0,
        ],
        'pages' => [],
        'families' => [],
        'downloads' => [
            'sources' => [],
            'targets' => [],
        ],
        'logins' => [
            'methods' => [],
            'cohorts' => [],
        ],
        'recentLogins' => [],
        'cohorts' => [],
        'daily' => [],
    ];
}

function analytics_normalize_store(array $store): array
{
    $defaults = analytics_default_store();
    $normalized = $defaults;

    foreach (['schemaVersion', 'totals', 'pages', 'families', 'downloads', 'logins', 'recentLogins', 'cohorts', 'daily'] as $key) {
        if (array_key_exists($key, $store)) {
            $normalized[$key] = $store[$key];
        }
    }

    if (!is_array($normalized['totals'])) {
        $normalized['totals'] = [];
    }
    $normalized['totals'] = array_merge($defaults['totals'], $normalized['totals']);
    foreach (['pageViews', 'logins', 'downloads'] as $key) {
        $normalized['totals'][$key] = max(0, (int) ($normalized['totals'][$key] ?? 0));
    }

    foreach (['pages', 'families', 'cohorts', 'daily'] as $key) {
        if (!is_array($normalized[$key])) {
            $normalized[$key] = [];
        }
    }

    if (!is_array($normalized['downloads'])) {
        $normalized['downloads'] = [];
    }
    $normalized['downloads'] = array_merge($defaults['downloads'], $normalized['downloads']);
    if (!is_array($normalized['downloads']['sources'] ?? null)) {
        $normalized['downloads']['sources'] = [];
    }
    if (!is_array($normalized['downloads']['targets'] ?? null)) {
        $normalized['downloads']['targets'] = [];
    }

    if (!is_array($normalized['logins'])) {
        $normalized['logins'] = [];
    }
    $normalized['logins'] = array_merge($defaults['logins'], $normalized['logins']);
    if (!is_array($normalized['logins']['methods'] ?? null)) {
        $normalized['logins']['methods'] = [];
    }
    if (!is_array($normalized['logins']['cohorts'] ?? null)) {
        $normalized['logins']['cohorts'] = [];
    }

    foreach ($normalized['pages'] as $path => $page) {
        if (!is_array($page)) {
            unset($normalized['pages'][$path]);
            continue;
        }
        $cleanPath = analytics_clean_path((string) ($page['path'] ?? $path));
        if ($cleanPath === '') {
            unset($normalized['pages'][$path]);
            continue;
        }
        $normalized['pages'][$cleanPath] = [
            'path' => $cleanPath,
            'title' => analytics_clean_label((string) ($page['title'] ?? ''), 160),
            'family' => analytics_page_family($cleanPath),
            'views' => max(0, (int) ($page['views'] ?? 0)),
            'lastViewedAt' => trim((string) ($page['lastViewedAt'] ?? '')),
        ];
        if ($cleanPath !== $path) {
            unset($normalized['pages'][$path]);
        }
    }

    foreach ($normalized['families'] as $family => $payload) {
        if (!is_array($payload)) {
            unset($normalized['families'][$family]);
            continue;
        }
        $cleanFamily = analytics_clean_family((string) $family);
        if ($cleanFamily === '') {
            unset($normalized['families'][$family]);
            continue;
        }
        $normalized['families'][$cleanFamily] = [
            'key' => $cleanFamily,
            'views' => max(0, (int) ($payload['views'] ?? 0)),
            'downloads' => max(0, (int) ($payload['downloads'] ?? 0)),
            'lastViewedAt' => trim((string) ($payload['lastViewedAt'] ?? '')),
            'lastDownloadAt' => trim((string) ($payload['lastDownloadAt'] ?? '')),
        ];
        if ($cleanFamily !== $family) {
            unset($normalized['families'][$family]);
        }
    }

    foreach ($normalized['downloads']['sources'] as $source => $count) {
        $cleanSource = analytics_clean_family((string) $source);
        if ($cleanSource === '') {
            unset($normalized['downloads']['sources'][$source]);
            continue;
        }
        $normalized['downloads']['sources'][$cleanSource] = max(0, (int) $count);
        if ($cleanSource !== $source) {
            unset($normalized['downloads']['sources'][$source]);
        }
    }

    foreach ($normalized['downloads']['targets'] as $key => $target) {
        if (!is_array($target)) {
            unset($normalized['downloads']['targets'][$key]);
            continue;
        }
        $targetKey = analytics_clean_bucket_key((string) ($target['key'] ?? $key));
        if ($targetKey === '') {
            unset($normalized['downloads']['targets'][$key]);
            continue;
        }
        $normalized['downloads']['targets'][$targetKey] = [
            'key' => $targetKey,
            'label' => analytics_clean_label((string) ($target['label'] ?? ''), 180),
            'href' => analytics_clean_href((string) ($target['href'] ?? '')),
            'sourceFamily' => analytics_clean_family((string) ($target['sourceFamily'] ?? '')),
            'count' => max(0, (int) ($target['count'] ?? 0)),
            'lastAt' => trim((string) ($target['lastAt'] ?? '')),
        ];
        if ($targetKey !== $key) {
            unset($normalized['downloads']['targets'][$key]);
        }
    }

    foreach ($normalized['logins']['methods'] as $method => $count) {
        $cleanMethod = analytics_clean_login_method((string) $method);
        if ($cleanMethod === '') {
            unset($normalized['logins']['methods'][$method]);
            continue;
        }
        $normalized['logins']['methods'][$cleanMethod] = max(0, (int) $count);
        if ($cleanMethod !== $method) {
            unset($normalized['logins']['methods'][$method]);
        }
    }

    foreach ($normalized['logins']['cohorts'] as $cohortKey => $count) {
        $cleanCohort = analytics_clean_cohort_key((string) $cohortKey);
        if ($cleanCohort === '') {
            unset($normalized['logins']['cohorts'][$cohortKey]);
            continue;
        }
        $normalized['logins']['cohorts'][$cleanCohort] = max(0, (int) $count);
        if ($cleanCohort !== $cohortKey) {
            unset($normalized['logins']['cohorts'][$cohortKey]);
        }
    }

    if (!is_array($normalized['recentLogins'])) {
        $normalized['recentLogins'] = [];
    }
    $recentLogins = [];
    foreach ($normalized['recentLogins'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $cleanEntry = analytics_clean_recent_login_entry($entry);
        if ($cleanEntry !== null) {
            $recentLogins[] = $cleanEntry;
        }
    }
    $normalized['recentLogins'] = array_slice($recentLogins, 0, ANALYTICS_RECENT_LOGINS_LIMIT);

    foreach ($normalized['cohorts'] as $cohortKey => $payload) {
        if (!is_array($payload)) {
            unset($normalized['cohorts'][$cohortKey]);
            continue;
        }
        $cleanCohort = analytics_clean_cohort_key((string) $cohortKey);
        if ($cleanCohort === '') {
            unset($normalized['cohorts'][$cohortKey]);
            continue;
        }
        $normalized['cohorts'][$cleanCohort] = [
            'key' => $cleanCohort,
            'pageViews' => max(0, (int) ($payload['pageViews'] ?? 0)),
            'logins' => max(0, (int) ($payload['logins'] ?? 0)),
            'downloads' => max(0, (int) ($payload['downloads'] ?? 0)),
            'lastAt' => trim((string) ($payload['lastAt'] ?? '')),
        ];
        if ($cleanCohort !== $cohortKey) {
            unset($normalized['cohorts'][$cohortKey]);
        }
    }

    $daily = [];
    foreach ($normalized['daily'] as $day => $payload) {
        $cleanDay = analytics_clean_day_key((string) $day);
        if ($cleanDay === '' || !is_array($payload)) {
            continue;
        }
        $daily[$cleanDay] = analytics_normalize_daily_bucket($cleanDay, $payload);
    }
    $normalized['daily'] = $daily;

    analytics_trim_daily_store($normalized);
    return $normalized;
}

function analytics_read_store(): array
{
    $raw = dent_read_json_file(analytics_store_path(), analytics_default_store());
    if (!is_array($raw)) {
        $raw = analytics_default_store();
    }
    return analytics_normalize_store($raw);
}

function analytics_update_store(callable $mutator): array
{
    $path = analytics_store_path();
    dent_ensure_directory(dirname($path));
    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        dent_error('ذخیره‌سازی آمار سایت در دسترس نیست.', 500);
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            dent_error('قفل ذخیره‌سازی آمار سایت آماده نشد.', 500);
        }

        $raw = stream_get_contents($handle);
        if (!is_string($raw) || trim($raw) === '') {
            $store = analytics_default_store();
        } else {
            $decoded = json_decode($raw, true);
            $store = is_array($decoded) ? analytics_normalize_store($decoded) : analytics_default_store();
        }

        $result = $mutator($store);
        if (is_array($result)) {
            $store = $result;
        }

        $store = analytics_normalize_store($store);
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $json = json_encode($store, $flags);
        if (!is_string($json) || $json === '') {
            dent_error('تولید JSON آمار سایت ناموفق بود.', 500);
        }

        rewind($handle);
        if (!ftruncate($handle, 0)) {
            dent_error('بازنویسی آمار سایت ناموفق بود.', 500);
        }
        if (@fwrite($handle, $json . PHP_EOL) === false) {
            dent_error('ذخیره آمار سایت ناموفق بود.', 500);
        }
        fflush($handle);
        return $store;
    } finally {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

function analytics_clean_path(string $path): string
{
    $path = dent_force_utf8($path);
    $path = trim($path);
    if ($path === '') {
        return '/';
    }

    if (preg_match('#^https?://#i', $path) === 1) {
        $parsed = parse_url($path);
        $path = (string) ($parsed['path'] ?? '/');
        if (!empty($parsed['query'])) {
            $path .= '?' . (string) $parsed['query'];
        }
    }

    if ($path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }

    $parts = explode('?', $path, 2);
    $cleanPath = preg_replace('#/+#', '/', trim((string) ($parts[0] ?? '/'))) ?? '/';
    if ($cleanPath === '') {
        $cleanPath = '/';
    }
    if ($cleanPath[0] !== '/') {
        $cleanPath = '/' . $cleanPath;
    }

    $query = '';
    if (isset($parts[1]) && trim((string) $parts[1]) !== '') {
        parse_str((string) $parts[1], $queryParams);
        if (is_array($queryParams) && $queryParams !== []) {
            ksort($queryParams, SORT_STRING);
            $filtered = [];
            foreach ($queryParams as $key => $value) {
                $cleanKey = analytics_clean_bucket_key((string) $key);
                if ($cleanKey === '') {
                    continue;
                }
                $filtered[$cleanKey] = analytics_clean_label(is_array($value) ? implode(',', $value) : (string) $value, 80);
            }
            if ($filtered !== []) {
                $query = http_build_query($filtered, '', '&', PHP_QUERY_RFC3986);
            }
        }
    }

    $full = $query !== '' ? ($cleanPath . '?' . $query) : $cleanPath;
    return analytics_clean_label($full, 240) ?: '/';
}

function analytics_clean_href(string $href): string
{
    $href = dent_force_utf8($href);
    $href = trim($href);
    if ($href === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $href) !== 1 && !str_starts_with($href, '/')) {
        return analytics_clean_label($href, 240);
    }
    return analytics_clean_label($href, 240);
}

function analytics_clean_label(string $value, int $maxLength): string
{
    return dent_clean_text($value, $maxLength);
}

function analytics_clean_bucket_key(string $value): string
{
    $value = strtolower(trim(dent_force_utf8($value)));
    $value = preg_replace('/[^a-z0-9:_\\/-]+/u', '-', $value) ?? '';
    return trim((string) preg_replace('/-{2,}/', '-', $value), '-');
}

function analytics_clean_family(string $value): string
{
    $value = analytics_clean_bucket_key($value);
    return $value === '' ? 'other' : $value;
}

function analytics_clean_login_method(string $value): string
{
    $method = analytics_clean_bucket_key($value);
    return in_array($method, ['password', 'otp', 'external-signup'], true) ? $method : 'other';
}

function analytics_clean_cohort_key(string $value): string
{
    $raw = trim(dent_force_utf8($value));
    if ($raw === '') {
        return '';
    }
    if (function_exists('dent_clean_cohort_key')) {
        return dent_clean_cohort_key($raw);
    }
    return analytics_clean_bucket_key($raw);
}

function analytics_clean_day_key(string $value): string
{
    $value = trim($value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
}

function analytics_page_family(string $path): string
{
    $cleanPath = analytics_clean_path($path);
    $pathOnly = explode('?', $cleanPath, 2)[0];
    if ($pathOnly === '/' || $pathOnly === '') {
        return 'home';
    }

    $segments = array_values(array_filter(explode('/', trim($pathOnly, '/')), static function ($segment): bool {
        return trim((string) $segment) !== '';
    }));
    $head = strtolower((string) ($segments[0] ?? 'home'));

    return match ($head) {
        'app' => 'app',
        'account' => 'account',
        'chat' => 'chat',
        'exams' => 'exams',
        'notes' => 'notes',
        'forms' => 'forms',
        'grades' => 'grades',
        'resources' => 'resources',
        'buy', 'payments' => 'buy',
        'files' => 'files',
        'paste' => 'paste',
        'html', 'html-uploader' => 'html',
        'navid' => 'navid',
        'offline.html', 'offline' => 'offline',
        default => $head !== '' ? $head : 'other',
    };
}

function analytics_family_label(string $family): string
{
    return match (analytics_clean_family($family)) {
        'home' => 'خانه',
        'app' => 'اپ',
        'account' => 'حساب',
        'chat' => 'چت',
        'exams' => 'آزمون‌ها',
        'notes' => 'منابع',
        'forms' => 'فرم‌ها',
        'grades' => 'نمرات',
        'resources' => 'منابع عمومی',
        'buy' => 'خرید',
        'files' => 'فایل سنتر',
        'paste' => 'پیست',
        'html' => 'HTML',
        'navid' => 'نوید',
        'offline' => 'آفلاین',
        default => 'سایر',
    };
}

function analytics_login_method_label(string $method): string
{
    return match (analytics_clean_login_method($method)) {
        'password' => 'رمز عبور',
        'otp' => 'پیامکی',
        'external-signup' => 'ثبت‌نام آزمون',
        default => 'سایر',
    };
}

function analytics_normalize_daily_bucket(string $day, array $payload): array
{
    $bucket = [
        'day' => $day,
        'pageViews' => max(0, (int) ($payload['pageViews'] ?? 0)),
        'logins' => max(0, (int) ($payload['logins'] ?? 0)),
        'downloads' => max(0, (int) ($payload['downloads'] ?? 0)),
        'pages' => [],
        'families' => [],
        'downloadSources' => [],
        'downloadTargets' => [],
        'cohorts' => [],
        'visitorKeys' => [],
        'visitKeys' => [],
        'loginUsers' => [],
    ];

    foreach (['pages', 'families', 'downloadSources', 'downloadTargets'] as $mapKey) {
        $source = is_array($payload[$mapKey] ?? null) ? $payload[$mapKey] : [];
        foreach ($source as $key => $count) {
            $cleanKey = $mapKey === 'pages' ? analytics_clean_path((string) $key) : analytics_clean_bucket_key((string) $key);
            if ($cleanKey === '') {
                continue;
            }
            $bucket[$mapKey][$cleanKey] = max(0, (int) $count);
        }
    }

    $cohortSource = is_array($payload['cohorts'] ?? null) ? $payload['cohorts'] : [];
    foreach ($cohortSource as $cohortKey => $cohortPayload) {
        $cleanCohort = analytics_clean_cohort_key((string) $cohortKey);
        if ($cleanCohort === '' || !is_array($cohortPayload)) {
            continue;
        }
        $bucket['cohorts'][$cleanCohort] = [
            'pageViews' => max(0, (int) ($cohortPayload['pageViews'] ?? 0)),
            'logins' => max(0, (int) ($cohortPayload['logins'] ?? 0)),
            'downloads' => max(0, (int) ($cohortPayload['downloads'] ?? 0)),
        ];
    }

    foreach (['visitorKeys', 'visitKeys', 'loginUsers'] as $setKey) {
        $source = is_array($payload[$setKey] ?? null) ? $payload[$setKey] : [];
        foreach ($source as $key => $value) {
            $cleanKey = analytics_clean_bucket_key((string) $key);
            if ($cleanKey !== '') {
                $bucket[$setKey][$cleanKey] = 1;
            }
        }
    }

    return $bucket;
}

function analytics_trim_daily_store(array &$store): void
{
    $cutoff = date('Y-m-d', strtotime('-' . (ANALYTICS_KEEP_DAILY_DAYS - 1) . ' days'));
    foreach (array_keys($store['daily']) as $day) {
        if ((string) $day < $cutoff) {
            unset($store['daily'][$day]);
        }
    }
}

function analytics_ensure_daily_bucket(array &$store, string $day): array
{
    $current = is_array($store['daily'][$day] ?? null) ? $store['daily'][$day] : [];
    $store['daily'][$day] = analytics_normalize_daily_bucket($day, $current);
    return $store['daily'][$day];
}

function analytics_increment_counter(array &$map, string $key, int $amount = 1): void
{
    if ($key === '') {
        return;
    }
    $map[$key] = max(0, (int) ($map[$key] ?? 0)) + max(0, $amount);
}

function analytics_mark_set_value(array &$map, string $key): void
{
    if ($key === '') {
        return;
    }
    $map[$key] = 1;
}

function analytics_download_target_key(string $href, string $label): string
{
    $seed = trim($href) !== '' ? trim($href) : trim($label);
    if ($seed === '') {
        $seed = dent_iso_now();
    }
    return 'dl:' . substr(hash('sha256', $seed), 0, 20);
}

function analytics_event_now_day(): string
{
    return date('Y-m-d');
}

function analytics_record_page_view(array $payload): void
{
    $path = analytics_clean_path((string) ($payload['path'] ?? '/'));
    $title = analytics_clean_label((string) ($payload['title'] ?? ''), 160);
    $family = analytics_page_family($path);
    $cohortKey = analytics_clean_cohort_key((string) ($payload['cohort'] ?? ''));
    $visitorKey = analytics_clean_bucket_key((string) ($payload['visitorId'] ?? ''));
    $visitKey = analytics_clean_bucket_key((string) ($payload['visitId'] ?? ''));
    $now = dent_iso_now();
    $day = analytics_event_now_day();

    analytics_update_store(static function (array $store) use ($path, $title, $family, $cohortKey, $visitorKey, $visitKey, $now, $day): array {
        $store['totals']['pageViews'] = max(0, (int) ($store['totals']['pageViews'] ?? 0)) + 1;

        $page = is_array($store['pages'][$path] ?? null) ? $store['pages'][$path] : [
            'path' => $path,
            'title' => '',
            'family' => $family,
            'views' => 0,
            'lastViewedAt' => '',
        ];
        $page['title'] = $title !== '' ? $title : (string) ($page['title'] ?? '');
        $page['family'] = $family;
        $page['views'] = max(0, (int) ($page['views'] ?? 0)) + 1;
        $page['lastViewedAt'] = $now;
        $store['pages'][$path] = $page;

        $familyRow = is_array($store['families'][$family] ?? null) ? $store['families'][$family] : [
            'key' => $family,
            'views' => 0,
            'downloads' => 0,
            'lastViewedAt' => '',
            'lastDownloadAt' => '',
        ];
        $familyRow['views'] = max(0, (int) ($familyRow['views'] ?? 0)) + 1;
        $familyRow['lastViewedAt'] = $now;
        $store['families'][$family] = $familyRow;

        $bucket = analytics_ensure_daily_bucket($store, $day);
        $bucket['pageViews'] = max(0, (int) ($bucket['pageViews'] ?? 0)) + 1;
        analytics_increment_counter($bucket['pages'], $path);
        analytics_increment_counter($bucket['families'], $family);
        analytics_mark_set_value($bucket['visitorKeys'], $visitorKey);
        analytics_mark_set_value($bucket['visitKeys'], $visitKey);
        if ($cohortKey !== '') {
            $cohortBucket = is_array($bucket['cohorts'][$cohortKey] ?? null) ? $bucket['cohorts'][$cohortKey] : [
                'pageViews' => 0,
                'logins' => 0,
                'downloads' => 0,
            ];
            $cohortBucket['pageViews'] = max(0, (int) ($cohortBucket['pageViews'] ?? 0)) + 1;
            $bucket['cohorts'][$cohortKey] = $cohortBucket;

            $cohortRow = is_array($store['cohorts'][$cohortKey] ?? null) ? $store['cohorts'][$cohortKey] : [
                'key' => $cohortKey,
                'pageViews' => 0,
                'logins' => 0,
                'downloads' => 0,
                'lastAt' => '',
            ];
            $cohortRow['pageViews'] = max(0, (int) ($cohortRow['pageViews'] ?? 0)) + 1;
            $cohortRow['lastAt'] = $now;
            $store['cohorts'][$cohortKey] = $cohortRow;
        }
        $store['daily'][$day] = $bucket;

        analytics_trim_daily_store($store);
        return $store;
    });
}

function analytics_record_download(array $payload): void
{
    $href = analytics_clean_href((string) ($payload['href'] ?? ''));
    $label = analytics_clean_label((string) ($payload['label'] ?? ''), 180);
    $sourceFamily = analytics_clean_family((string) ($payload['sourceFamily'] ?? analytics_page_family((string) ($payload['sourcePath'] ?? '/'))));
    $cohortKey = analytics_clean_cohort_key((string) ($payload['cohort'] ?? ''));
    $targetKey = analytics_download_target_key($href, $label);
    $now = dent_iso_now();
    $day = analytics_event_now_day();

    analytics_update_store(static function (array $store) use ($href, $label, $sourceFamily, $cohortKey, $targetKey, $now, $day): array {
        $store['totals']['downloads'] = max(0, (int) ($store['totals']['downloads'] ?? 0)) + 1;
        analytics_increment_counter($store['downloads']['sources'], $sourceFamily);

        $target = is_array($store['downloads']['targets'][$targetKey] ?? null) ? $store['downloads']['targets'][$targetKey] : [
            'key' => $targetKey,
            'label' => '',
            'href' => '',
            'sourceFamily' => $sourceFamily,
            'count' => 0,
            'lastAt' => '',
        ];
        $target['label'] = $label !== '' ? $label : (string) ($target['label'] ?? '');
        $target['href'] = $href !== '' ? $href : (string) ($target['href'] ?? '');
        $target['sourceFamily'] = $sourceFamily;
        $target['count'] = max(0, (int) ($target['count'] ?? 0)) + 1;
        $target['lastAt'] = $now;
        $store['downloads']['targets'][$targetKey] = $target;

        $familyRow = is_array($store['families'][$sourceFamily] ?? null) ? $store['families'][$sourceFamily] : [
            'key' => $sourceFamily,
            'views' => 0,
            'downloads' => 0,
            'lastViewedAt' => '',
            'lastDownloadAt' => '',
        ];
        $familyRow['downloads'] = max(0, (int) ($familyRow['downloads'] ?? 0)) + 1;
        $familyRow['lastDownloadAt'] = $now;
        $store['families'][$sourceFamily] = $familyRow;

        $bucket = analytics_ensure_daily_bucket($store, $day);
        $bucket['downloads'] = max(0, (int) ($bucket['downloads'] ?? 0)) + 1;
        analytics_increment_counter($bucket['downloadSources'], $sourceFamily);
        analytics_increment_counter($bucket['downloadTargets'], $targetKey);
        if ($cohortKey !== '') {
            $cohortBucket = is_array($bucket['cohorts'][$cohortKey] ?? null) ? $bucket['cohorts'][$cohortKey] : [
                'pageViews' => 0,
                'logins' => 0,
                'downloads' => 0,
            ];
            $cohortBucket['downloads'] = max(0, (int) ($cohortBucket['downloads'] ?? 0)) + 1;
            $bucket['cohorts'][$cohortKey] = $cohortBucket;

            $cohortRow = is_array($store['cohorts'][$cohortKey] ?? null) ? $store['cohorts'][$cohortKey] : [
                'key' => $cohortKey,
                'pageViews' => 0,
                'logins' => 0,
                'downloads' => 0,
                'lastAt' => '',
            ];
            $cohortRow['downloads'] = max(0, (int) ($cohortRow['downloads'] ?? 0)) + 1;
            $cohortRow['lastAt'] = $now;
            $store['cohorts'][$cohortKey] = $cohortRow;
        }
        $store['daily'][$day] = $bucket;

        analytics_trim_daily_store($store);
        return $store;
    });
}

function analytics_clean_recent_login_entry(array $entry): ?array
{
    $studentNumber = analytics_clean_bucket_key((string) ($entry['studentNumber'] ?? ''));
    $at = trim((string) ($entry['at'] ?? ''));
    if ($studentNumber === '' || $at === '') {
        return null;
    }

    return [
        'studentNumber' => $studentNumber,
        'name' => analytics_clean_label((string) ($entry['name'] ?? ''), 120),
        'cohortKey' => analytics_clean_cohort_key((string) ($entry['cohortKey'] ?? '')),
        'role' => analytics_clean_bucket_key((string) ($entry['role'] ?? 'student')),
        'method' => analytics_clean_login_method((string) ($entry['method'] ?? '')),
        'at' => $at,
    ];
}

function analytics_record_login(array $user, string $method): void
{
    $studentNumber = analytics_clean_bucket_key((string) ($user['studentNumber'] ?? ''));
    $cohortKey = analytics_clean_cohort_key((string) ($user['cohortKey'] ?? (($user['cohort']['key'] ?? ''))));
    $cleanMethod = analytics_clean_login_method($method);
    $name = analytics_clean_label((string) ($user['name'] ?? ''), 120);
    $role = analytics_clean_bucket_key((string) ($user['role'] ?? 'student'));
    $now = dent_iso_now();
    $day = analytics_event_now_day();

    analytics_update_store(static function (array $store) use ($studentNumber, $cohortKey, $cleanMethod, $name, $role, $now, $day): array {
        $store['totals']['logins'] = max(0, (int) ($store['totals']['logins'] ?? 0)) + 1;
        analytics_increment_counter($store['logins']['methods'], $cleanMethod);
        if ($cohortKey !== '') {
            analytics_increment_counter($store['logins']['cohorts'], $cohortKey);
        }

        if (!is_array($store['recentLogins'] ?? null)) {
            $store['recentLogins'] = [];
        }
        array_unshift($store['recentLogins'], [
            'studentNumber' => $studentNumber,
            'name' => $name,
            'cohortKey' => $cohortKey,
            'role' => $role,
            'method' => $cleanMethod,
            'at' => $now,
        ]);
        $store['recentLogins'] = array_slice($store['recentLogins'], 0, ANALYTICS_RECENT_LOGINS_LIMIT);

        $bucket = analytics_ensure_daily_bucket($store, $day);
        $bucket['logins'] = max(0, (int) ($bucket['logins'] ?? 0)) + 1;
        analytics_mark_set_value($bucket['loginUsers'], $studentNumber);
        if ($cohortKey !== '') {
            $cohortBucket = is_array($bucket['cohorts'][$cohortKey] ?? null) ? $bucket['cohorts'][$cohortKey] : [
                'pageViews' => 0,
                'logins' => 0,
                'downloads' => 0,
            ];
            $cohortBucket['logins'] = max(0, (int) ($cohortBucket['logins'] ?? 0)) + 1;
            $bucket['cohorts'][$cohortKey] = $cohortBucket;

            $cohortRow = is_array($store['cohorts'][$cohortKey] ?? null) ? $store['cohorts'][$cohortKey] : [
                'key' => $cohortKey,
                'pageViews' => 0,
                'logins' => 0,
                'downloads' => 0,
                'lastAt' => '',
            ];
            $cohortRow['logins'] = max(0, (int) ($cohortRow['logins'] ?? 0)) + 1;
            $cohortRow['lastAt'] = $now;
            $store['cohorts'][$cohortKey] = $cohortRow;
        }
        $store['daily'][$day] = $bucket;

        analytics_trim_daily_store($store);
        return $store;
    });
}

function analytics_series_days(int $days): array
{
    $result = [];
    $days = max(1, min(60, $days));
    for ($offset = $days - 1; $offset >= 0; $offset -= 1) {
        $result[] = date('Y-m-d', strtotime('-' . $offset . ' days'));
    }
    return $result;
}

function analytics_daily_metric_series(array $store, string $metricKey, int $days, ?callable $resolver = null): array
{
    $series = [];
    foreach (analytics_series_days($days) as $day) {
        $bucket = is_array($store['daily'][$day] ?? null) ? $store['daily'][$day] : null;
        $value = $resolver !== null
            ? max(0, (int) $resolver($bucket, $day))
            : max(0, (int) ($bucket[$metricKey] ?? 0));
        $series[] = [
            'day' => $day,
            'label' => analytics_day_short_label($day),
            'fullLabel' => analytics_day_full_label($day),
            'value' => $value,
        ];
    }
    return $series;
}

function analytics_day_short_label(string $day): string
{
    $timestamp = strtotime($day . ' 00:00:00');
    if ($timestamp === false) {
        return $day;
    }
    return function_exists('dent_to_fa_digits')
        ? dent_to_fa_digits(date('m/d', $timestamp))
        : date('m/d', $timestamp);
}

function analytics_day_full_label(string $day): string
{
    $timestamp = strtotime($day . ' 00:00:00');
    if ($timestamp === false) {
        return $day;
    }
    $label = function_exists('IntlDateFormatter')
        ? null
        : null;
    if (class_exists('IntlDateFormatter')) {
        $formatter = new IntlDateFormatter('fa_IR@calendar=persian', IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE, 'Asia/Tehran', IntlDateFormatter::TRADITIONAL);
        if ($formatter) {
            $formatted = $formatter->format($timestamp);
            if (is_string($formatted) && trim($formatted) !== '') {
                return $formatted;
            }
        }
    }
    return function_exists('dent_to_fa_digits')
        ? dent_to_fa_digits(date('Y/m/d', $timestamp))
        : date('Y/m/d', $timestamp);
}

function analytics_sum_daily_metric(array $store, string $metricKey, int $days): int
{
    $total = 0;
    foreach (analytics_series_days($days) as $day) {
        $bucket = is_array($store['daily'][$day] ?? null) ? $store['daily'][$day] : [];
        $total += max(0, (int) ($bucket[$metricKey] ?? 0));
    }
    return $total;
}

function analytics_unique_daily_keys_count(array $store, string $setKey, int $days): int
{
    $unique = [];
    foreach (analytics_series_days($days) as $day) {
        $bucket = is_array($store['daily'][$day] ?? null) ? $store['daily'][$day] : [];
        $items = is_array($bucket[$setKey] ?? null) ? $bucket[$setKey] : [];
        foreach ($items as $key => $_value) {
            $unique[(string) $key] = true;
        }
    }
    return count($unique);
}

function analytics_sort_desc(array $items, string $field): array
{
    usort($items, static function (array $left, array $right) use ($field): int {
        return max(0, (int) ($right[$field] ?? 0)) <=> max(0, (int) ($left[$field] ?? 0));
    });
    return $items;
}

function analytics_sum_daily_family_views(array $store, string $familyKey, int $days): int
{
    $cleanFamily = analytics_clean_family($familyKey);
    if ($cleanFamily === '') {
        return 0;
    }

    $total = 0;
    foreach (analytics_series_days($days) as $day) {
        $bucket = is_array($store['daily'][$day] ?? null) ? $store['daily'][$day] : [];
        $families = is_array($bucket['families'] ?? null) ? $bucket['families'] : [];
        $total += max(0, (int) ($families[$cleanFamily] ?? 0));
    }

    return $total;
}

function analytics_exam_question_count(array $exam): int
{
    $questions = $exam['questions'] ?? null;
    if (is_array($questions)) {
        return count($questions);
    }

    return max(0, (int) ($exam['questionCount'] ?? 0));
}

function analytics_exam_counts_toward_stats(array $exam): bool
{
    if (!array_key_exists('countsTowardStats', $exam)) {
        return true;
    }

    return (bool) $exam['countsTowardStats'];
}

function analytics_exam_collection_ids(array $examsStore): array
{
    $ids = [];
    $settings = is_array($examsStore['courseSettings'] ?? null) ? $examsStore['courseSettings'] : [];
    foreach ($settings as $setting) {
        if (!is_array($setting)) {
            continue;
        }

        $primaryId = max(0, (int) ($setting['collectionId'] ?? ($setting['collection_id'] ?? 0)));
        if ($primaryId > 0) {
            $ids[$primaryId] = true;
        }

        $legacyIds = is_array($setting['legacyCollectionIds'] ?? null)
            ? $setting['legacyCollectionIds']
            : (is_array($setting['legacy_collection_ids'] ?? null) ? $setting['legacy_collection_ids'] : []);
        foreach ($legacyIds as $legacyId) {
            $cleanId = max(0, (int) $legacyId);
            if ($cleanId > 0) {
                $ids[$cleanId] = true;
            }
        }
    }

    return array_map('intval', array_keys($ids));
}

function analytics_exam_paid_participant_key(array $order): string
{
    $candidates = [
        (string) ($order['user_id'] ?? ''),
        (string) ($order['payer_student_number'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        $normalized = dent_normalize_student_number($candidate);
        if ($normalized !== '') {
            return $normalized;
        }
    }

    $orderId = max(0, (int) ($order['id'] ?? 0));
    return $orderId > 0 ? 'order-' . $orderId : '';
}

function analytics_build_exam_summary(array $analyticsStore, array $examsStore, array $paymentsStore): array
{
    $courseCount = 0;
    $paidCourseCount = 0;
    $examCount = 0;
    $questionCount = 0;

    foreach (dent_exams_catalogs() as $catalogKey => $catalog) {
        if (!is_array($catalog)) {
            continue;
        }

        $courses = is_array($catalog['courses'] ?? null) ? $catalog['courses'] : [];
        foreach ($courses as $courseSlug => $course) {
            if (!is_array($course)) {
                continue;
            }

            $courseCount++;
            $setting = dent_exams_course_setting($examsStore, (string) $catalogKey, (string) $courseSlug);
            if ((string) ($setting['paymentMode'] ?? 'free') === 'paid') {
                $paidCourseCount++;
            }

            $exams = is_array($course['exams'] ?? null) ? $course['exams'] : [];
            foreach ($exams as $exam) {
                if (!is_array($exam) || !analytics_exam_counts_toward_stats($exam)) {
                    continue;
                }

                $examCount++;
                $questionCount += analytics_exam_question_count($exam);
            }
        }
    }

    $startedCount = 0;
    $submittedCount = 0;
    $percentCount = 0;
    $percentTotal = 0.0;
    $records = is_array($examsStore['examRecords'] ?? null) ? $examsStore['examRecords'] : [];
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }

        $normalizedRecord = dent_exams_normalize_exam_record($record);
        $participants = [];

        foreach (is_array($normalizedRecord['activityByUser'] ?? null) ? $normalizedRecord['activityByUser'] : [] as $participantKey => $activity) {
            if (!is_array($activity)) {
                continue;
            }

            $cleanParticipant = dent_exams_clean_participant_key((string) $participantKey);
            if ($cleanParticipant !== '') {
                $participants[$cleanParticipant] = true;
            }
        }

        foreach (is_array($normalizedRecord['flagsByUser'] ?? null) ? $normalizedRecord['flagsByUser'] : [] as $participantKey => $indexes) {
            $cleanParticipant = dent_exams_clean_participant_key((string) $participantKey);
            if ($cleanParticipant === '' || !is_array($indexes) || $indexes === []) {
                continue;
            }

            $participants[$cleanParticipant] = true;
        }

        foreach (is_array($normalizedRecord['reportsByUser'] ?? null) ? $normalizedRecord['reportsByUser'] : [] as $participantKey => $report) {
            if (!is_array($report)) {
                continue;
            }

            $cleanParticipant = dent_exams_clean_participant_key((string) $participantKey);
            if ($cleanParticipant === '') {
                continue;
            }

            $participants[$cleanParticipant] = true;
            $submittedCount++;
            $percentCount++;
            $percentTotal += dent_exams_normalize_percent($report['percent'] ?? 0);
        }

        $startedCount += count($participants);
    }

    $collectionIdMap = array_fill_keys(analytics_exam_collection_ids($examsStore), true);
    $purchasers = [];
    $paidOrderCount = 0;
    $receivedAmount = 0;
    foreach (is_array($paymentsStore['orders'] ?? null) ? $paymentsStore['orders'] : [] as $order) {
        if (!is_array($order)) {
            continue;
        }

        $extra = is_array($order['extra_form_data'] ?? null) ? $order['extra_form_data'] : [];
        $collectionId = max(0, (int) ($extra['collection_id'] ?? 0));
        if ((string) ($extra['_source'] ?? '') !== 'collection' || $collectionId <= 0 || !isset($collectionIdMap[$collectionId])) {
            continue;
        }
        if ((string) ($order['status'] ?? '') !== PAYMENTS_ORDER_STATUS_SUCCESS) {
            continue;
        }

        $paidOrderCount++;
        $receivedAmount += max(0, (int) ($order['amount'] ?? 0));

        $participantKey = analytics_exam_paid_participant_key($order);
        if ($participantKey !== '') {
            $purchasers[$participantKey] = true;
        }
    }

    $examFamily = is_array($analyticsStore['families']['exams'] ?? null) ? $analyticsStore['families']['exams'] : [];

    return [
        'courseCount' => $courseCount,
        'paidCourseCount' => $paidCourseCount,
        'examCount' => $examCount,
        'questionCount' => $questionCount,
        'startedCount' => $startedCount,
        'submittedCount' => $submittedCount,
        'purchaserCount' => count($purchasers),
        'paidOrderCount' => $paidOrderCount,
        'receivedAmount' => $receivedAmount,
        'averagePercent' => $percentCount > 0 ? round($percentTotal / $percentCount, 1) : null,
        'pageViews' => max(0, (int) ($examFamily['views'] ?? 0)),
        'pageViews30d' => analytics_sum_daily_family_views($analyticsStore, 'exams', 30),
    ];
}

function analytics_recent_logins_payload(array $store, array $visibleCohorts, int $limit): array
{
    $cohortKeys = [];
    foreach ($visibleCohorts as $cohort) {
        if (!is_array($cohort)) {
            continue;
        }
        $cohortKey = analytics_clean_cohort_key((string) ($cohort['key'] ?? ''));
        if ($cohortKey !== '') {
            $cohortKeys[$cohortKey] = true;
        }
    }

    $result = [];
    foreach (($store['recentLogins'] ?? []) as $entry) {
        if (!is_array($entry) || count($result) >= $limit) {
            continue;
        }

        $cohortKey = (string) ($entry['cohortKey'] ?? '');
        if ($cohortKeys !== [] && $cohortKey !== '' && !isset($cohortKeys[$cohortKey])) {
            continue;
        }

        $cohortRecord = $cohortKey !== '' ? dent_cohort_record($cohortKey) : null;
        $role = (string) ($entry['role'] ?? 'student');

        $result[] = [
            'studentNumber' => (string) ($entry['studentNumber'] ?? ''),
            'name' => (string) ($entry['name'] ?? ''),
            'cohortKey' => $cohortKey,
            'cohortLabel' => $cohortRecord !== null
                ? (string) ($cohortRecord['shortTitle'] ?? $cohortRecord['title'] ?? $cohortKey)
                : $cohortKey,
            'role' => $role,
            'roleLabel' => function_exists('dent_role_label') ? dent_role_label($role) : $role,
            'method' => (string) ($entry['method'] ?? ''),
            'methodLabel' => analytics_login_method_label((string) ($entry['method'] ?? '')),
            'at' => (string) ($entry['at'] ?? ''),
        ];
    }

    return $result;
}

function analytics_build_owner_dashboard(array $viewer): array
{
    require_once __DIR__ . '/auth_store.php';
    require_once __DIR__ . '/content_tools_store.php';
    require_once __DIR__ . '/html_uploader_store.php';
    require_once __DIR__ . '/exams_store.php';
    require_once __DIR__ . '/payments_store.php';

    $store = analytics_read_store();
    $users = dent_list_public_users(false);
    $today = analytics_event_now_day();
    $todayBucket = is_array($store['daily'][$today] ?? null) ? $store['daily'][$today] : analytics_normalize_daily_bucket($today, []);
    $cohorts = dent_visible_cohorts_for_user($viewer);
    $cohortUserCounts = [];
    foreach ($users as $user) {
        if (!is_array($user)) {
            continue;
        }
        $cohortKey = analytics_clean_cohort_key((string) ($user['cohortKey'] ?? ''));
        if ($cohortKey === '') {
            continue;
        }
        if (!isset($cohortUserCounts[$cohortKey])) {
            $cohortUserCounts[$cohortKey] = [
                'totalUsers' => 0,
                'representatives' => 0,
            ];
        }
        $cohortUserCounts[$cohortKey]['totalUsers']++;
        if (in_array((string) ($user['role'] ?? ''), ['representative', 'prosthesis_representative'], true)) {
            $cohortUserCounts[$cohortKey]['representatives']++;
        }
    }

    $families = [];
    foreach ($store['families'] as $familyKey => $payload) {
        if (!is_array($payload)) {
            continue;
        }
        $families[] = [
            'key' => analytics_clean_family((string) $familyKey),
            'label' => analytics_family_label((string) $familyKey),
            'views' => max(0, (int) ($payload['views'] ?? 0)),
            'downloads' => max(0, (int) ($payload['downloads'] ?? 0)),
            'lastViewedAt' => trim((string) ($payload['lastViewedAt'] ?? '')),
            'lastDownloadAt' => trim((string) ($payload['lastDownloadAt'] ?? '')),
        ];
    }
    $families = array_slice(analytics_sort_desc($families, 'views'), 0, 8);

    $pages = [];
    foreach ($store['pages'] as $path => $payload) {
        if (!is_array($payload)) {
            continue;
        }
        $pages[] = [
            'path' => analytics_clean_path((string) ($payload['path'] ?? $path)),
            'title' => analytics_clean_label((string) ($payload['title'] ?? ''), 160),
            'family' => analytics_clean_family((string) ($payload['family'] ?? analytics_page_family((string) $path))),
            'familyLabel' => analytics_family_label((string) ($payload['family'] ?? analytics_page_family((string) $path))),
            'views' => max(0, (int) ($payload['views'] ?? 0)),
            'lastViewedAt' => trim((string) ($payload['lastViewedAt'] ?? '')),
        ];
    }
    $pages = array_slice(analytics_sort_desc($pages, 'views'), 0, 10);

    $downloads = [];
    foreach (($store['downloads']['targets'] ?? []) as $targetKey => $payload) {
        if (!is_array($payload)) {
            continue;
        }
        $downloads[] = [
            'key' => analytics_clean_bucket_key((string) ($payload['key'] ?? $targetKey)),
            'label' => analytics_clean_label((string) ($payload['label'] ?? ''), 180),
            'href' => analytics_clean_href((string) ($payload['href'] ?? '')),
            'sourceFamily' => analytics_clean_family((string) ($payload['sourceFamily'] ?? 'other')),
            'sourceLabel' => analytics_family_label((string) ($payload['sourceFamily'] ?? 'other')),
            'count' => max(0, (int) ($payload['count'] ?? 0)),
            'lastAt' => trim((string) ($payload['lastAt'] ?? '')),
        ];
    }
    $downloads = array_slice(analytics_sort_desc($downloads, 'count'), 0, 10);

    $loginMethods = [];
    foreach (($store['logins']['methods'] ?? []) as $method => $count) {
        $loginMethods[] = [
            'key' => analytics_clean_login_method((string) $method),
            'label' => analytics_login_method_label((string) $method),
            'count' => max(0, (int) $count),
        ];
    }
    $loginMethods = analytics_sort_desc($loginMethods, 'count');

    $cohortRows = [];
    foreach ($cohorts as $cohort) {
        $cohortKey = analytics_clean_cohort_key((string) ($cohort['key'] ?? ''));
        if ($cohortKey === '') {
            continue;
        }
        $pageViews30d = 0;
        $logins30d = 0;
        $downloads30d = 0;
        foreach (analytics_series_days($store['daily'] === [] ? 1 : 30) as $day) {
            $dayCohort = is_array($store['daily'][$day]['cohorts'][$cohortKey] ?? null) ? $store['daily'][$day]['cohorts'][$cohortKey] : [];
            $pageViews30d += max(0, (int) ($dayCohort['pageViews'] ?? 0));
            $logins30d += max(0, (int) ($dayCohort['logins'] ?? 0));
            $downloads30d += max(0, (int) ($dayCohort['downloads'] ?? 0));
        }

        $counts = $cohortUserCounts[$cohortKey] ?? ['totalUsers' => 0, 'representatives' => 0];
        $cohortRows[] = [
            'key' => $cohortKey,
            'title' => (string) ($cohort['title'] ?? $cohortKey),
            'shortTitle' => (string) ($cohort['shortTitle'] ?? $cohortKey),
            'productType' => (string) ($cohort['productType'] ?? ''),
            'totalUsers' => (int) ($counts['totalUsers'] ?? 0),
            'representatives' => (int) ($counts['representatives'] ?? 0),
            'pageViews30d' => $pageViews30d,
            'logins30d' => $logins30d,
            'downloads30d' => $downloads30d,
        ];
    }

    $contentSummary = function_exists('content_read_store')
        ? content_storage_summary(content_read_store())
        : [];
    $htmlSummary = function_exists('html_uploader_read_store')
        ? html_uploader_owner_summary(html_uploader_read_store())
        : [];
    $examsSummary = analytics_build_exam_summary($store, dent_exams_read_store(), payments_read_store());

    return [
        'generatedAt' => dent_iso_now(),
        'totals' => [
            'totalUsers' => count($users),
            'pageViews' => max(0, (int) ($store['totals']['pageViews'] ?? 0)),
            'pageViewsToday' => max(0, (int) ($todayBucket['pageViews'] ?? 0)),
            'pageViews30d' => analytics_sum_daily_metric($store, 'pageViews', 30),
            'logins' => max(0, (int) ($store['totals']['logins'] ?? 0)),
            'loginsToday' => max(0, (int) ($todayBucket['logins'] ?? 0)),
            'logins30d' => analytics_sum_daily_metric($store, 'logins', 30),
            'downloads' => max(0, (int) ($store['totals']['downloads'] ?? 0)),
            'downloadsToday' => max(0, (int) ($todayBucket['downloads'] ?? 0)),
            'downloads30d' => analytics_sum_daily_metric($store, 'downloads', 30),
            'uniqueVisitorsToday' => count((array) ($todayBucket['visitorKeys'] ?? [])),
            'uniqueVisitors30d' => analytics_unique_daily_keys_count($store, 'visitorKeys', 30),
            'uniqueLoginUsersToday' => count((array) ($todayBucket['loginUsers'] ?? [])),
            'uniqueLoginUsers30d' => analytics_unique_daily_keys_count($store, 'loginUsers', 30),
            'contentToolsDownloads' => max(0, (int) ($contentSummary['downloadCount'] ?? 0)),
            'pasteViews' => max(0, (int) ($contentSummary['pasteViewCount'] ?? 0)),
            'pasteRawViews' => max(0, (int) ($contentSummary['pasteRawViewCount'] ?? 0)),
            'htmlPageViews' => max(0, (int) ($htmlSummary['viewCount'] ?? 0)),
        ],
        'charts' => [
            'pageViews14d' => analytics_daily_metric_series($store, 'pageViews', 14),
            'logins14d' => analytics_daily_metric_series($store, 'logins', 14),
            'downloads14d' => analytics_daily_metric_series($store, 'downloads', 14),
        ],
        'families' => $families,
        'topPages' => $pages,
        'topDownloads' => $downloads,
        'loginMethods' => $loginMethods,
        'recentLogins' => analytics_recent_logins_payload($store, $cohorts, 20),
        'cohorts' => $cohortRows,
        'exams' => $examsSummary,
        'references' => [
            'contentTools' => [
                'totalFiles' => max(0, (int) ($contentSummary['totalFiles'] ?? 0)),
                'activeFiles' => max(0, (int) ($contentSummary['activeFiles'] ?? 0)),
                'downloadCount' => max(0, (int) ($contentSummary['downloadCount'] ?? 0)),
                'pasteCount' => max(0, (int) ($contentSummary['pasteCount'] ?? 0)),
                'pasteViewCount' => max(0, (int) ($contentSummary['pasteViewCount'] ?? 0)),
                'pasteRawViewCount' => max(0, (int) ($contentSummary['pasteRawViewCount'] ?? 0)),
            ],
            'htmlUploader' => [
                'totalPages' => max(0, (int) ($htmlSummary['totalPages'] ?? 0)),
                'activePages' => max(0, (int) ($htmlSummary['activePages'] ?? 0)),
                'hiddenPages' => max(0, (int) ($htmlSummary['hiddenPages'] ?? 0)),
                'viewCount' => max(0, (int) ($htmlSummary['viewCount'] ?? 0)),
            ],
        ],
    ];
}
