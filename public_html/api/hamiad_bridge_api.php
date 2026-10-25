<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/hamiad_bridge_service.php';
require_once __DIR__ . '/hamiad_courses.php';
require_once __DIR__ . '/hamiad_dashboard.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');

dent_require_owner();
$action = trim((string) ($_GET['action'] ?? ''));
if (dent_request_method() === 'GET' && $action === 'dashboard') {
    try {
        dent_json_response(['success' => true, 'dashboard' => hamiad_term7_dashboard()]);
    } catch (RuntimeException $error) {
        dent_error($error->getMessage(), 422);
    }
}
if (dent_request_method() === 'GET' && $action === 'termCourse') {
    try {
        dent_json_response(['success' => true, 'detail' => hamiad_term7_course_view((int) ($_GET['id'] ?? 0))]);
    } catch (RuntimeException $error) {
        dent_error($error->getMessage(), 422);
    }
}
if (dent_request_method() === 'GET' && $action === 'assignment') {
    try {
        $courseId = (int) ($_GET['courseId'] ?? 0);
        $assignmentId = (int) ($_GET['activityId'] ?? 0);
        dent_json_response(['success' => true, 'assignment' => hamiad_assignment_detail_or_snapshot($courseId, $assignmentId)]);
    } catch (RuntimeException $error) {
        dent_error($error->getMessage(), 422);
    }
}
if (dent_request_method() === 'GET' && $action === 'status') {
    dent_json_response(['success' => true, 'status' => hamiad_session_status()]);
}
if (dent_request_method() === 'GET' && $action === 'course') {
    try {
        dent_json_response(['success' => true, 'items' => hamiad_course_items((int) ($_GET['id'] ?? 0))]);
    } catch (RuntimeException $error) {
        dent_error($error->getMessage(), 422);
    }
}
if (dent_request_method() === 'GET' && $action === 'courses') {
    try {
        dent_json_response(['success' => true, 'courses' => hamiad_course_list(), 'status' => hamiad_session_status()]);
    } catch (RuntimeException $error) {
        dent_error($error->getMessage(), 422);
    }
}
if (dent_request_method() !== 'POST') {
    dent_error('درخواست نامعتبر است.', 405);
}
dent_auth_session_require_csrf();
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
