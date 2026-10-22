<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/hamiad_bridge_service.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');

dent_require_owner();
if (dent_request_method() !== 'POST') {
    dent_error('درخواست نامعتبر است.', 405);
}
dent_auth_session_require_csrf();

$action = trim((string) ($_GET['action'] ?? ''));
try {
    if ($action === 'challenge') {
        dent_json_response(['success' => true] + hamiad_challenge());
    }
    if ($action === 'login') {
        dent_json_response(['success' => true] + hamiad_login(
            trim((string) ($_POST['username'] ?? '')),
            (string) ($_POST['password'] ?? ''),
            strtoupper(trim((string) ($_POST['captcha'] ?? '')))
        ));
    }
    dent_error('درخواست نامعتبر است.', 404);
} catch (RuntimeException $error) {
    dent_error($error->getMessage(), 422);
}
