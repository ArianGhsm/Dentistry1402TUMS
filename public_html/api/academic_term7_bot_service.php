<?php
declare(strict_types=1);

require_once __DIR__ . '/academic_term7_management.php';

function dent_term7_bot_service_action(string $action): bool
{
    return in_array($action, [
        'academicTerm7Self',
        'academicTerm7Roster',
        'academicTerm7AssignmentUpdate',
        'academicTerm7LeaderUpdate',
    ], true);
}

function dent_term7_bot_service_linked_user(array $payload): array
{
    [$platform, $platformUserId] = dent_bot_identity(
        (string) ($payload['platform'] ?? ''),
        (string) ($payload['platformUserId'] ?? '')
    );
    $user = dent_bot_linked_user($platform, $platformUserId);
    if (!is_array($user)) {
        dent_error('اتصال حساب سایت برای این عملیات لازم است.', 403, ['code' => 'TERM7_BOT_LINK_REQUIRED']);
    }
    return $user;
}

function dent_term7_bot_service_has_assignment(string $studentNumber, ?array $state = null): bool
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    $state = $state ?? dent_term7_state_read();
    return $studentNumber !== '' && is_array($state['assignments'][$studentNumber] ?? null);
}

function dent_term7_bot_service_roster(array $owner): array
{
    $state = dent_term7_state_read();
    return array_values(array_filter(
        dent_term7_owner_roster($owner),
        static fn(array $row): bool => dent_term7_bot_service_has_assignment(
            (string) ($row['studentNumber'] ?? ''),
            $state
        )
    ));
}

function dent_term7_bot_service_dispatch(array $request): array
{
    $action = trim((string) ($request['action'] ?? ''));
    $user = dent_term7_bot_service_linked_user($request);

    if ($action === 'academicTerm7Self') {
        $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
        if (dent_user_cohort_key($user) !== DENT_TERM7_COHORT
            || !dent_term7_bot_service_has_assignment($studentNumber)) {
            return ['success' => true, 'eligible' => false, 'assignment' => null];
        }
        return [
            'success' => true,
            'eligible' => true,
            'assignment' => dent_term7_public_assignment_for_student($studentNumber),
        ];
    }

    dent_term7_require_owner($user);

    if ($action === 'academicTerm7Roster') {
        return ['success' => true, 'roster' => dent_term7_bot_service_roster($user)];
    }

    $studentNumber = (string) ($request['studentNumber'] ?? '');
    $field = trim((string) ($request['field'] ?? ''));

    if ($action === 'academicTerm7AssignmentUpdate') {
        $rawGroup = $request['group'] ?? null;
        $group = ($rawGroup === null || $rawGroup === '') ? null : (int) $rawGroup;
        return [
            'success' => true,
            'assignment' => dent_term7_owner_update_assignment($user, $studentNumber, $field, $group),
        ];
    }

    if ($action === 'academicTerm7LeaderUpdate') {
        $leader = filter_var($request['leader'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($leader === null) {
            dent_error('وضعیت سرگروهی معتبر نیست.', 422, ['code' => 'TERM7_LEADER_VALUE_INVALID']);
        }
        return [
            'success' => true,
            'assignment' => dent_term7_owner_set_leader($user, $studentNumber, $field, $leader),
        ];
    }

    dent_error('عملیات گروه‌بندی ترم ۷ معتبر نیست.', 404, ['code' => 'TERM7_BOT_ACTION_UNKNOWN']);
}
