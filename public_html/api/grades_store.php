<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function dent_grades_store_path(): string
{
    return dent_storage_path('grades/grades.csv');
}

function dent_normalize_number_string(?string $value): string
{
    $value = dent_normalize_digits($value);
    $value = str_replace(['?', '?', '?'], ['.', '', ''], $value);
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
    return $score > 0 ? $score : null;
}

function dent_read_grades_source(): array
{
    $csvFile = dent_grades_store_path();
    if (!file_exists($csvFile)) {
        dent_error('???? ????? ?? storage ???? ???.', 500);
    }

    $handle = fopen($csvFile, 'r');
    if ($handle === false) {
        dent_error('????? ?????? ???? ????? ???? ?????.', 500);
    }

    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        dent_error('??? ???? ????? ??????? ???.', 500);
    }

    $idIndex = array_search('StudentID', $header, true);
    $nameIndex = array_search('Name', $header, true);

    if ($idIndex === false || $nameIndex === false) {
        fclose($handle);
        dent_error('???????? StudentID ? Name ?? ???? ????? ???? ?????.', 500);
    }

    $gradeColumns = [];
    foreach ($header as $index => $label) {
        if ($index === $idIndex || $index === $nameIndex) {
            continue;
        }

        $gradeColumns[$index] = (string) $label;
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
    $source = dent_read_grades_source();
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
        dent_error('????? ???????? ????? ??????? ???.', 422);
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
        'name' => (string) ($user['name'] ?? ($row[$source['nameIndex']] ?? '??????')),
        'studentNumber' => $studentNumber,
        'grades' => $grades,
        'stats' => $stats,
        'classAverage' => $default['classAverage'] ?? null,
        'rank' => $default['rank'] ?? null,
        'totalWithScore' => $default['totalWithScore'] ?? 0,
    ];
}
