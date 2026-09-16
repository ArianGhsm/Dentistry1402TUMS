<?php
declare(strict_types=1);

// Provider-neutral OTP issuance and verification engine.

function dent_otp_ttl_seconds(): int
{
    return 180;
}

function dent_otp_cooldown_seconds(): int
{
    return 60;
}

function dent_otp_max_attempts(): int
{
    return 5;
}

function dent_otp_window_seconds(): int
{
    return 3600;
}

function dent_otp_max_send_per_window(): int
{
    return 6;
}

function dent_otp_record_key(string $purpose, string $phoneNumber): string
{
    return hash('sha256', $purpose . '|' . dent_normalize_phone_number($phoneNumber));
}

function dent_otp_cleanup_records(array &$metaStore): bool
{
    $changed = false;
    $now = time();
    $records = is_array($metaStore['otp']['records'] ?? null) ? $metaStore['otp']['records'] : [];
    foreach ($records as $key => $record) {
        if (!is_array($record)) {
            unset($records[$key]);
            $changed = true;
            continue;
        }
        $expiresAt = (int) ($record['expiresAt'] ?? 0);
        $consumedAt = (int) ($record['consumedAt'] ?? 0);
        $issuedAt = (int) ($record['issuedAt'] ?? 0);
        if ($expiresAt > 0 && $expiresAt + 86400 < $now) {
            unset($records[$key]);
            $changed = true;
            continue;
        }
        if ($consumedAt > 0 && $consumedAt + 86400 < $now) {
            unset($records[$key]);
            $changed = true;
            continue;
        }
        if ($issuedAt > 0 && $issuedAt + (2 * 86400) < $now) {
            unset($records[$key]);
            $changed = true;
            continue;
        }
    }

    if ($changed) {
        $metaStore['otp']['records'] = $records;
    }
    return $changed;
}

function dent_issue_otp_for_phone(string $purpose, string $phoneNumber, string $studentNumber = ''): array
{
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        return ['success' => false, 'error' => 'شماره موبایل نامعتبر است.', 'statusCode' => 422];
    }

    $meta = dent_load_auth_meta_store();
    $metaChanged = dent_otp_cleanup_records($meta);
    $records = is_array($meta['otp']['records'] ?? null) ? $meta['otp']['records'] : [];
    $now = time();
    $key = dent_otp_record_key($purpose, $normalizedPhone);
    $record = is_array($records[$key] ?? null) ? $records[$key] : [];

    $cooldownUntil = (int) ($record['cooldownUntil'] ?? 0);
    if ($cooldownUntil > $now) {
        if ($metaChanged) {
            dent_save_auth_meta_store($meta);
        }
        return [
            'success' => false,
            'error' => 'برای دریافت مجدد کد کمی صبر کن.',
            'statusCode' => 429,
            'cooldownSeconds' => $cooldownUntil - $now,
        ];
    }

    $windowStart = (int) ($record['sendWindowStart'] ?? 0);
    $sendCount = (int) ($record['sendCount'] ?? 0);
    if ($windowStart <= 0 || ($now - $windowStart) > dent_otp_window_seconds()) {
        $windowStart = $now;
        $sendCount = 0;
    }
    if ($sendCount >= dent_otp_max_send_per_window()) {
        if ($metaChanged) {
            dent_save_auth_meta_store($meta);
        }
        return [
            'success' => false,
            'error' => 'تعداد درخواست‌های کد تایید بیش از حد مجاز است. بعداً دوباره تلاش کن.',
            'statusCode' => 429,
        ];
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $salt = dent_base64url_encode(random_bytes(12));
    $codeHash = hash_hmac('sha256', $code, dent_auth_secret_key() . '|' . $salt);

    $records[$key] = array_merge($record, [
        'purpose' => $purpose,
        'phoneNumber' => $normalizedPhone,
        'studentNumber' => dent_normalize_student_number($studentNumber),
        'issuedAt' => $now,
        'cooldownUntil' => $now + dent_otp_cooldown_seconds(),
        'sendWindowStart' => $windowStart,
        'sendCount' => $sendCount + 1,
        'lastSendFailedAt' => 0,
    ]);
    $meta['otp']['records'] = $records;
    dent_save_auth_meta_store($meta);

    $sendResult = dent_sms_send_pattern($normalizedPhone, $code);
    if (!(bool) ($sendResult['success'] ?? false)) {
        $records[$key]['lastSendFailedAt'] = time();
        $records[$key]['lastSendFailureMessage'] = dent_clean_text((string) ($sendResult['message'] ?? ''), 220);
        $records[$key]['codeHash'] = '';
        $records[$key]['salt'] = '';
        $records[$key]['expiresAt'] = 0;
        $records[$key]['attempts'] = 0;
        $records[$key]['maxAttempts'] = dent_otp_max_attempts();
        $records[$key]['consumedAt'] = 0;
        $meta['otp']['records'] = $records;
        dent_save_auth_meta_store($meta);
        dent_sms_health_store_update(false, (string) ($sendResult['message'] ?? ''));
        return [
            'success' => false,
            'error' => (string) ($sendResult['message'] ?? 'ارسال کد تایید انجام نشد.'),
            'statusCode' => 502,
            'cooldownSeconds' => dent_otp_cooldown_seconds(),
        ];
    }

    $records[$key] = [
        'purpose' => $purpose,
        'phoneNumber' => $normalizedPhone,
        'studentNumber' => dent_normalize_student_number($studentNumber),
        'codeHash' => $codeHash,
        'salt' => $salt,
        'issuedAt' => $now,
        'expiresAt' => $now + dent_otp_ttl_seconds(),
        'cooldownUntil' => $now + dent_otp_cooldown_seconds(),
        'attempts' => 0,
        'maxAttempts' => dent_otp_max_attempts(),
        'consumedAt' => 0,
        'sendWindowStart' => $windowStart,
        'sendCount' => $sendCount + 1,
    ];
    $meta['otp']['records'] = $records;
    dent_save_auth_meta_store($meta);
    dent_sms_health_store_update(true, (string) ($sendResult['message'] ?? ''));

    return [
        'success' => true,
        'cooldownSeconds' => dent_otp_cooldown_seconds(),
        'expiresInSeconds' => dent_otp_ttl_seconds(),
        'phoneMasked' => dent_mask_phone_number($normalizedPhone),
    ];
}

function dent_verify_otp_for_phone(string $purpose, string $phoneNumber, string $code, string $studentNumber = ''): array
{
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        return ['success' => false, 'error' => 'شماره موبایل نامعتبر است.', 'statusCode' => 422];
    }

    $normalizedCode = preg_replace('/\D+/u', '', dent_normalize_digits($code)) ?? '';
    if ($normalizedCode === '') {
        return ['success' => false, 'error' => 'کد تایید نامعتبر است.', 'statusCode' => 422];
    }

    $meta = dent_load_auth_meta_store();
    dent_otp_cleanup_records($meta);
    $records = is_array($meta['otp']['records'] ?? null) ? $meta['otp']['records'] : [];
    $key = dent_otp_record_key($purpose, $normalizedPhone);
    $record = is_array($records[$key] ?? null) ? $records[$key] : null;
    if ($record === null) {
        dent_save_auth_meta_store($meta);
        return ['success' => false, 'error' => 'درخواست کد تایید پیدا نشد یا منقضی شده است.', 'statusCode' => 404];
    }

    $now = time();
    $expiresAt = (int) ($record['expiresAt'] ?? 0);
    if ($expiresAt <= $now) {
        unset($records[$key]);
        $meta['otp']['records'] = $records;
        dent_save_auth_meta_store($meta);
        return ['success' => false, 'error' => 'کد تایید منقضی شده است.', 'statusCode' => 422];
    }

    $consumedAt = (int) ($record['consumedAt'] ?? 0);
    if ($consumedAt > 0) {
        return ['success' => false, 'error' => 'این کد قبلاً استفاده شده است.', 'statusCode' => 409];
    }

    $expectedStudent = dent_normalize_student_number((string) ($record['studentNumber'] ?? ''));
    $normalizedStudent = dent_normalize_student_number($studentNumber);
    if ($expectedStudent !== '' && $normalizedStudent !== '' && $expectedStudent !== $normalizedStudent) {
        return ['success' => false, 'error' => 'این کد برای کاربر دیگری صادر شده است.', 'statusCode' => 403];
    }

    $attempts = max(0, (int) ($record['attempts'] ?? 0));
    $maxAttempts = max(1, (int) ($record['maxAttempts'] ?? dent_otp_max_attempts()));
    $salt = (string) ($record['salt'] ?? '');
    $expectedHash = (string) ($record['codeHash'] ?? '');
    if ($expectedHash === '' || $salt === '') {
        return ['success' => false, 'error' => 'کد فعالی برای این شماره ثبت نشده است. دوباره درخواست ارسال بده.', 'statusCode' => 404];
    }
    $candidateHash = hash_hmac('sha256', $normalizedCode, dent_auth_secret_key() . '|' . $salt);
    if (!hash_equals($expectedHash, $candidateHash)) {
        $attempts++;
        if ($attempts >= $maxAttempts) {
            unset($records[$key]);
        } else {
            $record['attempts'] = $attempts;
            $records[$key] = $record;
        }
        $meta['otp']['records'] = $records;
        dent_save_auth_meta_store($meta);
        return [
            'success' => false,
            'error' => 'کد تایید صحیح نیست.',
            'statusCode' => 422,
            'remainingAttempts' => max(0, $maxAttempts - $attempts),
        ];
    }

    $record['consumedAt'] = $now;
    $records[$key] = $record;
    $meta['otp']['records'] = $records;
    dent_save_auth_meta_store($meta);

    return ['success' => true];
}
