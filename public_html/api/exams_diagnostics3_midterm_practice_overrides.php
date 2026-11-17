<?php
declare(strict_types=1);

require_once __DIR__ . '/exams_diagnostics3_midterm_practice_data.php';

function dent_exams_apply_diagnostics3_midterm_practice_catalog_overrides(array $bank): array
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

    $course = dent_exams_diagnostics3_midterm_practice_catalog_course();
    if (($course['exams'] ?? []) === []) {
        return $bank;
    }

    $rebuilt = [];
    $inserted = false;
    foreach ($courses as $slug => $existingCourse) {
        if ($slug === $course['slug']) {
            continue;
        }
        // Reuse the existing native exams catalog and position this new
        // midterm alongside diagnostic subjects, not in a separate UI.
        if (!$inserted && $slug === 'diagnostics-2-final-sample') {
            $rebuilt[$course['slug']] = $course;
            $inserted = true;
        }
        $rebuilt[$slug] = $existingCourse;
    }
    if (!$inserted) {
        $rebuilt[$course['slug']] = $course;
    }

    $bank['catalogs']['shared']['courses'] = $rebuilt;
    return $bank;
}
