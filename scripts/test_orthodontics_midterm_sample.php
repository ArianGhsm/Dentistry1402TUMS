<?php
declare(strict_types=1);
require_once __DIR__ . '/../public_html/api/exams_store.php';
require_once __DIR__ . '/../public_html/api/dentistry_curriculum.php';
require_once __DIR__ . '/../public_html/api/exams_orthodontics_midterm_sample_data.php';

function ortho_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
$slug = 'orthodontics-theory-1-midterm-sample';
$course = dent_exams_catalog('shared')['courses'][$slug] ?? null;
ortho_assert(is_array($course), 'Missing canonical catalog course');
ortho_assert($course['defaultPaymentMode'] === 'paid' && $course['defaultAmount'] === 450000, 'Standard price');
$settings = dent_exams_course_default_setting('shared', $slug);
ortho_assert($settings['paymentMode'] === 'paid' && $settings['amount'] === 450000, 'Standard payment setting');
$unit = dent_dentistry_curriculum_find_unit_by_exam_course_slug($slug);
ortho_assert(($unit['key'] ?? '') === 'ortho-theory-1' && (int)($unit['termNumber'] ?? 0) === 7, 'Wrong unit');
ortho_assert(count($course['exams']) === 1 && $course['exams'][0]['questionCount'] === 24, 'Wrong count');
$exam = dent_exams_orthodontics_midterm_sample_runtime_exam_payload('shared', $slug, 'midterm-24');
$questions = $exam['questions'] ?? [];
ortho_assert(count($questions) === 24, 'API hydration failed');
$key = [2,1,4,4,1,4,2,3,3,3,4,2,4,1,4,3,2,1,1,1,1,1,3,3];
foreach ($questions as $idx => $question) {
    ortho_assert($question['number'] === $idx + 1 && $question['correctIndex'] === $key[$idx]-1, 'Order/key mismatch');
    ortho_assert(count($question['options']) === 4 && count($question['optionRationales']) === 4, 'Incomplete options');
    ortho_assert(count($question['answerMeta']) === 4 && count($question['answerSections']) >= 2, 'Missing answer blocks');
    ortho_assert($question['reference'] !== '' && $question['explanation'] !== '', 'Missing evidence');
    ortho_assert(($question['answerMeta'][2]['tone'] === 'warning') === ($idx === 4), 'Scan difference');
    if (in_array($idx+1, [4,5,8,16,17,19,20,21,22,23,24], true)) {
        ortho_assert(count($question['answerSections']) === 3 && $question['answerSections'][2]['tone'] === 'warning', 'Missing qualification');
    }
}
ortho_assert(dent_exams_orthodontics_midterm_sample_runtime_exam_payload('other', $slug, 'midterm-24') === null, 'Wrong catalog');
ortho_assert(dent_exams_orthodontics_midterm_sample_runtime_exam_payload('shared', 'other', 'midterm-24') === null, 'Wrong course');
foreach (['index.html','midterm-24/index.html'] as $route) {
    $html = file_get_contents(__DIR__.'/../public_html/exams/'.$slug.'/'.$route);
    ortho_assert(str_contains($html, 'lang="fa" dir="rtl"') && str_contains($html, 'data-exams-course="'.$slug.'"'), 'Native RTL route');
    ortho_assert(str_contains($html, 'auth.js') && (str_contains($html, 'exams-course.js') || str_contains($html, 'exam-bootstrap.js')), 'Native UI');
}
ortho_assert(str_contains(file_get_contents(__DIR__.'/../public_html/api/exams_api.php'), 'dent_exams_orthodontics_midterm_sample_runtime_exam_payload('), 'API runtime registration');
echo "PASS orthodontics: 24 questions, 96 rationales, term 7, 450000 rial, Q5 scan difference, source warnings preserved.\n";
