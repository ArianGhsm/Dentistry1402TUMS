<?php
declare(strict_types=1);

require_once __DIR__ . '/exams_diagnostics3_midterm_sample_data.php';

/** Reuse the current shared catalog with an independent payment boundary. */
function dent_exams_apply_diagnostics3_midterm_sample_catalog_overrides(array $bank): array
{
    if (function_exists('dent_requested_cohort_key') && function_exists('dent_is_prosthesis_cohort_key')) {
        if (dent_is_prosthesis_cohort_key(dent_requested_cohort_key())) {
            return $bank;
        }
    }
    $courses = $bank['catalogs']['shared']['courses'] ?? null;
    if (!is_array($courses)) {
        return $bank;
    }
    $course = dent_exams_diagnostics3_midterm_sample_course();
    if (dent_exams_diagnostics3_midterm_sample_questions() === []) {
        return $bank;
    }
    $rebuilt = [];
    $inserted = false;
    foreach ($courses as $slug => $existingCourse) {
        if ($slug === $course['slug']) {
            continue;
        }
        if (!$inserted && $slug === 'diagnostics-3-midterm-practice') {
            $rebuilt[$slug] = $existingCourse;
            $rebuilt[$course['slug']] = $course;
            $inserted = true;
            continue;
        }
        $rebuilt[$slug] = $existingCourse;
    }
    if (!$inserted) {
        $rebuilt[$course['slug']] = $course;
    }
    $bank['catalogs']['shared']['courses'] = $rebuilt;
    return $bank;
}
