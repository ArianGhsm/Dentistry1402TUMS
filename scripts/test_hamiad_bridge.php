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

// Regression: Moodle completes successful POST via a testsession redirect
// before /my/ can be used. Follow only allowlisted Moodle URLs, preserving
// cookies from each hop and never treating the login page as success.
$hops = [];
$loginRedirect = static function(string $method, string $url, array $options) use (&$hops): array {
    $hops[] = ['url'=>$url, 'cookieHeader'=>hamiad_build_cookie_header($options['cookies'])];
    if (count($hops) === 1) {
        return [
            'ok'=>true, 'status'=>303, 'headers'=>['Location: /my/'],
            'cookies'=>[['name'=>'MoodleSession', 'value'=>'authenticated']],
            'body'=>'',
        ];
    }
    return [
        'ok'=>true, 'status'=>200, 'headers'=>[],
        'cookies'=>[['name'=>'waf','value'=>'verified']],
        'body'=>'<a href="/login/logout.php?sesskey=123">خروج</a>',
    ];
};
$loginCookies = [['name'=>'MoodleSession', 'value'=>'initial']];
check(hamiad_complete_login_handoff(
    HAMIAD_LOGIN . '?testsession=405', $loginCookies, $loginRedirect
), 'Completes Moodle testsession handoff');
check(count($hops) === 2, 'Follows exactly the valid testsession and dashboard');
check(str_contains($hops[0]['url'], 'testsession=405'), 'Uses provider-specified testsession location');
check(str_contains($hops[1]['cookieHeader'], 'MoodleSession=authenticated'), 'Carries updated session through redirect');
check(str_contains(hamiad_build_cookie_header($loginCookies), 'waf=verified'), 'Retains final WAF cookie');
check(hamiad_login_handoff_url('?testsession=123', HAMIAD_LOGIN) === HAMIAD_LOGIN . '?testsession=123', 'Resolves Moodle relative testsession');
check(hamiad_login_handoff_url('/my/', HAMIAD_LOGIN) === HAMIAD_HOME, 'Resolves Moodle absolute path');
foreach ([
    'http://lms.tums.ac.ir/login/index.php?testsession=405',
    'https://evil.example/login/index.php?testsession=405',
    'https://lms.tums.ac.ir:8443/login/index.php?testsession=405',
    'https://user@lms.tums.ac.ir/login/index.php?testsession=405',
    '//evil.example/login/index.php?testsession=405',
    '/mod/assign/view.php?id=32',
    "https://lms.tums.ac.ir/login/index.php\r\nLocation: https://evil.example",
] as $untrustedTarget) {
    check(hamiad_login_handoff_url($untrustedTarget, HAMIAD_LOGIN) === null, 'Reject untrusted handoff destination');
}
$externalFollowInvocations = 0;
$neverExternal = static function(string $method, string $url, array $options) use (&$externalFollowInvocations): array {
    $externalFollowInvocations++;
    return ['ok'=>false,'status'=>0,'headers'=>[],'cookies'=>[],'body'=>''];
};
$excludedCookies = [];
check(!hamiad_complete_login_handoff('https://evil.example/my/', $excludedCookies, $neverExternal), 'External redirect rejected');
check($externalFollowInvocations === 0, 'Never request external redirect');
$anonymousHandoff = static fn(string $method,string $url,array $options): array => [
    'ok'=>true,'status'=>200,'headers'=>[],'cookies'=>[],
    'body'=>'<form id="login"><input name="login_captcha_id0"></form>',
];
$anonymousCookies = [];
check(!hamiad_complete_login_handoff(HAMIAD_LOGIN.'?testsession=0',$anonymousCookies,$anonymousHandoff), 'Reject testsession returned to login page');
$loopCount = 0;
$loopingWaf = static function(string $method,string $url,array $options) use (&$loopCount): array {
    $loopCount++;
    return ['ok'=>true,'status'=>307,'headers'=>['Location: '.$url],
        'cookies'=>[],'body'=>''];
};
$wafCookies = [];
check(!hamiad_complete_login_handoff(HAMIAD_LOGIN.'?testsession=4',$wafCookies,$loopingWaf), 'Bound repeated WAF redirects');
check($loopCount === 8, 'At most eight provider handoff requests');


// A valid CAPTCHA may still be sent back to the login page with 303.
// The provider's reason is on the *followed* HTML response. Preserve it
// only in memory for strict allowlisted classification, never in logs.
$rejectedHtml = null;
$loginErrorPage = '<html><form id="login"><input name="login_captcha_id0"></form>'
    . '<div id="loginerrormessage">نام کاربری یا رمز عبور اشتباه است.</div></html>';
$followLoginFailure = static fn(string $method, string $url, array $options): array => [
    'ok'=>true,'status'=>200,'headers'=>[],'cookies'=>[],
    'body'=>$loginErrorPage,
];
$rejectedCookies = [];
$rejectedSteps = [];
check(!hamiad_complete_login_handoff(
    HAMIAD_LOGIN, $rejectedCookies, $followLoginFailure,
    $rejectedSteps, $rejectedHtml
), 'Login page on 303 follow-up must not authenticate');
check($rejectedHtml === $loginErrorPage, 'Return rejected page only to local login classifier');
check(str_contains(hamiad_login_failure_message($rejectedHtml), 'رمز عبور'),
    'Correctly report denied username/password');
check($rejectedSteps[0]['loginForm'] === true, 'Sanitized trace marks anonymous HTML');
check(!array_key_exists('body', $rejectedSteps[0]), 'Sanitized trace omits HTML');
check(!array_key_exists('errorMessage', $rejectedSteps[0]), 'Sanitized trace omits error text');
$captchaErrorHtml='<html><form id="login"><input name="login_captcha_id0"></form>'
    . '<div class="loginerrors">کد کپچا اشتباه است.</div></html>';
check(str_contains(hamiad_login_failure_message($captchaErrorHtml), 'کپچا'),
    'Custom Moodle theme captcha error classified');
$unknownSensitiveHtml='<html><form id="login"><input name="login_captcha_id0"></form>'
    . '<div class="alert-danger">private-provider-string-9935</div></html>';
check(!str_contains(hamiad_login_failure_message($unknownSensitiveHtml), 'private-provider-string-9935'),
    'Unknown remote message never echoed');
$successHtml=null;
$successCookies=[];
$successfulFlow=static fn(string $method, string $url, array $options): array => [
    'ok'=>true,'status'=>200,'headers'=>[],'cookies'=>[],
    'body'=>'<a href="/login/logout.php?sesskey=fake">خروج</a>',
];
$successSteps=[];
check(hamiad_complete_login_handoff(HAMIAD_HOME,$successCookies,$successfulFlow,$successSteps,$successHtml),
    'Authenticated page is accepted');
check($successHtml===null,'No failure page on successful login');

echo "HAMIAD_BRIDGE_TESTS_OK\n";
