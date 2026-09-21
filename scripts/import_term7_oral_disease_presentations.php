<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../public_html/api/bot_store.php';
restore_error_handler();
restore_exception_handler();

$options = getopt('', ['commit']);
$commit = array_key_exists('commit', $options);

try {
    $report = dent_term7_import_oral_disease_presentations($commit);
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    if (
        ($report['ambiguous'] ?? []) !== []
        || ($report['duplicates'] ?? []) !== []
        || ($report['missingCurrentStudents'] ?? []) !== []
        || (int) ($report['matchedStudentCount'] ?? 0) !== (int) ($report['currentStudentCount'] ?? 0)
    ) {
        exit(1);
    }
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
