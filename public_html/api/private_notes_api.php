<?php
declare(strict_types=1);

require_once __DIR__ . '/private_notes_store.php';

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
        'documents' => array_values($store['documents'] ?? []),
        'courseMemberships' => array_values($store['courseMemberships'] ?? []),
        'documentPermissions' => array_values($store['documentPermissions'] ?? []),
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
        return $document;
    });
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

