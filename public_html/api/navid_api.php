<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/navid_service.php';

$action = dent_request_action();

if ($action === 'feed') {
    $user = dent_require_user();
    $payload = navid_feed_payload(!empty($user['isOwner']) || (($user['role'] ?? '') === 'owner'));
    dent_json_response([
        'success' => true,
        'data' => $payload['data'],
    ]);
}

if ($action === 'ownerStatus') {
    dent_require_owner();
    $store = navid_load_store();
    dent_json_response([
        'success' => true,
        'ownerStatus' => navid_build_owner_status($store),
    ]);
}

if ($action === 'saveConfig') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ذخیره تنظیمات نوید نامعتبر است.', 405);
    }
    dent_require_owner();

    $result = navid_update_config([
        'enabled' => ($_POST['enabled'] ?? '1') === '1',
        'loginUrl' => (string) ($_POST['loginUrl'] ?? ''),
        'syncIntervalMinutes' => (int) ($_POST['syncIntervalMinutes'] ?? 30),
        'captchaStrategy' => (string) ($_POST['captchaStrategy'] ?? 'python_ocr'),
        'username' => (string) ($_POST['username'] ?? ''),
        'password' => (string) ($_POST['password'] ?? ''),
    ]);

    dent_json_response([
        'success' => true,
        'message' => (string) ($result['message'] ?? 'تنظیمات ذخیره شد.'),
        'ownerStatus' => $result['ownerStatus'] ?? navid_build_owner_status(navid_load_store()),
    ]);
}

if ($action === 'syncNow') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد همگام‌سازی نوید نامعتبر است.', 405);
    }
    dent_require_owner();

    $result = navid_sync(true);
    dent_json_response([
        'success' => !empty($result['success']),
        'status' => (string) ($result['status'] ?? ''),
        'message' => (string) ($result['message'] ?? ''),
        'summary' => $result['summary'] ?? null,
        'ownerStatus' => $result['ownerStatus'] ?? navid_build_owner_status(navid_load_store()),
    ]);
}

if ($action === 'captchaChallenge') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد دریافت کپچا نامعتبر است.', 405);
    }
    dent_require_owner();

    $result = navid_create_captcha_challenge();
    dent_json_response([
        'success' => true,
        'message' => (string) ($result['message'] ?? ''),
        'captchaDataUri' => (string) ($result['captchaDataUri'] ?? ''),
        'expiresAt' => (string) ($result['expiresAt'] ?? ''),
        'ownerStatus' => $result['ownerStatus'] ?? navid_build_owner_status(navid_load_store()),
    ]);
}

if ($action === 'completeReconnect') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد اتصال مجدد نوید نامعتبر است.', 405);
    }
    dent_require_owner();

    $captchaCode = (string) ($_POST['captchaCode'] ?? '');
    $result = navid_complete_captcha_challenge($captchaCode);
    dent_json_response([
        'success' => true,
        'message' => (string) ($result['message'] ?? ''),
        'ownerStatus' => $result['ownerStatus'] ?? navid_build_owner_status(navid_load_store()),
    ]);
}

if ($action === 'clearSession') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد پاک‌سازی نشست نوید نامعتبر است.', 405);
    }
    dent_require_owner();

    $store = navid_load_store();
    navid_clear_session_cookies($store);
    $store['state']['requiresReconnect'] = true;
    $store['state']['lastError'] = 'نشست نوید دستی پاک شد.';
    navid_save_store($store);

    dent_json_response([
        'success' => true,
        'message' => 'نشست نوید پاک شد.',
        'ownerStatus' => navid_build_owner_status($store),
    ]);
}

dent_error('درخواست نامعتبر است.', 404);

