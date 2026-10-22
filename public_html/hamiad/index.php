<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/bootstrap.php';
require_once __DIR__ . '/../api/auth_store.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; script-src 'self'; style-src 'self'; connect-src 'self'; font-src 'self' data:; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

$viewer = dent_current_user();
if ($viewer === null) {
    header('Location: /account/', true, 302);
    exit;
}
if (($viewer['role'] ?? '') !== 'owner') {
    http_response_code(403);
    exit('دسترسی به این صفحه فقط برای مالک سامانه مجاز است.');
}
$csrf = dent_auth_session_csrf_token();
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="theme-color" content="#f3f6fa">
    <title>ورود به همیاد | دنت‌یار</title>
    <link rel="stylesheet" href="/assets/site/styles/core.css?v=20261022-hamiad1">
    <link rel="stylesheet" href="/assets/site/styles/bot-link.css?v=20261022-hamiad1">
    <link rel="stylesheet" href="/assets/site/styles/hamiad-bridge.css?v=20261022-hamiad1">
</head>
<body class="bot-link-page">
<main class="bot-link-shell">
    <header class="bot-link-header">
        <span class="bot-link-kicker">🔐 اتصال امن مالک</span>
        <h1>ورود به همیاد</h1>
        <p>سامانهٔ آموزش الکترونیکی دانشگاه علوم پزشکی تهران</p>
    </header>

    <section class="bot-link-panel" aria-live="polite">
        <div id="hamiad-form-panel" class="bot-link-state">
            <div class="hamiad-summary">
                <strong>اتصال حساب دانشگاه</strong>
                <p>نام کاربری و رمز سیپاد خودت را وارد کن. ورود فقط بعد از انتخاب دکمهٔ «ورود به همیاد» انجام می‌شود.</p>
            </div>
            <form id="hamiad-form" class="hamiad-form" method="post" autocomplete="off">
                <label for="hamiad-username">نام کاربری سیپاد</label>
                <input id="hamiad-username" name="username" type="text" inputmode="numeric" dir="ltr" autocomplete="off" required maxlength="80" placeholder="شماره دانشجویی">
                <label for="hamiad-password">رمز عبور</label>
                <input id="hamiad-password" name="password" type="password" dir="ltr" autocomplete="off" required maxlength="256" placeholder="رمز ورود سیپاد">
                <div class="hamiad-captcha-label">
                    <label for="hamiad-captcha">عبارت امنیتی</label>
                    <button id="hamiad-refresh" class="hamiad-refresh" type="button">↻ تصویر جدید</button>
                </div>
                <div class="hamiad-captcha-image">
                    <img id="hamiad-image" alt="کپچای ورود همیاد" hidden>
                    <span id="hamiad-image-loading">در حال دریافت تصویر امنیتی…</span>
                </div>
                <input id="hamiad-captcha" name="captcha" type="text" dir="ltr" autocomplete="off" required maxlength="10" placeholder="کد تصویر">
                <button id="hamiad-submit" class="bot-link-primary" type="submit" disabled>ورود به همیاد</button>
            </form>
            <p id="hamiad-feedback" class="hamiad-feedback" role="status"></p>
        </div>
        <div id="hamiad-success-panel" class="bot-link-state" hidden>
            <strong>✅ اتصال تأیید شد</strong>
            <p>نشست همیاد به‌صورت رمزنگاری‌شده برای بررسی درس‌ها ذخیره شد. اطلاعات ورود در این صفحه نگه‌داری نمی‌شود.</p>
            <a class="bot-link-secondary" href="/app/">بازگشت به دنتیستری</a>
        </div>
    </section>
    <p class="bot-link-security">این صفحه فقط برای مالک سامانه است. رمز عبور در پایگاه داده ذخیره نمی‌شود و داخل لینک قرار نمی‌گیرد.</p>
</main>
<div id="hamiad-config" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" hidden></div>
<script src="/assets/site/scripts/hamiad-bridge.js?v=20261022-hamiad1"></script>
</body>
</html>
