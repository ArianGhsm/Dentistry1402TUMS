<?php
declare(strict_types=1);

require_once __DIR__ . '/../public_html/api/bot_payments.php';

function paid_file_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$assetRef = 'abcdefghijklmnop';
$safe = dent_bot_payment_safe_fulfillment([
    'text' => 'تحویل محافظت‌شده',
    'kind' => 'paid_file',
    'action' => 'paid-file-get:' . $assetRef,
    'fileId' => 'telegram-secret-file-id',
    'sourceChatId' => '123',
]);

paid_file_assert(($safe['kind'] ?? '') === 'paid_file', 'paid file kind survives safe fulfillment');
paid_file_assert(($safe['action'] ?? '') === 'paid-file-get:' . $assetRef, 'opaque paid file action survives safe fulfillment');
paid_file_assert(($safe['text'] ?? '') === 'تحویل محافظت‌شده', 'safe fulfillment text survives');
paid_file_assert(!array_key_exists('fileId', $safe), 'Telegram file id never enters payment fulfillment');
paid_file_assert(!array_key_exists('sourceChatId', $safe), 'Telegram source chat never enters payment fulfillment');

$badAction = dent_bot_payment_safe_fulfillment([
    'kind' => 'paid_file',
    'action' => 'paid-file-get:bad!',
]);
paid_file_assert(!isset($badAction['kind']) && !isset($badAction['action']), 'invalid paid file action fails closed');

$unknownKind = dent_bot_payment_safe_fulfillment([
    'kind' => 'other',
    'action' => 'paid-file-get:' . $assetRef,
]);
paid_file_assert(!isset($unknownKind['kind']) && !isset($unknownKind['action']), 'unknown fulfillment kind fails closed');

echo "Paid file fulfillment contract: ok\n";
