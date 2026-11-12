<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function hamiad_build_cookie_header(array $cookies): string
{
    $pairs = [];
    foreach ($cookies as $cookie) {
        if (!is_array($cookie)) {
            continue;
        }
        $name = trim((string) ($cookie['name'] ?? ''));
        $value = (string) ($cookie['value'] ?? '');
        if ($name === '') {
            continue;
        }
        $pairs[] = $name . '=' . $value;
    }

    return implode('; ', $pairs);
}

function hamiad_cookie_key(array $cookie): string
{
    return strtolower((string) ($cookie['name'] ?? ''));
}

function hamiad_merge_cookies(array $existing, array $incoming): array
{
    $merged = [];
    foreach ($existing as $cookie) {
        if (!is_array($cookie)) {
            continue;
        }
        $key = hamiad_cookie_key($cookie);
        if ($key === '') {
            continue;
        }
        $merged[$key] = $cookie;
    }

    foreach ($incoming as $cookie) {
        if (!is_array($cookie)) {
            continue;
        }
        $key = hamiad_cookie_key($cookie);
        if ($key === '') {
            continue;
        }
        $merged[$key] = $cookie;
    }

    return array_values($merged);
}

function hamiad_parse_set_cookie(string $line): ?array
{
    $value = trim($line);
    if ($value === '') {
        return null;
    }

    $parts = explode(';', $value);
    if (!$parts) {
        return null;
    }

    $nameValue = array_shift($parts);
    if (!is_string($nameValue) || strpos($nameValue, '=') === false) {
        return null;
    }

    [$name, $cookieValue] = explode('=', $nameValue, 2);
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $cookie = [
        'name' => $name,
        'value' => trim($cookieValue),
        'domain' => '',
        'path' => '/',
        'expires' => '',
        'secure' => false,
        'httpOnly' => false,
    ];

    foreach ($parts as $attrRaw) {
        $attr = trim($attrRaw);
        if ($attr === '') {
            continue;
        }

        if (strpos($attr, '=') === false) {
            $flag = strtolower($attr);
            if ($flag === 'secure') {
                $cookie['secure'] = true;
            } elseif ($flag === 'httponly') {
                $cookie['httpOnly'] = true;
            }
            continue;
        }

        [$k, $v] = explode('=', $attr, 2);
        $key = strtolower(trim($k));
        $val = trim($v);
        if ($key === 'domain') {
            $cookie['domain'] = strtolower($val);
        } elseif ($key === 'path') {
            $cookie['path'] = $val;
        } elseif ($key === 'expires') {
            $cookie['expires'] = $val;
        }
    }

    return $cookie;
}

function hamiad_http_request(string $method, string $url, array $options = []): array
{
    $uri = parse_url($url);
    if (!is_array($uri) || strtolower((string) ($uri['host'] ?? '')) !== 'lms.tums.ac.ir'
        || strtolower((string) ($uri['scheme'] ?? '')) !== 'https' || isset($uri['user']) || isset($uri['port'])) {
        throw new RuntimeException('درخواست خارج از دامنهٔ همیاد مجاز نیست.');
    }
    $method = strtoupper(trim($method));
    if ($method === '') {
        $method = 'GET';
    }

    $query = $options['query'] ?? null;
    if (is_array($query) && $query) {
        $separator = strpos($url, '?') === false ? '?' : '&';
        $url .= $separator . http_build_query($query);
    }

    $timeout = (int) ($options['timeoutSec'] ?? 35);
    if ($timeout < 5) {
        $timeout = 5;
    }

    $cookieHeader = hamiad_build_cookie_header(is_array($options['cookies'] ?? null) ? $options['cookies'] : []);
    $origin = trim((string) ($options['origin'] ?? ''));
    $referer = trim((string) ($options['referer'] ?? ''));

    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,application/json;q=0.8,*/*;q=0.7',
        'Accept-Language: fa-IR,fa;q=0.9,en-US;q=0.7,en;q=0.6',
    ];
    if ($origin !== '') {
        $headers[] = 'Origin: ' . $origin;
    }
    if ($referer !== '') {
        $headers[] = 'Referer: ' . $referer;
    }
    if ($cookieHeader !== '') {
        $headers[] = 'Cookie: ' . $cookieHeader;
    }

    $customHeaders = $options['headers'] ?? null;
    if (is_array($customHeaders)) {
        foreach ($customHeaders as $header) {
            if (is_string($header) && trim($header) !== '') {
                $headers[] = trim($header);
            }
        }
    }

    $setCookies = [];
    $responseHeaders = [];
    $body = '';

    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'status' => 0,
            'body' => '',
            'headers' => [],
            'cookies' => [],
            'error' => 'curl-missing',
            'effectiveUrl' => $url,
        ];
    }

    $curl = curl_init($url);
    if ($curl === false) {
        return [
            'ok' => false,
            'status' => 0,
            'body' => '',
            'headers' => [],
            'cookies' => [],
            'error' => 'curl_init_failed',
            'effectiveUrl' => $url,
        ];
    }

    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, min(25, $timeout));
    curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_ENCODING, '');
    curl_setopt($curl, CURLOPT_PROXY, '');
    if (defined('CURL_IPRESOLVE_V4')) {
        curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }

    if (defined('CURLSSLOPT_NO_REVOKE')) {
        @curl_setopt($curl, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NO_REVOKE);
    }

    curl_setopt(
        $curl,
        CURLOPT_HEADERFUNCTION,
        static function ($ch, string $headerLine) use (&$setCookies, &$responseHeaders): int {
            $trimmed = trim($headerLine);
            if ($trimmed !== '') {
                $responseHeaders[] = $trimmed;
                if (stripos($trimmed, 'Set-Cookie:') === 0) {
                    $cookieLine = trim(substr($trimmed, strlen('Set-Cookie:')));
                    $parsed = hamiad_parse_set_cookie($cookieLine);
                    if ($parsed !== null) {
                        $setCookies[] = $parsed;
                    }
                }
            }
            return strlen($headerLine);
        }
    );

    $jsonPayload = $options['json'] ?? null;
    $formPayload = $options['form'] ?? null;
    $rawBodyProvided = array_key_exists('body', $options);
    $rawBody = $rawBodyProvided ? (string) ($options['body'] ?? '') : null;
    if (is_array($jsonPayload)) {
        $headers[] = 'Content-Type: application/json;charset=UTF-8';
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $encoded = json_encode($jsonPayload);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $encoded === false ? '{}' : $encoded);
    } elseif (is_array($formPayload)) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8';
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($formPayload));
    } elseif ($rawBodyProvided) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $rawBody);
    } elseif (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $headers[] = 'Content-Length: 0';
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, '');
    }

    $body = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $effectiveUrl = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);

    curl_close($curl);

    if (!is_string($body)) {
        $body = '';
    }

    return [
        'ok' => $error === '',
        'status' => $status,
        'body' => $body,
        'headers' => $responseHeaders,
        'cookies' => $setCookies,
        'error' => $error,
        'effectiveUrl' => $effectiveUrl !== '' ? $effectiveUrl : $url,
    ];
}

function hamiad_response_header_value(array $headers, string $name): string
{
    $needle = strtolower($name) . ':';
    foreach (array_reverse($headers) as $headerLine) {
        if (!is_string($headerLine)) {
            continue;
        }
        $trimmed = trim($headerLine);
        if (strtolower(substr($trimmed, 0, strlen($needle))) !== $needle) {
            continue;
        }
        return trim(substr($trimmed, strlen($needle)));
    }

    return '';
}


/**
 * Resolve only the Moodle login handoff URLs we expect. Never forward
 * authentication cookies to an external host, a custom port or userinfo.
 */
function hamiad_login_handoff_url(string $location, string $current): ?string
{
    $location = trim($location);
    if ($location === '' || strlen($location) > 1024
        || preg_match('/[\\x00-\\x1f\\x7f]/', $location)
        || str_starts_with($location, '//')) {
        return null;
    }
    if (str_starts_with($location, '?')) {
        $parsed = parse_url($current);
        if (!is_array($parsed) || !isset($parsed['path'])) return null;
        $url = 'https://lms.tums.ac.ir' . $parsed['path'] . $location;
    } elseif (str_starts_with($location, '/')) {
        $url = 'https://lms.tums.ac.ir' . $location;
    } else {
        $url = $location;
    }
    $uri = parse_url($url);
    if (!is_array($uri)
        || strtolower((string)($uri['scheme'] ?? '')) !== 'https'
        || strtolower((string)($uri['host'] ?? '')) !== 'lms.tums.ac.ir'
        || isset($uri['user']) || isset($uri['pass']) || isset($uri['port'])
        || isset($uri['fragment'])) {
        return null;
    }
    $path = (string)($uri['path'] ?? '/');
    return in_array($path, ['/', '/my', '/my/', '/my/index.php', '/login/index.php'], true)
        ? $url : null;
}

/**
 * Moodle's valid login redirects to /login/index.php?testsession=... to
 * verify/establish the session before the dashboard is accessible. Follow
 * that same-origin handoff and propagate all Set-Cookie changes; do not
 * consider redirects or a login form proof of authentication.
 */
function hamiad_complete_login_handoff(
    string $location,
    array &$cookies,
    ?callable $request = null
): bool {
    $request ??= 'hamiad_http_request';
    $url = hamiad_login_handoff_url($location, 'https://lms.tums.ac.ir/login/index.php');
    if ($url === null) return false;
    for ($hop = 0; $hop < 8; $hop++) {
        $result = $request('GET', $url, [
            'cookies' => $cookies,
            'timeoutSec' => 22,
            'referer' => 'https://lms.tums.ac.ir/login/index.php',
        ]);
        $cookies = hamiad_merge_cookies($cookies, (array)($result['cookies'] ?? []));
        if (empty($result['ok'])) return false;
        $status = (int)($result['status'] ?? 0);
        if ($status === 200) {
            $body = (string)($result['body'] ?? '');
            return !str_contains($body, 'login_captcha_id0')
                && !str_contains($body, 'id="login"')
                && $body !== '';
        }
        if (!in_array($status, [302, 303, 307], true)) return false;
        $next = hamiad_login_handoff_url(
            hamiad_response_header_value((array)($result['headers'] ?? []), 'location'),
            $url
        );
        if ($next === null) return false;
        $url = $next;
    }
    return false;
}

/**
 * Handle the provider's same-URL 307 challenge while retaining short-lived
 * WAF cookies. All destinations remain fixed to the approved LMS host.
 */
function hamiad_get_html_with_waf_retry(string $url, array &$cookies, ?callable $request = null): string
{
    $request ??= 'hamiad_http_request';
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $result = $request('GET', $url, [
            'cookies' => $cookies,
            'timeoutSec' => 22,
            'referer' => 'https://lms.tums.ac.ir/my/',
        ]);
        $cookies = hamiad_merge_cookies($cookies, (array) ($result['cookies'] ?? []));
        if (empty($result['ok'])) {
            throw new RuntimeException('ارتباط با همیاد برقرار نشد.');
        }
        $status = (int) ($result['status'] ?? 0);
        if ($status === 200) {
            $body = (string) ($result['body'] ?? '');
            if (str_contains($body, 'login_captcha_id0') || str_contains($body, 'id="login"')) {
                throw new RuntimeException('نشست همیاد منقضی شده است. دوباره وارد شو.');
            }
            return $body;
        }
        $location = hamiad_response_header_value((array) ($result['headers'] ?? []), 'location');
        if ($status !== 307 || !hash_equals($url, $location)) {
            throw new RuntimeException('دریافت اطلاعات همیاد با پاسخ غیرمنتظره مواجه شد.');
        }
    }
    throw new RuntimeException('سامانه همیاد همچنان در حال انتقال درخواست است. دوباره تلاش کن.');
}
