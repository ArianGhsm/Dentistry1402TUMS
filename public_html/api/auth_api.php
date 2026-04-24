<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/grades_store.php';

$action = dent_request_action();

if ($action === 'login') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ورود نامعتبر است.', 405);
    }

    $studentNumber = dent_normalize_student_number($_POST['studentNumber'] ?? $_POST['username'] ?? '');
    $password = dent_normalize_digits($_POST['password'] ?? '');

    if ($studentNumber === '' || $password === '') {
        dent_error('شماره دانشجویی و رمز عبور را وارد کن.', 422);
    }

    $user = dent_verify_credentials($studentNumber, $password);
    if ($user === null) {
        dent_error('شماره دانشجویی یا رمز عبور اشتباه است.', 401, ['loggedOut' => true]);
    }

    dent_json_response([
        'success' => true,
        'loggedIn' => true,
        'status' => dent_auth_status($user),
        'user' => dent_login_user($user),
    ]);
}

if ($action === 'logout') {
    dent_logout_user();

    dent_json_response([
        'success' => true,
        'loggedIn' => false,
        'status' => 'logged-out',
    ]);
}

if ($action === 'me') {
    $user = dent_current_user();

    if ($user === null) {
        dent_json_response([
            'success' => true,
            'loggedIn' => false,
            'status' => 'logged-out',
        ]);
    }

    dent_json_response([
        'success' => true,
        'loggedIn' => true,
        'status' => dent_auth_status($user),
        'user' => dent_public_user($user),
    ]);
}

if ($action === 'updateProfile') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد به‌روزرسانی پروفایل نامعتبر است.', 405);
    }

    $user = dent_require_user();
    $profileInput = [
        'contactHandle' => $_POST['contactHandle'] ?? '',
        'focusArea' => $_POST['focusArea'] ?? '',
    ];

    if (array_key_exists('about', $_POST) || array_key_exists('bio', $_POST)) {
        $profileInput['about'] = $_POST['about'] ?? ($_POST['bio'] ?? '');
        $profileInput['bio'] = $_POST['bio'] ?? ($_POST['about'] ?? '');
    }

    if (array_key_exists('avatarUrl', $_POST)) {
        $profileInput['avatarUrl'] = $_POST['avatarUrl'];
    }

    $updatedUser = dent_update_profile($user, $profileInput);

    dent_json_response([
        'success' => true,
        'user' => dent_public_user($updatedUser),
        'message' => 'اطلاعات حساب کاربری ذخیره شد.',
    ]);
}

if ($action === 'changePassword') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تغییر رمز نامعتبر است.', 405);
    }

    $user = dent_require_user();
    $updatedUser = dent_change_user_password(
        $user,
        (string) ($_POST['currentPassword'] ?? ''),
        (string) ($_POST['newPassword'] ?? '')
    );

    dent_json_response([
        'success' => true,
        'user' => dent_public_user($updatedUser),
        'message' => 'رمز عبور با موفقیت تغییر کرد.',
    ]);
}

if ($action === 'requestLoginOtp') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد درخواست کد ورود نامعتبر است.', 405);
    }

    $phoneNumber = (string) ($_POST['phoneNumber'] ?? '');
    $result = dent_request_login_otp($phoneNumber);

    dent_json_response([
        'success' => true,
        'message' => 'کد ورود پیامکی ارسال شد.',
        'phoneMasked' => (string) ($result['phoneMasked'] ?? ''),
        'cooldownSeconds' => (int) ($result['cooldownSeconds'] ?? 0),
        'expiresInSeconds' => (int) ($result['expiresInSeconds'] ?? 0),
    ]);
}

if ($action === 'verifyLoginOtp') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تایید کد ورود نامعتبر است.', 405);
    }

    $phoneNumber = (string) ($_POST['phoneNumber'] ?? '');
    $otpCode = (string) ($_POST['otpCode'] ?? ($_POST['code'] ?? ''));
    $loggedInUser = dent_verify_login_otp($phoneNumber, $otpCode);

    dent_json_response([
        'success' => true,
        'loggedIn' => true,
        'status' => 'logged-in',
        'user' => $loggedInUser,
        'message' => 'ورود با کد تایید انجام شد.',
    ]);
}

if ($action === 'requestPhoneEnrollOtp') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد درخواست تایید شماره نامعتبر است.', 405);
    }

    $user = dent_require_user();
    $phoneNumber = (string) ($_POST['phoneNumber'] ?? '');
    $result = dent_request_phone_enrollment_otp($user, $phoneNumber);

    dent_json_response([
        'success' => true,
        'message' => 'کد تایید شماره موبایل ارسال شد.',
        'phoneMasked' => (string) ($result['phoneMasked'] ?? ''),
        'cooldownSeconds' => (int) ($result['cooldownSeconds'] ?? 0),
        'expiresInSeconds' => (int) ($result['expiresInSeconds'] ?? 0),
    ]);
}

if ($action === 'verifyPhoneEnrollOtp') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تایید شماره موبایل نامعتبر است.', 405);
    }

    $user = dent_require_user();
    $phoneNumber = (string) ($_POST['phoneNumber'] ?? '');
    $otpCode = (string) ($_POST['otpCode'] ?? ($_POST['code'] ?? ''));
    $updatedUser = dent_verify_phone_enrollment_otp($user, $phoneNumber, $otpCode);

    dent_json_response([
        'success' => true,
        'user' => dent_public_user($updatedUser),
        'message' => 'شماره موبایل تایید شد.',
    ]);
}

if ($action === 'setPhoneLoginEnabled') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تغییر وضعیت ورود پیامکی نامعتبر است.', 405);
    }

    $user = dent_require_user();
    $enabled = filter_var($_POST['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    $updatedUser = dent_set_phone_login_enabled($user, $enabled);

    dent_json_response([
        'success' => true,
        'user' => dent_public_user($updatedUser),
        'message' => $enabled ? 'ورود با کد تایید فعال شد.' : 'ورود با کد تایید غیرفعال شد.',
    ]);
}

if ($action === 'removePhoneNumber') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد حذف شماره موبایل نامعتبر است.', 405);
    }

    $user = dent_require_user();
    $updatedUser = dent_remove_phone_number($user);

    dent_json_response([
        'success' => true,
        'user' => dent_public_user($updatedUser),
        'message' => 'شماره موبایل از حساب حذف شد.',
    ]);
}

if ($action === 'dismissPhoneNudge') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد بستن یادآوری شماره موبایل نامعتبر است.', 405);
    }

    $user = dent_require_user();
    $updatedUser = dent_dismiss_phone_nudge($user);

    dent_json_response([
        'success' => true,
        'user' => dent_public_user($updatedUser),
        'message' => 'یادآوری ثبت شماره موبایل بسته شد.',
    ]);
}

if ($action === 'smsStatus') {
    dent_require_owner();

    dent_json_response([
        'success' => true,
        'status' => dent_sms_status_payload(),
    ]);
}

if ($action === 'saveSmsConfig') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ذخیره تنظیمات پیامک نامعتبر است.', 405);
    }

    dent_require_owner();
    $status = dent_save_sms_owner_config([
        'enabled' => $_POST['enabled'] ?? '0',
        'apiKey' => $_POST['apiKey'] ?? '',
        'clearApiKey' => $_POST['clearApiKey'] ?? '0',
        'patternCode' => $_POST['patternCode'] ?? '',
        'senderLine' => $_POST['senderLine'] ?? '',
        'domain' => $_POST['domain'] ?? '',
        'codeParam' => $_POST['codeParam'] ?? 'code',
    ]);

    dent_json_response([
        'success' => true,
        'status' => $status,
        'message' => 'تنظیمات سرویس پیامک ذخیره شد.',
    ]);
}

if ($action === 'smsHealthCheck') {
    if (!in_array(dent_request_method(), ['POST', 'GET'], true)) {
        dent_error('متد بررسی سلامت پیامک نامعتبر است.', 405);
    }

    dent_require_owner();
    $phoneNumber = (string) ($_POST['phoneNumber'] ?? ($_GET['phoneNumber'] ?? ''));
    $health = dent_sms_health_check($phoneNumber === '' ? null : $phoneNumber);

    dent_json_response([
        'success' => (bool) ($health['success'] ?? false),
        'message' => (string) ($health['message'] ?? ''),
        'status' => $health['status'] ?? dent_sms_status_payload(),
    ]);
}

if ($action === 'users') {
    dent_require_owner();

    $users = dent_list_public_users();
    $gradeRoster = dent_grade_roster_index();
    $representativeCount = 0;

    foreach ($users as &$user) {
        $studentNumber = (string) ($user['studentNumber'] ?? '');
        $hasGrades = isset($gradeRoster[$studentNumber]);
        $user['hasGrades'] = $hasGrades;
        if (($user['role'] ?? '') === 'representative') {
            $representativeCount++;
        }
    }
    unset($user);

    dent_json_response([
        'success' => true,
        'users' => $users,
        'summary' => [
            'totalUsers' => count($users),
            'representatives' => $representativeCount,
            'ownerStudentNumber' => dent_owner_student_number(),
        ],
    ]);
}

if ($action === 'setRepresentative') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تغییر نماینده نامعتبر است.', 405);
    }

    dent_require_owner();

    $studentNumber = dent_normalize_student_number($_POST['studentNumber'] ?? '');
    $representative = (string) ($_POST['representative'] ?? '0') === '1';
    $updatedUser = dent_set_representative_status($studentNumber, $representative);

    dent_json_response([
        'success' => true,
        'user' => dent_public_user($updatedUser),
        'message' => $representative ? 'نماینده ثبت شد.' : 'نماینده از این حساب برداشته شد.',
    ]);
}

if ($action === 'createStudent') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ایجاد حساب دانشجو نامعتبر است.', 405);
    }

    dent_require_owner();

    $firstName = (string) ($_POST['firstName'] ?? '');
    $lastName = (string) ($_POST['lastName'] ?? '');
    $studentNumber = (string) ($_POST['studentNumber'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $created = dent_create_student_account($firstName, $lastName, $studentNumber, $password);

    dent_json_response([
        'success' => true,
        'user' => dent_public_user($created),
        'message' => 'حساب دانشجو ایجاد شد.',
    ]);
}

dent_error('درخواست نامعتبر است.', 404);
