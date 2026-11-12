<?php
declare(strict_types=1);
require_once __DIR__ . '/hamiad_bridge_service.php';
require_once __DIR__ . '/hamiad_credentials.php';

/* The website owns the only Moodle session. The bot never sees credentials or cookies. */
function hamiad_bot_login_path(string $platform): string
{
    if (!in_array($platform, ['telegram', 'bale'], true)) {
        throw new RuntimeException('مسیر ورود ربات معتبر نیست.');
    }
    return dent_storage_path('hamiad/bot-login-' . $platform . '.json');
}

function hamiad_bot_login_start(string $platform): array
{
    $path = hamiad_bot_login_path($platform);
    if (!hamiad_credentials_status()['saved']) {
        throw new RuntimeException('مشخصات همیاد ذخیره نشده‌اند. از سایت دنتیستری آن‌ها را ثبت کن.');
    }

    $cookies = hamiad_load_cookies();
    if ($cookies && hamiad_verify_home($cookies)) {
        return ['success'=>true, 'status'=>'connected',
            'message'=>'حساب همیاد از قبل متصل است.'];
    }

    $image = hamiad_challenge();
    $challenge = $_SESSION['hamiad_challenge'] ?? null;
    unset($_SESSION['hamiad_challenge']);
    if (!is_array($challenge)) {
        throw new RuntimeException('دریافت تصویر امنیتی کامل نشد.');
    }

    $ref = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    $expiresAt = date(DATE_ATOM, time() + 300);
    $oldUmask = umask(0077);
    try {
        dent_write_json_file($path, [
            'schemaVersion'=>1,
            'refHash'=>hash('sha256', $ref),
            'expiresAt'=>$expiresAt,
            'sealed'=>hamiad_seal($challenge),
        ]);
        if (!@chmod($path, 0600)) {
            throw new RuntimeException('ذخیره امن کپچا تکمیل نشد.');
        }
    } finally {
        umask($oldUmask);
    }
    return ['success'=>true, 'status'=>'challenge',
        'challengeRef'=>$ref, 'captchaDataUri'=>$image['image'],
        'expiresAt'=>$expiresAt];
}

/** Consume exactly once under lock, including on failed password or CAPTCHA. */
function hamiad_bot_login_consume(string $platform, string $ref): array
{
    $path = hamiad_bot_login_path($platform);
    if (!preg_match('/^[A-Za-z0-9_-]{32}$/D', $ref)) {
        throw new RuntimeException('شناسه کپچا معتبر نیست.');
    }
    $oldUmask = umask(0077);
    try {
        $lock = @fopen($path . '.lock', 'c');
        if (!is_resource($lock) || !@flock($lock, LOCK_EX)) {
            if (is_resource($lock)) fclose($lock);
            throw new RuntimeException('دسترسی به کپچا ممکن نیست.');
        }
        try {
            $stored = dent_read_json_file($path, []);
            if ($stored === []
                || !hash_equals((string)($stored['refHash'] ?? ''), hash('sha256', $ref))) {
                throw new RuntimeException('این کپچا جایگزین شده است؛ تصویر تازه درخواست کن.');
            }
            if (!@unlink($path)) {
                throw new RuntimeException('مصرف یک‌باره کپچا ممکن نشد.');
            }
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    } finally {
        umask($oldUmask);
    }
    if (strtotime((string)($stored['expiresAt'] ?? '')) <= time()) {
        throw new RuntimeException('اعتبار کپچا تمام شده است؛ تصویر تازه درخواست کن.');
    }
    $challenge = hamiad_unseal($stored['sealed'] ?? null);
    if (!is_array($challenge)) {
        throw new RuntimeException('نشست کپچا قابل بازیابی نیست؛ دوباره درخواست کن.');
    }
    return $challenge;
}

function hamiad_bot_login_complete(string $platform, string $ref, string $captcha): array
{
    $captcha = strtoupper(trim($captcha));
    if (!preg_match('/^[A-Z0-9]{4,10}$/D', $captcha)) {
        throw new RuntimeException('کد کپچا باید بین ۴ تا ۱۰ حرف یا عدد باشد.');
    }
    $challenge = hamiad_bot_login_consume($platform, $ref);
    $credentials = hamiad_credentials_load();
    if ($credentials === null) {
        throw new RuntimeException('مشخصات ورود همیاد ذخیره نشده‌اند.');
    }
    // Reuse the exact tested website login implementation, including its
    // bounded WAF retry and post-login authenticated-home verification.
    $_SESSION['hamiad_challenge'] = $challenge;
    $result = hamiad_login($credentials['username'], $credentials['password'], $captcha);
    return ['success'=>true, 'status'=>'connected',
        'message'=>(string)($result['message'] ?? 'ورود به همیاد تأیید شد.')];
}
