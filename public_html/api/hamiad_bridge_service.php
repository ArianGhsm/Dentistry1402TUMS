<?php
declare(strict_types=1);

require_once __DIR__ . '/hamiad_http.php';
require_once __DIR__ . '/hamiad_storage.php';

// Owner-only LMS login bridge; never persist a password.
const HAMIAD_BASE = 'https://lms.tums.ac.ir';
const HAMIAD_LOGIN = HAMIAD_BASE . '/login/index.php';
const HAMIAD_HOME = HAMIAD_BASE . '/my/';

function hamiad_field(DOMXPath $xpath, string $name): string
{
    $nodes = $xpath->query('//form[@id="login"]//input[@name="' . $name . '"]');
    return $nodes instanceof DOMNodeList && $nodes->length > 0
        ? trim((string) ($nodes->item(0)?->attributes?->getNamedItem('value')?->nodeValue ?? ''))
        : '';
}

function hamiad_parse_login_form(string $markup): array
{
    $dom = new DOMDocument();
    $wasInternal = libxml_use_internal_errors(true);
    try {
        $parsed = $dom->loadHTML('<?xml encoding="UTF-8"?>' . $markup);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($wasInternal);
    }
    if (!$parsed) {
        return [];
    }
    $xpath = new DOMXPath($dom);
    $token = hamiad_field($xpath, 'logintoken');
    $captchaId = hamiad_field($xpath, 'login_captcha_id0');
    if (!preg_match('/^[A-Za-z0-9]{16,128}$/D', $token)
        || !preg_match('/^[A-Za-z0-9]{16,128}$/D', $captchaId)) {
        return [];
    }
    $images = $xpath->query('//form[@id="login"]//img');
    $validImage = false;
    foreach ($images instanceof DOMNodeList ? $images : [] as $image) {
        $src = trim((string) ($image->attributes?->getNamedItem('src')?->nodeValue
            ?: $image->attributes?->getNamedItem('src0')?->nodeValue));
        $parsedSrc = parse_url($src);
        if (!is_array($parsedSrc)
            || strtolower((string) ($parsedSrc['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($parsedSrc['host'] ?? '')) !== 'lms.tums.ac.ir'
            || ($parsedSrc['path'] ?? '') !== '/local/fic_form_fields/includes/captcha_img.php') {
            continue;
        }
        parse_str((string) ($parsedSrc['query'] ?? ''), $params);
        if (hash_equals($captchaId, (string) ($params['captcha_id'] ?? ''))) {
            $validImage = true;
            break;
        }
    }
    return $validImage ? ['token' => $token, 'captchaId' => $captchaId] : [];
}

function hamiad_challenge(): array
{
    // ArvanCloud sometimes issues a same-URL 307 with short-lived cookies.
    // Follow it manually (bounded), preserving those cookies before parsing LMS.
    $cookies = [];
    $page = [];
    for ($retry = 0; $retry < 3; $retry++) {
        $page = hamiad_http_request('GET', HAMIAD_LOGIN, [
            'cookies' => $cookies, 'timeoutSec' => 16,
        ]);
        $cookies = hamiad_merge_cookies($cookies, (array) ($page['cookies'] ?? []));
        if (!empty($page['ok']) && (int) ($page['status'] ?? 0) === 200) {
            break;
        }
        $location = hamiad_response_header_value((array) ($page['headers'] ?? []), 'location');
        if (empty($page['ok']) || (int) ($page['status'] ?? 0) !== 307
            || $location !== HAMIAD_LOGIN) {
            break;
        }
    }
    if (empty($page['ok']) || (int) ($page['status'] ?? 0) !== 200) {
        throw new RuntimeException('سامانه همیاد در حال حاضر اجازه دریافت صفحه ورود را نداده است.');
    }
    $form = hamiad_parse_login_form((string) ($page['body'] ?? ''));
    if (!$form) {
        throw new RuntimeException('ساختار ورود همیاد قابل شناسایی نیست.');
    }
    $image = hamiad_http_request('GET',
        HAMIAD_BASE . '/local/fic_form_fields/includes/captcha_img.php?captcha_id=' . rawurlencode($form['captchaId']),
        ['cookies' => $cookies, 'referer' => HAMIAD_LOGIN, 'timeoutSec' => 16]
    );
    $bytes = (string) ($image['body'] ?? '');
    $mime = strtolower(hamiad_response_header_value((array) ($image['headers'] ?? []), 'content-type'));
    if (empty($image['ok']) || (int) ($image['status'] ?? 0) !== 200
        || !str_starts_with($mime, 'image/png')
        || strlen($bytes) < 100 || strlen($bytes) > 200000
        || !str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
        throw new RuntimeException('تصویر امنیتی همیاد دریافت نشد.');
    }
    $_SESSION['hamiad_challenge'] = [
        'token' => $form['token'],
        'captchaId' => $form['captchaId'],
        'cookies' => hamiad_merge_cookies($cookies, (array) ($image['cookies'] ?? [])),
        'issuedAt' => time(),
    ];
    return ['image' => 'data:image/png;base64,' . base64_encode($bytes)];
}

/**
 * Verify a successful Moodle login across Arvan's bounded same-URL 307
 * challenge, preserving its refreshed cookies before storing the session.
 * A redirect to /login or an anonymous HTML response is never success.
 */
function hamiad_verify_home(array &$cookies, ?callable $request = null): bool
{
    try {
        $content = hamiad_get_html_with_waf_retry(HAMIAD_HOME, $cookies, $request);
    } catch (RuntimeException $error) {
        return false;
    }
    return str_contains($content, 'login/logout.php');
}

/**
 * Extract only the known, non-sensitive login failure category returned by
 * Moodle. Never return raw remote HTML or arbitrary provider error content.
 */
function hamiad_login_failure_message(string $body): string
{
    if ($body === '' || strlen($body) > 524288) {
        return 'ورود تأیید نشد. رمز یا عبارت امنیتی را بررسی کن.';
    }
    $dom = new DOMDocument();
    $prior = libxml_use_internal_errors(true);
    try {
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8"?>' . $body);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($prior);
    }
    if (!$loaded) {
        return 'ورود تأیید نشد. رمز یا عبارت امنیتی را بررسی کن.';
    }
    $xpath = new DOMXPath($dom);
    // Prefer Moodle's known error container; some themes use loginerrors
    // or an alert-danger instead. Never return arbitrary remote content.
    $message = '';
    foreach ([
        '//*[@id="loginerrormessage"]',
        '//*[contains(concat(" ", normalize-space(@class), " "), " loginerrors ")]',
        '//*[contains(concat(" ", normalize-space(@class), " "), " alert-danger ")]',
    ] as $selector) {
        $nodes = $xpath->query($selector);
        if (!$nodes instanceof DOMNodeList) continue;
        foreach ($nodes as $node) {
            $candidate = trim((string)$node->textContent);
            if ($candidate !== '' && mb_strlen($candidate) <= 800) {
                $message = mb_strtolower($candidate);
                break 2;
            }
        }
    }
    $captchaKeywords = ['کپچا', 'captcha', 'عبارت امنیتی', 'عبارت‌امنیتی',
        'کد امنیتی', 'verification code', 'security code'];
    foreach ($captchaKeywords as $needle) {
        if (str_contains($message, $needle)) {
            return 'همیاد کد کپچا را نپذیرفت؛ کد تصویر جدید را بررسی کن.';
        }
    }
    $credentialKeywords = ['نام کاربری', 'رمز عبور', 'کلمه عبور',
        'username', 'password', 'invalid login', 'incorrect login'];
    foreach ($credentialKeywords as $needle) {
        if (str_contains($message, $needle)) {
            return 'همیاد نام کاربری یا رمز عبور را رد کرد؛ مشخصات ذخیره‌شده را در پنل امن همیاد بررسی و اصلاح کن.';
        }
    }
    return 'همیاد ورود را رد کرد؛ علت مشخصی در پیام صفحه ورود اعلام نکرده است. رمز ذخیره‌شده و ارتباط کپچا را بررسی کن.';
}

/**
 * Private, intentionally minimal phases. Never include credentials, CAPTCHA,
 * raw URLs, query values, cookies, HTML or user identifiers.
 */
function hamiad_record_login_diagnostic(string $phase, array $trace): void
{
    try {
        $allowed = ['post_login_form', 'post_nonredirect', 'handoff_failed',
                    'home_verify_failed', 'connected'];
        if (!in_array($phase, $allowed, true)) return;
        $paths = ['login', 'dashboard', 'root', 'other', 'unaccepted', 'none'];
        $hops = [];
        foreach (array_slice((array)($trace['hops'] ?? []), 0, 9) as $hop) {
            if (!is_array($hop)) continue;
            $hops[] = [
                'step'=>in_array($hop['step'] ?? '', ['follow', 'unsafe_location', 'redirect_limit'], true)
                    ? $hop['step'] : 'other',
                'path'=>in_array($hop['path'] ?? '', $paths, true) ? $hop['path'] : 'other',
                'testsession'=>(bool)($hop['testsession'] ?? false),
                'httpStatus'=>(int)($hop['httpStatus'] ?? 0),
                'transportOk'=>(bool)($hop['transportOk'] ?? false),
                'nextPath'=>in_array($hop['nextPath'] ?? '', $paths, true)
                    ? $hop['nextPath'] : 'other',
                'cookieSet'=>(bool)($hop['cookieSet'] ?? false),
                'sessionCookiePresent'=>(bool)($hop['sessionCookiePresent'] ?? false),
                'loginForm'=>(bool)($hop['loginForm'] ?? false),
                'logoutMarker'=>(bool)($hop['logoutMarker'] ?? false),
            ];
        }
        $record = [
            'at'=>dent_iso_now(),
            'phase'=>$phase,
            'postStatus'=>(int)($trace['postStatus'] ?? 0),
            'postPath'=>in_array($trace['postPath'] ?? '', $paths, true)
                ? $trace['postPath'] : 'other',
            'postSessionCookie'=>(bool)($trace['postSessionCookie'] ?? false),
            'hops'=>$hops,
        ];
        $path = dent_storage_path('hamiad/last-login-diagnostic.json');
        $oldUmask = umask(0077);
        try {
            dent_write_json_file($path, $record);
            @chmod($path, 0600);
        } finally {
            umask($oldUmask);
        }
    } catch (Throwable $ignored) {
        // Diagnostic failure must never affect provider authentication.
    }
}

function hamiad_handoff_failure_message(array $hops): string
{
    $last = $hops ? end($hops) : [];
    if (!is_array($last)) $last = [];
    if (($last['loginForm'] ?? false) === true) {
        return 'همیاد پس از ثبت ورود دوباره فرم ورود را نشان داد؛ نشست یا کوکی پذیرفته نشد.';
    }
    if (($last['step'] ?? '') === 'unsafe_location'
        || ($last['nextPath'] ?? '') === 'unaccepted') {
        return 'همیاد پس از ورود به مسیری غیرمنتظره منتقل شد. نشست تأیید نشد.';
    }
    if (($last['step'] ?? '') === 'redirect_limit') {
        return 'انتقال‌های پیاپی سامانه امنیتی همیاد تمام نشد. نشست تأیید نشد.';
    }
    if (($last['transportOk'] ?? true) === false) {
        return 'ارتباط با همیاد هنگام تأیید نشست قطع شد.';
    }
    return 'تأیید نشست همیاد در مرحله انتقال کامل نشد؛ جزئیات غیرحساس روی سرور ثبت شد.';
}

function hamiad_login(string $username, string $password, string $captcha): array
{
    $challenge = $_SESSION['hamiad_challenge'] ?? null;
    unset($_SESSION['hamiad_challenge']);
    $attempts = array_values(array_filter(
        (array) ($_SESSION['hamiad_login_attempts'] ?? []),
        static fn ($at): bool => is_int($at) && $at > time() - 900
    ));
    if (count($attempts) >= 5) {
        throw new RuntimeException('تعداد تلاش‌ها زیاد است. کمی بعد دوباره امتحان کن.');
    }
    $attempts[] = time();
    $_SESSION['hamiad_login_attempts'] = $attempts;
    if (!is_array($challenge) || (int) ($challenge['issuedAt'] ?? 0) < time() - 300) {
        throw new RuntimeException('تصویر امنیتی منقضی شده است. تصویر تازه بگیر.');
    }
    if (!preg_match('/^[A-Za-z0-9._@-]{3,80}$/D', $username)
        || $password === '' || strlen($password) > 256
        || !preg_match('/^[A-Za-z0-9]{4,10}$/D', $captcha)) {
        throw new RuntimeException('اطلاعات ورود یا عبارت امنیتی ناقص است.');
    }
    $response = hamiad_http_request('POST', HAMIAD_LOGIN, [
        'cookies' => (array) $challenge['cookies'],
        'origin' => HAMIAD_BASE, 'referer' => HAMIAD_LOGIN, 'timeoutSec' => 22,
        'form' => [
            'anchor' => '',
            'logintoken' => (string) $challenge['token'],
            'username' => $username,
            'password' => $password,
            'login_captcha_id0' => (string) $challenge['captchaId'],
            'login_captcha' => $captcha,
        ],
    ]);
    $location = hamiad_response_header_value((array)($response['headers'] ?? []), 'location');
    $postTrace = [
        'postStatus'=>(int)($response['status'] ?? 0),
        'postPath'=>hamiad_handoff_path_category(
            hamiad_login_handoff_url($location, HAMIAD_LOGIN)
        ),
        'postSessionCookie'=>hamiad_handoff_has_session_cookie((array)($response['cookies'] ?? [])),
        'hops'=>[],
    ];
    if (!empty($response['ok']) && (int)($response['status'] ?? 0) === 200) {
        hamiad_record_login_diagnostic('post_login_form', $postTrace);
        throw new RuntimeException(hamiad_login_failure_message((string)($response['body'] ?? '')));
    }
    if (empty($response['ok']) || !in_array((int) ($response['status'] ?? 0), [302, 303], true)) {
        hamiad_record_login_diagnostic('post_nonredirect', $postTrace);
        throw new RuntimeException('همیاد پاسخ غیرمنتظره‌ای به ورود داد؛ کپچای تازه بگیر.');
    }
    $cookies = hamiad_merge_cookies((array) $challenge['cookies'], (array) ($response['cookies'] ?? []));
    $rejectedLoginHtml = null;
    if (!hamiad_complete_login_handoff($location, $cookies, null, $postTrace['hops'], $rejectedLoginHtml)) {
        hamiad_record_login_diagnostic('handoff_failed', $postTrace);
        if (is_string($rejectedLoginHtml)) {
            throw new RuntimeException(hamiad_login_failure_message($rejectedLoginHtml));
        }
        throw new RuntimeException(hamiad_handoff_failure_message($postTrace['hops']));
    }
    if (!hamiad_verify_home($cookies)) {
        hamiad_record_login_diagnostic('home_verify_failed', $postTrace);
        throw new RuntimeException('پس از تأیید کوکی، نشست همیاد هنوز معتبر نیست.');
    }
    dent_write_json_file(hamiad_session_path(), [
        'schemaVersion' => 1,
        'origin' => HAMIAD_BASE,
        'connectedAt' => dent_iso_now(),
        'usernameMasked' => substr($username, 0, 2) . str_repeat("*", max(2, strlen($username) - 4)) . substr($username, -2),
        'cookies' => hamiad_seal($cookies),
    ]);
    hamiad_record_login_diagnostic('connected', $postTrace);
    $_SESSION['hamiad_login_attempts'] = [];
    return ['message' => 'ورود به همیاد با موفقیت تأیید شد.'];
}
