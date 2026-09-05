<?php
declare(strict_types=1);

function fail_test(string $message): never
{
    fwrite(STDERR, "DELIVERY_LOAD_TEST_FAILED: " . $message . PHP_EOL);
    exit(1);
}

$mode = (string) ($argv[1] ?? 'parent');
$durationSeconds = 0;
if (str_starts_with($mode, '--duration-seconds=')) {
    $durationSeconds = max(0, min(300, (int) substr($mode, strlen('--duration-seconds='))));
    $mode = 'parent';
}
if ($mode === 'worker') {
    require_once dirname(__DIR__) . '/public_html/api/bot_store.php';
    $platform = (string) ($argv[2] ?? '');
    $claimed = 0;
    for ($cycle = 0; $cycle < 3; $cycle++) {
        $result = dent_bot_claim_payment_result_deliveries($platform, [
            'platformUserId' => '999999',
            'contractVersion' => 'bot-payment-return-v1',
            'limit' => 20,
        ]);
        $claimed += count($result['deliveries'] ?? []);
    }
    echo json_encode(['platform' => $platform, 'claimed' => $claimed], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dent-delivery-load-' . bin2hex(random_bytes(6));
$storage = $root . DIRECTORY_SEPARATOR . 'storage';
$serverOnly = $root . DIRECTORY_SEPARATOR . 'server-only';
mkdir($storage, 0700, true);
mkdir($serverOnly, 0700, true);
putenv('DENT_STORAGE_ROOT=' . $storage);
putenv('DENT_SERVER_ONLY_ROOT=' . $serverOnly);
putenv('DENT_AUTH_SECRET_KEY=' . base64_encode(str_repeat('k', 32)));
putenv('DENT_BOT_SERVICE_SECRET=' . str_repeat('ab', 32));
putenv('DENT_SITE_PUBLIC_URL=https://example.test');
putenv('DENT_APP_ENV=test');

require_once dirname(__DIR__) . '/public_html/api/bot_store.php';

try {
    $identity = dent_bot_store_default();
    for ($index = 1; $index <= 109; $index++) {
        $platform = $index <= 93 ? 'telegram' : 'bale';
        $platformId = (string) (700000 + $index);
        $hash = dent_bot_identity_hash($platform, $platformId);
        $identity['links'][$hash] = [
            'platform' => $platform,
            'platformUserIdEncrypted' => dent_encrypt_secret_text($platformId),
            'studentNumber' => (string) (402100000 + $index),
            'authVersion' => dent_bot_canonical_auth_version(),
            'authCompletedAt' => dent_iso_now(),
            'authMethod' => 'secure-site-login',
        ];
    }
    for ($index = 1; $index <= 100; $index++) {
        $identity['onboardingProfiles']['profile-' . $index] = ['profileRef' => 'profile-' . $index];
    }
    for ($index = 1; $index <= 400; $index++) {
        $identity['notificationDeliveries']['legacy-notification-' . $index] = [
            'deliveryId' => 'nd-' . str_pad(dechex($index), 32, '0', STR_PAD_LEFT),
            'notificationId' => 'nt-load-' . $index,
            'identityHash' => array_key_first($identity['links']),
            'platform' => 'telegram',
            'status' => 'delivered',
            'attempts' => 1,
            'leaseUntil' => 0,
            'deliveredAt' => dent_iso_now(),
        ];
    }

    $orders = [];
    for ($index = 1; $index <= 1100; $index++) {
        $platform = $index % 2 === 0 ? 'telegram' : 'bale';
        $platformId = (string) (800000 + ($index % 100));
        $identityHash = dent_bot_identity_hash($platform, $platformId);
        $route = dent_encrypt_secret_text($platformId);
        $orders[] = [
            'id' => $index,
            'public_token' => 'load-' . $index,
            'amount' => 10000,
            'status' => PAYMENTS_ORDER_STATUS_SUCCESS,
            'gateway' => 'mock',
            'created_at' => gmdate('c', 1700000000 + $index),
            'updated_at' => gmdate('c', 1700000000 + $index),
            'extra_form_data' => [
                'source' => 'bot-offer',
                'bot_offer_ref' => 'load_offer_abcdefghijklmnop',
                'bot_offer_title' => 'Load fixture',
                'bot_origin_platform' => $platform,
                'bot_origin_identity_hash' => $identityHash,
                'bot_origin_route_encrypted_json' => json_encode($route, JSON_UNESCAPED_SLASHES),
            ],
        ];
        if ($index <= 1000) {
            $dedupe = $index . '|user|' . $platform . '|' . $identityHash;
            $deliveryId = 'prd-' . substr(hash_hmac('sha256', $dedupe, dent_auth_secret_key()), 0, 32);
            $identity['paymentResultDeliveries'][$deliveryId] = [
                'deliveryId' => $deliveryId,
                'dedupeKey' => hash('sha256', $dedupe),
                'kind' => 'user',
                'platform' => $platform,
                'identityHash' => $identityHash,
                'platformUserIdEncrypted' => $route,
                'orderId' => $index,
                'order' => dent_bot_payment_order_payload($orders[array_key_last($orders)]),
                'status' => 'delivered',
                'attempts' => 1,
                'leaseUntil' => 0,
                'createdAt' => dent_iso_now(),
                'lastAttemptAt' => dent_iso_now(),
                'deliveredAt' => dent_iso_now(),
                'reasonCode' => '',
            ];
        }
    }
    $identity['paymentResultPoll'] = ['highWatermark' => 1000, 'unresolvedOrderIds' => []];
    $identity['notificationDispatchSince'] = gmdate('c', 1700000000);
    dent_bot_persistence_initialize(dent_bot_store_path(), $identity, 'dent_bot_store_normalize', 'load-identity-init');

    $paymentData = payments_default_store();
    $paymentData['orders'] = $orders;
    $paymentData['nextOrderId'] = 1101;
    dent_write_json_file(payments_store_path(), $paymentData);

    dent_bot_delivery_stores_ensure_migrated();
    $paymentPath = dent_bot_payment_delivery_store_path();
    $notificationPath = dent_bot_notification_delivery_store_path();
    $migratedPayment = json_decode((string) file_get_contents($paymentPath), true);
    $migratedNotification = json_decode((string) file_get_contents($notificationPath), true);
    if (!is_array($migratedPayment) || count($migratedPayment['deliveries'] ?? []) !== 1000) {
        fail_test('Payment migration count mismatch');
    }
    if (!is_array($migratedNotification) || count($migratedNotification['deliveries'] ?? []) !== 400) {
        fail_test('Notification migration count mismatch');
    }

    $zeroBefore = hash_file('sha256', $paymentPath);
    $zeroGeneration = (int) ($migratedPayment['_storage']['generation'] ?? 0);
    $started = microtime(true);
    $zero = dent_bot_payment_delivery_store_with_lock(
        static fn(array &$store): array => dent_bot_backfill_payment_success_deliveries($store, $identity, array_slice($orders, 0, 1000)),
        'load-zero-new'
    );
    $zeroMs = (microtime(true) - $started) * 1000;
    $afterZero = json_decode((string) file_get_contents($paymentPath), true);
    if (($zero['scanned'] ?? -1) !== 0 || hash_file('sha256', $paymentPath) !== $zeroBefore
        || (int) ($afterZero['_storage']['generation'] ?? 0) !== $zeroGeneration) {
        fail_test('Historical zero-new poll performed a write');
    }

    $started = microtime(true);
    $newResult = dent_bot_payment_delivery_store_with_lock(
        static fn(array &$store): array => dent_bot_backfill_payment_success_deliveries($store, $identity, $orders),
        'load-100-new'
    );
    $newMs = (microtime(true) - $started) * 1000;
    if (($newResult['created'] ?? -1) !== 100 || ($newResult['scanned'] ?? -1) !== 100) {
        fail_test('New payment batch was not exact');
    }
    $afterNew = json_decode((string) file_get_contents($paymentPath), true);
    $newGeneration = (int) ($afterNew['_storage']['generation'] ?? 0);
    if (count($afterNew['deliveries'] ?? []) !== 1100 || $newGeneration !== $zeroGeneration + 1) {
        fail_test('New payments were not committed in one generation');
    }
    $pendingByPlatform = ['telegram' => 0, 'bale' => 0];
    $decryptablePending = 0;
    foreach ($afterNew['deliveries'] ?? [] as $item) {
        if (is_array($item) && (string) ($item['status'] ?? '') === 'pending') {
            $itemPlatform = (string) ($item['platform'] ?? '');
            if (array_key_exists($itemPlatform, $pendingByPlatform)) {
                $pendingByPlatform[$itemPlatform]++;
            }
            if (preg_match('/^[0-9]{1,24}$/', dent_decrypt_secret_text($item['platformUserIdEncrypted'] ?? null)) === 1) {
                $decryptablePending++;
            }
        }
    }
    $duplicateHash = hash_file('sha256', $paymentPath);
    $duplicate = dent_bot_payment_delivery_store_with_lock(
        static fn(array &$store): array => dent_bot_backfill_payment_success_deliveries($store, $identity, $orders),
        'load-duplicate-poll'
    );
    if (($duplicate['created'] ?? -1) !== 0 || ($duplicate['scanned'] ?? -1) !== 0
        || hash_file('sha256', $paymentPath) !== $duplicateHash) {
        fail_test('Duplicate poll was not a zero-write no-op');
    }

    $identityRaw = (string) file_get_contents(dent_bot_store_path());
    file_put_contents(dent_bot_store_path(), '{malformed', LOCK_EX);
    $phpCommand = escapeshellarg(PHP_BINARY);
    if (PHP_OS_FAMILY === 'Windows' && extension_loaded('openssl')) {
        $phpCommand .= ' -d ' . escapeshellarg('extension_dir=' . (string) ini_get('extension_dir'))
            . ' -d ' . escapeshellarg('extension=php_openssl.dll');
    }
    $commandBase = $phpCommand . ' ' . escapeshellarg(__FILE__) . ' worker ';
    $processes = [];
    foreach (['telegram', 'bale'] as $platform) {
        $pipes = [];
        $process = proc_open($commandBase . escapeshellarg($platform), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            fail_test('Unable to start platform worker');
        }
        $processes[] = [$platform, $process, $pipes];
    }
    $workerResults = [];
    foreach ($processes as [$platform, $process, $pipes]) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0) {
            fail_test('Concurrent worker failed: ' . $platform . ' ' . $stderr);
        }
        $lines = preg_split('/\R/', trim((string) $stdout)) ?: [];
        $decoded = json_decode((string) end($lines), true);
        $workerResults[$platform] = (int) ($decoded['claimed'] ?? -1);
    }
    file_put_contents(dent_bot_store_path(), $identityRaw, LOCK_EX);
    if ($workerResults !== ['telegram' => 50, 'bale' => 50]) {
        fail_test('Telegram/Bale concurrent claims were not exact: ' . json_encode([
            'workers' => $workerResults,
            'pendingBefore' => $pendingByPlatform,
            'decryptableBefore' => $decryptablePending,
        ]));
    }
    $final = json_decode((string) file_get_contents($paymentPath), true);
    if (!is_array($final) || count($final['deliveries'] ?? []) !== 1100) {
        fail_test('Delivery store corrupted during concurrent claim');
    }
    $newStatuses = array_count_values(array_map(
        static fn(array $item): string => (string) ($item['status'] ?? ''),
        array_filter($final['deliveries'] ?? [], static fn(array $item): bool => (int) ($item['orderId'] ?? 0) > 1000)
    ));
    if (($newStatuses['leased'] ?? 0) !== 100) {
        fail_test('Concurrent claims lost or duplicated deliveries');
    }

    $ackWrites = 0;
    foreach (['telegram', 'bale'] as $platform) {
        $platformLeased = array_values(array_filter(
            $final['deliveries'] ?? [],
            static fn(array $item): bool => (int) ($item['orderId'] ?? 0) > 1000
                && (string) ($item['platform'] ?? '') === $platform
                && (string) ($item['status'] ?? '') === 'leased'
        ));
        foreach (array_chunk($platformLeased, 20) as $chunk) {
            $ack = dent_bot_ack_payment_result_deliveries($platform, [
                'platformUserId' => '999999',
                'contractVersion' => 'bot-payment-return-v1',
                'results' => array_map(static fn(array $item): array => [
                    'deliveryId' => (string) $item['deliveryId'],
                    'delivered' => true,
                    'reasonCode' => '',
                ], $chunk),
            ]);
            if (count($ack['results'] ?? []) !== count($chunk)) {
                fail_test('Batch acknowledgement lost a delivery result');
            }
            $ackWrites++;
        }
    }
    if ($ackWrites !== 6) {
        fail_test('100 acknowledgements were not bounded to six platform batches');
    }
    $final = json_decode((string) file_get_contents($paymentPath), true);

    $steadyHash = hash_file('sha256', $paymentPath);
    $steadyGeneration = (int) ($final['_storage']['generation'] ?? 0);
    $steadyCycles = 0;
    $steadyStarted = microtime(true);
    while ($durationSeconds > 0 && (microtime(true) - $steadyStarted) < $durationSeconds) {
        foreach (['telegram', 'bale'] as $platform) {
            dent_bot_claim_payment_result_deliveries($platform, [
                'platformUserId' => '999999',
                'contractVersion' => 'bot-payment-return-v1',
                'limit' => 20,
            ]);
        }
        $steadyCycles++;
        usleep(500000);
    }
    $steadyFinal = json_decode((string) file_get_contents($paymentPath), true);
    if (!is_array($steadyFinal)
        || hash_file('sha256', $paymentPath) !== $steadyHash
        || (int) ($steadyFinal['_storage']['generation'] ?? 0) !== $steadyGeneration) {
        fail_test('Steady-state polling caused an unnecessary queue rewrite');
    }

    $combinedLegacyBytes = strlen(dent_bot_persistence_encode($identity));
    $splitIdentityBytes = filesize(dent_bot_store_path());
    $finalPaymentBytes = filesize($paymentPath);
    $finalNotificationBytes = filesize($notificationPath);
    echo json_encode([
        'status' => 'passed',
        'fixture' => ['links' => 109, 'profiles' => 100, 'notificationDeliveries' => 400, 'historicalPayments' => 1000, 'newPayments' => 100],
        'zeroNew' => ['writes' => 0, 'elapsedMs' => round($zeroMs, 3)],
        'newBatch' => ['writes' => 1, 'created' => 100, 'elapsedMs' => round($newMs, 3)],
        'duplicatePoll' => ['writes' => 0],
        'concurrentClaims' => $workerResults,
        'batchAcknowledgement' => ['deliveries' => 100, 'writes' => $ackWrites],
        'steadyState' => [
            'durationSeconds' => round(microtime(true) - $steadyStarted, 3),
            'cyclesPerPlatform' => $steadyCycles,
            'writes' => 0,
            'oldModeEstimatedWrites' => $steadyCycles * 2,
        ],
        'identityCorruptionIsolation' => true,
        'peakPhpMemoryBytes' => memory_get_peak_usage(true),
        'bytes' => ['legacyCombined' => $combinedLegacyBytes, 'splitIdentity' => $splitIdentityBytes, 'paymentQueue' => $finalPaymentBytes, 'notificationQueue' => $finalNotificationBytes],
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($root);
    }
}
