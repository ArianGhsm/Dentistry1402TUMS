<?php
declare(strict_types=1);

require_once __DIR__ . '/../../api/auth_store.php';

function drx_residency_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $lifetime = 60 * 60 * 24 * 30;
    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $forwardedSsl = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')));
    $isSecure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || $forwardedProto === 'https'
        || $forwardedSsl === 'on';

    session_name('drx_residency_session');
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/dental-residency/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function drx_store_path(): string
{
    return dent_storage_path('dental_residency/auth_store.json');
}

function drx_default_store(): array
{
    return [
        'schemaVersion' => 1,
        'users' => [],
        'otp' => [],
        'events' => [],
    ];
}

function drx_load_store(): array
{
    $store = dent_read_json_file(drx_store_path(), drx_default_store());
    return is_array($store) ? array_merge(drx_default_store(), $store) : drx_default_store();
}

function drx_save_store(array $store): void
{
    $store['schemaVersion'] = 1;
    dent_write_json_file(drx_store_path(), $store);
}

function drx_phone_key(string $phone): string
{
    return hash('sha256', dent_normalize_phone_number($phone));
}

function drx_public_user(array $user): array
{
    $phone = dent_normalize_phone_number((string) ($user['phoneNumber'] ?? ''));
    return [
        'id' => (string) ($user['id'] ?? drx_phone_key($phone)),
        'phoneMasked' => dent_mask_phone_number($phone),
        'role' => (string) ($user['role'] ?? 'learner'),
        'roleLabel' => ((string) ($user['role'] ?? 'learner')) === 'owner' ? 'مالک رزیدنتی' : 'داوطلب رزیدنتی',
        'createdAt' => (string) ($user['createdAt'] ?? ''),
        'lastLoginAt' => (string) ($user['lastLoginAt'] ?? ''),
    ];
}

function drx_current_user(array $store): ?array
{
    $phone = dent_normalize_phone_number((string) ($_SESSION['drx_phone'] ?? ''));
    if ($phone === '') {
        return null;
    }
    $key = drx_phone_key($phone);
    $users = is_array($store['users'] ?? null) ? $store['users'] : [];
    return is_array($users[$key] ?? null) ? $users[$key] : null;
}

function drx_remember_user(string $phone, array &$store): array
{
    $normalized = dent_normalize_phone_number($phone);
    $key = drx_phone_key($normalized);
    $users = is_array($store['users'] ?? null) ? $store['users'] : [];
    $now = dent_iso_now();
    $user = is_array($users[$key] ?? null) ? $users[$key] : [
        'id' => $key,
        'phoneNumber' => $normalized,
        'createdAt' => $now,
    ];
    $user['phoneNumber'] = $normalized;
    $user['role'] = $normalized === '09009840305' ? 'owner' : (string) ($user['role'] ?? 'learner');
    $user['lastLoginAt'] = $now;
    $users[$key] = $user;
    $store['users'] = $users;
    return $user;
}

function drx_otp_key(string $phone): string
{
    return hash('sha256', 'dental-residency-login|' . dent_normalize_phone_number($phone));
}

function drx_cleanup_otps(array &$store): void
{
    $records = is_array($store['otp'] ?? null) ? $store['otp'] : [];
    $now = time();
    foreach ($records as $key => $record) {
        if (!is_array($record) || (int) ($record['expiresAt'] ?? 0) + 86400 < $now) {
            unset($records[$key]);
        }
    }
    $store['otp'] = $records;
}

function drx_issue_otp(string $phone, array &$store): array
{
    $normalized = dent_normalize_phone_number($phone);
    if ($normalized === '') {
        dent_error('شماره موبایل معتبر نیست.', 422);
    }

    drx_cleanup_otps($store);
    $records = is_array($store['otp'] ?? null) ? $store['otp'] : [];
    $key = drx_otp_key($normalized);
    $record = is_array($records[$key] ?? null) ? $records[$key] : [];
    $now = time();
    $cooldownUntil = (int) ($record['cooldownUntil'] ?? 0);
    if ($cooldownUntil > $now) {
        dent_error('برای دریافت مجدد کد کمی صبر کن.', 429, [
            'cooldownSeconds' => $cooldownUntil - $now,
        ]);
    }

    $windowStart = (int) ($record['windowStart'] ?? 0);
    $sendCount = (int) ($record['sendCount'] ?? 0);
    if ($windowStart <= 0 || ($now - $windowStart) > 3600) {
        $windowStart = $now;
        $sendCount = 0;
    }
    if ($sendCount >= 6) {
        dent_error('تعداد درخواست‌های کد بیش از حد مجاز است. بعداً دوباره تلاش کن.', 429);
    }

    $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $sendResult = dent_sms_send_pattern($normalized, $code);
    if (!(bool) ($sendResult['success'] ?? false)) {
        dent_error((string) ($sendResult['message'] ?? 'ارسال کد تایید انجام نشد.'), 502);
    }

    $salt = dent_base64url_encode(random_bytes(12));
    $records[$key] = [
        'phoneNumber' => $normalized,
        'salt' => $salt,
        'codeHash' => hash_hmac('sha256', $code, dent_auth_secret_key() . '|drx|' . $salt),
        'issuedAt' => $now,
        'expiresAt' => $now + 180,
        'cooldownUntil' => $now + 40,
        'attempts' => 0,
        'windowStart' => $windowStart,
        'sendCount' => $sendCount + 1,
    ];
    $store['otp'] = $records;

    return [
        'phoneMasked' => dent_mask_phone_number($normalized),
        'cooldownSeconds' => 40,
        'expiresInSeconds' => 180,
    ];
}

function drx_verify_otp(string $phone, string $code, array &$store): array
{
    $normalized = dent_normalize_phone_number($phone);
    $normalizedCode = preg_replace('/\D+/u', '', dent_normalize_digits($code)) ?? '';
    if ($normalized === '' || $normalizedCode === '') {
        dent_error('شماره موبایل یا کد تایید معتبر نیست.', 422);
    }

    drx_cleanup_otps($store);
    $records = is_array($store['otp'] ?? null) ? $store['otp'] : [];
    $key = drx_otp_key($normalized);
    $record = is_array($records[$key] ?? null) ? $records[$key] : null;
    if ($record === null || (int) ($record['expiresAt'] ?? 0) <= time()) {
        unset($records[$key]);
        $store['otp'] = $records;
        dent_error('کد تایید پیدا نشد یا منقضی شده است.', 422);
    }

    $attempts = (int) ($record['attempts'] ?? 0);
    if ($attempts >= 5) {
        unset($records[$key]);
        $store['otp'] = $records;
        dent_error('تعداد تلاش‌های ناموفق بیش از حد مجاز است. دوباره کد بگیر.', 429);
    }

    $salt = (string) ($record['salt'] ?? '');
    $expected = (string) ($record['codeHash'] ?? '');
    $candidate = hash_hmac('sha256', $normalizedCode, dent_auth_secret_key() . '|drx|' . $salt);
    if ($salt === '' || $expected === '' || !hash_equals($expected, $candidate)) {
        $record['attempts'] = $attempts + 1;
        $records[$key] = $record;
        $store['otp'] = $records;
        dent_error('کد تایید صحیح نیست.', 422, [
            'remainingAttempts' => max(0, 5 - (int) $record['attempts']),
        ]);
    }

    unset($records[$key]);
    $store['otp'] = $records;
    $user = drx_remember_user($normalized, $store);
    $_SESSION['drx_phone'] = $normalized;
    $_SESSION['drx_login_at'] = time();
    return $user;
}

drx_residency_start_session();
$action = dent_request_action();
$store = drx_load_store();

if ($action === 'me') {
    $user = drx_current_user($store);
    dent_json_response([
        'success' => true,
        'loggedIn' => $user !== null,
        'user' => $user ? drx_public_user($user) : null,
    ]);
}

if ($action === 'requestOtp') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد درخواست کد نامعتبر است.', 405);
    }
    $result = drx_issue_otp((string) ($_POST['phoneNumber'] ?? ''), $store);
    drx_save_store($store);
    dent_json_response(array_merge([
        'success' => true,
        'message' => 'کد تایید رزیدنتی دندانپزشکی ارسال شد.',
    ], $result));
}

if ($action === 'verifyOtp') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تایید کد نامعتبر است.', 405);
    }
    $user = drx_verify_otp((string) ($_POST['phoneNumber'] ?? ''), (string) ($_POST['otpCode'] ?? ''), $store);
    drx_save_store($store);
    dent_json_response([
        'success' => true,
        'loggedIn' => true,
        'user' => drx_public_user($user),
        'message' => 'ورود به بخش رزیدنتی دندانپزشکی انجام شد.',
    ]);
}

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
    dent_json_response([
        'success' => true,
        'loggedIn' => false,
        'message' => 'از بخش رزیدنتی دندانپزشکی خارج شدی.',
    ]);
}

dent_error('درخواست نامعتبر است.', 404);
