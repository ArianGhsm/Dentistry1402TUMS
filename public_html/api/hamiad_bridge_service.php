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
    if (empty($response['ok']) || !in_array((int) ($response['status'] ?? 0), [302, 303], true)) {
        throw new RuntimeException('ورود تأیید نشد. رمز یا عبارت امنیتی را بررسی کن.');
    }
    $cookies = hamiad_merge_cookies((array) $challenge['cookies'], (array) ($response['cookies'] ?? []));
    $verified = hamiad_http_request('GET', HAMIAD_HOME, [
        'cookies' => $cookies, 'referer' => HAMIAD_LOGIN, 'timeoutSec' => 20,
    ]);
    $content = (string) ($verified['body'] ?? '');
    if (empty($verified['ok']) || (int) ($verified['status'] ?? 0) !== 200
        || !str_contains($content, 'login/logout.php')) {
        throw new RuntimeException('نشست همیاد هنوز تأیید نشده است.');
    }
    $cookies = hamiad_merge_cookies($cookies, (array) ($verified['cookies'] ?? []));
    dent_write_json_file(hamiad_session_path(), [
        'schemaVersion' => 1,
        'origin' => HAMIAD_BASE,
        'connectedAt' => dent_iso_now(),
        'usernameMasked' => substr($username, 0, 2) . str_repeat("*", max(2, strlen($username) - 4)) . substr($username, -2),
        'cookies' => hamiad_seal($cookies),
    ]);
    $_SESSION['hamiad_login_attempts'] = [];
    return ['message' => 'ورود به همیاد با موفقیت تأیید شد.'];
}
