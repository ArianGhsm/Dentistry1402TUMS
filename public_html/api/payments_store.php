<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!defined('PAYMENTS_SCHEMA_VERSION')) {
    define('PAYMENTS_SCHEMA_VERSION', 1);
}
if (!defined('PAYMENTS_ITEM_STATUS_ACTIVE')) {
    define('PAYMENTS_ITEM_STATUS_ACTIVE', 'active');
}
if (!defined('PAYMENTS_ITEM_STATUS_INACTIVE')) {
    define('PAYMENTS_ITEM_STATUS_INACTIVE', 'inactive');
}
if (!defined('PAYMENTS_ORDER_STATUS_PENDING')) {
    define('PAYMENTS_ORDER_STATUS_PENDING', 'pending');
}
if (!defined('PAYMENTS_ORDER_STATUS_SUCCESS')) {
    define('PAYMENTS_ORDER_STATUS_SUCCESS', 'success');
}
if (!defined('PAYMENTS_ORDER_STATUS_FAILED')) {
    define('PAYMENTS_ORDER_STATUS_FAILED', 'failed');
}
if (!defined('PAYMENTS_ORDER_STATUS_CANCELED')) {
    define('PAYMENTS_ORDER_STATUS_CANCELED', 'canceled');
}
if (!defined('PAYMENTS_ORDER_STATUS_EXPIRED')) {
    define('PAYMENTS_ORDER_STATUS_EXPIRED', 'expired');
}
if (!defined('PAYMENTS_NOTIFICATION_TYPE_ORDER_SUCCESS')) {
    define('PAYMENTS_NOTIFICATION_TYPE_ORDER_SUCCESS', 'order-success');
}
if (!defined('PAYMENTS_NOTIFICATION_TYPE_ORDER_FAILED')) {
    define('PAYMENTS_NOTIFICATION_TYPE_ORDER_FAILED', 'order-failed');
}

function payments_store_path(): string
{
    return dent_storage_path('payments/store.json');
}

function payments_lock_path(): string
{
    return dent_storage_path('payments/store.lock');
}

function payments_log_path(): string
{
    return dent_storage_path('payments/gateway.log');
}

function payments_default_store(): array
{
    return [
        'schemaVersion' => PAYMENTS_SCHEMA_VERSION,
        'nextItemId' => 1,
        'nextOrderId' => 1,
        'nextNotificationId' => 1,
        'items' => [],
        'orders' => [],
        'notifications' => [],
    ];
}

function payments_ensure_storage(): void
{
    dent_ensure_directory(dirname(payments_store_path()));

    if (!is_file(payments_store_path())) {
        dent_write_json_file(payments_store_path(), payments_default_store());
    }
}

function payments_read_store(): array
{
    payments_ensure_storage();

    $lock = fopen(payments_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل فضای ذخیره‌سازی پرداخت.', 500);
    }

    $store = payments_default_store();
    try {
        if (!flock($lock, LOCK_SH)) {
            throw new RuntimeException('Unable to acquire shared lock.');
        }

        $store = payments_load_store_unlocked();
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }

    return $store;
}

/**
 * @template T
 * @param callable(array):T $callback
 * @return T
 */
function payments_with_store_lock(callable $callback)
{
    payments_ensure_storage();

    $lock = fopen(payments_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل فضای ذخیره‌سازی پرداخت.', 500);
    }

    $store = payments_default_store();
    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to acquire exclusive lock.');
        }

        $store = payments_load_store_unlocked();
        $result = $callback($store);
        $store = payments_normalize_store($store);
        payments_save_store_unlocked($store);

        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function payments_load_store_unlocked(): array
{
    $raw = dent_read_json_file(payments_store_path(), payments_default_store());
    if (!is_array($raw)) {
        $raw = payments_default_store();
    }

    return payments_normalize_store($raw);
}

function payments_save_store_unlocked(array $store): void
{
    dent_write_json_file(payments_store_path(), payments_normalize_store($store));
}

function payments_normalize_store(array $store): array
{
    $itemsRaw = $store['items'] ?? [];
    if (!is_array($itemsRaw)) {
        $itemsRaw = [];
    }

    $ordersRaw = $store['orders'] ?? [];
    if (!is_array($ordersRaw)) {
        $ordersRaw = [];
    }

    $notificationsRaw = $store['notifications'] ?? [];
    if (!is_array($notificationsRaw)) {
        $notificationsRaw = [];
    }

    $normalizedItems = [];
    $maxItemId = 0;
    $seenSlugs = [];
    foreach ($itemsRaw as $seed) {
        if (!is_array($seed)) {
            continue;
        }

        $item = payments_normalize_item_record($seed);
        if ($item === null) {
            continue;
        }

        $itemId = (int) $item['id'];
        $maxItemId = max($maxItemId, $itemId);

        $slug = (string) $item['slug'];
        if ($slug === '' || isset($seenSlugs[$slug])) {
            $slug = 'item-' . $itemId;
            while (isset($seenSlugs[$slug])) {
                $slug .= '-x';
            }
            $item['slug'] = $slug;
        }
        $seenSlugs[$slug] = true;

        $normalizedItems[] = $item;
    }

    usort($normalizedItems, static function (array $left, array $right): int {
        return (int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0);
    });

    $normalizedOrders = [];
    $maxOrderId = 0;
    $seenOrderTokens = [];
    foreach ($ordersRaw as $seed) {
        if (!is_array($seed)) {
            continue;
        }

        $order = payments_normalize_order_record($seed);
        if ($order === null) {
            continue;
        }

        $orderId = (int) $order['id'];
        $maxOrderId = max($maxOrderId, $orderId);
        $token = (string) $order['public_token'];
        if ($token === '' || isset($seenOrderTokens[$token])) {
            $token = payments_random_token();
            $order['public_token'] = $token;
        }
        $seenOrderTokens[$token] = true;

        $normalizedOrders[] = $order;
    }

    usort($normalizedOrders, static function (array $left, array $right): int {
        return (int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0);
    });

    $normalizedNotifications = [];
    $maxNotificationId = 0;
    foreach ($notificationsRaw as $seed) {
        if (!is_array($seed)) {
            continue;
        }

        $notification = payments_normalize_notification_record($seed);
        if ($notification === null) {
            continue;
        }

        $notificationId = (int) $notification['id'];
        $maxNotificationId = max($maxNotificationId, $notificationId);
        $normalizedNotifications[] = $notification;
    }

    usort($normalizedNotifications, static function (array $left, array $right): int {
        return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    });

    $normalized = [
        'schemaVersion' => PAYMENTS_SCHEMA_VERSION,
        'nextItemId' => max($maxItemId + 1, (int) ($store['nextItemId'] ?? 1), 1),
        'nextOrderId' => max($maxOrderId + 1, (int) ($store['nextOrderId'] ?? 1), 1),
        'nextNotificationId' => max($maxNotificationId + 1, (int) ($store['nextNotificationId'] ?? 1), 1),
        'items' => $normalizedItems,
        'orders' => $normalizedOrders,
        'notifications' => $normalizedNotifications,
    ];

    payments_recalculate_sold_counts($normalized);
    return $normalized;
}

function payments_normalize_item_record(array $seed): ?array
{
    $id = (int) ($seed['id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $slug = payments_clean_slug((string) ($seed['slug'] ?? ''));
    if ($slug === '') {
        $slug = 'item-' . $id;
    }

    $status = trim((string) ($seed['status'] ?? PAYMENTS_ITEM_STATUS_ACTIVE));
    if (!in_array($status, [PAYMENTS_ITEM_STATUS_ACTIVE, PAYMENTS_ITEM_STATUS_INACTIVE], true)) {
        $status = PAYMENTS_ITEM_STATUS_INACTIVE;
    }

    $gallery = payments_normalize_string_list($seed['gallery'] ?? [], 24, 420);
    $specifications = payments_normalize_specifications($seed['specifications'] ?? []);
    $requiredFields = payments_normalize_required_fields_loose($seed['required_fields'] ?? []);

    $startsAt = payments_normalize_datetime_string((string) ($seed['starts_at'] ?? ''));
    $expiresAt = payments_normalize_datetime_string((string) ($seed['expires_at'] ?? ''));
    $capacity = payments_normalize_positive_int_nullable($seed['capacity'] ?? null, 1000000);

    return [
        'id' => $id,
        'slug' => $slug,
        'title' => dent_clean_text((string) ($seed['title'] ?? ''), 140),
        'short_description' => dent_clean_text((string) ($seed['short_description'] ?? ''), 460),
        'full_description' => dent_clean_text((string) ($seed['full_description'] ?? ''), 6000),
        'hero_image' => dent_clean_text((string) ($seed['hero_image'] ?? ''), 420),
        'gallery' => $gallery,
        'specifications' => $specifications,
        'price' => max(0, (int) ($seed['price'] ?? 0)),
        'status' => $status,
        'starts_at' => $startsAt,
        'expires_at' => $expiresAt,
        'capacity' => $capacity,
        'sold_count' => max(0, (int) ($seed['sold_count'] ?? 0)),
        'required_fields' => $requiredFields,
        'success_message' => dent_clean_text((string) ($seed['success_message'] ?? 'پرداخت شما با موفقیت ثبت شد.'), 600),
        'failure_message' => dent_clean_text((string) ($seed['failure_message'] ?? 'پرداخت شما تایید نشد.'), 600),
        'created_at' => payments_normalize_datetime_string((string) ($seed['created_at'] ?? dent_iso_now()), dent_iso_now()),
        'updated_at' => payments_normalize_datetime_string((string) ($seed['updated_at'] ?? dent_iso_now()), dent_iso_now()),
    ];
}

function payments_normalize_order_record(array $seed): ?array
{
    $id = (int) ($seed['id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $status = trim((string) ($seed['status'] ?? PAYMENTS_ORDER_STATUS_PENDING));
    if (!in_array(
        $status,
        [
            PAYMENTS_ORDER_STATUS_PENDING,
            PAYMENTS_ORDER_STATUS_SUCCESS,
            PAYMENTS_ORDER_STATUS_FAILED,
            PAYMENTS_ORDER_STATUS_CANCELED,
            PAYMENTS_ORDER_STATUS_EXPIRED,
        ],
        true
    )) {
        $status = PAYMENTS_ORDER_STATUS_PENDING;
    }

    $snapshot = $seed['gateway_response_snapshot'] ?? [];
    if (!is_array($snapshot)) {
        $snapshot = [];
    }

    $extraFormData = $seed['extra_form_data'] ?? [];
    if (!is_array($extraFormData)) {
        $extraFormData = [];
    }

    $publicToken = dent_clean_text((string) ($seed['public_token'] ?? ''), 120);
    if ($publicToken === '') {
        $publicToken = payments_random_token();
    }

    return [
        'id' => $id,
        'item_id' => max(0, (int) ($seed['item_id'] ?? 0)),
        'user_id' => dent_normalize_student_number((string) ($seed['user_id'] ?? '')),
        'payer_name' => dent_clean_text((string) ($seed['payer_name'] ?? ''), 120),
        'payer_phone' => payments_normalize_phone((string) ($seed['payer_phone'] ?? '')),
        'payer_student_number' => dent_normalize_student_number((string) ($seed['payer_student_number'] ?? '')),
        'extra_form_data' => payments_normalize_extra_form_data($extraFormData),
        'amount' => max(0, (int) ($seed['amount'] ?? 0)),
        'gateway' => dent_clean_text((string) ($seed['gateway'] ?? ''), 32),
        'authority' => dent_clean_text((string) ($seed['authority'] ?? ''), 120),
        'ref_id' => dent_clean_text((string) ($seed['ref_id'] ?? ''), 120),
        'status' => $status,
        'gateway_response_snapshot' => $snapshot,
        'created_at' => payments_normalize_datetime_string((string) ($seed['created_at'] ?? dent_iso_now()), dent_iso_now()),
        'paid_at' => payments_normalize_datetime_string((string) ($seed['paid_at'] ?? ''), ''),
        'verified_at' => payments_normalize_datetime_string((string) ($seed['verified_at'] ?? ''), ''),
        'public_token' => $publicToken,
    ];
}

function payments_normalize_notification_record(array $seed): ?array
{
    $id = (int) ($seed['id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    return [
        'id' => $id,
        'type' => dent_clean_text((string) ($seed['type'] ?? ''), 40),
        'title' => dent_clean_text((string) ($seed['title'] ?? ''), 180),
        'body' => dent_clean_text((string) ($seed['body'] ?? ''), 900),
        'related_order_id' => max(0, (int) ($seed['related_order_id'] ?? 0)),
        'read_at' => payments_normalize_datetime_string((string) ($seed['read_at'] ?? ''), ''),
        'created_at' => payments_normalize_datetime_string((string) ($seed['created_at'] ?? dent_iso_now()), dent_iso_now()),
    ];
}

function payments_random_token(int $bytes = 18): string
{
    try {
        return rtrim(strtr(base64_encode(random_bytes(max(8, $bytes))), '+/', '-_'), '=');
    } catch (Throwable $error) {
        return 'tok-' . sha1((string) mt_rand() . '|' . microtime(true));
    }
}

function payments_clean_slug(string $value): string
{
    $value = dent_force_utf8($value);
    $value = trim(strtolower($value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
    $value = trim($value, '-');
    if ($value === '') {
        return '';
    }

    if (strlen($value) > 80) {
        $value = substr($value, 0, 80);
        $value = trim($value, '-');
    }

    return $value;
}

function payments_normalize_datetime_string(string $value, string $fallback = ''): string
{
    $value = trim($value);
    if ($value === '') {
        return $fallback;
    }

    $parsed = strtotime($value);
    if ($parsed === false) {
        return $fallback;
    }

    return date('c', $parsed);
}

function payments_normalize_positive_int_nullable($value, int $max): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    $normalized = (int) dent_normalize_digits((string) $value);
    if ($normalized <= 0) {
        return null;
    }

    return min($max, $normalized);
}

function payments_normalize_phone(string $value): string
{
    $digits = preg_replace('/\D+/u', '', dent_normalize_digits($value)) ?? '';
    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '0098')) {
        $digits = substr($digits, 4);
    } elseif (str_starts_with($digits, '98')) {
        $digits = substr($digits, 2);
    }

    if (str_starts_with($digits, '9') && strlen($digits) === 10) {
        $digits = '0' . $digits;
    }

    return $digits;
}

function payments_normalize_extra_form_data(array $value): array
{
    $output = [];
    foreach ($value as $key => $fieldValue) {
        $fieldName = dent_clean_text((string) $key, 60);
        if ($fieldName === '') {
            continue;
        }
        $output[$fieldName] = dent_clean_text((string) $fieldValue, 1000);
    }

    return $output;
}

function payments_normalize_string_list($value, int $maxItems, int $maxLength): array
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
                $items = preg_split('/\r\n|\r|\n|,/u', $trimmed) ?: [];
            }
        }
    }

    $normalized = [];
    $seen = [];
    foreach ($items as $item) {
        $clean = dent_clean_text((string) $item, $maxLength);
        if ($clean === '' || isset($seen[$clean])) {
            continue;
        }
        $seen[$clean] = true;
        $normalized[] = $clean;
        if (count($normalized) >= $maxItems) {
            break;
        }
    }

    return $normalized;
}

function payments_normalize_specifications($value): array
{
    $specs = [];
    if (is_array($value)) {
        $specs = $value;
    } elseif (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $specs = $decoded;
            } else {
                $lines = preg_split('/\r\n|\r|\n/u', $trimmed) ?: [];
                foreach ($lines as $line) {
                    $parts = preg_split('/[:|]/u', (string) $line, 2);
                    $label = dent_clean_text((string) ($parts[0] ?? ''), 120);
                    $textValue = dent_clean_text((string) ($parts[1] ?? ''), 220);
                    if ($label !== '' && $textValue !== '') {
                        $specs[] = ['label' => $label, 'value' => $textValue];
                    }
                }
            }
        }
    }

    $normalized = [];
    foreach ($specs as $spec) {
        if (!is_array($spec)) {
            continue;
        }

        $label = dent_clean_text((string) ($spec['label'] ?? ''), 120);
        $textValue = dent_clean_text((string) ($spec['value'] ?? ''), 220);
        if ($label === '' || $textValue === '') {
            continue;
        }

        $normalized[] = [
            'label' => $label,
            'value' => $textValue,
        ];

        if (count($normalized) >= 30) {
            break;
        }
    }

    return $normalized;
}

function payments_normalize_required_fields_loose($value): array
{
    $fields = [];
    if (is_array($value)) {
        $fields = $value;
    } elseif (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $fields = $decoded;
            }
        }
    }

    $normalized = [];
    $seenNames = [];
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        $name = dent_clean_text((string) ($field['name'] ?? ''), 60);
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9_]/', '', $name) ?? '';
        if ($name === '' || isset($seenNames[$name])) {
            continue;
        }

        $type = trim(strtolower((string) ($field['type'] ?? 'text')));
        if (!in_array($type, ['text', 'tel', 'number', 'textarea', 'select'], true)) {
            $type = 'text';
        }

        $label = dent_clean_text((string) ($field['label'] ?? ''), 80);
        if ($label === '') {
            continue;
        }

        $options = payments_normalize_string_list($field['options'] ?? [], 20, 80);
        if ($type === 'select' && $options === []) {
            continue;
        }

        $maxLength = (int) ($field['maxLength'] ?? 120);
        $maxLength = max(10, min(1000, $maxLength));

        $normalized[] = [
            'name' => $name,
            'label' => $label,
            'type' => $type,
            'required' => (bool) ($field['required'] ?? true),
            'placeholder' => dent_clean_text((string) ($field['placeholder'] ?? ''), 120),
            'maxLength' => $maxLength,
            'options' => $options,
        ];

        $seenNames[$name] = true;
        if (count($normalized) >= 12) {
            break;
        }
    }

    return $normalized;
}

function payments_find_item_index_by_id(array $store, int $itemId): int
{
    foreach ($store['items'] as $index => $item) {
        if ((int) ($item['id'] ?? 0) === $itemId) {
            return (int) $index;
        }
    }
    return -1;
}

function payments_find_item_index_by_slug(array $store, string $slug): int
{
    foreach ($store['items'] as $index => $item) {
        if ((string) ($item['slug'] ?? '') === $slug) {
            return (int) $index;
        }
    }
    return -1;
}

function payments_find_order_index_by_id(array $store, int $orderId): int
{
    foreach ($store['orders'] as $index => $order) {
        if ((int) ($order['id'] ?? 0) === $orderId) {
            return (int) $index;
        }
    }
    return -1;
}

function payments_find_order_index_by_token(array $store, string $token): int
{
    foreach ($store['orders'] as $index => $order) {
        if ((string) ($order['public_token'] ?? '') === $token) {
            return (int) $index;
        }
    }
    return -1;
}

function payments_next_item_id(array &$store): int
{
    $next = max(1, (int) ($store['nextItemId'] ?? 1));
    $store['nextItemId'] = $next + 1;
    return $next;
}

function payments_next_order_id(array &$store): int
{
    $next = max(1, (int) ($store['nextOrderId'] ?? 1));
    $store['nextOrderId'] = $next + 1;
    return $next;
}

function payments_next_notification_id(array &$store): int
{
    $next = max(1, (int) ($store['nextNotificationId'] ?? 1));
    $store['nextNotificationId'] = $next + 1;
    return $next;
}

function payments_recalculate_sold_counts(array &$store): void
{
    $successByItem = [];
    foreach ($store['orders'] as $order) {
        if ((string) ($order['status'] ?? '') !== PAYMENTS_ORDER_STATUS_SUCCESS) {
            continue;
        }

        $itemId = max(0, (int) ($order['item_id'] ?? 0));
        if ($itemId <= 0) {
            continue;
        }

        $successByItem[$itemId] = ($successByItem[$itemId] ?? 0) + 1;
    }

    foreach ($store['items'] as $index => $item) {
        $itemId = max(0, (int) ($item['id'] ?? 0));
        $store['items'][$index]['sold_count'] = $successByItem[$itemId] ?? 0;
    }
}

function payments_item_public_state(array $item): array
{
    $status = (string) ($item['status'] ?? PAYMENTS_ITEM_STATUS_INACTIVE);
    if ($status !== PAYMENTS_ITEM_STATUS_ACTIVE) {
        return [
            'key' => 'inactive',
            'label' => 'غیرفعال',
            'isPayable' => false,
        ];
    }

    $now = time();
    $startsAt = payments_timestamp_or_null((string) ($item['starts_at'] ?? ''));
    if ($startsAt !== null && $startsAt > $now) {
        return [
            'key' => 'upcoming',
            'label' => 'شروع‌نشده',
            'isPayable' => false,
        ];
    }

    $expiresAt = payments_timestamp_or_null((string) ($item['expires_at'] ?? ''));
    if ($expiresAt !== null && $expiresAt <= $now) {
        return [
            'key' => 'expired',
            'label' => 'منقضی‌شده',
            'isPayable' => false,
        ];
    }

    $capacity = payments_normalize_positive_int_nullable($item['capacity'] ?? null, 1000000);
    $soldCount = max(0, (int) ($item['sold_count'] ?? 0));
    if ($capacity !== null && $soldCount >= $capacity) {
        return [
            'key' => 'full',
            'label' => 'تکمیل ظرفیت',
            'isPayable' => false,
        ];
    }

    return [
        'key' => 'active',
        'label' => 'فعال',
        'isPayable' => true,
    ];
}

function payments_timestamp_or_null(string $value): ?int
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $parsed = strtotime($value);
    if ($parsed === false) {
        return null;
    }

    return (int) $parsed;
}

function payments_public_item_payload(array $item): array
{
    $state = payments_item_public_state($item);
    return [
        'id' => (int) ($item['id'] ?? 0),
        'slug' => (string) ($item['slug'] ?? ''),
        'title' => (string) ($item['title'] ?? ''),
        'shortDescription' => (string) ($item['short_description'] ?? ''),
        'fullDescription' => (string) ($item['full_description'] ?? ''),
        'heroImage' => (string) ($item['hero_image'] ?? ''),
        'gallery' => is_array($item['gallery'] ?? null) ? array_values($item['gallery']) : [],
        'specifications' => is_array($item['specifications'] ?? null) ? array_values($item['specifications']) : [],
        'price' => max(0, (int) ($item['price'] ?? 0)),
        'status' => (string) ($item['status'] ?? PAYMENTS_ITEM_STATUS_INACTIVE),
        'state' => $state,
        'startsAt' => (string) ($item['starts_at'] ?? ''),
        'expiresAt' => (string) ($item['expires_at'] ?? ''),
        'capacity' => payments_normalize_positive_int_nullable($item['capacity'] ?? null, 1000000),
        'soldCount' => max(0, (int) ($item['sold_count'] ?? 0)),
        'remainingCapacity' => payments_item_remaining_capacity($item),
        'requiredFields' => is_array($item['required_fields'] ?? null) ? array_values($item['required_fields']) : [],
        'successMessage' => (string) ($item['success_message'] ?? ''),
        'failureMessage' => (string) ($item['failure_message'] ?? ''),
        'updatedAt' => (string) ($item['updated_at'] ?? ''),
    ];
}

function payments_item_remaining_capacity(array $item): ?int
{
    $capacity = payments_normalize_positive_int_nullable($item['capacity'] ?? null, 1000000);
    if ($capacity === null) {
        return null;
    }

    $soldCount = max(0, (int) ($item['sold_count'] ?? 0));
    return max(0, $capacity - $soldCount);
}

function payments_owner_item_payload(array $item): array
{
    $public = payments_public_item_payload($item);
    $public['createdAt'] = (string) ($item['created_at'] ?? '');
    $public['publicUrl'] = '/buy/item/?slug=' . rawurlencode((string) ($item['slug'] ?? ''));
    return $public;
}

function payments_owner_order_payload(array $order, ?array $item = null): array
{
    return [
        'id' => (int) ($order['id'] ?? 0),
        'itemId' => (int) ($order['item_id'] ?? 0),
        'itemTitle' => (string) ($item['title'] ?? ''),
        'itemSlug' => (string) ($item['slug'] ?? ''),
        'userId' => (string) ($order['user_id'] ?? ''),
        'payerName' => (string) ($order['payer_name'] ?? ''),
        'payerPhone' => (string) ($order['payer_phone'] ?? ''),
        'payerStudentNumber' => (string) ($order['payer_student_number'] ?? ''),
        'extraFormData' => is_array($order['extra_form_data'] ?? null) ? $order['extra_form_data'] : [],
        'amount' => max(0, (int) ($order['amount'] ?? 0)),
        'gateway' => (string) ($order['gateway'] ?? ''),
        'authority' => (string) ($order['authority'] ?? ''),
        'refId' => (string) ($order['ref_id'] ?? ''),
        'status' => (string) ($order['status'] ?? PAYMENTS_ORDER_STATUS_PENDING),
        'createdAt' => (string) ($order['created_at'] ?? ''),
        'paidAt' => (string) ($order['paid_at'] ?? ''),
        'verifiedAt' => (string) ($order['verified_at'] ?? ''),
        'publicToken' => (string) ($order['public_token'] ?? ''),
    ];
}

function payments_order_public_result_payload(array $order, ?array $item = null): array
{
    $status = (string) ($order['status'] ?? PAYMENTS_ORDER_STATUS_PENDING);
    $statusLabel = 'در انتظار';
    if ($status === PAYMENTS_ORDER_STATUS_SUCCESS) {
        $statusLabel = 'موفق';
    } elseif ($status === PAYMENTS_ORDER_STATUS_FAILED) {
        $statusLabel = 'ناموفق';
    } elseif ($status === PAYMENTS_ORDER_STATUS_CANCELED) {
        $statusLabel = 'لغو شده';
    } elseif ($status === PAYMENTS_ORDER_STATUS_EXPIRED) {
        $statusLabel = 'منقضی شده';
    }

    $message = '';
    if ($status === PAYMENTS_ORDER_STATUS_SUCCESS) {
        $message = (string) ($item['success_message'] ?? 'پرداخت شما با موفقیت تایید شد.');
    } elseif (in_array($status, [PAYMENTS_ORDER_STATUS_FAILED, PAYMENTS_ORDER_STATUS_CANCELED, PAYMENTS_ORDER_STATUS_EXPIRED], true)) {
        $message = (string) ($item['failure_message'] ?? 'پرداخت شما ناموفق بود.');
    } else {
        $message = 'پرداخت هنوز در حال بررسی است.';
    }

    return [
        'id' => (int) ($order['id'] ?? 0),
        'status' => $status,
        'statusLabel' => $statusLabel,
        'message' => $message,
        'amount' => max(0, (int) ($order['amount'] ?? 0)),
        'authority' => (string) ($order['authority'] ?? ''),
        'refId' => (string) ($order['ref_id'] ?? ''),
        'createdAt' => (string) ($order['created_at'] ?? ''),
        'paidAt' => (string) ($order['paid_at'] ?? ''),
        'verifiedAt' => (string) ($order['verified_at'] ?? ''),
        'item' => $item ? payments_public_item_payload($item) : null,
        'payerName' => (string) ($order['payer_name'] ?? ''),
        'payerPhone' => (string) ($order['payer_phone'] ?? ''),
        'payerStudentNumber' => (string) ($order['payer_student_number'] ?? ''),
    ];
}

function payments_append_notification(
    array &$store,
    string $type,
    string $title,
    string $body,
    int $relatedOrderId
): array {
    $notification = [
        'id' => payments_next_notification_id($store),
        'type' => dent_clean_text($type, 40),
        'title' => dent_clean_text($title, 180),
        'body' => dent_clean_text($body, 900),
        'related_order_id' => max(0, $relatedOrderId),
        'read_at' => '',
        'created_at' => dent_iso_now(),
    ];

    $store['notifications'][] = $notification;
    return $notification;
}

function payments_log_gateway_event(string $event, array $payload): void
{
    $entry = [
        'event' => dent_clean_text($event, 60),
        'at' => dent_iso_now(),
        'payload' => $payload,
    ];

    $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || $json === '') {
        return;
    }

    dent_ensure_directory(dirname(payments_log_path()));
    @file_put_contents(payments_log_path(), $json . PHP_EOL, FILE_APPEND | LOCK_EX);
}
