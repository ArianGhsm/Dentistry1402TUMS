<?php
declare(strict_types=1);

// SMS provider/configuration primitives extracted from auth_store.php.

function dent_sms_env_bool(string $name): ?bool
{
    $raw = getenv($name);
    if ($raw === false) {
        return null;
    }
    $parsed = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return is_bool($parsed) ? $parsed : null;
}

function dent_normalize_sms_sender_number(string $value): string
{
    $clean = trim(dent_normalize_digits($value));
    if ($clean === '') {
        return '';
    }
    $digits = preg_replace('/\D+/u', '', $clean) ?? '';
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '0098')) {
        $digits = substr($digits, 2);
    }

    return $digits;
}

function dent_sms_provider_recipient_number(string $value): string
{
    $clean = trim(dent_normalize_digits($value));
    if ($clean === '') {
        return '';
    }

    if (str_starts_with($clean, '+')) {
        $clean = substr($clean, 1);
    }

    $digits = preg_replace('/\D+/u', '', $clean) ?? '';
    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '0098')) {
        $digits = '0' . substr($digits, 4);
    } elseif (str_starts_with($digits, '98')) {
        $digits = '0' . substr($digits, 2);
    } elseif (str_starts_with($digits, '9')) {
        $digits = '0' . $digits;
    }

    if (strlen($digits) !== 11 || !str_starts_with($digits, '09')) {
        return '';
    }

    return $digits;
}

function dent_sms_log(string $event, array $context = []): void
{
    $safe = [
        'event' => $event,
        'at' => dent_iso_now(),
    ];
    foreach ($context as $key => $value) {
        if (in_array((string) $key, ['apiKey', 'authorization', 'otpCode', 'code'], true)) {
            continue;
        }
        $safe[(string) $key] = is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    error_log('[dent_sms] ' . json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function dent_sms_resolved_config(): array
{
    $meta = dent_load_auth_meta_store();
    $sms = is_array($meta['sms'] ?? null) ? $meta['sms'] : [];

    $storedApiKey = dent_decrypt_secret_text($sms['apiKeyEncrypted'] ?? null);
    $envApiKey = trim((string) (getenv('DENT_SMS_FARAZ_API_KEY') ?: ''));
    $apiKey = $storedApiKey !== '' ? $storedApiKey : $envApiKey;

    $patternCode = dent_clean_text((string) ($sms['patternCode'] ?? ''), 80);
    if ($patternCode === '') {
        $patternCode = dent_clean_text((string) (getenv('DENT_SMS_FARAZ_PATTERN_CODE') ?: ''), 80);
    }

    $senderLine = dent_clean_text((string) ($sms['senderLine'] ?? ''), 40);
    if ($senderLine === '') {
        $senderLine = dent_clean_text((string) (getenv('DENT_SMS_FARAZ_SENDER_LINE') ?: ''), 40);
    }
    $senderLine = dent_normalize_sms_sender_number($senderLine);

    $domain = dent_clean_text((string) ($sms['domain'] ?? ''), 120);
    if ($domain === '') {
        $domain = dent_clean_text((string) (getenv('DENT_SMS_FARAZ_DOMAIN') ?: ''), 120);
    }
    if ($domain === '') {
        $domain = dent_clean_text((string) ($_SERVER['HTTP_HOST'] ?? ''), 120);
        $domain = preg_replace('/:\d+$/', '', $domain) ?? '';
    }
    $domain = trim($domain, " \t\n\r\0\x0B/");

    $codeParam = dent_clean_text((string) ($sms['codeParam'] ?? ''), 40);
    if ($codeParam === '') {
        $codeParam = dent_clean_text((string) (getenv('DENT_SMS_FARAZ_CODE_PARAM') ?: 'code'), 40);
    }
    if ($codeParam === '') {
        $codeParam = 'code';
    }

    $enabled = (bool) ($sms['enabled'] ?? false);
    $envEnabled = dent_sms_env_bool('DENT_SMS_FARAZ_ENABLED');
    if ($envEnabled !== null) {
        $enabled = $envEnabled;
    } elseif ($storedApiKey === '' && $envApiKey !== '' && $patternCode !== '' && $senderLine !== '') {
        $enabled = true;
    }

    return [
        'enabled' => $enabled,
        'apiKey' => $apiKey,
        'patternCode' => $patternCode,
        'senderLine' => $senderLine,
        'domain' => $domain,
        'codeParam' => $codeParam,
        'lastHealthAt' => (string) ($sms['lastHealthAt'] ?? ''),
        'lastHealthStatus' => (string) ($sms['lastHealthStatus'] ?? 'unknown'),
        'lastHealthMessage' => (string) ($sms['lastHealthMessage'] ?? ''),
    ];
}

function dent_sms_health_store_update(bool $ok, string $message): void
{
    $meta = dent_load_auth_meta_store();
    if (!is_array($meta['sms'] ?? null)) {
        $meta['sms'] = dent_default_auth_meta_store()['sms'];
    }
    $meta['sms']['lastHealthAt'] = dent_iso_now();
    $meta['sms']['lastHealthStatus'] = $ok ? 'ok' : 'error';
    $meta['sms']['lastHealthMessage'] = dent_clean_text($message, 220);
    dent_save_auth_meta_store($meta);
}

function dent_sms_status_payload(): array
{
    $config = dent_sms_resolved_config();
    return [
        'enabled' => (bool) $config['enabled'],
        'provider' => 'farazsms',
        'apiKeyConfigured' => trim((string) $config['apiKey']) !== '',
        'patternConfigured' => trim((string) $config['patternCode']) !== '',
        'senderLineConfigured' => trim((string) $config['senderLine']) !== '',
        'senderLine' => (string) $config['senderLine'],
        'domainConfigured' => trim((string) $config['domain']) !== '',
        'domain' => (string) $config['domain'],
        'codeParam' => (string) $config['codeParam'],
        'lastHealthAt' => (string) $config['lastHealthAt'],
        'lastHealthStatus' => (string) $config['lastHealthStatus'],
        'lastHealthMessage' => (string) $config['lastHealthMessage'],
    ];
}

function dent_save_sms_owner_config(array $input): array
{
    $meta = dent_load_auth_meta_store();
    if (!is_array($meta['sms'] ?? null)) {
        $meta['sms'] = dent_default_auth_meta_store()['sms'];
    }

    $enabled = filter_var($input['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $meta['sms']['enabled'] = is_bool($enabled) ? $enabled : false;
    $meta['sms']['patternCode'] = dent_clean_text((string) ($input['patternCode'] ?? ''), 80);
    $meta['sms']['senderLine'] = dent_clean_text((string) ($input['senderLine'] ?? ''), 40);
    $meta['sms']['domain'] = dent_clean_text((string) ($input['domain'] ?? ''), 120);
    $meta['sms']['domain'] = trim((string) $meta['sms']['domain'], " \t\n\r\0\x0B/");
    $meta['sms']['codeParam'] = dent_clean_text((string) ($input['codeParam'] ?? 'code'), 40);
    if ($meta['sms']['codeParam'] === '') {
        $meta['sms']['codeParam'] = 'code';
    }

    $apiKey = trim((string) ($input['apiKey'] ?? ''));
    $clearApiKey = filter_var($input['clearApiKey'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    if ($clearApiKey) {
        $meta['sms']['apiKeyEncrypted'] = null;
    } elseif ($apiKey !== '') {
        $meta['sms']['apiKeyEncrypted'] = dent_encrypt_secret_text($apiKey);
    }

    $meta['sms']['updatedAt'] = dent_iso_now();
    dent_save_auth_meta_store($meta);
    return dent_sms_status_payload();
}

function dent_sms_send_pattern(string $phoneNumber, string $otpCode): array
{
    $config = dent_sms_resolved_config();
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    $providerPhone = dent_sms_provider_recipient_number($normalizedPhone);

    if (!(bool) $config['enabled']) {
        dent_sms_log('send_blocked', ['reason' => 'disabled', 'phone' => dent_mask_phone_number($normalizedPhone)]);
        return ['success' => false, 'message' => 'سرویس پیامکی غیرفعال است.'];
    }
    if (trim((string) $config['apiKey']) === '') {
        dent_sms_log('send_blocked', ['reason' => 'missing_api_key', 'phone' => dent_mask_phone_number($normalizedPhone)]);
        return ['success' => false, 'message' => 'کلید API سرویس پیامکی تنظیم نشده است.'];
    }
    if (trim((string) $config['patternCode']) === '') {
        dent_sms_log('send_blocked', ['reason' => 'missing_pattern', 'phone' => dent_mask_phone_number($normalizedPhone)]);
        return ['success' => false, 'message' => 'کد پترن پیامکی تنظیم نشده است.'];
    }
    if (trim((string) $config['senderLine']) === '') {
        dent_sms_log('send_blocked', ['reason' => 'missing_sender', 'phone' => dent_mask_phone_number($normalizedPhone)]);
        return ['success' => false, 'message' => 'لاین/شماره ارسال پیامک تنظیم نشده است.'];
    }

    if ($normalizedPhone === '' || $providerPhone === '') {
        return ['success' => false, 'message' => 'شماره موبایل مقصد نامعتبر است.'];
    }

    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'cURL روی سرور فعال نیست.'];
    }

    $params = [
        (string) $config['codeParam'] => $otpCode,
    ];
    if (trim((string) $config['domain']) !== '') {
        $webOtpLine = '@' . (string) $config['domain'] . ' #' . $otpCode;
        $params['domain'] = (string) $config['domain'];
        $params['webotp'] = $webOtpLine;
        $params['webOtpLine'] = $webOtpLine;
    }

    $payload = [
        'code' => (string) $config['patternCode'],
        'recipient' => $providerPhone,
        'line_number' => (string) $config['senderLine'],
        'number_format' => 'english',
        'attributes' => $params,
    ];

    $attempts = 3;
    $raw = '';
    $httpCode = 0;
    $curlError = '';
    $decoded = null;

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        dent_sms_log('send_attempt', [
            'attempt' => $attempt,
            'phone' => dent_mask_phone_number($normalizedPhone),
            'patternConfigured' => true,
            'sender' => (string) $config['senderLine'],
            'params' => implode(',', array_keys($params)),
        ]);

        $ch = curl_init('https://api.iranpayamak.com/ws/v1/sms/pattern');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Api-Key: ' . (string) $config['apiKey'],
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $raw = is_string($response) ? $response : '';
        $decoded = $raw !== '' ? json_decode($raw, true) : null;

        $temporaryFailure = $curlError !== '' || $httpCode >= 500;
        if (!$temporaryFailure) {
            break;
        }
        if ($attempt < $attempts) {
            usleep(250000 * $attempt);
            continue;
        }
    }

    if ($raw === '') {
        dent_sms_log('send_failed', [
            'reason' => 'empty_response',
            'phone' => dent_mask_phone_number($normalizedPhone),
            'httpStatus' => $httpCode,
            'curlError' => dent_clean_text($curlError, 160),
        ]);
        return [
            'success' => false,
            'message' => $curlError !== '' ? ('خطای ارتباط با سرویس پیامک: ' . $curlError) : 'پاسخی از سرویس پیامکی دریافت نشد.',
            'httpStatus' => $httpCode,
        ];
    }

    if (!is_array($decoded)) {
        dent_sms_log('send_failed', [
            'reason' => 'invalid_json',
            'phone' => dent_mask_phone_number($normalizedPhone),
            'httpStatus' => $httpCode,
        ]);
        if ($httpCode >= 500) {
            return ['success' => false, 'message' => 'سرویس پیامکی موقتاً در دسترس نیست.', 'httpStatus' => $httpCode];
        }
        $brief = dent_clean_text(trim(strip_tags($raw)), 120);
        $detail = $brief !== '' ? (' جزئیات: ' . $brief) : '';
        return ['success' => false, 'message' => 'پاسخ سرویس پیامکی نامعتبر است.' . $detail, 'httpStatus' => $httpCode];
    }

    $providerResult = dent_sms_parse_pattern_response($decoded, $httpCode);
    $ok = (bool) $providerResult['success'];
    $messageCode = (string) $providerResult['messageCode'];
    $providerRequestId = (string) $providerResult['providerRequestId'];
    $message = (string) $providerResult['message'];

    dent_sms_log($ok ? 'send_ok' : 'send_failed', [
        'phone' => dent_mask_phone_number($normalizedPhone),
        'httpStatus' => $httpCode,
        'messageCode' => $messageCode,
        'providerRequestId' => $providerRequestId,
        'providerMessage' => $message,
    ]);

    return [
        'success' => $ok,
        'message' => $message,
        'httpStatus' => $httpCode,
        'messageCode' => $messageCode,
        'providerRequestId' => $providerRequestId,
    ];
}

/**
 * Normalize the current IranPayamak/FarazSMS pattern endpoint response.
 *
 * A successful response means the provider accepted the request. It does not
 * prove delivery to the handset, so callers must not present it as delivered.
 */
function dent_sms_parse_pattern_response(array $decoded, int $httpCode): array
{
    $statusRaw = strtolower(trim((string) ($decoded['status'] ?? '')));
    $ok = $httpCode >= 200 && $httpCode < 300 && $statusRaw === 'success';
    $messageCode = dent_clean_text((string) ($decoded['code'] ?? ''), 40);
    $providerRequestId = '';
    if (is_string($decoded['data'] ?? null) || is_int($decoded['data'] ?? null)) {
        $providerRequestId = dent_clean_text((string) $decoded['data'], 80);
    }

    $message = '';
    $rawMessage = $decoded['messages'] ?? ($decoded['message'] ?? '');
    if (is_string($rawMessage)) {
        $message = dent_clean_text($rawMessage, 220);
    } elseif (is_array($rawMessage)) {
        foreach ($rawMessage as $messageItem) {
            if (is_string($messageItem)) {
                $message = dent_clean_text($messageItem, 220);
                if ($message !== '') {
                    break;
                }
                continue;
            }
            if (!is_array($messageItem)) {
                continue;
            }
            foreach ($messageItem as $nestedMessage) {
                if (!is_string($nestedMessage)) {
                    continue;
                }
                $message = dent_clean_text($nestedMessage, 220);
                if ($message !== '') {
                    break 2;
                }
            }
        }
    }
    if ($message === '') {
        $message = $ok ? 'درخواست ارسال در سرویس پیامکی ثبت شد.' : 'ارسال پیامک انجام نشد.';
    }
    if (!$ok && $httpCode >= 500) {
        $message = 'سرویس پیامکی موقتاً در دسترس نیست.';
    }

    return [
        'success' => $ok,
        'message' => $message,
        'httpStatus' => $httpCode,
        'messageCode' => $messageCode,
        'providerRequestId' => $providerRequestId,
        'acceptanceOnly' => $ok,
    ];
}

function dent_sms_send_simple(array $phoneNumbers, string $messageText): array
{
    $config = dent_sms_resolved_config();
    $cleanText = dent_clean_text($messageText, 480);

    if (!(bool) $config['enabled']) {
        dent_sms_log('send_simple_blocked', ['reason' => 'disabled']);
        return ['success' => false, 'message' => 'سرویس پیامکی غیرفعال است.'];
    }
    if (trim((string) $config['apiKey']) === '') {
        dent_sms_log('send_simple_blocked', ['reason' => 'missing_api_key']);
        return ['success' => false, 'message' => 'کلید API سرویس پیامکی تنظیم نشده است.'];
    }
    if (trim((string) $config['senderLine']) === '') {
        dent_sms_log('send_simple_blocked', ['reason' => 'missing_sender']);
        return ['success' => false, 'message' => 'لاین/شماره ارسال پیامک تنظیم نشده است.'];
    }
    if ($cleanText === '') {
        return ['success' => false, 'message' => 'متن پیامک خالی است.'];
    }
    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'cURL روی سرور فعال نیست.'];
    }

    $recipients = [];
    foreach ($phoneNumbers as $phoneNumber) {
        $providerPhone = dent_sms_provider_recipient_number(dent_normalize_phone_number((string) $phoneNumber));
        if ($providerPhone === '') {
            continue;
        }
        $recipients[$providerPhone] = true;
    }

    if (!$recipients) {
        return ['success' => false, 'message' => 'هیچ شماره موبایل معتبری برای ارسال پیامک پیدا نشد.'];
    }

    $payload = [
        'text' => $cleanText,
        'line_number' => (string) $config['senderLine'],
        'recipients' => array_keys($recipients),
        'number_format' => 'english',
    ];

    $attempts = 3;
    $raw = '';
    $httpCode = 0;
    $curlError = '';
    $decoded = null;

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        dent_sms_log('send_simple_attempt', [
            'attempt' => $attempt,
            'recipientCount' => count($payload['recipients']),
            'sender' => (string) $config['senderLine'],
        ]);

        $ch = curl_init('https://api.iranpayamak.com/ws/v1/sms/simple');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Api-Key: ' . (string) $config['apiKey'],
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $raw = is_string($response) ? $response : '';
        $decoded = $raw !== '' ? json_decode($raw, true) : null;

        $temporaryFailure = $curlError !== '' || $httpCode >= 500;
        if (!$temporaryFailure) {
            break;
        }
        if ($attempt < $attempts) {
            usleep(250000 * $attempt);
            continue;
        }
    }

    if ($raw === '') {
        dent_sms_log('send_simple_failed', [
            'reason' => 'empty_response',
            'httpStatus' => $httpCode,
            'curlError' => dent_clean_text($curlError, 160),
        ]);
        return [
            'success' => false,
            'message' => $curlError !== '' ? ('خطای ارتباط با سرویس پیامک: ' . $curlError) : 'پاسخی از سرویس پیامکی دریافت نشد.',
            'httpStatus' => $httpCode,
        ];
    }

    if (!is_array($decoded)) {
        dent_sms_log('send_simple_failed', [
            'reason' => 'invalid_json',
            'httpStatus' => $httpCode,
        ]);
        if ($httpCode >= 500) {
            return ['success' => false, 'message' => 'سرویس پیامکی موقتاً در دسترس نیست.', 'httpStatus' => $httpCode];
        }
        $brief = dent_clean_text(trim(strip_tags($raw)), 120);
        $detail = $brief !== '' ? (' جزئیات: ' . $brief) : '';
        return ['success' => false, 'message' => 'پاسخ سرویس پیامکی نامعتبر است.' . $detail, 'httpStatus' => $httpCode];
    }

    $statusRaw = strtolower(trim((string) ($decoded['status'] ?? '')));
    $ok = $statusRaw === 'success';
    $messageCode = dent_clean_text((string) ($decoded['code'] ?? ''), 40);
    $message = '';
    $rawMessage = $decoded['messages'] ?? ($decoded['message'] ?? '');
    if (is_string($rawMessage)) {
        $message = dent_clean_text($rawMessage, 220);
    } elseif (is_array($rawMessage)) {
        foreach ($rawMessage as $messageItem) {
            if (is_string($messageItem)) {
                $message = dent_clean_text($messageItem, 220);
                if ($message !== '') {
                    break;
                }
                continue;
            }
            if (!is_array($messageItem)) {
                continue;
            }
            foreach ($messageItem as $nestedMessage) {
                if (!is_string($nestedMessage)) {
                    continue;
                }
                $message = dent_clean_text($nestedMessage, 220);
                if ($message !== '') {
                    break 2;
                }
            }
        }
    }
    if ($message === '') {
        $message = $ok ? 'ارسال انجام شد.' : 'ارسال پیامک انجام نشد.';
    }
    if (!$ok && $httpCode >= 500) {
        $message = 'سرویس پیامکی موقتاً در دسترس نیست.';
    }

    dent_sms_log($ok ? 'send_simple_ok' : 'send_simple_failed', [
        'httpStatus' => $httpCode,
        'messageCode' => $messageCode,
        'providerMessage' => $message,
        'recipientCount' => count($payload['recipients']),
    ]);

    return [
        'success' => $ok,
        'message' => $message,
        'httpStatus' => $httpCode,
        'messageCode' => $messageCode,
    ];
}

function dent_sms_health_check(?string $phoneNumber = null): array
{
    $status = dent_sms_status_payload();
    if ($phoneNumber === null || trim($phoneNumber) === '') {
        return [
            'success' => $status['enabled'] && $status['apiKeyConfigured'] && $status['patternConfigured'],
            'message' => ($status['enabled'] && $status['apiKeyConfigured'] && $status['patternConfigured'])
                ? 'تنظیمات پیامکی کامل است.'
                : 'تنظیمات پیامکی کامل نیست.',
            'status' => $status,
        ];
    }

    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        dent_error('شماره موبایل تست نامعتبر است.', 422);
    }

    $testCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $sendResult = dent_sms_send_pattern($normalizedPhone, $testCode);
    dent_sms_health_store_update((bool) ($sendResult['success'] ?? false), (string) ($sendResult['message'] ?? ''));
    return [
        'success' => (bool) ($sendResult['success'] ?? false),
        'message' => (string) ($sendResult['message'] ?? ''),
        'status' => dent_sms_status_payload(),
    ];
}
