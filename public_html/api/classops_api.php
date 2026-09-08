<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/classops_domain_store_adapter.php';
require_once __DIR__ . '/classops_modules/domain_facade.php';

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');

function classops_api_payload(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $declaredLength = max(0, (int) ($_SERVER['CONTENT_LENGTH'] ?? 0));
        if ($declaredLength > 65536) {
            classops_domain_error('CLASSOPS_REQUEST_TOO_LARGE', 'حجم درخواست بیش از حد مجاز است.', 413);
        }
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || strlen($raw) > 65536) {
            classops_domain_error('CLASSOPS_REQUEST_TOO_LARGE', 'حجم درخواست بیش از حد مجاز است.', 413);
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || array_is_list($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            classops_domain_error('CLASSOPS_INVALID_JSON', 'بدنه JSON معتبر نیست.');
        }
        return $decoded;
    }
    return $_POST;
}

function classops_api_object_field(array $payload, string $field): array
{
    $value = $payload[$field] ?? [];
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (!is_array($decoded) || array_is_list($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            classops_domain_error('CLASSOPS_INVALID_OBJECT', "فیلد {$field} باید object معتبر باشد.");
        }
        return $decoded;
    }
    if (!is_array($value) || array_is_list($value)) {
        classops_domain_error('CLASSOPS_INVALID_OBJECT', "فیلد {$field} باید object معتبر باشد.");
    }
    return $value;
}

function classops_api_validate_cohort(string $cohortKey): void
{
    if ($cohortKey === '' || !dent_cohort_exists($cohortKey)) {
        classops_domain_error('CLASSOPS_INVALID_COHORT', 'ورودی canonical موردنظر وجود ندارد.');
    }
}

function classops_api_owner_for_read(): array
{
    $viewer = dent_require_owner();
    dent_release_session_lock();
    return $viewer;
}

function classops_api_owner_for_mutation(): array
{
    if (dent_request_method() !== 'POST') {
        classops_domain_error('CLASSOPS_METHOD_NOT_ALLOWED', 'متد این عملیات معتبر نیست.', 405);
    }
    dent_auth_session_require_csrf();
    $viewer = dent_require_owner();
    dent_release_session_lock();
    return $viewer;
}

try {
    $payload = dent_request_method() === 'POST' ? classops_api_payload() : [];
    $action = dent_request_action();
    if ($action === '' && isset($payload['action'])) {
        $action = trim((string) $payload['action']);
    }
    if ($action === '') {
        $action = 'capabilities';
    }

    if ($action === 'capabilities') {
        if (dent_request_method() !== 'GET') {
            classops_domain_error('CLASSOPS_METHOD_NOT_ALLOWED', 'متد دریافت capability معتبر نیست.', 405);
        }
        dent_json_response(classops_capabilities());
    }

    if ($action === 'domain-capabilities') {
        if (dent_request_method() !== 'GET') {
            classops_domain_error('CLASSOPS_METHOD_NOT_ALLOWED', 'متد دریافت domain capability معتبر نیست.', 405);
        }
        dent_json_response(['success' => true, 'domain' => classops_domain_capabilities()]);
    }

    if ($action === 'status') {
        if (dent_request_method() !== 'GET') {
            classops_domain_error('CLASSOPS_METHOD_NOT_ALLOWED', 'متد دریافت وضعیت معتبر نیست.', 405);
        }
        classops_api_owner_for_read();
        dent_json_response(['success' => true, 'status' => classops_status()]);
    }

    if ($action === 'list') {
        if (dent_request_method() !== 'GET') {
            classops_domain_error('CLASSOPS_METHOD_NOT_ALLOWED', 'متد دریافت فهرست معتبر نیست.', 405);
        }
        classops_api_owner_for_read();
        classops_assert_known_keys($_GET, ['action', 'cohortKey', 'type', 'status', 'limit', 'cursor']);
        $cohortKey = trim((string) ($_GET['cohortKey'] ?? ''));
        if ($cohortKey !== '') {
            classops_api_validate_cohort($cohortKey);
        }
        dent_json_response(['success' => true, 'data' => classops_list_items([
            'cohortKey' => $cohortKey,
            'type' => (string) ($_GET['type'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
            'limit' => $_GET['limit'] ?? 25,
            'cursor' => (string) ($_GET['cursor'] ?? ''),
        ])]);
    }

    if ($action === 'get') {
        if (dent_request_method() !== 'GET') {
            classops_domain_error('CLASSOPS_METHOD_NOT_ALLOWED', 'متد دریافت آیتم معتبر نیست.', 405);
        }
        classops_api_owner_for_read();
        classops_assert_known_keys($_GET, ['action', 'id']);
        dent_json_response(['success' => true, 'item' => classops_get_item((string) ($_GET['id'] ?? ''))]);
    }

    if ($action === 'revisions') {
        if (dent_request_method() !== 'GET') {
            classops_domain_error('CLASSOPS_METHOD_NOT_ALLOWED', 'متد دریافت تاریخچه معتبر نیست.', 405);
        }
        classops_api_owner_for_read();
        classops_assert_known_keys($_GET, ['action', 'id', 'limit', 'cursor']);
        dent_json_response(['success' => true, 'data' => classops_revision_history(
            (string) ($_GET['id'] ?? ''),
            (int) ($_GET['limit'] ?? 50),
            (string) ($_GET['cursor'] ?? '')
        )]);
    }

    if ($action === 'create') {
        $viewer = classops_api_owner_for_mutation();
        classops_assert_known_keys($payload, ['action', 'idempotencyKey', 'reason', 'item']);
        $item = classops_api_object_field($payload, 'item');
        classops_api_validate_cohort(trim((string) ($item['cohortKey'] ?? '')));
        dent_json_response(['success' => true] + classops_domain_store_create_item(
            $item,
            $viewer,
            (string) ($payload['idempotencyKey'] ?? ''),
            (string) ($payload['reason'] ?? '')
        ));
    }

    if ($action === 'update') {
        $viewer = classops_api_owner_for_mutation();
        classops_assert_known_keys($payload, ['action', 'idempotencyKey', 'reason', 'id', 'expectedRevision', 'patch']);
        $patch = classops_api_object_field($payload, 'patch');
        if (array_key_exists('cohortKey', $patch)) {
            classops_api_validate_cohort(trim((string) $patch['cohortKey']));
        }
        dent_json_response(['success' => true] + classops_domain_store_update_item(
            (string) ($payload['id'] ?? ''),
            (int) ($payload['expectedRevision'] ?? 0),
            $patch,
            $viewer,
            (string) ($payload['idempotencyKey'] ?? ''),
            (string) ($payload['reason'] ?? '')
        ));
    }

    if ($action === 'cancel' || $action === 'archive') {
        $viewer = classops_api_owner_for_mutation();
        classops_assert_known_keys($payload, ['action', 'idempotencyKey', 'reason', 'id', 'expectedRevision']);
        dent_json_response(['success' => true] + classops_transition_item(
            $action,
            (string) ($payload['id'] ?? ''),
            (int) ($payload['expectedRevision'] ?? 0),
            $viewer,
            (string) ($payload['idempotencyKey'] ?? ''),
            (string) ($payload['reason'] ?? '')
        ));
    }

    classops_domain_error('CLASSOPS_UNKNOWN_ACTION', 'عملیات ClassOps شناخته‌شده نیست.', 404);
} catch (DentClassOpsDomainException $exception) {
    dent_error($exception->getMessage(), $exception->httpStatus, ['code' => $exception->reasonCode]);
} catch (DentClassOpsPersistenceException $exception) {
    dent_error('ذخیره‌سازی ClassOps موقتاً در دسترس نیست؛ داده موجود دست‌نخورده باقی ماند.', 503, [
        'code' => $exception->reasonCode,
    ]);
} catch (Throwable $exception) {
    classops_persistence_log('error', [
        'action' => 'api',
        'decodeStatus' => 'not-applicable',
        'commitResult' => 'unhandled-error',
        'reasonCode' => 'CLASSOPS_INTERNAL_ERROR',
    ]);
    dent_error('خطای داخلی ClassOps رخ داد.', 500, ['code' => 'CLASSOPS_INTERNAL_ERROR']);
}
