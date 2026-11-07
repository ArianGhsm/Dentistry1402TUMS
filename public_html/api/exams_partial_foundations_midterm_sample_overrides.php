<?php
declare(strict_types=1);

require_once __DIR__ . '/exams_partial_foundations_midterm_sample_data.php';

function dent_exams_apply_partial_foundations_midterm_sample_catalog_overrides(array $bank): array
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
    $course = dent_exams_partial_foundations_midterm_sample_course();
    $courses[$course['slug']] = $course;
    $bank['catalogs']['shared']['courses'] = $courses;
    return $bank;
}
