<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';

const DENT_NOTIFICATIONS_SCHEMA_VERSION = 4;
const DENT_NOTIFICATION_ID_PREFIX = 'nt-';
const DENT_NOTIFICATION_KIND_ANNOUNCEMENT = 'announcement';
const DENT_NOTIFICATION_KIND_NAVID_ASSIGNMENT = 'navid-assignment';
const DENT_NOTIFICATION_TARGET_ALL = 'all';
const DENT_NOTIFICATION_TARGET_COHORT = 'cohort';
const DENT_NOTIFICATION_TARGET_USER = 'user';
const DENT_NOTIFICATION_STATUS_ACTIVE = 'active';
const DENT_NOTIFICATION_STATUS_SCHEDULED = 'scheduled';
const DENT_NOTIFICATION_SMS_STATUS_NONE = 'none';
const DENT_NOTIFICATION_SMS_STATUS_PENDING = 'pending';
const DENT_NOTIFICATION_SMS_STATUS_SENDING = 'sending';
const DENT_NOTIFICATION_SMS_STATUS_SENT = 'sent';
const DENT_NOTIFICATION_SMS_STATUS_PARTIAL = 'partial';
const DENT_NOTIFICATION_SMS_STATUS_FAILED = 'failed';
const DENT_NOTIFICATIONS_MAX_RECORDS = 320;

function notifications_store_path(): string
{
    return dent_storage_path('notifications/store.json');
}

function notifications_store_lock_path(): string
{
    return dent_storage_path('notifications/store.lock');
}

function notifications_default_store(): array
{
    return [
        'schemaVersion' => DENT_NOTIFICATIONS_SCHEMA_VERSION,
        'notifications' => [],
        'userStates' => [],
        'suppressedSources' => [],
    ];
}

function notifications_ensure_storage(): void
{
    dent_ensure_directory(dirname(notifications_store_path()));
    if (!is_file(notifications_store_path())) {
        dent_write_json_file(notifications_store_path(), notifications_default_store());
    }
}

function notifications_load_store_unlocked(): array
{
    notifications_ensure_storage();
    $raw = dent_read_json_file(notifications_store_path(), notifications_default_store());
    if (!is_array($raw)) {
        $raw = notifications_default_store();
    }

    return notifications_normalize_store($raw);
}

function notifications_read_store(): array
{
    notifications_ensure_storage();
    $lock = fopen(notifications_store_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل اعلان‌ها.', 500);
    }

    try {
        if (!flock($lock, LOCK_SH)) {
            dent_error('قفل خواندن اعلان‌ها آماده نشد.', 500);
        }
        return notifications_load_store_unlocked();
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

/**
 * @template T
 * @param callable(array):T $callback
 * @return T
 */
function notifications_with_store_lock(callable $callback)
{
    notifications_ensure_storage();
    $lock = fopen(notifications_store_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل اعلان‌ها.', 500);
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            dent_error('قفل ذخیره‌سازی اعلان‌ها آماده نشد.', 500);
        }
        $store = notifications_load_store_unlocked();
        $result = $callback($store);
        dent_write_json_file(notifications_store_path(), notifications_normalize_store($store));
        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function notifications_clean_cta_href(?string $value): string
{
    $href = trim((string) $value);
    if ($href === '') {
        return '';
    }
    if (!str_starts_with($href, '/') || str_starts_with($href, '//')) {
        return '';
    }

    return $href;
}

function notifications_clean_tone(?string $value): string
{
    $tone = trim((string) $value);
    return in_array($tone, ['accent', 'ok', 'warn', 'danger', 'muted'], true) ? $tone : 'accent';
}

function notifications_clean_kind(?string $value): string
{
    $kind = trim((string) $value);
    return in_array($kind, [DENT_NOTIFICATION_KIND_ANNOUNCEMENT, DENT_NOTIFICATION_KIND_NAVID_ASSIGNMENT], true)
        ? $kind
        : DENT_NOTIFICATION_KIND_ANNOUNCEMENT;
}

function notifications_clean_target(?string $value): string
{
    $target = trim((string) $value);
    return in_array($target, [DENT_NOTIFICATION_TARGET_ALL, DENT_NOTIFICATION_TARGET_COHORT, DENT_NOTIFICATION_TARGET_USER], true)
        ? $target
        : DENT_NOTIFICATION_TARGET_ALL;
}

function notifications_clean_status(?string $value, string $publishAt = ''): string
{
    $status = trim((string) $value);
    if (in_array($status, [DENT_NOTIFICATION_STATUS_ACTIVE, DENT_NOTIFICATION_STATUS_SCHEDULED], true)) {
        return $status;
    }

    return notifications_timestamp($publishAt) > time()
        ? DENT_NOTIFICATION_STATUS_SCHEDULED
        : DENT_NOTIFICATION_STATUS_ACTIVE;
}

function notifications_clean_sms_status(?string $value, bool $sendSms): string
{
    $status = trim((string) $value);
    if (!$sendSms) {
        return DENT_NOTIFICATION_SMS_STATUS_NONE;
    }

    return in_array($status, [
        DENT_NOTIFICATION_SMS_STATUS_PENDING,
        DENT_NOTIFICATION_SMS_STATUS_SENDING,
        DENT_NOTIFICATION_SMS_STATUS_SENT,
        DENT_NOTIFICATION_SMS_STATUS_PARTIAL,
        DENT_NOTIFICATION_SMS_STATUS_FAILED,
    ], true)
        ? $status
        : DENT_NOTIFICATION_SMS_STATUS_PENDING;
}

function notifications_normalize_iso_datetime(?string $value): string
{
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }

    $timestamp = strtotime(str_replace('T', ' ', $text));
    if ($timestamp === false) {
        return '';
    }

    return date('c', $timestamp);
}

function notifications_timestamp(?string $value): int
{
    $normalized = notifications_normalize_iso_datetime($value);
    if ($normalized === '') {
        return 0;
    }

    $timestamp = strtotime($normalized);
    return $timestamp === false ? 0 : (int) $timestamp;
}

function notifications_parse_deploy_source_key(?string $value): array
{
    $sourceKey = trim((string) $value);
    if ($sourceKey === '') {
        return [
            'version' => '',
            'deployedAt' => '',
        ];
    }

    if (!preg_match('/^deploy:([^:]+):(.+)$/', $sourceKey, $matches)) {
        return [
            'version' => '',
            'deployedAt' => '',
        ];
    }

    return [
        'version' => dent_clean_text((string) ($matches[1] ?? ''), 80),
        'deployedAt' => notifications_normalize_iso_datetime((string) ($matches[2] ?? '')),
    ];
}

function notifications_extract_deploy_body_value(string $body, string $pattern, int $maxLength): string
{
    if ($body === '') {
        return '';
    }

    if (!preg_match($pattern, $body, $matches)) {
        return '';
    }

    return dent_clean_text((string) ($matches[1] ?? ''), $maxLength);
}

function notifications_hydrate_deploy_meta(array $record, array $meta): array
{
    if ((string) ($record['source'] ?? '') !== 'deploy') {
        return [];
    }

    $sourceKeyMeta = notifications_parse_deploy_source_key((string) ($record['sourceKey'] ?? ''));
    $body = (string) ($record['body'] ?? '');
    $title = (string) ($record['title'] ?? '');

    $version = dent_clean_text((string) ($meta['version'] ?? ''), 80);
    if ($version === '') {
        $version = $sourceKeyMeta['version'];
    }
    if ($version === '' && preg_match('/\b(\d{8}-\d{6})\b/u', $title, $matches)) {
        $version = dent_clean_text((string) ($matches[1] ?? ''), 80);
    }
    if ($version === '') {
        $version = notifications_extract_deploy_body_value($body, '/^نسخه(?:\s+منتشرشده)?:\s*(.+)$/mu', 80);
    }

    $deployedAt = notifications_normalize_iso_datetime((string) ($meta['deployedAt'] ?? ''));
    if ($deployedAt === '') {
        $deployedAt = $sourceKeyMeta['deployedAt'];
    }
    if ($deployedAt === '') {
        $deployedAt = notifications_normalize_iso_datetime(
            notifications_extract_deploy_body_value($body, '/^زمان(?:\s+دقیق)?(?:\s+deploy|\s+استقرار)?:\s*(.+)$/mu', 80)
        );
    }
    if ($deployedAt === '') {
        $deployedAt = notifications_normalize_iso_datetime((string) ($record['releasedAt'] ?? ($record['publishAt'] ?? ($record['createdAt'] ?? ''))));
    }

    $branch = dent_clean_text((string) ($meta['branch'] ?? ''), 120);
    if ($branch === '') {
        $branch = notifications_extract_deploy_body_value($body, '/^شاخه(?:\s+استقرار)?:\s*(.+)$/mu', 120);
    }

    $deployHead = dent_clean_text((string) ($meta['deployHead'] ?? ''), 80);
    if ($deployHead === '') {
        $deployHead = notifications_extract_deploy_body_value($body, '/^(?:HEAD|کد\s+استقرار):\s*(.+)$/mu', 80);
    }

    $clean = [];
    if ($version !== '') {
        $clean['version'] = $version;
    }
    if ($deployedAt !== '') {
        $clean['deployedAt'] = $deployedAt;
    }
    if ($branch !== '') {
        $clean['branch'] = $branch;
    }
    if ($deployHead !== '') {
        $clean['deployHead'] = $deployHead;
    }

    return $clean;
}

function notifications_clean_meta(array $meta, array $record = []): array
{
    $clean = [];

    $assignmentKey = dent_clean_text((string) ($meta['assignmentKey'] ?? ''), 120);
    if ($assignmentKey !== '') {
        $clean['assignmentKey'] = $assignmentKey;
    }

    $courseTitle = dent_clean_text((string) ($meta['courseTitle'] ?? ''), 180);
    if ($courseTitle !== '') {
        $clean['courseTitle'] = $courseTitle;
    }

    $endDateIso = notifications_normalize_iso_datetime((string) ($meta['endDateIso'] ?? ''));
    if ($endDateIso !== '') {
        $clean['endDateIso'] = $endDateIso;
    }

    $endDateLabel = dent_clean_text((string) ($meta['endDateLabel'] ?? ''), 80);
    if ($endDateLabel !== '') {
        $clean['endDateLabel'] = $endDateLabel;
    }

    $proposeDate = dent_clean_text((string) ($meta['proposeDate'] ?? ''), 80);
    if ($proposeDate !== '') {
        $clean['proposeDate'] = $proposeDate;
    }

    $replyStatusName = dent_clean_text((string) ($meta['replyStatusName'] ?? ''), 80);
    if ($replyStatusName !== '') {
        $clean['replyStatusName'] = $replyStatusName;
    }

    foreach (notifications_hydrate_deploy_meta($record, $meta) as $key => $value) {
        if ((string) $value === '') {
            continue;
        }
        $clean[$key] = $value;
    }

    return $clean;
}

function notifications_generate_id(): string
{
    try {
        return DENT_NOTIFICATION_ID_PREFIX . bin2hex(random_bytes(6));
    } catch (Throwable $error) {
        return DENT_NOTIFICATION_ID_PREFIX . str_replace('.', '', uniqid('', true));
    }
}

function notifications_clean_recipient_snapshot(array $recipient): ?array
{
    $studentNumber = dent_normalize_student_number((string) ($recipient['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        return null;
    }

    $role = dent_normalize_role(
        (string) ($recipient['role'] ?? 'student'),
        $studentNumber
    );

    return [
        'studentNumber' => $studentNumber,
        'name' => dent_clean_text((string) ($recipient['name'] ?? ''), 120),
        'role' => $role,
        'cohortKey' => dent_clean_cohort_key((string) ($recipient['cohortKey'] ?? '')),
    ];
}

function notifications_normalize_recipients(array $recipients): array
{
    $normalized = [];
    foreach ($recipients as $recipient) {
        if (!is_array($recipient)) {
            continue;
        }
        $clean = notifications_clean_recipient_snapshot($recipient);
        if ($clean === null) {
            continue;
        }
        $normalized[$clean['studentNumber']] = $clean;
    }

    uasort($normalized, static function (array $left, array $right): int {
        $nameCompare = strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        if ($nameCompare !== 0) {
            return $nameCompare;
        }
        return strcmp((string) ($left['studentNumber'] ?? ''), (string) ($right['studentNumber'] ?? ''));
    });

    return array_values($normalized);
}

function notifications_source_signature(string $source, string $sourceKey): string
{
    $cleanSource = dent_clean_text($source, 40);
    $cleanKey = dent_clean_text($sourceKey, 220);
    if ($cleanSource === '' || $cleanKey === '') {
        return '';
    }

    return $cleanSource . '|' . $cleanKey;
}

function notifications_normalize_record(string $key, array $record): ?array
{
    $id = trim((string) ($record['id'] ?? $key));
    if ($id === '') {
        $id = notifications_generate_id();
    }

    $title = dent_clean_text((string) ($record['title'] ?? ''), 180);
    $body = dent_clean_text((string) ($record['body'] ?? ''), 4000);
    if ($title === '' && $body === '') {
        return null;
    }

    $target = notifications_clean_target((string) ($record['target'] ?? ''));
    $cohortKey = '';
    $targetStudentNumber = '';
    if ($target === DENT_NOTIFICATION_TARGET_COHORT) {
        $cohortKey = dent_clean_cohort_key((string) ($record['cohortKey'] ?? ''));
        if ($cohortKey === '' || !dent_cohort_exists($cohortKey)) {
            return null;
        }
    } elseif ($target === DENT_NOTIFICATION_TARGET_USER) {
        $targetStudentNumber = dent_normalize_student_number((string) ($record['targetStudentNumber'] ?? ($record['studentNumber'] ?? '')));
        if ($targetStudentNumber === '') {
            return null;
        }
        $targetUser = dent_get_user_record($targetStudentNumber);
        if ($targetUser === null) {
            return null;
        }
        $cohortKey = dent_user_cohort_key($targetUser);
    }

    $ctaHref = notifications_clean_cta_href((string) ($record['ctaHref'] ?? ''));
    $ctaLabel = $ctaHref !== ''
        ? dent_clean_text((string) ($record['ctaLabel'] ?? ''), 80)
        : '';
    if ($ctaHref !== '' && $ctaLabel === '') {
        $ctaLabel = 'مشاهده';
    }

    $createdAt = notifications_normalize_iso_datetime((string) ($record['createdAt'] ?? ''));
    if ($createdAt === '') {
        $createdAt = dent_iso_now();
    }

    $publishAt = notifications_normalize_iso_datetime((string) ($record['publishAt'] ?? $createdAt));
    if ($publishAt === '') {
        $publishAt = $createdAt;
    }

    $status = notifications_clean_status((string) ($record['status'] ?? ''), $publishAt);
    $releasedAt = $status === DENT_NOTIFICATION_STATUS_ACTIVE
        ? notifications_normalize_iso_datetime((string) ($record['releasedAt'] ?? $publishAt))
        : '';
    if ($status === DENT_NOTIFICATION_STATUS_ACTIVE && $releasedAt === '') {
        $releasedAt = $publishAt;
    }

    $sendSms = dent_parse_bool($record['sendSms'] ?? null, false);
    $smsRecipientCount = max(0, (int) ($record['smsRecipientCount'] ?? 0));
    $smsEligibleCount = max(0, (int) ($record['smsEligibleCount'] ?? 0));
    $smsSentCount = max(0, (int) ($record['smsSentCount'] ?? 0));
    $smsFailedCount = max(0, (int) ($record['smsFailedCount'] ?? 0));
    $smsSkippedCount = max(0, (int) ($record['smsSkippedCount'] ?? 0));
    $smsAttempts = max(0, (int) ($record['smsAttempts'] ?? 0));
    $smsRequestedAt = $sendSms
        ? notifications_normalize_iso_datetime((string) ($record['smsRequestedAt'] ?? $createdAt))
        : '';
    if ($sendSms && $smsRequestedAt === '') {
        $smsRequestedAt = $createdAt;
    }
    $smsSentAt = $sendSms
        ? notifications_normalize_iso_datetime((string) ($record['smsSentAt'] ?? ''))
        : '';

    return [
        'id' => $id,
        'kind' => notifications_clean_kind((string) ($record['kind'] ?? '')),
        'title' => $title,
        'body' => $body,
        'tone' => notifications_clean_tone((string) ($record['tone'] ?? '')),
        'target' => $target,
        'cohortKey' => $cohortKey,
        'targetStudentNumber' => $targetStudentNumber,
        'source' => dent_clean_text((string) ($record['source'] ?? 'manager'), 40),
        'sourceKey' => dent_clean_text((string) ($record['sourceKey'] ?? ''), 220),
        'ctaLabel' => $ctaLabel,
        'ctaHref' => $ctaHref,
        'createdAt' => $createdAt,
        'publishAt' => $publishAt,
        'releasedAt' => $releasedAt,
        'status' => $status,
        'createdByStudentNumber' => dent_normalize_student_number((string) ($record['createdByStudentNumber'] ?? '')),
        'createdByName' => dent_clean_text((string) ($record['createdByName'] ?? ''), 120),
        'createdByRole' => dent_clean_text((string) ($record['createdByRole'] ?? ''), 40),
        'meta' => notifications_clean_meta(is_array($record['meta'] ?? null) ? $record['meta'] : [], $record),
        'recipients' => notifications_normalize_recipients(is_array($record['recipients'] ?? null) ? $record['recipients'] : []),
        'sendSms' => $sendSms,
        'smsStatus' => notifications_clean_sms_status((string) ($record['smsStatus'] ?? ''), $sendSms),
        'smsRequestedAt' => $smsRequestedAt,
        'smsSentAt' => $smsSentAt,
        'smsRecipientCount' => $smsRecipientCount,
        'smsEligibleCount' => $smsEligibleCount,
        'smsSentCount' => $smsSentCount,
        'smsFailedCount' => $smsFailedCount,
        'smsSkippedCount' => $smsSkippedCount,
        'smsAttempts' => $smsAttempts,
        'smsLastMessage' => dent_clean_text((string) ($record['smsLastMessage'] ?? ''), 220),
    ];
}

function notifications_normalize_user_state(string $studentNumber, array $state, array $validIds): ?array
{
    $normalizedStudentNumber = dent_normalize_student_number($studentNumber !== '' ? $studentNumber : (string) ($state['studentNumber'] ?? ''));
    if ($normalizedStudentNumber === '') {
        return null;
    }

    $readIds = [];
    $rawReadIds = is_array($state['readIds'] ?? null) ? $state['readIds'] : [];
    foreach ($rawReadIds as $id => $readAt) {
        $recordId = trim((string) $id);
        if ($recordId === '' || !isset($validIds[$recordId])) {
            continue;
        }
        $timestamp = notifications_normalize_iso_datetime((string) $readAt);
        $readIds[$recordId] = $timestamp !== '' ? $timestamp : dent_iso_now();
    }

    $preferences = is_array($state['preferences'] ?? null) ? $state['preferences'] : [];
    return [
        'studentNumber' => $normalizedStudentNumber,
        'readIds' => $readIds,
        'preferences' => [
            'navidAssignmentAlerts' => dent_parse_bool($preferences['navidAssignmentAlerts'] ?? null, true),
        ],
    ];
}

function notifications_normalize_suppressed_sources($raw): array
{
    $suppressed = [];
    if (!is_array($raw)) {
        return $suppressed;
    }

    foreach ($raw as $signature => $deletedAt) {
        $cleanSignature = trim((string) $signature);
        if ($cleanSignature === '' || !str_contains($cleanSignature, '|')) {
            continue;
        }
        $timestamp = notifications_normalize_iso_datetime((string) $deletedAt);
        $suppressed[$cleanSignature] = $timestamp !== '' ? $timestamp : dent_iso_now();
    }

    return $suppressed;
}

function notifications_record_effective_at(array $record): string
{
    $status = (string) ($record['status'] ?? DENT_NOTIFICATION_STATUS_ACTIVE);
    if ($status === DENT_NOTIFICATION_STATUS_SCHEDULED) {
        return (string) ($record['publishAt'] ?? $record['createdAt'] ?? '');
    }

    return (string) (($record['releasedAt'] ?? '') ?: ($record['publishAt'] ?? ($record['createdAt'] ?? '')));
}

function notifications_normalize_store(array $store): array
{
    $notifications = [];
    foreach (($store['notifications'] ?? []) as $key => $record) {
        if (!is_array($record)) {
            continue;
        }
        $normalized = notifications_normalize_record((string) $key, $record);
        if ($normalized === null) {
            continue;
        }
        $notifications[$normalized['id']] = $normalized;
    }

    uasort($notifications, static function (array $left, array $right): int {
        $effectiveCompare = strcmp(
            notifications_record_effective_at($right),
            notifications_record_effective_at($left)
        );
        if ($effectiveCompare !== 0) {
            return $effectiveCompare;
        }
        return strcmp((string) ($right['id'] ?? ''), (string) ($left['id'] ?? ''));
    });

    if (count($notifications) > DENT_NOTIFICATIONS_MAX_RECORDS) {
        $notifications = array_slice($notifications, 0, DENT_NOTIFICATIONS_MAX_RECORDS, true);
    }

    $validIds = array_fill_keys(array_keys($notifications), true);
    $userStates = [];
    foreach (($store['userStates'] ?? []) as $studentNumber => $state) {
        if (!is_array($state)) {
            continue;
        }
        $normalizedState = notifications_normalize_user_state((string) $studentNumber, $state, $validIds);
        if ($normalizedState === null) {
            continue;
        }
        $userStates[$normalizedState['studentNumber']] = $normalizedState;
    }

    return [
        'schemaVersion' => DENT_NOTIFICATIONS_SCHEMA_VERSION,
        'notifications' => $notifications,
        'userStates' => $userStates,
        'suppressedSources' => notifications_normalize_suppressed_sources($store['suppressedSources'] ?? []),
    ];
}

function notifications_default_preferences_for_user(array $user): array
{
    return [
        'navidAssignmentAlerts' => dent_user_cohort_key($user) === dent_primary_cohort_key(),
    ];
}

function notifications_user_state(array $store, array $user): array
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $defaults = notifications_default_preferences_for_user($user);
    if ($studentNumber === '') {
        return [
            'studentNumber' => '',
            'readIds' => [],
            'preferences' => $defaults,
        ];
    }

    $state = is_array($store['userStates'][$studentNumber] ?? null)
        ? $store['userStates'][$studentNumber]
        : [];

    $preferences = is_array($state['preferences'] ?? null) ? $state['preferences'] : [];
    return [
        'studentNumber' => $studentNumber,
        'readIds' => is_array($state['readIds'] ?? null) ? $state['readIds'] : [],
        'preferences' => [
            'navidAssignmentAlerts' => dent_parse_bool($preferences['navidAssignmentAlerts'] ?? null, $defaults['navidAssignmentAlerts']),
        ],
    ];
}

function notifications_user_can_broadcast(array $user, ?string $cohortKey = null): bool
{
    $role = dent_normalize_role((string) ($user['role'] ?? 'student'), (string) ($user['studentNumber'] ?? ''));
    if ($role === 'owner') {
        return true;
    }

    if (!in_array($role, ['representative', 'prosthesis_representative'], true)) {
        return false;
    }

    $targetCohortKey = dent_clean_cohort_key($cohortKey ?? dent_user_cohort_key($user));
    return $targetCohortKey !== '' && $targetCohortKey === dent_user_cohort_key($user);
}

function notifications_user_is_owner(array $user): bool
{
    return dent_normalize_role((string) ($user['role'] ?? 'student'), (string) ($user['studentNumber'] ?? '')) === 'owner';
}

function notifications_user_is_prosthesis(array $user): bool
{
    return dent_user_is_prosthesis($user);
}

function notifications_user_can_manage_record(array $user, array $record): bool
{
    if (!notifications_user_can_broadcast($user)) {
        return false;
    }

    if (notifications_user_is_owner($user)) {
        return true;
    }

    return (string) ($record['target'] ?? '') === DENT_NOTIFICATION_TARGET_COHORT
        && dent_user_cohort_key($user) === (string) ($record['cohortKey'] ?? '');
}

function notifications_record_is_scheduled(array $record): bool
{
    return (string) ($record['status'] ?? DENT_NOTIFICATION_STATUS_ACTIVE) === DENT_NOTIFICATION_STATUS_SCHEDULED;
}

function notifications_record_is_active(array $record): bool
{
    return !notifications_record_is_scheduled($record);
}

function notifications_record_matches_user(array $record, array $user): bool
{
    if (!notifications_record_is_active($record)) {
        return false;
    }

    $target = (string) ($record['target'] ?? DENT_NOTIFICATION_TARGET_ALL);
    if ($target === DENT_NOTIFICATION_TARGET_USER) {
        $targetStudentNumber = dent_normalize_student_number((string) ($record['targetStudentNumber'] ?? ''));
        if ($targetStudentNumber === '') {
            return false;
        }

        if (notifications_user_is_owner($user)) {
            return true;
        }

        return dent_normalize_student_number((string) ($user['studentNumber'] ?? '')) === $targetStudentNumber;
    }

    if (notifications_user_is_owner($user)) {
        return true;
    }

    if ($target === DENT_NOTIFICATION_TARGET_ALL) {
        return true;
    }

    if ($target === DENT_NOTIFICATION_TARGET_COHORT) {
        return dent_user_cohort_key($user) === (string) ($record['cohortKey'] ?? '');
    }

    return false;
}

function notifications_record_visible_to_user(array $record, array $user, array $preferences): bool
{
    if (!notifications_record_matches_user($record, $user)) {
        return false;
    }

    if ((string) ($record['kind'] ?? '') === DENT_NOTIFICATION_KIND_NAVID_ASSIGNMENT) {
        return !empty($preferences['navidAssignmentAlerts']);
    }

    return true;
}

function notifications_target_label(array $record): string
{
    $target = (string) ($record['target'] ?? DENT_NOTIFICATION_TARGET_ALL);
    if ($target === DENT_NOTIFICATION_TARGET_USER) {
        $targetStudentNumber = dent_normalize_student_number((string) ($record['targetStudentNumber'] ?? ''));
        if ($targetStudentNumber === dent_owner_student_number()) {
            return 'فقط مالک';
        }

        $targetUser = $targetStudentNumber !== '' ? dent_get_user_record($targetStudentNumber) : null;
        $targetName = trim((string) ($targetUser['name'] ?? ''));
        if ($targetName !== '') {
            return 'فقط ' . $targetName;
        }

        return 'دریافت‌کننده مستقیم';
    }

    if ($target === DENT_NOTIFICATION_TARGET_COHORT) {
        $cohort = dent_cohort_record((string) ($record['cohortKey'] ?? ''));
        return (string) ($cohort['title'] ?? ($record['cohortKey'] ?? 'ورودی'));
    }

    return 'همه ورودی‌ها';
}

function notifications_sender_label(array $record): string
{
    if ((string) ($record['source'] ?? '') === 'navid') {
        return 'سامانه نوید';
    }

    if ((string) ($record['source'] ?? '') === 'deploy') {
        return 'سامانه استقرار سایت';
    }

    $name = trim((string) ($record['createdByName'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    $role = trim((string) ($record['createdByRole'] ?? ''));
    return $role !== '' ? $role : 'اعلان سیستمی';
}

function notifications_snapshot_recipients_for_target(string $target, string $cohortKey = '', string $targetStudentNumber = ''): array
{
    $userStore = dent_load_user_store();
    $recipients = [];
    $targetStudentNumber = dent_normalize_student_number($targetStudentNumber);

    foreach (($userStore['users'] ?? []) as $user) {
        if (!is_array($user)) {
            continue;
        }

        $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
        if ($studentNumber === '') {
            continue;
        }

        $userCohortKey = dent_user_cohort_key($user);
        if ($target === DENT_NOTIFICATION_TARGET_USER) {
            if ($targetStudentNumber === '' || $studentNumber !== $targetStudentNumber) {
                continue;
            }
        } elseif ($studentNumber === dent_owner_student_number()) {
            continue;
        }

        if ($target === DENT_NOTIFICATION_TARGET_COHORT && $userCohortKey !== $cohortKey) {
            continue;
        }

        $recipients[] = [
            'studentNumber' => $studentNumber,
            'name' => (string) ($user['name'] ?? ''),
            'role' => dent_normalize_role((string) ($user['role'] ?? 'student'), $studentNumber),
            'cohortKey' => $userCohortKey,
        ];
    }

    return notifications_normalize_recipients($recipients);
}

function notifications_record_recipients(array $record): array
{
    $recipients = notifications_normalize_recipients(is_array($record['recipients'] ?? null) ? $record['recipients'] : []);
    if ($recipients) {
        return $recipients;
    }

    return notifications_snapshot_recipients_for_target(
        (string) ($record['target'] ?? DENT_NOTIFICATION_TARGET_ALL),
        (string) ($record['cohortKey'] ?? ''),
        (string) ($record['targetStudentNumber'] ?? '')
    );
}

function notifications_record_audience_counts(array $record, array $store): array
{
    $recipients = notifications_record_recipients($record);
    $viewedCount = 0;
    $recordId = (string) ($record['id'] ?? '');

    foreach ($recipients as $recipient) {
        $studentNumber = (string) ($recipient['studentNumber'] ?? '');
        if ($studentNumber === '') {
            continue;
        }
        $readAt = trim((string) ($store['userStates'][$studentNumber]['readIds'][$recordId] ?? ''));
        if ($readAt !== '') {
            $viewedCount++;
        }
    }

    return [
        'recipientCount' => count($recipients),
        'viewedCount' => $viewedCount,
        'pendingCount' => max(0, count($recipients) - $viewedCount),
    ];
}

function notifications_record_sms_payload(array $record): array
{
    return [
        'requested' => !empty($record['sendSms']),
        'status' => (string) ($record['smsStatus'] ?? DENT_NOTIFICATION_SMS_STATUS_NONE),
        'requestedAt' => (string) ($record['smsRequestedAt'] ?? ''),
        'sentAt' => (string) ($record['smsSentAt'] ?? ''),
        'recipientCount' => max(0, (int) ($record['smsRecipientCount'] ?? 0)),
        'eligibleCount' => max(0, (int) ($record['smsEligibleCount'] ?? 0)),
        'sentCount' => max(0, (int) ($record['smsSentCount'] ?? 0)),
        'failedCount' => max(0, (int) ($record['smsFailedCount'] ?? 0)),
        'skippedCount' => max(0, (int) ($record['smsSkippedCount'] ?? 0)),
        'attempts' => max(0, (int) ($record['smsAttempts'] ?? 0)),
        'lastMessage' => (string) ($record['smsLastMessage'] ?? ''),
    ];
}

function notifications_public_payload(array $record, array $user, array $store): array
{
    $userState = notifications_user_state($store, $user);
    $readIds = is_array($userState['readIds'] ?? null) ? $userState['readIds'] : [];
    $isScheduled = notifications_record_is_scheduled($record);
    $canManage = notifications_user_can_manage_record($user, $record);
    $audienceSummary = $canManage ? notifications_record_audience_counts($record, $store) : null;

    return [
        'id' => (string) ($record['id'] ?? ''),
        'kind' => (string) ($record['kind'] ?? ''),
        'title' => (string) ($record['title'] ?? ''),
        'body' => (string) ($record['body'] ?? ''),
        'tone' => (string) ($record['tone'] ?? 'accent'),
        'targetLabel' => notifications_target_label($record),
        'cohortKey' => (string) ($record['cohortKey'] ?? ''),
        'source' => (string) ($record['source'] ?? ''),
        'senderLabel' => notifications_sender_label($record),
        'ctaLabel' => (string) ($record['ctaLabel'] ?? ''),
        'ctaHref' => (string) ($record['ctaHref'] ?? ''),
        'createdAt' => (string) ($record['createdAt'] ?? ''),
        'publishAt' => (string) ($record['publishAt'] ?? ''),
        'effectiveAt' => notifications_record_effective_at($record),
        'state' => $isScheduled ? DENT_NOTIFICATION_STATUS_SCHEDULED : DENT_NOTIFICATION_STATUS_ACTIVE,
        'scheduled' => $isScheduled,
        'unread' => !$isScheduled && !isset($readIds[(string) ($record['id'] ?? '')]),
        'meta' => is_array($record['meta'] ?? null) ? $record['meta'] : [],
        'sms' => notifications_record_sms_payload($record),
        'manager' => [
            'canInspectAudience' => $canManage,
            'canDelete' => $canManage,
            'audienceSummary' => $audienceSummary,
        ],
    ];
}

function notifications_visible_records_for_user(array $store, array $user): array
{
    $userState = notifications_user_state($store, $user);
    $preferences = is_array($userState['preferences'] ?? null) ? $userState['preferences'] : [];
    $visible = [];

    foreach (($store['notifications'] ?? []) as $record) {
        if (!is_array($record)) {
            continue;
        }
        if (!notifications_record_visible_to_user($record, $user, $preferences)) {
            continue;
        }
        $visible[] = $record;
    }

    return $visible;
}

function notifications_feed_records_for_user(array $store, array $user): array
{
    $userState = notifications_user_state($store, $user);
    $preferences = is_array($userState['preferences'] ?? null) ? $userState['preferences'] : [];
    $active = [];
    $scheduled = [];

    foreach (($store['notifications'] ?? []) as $record) {
        if (!is_array($record)) {
            continue;
        }

        if (notifications_record_visible_to_user($record, $user, $preferences)) {
            $active[] = $record;
            continue;
        }

        if (notifications_record_is_scheduled($record) && notifications_user_can_manage_record($user, $record)) {
            $scheduled[] = $record;
        }
    }

    usort($scheduled, static function (array $left, array $right): int {
        $timestampCompare = notifications_timestamp((string) ($left['publishAt'] ?? ''))
            <=> notifications_timestamp((string) ($right['publishAt'] ?? ''));
        if ($timestampCompare !== 0) {
            return $timestampCompare;
        }
        return strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
    });

    return array_merge($scheduled, $active);
}

function notifications_summary_for_user(array $store, array $user): array
{
    $visible = notifications_visible_records_for_user($store, $user);
    $userState = notifications_user_state($store, $user);
    $readIds = is_array($userState['readIds'] ?? null) ? $userState['readIds'] : [];

    $unreadCount = 0;
    $announcementCount = 0;
    $navidCount = 0;
    foreach ($visible as $record) {
        $id = (string) ($record['id'] ?? '');
        if (!isset($readIds[$id])) {
            $unreadCount++;
        }
        if ((string) ($record['kind'] ?? '') === DENT_NOTIFICATION_KIND_NAVID_ASSIGNMENT) {
            $navidCount++;
        } else {
            $announcementCount++;
        }
    }

    $scheduledCount = 0;
    foreach (($store['notifications'] ?? []) as $record) {
        if (!is_array($record) || !notifications_record_is_scheduled($record)) {
            continue;
        }
        if (!notifications_user_can_manage_record($user, $record)) {
            continue;
        }
        $scheduledCount++;
    }

    $latest = $visible[0] ?? null;
    return [
        'unreadCount' => $unreadCount,
        'visibleCount' => count($visible),
        'announcementCount' => $announcementCount,
        'navidCount' => $navidCount,
        'scheduledCount' => $scheduledCount,
        'latestTitle' => $latest ? (string) ($latest['title'] ?? '') : '',
        'latestCreatedAt' => $latest ? notifications_record_effective_at($latest) : '',
    ];
}

function notifications_latest_unread_payload_for_user(array $store, array $user): ?array
{
    $userState = notifications_user_state($store, $user);
    $readIds = is_array($userState['readIds'] ?? null) ? $userState['readIds'] : [];

    foreach (notifications_visible_records_for_user($store, $user) as $record) {
        $id = (string) ($record['id'] ?? '');
        if ($id === '' || isset($readIds[$id])) {
            continue;
        }
        return notifications_public_payload($record, $user, $store);
    }

    return null;
}

function notifications_manager_targets_for_user(array $user): array
{
    $targets = [];
    if (!notifications_user_can_broadcast($user)) {
        return $targets;
    }

    if (notifications_user_is_owner($user)) {
        $targets[] = [
            'key' => 'all',
            'label' => 'همه ورودی‌ها',
            'target' => DENT_NOTIFICATION_TARGET_ALL,
        ];
    }

    foreach (dent_visible_cohorts_for_user($user) as $cohort) {
        $cohortKey = (string) ($cohort['key'] ?? '');
        if (!notifications_user_can_broadcast($user, $cohortKey)) {
            continue;
        }
        $targets[] = [
            'key' => $cohortKey,
            'label' => (string) ($cohort['title'] ?? $cohortKey),
            'target' => DENT_NOTIFICATION_TARGET_COHORT,
        ];
    }

    return $targets;
}

function notifications_manager_payload(array $user): array
{
    $targets = notifications_manager_targets_for_user($user);
    return [
        'canBroadcast' => count($targets) > 0,
        'targets' => $targets,
        'defaultTargetKey' => (string) (($targets[0]['key'] ?? '') ?: ''),
    ];
}

function notifications_preferences_payload(array $user, array $store): array
{
    $state = notifications_user_state($store, $user);
    $cohortKey = dent_user_cohort_key($user);
    return [
        'navidAssignmentAlerts' => !empty($state['preferences']['navidAssignmentAlerts']),
        'canToggleNavidAssignmentAlerts' => $cohortKey === dent_primary_cohort_key() && !notifications_user_is_prosthesis($user),
    ];
}

function notifications_list_payload_for_user(array $store, array $user, int $limit = 60): array
{
    $items = array_slice(notifications_feed_records_for_user($store, $user), 0, max(1, min(120, $limit)));
    return [
        'summary' => notifications_summary_for_user($store, $user),
        'preview' => notifications_latest_unread_payload_for_user($store, $user),
        'preferences' => notifications_preferences_payload($user, $store),
        'manager' => notifications_manager_payload($user),
        'items' => array_map(static function (array $record) use ($user, $store): array {
            return notifications_public_payload($record, $user, $store);
        }, $items),
    ];
}

function notifications_mark_read(array $user, array $ids): array
{
    return notifications_with_store_lock(static function (array &$store) use ($user, $ids): array {
        $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
        if ($studentNumber === '') {
            return notifications_summary_for_user($store, $user);
        }

        if (!isset($store['userStates'][$studentNumber]) || !is_array($store['userStates'][$studentNumber])) {
            $store['userStates'][$studentNumber] = [
                'studentNumber' => $studentNumber,
                'readIds' => [],
                'preferences' => notifications_default_preferences_for_user($user),
            ];
        }

        $visible = notifications_visible_records_for_user($store, $user);
        $visibleIndex = [];
        foreach ($visible as $record) {
            $visibleIndex[(string) ($record['id'] ?? '')] = true;
        }

        foreach ($ids as $id) {
            $recordId = trim((string) $id);
            if ($recordId === '' || !isset($visibleIndex[$recordId])) {
                continue;
            }
            $store['userStates'][$studentNumber]['readIds'][$recordId] = dent_iso_now();
        }

        return notifications_summary_for_user($store, $user);
    });
}

function notifications_mark_all_read(array $user): array
{
    return notifications_with_store_lock(static function (array &$store) use ($user): array {
        $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
        if ($studentNumber === '') {
            return notifications_summary_for_user($store, $user);
        }

        if (!isset($store['userStates'][$studentNumber]) || !is_array($store['userStates'][$studentNumber])) {
            $store['userStates'][$studentNumber] = [
                'studentNumber' => $studentNumber,
                'readIds' => [],
                'preferences' => notifications_default_preferences_for_user($user),
            ];
        }

        foreach (notifications_visible_records_for_user($store, $user) as $record) {
            $recordId = (string) ($record['id'] ?? '');
            if ($recordId === '') {
                continue;
            }
            $store['userStates'][$studentNumber]['readIds'][$recordId] = dent_iso_now();
        }

        return notifications_summary_for_user($store, $user);
    });
}

function notifications_save_preferences(array $user, array $payload): array
{
    return notifications_with_store_lock(static function (array &$store) use ($user, $payload): array {
        $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
        if ($studentNumber === '') {
            return notifications_preferences_payload($user, $store);
        }

        $current = notifications_user_state($store, $user);
        if (!isset($store['userStates'][$studentNumber]) || !is_array($store['userStates'][$studentNumber])) {
            $store['userStates'][$studentNumber] = [
                'studentNumber' => $studentNumber,
                'readIds' => [],
                'preferences' => $current['preferences'],
            ];
        }

        $canToggleNavid = dent_user_cohort_key($user) === dent_primary_cohort_key() && !notifications_user_is_prosthesis($user);
        if ($canToggleNavid && array_key_exists('navidAssignmentAlerts', $payload)) {
            $store['userStates'][$studentNumber]['preferences']['navidAssignmentAlerts'] = dent_parse_bool(
                $payload['navidAssignmentAlerts'],
                !empty($current['preferences']['navidAssignmentAlerts'])
            );
        }

        return notifications_preferences_payload($user, $store);
    });
}

function notifications_absolute_url(string $path): string
{
    $cleanPath = notifications_clean_cta_href($path);
    if ($cleanPath === '') {
        return '';
    }

    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return $cleanPath;
    }

    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $secure = $forwardedProto === 'https' || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
    return ($secure ? 'https://' : 'http://') . $host . $cleanPath;
}

function notifications_sms_text_for_record(array $record): string
{
    $parts = [];
    $title = trim((string) ($record['title'] ?? ''));
    $body = trim((string) ($record['body'] ?? ''));
    $link = notifications_absolute_url((string) (($record['ctaHref'] ?? '') ?: '/account/#notifications'));

    if ($title !== '') {
        $parts[] = $title;
    }
    if ($body !== '') {
        $parts[] = $body;
    }
    if ($link !== '') {
        $parts[] = $link;
    }

    return dent_clean_text(implode("\n", $parts), 480);
}

function notifications_sms_config_state(): array
{
    $config = dent_sms_resolved_config();
    $errors = [];

    if (!(bool) ($config['enabled'] ?? false)) {
        $errors[] = 'سرویس پیامکی غیرفعال است.';
    }
    if (trim((string) ($config['apiKey'] ?? '')) === '') {
        $errors[] = 'کلید API سرویس پیامکی تنظیم نشده است.';
    }
    if (trim((string) ($config['senderLine'] ?? '')) === '') {
        $errors[] = 'لاین/شماره ارسال پیامک تنظیم نشده است.';
    }

    return [
        'ok' => $errors === [],
        'message' => $errors === [] ? '' : implode(' ', $errors),
    ];
}

function notifications_assert_sms_ready(): void
{
    $state = notifications_sms_config_state();
    if (!$state['ok']) {
        dent_error((string) ($state['message'] ?? 'سرویس پیامکی آماده نیست.'), 422);
    }
}

function notifications_sms_phone_targets(array $record): array
{
    $userStore = dent_load_user_store();
    $userIndex = is_array($userStore['users'] ?? null) ? $userStore['users'] : [];
    $phones = [];
    $eligibleCount = 0;
    $skippedCount = 0;
    $recipientCount = 0;

    foreach (notifications_record_recipients($record) as $recipient) {
        $recipientCount++;
        $studentNumber = (string) ($recipient['studentNumber'] ?? '');
        if ($studentNumber === '' || !is_array($userIndex[$studentNumber] ?? null)) {
            $skippedCount++;
            continue;
        }

        $user = $userIndex[$studentNumber];
        $phone = dent_normalize_phone_number((string) ($user['phoneNumber'] ?? ''));
        $verifiedAt = trim((string) ($user['phoneVerifiedAt'] ?? ''));
        $providerPhone = dent_sms_provider_recipient_number($phone);
        if ($phone === '' || $verifiedAt === '' || $providerPhone === '') {
            $skippedCount++;
            continue;
        }

        if (!isset($phones[$providerPhone])) {
            $phones[$providerPhone] = true;
            $eligibleCount++;
        }
    }

    return [
        'phones' => array_keys($phones),
        'recipientCount' => $recipientCount,
        'eligibleCount' => $eligibleCount,
        'skippedCount' => $skippedCount,
    ];
}

function notifications_dispatch_sms_if_needed(string $notificationId): array
{
    $notificationId = trim($notificationId);
    if ($notificationId === '') {
        return ['success' => false, 'reason' => 'invalid-id'];
    }

    $dispatch = notifications_with_store_lock(static function (array &$store) use ($notificationId): array {
        $record = is_array($store['notifications'][$notificationId] ?? null)
            ? $store['notifications'][$notificationId]
            : null;
        if ($record === null) {
            return ['success' => false, 'reason' => 'missing'];
        }
        if (notifications_record_is_scheduled($record)) {
            return ['success' => false, 'reason' => 'scheduled'];
        }
        if (empty($record['sendSms'])) {
            return ['success' => false, 'reason' => 'sms-not-requested'];
        }

        $currentStatus = (string) ($record['smsStatus'] ?? DENT_NOTIFICATION_SMS_STATUS_NONE);
        if (in_array($currentStatus, [
            DENT_NOTIFICATION_SMS_STATUS_SENDING,
            DENT_NOTIFICATION_SMS_STATUS_SENT,
            DENT_NOTIFICATION_SMS_STATUS_PARTIAL,
            DENT_NOTIFICATION_SMS_STATUS_FAILED,
        ], true)) {
            return ['success' => false, 'reason' => 'already-processed'];
        }

        $targets = notifications_sms_phone_targets($record);
        $record['smsStatus'] = DENT_NOTIFICATION_SMS_STATUS_SENDING;
        $record['smsAttempts'] = max(0, (int) ($record['smsAttempts'] ?? 0)) + 1;
        $record['smsRecipientCount'] = (int) ($targets['recipientCount'] ?? 0);
        $record['smsEligibleCount'] = (int) ($targets['eligibleCount'] ?? 0);
        $record['smsSkippedCount'] = (int) ($targets['skippedCount'] ?? 0);
        $record['smsSentCount'] = 0;
        $record['smsFailedCount'] = 0;
        $store['notifications'][$notificationId] = $record;

        return [
            'success' => true,
            'record' => $record,
            'targets' => $targets,
            'phones' => is_array($targets['phones'] ?? null) ? $targets['phones'] : [],
            'text' => notifications_sms_text_for_record($record),
        ];
    });

    if (empty($dispatch['success'])) {
        return $dispatch;
    }

    $targets = is_array($dispatch['targets'] ?? null) ? $dispatch['targets'] : [];
    $phones = is_array($dispatch['phones'] ?? null) ? $dispatch['phones'] : [];
    if (!$phones) {
        notifications_with_store_lock(static function (array &$store) use ($notificationId, $targets): void {
            if (!is_array($store['notifications'][$notificationId] ?? null)) {
                return;
            }
            $record = $store['notifications'][$notificationId];
            $record['smsStatus'] = DENT_NOTIFICATION_SMS_STATUS_FAILED;
            $record['smsSentCount'] = 0;
            $record['smsFailedCount'] = 0;
            $record['smsRecipientCount'] = (int) ($targets['recipientCount'] ?? 0);
            $record['smsEligibleCount'] = 0;
            $record['smsSkippedCount'] = (int) ($targets['skippedCount'] ?? 0);
            $record['smsLastMessage'] = 'برای هیچ‌کدام از مخاطبان مقصد شماره تاییدشده پیدا نشد.';
            $store['notifications'][$notificationId] = $record;
        });

        return ['success' => false, 'reason' => 'no-verified-phone'];
    }

    $sendResult = dent_sms_send_simple($phones, (string) ($dispatch['text'] ?? ''));
    notifications_with_store_lock(static function (array &$store) use ($notificationId, $targets, $sendResult): void {
        if (!is_array($store['notifications'][$notificationId] ?? null)) {
            return;
        }

        $record = $store['notifications'][$notificationId];
        $record['smsRecipientCount'] = (int) ($targets['recipientCount'] ?? 0);
        $record['smsEligibleCount'] = (int) ($targets['eligibleCount'] ?? 0);
        $record['smsSkippedCount'] = (int) ($targets['skippedCount'] ?? 0);
        $record['smsLastMessage'] = dent_clean_text((string) ($sendResult['message'] ?? ''), 220);

        if (!empty($sendResult['success'])) {
            $record['smsSentAt'] = dent_iso_now();
            $record['smsSentCount'] = (int) ($targets['eligibleCount'] ?? 0);
            $record['smsFailedCount'] = 0;
            $record['smsStatus'] = ((int) ($targets['skippedCount'] ?? 0)) > 0
                ? DENT_NOTIFICATION_SMS_STATUS_PARTIAL
                : DENT_NOTIFICATION_SMS_STATUS_SENT;
        } else {
            $record['smsSentAt'] = '';
            $record['smsSentCount'] = 0;
            $record['smsFailedCount'] = (int) ($targets['eligibleCount'] ?? 0);
            $record['smsStatus'] = DENT_NOTIFICATION_SMS_STATUS_FAILED;
        }

        $store['notifications'][$notificationId] = $record;
    });

    return $sendResult;
}

function notifications_process_due_queue(): array
{
    $dispatchIds = notifications_with_store_lock(static function (array &$store): array {
        $now = time();
        $dispatch = [];

        foreach (($store['notifications'] ?? []) as $id => $record) {
            if (!is_array($record) || !notifications_record_is_scheduled($record)) {
                continue;
            }

            $publishTimestamp = notifications_timestamp((string) ($record['publishAt'] ?? ''));
            if ($publishTimestamp <= 0 || $publishTimestamp > $now) {
                continue;
            }

            $record['status'] = DENT_NOTIFICATION_STATUS_ACTIVE;
            $record['releasedAt'] = (string) ($record['publishAt'] ?? dent_iso_now());
            $store['notifications'][$id] = $record;

            if (!empty($record['sendSms']) && (string) ($record['smsStatus'] ?? '') === DENT_NOTIFICATION_SMS_STATUS_PENDING) {
                $dispatch[] = (string) $id;
            }
        }

        return $dispatch;
    });

    foreach ($dispatchIds as $notificationId) {
        notifications_dispatch_sms_if_needed((string) $notificationId);
    }

    return $dispatchIds;
}

function notifications_create_broadcast(array $viewer, array $payload): array
{
    if (!notifications_user_can_broadcast($viewer)) {
        dent_error('ارسال اعلان فقط برای مالک یا نماینده مجاز همان ورودی فعال است.', 403);
    }

    $title = dent_clean_text((string) ($payload['title'] ?? ''), 180);
    $body = dent_clean_text((string) ($payload['body'] ?? ''), 4000);
    if ($title === '' && $body === '') {
        dent_error('عنوان یا متن اعلان را وارد کن.', 422);
    }

    $targetKey = trim((string) ($payload['targetKey'] ?? ''));
    $target = $targetKey === 'all' ? DENT_NOTIFICATION_TARGET_ALL : DENT_NOTIFICATION_TARGET_COHORT;
    $cohortKey = '';
    if ($target === DENT_NOTIFICATION_TARGET_ALL) {
        if (!notifications_user_is_owner($viewer)) {
            dent_error('ارسال اعلان برای همه ورودی‌ها فقط برای مالک فعال است.', 403);
        }
    } else {
        $cohortKey = dent_clean_cohort_key($targetKey !== '' ? $targetKey : dent_user_cohort_key($viewer));
        if ($cohortKey === '' || !notifications_user_can_broadcast($viewer, $cohortKey) || !dent_cohort_exists($cohortKey)) {
            dent_error('این مقصد برای حساب شما مجاز نیست.', 403);
        }
    }

    $ctaHref = notifications_clean_cta_href((string) ($payload['ctaHref'] ?? ''));
    $ctaLabel = $ctaHref !== ''
        ? dent_clean_text((string) ($payload['ctaLabel'] ?? ''), 80)
        : '';
    if ($ctaHref !== '' && $ctaLabel === '') {
        $ctaLabel = 'مشاهده';
    }

    $publishAtInput = (string) ($payload['scheduleAt'] ?? ($payload['publishAt'] ?? ''));
    $publishAt = trim($publishAtInput) === '' ? '' : notifications_normalize_iso_datetime($publishAtInput);
    if (trim($publishAtInput) !== '' && $publishAt === '') {
        dent_error('زمان‌بندی اعلان معتبر نیست.', 422);
    }
    $scheduled = $publishAt !== '' && notifications_timestamp($publishAt) > time();
    if ($publishAt === '') {
        $publishAt = dent_iso_now();
    }

    $recipients = notifications_snapshot_recipients_for_target($target, $cohortKey);
    if (!$recipients) {
        dent_error('برای مقصد انتخاب‌شده کاربری پیدا نشد.', 422);
    }

    $sendSms = dent_parse_bool($payload['sendSms'] ?? null, false);
    if ($sendSms) {
        notifications_assert_sms_ready();
    }

    $viewerRole = dent_role_label((string) ($viewer['role'] ?? 'student'));
    $record = notifications_with_store_lock(static function (array &$store) use (
        $title,
        $body,
        $target,
        $cohortKey,
        $ctaHref,
        $ctaLabel,
        $viewer,
        $viewerRole,
        $publishAt,
        $scheduled,
        $recipients,
        $sendSms
    ): array {
        $id = notifications_generate_id();
        $record = notifications_normalize_record($id, [
            'id' => $id,
            'kind' => DENT_NOTIFICATION_KIND_ANNOUNCEMENT,
            'title' => $title,
            'body' => $body,
            'tone' => 'accent',
            'target' => $target,
            'cohortKey' => $cohortKey,
            'source' => 'manager',
            'sourceKey' => '',
            'ctaHref' => $ctaHref,
            'ctaLabel' => $ctaLabel,
            'createdAt' => dent_iso_now(),
            'publishAt' => $publishAt,
            'releasedAt' => $scheduled ? '' : $publishAt,
            'status' => $scheduled ? DENT_NOTIFICATION_STATUS_SCHEDULED : DENT_NOTIFICATION_STATUS_ACTIVE,
            'createdByStudentNumber' => (string) ($viewer['studentNumber'] ?? ''),
            'createdByName' => (string) ($viewer['name'] ?? ''),
            'createdByRole' => $viewerRole,
            'meta' => [],
            'recipients' => $recipients,
            'sendSms' => $sendSms,
            'smsStatus' => $sendSms ? DENT_NOTIFICATION_SMS_STATUS_PENDING : DENT_NOTIFICATION_SMS_STATUS_NONE,
            'smsRequestedAt' => $sendSms ? dent_iso_now() : '',
            'smsRecipientCount' => count($recipients),
            'smsEligibleCount' => 0,
            'smsSentCount' => 0,
            'smsFailedCount' => 0,
            'smsSkippedCount' => 0,
            'smsAttempts' => 0,
            'smsLastMessage' => '',
        ]);
        if ($record === null) {
            dent_error('اعلان قابل ذخیره‌سازی نبود.', 500);
        }

        $store['notifications'][$record['id']] = $record;
        return $record;
    });

    if (!$scheduled && $sendSms) {
        notifications_dispatch_sms_if_needed((string) ($record['id'] ?? ''));
    }

    $latestStore = notifications_read_store();
    $latestRecord = is_array($latestStore['notifications'][$record['id']] ?? null)
        ? $latestStore['notifications'][$record['id']]
        : $record;

    return $latestRecord;
}

function notifications_build_owner_deploy_notice_body(string $version, string $deployedAt, string $branch = '', string $deployHead = ''): string
{
    $deployedLabel = notifications_format_fa_tehran_datetime($deployedAt, true);
    $lines = [
        'استقرار جدید سایت با موفقیت انجام شد.',
        'نسخه فعال: ' . dent_to_fa_digits($version),
        'زمان استقرار (ایران): ' . $deployedLabel,
    ];

    $branch = trim($branch);
    if ($branch !== '') {
        $lines[] = 'شاخه استقرار: ' . $branch;
    }

    $deployHead = trim($deployHead);
    if ($deployHead !== '') {
        $lines[] = 'کد استقرار: ' . substr($deployHead, 0, 12);
    }

    return implode("\n", $lines);
}

function notifications_gregorian_to_jalali(int $gregorianYear, int $gregorianMonth, int $gregorianDay): array
{
    $monthDaySums = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $jalaliYear = $gregorianYear <= 1600 ? 0 : 979;
    $normalizedYear = $gregorianYear <= 1600 ? $gregorianYear - 621 : $gregorianYear - 1600;
    $leapAdjustedYear = $gregorianMonth > 2 ? $normalizedYear + 1 : $normalizedYear;
    $days = (365 * $normalizedYear)
        + intdiv($leapAdjustedYear + 3, 4)
        - intdiv($leapAdjustedYear + 99, 100)
        + intdiv($leapAdjustedYear + 399, 400)
        - 80
        + $gregorianDay
        + $monthDaySums[$gregorianMonth - 1];

    $jalaliYear += 33 * intdiv($days, 12053);
    $days %= 12053;
    $jalaliYear += 4 * intdiv($days, 1461);
    $days %= 1461;

    if ($days > 365) {
        $jalaliYear += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }

    if ($days < 186) {
        $jalaliMonth = 1 + intdiv($days, 31);
        $jalaliDay = 1 + ($days % 31);
    } else {
        $jalaliMonth = 7 + intdiv($days - 186, 30);
        $jalaliDay = 1 + (($days - 186) % 30);
    }

    return [$jalaliYear, $jalaliMonth, $jalaliDay];
}

function notifications_format_fa_tehran_datetime(string $value, bool $includeSeconds = false): string
{
    $normalized = notifications_normalize_iso_datetime($value);
    if ($normalized === '') {
        return dent_to_fa_digits(trim($value));
    }

    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return dent_to_fa_digits(trim($value));
    }

    try {
        $date = new DateTimeImmutable('@' . $timestamp);
        $date = $date->setTimezone(new DateTimeZone('Asia/Tehran'));
        [$jalaliYear, $jalaliMonth, $jalaliDay] = notifications_gregorian_to_jalali(
            (int) $date->format('Y'),
            (int) $date->format('n'),
            (int) $date->format('j')
        );
        $timeLabel = $includeSeconds
            ? sprintf(
                '%04d/%02d/%02d ساعت %02d:%02d:%02d',
                $jalaliYear,
                $jalaliMonth,
                $jalaliDay,
                (int) $date->format('H'),
                (int) $date->format('i'),
                (int) $date->format('s')
            )
            : sprintf(
                '%04d/%02d/%02d ساعت %02d:%02d',
                $jalaliYear,
                $jalaliMonth,
                $jalaliDay,
                (int) $date->format('H'),
                (int) $date->format('i')
            );
        return dent_to_fa_digits($timeLabel);
    } catch (Throwable $error) {
        return dent_to_fa_digits(trim($value));
    }
}

function notifications_create_owner_deploy_notice(array $viewer, array $payload): array
{
    if (!notifications_user_is_owner($viewer)) {
        dent_error('ثبت اعلان استقرار فقط برای مالک سامانه مجاز است.', 403);
    }

    $version = dent_clean_text((string) ($payload['version'] ?? ''), 80);
    $deployedAt = notifications_normalize_iso_datetime((string) ($payload['deployedAt'] ?? ''));
    $branch = dent_clean_text((string) ($payload['branch'] ?? ''), 120);
    $deployHead = dent_clean_text((string) ($payload['deployHead'] ?? ''), 80);

    if ($version === '') {
        dent_error('نسخه استقرار برای اعلان مالک نامعتبر است.', 422);
    }
    if ($deployedAt === '') {
        dent_error('زمان استقرار برای اعلان مالک نامعتبر است.', 422);
    }

    $recipientStudentNumber = dent_owner_student_number();
    $recipients = notifications_snapshot_recipients_for_target(
        DENT_NOTIFICATION_TARGET_USER,
        '',
        $recipientStudentNumber
    );
    if (!$recipients) {
        dent_error('گیرنده اعلان استقرار مالک پیدا نشد.', 500);
    }

    $title = dent_clean_text((string) ($payload['title'] ?? ''), 180);
    if ($title === '') {
        $title = 'استقرار جدید سایت انجام شد';
    }

    $body = dent_clean_text((string) ($payload['body'] ?? ''), 4000);
    if ($body === '') {
        $body = notifications_build_owner_deploy_notice_body($version, $deployedAt, $branch, $deployHead);
    }

    $record = notifications_with_store_lock(static function (array &$store) use (
        $title,
        $body,
        $version,
        $deployedAt,
        $branch,
        $deployHead,
        $recipientStudentNumber,
        $recipients
    ): array {
        $id = notifications_generate_id();
        $record = notifications_normalize_record($id, [
            'id' => $id,
            'kind' => DENT_NOTIFICATION_KIND_ANNOUNCEMENT,
            'title' => $title,
            'body' => $body,
            'tone' => 'ok',
            'target' => DENT_NOTIFICATION_TARGET_USER,
            'targetStudentNumber' => $recipientStudentNumber,
            'source' => 'deploy',
            'sourceKey' => 'deploy:' . $version . ':' . $deployedAt,
            'createdAt' => $deployedAt,
            'publishAt' => $deployedAt,
            'releasedAt' => $deployedAt,
            'status' => DENT_NOTIFICATION_STATUS_ACTIVE,
            'createdByStudentNumber' => $recipientStudentNumber,
            'createdByName' => 'استقرار خودکار',
            'createdByRole' => 'سیستم',
            'meta' => [
                'version' => $version,
                'deployedAt' => $deployedAt,
                'branch' => $branch,
                'deployHead' => $deployHead,
            ],
            'recipients' => $recipients,
            'sendSms' => false,
            'smsStatus' => DENT_NOTIFICATION_SMS_STATUS_NONE,
        ]);
        if ($record === null) {
            dent_error('اعلان استقرار مالک قابل ذخیره‌سازی نبود.', 500);
        }

        $store['notifications'][$record['id']] = $record;
        return $record;
    });

    $latestStore = notifications_read_store();
    $recordId = (string) ($record['id'] ?? '');
    return is_array($latestStore['notifications'][$recordId] ?? null)
        ? $latestStore['notifications'][$recordId]
        : $record;
}

function notifications_write_store_locked_best_effort(array $store): bool
{
    $normalizedStore = notifications_normalize_store($store);
    $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $json = json_encode($normalizedStore, $flags);
    if ($json === false) {
        return false;
    }

    $path = notifications_store_path();
    $tmpPath = $path . '.tmp-' . preg_replace('/[^a-z0-9]+/i', '', uniqid('', true));
    if (@file_put_contents($tmpPath, $json . PHP_EOL) === false) {
        @unlink($tmpPath);
        return false;
    }

    if (!@rename($tmpPath, $path)) {
        @unlink($tmpPath);
        return false;
    }

    return true;
}

function notifications_try_create_chat_mention_notice(array $viewer, array $payload): ?array
{
    $recipientStudentNumber = dent_normalize_student_number((string) ($payload['targetStudentNumber'] ?? ''));
    $senderStudentNumber = dent_normalize_student_number((string) ($viewer['studentNumber'] ?? ''));
    $conversationId = dent_clean_text((string) ($payload['conversationId'] ?? ''), 120);
    $messageId = max(0, (int) ($payload['messageId'] ?? 0));
    $title = dent_clean_text((string) ($payload['title'] ?? ''), 180);
    $body = dent_clean_text((string) ($payload['body'] ?? ''), 4000);
    $ctaHref = notifications_clean_cta_href((string) ($payload['ctaHref'] ?? ''));
    $ctaLabel = $ctaHref !== ''
        ? dent_clean_text((string) ($payload['ctaLabel'] ?? ''), 80)
        : '';
    $conversationTitle = dent_clean_text((string) ($payload['conversationTitle'] ?? ''), 160);
    $previewText = dent_clean_text((string) ($payload['previewText'] ?? ''), 320);
    $createdAt = notifications_normalize_iso_datetime((string) ($payload['createdAt'] ?? ''));

    if ($recipientStudentNumber === '' || $recipientStudentNumber === $senderStudentNumber || $conversationId === '' || $messageId <= 0) {
        return null;
    }

    if ($title === '' || $body === '') {
        return null;
    }

    if ($createdAt === '') {
        $createdAt = dent_iso_now();
    }

    if ($ctaHref === '' || $ctaLabel === '') {
        $ctaHref = '';
        $ctaLabel = '';
    }

    $recipients = notifications_snapshot_recipients_for_target(
        DENT_NOTIFICATION_TARGET_USER,
        '',
        $recipientStudentNumber
    );
    if ($recipients === []) {
        return null;
    }

    notifications_ensure_storage();
    $lock = @fopen(notifications_store_lock_path(), 'c+');
    if ($lock === false) {
        return null;
    }

    try {
        if (!@flock($lock, LOCK_EX)) {
            return null;
        }

        $store = notifications_load_store_unlocked();
        $sourceKey = 'chat-mention:' . $conversationId . ':' . $messageId . ':' . $recipientStudentNumber;
        $sourceSignature = notifications_source_signature('chat', $sourceKey);
        if ($sourceSignature !== '' && isset($store['suppressedSources'][$sourceSignature])) {
            return null;
        }

        foreach (($store['notifications'] ?? []) as $existingRecord) {
            if (!is_array($existingRecord)) {
                continue;
            }
            if ((string) ($existingRecord['source'] ?? '') === 'chat' && (string) ($existingRecord['sourceKey'] ?? '') === $sourceKey) {
                return $existingRecord;
            }
        }

        $id = notifications_generate_id();
        $record = notifications_normalize_record($id, [
            'id' => $id,
            'kind' => DENT_NOTIFICATION_KIND_ANNOUNCEMENT,
            'title' => $title,
            'body' => $body,
            'tone' => 'warn',
            'target' => DENT_NOTIFICATION_TARGET_USER,
            'targetStudentNumber' => $recipientStudentNumber,
            'source' => 'chat',
            'sourceKey' => $sourceKey,
            'ctaHref' => $ctaHref,
            'ctaLabel' => $ctaLabel,
            'createdAt' => $createdAt,
            'publishAt' => $createdAt,
            'releasedAt' => $createdAt,
            'status' => DENT_NOTIFICATION_STATUS_ACTIVE,
            'createdByStudentNumber' => $senderStudentNumber,
            'createdByName' => (string) ($viewer['name'] ?? ''),
            'createdByRole' => dent_role_label((string) ($viewer['role'] ?? 'student')),
            'meta' => [
                'conversationId' => $conversationId,
                'conversationTitle' => $conversationTitle,
                'messageId' => $messageId,
                'previewText' => $previewText,
                'mentionType' => 'important',
            ],
            'recipients' => $recipients,
            'sendSms' => false,
            'smsStatus' => DENT_NOTIFICATION_SMS_STATUS_NONE,
        ]);
        if ($record === null) {
            return null;
        }

        $store['notifications'][$record['id']] = $record;
        if (!notifications_write_store_locked_best_effort($store)) {
            unset($store['notifications'][$record['id']]);
            return null;
        }

        return $record;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function notifications_audience_payload(array $viewer, string $notificationId): array
{
    $notificationId = trim($notificationId);
    if ($notificationId === '') {
        dent_error('شناسه اعلان نامعتبر است.', 422);
    }

    $store = notifications_read_store();
    $record = is_array($store['notifications'][$notificationId] ?? null)
        ? $store['notifications'][$notificationId]
        : null;
    if ($record === null) {
        dent_error('اعلان موردنظر پیدا نشد.', 404);
    }
    if (!notifications_user_can_manage_record($viewer, $record)) {
        dent_error('مدیریت وضعیت این اعلان برای حساب شما مجاز نیست.', 403);
    }

    $userStore = dent_load_user_store();
    $userIndex = is_array($userStore['users'] ?? null) ? $userStore['users'] : [];
    $recipients = notifications_record_recipients($record);
    $recordId = (string) ($record['id'] ?? '');
    $viewed = [];
    $pending = [];
    $verifiedPhoneCount = 0;

    foreach ($recipients as $recipient) {
        $studentNumber = (string) ($recipient['studentNumber'] ?? '');
        if ($studentNumber === '') {
            continue;
        }

        $currentUser = is_array($userIndex[$studentNumber] ?? null) ? $userIndex[$studentNumber] : null;
        $role = $currentUser !== null
            ? dent_normalize_role((string) ($currentUser['role'] ?? ($recipient['role'] ?? 'student')), $studentNumber)
            : dent_normalize_role((string) ($recipient['role'] ?? 'student'), $studentNumber);
        $cohortKey = $currentUser !== null
            ? dent_user_cohort_key($currentUser)
            : dent_clean_cohort_key((string) ($recipient['cohortKey'] ?? ''));
        $cohort = $cohortKey !== '' ? dent_cohort_record($cohortKey) : null;
        $phone = $currentUser !== null ? dent_normalize_phone_number((string) ($currentUser['phoneNumber'] ?? '')) : '';
        $phoneVerified = $currentUser !== null && trim((string) ($currentUser['phoneVerifiedAt'] ?? '')) !== '';
        if ($phone !== '' && $phoneVerified) {
            $verifiedPhoneCount++;
        }

        $entry = [
            'studentNumber' => $studentNumber,
            'name' => (string) (($currentUser['name'] ?? '') ?: ($recipient['name'] ?? '') ?: $studentNumber),
            'roleLabel' => dent_role_label($role),
            'cohortLabel' => (string) ($cohort['title'] ?? ($cohortKey !== '' ? $cohortKey : 'بدون ورودی')),
            'hasVerifiedPhone' => $phone !== '' && $phoneVerified,
            'phoneMasked' => ($phone !== '' && $phoneVerified) ? dent_mask_phone_number($phone) : '',
        ];

        $readAt = notifications_normalize_iso_datetime((string) ($store['userStates'][$studentNumber]['readIds'][$recordId] ?? ''));
        if ($readAt !== '') {
            $entry['readAt'] = $readAt;
            $viewed[] = $entry;
        } else {
            $pending[] = $entry;
        }
    }

    usort($viewed, static function (array $left, array $right): int {
        $timeCompare = strcmp((string) ($right['readAt'] ?? ''), (string) ($left['readAt'] ?? ''));
        if ($timeCompare !== 0) {
            return $timeCompare;
        }
        return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
    });

    usort($pending, static function (array $left, array $right): int {
        $nameCompare = strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        if ($nameCompare !== 0) {
            return $nameCompare;
        }
        return strcmp((string) ($left['studentNumber'] ?? ''), (string) ($right['studentNumber'] ?? ''));
    });

    return [
        'record' => [
            'id' => $recordId,
            'title' => (string) ($record['title'] ?? ''),
            'state' => notifications_record_is_scheduled($record) ? DENT_NOTIFICATION_STATUS_SCHEDULED : DENT_NOTIFICATION_STATUS_ACTIVE,
            'publishAt' => (string) ($record['publishAt'] ?? ''),
            'effectiveAt' => notifications_record_effective_at($record),
            'targetLabel' => notifications_target_label($record),
            'senderLabel' => notifications_sender_label($record),
        ],
        'summary' => [
            'recipientCount' => count($recipients),
            'viewedCount' => count($viewed),
            'pendingCount' => count($pending),
            'verifiedPhoneCount' => $verifiedPhoneCount,
        ],
        'sms' => notifications_record_sms_payload($record),
        'viewed' => $viewed,
        'pending' => $pending,
    ];
}

function notifications_delete_record(array $viewer, string $notificationId): array
{
    $notificationId = trim($notificationId);
    if ($notificationId === '') {
        dent_error('شناسه اعلان نامعتبر است.', 422);
    }

    return notifications_with_store_lock(static function (array &$store) use ($viewer, $notificationId): array {
        $record = is_array($store['notifications'][$notificationId] ?? null)
            ? $store['notifications'][$notificationId]
            : null;
        if ($record === null) {
            dent_error('اعلان موردنظر پیدا نشد.', 404);
        }
        if (!notifications_user_can_manage_record($viewer, $record)) {
            dent_error('حذف این اعلان برای حساب شما مجاز نیست.', 403);
        }

        $sourceSignature = notifications_source_signature(
            (string) ($record['source'] ?? ''),
            (string) ($record['sourceKey'] ?? '')
        );
        if ($sourceSignature !== '') {
            $store['suppressedSources'][$sourceSignature] = dent_iso_now();
        }

        unset($store['notifications'][$notificationId]);
        foreach (($store['userStates'] ?? []) as $studentNumber => $state) {
            if (!is_array($state) || !is_array($state['readIds'] ?? null)) {
                continue;
            }
            unset($store['userStates'][$studentNumber]['readIds'][$notificationId]);
        }

        return $record;
    });
}

function notifications_enqueue_navid_assignment(array $assignment): ?array
{
    $assignmentKey = dent_clean_text((string) ($assignment['assignmentKey'] ?? ''), 120);
    if ($assignmentKey === '') {
        return null;
    }

    $courseTitle = dent_clean_text((string) ($assignment['courseTitle'] ?? ''), 180);
    $assignmentTitle = dent_clean_text((string) ($assignment['title'] ?? ''), 220);
    $deadlineLabel = dent_clean_text((string) ($assignment['endDateShamsi'] ?? ''), 80);
    $bodyParts = array_values(array_filter([
        $courseTitle !== '' && $assignmentTitle !== '' ? ($courseTitle . ' • ' . $assignmentTitle) : ($assignmentTitle !== '' ? $assignmentTitle : $courseTitle),
        $deadlineLabel !== '' ? ('مهلت ارسال: ' . $deadlineLabel) : '',
    ], static function ($value): bool {
        return trim((string) $value) !== '';
    }));

    return notifications_with_store_lock(static function (array &$store) use (
        $assignmentKey,
        $courseTitle,
        $deadlineLabel,
        $bodyParts,
        $assignment
    ): ?array {
        $sourceKey = 'navid-created:' . $assignmentKey;
        $sourceSignature = notifications_source_signature('navid', $sourceKey);
        if ($sourceSignature !== '' && isset($store['suppressedSources'][$sourceSignature])) {
            return null;
        }

        foreach (($store['notifications'] ?? []) as $record) {
            if (!is_array($record)) {
                continue;
            }
            if ((string) ($record['source'] ?? '') === 'navid' && (string) ($record['sourceKey'] ?? '') === $sourceKey) {
                return $record;
            }
        }

        $id = notifications_generate_id();
        $record = notifications_normalize_record($id, [
            'id' => $id,
            'kind' => DENT_NOTIFICATION_KIND_NAVID_ASSIGNMENT,
            'title' => 'تکلیف جدید نوید',
            'body' => implode("\n", $bodyParts),
            'tone' => 'warn',
            'target' => DENT_NOTIFICATION_TARGET_COHORT,
            'cohortKey' => dent_primary_cohort_key(),
            'source' => 'navid',
            'sourceKey' => $sourceKey,
            'ctaHref' => '/navid/',
            'ctaLabel' => 'مشاهده تکالیف',
            'createdAt' => dent_iso_now(),
            'publishAt' => dent_iso_now(),
            'releasedAt' => dent_iso_now(),
            'status' => DENT_NOTIFICATION_STATUS_ACTIVE,
            'createdByStudentNumber' => '',
            'createdByName' => '',
            'createdByRole' => '',
            'meta' => [
                'assignmentKey' => $assignmentKey,
                'courseTitle' => $courseTitle,
                'endDateIso' => (string) ($assignment['endDateIso'] ?? ''),
                'endDateLabel' => $deadlineLabel,
                'proposeDate' => (string) ($assignment['proposeDate'] ?? ''),
                'replyStatusName' => (string) ($assignment['replyStatusName'] ?? ''),
            ],
            'recipients' => notifications_snapshot_recipients_for_target(
                DENT_NOTIFICATION_TARGET_COHORT,
                dent_primary_cohort_key()
            ),
            'sendSms' => false,
            'smsStatus' => DENT_NOTIFICATION_SMS_STATUS_NONE,
        ]);
        if ($record === null) {
            return null;
        }

        $store['notifications'][$record['id']] = $record;
        return $record;
    });
}
