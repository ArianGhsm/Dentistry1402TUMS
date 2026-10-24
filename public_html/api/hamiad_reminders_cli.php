<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/hamiad_reminders.php';

$dryRun = in_array('--dry-run', $argv, true);
try {
    $result = hamiad_process_reminders($dryRun);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, "پایش تکالیف همیاد ناموفق بود؛ نشست دانشگاه یا ارتباط سرور بررسی شود.\n");
    exit(1);
}
