<?php
declare(strict_types=1);

require_once __DIR__ . '/../public_html/api/private_notes_processing.php';

$limit = 1;
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', (string) $arg, $matches) === 1) {
        $limit = max(1, min(50, (int) $matches[1]));
    }
}

$processed = 0;
$failed = 0;
for ($index = 0; $index < $limit; $index++) {
    $result = private_notes_process_next_pending_job();
    if (empty($result['processed'])) {
        echo (string) ($result['message'] ?? 'No pending private-notes processing jobs.') . PHP_EOL;
        break;
    }

    $processed++;
    $documentId = (string) ($result['documentId'] ?? '');
    $jobId = (string) ($result['jobId'] ?? '');
    if (!empty($result['ok'])) {
        echo 'OK: processed ' . $jobId . ' for ' . $documentId . PHP_EOL;
        continue;
    }

    $failed++;
    echo 'FAILED: processed ' . $jobId . ' for ' . $documentId . ' - ' . (string) ($result['error'] ?? 'unknown error') . PHP_EOL;
}

echo 'Private notes jobs processed: ' . $processed . ', failed: ' . $failed . PHP_EOL;
exit($failed > 0 ? 1 : 0);

