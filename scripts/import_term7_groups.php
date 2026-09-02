<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../public_html/api/notifications_store.php';
require_once __DIR__ . '/../public_html/api/academic_term7.php';

function term7_import_usage(): never
{
    fwrite(STDERR, "Usage: php scripts/import_term7_groups.php --field=group10|group8 --file=path.csv|path.json [--commit]\n");
    exit(2);
}

function term7_import_rows(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Input file is not readable: ' . $path);
    }
    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    if ($extension === 'json') {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('JSON input must contain an array.');
        }
        return array_values($decoded);
    }
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Cannot open input file.');
    }
    try {
        $header = fgetcsv($handle);
        if (!is_array($header)) {
            return [];
        }
        $header = array_map(static fn($value): string => trim((string) $value), $header);
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (!is_array($values) || array_filter($values, static fn($value): bool => trim((string) $value) !== '') === []) {
                continue;
            }
            $row = [];
            foreach ($header as $index => $key) {
                if ($key !== '') {
                    $row[$key] = (string) ($values[$index] ?? '');
                }
            }
            $rows[] = $row;
        }
        return $rows;
    } finally {
        fclose($handle);
    }
}

$options = getopt('', ['field:', 'file:', 'commit']);
$field = trim((string) ($options['field'] ?? ''));
$file = trim((string) ($options['file'] ?? ''));
if (!in_array($field, ['group10', 'group8'], true) || $file === '') {
    term7_import_usage();
}

try {
    $report = dent_term7_import_assignments(term7_import_rows($file), $field, array_key_exists('commit', $options));
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    if (($report['unmatched'] ?? []) !== [] || ($report['ambiguous'] ?? []) !== [] || ($report['duplicates'] ?? []) !== [] || ($report['invalid'] ?? []) !== []) {
        exit(1);
    }
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
