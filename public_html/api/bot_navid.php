<?php
declare(strict_types=1);

function dent_bot_navid_require_owner(array $user): void
{
    if (dent_normalize_role((string) ($user['role'] ?? 'student'), (string) ($user['studentNumber'] ?? '')) !== 'owner') {
        dent_error('این عملیات فقط برای مالک مجاز است.', 403);
    }
}

function dent_bot_navid_daily_start(array $user, string $platform, array $payload): array
{
    dent_bot_navid_require_owner($user);
    return ['success' => false, 'code' => 'NAVID_RETIRED', 'message' => 'سامانهٔ پیشین غیرفعال است؛ از همیاد استفاده کن.'];
}

function dent_bot_navid_daily_complete(array $user, string $platform, array $payload): array
{
    return dent_bot_navid_daily_start($user, $platform, $payload);
}

function dent_bot_navid_status(array $user): array
{
    dent_bot_navid_require_owner($user);
    return [
        'success' => true,
        'status' => ['enabled' => false, 'sessionStatus' => 'retired', 'lastResult' => 'retired'],
        'assignments' => [],
        'automation' => [],
        'replacementHref' => '/hamiad/',
    ];
}
