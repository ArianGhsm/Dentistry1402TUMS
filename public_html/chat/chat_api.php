<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/auth_store.php';

const CHAT_SCHEMA_VERSION = 7;
const CHAT_CLASS_CONVERSATION_ID = 'class-main';
const CHAT_GROUP_ID_PREFIX = 'grp-';
const CHAT_POLL_ID_PREFIX = 'poll-';
const CHAT_ATTACHMENT_ID_PREFIX = 'att-';
const CHAT_MEDIA_VARIANT_ORIGINAL = 'original';
const CHAT_MEDIA_VARIANT_PREVIEW = 'preview';
const CHAT_MEDIA_DEFAULT_TARGET_BYTES = 536870912; // 512 MB
const CHAT_MEDIA_CLEANUP_THRESHOLD = 0.60;
const CHAT_MEDIA_MAINTENANCE_INTERVAL_SECONDS = 300;
const CHAT_MEDIA_MAX_FILE_BYTES = 26214400; // 25 MB
const CHAT_POLL_VISIBILITY_LIVE = 'live';
const CHAT_POLL_VISIBILITY_AFTER_CLOSE = 'after-close';
const CHAT_POLL_VISIBILITY_CREATOR_ONLY = 'creator-only';
const CHAT_POLL_AUDIENCE_LINK = 'link';
const CHAT_POLL_AUDIENCE_ALL_USERS = 'all-users';
const CHAT_POLL_AUDIENCE_ROTATION_1 = 'rotation-1';
const CHAT_POLL_AUDIENCE_ROTATION_2 = 'rotation-2';
const CHAT_POLL_AUDIENCE_BOTH_ROTATIONS = 'both-rotations';
const CHAT_BRAND_ASSET_VERSION = '20260422-brand1';
const CHAT_PRESENCE_SCHEMA_VERSION = 1;
const CHAT_PRESENCE_ONLINE_WINDOW_SECONDS = 70;
const CHAT_PRESENCE_TYPING_WINDOW_SECONDS = 6;
const CHAT_PRESENCE_RETENTION_SECONDS = 7776000;
const CHAT_STREAM_MAX_DURATION_SECONDS = 25;
const CHAT_STREAM_POLL_INTERVAL_MICROSECONDS = 700000;
const CHAT_STREAM_KEEPALIVE_SECONDS = 12;

function chat_clean_cohort(?string $value): string
{
    $value = dent_clean_cohort_key((string) $value);
    return $value !== '' ? $value : dent_primary_cohort_key();
}

function chat_requested_cohort(): string
{
    return chat_clean_cohort((string) ($_POST['cohort'] ?? ($_GET['cohort'] ?? '')));
}

function chat_set_active_cohort(string $cohort): void
{
    $GLOBALS['chat_active_cohort'] = chat_clean_cohort($cohort);
}

function chat_active_cohort(): string
{
    return chat_clean_cohort((string) ($GLOBALS['chat_active_cohort'] ?? dent_primary_cohort_key()));
}

function chat_is_prosthesis_context(): bool
{
    return dent_is_prosthesis_cohort_key(chat_active_cohort());
}

function chat_active_cohort_record(): array
{
    return dent_cohort_record(chat_active_cohort());
}

function chat_active_cohort_title(): string
{
    $record = chat_active_cohort_record();
    $title = trim((string) ($record['shortTitle'] ?? ($record['title'] ?? '')));
    return $title !== '' ? $title : 'کلاس';
}

function chat_page_url(string $suffix = ''): string
{
    $cohortKey = chat_active_cohort();
    $base = '/chat/';
    $query = [];
    if ($cohortKey !== dent_primary_cohort_key()) {
        $query['cohort'] = $cohortKey;
    }

    $suffix = (string) $suffix;
    if ($suffix === '') {
        return $query === [] ? $base : ($base . '?' . http_build_query($query));
    }

    if (strncmp($suffix, '?', 1) === 0) {
        parse_str(substr($suffix, 1), $suffixQuery);
        if (!is_array($suffixQuery)) {
            $suffixQuery = [];
        }
        $suffixQuery = array_merge($suffixQuery, $query);
        return $base . '?' . http_build_query($suffixQuery);
    }

    $path = $base . ltrim($suffix, '/');
    if ($query === []) {
        return $path;
    }

    return $path . (str_contains($path, '?') ? '&' : '?') . http_build_query($query);
}

function chat_brand_logo_url(): string
{
    return '/assets/images/logo.png?v=' . CHAT_BRAND_ASSET_VERSION;
}

function chat_store_path(): string
{
    $cohortKey = chat_active_cohort();
    if ($cohortKey === dent_prosthesis_legacy_cohort_key()) {
        return dent_storage_path('prosthesis_1402/chat/store.json');
    }
    if ($cohortKey === dent_primary_cohort_key()) {
        return dent_storage_path('chat/store.json');
    }

    return dent_storage_path('chat/' . dent_cohort_storage_slug($cohortKey) . '_store.json');
}

function chat_store_lock_path(): string
{
    $cohortKey = chat_active_cohort();
    if ($cohortKey === dent_prosthesis_legacy_cohort_key()) {
        return dent_storage_path('prosthesis_1402/chat/store.lock');
    }
    if ($cohortKey === dent_primary_cohort_key()) {
        return dent_storage_path('chat/store.lock');
    }

    return dent_storage_path('chat/' . dent_cohort_storage_slug($cohortKey) . '_store.lock');
}

function chat_presence_path(): string
{
    $cohortKey = chat_active_cohort();
    if ($cohortKey === dent_prosthesis_legacy_cohort_key()) {
        return dent_storage_path('prosthesis_1402/chat/presence.json');
    }
    if ($cohortKey === dent_primary_cohort_key()) {
        return dent_storage_path('chat/presence.json');
    }

    return dent_storage_path('chat/' . dent_cohort_storage_slug($cohortKey) . '_presence.json');
}

function chat_presence_lock_path(): string
{
    $cohortKey = chat_active_cohort();
    if ($cohortKey === dent_prosthesis_legacy_cohort_key()) {
        return dent_storage_path('prosthesis_1402/chat/presence.lock');
    }
    if ($cohortKey === dent_primary_cohort_key()) {
        return dent_storage_path('chat/presence.lock');
    }

    return dent_storage_path('chat/' . dent_cohort_storage_slug($cohortKey) . '_presence.lock');
}

function chat_legacy_messages_path(): string
{
    return dent_storage_path('chat/messages.json');
}

function chat_legacy_state_path(): string
{
    return dent_storage_path('chat/state.json');
}

function chat_media_root_path(): string
{
    $cohortKey = chat_active_cohort();
    if ($cohortKey === dent_prosthesis_legacy_cohort_key()) {
        return dent_storage_path('prosthesis_1402/chat/media');
    }
    if ($cohortKey === dent_primary_cohort_key()) {
        return dent_storage_path('chat/media');
    }

    return dent_storage_path('chat/' . dent_cohort_storage_slug($cohortKey) . '_media');
}

function chat_media_originals_path(): string
{
    return chat_media_root_path() . DIRECTORY_SEPARATOR . 'originals';
}

function chat_media_previews_path(): string
{
    return chat_media_root_path() . DIRECTORY_SEPARATOR . 'previews';
}

function chat_media_cleanup_log_path(): string
{
    return dent_storage_path('chat/media-cleanup.log');
}

function chat_media_target_bytes(): int
{
    $envMb = getenv('DENT_CHAT_MEDIA_TARGET_MB');
    if ($envMb !== false) {
        $parsed = (int) trim((string) $envMb);
        if ($parsed >= 128) {
            return $parsed * 1024 * 1024;
        }
    }

    return CHAT_MEDIA_DEFAULT_TARGET_BYTES;
}

function chat_public_media_url(string $attachmentId, string $variant = CHAT_MEDIA_VARIANT_ORIGINAL, bool $download = false): string
{
    $query = [
        'action' => 'media',
        'attachmentId' => $attachmentId,
        'variant' => $variant === CHAT_MEDIA_VARIANT_PREVIEW ? CHAT_MEDIA_VARIANT_PREVIEW : CHAT_MEDIA_VARIANT_ORIGINAL,
    ];
    if (chat_active_cohort() !== dent_primary_cohort_key()) {
        $query['cohort'] = chat_active_cohort();
    }
    if ($download) {
        $query['download'] = '1';
    }

    return '/chat/chat_api.php?' . http_build_query($query);
}

function chat_stream_url(): string
{
    $query = ['action' => 'stream'];
    if (chat_active_cohort() !== dent_primary_cohort_key()) {
        $query['cohort'] = chat_active_cohort();
    }

    return '/chat/chat_api.php?' . http_build_query($query);
}

function chat_presence_url(): string
{
    $query = ['action' => 'presence'];
    if (chat_active_cohort() !== dent_primary_cohort_key()) {
        $query['cohort'] = chat_active_cohort();
    }

    return '/chat/chat_api.php?' . http_build_query($query);
}

function chat_default_settings(array $seed = []): array
{
    $kind = dent_clean_text((string) ($seed['conversationKind'] ?? ($seed['kind'] ?? 'group')), 20);
    if (!in_array($kind, ['group', 'channel'], true)) {
        $kind = 'group';
    }

    $reactionMode = dent_clean_text((string) ($seed['reactionMode'] ?? 'all'), 20);
    if (!in_array($reactionMode, ['all', 'quick', 'none'], true)) {
        $reactionMode = 'all';
    }

    $visibility = dent_clean_text((string) ($seed['visibility'] ?? ($seed['groupVisibility'] ?? 'private')), 20);
    if (!in_array($visibility, ['private', 'public'], true)) {
        $visibility = 'private';
    }

    return [
        'muted' => (bool) ($seed['muted'] ?? false),
        'mutedBy' => ($seed['mutedBy'] ?? null) !== null
            ? dent_normalize_student_number((string) $seed['mutedBy'])
            : null,
        'mutedAt' => isset($seed['mutedAt']) ? (int) $seed['mutedAt'] : null,
        'conversationKind' => $kind,
        'memberPosting' => $kind === 'channel'
            ? false
            : (bool) ($seed['memberPosting'] ?? true),
        'reactionMode' => $reactionMode,
        'visibility' => $visibility,
    ];
}

function chat_default_read_state(array $seed = []): array
{
    $lastReadMessageId = max(0, (int) ($seed['lastReadMessageId'] ?? 0));
    $lastReadAt = isset($seed['lastReadAt']) ? (int) $seed['lastReadAt'] : null;
    if ($lastReadAt !== null && $lastReadAt <= 0) {
        $lastReadAt = null;
    }

    $pinned = (bool) ($seed['pinned'] ?? false);
    $pinnedAt = isset($seed['pinnedAt']) ? (int) $seed['pinnedAt'] : null;
    if ($pinnedAt !== null && $pinnedAt <= 0) {
        $pinnedAt = null;
    }
    if (!$pinned) {
        $pinnedAt = null;
    } elseif ($pinnedAt === null) {
        $pinnedAt = time();
    }

    $archived = (bool) ($seed['archived'] ?? false);
    $archivedAt = isset($seed['archivedAt']) ? (int) $seed['archivedAt'] : null;
    if ($archivedAt !== null && $archivedAt <= 0) {
        $archivedAt = null;
    }
    if (!$archived) {
        $archivedAt = null;
    } elseif ($archivedAt === null) {
        $archivedAt = time();
    }

    $deleted = (bool) ($seed['deleted'] ?? false);
    $deletedAt = isset($seed['deletedAt']) ? (int) $seed['deletedAt'] : null;
    if ($deletedAt !== null && $deletedAt <= 0) {
        $deletedAt = null;
    }
    if (!$deleted) {
        $deletedAt = null;
    } elseif ($deletedAt === null) {
        $deletedAt = time();
    }

    $notificationsMuted = (bool) ($seed['notificationsMuted'] ?? false);
    $notificationsMutedAt = isset($seed['notificationsMutedAt']) ? (int) $seed['notificationsMutedAt'] : null;
    if ($notificationsMutedAt !== null && $notificationsMutedAt <= 0) {
        $notificationsMutedAt = null;
    }
    if (!$notificationsMuted) {
        $notificationsMutedAt = null;
    } elseif ($notificationsMutedAt === null) {
        $notificationsMutedAt = time();
    }

    return [
        'lastReadMessageId' => $lastReadMessageId,
        'lastReadAt' => $lastReadAt,
        'pinned' => $pinned,
        'pinnedAt' => $pinnedAt,
        'archived' => $archived,
        'archivedAt' => $archivedAt,
        'deleted' => $deleted,
        'deletedAt' => $deletedAt,
        'notificationsMuted' => $notificationsMuted,
        'notificationsMutedAt' => $notificationsMutedAt,
    ];
}

function chat_default_maintenance_state(array $seed = []): array
{
    $nonClassCleanupDone = chat_parse_bool($seed['nonClassCleanupDone'] ?? false, false);
    $nonClassCleanupAt = chat_parse_timestamp($seed['nonClassCleanupAt'] ?? null);
    if (!$nonClassCleanupDone) {
        $nonClassCleanupAt = null;
    } elseif ($nonClassCleanupAt === null) {
        $nonClassCleanupAt = time();
    }

    $mediaTargetBytes = max(64 * 1024 * 1024, (int) ($seed['mediaTargetBytes'] ?? chat_media_target_bytes()));
    $mediaUsageBytes = max(0, (int) ($seed['mediaUsageBytes'] ?? 0));
    $mediaOriginalCount = max(0, (int) ($seed['mediaOriginalCount'] ?? 0));
    $mediaPurgedCount = max(0, (int) ($seed['mediaPurgedCount'] ?? 0));
    $mediaCleanupRuns = max(0, (int) ($seed['mediaCleanupRuns'] ?? 0));
    $mediaCleanupThreshold = CHAT_MEDIA_CLEANUP_THRESHOLD;
    $lastMediaCleanupAt = chat_parse_timestamp($seed['lastMediaCleanupAt'] ?? null);
    $lastMediaMaintenanceAt = chat_parse_timestamp($seed['lastMediaMaintenanceAt'] ?? null);
    $lastMediaCleanupStatus = dent_clean_text((string) ($seed['lastMediaCleanupStatus'] ?? ''), 120);
    $lastMediaCleanupError = dent_clean_text((string) ($seed['lastMediaCleanupError'] ?? ''), 300);

    return [
        'nonClassCleanupDone' => $nonClassCleanupDone,
        'nonClassCleanupAt' => $nonClassCleanupAt,
        'mediaTargetBytes' => $mediaTargetBytes,
        'mediaCleanupThreshold' => $mediaCleanupThreshold,
        'mediaUsageBytes' => $mediaUsageBytes,
        'mediaOriginalCount' => $mediaOriginalCount,
        'mediaPurgedCount' => $mediaPurgedCount,
        'mediaCleanupRuns' => $mediaCleanupRuns,
        'lastMediaCleanupAt' => $lastMediaCleanupAt,
        'lastMediaMaintenanceAt' => $lastMediaMaintenanceAt,
        'lastMediaCleanupStatus' => $lastMediaCleanupStatus,
        'lastMediaCleanupError' => $lastMediaCleanupError,
    ];
}

function chat_default_class_conversation(array $legacyState = []): array
{
    $now = time();
    $isProsthesis = chat_is_prosthesis_context();

    return [
        'id' => CHAT_CLASS_CONVERSATION_ID,
        'type' => 'class-group',
        'title' => $isProsthesis ? 'گفت‌وگوی پروتز ۱۴۰۲' : 'گفت‌وگوی کلاس',
        'about' => $isProsthesis
            ? 'گفت‌وگوی عمومی پروتز ۱۴۰۲. عضویت این گفتگو برای دانشجوهای پروتز اجباری است.'
            : 'گفت‌وگوی عمومی ورودی ۱۴۰۲. عضویت این گفتگو برای همه دانشجویان اجباری است.',
        'avatarUrl' => chat_brand_logo_url(),
        'createdAt' => $now,
        'updatedAt' => $now,
        'createdBy' => 'system',
        'mandatory' => true,
        'memberStudentNumbers' => [],
        'directParticipants' => [],
        'admins' => [],
        'temporary' => false,
        'settings' => chat_default_settings($legacyState),
    ];
}

function chat_new_store_base(array $legacyState = []): array
{
    return [
        'schemaVersion' => CHAT_SCHEMA_VERSION,
        'classConversationId' => CHAT_CLASS_CONVERSATION_ID,
        'nextMessageId' => 1,
        'nextGroupId' => 1,
        'nextPollId' => 1,
        'nextAttachmentId' => 1,
        'conversations' => [
            CHAT_CLASS_CONVERSATION_ID => chat_default_class_conversation($legacyState),
        ],
        'messages' => [
            CHAT_CLASS_CONVERSATION_ID => [],
        ],
        'reads' => [],
        'polls' => [],
        'attachments' => [],
        'maintenance' => chat_default_maintenance_state([
            'nonClassCleanupDone' => true,
            'nonClassCleanupAt' => time(),
            'mediaTargetBytes' => chat_media_target_bytes(),
        ]),
    ];
}

function chat_ensure_storage(): void
{
    dent_ensure_directory(dirname(chat_store_path()));
    dent_ensure_directory(chat_media_originals_path());
    dent_ensure_directory(chat_media_previews_path());
    dent_ensure_directory(dirname(chat_presence_path()));

    if (!isset($_SESSION['chat_rate_limit']) || !is_array($_SESSION['chat_rate_limit'])) {
        $_SESSION['chat_rate_limit'] = [];
    }
}

function chat_acquire_store_lock(): void
{
    if (($GLOBALS['chat_store_lock_acquired'] ?? false) === true) {
        return;
    }

    dent_ensure_directory(dirname(chat_store_lock_path()));
    $handle = @fopen(chat_store_lock_path(), 'c');
    if ($handle === false) {
        dent_error('Chat storage lock is unavailable.', 503);
    }

    if (!@flock($handle, LOCK_EX)) {
        @fclose($handle);
        dent_error('Chat storage lock is unavailable.', 503);
    }

    $GLOBALS['chat_store_lock_handle'] = $handle;
    $GLOBALS['chat_store_lock_acquired'] = true;
    register_shutdown_function('chat_release_store_lock');
}

function chat_release_store_lock(): void
{
    if (($GLOBALS['chat_store_lock_acquired'] ?? false) !== true) {
        return;
    }

    $handle = $GLOBALS['chat_store_lock_handle'] ?? null;
    if (is_resource($handle)) {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }

    $GLOBALS['chat_store_lock_handle'] = null;
    $GLOBALS['chat_store_lock_acquired'] = false;
}

function chat_acquire_presence_lock(): void
{
    if (($GLOBALS['chat_presence_lock_acquired'] ?? false) === true) {
        return;
    }

    dent_ensure_directory(dirname(chat_presence_lock_path()));
    $handle = @fopen(chat_presence_lock_path(), 'c');
    if ($handle === false) {
        dent_error('Chat presence lock is unavailable.', 503);
    }

    if (!@flock($handle, LOCK_EX)) {
        @fclose($handle);
        dent_error('Chat presence lock is unavailable.', 503);
    }

    $GLOBALS['chat_presence_lock_handle'] = $handle;
    $GLOBALS['chat_presence_lock_acquired'] = true;
    register_shutdown_function('chat_release_presence_lock');
}

function chat_release_presence_lock(): void
{
    if (($GLOBALS['chat_presence_lock_acquired'] ?? false) !== true) {
        return;
    }

    $handle = $GLOBALS['chat_presence_lock_handle'] ?? null;
    if (is_resource($handle)) {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }

    $GLOBALS['chat_presence_lock_handle'] = null;
    $GLOBALS['chat_presence_lock_acquired'] = false;
}

function chat_clean_conversation_id(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^[a-z0-9:-]{2,120}$/', $value) !== 1) {
        return '';
    }

    return $value;
}

function chat_clean_poll_id(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^' . preg_quote(CHAT_POLL_ID_PREFIX, '/') . '[a-z0-9-]{1,48}$/', $value) !== 1) {
        return '';
    }

    return $value;
}

function chat_parse_bool($value, bool $fallback = false): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value)) {
        return (int) $value !== 0;
    }

    $text = trim(strtolower((string) $value));
    if ($text === '') {
        return $fallback;
    }

    if (in_array($text, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    if (in_array($text, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return $fallback;
}

function chat_parse_timestamp($value): ?int
{
    if ($value === null) {
        return null;
    }

    if (is_int($value) || is_float($value)) {
        $ts = (int) $value;
        return $ts > 0 ? $ts : null;
    }

    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }

    if (preg_match('/^\d+$/', $text) === 1) {
        $ts = (int) $text;
        return $ts > 0 ? $ts : null;
    }

    $parsed = strtotime($text);
    if ($parsed === false || $parsed <= 0) {
        return null;
    }

    return (int) $parsed;
}

function chat_default_presence_store(array $seed = []): array
{
    return [
        'schemaVersion' => CHAT_PRESENCE_SCHEMA_VERSION,
        'updatedAt' => chat_parse_timestamp($seed['updatedAt'] ?? null) ?? time(),
        'users' => is_array($seed['users'] ?? null) ? $seed['users'] : [],
    ];
}

function chat_normalize_presence_entry(string $studentNumber, array $source, ?int $now = null): ?array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        return null;
    }

    $now = $now ?? time();
    $lastSeenAt = chat_parse_timestamp($source['lastSeenAt'] ?? null);
    $lastActiveAt = chat_parse_timestamp($source['lastActiveAt'] ?? null);
    $lastHeartbeatAt = chat_parse_timestamp($source['lastHeartbeatAt'] ?? null);
    $activeConversationId = chat_clean_conversation_id((string) ($source['activeConversationId'] ?? ''));
    $typingConversationId = chat_clean_conversation_id((string) ($source['typingConversationId'] ?? ''));
    $typingExpiresAt = chat_parse_timestamp($source['typingExpiresAt'] ?? null);

    if ($lastSeenAt !== null && $lastSeenAt > $now + 300) {
        $lastSeenAt = $now;
    }
    if ($lastActiveAt !== null && $lastActiveAt > $now + 300) {
        $lastActiveAt = $now;
    }
    if ($lastHeartbeatAt !== null && $lastHeartbeatAt > $now + 300) {
        $lastHeartbeatAt = $now;
    }
    if ($typingExpiresAt !== null && $typingExpiresAt > $now + 300) {
        $typingExpiresAt = $now + CHAT_PRESENCE_TYPING_WINDOW_SECONDS;
    }

    if ($typingExpiresAt === null || $typingExpiresAt <= $now || $typingConversationId === '') {
        $typingConversationId = '';
        $typingExpiresAt = null;
    }

    $latestSeenAt = max(
        $lastSeenAt ?? 0,
        $lastActiveAt ?? 0,
        $lastHeartbeatAt ?? 0
    );
    if ($latestSeenAt > 0) {
        $lastSeenAt = $latestSeenAt;
    }

    return [
        'studentNumber' => $studentNumber,
        'lastSeenAt' => $lastSeenAt,
        'lastActiveAt' => $lastActiveAt,
        'lastHeartbeatAt' => $lastHeartbeatAt,
        'activeConversationId' => $activeConversationId,
        'typingConversationId' => $typingConversationId,
        'typingExpiresAt' => $typingExpiresAt,
    ];
}

function chat_normalize_presence_store(array $rawStore): array
{
    $normalized = chat_default_presence_store($rawStore);
    $users = [];
    $now = time();
    foreach ((array) ($normalized['users'] ?? []) as $key => $entry) {
        $studentNumber = dent_normalize_student_number((string) (is_array($entry) ? ($entry['studentNumber'] ?? $key) : $key));
        if (!is_array($entry)) {
            continue;
        }
        $normalizedEntry = chat_normalize_presence_entry($studentNumber, $entry, $now);
        if ($normalizedEntry === null) {
            continue;
        }

        $freshUntil = max(
            (int) ($normalizedEntry['lastSeenAt'] ?? 0),
            (int) ($normalizedEntry['lastActiveAt'] ?? 0),
            (int) ($normalizedEntry['lastHeartbeatAt'] ?? 0)
        );
        if ($freshUntil > 0 && ($now - $freshUntil) > CHAT_PRESENCE_RETENTION_SECONDS) {
            continue;
        }

        $users[$studentNumber] = $normalizedEntry;
    }

    ksort($users, SORT_STRING);
    $normalized['schemaVersion'] = CHAT_PRESENCE_SCHEMA_VERSION;
    $normalized['updatedAt'] = chat_parse_timestamp($normalized['updatedAt'] ?? null) ?? $now;
    $normalized['users'] = $users;
    return $normalized;
}

function chat_read_presence_store_file(): ?array
{
    $path = chat_presence_path();
    if (!is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function chat_write_presence_store_file(array $store): void
{
    dent_ensure_directory(dirname(chat_presence_path()));

    $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $store = chat_normalize_presence_store($store);
    $store['schemaVersion'] = CHAT_PRESENCE_SCHEMA_VERSION;
    $store['updatedAt'] = time();

    $json = json_encode($store, $flags);
    if ($json === false) {
        dent_error('Chat presence JSON encoding failed.', 500);
    }

    $path = chat_presence_path();
    $tmpPath = $path . '.tmp.' . getmypid() . '.' . str_replace('.', '', uniqid('', true));
    if (@file_put_contents($tmpPath, $json . PHP_EOL, LOCK_EX) === false) {
        @unlink($tmpPath);
        dent_error('Chat presence write failed.', 500);
    }
    @chmod($tmpPath, 0644);

    if (!@rename($tmpPath, $path)) {
        if (is_file($path)) {
            @unlink($path);
        }
        if (!@rename($tmpPath, $path)) {
            @unlink($tmpPath);
            dent_error('Chat presence replace failed.', 500);
        }
    }
}

function chat_load_presence_store(): array
{
    chat_acquire_presence_lock();
    $rawStore = chat_read_presence_store_file();
    $normalized = chat_normalize_presence_store(is_array($rawStore) ? $rawStore : chat_default_presence_store());
    $rawEncoded = is_array($rawStore)
        ? (json_encode($rawStore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')
        : '';
    $normalizedEncoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    if (!is_array($rawStore) || $rawEncoded !== $normalizedEncoded) {
        chat_write_presence_store_file($normalized);
    }
    return $normalized;
}

function chat_save_presence_store(array $store): void
{
    chat_acquire_presence_lock();
    chat_write_presence_store_file($store);
}

function chat_read_presence_snapshot(bool $fresh = false): array
{
    static $cache = [];
    $cacheKey = chat_active_cohort();
    if (!$fresh && isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $rawStore = chat_read_presence_store_file();
    $cache[$cacheKey] = chat_normalize_presence_store(is_array($rawStore) ? $rawStore : chat_default_presence_store());
    return $cache[$cacheKey];
}

function chat_presence_file_signature(): string
{
    $path = chat_presence_path();
    clearstatcache(true, $path);
    if (!is_file($path)) {
        return '0:0';
    }

    $mtime = @filemtime($path) ?: 0;
    $size = @filesize($path) ?: 0;
    return $mtime . ':' . $size;
}

function chat_clean_attachment_id(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^' . preg_quote(CHAT_ATTACHMENT_ID_PREFIX, '/') . '[a-z0-9-]{1,64}$/', $value) !== 1) {
        return '';
    }

    return $value;
}

function chat_clean_media_variant(?string $value): string
{
    $value = trim((string) $value);
    if ($value === CHAT_MEDIA_VARIANT_PREVIEW) {
        return CHAT_MEDIA_VARIANT_PREVIEW;
    }
    return CHAT_MEDIA_VARIANT_ORIGINAL;
}

function chat_parse_attachment_ids_input($raw): array
{
    $items = [];
    if (is_array($raw)) {
        $items = $raw;
    } else {
        $text = trim((string) $raw);
        if ($text === '') {
            return [];
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            $items = $decoded;
        } else {
            $items = preg_split('/[\s,;]+/u', $text) ?: [];
        }
    }

    $normalized = [];
    $seen = [];
    foreach ($items as $item) {
        $clean = chat_clean_attachment_id((string) $item);
        if ($clean === '' || isset($seen[$clean])) {
            continue;
        }
        $seen[$clean] = true;
        $normalized[] = $clean;
    }

    return $normalized;
}

function chat_safe_extension(string $fileName): string
{
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($extension === '' || preg_match('/^[a-z0-9]{1,12}$/', $extension) !== 1) {
        return '';
    }
    return $extension;
}

function chat_is_blocked_upload_extension(string $extension): bool
{
    if ($extension === '') {
        return false;
    }

    static $blocked = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'exe', 'dll', 'com', 'bat', 'cmd', 'sh', 'msi',
        'jar', 'js', 'mjs', 'cjs', 'vbs', 'ps1', 'psm1',
        'scr', 'reg', 'lnk', 'app', 'apk', 'dmg',
        'html', 'htm', 'svg',
    ];

    return in_array($extension, $blocked, true);
}

function chat_attachment_category_for_mime(string $mime, string $extension, bool $voice = false): string
{
    if ($voice) {
        return 'voice';
    }

    $mime = strtolower(trim($mime));
    $extension = strtolower(trim($extension));

    if ($mime !== '') {
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if ($mime === 'application/pdf') {
            return 'pdf';
        }
        if (str_contains($mime, 'officedocument')
            || str_contains($mime, 'msword')
            || str_contains($mime, 'vnd.ms-')) {
            return 'office';
        }
        if (str_contains($mime, 'zip')
            || str_contains($mime, 'rar')
            || str_contains($mime, '7z')
            || str_contains($mime, 'tar')
            || str_contains($mime, 'gzip')) {
            return 'archive';
        }
    }

    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic', 'heif'], true)) {
        return 'image';
    }
    if (in_array($extension, ['mp4', 'webm', 'mov', 'mkv', 'avi', 'm4v'], true)) {
        return 'video';
    }
    if (in_array($extension, ['mp3', 'wav', 'ogg', 'oga', 'm4a', 'aac', 'flac', 'opus'], true)) {
        return 'audio';
    }
    if ($extension === 'pdf') {
        return 'pdf';
    }
    if (in_array($extension, ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'odt', 'ods', 'odp', 'rtf'], true)) {
        return 'office';
    }
    if (in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'bz2'], true)) {
        return 'archive';
    }
    if (in_array($extension, ['txt', 'csv', 'json', 'xml', 'md'], true)) {
        return 'document';
    }

    return 'file';
}

function chat_clean_poll_result_visibility(string $value): string
{
    $value = trim((string) $value);
    if ($value === CHAT_POLL_VISIBILITY_AFTER_CLOSE) {
        return CHAT_POLL_VISIBILITY_AFTER_CLOSE;
    }

    if ($value === CHAT_POLL_VISIBILITY_CREATOR_ONLY) {
        return CHAT_POLL_VISIBILITY_CREATOR_ONLY;
    }

    return CHAT_POLL_VISIBILITY_LIVE;
}

function chat_clean_poll_audience(string $value): string
{
    $value = trim((string) $value);
    if ($value === CHAT_POLL_AUDIENCE_ALL_USERS) {
        return CHAT_POLL_AUDIENCE_ALL_USERS;
    }
    if ($value === CHAT_POLL_AUDIENCE_ROTATION_1) {
        return CHAT_POLL_AUDIENCE_ROTATION_1;
    }
    if ($value === CHAT_POLL_AUDIENCE_ROTATION_2) {
        return CHAT_POLL_AUDIENCE_ROTATION_2;
    }
    if ($value === CHAT_POLL_AUDIENCE_BOTH_ROTATIONS) {
        return CHAT_POLL_AUDIENCE_BOTH_ROTATIONS;
    }
    return CHAT_POLL_AUDIENCE_LINK;
}

function chat_clean_poll_option_id(string $value): string
{
    $value = trim(strtolower($value));
    if ($value === '') {
        return '';
    }

    if (preg_match('/^[a-z0-9_-]{1,40}$/', $value) !== 1) {
        return '';
    }

    return $value;
}

function chat_normalize_poll_options($value): array
{
    $items = [];
    if (is_array($value)) {
        $items = $value;
    } elseif (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $items = $decoded;
            } else {
                $items = preg_split('/\r\n|\r|\n/u', $trimmed) ?: [];
            }
        }
    }

    $options = [];
    $usedIds = [];
    $index = 1;

    foreach ($items as $item) {
        $text = '';
        $id = '';
        if (is_array($item)) {
            $text = dent_clean_text((string) ($item['text'] ?? ''), 120);
            $id = chat_clean_poll_option_id((string) ($item['id'] ?? ''));
        } else {
            $text = dent_clean_text((string) $item, 120);
        }

        if ($text === '') {
            continue;
        }

        if ($id === '') {
            $id = 'opt-' . $index;
        }
        while (isset($usedIds[$id])) {
            $index++;
            $id = 'opt-' . $index;
        }
        $index++;

        $usedIds[$id] = true;
        $options[] = [
            'id' => $id,
            'text' => $text,
        ];

        if (count($options) >= 12) {
            break;
        }
    }

    return $options;
}

function chat_normalize_poll_option_ids($value, array $allowedOptionIds): array
{
    $items = [];
    if (is_array($value)) {
        $items = $value;
    } elseif (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $items = $decoded;
            } else {
                $items = preg_split('/[\s,;]+/u', $trimmed) ?: [];
            }
        }
    }

    $allowed = [];
    foreach ($allowedOptionIds as $optionId) {
        $clean = chat_clean_poll_option_id((string) $optionId);
        if ($clean !== '') {
            $allowed[$clean] = true;
        }
    }

    $normalized = [];
    foreach ($items as $item) {
        $optionId = chat_clean_poll_option_id((string) $item);
        if ($optionId === '' || !isset($allowed[$optionId])) {
            continue;
        }
        $normalized[$optionId] = true;
    }

    return array_keys($normalized);
}

function chat_normalize_student_list($value): array
{
    $items = [];
    if (is_array($value)) {
        $items = $value;
    } elseif (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $items = $decoded;
            } else {
                $items = preg_split('/[\s,;]+/u', $trimmed) ?: [];
            }
        }
    }

    $normalized = [];
    $seen = [];
    foreach ($items as $item) {
        $studentNumber = dent_normalize_student_number((string) $item);
        if ($studentNumber !== '') {
            $key = 'sn:' . $studentNumber;
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $normalized[] = $studentNumber;
            }
        }
    }

    sort($normalized, SORT_STRING);
    return $normalized;
}

function chat_actor_student_number(array $user): string
{
    $candidates = [
        $user['studentNumber'] ?? null,
        $user['username'] ?? null,
        $user['student_number'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        $normalized = dent_normalize_student_number((string) ($candidate ?? ''));
        if ($normalized !== '') {
            return $normalized;
        }
    }

    $publicUser = dent_public_user($user);
    $publicStudentNumber = dent_normalize_student_number((string) ($publicUser['studentNumber'] ?? ''));
    if ($publicStudentNumber !== '') {
        return $publicStudentNumber;
    }

    $sessionStudentNumber = dent_normalize_student_number((string) ($_SESSION['student_number'] ?? ''));
    if ($sessionStudentNumber !== '') {
        return $sessionStudentNumber;
    }

    $currentUser = dent_current_user();
    if (is_array($currentUser)) {
        $currentStudentNumber = dent_normalize_student_number((string) ($currentUser['studentNumber'] ?? ''));
        if ($currentStudentNumber !== '') {
            return $currentStudentNumber;
        }
    }

    return '';
}

function chat_sanitize_emoji(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $length = function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
    if ($length > 24) {
        return '';
    }

    if (@preg_match('/[\p{Cc}\p{Zl}\p{Zp}]/u', $value) === 1) {
        return '';
    }

    // Try modern emoji properties first, then fall back to explicit ranges for older PCRE builds.
    $patterns = [
        '/(?:\p{Extended_Pictographic}|[\x{2600}-\x{27BF}]|[\x{1F1E6}-\x{1F1FF}]|\x{1F3F4})/u',
        '/(?:[\x{1F300}-\x{1FAFF}]|[\x{2600}-\x{27BF}]|[\x{1F1E6}-\x{1F1FF}]|\x{1F3F4})/u',
    ];

    foreach ($patterns as $pattern) {
        $matched = @preg_match($pattern, $value);
        if ($matched === 1) {
            return $value;
        }
    }

    return '';
}

function chat_reaction_user_candidates($users): array
{
    if (!is_array($users)) {
        return [];
    }

    $candidates = [];
    $keys = array_keys($users);
    $isList = $keys === range(0, count($users) - 1);

    if ($isList) {
        foreach ($users as $value) {
            if (is_string($value) || is_int($value) || is_float($value)) {
                $candidates[] = (string) $value;
                continue;
            }

            if (is_array($value)) {
                $nestedStudentNumber = $value['studentNumber'] ?? $value['username'] ?? null;
                if ($nestedStudentNumber !== null) {
                    $candidates[] = (string) $nestedStudentNumber;
                }
            }
        }

        return $candidates;
    }

    foreach ($users as $key => $value) {
        if (is_string($key) || is_int($key) || is_float($key)) {
            $candidates[] = (string) $key;
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            $candidates[] = (string) $value;
            continue;
        }

        if (is_array($value)) {
            $nestedStudentNumber = $value['studentNumber'] ?? $value['username'] ?? null;
            if ($nestedStudentNumber !== null) {
                $candidates[] = (string) $nestedStudentNumber;
            }
        }
    }

    return $candidates;
}

function chat_sanitize_reactions($raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    $normalized = [];
    foreach ($raw as $emoji => $users) {
        $cleanEmoji = chat_sanitize_emoji((string) $emoji);
        if ($cleanEmoji === '') {
            continue;
        }

        $set = [];
        $userList = [];
        foreach (chat_reaction_user_candidates($users) as $studentNumber) {
            $normalizedStudentNumber = dent_normalize_student_number((string) $studentNumber);
            if ($normalizedStudentNumber !== '') {
                $key = 'sn:' . $normalizedStudentNumber;
                if (!isset($set[$key])) {
                    $set[$key] = true;
                    $userList[] = $normalizedStudentNumber;
                }
            }
        }

        sort($userList, SORT_STRING);
        if ($userList !== []) {
            $normalized[$cleanEmoji] = $userList;
        }
    }

    return $normalized;
}

function chat_parse_reaction_timestamp($value): ?int
{
    if ($value === null) {
        return null;
    }

    if (is_int($value) || is_float($value)) {
        $ts = (int) $value;
        return $ts > 0 ? $ts : null;
    }

    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }

    if (preg_match('/^\d+$/', $text) === 1) {
        $ts = (int) $text;
        return $ts > 0 ? $ts : null;
    }

    $parsed = strtotime($text);
    if ($parsed === false) {
        return null;
    }

    return $parsed > 0 ? $parsed : null;
}

function chat_sanitize_reaction_meta($raw, array $reactions): array
{
    if (!is_array($raw) || $reactions === []) {
        return [];
    }

    $normalized = [];

    foreach ($reactions as $emoji => $studentNumbers) {
        $rawBucket = is_array($raw[$emoji] ?? null) ? $raw[$emoji] : [];
        $timestampsByStudent = [];

        foreach ($rawBucket as $key => $value) {
            $studentNumber = '';
            $timestamp = null;

            if (is_array($value)) {
                $studentNumber = dent_normalize_student_number((string) ($value['studentNumber'] ?? $value['username'] ?? $key));
                $timestamp = chat_parse_reaction_timestamp($value['reactedAt'] ?? $value['ts'] ?? $value['timestamp'] ?? null);
            } else {
                $studentNumber = dent_normalize_student_number((string) $key);
                $timestamp = chat_parse_reaction_timestamp($value);
            }

            if ($studentNumber === '' || $timestamp === null) {
                continue;
            }

            $timestampsByStudent[$studentNumber] = $timestamp;
        }

        $bucket = [];
        foreach ($studentNumbers as $studentNumber) {
            if (isset($timestampsByStudent[$studentNumber])) {
                $bucket[$studentNumber] = $timestampsByStudent[$studentNumber];
            }
        }

        if ($bucket !== []) {
            $normalized[$emoji] = $bucket;
        }
    }

    return $normalized;
}

function chat_reaction_records(array $message): array
{
    $reactions = chat_sanitize_reactions($message['reactions'] ?? []);
    $meta = chat_sanitize_reaction_meta($message['reactionMeta'] ?? [], $reactions);
    $fallbackTs = max(0, (int) ($message['ts'] ?? 0));
    $records = [];

    foreach ($reactions as $emoji => $studentNumbers) {
        $bucket = [];
        foreach ($studentNumbers as $studentNumber) {
            $bucket[] = [
                'studentNumber' => $studentNumber,
                'reactedAt' => $meta[$emoji][$studentNumber] ?? ($fallbackTs > 0 ? $fallbackTs : null),
            ];
        }

        if ($bucket !== []) {
            $records[$emoji] = $bucket;
        }
    }

    return $records;
}

function chat_reaction_details_payload(array $message, string $emoji = ''): array
{
    $payload = [];
    $filterEmoji = chat_sanitize_emoji($emoji);

    foreach (chat_reaction_records($message) as $reactionEmoji => $records) {
        if ($filterEmoji !== '' && $reactionEmoji !== $filterEmoji) {
            continue;
        }

        $users = [];
        foreach ($records as $record) {
            $studentNumber = dent_normalize_student_number((string) ($record['studentNumber'] ?? ''));
            if ($studentNumber === '') {
                continue;
            }

            $user = chat_public_user_for_student($studentNumber);
            $users[] = [
                'studentNumber' => $studentNumber,
                'reactedAt' => chat_parse_reaction_timestamp($record['reactedAt'] ?? null),
                'user' => $user,
            ];
        }

        usort($users, static function (array $left, array $right): int {
            $leftTs = (int) ($left['reactedAt'] ?? 0);
            $rightTs = (int) ($right['reactedAt'] ?? 0);
            if ($leftTs !== $rightTs) {
                return $rightTs <=> $leftTs;
            }

            return strcasecmp(
                (string) (($left['user']['name'] ?? '') ?: ($left['studentNumber'] ?? '')),
                (string) (($right['user']['name'] ?? '') ?: ($right['studentNumber'] ?? ''))
            );
        });

        if ($users !== []) {
            $payload[$reactionEmoji] = $users;
        }
    }

    return $payload;
}

function chat_normalize_message_record(array $message, string $conversationId): ?array
{
    $senderStudentNumber = dent_normalize_student_number((string) (
        $message['senderStudentNumber']
        ?? $message['username']
        ?? $message['studentNumber']
        ?? ''
    ));

    if ($senderStudentNumber === '') {
        return null;
    }

    $kind = trim((string) ($message['kind'] ?? 'text'));
    if (!in_array($kind, ['text', 'poll', 'attachment', 'voice'], true)) {
        $kind = 'text';
    }

    $pollId = '';
    if ($kind === 'poll') {
        $pollId = chat_clean_poll_id((string) ($message['pollId'] ?? ''));
        if ($pollId === '') {
            $kind = 'text';
        }
    }

    $attachmentIds = chat_parse_attachment_ids_input(
        $message['attachmentIds'] ?? ($message['attachments'] ?? [])
    );

    $text = dent_clean_text((string) ($message['text'] ?? ''), 2000);
    if ($text === '' && $kind === 'poll') {
        $text = 'Poll';
    }
    if ($text === '' && $attachmentIds === [] && $kind !== 'poll') {
        return null;
    }
    if ($text === '' && $attachmentIds !== []) {
        $text = 'Attachment';
    }

    $id = (int) ($message['id'] ?? 0);
    $ts = (int) ($message['ts'] ?? time());
    if ($ts <= 0) {
        $ts = time();
    }

    $editedAt = isset($message['editedAt']) ? (int) $message['editedAt'] : null;
    if ($editedAt !== null && $editedAt <= 0) {
        $editedAt = null;
    }

    $replyTo = isset($message['replyTo']) ? (int) $message['replyTo'] : null;
    if ($replyTo !== null && $replyTo <= 0) {
        $replyTo = null;
    }

    $reactions = chat_sanitize_reactions($message['reactions'] ?? []);

    return [
        'id' => $id,
        'conversationId' => $conversationId,
        'senderStudentNumber' => $senderStudentNumber,
        'text' => $text,
        'ts' => $ts,
        'editedAt' => $editedAt,
        'replyTo' => $replyTo,
        'pinned' => (bool) ($message['pinned'] ?? false),
        'reactions' => $reactions,
        'reactionMeta' => chat_sanitize_reaction_meta($message['reactionMeta'] ?? [], $reactions),
        'kind' => $kind,
        'pollId' => $kind === 'poll' ? $pollId : '',
        'attachmentIds' => $attachmentIds,
    ];
}

function chat_normalize_poll_record(string $pollId, array $poll, array $knownConversationIds = []): ?array
{
    $pollId = chat_clean_poll_id($pollId !== '' ? $pollId : (string) ($poll['id'] ?? ''));
    if ($pollId === '') {
        return null;
    }

    $question = dent_clean_text((string) ($poll['question'] ?? ($poll['title'] ?? '')), 280);
    if ($question === '') {
        return null;
    }

    $options = chat_normalize_poll_options($poll['options'] ?? []);
    if (count($options) < 2) {
        return null;
    }

    $optionIds = [];
    foreach ($options as $option) {
        $optionIds[] = (string) ($option['id'] ?? '');
    }

    $createdBy = dent_normalize_student_number((string) ($poll['createdBy'] ?? ''));
    if ($createdBy === '') {
        return null;
    }

    $createdAt = chat_parse_timestamp($poll['createdAt'] ?? null) ?? time();
    $updatedAt = chat_parse_timestamp($poll['updatedAt'] ?? null) ?? $createdAt;
    $startAt = chat_parse_timestamp($poll['startAt'] ?? null);
    $endAt = chat_parse_timestamp($poll['endAt'] ?? null);
    if ($startAt !== null && $endAt !== null && $endAt <= $startAt) {
        $endAt = null;
    }

    $closedAt = chat_parse_timestamp($poll['closedAt'] ?? null);
    $closedBy = dent_normalize_student_number((string) ($poll['closedBy'] ?? ''));
    if ($closedAt === null) {
        $closedBy = '';
    }

    $settings = is_array($poll['settings'] ?? null) ? $poll['settings'] : [];
    $anonymous = chat_parse_bool($poll['anonymous'] ?? ($settings['anonymous'] ?? true), true);
    $multipleChoice = chat_parse_bool($poll['multipleChoice'] ?? ($settings['multipleChoice'] ?? false), false);
    $allowVoteChange = chat_parse_bool($poll['allowVoteChange'] ?? ($settings['allowVoteChange'] ?? true), true);
    $allowCreatorVote = chat_parse_bool($poll['allowCreatorVote'] ?? ($settings['allowCreatorVote'] ?? true), true);
    $resultVisibility = chat_clean_poll_result_visibility((string) (
        $poll['resultVisibility']
        ?? ($settings['resultVisibility'] ?? CHAT_POLL_VISIBILITY_LIVE)
    ));
    $audience = chat_clean_poll_audience((string) (
        $poll['audience']
        ?? ($settings['audience'] ?? CHAT_POLL_AUDIENCE_LINK)
    ));

    $maxChoicesRaw = (int) (
        $poll['maxChoices']
        ?? ($settings['maxChoices'] ?? ($multipleChoice ? 2 : 1))
    );

    if (!$multipleChoice) {
        $maxChoices = 1;
    } else {
        $maxChoices = max(2, $maxChoicesRaw);
        $maxChoices = min($maxChoices, count($options));
    }

    $votesRaw = $poll['votes'] ?? [];
    if (!is_array($votesRaw)) {
        $votesRaw = [];
    }

    $votes = [];
    foreach ($votesRaw as $studentNumber => $vote) {
        $studentNumber = dent_normalize_student_number((string) $studentNumber);
        if ($studentNumber === '') {
            continue;
        }

        if (is_array($vote)) {
            $selectedOptionIds = chat_normalize_poll_option_ids(
                $vote['optionIds'] ?? ($vote['choices'] ?? []),
                $optionIds
            );
            $votedAt = chat_parse_timestamp($vote['updatedAt'] ?? ($vote['votedAt'] ?? null)) ?? $updatedAt;
        } else {
            $selectedOptionIds = chat_normalize_poll_option_ids($vote, $optionIds);
            $votedAt = $updatedAt;
        }

        if ($selectedOptionIds === []) {
            continue;
        }

        if (!$multipleChoice) {
            $firstSelectedOptionId = reset($selectedOptionIds);
            $selectedOptionIds = [$firstSelectedOptionId !== false ? (string) $firstSelectedOptionId : ''];
            $selectedOptionIds = array_values(array_filter(
                $selectedOptionIds,
                static fn($optionId): bool => (string) $optionId !== ''
            ));
            if ($selectedOptionIds === []) {
                continue;
            }
        } elseif (count($selectedOptionIds) > $maxChoices) {
            $selectedOptionIds = array_slice($selectedOptionIds, 0, $maxChoices);
        }

        sort($selectedOptionIds, SORT_STRING);
        $votes[$studentNumber] = [
            'optionIds' => $selectedOptionIds,
            'updatedAt' => $votedAt,
        ];
    }

    $conversationId = chat_clean_conversation_id((string) ($poll['conversationId'] ?? ''));
    if ($conversationId !== '' && $knownConversationIds !== [] && !isset($knownConversationIds[$conversationId])) {
        $conversationId = '';
    }

    $messageId = (int) ($poll['messageId'] ?? 0);
    if ($messageId <= 0) {
        $messageId = null;
    }

    return [
        'id' => $pollId,
        'conversationId' => $conversationId,
        'messageId' => $messageId,
        'question' => $question,
        'options' => $options,
        'createdBy' => $createdBy,
        'createdAt' => $createdAt,
        'updatedAt' => max($updatedAt, $createdAt),
        'startAt' => $startAt,
        'endAt' => $endAt,
        'closedAt' => $closedAt,
        'closedBy' => $closedBy !== '' ? $closedBy : null,
        'manualClosed' => chat_parse_bool($poll['manualClosed'] ?? ($closedAt !== null), $closedAt !== null),
        'anonymous' => $anonymous,
        'multipleChoice' => $multipleChoice,
        'maxChoices' => $maxChoices,
        'allowVoteChange' => $allowVoteChange,
        'allowCreatorVote' => $allowCreatorVote,
        'resultVisibility' => $resultVisibility,
        'audience' => $audience,
        'votes' => $votes,
    ];
}

function chat_normalize_conversation_record(string $conversationId, array $conversation): ?array
{
    $conversationId = chat_clean_conversation_id($conversationId !== '' ? $conversationId : (string) ($conversation['id'] ?? ''));
    if ($conversationId === '') {
        return null;
    }

    $type = trim((string) ($conversation['type'] ?? 'group'));
    if (!in_array($type, ['class-group', 'direct', 'group'], true)) {
        $type = 'group';
    }
    if ($type !== 'direct' && str_starts_with($conversationId, 'dm:')) {
        $type = 'direct';
    }

    $createdAt = (int) ($conversation['createdAt'] ?? time());
    if ($createdAt <= 0) {
        $createdAt = time();
    }

    $updatedAt = (int) ($conversation['updatedAt'] ?? $createdAt);
    if ($updatedAt <= 0) {
        $updatedAt = $createdAt;
    }

    $memberStudentNumbers = chat_normalize_student_list($conversation['memberStudentNumbers'] ?? []);
    $directParticipants = chat_normalize_student_list($conversation['directParticipants'] ?? []);
    $directParticipantsFromId = chat_dm_participants_from_conversation_id($conversationId);
    $admins = chat_normalize_student_list($conversation['admins'] ?? []);
    $createdBy = dent_normalize_student_number((string) ($conversation['createdBy'] ?? ''));
    $settings = is_array($conversation['settings'] ?? null) ? $conversation['settings'] : [];
    $memberTagsRaw = is_array($conversation['memberTags'] ?? null) ? $conversation['memberTags'] : [];
    $temporary = chat_parse_bool(
        $conversation['temporary'] ?? ($settings['temporary'] ?? false),
        false
    );
    $rawTitle = dent_clean_text((string) ($conversation['title'] ?? ''), 80);

    // Repair legacy malformed DM records that were persisted as group conversations.
    $settingsConversationKind = (string) (($settings['conversationKind'] ?? $settings['kind'] ?? 'group'));
    if ($type === 'group' && count($directParticipants) === 2) {
        $type = 'direct';
    }
    if ($type === 'group' && $directParticipants === [] && count($memberStudentNumbers) === 2 && $rawTitle === '') {
        $type = 'direct';
        $directParticipants = $memberStudentNumbers;
    }
    if ($type === 'group' && $directParticipants === [] && count($memberStudentNumbers) === 2 && $settingsConversationKind !== 'channel') {
        $type = 'direct';
        $directParticipants = $memberStudentNumbers;
    }

    if ($type === 'class-group') {
        $conversationId = CHAT_CLASS_CONVERSATION_ID;
        $memberStudentNumbers = [];
        $directParticipants = [];
        $admins = [];
        $temporary = false;
    } elseif ($type === 'direct') {
        if ($directParticipants === [] && count($directParticipantsFromId) === 2) {
            $directParticipants = $directParticipantsFromId;
        }
        if ($directParticipants === [] && count($memberStudentNumbers) === 2) {
            $directParticipants = $memberStudentNumbers;
        }
        if (count($directParticipants) !== 2) {
            return null;
        }

        sort($directParticipants, SORT_STRING);
        $memberStudentNumbers = $directParticipants;
        $admins = [];
    } else {
        if ($memberStudentNumbers === [] && $createdBy !== '') {
            $memberStudentNumbers = [$createdBy];
        }
        if ($memberStudentNumbers === []) {
            return null;
        }

        if ($createdBy !== '' && !in_array($createdBy, $memberStudentNumbers, true)) {
            $memberStudentNumbers[] = $createdBy;
            sort($memberStudentNumbers, SORT_STRING);
        }

        $admins = array_values(array_filter(
            $admins,
            static fn($studentNumber): bool => in_array(
                dent_normalize_student_number((string) $studentNumber),
                $memberStudentNumbers,
                true
            )
        ));

        if ($admins === [] && $createdBy !== '') {
            $admins[] = $createdBy;
        }
    }

    $title = $rawTitle;
    if ($title === '' && $type === 'group') {
        $title = 'گروه جدید';
    }

    $about = dent_clean_text((string) ($conversation['about'] ?? ''), 280);
    $avatarUrl = dent_clean_avatar_url((string) ($conversation['avatarUrl'] ?? ''), false);
    $memberTags = [];
    foreach ($memberTagsRaw as $studentNumber => $tag) {
        $cleanStudentNumber = dent_normalize_student_number((string) $studentNumber);
        if ($cleanStudentNumber === '' || !in_array($cleanStudentNumber, $memberStudentNumbers, true)) {
            continue;
        }

        $cleanTag = chat_clean_member_tag((string) $tag);
        if ($cleanTag !== '') {
            $memberTags[$cleanStudentNumber] = $cleanTag;
        }
    }

    return [
        'id' => $conversationId,
        'type' => $type,
        'title' => $title,
        'about' => $about,
        'avatarUrl' => $avatarUrl,
        'createdAt' => $createdAt,
        'updatedAt' => $updatedAt,
        'createdBy' => $type === 'direct' ? '' : ($createdBy !== '' ? $createdBy : 'system'),
        'mandatory' => $type === 'class-group' ? true : (bool) ($conversation['mandatory'] ?? false),
        'memberStudentNumbers' => $memberStudentNumbers,
        'directParticipants' => $directParticipants,
        'admins' => $admins,
        'memberTags' => $memberTags,
        'temporary' => $temporary,
        'settings' => chat_default_settings($settings),
    ];
}

function chat_clean_member_tag(?string $value): string
{
    return dent_clean_text((string) $value, 32);
}

function chat_clean_conversation_kind(?string $value): string
{
    $kind = dent_clean_text((string) $value, 20);
    return $kind === 'channel' ? 'channel' : 'group';
}

function chat_clean_reaction_mode(?string $value): string
{
    $mode = dent_clean_text((string) $value, 20);
    return in_array($mode, ['all', 'quick', 'none'], true) ? $mode : 'all';
}

function chat_clean_group_visibility(?string $value): string
{
    $visibility = dent_clean_text((string) $value, 20);
    return $visibility === 'public' ? 'public' : 'private';
}

function chat_quick_reaction_set(): array
{
    return array_fill_keys([
        '👍', '❤️', '😂', '🔥', '👏', '😮', '👎', '😍', '🤔', '🎉', '👌', '🙏',
    ], true);
}

function chat_reaction_allowed_for_conversation(array $conversation, string $emoji): bool
{
    $mode = chat_clean_reaction_mode((string) (($conversation['settings']['reactionMode'] ?? '') ?: 'all'));
    if ($mode === 'none') {
        return false;
    }
    if ($mode === 'quick') {
        $quick = chat_quick_reaction_set();
        return isset($quick[$emoji]);
    }
    return true;
}

function chat_clean_media_relative_path(?string $value): string
{
    $value = trim(str_replace('\\', '/', (string) $value));
    if ($value === '') {
        return '';
    }

    if (str_contains($value, '..')) {
        return '';
    }

    if (preg_match('/^[a-z0-9\/_.-]{3,220}$/', strtolower($value)) !== 1) {
        return '';
    }

    return ltrim($value, '/');
}

function chat_normalize_attachment_record(string $attachmentId, array $attachment, array $knownConversationIds = []): ?array
{
    $attachmentId = chat_clean_attachment_id($attachmentId !== '' ? $attachmentId : (string) ($attachment['id'] ?? ''));
    if ($attachmentId === '') {
        return null;
    }

    $conversationId = chat_clean_conversation_id((string) ($attachment['conversationId'] ?? ''));
    if ($conversationId !== '' && $knownConversationIds !== [] && !isset($knownConversationIds[$conversationId])) {
        $conversationId = '';
    }

    $messageId = isset($attachment['messageId']) ? (int) $attachment['messageId'] : null;
    if ($messageId !== null && $messageId <= 0) {
        $messageId = null;
    }

    $uploaderStudentNumber = dent_normalize_student_number((string) (
        $attachment['uploaderStudentNumber']
        ?? $attachment['studentNumber']
        ?? ''
    ));
    if ($uploaderStudentNumber === '') {
        return null;
    }

    $mime = strtolower(dent_clean_text((string) ($attachment['mime'] ?? ''), 120));
    $extension = chat_safe_extension((string) ($attachment['extension'] ?? ($attachment['ext'] ?? '')));
    $isVoice = chat_parse_bool($attachment['isVoice'] ?? false, false);
    $category = dent_clean_text((string) ($attachment['category'] ?? ''), 20);
    if ($category === '') {
        $category = chat_attachment_category_for_mime($mime, $extension, $isVoice);
    }
    if (!in_array($category, ['image', 'video', 'audio', 'voice', 'document', 'pdf', 'office', 'archive', 'file'], true)) {
        $category = chat_attachment_category_for_mime($mime, $extension, $isVoice);
    }
    if ($category === 'voice') {
        $isVoice = true;
    }

    $status = dent_clean_text((string) ($attachment['status'] ?? 'available'), 20);
    if (!in_array($status, ['available', 'expired'], true)) {
        $status = 'available';
    }

    $createdAt = chat_parse_timestamp($attachment['createdAt'] ?? null) ?? time();
    $updatedAt = chat_parse_timestamp($attachment['updatedAt'] ?? null) ?? $createdAt;
    $linkedAt = chat_parse_timestamp($attachment['linkedAt'] ?? null);
    $purgedAt = chat_parse_timestamp($attachment['purgedAt'] ?? null);
    if ($status !== 'expired') {
        $purgedAt = null;
    } elseif ($purgedAt === null) {
        $purgedAt = $updatedAt;
    }

    $originalPath = chat_clean_media_relative_path((string) ($attachment['originalPath'] ?? ''));
    $previewPath = chat_clean_media_relative_path((string) ($attachment['previewPath'] ?? ''));
    $originalExists = chat_parse_bool($attachment['originalExists'] ?? ($status === 'available' && $originalPath !== ''), $status === 'available' && $originalPath !== '');
    $previewExists = chat_parse_bool($attachment['previewExists'] ?? ($previewPath !== ''), $previewPath !== '');
    if ($status === 'expired') {
        $originalExists = false;
    }

    $originalName = dent_clean_text((string) ($attachment['originalName'] ?? ($attachment['name'] ?? '')), 180);
    if ($originalName === '') {
        $originalName = $attachmentId;
    }

    $safeFileName = dent_clean_text((string) ($attachment['safeFileName'] ?? ''), 200);
    if ($safeFileName === '') {
        $safeFileName = $attachmentId . ($extension !== '' ? ('.' . $extension) : '');
    }

    return [
        'id' => $attachmentId,
        'conversationId' => $conversationId,
        'messageId' => $messageId,
        'uploaderStudentNumber' => $uploaderStudentNumber,
        'originalName' => $originalName,
        'safeFileName' => $safeFileName,
        'mime' => $mime,
        'extension' => $extension,
        'category' => $category,
        'isVoice' => $isVoice,
        'sizeBytes' => max(0, (int) ($attachment['sizeBytes'] ?? 0)),
        'durationSeconds' => isset($attachment['durationSeconds']) ? max(0, (float) $attachment['durationSeconds']) : null,
        'width' => isset($attachment['width']) ? max(1, (int) $attachment['width']) : null,
        'height' => isset($attachment['height']) ? max(1, (int) $attachment['height']) : null,
        'createdAt' => $createdAt,
        'updatedAt' => max($updatedAt, $createdAt),
        'linkedAt' => $linkedAt,
        'status' => $status,
        'purgedAt' => $purgedAt,
        'purgeReason' => dent_clean_text((string) ($attachment['purgeReason'] ?? ''), 140),
        'originalPath' => $originalPath,
        'previewPath' => $previewPath,
        'originalExists' => $originalExists,
        'previewExists' => $previewExists,
    ];
}

function chat_normalize_store(array $rawStore): array
{
    $normalized = chat_new_store_base();
    $maintenance = is_array($rawStore['maintenance'] ?? null)
        ? chat_default_maintenance_state($rawStore['maintenance'])
        : chat_default_maintenance_state();
    $normalized['maintenance'] = $maintenance;

    $conversationsRaw = $rawStore['conversations'] ?? [];
    if (!is_array($conversationsRaw)) {
        $conversationsRaw = [];
    }

    $conversationIdAliases = [];
    foreach ($conversationsRaw as $key => $conversation) {
        if (!is_array($conversation)) {
            continue;
        }

        $normalizedConversation = chat_normalize_conversation_record((string) $key, $conversation);
        if ($normalizedConversation === null) {
            continue;
        }

        $normalizedConversationId = (string) ($normalizedConversation['id'] ?? '');
        if ((string) ($normalizedConversation['type'] ?? '') === 'direct') {
            $participants = chat_normalize_student_list($normalizedConversation['directParticipants'] ?? []);
            if (count($participants) === 2) {
                $canonicalDirectId = chat_dm_conversation_id($participants[0], $participants[1]);
                if ($canonicalDirectId !== '' && $canonicalDirectId !== $normalizedConversationId) {
                    $conversationIdAliases[$normalizedConversationId] = $canonicalDirectId;
                    $normalizedConversation['id'] = $canonicalDirectId;
                    $normalizedConversationId = $canonicalDirectId;
                }
            }
        }

        if (isset($normalized['conversations'][$normalizedConversationId])) {
            $existingConversation = $normalized['conversations'][$normalizedConversationId];
            $normalizedConversation['createdAt'] = min(
                (int) ($existingConversation['createdAt'] ?? time()),
                (int) ($normalizedConversation['createdAt'] ?? time())
            );
            $normalizedConversation['updatedAt'] = max(
                (int) ($existingConversation['updatedAt'] ?? 0),
                (int) ($normalizedConversation['updatedAt'] ?? 0)
            );
        }

        $normalized['conversations'][$normalizedConversation['id']] = $normalizedConversation;
    }

    if (!isset($normalized['conversations'][CHAT_CLASS_CONVERSATION_ID])) {
        $normalized['conversations'][CHAT_CLASS_CONVERSATION_ID] = chat_default_class_conversation();
    }

    $knownConversationIds = [];
    foreach ($normalized['conversations'] as $conversationId => $_conversation) {
        $knownConversationIds[(string) $conversationId] = true;
    }
    foreach ($conversationIdAliases as $oldConversationId => $_newConversationId) {
        $knownConversationIds[(string) $oldConversationId] = true;
    }

    $attachmentsRaw = $rawStore['attachments'] ?? [];
    if (!is_array($attachmentsRaw)) {
        $attachmentsRaw = [];
    }

    $attachments = [];
    $maxAttachmentIndex = 0;
    foreach ($attachmentsRaw as $key => $attachment) {
        if (!is_array($attachment)) {
            continue;
        }

        $normalizedAttachment = chat_normalize_attachment_record((string) $key, $attachment, $knownConversationIds);
        if ($normalizedAttachment === null) {
            continue;
        }
        $attachmentConversationId = (string) ($normalizedAttachment['conversationId'] ?? '');
        if (isset($conversationIdAliases[$attachmentConversationId])) {
            $normalizedAttachment['conversationId'] = $conversationIdAliases[$attachmentConversationId];
        }

        $attachmentId = (string) ($normalizedAttachment['id'] ?? '');
        if ($attachmentId === '') {
            continue;
        }

        if (str_starts_with($attachmentId, CHAT_ATTACHMENT_ID_PREFIX)) {
            $rawIndex = substr($attachmentId, strlen(CHAT_ATTACHMENT_ID_PREFIX));
            if (preg_match('/^(\d+)/', $rawIndex, $matches) === 1) {
                $index = (int) ($matches[1] ?? 0);
                if ($index > $maxAttachmentIndex) {
                    $maxAttachmentIndex = $index;
                }
            }
        }

        $attachments[$attachmentId] = $normalizedAttachment;
    }

    $normalized['attachments'] = $attachments;

    $messagesRaw = $rawStore['messages'] ?? ($rawStore['messagesByConversation'] ?? []);
    if (!is_array($messagesRaw)) {
        $messagesRaw = [];
    }

    $usedMessageIds = [];
    $globalMaxMessageId = 0;

    foreach ($normalized['conversations'] as $conversationId => $conversation) {
        $bucket = $messagesRaw[$conversationId] ?? [];
        if (!is_array($bucket)) {
            $bucket = [];
        }
        foreach ($conversationIdAliases as $oldConversationId => $newConversationId) {
            if ((string) $newConversationId !== (string) $conversationId) {
                continue;
            }
            $legacyBucket = $messagesRaw[$oldConversationId] ?? [];
            if (is_array($legacyBucket) && $legacyBucket !== []) {
                $bucket = array_merge($bucket, $legacyBucket);
            }
        }

        $messages = [];
        foreach ($bucket as $message) {
            if (!is_array($message)) {
                continue;
            }

            $normalizedMessage = chat_normalize_message_record($message, $conversationId);
            if ($normalizedMessage === null) {
                continue;
            }

            $candidateId = (int) ($normalizedMessage['id'] ?? 0);
            if ($candidateId <= 0 || isset($usedMessageIds[$candidateId])) {
                $candidateId = $globalMaxMessageId + 1;
                while (isset($usedMessageIds[$candidateId])) {
                    $candidateId++;
                }
            }

            $normalizedMessage['id'] = $candidateId;
            $usedMessageIds[$candidateId] = true;
            $globalMaxMessageId = max($globalMaxMessageId, $candidateId);
            $messages[] = $normalizedMessage;
        }

        usort($messages, static function (array $left, array $right): int {
            return (int) $left['id'] <=> (int) $right['id'];
        });

        $normalized['messages'][$conversationId] = $messages;

        if ($messages !== []) {
            $last = $messages[count($messages) - 1];
            $normalized['conversations'][$conversationId]['updatedAt'] = max(
                (int) ($normalized['conversations'][$conversationId]['updatedAt'] ?? 0),
                (int) ($last['ts'] ?? 0)
            );
        }
    }

    $readsRaw = $rawStore['reads'] ?? [];
    if (!is_array($readsRaw)) {
        $readsRaw = [];
    }

    $reads = [];
    foreach ($readsRaw as $conversationId => $conversationReads) {
        $conversationId = chat_clean_conversation_id((string) $conversationId);
        if (isset($conversationIdAliases[$conversationId])) {
            $conversationId = $conversationIdAliases[$conversationId];
        }
        if ($conversationId === '' || !isset($normalized['conversations'][$conversationId]) || !is_array($conversationReads)) {
            continue;
        }

        foreach ($conversationReads as $studentNumber => $readInfo) {
            $studentNumber = dent_normalize_student_number((string) $studentNumber);
            if ($studentNumber === '' || !is_array($readInfo)) {
                continue;
            }

            $nextReadState = chat_default_read_state($readInfo);
            if ((bool) ($normalized['conversations'][$conversationId]['mandatory'] ?? false)) {
                $nextReadState['archived'] = false;
                $nextReadState['archivedAt'] = null;
                $nextReadState['deleted'] = false;
                $nextReadState['deletedAt'] = null;
            }

            $reads[$conversationId][$studentNumber] = $nextReadState;
        }
    }

    $normalized['reads'] = $reads;
    $normalized['nextMessageId'] = max((int) ($rawStore['nextMessageId'] ?? 1), $globalMaxMessageId + 1);

    $maxGroupId = 0;
    foreach (array_keys($normalized['conversations']) as $conversationId) {
        if (str_starts_with($conversationId, CHAT_GROUP_ID_PREFIX)) {
            $rawIndex = substr($conversationId, strlen(CHAT_GROUP_ID_PREFIX));
            $index = (int) $rawIndex;
            if ($index > $maxGroupId) {
                $maxGroupId = $index;
            }
        }
    }
    $normalized['nextGroupId'] = max((int) ($rawStore['nextGroupId'] ?? 1), $maxGroupId + 1);

    $pollsRaw = $rawStore['polls'] ?? [];
    if (!is_array($pollsRaw)) {
        $pollsRaw = [];
    }

    $polls = [];
    $maxPollId = 0;
    foreach ($pollsRaw as $key => $poll) {
        if (!is_array($poll)) {
            continue;
        }

        $normalizedPoll = chat_normalize_poll_record((string) $key, $poll, $knownConversationIds);
        if ($normalizedPoll === null) {
            continue;
        }
        $pollConversationId = (string) ($normalizedPoll['conversationId'] ?? '');
        if (isset($conversationIdAliases[$pollConversationId])) {
            $normalizedPoll['conversationId'] = $conversationIdAliases[$pollConversationId];
        }

        $pollId = (string) ($normalizedPoll['id'] ?? '');
        if ($pollId === '') {
            continue;
        }

        $polls[$pollId] = $normalizedPoll;
        if (str_starts_with($pollId, CHAT_POLL_ID_PREFIX)) {
            $rawIndex = substr($pollId, strlen(CHAT_POLL_ID_PREFIX));
            if (preg_match('/^(\d+)/', $rawIndex, $matches) === 1) {
                $index = (int) ($matches[1] ?? 0);
                if ($index > $maxPollId) {
                    $maxPollId = $index;
                }
            }
        }
    }

    foreach ($normalized['messages'] as $conversationId => $messages) {
        if (!is_array($messages)) {
            continue;
        }

        foreach ($messages as $index => $message) {
            if (!is_array($message)) {
                continue;
            }

            $kind = (string) ($message['kind'] ?? 'text');
            if ($kind !== 'poll') {
                continue;
            }

            $pollId = chat_clean_poll_id((string) ($message['pollId'] ?? ''));
            if ($pollId === '' || !isset($polls[$pollId])) {
                $messages[$index]['kind'] = 'text';
                $messages[$index]['pollId'] = '';
            }

            $messageId = (int) ($messages[$index]['id'] ?? 0);
            $attachmentIds = chat_parse_attachment_ids_input($messages[$index]['attachmentIds'] ?? []);
            $resolvedAttachmentIds = [];
            foreach ($attachmentIds as $attachmentId) {
                if (!isset($attachments[$attachmentId])) {
                    continue;
                }

                $attachment = $attachments[$attachmentId];
                if (($attachment['conversationId'] ?? '') !== $conversationId) {
                    continue;
                }

                $resolvedAttachmentIds[] = $attachmentId;
                if (isset($attachments[$attachmentId])) {
                    if ($messageId > 0 && ((int) ($attachments[$attachmentId]['messageId'] ?? 0) <= 0)) {
                        $attachments[$attachmentId]['messageId'] = $messageId;
                        $attachments[$attachmentId]['linkedAt'] = time();
                        $attachments[$attachmentId]['updatedAt'] = time();
                    }
                }
            }

            $messages[$index]['attachmentIds'] = $resolvedAttachmentIds;
            if ($resolvedAttachmentIds === [] && in_array((string) ($messages[$index]['kind'] ?? ''), ['attachment', 'voice'], true)) {
                $messages[$index]['kind'] = 'text';
            } elseif ($resolvedAttachmentIds !== [] && (string) ($messages[$index]['kind'] ?? '') === 'text') {
                $primaryAttachmentId = $resolvedAttachmentIds[0];
                $primaryCategory = (string) ($attachments[$primaryAttachmentId]['category'] ?? 'file');
                $messages[$index]['kind'] = $primaryCategory === 'voice' ? 'voice' : 'attachment';
            }
        }

        $normalized['messages'][$conversationId] = $messages;
    }

    $normalized['attachments'] = $attachments;
    $normalized['polls'] = $polls;
    $normalized['nextPollId'] = max((int) ($rawStore['nextPollId'] ?? 1), $maxPollId + 1);
    $normalized['nextAttachmentId'] = max((int) ($rawStore['nextAttachmentId'] ?? 1), $maxAttachmentIndex + 1);

    return $normalized;
}

function chat_is_classroom_conversation(array $conversation): bool
{
    $type = (string) ($conversation['type'] ?? 'group');
    if ($type === 'class-group') {
        return true;
    }

    return (bool) ($conversation['mandatory'] ?? false);
}

function chat_cleanup_non_class_conversations(array &$store): bool
{
    $conversations = $store['conversations'] ?? [];
    if (!is_array($conversations)) {
        $conversations = [];
    }

    $messages = $store['messages'] ?? [];
    if (!is_array($messages)) {
        $messages = [];
    }

    $reads = $store['reads'] ?? [];
    if (!is_array($reads)) {
        $reads = [];
    }

    $polls = $store['polls'] ?? [];
    if (!is_array($polls)) {
        $polls = [];
    }

    $attachments = $store['attachments'] ?? [];
    if (!is_array($attachments)) {
        $attachments = [];
    }

    $keptConversations = [];
    $removedConversationIds = [];

    foreach ($conversations as $key => $conversation) {
        if (!is_array($conversation)) {
            continue;
        }

        $conversationId = chat_clean_conversation_id((string) ($conversation['id'] ?? $key));
        if ($conversationId === '') {
            continue;
        }

        if (chat_is_classroom_conversation($conversation)) {
            $keptConversations[$conversationId] = $conversation;
        } else {
            $removedConversationIds[$conversationId] = true;
        }
    }

    if (!isset($keptConversations[CHAT_CLASS_CONVERSATION_ID])) {
        $keptConversations[CHAT_CLASS_CONVERSATION_ID] = chat_default_class_conversation();
    }

    $keptMessages = [];
    foreach ($keptConversations as $conversationId => $_conversation) {
        $bucket = $messages[$conversationId] ?? [];
        $keptMessages[$conversationId] = is_array($bucket) ? $bucket : [];
    }

    $keptReads = [];
    foreach ($reads as $conversationId => $conversationReads) {
        $conversationId = chat_clean_conversation_id((string) $conversationId);
        if ($conversationId === '' || !isset($keptConversations[$conversationId]) || !is_array($conversationReads)) {
            continue;
        }

        $keptReads[$conversationId] = $conversationReads;
    }

    $keptPolls = [];
    foreach ($polls as $pollId => $poll) {
        if (!is_array($poll)) {
            continue;
        }

        $pollConversationId = chat_clean_conversation_id((string) ($poll['conversationId'] ?? ''));
        if ($pollConversationId !== '' && !isset($keptConversations[$pollConversationId])) {
            continue;
        }

        $keptPolls[$pollId] = $poll;
    }

    $keptAttachments = [];
    foreach ($attachments as $attachmentId => $attachment) {
        if (!is_array($attachment)) {
            continue;
        }
        $conversationId = chat_clean_conversation_id((string) ($attachment['conversationId'] ?? ''));
        if ($conversationId === '' || isset($keptConversations[$conversationId])) {
            $keptAttachments[$attachmentId] = $attachment;
            continue;
        }

        chat_delete_attachment_artifacts($attachment);
    }

    $store['conversations'] = $keptConversations;
    $store['messages'] = $keptMessages;
    $store['reads'] = $keptReads;
    $store['polls'] = $keptPolls;
    $store['attachments'] = $keptAttachments;

    return $removedConversationIds !== []
        || count($conversations) !== count($keptConversations)
        || count($messages) !== count($keptMessages)
        || count($reads) !== count($keptReads)
        || count($polls) !== count($keptPolls)
        || count($attachments) !== count($keptAttachments);
}

function chat_title_marks_test_conversation(string $title): bool
{
    $normalized = strtolower(trim($title));
    if ($normalized === '') {
        return false;
    }

    $prefixes = [
        'grp-e2e-',
        'e2e-',
        'tmp-chat-',
        'tmp-e2e-',
        '[e2e]',
        '[tmp-chat]',
    ];

    foreach ($prefixes as $prefix) {
        if (str_starts_with($normalized, $prefix)) {
            return true;
        }
    }

    return false;
}

function chat_is_temporary_test_conversation(array $conversation): bool
{
    if (chat_is_classroom_conversation($conversation)) {
        return false;
    }

    if (chat_parse_bool($conversation['temporary'] ?? false, false)) {
        return true;
    }

    $settings = is_array($conversation['settings'] ?? null) ? $conversation['settings'] : [];
    if (chat_parse_bool($settings['temporary'] ?? false, false)) {
        return true;
    }

    return chat_title_marks_test_conversation((string) ($conversation['title'] ?? ''));
}

function chat_cleanup_temporary_test_conversations(array &$store): bool
{
    $conversations = $store['conversations'] ?? [];
    if (!is_array($conversations)) {
        return false;
    }

    $messages = $store['messages'] ?? [];
    if (!is_array($messages)) {
        $messages = [];
    }

    $reads = $store['reads'] ?? [];
    if (!is_array($reads)) {
        $reads = [];
    }

    $polls = $store['polls'] ?? [];
    if (!is_array($polls)) {
        $polls = [];
    }

    $attachments = $store['attachments'] ?? [];
    if (!is_array($attachments)) {
        $attachments = [];
    }

    $removedConversationIds = [];
    foreach ($conversations as $key => $conversation) {
        if (!is_array($conversation)) {
            continue;
        }

        $conversationId = chat_clean_conversation_id((string) ($conversation['id'] ?? $key));
        if ($conversationId === '') {
            continue;
        }

        if (chat_is_temporary_test_conversation($conversation)) {
            $removedConversationIds[$conversationId] = true;
        }
    }

    if ($removedConversationIds === []) {
        return false;
    }

    foreach ($removedConversationIds as $conversationId => $_true) {
        unset($conversations[$conversationId], $messages[$conversationId], $reads[$conversationId]);
    }

    foreach ($polls as $pollId => $poll) {
        if (!is_array($poll)) {
            continue;
        }

        $pollConversationId = chat_clean_conversation_id((string) ($poll['conversationId'] ?? ''));
        if ($pollConversationId !== '' && isset($removedConversationIds[$pollConversationId])) {
            unset($polls[$pollId]);
        }
    }

    foreach ($attachments as $attachmentId => $attachment) {
        if (!is_array($attachment)) {
            unset($attachments[$attachmentId]);
            continue;
        }
        $conversationId = chat_clean_conversation_id((string) ($attachment['conversationId'] ?? ''));
        if ($conversationId !== '' && isset($removedConversationIds[$conversationId])) {
            chat_delete_attachment_artifacts($attachment);
            unset($attachments[$attachmentId]);
        }
    }

    if (!isset($conversations[CHAT_CLASS_CONVERSATION_ID])) {
        $conversations[CHAT_CLASS_CONVERSATION_ID] = chat_default_class_conversation();
    }
    if (!isset($messages[CHAT_CLASS_CONVERSATION_ID]) || !is_array($messages[CHAT_CLASS_CONVERSATION_ID])) {
        $messages[CHAT_CLASS_CONVERSATION_ID] = [];
    }

    $store['conversations'] = $conversations;
    $store['messages'] = $messages;
    $store['reads'] = $reads;
    $store['polls'] = $polls;
    $store['attachments'] = $attachments;

    return true;
}

function chat_legacy_migration_enabled(): bool
{
    return chat_parse_bool(getenv('DENT_CHAT_ALLOW_LEGACY_MIGRATION') ?: false, false);
}

function chat_has_legacy_store_files(): bool
{
    if (chat_is_prosthesis_context()) {
        return false;
    }

    return is_file(chat_legacy_messages_path()) || is_file(chat_legacy_state_path());
}

function chat_read_store_file(): ?array
{
    $path = chat_store_path();
    if (!is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        dent_error('Chat canonical store is unreadable.', 503);
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        dent_error('Chat canonical store is temporarily invalid. Deployment must not fall back to legacy files.', 503);
    }

    return $decoded;
}

function chat_write_store_file(array $store): void
{
    dent_ensure_directory(dirname(chat_store_path()));

    $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $store['schemaVersion'] = CHAT_SCHEMA_VERSION;
    $store['classConversationId'] = CHAT_CLASS_CONVERSATION_ID;

    $json = json_encode($store, $flags);
    if ($json === false) {
        dent_error('Chat store JSON encoding failed.', 500);
    }

    $path = chat_store_path();
    $tmpPath = $path . '.tmp.' . getmypid() . '.' . str_replace('.', '', uniqid('', true));
    if (@file_put_contents($tmpPath, $json . PHP_EOL, LOCK_EX) === false) {
        @unlink($tmpPath);
        dent_error('Chat store write failed.', 500);
    }
    @chmod($tmpPath, 0644);

    if (!@rename($tmpPath, $path)) {
        if (is_file($path)) {
            @unlink($path);
        }
        if (!@rename($tmpPath, $path)) {
            @unlink($tmpPath);
            dent_error('Chat store replace failed.', 500);
        }
    }
}

function chat_migrate_from_legacy_files(): array
{
    $legacyState = dent_read_json_file(chat_legacy_state_path(), [
        'muted' => false,
        'mutedBy' => null,
        'mutedAt' => null,
    ]);
    if (!is_array($legacyState)) {
        $legacyState = [];
    }

    $store = chat_new_store_base($legacyState);

    $legacyMessages = dent_read_json_file(chat_legacy_messages_path(), []);
    if (!is_array($legacyMessages)) {
        $legacyMessages = [];
    }

    $migrated = [];
    foreach ($legacyMessages as $legacyMessage) {
        if (!is_array($legacyMessage)) {
            continue;
        }

        $normalizedMessage = chat_normalize_message_record($legacyMessage, CHAT_CLASS_CONVERSATION_ID);
        if ($normalizedMessage !== null) {
            $migrated[] = $normalizedMessage;
        }
    }

    if ($migrated !== []) {
        usort($migrated, static function (array $left, array $right): int {
            return (int) $left['id'] <=> (int) $right['id'];
        });
        $store['messages'][CHAT_CLASS_CONVERSATION_ID] = $migrated;
    }

    return chat_normalize_store($store);
}

function chat_load_store(): array
{
    chat_acquire_store_lock();

    $rawStore = chat_read_store_file();
    if (!is_array($rawStore)) {
        if (chat_has_legacy_store_files() && !chat_legacy_migration_enabled()) {
            dent_error('Chat canonical store is missing and legacy migration is disabled.', 503);
        }

        $store = chat_has_legacy_store_files()
            ? chat_migrate_from_legacy_files()
            : chat_new_store_base();
        chat_save_store($store);
        return $store;
    }

    $rawSchemaVersion = max(1, (int) ($rawStore['schemaVersion'] ?? 1));
    $normalized = chat_normalize_store($rawStore);
    $markedNonClassCleanupComplete = false;
    $cleanedUpTemporaryConversations = false;
    $mediaMaintenanceChanged = false;
    $maintenance = is_array($normalized['maintenance'] ?? null)
        ? chat_default_maintenance_state($normalized['maintenance'])
        : chat_default_maintenance_state();
    if (!chat_parse_bool($maintenance['nonClassCleanupDone'] ?? false, false)) {
        $normalized['maintenance'] = chat_default_maintenance_state(array_merge($maintenance, [
            'nonClassCleanupDone' => true,
            'nonClassCleanupAt' => time(),
        ]));
        $normalized = chat_normalize_store($normalized);
        $markedNonClassCleanupComplete = true;
    }
    $cleanedUpTemporaryConversations = chat_cleanup_temporary_test_conversations($normalized);
    if ($cleanedUpTemporaryConversations) {
        $normalized = chat_normalize_store($normalized);
    }

    $maintenance = is_array($normalized['maintenance'] ?? null)
        ? chat_default_maintenance_state($normalized['maintenance'])
        : chat_default_maintenance_state();
    $lastMaintenanceAt = chat_parse_timestamp($maintenance['lastMediaMaintenanceAt'] ?? null);
    $shouldRunMediaMaintenance = $lastMaintenanceAt === null
        || (time() - $lastMaintenanceAt) >= CHAT_MEDIA_MAINTENANCE_INTERVAL_SECONDS;
    if ($shouldRunMediaMaintenance) {
        $mediaMaintenanceChanged = chat_sync_media_health($normalized, false);
    }

    $rawEncoded = json_encode($rawStore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $normalizedEncoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (
        $markedNonClassCleanupComplete
        || $cleanedUpTemporaryConversations
        || $mediaMaintenanceChanged
        || $rawSchemaVersion < CHAT_SCHEMA_VERSION
        || $rawEncoded !== $normalizedEncoded
    ) {
        chat_save_store($normalized);
    }

    return $normalized;
}

function chat_save_store(array $store): void
{
    chat_acquire_store_lock();
    chat_write_store_file($store);
}

function chat_read_store_snapshot(bool $fresh = false): array
{
    static $cache = [];
    $cacheKey = chat_active_cohort();
    if (!$fresh && isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $rawStore = chat_read_store_file();
    $cache[$cacheKey] = is_array($rawStore)
        ? chat_normalize_store($rawStore)
        : chat_new_store_base();
    return $cache[$cacheKey];
}

function chat_store_file_signature(): string
{
    $path = chat_store_path();
    clearstatcache(true, $path);
    if (!is_file($path)) {
        return '0:0';
    }

    $mtime = @filemtime($path) ?: 0;
    $size = @filesize($path) ?: 0;
    return $mtime . ':' . $size;
}

function chat_get_conversation(array $store, string $conversationId): ?array
{
    $conversationId = chat_clean_conversation_id($conversationId);
    if ($conversationId === '') {
        return null;
    }

    $conversation = $store['conversations'][$conversationId] ?? null;
    return is_array($conversation) ? $conversation : null;
}

function chat_put_conversation(array &$store, array $conversation): void
{
    $conversationId = chat_clean_conversation_id((string) ($conversation['id'] ?? ''));
    if ($conversationId === '') {
        return;
    }

    $store['conversations'][$conversationId] = $conversation;
}

function chat_get_messages(array $store, string $conversationId): array
{
    $messages = $store['messages'][$conversationId] ?? [];
    return is_array($messages) ? $messages : [];
}

function chat_put_messages(array &$store, string $conversationId, array $messages): void
{
    $store['messages'][$conversationId] = array_values($messages);
}

function chat_next_message_id(array &$store): int
{
    $next = max(1, (int) ($store['nextMessageId'] ?? 1));
    $store['nextMessageId'] = $next + 1;
    return $next;
}

function chat_next_group_conversation_id(array &$store): string
{
    $next = max(1, (int) ($store['nextGroupId'] ?? 1));
    $store['nextGroupId'] = $next + 1;
    return CHAT_GROUP_ID_PREFIX . $next;
}

function chat_next_poll_id(array &$store): string
{
    $next = max(1, (int) ($store['nextPollId'] ?? 1));
    $store['nextPollId'] = $next + 1;
    return CHAT_POLL_ID_PREFIX . $next;
}

function chat_next_attachment_id(array &$store): string
{
    $next = max(1, (int) ($store['nextAttachmentId'] ?? 1));
    $store['nextAttachmentId'] = $next + 1;
    return CHAT_ATTACHMENT_ID_PREFIX . $next;
}

function chat_get_poll(array $store, string $pollId): ?array
{
    $pollId = chat_clean_poll_id($pollId);
    if ($pollId === '') {
        return null;
    }

    $poll = $store['polls'][$pollId] ?? null;
    return is_array($poll) ? $poll : null;
}

function chat_put_poll(array &$store, array $poll): void
{
    $pollId = chat_clean_poll_id((string) ($poll['id'] ?? ''));
    if ($pollId === '') {
        return;
    }

    if (!isset($store['polls']) || !is_array($store['polls'])) {
        $store['polls'] = [];
    }

    $store['polls'][$pollId] = $poll;
}

function chat_get_attachment(array $store, string $attachmentId): ?array
{
    $attachmentId = chat_clean_attachment_id($attachmentId);
    if ($attachmentId === '') {
        return null;
    }

    $attachment = $store['attachments'][$attachmentId] ?? null;
    return is_array($attachment) ? $attachment : null;
}

function chat_put_attachment(array &$store, array $attachment): void
{
    $attachmentId = chat_clean_attachment_id((string) ($attachment['id'] ?? ''));
    if ($attachmentId === '') {
        return;
    }

    if (!isset($store['attachments']) || !is_array($store['attachments'])) {
        $store['attachments'] = [];
    }

    $conversationIds = [];
    foreach ((array) ($store['conversations'] ?? []) as $conversationId => $_conversation) {
        $conversationIds[(string) $conversationId] = true;
    }

    $normalized = chat_normalize_attachment_record($attachmentId, $attachment, $conversationIds);
    if ($normalized === null) {
        return;
    }

    $store['attachments'][$attachmentId] = $normalized;
}

function chat_log_media_cleanup(string $message): void
{
    $line = '[' . date('c') . '] ' . dent_force_utf8($message) . PHP_EOL;
    @file_put_contents(chat_media_cleanup_log_path(), $line, FILE_APPEND | LOCK_EX);
}

function chat_mark_attachment_expired(array &$attachment, string $reason, bool $keepStatusWhenAlreadyExpired = true): void
{
    $currentStatus = (string) ($attachment['status'] ?? 'available');
    if (!$keepStatusWhenAlreadyExpired || $currentStatus !== 'expired') {
        $attachment['status'] = 'expired';
    }
    $attachment['originalExists'] = false;
    $attachment['purgedAt'] = time();
    $attachment['updatedAt'] = time();
    if (dent_clean_text($reason, 140) !== '') {
        $attachment['purgeReason'] = dent_clean_text($reason, 140);
    }
}

function chat_attachment_size_bytes(array $attachment): int
{
    $path = chat_attachment_absolute_path((string) ($attachment['originalPath'] ?? ''));
    if ($path !== '' && is_file($path)) {
        $size = @filesize($path);
        if ($size !== false) {
            return max(0, (int) $size);
        }
    }
    return max(0, (int) ($attachment['sizeBytes'] ?? 0));
}

function chat_sync_attachment_file_flags(array &$attachment): bool
{
    $changed = false;

    $originalPath = chat_attachment_absolute_path((string) ($attachment['originalPath'] ?? ''));
    $previewPath = chat_attachment_absolute_path((string) ($attachment['previewPath'] ?? ''));
    $originalExists = $originalPath !== '' && is_file($originalPath);
    $previewExists = $previewPath !== '' && is_file($previewPath);

    if ((bool) ($attachment['originalExists'] ?? false) !== $originalExists) {
        $attachment['originalExists'] = $originalExists;
        $changed = true;
    }
    if ((bool) ($attachment['previewExists'] ?? false) !== $previewExists) {
        $attachment['previewExists'] = $previewExists;
        $changed = true;
    }

    if (!$originalExists && (string) ($attachment['status'] ?? 'available') === 'available') {
        chat_mark_attachment_expired($attachment, 'original-file-missing');
        $changed = true;
    }

    return $changed;
}

function chat_delete_attachment_original_file(array &$attachment): bool
{
    $path = chat_attachment_absolute_path((string) ($attachment['originalPath'] ?? ''));
    if ($path === '' || !is_file($path)) {
        chat_mark_attachment_expired($attachment, 'original-file-missing');
        return true;
    }

    if (!@unlink($path)) {
        return false;
    }

    chat_mark_attachment_expired($attachment, 'storage-cleanup');
    return true;
}

function chat_delete_attachment_artifacts(array $attachment): void
{
    $originalPath = chat_attachment_absolute_path((string) ($attachment['originalPath'] ?? ''));
    if ($originalPath !== '' && is_file($originalPath)) {
        @unlink($originalPath);
    }

    $previewPath = chat_attachment_absolute_path((string) ($attachment['previewPath'] ?? ''));
    if ($previewPath !== '' && is_file($previewPath)) {
        @unlink($previewPath);
    }
}

function chat_sync_media_health(array &$store, bool $forceCleanup = false): bool
{
    $attachments = is_array($store['attachments'] ?? null) ? $store['attachments'] : [];
    $maintenance = is_array($store['maintenance'] ?? null)
        ? chat_default_maintenance_state($store['maintenance'])
        : chat_default_maintenance_state();

    $changed = false;
    $usageBytes = 0;
    $originalCount = 0;
    $cleanupThreshold = max(0.1, min(0.95, (float) ($maintenance['mediaCleanupThreshold'] ?? CHAT_MEDIA_CLEANUP_THRESHOLD)));
    $targetBytes = max(64 * 1024 * 1024, (int) ($maintenance['mediaTargetBytes'] ?? chat_media_target_bytes()));

    foreach ($attachments as $attachmentId => $attachment) {
        if (!is_array($attachment)) {
            continue;
        }
        $normalizedAttachment = chat_normalize_attachment_record((string) $attachmentId, $attachment);
        if ($normalizedAttachment === null) {
            unset($attachments[$attachmentId]);
            $changed = true;
            continue;
        }

        if (chat_sync_attachment_file_flags($normalizedAttachment)) {
            $changed = true;
        }

        if ((bool) ($normalizedAttachment['originalExists'] ?? false)) {
            $originalCount++;
            $usageBytes += chat_attachment_size_bytes($normalizedAttachment);
        }

        $attachments[$attachmentId] = $normalizedAttachment;
    }

    $thresholdBytes = (int) floor($targetBytes * $cleanupThreshold);
    $cleanupTriggered = $forceCleanup || $usageBytes > $thresholdBytes;
    $purgedCountThisRun = 0;
    if ($cleanupTriggered && $usageBytes > 0) {
        $candidates = [];
        foreach ($attachments as $attachmentId => $attachment) {
            if (!is_array($attachment) || !(bool) ($attachment['originalExists'] ?? false)) {
                continue;
            }

            $candidates[] = [
                'id' => (string) $attachmentId,
                'createdAt' => (int) ($attachment['createdAt'] ?? 0),
            ];
        }

        usort($candidates, static function (array $left, array $right): int {
            if ((int) $left['createdAt'] !== (int) $right['createdAt']) {
                return (int) $left['createdAt'] <=> (int) $right['createdAt'];
            }
            return strcasecmp((string) $left['id'], (string) $right['id']);
        });

        foreach ($candidates as $candidate) {
            if ($usageBytes <= $thresholdBytes) {
                break;
            }

            $attachmentId = (string) ($candidate['id'] ?? '');
            if ($attachmentId === '' || !isset($attachments[$attachmentId]) || !is_array($attachments[$attachmentId])) {
                continue;
            }

            $size = chat_attachment_size_bytes($attachments[$attachmentId]);
            $deleted = chat_delete_attachment_original_file($attachments[$attachmentId]);
            if (!$deleted) {
                $maintenance['lastMediaCleanupStatus'] = 'failed';
                $maintenance['lastMediaCleanupError'] = 'failed-to-delete-' . $attachmentId;
                chat_log_media_cleanup('FAILED delete original for ' . $attachmentId);
                continue;
            }

            $purgedCountThisRun++;
            $usageBytes = max(0, $usageBytes - $size);
            $originalCount = max(0, $originalCount - 1);
            chat_log_media_cleanup('Purged original file for ' . $attachmentId . ' (' . $size . ' bytes)');
            $changed = true;
        }
    }

    $maintenance['mediaTargetBytes'] = $targetBytes;
    $maintenance['mediaCleanupThreshold'] = $cleanupThreshold;
    $maintenance['mediaUsageBytes'] = max(0, $usageBytes);
    $maintenance['mediaOriginalCount'] = max(0, $originalCount);
    $maintenance['lastMediaMaintenanceAt'] = time();
    if ($cleanupTriggered) {
        $maintenance['mediaCleanupRuns'] = max(0, (int) ($maintenance['mediaCleanupRuns'] ?? 0)) + 1;
        $maintenance['lastMediaCleanupAt'] = time();
        $maintenance['mediaPurgedCount'] = max(0, (int) ($maintenance['mediaPurgedCount'] ?? 0)) + $purgedCountThisRun;
        if ($purgedCountThisRun > 0) {
            $maintenance['lastMediaCleanupStatus'] = 'purged';
            $maintenance['lastMediaCleanupError'] = '';
        } elseif (($maintenance['lastMediaCleanupStatus'] ?? '') !== 'failed') {
            $maintenance['lastMediaCleanupStatus'] = 'ok';
            $maintenance['lastMediaCleanupError'] = '';
        }
    } elseif (($maintenance['lastMediaCleanupStatus'] ?? '') === '') {
        $maintenance['lastMediaCleanupStatus'] = 'ok';
    }

    if (($store['attachments'] ?? null) !== $attachments) {
        $store['attachments'] = $attachments;
        $changed = true;
    }

    if (($store['maintenance'] ?? null) !== $maintenance) {
        $store['maintenance'] = $maintenance;
        $changed = true;
    }

    return $changed;
}

function chat_media_status_payload(array $store): array
{
    $maintenance = is_array($store['maintenance'] ?? null)
        ? chat_default_maintenance_state($store['maintenance'])
        : chat_default_maintenance_state();
    $targetBytes = max(64 * 1024 * 1024, (int) ($maintenance['mediaTargetBytes'] ?? chat_media_target_bytes()));
    $usageBytes = max(0, (int) ($maintenance['mediaUsageBytes'] ?? 0));
    $usagePercent = $targetBytes > 0 ? (($usageBytes / $targetBytes) * 100) : 0;

    return [
        'targetBytes' => $targetBytes,
        'usageBytes' => $usageBytes,
        'usagePercent' => round($usagePercent, 2),
        'thresholdPercent' => round(max(0.1, min(0.95, (float) ($maintenance['mediaCleanupThreshold'] ?? CHAT_MEDIA_CLEANUP_THRESHOLD))) * 100, 2),
        'originalCount' => max(0, (int) ($maintenance['mediaOriginalCount'] ?? 0)),
        'purgedCount' => max(0, (int) ($maintenance['mediaPurgedCount'] ?? 0)),
        'cleanupRuns' => max(0, (int) ($maintenance['mediaCleanupRuns'] ?? 0)),
        'lastCleanupAt' => isset($maintenance['lastMediaCleanupAt']) ? (int) $maintenance['lastMediaCleanupAt'] : null,
        'lastMaintenanceAt' => isset($maintenance['lastMediaMaintenanceAt']) ? (int) $maintenance['lastMediaMaintenanceAt'] : null,
        'lastCleanupStatus' => (string) ($maintenance['lastMediaCleanupStatus'] ?? ''),
        'lastCleanupError' => (string) ($maintenance['lastMediaCleanupError'] ?? ''),
        'cleanupHealthy' => (string) ($maintenance['lastMediaCleanupStatus'] ?? 'ok') !== 'failed',
    ];
}

function chat_find_message_index(array $messages, int $messageId): int
{
    foreach ($messages as $index => $message) {
        if ((int) ($message['id'] ?? 0) === $messageId) {
            return (int) $index;
        }
    }

    return -1;
}

function chat_last_message(array $messages): ?array
{
    if ($messages === []) {
        return null;
    }

    return $messages[count($messages) - 1];
}

function chat_latest_pinned_message(array $messages): ?array
{
    $pinned = null;
    foreach ($messages as $message) {
        if (!(bool) ($message['pinned'] ?? false)) {
            continue;
        }

        if ($pinned === null || (int) ($message['id'] ?? 0) > (int) ($pinned['id'] ?? 0)) {
            $pinned = $message;
        }
    }

    return $pinned;
}

function chat_sanitize_message_text(string $value, int $maxLength = 2000): string
{
    return dent_clean_text($value, $maxLength);
}

function chat_require_user(): array
{
    $user = dent_require_user();
    $role = dent_normalize_role((string) ($user['role'] ?? 'student'), (string) ($user['studentNumber'] ?? ''));
    if (function_exists('dent_user_is_external_exam_role') && dent_user_is_external_exam_role($role)) {
        dent_error('چت فقط برای دانشجویان دارای شماره دانشجویی فعال است.', 403);
    }

    $requestedCohort = chat_requested_cohort();
    if ($role === 'owner') {
        chat_set_active_cohort($requestedCohort);
    } else {
        $userCohort = dent_user_cohort_key($user);
        if ($requestedCohort !== dent_primary_cohort_key() && $requestedCohort !== $userCohort) {
            dent_error('این پیام‌رسان برای حساب شما فعال نیست.', 403);
        }
        chat_set_active_cohort($userCohort);
    }

    chat_ensure_storage();
    $actorStudentNumber = chat_actor_student_number($user);
    if ($actorStudentNumber === '') {
        dent_error('هویت کاربر نامعتبر است.', 401, ['loggedOut' => true]);
    }

    $user['studentNumber'] = $actorStudentNumber;
    $user['username'] = $actorStudentNumber;
    $user['student_number'] = $actorStudentNumber;
    dent_release_session_lock();
    return $user;
}

function chat_user_belongs_to_active_cohort(array $user): bool
{
    $role = dent_normalize_role((string) ($user['role'] ?? 'student'), (string) ($user['studentNumber'] ?? ''));
    if ($role === 'owner') {
        return true;
    }

    return dent_user_cohort_key($user) === chat_active_cohort();
}

function chat_user_can_moderate_active_cohort(array $user): bool
{
    return dent_can_moderate_chat($user, chat_active_cohort());
}

function chat_public_user_payload(array $user): array
{
    $public = dent_public_user($user);
    return [
        'studentNumber' => (string) ($public['studentNumber'] ?? ''),
        'username' => (string) ($public['studentNumber'] ?? ''),
        'name' => (string) ($public['name'] ?? ''),
        'role' => (string) ($public['role'] ?? 'student'),
        'roleLabel' => (string) ($public['roleLabel'] ?? dent_role_label('student')),
        'isOwner' => (bool) ($public['isOwner'] ?? false),
        'isRepresentative' => chat_user_can_moderate_active_cohort($user),
        'canModerateChat' => chat_user_can_moderate_active_cohort($user),
        'profile' => is_array($public['profile'] ?? null) ? $public['profile'] : dent_default_profile(),
        'avatarUrl' => (string) (($public['profile']['avatarUrl'] ?? '') ?: ''),
        'about' => (string) (($public['profile']['about'] ?? ($public['profile']['bio'] ?? '')) ?: ''),
    ];
}

function chat_public_user_for_student($studentNumber): array
{
    static $cache = [];

    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        return [
            'studentNumber' => '',
            'username' => '',
            'name' => dent_role_label('student'),
            'role' => 'student',
            'roleLabel' => dent_role_label('student'),
            'canModerateChat' => false,
            'profile' => dent_default_profile(),
            'avatarUrl' => '',
            'about' => '',
        ];
    }

    if (isset($cache[$studentNumber])) {
        return $cache[$studentNumber];
    }

    $user = dent_get_user_record($studentNumber);
    if ($user === null) {
        $cache[$studentNumber] = [
            'studentNumber' => $studentNumber,
            'username' => $studentNumber,
            'name' => dent_role_label('student'),
            'role' => 'student',
            'roleLabel' => dent_role_label('student'),
            'canModerateChat' => false,
            'profile' => dent_default_profile(),
            'avatarUrl' => '',
            'about' => '',
        ];
        return $cache[$studentNumber];
    }

    $cache[$studentNumber] = chat_public_user_payload($user);
    return $cache[$studentNumber];
}

function chat_conversation_member_student_numbers(array $conversation): array
{
    $type = (string) ($conversation['type'] ?? 'group');
    if ($type === 'class-group') {
        $users = dent_list_public_users();
        $studentNumbers = [];
        foreach ($users as $user) {
            $record = dent_get_user_record((string) ($user['studentNumber'] ?? ''));
            if (!is_array($record) || !chat_user_belongs_to_active_cohort($record)) {
                continue;
            }
            $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
            if ($studentNumber !== '') {
                $studentNumbers[] = $studentNumber;
            }
        }
        sort($studentNumbers, SORT_STRING);
        return $studentNumbers;
    }

    if ($type === 'direct') {
        return chat_normalize_student_list($conversation['directParticipants'] ?? []);
    }

    return chat_normalize_student_list($conversation['memberStudentNumbers'] ?? []);
}

function chat_is_member(array $conversation, string $studentNumber): bool
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        return false;
    }

    if ((string) ($conversation['type'] ?? '') === 'class-group') {
        return true;
    }

    return in_array($studentNumber, chat_conversation_member_student_numbers($conversation), true);
}

function chat_is_group_admin(array $conversation, string $studentNumber): bool
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        return false;
    }

    return in_array($studentNumber, chat_normalize_student_list($conversation['admins'] ?? []), true);
}

function chat_presence_entry_for_student(array $presenceStore, string $studentNumber): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        return [
            'studentNumber' => '',
            'lastSeenAt' => null,
            'lastActiveAt' => null,
            'lastHeartbeatAt' => null,
            'activeConversationId' => '',
            'typingConversationId' => '',
            'typingExpiresAt' => null,
        ];
    }

    $users = is_array($presenceStore['users'] ?? null) ? $presenceStore['users'] : [];
    $entry = is_array($users[$studentNumber] ?? null) ? $users[$studentNumber] : [];
    return chat_normalize_presence_entry($studentNumber, $entry, time()) ?? [
        'studentNumber' => $studentNumber,
        'lastSeenAt' => null,
        'lastActiveAt' => null,
        'lastHeartbeatAt' => null,
        'activeConversationId' => '',
        'typingConversationId' => '',
        'typingExpiresAt' => null,
    ];
}

function chat_presence_is_online(array $entry, ?int $now = null): bool
{
    $now = $now ?? time();
    $heartbeatAt = chat_parse_timestamp($entry['lastHeartbeatAt'] ?? null);
    return $heartbeatAt !== null && ($now - $heartbeatAt) <= CHAT_PRESENCE_ONLINE_WINDOW_SECONDS;
}

function chat_presence_is_typing_in_conversation(array $entry, string $conversationId, ?int $now = null): bool
{
    $now = $now ?? time();
    $conversationId = chat_clean_conversation_id($conversationId);
    if ($conversationId === '') {
        return false;
    }

    $typingConversationId = chat_clean_conversation_id((string) ($entry['typingConversationId'] ?? ''));
    $typingExpiresAt = chat_parse_timestamp($entry['typingExpiresAt'] ?? null);
    return $typingConversationId !== '' && $typingConversationId === $conversationId
        && $typingExpiresAt !== null
        && $typingExpiresAt > $now;
}

function chat_presence_state_payload(array $entry, string $conversationId = '', ?int $now = null): array
{
    $now = $now ?? time();
    $conversationId = chat_clean_conversation_id($conversationId);
    return [
        'studentNumber' => dent_normalize_student_number((string) ($entry['studentNumber'] ?? '')),
        'isOnline' => chat_presence_is_online($entry, $now),
        'isTyping' => $conversationId !== '' ? chat_presence_is_typing_in_conversation($entry, $conversationId, $now) : false,
        'lastSeenAt' => chat_parse_timestamp($entry['lastSeenAt'] ?? null),
        'lastActiveAt' => chat_parse_timestamp($entry['lastActiveAt'] ?? null),
        'lastHeartbeatAt' => chat_parse_timestamp($entry['lastHeartbeatAt'] ?? null),
        'activeConversationId' => chat_clean_conversation_id((string) ($entry['activeConversationId'] ?? '')),
        'typingConversationId' => chat_clean_conversation_id((string) ($entry['typingConversationId'] ?? '')),
        'typingExpiresAt' => chat_parse_timestamp($entry['typingExpiresAt'] ?? null),
    ];
}

function chat_presence_typing_user_payloads(
    array $presenceStore,
    array $conversation,
    string $viewerStudentNumber,
    ?int $now = null
): array {
    $now = $now ?? time();
    $conversationId = chat_clean_conversation_id((string) ($conversation['id'] ?? ''));
    if ($conversationId === '') {
        return [];
    }

    $typingUsers = [];
    foreach (chat_conversation_member_student_numbers($conversation) as $studentNumber) {
        $studentNumber = dent_normalize_student_number((string) $studentNumber);
        if ($studentNumber === '' || $studentNumber === $viewerStudentNumber) {
            continue;
        }

        $entry = chat_presence_entry_for_student($presenceStore, $studentNumber);
        if (!chat_presence_is_typing_in_conversation($entry, $conversationId, $now)) {
            continue;
        }

        $user = chat_public_user_for_student($studentNumber);
        $profile = is_array($user['profile'] ?? null) ? $user['profile'] : dent_default_profile();
        $typingUsers[] = [
            'studentNumber' => $studentNumber,
            'name' => (string) ($user['name'] ?? dent_role_label('student')),
            'role' => (string) ($user['role'] ?? 'student'),
            'roleLabel' => (string) ($user['roleLabel'] ?? dent_role_label('student')),
            'avatarUrl' => (string) (($profile['avatarUrl'] ?? '') ?: ''),
            'presence' => chat_presence_state_payload($entry, $conversationId, $now),
        ];
    }

    usort($typingUsers, static function (array $left, array $right): int {
        $leftTs = (int) ($left['presence']['typingExpiresAt'] ?? 0);
        $rightTs = (int) ($right['presence']['typingExpiresAt'] ?? 0);
        if ($leftTs !== $rightTs) {
            return $rightTs <=> $leftTs;
        }
        return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
    });

    return array_slice($typingUsers, 0, 4);
}

function chat_conversation_presence_payload(array $conversation, array $viewer, ?array $presenceStore = null): array
{
    $presenceStore = is_array($presenceStore) ? $presenceStore : chat_read_presence_snapshot();
    $viewerStudentNumber = chat_actor_student_number($viewer);
    $conversationId = chat_clean_conversation_id((string) ($conversation['id'] ?? ''));
    $conversationType = (string) ($conversation['type'] ?? 'group');
    $now = time();

    $payload = [
        'conversationId' => $conversationId,
        'onlineCount' => 0,
        'typingUsers' => [],
        'peer' => null,
    ];

    if ($conversationId === '') {
        return $payload;
    }

    if ($conversationType === 'direct') {
        foreach (chat_normalize_student_list($conversation['directParticipants'] ?? []) as $studentNumber) {
            if ($studentNumber === $viewerStudentNumber) {
                continue;
            }

            $entry = chat_presence_entry_for_student($presenceStore, $studentNumber);
            $payload['peer'] = chat_presence_state_payload($entry, $conversationId, $now);
            if (!empty($payload['peer']['isOnline'])) {
                $payload['onlineCount'] = 1;
            }
            if (!empty($payload['peer']['isTyping'])) {
                $payload['typingUsers'] = chat_presence_typing_user_payloads($presenceStore, $conversation, $viewerStudentNumber, $now);
            }
            break;
        }

        return $payload;
    }

    $onlineCount = 0;
    foreach (chat_conversation_member_student_numbers($conversation) as $studentNumber) {
        $studentNumber = dent_normalize_student_number((string) $studentNumber);
        if ($studentNumber === '' || $studentNumber === $viewerStudentNumber) {
            continue;
        }

        $entry = chat_presence_entry_for_student($presenceStore, $studentNumber);
        if (chat_presence_is_online($entry, $now)) {
            $onlineCount++;
        }
    }

    $payload['onlineCount'] = $onlineCount;
    $payload['typingUsers'] = chat_presence_typing_user_payloads($presenceStore, $conversation, $viewerStudentNumber, $now);
    return $payload;
}

function chat_presence_bundle_payload(
    array $store,
    array $user,
    string $activeConversationId = '',
    bool $includeMembers = false,
    ?array $presenceStore = null
): array
{
    $activeConversationId = chat_clean_conversation_id($activeConversationId);
    $presenceStore = is_array($presenceStore) ? $presenceStore : chat_read_presence_snapshot();
    $conversationIds = [];
    if ($activeConversationId !== '') {
        $conversationIds[] = $activeConversationId;
    }

    foreach (chat_user_visible_conversation_ids($store, $user) as $conversationId) {
        $conversation = chat_get_conversation($store, (string) $conversationId);
        if ($conversation === null) {
            continue;
        }
        if ((string) ($conversation['type'] ?? 'group') === 'direct') {
            $conversationIds[] = (string) $conversationId;
        }
    }

    $updates = [];
    foreach (array_values(array_unique($conversationIds)) as $conversationId) {
        $conversation = chat_get_conversation($store, $conversationId);
        if ($conversation === null) {
            continue;
        }

        $update = [
            'id' => $conversationId,
            'presence' => chat_conversation_presence_payload($conversation, $user, $presenceStore),
        ];

        if ((string) ($conversation['type'] ?? 'group') === 'direct') {
            $viewerStudentNumber = chat_actor_student_number($user);
            foreach (chat_normalize_student_list($conversation['directParticipants'] ?? []) as $studentNumber) {
                if ($studentNumber === $viewerStudentNumber) {
                    continue;
                }
                $peer = chat_public_user_for_student($studentNumber);
                $peer['presence'] = chat_presence_state_payload(
                    chat_presence_entry_for_student($presenceStore, $studentNumber),
                    $conversationId
                );
                $update['peer'] = $peer;
                break;
            }
        }

        if ($includeMembers) {
            $update['members'] = chat_conversation_members_payload($conversation, $conversationId, $presenceStore);
        }

        $updates[] = $update;
    }

    return [
        'version' => chat_presence_file_signature(),
        'activeConversationId' => $activeConversationId,
        'conversations' => $updates,
    ];
}

function chat_can_manage_conversation(array $conversation, array $user): bool
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        return false;
    }

    $type = (string) ($conversation['type'] ?? 'group');
    if ($type === 'class-group') {
        return chat_user_can_moderate_active_cohort($user);
    }

    if ($type === 'group') {
        if (chat_user_can_moderate_active_cohort($user)) {
            return true;
        }

        return chat_is_group_admin($conversation, $studentNumber);
    }

    return false;
}

function chat_user_can_view_without_membership(array $conversation, array $user): bool
{
    if ((string) ($conversation['type'] ?? 'group') === 'direct') {
        return false;
    }

    return chat_user_can_moderate_active_cohort($user);
}

function chat_can_pin_message(array $conversation, array $user): bool
{
    $type = (string) ($conversation['type'] ?? 'group');
    if ($type === 'direct') {
        return chat_is_member($conversation, (string) ($user['studentNumber'] ?? ''));
    }

    return chat_can_manage_conversation($conversation, $user);
}

function chat_can_manage_message(array $conversation, array $user, array $message): bool
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $ownerStudentNumber = dent_normalize_student_number((string) ($message['senderStudentNumber'] ?? ''));

    if ($studentNumber !== '' && $studentNumber === $ownerStudentNumber) {
        return true;
    }

    $type = (string) ($conversation['type'] ?? 'group');
    if ($type === 'direct') {
        return false;
    }

    return chat_can_manage_conversation($conversation, $user);
}

function chat_can_send_message(array $conversation, array $user): bool
{
    if (!chat_is_member($conversation, (string) ($user['studentNumber'] ?? ''))) {
        return false;
    }

    $type = (string) ($conversation['type'] ?? 'group');
    if ($type === 'direct') {
        return true;
    }

    $settings = is_array($conversation['settings'] ?? null) ? $conversation['settings'] : chat_default_settings();
    $conversationKind = (string) ($settings['conversationKind'] ?? 'group');
    if ($conversationKind === 'channel' && !chat_can_manage_conversation($conversation, $user)) {
        return false;
    }

    $muted = (bool) ($settings['muted'] ?? false);
    if (!$muted) {
        return true;
    }

    return chat_can_manage_conversation($conversation, $user);
}

function chat_can_create_poll(array $user): bool
{
    $role = (string) ($user['role'] ?? 'student');
    if ($role === 'owner') {
        return true;
    }

    return chat_user_can_moderate_active_cohort($user);
}

function chat_can_view_student_numbers(array $user): bool
{
    $role = (string) ($user['role'] ?? 'student');
    if ($role === 'owner') {
        return true;
    }

    return chat_user_can_moderate_active_cohort($user);
}

function chat_poll_status(array $poll, ?int $now = null): string
{
    $now = $now ?? time();
    $startAt = chat_parse_timestamp($poll['startAt'] ?? null);
    $endAt = chat_parse_timestamp($poll['endAt'] ?? null);
    $closedAt = chat_parse_timestamp($poll['closedAt'] ?? null);

    if ($closedAt !== null && $closedAt > 0) {
        return 'closed';
    }

    if ($endAt !== null && $endAt <= $now) {
        return 'closed';
    }

    if ($startAt !== null && $startAt > $now) {
        return 'scheduled';
    }

    return 'open';
}

function chat_poll_is_voting_open(array $poll, ?int $now = null): bool
{
    return chat_poll_status($poll, $now) === 'open';
}

function chat_poll_option_ids(array $poll): array
{
    $options = is_array($poll['options'] ?? null) ? $poll['options'] : [];
    $ids = [];
    foreach ($options as $option) {
        if (!is_array($option)) {
            continue;
        }

        $optionId = chat_clean_poll_option_id((string) ($option['id'] ?? ''));
        if ($optionId === '') {
            continue;
        }

        $ids[] = $optionId;
    }

    return $ids;
}

function chat_poll_selected_option_ids_for_user(array $poll, string $studentNumber): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        return [];
    }

    $votes = is_array($poll['votes'] ?? null) ? $poll['votes'] : [];
    $vote = $votes[$studentNumber] ?? null;
    if (!is_array($vote)) {
        return [];
    }

    return chat_normalize_poll_option_ids($vote['optionIds'] ?? [], chat_poll_option_ids($poll));
}

function chat_poll_total_voters(array $poll): int
{
    $votes = is_array($poll['votes'] ?? null) ? $poll['votes'] : [];
    $count = 0;

    foreach ($votes as $vote) {
        if (!is_array($vote)) {
            continue;
        }

        $optionIds = $vote['optionIds'] ?? [];
        if (is_array($optionIds) && $optionIds !== []) {
            $count++;
        }
    }

    return $count;
}

function chat_poll_total_votes(array $poll): int
{
    $votes = is_array($poll['votes'] ?? null) ? $poll['votes'] : [];
    $count = 0;

    foreach ($votes as $vote) {
        if (!is_array($vote)) {
            continue;
        }

        $optionIds = $vote['optionIds'] ?? [];
        if (!is_array($optionIds)) {
            continue;
        }

        $count += count($optionIds);
    }

    return $count;
}

function chat_poll_can_manage(array $store, array $poll, array $user): bool
{
    $viewerStudentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($viewerStudentNumber === '') {
        return false;
    }

    if ($viewerStudentNumber === dent_normalize_student_number((string) ($poll['createdBy'] ?? ''))) {
        return true;
    }

    if (chat_user_can_moderate_active_cohort($user)) {
        return true;
    }

    $conversationId = chat_clean_conversation_id((string) ($poll['conversationId'] ?? ''));
    if ($conversationId === '') {
        return false;
    }

    $conversation = chat_get_conversation($store, $conversationId);
    if ($conversation === null) {
        return false;
    }

    return chat_can_manage_conversation($conversation, $user);
}

function chat_poll_audience_label(string $audience): string
{
    $audience = chat_clean_poll_audience($audience);
    if ($audience === CHAT_POLL_AUDIENCE_ALL_USERS) {
        return 'همه کاربران سایت';
    }
    if ($audience === CHAT_POLL_AUDIENCE_ROTATION_1) {
        return 'فقط روتیشن ۱';
    }
    if ($audience === CHAT_POLL_AUDIENCE_ROTATION_2) {
        return 'فقط روتیشن ۲';
    }
    if ($audience === CHAT_POLL_AUDIENCE_BOTH_ROTATIONS) {
        return 'هر دو روتیشن';
    }
    return 'هر کاربری که لینک را داشته باشد';
}

function chat_user_rotation_id(array $user): ?int
{
    $assignment = dent_user_rotation_assignment($user);
    if (!is_array($assignment)) {
        return null;
    }

    $rotationId = (int) ($assignment['rotationId'] ?? 0);
    if (!in_array($rotationId, [1, 2], true)) {
        return null;
    }

    return $rotationId;
}

function chat_poll_user_matches_audience(array $poll, array $user): bool
{
    $audience = chat_clean_poll_audience((string) ($poll['audience'] ?? CHAT_POLL_AUDIENCE_LINK));
    if ($audience === CHAT_POLL_AUDIENCE_LINK || $audience === CHAT_POLL_AUDIENCE_ALL_USERS) {
        return true;
    }

    $rotationId = chat_user_rotation_id($user);
    if ($audience === CHAT_POLL_AUDIENCE_ROTATION_1) {
        return $rotationId === 1;
    }
    if ($audience === CHAT_POLL_AUDIENCE_ROTATION_2) {
        return $rotationId === 2;
    }

    return in_array($rotationId, [1, 2], true);
}

function chat_poll_can_access(array $store, array $poll, array $user): bool
{
    if (chat_poll_can_manage($store, $poll, $user)) {
        return true;
    }

    return chat_poll_user_matches_audience($poll, $user);
}

function chat_poll_is_discoverable_for_user(array $store, array $poll, array $user): bool
{
    if (chat_poll_can_manage($store, $poll, $user)) {
        return true;
    }

    if (!chat_poll_user_matches_audience($poll, $user)) {
        return false;
    }

    $audience = chat_clean_poll_audience((string) ($poll['audience'] ?? CHAT_POLL_AUDIENCE_LINK));
    return $audience !== CHAT_POLL_AUDIENCE_LINK;
}

function chat_poll_results_visible(array $store, array $poll, array $viewer): bool
{
    $status = chat_poll_status($poll);
    if ($status === 'closed') {
        return true;
    }

    $visibility = chat_clean_poll_result_visibility((string) ($poll['resultVisibility'] ?? CHAT_POLL_VISIBILITY_LIVE));
    if ($visibility === CHAT_POLL_VISIBILITY_LIVE) {
        return true;
    }

    return chat_poll_can_manage($store, $poll, $viewer);
}

function chat_poll_voter_identities_visible(array $store, array $poll, array $viewer): bool
{
    if ((bool) ($poll['anonymous'] ?? true)) {
        return false;
    }

    return chat_poll_results_visible($store, $poll, $viewer);
}

function chat_poll_option_voters(array $poll): array
{
    $optionVoters = [];
    foreach (chat_poll_option_ids($poll) as $optionId) {
        $optionVoters[$optionId] = [];
    }

    $votes = is_array($poll['votes'] ?? null) ? $poll['votes'] : [];
    foreach ($votes as $studentNumber => $vote) {
        $studentNumber = dent_normalize_student_number((string) $studentNumber);
        if ($studentNumber === '' || !is_array($vote)) {
            continue;
        }

        $optionIds = $vote['optionIds'] ?? [];
        if (!is_array($optionIds)) {
            continue;
        }

        foreach ($optionIds as $optionId) {
            $optionId = chat_clean_poll_option_id((string) $optionId);
            if ($optionId === '' || !isset($optionVoters[$optionId])) {
                continue;
            }
            $optionVoters[$optionId]['sn:' . $studentNumber] = $studentNumber;
        }
    }

    foreach ($optionVoters as $optionId => $voters) {
        $list = array_values($voters);
        sort($list, SORT_STRING);
        $optionVoters[$optionId] = $list;
    }

    return $optionVoters;
}

function chat_poll_share_url(string $pollId): string
{
    return chat_page_url();
}

function chat_poll_payload(array $store, array $poll, array $viewer, bool $withVoterLists = true): array
{
    $now = time();
    $pollId = chat_clean_poll_id((string) ($poll['id'] ?? ''));
    $viewerStudentNumber = dent_normalize_student_number((string) ($viewer['studentNumber'] ?? ''));
    $createdByStudentNumber = dent_normalize_student_number((string) ($poll['createdBy'] ?? ''));
    $status = chat_poll_status($poll, $now);
    $resultsVisible = chat_poll_results_visible($store, $poll, $viewer);
    $identitiesVisible = chat_poll_voter_identities_visible($store, $poll, $viewer);
    $selectedOptionIds = chat_poll_selected_option_ids_for_user($poll, $viewerStudentNumber);
    $hasVoted = $selectedOptionIds !== [];
    $viewerCanViewStudentNumbers = chat_can_view_student_numbers($viewer);

    $allowVoteChange = (bool) ($poll['allowVoteChange'] ?? true);
    $allowCreatorVote = (bool) ($poll['allowCreatorVote'] ?? true);
    $isCreator = $viewerStudentNumber !== '' && $viewerStudentNumber === $createdByStudentNumber;
    $canManage = chat_poll_can_manage($store, $poll, $viewer);
    $audience = chat_clean_poll_audience((string) ($poll['audience'] ?? CHAT_POLL_AUDIENCE_LINK));
    $viewerInAudience = chat_poll_user_matches_audience($poll, $viewer);
    $viewerCanAccess = chat_poll_can_access($store, $poll, $viewer);

    $canVote = chat_poll_is_voting_open($poll, $now);
    if ($canVote && !$viewerCanAccess) {
        $canVote = false;
    }
    if ($canVote && $isCreator && !$allowCreatorVote) {
        $canVote = false;
    }
    if ($canVote && $hasVoted && !$allowVoteChange) {
        $canVote = false;
    }

    $optionVoters = chat_poll_option_voters($poll);
    $totalVoters = chat_poll_total_voters($poll);
    $totalVotes = chat_poll_total_votes($poll);

    $optionsPayload = [];
    foreach ((array) ($poll['options'] ?? []) as $option) {
        if (!is_array($option)) {
            continue;
        }

        $optionId = chat_clean_poll_option_id((string) ($option['id'] ?? ''));
        if ($optionId === '') {
            continue;
        }

        $voters = isset($optionVoters[$optionId]) && is_array($optionVoters[$optionId])
            ? $optionVoters[$optionId]
            : [];
        $voteCount = count($voters);
        $percent = $totalVoters > 0 ? round(($voteCount * 1000) / $totalVoters) / 10 : 0.0;

        $optionPayload = [
            'id' => $optionId,
            'text' => (string) ($option['text'] ?? ''),
            'isSelected' => in_array($optionId, $selectedOptionIds, true),
            'voteCount' => $resultsVisible ? $voteCount : null,
            'percent' => $resultsVisible ? $percent : null,
            'voters' => [],
        ];

        if ($resultsVisible && $identitiesVisible && $withVoterLists) {
            $votersPayload = [];
            foreach ($voters as $studentNumber) {
                $voter = chat_public_user_for_student((string) $studentNumber);
                if (!$viewerCanViewStudentNumbers) {
                    $voter['studentNumber'] = '';
                    $voter['username'] = '';
                }
                $votersPayload[] = $voter;
            }

            usort($votersPayload, static function (array $left, array $right): int {
                return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
            });

            $optionPayload['voters'] = $votersPayload;
        }

        $optionsPayload[] = $optionPayload;
    }

    $hiddenReason = '';
    if (!$resultsVisible && $status !== 'closed') {
        $visibility = chat_clean_poll_result_visibility((string) ($poll['resultVisibility'] ?? CHAT_POLL_VISIBILITY_LIVE));
        if ($visibility === CHAT_POLL_VISIBILITY_AFTER_CLOSE) {
            $hiddenReason = 'نتایج تا پایان نظرسنجی مخفی است.';
        } else {
            $hiddenReason = 'نمایش نتایج فقط برای سازنده/مدیر فعال است.';
        }
    }

    $conversation = null;
    $conversationId = chat_clean_conversation_id((string) ($poll['conversationId'] ?? ''));
    if ($conversationId !== '') {
        $conversationEntity = chat_get_conversation($store, $conversationId);
        if ($conversationEntity !== null) {
            $conversation = [
                'id' => $conversationId,
                'type' => (string) ($conversationEntity['type'] ?? 'group'),
                'title' => chat_conversation_title_for_user($conversationEntity, $viewerStudentNumber),
            ];
        }
    }

    return [
        'id' => $pollId,
        'shareUrl' => chat_poll_share_url($pollId),
        'question' => (string) ($poll['question'] ?? ''),
        'options' => $optionsPayload,
        'createdBy' => chat_public_user_for_student((string) $createdByStudentNumber),
        'createdAt' => (int) ($poll['createdAt'] ?? $now),
        'updatedAt' => (int) ($poll['updatedAt'] ?? $now),
        'startAt' => chat_parse_timestamp($poll['startAt'] ?? null),
        'endAt' => chat_parse_timestamp($poll['endAt'] ?? null),
        'closedAt' => chat_parse_timestamp($poll['closedAt'] ?? null),
        'closedBy' => $poll['closedBy'] ? chat_public_user_for_student((string) $poll['closedBy']) : null,
        'status' => $status,
        'audience' => [
            'mode' => $audience,
            'label' => chat_poll_audience_label($audience),
            'discoverable' => $audience !== CHAT_POLL_AUDIENCE_LINK,
        ],
        'conversation' => $conversation,
        'messageId' => isset($poll['messageId']) ? (int) $poll['messageId'] : null,
        'settings' => [
            'anonymous' => (bool) ($poll['anonymous'] ?? true),
            'multipleChoice' => (bool) ($poll['multipleChoice'] ?? false),
            'maxChoices' => (int) ($poll['maxChoices'] ?? 1),
            'allowVoteChange' => $allowVoteChange,
            'allowCreatorVote' => $allowCreatorVote,
            'resultVisibility' => chat_clean_poll_result_visibility((string) ($poll['resultVisibility'] ?? CHAT_POLL_VISIBILITY_LIVE)),
            'audience' => $audience,
        ],
        'viewer' => [
            'hasVoted' => $hasVoted,
            'selectedOptionIds' => $selectedOptionIds,
            'canVote' => $canVote,
            'canChangeVote' => $hasVoted && $allowVoteChange && chat_poll_is_voting_open($poll, $now) && $viewerCanAccess,
            'isCreator' => $isCreator,
            'isInAudience' => $viewerInAudience,
            'canAccess' => $viewerCanAccess,
        ],
        'results' => [
            'visible' => $resultsVisible,
            'identitiesVisible' => $identitiesVisible,
            'hiddenReason' => $hiddenReason,
            'totalVoters' => $resultsVisible ? $totalVoters : null,
            'totalVotes' => $resultsVisible ? $totalVotes : null,
        ],
        'permissions' => [
            'canManage' => $canManage,
            'canClose' => $canManage && $status !== 'closed',
            'canReopen' => $canManage && $status === 'closed' && (
                chat_parse_timestamp($poll['endAt'] ?? null) === null
                || (chat_parse_timestamp($poll['endAt'] ?? null) > $now)
            ),
        ],
    ];
}

function chat_touch_conversation(array &$store, string $conversationId, ?int $ts = null): void
{
    $conversation = chat_get_conversation($store, $conversationId);
    if ($conversation === null) {
        return;
    }

    $conversation['updatedAt'] = $ts ?? time();
    chat_put_conversation($store, $conversation);
}

function chat_read_state(array $store, string $conversationId, string $studentNumber): array
{
    $conversationId = chat_clean_conversation_id($conversationId);
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($conversationId === '' || $studentNumber === '') {
        return chat_default_read_state();
    }

    $conversationReads = $store['reads'][$conversationId] ?? [];
    if (!is_array($conversationReads)) {
        return chat_default_read_state();
    }

    $readInfo = $conversationReads[$studentNumber] ?? null;
    return is_array($readInfo) ? chat_default_read_state($readInfo) : chat_default_read_state();
}

function chat_put_read_state(array &$store, string $conversationId, string $studentNumber, array $state): void
{
    $conversationId = chat_clean_conversation_id($conversationId);
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($conversationId === '' || $studentNumber === '') {
        return;
    }

    if (!isset($store['reads']) || !is_array($store['reads'])) {
        $store['reads'] = [];
    }

    if (!isset($store['reads'][$conversationId]) || !is_array($store['reads'][$conversationId])) {
        $store['reads'][$conversationId] = [];
    }

    $store['reads'][$conversationId][$studentNumber] = chat_default_read_state($state);
}

function chat_last_read_message_id(array $store, string $conversationId, string $studentNumber): int
{
    $readState = chat_read_state($store, $conversationId, $studentNumber);
    return max(0, (int) ($readState['lastReadMessageId'] ?? 0));
}

function chat_is_conversation_archived_for_user(array $store, string $conversationId, string $studentNumber): bool
{
    $readState = chat_read_state($store, $conversationId, $studentNumber);
    return (bool) ($readState['archived'] ?? false);
}

function chat_is_conversation_pinned_for_user(array $store, string $conversationId, string $studentNumber): bool
{
    $readState = chat_read_state($store, $conversationId, $studentNumber);
    return (bool) ($readState['pinned'] ?? false);
}

function chat_is_conversation_deleted_for_user(array $store, string $conversationId, string $studentNumber): bool
{
    $readState = chat_read_state($store, $conversationId, $studentNumber);
    return (bool) ($readState['deleted'] ?? false);
}

function chat_set_conversation_archived_state(
    array &$store,
    string $conversationId,
    string $studentNumber,
    bool $archived
): bool {
    $conversationId = chat_clean_conversation_id($conversationId);
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($conversationId === '' || $studentNumber === '') {
        return false;
    }

    $readState = chat_read_state($store, $conversationId, $studentNumber);
    $current = (bool) ($readState['archived'] ?? false);
    if ($current === $archived) {
        return false;
    }

    $readState['archived'] = $archived;
    $readState['archivedAt'] = $archived ? time() : null;
    if ($archived) {
        $readState['deleted'] = false;
        $readState['deletedAt'] = null;
    }

    chat_put_read_state($store, $conversationId, $studentNumber, $readState);
    return true;
}

function chat_set_conversation_pinned_state(
    array &$store,
    string $conversationId,
    string $studentNumber,
    bool $pinned
): bool {
    $conversationId = chat_clean_conversation_id($conversationId);
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($conversationId === '' || $studentNumber === '') {
        return false;
    }

    $readState = chat_read_state($store, $conversationId, $studentNumber);
    $current = (bool) ($readState['pinned'] ?? false);
    if ($current === $pinned) {
        return false;
    }

    $readState['pinned'] = $pinned;
    $readState['pinnedAt'] = $pinned ? time() : null;
    chat_put_read_state($store, $conversationId, $studentNumber, $readState);
    return true;
}

function chat_set_conversation_deleted_state(
    array &$store,
    string $conversationId,
    string $studentNumber,
    bool $deleted
): bool {
    $conversationId = chat_clean_conversation_id($conversationId);
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($conversationId === '' || $studentNumber === '') {
        return false;
    }

    $readState = chat_read_state($store, $conversationId, $studentNumber);
    $current = (bool) ($readState['deleted'] ?? false);
    if ($current === $deleted) {
        return false;
    }

    $readState['deleted'] = $deleted;
    $readState['deletedAt'] = $deleted ? time() : null;
    if ($deleted) {
        $readState['pinned'] = false;
        $readState['pinnedAt'] = null;
        $readState['archived'] = false;
        $readState['archivedAt'] = null;
    }

    chat_put_read_state($store, $conversationId, $studentNumber, $readState);
    return true;
}

function chat_restore_conversation_visibility_for_user(
    array &$store,
    string $conversationId,
    string $studentNumber
): bool {
    $conversationId = chat_clean_conversation_id($conversationId);
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($conversationId === '' || $studentNumber === '') {
        return false;
    }

    $readState = chat_read_state($store, $conversationId, $studentNumber);
    $changed = false;
    if ((bool) ($readState['deleted'] ?? false)) {
        $readState['deleted'] = false;
        $readState['deletedAt'] = null;
        $readState['archived'] = false;
        $readState['archivedAt'] = null;
        $changed = true;
    }

    if ($changed) {
        chat_put_read_state($store, $conversationId, $studentNumber, $readState);
    }

    return $changed;
}

function chat_set_notification_mute_state(
    array &$store,
    string $conversationId,
    string $studentNumber,
    bool $muted
): bool {
    $conversationId = chat_clean_conversation_id($conversationId);
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($conversationId === '' || $studentNumber === '') {
        return false;
    }

    $readState = chat_read_state($store, $conversationId, $studentNumber);
    $current = (bool) ($readState['notificationsMuted'] ?? false);
    if ($current === $muted) {
        return false;
    }

    $readState['notificationsMuted'] = $muted;
    $readState['notificationsMutedAt'] = $muted ? time() : null;
    chat_put_read_state($store, $conversationId, $studentNumber, $readState);
    return true;
}

function chat_restore_visibility_for_conversation_members(array &$store, array $conversation): bool
{
    $conversationId = chat_clean_conversation_id((string) ($conversation['id'] ?? ''));
    if ($conversationId === '') {
        return false;
    }
    if ((bool) ($conversation['mandatory'] ?? false)) {
        return false;
    }

    $changed = false;
    foreach (chat_conversation_member_student_numbers($conversation) as $studentNumber) {
        if (chat_restore_conversation_visibility_for_user($store, $conversationId, (string) $studentNumber)) {
            $changed = true;
        }
    }

    return $changed;
}

function chat_mark_read(array &$store, string $conversationId, string $studentNumber, ?int $targetMessageId = null): bool
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        return false;
    }

    $messages = chat_get_messages($store, $conversationId);
    if ($messages === []) {
        return false;
    }

    $readState = chat_read_state($store, $conversationId, $studentNumber);
    $currentRead = max(0, (int) ($readState['lastReadMessageId'] ?? 0));
    $latestMessage = chat_last_message($messages);
    $latestMessageId = (int) ($latestMessage['id'] ?? 0);
    $nextRead = $targetMessageId !== null ? max(0, $targetMessageId) : $latestMessageId;

    if ($nextRead <= $currentRead) {
        return false;
    }

    $readState['lastReadMessageId'] = $nextRead;
    $readState['lastReadAt'] = time();
    chat_put_read_state($store, $conversationId, $studentNumber, $readState);

    return true;
}

function chat_mark_unread(array &$store, string $conversationId, string $studentNumber): bool
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        return false;
    }

    $conversation = chat_get_conversation($store, $conversationId);
    if ($conversation === null) {
        return false;
    }

    if (chat_unread_count($store, $conversation, $studentNumber) > 0) {
        return false;
    }

    $messages = chat_get_messages($store, $conversationId);
    if ($messages === []) {
        return false;
    }

    $currentRead = chat_last_read_message_id($store, $conversationId, $studentNumber);
    $targetRead = null;

    for ($index = count($messages) - 1; $index >= 0; $index--) {
        $message = $messages[$index];
        if (!is_array($message)) {
            continue;
        }

        $messageId = (int) ($message['id'] ?? 0);
        if ($messageId <= 0) {
            continue;
        }

        $senderStudentNumber = dent_normalize_student_number((string) ($message['senderStudentNumber'] ?? ''));
        if ($senderStudentNumber === $studentNumber) {
            continue;
        }

        if ($messageId <= $currentRead) {
            return false;
        }

        $targetRead = max(0, $messageId - 1);
        break;
    }

    if ($targetRead === null || $targetRead === $currentRead) {
        return false;
    }

    $readState = chat_read_state($store, $conversationId, $studentNumber);
    $readState['lastReadMessageId'] = $targetRead;
    $readState['lastReadAt'] = time();
    chat_put_read_state($store, $conversationId, $studentNumber, $readState);
    return true;
}

function chat_unread_count(array $store, array $conversation, string $studentNumber): int
{
    $conversationId = (string) ($conversation['id'] ?? '');
    $messages = chat_get_messages($store, $conversationId);
    if ($messages === []) {
        return 0;
    }

    $lastReadMessageId = chat_last_read_message_id($store, $conversationId, $studentNumber);
    $count = 0;

    foreach ($messages as $message) {
        $messageId = (int) ($message['id'] ?? 0);
        if ($messageId <= $lastReadMessageId) {
            continue;
        }

        if (dent_normalize_student_number((string) ($message['senderStudentNumber'] ?? '')) === $studentNumber) {
            continue;
        }

        $count++;
    }

    return $count;
}

function chat_message_delivery_meta(array $store, array $conversation, array $message, string $viewerStudentNumber): array
{
    $conversationId = chat_clean_conversation_id((string) ($conversation['id'] ?? ''));
    $viewerStudentNumber = dent_normalize_student_number($viewerStudentNumber);
    $senderStudentNumber = dent_normalize_student_number((string) ($message['senderStudentNumber'] ?? ''));
    $messageId = (int) ($message['id'] ?? 0);

    if (
        $conversationId === ''
        || $viewerStudentNumber === ''
        || $senderStudentNumber === ''
        || $senderStudentNumber !== $viewerStudentNumber
        || $messageId <= 0
    ) {
        return [
            'delivery' => 'sent',
            'seenByCount' => 0,
        ];
    }

    $seenByCount = 0;
    foreach (chat_conversation_member_student_numbers($conversation) as $studentNumber) {
        $studentNumber = dent_normalize_student_number((string) $studentNumber);
        if ($studentNumber === '' || $studentNumber === $viewerStudentNumber) {
            continue;
        }

        if (chat_last_read_message_id($store, $conversationId, $studentNumber) >= $messageId) {
            $seenByCount++;
        }
    }

    return [
        'delivery' => $seenByCount > 0 ? 'seen' : 'sent',
        'seenByCount' => $seenByCount,
    ];
}

function chat_message_receipts_payload(array $store, array $conversation, array $message, array $viewer): array
{
    $conversationId = chat_clean_conversation_id((string) ($conversation['id'] ?? ''));
    $messageId = (int) ($message['id'] ?? 0);
    $senderStudentNumber = dent_normalize_student_number((string) ($message['senderStudentNumber'] ?? ''));
    $viewerStudentNumber = chat_actor_student_number($viewer);
    $canInspect = $viewerStudentNumber !== ''
        && $senderStudentNumber !== ''
        && ($viewerStudentNumber === $senderStudentNumber || chat_can_manage_conversation($conversation, $viewer));

    if ($conversationId === '' || $messageId <= 0 || !$canInspect) {
        dent_error('اجازه مشاهده وضعیت سین این پیام را ندارید.', 403);
    }

    $seen = [];
    $pending = [];
    foreach (chat_conversation_member_student_numbers($conversation) as $studentNumber) {
        $studentNumber = dent_normalize_student_number((string) $studentNumber);
        if ($studentNumber === '' || $studentNumber === $senderStudentNumber) {
            continue;
        }

        $readState = chat_read_state($store, $conversationId, $studentNumber);
        $lastReadMessageId = max(0, (int) ($readState['lastReadMessageId'] ?? 0));
        $entry = [
            'user' => chat_public_user_for_student($studentNumber),
            'seen' => $lastReadMessageId >= $messageId,
            'seenAt' => $lastReadMessageId >= $messageId ? ($readState['lastReadAt'] ?? null) : null,
        ];

        if ($entry['seen']) {
            $seen[] = $entry;
        } else {
            $pending[] = $entry;
        }
    }

    usort($seen, static function (array $left, array $right): int {
        return (int) ($right['seenAt'] ?? 0) <=> (int) ($left['seenAt'] ?? 0);
    });
    usort($pending, static function (array $left, array $right): int {
        return strcasecmp((string) ($left['user']['name'] ?? ''), (string) ($right['user']['name'] ?? ''));
    });

    return [
        'messageId' => $messageId,
        'conversationId' => $conversationId,
        'conversationType' => (string) ($conversation['type'] ?? 'group'),
        'seenCount' => count($seen),
        'pendingCount' => count($pending),
        'seen' => $seen,
        'pending' => $pending,
    ];
}

function chat_attachment_absolute_path(string $relativePath): string
{
    $clean = chat_clean_media_relative_path($relativePath);
    if ($clean === '') {
        return '';
    }

    return chat_media_root_path() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean);
}

function chat_attachment_payload(array $attachment): array
{
    $attachmentId = (string) ($attachment['id'] ?? '');
    $category = (string) ($attachment['category'] ?? 'file');
    $status = (string) ($attachment['status'] ?? 'available');
    $originalExists = (bool) ($attachment['originalExists'] ?? false);
    $previewExists = (bool) ($attachment['previewExists'] ?? false);
    $isAvailable = $status === 'available' && $originalExists;

    return [
        'id' => $attachmentId,
        'conversationId' => (string) ($attachment['conversationId'] ?? ''),
        'messageId' => isset($attachment['messageId']) ? (int) $attachment['messageId'] : null,
        'name' => (string) ($attachment['originalName'] ?? ''),
        'safeFileName' => (string) ($attachment['safeFileName'] ?? ''),
        'mime' => (string) ($attachment['mime'] ?? ''),
        'extension' => (string) ($attachment['extension'] ?? ''),
        'category' => $category,
        'isVoice' => (bool) ($attachment['isVoice'] ?? false),
        'sizeBytes' => max(0, (int) ($attachment['sizeBytes'] ?? 0)),
        'durationSeconds' => isset($attachment['durationSeconds']) ? max(0, (float) $attachment['durationSeconds']) : null,
        'width' => isset($attachment['width']) ? max(1, (int) $attachment['width']) : null,
        'height' => isset($attachment['height']) ? max(1, (int) $attachment['height']) : null,
        'createdAt' => (int) ($attachment['createdAt'] ?? time()),
        'linkedAt' => isset($attachment['linkedAt']) ? (int) ($attachment['linkedAt']) : null,
        'status' => $status,
        'available' => $isAvailable,
        'expired' => $status === 'expired' || !$isAvailable,
        'purgedAt' => isset($attachment['purgedAt']) ? (int) ($attachment['purgedAt']) : null,
        'purgeReason' => (string) ($attachment['purgeReason'] ?? ''),
        'hasPreview' => $previewExists,
        'url' => $isAvailable ? chat_public_media_url($attachmentId, CHAT_MEDIA_VARIANT_ORIGINAL, false) : '',
        'downloadUrl' => $isAvailable ? chat_public_media_url($attachmentId, CHAT_MEDIA_VARIANT_ORIGINAL, true) : '',
        'previewUrl' => $previewExists
            ? chat_public_media_url($attachmentId, CHAT_MEDIA_VARIANT_PREVIEW, false)
            : ($isAvailable && $category === 'image'
                ? chat_public_media_url($attachmentId, CHAT_MEDIA_VARIANT_ORIGINAL, false)
                : ''),
    ];
}

function chat_message_attachments_payload(array $message, array $store): array
{
    $payload = [];
    $attachmentIds = chat_parse_attachment_ids_input($message['attachmentIds'] ?? []);
    foreach ($attachmentIds as $attachmentId) {
        $attachment = chat_get_attachment($store, $attachmentId);
        if ($attachment === null) {
            continue;
        }

        $payload[] = chat_attachment_payload($attachment);
    }

    return $payload;
}

function chat_attachment_access_allowed(array $store, array $attachment, array $user): bool
{
    $viewerStudentNumber = chat_actor_student_number($user);
    if ($viewerStudentNumber === '') {
        return false;
    }

    $uploaderStudentNumber = dent_normalize_student_number((string) ($attachment['uploaderStudentNumber'] ?? ''));
    $conversationId = chat_clean_conversation_id((string) ($attachment['conversationId'] ?? ''));

    if ($conversationId === '') {
        return $viewerStudentNumber === $uploaderStudentNumber;
    }

    $conversation = chat_get_conversation($store, $conversationId);
    if ($conversation === null) {
        return false;
    }

    return chat_is_member($conversation, $viewerStudentNumber)
        || chat_user_can_view_without_membership($conversation, $user);
}

function chat_safe_download_filename(string $rawName, string $fallbackId): string
{
    $name = dent_clean_text($rawName, 180);
    if ($name === '') {
        $name = $fallbackId;
    }

    $name = str_replace(["\r", "\n", "\t"], ' ', $name);
    return preg_replace('/[^\p{L}\p{N}\s._-]+/u', '_', $name) ?: $fallbackId;
}

function chat_stream_attachment_file(array $attachment, string $variant, bool $download): void
{
    $variant = chat_clean_media_variant($variant);
    $attachmentId = (string) ($attachment['id'] ?? '');
    $category = (string) ($attachment['category'] ?? 'file');

    $relativePath = $variant === CHAT_MEDIA_VARIANT_PREVIEW
        ? (string) ($attachment['previewPath'] ?? '')
        : (string) ($attachment['originalPath'] ?? '');
    $absolutePath = chat_attachment_absolute_path($relativePath);
    if ($absolutePath === '' || !is_file($absolutePath)) {
        if ($variant === CHAT_MEDIA_VARIANT_ORIGINAL) {
            dent_error('فایل اصلی دیگر در دسترس نیست.', 410, [
                'expired' => true,
                'attachmentId' => $attachmentId,
            ]);
        }

        dent_error('پیش‌نمایش فایل در دسترس نیست.', 404, [
            'attachmentId' => $attachmentId,
        ]);
    }

    $mime = $variant === CHAT_MEDIA_VARIANT_PREVIEW
        ? 'image/jpeg'
        : strtolower(trim((string) ($attachment['mime'] ?? '')));
    if ($mime === '') {
        $mime = 'application/octet-stream';
    }

    if ($variant === CHAT_MEDIA_VARIANT_ORIGINAL && $category === 'voice' && str_starts_with($mime, 'audio/')) {
        $download = false;
    }

    $size = @filesize($absolutePath);
    if ($size === false) {
        $size = 0;
    }

    http_response_code(200);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) max(0, (int) $size));
    header('Cache-Control: private, max-age=120');

    if ($download) {
        $fileName = chat_safe_download_filename((string) ($attachment['originalName'] ?? ''), $attachmentId);
        header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
    } else {
        header('Content-Disposition: inline');
    }

    readfile($absolutePath);
    exit;
}

function chat_normalize_message_for_client(array $message, ?array $store = null, ?array $viewer = null): array
{
    $sender = chat_public_user_for_student((string) ($message['senderStudentNumber'] ?? ''));
    $profile = is_array($sender['profile'] ?? null) ? $sender['profile'] : dent_default_profile();
    $kind = (string) ($message['kind'] ?? 'text');
    if (!in_array($kind, ['text', 'poll', 'attachment', 'voice'], true)) {
        $kind = 'text';
    }

    $pollId = $kind === 'poll' ? chat_clean_poll_id((string) ($message['pollId'] ?? '')) : '';
    if ($kind === 'poll' && $pollId === '') {
        $kind = 'text';
    }

    $delivery = [
        'delivery' => 'sent',
        'seenByCount' => 0,
    ];
    if (is_array($store) && is_array($viewer)) {
        $conversationId = chat_clean_conversation_id((string) ($message['conversationId'] ?? ''));
        $conversation = $conversationId !== '' ? chat_get_conversation($store, $conversationId) : null;
        if ($conversation !== null) {
            $delivery = chat_message_delivery_meta($store, $conversation, $message, chat_actor_student_number($viewer));
        }
    }

    $payload = [
        'id' => (int) ($message['id'] ?? 0),
        'conversationId' => (string) ($message['conversationId'] ?? ''),
        'username' => (string) ($sender['studentNumber'] ?? ''),
        'studentNumber' => (string) ($sender['studentNumber'] ?? ''),
        'senderStudentNumber' => (string) ($sender['studentNumber'] ?? ''),
        'name' => (string) ($sender['name'] ?? ''),
        'text' => (string) ($message['text'] ?? ''),
        'ts' => (int) ($message['ts'] ?? time()),
        'editedAt' => isset($message['editedAt']) ? (int) $message['editedAt'] : null,
        'replyTo' => isset($message['replyTo']) ? (int) $message['replyTo'] : null,
        'pinned' => (bool) ($message['pinned'] ?? false),
        'reactions' => chat_sanitize_reactions($message['reactions'] ?? []),
        'role' => (string) ($sender['role'] ?? 'student'),
        'roleLabel' => (string) ($sender['roleLabel'] ?? dent_role_label('student')),
        'canModerateChat' => (bool) ($sender['canModerateChat'] ?? false),
        'profile' => $profile,
        'avatarUrl' => (string) (($profile['avatarUrl'] ?? '') ?: ''),
        'about' => (string) (($profile['about'] ?? ($profile['bio'] ?? '')) ?: ''),
        'delivery' => (string) ($delivery['delivery'] ?? 'sent'),
        'seenByCount' => max(0, (int) ($delivery['seenByCount'] ?? 0)),
        'kind' => $kind,
        'pollId' => $kind === 'poll' ? $pollId : '',
        'attachments' => is_array($store) ? chat_message_attachments_payload($message, $store) : [],
    ];

    if ($payload['attachments'] === [] && in_array($payload['kind'], ['attachment', 'voice'], true)) {
        $payload['kind'] = 'text';
    } elseif ($payload['attachments'] !== [] && $payload['kind'] === 'text') {
        $first = $payload['attachments'][0];
        $payload['kind'] = ((string) ($first['category'] ?? '') === 'voice') ? 'voice' : 'attachment';
    }

    if ($kind === 'poll' && $pollId !== '' && is_array($store)) {
        $poll = chat_get_poll($store, $pollId);
        if ($poll !== null) {
            $payload['poll'] = chat_poll_payload($store, $poll, is_array($viewer) ? $viewer : [], false);
        }
    }

    return $payload;
}

function chat_normalize_messages_for_client(array $messages, ?array $store = null, ?array $viewer = null): array
{
    $normalized = [];
    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }

        $normalized[] = chat_normalize_message_for_client($message, $store, $viewer);
    }

    return $normalized;
}

function chat_conversation_search_text(array $messages): string
{
    $parts = [];
    $limit = 80;
    $count = 0;

    for ($index = count($messages) - 1; $index >= 0; $index--) {
        if ($count >= $limit) {
            break;
        }

        $message = is_array($messages[$index] ?? null) ? $messages[$index] : [];
        $text = dent_clean_text((string) ($message['text'] ?? ''), 220);
        if ($text === '' && is_array($message['attachments'] ?? null)) {
            foreach ($message['attachments'] as $attachment) {
                if (!is_array($attachment)) {
                    continue;
                }
                $text = dent_clean_text((string) ($attachment['name'] ?? ''), 120);
                if ($text !== '') {
                    break;
                }
            }
        }
        if ($text === '') {
            continue;
        }

        $parts[] = $text;
        $count++;
    }

    return dent_clean_text(implode(' ', array_reverse($parts)), 8000);
}

function chat_conversation_members_payload(
    array $conversation,
    string $conversationId = '',
    ?array $presenceStore = null
): array
{
    $members = [];
    $admins = chat_normalize_student_list($conversation['admins'] ?? []);
    $memberTags = is_array($conversation['memberTags'] ?? null) ? $conversation['memberTags'] : [];
    $createdBy = dent_normalize_student_number((string) ($conversation['createdBy'] ?? ''));
    $conversationId = chat_clean_conversation_id($conversationId !== '' ? $conversationId : (string) ($conversation['id'] ?? ''));
    if ($conversationId !== '') {
        $presenceStore = is_array($presenceStore) ? $presenceStore : chat_read_presence_snapshot();
    }
    foreach (chat_conversation_member_student_numbers($conversation) as $studentNumber) {
        $studentNumber = dent_normalize_student_number((string) $studentNumber);
        if ($studentNumber === '') {
            continue;
        }

        $member = chat_public_user_for_student($studentNumber);
        $member['isConversationAdmin'] = in_array($studentNumber, $admins, true);
        $member['isConversationCreator'] = $createdBy !== '' && $studentNumber === $createdBy;
        $member['conversationRole'] = $member['isConversationCreator']
            ? 'owner'
            : ($member['isConversationAdmin'] ? 'admin' : 'member');
        $tag = chat_clean_member_tag((string) ($memberTags[$studentNumber] ?? ''));
        if ($tag === '' && $member['isConversationCreator']) {
            $tag = 'مالک';
        } elseif ($tag === '' && $member['isConversationAdmin']) {
            $tag = 'مدیر';
        }
        $member['conversationTag'] = $tag;
        if ($conversationId !== '' && is_array($presenceStore)) {
            $member['presence'] = chat_presence_state_payload(
                chat_presence_entry_for_student($presenceStore, $studentNumber),
                $conversationId
            );
        }
        $members[] = $member;
    }

    usort($members, static function (array $left, array $right): int {
        $leftCanModerate = (bool) ($left['canModerateChat'] ?? false);
        $rightCanModerate = (bool) ($right['canModerateChat'] ?? false);
        if ($leftCanModerate !== $rightCanModerate) {
            return $leftCanModerate ? -1 : 1;
        }

        return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
    });

    return $members;
}

function chat_conversation_title_for_user(array $conversation, string $viewerStudentNumber): string
{
    $type = (string) ($conversation['type'] ?? 'group');
    if ($type === 'class-group') {
        return 'گفت‌وگوی ' . chat_active_cohort_title();
    }

    if ($type === 'direct') {
        $participants = chat_normalize_student_list($conversation['directParticipants'] ?? []);
        foreach ($participants as $studentNumber) {
            if ($studentNumber !== $viewerStudentNumber) {
                $peer = chat_public_user_for_student((string) $studentNumber);
                return (string) ($peer['name'] ?: dent_role_label('student'));
            }
        }

        return 'گفت‌وگوی خصوصی';
    }

    $title = dent_clean_text((string) ($conversation['title'] ?? ''), 80);
    return $title !== '' ? $title : 'گروه';
}

function chat_conversation_about_for_user(array $conversation, string $viewerStudentNumber): string
{
    $type = (string) ($conversation['type'] ?? 'group');
    if ($type === 'direct') {
        $participants = chat_normalize_student_list($conversation['directParticipants'] ?? []);
        foreach ($participants as $studentNumber) {
            if ($studentNumber !== $viewerStudentNumber) {
                $peer = chat_public_user_for_student((string) $studentNumber);
                return (string) ($peer['about'] ?? '');
            }
        }
    }

    return dent_clean_text((string) ($conversation['about'] ?? ''), 280);
}

function chat_conversation_avatar_for_user(array $conversation, string $viewerStudentNumber): string
{
    $type = (string) ($conversation['type'] ?? 'group');
    if ($type === 'class-group') {
        return chat_brand_logo_url();
    }

    if ($type === 'direct') {
        $participants = chat_normalize_student_list($conversation['directParticipants'] ?? []);
        foreach ($participants as $studentNumber) {
            if ($studentNumber !== $viewerStudentNumber) {
                $peer = chat_public_user_for_student((string) $studentNumber);
                return dent_clean_avatar_url((string) ($peer['avatarUrl'] ?? ''), false);
            }
        }
    }

    return dent_clean_avatar_url((string) ($conversation['avatarUrl'] ?? ''), false);
}

function chat_conversation_subtitle_for_user(
    array $conversation,
    string $viewerStudentNumber,
    bool $viewerCanViewStudentNumbers = false
): string
{
    $type = (string) ($conversation['type'] ?? 'group');
    if ($type === 'class-group') {
        return 'گفت‌وگوی اجباری ' . chat_active_cohort_title();
    }

    if ($type === 'direct') {
        $participants = chat_normalize_student_list($conversation['directParticipants'] ?? []);
        foreach ($participants as $studentNumber) {
            if ($studentNumber !== $viewerStudentNumber) {
                $peer = chat_public_user_for_student((string) $studentNumber);
                $roleLabel = (string) ($peer['roleLabel'] ?? 'دانشجو');
                if ($viewerCanViewStudentNumbers && $studentNumber !== '') {
                    return $roleLabel . ' • ' . $studentNumber;
                }
                return $roleLabel;
            }
        }
    }

    $members = chat_conversation_member_student_numbers($conversation);
    return count($members) . ' عضو';
}

function chat_conversation_payload(array $store, array $conversation, array $viewer, bool $includeMembers = false): array
{
    $conversationId = (string) ($conversation['id'] ?? '');
    $conversationType = (string) ($conversation['type'] ?? 'group');
    $isMandatory = (bool) ($conversation['mandatory'] ?? false);
    $viewerStudentNumber = chat_actor_student_number($viewer);
    $viewerCanViewStudentNumbers = chat_can_view_student_numbers($viewer);
    $messages = chat_get_messages($store, $conversationId);
    $lastMessage = chat_last_message($messages);
    $pinnedMessage = chat_latest_pinned_message($messages);
    $settings = is_array($conversation['settings'] ?? null)
        ? chat_default_settings($conversation['settings'])
        : chat_default_settings();
    $canManageConversation = chat_can_manage_conversation($conversation, $viewer);
    $canSend = chat_can_send_message($conversation, $viewer);
    $canPinMessages = chat_can_pin_message($conversation, $viewer);
    $canMuteConversation = $conversationType !== 'direct' && $canManageConversation;
    $isMember = chat_is_member($conversation, $viewerStudentNumber);
    $canPinConversation = $isMember;
    $canArchiveConversation = $isMember && !$isMandatory;
    $canLeaveConversation = $conversationType === 'group' && $isMember && !$isMandatory;
    $canClearHistory = $conversationType === 'direct'
        ? $isMember
        : $canManageConversation;
    $canDeleteConversation = false;
    if ($conversationType === 'direct') {
        $canDeleteConversation = $isMember;
    } elseif ($conversationType === 'group' && !$isMandatory) {
        $canDeleteConversation = $canManageConversation;
    }

    $viewerReadState = chat_read_state($store, $conversationId, $viewerStudentNumber);
    $isPinned = (bool) ($viewerReadState['pinned'] ?? false);
    $isArchived = $canArchiveConversation && (bool) ($viewerReadState['archived'] ?? false);
    $isDeleted = !$isMandatory && (bool) ($viewerReadState['deleted'] ?? false);
    $presenceStore = chat_read_presence_snapshot();

    $payload = [
        'id' => $conversationId,
        'type' => $conversationType,
        'title' => chat_conversation_title_for_user($conversation, $viewerStudentNumber),
        'subtitle' => chat_conversation_subtitle_for_user($conversation, $viewerStudentNumber, $viewerCanViewStudentNumbers),
        'about' => chat_conversation_about_for_user($conversation, $viewerStudentNumber),
        'avatarUrl' => chat_conversation_avatar_for_user($conversation, $viewerStudentNumber),
        'createdAt' => (int) ($conversation['createdAt'] ?? time()),
        'updatedAt' => (int) ($conversation['updatedAt'] ?? time()),
        'isMandatory' => $isMandatory,
        'temporary' => chat_parse_bool($conversation['temporary'] ?? false, false),
        'conversationKind' => (string) ($settings['conversationKind'] ?? 'group'),
        'shareUrl' => chat_page_url('?conversationId=' . rawurlencode($conversationId)),
        'memberCount' => count(chat_conversation_member_student_numbers($conversation)),
        'unreadCount' => chat_unread_count($store, $conversation, $viewerStudentNumber),
        'lastReadMessageId' => chat_last_read_message_id($store, $conversationId, $viewerStudentNumber),
        'settings' => $settings,
        'viewerState' => [
            'pinned' => $isPinned,
            'pinnedAt' => $isPinned ? ($viewerReadState['pinnedAt'] ?? null) : null,
            'archived' => $isArchived,
            'archivedAt' => $isArchived ? ($viewerReadState['archivedAt'] ?? null) : null,
            'deleted' => $isDeleted,
            'deletedAt' => $isDeleted ? ($viewerReadState['deletedAt'] ?? null) : null,
            'notificationsMuted' => (bool) ($viewerReadState['notificationsMuted'] ?? false),
            'notificationsMutedAt' => (bool) ($viewerReadState['notificationsMuted'] ?? false)
                ? ($viewerReadState['notificationsMutedAt'] ?? null)
                : null,
        ],
        'permissions' => [
            'canSend' => $canSend,
            'canManageConversation' => $canManageConversation,
            'canPinMessages' => $canPinMessages,
            'canPinConversation' => $canPinConversation,
            'canMuteConversation' => $canMuteConversation,
            'canArchiveConversation' => $canArchiveConversation,
            'canLeaveConversation' => $canLeaveConversation,
            'canClearHistory' => $canClearHistory,
            'canDeleteConversation' => $canDeleteConversation,
            'canMarkRead' => $isMember,
            'canMarkUnread' => $isMember,
            'canCreateGroup' => true,
            'canCreatePoll' => false,
            'canEditProfile' => $conversationType === 'group' && $canManageConversation && !$isMandatory,
            'canEditGroupType' => $conversationType === 'group' && $canManageConversation && !$isMandatory,
            'canEditReactions' => $conversationType !== 'direct' && $canManageConversation,
            'canAddMembers' => $conversationType === 'group' && $canManageConversation,
        ],
        'lastMessage' => $lastMessage !== null ? chat_normalize_message_for_client($lastMessage, $store, $viewer) : null,
        'pinnedMessage' => $pinnedMessage !== null ? chat_normalize_message_for_client($pinnedMessage, $store, $viewer) : null,
        'searchText' => chat_conversation_search_text($messages),
        'presence' => chat_conversation_presence_payload($conversation, $viewer, $presenceStore),
    ];

    if ($canManageConversation) {
        $payload['admins'] = chat_normalize_student_list($conversation['admins'] ?? []);
    }

    if ($conversationType === 'direct') {
        $participants = chat_normalize_student_list($conversation['directParticipants'] ?? []);
        foreach ($participants as $studentNumber) {
            if ($studentNumber !== $viewerStudentNumber) {
                $payload['peer'] = chat_public_user_for_student((string) $studentNumber);
                $payload['peer']['presence'] = chat_presence_state_payload(
                    chat_presence_entry_for_student($presenceStore, $studentNumber),
                    $conversationId
                );
            }
        }
    }

    if ($includeMembers) {
        $payload['members'] = chat_conversation_members_payload($conversation, $conversationId, $presenceStore);
    }

    return $payload;
}

function chat_user_visible_conversation_ids(array &$store, array $user): array
{
    $studentNumber = chat_actor_student_number($user);
    $ids = [];

    foreach ($store['conversations'] as $conversationId => $conversation) {
        if (!is_array($conversation)) {
            continue;
        }

        if (!chat_is_member($conversation, $studentNumber)) {
            $conversation = chat_try_repair_direct_conversation_for_student(
                $store,
                (string) $conversationId,
                $conversation,
                $studentNumber
            );
        }

        if (chat_is_member($conversation, $studentNumber) || chat_user_can_view_without_membership($conversation, $user)) {
            $isMandatory = (bool) ($conversation['mandatory'] ?? false);
            if (
                !$isMandatory
                && chat_is_conversation_deleted_for_user($store, (string) $conversationId, $studentNumber)
            ) {
                continue;
            }

            $ids[] = (string) $conversationId;
        }
    }

    if (!in_array(CHAT_CLASS_CONVERSATION_ID, $ids, true)) {
        $ids[] = CHAT_CLASS_CONVERSATION_ID;
    }

    return array_values(array_unique($ids));
}

function chat_conversation_summaries_for_user(array &$store, array $user): array
{
    $summaries = [];
    foreach (chat_user_visible_conversation_ids($store, $user) as $conversationId) {
        $conversation = chat_get_conversation($store, $conversationId);
        if ($conversation === null) {
            continue;
        }

        $summaries[] = chat_conversation_payload($store, $conversation, $user, false);
    }

    usort($summaries, static function (array $left, array $right): int {
        $leftMandatory = (bool) ($left['isMandatory'] ?? false);
        $rightMandatory = (bool) ($right['isMandatory'] ?? false);
        if ($leftMandatory !== $rightMandatory) {
            return $leftMandatory ? -1 : 1;
        }

        $leftPinned = (bool) ($left['viewerState']['pinned'] ?? false);
        $rightPinned = (bool) ($right['viewerState']['pinned'] ?? false);
        if ($leftPinned !== $rightPinned) {
            return $leftPinned ? -1 : 1;
        }

        if ($leftPinned && $rightPinned) {
            $leftPinnedAt = (int) ($left['viewerState']['pinnedAt'] ?? 0);
            $rightPinnedAt = (int) ($right['viewerState']['pinnedAt'] ?? 0);
            if ($leftPinnedAt !== $rightPinnedAt) {
                return $rightPinnedAt <=> $leftPinnedAt;
            }
        }

        $leftUnread = (int) ($left['unreadCount'] ?? 0);
        $rightUnread = (int) ($right['unreadCount'] ?? 0);
        if ($leftUnread > 0 && $rightUnread === 0) {
            return -1;
        }
        if ($rightUnread > 0 && $leftUnread === 0) {
            return 1;
        }

        $timeDiff = (int) ($right['updatedAt'] ?? 0) <=> (int) ($left['updatedAt'] ?? 0);
        if ($timeDiff !== 0) {
            return $timeDiff;
        }

        return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
    });

    return $summaries;
}

function chat_conversation_list_version_for_user(array &$store, array $user): string
{
    $studentNumber = chat_actor_student_number($user);
    $visibleIds = chat_user_visible_conversation_ids($store, $user);
    sort($visibleIds, SORT_STRING);

    $signature = [];
    foreach ($visibleIds as $conversationId) {
        $conversation = chat_get_conversation($store, $conversationId);
        if ($conversation === null) {
            continue;
        }

        $messages = chat_get_messages($store, $conversationId);
        $lastMessage = chat_last_message($messages);
        $lastMessageId = $lastMessage !== null ? (int) ($lastMessage['id'] ?? 0) : 0;
        $readState = chat_read_state($store, $conversationId, $studentNumber);

        $signature[] = [
            'id' => (string) $conversationId,
            'updatedAt' => (int) ($conversation['updatedAt'] ?? 0),
            'lastMessageId' => $lastMessageId,
            'lastReadMessageId' => max(0, (int) ($readState['lastReadMessageId'] ?? 0)),
            'pinned' => (bool) ($readState['pinned'] ?? false),
            'pinnedAt' => isset($readState['pinnedAt']) ? (int) $readState['pinnedAt'] : 0,
            'archived' => (bool) ($readState['archived'] ?? false),
            'archivedAt' => isset($readState['archivedAt']) ? (int) $readState['archivedAt'] : 0,
            'deleted' => (bool) ($readState['deleted'] ?? false),
            'notificationsMuted' => (bool) ($readState['notificationsMuted'] ?? false),
            'mandatory' => (bool) ($conversation['mandatory'] ?? false),
        ];
    }

    return hash('sha256', json_encode($signature, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
}

function chat_pick_active_conversation_id(array &$store, array $user, string $requestedConversationId): string
{
    $visibleIds = chat_user_visible_conversation_ids($store, $user);
    if ($visibleIds === []) {
        return CHAT_CLASS_CONVERSATION_ID;
    }

    $requestedConversationId = chat_clean_conversation_id($requestedConversationId);
    if ($requestedConversationId !== '' && in_array($requestedConversationId, $visibleIds, true)) {
        return $requestedConversationId;
    }

    if (in_array(CHAT_CLASS_CONVERSATION_ID, $visibleIds, true)) {
        return CHAT_CLASS_CONVERSATION_ID;
    }

    return $visibleIds[0];
}

function chat_dm_conversation_id(string $leftStudentNumber, string $rightStudentNumber): string
{
    $pair = [
        dent_normalize_student_number($leftStudentNumber),
        dent_normalize_student_number($rightStudentNumber),
    ];
    sort($pair, SORT_STRING);
    return 'dm:' . $pair[0] . ':' . $pair[1];
}

function chat_dm_participants_from_conversation_id(string $conversationId): array
{
    if (preg_match('/^dm:([^:]+):([^:]+)$/', $conversationId, $matches) !== 1) {
        return [];
    }

    $left = dent_normalize_student_number((string) ($matches[1] ?? ''));
    $right = dent_normalize_student_number((string) ($matches[2] ?? ''));
    if ($left === '' || $right === '' || $left === $right) {
        return [];
    }

    $pair = [$left, $right];
    sort($pair, SORT_STRING);
    return $pair;
}

function chat_try_repair_direct_conversation_for_student(
    array &$store,
    string $conversationId,
    array $conversation,
    string $studentNumber
): array {
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        return $conversation;
    }

    $normalizedConversationId = chat_clean_conversation_id($conversationId);
    if ($normalizedConversationId === '') {
        $normalizedConversationId = chat_clean_conversation_id((string) ($conversation['id'] ?? ''));
    }
    if ($normalizedConversationId === '') {
        return $conversation;
    }

    $expectedParticipants = chat_dm_participants_from_conversation_id(
        (string) (($conversation['id'] ?? '') !== '' ? $conversation['id'] : $normalizedConversationId)
    );
    if ($expectedParticipants === [] || !in_array($studentNumber, $expectedParticipants, true)) {
        return $conversation;
    }

    $conversation['id'] = $normalizedConversationId;
    $conversation['type'] = 'direct';
    $conversation['directParticipants'] = $expectedParticipants;
    $conversation['memberStudentNumbers'] = $expectedParticipants;
    $conversation['admins'] = [];
    $conversation['settings'] = is_array($conversation['settings'] ?? null)
        ? chat_default_settings($conversation['settings'])
        : chat_default_settings();

    $conversation['createdBy'] = '';

    chat_put_conversation($store, $conversation);
    if (!isset($store['messages'][$normalizedConversationId]) || !is_array($store['messages'][$normalizedConversationId])) {
        $store['messages'][$normalizedConversationId] = [];
    }

    return chat_get_conversation($store, $normalizedConversationId) ?? $conversation;
}

function chat_ensure_direct_conversation(
    array &$store,
    string $leftStudentNumber,
    string $rightStudentNumber,
    string $creatorStudentNumber,
    bool $temporary = false
): array
{
    $leftStudentNumber = dent_normalize_student_number($leftStudentNumber);
    $rightStudentNumber = dent_normalize_student_number($rightStudentNumber);

    if ($leftStudentNumber === '' || $rightStudentNumber === '' || $leftStudentNumber === $rightStudentNumber) {
        dent_error('شناسه گفت‌وگوی خصوصی نامعتبر است.', 422);
    }

    $conversationId = chat_dm_conversation_id($leftStudentNumber, $rightStudentNumber);
    $expectedParticipants = [$leftStudentNumber, $rightStudentNumber];
    sort($expectedParticipants, SORT_STRING);

    $existing = chat_get_conversation($store, $conversationId);
    if ($existing !== null) {
        $existingParticipants = chat_normalize_student_list($existing['directParticipants'] ?? []);
        if ($existingParticipants === [] && is_array($existing['memberStudentNumbers'] ?? null)) {
            $existingParticipants = chat_normalize_student_list($existing['memberStudentNumbers']);
        }
        sort($existingParticipants, SORT_STRING);

        $needsRepair = (string) ($existing['type'] ?? '') !== 'direct'
            || count($existingParticipants) !== 2
            || $existingParticipants !== $expectedParticipants;

        if ($needsRepair) {
            $existing['type'] = 'direct';
            $existing['directParticipants'] = $expectedParticipants;
            $existing['memberStudentNumbers'] = $expectedParticipants;
            $existing['admins'] = [];
            // Direct conversations should not carry a persistent owner
            $existing['createdBy'] = '';
            $existing['updatedAt'] = time();
            chat_put_conversation($store, $existing);
        }

        if ($temporary && !chat_parse_bool($existing['temporary'] ?? false, false)) {
            $existing['temporary'] = true;
            $existing['updatedAt'] = time();
            chat_put_conversation($store, $existing);
        }

        if (!isset($store['messages'][$conversationId]) || !is_array($store['messages'][$conversationId])) {
            $store['messages'][$conversationId] = [];
        }

        chat_restore_conversation_visibility_for_user(
            $store,
            $conversationId,
            dent_normalize_student_number($creatorStudentNumber)
        );

        return chat_get_conversation($store, $conversationId) ?? $existing;
    }

    $now = time();
    $conversation = [
        'id' => $conversationId,
        'type' => 'direct',
        'title' => '',
        'about' => '',
        'avatarUrl' => '',
        'createdAt' => $now,
        'updatedAt' => $now,
        // Direct conversation: no single owner
        'createdBy' => '',
        'mandatory' => false,
        'memberStudentNumbers' => $expectedParticipants,
        'directParticipants' => $expectedParticipants,
        'admins' => [],
        'temporary' => $temporary,
        'settings' => chat_default_settings(),
    ];

    chat_put_conversation($store, $conversation);
    if (!isset($store['messages'][$conversationId]) || !is_array($store['messages'][$conversationId])) {
        $store['messages'][$conversationId] = [];
    }
    chat_restore_conversation_visibility_for_user(
        $store,
        $conversationId,
        dent_normalize_student_number($creatorStudentNumber)
    );

    return $conversation;
}

function chat_create_group_conversation(
    array &$store,
    array $user,
    string $title,
    string $about,
    array $members,
    string $avatarUrl,
    bool $temporary = false,
    string $conversationKind = 'group'
): array {
    $creatorStudentNumber = chat_actor_student_number($user);
    if ($creatorStudentNumber === '') {
        dent_error('شناسه کاربر نامعتبر است.', 422);
    }

    $members[$creatorStudentNumber] = $creatorStudentNumber;
    $memberList = [];
    foreach ($members as $member) {
        $studentNumber = dent_normalize_student_number((string) $member);
        if ($studentNumber === '') {
            continue;
        }

        if (dent_get_user_record($studentNumber) !== null) {
            $memberList[$studentNumber] = $studentNumber;
        }
    }

    $memberList = array_values($memberList);
    sort($memberList, SORT_STRING);
    $conversationKind = chat_clean_conversation_kind($conversationKind);
    if ($conversationKind === 'group' && count($memberList) < 3) {
        dent_error('برای ساخت گروه باید حداقل دو عضو دیگر انتخاب شود. برای گفت‌وگوی یک‌به‌یک از پیام خصوصی استفاده کنید.', 422);
    }

    $conversationId = chat_next_group_conversation_id($store);
    $now = time();
    $isTemporary = $temporary || chat_title_marks_test_conversation($title);
    $conversation = [
        'id' => $conversationId,
        'type' => 'group',
        'title' => $title,
        'about' => $about,
        'avatarUrl' => $avatarUrl,
        'createdAt' => $now,
        'updatedAt' => $now,
        'createdBy' => $creatorStudentNumber,
        'mandatory' => false,
        'memberStudentNumbers' => $memberList,
        'directParticipants' => [],
        'admins' => [$creatorStudentNumber],
        'memberTags' => [],
        'temporary' => $isTemporary,
        'settings' => chat_default_settings([
            'conversationKind' => $conversationKind,
            'memberPosting' => $conversationKind !== 'channel',
        ]),
    ];

    chat_put_conversation($store, $conversation);
    $store['messages'][$conversationId] = [];
    return $conversation;
}

function chat_delete_poll_links_for_conversation(array &$store, string $conversationId): void
{
    $conversationId = chat_clean_conversation_id($conversationId);
    if ($conversationId === '') {
        return;
    }

    if (!isset($store['polls']) || !is_array($store['polls'])) {
        return;
    }

    foreach ($store['polls'] as $pollId => $poll) {
        if (!is_array($poll)) {
            continue;
        }

        $pollConversationId = chat_clean_conversation_id((string) ($poll['conversationId'] ?? ''));
        if ($pollConversationId !== $conversationId) {
            continue;
        }

        unset($store['polls'][$pollId]);
    }
}

function chat_clear_conversation_history(array &$store, array $conversation): int
{
    $conversationId = chat_clean_conversation_id((string) ($conversation['id'] ?? ''));
    if ($conversationId === '') {
        return 0;
    }

    $messages = chat_get_messages($store, $conversationId);
    $count = count($messages);
    if ($count <= 0) {
        return 0;
    }

    chat_put_messages($store, $conversationId, []);
    chat_delete_poll_links_for_conversation($store, $conversationId);
    chat_delete_conversation_attachments($store, $conversationId);

    if (isset($store['reads'][$conversationId]) && is_array($store['reads'][$conversationId])) {
        foreach ($store['reads'][$conversationId] as $studentNumber => $readState) {
            $nextReadState = chat_default_read_state(is_array($readState) ? $readState : []);
            $nextReadState['lastReadMessageId'] = 0;
            $nextReadState['lastReadAt'] = time();
            $store['reads'][$conversationId][$studentNumber] = $nextReadState;
        }
    }

    chat_touch_conversation($store, $conversationId, time());
    return $count;
}

function chat_delete_conversation_record(array &$store, string $conversationId): void
{
    $conversationId = chat_clean_conversation_id($conversationId);
    if ($conversationId === '' || $conversationId === CHAT_CLASS_CONVERSATION_ID) {
        return;
    }

    unset($store['conversations'][$conversationId]);
    unset($store['messages'][$conversationId]);
    unset($store['reads'][$conversationId]);
    chat_delete_poll_links_for_conversation($store, $conversationId);
    chat_delete_conversation_attachments($store, $conversationId);
}

function chat_delete_message_attachments(array &$store, string $conversationId, int $messageId): void
{
    if ($messageId <= 0) {
        return;
    }
    $conversationId = chat_clean_conversation_id($conversationId);
    if ($conversationId === '') {
        return;
    }

    $attachments = is_array($store['attachments'] ?? null) ? $store['attachments'] : [];
    foreach ($attachments as $attachmentId => $attachment) {
        if (!is_array($attachment)) {
            unset($attachments[$attachmentId]);
            continue;
        }
        if (chat_clean_conversation_id((string) ($attachment['conversationId'] ?? '')) !== $conversationId) {
            continue;
        }
        if ((int) ($attachment['messageId'] ?? 0) !== $messageId) {
            continue;
        }

        chat_delete_attachment_artifacts($attachment);
        unset($attachments[$attachmentId]);
    }
    $store['attachments'] = $attachments;
}

function chat_delete_conversation_attachments(array &$store, string $conversationId): void
{
    $conversationId = chat_clean_conversation_id($conversationId);
    if ($conversationId === '') {
        return;
    }

    $attachments = is_array($store['attachments'] ?? null) ? $store['attachments'] : [];
    foreach ($attachments as $attachmentId => $attachment) {
        if (!is_array($attachment)) {
            unset($attachments[$attachmentId]);
            continue;
        }
        if (chat_clean_conversation_id((string) ($attachment['conversationId'] ?? '')) !== $conversationId) {
            continue;
        }

        chat_delete_attachment_artifacts($attachment);
        unset($attachments[$attachmentId]);
    }
    $store['attachments'] = $attachments;
}

function chat_leave_group_conversation(array &$store, array $conversation, string $studentNumber): bool
{
    $conversationId = chat_clean_conversation_id((string) ($conversation['id'] ?? ''));
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($conversationId === '' || $studentNumber === '') {
        return false;
    }

    $memberList = chat_normalize_student_list($conversation['memberStudentNumbers'] ?? []);
    $memberList = array_values(array_filter(
        $memberList,
        static fn($value): bool => dent_normalize_student_number((string) $value) !== $studentNumber
    ));

    if ($memberList === []) {
        chat_delete_conversation_record($store, $conversationId);
        return true;
    }

    sort($memberList, SORT_STRING);
    $adminList = chat_normalize_student_list($conversation['admins'] ?? []);
    $adminList = array_values(array_filter(
        $adminList,
        static fn($value): bool => dent_normalize_student_number((string) $value) !== $studentNumber
    ));

    if ($adminList === []) {
        $adminList[] = $memberList[0];
    }

    $conversation['memberStudentNumbers'] = $memberList;
    $conversation['admins'] = $adminList;
    $conversation['updatedAt'] = time();
    chat_put_conversation($store, $conversation);

    if (isset($store['reads'][$conversationId][$studentNumber])) {
        unset($store['reads'][$conversationId][$studentNumber]);
    }

    return true;
}

function chat_collect_attachable_ids(array &$store, string $conversationId, array $user, array $attachmentIds): array
{
    $conversationId = chat_clean_conversation_id($conversationId);
    $actorStudentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($conversationId === '' || $actorStudentNumber === '') {
        return [];
    }

    $cleanIds = chat_parse_attachment_ids_input($attachmentIds);
    if ($cleanIds === []) {
        return [];
    }

    $resolved = [];
    foreach ($cleanIds as $attachmentId) {
        $attachment = chat_get_attachment($store, $attachmentId);
        if ($attachment === null) {
            dent_error('یکی از فایل‌های انتخاب‌شده پیدا نشد.', 404);
        }

        if ((string) ($attachment['conversationId'] ?? '') !== $conversationId) {
            dent_error('فایل انتخاب‌شده متعلق به این گفتگو نیست.', 422);
        }

        if (dent_normalize_student_number((string) ($attachment['uploaderStudentNumber'] ?? '')) !== $actorStudentNumber) {
            dent_error('اجازه الصاق این فایل را ندارید.', 403);
        }

        if ((string) ($attachment['status'] ?? 'available') !== 'available') {
            dent_error('یکی از فایل‌های انتخاب‌شده منقضی شده است.', 422);
        }

        $linkedMessageId = (int) ($attachment['messageId'] ?? 0);
        if ($linkedMessageId > 0) {
            dent_error('یکی از فایل‌های انتخاب‌شده قبلاً به پیام دیگری متصل شده است.', 409);
        }

        $resolved[] = $attachmentId;
    }

    return $resolved;
}

function chat_append_message(
    array &$store,
    string $conversationId,
    array $user,
    string $text,
    ?int $replyTo = null,
    string $kind = 'text',
    string $pollId = '',
    array $attachmentIds = []
): array {
    $conversationId = chat_clean_conversation_id($conversationId);
    if ($conversationId === '') {
        dent_error('شناسه گفتگو نامعتبر است.', 422);
    }

    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    if (!chat_can_send_message($conversation, $user)) {
        dent_error('در حال حاضر امکان ارسال پیام در این گفتگو را ندارید.', 403);
    }

    chat_apply_rate_limit($conversationId);

    $text = chat_sanitize_message_text($text);
    $attachmentIds = chat_collect_attachable_ids($store, $conversationId, $user, $attachmentIds);

    $messages = chat_get_messages($store, $conversationId);
    if ($replyTo !== null && $replyTo > 0 && chat_find_message_index($messages, $replyTo) === -1) {
        $replyTo = null;
    }

    $kind = trim($kind);
    if (!in_array($kind, ['text', 'poll', 'attachment', 'voice'], true)) {
        $kind = 'text';
    }

    $pollId = $kind === 'poll' ? chat_clean_poll_id($pollId) : '';
    if ($kind === 'poll' && $pollId === '') {
        $kind = 'text';
    }

    if ($kind === 'poll') {
        $attachmentIds = [];
    } elseif ($attachmentIds !== []) {
        if (count($attachmentIds) === 1) {
            $singleAttachment = chat_get_attachment($store, $attachmentIds[0]);
            $singleCategory = (string) ($singleAttachment['category'] ?? '');
            $kind = $singleCategory === 'voice' ? 'voice' : 'attachment';
        } else {
            $kind = 'attachment';
        }
    } else {
        $kind = 'text';
    }

    if ($text === '' && $kind === 'poll') {
        $text = 'Poll';
    }
    if ($text === '' && $attachmentIds !== []) {
        $text = 'Attachment';
    }
    if ($text === '' && $attachmentIds === []) {
        dent_error('متن پیام یا فایل پیوست الزامی است.', 422);
    }

    $message = [
        'id' => chat_next_message_id($store),
        'conversationId' => $conversationId,
        'senderStudentNumber' => dent_normalize_student_number((string) ($user['studentNumber'] ?? '')),
        'text' => $text,
        'ts' => time(),
        'editedAt' => null,
        'replyTo' => $replyTo,
        'pinned' => false,
        'reactions' => [],
        'kind' => $kind,
        'pollId' => $kind === 'poll' ? $pollId : '',
        'attachmentIds' => $attachmentIds,
    ];

    $messages[] = $message;
    chat_put_messages($store, $conversationId, $messages);

    foreach ($attachmentIds as $attachmentId) {
        $attachment = chat_get_attachment($store, $attachmentId);
        if ($attachment === null) {
            continue;
        }

        $attachment['messageId'] = (int) $message['id'];
        $attachment['linkedAt'] = time();
        $attachment['updatedAt'] = time();
        chat_put_attachment($store, $attachment);
    }

    chat_touch_conversation($store, $conversationId, (int) $message['ts']);
    chat_restore_visibility_for_conversation_members($store, $conversation);
    chat_mark_read($store, $conversationId, (string) ($user['studentNumber'] ?? ''), (int) $message['id']);

    return $message;
}

function chat_require_conversation_for_user(array &$store, string $conversationId, array $user): array
{
    $conversation = chat_get_conversation($store, $conversationId);
    if ($conversation === null) {
        dent_error('گفت‌وگو پیدا نشد.', 404);
    }

    $studentNumber = chat_actor_student_number($user);
    if ($studentNumber === '') {
        dent_error('هویت کاربر نامعتبر است.', 401);
    }

    $isMemberBeforeRepair = chat_is_member($conversation, $studentNumber);
    if (!$isMemberBeforeRepair) {
        $conversation = chat_try_repair_direct_conversation_for_student(
            $store,
            $conversationId,
            $conversation,
            $studentNumber
        );
    }

    $isMemberAfterRepair = chat_is_member($conversation, $studentNumber);
    if (!$isMemberAfterRepair && !chat_user_can_view_without_membership($conversation, $user)) {
        dent_error('به این گفت‌وگو دسترسی ندارید.', 403);
    }

    return chat_get_conversation($store, $conversationId) ?? $conversation;
}

function chat_apply_rate_limit(string $conversationId): void
{
    $windowSeconds = 10;
    $maxMessages = 8;
    $now = time();

    $conversationId = chat_clean_conversation_id($conversationId);
    if ($conversationId === '') {
        $conversationId = 'global';
    }

    $rateLimitState = $_SESSION['chat_rate_limit'][$conversationId] ?? [
        'startedAt' => 0,
        'count' => 0,
    ];

    if (($now - (int) ($rateLimitState['startedAt'] ?? 0)) > $windowSeconds) {
        $rateLimitState = [
            'startedAt' => $now,
            'count' => 0,
        ];
    }

    $rateLimitState['count'] = (int) ($rateLimitState['count'] ?? 0) + 1;
    $_SESSION['chat_rate_limit'][$conversationId] = $rateLimitState;

    if ((int) $rateLimitState['count'] > $maxMessages) {
        dent_error('تعداد ارسال پیام بیش از حد مجاز است. چند لحظه بعد دوباره تلاش کن.', 429);
    }
}

function chat_parse_members_input($raw): array
{
    if (is_array($raw)) {
        return chat_normalize_student_list($raw);
    }

    $text = trim((string) $raw);
    if ($text === '') {
        return [];
    }

    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return chat_normalize_student_list($decoded);
    }

    return chat_normalize_student_list($text);
}

function chat_parse_poll_options_input($raw): array
{
    return chat_normalize_poll_options($raw);
}

function chat_parse_poll_option_ids_input($raw): array
{
    if (is_array($raw)) {
        return $raw;
    }

    $text = trim((string) $raw);
    if ($text === '') {
        return [];
    }

    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    return preg_split('/[\s,;]+/u', $text) ?: [];
}

function chat_uploaded_file_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'حجم فایل بیشتر از حد مجاز سرور است.',
        UPLOAD_ERR_PARTIAL => 'بارگذاری فایل ناقص انجام شد. دوباره تلاش کنید.',
        UPLOAD_ERR_NO_FILE => 'فایلی برای بارگذاری انتخاب نشده است.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'بارگذاری فایل روی سرور با خطا مواجه شد.',
        default => 'بارگذاری فایل انجام نشد.',
    };
}

function chat_detect_file_mime(string $path, string $fallback = ''): string
{
    $fallback = strtolower(trim($fallback));

    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = @finfo_file($finfo, $path);
            @finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return strtolower(trim($mime));
            }
        }
    }

    if ($fallback !== '') {
        return $fallback;
    }

    return 'application/octet-stream';
}

function chat_generate_image_preview(string $sourcePath, string $targetPath): bool
{
    if (!function_exists('imagecreatefromstring')) {
        return false;
    }

    $raw = @file_get_contents($sourcePath);
    if (!is_string($raw) || $raw === '') {
        return false;
    }

    $image = @imagecreatefromstring($raw);
    if (!$image) {
        return false;
    }

    $sourceWidth = imagesx($image);
    $sourceHeight = imagesy($image);
    if ($sourceWidth <= 0 || $sourceHeight <= 0) {
        imagedestroy($image);
        return false;
    }

    $maxEdge = 480;
    $scale = min(1, $maxEdge / max($sourceWidth, $sourceHeight));
    $targetWidth = max(1, (int) floor($sourceWidth * $scale));
    $targetHeight = max(1, (int) floor($sourceHeight * $scale));

    $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
    if (!$canvas) {
        imagedestroy($image);
        return false;
    }

    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);
    imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

    dent_ensure_directory(dirname($targetPath));
    $saved = @imagejpeg($canvas, $targetPath, 76);

    imagedestroy($canvas);
    imagedestroy($image);
    return (bool) $saved;
}

function chat_exec_available(): bool
{
    if (!function_exists('exec')) {
        return false;
    }

    $disabledRaw = strtolower((string) (ini_get('disable_functions') ?: ''));
    if ($disabledRaw === '') {
        return true;
    }

    $disabled = array_map('trim', explode(',', $disabledRaw));
    return !in_array('exec', $disabled, true);
}

function chat_ffmpeg_binary(): string
{
    static $resolved = null;
    if (is_string($resolved)) {
        return $resolved;
    }

    $candidate = trim((string) (getenv('DENT_CHAT_FFMPEG_BIN') ?: 'ffmpeg'));
    if ($candidate === '') {
        $resolved = '';
        return $resolved;
    }

    $resolved = $candidate;
    return $resolved;
}

function chat_generate_video_preview(string $sourcePath, string $targetPath): bool
{
    if (!chat_exec_available()) {
        return false;
    }

    $binary = chat_ffmpeg_binary();
    if ($binary === '') {
        return false;
    }

    dent_ensure_directory(dirname($targetPath));
    $scaleFilter = "thumbnail,scale='min(480,iw)':-2";
    $commands = [
        escapeshellarg($binary)
            . ' -y -ss 00:00:01 -i ' . escapeshellarg($sourcePath)
            . ' -frames:v 1 -vf ' . escapeshellarg($scaleFilter)
            . ' -q:v 7 ' . escapeshellarg($targetPath)
            . ' 2>&1',
        escapeshellarg($binary)
            . ' -y -i ' . escapeshellarg($sourcePath)
            . ' -frames:v 1 -vf ' . escapeshellarg($scaleFilter)
            . ' -q:v 7 ' . escapeshellarg($targetPath)
            . ' 2>&1',
    ];

    foreach ($commands as $command) {
        if (is_file($targetPath)) {
            @unlink($targetPath);
        }

        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);
        if ($exitCode === 0 && is_file($targetPath)) {
            return true;
        }
    }

    return false;
}

function chat_store_uploaded_attachment(
    array &$store,
    array $user,
    string $conversationId,
    array $fileInfo,
    array $options = []
): array {
    if (!isset($fileInfo['tmp_name']) || !isset($fileInfo['name'])) {
        dent_error('فایل بارگذاری‌شده معتبر نیست.', 422);
    }

    $errorCode = (int) ($fileInfo['error'] ?? UPLOAD_ERR_OK);
    if ($errorCode !== UPLOAD_ERR_OK) {
        dent_error(chat_uploaded_file_error_message($errorCode), 422);
    }

    $tmpPath = (string) ($fileInfo['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        dent_error('فایل بارگذاری‌شده معتبر نیست.', 422);
    }

    $originalName = dent_clean_text((string) ($fileInfo['name'] ?? 'file'), 180);
    if ($originalName === '') {
        $originalName = 'file';
    }

    $extension = chat_safe_extension($originalName);
    if (chat_is_blocked_upload_extension($extension)) {
        dent_error('این نوع فایل برای امنیت سامانه قابل بارگذاری نیست.', 422);
    }

    $sizeBytes = max(0, (int) ($fileInfo['size'] ?? 0));
    if ($sizeBytes <= 0) {
        $sizeBytes = max(0, (int) (@filesize($tmpPath) ?: 0));
    }
    if ($sizeBytes <= 0) {
        dent_error('فایل انتخاب‌شده خالی است.', 422);
    }
    if ($sizeBytes > CHAT_MEDIA_MAX_FILE_BYTES) {
        dent_error('حجم فایل از سقف مجاز بیشتر است.', 422);
    }

    $voiceFlag = chat_parse_bool($options['isVoice'] ?? false, false);
    $durationSeconds = isset($options['durationSeconds']) ? max(0, (float) $options['durationSeconds']) : null;
    $mime = chat_detect_file_mime($tmpPath, (string) ($fileInfo['type'] ?? ''));
    $category = chat_attachment_category_for_mime($mime, $extension, $voiceFlag);
    if ($voiceFlag && !in_array($category, ['audio', 'voice'], true)) {
        dent_error('فایل ضبط‌شده به‌عنوان پیام صوتی معتبر نیست.', 422);
    }
    if ($voiceFlag) {
        $category = 'voice';
    }

    $attachmentId = chat_next_attachment_id($store);
    $safeFileName = $attachmentId . ($extension !== '' ? ('.' . $extension) : '');
    $originalRelative = 'originals/' . $safeFileName;
    $originalPath = chat_attachment_absolute_path($originalRelative);
    if ($originalPath === '') {
        dent_error('مسیر ذخیره فایل معتبر نیست.', 500);
    }
    dent_ensure_directory(dirname($originalPath));

    if (!@move_uploaded_file($tmpPath, $originalPath)) {
        dent_error('ذخیره فایل روی سرور انجام نشد.', 500);
    }

    $savedSize = @filesize($originalPath);
    if ($savedSize !== false) {
        $sizeBytes = max(0, (int) $savedSize);
    }

    $previewRelative = '';
    $previewExists = false;
    if ($category === 'image' || $category === 'video') {
        $previewRelative = 'previews/' . $attachmentId . '.jpg';
        $previewPath = chat_attachment_absolute_path($previewRelative);
        $previewGenerated = false;
        if ($previewPath !== '') {
            if ($category === 'image') {
                $previewGenerated = chat_generate_image_preview($originalPath, $previewPath);
            } else {
                $previewGenerated = chat_generate_video_preview($originalPath, $previewPath);
            }
        }

        if ($previewPath !== '' && $previewGenerated) {
            $previewExists = true;
        } else {
            $previewRelative = '';
            $previewExists = false;
        }
    }

    $now = time();
    $attachment = [
        'id' => $attachmentId,
        'conversationId' => $conversationId,
        'messageId' => null,
        'uploaderStudentNumber' => dent_normalize_student_number((string) ($user['studentNumber'] ?? '')),
        'originalName' => $originalName,
        'safeFileName' => $safeFileName,
        'mime' => $mime,
        'extension' => $extension,
        'category' => $category,
        'isVoice' => $category === 'voice',
        'sizeBytes' => $sizeBytes,
        'durationSeconds' => $durationSeconds,
        'width' => null,
        'height' => null,
        'createdAt' => $now,
        'updatedAt' => $now,
        'linkedAt' => null,
        'status' => 'available',
        'purgedAt' => null,
        'purgeReason' => '',
        'originalPath' => $originalRelative,
        'previewPath' => $previewRelative,
        'originalExists' => true,
        'previewExists' => $previewExists,
    ];

    chat_put_attachment($store, $attachment);
    chat_sync_media_health($store, false);

    $storedAttachment = chat_get_attachment($store, $attachmentId);
    if ($storedAttachment === null) {
        dent_error('فایل بارگذاری شد اما ثبت متادیتا انجام نشد.', 500);
    }

    return chat_attachment_payload($storedAttachment);
}

function chat_create_poll(
    array &$store,
    array $user,
    array $input
): array {
    if (!chat_can_create_poll($user)) {
        dent_error('فقط نماینده یا مالک می‌تواند نظرسنجی بسازد.', 403);
    }

    $creatorStudentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($creatorStudentNumber === '') {
        dent_error('کاربر جاری معتبر نیست.', 422);
    }

    $question = dent_clean_text((string) ($input['question'] ?? ''), 280);
    if ($question === '') {
        dent_error('عنوان نظرسنجی الزامی است.', 422);
    }

    $options = chat_parse_poll_options_input($input['options'] ?? []);
    if (count($options) < 2) {
        dent_error('حداقل دو گزینه برای نظرسنجی لازم است.', 422);
    }

    $conversationId = chat_clean_conversation_id((string) ($input['conversationId'] ?? ''));
    $conversation = null;
    if ($conversationId !== '') {
        $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
        if (!chat_can_send_message($conversation, $user)) {
            dent_error('در این گفتگو اجازه ارسال پیام ندارید.', 403);
        }
    }

    $anonymous = chat_parse_bool($input['anonymous'] ?? true, true);
    $multipleChoice = chat_parse_bool($input['multipleChoice'] ?? false, false);
    $allowVoteChange = chat_parse_bool($input['allowVoteChange'] ?? true, true);
    $allowCreatorVote = chat_parse_bool($input['allowCreatorVote'] ?? true, true);
    $resultVisibility = chat_clean_poll_result_visibility((string) (
        $input['resultVisibility'] ?? CHAT_POLL_VISIBILITY_LIVE
    ));
    $audience = chat_clean_poll_audience((string) (
        $input['audience'] ?? CHAT_POLL_AUDIENCE_LINK
    ));

    $maxChoicesInput = (int) ($input['maxChoices'] ?? ($multipleChoice ? 2 : 1));
    if (!$multipleChoice) {
        $maxChoices = 1;
    } else {
        $maxChoices = max(2, $maxChoicesInput);
        $maxChoices = min($maxChoices, count($options));
    }

    $startAt = chat_parse_timestamp($input['startAt'] ?? null);
    $endAt = chat_parse_timestamp($input['endAt'] ?? null);
    $now = time();

    if ($startAt !== null && $endAt !== null && $endAt <= $startAt) {
        dent_error('زمان پایان باید بعد از زمان شروع باشد.', 422);
    }

    if ($endAt !== null && $endAt <= $now) {
        dent_error('زمان پایان باید در آینده باشد.', 422);
    }

    $pollId = chat_next_poll_id($store);
    $poll = [
        'id' => $pollId,
        'conversationId' => $conversationId,
        'messageId' => null,
        'question' => $question,
        'options' => $options,
        'createdBy' => $creatorStudentNumber,
        'createdAt' => $now,
        'updatedAt' => $now,
        'startAt' => $startAt,
        'endAt' => $endAt,
        'closedAt' => null,
        'closedBy' => null,
        'manualClosed' => false,
        'anonymous' => $anonymous,
        'multipleChoice' => $multipleChoice,
        'maxChoices' => $maxChoices,
        'allowVoteChange' => $allowVoteChange,
        'allowCreatorVote' => $allowCreatorVote,
        'resultVisibility' => $resultVisibility,
        'audience' => $audience,
        'votes' => [],
    ];

    chat_put_poll($store, $poll);

    $postedMessage = null;
    $postInConversation = chat_parse_bool($input['postInConversation'] ?? true, true);
    if ($conversationId !== '' && $postInConversation) {
        $message = chat_append_message(
            $store,
            $conversationId,
            $user,
            '📊 ' . $question . "\n" . chat_poll_share_url($pollId),
            null,
            'poll',
            $pollId
        );
        $poll['messageId'] = (int) ($message['id'] ?? 0);
        $poll['updatedAt'] = time();
        chat_put_poll($store, $poll);
        $postedMessage = $message;
    }

    return [
        'poll' => $poll,
        'message' => $postedMessage,
    ];
}

function chat_apply_poll_vote(array &$poll, array $user, array $selectedOptionIds): void
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        dent_error('کاربر جاری معتبر نیست.', 422);
    }

    if (!chat_poll_is_voting_open($poll)) {
        dent_error('رأی‌گیری این نظرسنجی در حال حاضر بسته است.', 422);
    }

    $createdBy = dent_normalize_student_number((string) ($poll['createdBy'] ?? ''));
    if ($studentNumber === $createdBy && !(bool) ($poll['allowCreatorVote'] ?? true)) {
        dent_error('سازنده این نظرسنجی اجازه رأی‌دادن ندارد.', 403);
    }

    $allowedOptionIds = chat_poll_option_ids($poll);
    $normalizedOptionIds = chat_normalize_poll_option_ids($selectedOptionIds, $allowedOptionIds);
    if ($normalizedOptionIds === []) {
        dent_error('حداقل یک گزینه معتبر انتخاب کنید.', 422);
    }

    $multipleChoice = (bool) ($poll['multipleChoice'] ?? false);
    $maxChoices = max(1, (int) ($poll['maxChoices'] ?? 1));

    if (!$multipleChoice) {
        $firstSelectedOptionId = reset($normalizedOptionIds);
        $normalizedOptionIds = [$firstSelectedOptionId !== false ? (string) $firstSelectedOptionId : ''];
        $normalizedOptionIds = array_values(array_filter(
            $normalizedOptionIds,
                static fn($optionId): bool => (string) $optionId !== ''
        ));
        if ($normalizedOptionIds === []) {
            dent_error('گزینه انتخابی معتبر نیست.', 422);
        }
    } elseif (count($normalizedOptionIds) > $maxChoices) {
        dent_error('تعداد انتخاب‌ها بیشتر از حد مجاز است.', 422);
    }

    sort($normalizedOptionIds, SORT_STRING);
    $votes = is_array($poll['votes'] ?? null) ? $poll['votes'] : [];
    $existing = $votes[$studentNumber] ?? null;
    $allowVoteChange = (bool) ($poll['allowVoteChange'] ?? true);

    if (is_array($existing) && !$allowVoteChange) {
        dent_error('این نظرسنجی فقط یک‌بار رأی‌گیری دارد و تغییر رأی فعال نیست.', 409);
    }

    $votes[$studentNumber] = [
        'optionIds' => $normalizedOptionIds,
        'updatedAt' => time(),
    ];

    $poll['votes'] = $votes;
    $poll['updatedAt'] = time();
}

function chat_close_poll(array &$poll, array $user): void
{
    $poll['closedAt'] = time();
    $poll['closedBy'] = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $poll['manualClosed'] = true;
    $poll['updatedAt'] = time();
}

function chat_reopen_poll(array &$poll): void
{
    $now = time();
    $endAt = chat_parse_timestamp($poll['endAt'] ?? null);
    if ($endAt !== null && $endAt <= $now) {
        dent_error('به‌دلیل پایان زمان‌بندی، بازگشایی این نظرسنجی ممکن نیست.', 422);
    }

    $poll['closedAt'] = null;
    $poll['closedBy'] = null;
    $poll['manualClosed'] = false;
    $poll['updatedAt'] = $now;
}

function chat_list_polls_for_user(array $store, array $user): array
{
    $polls = [];
    $viewerStudentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $canManageAny = chat_user_can_moderate_active_cohort($user);

    foreach ((array) ($store['polls'] ?? []) as $poll) {
        if (!is_array($poll)) {
            continue;
        }

        $pollId = chat_clean_poll_id((string) ($poll['id'] ?? ''));
        if ($pollId === '') {
            continue;
        }

        $createdBy = dent_normalize_student_number((string) ($poll['createdBy'] ?? ''));
        $isCreator = $viewerStudentNumber !== '' && $viewerStudentNumber === $createdBy;
        $hasVoted = chat_poll_selected_option_ids_for_user($poll, $viewerStudentNumber) !== [];
        $isManageable = chat_poll_can_manage($store, $poll, $user);

        if (!$isCreator && !$hasVoted && !$isManageable && !$canManageAny) {
            continue;
        }

        $polls[] = chat_poll_payload($store, $poll, $user, false);
    }

    usort($polls, static function (array $left, array $right): int {
        return (int) ($right['updatedAt'] ?? 0) <=> (int) ($left['updatedAt'] ?? 0);
    });

    return $polls;
}

function chat_list_active_polls_for_user(array $store, array $user): array
{
    $polls = [];
    foreach ((array) ($store['polls'] ?? []) as $poll) {
        if (!is_array($poll)) {
            continue;
        }

        $pollId = chat_clean_poll_id((string) ($poll['id'] ?? ''));
        if ($pollId === '') {
            continue;
        }

        if (chat_poll_status($poll) !== 'open') {
            continue;
        }

        if (!chat_poll_is_discoverable_for_user($store, $poll, $user)) {
            continue;
        }

        $polls[] = chat_poll_payload($store, $poll, $user, false);
    }

    usort($polls, static function (array $left, array $right): int {
        return (int) ($right['updatedAt'] ?? 0) <=> (int) ($left['updatedAt'] ?? 0);
    });

    return $polls;
}

function chat_delete_poll(array &$store, array $poll): void
{
    $pollId = chat_clean_poll_id((string) ($poll['id'] ?? ''));
    if ($pollId === '') {
        return;
    }

    if (isset($store['polls'][$pollId])) {
        unset($store['polls'][$pollId]);
    }

    $conversationId = chat_clean_conversation_id((string) ($poll['conversationId'] ?? ''));
    $messageId = (int) ($poll['messageId'] ?? 0);
    if ($conversationId === '' || $messageId <= 0) {
        return;
    }

    $messages = chat_get_messages($store, $conversationId);
    $index = chat_find_message_index($messages, $messageId);
    if ($index === -1) {
        return;
    }

    array_splice($messages, $index, 1);
    chat_put_messages($store, $conversationId, $messages);
    chat_touch_conversation($store, $conversationId, time());
}

chat_ensure_storage();

$action = dent_request_action();
chat_set_active_cohort(chat_requested_cohort());
chat_ensure_storage();

if (in_array($action, ['login', 'logout', 'me'], true)) {
    dent_error('Chat-specific auth actions are deprecated. Use /api/auth_api.php for login/session.', 410, [
        'deprecatedAuthSurface' => true,
        'useAuthApi' => '/api/auth_api.php',
    ]);
}

if ($action === 'media') {
    if (dent_request_method() !== 'GET') {
        dent_error('متد دریافت فایل نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $attachmentId = chat_clean_attachment_id((string) ($_GET['attachmentId'] ?? ''));
    if ($attachmentId === '') {
        dent_error('شناسه فایل نامعتبر است.', 422);
    }

    $variant = chat_clean_media_variant((string) ($_GET['variant'] ?? CHAT_MEDIA_VARIANT_ORIGINAL));
    $download = (string) ($_GET['download'] ?? '0') === '1';

    $store = chat_load_store();
    $attachment = chat_get_attachment($store, $attachmentId);
    if ($attachment === null) {
        dent_error('فایل موردنظر پیدا نشد.', 404);
    }

    if (!chat_attachment_access_allowed($store, $attachment, $user)) {
        dent_error('به این فایل دسترسی ندارید.', 403);
    }

    $attachmentChanged = chat_sync_attachment_file_flags($attachment);
    if ($attachmentChanged) {
        chat_put_attachment($store, $attachment);
        chat_sync_media_health($store, false);
        chat_save_store($store);
        $attachment = chat_get_attachment($store, $attachmentId) ?? $attachment;
    }

    if ($variant === CHAT_MEDIA_VARIANT_PREVIEW
        && !(bool) ($attachment['previewExists'] ?? false)
        && (string) ($attachment['category'] ?? '') === 'image'
        && (bool) ($attachment['originalExists'] ?? false)
        && (string) ($attachment['status'] ?? 'available') === 'available') {
        $variant = CHAT_MEDIA_VARIANT_ORIGINAL;
        $download = false;
    }

    if ($variant === CHAT_MEDIA_VARIANT_ORIGINAL && (string) ($attachment['status'] ?? 'available') !== 'available') {
        dent_error('فایل اصلی دیگر در دسترس نیست.', 410, [
            'expired' => true,
            'attachment' => chat_attachment_payload($attachment),
        ]);
    }

    chat_release_store_lock();
    chat_stream_attachment_file($attachment, $variant, $download);
}

if ($action === 'uploadAttachment') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد بارگذاری فایل نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? CHAT_CLASS_CONVERSATION_ID));
    if ($conversationId === '') {
        dent_error('شناسه گفت‌وگو نامعتبر است.', 422);
    }

    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        dent_error('فایل بارگذاری نشده است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    if (!chat_can_send_message($conversation, $user)) {
        dent_error('در حال حاضر اجازه ارسال فایل در این گفتگو را ندارید.', 403);
    }

    $attachmentPayload = chat_store_uploaded_attachment($store, $user, $conversationId, $_FILES['file'], [
        'isVoice' => $_POST['isVoice'] ?? '0',
        'durationSeconds' => $_POST['durationSeconds'] ?? null,
    ]);
    chat_save_store($store);

    dent_json_response([
        'success' => true,
        'attachment' => $attachmentPayload,
        'conversation' => chat_conversation_payload($store, $conversation, $user, false),
        'mediaStatus' => chat_media_status_payload($store),
        'notice' => 'به‌دلیل محدودیت فضای ذخیره‌سازی، فایل‌های پیام‌رسان ممکن است در آینده پاک شوند.',
    ]);
}

if ($action === 'mediaStatus') {
    dent_require_owner();
    $store = chat_load_store();
    $changed = chat_sync_media_health($store, false);
    if ($changed) {
        chat_save_store($store);
    }

    dent_json_response([
        'success' => true,
        'media' => chat_media_status_payload($store),
    ]);
}

if ($action === 'mediaCleanupNow') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد پاکسازی فایل نامعتبر است.', 405);
    }

    dent_require_owner();
    $store = chat_load_store();
    $changed = chat_sync_media_health($store, true);
    if ($changed) {
        chat_save_store($store);
    }

    dent_json_response([
        'success' => true,
        'media' => chat_media_status_payload($store),
    ]);
}

if ($action === 'directory') {
    $viewer = chat_require_user();

    $users = [];
    foreach (dent_list_public_users() as $user) {
        $record = dent_get_user_record((string) ($user['studentNumber'] ?? ''));
        if (!is_array($record) || !chat_user_belongs_to_active_cohort($record)) {
            continue;
        }
        $profile = is_array($user['profile'] ?? null) ? $user['profile'] : dent_default_profile();
        $user['avatarUrl'] = (string) (($profile['avatarUrl'] ?? '') ?: '');
        $user['about'] = (string) (($profile['about'] ?? ($profile['bio'] ?? '')) ?: '');
        $user['canModerateChat'] = chat_user_can_moderate_active_cohort($record);
        $user['isRepresentative'] = chat_user_can_moderate_active_cohort($record) && empty($user['isOwner']);
        $users[] = $user;
    }

    dent_json_response([
        'success' => true,
        'cohort' => chat_active_cohort(),
        'viewer' => chat_public_user_payload($viewer),
        'users' => $users,
    ]);
}

if (in_array($action, ['listPolls', 'activePolls', 'createPoll', 'poll', 'votePoll', 'deletePoll', 'closePoll', 'reopenPoll'], true)) {
    dent_error('بخش نظرسنجی در پیام‌رسان غیرفعال شده است.', 410);
}

if ($action === 'listPolls') {
    $user = chat_require_user();
    if (!chat_can_create_poll($user)) {
        dent_error('دسترسی این بخش فقط برای مالک و نماینده فعال است.', 403);
    }

    $store = chat_load_store();

    dent_json_response([
        'success' => true,
        'canCreatePoll' => chat_can_create_poll($user),
        'polls' => chat_list_polls_for_user($store, $user),
        'conversations' => chat_conversation_summaries_for_user($store, $user),
    ]);
}

if ($action === 'activePolls') {
    $user = chat_require_user();
    $store = chat_load_store();
    $polls = chat_list_active_polls_for_user($store, $user);

    dent_json_response([
        'success' => true,
        'count' => count($polls),
        'polls' => $polls,
    ]);
}

if ($action === 'createPoll') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ساخت نظرسنجی نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $store = chat_load_store();

    $created = chat_create_poll($store, $user, [
        'question' => $_POST['question'] ?? '',
        'options' => $_POST['options'] ?? ($_POST['optionsJson'] ?? ''),
        'conversationId' => $_POST['conversationId'] ?? '',
        'anonymous' => $_POST['anonymous'] ?? '1',
        'multipleChoice' => $_POST['multipleChoice'] ?? '0',
        'maxChoices' => $_POST['maxChoices'] ?? null,
        'allowVoteChange' => $_POST['allowVoteChange'] ?? '1',
        'allowCreatorVote' => $_POST['allowCreatorVote'] ?? '1',
        'resultVisibility' => $_POST['resultVisibility'] ?? CHAT_POLL_VISIBILITY_LIVE,
        'audience' => $_POST['audience'] ?? CHAT_POLL_AUDIENCE_LINK,
        'startAt' => $_POST['startAt'] ?? null,
        'endAt' => $_POST['endAt'] ?? null,
        'postInConversation' => $_POST['postInConversation'] ?? '1',
    ]);

    $poll = is_array($created['poll'] ?? null) ? $created['poll'] : null;
    if ($poll === null) {
        dent_error('ساخت نظرسنجی انجام نشد.', 500);
    }

    chat_save_store($store);

    $response = [
        'success' => true,
        'poll' => chat_poll_payload($store, $poll, $user, true),
        'polls' => chat_list_polls_for_user($store, $user),
        'conversations' => chat_conversation_summaries_for_user($store, $user),
    ];

    $message = $created['message'] ?? null;
    if (is_array($message)) {
        $response['message'] = chat_normalize_message_for_client($message, $store, $user);
    }

    dent_json_response($response);
}

if ($action === 'poll') {
    $user = chat_require_user();
    $pollId = chat_clean_poll_id((string) ($_GET['pollId'] ?? $_POST['pollId'] ?? $_GET['poll'] ?? $_POST['poll'] ?? ''));
    if ($pollId === '') {
        dent_error('شناسه نظرسنجی نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $poll = chat_get_poll($store, $pollId);
    if ($poll === null) {
        dent_error('نظرسنجی پیدا نشد.', 404);
    }
    if (!chat_poll_can_access($store, $poll, $user)) {
        dent_error('این نظرسنجی برای شما فعال نیست.', 403);
    }

    dent_json_response([
        'success' => true,
        'poll' => chat_poll_payload($store, $poll, $user, true),
    ]);
}

if ($action === 'votePoll') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ثبت رأی نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $pollId = chat_clean_poll_id((string) ($_POST['pollId'] ?? $_POST['poll'] ?? ''));
    if ($pollId === '') {
        dent_error('شناسه نظرسنجی نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $poll = chat_get_poll($store, $pollId);
    if ($poll === null) {
        dent_error('نظرسنجی پیدا نشد.', 404);
    }
    if (!chat_poll_can_access($store, $poll, $user)) {
        dent_error('این نظرسنجی برای شما فعال نیست.', 403);
    }

    $optionIds = chat_parse_poll_option_ids_input($_POST['optionIds'] ?? ($_POST['options'] ?? []));
    chat_apply_poll_vote($poll, $user, $optionIds);
    chat_put_poll($store, $poll);
    chat_save_store($store);

    dent_json_response([
        'success' => true,
        'poll' => chat_poll_payload($store, $poll, $user, true),
    ]);
}

if ($action === 'deletePoll') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد حذف نظرسنجی نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $pollId = chat_clean_poll_id((string) ($_POST['pollId'] ?? $_POST['poll'] ?? ''));
    if ($pollId === '') {
        dent_error('شناسه نظرسنجی نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $poll = chat_get_poll($store, $pollId);
    if ($poll === null) {
        dent_error('نظرسنجی پیدا نشد.', 404);
    }

    if (!chat_poll_can_manage($store, $poll, $user)) {
        dent_error('اجازه حذف این نظرسنجی را ندارید.', 403);
    }

    chat_delete_poll($store, $poll);
    chat_save_store($store);

    dent_json_response([
        'success' => true,
        'pollId' => $pollId,
        'polls' => chat_list_polls_for_user($store, $user),
        'activePolls' => chat_list_active_polls_for_user($store, $user),
    ]);
}

if ($action === 'closePoll') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد بستن نظرسنجی نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $pollId = chat_clean_poll_id((string) ($_POST['pollId'] ?? $_POST['poll'] ?? ''));
    if ($pollId === '') {
        dent_error('شناسه نظرسنجی نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $poll = chat_get_poll($store, $pollId);
    if ($poll === null) {
        dent_error('نظرسنجی پیدا نشد.', 404);
    }

    if (!chat_poll_can_manage($store, $poll, $user)) {
        dent_error('اجازه مدیریت این نظرسنجی را ندارید.', 403);
    }

    chat_close_poll($poll, $user);
    chat_put_poll($store, $poll);
    chat_save_store($store);

    dent_json_response([
        'success' => true,
        'poll' => chat_poll_payload($store, $poll, $user, true),
    ]);
}

if ($action === 'reopenPoll') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد بازگشایی نظرسنجی نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $pollId = chat_clean_poll_id((string) ($_POST['pollId'] ?? $_POST['poll'] ?? ''));
    if ($pollId === '') {
        dent_error('شناسه نظرسنجی نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $poll = chat_get_poll($store, $pollId);
    if ($poll === null) {
        dent_error('نظرسنجی پیدا نشد.', 404);
    }

    if (!chat_poll_can_manage($store, $poll, $user)) {
        dent_error('اجازه مدیریت این نظرسنجی را ندارید.', 403);
    }

    chat_reopen_poll($poll);
    chat_put_poll($store, $poll);
    chat_save_store($store);

    dent_json_response([
        'success' => true,
        'poll' => chat_poll_payload($store, $poll, $user, true),
    ]);
}

if ($action === 'startDirect') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد شروع گفت‌وگوی خصوصی نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $selfStudentNumber = chat_actor_student_number($user);
    $peerStudentNumber = dent_normalize_student_number((string) ($_POST['peerStudentNumber'] ?? ''));
    $temporaryConversation = chat_parse_bool($_POST['temporary'] ?? false, false);

    if ($peerStudentNumber === '' || $peerStudentNumber === $selfStudentNumber) {
        dent_error('کاربر مقصد برای گفت‌وگوی خصوصی نامعتبر است.', 422);
    }

    if (dent_get_user_record($peerStudentNumber) === null) {
        dent_error('کاربر مقصد پیدا نشد.', 404);
    }

    $store = chat_load_store();
    $conversation = chat_ensure_direct_conversation(
        $store,
        $selfStudentNumber,
        $peerStudentNumber,
        $selfStudentNumber,
        $temporaryConversation
    );
    chat_save_store($store);

    dent_json_response([
        'success' => true,
        'conversationId' => (string) ($conversation['id'] ?? ''),
        'conversation' => chat_conversation_payload($store, $conversation, $user, true),
        'conversations' => chat_conversation_summaries_for_user($store, $user),
    ]);
}

if ($action === 'createGroup') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ساخت گروه نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $title = dent_clean_text((string) ($_POST['title'] ?? ''), 80);
    $about = dent_clean_text((string) ($_POST['about'] ?? ''), 280);
    $avatarUrl = dent_clean_avatar_url((string) ($_POST['avatarUrl'] ?? ''), false);
    $members = chat_parse_members_input($_POST['members'] ?? ($_POST['membersJson'] ?? []));
    $temporaryConversation = chat_parse_bool($_POST['temporary'] ?? false, false);
    $conversationKind = chat_clean_conversation_kind((string) ($_POST['kind'] ?? $_POST['conversationKind'] ?? 'group'));

    if ($title === '') {
        dent_error('نام گروه الزامی است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_create_group_conversation(
        $store,
        $user,
        $title,
        $about,
        $members,
        $avatarUrl,
        $temporaryConversation,
        $conversationKind
    );
    chat_save_store($store);

    dent_json_response([
        'success' => true,
        'conversationId' => (string) ($conversation['id'] ?? ''),
        'conversation' => chat_conversation_payload($store, $conversation, $user, true),
        'conversations' => chat_conversation_summaries_for_user($store, $user),
    ]);
}

if ($action === 'setArchive') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد بایگانی‌کردن نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? ''));
    $archived = (string) ($_POST['archived'] ?? '1') === '1';

    if ($conversationId === '') {
        dent_error('شناسه گفتگو نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    if ((bool) ($conversation['mandatory'] ?? false)) {
        dent_error('این گفتگو قابل بایگانی نیست.', 422);
    }

    $changed = chat_set_conversation_archived_state(
        $store,
        $conversationId,
        (string) ($user['studentNumber'] ?? ''),
        $archived
    );

    if ($changed) {
        chat_save_store($store);
        $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    }

    dent_json_response([
        'success' => true,
        'archived' => $archived,
        'conversation' => chat_conversation_payload($store, $conversation, $user, false),
        'conversations' => chat_conversation_summaries_for_user($store, $user),
    ]);
}

if ($action === 'setPinConversation') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد سنجاق گفتگو نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? ''));
    $pinned = (string) ($_POST['pinned'] ?? '1') === '1';

    if ($conversationId === '') {
        dent_error('شناسه گفتگو نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    if (!chat_is_member($conversation, (string) ($user['studentNumber'] ?? ''))) {
        dent_error('اجازه سنجاق این گفتگو را ندارید.', 403);
    }

    $changed = chat_set_conversation_pinned_state(
        $store,
        $conversationId,
        (string) ($user['studentNumber'] ?? ''),
        $pinned
    );

    if ($changed) {
        chat_save_store($store);
        $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    }

    dent_json_response([
        'success' => true,
        'pinned' => $pinned,
        'conversation' => chat_conversation_payload($store, $conversation, $user, false),
        'conversations' => chat_conversation_summaries_for_user($store, $user),
    ]);
}

if ($action === 'clearHistory') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد پاک‌کردن تاریخچه نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? ''));
    if ($conversationId === '') {
        dent_error('شناسه گفتگو نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    $conversationType = (string) ($conversation['type'] ?? 'group');
    $canClearHistory = $conversationType === 'direct'
        ? chat_is_member($conversation, (string) ($user['studentNumber'] ?? ''))
        : chat_can_manage_conversation($conversation, $user);

    if (!$canClearHistory) {
        dent_error('اجازه پاک‌کردن تاریخچه این گفتگو را ندارید.', 403);
    }

    $clearedCount = chat_clear_conversation_history($store, $conversation);
    chat_save_store($store);
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);

    dent_json_response([
        'success' => true,
        'clearedCount' => $clearedCount,
        'conversation' => chat_conversation_payload($store, $conversation, $user, false),
        'conversations' => chat_conversation_summaries_for_user($store, $user),
    ]);
}

if ($action === 'leaveConversation') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد خروج از گفتگو نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? ''));
    if ($conversationId === '') {
        dent_error('شناسه گفتگو نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    if ((string) ($conversation['type'] ?? '') !== 'group') {
        dent_error('خروج فقط برای گروه‌های اختیاری پشتیبانی می‌شود.', 422);
    }
    if ((bool) ($conversation['mandatory'] ?? false)) {
        dent_error('خروج از گروه اجباری امکان‌پذیر نیست.', 422);
    }

    $left = chat_leave_group_conversation($store, $conversation, (string) ($user['studentNumber'] ?? ''));
    if (!$left) {
        dent_error('خروج از گفتگو انجام نشد.', 500);
    }
    chat_save_store($store);

    $nextConversationId = chat_pick_active_conversation_id($store, $user, '');
    $nextConversation = chat_get_conversation($store, $nextConversationId);

    dent_json_response([
        'success' => true,
        'leftConversationId' => $conversationId,
        'conversationId' => $nextConversationId,
        'conversation' => $nextConversation !== null
            ? chat_conversation_payload($store, $nextConversation, $user, false)
            : null,
        'conversations' => chat_conversation_summaries_for_user($store, $user),
    ]);
}

if ($action === 'deleteConversation') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد حذف گفتگو نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? ''));
    if ($conversationId === '') {
        dent_error('شناسه گفتگو نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    $conversationType = (string) ($conversation['type'] ?? 'group');
    $isMandatory = (bool) ($conversation['mandatory'] ?? false);
    $mode = '';

    if ($conversationType === 'direct') {
        chat_set_conversation_deleted_state(
            $store,
            $conversationId,
            (string) ($user['studentNumber'] ?? ''),
            true
        );
        $mode = 'hidden';
    } elseif ($conversationType === 'group' && !$isMandatory) {
        if (!chat_can_manage_conversation($conversation, $user)) {
            dent_error('اجازه حذف این گفتگو را ندارید.', 403);
        }
        chat_delete_conversation_record($store, $conversationId);
        $mode = 'removed';
    } else {
        dent_error('حذف این گفتگو پشتیبانی نمی‌شود.', 422);
    }

    chat_save_store($store);

    $nextConversationId = chat_pick_active_conversation_id($store, $user, '');
    $nextConversation = chat_get_conversation($store, $nextConversationId);

    dent_json_response([
        'success' => true,
        'mode' => $mode,
        'deletedConversationId' => $conversationId,
        'conversationId' => $nextConversationId,
        'conversation' => $nextConversation !== null
            ? chat_conversation_payload($store, $nextConversation, $user, false)
            : null,
        'conversations' => chat_conversation_summaries_for_user($store, $user),
    ]);
}

if ($action === 'markRead') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد به‌روزرسانی خوانده‌شدن نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? CHAT_CLASS_CONVERSATION_ID));
    if ($conversationId === '') {
        dent_error('شناسه گفت‌وگو نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);

    $targetMessageId = isset($_POST['lastReadMessageId']) ? (int) $_POST['lastReadMessageId'] : null;
    $changed = chat_mark_read(
        $store,
        $conversationId,
        (string) ($user['studentNumber'] ?? ''),
        $targetMessageId
    );

    if ($changed) {
        chat_save_store($store);
    }

    dent_json_response([
        'success' => true,
        'conversation' => chat_conversation_payload($store, $conversation, $user, false),
        'conversations' => chat_conversation_summaries_for_user($store, $user),
    ]);
}

if ($action === 'markUnread') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد به‌روزرسانی خوانده‌نشده نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? CHAT_CLASS_CONVERSATION_ID));
    if ($conversationId === '') {
        dent_error('شناسه گفت‌وگو نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    $changed = chat_mark_unread(
        $store,
        $conversationId,
        (string) ($user['studentNumber'] ?? '')
    );

    if ($changed) {
        chat_save_store($store);
    }

    dent_json_response([
        'success' => true,
        'conversation' => chat_conversation_payload($store, $conversation, $user, false),
        'conversations' => chat_conversation_summaries_for_user($store, $user),
    ]);
}

if ($action === 'messageReceipts') {
    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_GET['conversationId'] ?? $_POST['conversationId'] ?? CHAT_CLASS_CONVERSATION_ID));
    $messageId = (int) ($_GET['messageId'] ?? $_POST['messageId'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);

    if ($conversationId === '' || $messageId <= 0) {
        dent_error('شناسه پیام یا گفتگو نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    $messages = chat_get_messages($store, $conversationId);
    $index = chat_find_message_index($messages, $messageId);
    if ($index === -1) {
        dent_error('پیام پیدا نشد.', 404);
    }

    dent_json_response([
        'success' => true,
        'receipts' => chat_message_receipts_payload($store, $conversation, $messages[$index], $user),
    ]);
}

if ($action === 'messageReactions') {
    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_GET['conversationId'] ?? $_POST['conversationId'] ?? CHAT_CLASS_CONVERSATION_ID));
    $messageId = (int) ($_GET['messageId'] ?? $_POST['messageId'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);
    $emoji = chat_sanitize_emoji((string) ($_GET['emoji'] ?? $_POST['emoji'] ?? ''));

    if ($conversationId === '' || $messageId <= 0 || $emoji === '') {
        dent_error('شناسه پیام، گفتگو یا واکنش نامعتبر است.', 422);
    }

    $store = chat_load_store();
    chat_require_conversation_for_user($store, $conversationId, $user);
    $messages = chat_get_messages($store, $conversationId);
    $index = chat_find_message_index($messages, $messageId);
    if ($index === -1) {
        dent_error('پیام پیدا نشد.', 404);
    }

    $details = chat_reaction_details_payload($messages[$index], $emoji);

    dent_json_response([
        'success' => true,
        'messageId' => $messageId,
        'emoji' => $emoji,
        'reactions' => [
            $emoji => array_values($details[$emoji] ?? []),
        ],
    ]);
}

if ($action === 'setMemberTag') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تنظیم تگ عضو نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? ''));
    $memberStudentNumber = dent_normalize_student_number((string) ($_POST['memberStudentNumber'] ?? ''));
    $tag = chat_clean_member_tag((string) ($_POST['tag'] ?? ''));

    if ($conversationId === '' || $memberStudentNumber === '') {
        dent_error('شناسه گفتگو یا عضو نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    if (!chat_can_manage_conversation($conversation, $user)) {
        dent_error('اجازه مدیریت این گفتگو را ندارید.', 403);
    }
    if (!chat_is_member($conversation, $memberStudentNumber)) {
        dent_error('این کاربر عضو گفتگو نیست.', 422);
    }

    $memberTags = is_array($conversation['memberTags'] ?? null) ? $conversation['memberTags'] : [];
    if ($tag === '') {
        unset($memberTags[$memberStudentNumber]);
    } else {
        $memberTags[$memberStudentNumber] = $tag;
    }
    $conversation['memberTags'] = $memberTags;
    $conversation['updatedAt'] = time();
    chat_put_conversation($store, $conversation);
    chat_save_store($store);

    dent_json_response([
        'success' => true,
        'conversation' => chat_conversation_payload($store, $conversation, $user, true),
    ]);
}

if ($action === 'setMemberAdmin') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تنظیم مدیر نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? ''));
    $memberStudentNumber = dent_normalize_student_number((string) ($_POST['memberStudentNumber'] ?? ''));
    $admin = (string) ($_POST['admin'] ?? '1') === '1';

    if ($conversationId === '' || $memberStudentNumber === '') {
        dent_error('شناسه گفتگو یا عضو نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    if ((string) ($conversation['type'] ?? '') !== 'group') {
        dent_error('مدیریت مدیرها فقط برای گروه و کانال پشتیبانی می‌شود.', 422);
    }
    if (!chat_can_manage_conversation($conversation, $user)) {
        dent_error('اجازه مدیریت این گفتگو را ندارید.', 403);
    }
    if (!chat_is_member($conversation, $memberStudentNumber)) {
        dent_error('این کاربر عضو گفتگو نیست.', 422);
    }
    if (dent_normalize_student_number((string) ($conversation['createdBy'] ?? '')) === $memberStudentNumber && !$admin) {
        dent_error('مالک گفتگو باید مدیر بماند.', 422);
    }

    $admins = chat_normalize_student_list($conversation['admins'] ?? []);
    if ($admin && !in_array($memberStudentNumber, $admins, true)) {
        $admins[] = $memberStudentNumber;
    } elseif (!$admin) {
        $admins = array_values(array_filter(
            $admins,
            static fn($studentNumber): bool => dent_normalize_student_number((string) $studentNumber) !== $memberStudentNumber
        ));
    }
    if ($admins === []) {
        $admins[] = dent_normalize_student_number((string) ($conversation['createdBy'] ?? $user['studentNumber'] ?? ''));
    }
    sort($admins, SORT_STRING);
    $conversation['admins'] = $admins;
    $conversation['updatedAt'] = time();
    chat_put_conversation($store, $conversation);
    chat_save_store($store);

    dent_json_response([
        'success' => true,
        'conversation' => chat_conversation_payload($store, $conversation, $user, true),
    ]);
}

if ($action === 'updateConversationProfile') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ویرایش پروفایل گفتگو نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? ''));
    $title = dent_clean_text((string) ($_POST['title'] ?? ''), 80);
    $about = dent_clean_text((string) ($_POST['about'] ?? ''), 280);
    $avatarUrl = dent_clean_avatar_url((string) ($_POST['avatarUrl'] ?? ''), false);

    if ($conversationId === '') {
        dent_error('شناسه گفتگو نامعتبر است.', 422);
    }
    if ($title === '') {
        dent_error('نام گروه الزامی است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    if ((string) ($conversation['type'] ?? '') !== 'group' || (bool) ($conversation['mandatory'] ?? false)) {
        dent_error('ویرایش پروفایل فقط برای گروه‌ها و کانال‌های عادی پشتیبانی می‌شود.', 422);
    }
    if (!chat_can_manage_conversation($conversation, $user)) {
        dent_error('اجازه ویرایش پروفایل این گفتگو را ندارید.', 403);
    }

    $conversation['title'] = $title;
    $conversation['about'] = $about;
    $conversation['avatarUrl'] = $avatarUrl;
    $conversation['updatedAt'] = time();
    chat_put_conversation($store, $conversation);
    chat_save_store($store);

    dent_json_response([
        'success' => true,
        'conversation' => chat_conversation_payload($store, $conversation, $user, true),
        'conversations' => chat_conversation_summaries_for_user($store, $user),
    ]);
}

if ($action === 'setGroupVisibility') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تنظیم نوع گروه نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? ''));
    $visibility = chat_clean_group_visibility((string) ($_POST['visibility'] ?? 'private'));

    if ($conversationId === '') {
        dent_error('شناسه گفتگو نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    if ((string) ($conversation['type'] ?? '') !== 'group' || (bool) ($conversation['mandatory'] ?? false)) {
        dent_error('تنظیم نوع فقط برای گروه‌ها و کانال‌های عادی پشتیبانی می‌شود.', 422);
    }
    if (!chat_can_manage_conversation($conversation, $user)) {
        dent_error('اجازه مدیریت این گفتگو را ندارید.', 403);
    }

    $settings = chat_default_settings(is_array($conversation['settings'] ?? null) ? $conversation['settings'] : []);
    $settings['visibility'] = $visibility;
    $conversation['settings'] = $settings;
    $conversation['updatedAt'] = time();
    chat_put_conversation($store, $conversation);
    chat_save_store($store);

    dent_json_response([
        'success' => true,
        'state' => $settings,
        'conversation' => chat_conversation_payload($store, $conversation, $user, true),
        'conversations' => chat_conversation_summaries_for_user($store, $user),
    ]);
}

if ($action === 'setReactionMode') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تنظیم واکنش نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? ''));
    $reactionMode = chat_clean_reaction_mode((string) ($_POST['reactionMode'] ?? 'all'));

    if ($conversationId === '') {
        dent_error('شناسه گفتگو نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    if ((string) ($conversation['type'] ?? '') === 'direct') {
        dent_error('تنظیم واکنش برای گفتگوی خصوصی لازم نیست.', 422);
    }
    if (!chat_can_manage_conversation($conversation, $user)) {
        dent_error('اجازه مدیریت واکنش‌های این گفتگو را ندارید.', 403);
    }

    $settings = chat_default_settings(is_array($conversation['settings'] ?? null) ? $conversation['settings'] : []);
    $settings['reactionMode'] = $reactionMode;
    $conversation['settings'] = $settings;
    $conversation['updatedAt'] = time();
    chat_put_conversation($store, $conversation);
    chat_save_store($store);

    dent_json_response([
        'success' => true,
        'state' => $settings,
        'conversation' => chat_conversation_payload($store, $conversation, $user, true),
        'conversations' => chat_conversation_summaries_for_user($store, $user),
    ]);
}

if ($action === 'addMembers') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد افزودن عضو نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? ''));
    $members = chat_parse_members_input($_POST['members'] ?? ($_POST['membersJson'] ?? []));

    if ($conversationId === '') {
        dent_error('شناسه گفتگو نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    if ((string) ($conversation['type'] ?? '') !== 'group') {
        dent_error('افزودن عضو فقط برای گروه و کانال پشتیبانی می‌شود.', 422);
    }
    if (!chat_can_manage_conversation($conversation, $user)) {
        dent_error('اجازه افزودن عضو به این گفتگو را ندارید.', 403);
    }

    $memberList = array_fill_keys(chat_normalize_student_list($conversation['memberStudentNumbers'] ?? []), true);
    $added = 0;
    foreach ($members as $member) {
        $studentNumber = dent_normalize_student_number((string) $member);
        if ($studentNumber === '' || dent_get_user_record($studentNumber) === null || isset($memberList[$studentNumber])) {
            continue;
        }
        $memberList[$studentNumber] = true;
        $added++;
    }

    if ($added <= 0) {
        dent_error('عضو جدیدی برای افزودن انتخاب نشده است.', 422);
    }

    $nextMembers = array_keys($memberList);
    sort($nextMembers, SORT_STRING);
    $conversation['memberStudentNumbers'] = $nextMembers;
    $conversation['updatedAt'] = time();
    chat_put_conversation($store, $conversation);
    chat_save_store($store);

    dent_json_response([
        'success' => true,
        'addedCount' => $added,
        'conversation' => chat_conversation_payload($store, $conversation, $user, true),
        'conversations' => chat_conversation_summaries_for_user($store, $user),
    ]);
}

if ($action === 'presence') {
    $user = chat_require_user();
    $store = chat_read_store_snapshot(true);
    $requestedConversationId = chat_clean_conversation_id((string) ($_GET['conversationId'] ?? $_POST['conversationId'] ?? ''));
    $includeMembers = (string) ($_GET['includeMembers'] ?? $_POST['includeMembers'] ?? '0') === '1';
    $activeConversationId = '';
    if ($requestedConversationId !== '') {
        $conversation = chat_get_conversation($store, $requestedConversationId);
        $studentNumber = chat_actor_student_number($user);
        if (
            $conversation !== null
            && (chat_is_member($conversation, $studentNumber) || chat_user_can_view_without_membership($conversation, $user))
            && (
                (bool) ($conversation['mandatory'] ?? false)
                || !chat_is_conversation_deleted_for_user($store, $requestedConversationId, $studentNumber)
            )
        ) {
            $activeConversationId = $requestedConversationId;
        }
    }

    dent_json_response([
        'success' => true,
        'presence' => chat_presence_bundle_payload($store, $user, $activeConversationId, $includeMembers, chat_read_presence_snapshot(true)),
    ]);
}

if ($action === 'presencePing') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد بروزرسانی وضعیت حضور نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $studentNumber = chat_actor_student_number($user);
    $requestedConversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? ''));
    $typing = chat_parse_bool($_POST['typing'] ?? false, false);
    $activity = dent_clean_text((string) ($_POST['activity'] ?? 'active'), 20);
    if (!in_array($activity, ['active', 'typing', 'idle'], true)) {
        $activity = 'active';
    }

    $validatedConversationId = '';
    if ($requestedConversationId !== '') {
        $snapshotStore = chat_read_store_snapshot(true);
        $conversation = chat_get_conversation($snapshotStore, $requestedConversationId);
        if (
            $conversation !== null
            && (chat_is_member($conversation, $studentNumber) || chat_user_can_view_without_membership($conversation, $user))
            && (
                (bool) ($conversation['mandatory'] ?? false)
                || !chat_is_conversation_deleted_for_user($snapshotStore, $requestedConversationId, $studentNumber)
            )
        ) {
            $validatedConversationId = $requestedConversationId;
            if ($typing && !chat_can_send_message($conversation, $user)) {
                $typing = false;
            }
        } else {
            $typing = false;
        }
    } else {
        $typing = false;
    }

    $presenceStore = chat_load_presence_store();
    $entry = chat_presence_entry_for_student($presenceStore, $studentNumber);
    $now = time();
    $entry['lastSeenAt'] = $now;
    if ($activity !== 'idle' || $typing) {
        $entry['lastActiveAt'] = $now;
    }
    $entry['lastHeartbeatAt'] = $now;
    $entry['activeConversationId'] = $validatedConversationId;
    if ($typing && $validatedConversationId !== '') {
        $entry['typingConversationId'] = $validatedConversationId;
        $entry['typingExpiresAt'] = $now + CHAT_PRESENCE_TYPING_WINDOW_SECONDS;
    } else {
        $entry['typingConversationId'] = '';
        $entry['typingExpiresAt'] = null;
    }

    if (!isset($presenceStore['users']) || !is_array($presenceStore['users'])) {
        $presenceStore['users'] = [];
    }
    $presenceStore['users'][$studentNumber] = $entry;
    $presenceStore['updatedAt'] = $now;
    chat_save_presence_store($presenceStore);

    dent_json_response([
        'success' => true,
        'presence' => chat_presence_state_payload($entry, $validatedConversationId, $now),
    ]);
}

if ($action === 'stream') {
    $user = chat_require_user();
    $store = chat_read_store_snapshot(true);
    $requestedConversationId = chat_clean_conversation_id((string) ($_GET['conversationId'] ?? ''));
    $includeMembers = (string) ($_GET['includeMembers'] ?? '0') === '1';
    $activeConversationId = '';
    if ($requestedConversationId !== '') {
        $conversation = chat_get_conversation($store, $requestedConversationId);
        $studentNumber = chat_actor_student_number($user);
        if (
            $conversation !== null
            && (chat_is_member($conversation, $studentNumber) || chat_user_can_view_without_membership($conversation, $user))
            && (
                (bool) ($conversation['mandatory'] ?? false)
                || !chat_is_conversation_deleted_for_user($store, $requestedConversationId, $studentNumber)
            )
        ) {
            $activeConversationId = $requestedConversationId;
        }
    }

    chat_release_store_lock();
    chat_release_presence_lock();

    ignore_user_abort(true);
    @set_time_limit(0);
    header('Content-Type: text/event-stream; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Accel-Buffering: no');

    $sendEvent = static function (string $eventName, array $payload): void {
        echo 'event: ' . $eventName . "\n";
        echo 'data: ' . (json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') . "\n\n";
    };

    $flushStream = static function (): void {
        @ob_flush();
        @flush();
    };

    $lastStoreSignature = chat_store_file_signature();
    $lastPresenceSignature = chat_presence_file_signature();
    $sendEvent('hello', [
        'mode' => 'sse',
        'storeVersion' => $lastStoreSignature,
        'presenceVersion' => $lastPresenceSignature,
        'activeConversationId' => $activeConversationId,
    ]);
    $sendEvent('presence', chat_presence_bundle_payload(
        chat_read_store_snapshot(true),
        $user,
        $activeConversationId,
        $includeMembers,
        chat_read_presence_snapshot(true)
    ));
    $flushStream();

    $startedAt = time();
    $lastKeepaliveAt = $startedAt;
    while (!connection_aborted() && (time() - $startedAt) < CHAT_STREAM_MAX_DURATION_SECONDS) {
        usleep(CHAT_STREAM_POLL_INTERVAL_MICROSECONDS);

        $storeSignature = chat_store_file_signature();
        if ($storeSignature !== $lastStoreSignature) {
            $lastStoreSignature = $storeSignature;
            $sendEvent('sync', [
                'storeVersion' => $storeSignature,
                'activeConversationId' => $activeConversationId,
            ]);
            $flushStream();
        }

        $presenceSignature = chat_presence_file_signature();
        if ($presenceSignature !== $lastPresenceSignature) {
            $lastPresenceSignature = $presenceSignature;
            $sendEvent('presence', chat_presence_bundle_payload(
                chat_read_store_snapshot(true),
                $user,
                $activeConversationId,
                $includeMembers,
                chat_read_presence_snapshot(true)
            ));
            $flushStream();
        }

        if ((time() - $lastKeepaliveAt) >= CHAT_STREAM_KEEPALIVE_SECONDS) {
            echo ": keepalive\n\n";
            $flushStream();
            $lastKeepaliveAt = time();
        }
    }

    exit;
}

if ($action === 'navSummary') {
    $user = chat_require_user();
    dent_release_session_lock();
    $store = chat_load_store();
    $conversations = chat_conversation_summaries_for_user($store, $user);
    $unreadCount = 0;
    $activeCount = 0;
    foreach ($conversations as $conversation) {
        if (!is_array($conversation)) {
            continue;
        }
        $viewerState = is_array($conversation['viewerState'] ?? null) ? $conversation['viewerState'] : [];
        if (!empty($viewerState['archived'])) {
            continue;
        }
        $activeCount++;
        $unreadCount += max(0, (int) ($conversation['unreadCount'] ?? 0));
    }

    dent_json_response([
        'success' => true,
        'summary' => [
            'unreadCount' => $unreadCount,
            'activeConversationCount' => $activeCount,
        ],
    ]);
}

if ($action === 'sync' || $action === 'fetch') {
    $user = chat_require_user();
    $store = chat_load_store();

    $requestedConversationId = chat_clean_conversation_id((string) ($_GET['conversationId'] ?? $_POST['conversationId'] ?? ''));
    if ($requestedConversationId !== '') {
        $activeConversationId = $requestedConversationId;
        $conversation = chat_require_conversation_for_user($store, $activeConversationId, $user);
        $studentNumber = chat_actor_student_number($user);
        if (
            !(bool) ($conversation['mandatory'] ?? false)
            && chat_is_conversation_deleted_for_user($store, $activeConversationId, $studentNumber)
        ) {
            dent_error('Conversation is not active in your list.', 404);
        }
    } else {
        $activeConversationId = chat_pick_active_conversation_id($store, $user, '');
        $conversation = chat_require_conversation_for_user($store, $activeConversationId, $user);
    }

    $sinceId = (int) ($_GET['sinceId'] ?? $_POST['sinceId'] ?? 0);
    $full = (string) ($_GET['full'] ?? $_POST['full'] ?? '0') === '1' || $sinceId <= 0;
    $includeMembers = (string) ($_GET['includeMembers'] ?? $_POST['includeMembers'] ?? '1') !== '0';
    $beforeId = max(0, (int) ($_GET['beforeId'] ?? $_POST['beforeId'] ?? 0));
    $limit = max(0, min(200, (int) ($_GET['limit'] ?? $_POST['limit'] ?? 0)));
    $clientConversationListVersion = trim((string) ($_GET['conversationListVersion'] ?? $_POST['conversationListVersion'] ?? ''));

    $allMessages = chat_get_messages($store, $activeConversationId);
    $messages = [];
    $hasMoreBefore = false;
    if ($beforeId > 0) {
        $olderMessages = [];
        foreach ($allMessages as $message) {
            if ((int) ($message['id'] ?? 0) < $beforeId) {
                $olderMessages[] = $message;
            }
        }
        if ($limit > 0 && count($olderMessages) > $limit) {
            $hasMoreBefore = true;
            $messages = array_slice($olderMessages, -$limit);
        } else {
            $messages = $olderMessages;
        }
    } elseif ($full) {
        if ($limit > 0 && count($allMessages) > $limit) {
            $hasMoreBefore = true;
            $messages = array_slice($allMessages, -$limit);
        } else {
            $messages = $allMessages;
        }
    } else {
        foreach ($allMessages as $message) {
            if ((int) ($message['id'] ?? 0) > $sinceId) {
                $messages[] = $message;
            }
        }
    }
    $oldestMessageId = null;
    if ($messages !== []) {
        $firstMessage = $messages[0];
        $oldestMessageId = (int) ($firstMessage['id'] ?? 0);
    }

    $lastMessage = chat_last_message($allMessages);

    $conversationListVersion = chat_conversation_list_version_for_user($store, $user);
    $includeConversationList = $full || $clientConversationListVersion === '' || $clientConversationListVersion !== $conversationListVersion;
    $presenceBundle = chat_presence_bundle_payload($store, $user, $activeConversationId, $includeMembers);

    $response = [
        'success' => true,
        'conversationId' => $activeConversationId,
        'conversation' => chat_conversation_payload($store, $conversation, $user, $includeMembers),
        'conversationListVersion' => $conversationListVersion,
        'messages' => chat_normalize_messages_for_client($messages, $store, $user),
        'messagePage' => [
            'limit' => $limit,
            'beforeId' => $beforeId,
            'oldestMessageId' => $oldestMessageId,
            'hasMoreBefore' => $hasMoreBefore,
            'returnedCount' => count($messages),
            'totalCount' => count($allMessages),
        ],
        'state' => is_array($conversation['settings'] ?? null)
            ? chat_default_settings($conversation['settings'])
            : chat_default_settings(),
        'presence' => $presenceBundle,
        'transport' => [
            'mode' => 'sse',
            'intervalMs' => 0,
            'fallbackIntervalMs' => 5000,
            'streamUrl' => chat_stream_url(),
            'presenceUrl' => chat_presence_url(),
        ],
        'limitations' => [
            'realtime' => true,
            'presence' => true,
            'deliveryReceipts' => false,
            'storage' => 'json-file',
        ],
    ];

    if ($includeConversationList) {
        $response['conversations'] = chat_conversation_summaries_for_user($store, $user);
    }

    dent_json_response($response);
}

if ($action === 'send') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ارسال پیام نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? CHAT_CLASS_CONVERSATION_ID));
    if ($conversationId === '') {
        dent_error('شناسه گفت‌وگو نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $text = chat_sanitize_message_text((string) ($_POST['text'] ?? ''));
    $replyTo = isset($_POST['replyTo']) ? (int) $_POST['replyTo'] : null;
    $kind = trim((string) ($_POST['kind'] ?? ''));
    if (!in_array($kind, ['text', 'attachment', 'voice'], true)) {
        $kind = 'text';
    }
    $attachmentIds = chat_parse_attachment_ids_input($_POST['attachmentIds'] ?? []);
    $message = chat_append_message($store, $conversationId, $user, $text, $replyTo, $kind, '', $attachmentIds);
    chat_save_store($store);

    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    dent_json_response([
        'success' => true,
        'message' => chat_normalize_message_for_client($message, $store, $user),
        'conversation' => chat_conversation_payload($store, $conversation, $user, false),
    ]);
}

if ($action === 'edit') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ویرایش پیام نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? CHAT_CLASS_CONVERSATION_ID));
    $messageId = (int) ($_POST['id'] ?? 0);
    $text = chat_sanitize_message_text((string) ($_POST['text'] ?? ''));

    if ($conversationId === '') {
        dent_error('شناسه گفت‌وگو نامعتبر است.', 422);
    }
    if ($messageId <= 0) {
        dent_error('شناسه پیام نامعتبر است.', 422);
    }
    if ($text === '') {
        dent_error('متن پیام خالی است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    $messages = chat_get_messages($store, $conversationId);
    $index = chat_find_message_index($messages, $messageId);
    if ($index === -1) {
        dent_error('پیام پیدا نشد.', 404);
    }

    if (!chat_can_manage_message($conversation, $user, $messages[$index])) {
        dent_error('اجازه ویرایش این پیام را ندارید.', 403);
    }

    $messages[$index]['text'] = $text;
    $messages[$index]['editedAt'] = time();
    chat_put_messages($store, $conversationId, $messages);
    chat_save_store($store);

    dent_json_response([
        'success' => true,
        'message' => chat_normalize_message_for_client($messages[$index], $store, $user),
    ]);
}

if ($action === 'delete') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد حذف پیام نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? CHAT_CLASS_CONVERSATION_ID));
    $messageId = (int) ($_POST['id'] ?? 0);

    if ($conversationId === '') {
        dent_error('شناسه گفت‌وگو نامعتبر است.', 422);
    }
    if ($messageId <= 0) {
        dent_error('شناسه پیام نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    $messages = chat_get_messages($store, $conversationId);
    $index = chat_find_message_index($messages, $messageId);
    if ($index === -1) {
        dent_error('پیام پیدا نشد.', 404);
    }

    if (!chat_can_manage_message($conversation, $user, $messages[$index])) {
        dent_error('اجازه حذف این پیام را ندارید.', 403);
    }

    chat_delete_message_attachments($store, $conversationId, $messageId);
    array_splice($messages, $index, 1);
    chat_put_messages($store, $conversationId, $messages);
    chat_save_store($store);

    dent_json_response([
        'success' => true,
        'deletedId' => $messageId,
    ]);
}

if ($action === 'pin') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد سنجاق پیام نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? CHAT_CLASS_CONVERSATION_ID));
    $messageId = (int) ($_POST['id'] ?? 0);
    $shouldPin = (string) ($_POST['pinned'] ?? '1') === '1';

    if ($conversationId === '') {
        dent_error('شناسه گفت‌وگو نامعتبر است.', 422);
    }
    if ($messageId <= 0) {
        dent_error('شناسه پیام نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    if (!chat_can_pin_message($conversation, $user)) {
        dent_error('اجازه سنجاق این گفتگو را ندارید.', 403);
    }

    $messages = chat_get_messages($store, $conversationId);
    $index = chat_find_message_index($messages, $messageId);
    if ($index === -1) {
        dent_error('پیام پیدا نشد.', 404);
    }

    $messages[$index]['pinned'] = $shouldPin;
    chat_put_messages($store, $conversationId, $messages);
    chat_save_store($store);

    dent_json_response([
        'success' => true,
        'message' => chat_normalize_message_for_client($messages[$index], $store, $user),
    ]);
}

if ($action === 'react') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد واکنش نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? CHAT_CLASS_CONVERSATION_ID));
    $messageId = (int) ($_POST['messageId'] ?? ($_POST['id'] ?? 0));
    $emoji = chat_sanitize_emoji((string) ($_POST['emoji'] ?? ''));

    if ($conversationId === '') {
        dent_error('شناسه گفت‌وگو نامعتبر است.', 422);
    }
    if ($messageId <= 0) {
        dent_error('شناسه پیام نامعتبر است.', 422);
    }
    if ($emoji === '') {
        dent_error('واکنش انتخاب‌شده معتبر نیست.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    $messages = chat_get_messages($store, $conversationId);
    $index = chat_find_message_index($messages, $messageId);
    if ($index === -1) {
        dent_error('پیام پیدا نشد.', 404);
    }

    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $reactions = chat_sanitize_reactions($messages[$index]['reactions'] ?? []);
    $reactionMeta = chat_sanitize_reaction_meta($messages[$index]['reactionMeta'] ?? [], $reactions);
    $emojiUsers = isset($reactions[$emoji]) && is_array($reactions[$emoji]) ? $reactions[$emoji] : [];

    if (in_array($studentNumber, $emojiUsers, true)) {
        $emojiUsers = array_values(array_filter(
            $emojiUsers,
            static fn($value): bool => dent_normalize_student_number((string) $value) !== $studentNumber
        ));
        if (isset($reactionMeta[$emoji][$studentNumber])) {
            unset($reactionMeta[$emoji][$studentNumber]);
        }
    } else {
        $emojiUsers[] = $studentNumber;
        sort($emojiUsers, SORT_STRING);
        if (!isset($reactionMeta[$emoji]) || !is_array($reactionMeta[$emoji])) {
            $reactionMeta[$emoji] = [];
        }
        $reactionMeta[$emoji][$studentNumber] = time();
    }

    if ($emojiUsers === []) {
        unset($reactions[$emoji]);
        unset($reactionMeta[$emoji]);
    } else {
        $reactions[$emoji] = $emojiUsers;
        $reactionMeta[$emoji] = chat_sanitize_reaction_meta([$emoji => $reactionMeta[$emoji] ?? []], [$emoji => $emojiUsers])[$emoji] ?? [];
    }

    $messages[$index]['reactions'] = $reactions;
    $messages[$index]['reactionMeta'] = chat_sanitize_reaction_meta($reactionMeta, $reactions);
    chat_put_messages($store, $conversationId, $messages);
    chat_save_store($store);

    dent_json_response([
        'success' => true,
        'message' => chat_normalize_message_for_client($messages[$index], $store, $user),
    ]);
}

if ($action === 'setNotificationMute') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تنظیم اعلان نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? CHAT_CLASS_CONVERSATION_ID));
    $muted = (string) ($_POST['muted'] ?? '0') === '1';

    if ($conversationId === '') {
        dent_error('شناسه گفتگو نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    $studentNumber = chat_actor_student_number($user);
    if (!chat_is_member($conversation, $studentNumber)) {
        dent_error('عضو این گفتگو نیستید.', 403);
    }

    $changed = chat_set_notification_mute_state($store, $conversationId, $studentNumber, $muted);
    if ($changed) {
        chat_save_store($store);
    }

    dent_json_response([
        'success' => true,
        'conversation' => chat_conversation_payload($store, $conversation, $user, false),
        'conversations' => chat_conversation_summaries_for_user($store, $user),
    ]);
}

if ($action === 'setMute') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد مدیریت ارسال نامعتبر است.', 405);
    }

    $user = chat_require_user();
    $conversationId = chat_clean_conversation_id((string) ($_POST['conversationId'] ?? CHAT_CLASS_CONVERSATION_ID));
    $muted = (string) ($_POST['muted'] ?? '0') === '1';

    if ($conversationId === '') {
        dent_error('شناسه گفت‌وگو نامعتبر است.', 422);
    }

    $store = chat_load_store();
    $conversation = chat_require_conversation_for_user($store, $conversationId, $user);
    if ((string) ($conversation['type'] ?? '') === 'direct') {
        dent_error('بستن ارسال پیام برای گفت‌وگوی خصوصی پشتیبانی نمی‌شود.', 422);
    }

    if (!chat_can_manage_conversation($conversation, $user)) {
        dent_error('اجازه مدیریت این گفتگو را ندارید.', 403);
    }

    $settings = chat_default_settings(is_array($conversation['settings'] ?? null) ? $conversation['settings'] : []);
    $settings['muted'] = $muted;
    $settings['mutedBy'] = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $settings['mutedAt'] = time();
    $conversation['settings'] = $settings;

    chat_put_conversation($store, $conversation);
    chat_save_store($store);

    dent_json_response([
        'success' => true,
        'state' => $settings,
        'conversation' => chat_conversation_payload($store, $conversation, $user, false),
    ]);
}

dent_error('درخواست نامعتبر است.', 404);
