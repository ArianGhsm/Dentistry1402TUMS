<?php
declare(strict_types=1);

// Regression: term-7 midterm must use existing quiz renderer/purchase
// contracts and preserve independent answer disagreements with the scan.
require_once dirname(__DIR__) . '/public_html/api/exams_modules.php';
require_once dirname(__DIR__) . '/public_html/api/exams_partial_foundations_midterm_sample_overrides.php';
require_once dirname(__DIR__) . '/public_html/api/dentistry_curriculum.php';

function must(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
$course = dent_exams_partial_foundations_midterm_sample_course();
must($course['defaultPaymentMode'] === 'paid', 'Default payment must be paid');
must($course['defaultAmount'] === 450000, 'Default amount should follow 45k-toman site policy');
must($course['path'] === '/exams/partial-foundations-midterm-sample/', 'Bad course path');
must(count($course['exams']) === 1, 'Expected one midterm');
$bank = ['catalogs' => ['shared' => ['courses' => []]]];
$withCourse = dent_exams_apply_partial_foundations_midterm_sample_catalog_overrides($bank);
must(isset($withCourse['catalogs']['shared']['courses'][$course['slug']]), 'Course missing from catalog');
$unit = dent_dentistry_curriculum_find_unit_by_exam_course_slug($course['slug']);
must(is_array($unit) && $unit['key'] === 'partial-foundations-theory', 'Wrong unit: should be term 7 theory');
$questions = dent_exams_partial_foundations_midterm_sample_runtime_exam_payload('shared', $course['slug'], 'midterm-41')['questions'] ?? [];
must(count($questions) === 41, 'Question count mismatch');
$diffs = [];
$gradable = 0;
foreach ($questions as $idx => $q) {
    must(($q['number'] ?? -1) === $idx + 1, 'Question order not consecutive');
    must(count($q['options'] ?? []) === 4, 'Need four options on question ' . ($idx + 1));
    must(count($q['optionRationales'] ?? []) === 4, 'Missing four option analyses');
    must(count($q['answerMeta'] ?? []) >= 4, 'Missing scan/independent/source answer cards');
    must(count($q['answerSections'] ?? []) >= 2, 'Missing educational content blocks');
    $names = array_column($q['answerSections'], 'label');
    $correct = $q['correctIndex'];
    if ($correct !== null) {
        must(is_int($correct) && $correct >= 0 && $correct < 4, 'Invalid correct index');
        must(in_array('علت درستی پاسخ مستقل', $names, true), 'No correct-option rationale');
        $gradable++;
    }
    if (($q['answerMeta'][2]['value'] ?? '') === 'مغایر با اسکن') {
        $diffs[] = $idx + 1;
    }
}
must($questions[5]['correctIndex'] === null, 'Missing image must not affect score');
must(str_contains($questions[5]['question'], 'تصویر'), 'Missing-image warning not visible');
must($gradable === 40, 'Exactly 40 questions should be scored');
must($diffs === [5, 24, 30, 31], 'Answer/scan disagreements lost');
$base = dirname(__DIR__) . '/public_html/exams/partial-foundations-midterm-sample';
foreach ([$base . '/index.html', $base . '/midterm-41/index.html'] as $page) {
    must(is_file($page), 'HTML page missing');
    $html = file_get_contents($page);
    must(str_contains($html, 'lang="fa" dir="rtl"'), 'Non-RTL HTML page');
    must(str_contains($html, 'auth.js') && (str_contains($html, 'exams-course.js') || str_contains($html, 'exam-bootstrap.js')), 'Not using existing exam UI');
}
echo "TERM7_PARTIAL_MIDTERM_OK questions=41 graded=40 differences=5,24,30,31 price=450000\n";
