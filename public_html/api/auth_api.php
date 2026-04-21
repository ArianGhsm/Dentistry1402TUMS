<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/grades_store.php';

$action = dent_request_action();

if ($action === 'login') {
    if (dent_request_method() !== 'POST') {
        dent_error('??? ???? ??????? ???.', 405);
    }

    $studentNumber = dent_normalize_student_number($_POST['studentNumber'] ?? $_POST['username'] ?? '');
    $password = dent_normalize_digits($_POST['password'] ?? '');

    if ($studentNumber === '' || $password === '') {
        dent_error('????? ???????? ? ??? ???? ?? ???? ??.', 422);
    }

    $user = dent_verify_credentials($studentNumber, $password);
    if ($user === null) {
        dent_error('????? ???????? ?? ??? ???? ?????? ???.', 401, ['loggedOut' => true]);
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
        dent_error('??? ??????????? ??????? ??????? ???.', 405);
    }

    $user = dent_require_user();
    $updatedUser = dent_update_profile($user, [
        'bio' => $_POST['bio'] ?? '',
        'contactHandle' => $_POST['contactHandle'] ?? '',
        'focusArea' => $_POST['focusArea'] ?? '',
    ]);

    dent_json_response([
        'success' => true,
        'user' => dent_public_user($updatedUser),
        'message' => '??????? ???? ?????? ????? ??.',
    ]);
}

if ($action === 'changePassword') {
    if (dent_request_method() !== 'POST') {
        dent_error('??? ????? ??? ??????? ???.', 405);
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
        'message' => '??? ???? ?? ?????? ????? ???.',
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
        dent_error('??? ????? ??????? ??????? ???.', 405);
    }

    dent_require_owner();

    $studentNumber = dent_normalize_student_number($_POST['studentNumber'] ?? '');
    $representative = (string) ($_POST['representative'] ?? '0') === '1';
    $updatedUser = dent_set_representative_status($studentNumber, $representative);

    dent_json_response([
        'success' => true,
        'user' => dent_public_user($updatedUser),
        'message' => $representative ? '??????? ??? ??.' : '??????? ?? ??? ???? ??????? ??.',
    ]);
}

dent_error('??????? ??????? ???.', 404);
