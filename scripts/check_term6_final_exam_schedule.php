<?php
declare(strict_types=1);

require_once __DIR__ . '/../public_html/api/dentistry_curriculum.php';

$expected = [
    'diagnostics-2' => ['۱۴۰۵/۰۴/۲۹', '2026-07-20T12:15:00+03:30'],
    'complete-prosthodontics-theory' => ['۱۴۰۵/۰۴/۳۱', '2026-07-22T12:15:00+03:30'],
    'restorative-theory-1' => ['۱۴۰۵/۰۵/۰۳', '2026-07-25T07:45:00+03:30'],
    'endodontics-foundations-1' => ['۱۴۰۵/۰۵/۰۷', '2026-07-29T12:15:00+03:30'],
    'surgery-practical-1' => ['۱۴۰۵/۰۵/۱۱', '2026-08-02T12:15:00+03:30'],
    'specialized-language-3-4' => ['۱۴۰۵/۰۵/۱۴', '2026-08-05T13:00:00+03:30'],
    'gerontology' => ['۱۴۰۵/۰۵/۱۷', '2026-08-08T12:15:00+03:30'],
    'equipment-ergonomics' => ['۱۴۰۵/۰۵/۲۰', '2026-08-11T12:15:00+03:30'],
    'diagnostics-1' => ['۱۴۰۵/۰۵/۲۴', '2026-08-15T12:15:00+03:30'],
    'research-methods-1' => ['۱۴۰۵/۰۵/۲۸', '2026-08-19T12:15:00+03:30'],
    'dental-materials-foundations' => ['۱۴۰۵/۰۵/۳۱', '2026-08-22T12:15:00+03:30'],
    'medical-emergencies' => ['۱۴۰۵/۰۶/۰۲', '2026-08-24T12:15:00+03:30'],
];

$schedule = dent_dentistry_term6_final_exam_schedule();
$errors = [];
if (count($schedule) !== count($expected)) {
    $errors[] = 'expected ' . count($expected) . ' schedule rows, received ' . count($schedule);
}

foreach ($expected as $key => [$jalaliDate, $startsAt]) {
    $record = is_array($schedule[$key] ?? null) ? $schedule[$key] : null;
    if ($record === null) {
        $errors[] = 'missing schedule row ' . $key;
        continue;
    }
    if ((string) ($record['jalaliDate'] ?? '') !== $jalaliDate) {
        $errors[] = $key . ' has an unexpected Jalali date';
    }
    $payload = dent_dentistry_final_exam_payload($record, new DateTimeImmutable('2026-07-12T00:00:00+03:30'));
    if (!is_array($payload) || (string) ($payload['startsAt'] ?? '') !== $startsAt) {
        $errors[] = $key . ' has an invalid startsAt value';
    }
    if (!is_array($payload) || !str_contains((string) ($payload['displayLabel'] ?? ''), $jalaliDate)) {
        $errors[] = $key . ' has an invalid display label';
    }
}

$restorativeUnit = dent_dentistry_curriculum_find_unit('restorative-theory-1');
$beforeExpiry = dent_dentistry_curriculum_final_exams(
    is_array($restorativeUnit) ? $restorativeUnit : [],
    'dentistry-1402',
    'restorative-theory-1-midterm-sample',
    new DateTimeImmutable('2026-07-25T23:59:59+03:30')
);
$afterExpiry = dent_dentistry_curriculum_final_exams(
    is_array($restorativeUnit) ? $restorativeUnit : [],
    'dentistry-1402',
    'restorative-theory-1-midterm-sample',
    new DateTimeImmutable('2026-07-26T00:00:00+03:30')
);
if (!is_array($beforeExpiry[0] ?? null) || !empty($beforeExpiry[0]['hasPassed'])) {
    $errors[] = 'restorative exam expires before the end of its exam day';
}
if (!is_array($afterExpiry[0] ?? null) || empty($afterExpiry[0]['hasPassed'])) {
    $errors[] = 'restorative exam does not expire after its exam day';
}

$diagnosticsUnit = dent_dentistry_curriculum_find_unit('diagnostics-1-2');
$diagnosticsCourseSchedule = dent_dentistry_curriculum_final_exams(
    is_array($diagnosticsUnit) ? $diagnosticsUnit : [],
    'dentistry-1402',
    'diagnostics-1-term6'
);
if (count($diagnosticsCourseSchedule) !== 1 || (string) ($diagnosticsCourseSchedule[0]['jalaliDate'] ?? '') !== '۱۴۰۵/۰۵/۲۴') {
    $errors[] = 'diagnostics-1 course is not mapped to its dedicated final-exam date';
}
$diagnostics2CourseSchedule = dent_dentistry_curriculum_final_exams(
    is_array($diagnosticsUnit) ? $diagnosticsUnit : [],
    'dentistry-1402',
    'diagnostics-2-term6'
);
if (count($diagnostics2CourseSchedule) !== 1 || (string) ($diagnostics2CourseSchedule[0]['jalaliDate'] ?? '') !== '۱۴۰۵/۰۴/۲۹') {
    $errors[] = 'diagnostics-2 course is not mapped to its dedicated final-exam date';
}

if (dent_dentistry_curriculum_final_exams(is_array($restorativeUnit) ? $restorativeUnit : [], 'dentistry-1403') !== []) {
    $errors[] = 'dentistry-1402 schedule leaked into dentistry-1403';
}
if (dent_dentistry_curriculum_final_exams(is_array($restorativeUnit) ? $restorativeUnit : [], 'prosthesis-1402') !== []) {
    $errors[] = 'dentistry-1402 schedule leaked into prosthesis-1402';
}

$selectionFixture = [
    ['slug' => 'newest-expired', 'curriculum' => ['finalExam' => ['hasPassed' => true]]],
    ['slug' => 'second-active', 'curriculum' => ['finalExam' => ['hasPassed' => false]]],
    ['slug' => 'third-active', 'curriculum' => ['finalExam' => ['hasPassed' => false]]],
];
$selection = dent_dentistry_select_home_active_exam_courses($selectionFixture, 2);
if (count($selection) !== 1 || (string) ($selection[0]['slug'] ?? '') !== 'second-active') {
    $errors[] = 'home selection backfilled an expired latest course with an older course';
}
$allExpiredSelection = dent_dentistry_select_home_active_exam_courses([
    ['slug' => 'first-expired', 'curriculum' => ['finalExam' => ['hasPassed' => true]]],
    ['slug' => 'second-expired', 'curriculum' => ['finalExam' => ['hasPassed' => true]]],
    ['slug' => 'third-active', 'curriculum' => ['finalExam' => ['hasPassed' => false]]],
], 2);
if ($allExpiredSelection !== []) {
    $errors[] = 'home selection did not hide the alert after both latest courses expired';
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, 'FAIL: ' . $error . PHP_EOL);
    }
    exit(1);
}

echo 'PASS: term 6 final-exam schedule has 12 indexed rows, correct expiry, course mapping, and cohort isolation.' . PHP_EOL;
