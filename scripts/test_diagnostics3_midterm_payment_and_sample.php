<?php
declare(strict_types=1);
require_once __DIR__ . '/../public_html/api/bootstrap.php';
require_once __DIR__ . '/../public_html/api/exams_store.php';
require_once __DIR__ . '/../public_html/api/exams_diagnostics3_midterm_practice_data.php';
require_once __DIR__ . '/../public_html/api/exams_diagnostics3_midterm_sample_data.php';

function diag3_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL diagnostics3 paid/sample: ' . $message . "\n");
        throw new RuntimeException('Diagnostics3 paid/samples: ' . $message);
    }
}
$catalog = dent_exams_catalog('shared');
diag3_assert(is_array($catalog), 'missing shared catalog');
$courses = $catalog['courses'] ?? [];
diag3_assert(is_array($courses), 'missing shared courses');
$practice = $courses['diagnostics-3-midterm-practice'] ?? null;
$sample = $courses['diagnostics-3-midterm-sample'] ?? null;
diag3_assert(is_array($practice), 'practice course missing');
diag3_assert(is_array($sample), 'independent sample course missing');
diag3_assert($practice['slug'] !== $sample['slug'], 'independent purchase required');
diag3_assert(($practice['paymentGroupSlug'] ?? '') === '' && ($sample['paymentGroupSlug'] ?? '') === '', 'payment groups must be independent');

foreach (['practice' => $practice, 'sample' => $sample] as $type => $course) {
    diag3_assert(($course['defaultPaymentMode'] ?? '') === 'paid', "{$type} payment not enabled");
    diag3_assert(($course['defaultAmount'] ?? 0) === 450000, "{$type} amount must be 450000 rial");
    diag3_assert(str_contains((string) ($course['paymentDescription'] ?? ''), '۴۵ هزار تومان'), "{$type} price copy");
    diag3_assert(str_contains((string) ($course['paymentSuccessMessage'] ?? ''), 'فعال') || str_contains((string) ($course['paymentSuccessMessage'] ?? ''), 'باز شد'), "{$type} success copy");
    diag3_assert(($course['addedAt'] ?? '') !== '', "{$type} missing timeline");
    $default = dent_exams_course_default_setting('shared', (string)$course['slug']);
    diag3_assert($default['paymentMode'] === 'paid' && $default['amount'] === 450000, "{$type} default setting contract");
}
$exams = $practice['exams'] ?? [];
diag3_assert(count($exams) === 15, 'practice session count');
diag3_assert(array_sum(array_map(static fn(array $a):int => (int)($a['questionCount'] ?? 0), $exams)) === 550, 'practice question count');
diag3_assert(count($sample['exams'] ?? []) === 1, 'sample must contain only one exam');
$exam = $sample['exams'][0];
diag3_assert(($exam['slug'] ?? '') === 'midterm-44', 'sample slug');
diag3_assert(($exam['questionCount'] ?? 0) === 44, 'sample count');
diag3_assert(str_contains((string)($exam['subtitle'] ?? ''),'۴۰ سؤال قابل نمره‌دهی'), 'sample score information missing');
$questions = dent_exams_diagnostics3_midterm_sample_questions();
diag3_assert(count($questions) === 44, 'complete questions missing');
$unscored = [];
$diff = [];
foreach ($questions as $idx => $q) {
    $n = $idx + 1;
    diag3_assert((int)($q['number'] ?? 0) === $n, "Q{$n} source numbering");
    diag3_assert(trim((string)($q['question'] ?? '')) !== '', "Q{$n} stem");
    diag3_assert(is_array($q['options'] ?? null) && count($q['options']) === 4, "Q{$n} four options");
    diag3_assert(is_array($q['optionRationales'] ?? null) && count($q['optionRationales']) === 4, "Q{$n} rationales");
    diag3_assert(count(array_filter($q['optionRationales'], static fn($v):bool => trim((string)$v) !== '')) === 4, "Q{$n} rationale blanks");
    diag3_assert(is_array($q['answerMeta'] ?? null) && count($q['answerMeta']) >= 4, "Q{$n} source and independent answer");
    diag3_assert(str_contains((string)($q['answerMeta'][0]['value'] ?? ''),'گزینهٔ'), "Q{$n} scan-marked key");
    diag3_assert(is_array($q['answerSections'] ?? null) && count($q['answerSections']) >= 2, "Q{$n} answers");
    diag3_assert(trim((string)($q['reference'] ?? '')) !== '', "Q{$n} evidence note");
    if (in_array($n, [35, 36], true)) {
        diag3_assert(str_contains((string)$q['reference'], 'منابع علمی بیرونی'), "Q{$n} missing source provenance warning");
    } else {
        diag3_assert(str_contains((string)$q['reference'], 'جلسهٔ'), "Q{$n} lecture citation");
    }
    if (($q['correctIndex'] ?? null) === null) {
        $unscored[] = $n;
        diag3_assert(str_contains((string)($q['answerMeta'][1]['value'] ?? ''),'بدون نمره'), "Q{$n} non-scored warning");
    } else {
        diag3_assert(is_int($q['correctIndex']) && $q['correctIndex'] >= 0 && $q['correctIndex'] < 4, "Q{$n} key");
    }
    if (str_contains((string)($q['answerMeta'][2]['value'] ?? ''),'مغایرت')) {
        $diff[] = $n;
    }
}
diag3_assert($unscored === [23,24,30,39], 'unscored list mismatch: '.json_encode($unscored));
diag3_assert($diff === [9,17,36,39], 'scan differences mismatch: '.json_encode($diff));
diag3_assert(str_contains($questions[38]['question'],'تصویر'), 'Q39 missing image warning');
diag3_assert(dent_exams_diagnostics3_midterm_sample_runtime_exam_payload('shared','diagnostics-3-midterm-sample','midterm-44') !== null,'sample runtime unavailable');
diag3_assert(dent_exams_diagnostics3_midterm_sample_runtime_exam_payload('shared','diagnostics-3-midterm-practice','midterm-44') === null,'sample runtime cross-course leakage');
diag3_assert(dent_exams_diagnostics3_midterm_sample_runtime_exam_payload('other','diagnostics-3-midterm-sample','midterm-44') === null,'cohort leakage');
foreach ([
    'diagnostics-3-midterm-sample/index.html' => 'data-exams-course="diagnostics-3-midterm-sample"',
    'diagnostics-3-midterm-sample/midterm-44/index.html' => 'data-exams-exam="midterm-44"',
] as $page => $marker) {
    $html = @file_get_contents(__DIR__ . '/../public_html/exams/' . $page);
    diag3_assert(is_string($html) && str_contains($html, $marker), "shared site UI route: {$page}");
}
diag3_assert(isset($courses['partial-foundations-midterm-sample']), 'best prior sample course preserved');
echo "PASS diagnostics3: 15 bundled/550 questions at 450000 rial, separate 44-question sample at 450000 rial, 40 scored, original scan differences preserved.\n";
