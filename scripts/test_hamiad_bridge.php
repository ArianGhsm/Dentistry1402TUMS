<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/public_html/api/hamiad_bridge_service.php';

function check(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}
$captchaId = str_repeat('a', 32);
$markup = '<html><form id="login" method="post">'
    . '<input name="username" type="text">'
    . '<input name="password" type="password">'
    . '<input name="logintoken" value="' . str_repeat('c', 32) . '">'
    . '<input name="login_captcha_id0" value="' . $captchaId . '">'
    . '<img src="https://lms.tums.ac.ir/local/fic_form_fields/includes/captcha_img.php?captcha_id=' . $captchaId . '">'
    . '</form></html>';
$form = hamiad_parse_login_form($markup);
check($form['token'] === str_repeat('c', 32), 'Form token detection');
check($form['captchaId'] === $captchaId, 'Captcha ID detection');
check(hamiad_parse_login_form(str_replace('lms.tums.ac.ir', 'evil.example', $markup)) === [], 'Reject external captcha');
check(hamiad_parse_login_form(str_replace('value="' . $captchaId, 'value="other-id', $markup)) === [], 'Reject mismatched captcha');
check(hamiad_parse_login_form('<form id="login"><input name="username"></form>') === [], 'Fail closed missing tokens');
check(str_starts_with(HAMIAD_LOGIN, 'https://lms.tums.ac.ir/'), 'Fixed HTTPS origin');
$browser = file_get_contents(dirname(__DIR__) . '/public_html/hamiad/index.php');
check(!str_contains($browser, 'ARIANgh@'), 'No password hardcoded');
check(str_contains($browser, 'type="password"'), 'Password input');
check(str_contains($browser, 'value="') === false, 'No prefilled input values');
// Regression: the first GET after a valid POST may be an Arvan 307.
// Verification must preserve WAF cookies, retry the exact URL, and accept
// only a Moodle authenticated home (never a redirect or anonymous form).
$seenCookies = [];
$redirectThenHome = static function (string $method, string $url, array $options) use (&$seenCookies): array {
    $seenCookies[] = $options['cookies'];
    if (count($seenCookies) === 1) {
        return [
            'ok' => true, 'status' => 307,
            'headers' => ['Location: ' . $url],
            'cookies' => [['name' => 'arvan', 'value' => 'validated']],
            'body' => '',
        ];
    }
    return [
        'ok' => true, 'status' => 200, 'headers' => [],
        'cookies' => [['name' => 'MoodleSession', 'value' => 'new-session']],
        'body' => '<a href="/login/logout.php?sesskey=test">خروج</a>',
    ];
};
$verifiedCookies = [['name' => 'prior', 'value' => 'preserved']];
check(hamiad_verify_home($verifiedCookies, $redirectThenHome), 'Arvan 307 then authenticated Moodle home');
check(count($seenCookies) === 2, '307 retries exactly once before verified 200');
check(str_contains(hamiad_build_cookie_header($seenCookies[1]), 'arvan=validated'), 'Retains WAF cookie during retry');
check(str_contains(hamiad_build_cookie_header($verifiedCookies), 'MoodleSession=new-session'), 'Retains refreshed Moodle session');
$anonymousRedirect = static fn(string $method, string $url, array $options): array => [
    'ok' => true, 'status' => 303, 'headers' => ['Location: ' . HAMIAD_LOGIN],
    'cookies' => [], 'body' => '',
];
$anonymousCookies = [];
check(!hamiad_verify_home($anonymousCookies, $anonymousRedirect), 'Fail closed on redirect to login');
$anonymousHtml = static fn(string $method, string $url, array $options): array => [
    'ok' => true, 'status' => 200, 'headers' => [], 'cookies' => [],
    'body' => '<form id="login"><input name="login_captcha_id0"></form>',
];
check(!hamiad_verify_home($anonymousCookies, $anonymousHtml), 'Fail closed on anonymous login HTML');
echo "HAMIAD_BRIDGE_TESTS_OK\n";
