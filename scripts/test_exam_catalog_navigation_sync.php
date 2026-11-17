<?php
declare(strict_types=1);

// All visible exam courses must be reachable through the same curriculum or
// reference source that feeds /exams/. This catches the regression where a
// newly published course appears in homepage highlights but its term is empty.
require_once __DIR__ . '/../public_html/api/exams_store.php';
require_once __DIR__ . '/../public_html/api/dentistry_curriculum.php';

function nav_sync_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: exam catalog navigation sync: {$message}\n");
        exit(1);
    }
}

$catalog = dent_exams_catalog('shared');
nav_sync_assert(is_array($catalog), 'shared exam catalog missing');
$courses = is_array($catalog['courses'] ?? null) ? $catalog['courses'] : [];
$curriculumSlugs = [];
$referenceSlugs = [];
$unitMatches = [];

foreach (dent_dentistry_curriculum_terms() as $term) {
    $termNumber = (int) ($term['number'] ?? 0);
    foreach (($term['categories'] ?? []) as $category) {
        foreach (($category['units'] ?? []) as $unit) {
            $unitKey = trim((string) ($unit['key'] ?? ''));
            foreach (($unit['examCourseSlugs'] ?? []) as $slug) {
                $slug = trim((string) $slug);
                nav_sync_assert($slug !== '', 'blank curriculum slug');
                $curriculumSlugs[$slug] = true;
                $unitMatches[$slug][] = ['term' => $termNumber, 'key' => $unitKey];
            }
        }
    }
}

foreach (dent_dentistry_exam_reference_specialties() as $specialty) {
    foreach (($specialty['references'] ?? []) as $reference) {
        foreach (($reference['courseSlugs'] ?? []) as $slug) {
            $slug = trim((string) $slug);
            nav_sync_assert($slug !== '', 'blank reference slug');
            $referenceSlugs[$slug] = true;
        }
    }
}

$visibleCount = 0;
$unreachable = [];
foreach ($courses as $slug => $course) {
    if (!is_array($course) || ($course['visibleOnCatalog'] ?? true) === false) {
        continue;
    }

    $visibleCount++;
    $slug = trim((string) $slug);
    if (!isset($curriculumSlugs[$slug]) && !isset($referenceSlugs[$slug])) {
        $unreachable[] = $slug;
    }
}
nav_sync_assert($unreachable === [], 'published courses missing from term/reference navigation: ' . implode(', ', $unreachable));

$diagnostics = dent_dentistry_curriculum_find_unit('diagnostics-3');
nav_sync_assert(is_array($diagnostics), 'term 7 diagnostics 3 unit missing');
nav_sync_assert((int) ($diagnostics['termNumber'] ?? 0) === 7, 'diagnostics 3 must belong to term 7');
$expected = ['diagnostics-3-midterm-practice', 'diagnostics-3-midterm-sample'];
$actual = $diagnostics['examCourseSlugs'] ?? [];
nav_sync_assert($actual === $expected, 'diagnostics 3 should list the two published collections in native order');

$examCount = 0;
$questionCount = 0;
foreach ($expected as $slug) {
    $mapped = $unitMatches[$slug] ?? [];
    nav_sync_assert(count($mapped) === 1
        && (int) $mapped[0]['term'] === 7
        && $mapped[0]['key'] === 'diagnostics-3',
        "unexpected term association for {$slug}");
    $found = dent_dentistry_curriculum_find_unit_by_exam_course_slug($slug);
    nav_sync_assert(is_array($found) && ($found['key'] ?? '') === 'diagnostics-3', "course metadata missing for {$slug}");
    $course = $courses[$slug] ?? null;
    nav_sync_assert(is_array($course), "published course {$slug} missing");
    nav_sync_assert(($course['defaultPaymentMode'] ?? '') === 'paid'
        && (int) ($course['defaultAmount'] ?? 0) === 450000,
        "paid course price changed: {$slug}");
    $exams = is_array($course['exams'] ?? null) ? $course['exams'] : [];
    $examCount += count($exams);
    foreach ($exams as $exam) {
        $questionCount += max(0, (int) ($exam['questionCount'] ?? 0));
    }
}
nav_sync_assert($examCount === 16 && $questionCount === 594,
    "term 7 diagnostics summary must be 16 exams / 594 questions; got {$examCount}/{$questionCount}");

// A second native example with multiple collections must still resolve.
$partial = dent_dentistry_curriculum_find_unit_by_exam_course_slug('partial-foundations-midterm-sample');
nav_sync_assert(is_array($partial) && (int) ($partial['termNumber'] ?? 0) === 7
    && ($partial['key'] ?? '') === 'partial-foundations-theory',
    'existing term-7 partial foundations navigation regressed');

echo "PASS exam catalog/navigation sync: {$visibleCount} published courses reachable, term 7 diagnostics 3 exposes both paid collections (16 exams, 594 questions), existing term links preserved.\n";
