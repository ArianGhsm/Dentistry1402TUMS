<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/exams_bank.php';

if (!defined('DENT_EXAMS_SCHEMA_VERSION')) {
    define('DENT_EXAMS_SCHEMA_VERSION', 1);
}

function dent_exams_store_path(): string
{
    return dent_storage_path('exams/store.json');
}

function dent_exams_lock_path(): string
{
    return dent_storage_path('exams/store.lock');
}

function dent_exams_default_store(): array
{
    return [
        'schemaVersion' => DENT_EXAMS_SCHEMA_VERSION,
        'courseSettings' => [],
    ];
}

function dent_exams_normalize_datetime_string(string $value, ?string $fallback = null): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return $fallback ?? dent_iso_now();
    }

    $parsed = strtotime($trimmed);
    if ($parsed === false) {
        return $fallback ?? dent_iso_now();
    }

    return date('c', $parsed);
}

function dent_exams_ensure_storage(): void
{
    dent_ensure_directory(dirname(dent_exams_store_path()));

    if (!is_file(dent_exams_store_path())) {
        dent_write_json_file(dent_exams_store_path(), dent_exams_default_store());
    }
}

function dent_exams_read_store(): array
{
    dent_exams_ensure_storage();

    $lock = fopen(dent_exams_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل فضای ذخیره‌سازی آزمون‌ها.', 500);
    }

    try {
        if (!flock($lock, LOCK_SH)) {
            throw new RuntimeException('Unable to acquire shared lock.');
        }

        return dent_exams_load_store_unlocked();
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

/**
 * @template T
 * @param callable(array):T $callback
 * @return T
 */
function dent_exams_with_store_lock(callable $callback)
{
    dent_exams_ensure_storage();

    $lock = fopen(dent_exams_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل فضای ذخیره‌سازی آزمون‌ها.', 500);
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to acquire exclusive lock.');
        }

        $store = dent_exams_load_store_unlocked();
        $result = $callback($store);
        dent_exams_save_store_unlocked($store);

        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function dent_exams_load_store_unlocked(): array
{
    $raw = dent_read_json_file(dent_exams_store_path(), dent_exams_default_store());
    if (!is_array($raw)) {
        $raw = dent_exams_default_store();
    }

    return dent_exams_normalize_store($raw);
}

function dent_exams_save_store_unlocked(array $store): void
{
    dent_write_json_file(dent_exams_store_path(), dent_exams_normalize_store($store));
}

function dent_exams_normalize_store(array $store): array
{
    $settingsRaw = $store['courseSettings'] ?? [];
    if (!is_array($settingsRaw)) {
        $settingsRaw = [];
    }

    $normalized = [];
    foreach ($settingsRaw as $key => $value) {
        $courseKey = dent_exams_clean_course_key((string) $key);
        if ($courseKey === '' || !is_array($value)) {
            continue;
        }

        $normalized[$courseKey] = dent_exams_normalize_course_setting($value);
    }

    ksort($normalized);

    return [
        'schemaVersion' => DENT_EXAMS_SCHEMA_VERSION,
        'courseSettings' => $normalized,
    ];
}

function dent_exams_clean_course_slug(string $value): string
{
    $value = trim(strtolower($value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9_-]+/', '', $value) ?? '';
    return substr($value, 0, 80);
}

function dent_exams_clean_catalog_key(string $value): string
{
    $value = trim(strtolower($value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9_-]+/', '', $value) ?? '';
    return substr($value, 0, 80);
}

function dent_exams_clean_course_key(string $value): string
{
    $value = trim(strtolower($value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9:_-]+/', '', $value) ?? '';
    return substr($value, 0, 160);
}

function dent_exams_course_key(string $catalogKey, string $courseSlug): string
{
    $cleanCatalog = dent_exams_clean_catalog_key($catalogKey);
    $cleanCourse = dent_exams_clean_course_slug($courseSlug);
    if ($cleanCatalog === '' || $cleanCourse === '') {
        return '';
    }

    return $cleanCatalog . ':' . $cleanCourse;
}

function dent_exams_normalize_course_setting(array $value): array
{
    $paymentMode = trim(strtolower((string) ($value['paymentMode'] ?? ($value['payment_mode'] ?? 'free'))));
    if (!in_array($paymentMode, ['free', 'paid'], true)) {
        $paymentMode = 'free';
    }

    return [
        'paymentMode' => $paymentMode,
        'amount' => max(0, (int) dent_normalize_digits((string) ($value['amount'] ?? 0))),
        'collectionId' => max(0, (int) ($value['collectionId'] ?? ($value['collection_id'] ?? 0))),
        'updatedAt' => dent_exams_normalize_datetime_string((string) ($value['updatedAt'] ?? ($value['updated_at'] ?? dent_iso_now())), dent_iso_now()),
    ];
}

function dent_exams_default_course_setting(): array
{
    return [
        'paymentMode' => 'free',
        'amount' => 0,
        'collectionId' => 0,
        'updatedAt' => dent_iso_now(),
    ];
}

function dent_exams_catalogs(): array
{
    $bank = dent_exams_bank();
    $catalogs = $bank['catalogs'] ?? [];
    return is_array($catalogs) ? $catalogs : [];
}

function dent_exams_resolve_catalog_key(?string $cohortKey = null): string
{
    $requested = dent_clean_cohort_key($cohortKey ?? dent_requested_cohort_key());
    $catalogs = dent_exams_catalogs();
    if ($requested !== '' && isset($catalogs[$requested]) && is_array($catalogs[$requested])) {
        return $requested;
    }
    if (isset($catalogs['shared']) && is_array($catalogs['shared'])) {
        return 'shared';
    }

    foreach ($catalogs as $key => $catalog) {
        if (is_array($catalog)) {
            return (string) $key;
        }
    }

    return '';
}

function dent_exams_catalog(string $catalogKey): ?array
{
    $catalogs = dent_exams_catalogs();
    $cleanKey = dent_exams_clean_catalog_key($catalogKey);
    $catalog = $cleanKey !== '' ? ($catalogs[$cleanKey] ?? null) : null;
    return is_array($catalog) ? $catalog : null;
}

function dent_exams_course(string $catalogKey, string $courseSlug): ?array
{
    $catalog = dent_exams_catalog($catalogKey);
    if ($catalog === null) {
        return null;
    }

    $courses = $catalog['courses'] ?? [];
    if (!is_array($courses)) {
        return null;
    }

    $course = $courses[dent_exams_clean_course_slug($courseSlug)] ?? null;
    return is_array($course) ? $course : null;
}

function dent_exams_exam(string $catalogKey, string $courseSlug, string $examSlug): ?array
{
    $course = dent_exams_course($catalogKey, $courseSlug);
    if ($course === null) {
        return null;
    }

    $exams = $course['exams'] ?? [];
    if (!is_array($exams)) {
        return null;
    }

    foreach ($exams as $exam) {
        if (!is_array($exam)) {
            continue;
        }
        if ((string) ($exam['slug'] ?? '') === trim((string) $examSlug)) {
            return $exam;
        }
    }

    return null;
}

function dent_exams_course_setting(array $store, string $catalogKey, string $courseSlug): array
{
    $courseKey = dent_exams_course_key($catalogKey, $courseSlug);
    if ($courseKey === '') {
        return dent_exams_default_course_setting();
    }

    $settings = $store['courseSettings'] ?? [];
    $current = is_array($settings[$courseKey] ?? null) ? $settings[$courseKey] : [];
    return dent_exams_normalize_course_setting($current);
}
