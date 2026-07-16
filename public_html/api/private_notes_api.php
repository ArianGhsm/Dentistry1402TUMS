<?php
declare(strict_types=1);

require_once __DIR__ . '/private_notes_processing.php';

function private_notes_api_require_method(array $methods): void
{
    if (!in_array(dent_request_method(), $methods, true)) {
        dent_error('Request method is not allowed.', 405);
    }
}

function private_notes_api_public_store_summary(array $store): array
{
    return [
        'schemaVersion' => (int) ($store['schemaVersion'] ?? 0),
        'counts' => [
            'semesters' => count($store['semesters'] ?? []),
            'courses' => count($store['courses'] ?? []),
            'documents' => count($store['documents'] ?? []),
            'courseMemberships' => count($store['courseMemberships'] ?? []),
            'documentPermissions' => count($store['documentPermissions'] ?? []),
            'processingJobs' => count($store['processingJobs'] ?? []),
            'registeredDevices' => count($store['registeredDevices'] ?? []),
            'activeViewingSessions' => count($store['activeViewingSessions'] ?? []),
            'documentViewEvents' => count($store['documentViewEvents'] ?? []),
            'securityEvents' => count($store['securityEvents'] ?? []),
            'temporarySuspensions' => count($store['temporarySuspensions'] ?? []),
        ],
    ];
}

function private_notes_api_owner_payload(array $store): array
{
    return [
        'summary' => private_notes_api_public_store_summary($store),
        'semesters' => array_values($store['semesters'] ?? []),
        'courses' => array_values($store['courses'] ?? []),
        'documents' => array_values(array_map('private_notes_document_admin_payload', $store['documents'] ?? [])),
        'courseMemberships' => array_values($store['courseMemberships'] ?? []),
        'documentPermissions' => array_values($store['documentPermissions'] ?? []),
        'processingJobs' => array_values($store['processingJobs'] ?? []),
        'registeredDevices' => array_values($store['registeredDevices'] ?? []),
        'activeViewingSessions' => array_values($store['activeViewingSessions'] ?? []),
        'securityEvents' => array_values($store['securityEvents'] ?? []),
        'temporarySuspensions' => array_values($store['temporarySuspensions'] ?? []),
    ];
}

function private_notes_api_save_semester(array $owner, array $input): array
{
    return private_notes_with_store_lock(static function (array &$store) use ($owner, $input): array {
        $id = private_notes_clean_id((string) ($input['semesterId'] ?? $input['id'] ?? ''), 'pnsem-');
        if ($id === '') {
            $id = private_notes_next_id('pnsem-');
        }
        $now = dent_iso_now();
        $semester = private_notes_normalize_semester($id, array_merge($store['semesters'][$id] ?? [], [
            'id' => $id,
            'cohortKey' => $input['cohortKey'] ?? dent_primary_cohort_key(),
            'title' => $input['title'] ?? '',
            'academicYear' => $input['academicYear'] ?? '',
            'termNumber' => $input['termNumber'] ?? 0,
            'startsAt' => $input['startsAt'] ?? '',
            'expiresAt' => $input['expiresAt'] ?? '',
            'createdAt' => $store['semesters'][$id]['createdAt'] ?? $now,
            'updatedAt' => $now,
        ]));
        if ($semester === null || (string) ($semester['title'] ?? '') === '') {
            dent_error('Semester title is required.', 422);
        }
        $store['semesters'][$id] = $semester;
        private_notes_add_security_event($store, 'semester-saved', 'info', [
            'semesterId' => $id,
            'message' => 'Private notes semester saved.',
            'createdBy' => private_notes_user_key($owner),
        ]);
        return $semester;
    });
}

function private_notes_api_save_course(array $owner, array $input): array
{
    return private_notes_with_store_lock(static function (array &$store) use ($owner, $input): array {
        $id = private_notes_clean_id((string) ($input['courseId'] ?? $input['id'] ?? ''), 'pncrs-');
        if ($id === '') {
            $id = private_notes_next_id('pncrs-');
        }
        $semesterId = private_notes_clean_id((string) ($input['semesterId'] ?? ''), 'pnsem-');
        if ($semesterId === '' || !is_array($store['semesters'][$semesterId] ?? null)) {
            dent_error('A valid semester is required for this course.', 422);
        }
        $now = dent_iso_now();
        $course = private_notes_normalize_course($id, array_merge($store['courses'][$id] ?? [], [
            'id' => $id,
            'cohortKey' => $input['cohortKey'] ?? dent_primary_cohort_key(),
            'semesterId' => $semesterId,
            'title' => $input['title'] ?? '',
            'slug' => $input['slug'] ?? '',
            'createdAt' => $store['courses'][$id]['createdAt'] ?? $now,
            'updatedAt' => $now,
        ]));
        if ($course === null || (string) ($course['title'] ?? '') === '') {
            dent_error('Course title is required.', 422);
        }
        $store['courses'][$id] = $course;
        private_notes_add_security_event($store, 'course-saved', 'info', [
            'courseId' => $id,
            'semesterId' => $semesterId,
            'message' => 'Private notes course saved.',
            'createdBy' => private_notes_user_key($owner),
        ]);
        return $course;
    });
}

function private_notes_api_save_document(array $owner, array $input): array
{
    return private_notes_with_store_lock(static function (array &$store) use ($owner, $input): array {
        $id = private_notes_clean_id((string) ($input['documentId'] ?? $input['id'] ?? ''), 'pndoc-');
        if ($id === '') {
            $id = private_notes_next_id('pndoc-');
        }
        $courseId = private_notes_clean_id((string) ($input['courseId'] ?? ''), 'pncrs-');
        $semesterId = private_notes_clean_id((string) ($input['semesterId'] ?? ''), 'pnsem-');
        if ($courseId === '' || !is_array($store['courses'][$courseId] ?? null)) {
            dent_error('A valid course is required for this document.', 422);
        }
        if ($semesterId === '' || !is_array($store['semesters'][$semesterId] ?? null)) {
            dent_error('A valid semester is required for this document.', 422);
        }
        $now = dent_iso_now();
        $document = private_notes_normalize_document($id, array_merge($store['documents'][$id] ?? [], [
            'id' => $id,
            'cohortKey' => $input['cohortKey'] ?? dent_primary_cohort_key(),
            'title' => $input['title'] ?? '',
            'courseId' => $courseId,
            'semesterId' => $semesterId,
            'originalFileRef' => $input['originalFileRef'] ?? '',
            'processingStatus' => $input['processingStatus'] ?? 'pending',
            'pageCount' => $input['pageCount'] ?? 0,
            'uploaderUserKey' => $input['uploaderUserKey'] ?? private_notes_user_key($owner),
            'publicationStatus' => $input['publicationStatus'] ?? 'draft',
            'createdAt' => $store['documents'][$id]['createdAt'] ?? $now,
            'updatedAt' => $now,
        ]));
        if ($document === null || (string) ($document['title'] ?? '') === '') {
            dent_error('Document title is required.', 422);
        }
        $store['documents'][$id] = $document;
        private_notes_add_security_event($store, 'document-saved', 'info', [
            'documentId' => $id,
            'courseId' => $courseId,
            'semesterId' => $semesterId,
            'message' => 'Private notes document saved.',
            'createdBy' => private_notes_user_key($owner),
        ]);
        return private_notes_document_admin_payload($document);
    });
}

function private_notes_api_upload_document(array $user, array $input, array $file): array
{
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        dent_error('A PDF file is required.', 422);
    }
    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_file($tmpName)) {
        dent_error('Uploaded file is not available.', 422);
    }
    if (function_exists('is_uploaded_file') && !is_uploaded_file($tmpName)) {
        dent_error('Uploaded file is not a valid HTTP upload.', 422);
    }

    $validation = private_notes_validate_pdf_file(
        $tmpName,
        (string) ($file['name'] ?? ''),
        (string) ($file['type'] ?? '')
    );
    if (empty($validation['ok'])) {
        dent_error((string) ($validation['error'] ?? 'Uploaded PDF is invalid.'), 422);
    }

    $courseId = private_notes_clean_id((string) ($input['courseId'] ?? ''), 'pncrs-');
    $semesterId = private_notes_clean_id((string) ($input['semesterId'] ?? ''), 'pnsem-');
    if ($courseId === '' || $semesterId === '') {
        dent_error('Course and semester are required.', 422);
    }

    $store = private_notes_read_store();
    if (!private_notes_user_can_manage_course($store, $user, $courseId, $semesterId)) {
        dent_error('You do not have permission to upload private notes for this course.', 403);
    }
    if (!is_array($store['semesters'][$semesterId] ?? null) || !is_array($store['courses'][$courseId] ?? null)) {
        dent_error('Course or semester was not found.', 404);
    }

    $documentId = private_notes_next_id('pndoc-');
    $storageKey = private_notes_storage_key($documentId);
    $targetPath = private_notes_original_absolute_path($storageKey);
    dent_ensure_directory(dirname($targetPath));
    if (!@move_uploaded_file($tmpName, $targetPath)) {
        dent_error('Private PDF could not be stored.', 500);
    }
    @chmod($targetPath, 0640);

    $document = private_notes_with_store_lock(static function (array &$lockedStore) use ($user, $input, $courseId, $semesterId, $documentId, $storageKey, $validation): array {
        $course = $lockedStore['courses'][$courseId] ?? [];
        $document = private_notes_normalize_document($documentId, [
            'id' => $documentId,
            'cohortKey' => $input['cohortKey'] ?? ($course['cohortKey'] ?? dent_primary_cohort_key()),
            'title' => $input['title'] ?? ($validation['safeName'] ?? 'Private PDF'),
            'courseId' => $courseId,
            'semesterId' => $semesterId,
            'originalFileRef' => $storageKey,
            'originalStorageKey' => $storageKey,
            'originalFilename' => $validation['safeName'] ?? '',
            'originalMimeType' => $validation['mimeType'] ?? 'application/pdf',
            'originalSizeBytes' => $validation['sizeBytes'] ?? 0,
            'originalSha256' => $validation['sha256'] ?? '',
            'processingStatus' => 'pending',
            'processingError' => '',
            'pageCount' => 0,
            'uploaderUserKey' => private_notes_user_key($user),
            'publicationStatus' => $input['publicationStatus'] ?? 'draft',
            'createdAt' => dent_iso_now(),
            'updatedAt' => dent_iso_now(),
        ]);
        if ($document === null) {
            dent_error('Document metadata is invalid.', 422);
        }
        $lockedStore['documents'][$documentId] = $document;
        $job = private_notes_create_processing_job($lockedStore, $documentId, $user);
        private_notes_add_security_event($lockedStore, 'document-uploaded', 'info', [
            'documentId' => $documentId,
            'courseId' => $courseId,
            'semesterId' => $semesterId,
            'message' => 'Private PDF uploaded into private storage.',
            'createdBy' => private_notes_user_key($user),
        ]);
        $document['processingJobId'] = (string) ($job['id'] ?? '');
        return $document;
    });

    $processNow = dent_parse_bool($input['processNow'] ?? getenv('DENT_PRIVATE_NOTES_PROCESS_INLINE'), false);
    $processing = $processNow ? private_notes_process_document($documentId) : ['ok' => true, 'queued' => true];
    $fresh = private_notes_read_store();
    $freshDocument = is_array($fresh['documents'][$documentId] ?? null) ? $fresh['documents'][$documentId] : $document;

    return [
        'document' => private_notes_document_admin_payload($freshDocument),
        'processing' => $processing,
    ];
}

function private_notes_api_create_temporary_suspension(array $owner, array $input): array
{
    return private_notes_with_store_lock(static function (array &$store) use ($owner, $input): array {
        $id = private_notes_next_id('pnsus-');
        $suspension = private_notes_normalize_temporary_suspension($id, [
            'id' => $id,
            'userKey' => $input['userKey'] ?? $input['studentNumber'] ?? '',
            'documentId' => $input['documentId'] ?? '',
            'courseId' => $input['courseId'] ?? '',
            'semesterId' => $input['semesterId'] ?? '',
            'status' => 'active',
            'reason' => $input['reason'] ?? $input['adminNotes'] ?? '',
            'startsAt' => $input['startsAt'] ?? dent_iso_now(),
            'expiresAt' => $input['expiresAt'] ?? '',
            'createdBy' => private_notes_user_key($owner),
            'createdAt' => dent_iso_now(),
        ]);
        if ($suspension === null || (string) ($suspension['userKey'] ?? '') === '') {
            dent_error('Suspension user is required.', 422);
        }
        $store['temporarySuspensions'][$id] = $suspension;
        private_notes_add_security_event($store, 'temporary-suspension-created', 'warning', [
            'userKey' => (string) ($suspension['userKey'] ?? ''),
            'documentId' => (string) ($suspension['documentId'] ?? ''),
            'courseId' => (string) ($suspension['courseId'] ?? ''),
            'semesterId' => (string) ($suspension['semesterId'] ?? ''),
            'message' => 'Private notes temporary suspension created.',
            'createdBy' => private_notes_user_key($owner),
        ]);
        return $suspension;
    });
}

function private_notes_api_processing_status_payload(array $document): array
{
    $payload = private_notes_document_admin_payload($document);
    unset($payload['pages']);
    $payload['pageCount'] = (int) ($document['pageCount'] ?? 0);
    $payload['renderProfile'] = is_array($document['renderProfile'] ?? null) ? $document['renderProfile'] : [];
    $payload['pageSummaries'] = [];
    foreach (is_array($document['pages'] ?? null) ? $document['pages'] : [] as $page) {
        if (!is_array($page)) {
            continue;
        }
        $tileCount = 0;
        foreach (is_array($page['levels'] ?? null) ? $page['levels'] : [] as $level) {
            $tileCount += count(is_array($level['tiles'] ?? null) ? $level['tiles'] : []);
        }
        $payload['pageSummaries'][] = [
            'pageNumber' => (int) ($page['pageNumber'] ?? 0),
            'width' => (int) ($page['width'] ?? 0),
            'height' => (int) ($page['height'] ?? 0),
            'levels' => count(is_array($page['levels'] ?? null) ? $page['levels'] : []),
            'tiles' => $tileCount,
        ];
    }
    return $payload;
}

$action = dent_request_action();
if ($action === '') {
    $action = 'status';
}

if ($action === 'status') {
    private_notes_api_require_method(['GET']);
    $user = dent_current_user();
    dent_release_session_lock();
    $store = private_notes_read_store();
    dent_json_response([
        'success' => true,
        'loggedIn' => is_array($user),
        'isOwner' => is_array($user) && private_notes_is_owner($user),
        'summary' => is_array($user) && private_notes_is_owner($user)
            ? private_notes_api_public_store_summary($store)
            : ['schemaVersion' => (int) ($store['schemaVersion'] ?? 0)],
    ]);
}

if ($action === 'ownerStore') {
    private_notes_api_require_method(['GET']);
    dent_require_owner();
    dent_release_session_lock();
    $store = private_notes_read_store();
    dent_json_response([
        'success' => true,
        'privateNotes' => private_notes_api_owner_payload($store),
    ]);
}

if ($action === 'checkAccess') {
    private_notes_api_require_method(['GET']);
    $user = dent_require_user();
    dent_release_session_lock();
    $store = private_notes_read_store();
    $decision = private_notes_user_can_view_document($store, $user, (string) ($_GET['documentId'] ?? ''));
    dent_json_response([
        'success' => true,
        'access' => [
            'allowed' => !empty($decision['allowed']),
            'reason' => (string) ($decision['reason'] ?? ''),
            'documentId' => (string) (($decision['document']['id'] ?? '') ?: ($_GET['documentId'] ?? '')),
        ],
    ]);
}

if ($action === 'ownerSaveSemester') {
    private_notes_api_require_method(['POST']);
    $owner = dent_require_owner();
    dent_release_session_lock();
    $semester = private_notes_api_save_semester($owner, $_POST);
    dent_json_response(['success' => true, 'semester' => $semester]);
}

if ($action === 'ownerSaveCourse') {
    private_notes_api_require_method(['POST']);
    $owner = dent_require_owner();
    dent_release_session_lock();
    $course = private_notes_api_save_course($owner, $_POST);
    dent_json_response(['success' => true, 'course' => $course]);
}

if ($action === 'ownerSaveDocument') {
    private_notes_api_require_method(['POST']);
    $owner = dent_require_owner();
    dent_release_session_lock();
    $document = private_notes_api_save_document($owner, $_POST);
    dent_json_response(['success' => true, 'document' => $document]);
}

if ($action === 'uploadDocument') {
    private_notes_api_require_method(['POST']);
    $user = dent_require_user();
    dent_release_session_lock();
    $result = private_notes_api_upload_document($user, $_POST, $_FILES['file'] ?? []);
    dent_json_response([
        'success' => true,
        'document' => $result['document'],
        'processing' => $result['processing'],
        'message' => 'Private PDF uploaded.',
    ]);
}

if ($action === 'processingStatus') {
    private_notes_api_require_method(['GET']);
    $user = dent_require_user();
    dent_release_session_lock();
    $documentId = private_notes_clean_id((string) ($_GET['documentId'] ?? ''), 'pndoc-');
    $store = private_notes_read_store();
    if ($documentId === '' || !is_array($store['documents'][$documentId] ?? null)) {
        dent_error('Document was not found.', 404);
    }
    $document = $store['documents'][$documentId];
    if (!private_notes_user_can_manage_course($store, $user, (string) ($document['courseId'] ?? ''), (string) ($document['semesterId'] ?? ''))) {
        dent_error('You do not have permission to view this processing status.', 403);
    }
    dent_json_response([
        'success' => true,
        'document' => private_notes_api_processing_status_payload($document),
    ]);
}

if ($action === 'retryProcessing' || $action === 'ownerRetryProcessing') {
    private_notes_api_require_method(['POST']);
    $user = dent_require_user();
    dent_release_session_lock();
    $documentId = private_notes_clean_id((string) ($_POST['documentId'] ?? ''), 'pndoc-');
    $store = private_notes_read_store();
    if ($documentId === '' || !is_array($store['documents'][$documentId] ?? null)) {
        dent_error('Document was not found.', 404);
    }
    $document = $store['documents'][$documentId];
    if (!private_notes_user_can_manage_course($store, $user, (string) ($document['courseId'] ?? ''), (string) ($document['semesterId'] ?? ''))) {
        dent_error('You do not have permission to retry this processing job.', 403);
    }
    private_notes_with_store_lock(static function (array &$lockedStore) use ($documentId, $user): array {
        if (!is_array($lockedStore['documents'][$documentId] ?? null)) {
            dent_error('Document was not found.', 404);
        }
        private_notes_create_processing_job($lockedStore, $documentId, $user);
        $document = $lockedStore['documents'][$documentId];
        $document['processingStatus'] = 'pending';
        $document['processingError'] = '';
        $document['updatedAt'] = dent_iso_now();
        $lockedStore['documents'][$documentId] = private_notes_normalize_document($documentId, $document);
        return [];
    });
    $processing = private_notes_process_document($documentId);
    $fresh = private_notes_read_store();
    dent_json_response([
        'success' => !empty($processing['ok']),
        'processing' => $processing,
        'document' => private_notes_api_processing_status_payload($fresh['documents'][$documentId] ?? []),
    ], !empty($processing['ok']) ? 200 : 422);
}

if ($action === 'ownerProcessNextJob') {
    private_notes_api_require_method(['POST']);
    dent_require_owner();
    dent_release_session_lock();
    $processing = private_notes_process_next_pending_job();
    dent_json_response([
        'success' => !empty($processing['ok']),
        'processing' => $processing,
    ], !empty($processing['ok']) ? 200 : 422);
}

if ($action === 'ownerGrantCourseMembership') {
    private_notes_api_require_method(['POST']);
    $owner = dent_require_owner();
    dent_release_session_lock();
    $membership = private_notes_admin_grant_course_membership($owner, $_POST);
    dent_json_response(['success' => true, 'membership' => $membership]);
}

if ($action === 'ownerSuspendCourseMembership') {
    private_notes_api_require_method(['POST']);
    $owner = dent_require_owner();
    dent_release_session_lock();
    $membership = private_notes_admin_set_membership_access_status(
        $owner,
        (string) ($_POST['membershipId'] ?? ''),
        'suspended',
        (string) ($_POST['adminNotes'] ?? '')
    );
    dent_json_response(['success' => true, 'membership' => $membership]);
}

if ($action === 'ownerRevokeCourseMembership') {
    private_notes_api_require_method(['POST']);
    $owner = dent_require_owner();
    dent_release_session_lock();
    $membership = private_notes_admin_set_membership_access_status(
        $owner,
        (string) ($_POST['membershipId'] ?? ''),
        'revoked',
        (string) ($_POST['adminNotes'] ?? '')
    );
    dent_json_response(['success' => true, 'membership' => $membership]);
}

if ($action === 'ownerGrantDocumentPermission') {
    private_notes_api_require_method(['POST']);
    $owner = dent_require_owner();
    dent_release_session_lock();
    $permission = private_notes_admin_grant_document_permission($owner, $_POST);
    dent_json_response(['success' => true, 'permission' => $permission]);
}

if ($action === 'ownerSuspendDocumentPermission') {
    private_notes_api_require_method(['POST']);
    $owner = dent_require_owner();
    dent_release_session_lock();
    $permission = private_notes_admin_set_document_permission_status(
        $owner,
        (string) ($_POST['permissionId'] ?? ''),
        'suspended',
        (string) ($_POST['adminNotes'] ?? '')
    );
    dent_json_response(['success' => true, 'permission' => $permission]);
}

if ($action === 'ownerRevokeDocumentPermission') {
    private_notes_api_require_method(['POST']);
    $owner = dent_require_owner();
    dent_release_session_lock();
    $permission = private_notes_admin_set_document_permission_status(
        $owner,
        (string) ($_POST['permissionId'] ?? ''),
        'revoked',
        (string) ($_POST['adminNotes'] ?? '')
    );
    dent_json_response(['success' => true, 'permission' => $permission]);
}

if ($action === 'ownerCreateTemporarySuspension') {
    private_notes_api_require_method(['POST']);
    $owner = dent_require_owner();
    dent_release_session_lock();
    $suspension = private_notes_api_create_temporary_suspension($owner, $_POST);
    dent_json_response(['success' => true, 'suspension' => $suspension]);
}

dent_error('Unknown private notes action.', 404);
