<?php
declare(strict_types=1);

// Deterministic regression: protect existing shared exams while exposing
// only verified Diagnostic 3 session questions and their explanations.
require_once __DIR__ . '/../public_html/api/bootstrap.php';
require_once __DIR__ . '/../public_html/api/exams_store.php';
require_once __DIR__ . '/../public_html/api/exams_diagnostics3_midterm_practice_data.php';

function dig3_assert(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException('FAIL diagnostics3: ' . $message);
    }
}

$catalog = dent_exams_catalog('shared');
dig3_assert(is_array($catalog), 'shared catalog missing');
$courses = $catalog['courses'] ?? [];
dig3_assert(is_array($courses), 'courses missing');
$slug = 'diagnostics-3-midterm-practice';
$course = $courses[$slug] ?? null;
dig3_assert(is_array($course), 'new course missing');
dig3_assert(($course['defaultPaymentMode'] ?? '') === 'free', 'unexpected paywall');
dig3_assert(($course['addedAt'] ?? '') !== '', 'missing course timeline');

$sessions = [1=>35,2=>35,3=>35,4=>35,5=>40,6=>35,8=>40,9=>40,10=>35,12=>35,15=>40,16=>40,17=>35];
$exams = $course['exams'] ?? [];
dig3_assert(count($exams) === count($sessions), 'exam count');
dig3_assert(isset($courses['complete-foundations-theory-midterm-practice']), 'existing practice course must be preserved');
$total = 0;
$seen = [];
foreach ($exams as $exam) {
    $n = (int) ($exam['slug'] ?? 0);
    dig3_assert(isset($sessions[$n]), 'unexpected session ' . $n);
    dig3_assert(($exam['path'] ?? '') === "/exams/{$slug}/{$n}/", "session {$n} path");
    dig3_assert(($exam['questionCount'] ?? -1) === $sessions[$n], "session {$n} question count");
    dig3_assert(($exam['addedAt'] ?? '') !== '', "session {$n} timeline");
    dig3_assert(($exam['siteBadge'] ?? '') === 'میان‌ترم تشخیصی ۳', "session {$n} badge");
    $payload = dent_exams_diagnostics3_midterm_practice_runtime_exam_payload('shared', $slug, (string) $n);
    dig3_assert(is_array($payload), "session {$n} payload unavailable");
    $questions = $payload['questions'] ?? [];
    dig3_assert(count($questions) === $sessions[$n], "session {$n} payload size");
    foreach ($questions as $idx => $q) {
        dig3_assert(is_array($q), "session {$n} Q{$idx} invalid");
        dig3_assert(mb_strlen(trim((string) ($q['question'] ?? ''))) >= 10, "session {$n} Q{$idx} stem");
        $opts = $q['options'] ?? [];
        dig3_assert(is_array($opts) && count($opts) === 4, "session {$n} Q{$idx} options");
        dig3_assert(count(array_unique($opts)) === 4, "session {$n} Q{$idx} repeated options");
        dig3_assert(is_int($q['correctIndex'] ?? null) && $q['correctIndex'] >= 0 && $q['correctIndex'] <= 3, "session {$n} Q{$idx} answer");
        dig3_assert(mb_strlen(trim((string) ($q['explanation'] ?? ''))) >= 8, "session {$n} Q{$idx} explanation");
        dig3_assert(($q['topic'] ?? '') === ($exam['title'] !== '' ? (dent_exams_diagnostics3_midterm_practice_session_index()[$n]['topic']) : ''), "session {$n} Q{$idx} topic");
        $normalized = mb_strtolower((string) preg_replace('/[\s\x{200C}]+/u', '', (string) $q['question']));
        $seen[$normalized][] = "{$n}/" . ($idx+1);
    }
    $page = __DIR__ . "/../public_html/exams/{$slug}/{$n}/index.html";
    dig3_assert(is_file($page), "session {$n} HTML route");
    $html = (string) file_get_contents($page);
    dig3_assert(str_contains($html, 'data-exams-course="'.$slug.'"'), "session {$n} native exam bootstrap");
    dig3_assert(str_contains($html, 'data-exams-exam="'.$n.'"'), "session {$n} native exam slug");
    dig3_assert(str_contains($html, '/assets/site/scripts/exam-bootstrap.js'), "session {$n} shared UI missing");
    $total += count($questions);
}
dig3_assert($total === 480, 'grand total: '.$total);
foreach ([7,11,13,14] as $missing) {
    dig3_assert(dent_exams_diagnostics3_midterm_practice_runtime_exam_payload('shared', $slug, (string)$missing) === null, "unverified session {$missing} must remain hidden");
}
dig3_assert(dent_exams_diagnostics3_midterm_practice_runtime_exam_payload('other', $slug, '1') === null, 'catalog isolation');
dig3_assert(dent_exams_diagnostics3_midterm_practice_runtime_exam_payload('shared', 'other', '1') === null, 'course isolation');
$home = __DIR__ . "/../public_html/exams/{$slug}/index.html";
dig3_assert(is_file($home) && str_contains((string)file_get_contents($home),'data-exams-course="'.$slug.'"'), 'course native UI');

$duplicates = array_filter($seen, static fn(array $locations):bool => count($locations) > 1);
dig3_assert($duplicates === [], 'duplicate stems: ' . json_encode($duplicates, JSON_UNESCAPED_UNICODE));
echo "PASS diagnostics3 midterm: " . count($sessions) . " exams, {$total} questions, correct answers, shared UI, topic boundaries, exclusions.\n";
