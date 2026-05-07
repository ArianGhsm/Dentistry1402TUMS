<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line." . PHP_EOL);
    exit(2);
}

require_once __DIR__ . '/../public_html/api/auth_store.php';

function dent_deploy_sms_emit(array $payload, int $exitCode): void
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    echo json_encode($payload, $flags) . PHP_EOL;
    exit($exitCode);
}

$options = getopt('', [
    'phone:',
    'dry-run',
]);

$phoneNumber = (string) ($options['phone'] ?? '09009840305');
$dryRun = array_key_exists('dry-run', $options);
$normalizedPhone = dent_normalize_phone_number($phoneNumber);

if ($normalizedPhone === '') {
    dent_deploy_sms_emit([
        'success' => false,
        'sent' => false,
        'status' => 'failed',
        'message' => 'Invalid owner phone number.',
    ], 2);
}

$status = dent_sms_status_payload();
$phoneMasked = dent_mask_phone_number($normalizedPhone);

if ($dryRun) {
    dent_deploy_sms_emit([
        'success' => true,
        'sent' => false,
        'status' => 'skipped-dry-run',
        'phoneMasked' => $phoneMasked,
        'message' => 'Completion SMS dry run skipped before sending.',
        'smsStatus' => $status,
    ], 0);
}

$ready = (bool) ($status['enabled'] ?? false)
    && (bool) ($status['apiKeyConfigured'] ?? false)
    && (bool) ($status['patternConfigured'] ?? false)
    && (bool) ($status['senderLineConfigured'] ?? false);

if (!$ready) {
    dent_deploy_sms_emit([
        'success' => false,
        'sent' => false,
        'status' => 'failed',
        'phoneMasked' => $phoneMasked,
        'message' => 'SMS service is not fully configured for deployment completion notifications.',
        'smsStatus' => $status,
    ], 1);
}

$code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$sendResult = dent_sms_send_pattern($normalizedPhone, $code);
$success = (bool) ($sendResult['success'] ?? false);
$message = (string) ($sendResult['message'] ?? '');
dent_sms_health_store_update($success, $message);

dent_deploy_sms_emit([
    'success' => $success,
    'sent' => $success,
    'status' => $success ? 'completed' : 'failed',
    'phoneMasked' => $phoneMasked,
    'codeLength' => strlen($code),
    'message' => $message,
    'httpStatus' => (int) ($sendResult['httpStatus'] ?? 0),
    'messageCode' => (string) ($sendResult['messageCode'] ?? ''),
    'smsStatus' => dent_sms_status_payload(),
], $success ? 0 : 1);
