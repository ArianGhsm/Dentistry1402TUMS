<?php
declare(strict_types=1);

// Isolated storage so the test never touches real Hamiad state.
$root = sys_get_temp_dir() . '/dent-hamiad-keepalive-' . bin2hex(random_bytes(6));
mkdir($root . '/hamiad', 0700, true);
putenv('DENT_STORAGE_ROOT=' . $root);
$_ENV['DENT_STORAGE_ROOT'] = $root;

require_once dirname(__DIR__) . '/public_html/api/hamiad_keepalive.php';

function check(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

check(str_starts_with(dent_storage_path('hamiad/x.json'), $root), 'test storage is isolated');

$saveSession = static function (string $connectedAt, array $cookies): void {
    dent_write_json_file(hamiad_session_path(), [
        'schemaVersion' => 1, 'origin' => HAMIAD_BASE, 'connectedAt' => $connectedAt,
        'usernameMasked' => '40**03', 'cookies' => hamiad_seal($cookies),
    ]);
};
$cookie = static fn (string $name, string $value): array => [
    'name' => $name, 'value' => $value, 'domain' => 'lms.tums.ac.ir', 'path' => '/',
];

// No session: nothing to touch.
check(hamiad_keepalive_touch(static fn () => ['ok' => true, 'status' => 200, 'body' => '', 'headers' => [], 'cookies' => []])['state'] === 'none',
    'no saved session reports none');

// Alive: authenticated page, refreshed cookie is persisted.
$saveSession('2026-11-13T08:00:00+03:30', [$cookie('MoodleSession', 'old')]);
$alive = static fn () => ['ok' => true, 'status' => 200, 'headers' => [],
    'cookies' => [$cookie('__arcsjs', 'rotated')],
    'body' => '<a href="https://lms.tums.ac.ir/login/logout.php?sesskey=x">خروج</a>'];
check(hamiad_keepalive_touch($alive)['state'] === 'alive', 'authenticated page keeps the session alive');
$names = array_map(static fn ($c) => $c['name'], hamiad_load_cookies());
check(in_array('__arcsjs', $names, true) && in_array('MoodleSession', $names, true), 'rotated cookies are persisted');
$status = dent_read_json_file(hamiad_keepalive_status_path(), []);
check(($status['state'] ?? '') === 'alive' && ($status['consecutiveFailures'] ?? -1) === 0, 'status records alive');

// A login that lands during the touch must not be overwritten by older cookies.
$raceLogin = static function () use ($saveSession, $cookie) {
    $saveSession('2026-11-13T09:00:00+03:30', [$cookie('MoodleSession', 'fresh-login')]);
    return ['ok' => true, 'status' => 200, 'headers' => [], 'cookies' => [],
        'body' => '<a href="/login/logout.php?sesskey=y">خروج</a>'];
};
hamiad_keepalive_touch($raceLogin);
$values = array_map(static fn ($c) => $c['value'], hamiad_load_cookies());
check($values === ['fresh-login'], 'a concurrent new login is never overwritten');

// Expired: Moodle shows the login form.
$expired = static fn () => ['ok' => true, 'status' => 200, 'headers' => [], 'cookies' => [],
    'body' => '<form id="login"><input name="login_captcha_id0"></form>'];
check(hamiad_keepalive_touch($expired)['state'] === 'expired', 'login page means expired');
check((dent_read_json_file(hamiad_keepalive_status_path(), [])['consecutiveFailures'] ?? 0) === 1, 'failures are counted');

// Transport error is not reported as expiry.
$down = static fn () => ['ok' => false, 'status' => 0, 'headers' => [], 'cookies' => [], 'body' => ''];
check(hamiad_keepalive_touch($down)['state'] === 'error', 'transport failure is an error, not expiry');

// Mobile probe keeps only the switches.
$probe = static fn () => ['ok' => true, 'status' => 200, 'headers' => [], 'cookies' => [],
    'body' => json_encode([['error' => false, 'data' => [
        'enablewebservices' => 1, 'enablemobilewebservice' => 1, 'typeoflogin' => 1,
        'sitename' => 'private-site-name-7731', 'maintenanceenabled' => 0,
    ]]])];
$record = hamiad_mobile_probe($probe);
check($record['answered'] === true && $record['enablemobilewebservice'] === 1 && $record['typeoflogin'] === 1,
    'mobile probe parses the switches');
check(!str_contains((string) file_get_contents(hamiad_mobile_probe_path()), 'private-site-name-7731'),
    'mobile probe stores no other provider data');
check(!hamiad_mobile_probe_due(time()), 'probe is not due right after running');

// Reminder slots: 08 / 14 / 20 Tehran, once each.
check(hamiad_reminder_slot_key(strtotime('2026-11-13T07:50:00+03:30')) === '2026-11-12@20' || hamiad_reminder_slot_key(strtotime('2026-11-13T07:50:00+03:30')) === null,
    'before 08 belongs to no new slot of the day');
check(hamiad_reminder_slot_key(strtotime('2026-11-13T08:05:00+03:30')) === '2026-11-13@8', '08 slot');
check(hamiad_reminder_slot_key(strtotime('2026-11-13T13:59:00+03:30')) === '2026-11-13@8', 'still the 08 slot');
check(hamiad_reminder_slot_key(strtotime('2026-11-13T14:00:00+03:30')) === '2026-11-13@14', '14 slot');
check(hamiad_reminder_slot_key(strtotime('2026-11-13T23:30:00+03:30')) === '2026-11-13@20', '20 slot');

// The comparison and write happen under the session-file lock login uses.
$saveSession('2026-11-13T10:00:00+03:30', [$cookie('MoodleSession', 'locked-check')]);
if (function_exists('pcntl_fork')) {
$lockHandle = fopen(hamiad_session_path() . '.lock', 'c');
check(flock($lockHandle, LOCK_EX), 'test can take the session lock');
$pid = pcntl_fork();
if ($pid === 0) {
    // child: holds nothing; keepalive must block until the parent releases
    exit(hamiad_keepalive_persist_cookies(hamiad_session_path(), '2026-11-13T09:59:00+03:30', []) ? 1 : 0);
}
usleep(200000);
flock($lockHandle, LOCK_UN);
pcntl_waitpid($pid, $childStatus);
check(pcntl_wexitstatus($childStatus) === 0, 'a changed connectedAt is not overwritten under the lock');
}
check(hamiad_keepalive_persist_cookies(hamiad_session_path(), '2026-11-13T10:00:00+03:30', [$cookie('MoodleSession', 'refreshed')]),
    'an unchanged session is refreshed under the lock');

// Moodle answers an expired session with a 303 to /login: that is expiry, not an error.
$saveSession('2026-11-13T11:00:00+03:30', [$cookie('MoodleSession', 'stale')]);
$redirectToLogin = static fn () => ['ok' => true, 'status' => 303, 'cookies' => [], 'body' => '',
    'headers' => ['Location: https://lms.tums.ac.ir/login/index.php']];
check(hamiad_keepalive_touch($redirectToLogin)['state'] === 'expired', '303 to /login is expiry');

// Arvan's same-URL 307 is followed with its cookies, then the page is read.
$calls = 0;
$arvanThenAlive = static function (string $method, string $url, array $options) use (&$calls, $cookie) {
    $calls++;
    if ($calls === 1) {
        return ['ok' => true, 'status' => 307, 'body' => '', 'cookies' => [$cookie('__arcsjs', 'challenge')],
            'headers' => ['Location: ' . HAMIAD_HOME]];
    }
    $names = array_map(static fn ($c) => $c['name'], (array) ($options['cookies'] ?? []));
    return ['ok' => true, 'status' => 200, 'headers' => [], 'cookies' => [],
        'body' => in_array('__arcsjs', $names, true) ? '<a href="/login/logout.php?sesskey=z">خروج</a>' : ''];
};
check(hamiad_keepalive_touch($arvanThenAlive)['state'] === 'alive', 'Arvan 307 is followed with its cookies');

// The mobile probe repeats its POST through Arvan's 307.
$probeCalls = 0;
$probe307 = static function (string $method, string $url, array $options) use (&$probeCalls, $cookie) {
    $probeCalls++;
    if ($probeCalls === 1) {
        return ['ok' => true, 'status' => 307, 'body' => '', 'cookies' => [$cookie('__arcsjs', 'p')],
            'headers' => ['Location: ' . $url]];
    }
    return ['ok' => true, 'status' => 200, 'headers' => [], 'cookies' => [],
        'body' => json_encode([['error' => false, 'data' => ['enablemobilewebservice' => 0, 'typeoflogin' => 1]]])];
};
$probed = hamiad_mobile_probe($probe307);
check($probed['answered'] === true && $probed['enablemobilewebservice'] === 0 && $probeCalls === 2, 'probe follows Arvan 307');

// An unanswered probe is retried after an hour, not a day.
hamiad_keepalive_write(hamiad_mobile_probe_path(), ['at' => date(DATE_ATOM, time() - 4000), 'answered' => false]);
check(hamiad_mobile_probe_due(time()), 'unanswered probe retries hourly');
hamiad_keepalive_write(hamiad_mobile_probe_path(), ['at' => date(DATE_ATOM, time() - 4000), 'answered' => true]);
check(!hamiad_mobile_probe_due(time()), 'answered probe waits a day');

echo "HAMIAD_KEEPALIVE_TESTS_OK\n";
