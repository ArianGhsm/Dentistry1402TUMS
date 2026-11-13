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

echo "HAMIAD_KEEPALIVE_TESTS_OK\n";
