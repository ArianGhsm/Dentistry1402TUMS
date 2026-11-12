<?php
declare(strict_types=1);
require_once __DIR__ . '/hamiad_dashboard.php';
require_once __DIR__ . '/hamiad_bot_login.php';

function dent_bot_hamiad_owner_only(array $user): void
{
    if (dent_normalize_role((string) ($user['role'] ?? 'student'), (string) ($user['studentNumber'] ?? '')) !== 'owner') {
        dent_error('فقط مالک سامانه می‌تواند به حساب متصل همیاد دسترسی داشته باشد.', 403);
    }
}

function dent_bot_hamiad_dashboard(array $user): array
{
    dent_bot_hamiad_owner_only($user);
    return ['success' => true, 'dashboard' => hamiad_term7_dashboard()];
}

function dent_bot_hamiad_course(array $user, array $payload): array
{
    dent_bot_hamiad_owner_only($user);
    $id = filter_var($payload['courseId'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        dent_error('درس معتبر نیست.', 422);
    }
    return ['success' => true, 'detail' => hamiad_term7_course_view((int) $id)];
}

function dent_bot_hamiad_assignment(array $user, array $payload): array
{
    dent_bot_hamiad_owner_only($user);
    $course = filter_var($payload['courseId'] ?? null, FILTER_VALIDATE_INT);
    $id = filter_var($payload['activityId'] ?? null, FILTER_VALIDATE_INT);
    if (!$course || !$id) {
        dent_error('مشخصات تکلیف معتبر نیست.', 422);
    }
    return ['success' => true, 'assignment' => hamiad_assignment_detail_or_snapshot((int)$course, (int)$id)];
}

function dent_bot_hamiad_login_start(array $user, string $platform): array
{
    dent_bot_hamiad_owner_only($user);
    try {
        return hamiad_bot_login_start($platform);
    } catch (RuntimeException $error) {
        dent_error($error->getMessage(), 422);
    }
}

function dent_bot_hamiad_login_complete(array $user, string $platform, array $payload): array
{
    dent_bot_hamiad_owner_only($user);
    try {
        return hamiad_bot_login_complete(
            $platform,
            trim((string)($payload['challengeRef'] ?? '')),
            trim((string)($payload['captchaCode'] ?? ''))
        );
    } catch (RuntimeException $error) {
        dent_error($error->getMessage(), 422);
    }
}
