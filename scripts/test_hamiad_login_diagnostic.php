<?php
declare(strict_types=1);

function diagnostic_check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
}
$root = sys_get_temp_dir() . '/dent-hamiad-diagnostic-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
putenv('DENT_STORAGE_ROOT=' . $root);
putenv('DENT_SERVER_ONLY_ROOT=' . $root . '/server-only');
try {
    require_once dirname(__DIR__) . '/public_html/api/hamiad_bridge_service.php';
    $secret = 'TEST_FAKE_SECRET_998877';
    $cookies = [['name'=>'MoodleSession','value'=>$secret]];
    $events = [];
    $fake = static fn(string $method, string $url, array $options): array => [
        'ok'=>true, 'status'=>200, 'headers'=>[], 'cookies'=>[],
        'body'=>'<form id="login"><input name="login_captcha_id0"></form>',
    ];
    diagnostic_check(
        hamiad_complete_login_handoff(HAMIAD_LOGIN.'?testsession=998877', $cookies, $fake, $events) === false,
        'Anonymous Moodle handoff rejected'
    );
    diagnostic_check(count($events)===1, 'One sanitized trace event');
    diagnostic_check($events[0]['path']==='login', 'Classifies login page');
    diagnostic_check($events[0]['testsession']===true, 'Detects testsession without recording value');
    diagnostic_check($events[0]['loginForm']===true, 'Detects anonymous response');
    diagnostic_check(str_contains(hamiad_handoff_failure_message($events), 'کوکی'), 'Anonymous login form gets actionable error');
    diagnostic_check(str_contains(hamiad_handoff_failure_message([['step'=>'redirect_limit']]), 'پیاپی'), 'WAF redirect limit gets specific error');
    diagnostic_check(str_contains(hamiad_handoff_failure_message([['step'=>'unsafe_location']]), 'غیرمنتظره'), 'Unsafe URL failure classified');
    diagnostic_check(str_contains(hamiad_handoff_failure_message([['step'=>'follow','transportOk'=>false]]), 'ارتباط'), 'Network failure classified');
    hamiad_record_login_diagnostic('handoff_failed',[
        'postStatus'=>303, 'postPath'=>'login', 'postSessionCookie'=>true,
        'hops'=>array_merge($events, [[
            'step'=>'follow',
            'path'=>'https://evil.example/'.$secret,
            'nextPath'=>$secret,
            'httpStatus'=>307,
            'cookieContent'=>$secret,
        ]]),
        'username'=>$secret, 'password'=>$secret, 'rawHtml'=>$secret,
    ]);
    $path = dent_storage_path('hamiad/last-login-diagnostic.json');
    diagnostic_check(file_exists($path), 'Private record persisted');
    diagnostic_check((fileperms($path) & 0777) === 0600, '0600 permissions');
    $raw = file_get_contents($path);
    diagnostic_check(!str_contains($raw, $secret), 'No credentials, cookies or arbitrary trace strings');
    diagnostic_check(!str_contains($raw, '998877'), 'No testsession identifier');
    diagnostic_check(!str_contains($raw, 'evil.example'), 'No untrusted raw URL');
    $record = json_decode($raw, true);
    diagnostic_check($record['phase']==='handoff_failed', 'Phase matches');
    diagnostic_check($record['postStatus']===303, 'HTTP status matches');
    diagnostic_check($record['hops'][0]['testsession']===true, 'Safe boolean preserved');
    diagnostic_check($record['hops'][1]['path']==='other', 'Non-allowlisted path suppressed');
    echo "HAMIAD_DIAGNOSTIC_CONTRACT_OK\n";
} finally {
    foreach (@scandir($root.'/hamiad') ?: [] as $entry) {
        if (!in_array($entry,['.','..'],true)) @unlink($root.'/hamiad/'.$entry);
    }
    @rmdir($root.'/hamiad');
    @rmdir($root);
}
