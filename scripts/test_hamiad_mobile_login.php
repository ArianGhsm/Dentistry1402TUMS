<?php
declare(strict_types=1);

$root = sys_get_temp_dir() . '/dent-hamiad-mobile-' . bin2hex(random_bytes(6));
mkdir($root . '/hamiad', 0700, true);
putenv('DENT_STORAGE_ROOT=' . $root);
$_ENV['DENT_STORAGE_ROOT'] = $root;

require_once dirname(__DIR__) . '/public_html/api/hamiad_mobile_login.php';

function check(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

check(str_starts_with(dent_storage_path('hamiad/x.json'), $root), 'isolated storage');
check(hamiad_mobile_relogin(static fn () => [])['reason'] === 'no_credentials', 'nothing to do without credentials');
hamiad_credentials_save('40112345603', 'correct-horse');

/**
 * A tiny fake Hamiad: Arvan 307 on first token POST, token.php, the two web
 * service calls, autologin.php → 303 to /my/ setting MoodleSession, /my/.
 */
function fake_hamiad(array &$log, string $password = 'correct-horse', bool $tokenValid = true): callable
{
    $arvanDone = false;
    return static function (string $method, string $url, array $options) use (&$log, &$arvanDone, $password, $tokenValid): array {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $form = (array) ($options['form'] ?? []);
        $log[] = $path . (isset($form['wsfunction']) ? '#' . $form['wsfunction'] : '');
        $json = static fn (array $data) => ['ok' => true, 'status' => 200, 'headers' => [], 'cookies' => [], 'body' => json_encode($data)];
        if ($path === '/login/token.php') {
            if (!$arvanDone) {
                $arvanDone = true;
                return ['ok' => true, 'status' => 307, 'body' => '', 'headers' => ['Location: ' . $url],
                    'cookies' => [['name' => '__arcsjs', 'value' => 'a', 'domain' => 'lms.tums.ac.ir', 'path' => '/']]];
            }
            check(str_contains((string) ($options['userAgent'] ?? ''), 'MoodleMobile'), 'token request uses the app user agent');
            return ($form['password'] ?? '') === $password
                ? $json(['token' => str_repeat('t', 32), 'privatetoken' => str_repeat('p', 64)])
                : $json(['error' => 'Invalid login', 'errorcode' => 'invalidlogin']);
        }
        if ($path === '/webservice/rest/server.php') {
            if (!$tokenValid) {
                return $json(['exception' => 'moodle_exception', 'errorcode' => 'invalidtoken']);
            }
            if (($form['wsfunction'] ?? '') === 'core_webservice_get_site_info') {
                return $json(['userid' => 4242, 'sitename' => 'x']);
            }
            if (($form['wsfunction'] ?? '') === 'tool_mobile_get_autologin_key') {
                check(($form['privatetoken'] ?? '') === str_repeat('p', 64), 'private token is sent');
                return $json(['key' => 'k123', 'autologinurl' => 'https://lms.tums.ac.ir/admin/tool/mobile/autologin.php']);
            }
        }
        if ($path === '/admin/tool/mobile/autologin.php') {
            return ['ok' => true, 'status' => 303, 'body' => '', 'headers' => ['Location: https://lms.tums.ac.ir/my/'],
                'cookies' => [['name' => 'MoodleSession', 'value' => 'from-token', 'domain' => 'lms.tums.ac.ir', 'path' => '/']]];
        }
        if ($path === '/my/') {
            $names = array_map(static fn ($c) => $c['name'] . '=' . $c['value'], (array) ($options['cookies'] ?? []));
            return in_array('MoodleSession=from-token', $names, true)
                ? ['ok' => true, 'status' => 200, 'headers' => [], 'cookies' => [], 'body' => '<a href="/login/logout.php?sesskey=s">خروج</a>']
                : ['ok' => true, 'status' => 303, 'headers' => ['Location: https://lms.tums.ac.ir/login/index.php'], 'cookies' => [], 'body' => ''];
        }
        return ['ok' => false, 'status' => 0, 'headers' => [], 'cookies' => [], 'body' => ''];
    };
}

// Fresh: token.php (through Arvan 307) → site info → autologin key → session.
$log = [];
$first = hamiad_mobile_relogin(fake_hamiad($log), 1_800_000_000);
check($first['ok'] === true && $first['reason'] === 'connected', 'CAPTCHA-free login connects');
$cookieValues = array_map(static fn ($c) => $c['value'], hamiad_load_cookies());
check(in_array('from-token', $cookieValues, true), 'session cookies from autologin are saved');
check(is_file(hamiad_mobile_token_path()), 'token is stored');
check(!str_contains((string) file_get_contents(hamiad_mobile_token_path()), str_repeat('t', 32)), 'token is sealed at rest');
check(!str_contains((string) file_get_contents(hamiad_session_path()), 'correct-horse'), 'password is never stored in the session');

// Throttled within 10 minutes, unless forced (bot button).
check(hamiad_mobile_relogin(fake_hamiad($log), 1_800_000_100)['reason'] === 'throttled', 'automatic attempts are throttled');

// Later: the stored token is reused; the password is not sent again.
$log = [];
$second = hamiad_mobile_relogin(fake_hamiad($log), 1_800_001_000);
check($second['ok'] === true && !in_array('/login/token.php', $log, true), 'stored token is reused without the password');

// Token revoked: a new token is fetched with the saved credentials.
$log = [];
$third = hamiad_mobile_relogin(fake_hamiad($log, 'correct-horse', false), 1_800_002_000);
check(in_array('/login/token.php', $log, true), 'invalid token triggers a fresh token request');

// Wrong password: blocked until credentials are re-saved (no lockout by retries).
@unlink(hamiad_mobile_token_path());
$log = [];
$rejected = hamiad_mobile_relogin(fake_hamiad($log, 'other-password'), 1_800_003_000, true);
check($rejected['reason'] === 'credentials_rejected', 'rejected password is reported');
$log = [];
$again = hamiad_mobile_relogin(fake_hamiad($log, 'other-password'), 1_800_009_000, true);
check($again['reason'] === 'credentials_rejected' && $log === [], 'no further password attempts until credentials change');
sleep(1);
hamiad_credentials_save('40112345603', 'correct-horse');
$log = [];
$fixed = hamiad_mobile_relogin(fake_hamiad($log), 1_800_010_000, true);
check($fixed['ok'] === true, 're-saved credentials unblock the automatic login');

// Redirects off Hamiad are refused.
check(hamiad_mobile_next_url('https://evil.example/my/', HAMIAD_BASE) === null, 'foreign redirect refused');
check(hamiad_mobile_next_url('/my/', HAMIAD_BASE) === 'https://lms.tums.ac.ir/my/', 'relative redirect resolved');

// A token issued for other saved credentials is never reused.
$log = [];
check(hamiad_mobile_relogin(fake_hamiad($log), 1_800_020_000, true)['ok'] === true, 'baseline connect');
$sealed = dent_read_json_file(hamiad_mobile_token_path(), []);
$tokens = hamiad_unseal($sealed['sealed'] ?? null);
$tokens['credentialId'] = hash('sha256', "someone-else\n2020-01-01");
dent_write_json_file(hamiad_mobile_token_path(), ['schemaVersion' => 1, 'sealed' => hamiad_seal($tokens)]);
$log = [];
hamiad_mobile_relogin(fake_hamiad($log), 1_800_030_000, true);
check(in_array('/login/token.php', $log, true), 'foreign-credential token is discarded and re-issued');
sleep(1);
hamiad_credentials_save('40199999999', 'correct-horse');
check(!is_file(hamiad_mobile_token_path()), 'saving credentials invalidates the cached token');

// Attempts are serialised: a held lock makes a concurrent attempt wait.
if (function_exists('pcntl_fork')) {
    $lockHandle = fopen(dent_storage_path('hamiad/mobile-login.lock'), 'c');
    check(flock($lockHandle, LOCK_EX), 'test holds the relogin lock');
    $pid = pcntl_fork();
    if ($pid === 0) {
        $childLog = [];
        $start = microtime(true);
        hamiad_mobile_relogin(fake_hamiad($childLog), 1_800_040_000, true);
        exit(microtime(true) - $start >= 0.25 ? 0 : 1);
    }
    usleep(400000);
    flock($lockHandle, LOCK_UN);
    pcntl_waitpid($pid, $childStatus);
    check(pcntl_wexitstatus($childStatus) === 0, 'a concurrent relogin waits for the lock');
}

// Re-saved credentials are tried at once, without waiting for the throttle.
sleep(1);
hamiad_credentials_save('40112345603', 'correct-horse');
$log = [];
check(hamiad_mobile_relogin(fake_hamiad($log), 1_800_050_000)['ok'] === true, 'baseline after re-save');
sleep(1);
hamiad_credentials_save('40112345603', 'correct-horse');
$log = [];
check(hamiad_mobile_relogin(fake_hamiad($log), 1_800_050_060)['reason'] !== 'throttled', 'new credentials skip the throttle');
$log = [];
check(hamiad_mobile_relogin(fake_hamiad($log), 1_800_050_120)['reason'] === 'throttled', 'same credentials stay throttled');

echo "HAMIAD_MOBILE_LOGIN_TESTS_OK\n";
