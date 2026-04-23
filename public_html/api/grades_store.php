<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function dent_grades_store_path(): string
{
    return dent_storage_path('grades/grades.csv');
}

function dent_empty_grades_source(): array
{
    return [
        'header' => [],
        'rows' => [],
        'idIndex' => 0,
        'nameIndex' => 0,
        'gradeColumns' => [],
        'statsAccumulator' => [],
    ];
}

function dent_normalize_header_label(?string $label): string
{
    $label = (string) $label;
    $label = ltrim($label, "\xEF\xBB\xBF");
    $label = trim($label);
    if ($label === '') {
        return '';
    }

    $label = preg_replace('/[\s_\-\.]+/u', '', $label) ?? $label;
    return strtolower($label);
}

function dent_find_header_index(array $header, array $aliases): ?int
{
    $lookup = [];
    foreach ($aliases as $alias) {
        $normalized = dent_normalize_header_label((string) $alias);
        if ($normalized !== '') {
            $lookup[$normalized] = true;
        }
    }

    foreach ($header as $index => $label) {
        $normalized = dent_normalize_header_label((string) $label);
        if ($normalized !== '' && isset($lookup[$normalized])) {
            return (int) $index;
        }
    }

    return null;
}

function dent_should_skip_grade_column(string $label): bool
{
    static $skip = null;

    if ($skip === null) {
        $skip = [];
        foreach (['password', 'pass', 'pwd'] as $alias) {
            $skip[dent_normalize_header_label($alias)] = true;
        }
    }

    $normalized = dent_normalize_header_label($label);
    return $normalized !== '' && isset($skip[$normalized]);
}

function dent_normalize_number_string(?string $value): string
{
    $value = dent_normalize_digits($value);
    $value = str_replace(['٫', '٬', '،'], ['.', '', ''], $value);
    $value = str_replace(',', '.', $value);

    return trim((string) $value);
}

function dent_parse_grade_score($value): ?float
{
    $normalized = dent_normalize_number_string((string) $value);
    if ($normalized === '' || !is_numeric($normalized)) {
        return null;
    }

    $score = (float) $normalized;
    return $score >= 0 ? $score : null;
}

function dent_read_grades_source(bool $strict = true): array
{
    $emptySource = dent_empty_grades_source();
    $csvFile = dent_grades_store_path();

    if (!file_exists($csvFile)) {
        if (!$strict) {
            return $emptySource;
        }
        dent_error('فایل نمرات در storage پیدا نشد.', 500);
    }

    $handle = fopen($csvFile, 'r');
    if ($handle === false) {
        if (!$strict) {
            return $emptySource;
        }
        dent_error('امکان خواندن فایل نمرات وجود ندارد.', 500);
    }

    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        if (!$strict) {
            return $emptySource;
        }
        dent_error('هدر فایل نمرات نامعتبر است.', 500);
    }

    $idIndex = dent_find_header_index($header, ['StudentID', 'StudentId', 'StudentNumber', 'StudentNo', 'SID']);
    $nameIndex = dent_find_header_index($header, ['Name', 'FullName', 'StudentName']);

    if ($idIndex === null && array_key_exists(0, $header)) {
        $idIndex = 0;
    }

    if ($nameIndex === null) {
        if (array_key_exists(1, $header) && $idIndex !== 1) {
            $nameIndex = 1;
        } else {
            $nameIndex = $idIndex;
        }
    }

    if ($idIndex === null || $nameIndex === null) {
        fclose($handle);
        if (!$strict) {
            return $emptySource;
        }
        dent_error('ستون‌های StudentID و Name در فایل نمرات پیدا نشدند.', 500);
    }

    $gradeColumns = [];
    foreach ($header as $index => $label) {
        if ($index === $idIndex || $index === $nameIndex) {
            continue;
        }

        $cleanLabel = trim((string) $label);
        if ($cleanLabel === '' || dent_should_skip_grade_column($cleanLabel)) {
            continue;
        }

        $gradeColumns[$index] = $cleanLabel;
    }

    $rows = [];
    $statsAccumulator = [];
    foreach ($gradeColumns as $index => $label) {
        $statsAccumulator[$index] = [
            'sum' => 0.0,
            'count' => 0,
            'scores' => [],
        ];
    }

    while (($row = fgetcsv($handle)) !== false) {
        $studentNumber = dent_normalize_student_number($row[$idIndex] ?? '');
        if ($studentNumber === '') {
            continue;
        }

        $rows[] = $row;

        foreach ($gradeColumns as $index => $label) {
            $score = dent_parse_grade_score($row[$index] ?? null);
            if ($score === null) {
                continue;
            }

            $statsAccumulator[$index]['sum'] += $score;
            $statsAccumulator[$index]['count']++;
            $statsAccumulator[$index]['scores'][] = $score;
        }
    }

    fclose($handle);

    return [
        'header' => $header,
        'rows' => $rows,
        'idIndex' => $idIndex,
        'nameIndex' => $nameIndex,
        'gradeColumns' => $gradeColumns,
        'statsAccumulator' => $statsAccumulator,
    ];
}

function dent_grade_roster_index(): array
{
    $source = dent_read_grades_source(false);
    $roster = [];

    foreach ($source['rows'] as $row) {
        $studentNumber = dent_normalize_student_number($row[$source['idIndex']] ?? '');
        if ($studentNumber !== '') {
            $roster[$studentNumber] = true;
        }
    }

    return $roster;
}

function dent_build_grades_payload(array $user): array
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی کاربر نامعتبر است.', 422);
    }

    $source = dent_read_grades_source();
    $row = null;

    foreach ($source['rows'] as $candidate) {
        $candidateStudentNumber = dent_normalize_student_number($candidate[$source['idIndex']] ?? '');
        if ($candidateStudentNumber === $studentNumber) {
            $row = $candidate;
            break;
        }
    }

    $grades = [];
    $stats = [];

    foreach ($source['gradeColumns'] as $index => $label) {
        $value = $row[$index] ?? '';
        $grades[] = [
            'label' => $label,
            'value' => $value,
        ];

        $bucket = $source['statsAccumulator'][$index];
        $count = (int) $bucket['count'];
        $sum = (float) $bucket['sum'];
        $scores = $bucket['scores'];

        $classAverage = $count > 0 ? ($sum / $count) : null;
        $totalWithScore = count($scores);
        $myScore = dent_parse_grade_score($value);
        $rank = null;

        if ($myScore !== null && $totalWithScore > 0) {
            rsort($scores, SORT_NUMERIC);
            $rank = 1;
            foreach ($scores as $score) {
                if ($score > $myScore) {
                    $rank++;
                    continue;
                }
                break;
            }
        }

        $stats[] = [
            'label' => $label,
            'myScore' => $myScore,
            'classAverage' => $classAverage,
            'rank' => $rank,
            'totalWithScore' => $totalWithScore,
        ];
    }

    $default = $stats[0] ?? null;

    return [
        'success' => true,
        'name' => (string) ($user['name'] ?? ($row[$source['nameIndex']] ?? 'دانشجو')),
        'studentNumber' => $studentNumber,
        'grades' => $grades,
        'stats' => $stats,
        'classAverage' => $default['classAverage'] ?? null,
        'rank' => $default['rank'] ?? null,
        'totalWithScore' => $default['totalWithScore'] ?? 0,
    ];
}