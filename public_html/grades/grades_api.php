<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/auth_store.php';
require_once __DIR__ . '/../api/grades_store.php';

$action = dent_request_action();

if ($action === 'me') {
    $user = dent_require_user();
    dent_json_response(dent_build_grades_payload($user));
}

dent_error('??????? ??????? ???.', 404);
