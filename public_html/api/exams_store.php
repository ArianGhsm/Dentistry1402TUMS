<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/exams_bank.php';

if (!defined('DENT_EXAMS_SCHEMA_VERSION')) {
    define('DENT_EXAMS_SCHEMA_VERSION', 4);
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
        'examRecords' => [],
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

    $normalizedSettings = [];
    foreach ($settingsRaw as $key => $value) {
        $courseKey = dent_exams_clean_course_key((string) $key);
        if ($courseKey === '' || !is_array($value)) {
            continue;
        }

        $normalizedSettings[$courseKey] = dent_exams_normalize_course_setting($value);
    }
    ksort($normalizedSettings);

    $recordsRaw = $store['examRecords'] ?? [];
    if (!is_array($recordsRaw)) {
        $recordsRaw = [];
    }

    $normalizedRecords = [];
    foreach ($recordsRaw as $key => $value) {
        $examKey = dent_exams_clean_exam_key((string) $key);
        if ($examKey === '' || !is_array($value)) {
            continue;
        }

        $normalizedRecords[$examKey] = dent_exams_normalize_exam_record($value);
    }
    ksort($normalizedRecords);

    return [
        'schemaVersion' => DENT_EXAMS_SCHEMA_VERSION,
        'courseSettings' => $normalizedSettings,
        'examRecords' => $normalizedRecords,
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

function dent_exams_clean_exam_slug(string $value): string
{
    $value = trim(strtolower($value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9_-]+/', '', $value) ?? '';
    return substr($value, 0, 120);
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

function dent_exams_clean_exam_key(string $value): string
{
    $value = trim(strtolower($value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9:_-]+/', '', $value) ?? '';
    return substr($value, 0, 220);
}

function dent_exams_clean_participant_key(string $value): string
{
    return dent_normalize_student_number($value);
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

function dent_exams_exam_key(string $catalogKey, string $courseSlug, string $examSlug): string
{
    $cleanCatalog = dent_exams_clean_catalog_key($catalogKey);
    $cleanCourse = dent_exams_clean_course_slug($courseSlug);
    $cleanExam = dent_exams_clean_exam_slug($examSlug);
    if ($cleanCatalog === '' || $cleanCourse === '' || $cleanExam === '') {
        return '';
    }

    return $cleanCatalog . ':' . $cleanCourse . ':' . $cleanExam;
}

function dent_exams_clean_discount_code(string $value): string
{
    $value = dent_clean_text($value, 40);
    if ($value === '') {
        return '';
    }

    return strtoupper(preg_replace('/\s+/u', '', $value) ?? '');
}

function dent_exams_normalize_discount_codes($value): array
{
    $codes = [];
    if (is_array($value)) {
        $codes = $value;
    } elseif (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $codes = $decoded;
            }
        }
    }

    $normalized = [];
    $seen = [];
    foreach ($codes as $code) {
        if (!is_array($code)) {
            continue;
        }

        $rawCode = dent_exams_clean_discount_code((string) ($code['code'] ?? ''));
        if ($rawCode === '' || isset($seen[$rawCode])) {
            continue;
        }

        $type = trim(strtolower((string) ($code['type'] ?? 'fixed')));
        if (!in_array($type, ['fixed', 'percent'], true)) {
            $type = 'fixed';
        }

        $amount = max(0, (int) dent_normalize_digits((string) ($code['amount'] ?? 0)));
        if ($amount <= 0) {
            continue;
        }
        if ($type === 'percent') {
            $amount = min(95, $amount);
        }

        $maxUsesRaw = $code['maxUses'] ?? ($code['max_uses'] ?? null);
        $maxUses = null;
        if ($maxUsesRaw !== null && $maxUsesRaw !== '') {
            $maxUses = max(1, min(1000000, (int) dent_normalize_digits((string) $maxUsesRaw)));
        }

        $enabledRaw = $code['isEnabled'] ?? ($code['is_enabled'] ?? true);
        $enabled = filter_var($enabledRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $normalized[] = [
            'code' => $rawCode,
            'label' => dent_clean_text((string) ($code['label'] ?? ''), 120),
            'type' => $type,
            'amount' => $amount,
            'maxUses' => $maxUses,
            'studentNumber' => dent_normalize_student_number((string) ($code['studentNumber'] ?? ($code['student_number'] ?? ''))),
            'expiresAt' => dent_exams_normalize_datetime_string((string) ($code['expiresAt'] ?? ($code['expires_at'] ?? '')), ''),
            'isEnabled' => $enabled !== false,
        ];
        $seen[$rawCode] = true;

        if (count($normalized) >= 30) {
            break;
        }
    }

    return $normalized;
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
        'discountCodes' => dent_exams_normalize_discount_codes($value['discountCodes'] ?? ($value['discount_codes'] ?? [])),
        'updatedAt' => dent_exams_normalize_datetime_string((string) ($value['updatedAt'] ?? ($value['updated_at'] ?? dent_iso_now())), dent_iso_now()),
    ];
}

function dent_exams_default_course_setting(?array $course = null): array
{
    $paymentMode = trim(strtolower((string) ($course['defaultPaymentMode'] ?? 'free')));
    if (!in_array($paymentMode, ['free', 'paid'], true)) {
        $paymentMode = 'free';
    }

    $amount = max(0, (int) dent_normalize_digits((string) ($course['defaultAmount'] ?? 0)));

    return [
        'paymentMode' => $paymentMode,
        'amount' => $amount,
        'collectionId' => 0,
        'discountCodes' => dent_exams_normalize_discount_codes($course['defaultDiscountCodes'] ?? []),
        'updatedAt' => dent_iso_now(),
    ];
}

function dent_exams_normalize_question_index_list($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $indexes = [];
    foreach ($value as $item) {
        $normalized = dent_normalize_digits((string) $item);
        if ($normalized === '' || !preg_match('/^-?\d+$/', $normalized)) {
            continue;
        }

        $index = (int) $normalized;
        if ($index < 0 || $index > 5000) {
            continue;
        }

        $indexes[] = $index;
    }

    $indexes = array_values(array_unique($indexes));
    sort($indexes, SORT_NUMERIC);
    return $indexes;
}

function dent_exams_normalize_answer_list($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $answers = [];
    foreach ($value as $item) {
        if ($item === null || $item === '') {
            $answers[] = null;
            continue;
        }

        $normalized = dent_normalize_digits((string) $item);
        if ($normalized === '' || !preg_match('/^-?\d+$/', $normalized)) {
            $answers[] = null;
            continue;
        }

        $answer = (int) $normalized;
        $answers[] = $answer >= 0 && $answer <= 32 ? $answer : null;
    }

    return array_slice($answers, 0, 5000);
}

function dent_exams_normalize_percent($value): float
{
    $percent = (float) $value;
    if ($percent < 0) {
        $percent = 0;
    }
    if ($percent > 100) {
        $percent = 100;
    }

    return round($percent, 1);
}

function dent_exams_normalize_assessment_report(array $value): array
{
    $answers = dent_exams_normalize_answer_list($value['answers'] ?? []);
    $reportedTotal = max(0, (int) ($value['totalQuestions'] ?? ($value['total_questions'] ?? 0)));
    $correct = max(0, (int) ($value['correct'] ?? 0));
    $wrong = max(0, (int) ($value['wrong'] ?? 0));
    $unanswered = max(0, (int) ($value['unanswered'] ?? 0));
    $scoreTotal = $correct + $wrong + $unanswered;
    $totalQuestions = max($reportedTotal, $scoreTotal, count($answers));

    if ($totalQuestions > 0 && $scoreTotal < $totalQuestions) {
        $unanswered = max(0, $totalQuestions - $correct - $wrong);
    } elseif ($scoreTotal > $totalQuestions) {
        $totalQuestions = $scoreTotal;
    }

    $percent = array_key_exists('percent', $value)
        ? dent_exams_normalize_percent($value['percent'])
        : ($totalQuestions > 0 ? round(($correct / $totalQuestions) * 100, 1) : 0.0);

    $submittedFallback = dent_exams_normalize_datetime_string((string) ($value['submittedAt'] ?? ($value['submitted_at'] ?? dent_iso_now())), dent_iso_now());
    $startedFallback = dent_exams_normalize_datetime_string((string) ($value['startedAt'] ?? ($value['started_at'] ?? $submittedFallback)), $submittedFallback);

    return [
        'answers' => $answers,
        'totalQuestions' => $totalQuestions,
        'correct' => $correct,
        'wrong' => $wrong,
        'unanswered' => $unanswered,
        'percent' => $percent,
        'startedAt' => dent_exams_normalize_datetime_string((string) ($value['startedAt'] ?? ($value['started_at'] ?? $startedFallback)), $startedFallback),
        'submittedAt' => $submittedFallback,
        'updatedAt' => dent_exams_normalize_datetime_string((string) ($value['updatedAt'] ?? ($value['updated_at'] ?? $submittedFallback)), $submittedFallback),
    ];
}

function dent_exams_default_exam_record(): array
{
    return [
        'flagsByUser' => [],
        'reportsByUser' => [],
        'activityByUser' => [],
    ];
}

function dent_exams_normalize_exam_activity(array $value): array
{
    $lastMode = trim(strtolower((string) ($value['lastMode'] ?? ($value['last_mode'] ?? 'view'))));
    if (!in_array($lastMode, ['view', 'assessment', 'learning'], true)) {
        $lastMode = 'view';
    }

    return [
        'lastMode' => $lastMode,
        'updatedAt' => dent_exams_normalize_datetime_string(
            (string) ($value['updatedAt'] ?? ($value['updated_at'] ?? dent_iso_now())),
            dent_iso_now()
        ),
    ];
}

function dent_exams_normalize_exam_record(array $value): array
{
    $flagsRaw = $value['flagsByUser'] ?? ($value['flags_by_user'] ?? []);
    if (!is_array($flagsRaw)) {
        $flagsRaw = [];
    }

    $normalizedFlags = [];
    foreach ($flagsRaw as $participantKey => $indexes) {
        $cleanParticipant = dent_exams_clean_participant_key((string) $participantKey);
        if ($cleanParticipant === '') {
            continue;
        }

        $normalizedFlags[$cleanParticipant] = dent_exams_normalize_question_index_list($indexes);
    }
    ksort($normalizedFlags);

    $reportsRaw = $value['reportsByUser'] ?? ($value['reports_by_user'] ?? []);
    if (!is_array($reportsRaw)) {
        $reportsRaw = [];
    }

    $normalizedReports = [];
    foreach ($reportsRaw as $participantKey => $report) {
        $cleanParticipant = dent_exams_clean_participant_key((string) $participantKey);
        if ($cleanParticipant === '' || !is_array($report)) {
            continue;
        }

        $normalizedReports[$cleanParticipant] = dent_exams_normalize_assessment_report($report);
    }
    ksort($normalizedReports);

    $activityRaw = $value['activityByUser'] ?? ($value['activity_by_user'] ?? []);
    if (!is_array($activityRaw)) {
        $activityRaw = [];
    }

    $normalizedActivity = [];
    foreach ($activityRaw as $participantKey => $activity) {
        $cleanParticipant = dent_exams_clean_participant_key((string) $participantKey);
        if ($cleanParticipant === '' || !is_array($activity)) {
            continue;
        }

        $normalizedActivity[$cleanParticipant] = dent_exams_normalize_exam_activity($activity);
    }
    ksort($normalizedActivity);

    return [
        'flagsByUser' => $normalizedFlags,
        'reportsByUser' => $normalizedReports,
        'activityByUser' => $normalizedActivity,
    ];
}

function dent_exams_course_default_setting(string $catalogKey, string $courseSlug): array
{
    $course = dent_exams_course($catalogKey, $courseSlug);
    if (!is_array($course)) {
        return dent_exams_default_course_setting();
    }

    return dent_exams_default_course_setting($course);
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

    $cleanExamSlug = dent_exams_clean_exam_slug($examSlug);
    foreach ($exams as $exam) {
        if (!is_array($exam)) {
            continue;
        }
        if (dent_exams_clean_exam_slug((string) ($exam['slug'] ?? '')) === $cleanExamSlug) {
            return $exam;
        }
    }

    return null;
}

function dent_exams_course_setting(array $store, string $catalogKey, string $courseSlug): array
{
    $courseKey = dent_exams_course_key($catalogKey, $courseSlug);
    if ($courseKey === '') {
        return dent_exams_course_default_setting($catalogKey, $courseSlug);
    }

    $settings = $store['courseSettings'] ?? [];
    $current = $settings[$courseKey] ?? null;
    if (!is_array($current)) {
        return dent_exams_course_default_setting($catalogKey, $courseSlug);
    }

    return dent_exams_normalize_course_setting($current);
}

function dent_exams_record(array $store, string $catalogKey, string $courseSlug, string $examSlug): array
{
    $examKey = dent_exams_exam_key($catalogKey, $courseSlug, $examSlug);
    if ($examKey === '') {
        return dent_exams_default_exam_record();
    }

    $records = $store['examRecords'] ?? [];
    $record = $records[$examKey] ?? null;
    if (!is_array($record)) {
        return dent_exams_default_exam_record();
    }

    return dent_exams_normalize_exam_record($record);
}

function dent_exams_report_for_user(array $store, string $catalogKey, string $courseSlug, string $examSlug, string $participantKey): ?array
{
    $cleanParticipant = dent_exams_clean_participant_key($participantKey);
    if ($cleanParticipant === '') {
        return null;
    }

    $record = dent_exams_record($store, $catalogKey, $courseSlug, $examSlug);
    $report = $record['reportsByUser'][$cleanParticipant] ?? null;
    return is_array($report) ? dent_exams_normalize_assessment_report($report) : null;
}

function dent_exams_flags_for_user(array $store, string $catalogKey, string $courseSlug, string $examSlug, string $participantKey): array
{
    $cleanParticipant = dent_exams_clean_participant_key($participantKey);
    if ($cleanParticipant === '') {
        return [];
    }

    $record = dent_exams_record($store, $catalogKey, $courseSlug, $examSlug);
    return dent_exams_normalize_question_index_list($record['flagsByUser'][$cleanParticipant] ?? []);
}

function dent_exams_reports_by_user(array $store, string $catalogKey, string $courseSlug, string $examSlug): array
{
    $record = dent_exams_record($store, $catalogKey, $courseSlug, $examSlug);
    $reports = $record['reportsByUser'] ?? [];
    return is_array($reports) ? $reports : [];
}

function dent_exams_activity_for_user(array $store, string $catalogKey, string $courseSlug, string $examSlug, string $participantKey): ?array
{
    $cleanParticipant = dent_exams_clean_participant_key($participantKey);
    if ($cleanParticipant === '') {
        return null;
    }

    $record = dent_exams_record($store, $catalogKey, $courseSlug, $examSlug);
    $activity = $record['activityByUser'][$cleanParticipant] ?? null;
    return is_array($activity) ? dent_exams_normalize_exam_activity($activity) : null;
}
