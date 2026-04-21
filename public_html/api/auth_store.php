<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function dent_owner_student_number(): string
{
    return '40211272003';
}

function dent_auth_store_path(): string
{
    return dent_storage_path('auth/users.json');
}

function dent_default_profile(): array
{
    return [
        'bio' => '',
        'contactHandle' => '',
        'focusArea' => '',
    ];
}

function dent_hash_password(string $password): string
{
    $password = dent_normalize_digits($password);
    if ($password === '') {
        dent_error('??? ???? ???? ????? ????.', 422);
    }

    $iterations = 210000;
    $salt = random_bytes(16);
    $derived = hash_pbkdf2('sha256', $password, $salt, $iterations, 32, true);

    return 'pbkdf2_sha256$' . $iterations . '$' . dent_base64url_encode($salt) . '$' . dent_base64url_encode($derived);
}

function dent_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function dent_base64url_decode(string $value): string
{
    $padding = strlen($value) % 4;
    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
}

function dent_verify_password(string $password, string $storedHash): bool
{
    $password = dent_normalize_digits($password);
    $storedHash = trim($storedHash);

    if ($password === '' || $storedHash === '') {
        return false;
    }

    if (str_starts_with($storedHash, 'pbkdf2_sha256$')) {
        $parts = explode('$', $storedHash, 4);
        if (count($parts) !== 4) {
            return false;
        }

        $iterations = (int) $parts[1];
        $salt = dent_base64url_decode($parts[2]);
        $expected = dent_base64url_decode($parts[3]);
        if ($iterations < 10000 || $salt === '' || $expected === '') {
            return false;
        }

        $derived = hash_pbkdf2('sha256', $password, $salt, $iterations, strlen($expected), true);
        return hash_equals($expected, $derived);
    }

    return password_verify($password, $storedHash);
}

function dent_password_needs_rehash(string $storedHash): bool
{
    if (!str_starts_with($storedHash, 'pbkdf2_sha256$')) {
        return true;
    }

    $parts = explode('$', $storedHash, 4);
    if (count($parts) !== 4) {
        return true;
    }

    return (int) $parts[1] !== 210000;
}

function dent_normalize_role(?string $role, string $studentNumber): string
{
    if ($studentNumber === dent_owner_student_number()) {
        return 'owner';
    }

    $role = trim((string) $role);
    if ($role === 'representative') {
        return 'representative';
    }

    return 'student';
}

function dent_role_label(string $role): string
{
    if ($role === 'owner') {
        return '???? ??????';
    }

    if ($role === 'representative') {
        return '???????';
    }

    return '??????';
}

function dent_permissions_for_role(string $role): array
{
    if ($role === 'owner') {
        return [
            'moderateChat' => true,
            'manageUsers' => true,
            'manageRepresentatives' => true,
        ];
    }

    if ($role === 'representative') {
        return [
            'moderateChat' => true,
            'manageUsers' => false,
            'manageRepresentatives' => false,
        ];
    }

    return [
        'moderateChat' => false,
        'manageUsers' => false,
        'manageRepresentatives' => false,
    ];
}

function dent_normalize_user_record(string $studentNumber, array $user): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    $profile = $user['profile'] ?? [];
    $defaults = dent_default_profile();

    $normalized = [
        'studentNumber' => $studentNumber,
        'name' => trim((string) ($user['name'] ?? $studentNumber)),
        'passwordHash' => (string) ($user['passwordHash'] ?? ''),
        'role' => dent_normalize_role($user['role'] ?? 'student', $studentNumber),
        'profile' => [
            'bio' => dent_clean_text($profile['bio'] ?? $defaults['bio'], 280),
            'contactHandle' => dent_clean_text($profile['contactHandle'] ?? $defaults['contactHandle'], 80),
            'focusArea' => dent_clean_text($profile['focusArea'] ?? $defaults['focusArea'], 80),
        ],
        'createdAt' => trim((string) ($user['createdAt'] ?? dent_iso_now())),
        'updatedAt' => trim((string) ($user['updatedAt'] ?? dent_iso_now())),
    ];

    if ($normalized['passwordHash'] === '') {
        dent_error('?? ???? ?????? ???? ??? ????? ?? storage ???? ??.', 500);
    }

    return $normalized;
}

function dent_load_user_store(): array
{
    $path = dent_auth_store_path();
    $store = dent_read_json_file($path, [
        'schemaVersion' => 1,
        'ownerStudentNumber' => dent_owner_student_number(),
        'users' => [],
    ]);

    if (!is_array($store)) {
        $store = [
            'schemaVersion' => 1,
            'ownerStudentNumber' => dent_owner_student_number(),
            'users' => [],
        ];
    }

    $users = $store['users'] ?? [];
    if (!is_array($users)) {
        $users = [];
    }

    $normalizedUsers = [];
    foreach ($users as $key => $user) {
        if (!is_array($user)) {
            continue;
        }

        $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $key));
        if ($studentNumber === '') {
            continue;
        }

        $normalizedUsers[$studentNumber] = dent_normalize_user_record($studentNumber, $user);
    }

    ksort($normalizedUsers, SORT_STRING);

    return [
        'schemaVersion' => 1,
        'ownerStudentNumber' => dent_owner_student_number(),
        'users' => $normalizedUsers,
    ];
}

function dent_save_user_store(array $store): void
{
    $users = $store['users'] ?? [];
    if (!is_array($users)) {
        $users = [];
    }

    $normalizedUsers = [];
    foreach ($users as $studentNumber => $user) {
        if (!is_array($user)) {
            continue;
        }

        $normalizedStudentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $studentNumber));
        if ($normalizedStudentNumber === '') {
            continue;
        }

        $normalizedUsers[$normalizedStudentNumber] = dent_normalize_user_record($normalizedStudentNumber, $user);
    }

    ksort($normalizedUsers, SORT_STRING);

    dent_write_json_file(dent_auth_store_path(), [
        'schemaVersion' => 1,
        'ownerStudentNumber' => dent_owner_student_number(),
        'users' => $normalizedUsers,
    ]);
}

function dent_get_user_record(string $studentNumber): ?array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        return null;
    }

    $store = dent_load_user_store();
    return $store['users'][$studentNumber] ?? null;
}

function dent_public_user(array $user): array
{
    $role = dent_normalize_role($user['role'] ?? 'student', (string) ($user['studentNumber'] ?? ''));
    $permissions = dent_permissions_for_role($role);

    return [
        'studentNumber' => (string) ($user['studentNumber'] ?? ''),
        'name' => (string) ($user['name'] ?? ''),
        'role' => $role,
        'roleLabel' => dent_role_label($role),
        'isOwner' => $role === 'owner',
        'isRepresentative' => $role === 'representative',
        'canModerateChat' => $permissions['moderateChat'],
        'permissions' => $permissions,
        'profile' => [
            'bio' => (string) (($user['profile']['bio'] ?? '') ?: ''),
            'contactHandle' => (string) (($user['profile']['contactHandle'] ?? '') ?: ''),
            'focusArea' => (string) (($user['profile']['focusArea'] ?? '') ?: ''),
        ],
        'createdAt' => (string) ($user['createdAt'] ?? ''),
        'updatedAt' => (string) ($user['updatedAt'] ?? ''),
    ];
}

function dent_auth_status(array $user): string
{
    $role = dent_normalize_role($user['role'] ?? 'student', (string) ($user['studentNumber'] ?? ''));

    if ($role === 'owner') {
        return 'logged-in-admin';
    }

    return 'logged-in';
}

function dent_current_user(): ?array
{
    $studentNumber = dent_normalize_student_number((string) ($_SESSION['student_number'] ?? ''));
    if ($studentNumber === '') {
        return null;
    }

    $user = dent_get_user_record($studentNumber);
    if ($user === null) {
        unset($_SESSION['student_number'], $_SESSION['auth_at']);
        return null;
    }

    return $user;
}

function dent_require_user(): array
{
    $user = dent_current_user();
    if ($user === null) {
        dent_error('???? ?? ???? ?? ???? ?????? ???.', 401, ['loggedOut' => true]);
    }

    return $user;
}

function dent_require_owner(): array
{
    $user = dent_require_user();
    if (($user['role'] ?? 'student') !== 'owner') {
        dent_error('?????? ??? ??? ??? ???? ???? ?????? ???? ???.', 403);
    }

    return $user;
}

function dent_can_moderate_chat(array $user): bool
{
    return (bool) dent_permissions_for_role((string) ($user['role'] ?? 'student'))['moderateChat'];
}

function dent_verify_credentials(string $studentNumber, string $password): ?array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    $password = dent_normalize_digits($password);

    $user = dent_get_user_record($studentNumber);
    if ($user === null) {
        return null;
    }

    if (!dent_verify_password($password, (string) $user['passwordHash'])) {
        return null;
    }

    if (dent_password_needs_rehash((string) $user['passwordHash'])) {
        $user['passwordHash'] = dent_hash_password($password);
        $user['updatedAt'] = dent_iso_now();
        dent_persist_user($user);
    }

    return $user;
}

function dent_persist_user(array $user): array
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        dent_error('????? ????? ??????? ???.', 422);
    }

    $store = dent_load_user_store();
    $existing = $store['users'][$studentNumber] ?? [];

    $merged = array_merge($existing, $user);
    $merged['studentNumber'] = $studentNumber;
    $merged['updatedAt'] = dent_iso_now();
    if (empty($merged['createdAt'])) {
        $merged['createdAt'] = dent_iso_now();
    }

    $store['users'][$studentNumber] = dent_normalize_user_record($studentNumber, $merged);
    dent_save_user_store($store);

    return $store['users'][$studentNumber];
}

function dent_login_user(array $user): array
{
    session_regenerate_id(true);
    $_SESSION['student_number'] = (string) ($user['studentNumber'] ?? '');
    $_SESSION['auth_at'] = time();

    return dent_public_user($user);
}

function dent_logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?: '/',
            $params['domain'] ?? '',
            (bool) ($params['secure'] ?? false),
            (bool) ($params['httponly'] ?? true)
        );
    }

    session_destroy();
}

function dent_update_profile(array $user, array $input): array
{
    $user['profile'] = [
        'bio' => dent_clean_text($input['bio'] ?? ($user['profile']['bio'] ?? ''), 280),
        'contactHandle' => dent_clean_text($input['contactHandle'] ?? ($user['profile']['contactHandle'] ?? ''), 80),
        'focusArea' => dent_clean_text($input['focusArea'] ?? ($user['profile']['focusArea'] ?? ''), 80),
    ];

    return dent_persist_user($user);
}

function dent_change_user_password(array $user, string $currentPassword, string $newPassword): array
{
    $currentPassword = dent_normalize_digits($currentPassword);
    $newPassword = dent_normalize_digits($newPassword);

    if ($currentPassword === '' || $newPassword === '') {
        dent_error('??? ???? ? ??? ???? ?? ???? ???? ??.', 422);
    }

    if (!dent_verify_password($currentPassword, (string) $user['passwordHash'])) {
        dent_error('??? ???? ???? ????.', 422);
    }

    if (mb_strlen($newPassword, 'UTF-8') < 6) {
        dent_error('??? ???? ???? ????? ? ??????? ????.', 422);
    }

    $user['passwordHash'] = dent_hash_password($newPassword);

    return dent_persist_user($user);
}

function dent_list_public_users(): array
{
    $store = dent_load_user_store();
    $users = [];

    foreach ($store['users'] as $studentNumber => $user) {
        $public = dent_public_user($user);
        $public['sortableName'] = trim((string) ($user['name'] ?? $studentNumber));
        $users[] = $public;
    }

    usort($users, static function (array $left, array $right): int {
        if (($left['role'] ?? '') === 'owner' && ($right['role'] ?? '') !== 'owner') {
            return -1;
        }

        if (($right['role'] ?? '') === 'owner' && ($left['role'] ?? '') !== 'owner') {
            return 1;
        }

        if (($left['role'] ?? '') === 'representative' && ($right['role'] ?? '') === 'student') {
            return -1;
        }

        if (($right['role'] ?? '') === 'representative' && ($left['role'] ?? '') === 'student') {
            return 1;
        }

        return strcasecmp((string) ($left['sortableName'] ?? ''), (string) ($right['sortableName'] ?? ''));
    });

    foreach ($users as &$user) {
        unset($user['sortableName']);
    }
    unset($user);

    return $users;
}

function dent_set_representative_status(string $studentNumber, bool $isRepresentative): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        dent_error('????? ???????? ??????? ???.', 422);
    }

    $user = dent_get_user_record($studentNumber);
    if ($user === null) {
        dent_error('????? ??????? ???? ???.', 404);
    }

    if ($studentNumber === dent_owner_student_number()) {
        dent_error('???? ???? ???? ????????? ?? ??? ???? ???? ???.', 422);
    }

    $user['role'] = $isRepresentative ? 'representative' : 'student';

    return dent_persist_user($user);
}
