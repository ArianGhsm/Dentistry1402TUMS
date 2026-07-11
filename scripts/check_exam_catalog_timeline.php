<?php
declare(strict_types=1);

require_once __DIR__ . '/../public_html/api/bootstrap.php';
require_once __DIR__ . '/../public_html/api/exams_store.php';

$catalog = dent_exams_catalog('shared');
if (!is_array($catalog)) {
    fwrite(STDERR, "FAIL: shared exam catalog is unavailable.\n");
    exit(1);
}

$errors = [];
$courseCount = 0;
$examCount = 0;
foreach (($catalog['courses'] ?? []) as $courseSlug => $course) {
    if (!is_array($course)) {
        continue;
    }

    $courseCount++;
    $addedAt = dent_exams_catalog_course_added_at((string) $courseSlug, $course);
    if ($addedAt === '') {
        $errors[] = 'course ' . $courseSlug . ' has no valid addedAt timestamp';
        continue;
    }

    foreach (($course['exams'] ?? []) as $exam) {
        if (!is_array($exam)) {
            continue;
        }
        $examCount++;
        $examSlug = (string) ($exam['slug'] ?? '?');
        if (dent_exams_catalog_exam_added_at($exam, $addedAt) === '') {
            $errors[] = 'exam ' . $courseSlug . '/' . $examSlug . ' has no valid addedAt timestamp';
        }
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, 'FAIL: ' . $error . PHP_EOL);
    }
    exit(1);
}

echo 'PASS: indexed addedAt for ' . $courseCount . ' courses and ' . $examCount . ' exams.' . PHP_EOL;
