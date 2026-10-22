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
echo "HAMIAD_BRIDGE_TESTS_OK\n";
