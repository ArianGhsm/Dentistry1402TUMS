<?php
declare(strict_types=1);
function check_hamiad(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
}
$root = sys_get_temp_dir() . '/hamiad-bot-test-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
putenv('DENT_STORAGE_ROOT=' . $root);
putenv('DENT_SERVER_ONLY_ROOT=' . $root . '/server-only');
try {
    require_once dirname(__DIR__) . '/public_html/api/hamiad_bot_login.php';
    try {
        hamiad_bot_login_path('invalid');
        throw new RuntimeException('Invalid platform accepted');
    } catch (RuntimeException $error) {
        check_hamiad(str_contains($error->getMessage(),'معتبر نیست'), 'Reject invalid platform');
    }
    $ref = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    check_hamiad(strlen($ref) === 32, 'Valid challenge reference');
    $path = hamiad_bot_login_path('telegram');
    $challenge = [
        'token'=>str_repeat('a',32),
        'captchaId'=>str_repeat('b',32),
        'cookies'=>[['name'=>'test-only','value'=>'opaque-value']],
        'issuedAt'=>time(),
    ];
    $data = [
        'schemaVersion'=>1,
        'refHash'=>hash('sha256',$ref),
        'expiresAt'=>date(DATE_ATOM,time()+260),
        'sealed'=>hamiad_seal($challenge),
    ];
    dent_write_json_file($path,$data);
    $raw=file_get_contents($path);
    check_hamiad(!str_contains($raw, $challenge['token']), 'No token plaintext');
    check_hamiad(!str_contains($raw, 'opaque-value'), 'No cookie plaintext');
    try {
        hamiad_bot_login_consume('telegram',str_repeat('X',32));
        throw new RuntimeException('Wrong reference accepted');
    } catch (RuntimeException $error) {
        check_hamiad(str_contains($error->getMessage(),'جایگزین'), 'Wrong ref fail-closed');
    }
    check_hamiad(file_exists($path), 'Wrong ref does not consume');
    $loaded=hamiad_bot_login_consume('telegram',$ref);
    check_hamiad($loaded['captchaId'] === $challenge['captchaId'], 'Consumes and decrypts correct challenge');
    check_hamiad(!file_exists($path), 'Single-use challenge removed');
    try {
        hamiad_bot_login_consume('telegram',$ref);
        throw new RuntimeException('Replay accepted');
    } catch (RuntimeException $error) {
        check_hamiad(str_contains($error->getMessage(),'جایگزین'), 'Replay rejected');
    }
    $data['expiresAt']=date(DATE_ATOM,time()-15);
    dent_write_json_file($path,$data);
    try {
        hamiad_bot_login_consume('telegram',$ref);
        throw new RuntimeException('Expired CAPTCHA accepted');
    } catch (RuntimeException $error) {
        check_hamiad(str_contains($error->getMessage(),'اعتبار کپچا'), 'Expired rejected');
    }
    check_hamiad(!file_exists($path), 'Expired challenge consumed');
    $badCaptchaHtml = '<html><div id="loginerrormessage">عبارت‌امنیتی نامعتبر است.</div></html>';
    check_hamiad(str_contains(hamiad_login_failure_message($badCaptchaHtml), 'کپچا'), 'Provider invalid captcha explained');
    $badPasswordHtml = '<html><div id="loginerrormessage">نام کاربری یا کلمه عبور اشتباه است.</div></html>';
    check_hamiad(str_contains(hamiad_login_failure_message($badPasswordHtml), 'رمز عبور'), 'Provider password error explained');
    check_hamiad(!str_contains(hamiad_login_failure_message('<html><div id="loginerrormessage">REMOTE SECRET 123</div></html>'), 'REMOTE SECRET'), 'Unknown provider content never leaked');
    $balePath=hamiad_bot_login_path('bale');
    check_hamiad($path !== $balePath, 'Separate Telegram/Bale challenges');
    $site=file_get_contents(dirname(__DIR__) . '/public_html/api/bot_hamiad.php');
    check_hamiad(str_contains($site,'dent_bot_hamiad_owner_only($user);'), 'Canonical owner guard');
    echo "HAMIAD_BOT_LOGIN_CONTRACT_OK\n";
} finally {
    foreach (@scandir($root.'/hamiad') ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') @unlink($root.'/hamiad/'.$entry);
    }
    @rmdir($root.'/hamiad');
    @rmdir($root);
}
