<?php
declare(strict_types=1);

require_once __DIR__ . '/hamiad_bridge_service.php';

/*
 * Hamiad (Moodle behind ArvanCloud) is unreliable to log in to: the CAPTCHA
 * login fails intermittently. The stable path is to need it as rarely as
 * possible: once a session exists, touch it every few minutes so Moodle never
 * idles it out, and persist the refreshed cookies (Arvan rotates them).
 */

function hamiad_keepalive_status_path(): string
{
    return dent_storage_path('hamiad/keepalive-status.json');
}

function hamiad_mobile_probe_path(): string
{
    return dent_storage_path('hamiad/mobile-probe.json');
}

function hamiad_keepalive_write(string $path, array $record): void
{
    $oldUmask = umask(0077);
    try {
        dent_write_json_file($path, $record);
        @chmod($path, 0600);
    } finally {
        umask($oldUmask);
    }
}

/**
 * Re-read, compare and write under the same lock dent_write_json_file (and so
 * hamiad_login) uses, so a login that completes concurrently is never replaced
 * by the older session's cookies.
 */
function hamiad_keepalive_persist_cookies(string $sessionPath, string $loadedConnectedAt, array $cookies): bool
{
    dent_ensure_directory(dirname($sessionPath));
    $oldUmask = umask(0077);
    $lock = @fopen($sessionPath . '.lock', 'c');
    umask($oldUmask);
    if (!is_resource($lock) || !@flock($lock, LOCK_EX)) {
        if (is_resource($lock)) @fclose($lock);
        return false;
    }
    try {
        $current = dent_read_json_file($sessionPath, []);
        if ((string) ($current['connectedAt'] ?? '') !== $loadedConnectedAt) {
            return false;
        }
        $current['cookies'] = hamiad_seal($cookies);
        $current['lastKeepaliveAt'] = dent_iso_now();
        $oldUmask = umask(0077);
        try {
            dent_write_json_file($sessionPath, $current, true);
            @chmod($sessionPath, 0600);
        } finally {
            umask($oldUmask);
        }
        return true;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

/**
 * GET /my/ with the saved cookies, following only Arvan's same-URL 307
 * challenge. Moodle answers an expired session with a 303 to /login (or the
 * login form itself); both mean "expired", not a transport error.
 */
function hamiad_keepalive_probe_home(array &$cookies, ?callable $request = null): string
{
    $request ??= 'hamiad_http_request';
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $result = $request('GET', HAMIAD_HOME, [
            'cookies' => $cookies, 'timeoutSec' => 22, 'referer' => HAMIAD_HOME,
        ]);
        $cookies = hamiad_merge_cookies($cookies, (array) ($result['cookies'] ?? []));
        if (empty($result['ok'])) {
            return 'error';
        }
        $status = (int) ($result['status'] ?? 0);
        $location = hamiad_response_header_value((array) ($result['headers'] ?? []), 'location');
        if ($status === 200) {
            $body = (string) ($result['body'] ?? '');
            if (str_contains($body, 'login/logout.php')) {
                return 'alive';
            }
            if (str_contains($body, 'login_captcha_id0') || str_contains($body, 'id="login"')) {
                return 'expired';
            }
            return 'error';
        }
        if ($status === 307 && hash_equals(HAMIAD_HOME, $location)) {
            continue;
        }
        if (in_array($status, [301, 302, 303, 307], true) && str_contains($location, '/login/')) {
            return 'expired';
        }
        return 'error';
    }
    return 'error';
}

/**
 * Touch the saved session. Returns ['state' => 'alive'|'expired'|'none'|'error'].
 * The saved session is only rewritten when it is still the one we loaded, so
 * a login that lands meanwhile is never overwritten with older cookies.
 */
function hamiad_keepalive_touch(?callable $request = null): array
{
    $sessionPath = hamiad_session_path();
    $saved = dent_read_json_file($sessionPath, []);
    $cookies = hamiad_unseal($saved['cookies'] ?? null);
    if (!is_array($cookies) || $cookies === []) {
        $result = ['state' => 'none'];
    } else {
        $loadedConnectedAt = (string) ($saved['connectedAt'] ?? '');
        $state = hamiad_keepalive_probe_home($cookies, $request);
        if ($state === 'alive') {
            hamiad_keepalive_persist_cookies($sessionPath, $loadedConnectedAt, $cookies);
        }
        $result = ['state' => $state];
    }

    $prior = dent_read_json_file(hamiad_keepalive_status_path(), []);
    $status = [
        'at' => dent_iso_now(),
        'state' => $result['state'],
        'lastAliveAt' => $result['state'] === 'alive' ? dent_iso_now() : (string) ($prior['lastAliveAt'] ?? ''),
        'consecutiveFailures' => $result['state'] === 'alive' ? 0 : (int) ($prior['consecutiveFailures'] ?? 0) + 1,
    ];
    hamiad_keepalive_write(hamiad_keepalive_status_path(), $status);
    return $result;
}

/**
 * Public, credential-free probe of Moodle's mobile-app login support. If the
 * mobile web service is enabled, a token login (no CAPTCHA) is possible.
 * Only a few non-sensitive switches are stored.
 */
function hamiad_mobile_probe(?callable $request = null): array
{
    $request ??= 'hamiad_http_request';
    $url = HAMIAD_BASE . '/lib/ajax/service-nologin.php?info=tool_mobile_get_public_config';
    $cookies = [];
    $result = [];
    // Arvan answers with a same-URL 307 that sets short-lived cookies; repeat
    // the POST with them (bounded), exactly like the GET challenge handling.
    for ($attempt = 0; $attempt < 4; $attempt++) {
        $result = $request('POST', $url, [
            'json' => [['index' => 0, 'methodname' => 'tool_mobile_get_public_config', 'args' => (object) []]],
            'cookies' => $cookies,
            'timeoutSec' => 16,
            'referer' => HAMIAD_LOGIN,
        ]);
        $cookies = hamiad_merge_cookies($cookies, (array) ($result['cookies'] ?? []));
        $location = hamiad_response_header_value((array) ($result['headers'] ?? []), 'location');
        if (empty($result['ok']) || (int) ($result['status'] ?? 0) !== 307 || !hash_equals($url, $location)) {
            break;
        }
    }
    $record = ['at' => dent_iso_now(), 'reachable' => !empty($result['ok']),
        'httpStatus' => (int) ($result['status'] ?? 0)];
    $decoded = json_decode((string) ($result['body'] ?? ''), true);
    $data = is_array($decoded) && is_array($decoded[0] ?? null) ? $decoded[0] : [];
    $config = is_array($data['data'] ?? null) ? $data['data'] : [];
    $record['answered'] = $config !== [];
    $record['error'] = !empty($data['error']);
    foreach (['enablewebservices', 'enablemobilewebservice', 'typeoflogin', 'maintenanceenabled'] as $key) {
        if (array_key_exists($key, $config) && is_scalar($config[$key])) {
            $record[$key] = (int) $config[$key];
        }
    }
    hamiad_keepalive_write(hamiad_mobile_probe_path(), $record);
    return $record;
}

function hamiad_mobile_probe_due(int $clock): bool
{
    $prior = dent_read_json_file(hamiad_mobile_probe_path(), []);
    $at = strtotime((string) ($prior['at'] ?? ''));
    // Unanswered probes (e.g. a WAF challenge) retry hourly; answers daily.
    $interval = !empty($prior['answered']) ? 86400 : 3600;
    return $at === false || $at < $clock - $interval;
}

/**
 * Reminders keep their three daily slots (08, 14, 20 Tehran) although the
 * timer now fires every 10 minutes. A slot runs once, on the first tick at
 * or after its start and before the next slot.
 */
function hamiad_reminder_slot_key(int $clock): ?string
{
    $tehran = new DateTimeImmutable('@' . $clock);
    $tehran = $tehran->setTimezone(new DateTimeZone('Asia/Tehran'));
    $hour = (int) $tehran->format('G');
    $slot = null;
    foreach ([8, 14, 20] as $start) {
        if ($hour >= $start) {
            $slot = $start;
        }
    }
    return $slot === null ? null : $tehran->format('Y-m-d') . '@' . $slot;
}
