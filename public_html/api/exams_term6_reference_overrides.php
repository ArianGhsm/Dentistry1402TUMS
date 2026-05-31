<?php
declare(strict_types=1);

function dent_exams_term6_reference_catalog_course_map_lazy(): array
{
    static $loaded = false;
    if (!$loaded) {
        $loaded = true;
        require_once __DIR__ . '/exams_term6_reference_catalog_data.php';
    }

    return function_exists('dent_exams_term6_reference_catalog_course_map')
        ? dent_exams_term6_reference_catalog_course_map()
        : [];
}

function dent_exams_term6_reference_course_map_lazy(): array
{
    static $loaded = false;
    if (!$loaded) {
        $loaded = true;
        require_once __DIR__ . '/exams_term6_reference_data.php';
    }

    return function_exists('dent_exams_term6_reference_course_map')
        ? dent_exams_term6_reference_course_map()
        : [];
}

function dent_exams_apply_term6_reference_catalog_overrides(array $bank): array
{
    if (function_exists('dent_requested_cohort_key') && function_exists('dent_is_prosthesis_cohort_key')) {
        $requestedCohort = dent_requested_cohort_key();
        if (dent_is_prosthesis_cohort_key($requestedCohort)) {
            return $bank;
        }
    }

    $courses = $bank['catalogs']['shared']['courses'] ?? null;
    if (!is_array($courses)) {
        return $bank;
    }

    foreach (dent_exams_term6_reference_catalog_course_map_lazy() as $slug => $course) {
        if (!is_string($slug) || !is_array($course)) {
            continue;
        }

        $courses[$slug] = $course;
    }

    $bank['catalogs']['shared']['courses'] = $courses;
    return $bank;
}

function dent_exams_term6_reference_runtime_exam_payload(string $catalogKey, string $courseSlug, string $examSlug): ?array
{
    if ($catalogKey !== 'shared') {
        return null;
    }

    $courseSlug = trim($courseSlug);
    $examSlug = trim($examSlug);
    if ($courseSlug === '' || $examSlug === '') {
        return null;
    }

    $courses = dent_exams_term6_reference_course_map_lazy();
    $course = $courses[$courseSlug] ?? null;
    if (!is_array($course)) {
        return null;
    }

    foreach ((is_array($course['exams'] ?? null) ? $course['exams'] : []) as $exam) {
        if (!is_array($exam) || (string) ($exam['slug'] ?? '') !== $examSlug) {
            continue;
        }
        return $exam;
    }

    return null;
}
