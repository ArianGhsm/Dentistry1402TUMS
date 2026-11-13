<?php
declare(strict_types=1);

$root = sys_get_temp_dir() . '/dent-hamiad-egress-' . bin2hex(random_bytes(6));
mkdir($root . '/hamiad', 0700, true);
putenv('DENT_STORAGE_ROOT=' . $root);
$_ENV['DENT_STORAGE_ROOT'] = $root;
putenv('DENT_HAMIAD_EGRESS_IPS=10.0.0.1,10.0.0.2,10.0.0.3');
$_ENV['DENT_HAMIAD_EGRESS_IPS'] = '10.0.0.1,10.0.0.2,10.0.0.3';

require_once dirname(__DIR__) . '/public_html/api/hamiad_bridge_service.php';

function check(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

check(hamiad_egress_ips() === ['10.0.0.1', '10.0.0.2', '10.0.0.3'], 'configured extra addresses');

$fake = static function (array $byPath) use (&$seen) {
    return static function (string $method, string $url, array $options) use ($byPath, &$seen) {
        $path = (string) ($options['sourceIp'] ?? 'default');
        $seen[] = $path;
        return $byPath[$path] ?? ['ok' => true, 'status' => 200, 'errno' => 0, 'body' => 'ok', 'headers' => [], 'cookies' => []];
    };
};
$down = ['ok' => false, 'status' => 0, 'errno' => 7, 'body' => '', 'headers' => [], 'cookies' => []];
$blocked = ['ok' => true, 'status' => 403, 'errno' => 0, 'body' => '', 'headers' => [], 'cookies' => []];
$timeout = ['ok' => false, 'status' => 0, 'errno' => 28, 'body' => '', 'headers' => [], 'cookies' => []];
$gone = ['ok' => false, 'status' => 0, 'errno' => 45, 'body' => '', 'headers' => [], 'cookies' => []];
$sticky = static fn () => (string) (dent_read_json_file(hamiad_egress_state_path(), [])['ip'] ?? '');

// Normal: the default route is used; extra addresses are untouched.
$seen = [];
$r = hamiad_http_request('GET', HAMIAD_HOME, [], $fake([]));
check($r['sourceIp'] === 'default' && $seen === ['default'], 'default route is primary');

// Default route blocked → first extra address, which becomes sticky.
$seen = [];
$r = hamiad_http_request('GET', HAMIAD_HOME, [], $fake(['default' => $blocked]));
check($r['sourceIp'] === '10.0.0.1' && $seen === ['default', '10.0.0.1'], 'fails over to an extra address');
check($sticky() === '10.0.0.1', 'the working extra address is remembered');
$seen = [];
hamiad_http_request('GET', HAMIAD_HOME, [], $fake([]));
check($seen === ['10.0.0.1'], 'sticky extra address is used next');

// The extra address's lease ends (address gone): back to the default route.
$seen = [];
$r = hamiad_http_request('GET', HAMIAD_HOME, [], $fake(['10.0.0.1' => $gone]));
check($r['sourceIp'] === 'default' && $seen === ['10.0.0.1', 'default'], 'vanished address is skipped');
check($sticky() === 'default', 'default route is sticky again');

// All extra addresses gone and default fine: never even noticed.
$seen = [];
$r = hamiad_http_request('GET', HAMIAD_HOME, [], $fake(['10.0.0.1' => $gone, '10.0.0.2' => $gone, '10.0.0.3' => $gone]));
check($seen === ['default'] && !empty($r['ok']), 'expired extras cost nothing while the default route works');

// Connect failure on default → extra address (safe even for a login POST).
$seen = [];
$r = hamiad_http_request('POST', HAMIAD_LOGIN, ['form' => ['a' => 'b']], $fake(['default' => $down]));
check($r['sourceIp'] === '10.0.0.1', 'unsent POST fails over');

// A POST that may have reached Hamiad (timeout) is never resent.
$seen = [];
$r = hamiad_http_request('POST', HAMIAD_LOGIN, ['form' => ['a' => 'b']], $fake(['10.0.0.1' => $timeout]));
check($seen === ['10.0.0.1'] && empty($r['ok']), 'possibly-delivered POST is not resent');

// Explicit sourceIp is honoured as-is.
$seen = [];
hamiad_http_request('GET', HAMIAD_HOME, ['sourceIp' => '10.0.0.3'], $fake([]));
check($seen === ['10.0.0.3'], 'explicit source address is used directly');

// A damaged egress state file never blocks Hamiad: default route first.
file_put_contents(hamiad_egress_state_path(), "{not json");
$seen = [];
$r = hamiad_http_request('GET', HAMIAD_HOME, [], $fake([]));
check(!empty($r['ok']) && $seen[0] === 'default', 'corrupt egress state falls back to the default route');

echo "HAMIAD_EGRESS_TESTS_OK\n";
