<?php
declare(strict_types=1);

require_once __DIR__ . '/hamiad_keepalive.php';
require_once __DIR__ . '/hamiad_credentials.php';

/*
 * CAPTCHA-free re-login through Moodle's mobile-app path (Hamiad has
 * enablemobilewebservice=1, typeoflogin=1 — verified by the public probe):
 *   1. login/token.php (username + password, service moodle_mobile_app)
 *      → a long-lived token + privatetoken. No CAPTCHA on this endpoint.
 *   2. tool_mobile_get_autologin_key (token + privatetoken, app user agent)
 *      → a one-time key; admin/tool/mobile/autologin.php turns it into a
 *      normal MoodleSession, which the existing scraping code keeps using.
 * The password is only sent when no valid token exists. A rejected password
 * blocks further automatic attempts until the owner re-saves credentials, so
 * the account can never be locked out by retries.
 */

const HAMIAD_MOBILE_UA = 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Mobile Safari/537.36 MoodleMobile 4.4.0 (44001)';
const HAMIAD_MOBILE_MIN_INTERVAL = 600; // seconds between automatic attempts

function hamiad_mobile_token_path(): string
{
    return dent_storage_path('hamiad/mobile-token.json');
}

function hamiad_mobile_state_path(): string
{
    return dent_storage_path('hamiad/mobile-login-state.json');
}

/** POST to Moodle with the app user agent, through Arvan's same-URL 307. */
function hamiad_mobile_post(string $url, array $form, ?callable $request = null): array
{
    $request ??= 'hamiad_http_request';
    $cookies = [];
    $result = [];
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $result = $request('POST', $url, [
            'form' => $form, 'cookies' => $cookies, 'timeoutSec' => 22,
            'userAgent' => HAMIAD_MOBILE_UA,
        ]);
        $cookies = hamiad_merge_cookies($cookies, (array) ($result['cookies'] ?? []));
        $location = hamiad_response_header_value((array) ($result['headers'] ?? []), 'location');
        if (empty($result['ok']) || (int) ($result['status'] ?? 0) !== 307 || !hash_equals($url, $location)) {
            break;
        }
    }
    $decoded = json_decode((string) ($result['body'] ?? ''), true);
    return [
        'ok' => !empty($result['ok']) && (int) ($result['status'] ?? 0) === 200 && is_array($decoded),
        'json' => is_array($decoded) ? $decoded : [],
    ];
}

function hamiad_mobile_ws(string $token, string $function, array $args, ?callable $request = null): array
{
    return hamiad_mobile_post(
        HAMIAD_BASE . '/webservice/rest/server.php?moodlewsrestformat=json',
        array_merge(['wstoken' => $token, 'wsfunction' => $function], $args),
        $request
    );
}

/** Exchange credentials for a mobile token. Never returns the password. */
function hamiad_mobile_fetch_token(string $username, string $password, ?callable $request = null): array
{
    $response = hamiad_mobile_post(HAMIAD_BASE . '/login/token.php', [
        'username' => $username, 'password' => $password, 'service' => 'moodle_mobile_app',
    ], $request);
    $json = $response['json'];
    $token = (string) ($json['token'] ?? '');
    $private = (string) ($json['privatetoken'] ?? '');
    if ($response['ok'] && preg_match('/^[A-Za-z0-9]{16,128}$/D', $token)) {
        return ['ok' => true, 'token' => $token, 'privatetoken' => $private];
    }
    $code = (string) ($json['errorcode'] ?? ($response['ok'] ? 'unknown' : 'transport'));
    return ['ok' => false, 'errorcode' => preg_replace('/[^a-z0-9_]/', '', strtolower($code)) ?: 'unknown'];
}

/** Resolve a redirect target, allowing only Hamiad pages on the autologin path. */
function hamiad_mobile_next_url(string $location, string $current): ?string
{
    $location = trim($location);
    if ($location === '' || strlen($location) > 2048 || preg_match('/[\x00-\x1f\x7f]/', $location)
        || str_starts_with($location, '//')) {
        return null;
    }
    $url = str_starts_with($location, '/') ? HAMIAD_BASE . $location : $location;
    $uri = parse_url($url);
    if (!is_array($uri) || strtolower((string) ($uri['scheme'] ?? '')) !== 'https'
        || strtolower((string) ($uri['host'] ?? '')) !== 'lms.tums.ac.ir'
        || isset($uri['user']) || isset($uri['pass']) || isset($uri['port'])) {
        return null;
    }
    return $url;
}

/** Turn a mobile token into a verified web session (cookies) or report why not. */
function hamiad_mobile_session_from_token(string $token, string $privateToken, ?callable $request = null): array
{
    $request ??= 'hamiad_http_request';
    $info = hamiad_mobile_ws($token, 'core_webservice_get_site_info', [], $request);
    $userId = (int) ($info['json']['userid'] ?? 0);
    if (!$info['ok'] || $userId <= 0) {
        $code = (string) ($info['json']['errorcode'] ?? 'siteinfo');
        return ['ok' => false, 'errorcode' => $code === 'invalidtoken' ? 'invalidtoken' : 'siteinfo'];
    }
    $key = hamiad_mobile_ws($token, 'tool_mobile_get_autologin_key', ['privatetoken' => $privateToken], $request);
    $autologinKey = (string) ($key['json']['key'] ?? '');
    $autologinUrl = (string) ($key['json']['autologinurl'] ?? (HAMIAD_BASE . '/admin/tool/mobile/autologin.php'));
    if (!$key['ok'] || $autologinKey === '') {
        $code = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($key['json']['errorcode'] ?? 'autologinkey')));
        return ['ok' => false, 'errorcode' => $code ?: 'autologinkey'];
    }
    $url = hamiad_mobile_next_url($autologinUrl, HAMIAD_BASE);
    if ($url === null) {
        return ['ok' => false, 'errorcode' => 'autologinurl'];
    }
    $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query([
        'userid' => $userId, 'key' => $autologinKey, 'urltogo' => HAMIAD_HOME,
    ]);
    $cookies = [];
    for ($hop = 0; $hop < 8; $hop++) {
        $result = $request('GET', $url, ['cookies' => $cookies, 'timeoutSec' => 22, 'userAgent' => HAMIAD_MOBILE_UA]);
        $cookies = hamiad_merge_cookies($cookies, (array) ($result['cookies'] ?? []));
        if (empty($result['ok'])) {
            return ['ok' => false, 'errorcode' => 'transport'];
        }
        $status = (int) ($result['status'] ?? 0);
        if (!in_array($status, [301, 302, 303, 307], true)) {
            break;
        }
        $next = hamiad_mobile_next_url(hamiad_response_header_value((array) ($result['headers'] ?? []), 'location'), $url);
        if ($next === null) {
            return ['ok' => false, 'errorcode' => 'redirect'];
        }
        $url = $next;
    }
    if (hamiad_keepalive_probe_home($cookies, $request) !== 'alive') {
        return ['ok' => false, 'errorcode' => 'notauthenticated'];
    }
    return ['ok' => true, 'cookies' => $cookies];
}

function hamiad_mobile_save_session(string $username, array $cookies): void
{
    $oldUmask = umask(0077);
    try {
        dent_write_json_file(hamiad_session_path(), [
            'schemaVersion' => 1,
            'origin' => HAMIAD_BASE,
            'connectedAt' => dent_iso_now(),
            'usernameMasked' => substr($username, 0, 2) . str_repeat('*', max(2, strlen($username) - 4)) . substr($username, -2),
            'cookies' => hamiad_seal($cookies),
            'via' => 'mobile-token',
        ]);
        @chmod(hamiad_session_path(), 0600);
    } finally {
        umask($oldUmask);
    }
}

/**
 * Restore the Hamiad web session without a CAPTCHA. Returns
 * ['ok' => bool, 'reason' => connected|no_credentials|throttled|credentials_rejected|<moodle errorcode>].
 */
function hamiad_mobile_relogin(?callable $request = null, ?int $clock = null, bool $force = false): array
{
    // One attempt at a time across the timer and the bot: the retry state is
    // read, checked and updated inside this lock, so a rejected password is
    // never submitted twice.
    $lockPath = dent_storage_path('hamiad/mobile-login.lock');
    dent_ensure_directory(dirname($lockPath));
    $oldUmask = umask(0077);
    $lock = @fopen($lockPath, 'c');
    umask($oldUmask);
    if (!is_resource($lock) || !@flock($lock, LOCK_EX)) {
        if (is_resource($lock)) @fclose($lock);
        return ['ok' => false, 'reason' => 'busy'];
    }
    try {
        return hamiad_mobile_relogin_locked($request, $clock, $force);
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function hamiad_mobile_relogin_locked(?callable $request, ?int $clock, bool $force): array
{
    $clock ??= time();
    $credentials = hamiad_credentials_load();
    if ($credentials === null) {
        return ['ok' => false, 'reason' => 'no_credentials'];
    }
    $savedAt = (string) (hamiad_credentials_status()['savedAt'] ?? '');
    $state = dent_read_json_file(hamiad_mobile_state_path(), []);
    if (($state['blockedCredentialsSavedAt'] ?? null) === $savedAt && $savedAt !== '') {
        return ['ok' => false, 'reason' => 'credentials_rejected'];
    }
    $last = strtotime((string) ($state['lastAttemptAt'] ?? '')) ?: 0;
    // Newly saved credentials deserve an immediate attempt; the throttle only
    // spaces out retries with the same credentials.
    $sameCredentials = ($state['lastCredentialsSavedAt'] ?? null) === $savedAt;
    if (!$force && $sameCredentials && $last > $clock - HAMIAD_MOBILE_MIN_INTERVAL) {
        return ['ok' => false, 'reason' => 'throttled'];
    }
    $state['lastAttemptAt'] = date(DATE_ATOM, $clock);
    $state['lastCredentialsSavedAt'] = $savedAt;

    $finish = static function (array $result) use (&$state): array {
        $state['lastResult'] = $result['reason'];
        if ($result['ok']) {
            $state['lastSuccessAt'] = dent_iso_now();
        }
        hamiad_keepalive_write(hamiad_mobile_state_path(), $state);
        return $result;
    };

    $credentialId = hash('sha256', $credentials['username'] . "\n" . $savedAt);
    $stored = dent_read_json_file(hamiad_mobile_token_path(), []);
    $tokens = hamiad_unseal($stored['sealed'] ?? null);
    if (is_array($tokens) && !hash_equals($credentialId, (string) ($tokens['credentialId'] ?? ''))) {
        // Issued for other (or older) saved credentials: never reuse it.
        @unlink(hamiad_mobile_token_path());
        $tokens = null;
    }
    if (is_array($tokens) && ($tokens['token'] ?? '') !== '') {
        $session = hamiad_mobile_session_from_token((string) $tokens['token'], (string) ($tokens['privatetoken'] ?? ''), $request);
        if ($session['ok']) {
            hamiad_mobile_save_session($credentials['username'], $session['cookies']);
            return $finish(['ok' => true, 'reason' => 'connected']);
        }
        if ($session['errorcode'] !== 'invalidtoken') {
            // Token still valid; Moodle refused the key now (rate limit etc.).
            return $finish(['ok' => false, 'reason' => (string) $session['errorcode']]);
        }
        @unlink(hamiad_mobile_token_path());
    }

    $fresh = hamiad_mobile_fetch_token($credentials['username'], $credentials['password'], $request);
    if (!$fresh['ok']) {
        if ($fresh['errorcode'] === 'invalidlogin') {
            // Do not retry a rejected password: Moodle would lock the account.
            $state['blockedCredentialsSavedAt'] = $savedAt;
            return $finish(['ok' => false, 'reason' => 'credentials_rejected']);
        }
        return $finish(['ok' => false, 'reason' => (string) $fresh['errorcode']]);
    }
    hamiad_keepalive_write(hamiad_mobile_token_path(), [
        'schemaVersion' => 1,
        'createdAt' => dent_iso_now(),
        'sealed' => hamiad_seal(['token' => $fresh['token'], 'privatetoken' => $fresh['privatetoken'],
            'credentialId' => $credentialId]),
    ]);
    $session = hamiad_mobile_session_from_token($fresh['token'], $fresh['privatetoken'], $request);
    if (!$session['ok']) {
        return $finish(['ok' => false, 'reason' => (string) $session['errorcode']]);
    }
    hamiad_mobile_save_session($credentials['username'], $session['cookies']);
    return $finish(['ok' => true, 'reason' => 'connected']);
}
