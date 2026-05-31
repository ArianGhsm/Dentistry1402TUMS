<?php
declare(strict_types=1);

require_once __DIR__ . '/exams_term6_reference_data.php';

function dent_exams_apply_term6_reference_catalog_overrides(array $bank): array
{
    $courses = $bank['catalogs']['shared']['courses'] ?? null;
    if (!is_array($courses)) {
        return $bank;
    }

    foreach (dent_exams_term6_reference_course_map() as $slug => $course) {
        if (!is_string($slug) || !is_array($course)) {
            continue;
        }

        $courses[$slug] = $course;
    }

    $bank['catalogs']['shared']['courses'] = $courses;
    return $bank;
}
