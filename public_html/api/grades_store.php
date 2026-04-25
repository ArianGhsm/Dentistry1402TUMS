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

function dent_format_grade_for_store(float $score): string
{
    $normalized = number_format($score, 2, '.', '');
    $normalized = rtrim(rtrim($normalized, '0'), '.');
    return $normalized === '' ? '0' : $normalized;
}

function dent_write_grades_source(array $header, array $rows): void
{
    $csvFile = dent_grades_store_path();
    dent_ensure_directory(dirname($csvFile));

    $handle = fopen($csvFile, 'c+');
    if ($handle === false) {
        dent_error('امکان نگارش فایل نمرات وجود ندارد.', 500);
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        dent_error('قفل فایل نمرات گرفته نشد.', 500);
    }

    $columnCount = count($header);
    rewind($handle);
    ftruncate($handle, 0);
    fputcsv($handle, $header);

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $normalizedRow = $row;
        if (count($normalizedRow) < $columnCount) {
            $normalizedRow = array_pad($normalizedRow, $columnCount, '');
        } elseif (count($normalizedRow) > $columnCount) {
            $normalizedRow = array_slice($normalizedRow, 0, $columnCount);
        }
        fputcsv($handle, $normalizedRow);
    }

    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function dent_owner_grades_payload(string $studentNumber, string $fallbackName = ''): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }

    $source = dent_read_grades_source();
    $row = null;
    $rowExists = false;

    foreach ($source['rows'] as $candidate) {
        $candidateStudentNumber = dent_normalize_student_number($candidate[$source['idIndex']] ?? '');
        if ($candidateStudentNumber === $studentNumber) {
            $row = $candidate;
            $rowExists = true;
            break;
        }
    }

    if (!is_array($row)) {
        $row = array_fill(0, count($source['header']), '');
        $row[$source['idIndex']] = $studentNumber;
        if ($fallbackName !== '') {
            $row[$source['nameIndex']] = dent_clean_text($fallbackName, 120);
        }
    }

    $name = trim((string) ($row[$source['nameIndex']] ?? ''));
    if ($name === '') {
        $name = dent_clean_text($fallbackName, 120);
    }

    $grades = [];
    foreach ($source['gradeColumns'] as $index => $label) {
        $grades[] = [
            'index' => (int) $index,
            'label' => $label,
            'value' => (string) ($row[$index] ?? ''),
        ];
    }

    return [
        'studentNumber' => $studentNumber,
        'name' => $name,
        'rowExists' => $rowExists,
        'grades' => $grades,
    ];
}

function dent_owner_set_grade(
    string $studentNumber,
    int $columnIndex,
    ?string $gradeValue,
    string $fallbackName = ''
): array {
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }

    $source = dent_read_grades_source();
    if (!array_key_exists($columnIndex, $source['gradeColumns'])) {
        dent_error('ستون نمره نامعتبر است.', 422);
    }

    $finalValue = '';
    $rawValue = trim((string) $gradeValue);
    if ($rawValue !== '') {
        $normalized = dent_normalize_number_string($rawValue);
        if ($normalized === '' || !is_numeric($normalized)) {
            dent_error('مقدار نمره باید عددی باشد.', 422);
        }

        $score = (float) $normalized;
        if ($score < 0) {
            dent_error('نمره منفی مجاز نیست.', 422);
        }

        $finalValue = dent_format_grade_for_store($score);
    }

    $fallbackName = dent_clean_text($fallbackName, 120);
    $rowFound = false;
    foreach ($source['rows'] as $index => $candidate) {
        $candidateStudentNumber = dent_normalize_student_number($candidate[$source['idIndex']] ?? '');
        if ($candidateStudentNumber !== $studentNumber) {
            continue;
        }

        $rowFound = true;
        $row = $candidate;
        if (count($row) < count($source['header'])) {
            $row = array_pad($row, count($source['header']), '');
        }
        $row[$source['idIndex']] = $studentNumber;
        if ($fallbackName !== '' && trim((string) ($row[$source['nameIndex']] ?? '')) === '') {
            $row[$source['nameIndex']] = $fallbackName;
        }
        $row[$columnIndex] = $finalValue;
        $source['rows'][$index] = $row;
        break;
    }

    if (!$rowFound) {
        $row = array_fill(0, count($source['header']), '');
        $row[$source['idIndex']] = $studentNumber;
        if ($fallbackName !== '') {
            $row[$source['nameIndex']] = $fallbackName;
        }
        $row[$columnIndex] = $finalValue;
        $source['rows'][] = $row;
    }

    dent_write_grades_source($source['header'], $source['rows']);
    return dent_owner_grades_payload($studentNumber, $fallbackName);
}

function dent_owner_remove_grades_row(string $studentNumber): bool
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        return false;
    }

    $source = dent_read_grades_source(false);
    if (!is_array($source['header']) || count($source['header']) === 0) {
        return false;
    }

    $keptRows = [];
    $removed = false;
    foreach ($source['rows'] as $row) {
        $candidateStudentNumber = dent_normalize_student_number($row[$source['idIndex']] ?? '');
        if ($candidateStudentNumber === $studentNumber) {
            $removed = true;
            continue;
        }
        $keptRows[] = $row;
    }

    if ($removed) {
        dent_write_grades_source($source['header'], $keptRows);
    }

    return $removed;
}
