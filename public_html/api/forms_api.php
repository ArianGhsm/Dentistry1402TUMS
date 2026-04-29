<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';

const FORMS_SCHEMA_VERSION = 1;
const FORMS_ID_PREFIX = 'frm-';
const FORMS_RESPONSE_ID_PREFIX = 'resp-';
const FORMS_SHARE_PATH = '/forms/fill/';

function forms_store_path(): string
{
    return dent_storage_path('forms/store.json');
}

function forms_default_store(): array
{
    return [
        'schemaVersion' => FORMS_SCHEMA_VERSION,
        'forms' => [],
        'responses' => [],
    ];
}

function forms_clean_id(string $value, string $prefix): string
{
    $value = trim(strtolower($value));
    if ($value === '') {
        return '';
    }

    if (!str_starts_with($value, $prefix)) {
        return '';
    }

    return preg_match('/^[a-z0-9_-]{4,80}$/', $value) === 1 ? $value : '';
}

function forms_next_id(string $prefix): string
{
    try {
        return $prefix . bin2hex(random_bytes(5));
    } catch (Throwable $error) {
        return $prefix . strtolower(str_replace('.', '', uniqid('', true)));
    }
}

function forms_clean_text($value, int $maxLength): string
{
    return dent_clean_text((string) $value, $maxLength);
}

function forms_only_digits($value): string
{
    return preg_replace('/\D+/u', '', dent_normalize_digits((string) $value)) ?? '';
}

function forms_parse_bool($value, bool $default = false): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return ((int) $value) !== 0;
    }
    $text = trim(strtolower((string) $value));
    if ($text === '') {
        return $default;
    }
    if (in_array($text, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }
    if (in_array($text, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }
    return $default;
}

function forms_parse_timestamp($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_int($value) || is_float($value)) {
        $number = (int) $value;
        if ($number <= 0) {
            return null;
        }
        return $number > 20000000000 ? (int) floor($number / 1000) : $number;
    }
    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }
    if (preg_match('/^\d+$/', $text) === 1) {
        $number = (int) $text;
        return $number > 20000000000 ? (int) floor($number / 1000) : $number;
    }
    $parsed = strtotime($text);
    return $parsed === false ? null : $parsed;
}

function forms_clean_kind(string $value): string
{
    $value = trim(strtolower($value));
    return in_array($value, ['form', 'survey', 'poll'], true) ? $value : 'form';
}

function forms_kind_label(string $kind): string
{
    $kind = forms_clean_kind($kind);
    if ($kind === 'poll') {
        return 'نظرسنجی';
    }
    if ($kind === 'survey') {
        return 'پرسشنامه';
    }
    return 'فرم';
}

function forms_clean_status(string $value): string
{
    $value = trim(strtolower($value));
    return in_array($value, ['draft', 'open', 'closed', 'archived'], true) ? $value : 'open';
}

function forms_clean_audience(string $value): string
{
    $value = trim(strtolower($value));
    $allowed = ['link', 'all-users', 'rotation-1', 'rotation-2', 'both-rotations', 'custom'];
    return in_array($value, $allowed, true) ? $value : 'link';
}

function forms_audience_label(string $audience): string
{
    $audience = forms_clean_audience($audience);
    if ($audience === 'all-users') {
        return 'همه کاربران سایت';
    }
    if ($audience === 'rotation-1') {
        return 'فقط روتیشن ۱';
    }
    if ($audience === 'rotation-2') {
        return 'فقط روتیشن ۲';
    }
    if ($audience === 'both-rotations') {
        return 'هر دو روتیشن';
    }
    if ($audience === 'custom') {
        return 'فهرست مجاز سفارشی';
    }
    return 'هر کسی که لینک را دارد';
}

function forms_clean_result_visibility(string $value): string
{
    $value = trim(strtolower($value));
    $allowed = ['live', 'after-submit', 'after-close', 'manager-only'];
    return in_array($value, $allowed, true) ? $value : 'after-submit';
}

function forms_clean_field_type(string $value): string
{
    $value = trim(strtolower($value));
    $allowed = [
        'short_text',
        'paragraph',
        'single_choice',
        'multiple_choice',
        'dropdown',
        'linear_scale',
        'multiple_choice_grid',
        'checkbox_grid',
        'date',
        'time',
        'number',
        'email',
        'phone',
        'url',
    ];
    return in_array($value, $allowed, true) ? $value : 'short_text';
}

function forms_clean_field_id(string $value, int $index): string
{
    $value = trim(strtolower($value));
    if (preg_match('/^[a-z0-9_-]{3,48}$/', $value) === 1) {
        return $value;
    }
    return 'q-' . (string) max(1, $index + 1);
}

function forms_normalize_options($raw): array
{
    $items = [];
    if (is_array($raw)) {
        $items = $raw;
    } elseif (is_string($raw)) {
        $trimmed = trim($raw);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            $items = is_array($decoded) ? $decoded : (preg_split('/\r\n|\r|\n/u', $trimmed) ?: []);
        }
    }

    $options = [];
    $seen = [];
    $index = 1;
    foreach ($items as $item) {
        $text = '';
        $id = '';
        if (is_array($item)) {
            $text = forms_clean_text($item['text'] ?? ($item['label'] ?? ''), 160);
            $id = trim(strtolower((string) ($item['id'] ?? '')));
        } else {
            $text = forms_clean_text($item, 160);
        }
        if ($text === '') {
            continue;
        }
        if (preg_match('/^[a-z0-9_-]{2,48}$/', $id) !== 1) {
            $id = 'opt-' . (string) $index;
        }
        while (isset($seen[$id])) {
            $index++;
            $id = 'opt-' . (string) $index;
        }
        $seen[$id] = true;
        $options[] = [
            'id' => $id,
            'text' => $text,
        ];
        $index++;
        if (count($options) >= 50) {
            break;
        }
    }

    return $options;
}

function forms_normalize_rows($raw): array
{
    $rows = forms_normalize_options($raw);
    if (count($rows) > 30) {
        return array_slice($rows, 0, 30);
    }
    return $rows;
}

function forms_normalize_field($raw, int $index): ?array
{
    if (!is_array($raw)) {
        return null;
    }

    $type = forms_clean_field_type((string) ($raw['type'] ?? 'short_text'));
    $label = forms_clean_text($raw['label'] ?? '', 180);
    if ($label === '') {
        return null;
    }

    $field = [
        'id' => forms_clean_field_id((string) ($raw['id'] ?? ''), $index),
        'type' => $type,
        'label' => $label,
        'help' => forms_clean_text($raw['help'] ?? '', 500),
        'required' => forms_parse_bool($raw['required'] ?? false, false),
        'options' => [],
        'rows' => [],
        'scale' => null,
    ];

    if (in_array($type, ['single_choice', 'multiple_choice', 'dropdown', 'multiple_choice_grid', 'checkbox_grid'], true)) {
        $options = forms_normalize_options($raw['options'] ?? []);
        if (count($options) < 2) {
            return null;
        }
        $field['options'] = $options;
    }

    if (in_array($type, ['multiple_choice_grid', 'checkbox_grid'], true)) {
        $rows = forms_normalize_rows($raw['rows'] ?? []);
        if (count($rows) < 1) {
            return null;
        }
        $field['rows'] = $rows;
    }

    if ($type === 'linear_scale') {
        $scale = is_array($raw['scale'] ?? null) ? $raw['scale'] : [];
        $min = max(0, min(10, (int) ($scale['min'] ?? 1)));
        $max = max($min + 1, min(10, (int) ($scale['max'] ?? 5)));
        $options = [];
        for ($value = $min; $value <= $max; $value++) {
            $options[] = [
                'id' => (string) $value,
                'text' => (string) $value,
            ];
        }
        $field['scale'] = [
            'min' => $min,
            'max' => $max,
            'minLabel' => forms_clean_text($scale['minLabel'] ?? '', 80),
            'maxLabel' => forms_clean_text($scale['maxLabel'] ?? '', 80),
        ];
        $field['options'] = $options;
    }

    return $field;
}

function forms_normalize_fields($raw, string $kind): array
{
    $items = is_array($raw) ? $raw : [];
    $fields = [];
    $seen = [];
    foreach ($items as $index => $item) {
        $field = forms_normalize_field($item, (int) $index);
        if ($field === null) {
            continue;
        }
        $baseId = (string) $field['id'];
        $id = $baseId;
        $suffix = 2;
        while (isset($seen[$id])) {
            $id = $baseId . '-' . (string) $suffix;
            $suffix++;
        }
        $field['id'] = $id;
        $seen[$id] = true;
        $fields[] = $field;
        if (count($fields) >= 80) {
            break;
        }
    }

    if ($kind === 'poll' && $fields !== []) {
        $first = $fields[0];
        if (!in_array((string) ($first['type'] ?? ''), ['single_choice', 'multiple_choice'], true)) {
            $first['type'] = 'single_choice';
        }
        $first['required'] = true;
        $fields = [$first];
    }

    return $fields;
}

function forms_normalize_student_numbers($raw): array
{
    $items = [];
    if (is_array($raw)) {
        $items = $raw;
    } else {
        $text = trim((string) $raw);
        if ($text !== '') {
            $decoded = json_decode($text, true);
            $items = is_array($decoded) ? $decoded : (preg_split('/[\s,،;]+/u', $text) ?: []);
        }
    }

    $normalized = [];
    foreach ($items as $item) {
        $studentNumber = dent_normalize_student_number((string) $item);
        if ($studentNumber !== '') {
            $normalized[$studentNumber] = true;
        }
    }

    $result = array_keys($normalized);
    sort($result, SORT_STRING);
    return $result;
}

function forms_normalize_export_settings($raw): array
{
    $raw = is_array($raw) ? $raw : [];
    $identityDefaults = ['index', 'responseId', 'submittedAt', 'participantKind', 'name', 'studentNumber', 'roleLabel', 'phone'];
    $identityColumns = [];
    foreach (($raw['identityColumns'] ?? $identityDefaults) as $item) {
        $key = trim((string) $item);
        if (in_array($key, $identityDefaults, true)) {
            $identityColumns[$key] = true;
        }
    }
    if ($identityColumns === []) {
        $identityColumns = array_fill_keys($identityDefaults, true);
    }

    $fieldIds = [];
    $rawFieldIds = $raw['fieldIds'] ?? [];
    if (is_string($rawFieldIds)) {
        $decoded = json_decode($rawFieldIds, true);
        $rawFieldIds = is_array($decoded) ? $decoded : (preg_split('/[\s,،;]+/u', $rawFieldIds) ?: []);
    }
    foreach (is_array($rawFieldIds) ? $rawFieldIds : [] as $fieldId) {
        $clean = trim(strtolower((string) $fieldId));
        if (preg_match('/^[a-z0-9_-]{3,48}$/', $clean) === 1) {
            $fieldIds[$clean] = true;
        }
    }

    return [
        'identityColumns' => array_keys($identityColumns),
        'includeAllFields' => forms_parse_bool($raw['includeAllFields'] ?? true, true),
        'fieldIds' => array_keys($fieldIds),
    ];
}

function forms_normalize_form_record(string $formId, array $form): ?array
{
    $formId = forms_clean_id($formId !== '' ? $formId : (string) ($form['id'] ?? ''), FORMS_ID_PREFIX);
    if ($formId === '') {
        return null;
    }

    $kind = forms_clean_kind((string) ($form['kind'] ?? 'form'));
    $title = forms_clean_text($form['title'] ?? '', 160);
    if ($title === '') {
        return null;
    }

    $fields = forms_normalize_fields($form['fields'] ?? [], $kind);
    if ($fields === []) {
        return null;
    }

    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $audience = forms_clean_audience((string) ($settings['audience'] ?? ($form['audience'] ?? 'link')));
    $allowedStudents = forms_normalize_student_numbers($settings['allowedStudents'] ?? ($form['allowedStudents'] ?? []));
    $managerStudentNumbers = forms_normalize_student_numbers($settings['managerStudentNumbers'] ?? []);

    $createdAt = forms_parse_timestamp($form['createdAt'] ?? null) ?? time();
    $updatedAt = forms_parse_timestamp($form['updatedAt'] ?? null) ?? $createdAt;
    $startAt = forms_parse_timestamp($settings['startAt'] ?? ($form['startAt'] ?? null));
    $endAt = forms_parse_timestamp($settings['endAt'] ?? ($form['endAt'] ?? null));
    if ($startAt !== null && $endAt !== null && $endAt <= $startAt) {
        $endAt = null;
    }

    return [
        'id' => $formId,
        'kind' => $kind,
        'title' => $title,
        'description' => forms_clean_text($form['description'] ?? '', 1400),
        'status' => forms_clean_status((string) ($form['status'] ?? 'open')),
        'createdBy' => dent_normalize_student_number((string) ($form['createdBy'] ?? '')),
        'createdAt' => $createdAt,
        'updatedAt' => max($updatedAt, $createdAt),
        'fields' => $fields,
        'settings' => [
            'audience' => $audience,
            'allowedStudents' => $allowedStudents,
            'allowRepresentativeManage' => forms_parse_bool($settings['allowRepresentativeManage'] ?? false, false),
            'managerStudentNumbers' => $managerStudentNumbers,
            'allowGuest' => forms_parse_bool($settings['allowGuest'] ?? false, false),
            'collectGuestName' => forms_parse_bool($settings['collectGuestName'] ?? true, true),
            'collectGuestPhone' => forms_parse_bool($settings['collectGuestPhone'] ?? false, false),
            'limitOneResponse' => forms_parse_bool($settings['limitOneResponse'] ?? true, true),
            'allowEditResponse' => forms_parse_bool($settings['allowEditResponse'] ?? false, false),
            'allowCreatorSubmit' => forms_parse_bool($settings['allowCreatorSubmit'] ?? true, true),
            'anonymousResponses' => forms_parse_bool($settings['anonymousResponses'] ?? false, false),
            'export' => forms_normalize_export_settings($settings['export'] ?? []),
            'resultVisibility' => forms_clean_result_visibility((string) ($settings['resultVisibility'] ?? 'after-submit')),
            'startAt' => $startAt,
            'endAt' => $endAt,
        ],
    ];
}

function forms_normalize_response_record(string $responseId, array $response, array $knownFormIds): ?array
{
    $responseId = forms_clean_id($responseId !== '' ? $responseId : (string) ($response['id'] ?? ''), FORMS_RESPONSE_ID_PREFIX);
    if ($responseId === '') {
        return null;
    }

    $formId = forms_clean_id((string) ($response['formId'] ?? ''), FORMS_ID_PREFIX);
    if ($formId === '' || !isset($knownFormIds[$formId])) {
        return null;
    }

    $identity = is_array($response['identity'] ?? null) ? $response['identity'] : [];
    $kind = (string) ($identity['kind'] ?? 'guest');
    if (!in_array($kind, ['user', 'guest'], true)) {
        $kind = 'guest';
    }
    $key = forms_clean_text($identity['key'] ?? '', 120);
    if ($key === '') {
        return null;
    }

    $answers = is_array($response['answers'] ?? null) ? $response['answers'] : [];
    $submittedAt = forms_parse_timestamp($response['submittedAt'] ?? null) ?? time();
    $updatedAt = forms_parse_timestamp($response['updatedAt'] ?? null) ?? $submittedAt;

    return [
        'id' => $responseId,
        'formId' => $formId,
        'submittedAt' => $submittedAt,
        'updatedAt' => max($updatedAt, $submittedAt),
        'identity' => [
            'kind' => $kind,
            'key' => $key,
            'studentNumber' => dent_normalize_student_number((string) ($identity['studentNumber'] ?? '')),
            'name' => forms_clean_text($identity['name'] ?? '', 140),
            'roleLabel' => forms_clean_text($identity['roleLabel'] ?? '', 80),
            'phone' => forms_only_digits($identity['phone'] ?? ''),
        ],
        'answers' => $answers,
    ];
}

function forms_load_store(): array
{
    $raw = dent_read_json_file(forms_store_path(), forms_default_store());
    if (!is_array($raw)) {
        $raw = forms_default_store();
    }

    $forms = [];
    foreach (($raw['forms'] ?? []) as $formId => $form) {
        if (!is_array($form)) {
            continue;
        }
        $normalized = forms_normalize_form_record((string) $formId, $form);
        if ($normalized !== null) {
            $forms[(string) $normalized['id']] = $normalized;
        }
    }

    $known = [];
    foreach ($forms as $formId => $_form) {
        $known[$formId] = true;
    }

    $responses = [];
    foreach (($raw['responses'] ?? []) as $responseId => $response) {
        if (!is_array($response)) {
            continue;
        }
        $normalized = forms_normalize_response_record((string) $responseId, $response, $known);
        if ($normalized !== null) {
            $responses[(string) $normalized['id']] = $normalized;
        }
    }

    uasort($forms, static fn(array $left, array $right): int => (int) ($right['updatedAt'] ?? 0) <=> (int) ($left['updatedAt'] ?? 0));
    uasort($responses, static fn(array $left, array $right): int => (int) ($right['submittedAt'] ?? 0) <=> (int) ($left['submittedAt'] ?? 0));

    return [
        'schemaVersion' => FORMS_SCHEMA_VERSION,
        'forms' => $forms,
        'responses' => $responses,
    ];
}

function forms_save_store(array $store): void
{
    $forms = is_array($store['forms'] ?? null) ? $store['forms'] : [];
    $responses = is_array($store['responses'] ?? null) ? $store['responses'] : [];

    dent_write_json_file(forms_store_path(), [
        'schemaVersion' => FORMS_SCHEMA_VERSION,
        'forms' => $forms,
        'responses' => $responses,
    ]);
}

function forms_base_origin(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }
    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $secure = $forwardedProto === 'https' || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
    return ($secure ? 'https://' : 'http://') . $host;
}

function forms_absolute_url(string $path): string
{
    $origin = forms_base_origin();
    return $origin === '' ? $path : $origin . $path;
}

function forms_share_path(string $formId): string
{
    return FORMS_SHARE_PATH . '?form=' . urlencode($formId);
}

function forms_user_payload(?array $user): ?array
{
    if ($user === null) {
        return null;
    }
    $public = dent_public_user($user);
    return [
        'studentNumber' => (string) ($public['studentNumber'] ?? ''),
        'name' => (string) ($public['name'] ?? ''),
        'role' => (string) ($public['role'] ?? 'student'),
        'roleLabel' => (string) ($public['roleLabel'] ?? ''),
        'isOwner' => (bool) ($public['isOwner'] ?? false),
        'isRepresentative' => (bool) ($public['isRepresentative'] ?? false),
    ];
}

function forms_can_create(?array $user): bool
{
    if ($user === null) {
        return false;
    }
    $role = (string) ($user['role'] ?? 'student');
    return $role === 'owner' || !empty($user['isOwner']);
}

function forms_is_representative(array $user): bool
{
    $role = (string) ($user['role'] ?? 'student');
    return $role === 'representative' || !empty($user['isRepresentative']);
}

function forms_can_manage(array $form, ?array $user): bool
{
    if ($user === null) {
        return false;
    }
    if (forms_can_create($user)) {
        return true;
    }
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '' || $studentNumber === dent_normalize_student_number((string) ($form['createdBy'] ?? ''))) {
        return $studentNumber !== '';
    }

    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $managerStudentNumbers = forms_normalize_student_numbers($settings['managerStudentNumbers'] ?? []);
    if (in_array($studentNumber, $managerStudentNumbers, true)) {
        return true;
    }

    return forms_is_representative($user) && forms_parse_bool($settings['allowRepresentativeManage'] ?? false, false);
}

function forms_can_delete(array $form, ?array $user): bool
{
    return forms_can_create($user);
}

function forms_status(array $form, ?int $now = null): string
{
    $now = $now ?? time();
    $status = forms_clean_status((string) ($form['status'] ?? 'open'));
    if ($status === 'archived') {
        return 'archived';
    }
    if ($status === 'draft') {
        return 'draft';
    }
    if ($status === 'closed') {
        return 'closed';
    }
    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $startAt = forms_parse_timestamp($settings['startAt'] ?? null);
    $endAt = forms_parse_timestamp($settings['endAt'] ?? null);
    if ($endAt !== null && $endAt <= $now) {
        return 'closed';
    }
    if ($startAt !== null && $startAt > $now) {
        return 'scheduled';
    }
    return 'open';
}

function forms_status_label(string $status): string
{
    if ($status === 'open') {
        return 'فعال';
    }
    if ($status === 'scheduled') {
        return 'زمان‌بندی‌شده';
    }
    if ($status === 'draft') {
        return 'پیش‌نویس';
    }
    if ($status === 'archived') {
        return 'بایگانی‌شده';
    }
    return 'بسته';
}

function forms_user_rotation_id(array $user): ?int
{
    $assignment = dent_user_rotation_assignment($user);
    if (!is_array($assignment)) {
        return null;
    }
    $rotationId = (int) ($assignment['rotationId'] ?? 0);
    return in_array($rotationId, [1, 2], true) ? $rotationId : null;
}

function forms_user_matches_audience(array $form, array $user): bool
{
    if (forms_can_manage($form, $user)) {
        return true;
    }

    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $audience = forms_clean_audience((string) ($settings['audience'] ?? 'link'));
    if ($audience === 'link' || $audience === 'all-users') {
        return true;
    }

    if ($audience === 'custom') {
        $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
        $allowed = forms_normalize_student_numbers($settings['allowedStudents'] ?? []);
        return $studentNumber !== '' && in_array($studentNumber, $allowed, true);
    }

    $rotationId = forms_user_rotation_id($user);
    if ($audience === 'rotation-1') {
        return $rotationId === 1;
    }
    if ($audience === 'rotation-2') {
        return $rotationId === 2;
    }
    return in_array($rotationId, [1, 2], true);
}

function forms_guest_allowed(array $form): bool
{
    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    return forms_parse_bool($settings['allowGuest'] ?? false, false);
}

function forms_viewer_can_access(array $form, ?array $user): bool
{
    if ($user !== null && forms_user_matches_audience($form, $user)) {
        return true;
    }
    return $user === null && forms_guest_allowed($form);
}

function forms_identity_key(?array $user, array $source): string
{
    if ($user !== null) {
        $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
        return $studentNumber === '' ? '' : 'user:' . $studentNumber;
    }
    $guestKey = forms_clean_text($source['guestKey'] ?? '', 80);
    if (preg_match('/^[a-z0-9_-]{8,80}$/i', $guestKey) !== 1) {
        $guestKey = 'guest-' . substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . microtime(true)), 0, 24);
    }
    return 'guest:' . strtolower($guestKey);
}

function forms_identity_payload(?array $user, array $source): array
{
    if ($user !== null) {
        $public = dent_public_user($user);
        return [
            'kind' => 'user',
            'key' => forms_identity_key($user, $source),
            'studentNumber' => dent_normalize_student_number((string) ($public['studentNumber'] ?? '')),
            'name' => forms_clean_text($public['name'] ?? '', 140),
            'roleLabel' => forms_clean_text($public['roleLabel'] ?? '', 80),
            'phone' => forms_only_digits($user['phoneNumber'] ?? ''),
        ];
    }

    return [
        'kind' => 'guest',
        'key' => forms_identity_key(null, $source),
        'studentNumber' => '',
        'name' => forms_clean_text($source['guestName'] ?? '', 140),
        'roleLabel' => 'مهمان',
        'phone' => forms_only_digits($source['guestPhone'] ?? ''),
    ];
}

function forms_response_for_identity(array $store, string $formId, string $identityKey): ?array
{
    foreach ($store['responses'] as $response) {
        if (!is_array($response)) {
            continue;
        }
        if ((string) ($response['formId'] ?? '') !== $formId) {
            continue;
        }
        $identity = is_array($response['identity'] ?? null) ? $response['identity'] : [];
        if ((string) ($identity['key'] ?? '') === $identityKey) {
            return $response;
        }
    }
    return null;
}

function forms_responses_for_form(array $store, string $formId): array
{
    $responses = [];
    foreach ($store['responses'] as $response) {
        if (is_array($response) && (string) ($response['formId'] ?? '') === $formId) {
            $responses[] = $response;
        }
    }
    usort($responses, static fn(array $left, array $right): int => (int) ($right['submittedAt'] ?? 0) <=> (int) ($left['submittedAt'] ?? 0));
    return $responses;
}

function forms_option_map(array $field): array
{
    $map = [];
    foreach ((array) ($field['options'] ?? []) as $option) {
        if (is_array($option)) {
            $id = (string) ($option['id'] ?? '');
            if ($id !== '') {
                $map[$id] = (string) ($option['text'] ?? $id);
            }
        }
    }
    return $map;
}

function forms_row_map(array $field): array
{
    $map = [];
    foreach ((array) ($field['rows'] ?? []) as $row) {
        if (is_array($row)) {
            $id = (string) ($row['id'] ?? '');
            if ($id !== '') {
                $map[$id] = (string) ($row['text'] ?? $id);
            }
        }
    }
    return $map;
}

function forms_normalize_answer(array $field, $raw)
{
    $type = (string) ($field['type'] ?? 'short_text');
    $required = (bool) ($field['required'] ?? false);
    $label = (string) ($field['label'] ?? 'فیلد');

    if ($type === 'multiple_choice') {
        $items = [];
        if (is_array($raw)) {
            $items = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $items = is_array($decoded) ? $decoded : preg_split('/[\s,،;]+/u', trim($raw));
        }
        $allowed = forms_option_map($field);
        $selected = [];
        foreach ($items as $item) {
            $id = trim((string) $item);
            if ($id !== '' && isset($allowed[$id])) {
                $selected[$id] = true;
            }
        }
        $values = array_keys($selected);
        if ($required && $values === []) {
            dent_error('پاسخ «' . $label . '» الزامی است.', 422);
        }
        return $values;
    }

    if (in_array($type, ['multiple_choice_grid', 'checkbox_grid'], true)) {
        $rawMap = is_array($raw) ? $raw : [];
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $rawMap = is_array($decoded) ? $decoded : [];
        }
        $rows = forms_row_map($field);
        $allowed = forms_option_map($field);
        $answer = [];
        foreach ($rows as $rowId => $_rowText) {
            $rowValue = $rawMap[$rowId] ?? null;
            if ($type === 'checkbox_grid') {
                $rowItems = is_array($rowValue) ? $rowValue : [];
                $selected = [];
                foreach ($rowItems as $item) {
                    $id = trim((string) $item);
                    if ($id !== '' && isset($allowed[$id])) {
                        $selected[$id] = true;
                    }
                }
                $answer[$rowId] = array_keys($selected);
            } else {
                $id = trim((string) $rowValue);
                $answer[$rowId] = $id !== '' && isset($allowed[$id]) ? $id : '';
            }
        }
        if ($required) {
            foreach ($answer as $rowAnswer) {
                if (is_array($rowAnswer) ? $rowAnswer === [] : $rowAnswer === '') {
                    dent_error('پاسخ همه ردیف‌های «' . $label . '» الزامی است.', 422);
                }
            }
        }
        return $answer;
    }

    $value = forms_clean_text($raw ?? '', $type === 'paragraph' ? 4000 : 700);
    if ($required && $value === '') {
        dent_error('پاسخ «' . $label . '» الزامی است.', 422);
    }
    if ($value === '') {
        return '';
    }

    if (in_array($type, ['single_choice', 'dropdown', 'linear_scale'], true)) {
        $allowed = forms_option_map($field);
        if (!isset($allowed[$value])) {
            dent_error('گزینه انتخاب‌شده برای «' . $label . '» معتبر نیست.', 422);
        }
        return $value;
    }

    if ($type === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
        dent_error('ایمیل واردشده برای «' . $label . '» معتبر نیست.', 422);
    }
    if ($type === 'url' && filter_var($value, FILTER_VALIDATE_URL) === false) {
        dent_error('لینک واردشده برای «' . $label . '» معتبر نیست.', 422);
    }
    if ($type === 'phone') {
        $digits = forms_only_digits($value);
        if ($digits === '' || strlen($digits) < 8 || strlen($digits) > 14) {
            dent_error('شماره تماس واردشده برای «' . $label . '» معتبر نیست.', 422);
        }
        return $digits;
    }
    if ($type === 'number') {
        $normalized = dent_normalize_digits($value);
        if (!is_numeric($normalized)) {
            dent_error('عدد واردشده برای «' . $label . '» معتبر نیست.', 422);
        }
        return (string) $normalized;
    }
    if ($type === 'date' && strtotime($value) === false) {
        dent_error('تاریخ واردشده برای «' . $label . '» معتبر نیست.', 422);
    }

    return $value;
}

function forms_collect_answers(array $form, array $source): array
{
    $rawAnswers = $source['answers'] ?? [];
    if (is_string($rawAnswers)) {
        $decoded = json_decode($rawAnswers, true);
        $rawAnswers = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($rawAnswers)) {
        $rawAnswers = [];
    }

    $answers = [];
    foreach ((array) ($form['fields'] ?? []) as $field) {
        if (!is_array($field)) {
            continue;
        }
        $fieldId = (string) ($field['id'] ?? '');
        if ($fieldId === '') {
            continue;
        }
        $answers[$fieldId] = forms_normalize_answer($field, $rawAnswers[$fieldId] ?? null);
    }
    return $answers;
}

function forms_response_payload(array $response, array $form): array
{
    $fieldsById = [];
    foreach ((array) ($form['fields'] ?? []) as $field) {
        if (is_array($field) && isset($field['id'])) {
            $fieldsById[(string) $field['id']] = $field;
        }
    }

    $answerPayload = [];
    foreach ((array) ($response['answers'] ?? []) as $fieldId => $answer) {
        $field = $fieldsById[(string) $fieldId] ?? null;
        $answerPayload[] = [
            'fieldId' => (string) $fieldId,
            'label' => is_array($field) ? (string) ($field['label'] ?? $fieldId) : (string) $fieldId,
            'type' => is_array($field) ? (string) ($field['type'] ?? 'short_text') : 'short_text',
            'value' => $answer,
            'displayValue' => forms_answer_display_value(is_array($field) ? $field : [], $answer),
        ];
    }

    return [
        'id' => (string) ($response['id'] ?? ''),
        'formId' => (string) ($response['formId'] ?? ''),
        'submittedAt' => (int) ($response['submittedAt'] ?? 0),
        'updatedAt' => (int) ($response['updatedAt'] ?? 0),
        'identity' => is_array($response['identity'] ?? null) ? $response['identity'] : [],
        'answers' => $answerPayload,
    ];
}

function forms_answer_display_value(array $field, $answer): string
{
    if ($answer === null || $answer === '') {
        return '';
    }
    $optionMap = forms_option_map($field);
    if (is_array($answer)) {
        $type = (string) ($field['type'] ?? '');
        if (in_array($type, ['multiple_choice_grid', 'checkbox_grid'], true)) {
            $rowMap = forms_row_map($field);
            $rows = [];
            foreach ($answer as $rowId => $rowAnswer) {
                $rowLabel = $rowMap[(string) $rowId] ?? (string) $rowId;
                if (is_array($rowAnswer)) {
                    $parts = [];
                    foreach ($rowAnswer as $item) {
                        $key = (string) $item;
                        $parts[] = $optionMap[$key] ?? $key;
                    }
                    $rows[] = $rowLabel . ': ' . implode('، ', $parts);
                } else {
                    $key = (string) $rowAnswer;
                    $rows[] = $rowLabel . ': ' . ($optionMap[$key] ?? $key);
                }
            }
            return implode(' | ', array_filter($rows, static fn(string $item): bool => trim($item) !== ''));
        }
        $parts = [];
        foreach ($answer as $item) {
            $key = (string) $item;
            $parts[] = $optionMap[$key] ?? $key;
        }
        return implode('، ', $parts);
    }
    $key = (string) $answer;
    return $optionMap[$key] ?? $key;
}

function forms_poll_results(array $store, array $form, ?array $viewer, ?string $identityKey = null): array
{
    if ((string) ($form['kind'] ?? '') !== 'poll') {
        return ['visible' => false, 'options' => [], 'totalResponses' => 0, 'hiddenReason' => ''];
    }

    $fields = (array) ($form['fields'] ?? []);
    $field = is_array($fields[0] ?? null) ? $fields[0] : null;
    if ($field === null) {
        return ['visible' => false, 'options' => [], 'totalResponses' => 0, 'hiddenReason' => ''];
    }

    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $visibility = forms_clean_result_visibility((string) ($settings['resultVisibility'] ?? 'after-submit'));
    $status = forms_status($form);
    $canManage = forms_can_manage($form, $viewer);
    $hasSubmitted = false;
    if ($identityKey !== null && $identityKey !== '') {
        $hasSubmitted = forms_response_for_identity($store, (string) ($form['id'] ?? ''), $identityKey) !== null;
    }

    $visible = $canManage || $visibility === 'live' || ($visibility === 'after-close' && $status === 'closed') || ($visibility === 'after-submit' && $hasSubmitted);
    $responses = forms_responses_for_form($store, (string) ($form['id'] ?? ''));
    $counts = [];
    foreach ((array) ($field['options'] ?? []) as $option) {
        if (is_array($option)) {
            $counts[(string) ($option['id'] ?? '')] = 0;
        }
    }

    foreach ($responses as $response) {
        $answers = is_array($response['answers'] ?? null) ? $response['answers'] : [];
        $answer = $answers[(string) ($field['id'] ?? '')] ?? null;
        if (is_array($answer)) {
            foreach ($answer as $id) {
                $id = (string) $id;
                if (isset($counts[$id])) {
                    $counts[$id]++;
                }
            }
        } else {
            $id = (string) $answer;
            if (isset($counts[$id])) {
                $counts[$id]++;
            }
        }
    }

    $total = count($responses);
    $options = [];
    foreach ((array) ($field['options'] ?? []) as $option) {
        if (!is_array($option)) {
            continue;
        }
        $id = (string) ($option['id'] ?? '');
        $count = $counts[$id] ?? 0;
        $options[] = [
            'id' => $id,
            'text' => (string) ($option['text'] ?? $id),
            'count' => $visible ? $count : null,
            'percent' => $visible && $total > 0 ? round(($count * 1000) / $total) / 10 : null,
        ];
    }

    $hiddenReason = '';
    if (!$visible) {
        $hiddenReason = $visibility === 'after-close'
            ? 'نتایج بعد از بسته‌شدن نظرسنجی نمایش داده می‌شود.'
            : 'نتایج فعلاً فقط برای سازنده/مدیر قابل مشاهده است.';
    }

    return [
        'visible' => $visible,
        'totalResponses' => $visible ? $total : null,
        'hiddenReason' => $hiddenReason,
        'options' => $options,
    ];
}

function forms_form_payload(array $store, array $form, ?array $viewer = null, bool $includeFields = true, ?string $identityKeyOverride = null): array
{
    $formId = (string) ($form['id'] ?? '');
    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $status = forms_status($form);
    $responses = forms_responses_for_form($store, $formId);
    $identityKey = $identityKeyOverride !== null ? $identityKeyOverride : ($viewer !== null ? forms_identity_key($viewer, []) : null);
    $alreadySubmitted = $identityKey !== null && $identityKey !== ''
        ? forms_response_for_identity($store, $formId, $identityKey) !== null
        : false;
    $limitOneResponse = forms_parse_bool($settings['limitOneResponse'] ?? true, true);
    $canSubmit = $status === 'open' && forms_viewer_can_access($form, $viewer);
    if ($canSubmit && $viewer !== null && forms_can_manage($form, $viewer) && !forms_parse_bool($settings['allowCreatorSubmit'] ?? true, true)) {
        $canSubmit = false;
    }
    if ($canSubmit && $limitOneResponse && $alreadySubmitted) {
        $canSubmit = false;
    }

    return [
        'id' => $formId,
        'kind' => (string) ($form['kind'] ?? 'form'),
        'kindLabel' => forms_kind_label((string) ($form['kind'] ?? 'form')),
        'title' => (string) ($form['title'] ?? ''),
        'description' => (string) ($form['description'] ?? ''),
        'status' => $status,
        'statusLabel' => forms_status_label($status),
        'createdBy' => (string) ($form['createdBy'] ?? ''),
        'createdAt' => (int) ($form['createdAt'] ?? 0),
        'updatedAt' => (int) ($form['updatedAt'] ?? 0),
        'sharePath' => forms_share_path($formId),
        'shareUrl' => forms_absolute_url(forms_share_path($formId)),
        'responseCount' => count($responses),
        'fields' => $includeFields ? (array) ($form['fields'] ?? []) : [],
        'settings' => [
            'audience' => forms_clean_audience((string) ($settings['audience'] ?? 'link')),
            'audienceLabel' => forms_audience_label((string) ($settings['audience'] ?? 'link')),
            'allowedStudents' => forms_normalize_student_numbers($settings['allowedStudents'] ?? []),
            'allowRepresentativeManage' => forms_parse_bool($settings['allowRepresentativeManage'] ?? false, false),
            'managerStudentNumbers' => forms_normalize_student_numbers($settings['managerStudentNumbers'] ?? []),
            'allowGuest' => forms_parse_bool($settings['allowGuest'] ?? false, false),
            'collectGuestName' => forms_parse_bool($settings['collectGuestName'] ?? true, true),
            'collectGuestPhone' => forms_parse_bool($settings['collectGuestPhone'] ?? false, false),
            'limitOneResponse' => forms_parse_bool($settings['limitOneResponse'] ?? true, true),
            'allowEditResponse' => forms_parse_bool($settings['allowEditResponse'] ?? false, false),
            'allowCreatorSubmit' => forms_parse_bool($settings['allowCreatorSubmit'] ?? true, true),
            'anonymousResponses' => forms_parse_bool($settings['anonymousResponses'] ?? false, false),
            'export' => forms_normalize_export_settings($settings['export'] ?? []),
            'resultVisibility' => forms_clean_result_visibility((string) ($settings['resultVisibility'] ?? 'after-submit')),
            'startAt' => forms_parse_timestamp($settings['startAt'] ?? null),
            'endAt' => forms_parse_timestamp($settings['endAt'] ?? null),
        ],
        'permissions' => [
            'canManage' => forms_can_manage($form, $viewer),
            'canDelete' => forms_can_delete($form, $viewer),
            'canCreate' => forms_can_create($viewer),
            'canSubmit' => $canSubmit,
            'guestAllowed' => forms_guest_allowed($form),
            'alreadySubmitted' => $alreadySubmitted,
        ],
        'results' => forms_poll_results($store, $form, $viewer, $identityKey),
    ];
}

function forms_request_payload(): array
{
    $raw = $_POST['payload'] ?? '';
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

function forms_build_form_from_payload(array $payload, array $user, ?array $existing = null): array
{
    $kind = forms_clean_kind((string) ($payload['kind'] ?? ($existing['kind'] ?? 'form')));
    $fields = forms_normalize_fields($payload['fields'] ?? ($existing['fields'] ?? []), $kind);
    if ($fields === []) {
        dent_error('حداقل یک پرسش معتبر لازم است.', 422);
    }

    $title = forms_clean_text($payload['title'] ?? ($existing['title'] ?? ''), 160);
    if ($title === '') {
        dent_error('عنوان فرم الزامی است.', 422);
    }

    $settingsRaw = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
    $existingSettings = is_array($existing['settings'] ?? null) ? $existing['settings'] : [];
    $settingsRaw = array_merge($existingSettings, $settingsRaw);
    if (!forms_can_create($user) && $existing !== null) {
        $settingsRaw['allowRepresentativeManage'] = $existingSettings['allowRepresentativeManage'] ?? false;
        $settingsRaw['managerStudentNumbers'] = $existingSettings['managerStudentNumbers'] ?? [];
    }
    $startAt = forms_parse_timestamp($settingsRaw['startAt'] ?? null);
    $endAt = forms_parse_timestamp($settingsRaw['endAt'] ?? null);
    if ($startAt !== null && $endAt !== null && $endAt <= $startAt) {
        dent_error('زمان پایان باید بعد از زمان شروع باشد.', 422);
    }

    $now = time();
    $formId = $existing !== null ? (string) ($existing['id'] ?? '') : forms_next_id(FORMS_ID_PREFIX);
    $createdBy = $existing !== null
        ? dent_normalize_student_number((string) ($existing['createdBy'] ?? ''))
        : dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));

    return [
        'id' => $formId,
        'kind' => $kind,
        'title' => $title,
        'description' => forms_clean_text($payload['description'] ?? ($existing['description'] ?? ''), 1400),
        'status' => forms_clean_status((string) ($payload['status'] ?? ($existing['status'] ?? 'open'))),
        'createdBy' => $createdBy,
        'createdAt' => $existing !== null ? (int) ($existing['createdAt'] ?? $now) : $now,
        'updatedAt' => $now,
        'fields' => $fields,
        'settings' => [
            'audience' => forms_clean_audience((string) ($settingsRaw['audience'] ?? 'link')),
            'allowedStudents' => forms_normalize_student_numbers($settingsRaw['allowedStudents'] ?? []),
            'allowRepresentativeManage' => forms_parse_bool($settingsRaw['allowRepresentativeManage'] ?? false, false),
            'managerStudentNumbers' => forms_normalize_student_numbers($settingsRaw['managerStudentNumbers'] ?? []),
            'allowGuest' => forms_parse_bool($settingsRaw['allowGuest'] ?? false, false),
            'collectGuestName' => forms_parse_bool($settingsRaw['collectGuestName'] ?? true, true),
            'collectGuestPhone' => forms_parse_bool($settingsRaw['collectGuestPhone'] ?? false, false),
            'limitOneResponse' => forms_parse_bool($settingsRaw['limitOneResponse'] ?? true, true),
            'allowEditResponse' => forms_parse_bool($settingsRaw['allowEditResponse'] ?? false, false),
            'allowCreatorSubmit' => forms_parse_bool($settingsRaw['allowCreatorSubmit'] ?? true, true),
            'anonymousResponses' => forms_parse_bool($settingsRaw['anonymousResponses'] ?? false, false),
            'export' => forms_normalize_export_settings($settingsRaw['export'] ?? []),
            'resultVisibility' => forms_clean_result_visibility((string) ($settingsRaw['resultVisibility'] ?? 'after-submit')),
            'startAt' => $startAt,
            'endAt' => $endAt,
        ],
    ];
}

function forms_active_count_for_user(array $store, array $user): int
{
    $count = 0;
    foreach ($store['forms'] as $form) {
        if (!is_array($form) || forms_status($form) !== 'open') {
            continue;
        }
        if (forms_viewer_can_access($form, $user)) {
            $count++;
        }
    }
    return $count;
}

function forms_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function forms_xlsx_column_name(int $columnNumber): string
{
    $name = '';
    while ($columnNumber > 0) {
        $remainder = ($columnNumber - 1) % 26;
        $name = chr(65 + $remainder) . $name;
        $columnNumber = intdiv($columnNumber - 1, 26);
    }
    return $name;
}

function forms_xlsx_cell(int $row, int $column, int $style, string $value): string
{
    $ref = forms_xlsx_column_name($column) . (string) $row;
    return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . forms_xml_escape($value) . '</t></is></c>';
}

function forms_xlsx_row(int $row, array $values, int $style, ?float $height = null): string
{
    $attrs = ' r="' . $row . '"';
    if ($height !== null) {
        $attrs .= ' ht="' . rtrim(rtrim(number_format($height, 2, '.', ''), '0'), '.') . '" customHeight="1"';
    }
    $cells = [];
    $column = 1;
    foreach ($values as $value) {
        $cells[] = forms_xlsx_cell($row, $column, $style, (string) $value);
        $column++;
    }
    return '<row' . $attrs . '>' . implode('', $cells) . '</row>';
}

function forms_xlsx_styles_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="3"><font><sz val="11"/><name val="B Nazanin"/></font><font><b/><sz val="11"/><name val="B Nazanin"/></font><font><b/><sz val="15"/><name val="B Nazanin"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="5">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" applyAlignment="1"><alignment horizontal="right" vertical="center" wrapText="1" readingOrder="2"/></xf>'
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1" readingOrder="2"/></xf>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1" readingOrder="2"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyAlignment="1"><alignment horizontal="right" vertical="top" wrapText="1" readingOrder="2"/></xf>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyAlignment="1"><alignment horizontal="right" vertical="center" wrapText="1" readingOrder="2"/></xf>'
        . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
}

function forms_xlsx_sheet_xml(string $title, array $headers, array $rows): string
{
    $columnCount = max(1, count($headers));
    $lastColumn = forms_xlsx_column_name($columnCount);
    $sheetRows = [];
    $sheetRows[] = forms_xlsx_row(1, [$title], 1, 32.0);
    $sheetRows[] = '<row r="2"></row>';
    $sheetRows[] = forms_xlsx_row(3, $headers, 2, 24.0);
    $rowNumber = 4;
    foreach ($rows as $row) {
        while (count($row) < $columnCount) {
            $row[] = '';
        }
        $sheetRows[] = forms_xlsx_row($rowNumber, array_slice($row, 0, $columnCount), 3, 24.0);
        $rowNumber++;
    }
    $signatureRow = $rowNumber + 1;
    $sheetRows[] = forms_xlsx_row($signatureRow, ['بخش تایید و امضا'], 4, 25.0);
    $sheetRows[] = forms_xlsx_row($signatureRow + 1, ['تهیه‌کننده خروجی: ماژول فرم‌ها و نظرسنجی‌ها'], 0, 24.0);

    $cols = [];
    for ($i = 1; $i <= $columnCount; $i++) {
        $width = $i <= 3 ? 16 : 24;
        $cols[] = '<col min="' . $i . '" max="' . $i . '" width="' . $width . '" customWidth="1"/>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
        . '<dimension ref="A1:' . $lastColumn . (string) ($signatureRow + 1) . '"/>'
        . '<sheetViews><sheetView workbookViewId="0" rightToLeft="1"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="22"/><cols>' . implode('', $cols) . '</cols>'
        . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
        . '<mergeCells count="2"><mergeCell ref="A1:' . $lastColumn . '1"/><mergeCell ref="A' . $signatureRow . ':' . $lastColumn . $signatureRow . '"/></mergeCells>'
        . '<autoFilter ref="A3:' . $lastColumn . max(3, $rowNumber - 1) . '"/>'
        . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
        . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
}

function forms_zip_dos_datetime(): array
{
    $parts = getdate();
    $year = max(1980, (int) $parts['year']);
    $dosTime = (((int) $parts['hours'] & 0x1F) << 11) | (((int) $parts['minutes'] & 0x3F) << 5) | ((int) floor((int) $parts['seconds'] / 2) & 0x1F);
    $dosDate = ((($year - 1980) & 0x7F) << 9) | (((int) $parts['mon'] & 0x0F) << 5) | ((int) $parts['mday'] & 0x1F);
    return [$dosTime, $dosDate];
}

function forms_build_zip_archive(array $files): string
{
    [$dosTime, $dosDate] = forms_zip_dos_datetime();
    $localData = '';
    $centralDirectory = '';
    $offset = 0;
    $entryCount = 0;
    foreach ($files as $name => $content) {
        $fileName = str_replace('\\', '/', (string) $name);
        $data = (string) $content;
        $crc = (int) sprintf('%u', crc32($data));
        $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, strlen($data), strlen($data), strlen($fileName), 0);
        $localData .= $localHeader . $fileName . $data;
        $centralHeader = pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $dosTime, $dosDate, $crc, strlen($data), strlen($data), strlen($fileName), 0, 0, 0, 0, 0, $offset);
        $centralDirectory .= $centralHeader . $fileName;
        $offset += strlen($localHeader) + strlen($fileName) + strlen($data);
        $entryCount++;
    }
    return $localData . $centralDirectory . pack('VvvvvVVv', 0x06054b50, 0, 0, $entryCount, $entryCount, strlen($centralDirectory), strlen($localData), 0);
}

function forms_export_xlsx(array $form, array $responses, string $mode = 'responses'): void
{
    $mode = in_array($mode, ['responses', 'summary', 'official'], true) ? $mode : 'responses';
    $fields = array_values(array_filter((array) ($form['fields'] ?? []), static fn($field): bool => is_array($field)));

    if ($mode === 'summary') {
        $headers = ['پرسش', 'گزینه/مقدار', 'تعداد'];
        $rows = [];
        foreach ($fields as $field) {
            $fieldId = (string) ($field['id'] ?? '');
            $type = (string) ($field['type'] ?? '');
            if (!in_array($type, ['single_choice', 'multiple_choice', 'dropdown', 'linear_scale'], true)) {
                continue;
            }
            $counts = [];
            foreach (forms_option_map($field) as $optionId => $_label) {
                $counts[$optionId] = 0;
            }
            foreach ($responses as $response) {
                $answers = is_array($response['answers'] ?? null) ? $response['answers'] : [];
                $answer = $answers[$fieldId] ?? null;
                foreach (is_array($answer) ? $answer : [$answer] as $item) {
                    $key = (string) $item;
                    if (isset($counts[$key])) {
                        $counts[$key]++;
                    }
                }
            }
            foreach ($counts as $optionId => $count) {
                $rows[] = [
                    (string) ($field['label'] ?? ''),
                    forms_answer_display_value($field, $optionId),
                    (string) $count,
                ];
            }
        }
    } else {
        $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
        $export = forms_normalize_export_settings($settings['export'] ?? []);
        $identityLabels = [
            'index' => 'ردیف',
            'responseId' => 'شناسه پاسخ',
            'submittedAt' => 'زمان ثبت',
            'participantKind' => 'نوع شرکت‌کننده',
            'name' => 'نام',
            'studentNumber' => 'شماره دانشجویی',
            'roleLabel' => 'نقش',
            'phone' => 'تلفن',
        ];
        $identityColumns = (array) ($export['identityColumns'] ?? array_keys($identityLabels));
        $headers = [];
        foreach ($identityColumns as $column) {
            if (isset($identityLabels[$column])) {
                $headers[] = $identityLabels[$column];
            }
        }

        $fieldIdFilter = [];
        foreach ((array) ($export['fieldIds'] ?? []) as $fieldId) {
            $fieldIdFilter[(string) $fieldId] = true;
        }
        $includeAllFields = $mode === 'official' || forms_parse_bool($export['includeAllFields'] ?? true, true);
        $exportFields = [];
        foreach ($fields as $field) {
            $fieldId = (string) ($field['id'] ?? '');
            if ($fieldId === '') {
                continue;
            }
            if ($includeAllFields || isset($fieldIdFilter[$fieldId])) {
                $exportFields[] = $field;
                $headers[] = (string) ($field['label'] ?? '');
            }
        }

        $rows = [];
        $index = 1;
        foreach ($responses as $response) {
            $identity = is_array($response['identity'] ?? null) ? $response['identity'] : [];
            $identityValues = [
                'index' => (string) $index,
                'responseId' => (string) ($response['id'] ?? ''),
                'submittedAt' => date('Y-m-d H:i:s', (int) ($response['submittedAt'] ?? time())),
                'participantKind' => (string) ($identity['kind'] ?? '') === 'user' ? 'کاربر سایت' : 'مهمان',
                'name' => (string) ($identity['name'] ?? ''),
                'studentNumber' => (string) ($identity['studentNumber'] ?? ''),
                'roleLabel' => (string) ($identity['roleLabel'] ?? ''),
                'phone' => (string) ($identity['phone'] ?? ''),
            ];
            $row = [];
            foreach ($identityColumns as $column) {
                if (array_key_exists((string) $column, $identityValues)) {
                    $row[] = $identityValues[(string) $column];
                }
            }
            $answers = is_array($response['answers'] ?? null) ? $response['answers'] : [];
            foreach ($exportFields as $field) {
                $fieldId = (string) ($field['id'] ?? '');
                $row[] = forms_answer_display_value($field, $answers[$fieldId] ?? '');
            }
            $rows[] = $row;
            $index++;
        }
    }

    $modeLabel = $mode === 'summary' ? 'خلاصه' : ($mode === 'official' ? 'خروجی رسمی' : 'پاسخ‌ها');
    $title = $modeLabel . ' - ' . forms_kind_label((string) ($form['kind'] ?? 'form')) . ' - ' . (string) ($form['title'] ?? '');
    $sheetXml = forms_xlsx_sheet_xml($title, $headers, $rows);
    $now = gmdate('Y-m-d\TH:i:s\Z');
    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
        'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Dentistry1402 Forms</Application></Properties>',
        'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>' . forms_xml_escape($title) . '</dc:title><dc:creator>Dentistry1402 Forms</dc:creator><cp:lastModifiedBy>Dentistry1402 Forms</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified></cp:coreProperties>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView rightToLeft="1"/></bookViews><sheets><sheet name="Responses" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => forms_xlsx_styles_xml(),
        'xl/worksheets/sheet1.xml' => $sheetXml,
    ];

    if (class_exists('ZipArchive')) {
        $tmpPath = tempnam(sys_get_temp_dir(), 'forms-xlsx-');
        if ($tmpPath === false) {
            dent_error('ساخت فایل خروجی انجام نشد.', 500);
        }
        $zip = new ZipArchive();
        if ($zip->open($tmpPath, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            @unlink($tmpPath);
            dent_error('ساخت فایل خروجی انجام نشد.', 500);
        }
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $binary = file_get_contents($tmpPath);
        @unlink($tmpPath);
        if ($binary === false) {
            dent_error('خواندن فایل خروجی انجام نشد.', 500);
        }
    } else {
        $binary = forms_build_zip_archive($files);
    }

    $fileName = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($form['id'] ?? 'forms-export')) . '-' . $mode . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . (string) strlen($binary));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo $binary;
    exit;
}

$action = dent_request_action();

if ($action === 'session') {
    $user = dent_current_user();
    $store = forms_load_store();
    dent_json_response([
        'success' => true,
        'viewer' => forms_user_payload($user),
        'canCreate' => forms_can_create($user),
        'activeCount' => $user !== null ? forms_active_count_for_user($store, $user) : 0,
        'legacyDis' => [
            'publicPath' => '/dis-request/',
            'managePath' => '/dis-request/manage/',
        ],
    ]);
}

if ($action === 'list') {
    $user = dent_require_user();
    $store = forms_load_store();
    $forms = [];
    foreach ($store['forms'] as $form) {
        if (!is_array($form)) {
            continue;
        }
        if (forms_can_create($user) || forms_viewer_can_access($form, $user)) {
            $forms[] = forms_form_payload($store, $form, $user, false);
        }
    }
    dent_json_response([
        'success' => true,
        'forms' => $forms,
        'canCreate' => forms_can_create($user),
    ]);
}

if ($action === 'create') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ساخت فرم نامعتبر است.', 405);
    }
    $user = dent_require_user();
    if (!forms_can_create($user)) {
        dent_error('ساخت فرم و نظرسنجی فقط برای مالک یا نماینده فعال است.', 403);
    }
    $store = forms_load_store();
    $form = forms_build_form_from_payload(forms_request_payload(), $user, null);
    $store['forms'][(string) $form['id']] = $form;
    forms_save_store($store);
    dent_json_response([
        'success' => true,
        'message' => forms_kind_label((string) $form['kind']) . ' ساخته شد.',
        'form' => forms_form_payload($store, $form, $user, true),
        'forms' => array_values(array_map(static fn(array $item): array => forms_form_payload($store, $item, $user, false), $store['forms'])),
    ]);
}

if ($action === 'update') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ویرایش فرم نامعتبر است.', 405);
    }
    $user = dent_require_user();
    $formId = forms_clean_id((string) ($_POST['formId'] ?? ''), FORMS_ID_PREFIX);
    if ($formId === '') {
        dent_error('شناسه فرم نامعتبر است.', 422);
    }
    $store = forms_load_store();
    $existing = $store['forms'][$formId] ?? null;
    if (!is_array($existing)) {
        dent_error('فرم پیدا نشد.', 404);
    }
    if (!forms_can_manage($existing, $user)) {
        dent_error('اجازه ویرایش این فرم را ندارید.', 403);
    }
    $payload = forms_request_payload();
    $updated = forms_build_form_from_payload($payload, $user, $existing);
    $updated['id'] = $formId;
    $store['forms'][$formId] = $updated;
    forms_save_store($store);
    dent_json_response([
        'success' => true,
        'message' => 'تنظیمات فرم ذخیره شد.',
        'form' => forms_form_payload($store, $updated, $user, true),
    ]);
}

if ($action === 'setStatus') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تغییر وضعیت نامعتبر است.', 405);
    }
    $user = dent_require_user();
    $formId = forms_clean_id((string) ($_POST['formId'] ?? ''), FORMS_ID_PREFIX);
    $status = forms_clean_status((string) ($_POST['status'] ?? 'open'));
    if ($formId === '') {
        dent_error('شناسه فرم نامعتبر است.', 422);
    }
    $store = forms_load_store();
    $form = $store['forms'][$formId] ?? null;
    if (!is_array($form)) {
        dent_error('فرم پیدا نشد.', 404);
    }
    if (!forms_can_manage($form, $user)) {
        dent_error('اجازه مدیریت این فرم را ندارید.', 403);
    }
    $form['status'] = $status;
    $form['updatedAt'] = time();
    $store['forms'][$formId] = $form;
    forms_save_store($store);
    dent_json_response([
        'success' => true,
        'form' => forms_form_payload($store, $form, $user, false),
    ]);
}

if ($action === 'delete') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد حذف فرم نامعتبر است.', 405);
    }
    $user = dent_require_user();
    $formId = forms_clean_id((string) ($_POST['formId'] ?? ''), FORMS_ID_PREFIX);
    if ($formId === '') {
        dent_error('شناسه فرم نامعتبر است.', 422);
    }
    $store = forms_load_store();
    $form = $store['forms'][$formId] ?? null;
    if (!is_array($form)) {
        dent_error('فرم پیدا نشد.', 404);
    }
    if (!forms_can_delete($form, $user)) {
        dent_error('حذف کامل فرم فقط برای مالک مجاز است.', 403);
    }
    unset($store['forms'][$formId]);
    foreach ($store['responses'] as $responseId => $response) {
        if (is_array($response) && (string) ($response['formId'] ?? '') === $formId) {
            unset($store['responses'][$responseId]);
        }
    }
    forms_save_store($store);
    dent_json_response([
        'success' => true,
        'formId' => $formId,
    ]);
}

if ($action === 'get') {
    $store = forms_load_store();
    $formId = forms_clean_id((string) ($_GET['form'] ?? $_GET['formId'] ?? $_POST['formId'] ?? ''), FORMS_ID_PREFIX);
    if ($formId === '') {
        dent_error('شناسه فرم نامعتبر است.', 422);
    }
    $form = $store['forms'][$formId] ?? null;
    if (!is_array($form)) {
        dent_error('فرم پیدا نشد.', 404);
    }
    $user = dent_current_user();
    if (!forms_viewer_can_access($form, $user)) {
        if ($user === null && !forms_guest_allowed($form)) {
            dent_error('برای شرکت در این فرم باید وارد حساب شوید.', 401, ['loggedOut' => true, 'requiresLogin' => true]);
        }
        dent_error('این فرم برای شما فعال نیست.', 403);
    }
    dent_json_response([
        'success' => true,
        'form' => forms_form_payload($store, $form, $user, true),
        'viewer' => forms_user_payload($user),
    ]);
}

if ($action === 'submit') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ثبت پاسخ نامعتبر است.', 405);
    }
    $store = forms_load_store();
    $formId = forms_clean_id((string) ($_POST['formId'] ?? ''), FORMS_ID_PREFIX);
    if ($formId === '') {
        dent_error('شناسه فرم نامعتبر است.', 422);
    }
    $form = $store['forms'][$formId] ?? null;
    if (!is_array($form)) {
        dent_error('فرم پیدا نشد.', 404);
    }
    $user = dent_current_user();
    if (!forms_viewer_can_access($form, $user)) {
        if ($user === null && !forms_guest_allowed($form)) {
            dent_error('برای ثبت پاسخ باید وارد حساب شوید.', 401, ['loggedOut' => true, 'requiresLogin' => true]);
        }
        dent_error('این فرم برای شما فعال نیست.', 403);
    }
    if (forms_status($form) !== 'open') {
        dent_error('ثبت پاسخ برای این فرم فعال نیست.', 422);
    }
    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    if ($user !== null && forms_can_manage($form, $user) && !forms_parse_bool($settings['allowCreatorSubmit'] ?? true, true)) {
        dent_error('ثبت پاسخ توسط سازنده/مدیر برای این فرم فعال نیست.', 403);
    }
    if ($user === null && forms_parse_bool($settings['collectGuestName'] ?? true, true) && forms_clean_text($_POST['guestName'] ?? '', 140) === '') {
        dent_error('نام شرکت‌کننده مهمان الزامی است.', 422);
    }
    if ($user === null && forms_parse_bool($settings['collectGuestPhone'] ?? false, false) && forms_only_digits($_POST['guestPhone'] ?? '') === '') {
        dent_error('شماره تماس شرکت‌کننده مهمان الزامی است.', 422);
    }
    $identityKey = forms_identity_key($user, $_POST);
    $existingResponse = forms_response_for_identity($store, $formId, $identityKey);
    if (forms_parse_bool($settings['limitOneResponse'] ?? true, true) && $existingResponse !== null && !forms_parse_bool($settings['allowEditResponse'] ?? false, false)) {
        dent_error('برای این شرکت‌کننده قبلاً پاسخ ثبت شده است.', 409);
    }
    $now = time();
    $response = [
        'id' => is_array($existingResponse) ? (string) ($existingResponse['id'] ?? forms_next_id(FORMS_RESPONSE_ID_PREFIX)) : forms_next_id(FORMS_RESPONSE_ID_PREFIX),
        'formId' => $formId,
        'submittedAt' => is_array($existingResponse) ? (int) ($existingResponse['submittedAt'] ?? $now) : $now,
        'updatedAt' => $now,
        'identity' => forms_identity_payload($user, $_POST),
        'answers' => forms_collect_answers($form, $_POST),
    ];
    $store['responses'][(string) $response['id']] = $response;
    $form['updatedAt'] = $now;
    $store['forms'][$formId] = $form;
    forms_save_store($store);
    $formPayload = forms_form_payload($store, $form, $user, true, $identityKey);
    dent_json_response([
        'success' => true,
        'message' => 'پاسخ با موفقیت ثبت شد.',
        'response' => forms_response_payload($response, $form),
        'form' => $formPayload,
    ]);
}

if ($action === 'responses') {
    $user = dent_require_user();
    $store = forms_load_store();
    $formId = forms_clean_id((string) ($_GET['formId'] ?? $_POST['formId'] ?? ''), FORMS_ID_PREFIX);
    if ($formId === '') {
        dent_error('شناسه فرم نامعتبر است.', 422);
    }
    $form = $store['forms'][$formId] ?? null;
    if (!is_array($form)) {
        dent_error('فرم پیدا نشد.', 404);
    }
    if (!forms_can_manage($form, $user)) {
        dent_error('اجازه مشاهده پاسخ‌ها را ندارید.', 403);
    }
    $responses = forms_responses_for_form($store, $formId);
    dent_json_response([
        'success' => true,
        'form' => forms_form_payload($store, $form, $user, true),
        'responses' => array_map(static fn(array $response): array => forms_response_payload($response, $form), $responses),
    ]);
}

if ($action === 'export') {
    $user = dent_require_user();
    $store = forms_load_store();
    $formId = forms_clean_id((string) ($_GET['formId'] ?? $_POST['formId'] ?? ''), FORMS_ID_PREFIX);
    if ($formId === '') {
        dent_error('شناسه فرم نامعتبر است.', 422);
    }
    $form = $store['forms'][$formId] ?? null;
    if (!is_array($form)) {
        dent_error('فرم پیدا نشد.', 404);
    }
    if (!forms_can_manage($form, $user)) {
        dent_error('اجازه دریافت خروجی را ندارید.', 403);
    }
    $mode = trim(strtolower((string) ($_GET['mode'] ?? $_POST['mode'] ?? 'responses')));
    forms_export_xlsx($form, forms_responses_for_form($store, $formId), $mode);
}

dent_error('درخواست نامعتبر است.', 404);
