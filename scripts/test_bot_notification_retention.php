<?php
declare(strict_types=1);

// Pruning old notification deliveries must also move dispatchSince forward,
// or a pruned (already delivered) notification would be pushed again.
function fail_test(string $message): never
{
    fwrite(STDERR, "NOTIFICATION_RETENTION_TEST_FAILED: " . $message . PHP_EOL);
    exit(1);
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dent-retention-' . bin2hex(random_bytes(6));
mkdir($root . '/storage', 0700, true);
mkdir($root . '/server-only', 0700, true);
putenv('DENT_STORAGE_ROOT=' . $root . '/storage');
putenv('DENT_SERVER_ONLY_ROOT=' . $root . '/server-only');
putenv('DENT_AUTH_SECRET_KEY=' . base64_encode(str_repeat('k', 32)));
putenv('DENT_BOT_SERVICE_SECRET=' . str_repeat('ab', 32));
putenv('DENT_APP_ENV=test');
putenv('DENT_BOT_NOTIFICATION_DELIVERY_RETENTION_DAYS');

require_once dirname(__DIR__) . '/public_html/api/bot_store.php';

$now = strtotime('2026-10-26T00:00:00Z');
$day = 86400;
$store = dent_bot_notification_delivery_store_default();
$store['dispatchSince'] = gmdate('c', $now - 60 * $day);
$store['deliveries'] = [
    'old-delivered' => ['status' => 'delivered', 'deliveredAt' => gmdate('c', $now - 40 * $day)],
    'old-failed' => ['status' => 'failed', 'lastAttemptAt' => gmdate('c', $now - 35 * $day)],
    'old-pending' => ['status' => 'pending', 'lastAttemptAt' => gmdate('c', $now - 40 * $day)],
    'recent' => ['status' => 'delivered', 'deliveredAt' => gmdate('c', $now - 5 * $day)],
];
dent_bot_cleanup_notification_delivery_store($store, $now);

if (isset($store['deliveries']['old-delivered']) || isset($store['deliveries']['old-failed'])) {
    fail_test('terminal deliveries older than the 30-day default were kept');
}
if (!isset($store['deliveries']['old-pending'], $store['deliveries']['recent'])) {
    fail_test('pending or recent deliveries were pruned');
}
$since = strtotime((string) $store['dispatchSince']);
if ($since === false || $since < $now - 30 * $day) {
    fail_test('dispatchSince was not advanced to the retention cutoff');
}

$store['dispatchSince'] = gmdate('c', $now - 2 * $day);
dent_bot_cleanup_notification_delivery_store($store, $now);
if (strtotime((string) $store['dispatchSince']) !== $now - 2 * $day) {
    fail_test('a newer dispatchSince was moved backwards');
}

echo "NOTIFICATION_RETENTION_TEST_OK" . PHP_EOL;
