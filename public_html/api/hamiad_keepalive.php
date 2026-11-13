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
        try {
            $body = hamiad_get_html_with_waf_retry(HAMIAD_HOME, $cookies, $request);
            $alive = str_contains($body, 'login/logout.php');
        } catch (RuntimeException $error) {
            $alive = false;
            $expired = str_contains($error->getMessage(), 'منقضی');
            if (!$expired) {
                $result = ['state' => 'error'];
            }
        }
        if (!isset($result)) {
            if ($alive) {
                hamiad_keepalive_persist_cookies($sessionPath, $loadedConnectedAt, $cookies);
                $result = ['state' => 'alive'];
            } else {
                $result = ['state' => 'expired'];
            }
        }
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
    $result = $request('POST', HAMIAD_BASE . '/lib/ajax/service-nologin.php?info=tool_mobile_get_public_config', [
        'json' => [['index' => 0, 'methodname' => 'tool_mobile_get_public_config', 'args' => (object) []]],
        'timeoutSec' => 16,
        'referer' => HAMIAD_LOGIN,
    ]);
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
    return $at === false || $at < $clock - 86400;
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
