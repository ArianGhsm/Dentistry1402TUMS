<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/navid_store.php';

function navid_normalize_login_url(?string $value): string
{
    $candidate = trim((string) $value);
    if ($candidate === '') {
        return 'https://navid.tums.ac.ir/account/loginsipadservice';
    }

    if (!preg_match('/^https?:\/\//i', $candidate)) {
        return 'https://navid.tums.ac.ir/account/loginsipadservice';
    }

    return $candidate;
}

function navid_origin_from_login_url(string $loginUrl): string
{
    $parts = parse_url($loginUrl);
    if (!is_array($parts)) {
        return 'https://navid.tums.ac.ir';
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
    $host = strtolower((string) ($parts['host'] ?? 'navid.tums.ac.ir'));
    $port = (int) ($parts['port'] ?? 0);

    $origin = $scheme . '://' . $host;
    if ($port > 0 && !in_array($port, [80, 443], true)) {
        $origin .= ':' . $port;
    }

    return $origin;
}

function navid_absolute_url(string $origin, string $pathOrUrl): string
{
    $value = trim($pathOrUrl);
    if ($value === '') {
        return $origin;
    }

    if (preg_match('/^https?:\/\//i', $value)) {
        return $value;
    }

    if ($value[0] !== '/') {
        $value = '/' . $value;
    }

    return rtrim($origin, '/') . $value;
}

function navid_build_cookie_header(array $cookies): string
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

function navid_cookie_key(array $cookie): string
{
    return strtolower((string) ($cookie['name'] ?? ''));
}

function navid_merge_cookies(array $existing, array $incoming): array
{
    $merged = [];
    foreach ($existing as $cookie) {
        if (!is_array($cookie)) {
            continue;
        }
        $key = navid_cookie_key($cookie);
        if ($key === '') {
            continue;
        }
        $merged[$key] = $cookie;
    }

    foreach ($incoming as $cookie) {
        if (!is_array($cookie)) {
            continue;
        }
        $key = navid_cookie_key($cookie);
        if ($key === '') {
            continue;
        }
        $merged[$key] = $cookie;
    }

    return array_values($merged);
}

function navid_parse_set_cookie(string $line): ?array
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

function navid_http_request(string $method, string $url, array $options = []): array
{
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

    $cookieHeader = navid_build_cookie_header(is_array($options['cookies'] ?? null) ? $options['cookies'] : []);
    $origin = trim((string) ($options['origin'] ?? ''));
    $referer = trim((string) ($options['referer'] ?? ''));

    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept: application/json, text/plain, */*',
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
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, min(12, $timeout));
    curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_ENCODING, '');
    curl_setopt($curl, CURLOPT_PROXY, '');

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
                    $parsed = navid_parse_set_cookie($cookieLine);
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
    if (is_array($jsonPayload)) {
        $headers[] = 'Content-Type: application/json;charset=UTF-8';
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $encoded = json_encode($jsonPayload);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $encoded === false ? '{}' : $encoded);
    } elseif (is_array($formPayload)) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8';
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($formPayload));
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

function navid_decode_json_response(array $response): ?array
{
    $decoded = json_decode((string) ($response['body'] ?? ''), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return null;
    }
    return $decoded;
}

function navid_extract_anti_forgery_token(string $html): string
{
    if (preg_match('/name="__RequestVerificationToken"[^>]*value="([^"]+)"/i', $html, $matches) === 1) {
        return trim((string) ($matches[1] ?? ''));
    }
    return '';
}

function navid_solve_captcha_python(string $captchaBase64): string
{
    $scriptPath = DENT_PROJECT_ROOT . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'navid_captcha_ocr.py';
    if (!is_file($scriptPath)) {
        return '';
    }

    $python = trim((string) (getenv('DENT_NAVID_PYTHON_BIN') ?: 'python'));
    if ($python === '') {
        $python = 'python';
    }

    $cmd = escapeshellcmd($python) . ' ' . escapeshellarg($scriptPath);
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = @proc_open($cmd, $descriptor, $pipes, DENT_PROJECT_ROOT);
    if (!is_resource($process)) {
        return '';
    }

    fwrite($pipes[0], $captchaBase64);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exit = proc_close($process);
    if ($exit !== 0) {
        navid_log('warning', 'captcha_solver_failed', ['exit' => $exit, 'stderr' => trim((string) $stderr)]);
        return '';
    }

    $code = strtoupper(trim((string) $stdout));
    $code = preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
    if (strlen($code) < 4) {
        return '';
    }

    return $code;
}

function navid_get_captcha_image(array $cookies, string $loginUrl): array
{
    $origin = navid_origin_from_login_url($loginUrl);
    $response = navid_http_request('POST', navid_absolute_url($origin, '/account/generate'), [
        'cookies' => $cookies,
        'origin' => $origin,
        'referer' => $loginUrl,
        'headers' => ['X-Requested-With: XMLHttpRequest'],
    ]);

    $responseCookies = navid_merge_cookies($cookies, $response['cookies'] ?? []);
    $decoded = navid_decode_json_response($response);
    if (!is_array($decoded) || empty($decoded['customResult']['data'])) {
        return [
            'success' => false,
            'error' => 'captcha_generate_failed',
            'cookies' => $responseCookies,
            'data' => '',
            'contentType' => 'image/png',
        ];
    }

    return [
        'success' => true,
        'cookies' => $responseCookies,
        'data' => (string) ($decoded['customResult']['data'] ?? ''),
        'contentType' => (string) ($decoded['customResult']['contentType'] ?? 'image/png'),
    ];
}

function navid_submit_login(
    string $loginUrl,
    array $cookies,
    string $requestToken,
    string $username,
    string $password,
    string $captchaCode
): array {
    $origin = navid_origin_from_login_url($loginUrl);
    $response = navid_http_request('POST', navid_absolute_url($origin, '/account/submitloginsipadservice'), [
        'cookies' => $cookies,
        'origin' => $origin,
        'referer' => $loginUrl,
        'form' => [
            'ReturnUrl' => '/',
            'Username' => $username,
            'Password' => $password,
            'CaptchaCode' => $captchaCode,
            '__RequestVerificationToken' => $requestToken,
        ],
        'headers' => ['X-Requested-With: XMLHttpRequest'],
    ]);

    $mergedCookies = navid_merge_cookies($cookies, $response['cookies'] ?? []);
    $decoded = navid_decode_json_response($response);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'error' => 'login_response_invalid',
            'cookies' => $mergedCookies,
            'code' => '',
            'message' => '',
            'nextUrl' => '',
        ];
    }

    return [
        'success' => !empty($decoded['success']),
        'error' => '',
        'cookies' => $mergedCookies,
        'code' => (string) ($decoded['customResult']['code'] ?? ''),
        'message' => (string) ($decoded['message'] ?? ''),
        'nextUrl' => (string) ($decoded['customResult']['url'] ?? ''),
    ];
}

function navid_attempt_login(array &$store): array
{
    $config = is_array($store['config'] ?? null) ? $store['config'] : [];
    $loginUrl = navid_normalize_login_url((string) ($config['loginUrl'] ?? ''));
    $credentials = navid_get_credentials($store);

    if ($credentials === null) {
        return [
            'success' => false,
            'error' => 'credentials_missing',
            'message' => 'اطلاعات ورود نوید ذخیره نشده است.',
            'cookies' => [],
        ];
    }

    $first = navid_http_request('GET', $loginUrl, [
        'headers' => ['X-Requested-With: XMLHttpRequest'],
    ]);
    if (!$first['ok'] || (int) ($first['status'] ?? 0) < 200 || (int) ($first['status'] ?? 0) >= 400) {
        return [
            'success' => false,
            'error' => 'login_page_unreachable',
            'message' => 'صفحه ورود نوید در دسترس نیست.',
            'cookies' => [],
        ];
    }

    $cookies = navid_merge_cookies([], $first['cookies'] ?? []);
    $token = navid_extract_anti_forgery_token((string) ($first['body'] ?? ''));
    if ($token === '') {
        return [
            'success' => false,
            'error' => 'token_not_found',
            'message' => 'توکن امنیتی ورود نوید پیدا نشد.',
            'cookies' => $cookies,
        ];
    }

    $captchaStrategy = (string) ($config['captchaStrategy'] ?? 'python_ocr');
    if ($captchaStrategy !== 'python_ocr') {
        return [
            'success' => false,
            'error' => 'captcha_manual_required',
            'message' => 'برای این تنظیمات، اتصال مجدد دستی کپچا لازم است.',
            'cookies' => $cookies,
        ];
    }

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $captchaResult = navid_get_captcha_image($cookies, $loginUrl);
        $cookies = navid_merge_cookies($cookies, $captchaResult['cookies'] ?? []);
        if (empty($captchaResult['success'])) {
            continue;
        }

        $captchaCode = navid_solve_captcha_python((string) $captchaResult['data']);
        if ($captchaCode === '') {
            return [
                'success' => false,
                'error' => 'captcha_solver_unavailable',
                'message' => 'حل خودکار کپچا در این سرور در دسترس نیست. اتصال دستی لازم است.',
                'cookies' => $cookies,
            ];
        }

        $submit = navid_submit_login(
            $loginUrl,
            $cookies,
            $token,
            (string) $credentials['username'],
            (string) $credentials['password'],
            $captchaCode
        );
        $cookies = navid_merge_cookies($cookies, $submit['cookies'] ?? []);

        if (!empty($submit['success'])) {
            $nextUrl = trim((string) ($submit['nextUrl'] ?? ''));
            if ($nextUrl !== '') {
                $origin = navid_origin_from_login_url($loginUrl);
                $nextAbsolute = navid_absolute_url($origin, $nextUrl);
                $final = navid_http_request('GET', $nextAbsolute, [
                    'cookies' => $cookies,
                    'referer' => $loginUrl,
                    'origin' => $origin,
                ]);
                $cookies = navid_merge_cookies($cookies, $final['cookies'] ?? []);
            }

            return [
                'success' => true,
                'error' => '',
                'message' => 'ورود خودکار نوید انجام شد.',
                'cookies' => $cookies,
            ];
        }

        $code = (string) ($submit['code'] ?? '');
        if ($code === 'FailCaptcha') {
            continue;
        }

        if ($code === 'FailLogin') {
            return [
                'success' => false,
                'error' => 'credentials_invalid',
                'message' => 'نام کاربری یا رمز نوید نادرست است.',
                'cookies' => $cookies,
            ];
        }
    }

    return [
        'success' => false,
        'error' => 'captcha_retries_exhausted',
        'message' => 'ورود خودکار نوید پس از چند تلاش کپچا موفق نشد.',
        'cookies' => $cookies,
    ];
}

function navid_fetch_dashboard_payload(array $cookies, string $loginUrl): array
{
    $origin = navid_origin_from_login_url($loginUrl);
    $response = navid_http_request('POST', navid_absolute_url($origin, '/dashboard/getcourseslist'), [
        'cookies' => $cookies,
        'origin' => $origin,
        'referer' => $origin . '/',
        'json' => [
            'searchContent' => '',
            'TermId' => null,
        ],
        'headers' => ['X-Requested-With: XMLHttpRequest'],
    ]);

    $mergedCookies = navid_merge_cookies($cookies, $response['cookies'] ?? []);
    $decoded = navid_decode_json_response($response);
    if (!is_array($decoded) || empty($decoded['success'])) {
        return [
            'success' => false,
            'cookies' => $mergedCookies,
            'payload' => null,
        ];
    }

    return [
        'success' => true,
        'cookies' => $mergedCookies,
        'payload' => $decoded['customResult'] ?? [],
    ];
}

function navid_extract_courses(array $dashboardPayload, string $loginUrl): array
{
    $origin = navid_origin_from_login_url($loginUrl);
    $activeCourses = is_array($dashboardPayload['activeCourses'] ?? null) ? $dashboardPayload['activeCourses'] : [];
    $coursesById = [];

    foreach ($activeCourses as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $instance = is_array($entry['courseInstance'] ?? null) ? $entry['courseInstance'] : [];
        $courseTemplateId = (int) ($instance['activeCourseTemplateId'] ?? 0);
        if ($courseTemplateId <= 0) {
            continue;
        }

        $title = dent_clean_text((string) ($instance['courseTitle'] ?? ''), 180);
        if ($title === '') {
            $title = 'درس ' . $courseTemplateId;
        }

        $coursesById[(string) $courseTemplateId] = [
            'courseTemplateId' => $courseTemplateId,
            'courseTitle' => $title,
            'courseInstanceId' => (int) ($instance['id'] ?? 0),
            'courseUrl' => navid_absolute_url($origin, '/coursetemplate/details/' . $courseTemplateId . '/1/'),
            'activeCourseTemplateCount' => (int) ($instance['activeCourseTemplateCount'] ?? 0),
            'termTitle' => dent_clean_text((string) ($instance['termTitle'] ?? ''), 120),
            'periodTitle' => dent_clean_text((string) ($instance['periodTitle'] ?? ''), 120),
        ];
    }

    ksort($coursesById, SORT_STRING);
    return array_values($coursesById);
}

function navid_fetch_course_assignments(array $cookies, string $loginUrl, int $courseTemplateId): array
{
    $origin = navid_origin_from_login_url($loginUrl);
    $response = navid_http_request('GET', navid_absolute_url($origin, '/Assignment/CourseAssignmentList'), [
        'cookies' => $cookies,
        'origin' => $origin,
        'referer' => navid_absolute_url($origin, '/coursetemplate/details/' . $courseTemplateId . '/1/'),
        'query' => [
            'courseTemplateId' => $courseTemplateId,
            'isTeacherView' => 'false',
            'sessionId' => '',
        ],
        'headers' => ['X-Requested-With: XMLHttpRequest'],
    ]);

    $mergedCookies = navid_merge_cookies($cookies, $response['cookies'] ?? []);
    $decoded = navid_decode_json_response($response);
    if (!is_array($decoded) || empty($decoded['success'])) {
        return [
            'success' => false,
            'cookies' => $mergedCookies,
            'assignments' => [],
        ];
    }

    $list = is_array($decoded['customResult'] ?? null) ? $decoded['customResult'] : [];
    return [
        'success' => true,
        'cookies' => $mergedCookies,
        'assignments' => $list,
    ];
}

function navid_description_text(string $descriptionHtml): string
{
    $decoded = html_entity_decode($descriptionHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strip_tags($decoded);
    return dent_clean_text($text, 1600);
}

function navid_normalize_assignment(array $course, array $raw, string $loginUrl): ?array
{
    $assignmentId = (int) ($raw['id'] ?? 0);
    if ($assignmentId <= 0) {
        return null;
    }

    $courseTemplateId = (int) ($course['courseTemplateId'] ?? 0);
    if ($courseTemplateId <= 0) {
        return null;
    }

    $origin = navid_origin_from_login_url($loginUrl);
    $title = dent_clean_text((string) ($raw['title'] ?? ''), 260);
    $descriptionHtml = (string) ($raw['description'] ?? '');
    $descriptionText = navid_description_text($descriptionHtml);
    $endDateIso = trim((string) ($raw['endDate'] ?? ''));
    $endDateShamsi = dent_clean_text((string) ($raw['endDateString'] ?? ''), 40);
    $proposeDate = dent_clean_text((string) ($raw['proposeDate'] ?? ''), 40);
    $ownerName = dent_clean_text((string) ($raw['owner']['fullname'] ?? ''), 120);

    $files = [];
    $rawFiles = is_array($raw['assignmentFiles'] ?? null) ? $raw['assignmentFiles'] : [];
    foreach ($rawFiles as $file) {
        if (!is_array($file)) {
            continue;
        }
        $fileId = (int) ($file['fileId'] ?? 0);
        $fileName = dent_clean_text((string) ($file['name'] ?? ''), 180);
        $fileUrl = navid_absolute_url($origin, (string) ($file['url'] ?? ''));
        $files[] = [
            'fileId' => $fileId,
            'name' => $fileName,
            'url' => $fileUrl,
            'extension' => dent_clean_text((string) ($file['extension'] ?? ''), 16),
            'size' => (int) ($file['size'] ?? 0),
        ];
    }

    $payloadForHash = [
        'assignmentId' => $assignmentId,
        'title' => $title,
        'descriptionHtml' => $descriptionHtml,
        'endDateIso' => $endDateIso,
        'endDateShamsi' => $endDateShamsi,
        'proposeDate' => $proposeDate,
        'isActive' => !empty($raw['isActive']),
        'isShown' => !empty($raw['isShown']),
        'files' => $files,
    ];
    $fingerprint = hash('sha256', json_encode($payloadForHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

    return [
        'assignmentKey' => $courseTemplateId . ':' . $assignmentId,
        'assignmentId' => $assignmentId,
        'courseTemplateId' => $courseTemplateId,
        'courseTitle' => (string) ($course['courseTitle'] ?? ''),
        'courseUrl' => (string) ($course['courseUrl'] ?? ''),
        'title' => $title,
        'descriptionHtml' => $descriptionHtml,
        'descriptionText' => $descriptionText,
        'proposeDate' => $proposeDate,
        'endDateIso' => $endDateIso,
        'endDateShamsi' => $endDateShamsi,
        'ownerName' => $ownerName,
        'isActive' => !empty($raw['isActive']),
        'isShown' => !empty($raw['isShown']),
        'replyStatusName' => dent_clean_text((string) ($raw['replyStatusName'] ?? ''), 80),
        'files' => $files,
        'fingerprint' => $fingerprint,
        'sourceUpdatedAt' => dent_iso_now(),
    ];
}

function navid_assignments_sorted(array $assignments): array
{
    usort(
        $assignments,
        static function (array $left, array $right): int {
            $leftDate = strtotime((string) ($left['endDateIso'] ?? '')) ?: PHP_INT_MAX;
            $rightDate = strtotime((string) ($right['endDateIso'] ?? '')) ?: PHP_INT_MAX;
            if ($leftDate === $rightDate) {
                return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
            }
            return $leftDate <=> $rightDate;
        }
    );
    return $assignments;
}

function navid_add_update_if_new(array &$store, array $event): void
{
    $updates = is_array($store['updates'] ?? null) ? $store['updates'] : [];
    $key = (string) ($event['assignmentKey'] ?? '');
    $fingerprint = (string) ($event['fingerprint'] ?? '');
    foreach (array_slice($updates, 0, 80) as $old) {
        if (!is_array($old)) {
            continue;
        }
        if ((string) ($old['assignmentKey'] ?? '') === $key && (string) ($old['fingerprint'] ?? '') === $fingerprint) {
            return;
        }
    }

    array_unshift($updates, $event);
    $store['updates'] = array_slice($updates, 0, 300);
}

function navid_sync_action_required(?array $credentials, array $state, bool $enabled): string
{
    if (!$enabled) {
        return 'disabled';
    }

    if ($credentials === null) {
        return 'save-credentials';
    }

    $lastResult = trim((string) ($state['lastResult'] ?? ''));
    if ($lastResult === 'credentials-missing') {
        return 'save-credentials';
    }
    if ($lastResult === 'credentials-invalid') {
        return 'update-credentials';
    }
    if (!empty($state['requiresReconnect'])) {
        return 'manual-reconnect';
    }

    return 'none';
}

function navid_login_failure_meta(string $errorCode): array
{
    switch ($errorCode) {
        case 'credentials_missing':
            return [
                'status' => 'credentials-missing',
                'requiresReconnect' => false,
            ];
        case 'credentials_invalid':
            return [
                'status' => 'credentials-invalid',
                'requiresReconnect' => false,
            ];
        case 'captcha_manual_required':
        case 'captcha_solver_unavailable':
        case 'captcha_retries_exhausted':
            return [
                'status' => 'reconnect-required',
                'requiresReconnect' => true,
            ];
        default:
            return [
                'status' => 'login-failed',
                'requiresReconnect' => true,
            ];
    }
}

function navid_build_owner_status(array $store): array
{
    $credentials = navid_get_credentials($store);
    $config = is_array($store['config'] ?? null) ? $store['config'] : [];
    $session = is_array($store['session'] ?? null) ? $store['session'] : [];
    $state = is_array($store['state'] ?? null) ? $store['state'] : [];
    $snapshot = is_array($store['snapshot'] ?? null) ? $store['snapshot'] : [];
    $assignments = is_array($snapshot['assignments'] ?? null) ? $snapshot['assignments'] : [];
    $enabled = !empty($config['enabled']);
    $actionRequired = navid_sync_action_required($credentials, $state, $enabled);

    return [
        'config' => [
            'enabled' => $enabled,
            'loginUrl' => navid_normalize_login_url((string) ($config['loginUrl'] ?? '')),
            'syncIntervalMinutes' => (int) ($config['syncIntervalMinutes'] ?? 30),
            'captchaStrategy' => (string) ($config['captchaStrategy'] ?? 'python_ocr'),
            'credentialUpdatedAt' => (string) ($config['credentialUpdatedAt'] ?? ''),
            'usernameMasked' => $credentials ? navid_mask_value((string) ($credentials['username'] ?? '')) : '',
            'hasCredentials' => $credentials !== null,
        ],
        'session' => [
            'status' => (string) ($session['status'] ?? 'missing'),
            'updatedAt' => (string) ($session['updatedAt'] ?? ''),
            'lastValidatedAt' => (string) ($session['lastValidatedAt'] ?? ''),
            'hasCookies' => !empty($session['cookies']),
        ],
        'state' => [
            'lastSyncAt' => (string) ($state['lastSyncAt'] ?? ''),
            'lastSuccessAt' => (string) ($state['lastSuccessAt'] ?? ''),
            'lastError' => (string) ($state['lastError'] ?? ''),
            'consecutiveFailures' => (int) ($state['consecutiveFailures'] ?? 0),
            'requiresReconnect' => !empty($state['requiresReconnect']),
            'lastSyncDurationMs' => (int) ($state['lastSyncDurationMs'] ?? 0),
            'lastResult' => (string) ($state['lastResult'] ?? ''),
            'actionRequired' => $actionRequired,
            'credentialsMissing' => $actionRequired === 'save-credentials',
            'credentialsInvalid' => $actionRequired === 'update-credentials',
        ],
        'snapshotCounts' => [
            'courses' => count(is_array($snapshot['courses'] ?? null) ? $snapshot['courses'] : []),
            'assignments' => count($assignments),
            'updates' => count(is_array($store['updates'] ?? null) ? $store['updates'] : []),
        ],
    ];
}

function navid_sync_due(array $store): bool
{
    $config = is_array($store['config'] ?? null) ? $store['config'] : [];
    $state = is_array($store['state'] ?? null) ? $store['state'] : [];

    if (empty($config['enabled'])) {
        return false;
    }

    $intervalMinutes = (int) ($config['syncIntervalMinutes'] ?? 30);
    if ($intervalMinutes < 5) {
        $intervalMinutes = 5;
    }

    $lastSuccessAt = trim((string) ($state['lastSuccessAt'] ?? ''));
    if ($lastSuccessAt === '') {
        return true;
    }

    $lastTs = strtotime($lastSuccessAt);
    if ($lastTs === false) {
        return true;
    }

    return (time() - $lastTs) >= ($intervalMinutes * 60);
}

function navid_sync(bool $force = false): array
{
    $store = navid_load_store();
    $config = is_array($store['config'] ?? null) ? $store['config'] : [];

    if (empty($config['enabled'])) {
        return [
            'success' => false,
            'status' => 'disabled',
            'message' => 'یکپارچه‌سازی نوید غیرفعال است.',
            'ownerStatus' => navid_build_owner_status($store),
        ];
    }

    if (!$force && !navid_sync_due($store)) {
        return [
            'success' => true,
            'status' => 'skipped',
            'message' => 'هنوز زمان همگام‌سازی دوره‌ای نوید نرسیده است.',
            'ownerStatus' => navid_build_owner_status($store),
        ];
    }

    $lockPath = dent_storage_path('navid/sync.lock');
    dent_ensure_directory(dirname($lockPath));
    $lock = fopen($lockPath, 'c+');
    if ($lock === false) {
        return [
            'success' => false,
            'status' => 'lock-failed',
            'message' => 'خطا در ایجاد قفل همگام‌سازی نوید.',
            'ownerStatus' => navid_build_owner_status($store),
        ];
    }

    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return [
            'success' => true,
            'status' => 'already-running',
            'message' => 'یک همگام‌سازی نوید در حال اجراست.',
            'ownerStatus' => navid_build_owner_status($store),
        ];
    }

    $startedAt = microtime(true);
    $loginUrl = navid_normalize_login_url((string) ($config['loginUrl'] ?? ''));
    $cookies = navid_get_session_cookies($store);
    $dashboard = navid_fetch_dashboard_payload($cookies, $loginUrl);
    $cookies = navid_merge_cookies($cookies, $dashboard['cookies'] ?? []);

    $store['state']['lastSyncAt'] = dent_iso_now();
    $store['state']['lastResult'] = 'running';

    try {
        if (empty($dashboard['success'])) {
            $login = navid_attempt_login($store);
            if (empty($login['success'])) {
                $store['state']['lastError'] = (string) ($login['message'] ?? 'ورود نوید انجام نشد.');
                $store['state']['consecutiveFailures'] = (int) ($store['state']['consecutiveFailures'] ?? 0) + 1;
                $loginErrorCode = (string) ($login['error'] ?? '');
                $loginFailureMeta = navid_login_failure_meta($loginErrorCode);
                $store['state']['requiresReconnect'] = !empty($loginFailureMeta['requiresReconnect']);
                $store['state']['lastResult'] = (string) ($loginFailureMeta['status'] ?? 'login-failed');
                navid_clear_session_cookies($store);
                navid_log('error', 'sync_login_failed', ['error' => $login['error'] ?? '', 'message' => $login['message'] ?? '']);
                navid_save_store($store);
                return [
                    'success' => false,
                    'status' => (string) ($loginFailureMeta['status'] ?? 'login-failed'),
                    'message' => (string) ($login['message'] ?? 'ورود نوید انجام نشد.'),
                    'ownerStatus' => navid_build_owner_status($store),
                ];
            }

            $cookies = navid_merge_cookies($cookies, $login['cookies'] ?? []);
            navid_set_session_cookies($store, $cookies);
            $store['session']['status'] = 'active';
            $store['state']['requiresReconnect'] = false;

            $dashboard = navid_fetch_dashboard_payload($cookies, $loginUrl);
            $cookies = navid_merge_cookies($cookies, $dashboard['cookies'] ?? []);
        }

        if (empty($dashboard['success']) || !is_array($dashboard['payload'] ?? null)) {
            $store['state']['lastError'] = 'خواندن داشبورد نوید انجام نشد.';
            $store['state']['consecutiveFailures'] = (int) ($store['state']['consecutiveFailures'] ?? 0) + 1;
            $store['state']['lastResult'] = 'dashboard-failed';
            navid_set_session_cookies($store, $cookies);
            navid_save_store($store);
            return [
                'success' => false,
                'status' => 'dashboard-failed',
                'message' => 'خواندن داشبورد نوید انجام نشد.',
                'ownerStatus' => navid_build_owner_status($store),
            ];
        }

        $courses = navid_extract_courses((array) $dashboard['payload'], $loginUrl);
        $currentAssignmentsByKey = [];
        $courseSnapshot = [];
        $failedCourses = 0;

        foreach ($courses as $course) {
            $courseTemplateId = (int) ($course['courseTemplateId'] ?? 0);
            if ($courseTemplateId <= 0) {
                continue;
            }

            $assignmentResult = navid_fetch_course_assignments($cookies, $loginUrl, $courseTemplateId);
            $cookies = navid_merge_cookies($cookies, $assignmentResult['cookies'] ?? []);

            if (empty($assignmentResult['success'])) {
                $failedCourses++;
                continue;
            }

            $assignmentList = is_array($assignmentResult['assignments'] ?? null) ? $assignmentResult['assignments'] : [];
            $courseAssignmentKeys = [];
            foreach ($assignmentList as $rawAssignment) {
                if (!is_array($rawAssignment)) {
                    continue;
                }

                $normalized = navid_normalize_assignment($course, $rawAssignment, $loginUrl);
                if ($normalized === null) {
                    continue;
                }

                $key = (string) $normalized['assignmentKey'];
                $courseAssignmentKeys[] = $key;
                $currentAssignmentsByKey[$key] = $normalized;
            }

            $courseSnapshot[(string) $courseTemplateId] = [
                'courseTemplateId' => $courseTemplateId,
                'courseTitle' => (string) ($course['courseTitle'] ?? ''),
                'courseUrl' => (string) ($course['courseUrl'] ?? ''),
                'assignmentKeys' => $courseAssignmentKeys,
                'assignmentCount' => count($courseAssignmentKeys),
                'lastCheckedAt' => dent_iso_now(),
            ];
        }

        $previousAssignments = is_array($store['snapshot']['assignments'] ?? null) ? $store['snapshot']['assignments'] : [];
        $newEvents = 0;

        foreach ($currentAssignmentsByKey as $key => $assignment) {
            $old = is_array($previousAssignments[$key] ?? null) ? $previousAssignments[$key] : null;
            if ($old === null) {
                $event = array_merge($assignment, [
                    'eventType' => 'created',
                    'detectedAt' => dent_iso_now(),
                    'eventId' => 'navid-' . str_replace(':', '-', $key) . '-' . time(),
                ]);
                navid_add_update_if_new($store, $event);
                $newEvents++;
                continue;
            }

            if ((string) ($old['fingerprint'] ?? '') !== (string) ($assignment['fingerprint'] ?? '')) {
                $event = array_merge($assignment, [
                    'eventType' => 'updated',
                    'detectedAt' => dent_iso_now(),
                    'eventId' => 'navid-' . str_replace(':', '-', $key) . '-' . time(),
                    'previousEndDateIso' => (string) ($old['endDateIso'] ?? ''),
                    'previousEndDateShamsi' => (string) ($old['endDateShamsi'] ?? ''),
                ]);
                navid_add_update_if_new($store, $event);
                $newEvents++;
            }
        }

        $store['snapshot']['courses'] = $courseSnapshot;
        $store['snapshot']['assignments'] = $currentAssignmentsByKey;

        navid_set_session_cookies($store, $cookies);
        $store['session']['status'] = 'active';
        $store['session']['lastValidatedAt'] = dent_iso_now();

        $store['state']['lastSuccessAt'] = dent_iso_now();
        $store['state']['lastError'] = '';
        $store['state']['consecutiveFailures'] = 0;
        $store['state']['requiresReconnect'] = false;
        $store['state']['lastResult'] = 'ok';
        $store['state']['lastSyncDurationMs'] = (int) round((microtime(true) - $startedAt) * 1000);

        navid_save_store($store);
        navid_log('info', 'sync_success', [
            'courses' => count($courses),
            'assignments' => count($currentAssignmentsByKey),
            'newEvents' => $newEvents,
            'failedCourses' => $failedCourses,
        ]);

        return [
            'success' => true,
            'status' => 'ok',
            'message' => 'همگام‌سازی نوید انجام شد.',
            'summary' => [
                'coursesChecked' => count($courses),
                'assignmentsFetched' => count($currentAssignmentsByKey),
                'newEvents' => $newEvents,
                'failedCourses' => $failedCourses,
            ],
            'ownerStatus' => navid_build_owner_status($store),
        ];
    } catch (Throwable $exception) {
        $store['state']['lastError'] = 'خطای داخلی همگام‌سازی نوید.';
        $store['state']['consecutiveFailures'] = (int) ($store['state']['consecutiveFailures'] ?? 0) + 1;
        $store['state']['lastResult'] = 'exception';
        $store['state']['lastSyncDurationMs'] = (int) round((microtime(true) - $startedAt) * 1000);
        navid_save_store($store);
        navid_log('error', 'sync_exception', ['type' => get_class($exception), 'message' => $exception->getMessage()]);

        return [
            'success' => false,
            'status' => 'exception',
            'message' => 'در همگام‌سازی نوید خطای داخلی رخ داد.',
            'ownerStatus' => navid_build_owner_status($store),
        ];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function navid_update_config(array $input): array
{
    $store = navid_load_store();
    $config = is_array($store['config'] ?? null) ? $store['config'] : navid_default_store()['config'];

    if (array_key_exists('enabled', $input)) {
        $config['enabled'] = !empty($input['enabled']);
    }

    if (array_key_exists('loginUrl', $input)) {
        $config['loginUrl'] = navid_normalize_login_url((string) $input['loginUrl']);
    }

    if (array_key_exists('syncIntervalMinutes', $input)) {
        $minutes = (int) $input['syncIntervalMinutes'];
        if ($minutes < 5) {
            $minutes = 5;
        }
        if ($minutes > 720) {
            $minutes = 720;
        }
        $config['syncIntervalMinutes'] = $minutes;
    }

    if (array_key_exists('captchaStrategy', $input)) {
        $strategy = trim((string) $input['captchaStrategy']);
        if (!in_array($strategy, ['python_ocr', 'manual_challenge'], true)) {
            $strategy = 'python_ocr';
        }
        $config['captchaStrategy'] = $strategy;
    }

    $username = trim((string) ($input['username'] ?? ''));
    $password = trim((string) ($input['password'] ?? ''));
    if ($username !== '' || $password !== '') {
        if ($username === '' || $password === '') {
            dent_error('برای به‌روزرسانی ورود نوید، نام کاربری و رمز را کامل وارد کن.', 422);
        }
        navid_set_credentials($store, $username, $password);
        $store['state']['requiresReconnect'] = false;
        $store['state']['lastError'] = '';
        $lastResult = (string) ($store['state']['lastResult'] ?? '');
        if (in_array($lastResult, ['credentials-missing', 'credentials-invalid'], true)) {
            $store['state']['lastResult'] = 'config-updated';
        }
    }

    if (!empty($config['enabled']) && navid_get_credentials($store) === null) {
        dent_error('برای فعال بودن نوید، باید نام کاربری و رمز را ذخیره کنید.', 422);
    }

    $store['config'] = array_merge($store['config'] ?? [], $config);
    navid_save_store($store);

    return [
        'success' => true,
        'message' => 'تنظیمات نوید ذخیره شد.',
        'ownerStatus' => navid_build_owner_status($store),
    ];
}

function navid_create_captcha_challenge(): array
{
    $store = navid_load_store();
    $loginUrl = navid_normalize_login_url((string) ($store['config']['loginUrl'] ?? ''));

    $first = navid_http_request('GET', $loginUrl, [
        'headers' => ['X-Requested-With: XMLHttpRequest'],
    ]);
    if (!$first['ok'] || (int) ($first['status'] ?? 0) >= 400 || (int) ($first['status'] ?? 0) < 200) {
        dent_error('صفحه ورود نوید در دسترس نیست.', 502);
    }

    $cookies = navid_merge_cookies([], $first['cookies'] ?? []);
    $token = navid_extract_anti_forgery_token((string) ($first['body'] ?? ''));
    if ($token === '') {
        dent_error('توکن امنیتی نوید پیدا نشد.', 502);
    }

    $captcha = navid_get_captcha_image($cookies, $loginUrl);
    if (empty($captcha['success'])) {
        dent_error('دریافت کپچای نوید انجام نشد.', 502);
    }
    $cookies = navid_merge_cookies($cookies, $captcha['cookies'] ?? []);

    $challenge = [
        'createdAt' => dent_iso_now(),
        'expiresAt' => date('c', time() + 10 * 60),
        'loginUrl' => $loginUrl,
        'requestVerificationToken' => $token,
        'cookies' => $cookies,
    ];
    navid_set_challenge($store, $challenge);
    navid_save_store($store);

    return [
        'success' => true,
        'message' => 'کپچا دریافت شد.',
        'captchaDataUri' => 'data:' . ($captcha['contentType'] ?? 'image/png') . ';base64,' . $captcha['data'],
        'expiresAt' => (string) $challenge['expiresAt'],
        'ownerStatus' => navid_build_owner_status($store),
    ];
}

function navid_complete_captcha_challenge(string $captchaCode): array
{
    $code = strtoupper(trim($captchaCode));
    $code = preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
    if (strlen($code) < 4) {
        dent_error('کد کپچا نامعتبر است.', 422);
    }

    $store = navid_load_store();
    $challenge = navid_get_challenge($store);
    if (!is_array($challenge)) {
        dent_error('چالش کپچای فعالی وجود ندارد.', 422);
    }

    $expiresAt = strtotime((string) ($challenge['expiresAt'] ?? ''));
    if ($expiresAt !== false && $expiresAt < time()) {
        navid_clear_challenge($store);
        navid_save_store($store);
        dent_error('زمان اعتبار کپچا تمام شده است. دوباره کپچا بگیر.', 422);
    }

    $credentials = navid_get_credentials($store);
    if ($credentials === null) {
        dent_error('نام کاربری/رمز نوید ذخیره نشده است.', 422);
    }

    $loginUrl = navid_normalize_login_url((string) ($challenge['loginUrl'] ?? ($store['config']['loginUrl'] ?? '')));
    $token = trim((string) ($challenge['requestVerificationToken'] ?? ''));
    $cookies = is_array($challenge['cookies'] ?? null) ? $challenge['cookies'] : [];
    if ($token === '') {
        dent_error('توکن چالش کپچا معتبر نیست.', 422);
    }

    $submit = navid_submit_login(
        $loginUrl,
        $cookies,
        $token,
        (string) $credentials['username'],
        (string) $credentials['password'],
        $code
    );
    if (empty($submit['success'])) {
        dent_error((string) ($submit['message'] ?: 'ورود با کپچا انجام نشد.'), 422);
    }

    $mergedCookies = navid_merge_cookies($cookies, $submit['cookies'] ?? []);
    $nextUrl = trim((string) ($submit['nextUrl'] ?? ''));
    if ($nextUrl !== '') {
        $origin = navid_origin_from_login_url($loginUrl);
        $final = navid_http_request('GET', navid_absolute_url($origin, $nextUrl), [
            'cookies' => $mergedCookies,
            'origin' => $origin,
            'referer' => $loginUrl,
        ]);
        $mergedCookies = navid_merge_cookies($mergedCookies, $final['cookies'] ?? []);
    }

    navid_set_session_cookies($store, $mergedCookies);
    navid_clear_challenge($store);
    $store['state']['requiresReconnect'] = false;
    $store['state']['lastError'] = '';
    navid_save_store($store);

    return [
        'success' => true,
        'message' => 'اتصال نوید با کپچای دستی انجام شد.',
        'ownerStatus' => navid_build_owner_status($store),
    ];
}

function navid_feed_payload(bool $ownerView): array
{
    $store = navid_load_store();

    // Opportunistic sync when due; this keeps data fresh even without explicit owner action.
    if (navid_sync_due($store)) {
        navid_sync(false);
        $store = navid_load_store();
    }

    $assignments = is_array($store['snapshot']['assignments'] ?? null) ? array_values($store['snapshot']['assignments']) : [];
    $assignments = navid_assignments_sorted($assignments);
    $updates = is_array($store['updates'] ?? null) ? array_slice($store['updates'], 0, 40) : [];
    $credentials = navid_get_credentials($store);
    $enabled = !empty($store['config']['enabled']);
    $actionRequired = navid_sync_action_required(
        $credentials,
        is_array($store['state'] ?? null) ? $store['state'] : [],
        $enabled
    );

    $payload = [
        'currentAssignments' => array_slice($assignments, 0, 120),
        'updates' => $updates,
        'publicStatus' => [
            'lastSyncAt' => (string) ($store['state']['lastSyncAt'] ?? ''),
            'lastSuccessAt' => (string) ($store['state']['lastSuccessAt'] ?? ''),
            'lastResult' => (string) ($store['state']['lastResult'] ?? ''),
            'lastError' => (string) ($store['state']['lastError'] ?? ''),
            'requiresReconnect' => !empty($store['state']['requiresReconnect']),
            'enabled' => $enabled,
            'hasCredentials' => $credentials !== null,
            'actionRequired' => $actionRequired,
            'credentialsMissing' => $actionRequired === 'save-credentials',
            'credentialsInvalid' => $actionRequired === 'update-credentials',
        ],
    ];

    if ($ownerView) {
        $payload['ownerStatus'] = navid_build_owner_status($store);
    }

    return [
        'success' => true,
        'message' => '',
        'data' => $payload,
    ];
}

