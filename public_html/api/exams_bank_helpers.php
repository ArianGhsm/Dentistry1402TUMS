<?php
declare(strict_types=1);

function dent_exams_sync_bank_question_counts(array $bank): array
{
    $catalogs = $bank['catalogs'] ?? null;
    if (!is_array($catalogs)) {
        return $bank;
    }

    foreach ($catalogs as $catalogKey => $catalog) {
        $courses = $catalog['courses'] ?? null;
        if (!is_array($courses)) {
            continue;
        }

        foreach ($courses as $courseSlug => $course) {
            $exams = $course['exams'] ?? null;
            if (!is_array($exams)) {
                continue;
            }

            foreach ($exams as $examIndex => $exam) {
                if (!is_array($exam)) {
                    continue;
                }

                $questions = $exam['questions'] ?? null;
                if (!is_array($questions)) {
                    continue;
                }

                $exam['questionCount'] = count($questions);
                $exams[$examIndex] = $exam;
            }

            $course['exams'] = $exams;
            $courses[$courseSlug] = $course;
        }

        $catalog['courses'] = $courses;
        $catalogs[$catalogKey] = $catalog;
    }

    $bank['catalogs'] = $catalogs;

    return $bank;
}
