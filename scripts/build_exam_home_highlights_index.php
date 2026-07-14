<?php
declare(strict_types=1);

require_once __DIR__ . '/../public_html/api/auth_store.php';
require_once __DIR__ . '/../public_html/api/dentistry_curriculum.php';
require_once __DIR__ . '/../public_html/api/exams_store.php';

const EXAMS_HOME_HIGHLIGHTS_INDEX_PATH = __DIR__ . '/../public_html/api/exams_home_highlights_index.php';
const EXAMS_HOME_HIGHLIGHTS_LIMIT = 2;

function dent_home_highlights_question_count(array $exam): int
{
    $questions = $exam['questions'] ?? null;
    if (is_array($questions)) {
        return count($questions);
    }

    return max(0, (int) ($exam['questionCount'] ?? 0));
}

function dent_home_highlights_exam_counts_toward_stats(array $exam): bool
{
    if (dent_home_highlights_question_count($exam) > 0) {
        return true;
    }

    return !array_key_exists('countsTowardStats', $exam) || (bool) $exam['countsTowardStats'];
}

function dent_home_highlights_course_exam_count(array $course): int
{
    $count = 0;
    foreach (is_array($course['exams'] ?? null) ? $course['exams'] : [] as $exam) {
        if (is_array($exam) && dent_home_highlights_exam_counts_toward_stats($exam)) {
            $count++;
        }
    }

    return $count;
}

function dent_home_highlights_final_exam(string $courseSlug): ?array
{
    $unit = dent_dentistry_curriculum_find_unit_by_exam_course_slug($courseSlug);
    if ($unit === null) {
        return null;
    }

    $finalExams = dent_dentistry_curriculum_final_exams(
        $unit,
        dent_primary_cohort_key(),
        $courseSlug,
        new DateTimeImmutable('now', new DateTimeZone('Asia/Tehran'))
    );
    $finalExam = is_array($finalExams[0] ?? null) ? $finalExams[0] : null;
    if ($finalExam === null) {
        return null;
    }

    return [
        'expiresAt' => (string) ($finalExam['expiresAt'] ?? ''),
    ];
}

function dent_home_highlights_course_payload(string $catalogKey, string $courseSlug, array $course): ?array
{
    if (array_key_exists('visibleOnCatalog', $course) && empty($course['visibleOnCatalog'])) {
        return null;
    }

    $examCount = dent_home_highlights_course_exam_count($course);
    $path = trim((string) ($course['path'] ?? ''));
    $addedAt = dent_exams_catalog_course_added_at($courseSlug, $course);
    if ($examCount <= 0 || $path === '' || $addedAt === '') {
        return null;
    }

    return [
        'catalogKey' => $catalogKey,
        'slug' => $courseSlug,
        'title' => (string) ($course['title'] ?? ''),
        'shortTitle' => (string) ($course['shortTitle'] ?? ''),
        'cardDescription' => (string) ($course['cardDescription'] ?? ''),
        'heroDescription' => (string) ($course['heroDescription'] ?? ''),
        'addedAt' => $addedAt,
        'path' => $path,
        'stats' => [
            'examCount' => $examCount,
        ],
        'curriculum' => [
            'finalExam' => dent_home_highlights_final_exam($courseSlug),
        ],
    ];
}

function dent_home_highlights_index_payload(): array
{
    $catalogs = [];
    foreach (dent_exams_catalogs() as $catalogKey => $catalog) {
        if (!is_array($catalog)) {
            continue;
        }

        $rows = [];
        $sortIndex = 0;
        foreach (is_array($catalog['courses'] ?? null) ? $catalog['courses'] : [] as $courseSlug => $course) {
            if (!is_array($course)) {
                continue;
            }

            $cleanSlug = dent_exams_clean_course_slug((string) $courseSlug);
            $payload = dent_home_highlights_course_payload((string) $catalogKey, $cleanSlug, $course);
            if ($payload === null) {
                continue;
            }

            $rows[] = [
                'sortIndex' => $sortIndex++,
                'addedAtTimestamp' => strtotime((string) $payload['addedAt']) ?: 0,
                'payload' => $payload,
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $dateOrder = (int) ($right['addedAtTimestamp'] ?? 0) <=> (int) ($left['addedAtTimestamp'] ?? 0);
            if ($dateOrder !== 0) {
                return $dateOrder;
            }

            return (int) ($right['sortIndex'] ?? 0) <=> (int) ($left['sortIndex'] ?? 0);
        });

        $catalogs[(string) $catalogKey] = array_values(array_map(static function (array $row): array {
            return is_array($row['payload'] ?? null) ? $row['payload'] : [];
        }, array_slice($rows, 0, EXAMS_HOME_HIGHLIGHTS_LIMIT)));
    }

    $defaultCatalogKey = isset($catalogs['shared']) ? 'shared' : (string) array_key_first($catalogs);
    $catalogByCohort = [];
    foreach (array_keys(dent_default_cohort_catalog()) as $cohortKey) {
        $catalogByCohort[$cohortKey] = isset($catalogs[$cohortKey]) ? $cohortKey : $defaultCatalogKey;
    }

    ksort($catalogs, SORT_STRING);
    ksort($catalogByCohort, SORT_STRING);
    $signaturePayload = [
        'defaultCatalogKey' => $defaultCatalogKey,
        'catalogByCohort' => $catalogByCohort,
        'catalogs' => $catalogs,
    ];

    return [
        'schemaVersion' => 1,
        'sourceSignature' => hash('sha256', json_encode($signaturePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
    ] + $signaturePayload;
}

function dent_home_highlights_index_source(array $payload): string
{
    return "<?php\ndeclare(strict_types=1);\n\n"
        . "/**\n * Generated by scripts/build_exam_home_highlights_index.php.\n"
        . " * Do not edit manually; run the generator after catalog changes.\n */\n"
        . "function dent_exams_home_highlights_index(): array\n{\n"
        . '    return ' . var_export($payload, true) . ";\n}\n";
}

$checkOnly = in_array('--check', $argv, true);
$payload = dent_home_highlights_index_payload();
$expected = dent_home_highlights_index_source($payload);
$existing = is_file(EXAMS_HOME_HIGHLIGHTS_INDEX_PATH)
    ? (string) file_get_contents(EXAMS_HOME_HIGHLIGHTS_INDEX_PATH)
    : '';

if ($checkOnly) {
    if ($existing !== $expected) {
        fwrite(STDERR, "FAIL: exam home highlights index is stale. Run php scripts/build_exam_home_highlights_index.php.\n");
        exit(1);
    }

    echo 'PASS: exam home highlights index is current (' . count($payload['catalogs'] ?? []) . " catalogs).\n";
    exit(0);
}

if ($existing === $expected) {
    echo "Exam home highlights index is already current.\n";
    exit(0);
}

if (file_put_contents(EXAMS_HOME_HIGHLIGHTS_INDEX_PATH, $expected, LOCK_EX) === false) {
    fwrite(STDERR, "FAIL: unable to write exam home highlights index.\n");
    exit(1);
}

echo 'Generated exam home highlights index (' . count($payload['catalogs'] ?? []) . " catalogs).\n";
