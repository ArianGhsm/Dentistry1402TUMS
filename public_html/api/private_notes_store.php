<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';

const PRIVATE_NOTES_SCHEMA_VERSION = 1;

const PRIVATE_NOTES_MEMBERSHIP_ROLES = ['writer', 'editor', 'manager'];
const PRIVATE_NOTES_CONTRIBUTION_STATUSES = ['pending', 'approved', 'paused', 'removed'];
const PRIVATE_NOTES_ACCESS_STATUSES = ['active', 'warning', 'suspended', 'revoked'];
const PRIVATE_NOTES_DOCUMENT_PROCESSING_STATUSES = ['pending', 'processing', 'ready', 'failed'];
const PRIVATE_NOTES_DOCUMENT_PUBLICATION_STATUSES = ['draft', 'private', 'published', 'archived'];
const PRIVATE_NOTES_PERMISSION_STATUSES = ['active', 'warning', 'suspended', 'revoked'];
const PRIVATE_NOTES_SUSPENSION_STATUSES = ['active', 'expired', 'resolved'];
const PRIVATE_NOTES_SECURITY_EVENT_STATUSES = ['open', 'reviewing', 'resolved', 'dismissed'];

function private_notes_store_path(): string
{
    return dent_storage_path('private_notes/store.json');
}

function private_notes_lock_path(): string
{
    return dent_storage_path('private_notes/store.lock');
}

function private_notes_originals_dir(): string
{
    return dent_storage_path('private_notes/originals');
}

function private_notes_pages_dir(): string
{
    return dent_storage_path('private_notes/pages');
}

function private_notes_default_store(): array
{
    return [
        'schemaVersion' => PRIVATE_NOTES_SCHEMA_VERSION,
        'semesters' => [],
        'courses' => [],
        'documents' => [],
        'courseMemberships' => [],
        'documentPermissions' => [],
        'processingJobs' => [],
        'registeredDevices' => [],
        'activeViewingSessions' => [],
        'documentViewEvents' => [],
        'securityEvents' => [],
        'temporarySuspensions' => [],
    ];
}

function private_notes_ensure_storage(): void
{
    dent_ensure_directory(dirname(private_notes_store_path()));
    dent_ensure_directory(private_notes_originals_dir());
    dent_ensure_directory(private_notes_pages_dir());
    if (!is_file(private_notes_store_path())) {
        dent_write_json_file(private_notes_store_path(), private_notes_default_store());
    }
}

function private_notes_read_store(): array
{
    private_notes_ensure_storage();
    $lock = @fopen(private_notes_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('Private notes store lock is not available.', 500);
    }

    try {
        if (!@flock($lock, LOCK_SH)) {
            dent_error('Private notes store read lock failed.', 500);
        }
        return private_notes_load_store_unlocked();
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function private_notes_load_store_unlocked(): array
{
    private_notes_ensure_storage();
    $raw = dent_read_json_file(private_notes_store_path(), private_notes_default_store());
    if (!is_array($raw)) {
        $raw = private_notes_default_store();
    }
    return private_notes_normalize_store($raw);
}

/**
 * @template T
 * @param callable(array):T $callback
 * @return T
 */
function private_notes_with_store_lock(callable $callback)
{
    private_notes_ensure_storage();
    $lock = @fopen(private_notes_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('Private notes store lock is not available.', 500);
    }

    try {
        if (!@flock($lock, LOCK_EX)) {
            dent_error('Private notes store write lock failed.', 500);
        }
        $store = private_notes_load_store_unlocked();
        $result = $callback($store);
        dent_write_json_file(private_notes_store_path(), private_notes_normalize_store($store));
        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function private_notes_normalize_store(array $store): array
{
    return [
        'schemaVersion' => PRIVATE_NOTES_SCHEMA_VERSION,
        'semesters' => private_notes_normalize_collection($store['semesters'] ?? [], 'private_notes_normalize_semester'),
        'courses' => private_notes_normalize_collection($store['courses'] ?? [], 'private_notes_normalize_course'),
        'documents' => private_notes_normalize_collection($store['documents'] ?? [], 'private_notes_normalize_document'),
        'courseMemberships' => private_notes_normalize_collection($store['courseMemberships'] ?? [], 'private_notes_normalize_course_membership'),
        'documentPermissions' => private_notes_normalize_collection($store['documentPermissions'] ?? [], 'private_notes_normalize_document_permission'),
        'processingJobs' => private_notes_normalize_collection($store['processingJobs'] ?? [], 'private_notes_normalize_processing_job'),
        'registeredDevices' => private_notes_normalize_collection($store['registeredDevices'] ?? [], 'private_notes_normalize_registered_device'),
        'activeViewingSessions' => private_notes_normalize_collection($store['activeViewingSessions'] ?? [], 'private_notes_normalize_viewing_session'),
        'documentViewEvents' => private_notes_normalize_collection($store['documentViewEvents'] ?? [], 'private_notes_normalize_view_event'),
        'securityEvents' => private_notes_normalize_collection($store['securityEvents'] ?? [], 'private_notes_normalize_security_event'),
        'temporarySuspensions' => private_notes_normalize_collection($store['temporarySuspensions'] ?? [], 'private_notes_normalize_temporary_suspension'),
    ];
}

function private_notes_normalize_collection($source, callable $normalizer): array
{
    $items = [];
    foreach (is_array($source) ? $source : [] as $key => $value) {
        if (!is_array($value)) {
            continue;
        }
        $record = $normalizer((string) $key, $value);
        if (!is_array($record)) {
            continue;
        }
        $items[(string) $record['id']] = $record;
    }
    ksort($items, SORT_STRING);
    return $items;
}

function private_notes_clean_id(string $value, string $prefix): string
{
    $value = trim(strtolower($value));
    if ($value === '' || !str_starts_with($value, $prefix)) {
        return '';
    }
    return preg_match('/^[a-z0-9_-]{6,96}$/', $value) === 1 ? $value : '';
}

function private_notes_next_id(string $prefix): string
{
    try {
        return $prefix . bin2hex(random_bytes(8));
    } catch (Throwable $error) {
        return $prefix . strtolower(str_replace('.', '', uniqid('', true)));
    }
}

function private_notes_clean_status($value, array $allowed, string $fallback): string
{
    $status = trim(strtolower((string) $value));
    return in_array($status, $allowed, true) ? $status : $fallback;
}

function private_notes_clean_text_field($value, int $max = 160): string
{
    return dent_clean_text((string) $value, $max);
}

function private_notes_clean_iso_datetime($value): string
{
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }
    $timestamp = strtotime($text);
    if ($timestamp === false) {
        return '';
    }
    return date('c', $timestamp);
}

function private_notes_normalize_page_count($value): int
{
    return max(0, (int) dent_normalize_digits((string) $value));
}

function private_notes_user_key(array $user): string
{
    return dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
}

function private_notes_now(?string $now = null): string
{
    $clean = private_notes_clean_iso_datetime($now ?? '');
    return $clean !== '' ? $clean : dent_iso_now();
}

function private_notes_is_in_date_window(string $startsAt, string $expiresAt, ?string $now = null): bool
{
    $current = strtotime(private_notes_now($now));
    if ($current === false) {
        $current = time();
    }
    if ($startsAt !== '') {
        $start = strtotime($startsAt);
        if ($start !== false && $current < $start) {
            return false;
        }
    }
    if ($expiresAt !== '') {
        $end = strtotime($expiresAt);
        if ($end !== false && $current > $end) {
            return false;
        }
    }
    return true;
}

function private_notes_normalize_semester(string $key, array $semester): ?array
{
    $id = private_notes_clean_id((string) ($semester['id'] ?? $key), 'pnsem-');
    if ($id === '') {
        return null;
    }
    $cohortKey = dent_clean_cohort_key((string) ($semester['cohortKey'] ?? dent_primary_cohort_key()));
    return [
        'id' => $id,
        'cohortKey' => $cohortKey !== '' ? $cohortKey : dent_primary_cohort_key(),
        'title' => private_notes_clean_text_field($semester['title'] ?? '', 140),
        'academicYear' => private_notes_clean_text_field($semester['academicYear'] ?? '', 40),
        'termNumber' => max(0, (int) dent_normalize_digits((string) ($semester['termNumber'] ?? '0'))),
        'startsAt' => private_notes_clean_iso_datetime($semester['startsAt'] ?? ''),
        'expiresAt' => private_notes_clean_iso_datetime($semester['expiresAt'] ?? ''),
        'createdAt' => private_notes_clean_iso_datetime($semester['createdAt'] ?? '') ?: dent_iso_now(),
        'updatedAt' => private_notes_clean_iso_datetime($semester['updatedAt'] ?? '') ?: dent_iso_now(),
    ];
}

function private_notes_normalize_course(string $key, array $course): ?array
{
    $id = private_notes_clean_id((string) ($course['id'] ?? $key), 'pncrs-');
    if ($id === '') {
        return null;
    }
    $cohortKey = dent_clean_cohort_key((string) ($course['cohortKey'] ?? dent_primary_cohort_key()));
    return [
        'id' => $id,
        'cohortKey' => $cohortKey !== '' ? $cohortKey : dent_primary_cohort_key(),
        'semesterId' => private_notes_clean_id((string) ($course['semesterId'] ?? ''), 'pnsem-'),
        'title' => private_notes_clean_text_field($course['title'] ?? '', 160),
        'slug' => private_notes_clean_text_field($course['slug'] ?? '', 120),
        'createdAt' => private_notes_clean_iso_datetime($course['createdAt'] ?? '') ?: dent_iso_now(),
        'updatedAt' => private_notes_clean_iso_datetime($course['updatedAt'] ?? '') ?: dent_iso_now(),
    ];
}

function private_notes_normalize_document(string $key, array $document): ?array
{
    $id = private_notes_clean_id((string) ($document['id'] ?? $key), 'pndoc-');
    if ($id === '') {
        return null;
    }
    $cohortKey = dent_clean_cohort_key((string) ($document['cohortKey'] ?? dent_primary_cohort_key()));
    return [
        'id' => $id,
        'cohortKey' => $cohortKey !== '' ? $cohortKey : dent_primary_cohort_key(),
        'title' => private_notes_clean_text_field($document['title'] ?? '', 180),
        'courseId' => private_notes_clean_id((string) ($document['courseId'] ?? ''), 'pncrs-'),
        'semesterId' => private_notes_clean_id((string) ($document['semesterId'] ?? ''), 'pnsem-'),
        'originalFileRef' => private_notes_clean_original_file_ref((string) ($document['originalFileRef'] ?? '')),
        'originalStorageKey' => private_notes_clean_original_file_ref((string) ($document['originalStorageKey'] ?? '')),
        'originalFilename' => private_notes_clean_text_field($document['originalFilename'] ?? '', 180),
        'originalMimeType' => private_notes_clean_text_field($document['originalMimeType'] ?? '', 80),
        'originalSizeBytes' => max(0, (int) ($document['originalSizeBytes'] ?? 0)),
        'originalSha256' => private_notes_clean_hash((string) ($document['originalSha256'] ?? '')),
        'processingStatus' => private_notes_clean_status($document['processingStatus'] ?? 'pending', PRIVATE_NOTES_DOCUMENT_PROCESSING_STATUSES, 'pending'),
        'processingError' => private_notes_clean_text_field($document['processingError'] ?? '', 1000),
        'processingAttempts' => max(0, (int) ($document['processingAttempts'] ?? 0)),
        'processingStartedAt' => private_notes_clean_iso_datetime($document['processingStartedAt'] ?? ''),
        'processingCompletedAt' => private_notes_clean_iso_datetime($document['processingCompletedAt'] ?? ''),
        'pageCount' => private_notes_normalize_page_count($document['pageCount'] ?? 0),
        'renderProfile' => private_notes_normalize_render_profile($document['renderProfile'] ?? []),
        'pages' => private_notes_normalize_document_pages($document['pages'] ?? []),
        'assetsStorageKey' => private_notes_clean_original_file_ref((string) ($document['assetsStorageKey'] ?? '')),
        'uploaderUserKey' => dent_normalize_student_number((string) ($document['uploaderUserKey'] ?? '')),
        'publicationStatus' => private_notes_clean_status($document['publicationStatus'] ?? 'draft', PRIVATE_NOTES_DOCUMENT_PUBLICATION_STATUSES, 'draft'),
        'createdAt' => private_notes_clean_iso_datetime($document['createdAt'] ?? '') ?: dent_iso_now(),
        'updatedAt' => private_notes_clean_iso_datetime($document['updatedAt'] ?? '') ?: dent_iso_now(),
    ];
}

function private_notes_clean_original_file_ref(string $value): string
{
    $value = trim(str_replace('\\', '/', $value));
    if ($value === '' || str_starts_with($value, '/') || str_contains($value, '../') || str_contains($value, '..\\')) {
        return '';
    }
    return preg_match('/^[A-Za-z0-9._\/-]{1,220}$/', $value) === 1 ? $value : '';
}

function private_notes_clean_hash(string $value): string
{
    $value = trim(strtolower($value));
    return preg_match('/^[a-f0-9]{64}$/', $value) === 1 ? $value : '';
}

function private_notes_normalize_render_profile($profile): array
{
    if (!is_array($profile)) {
        $profile = [];
    }
    return [
        'dpi' => max(0, (int) ($profile['dpi'] ?? 0)),
        'tileSize' => max(0, (int) ($profile['tileSize'] ?? 0)),
        'format' => private_notes_clean_status($profile['format'] ?? 'png', ['png', 'jpg', 'jpeg', 'webp'], 'png'),
        'renderer' => private_notes_clean_text_field($profile['renderer'] ?? '', 80),
        'generatedAt' => private_notes_clean_iso_datetime($profile['generatedAt'] ?? ''),
    ];
}

function private_notes_normalize_document_pages($pages): array
{
    $normalized = [];
    foreach (is_array($pages) ? $pages : [] as $pageKey => $page) {
        if (!is_array($page)) {
            continue;
        }
        $number = max(1, (int) ($page['pageNumber'] ?? $pageKey));
        $levels = [];
        foreach (is_array($page['levels'] ?? null) ? $page['levels'] : [] as $levelKey => $level) {
            if (!is_array($level)) {
                continue;
            }
            $levelNumber = max(0, (int) ($level['level'] ?? $levelKey));
            $tiles = [];
            foreach (is_array($level['tiles'] ?? null) ? $level['tiles'] : [] as $tile) {
                if (!is_array($tile)) {
                    continue;
                }
                $tiles[] = [
                    'x' => max(0, (int) ($tile['x'] ?? 0)),
                    'y' => max(0, (int) ($tile['y'] ?? 0)),
                    'width' => max(0, (int) ($tile['width'] ?? 0)),
                    'height' => max(0, (int) ($tile['height'] ?? 0)),
                    'storageKey' => private_notes_clean_original_file_ref((string) ($tile['storageKey'] ?? '')),
                    'bytes' => max(0, (int) ($tile['bytes'] ?? 0)),
                ];
            }
            $levels[] = [
                'level' => $levelNumber,
                'scale' => max(0.0, (float) ($level['scale'] ?? 1.0)),
                'width' => max(0, (int) ($level['width'] ?? 0)),
                'height' => max(0, (int) ($level['height'] ?? 0)),
                'tileSize' => max(0, (int) ($level['tileSize'] ?? 0)),
                'tiles' => $tiles,
            ];
        }
        usort($levels, static fn(array $left, array $right): int => (int) $left['level'] <=> (int) $right['level']);
        $normalized[(string) $number] = [
            'pageNumber' => $number,
            'width' => max(0, (int) ($page['width'] ?? 0)),
            'height' => max(0, (int) ($page['height'] ?? 0)),
            'levels' => $levels,
        ];
    }
    ksort($normalized, SORT_NATURAL);
    return $normalized;
}

function private_notes_normalize_processing_job(string $key, array $job): ?array
{
    $id = private_notes_clean_id((string) ($job['id'] ?? $key), 'pnjob-');
    if ($id === '') {
        return null;
    }
    return [
        'id' => $id,
        'documentId' => private_notes_clean_id((string) ($job['documentId'] ?? ''), 'pndoc-'),
        'status' => private_notes_clean_status($job['status'] ?? 'pending', ['pending', 'processing', 'ready', 'failed'], 'pending'),
        'attempts' => max(0, (int) ($job['attempts'] ?? 0)),
        'lastError' => private_notes_clean_text_field($job['lastError'] ?? '', 1000),
        'createdBy' => dent_normalize_student_number((string) ($job['createdBy'] ?? '')),
        'createdAt' => private_notes_clean_iso_datetime($job['createdAt'] ?? '') ?: dent_iso_now(),
        'updatedAt' => private_notes_clean_iso_datetime($job['updatedAt'] ?? '') ?: dent_iso_now(),
        'startedAt' => private_notes_clean_iso_datetime($job['startedAt'] ?? ''),
        'finishedAt' => private_notes_clean_iso_datetime($job['finishedAt'] ?? ''),
    ];
}

function private_notes_normalize_course_membership(string $key, array $membership): ?array
{
    $id = private_notes_clean_id((string) ($membership['id'] ?? $key), 'pnmem-');
    if ($id === '') {
        return null;
    }
    return [
        'id' => $id,
        'userKey' => dent_normalize_student_number((string) ($membership['userKey'] ?? ($membership['studentNumber'] ?? ''))),
        'courseId' => private_notes_clean_id((string) ($membership['courseId'] ?? ''), 'pncrs-'),
        'semesterId' => private_notes_clean_id((string) ($membership['semesterId'] ?? ''), 'pnsem-'),
        'role' => private_notes_clean_status($membership['role'] ?? 'writer', PRIVATE_NOTES_MEMBERSHIP_ROLES, 'writer'),
        'contributionStatus' => private_notes_clean_status($membership['contributionStatus'] ?? 'pending', PRIVATE_NOTES_CONTRIBUTION_STATUSES, 'pending'),
        'accessStatus' => private_notes_clean_status($membership['accessStatus'] ?? 'active', PRIVATE_NOTES_ACCESS_STATUSES, 'active'),
        'accessStartsAt' => private_notes_clean_iso_datetime($membership['accessStartsAt'] ?? ''),
        'accessExpiresAt' => private_notes_clean_iso_datetime($membership['accessExpiresAt'] ?? ''),
        'adminNotes' => private_notes_clean_text_field($membership['adminNotes'] ?? '', 600),
        'createdBy' => dent_normalize_student_number((string) ($membership['createdBy'] ?? '')),
        'updatedBy' => dent_normalize_student_number((string) ($membership['updatedBy'] ?? '')),
        'createdAt' => private_notes_clean_iso_datetime($membership['createdAt'] ?? '') ?: dent_iso_now(),
        'updatedAt' => private_notes_clean_iso_datetime($membership['updatedAt'] ?? '') ?: dent_iso_now(),
    ];
}

function private_notes_normalize_document_permission(string $key, array $permission): ?array
{
    $id = private_notes_clean_id((string) ($permission['id'] ?? $key), 'pnperm-');
    if ($id === '') {
        return null;
    }
    return [
        'id' => $id,
        'documentId' => private_notes_clean_id((string) ($permission['documentId'] ?? ''), 'pndoc-'),
        'userKey' => dent_normalize_student_number((string) ($permission['userKey'] ?? '')),
        'membershipId' => private_notes_clean_id((string) ($permission['membershipId'] ?? ''), 'pnmem-'),
        'permission' => private_notes_clean_status($permission['permission'] ?? 'view', ['view'], 'view'),
        'status' => private_notes_clean_status($permission['status'] ?? 'active', PRIVATE_NOTES_PERMISSION_STATUSES, 'active'),
        'startsAt' => private_notes_clean_iso_datetime($permission['startsAt'] ?? ''),
        'expiresAt' => private_notes_clean_iso_datetime($permission['expiresAt'] ?? ''),
        'adminNotes' => private_notes_clean_text_field($permission['adminNotes'] ?? '', 600),
        'createdBy' => dent_normalize_student_number((string) ($permission['createdBy'] ?? '')),
        'updatedBy' => dent_normalize_student_number((string) ($permission['updatedBy'] ?? '')),
        'createdAt' => private_notes_clean_iso_datetime($permission['createdAt'] ?? '') ?: dent_iso_now(),
        'updatedAt' => private_notes_clean_iso_datetime($permission['updatedAt'] ?? '') ?: dent_iso_now(),
    ];
}

function private_notes_normalize_registered_device(string $key, array $device): ?array
{
    $id = private_notes_clean_id((string) ($device['id'] ?? $key), 'pndev-');
    if ($id === '') {
        return null;
    }
    return [
        'id' => $id,
        'userKey' => dent_normalize_student_number((string) ($device['userKey'] ?? '')),
        'label' => private_notes_clean_text_field($device['label'] ?? '', 120),
        'tokenHash' => private_notes_clean_text_field($device['tokenHash'] ?? '', 160),
        'userAgentHash' => private_notes_clean_text_field($device['userAgentHash'] ?? '', 160),
        'status' => private_notes_clean_status($device['status'] ?? 'active', ['active', 'revoked'], 'active'),
        'firstSeenAt' => private_notes_clean_iso_datetime($device['firstSeenAt'] ?? '') ?: dent_iso_now(),
        'lastSeenAt' => private_notes_clean_iso_datetime($device['lastSeenAt'] ?? '') ?: dent_iso_now(),
        'revokedAt' => private_notes_clean_iso_datetime($device['revokedAt'] ?? ''),
    ];
}

function private_notes_normalize_viewing_session(string $key, array $session): ?array
{
    $id = private_notes_clean_id((string) ($session['id'] ?? $key), 'pnses-');
    if ($id === '') {
        return null;
    }
    return [
        'id' => $id,
        'userKey' => dent_normalize_student_number((string) ($session['userKey'] ?? '')),
        'documentId' => private_notes_clean_id((string) ($session['documentId'] ?? ''), 'pndoc-'),
        'deviceId' => private_notes_clean_id((string) ($session['deviceId'] ?? ''), 'pndev-'),
        'status' => private_notes_clean_status($session['status'] ?? 'active', ['active', 'closed', 'expired', 'blocked'], 'active'),
        'startedAt' => private_notes_clean_iso_datetime($session['startedAt'] ?? '') ?: dent_iso_now(),
        'lastSeenAt' => private_notes_clean_iso_datetime($session['lastSeenAt'] ?? '') ?: dent_iso_now(),
        'ipHash' => private_notes_clean_text_field($session['ipHash'] ?? '', 160),
        'userAgentHash' => private_notes_clean_text_field($session['userAgentHash'] ?? '', 160),
    ];
}

function private_notes_normalize_view_event(string $key, array $event): ?array
{
    $id = private_notes_clean_id((string) ($event['id'] ?? $key), 'pnevt-');
    if ($id === '') {
        return null;
    }
    return [
        'id' => $id,
        'sessionId' => private_notes_clean_id((string) ($event['sessionId'] ?? ''), 'pnses-'),
        'userKey' => dent_normalize_student_number((string) ($event['userKey'] ?? '')),
        'documentId' => private_notes_clean_id((string) ($event['documentId'] ?? ''), 'pndoc-'),
        'pageNumber' => max(0, (int) dent_normalize_digits((string) ($event['pageNumber'] ?? '0'))),
        'eventType' => private_notes_clean_status($event['eventType'] ?? 'page-view', ['page-view', 'tile-view', 'heartbeat'], 'page-view'),
        'createdAt' => private_notes_clean_iso_datetime($event['createdAt'] ?? '') ?: dent_iso_now(),
        'ipHash' => private_notes_clean_text_field($event['ipHash'] ?? '', 160),
        'userAgentHash' => private_notes_clean_text_field($event['userAgentHash'] ?? '', 160),
    ];
}

function private_notes_normalize_security_event(string $key, array $event): ?array
{
    $id = private_notes_clean_id((string) ($event['id'] ?? $key), 'pnsec-');
    if ($id === '') {
        return null;
    }
    return [
        'id' => $id,
        'type' => private_notes_clean_text_field($event['type'] ?? 'access-change', 80),
        'severity' => private_notes_clean_status($event['severity'] ?? 'info', ['info', 'warning', 'critical'], 'info'),
        'status' => private_notes_clean_status($event['status'] ?? 'open', PRIVATE_NOTES_SECURITY_EVENT_STATUSES, 'open'),
        'userKey' => dent_normalize_student_number((string) ($event['userKey'] ?? '')),
        'documentId' => private_notes_clean_id((string) ($event['documentId'] ?? ''), 'pndoc-'),
        'courseId' => private_notes_clean_id((string) ($event['courseId'] ?? ''), 'pncrs-'),
        'semesterId' => private_notes_clean_id((string) ($event['semesterId'] ?? ''), 'pnsem-'),
        'message' => private_notes_clean_text_field($event['message'] ?? '', 600),
        'evidence' => is_array($event['evidence'] ?? null) ? $event['evidence'] : [],
        'createdBy' => dent_normalize_student_number((string) ($event['createdBy'] ?? '')),
        'createdAt' => private_notes_clean_iso_datetime($event['createdAt'] ?? '') ?: dent_iso_now(),
        'resolvedBy' => dent_normalize_student_number((string) ($event['resolvedBy'] ?? '')),
        'resolvedAt' => private_notes_clean_iso_datetime($event['resolvedAt'] ?? ''),
    ];
}

function private_notes_normalize_temporary_suspension(string $key, array $suspension): ?array
{
    $id = private_notes_clean_id((string) ($suspension['id'] ?? $key), 'pnsus-');
    if ($id === '') {
        return null;
    }
    return [
        'id' => $id,
        'userKey' => dent_normalize_student_number((string) ($suspension['userKey'] ?? '')),
        'documentId' => private_notes_clean_id((string) ($suspension['documentId'] ?? ''), 'pndoc-'),
        'courseId' => private_notes_clean_id((string) ($suspension['courseId'] ?? ''), 'pncrs-'),
        'semesterId' => private_notes_clean_id((string) ($suspension['semesterId'] ?? ''), 'pnsem-'),
        'status' => private_notes_clean_status($suspension['status'] ?? 'active', PRIVATE_NOTES_SUSPENSION_STATUSES, 'active'),
        'reason' => private_notes_clean_text_field($suspension['reason'] ?? '', 600),
        'startsAt' => private_notes_clean_iso_datetime($suspension['startsAt'] ?? '') ?: dent_iso_now(),
        'expiresAt' => private_notes_clean_iso_datetime($suspension['expiresAt'] ?? ''),
        'createdBy' => dent_normalize_student_number((string) ($suspension['createdBy'] ?? '')),
        'createdAt' => private_notes_clean_iso_datetime($suspension['createdAt'] ?? '') ?: dent_iso_now(),
    ];
}

function private_notes_add_security_event(array &$store, string $type, string $severity, array $context = []): array
{
    $id = private_notes_next_id('pnsec-');
    $event = private_notes_normalize_security_event($id, array_merge($context, [
        'id' => $id,
        'type' => $type,
        'severity' => $severity,
        'status' => 'open',
        'createdAt' => dent_iso_now(),
    ]));
    if ($event === null) {
        dent_error('Security event could not be created.', 500);
    }
    $store['securityEvents'][$id] = $event;
    return $event;
}

function private_notes_is_owner(array $user): bool
{
    return (string) ($user['role'] ?? '') === 'owner';
}

function private_notes_require_owner_user(): array
{
    return dent_require_owner();
}

function private_notes_user_can_manage_course(array $store, array $user, string $courseId, string $semesterId = ''): bool
{
    $courseId = private_notes_clean_id($courseId, 'pncrs-');
    $semesterId = private_notes_clean_id($semesterId, 'pnsem-');
    if ($courseId === '' || !is_array($store['courses'][$courseId] ?? null)) {
        return false;
    }

    if (private_notes_is_owner($user)) {
        return true;
    }

    $course = $store['courses'][$courseId];
    $targetCohort = dent_clean_cohort_key((string) ($course['cohortKey'] ?? dent_primary_cohort_key()));
    if ($targetCohort !== '' && dent_user_has_cohort_management_access($user, $targetCohort)) {
        return true;
    }

    $userKey = private_notes_user_key($user);
    if ($userKey === '') {
        return false;
    }

    $targetSemesterId = $semesterId !== '' ? $semesterId : (string) ($course['semesterId'] ?? '');
    foreach ($store['courseMemberships'] ?? [] as $membership) {
        if (!is_array($membership)) {
            continue;
        }
        if ((string) ($membership['userKey'] ?? '') !== $userKey) {
            continue;
        }
        if ((string) ($membership['courseId'] ?? '') !== $courseId) {
            continue;
        }
        if ($targetSemesterId !== '' && (string) ($membership['semesterId'] ?? '') !== $targetSemesterId) {
            continue;
        }
        if ((string) ($membership['role'] ?? '') !== 'manager') {
            continue;
        }
        if ((string) ($membership['contributionStatus'] ?? '') !== 'approved') {
            continue;
        }
        if (!in_array((string) ($membership['accessStatus'] ?? ''), ['active', 'warning'], true)) {
            continue;
        }
        return true;
    }

    return false;
}

function private_notes_document_admin_payload(array $document): array
{
    $payload = $document;
    unset($payload['originalFileRef'], $payload['originalStorageKey']);
    $payload['hasOriginalFile'] = (string) ($document['originalFileRef'] ?? $document['originalStorageKey'] ?? '') !== '';
    return $payload;
}

function private_notes_find_active_membership_for_document(array $store, array $user, array $document, ?string $now = null): ?array
{
    $userKey = private_notes_user_key($user);
    if ($userKey === '') {
        return null;
    }
    $courseId = (string) ($document['courseId'] ?? '');
    $semesterId = (string) ($document['semesterId'] ?? '');
    foreach ($store['courseMemberships'] ?? [] as $membership) {
        if (!is_array($membership)) {
            continue;
        }
        if ((string) ($membership['userKey'] ?? '') !== $userKey) {
            continue;
        }
        if ((string) ($membership['courseId'] ?? '') !== $courseId || (string) ($membership['semesterId'] ?? '') !== $semesterId) {
            continue;
        }
        if ((string) ($membership['contributionStatus'] ?? '') !== 'approved') {
            continue;
        }
        if (!in_array((string) ($membership['accessStatus'] ?? ''), ['active', 'warning'], true)) {
            continue;
        }
        if (!private_notes_is_in_date_window((string) ($membership['accessStartsAt'] ?? ''), (string) ($membership['accessExpiresAt'] ?? ''), $now)) {
            continue;
        }
        return $membership;
    }
    return null;
}

function private_notes_find_active_document_permission(array $store, array $user, array $document, array $membership, ?string $now = null): ?array
{
    $userKey = private_notes_user_key($user);
    $documentId = (string) ($document['id'] ?? '');
    $membershipId = (string) ($membership['id'] ?? '');
    foreach ($store['documentPermissions'] ?? [] as $permission) {
        if (!is_array($permission)) {
            continue;
        }
        if ((string) ($permission['documentId'] ?? '') !== $documentId) {
            continue;
        }
        if ((string) ($permission['userKey'] ?? '') !== $userKey && (string) ($permission['membershipId'] ?? '') !== $membershipId) {
            continue;
        }
        if (!in_array((string) ($permission['status'] ?? ''), ['active', 'warning'], true)) {
            continue;
        }
        if (!private_notes_is_in_date_window((string) ($permission['startsAt'] ?? ''), (string) ($permission['expiresAt'] ?? ''), $now)) {
            continue;
        }
        return $permission;
    }
    return null;
}

function private_notes_matching_active_suspension(array $store, string $userKey, array $document, ?string $now = null): ?array
{
    foreach ($store['temporarySuspensions'] ?? [] as $suspension) {
        if (!is_array($suspension) || (string) ($suspension['status'] ?? '') !== 'active') {
            continue;
        }
        if ((string) ($suspension['userKey'] ?? '') !== $userKey) {
            continue;
        }
        $matchesDocument = (string) ($suspension['documentId'] ?? '') === '' || (string) ($suspension['documentId'] ?? '') === (string) ($document['id'] ?? '');
        $matchesCourse = (string) ($suspension['courseId'] ?? '') === '' || (string) ($suspension['courseId'] ?? '') === (string) ($document['courseId'] ?? '');
        $matchesSemester = (string) ($suspension['semesterId'] ?? '') === '' || (string) ($suspension['semesterId'] ?? '') === (string) ($document['semesterId'] ?? '');
        if (!$matchesDocument || !$matchesCourse || !$matchesSemester) {
            continue;
        }
        if (!private_notes_is_in_date_window((string) ($suspension['startsAt'] ?? ''), (string) ($suspension['expiresAt'] ?? ''), $now)) {
            continue;
        }
        return $suspension;
    }
    return null;
}

function private_notes_user_can_view_document(array $store, array $user, string $documentId, ?string $now = null): array
{
    $store = private_notes_normalize_store($store);
    $documentId = private_notes_clean_id($documentId, 'pndoc-');
    if ($documentId === '' || !is_array($store['documents'][$documentId] ?? null)) {
        return ['allowed' => false, 'reason' => 'document-not-found'];
    }
    $document = $store['documents'][$documentId];

    if (private_notes_is_owner($user)) {
        return ['allowed' => true, 'reason' => 'owner', 'document' => $document];
    }

    $userKey = private_notes_user_key($user);
    if ($userKey === '') {
        return ['allowed' => false, 'reason' => 'login-required', 'document' => $document];
    }

    if ((string) ($document['publicationStatus'] ?? '') !== 'published') {
        return ['allowed' => false, 'reason' => 'document-not-published', 'document' => $document];
    }
    if ((string) ($document['processingStatus'] ?? '') !== 'ready') {
        return ['allowed' => false, 'reason' => 'document-not-ready', 'document' => $document];
    }

    $suspension = private_notes_matching_active_suspension($store, $userKey, $document, $now);
    if ($suspension !== null) {
        return ['allowed' => false, 'reason' => 'temporarily-suspended', 'document' => $document, 'suspension' => $suspension];
    }

    $membership = private_notes_find_active_membership_for_document($store, $user, $document, $now);
    if ($membership === null) {
        return ['allowed' => false, 'reason' => 'active-membership-required', 'document' => $document];
    }

    $permission = private_notes_find_active_document_permission($store, $user, $document, $membership, $now);
    if ($permission === null) {
        return ['allowed' => false, 'reason' => 'document-permission-required', 'document' => $document, 'membership' => $membership];
    }

    return [
        'allowed' => true,
        'reason' => 'allowed',
        'document' => $document,
        'membership' => $membership,
        'permission' => $permission,
    ];
}

function private_notes_admin_grant_course_membership(array $admin, array $input): array
{
    if (!private_notes_is_owner($admin)) {
        dent_error('Private note memberships can only be managed by the site owner.', 403);
    }
    return private_notes_with_store_lock(static function (array &$store) use ($admin, $input): array {
        $userKey = dent_normalize_student_number((string) ($input['userKey'] ?? $input['studentNumber'] ?? ''));
        $courseId = private_notes_clean_id((string) ($input['courseId'] ?? ''), 'pncrs-');
        $semesterId = private_notes_clean_id((string) ($input['semesterId'] ?? ''), 'pnsem-');
        if ($userKey === '' || $courseId === '' || $semesterId === '') {
            dent_error('Membership user, course and semester are required.', 422);
        }
        if (!is_array($store['courses'][$courseId] ?? null) || !is_array($store['semesters'][$semesterId] ?? null)) {
            dent_error('Membership course or semester was not found.', 404);
        }

        $existingId = '';
        foreach ($store['courseMemberships'] as $id => $membership) {
            if ((string) ($membership['userKey'] ?? '') === $userKey
                && (string) ($membership['courseId'] ?? '') === $courseId
                && (string) ($membership['semesterId'] ?? '') === $semesterId) {
                $existingId = (string) $id;
                break;
            }
        }

        $now = dent_iso_now();
        $id = $existingId !== '' ? $existingId : private_notes_next_id('pnmem-');
        $membership = private_notes_normalize_course_membership($id, array_merge($store['courseMemberships'][$id] ?? [], [
            'id' => $id,
            'userKey' => $userKey,
            'courseId' => $courseId,
            'semesterId' => $semesterId,
            'role' => $input['role'] ?? 'writer',
            'contributionStatus' => $input['contributionStatus'] ?? 'approved',
            'accessStatus' => $input['accessStatus'] ?? 'active',
            'accessStartsAt' => $input['accessStartsAt'] ?? '',
            'accessExpiresAt' => $input['accessExpiresAt'] ?? '',
            'adminNotes' => $input['adminNotes'] ?? '',
            'createdBy' => $store['courseMemberships'][$id]['createdBy'] ?? private_notes_user_key($admin),
            'updatedBy' => private_notes_user_key($admin),
            'createdAt' => $store['courseMemberships'][$id]['createdAt'] ?? $now,
            'updatedAt' => $now,
        ]));
        if ($membership === null) {
            dent_error('Membership payload is invalid.', 422);
        }
        $store['courseMemberships'][$id] = $membership;
        private_notes_add_security_event($store, $existingId === '' ? 'membership-granted' : 'membership-updated', 'info', [
            'userKey' => $userKey,
            'courseId' => $courseId,
            'semesterId' => $semesterId,
            'message' => 'Private notes course membership changed.',
            'createdBy' => private_notes_user_key($admin),
        ]);
        return $membership;
    });
}

function private_notes_admin_set_membership_access_status(array $admin, string $membershipId, string $status, string $adminNotes = ''): array
{
    if (!private_notes_is_owner($admin)) {
        dent_error('Private note memberships can only be managed by the site owner.', 403);
    }
    $membershipId = private_notes_clean_id($membershipId, 'pnmem-');
    $status = private_notes_clean_status($status, PRIVATE_NOTES_ACCESS_STATUSES, '');
    if ($membershipId === '' || $status === '') {
        dent_error('Membership status change is invalid.', 422);
    }
    return private_notes_with_store_lock(static function (array &$store) use ($admin, $membershipId, $status, $adminNotes): array {
        if (!is_array($store['courseMemberships'][$membershipId] ?? null)) {
            dent_error('Membership was not found.', 404);
        }
        $membership = $store['courseMemberships'][$membershipId];
        $membership['accessStatus'] = $status;
        if ($adminNotes !== '') {
            $membership['adminNotes'] = $adminNotes;
        }
        $membership['updatedBy'] = private_notes_user_key($admin);
        $membership['updatedAt'] = dent_iso_now();
        $store['courseMemberships'][$membershipId] = private_notes_normalize_course_membership($membershipId, $membership);
        private_notes_add_security_event($store, 'membership-' . $status, $status === 'active' ? 'info' : 'warning', [
            'userKey' => (string) ($membership['userKey'] ?? ''),
            'courseId' => (string) ($membership['courseId'] ?? ''),
            'semesterId' => (string) ($membership['semesterId'] ?? ''),
            'message' => 'Private notes membership access status changed.',
            'createdBy' => private_notes_user_key($admin),
        ]);
        return $store['courseMemberships'][$membershipId];
    });
}

function private_notes_admin_grant_document_permission(array $admin, array $input): array
{
    if (!private_notes_is_owner($admin)) {
        dent_error('Private note document permissions can only be managed by the site owner.', 403);
    }
    return private_notes_with_store_lock(static function (array &$store) use ($admin, $input): array {
        $documentId = private_notes_clean_id((string) ($input['documentId'] ?? ''), 'pndoc-');
        $userKey = dent_normalize_student_number((string) ($input['userKey'] ?? $input['studentNumber'] ?? ''));
        if ($documentId === '' || $userKey === '') {
            dent_error('Document and user are required for permission.', 422);
        }
        if (!is_array($store['documents'][$documentId] ?? null)) {
            dent_error('Document was not found.', 404);
        }

        $existingId = '';
        foreach ($store['documentPermissions'] as $id => $permission) {
            if ((string) ($permission['documentId'] ?? '') === $documentId && (string) ($permission['userKey'] ?? '') === $userKey) {
                $existingId = (string) $id;
                break;
            }
        }

        $membership = private_notes_find_active_membership_for_document($store, ['studentNumber' => $userKey], $store['documents'][$documentId]);
        $now = dent_iso_now();
        $id = $existingId !== '' ? $existingId : private_notes_next_id('pnperm-');
        $permission = private_notes_normalize_document_permission($id, array_merge($store['documentPermissions'][$id] ?? [], [
            'id' => $id,
            'documentId' => $documentId,
            'userKey' => $userKey,
            'membershipId' => is_array($membership) ? (string) ($membership['id'] ?? '') : '',
            'permission' => 'view',
            'status' => $input['status'] ?? 'active',
            'startsAt' => $input['startsAt'] ?? '',
            'expiresAt' => $input['expiresAt'] ?? '',
            'adminNotes' => $input['adminNotes'] ?? '',
            'createdBy' => $store['documentPermissions'][$id]['createdBy'] ?? private_notes_user_key($admin),
            'updatedBy' => private_notes_user_key($admin),
            'createdAt' => $store['documentPermissions'][$id]['createdAt'] ?? $now,
            'updatedAt' => $now,
        ]));
        if ($permission === null) {
            dent_error('Document permission payload is invalid.', 422);
        }
        $store['documentPermissions'][$id] = $permission;
        private_notes_add_security_event($store, $existingId === '' ? 'document-permission-granted' : 'document-permission-updated', 'info', [
            'userKey' => $userKey,
            'documentId' => $documentId,
            'courseId' => (string) ($store['documents'][$documentId]['courseId'] ?? ''),
            'semesterId' => (string) ($store['documents'][$documentId]['semesterId'] ?? ''),
            'message' => 'Private notes document permission changed.',
            'createdBy' => private_notes_user_key($admin),
        ]);
        return $permission;
    });
}

function private_notes_admin_set_document_permission_status(array $admin, string $permissionId, string $status, string $adminNotes = ''): array
{
    if (!private_notes_is_owner($admin)) {
        dent_error('Private note document permissions can only be managed by the site owner.', 403);
    }
    $permissionId = private_notes_clean_id($permissionId, 'pnperm-');
    $status = private_notes_clean_status($status, PRIVATE_NOTES_PERMISSION_STATUSES, '');
    if ($permissionId === '' || $status === '') {
        dent_error('Document permission status change is invalid.', 422);
    }
    return private_notes_with_store_lock(static function (array &$store) use ($admin, $permissionId, $status, $adminNotes): array {
        if (!is_array($store['documentPermissions'][$permissionId] ?? null)) {
            dent_error('Document permission was not found.', 404);
        }
        $permission = $store['documentPermissions'][$permissionId];
        $permission['status'] = $status;
        if ($adminNotes !== '') {
            $permission['adminNotes'] = $adminNotes;
        }
        $permission['updatedBy'] = private_notes_user_key($admin);
        $permission['updatedAt'] = dent_iso_now();
        $store['documentPermissions'][$permissionId] = private_notes_normalize_document_permission($permissionId, $permission);
        $document = is_array($store['documents'][(string) ($permission['documentId'] ?? '')] ?? null)
            ? $store['documents'][(string) ($permission['documentId'] ?? '')]
            : [];
        private_notes_add_security_event($store, 'document-permission-' . $status, $status === 'active' ? 'info' : 'warning', [
            'userKey' => (string) ($permission['userKey'] ?? ''),
            'documentId' => (string) ($permission['documentId'] ?? ''),
            'courseId' => (string) ($document['courseId'] ?? ''),
            'semesterId' => (string) ($document['semesterId'] ?? ''),
            'message' => 'Private notes document permission status changed.',
            'createdBy' => private_notes_user_key($admin),
        ]);
        return $store['documentPermissions'][$permissionId];
    });
}
