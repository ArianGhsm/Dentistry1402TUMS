<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/payments_store.php';
require_once __DIR__ . '/payments_gateway.php';

const PAYMENTS_PUBLIC_LIST_PATH = '/buy/';
const PAYMENTS_PUBLIC_ITEM_PATH = '/buy/item/';
const PAYMENTS_PUBLIC_RESULT_PATH = '/buy/result/';

final class PaymentsApiException extends RuntimeException
{
    private int $statusCode;

    public function __construct(string $message, int $statusCode = 422)
    {
        parent::__construct($message);
        $this->statusCode = max(400, min(599, $statusCode));
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}

function payments_api_require_method(array $methods): void
{
    $method = dent_request_method();
    if (!in_array($method, $methods, true)) {
        dent_error('متد درخواست نامعتبر است.', 405);
    }
}

function payments_api_absolute_url(string $path): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return $path;
    }

    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $isSecure = $forwardedProto === 'https'
        || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');

    return ($isSecure ? 'https://' : 'http://') . $host . $path;
}

function payments_api_decode_json_assoc(string $value): ?array
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }

    $decoded = json_decode($trimmed, true);
    if (!is_array($decoded)) {
        return null;
    }

    return $decoded;
}

function payments_api_parse_required_fields_input($raw): array
{
    if (is_array($raw)) {
        return payments_normalize_required_fields_loose($raw);
    }

    $text = trim((string) $raw);
    if ($text === '') {
        return [];
    }

    $decoded = payments_api_decode_json_assoc($text);
    if (!is_array($decoded)) {
        dent_error('فرمت فیلدهای موردنیاز معتبر نیست. JSON معتبر وارد کنید.', 422);
    }

    $normalized = payments_normalize_required_fields_loose($decoded);
    if ($decoded !== [] && $normalized === []) {
        dent_error('حداقل یک فیلد معتبر برای فرم پرداخت تعریف کنید.', 422);
    }

    return $normalized;
}

function payments_api_parse_specifications_input($raw): array
{
    if (is_array($raw)) {
        return payments_normalize_specifications($raw);
    }

    $text = trim((string) $raw);
    if ($text === '') {
        return [];
    }

    $decoded = payments_api_decode_json_assoc($text);
    if (is_array($decoded)) {
        return payments_normalize_specifications($decoded);
    }

    return payments_normalize_specifications($text);
}

function payments_api_parse_gallery_input($raw): array
{
    if (is_array($raw)) {
        return payments_normalize_string_list($raw, 24, 420);
    }

    $text = trim((string) $raw);
    if ($text === '') {
        return [];
    }

    $decoded = payments_api_decode_json_assoc($text);
    if (is_array($decoded)) {
        return payments_normalize_string_list($decoded, 24, 420);
    }

    return payments_normalize_string_list($text, 24, 420);
}

function payments_api_parse_extra_form_data($raw): array
{
    if (is_array($raw)) {
        return payments_normalize_extra_form_data($raw);
    }

    $text = trim((string) $raw);
    if ($text === '') {
        return [];
    }

    $decoded = payments_api_decode_json_assoc($text);
    if (!is_array($decoded)) {
        return [];
    }

    return payments_normalize_extra_form_data($decoded);
}

function payments_api_status_label(string $status): string
{
    if ($status === PAYMENTS_ORDER_STATUS_SUCCESS) {
        return 'موفق';
    }
    if ($status === PAYMENTS_ORDER_STATUS_FAILED) {
        return 'ناموفق';
    }
    if ($status === PAYMENTS_ORDER_STATUS_CANCELED) {
        return 'لغو شده';
    }
    if ($status === PAYMENTS_ORDER_STATUS_EXPIRED) {
        return 'منقضی شده';
    }

    return 'در انتظار';
}

function payments_api_item_status_message(array $state): string
{
    $key = (string) ($state['key'] ?? '');
    if ($key === 'inactive') {
        return 'این آیتم پرداخت غیرفعال است.';
    }
    if ($key === 'upcoming') {
        return 'پرداخت این آیتم هنوز شروع نشده است.';
    }
    if ($key === 'expired') {
        return 'مهلت پرداخت این آیتم به پایان رسیده است.';
    }
    if ($key === 'full') {
        return 'ظرفیت این آیتم تکمیل شده است.';
    }

    return 'این آیتم قابل پرداخت است.';
}

function payments_api_notifications_unread_count(array $notifications): int
{
    $count = 0;
    foreach ($notifications as $notification) {
        if (!is_array($notification)) {
            continue;
        }
        if (trim((string) ($notification['read_at'] ?? '')) === '') {
            $count++;
        }
    }

    return $count;
}

function payments_api_orders_summary(array $orders): array
{
    $summary = [
        'totalOrders' => 0,
        'totalSuccess' => 0,
        'totalPending' => 0,
        'totalFailed' => 0,
        'totalCanceled' => 0,
        'totalExpired' => 0,
        'totalReceived' => 0,
    ];

    foreach ($orders as $order) {
        if (!is_array($order)) {
            continue;
        }

        $summary['totalOrders']++;
        $status = (string) ($order['status'] ?? PAYMENTS_ORDER_STATUS_PENDING);
        if ($status === PAYMENTS_ORDER_STATUS_SUCCESS) {
            $summary['totalSuccess']++;
            $summary['totalReceived'] += max(0, (int) ($order['amount'] ?? 0));
        } elseif ($status === PAYMENTS_ORDER_STATUS_FAILED) {
            $summary['totalFailed']++;
        } elseif ($status === PAYMENTS_ORDER_STATUS_CANCELED) {
            $summary['totalCanceled']++;
        } elseif ($status === PAYMENTS_ORDER_STATUS_EXPIRED) {
            $summary['totalExpired']++;
        } else {
            $summary['totalPending']++;
        }
    }

    return $summary;
}

function payments_api_order_matches_filters(array $order, array $itemById, array $filters): bool
{
    $filterItemId = max(0, (int) ($filters['itemId'] ?? 0));
    if ($filterItemId > 0 && (int) ($order['item_id'] ?? 0) !== $filterItemId) {
        return false;
    }

    $status = trim((string) ($filters['status'] ?? ''));
    if ($status !== '' && $status !== 'all' && (string) ($order['status'] ?? '') !== $status) {
        return false;
    }

    $fromTs = payments_timestamp_or_null((string) ($filters['dateFrom'] ?? ''));
    if ($fromTs !== null) {
        $orderTs = payments_timestamp_or_null((string) ($order['created_at'] ?? ''));
        if ($orderTs !== null && $orderTs < $fromTs) {
            return false;
        }
    }

    $toTs = payments_timestamp_or_null((string) ($filters['dateTo'] ?? ''));
    if ($toTs !== null) {
        $orderTs = payments_timestamp_or_null((string) ($order['created_at'] ?? ''));
        if ($orderTs !== null && $orderTs > ($toTs + 86399)) {
            return false;
        }
    }

    $query = dent_utf8_strtolower(dent_clean_text((string) ($filters['query'] ?? ''), 120));
    if ($query !== '') {
        $itemId = (int) ($order['item_id'] ?? 0);
        $item = $itemById[$itemId] ?? null;
        $haystacks = [
            (string) ($order['payer_name'] ?? ''),
            (string) ($order['payer_phone'] ?? ''),
            (string) ($order['payer_student_number'] ?? ''),
            (string) ($order['authority'] ?? ''),
            (string) ($order['ref_id'] ?? ''),
            is_array($item) ? (string) ($item['title'] ?? '') : '',
        ];
        $found = false;
        foreach ($haystacks as $haystack) {
            if (str_contains(dent_utf8_strtolower((string) $haystack), $query)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            return false;
        }
    }

    return true;
}

function payments_api_redirect_to_result(string $orderToken): void
{
    $target = PAYMENTS_PUBLIC_RESULT_PATH . '?orderToken=' . rawurlencode($orderToken);
    header('Location: ' . $target, true, 302);
    exit;
}

function payments_api_append_order_notification(
    array &$store,
    array $order,
    ?array $item,
    bool $success
): array {
    $orderId = (int) ($order['id'] ?? 0);
    $type = $success ? PAYMENTS_NOTIFICATION_TYPE_ORDER_SUCCESS : PAYMENTS_NOTIFICATION_TYPE_ORDER_FAILED;
    $title = $success ? 'پرداخت موفق جدید' : 'پرداخت ناموفق یا لغوشده';
    $itemTitle = $item ? (string) ($item['title'] ?? '') : 'آیتم نامشخص';
    $payerName = (string) ($order['payer_name'] ?? 'کاربر');
    $amount = number_format(max(0, (int) ($order['amount'] ?? 0)));
    $statusLabel = payments_api_status_label((string) ($order['status'] ?? ''));

    $body = sprintf(
        '%s | %s | %s ریال | وضعیت: %s',
        $itemTitle,
        $payerName,
        $amount,
        $statusLabel
    );

    return payments_append_notification($store, $type, $title, $body, $orderId);
}

function payments_api_trigger_notification_hook(array $notification, array $order, ?array $item): void
{
    $webhook = trim((string) getenv('DENT_PAYMENT_NOTIFICATION_WEBHOOK'));
    if ($webhook === '') {
        return;
    }

    $payload = [
        'event' => 'payment-notification',
        'notification' => $notification,
        'order' => payments_owner_order_payload($order, $item),
        'item' => $item ? payments_owner_item_payload($item) : null,
    ];

    payments_gateway_http_post_json($webhook, $payload, 8);
}

$action = dent_request_action();

if ($action === 'listPublicItems') {
    payments_api_require_method(['GET']);
    $store = payments_read_store();

    $items = [];
    foreach ($store['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }

        if ((string) ($item['status'] ?? '') !== PAYMENTS_ITEM_STATUS_ACTIVE) {
            continue;
        }

        $items[] = payments_public_item_payload($item);
    }

    usort($items, static function (array $left, array $right): int {
        return strcmp((string) ($right['updatedAt'] ?? ''), (string) ($left['updatedAt'] ?? ''));
    });

    dent_json_response([
        'success' => true,
        'items' => $items,
    ]);
}

if ($action === 'publicItem') {
    payments_api_require_method(['GET']);
    $slug = payments_clean_slug((string) ($_GET['slug'] ?? ''));
    if ($slug === '') {
        dent_error('شناسه لینک پرداخت معتبر نیست.', 422);
    }

    $store = payments_read_store();
    $itemIndex = payments_find_item_index_by_slug($store, $slug);
    if ($itemIndex < 0) {
        dent_error('آیتم پرداخت موردنظر پیدا نشد.', 404);
    }

    $item = $store['items'][$itemIndex];
    $payload = payments_public_item_payload($item);
    $payload['statusMessage'] = payments_api_item_status_message($payload['state']);

    dent_json_response([
        'success' => true,
        'item' => $payload,
    ]);
}

if ($action === 'createOrder') {
    payments_api_require_method(['POST']);

    $slug = payments_clean_slug((string) ($_POST['slug'] ?? ''));
    if ($slug === '') {
        dent_error('لینک آیتم پرداخت معتبر نیست.', 422);
    }

    $payerName = dent_clean_text((string) ($_POST['payerName'] ?? ''), 120);
    if ($payerName === '') {
        dent_error('نام پرداخت‌کننده الزامی است.', 422);
    }

    $payerPhone = payments_normalize_phone((string) ($_POST['payerPhone'] ?? ''));
    if ($payerPhone === '' || strlen($payerPhone) < 10 || strlen($payerPhone) > 14) {
        dent_error('شماره موبایل پرداخت‌کننده معتبر نیست.', 422);
    }

    $payerStudentNumber = dent_normalize_student_number((string) ($_POST['payerStudentNumber'] ?? ''));
    $extraFormData = payments_api_parse_extra_form_data($_POST['extraFormData'] ?? ($_POST['extra_form_data'] ?? ''));
    $requestedGateway = payments_gateway_clean((string) ($_POST['gateway'] ?? ''));
    $gateway = $requestedGateway !== '' ? $requestedGateway : payments_gateway_default();
    $user = dent_current_user();
    $userId = $user ? dent_normalize_student_number((string) ($user['studentNumber'] ?? '')) : '';
    if ($payerStudentNumber === '' && $userId !== '') {
        $payerStudentNumber = $userId;
    }

    try {
        $created = payments_with_store_lock(static function (array &$store) use (
            $slug,
            $payerName,
            $payerPhone,
            $payerStudentNumber,
            $extraFormData,
            $gateway,
            $userId
        ): array {
            $itemIndex = payments_find_item_index_by_slug($store, $slug);
            if ($itemIndex < 0) {
                throw new PaymentsApiException('آیتم پرداخت موردنظر پیدا نشد.', 404);
            }

            $item = $store['items'][$itemIndex];
            $publicItem = payments_public_item_payload($item);
            $state = is_array($publicItem['state'] ?? null) ? $publicItem['state'] : ['key' => 'inactive'];
            if (!(bool) ($state['isPayable'] ?? false)) {
                throw new PaymentsApiException(payments_api_item_status_message($state), 422);
            }

            $requiredFields = is_array($item['required_fields'] ?? null) ? $item['required_fields'] : [];
            foreach ($requiredFields as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $name = (string) ($field['name'] ?? '');
                if ($name === '') {
                    continue;
                }

                $required = (bool) ($field['required'] ?? true);
                $value = dent_clean_text((string) ($extraFormData[$name] ?? ''), (int) ($field['maxLength'] ?? 140));
                if ($required && $value === '') {
                    $label = dent_clean_text((string) ($field['label'] ?? $name), 80);
                    throw new PaymentsApiException('فیلد «' . $label . '» الزامی است.', 422);
                }
                $extraFormData[$name] = $value;
            }

            $amount = max(0, (int) ($item['price'] ?? 0));
            if ($amount <= 0) {
                throw new PaymentsApiException('مبلغ آیتم پرداخت معتبر نیست.', 422);
            }

            $now = dent_iso_now();
            $order = [
                'id' => payments_next_order_id($store),
                'item_id' => (int) ($item['id'] ?? 0),
                'user_id' => $userId,
                'payer_name' => $payerName,
                'payer_phone' => $payerPhone,
                'payer_student_number' => $payerStudentNumber,
                'extra_form_data' => $extraFormData,
                'amount' => $amount,
                'gateway' => $gateway,
                'authority' => '',
                'ref_id' => '',
                'status' => PAYMENTS_ORDER_STATUS_PENDING,
                'gateway_response_snapshot' => [
                    'created' => ['at' => $now],
                ],
                'created_at' => $now,
                'paid_at' => '',
                'verified_at' => '',
                'public_token' => payments_random_token(),
            ];

            $store['orders'][] = $order;
            return [
                'order' => $order,
                'item' => $item,
            ];
        });
    } catch (PaymentsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode());
    }

    if (!is_array($created['order'] ?? null) || !is_array($created['item'] ?? null)) {
        dent_error('ایجاد سفارش پرداخت انجام نشد.', 500);
    }

    $order = $created['order'];
    $item = $created['item'];
    $orderToken = (string) ($order['public_token'] ?? '');
    $callbackUrl = payments_api_absolute_url(
        '/api/payments_api.php?action=callback&orderToken=' . rawurlencode($orderToken)
    );

    $startResult = payments_gateway_start_payment((string) ($order['gateway'] ?? ''), $item, $order, [
        'callbackUrl' => $callbackUrl,
        'description' => 'پرداخت بابت ' . (string) ($item['title'] ?? 'آیتم پرداخت'),
        'mobile' => $payerPhone,
    ]);

    payments_log_gateway_event('start-request', [
        'orderId' => (int) ($order['id'] ?? 0),
        'gateway' => (string) ($order['gateway'] ?? ''),
        'result' => $startResult,
    ]);

    if (!(bool) ($startResult['success'] ?? false)) {
        $updated = payments_with_store_lock(static function (array &$store) use ($order, $startResult): array {
            $orderIndex = payments_find_order_index_by_id($store, (int) ($order['id'] ?? 0));
            if ($orderIndex < 0) {
                return [];
            }

            $current = $store['orders'][$orderIndex];
            if ((string) ($current['status'] ?? '') === PAYMENTS_ORDER_STATUS_PENDING) {
                $current['status'] = PAYMENTS_ORDER_STATUS_FAILED;
            }
            $snapshot = is_array($current['gateway_response_snapshot'] ?? null) ? $current['gateway_response_snapshot'] : [];
            $snapshot['start'] = $startResult;
            $current['gateway_response_snapshot'] = $snapshot;
            $store['orders'][$orderIndex] = $current;

            $item = null;
            $itemIndex = payments_find_item_index_by_id($store, (int) ($current['item_id'] ?? 0));
            if ($itemIndex >= 0) {
                $item = $store['items'][$itemIndex];
            }

            $notification = payments_api_append_order_notification($store, $current, $item, false);
            return [
                'order' => $current,
                'item' => $item,
                'notification' => $notification,
            ];
        });

        if (is_array($updated['notification'] ?? null) && is_array($updated['order'] ?? null)) {
            payments_api_trigger_notification_hook(
                $updated['notification'],
                $updated['order'],
                is_array($updated['item'] ?? null) ? $updated['item'] : null
            );
        }

        dent_error((string) ($startResult['error'] ?? 'ایجاد درخواست درگاه پرداخت انجام نشد.'), 503, [
            'orderToken' => $orderToken,
        ]);
    }

    $authority = dent_clean_text((string) ($startResult['authority'] ?? ''), 120);
    $redirectUrl = dent_clean_text((string) ($startResult['redirectUrl'] ?? ''), 900);
    if ($redirectUrl === '') {
        dent_error('لینک انتقال به درگاه پرداخت دریافت نشد.', 500);
    }

    payments_with_store_lock(static function (array &$store) use ($order, $authority, $startResult): void {
        $orderIndex = payments_find_order_index_by_id($store, (int) ($order['id'] ?? 0));
        if ($orderIndex < 0) {
            return;
        }

        $current = $store['orders'][$orderIndex];
        $current['authority'] = $authority;
        $snapshot = is_array($current['gateway_response_snapshot'] ?? null) ? $current['gateway_response_snapshot'] : [];
        $snapshot['start'] = $startResult;
        $current['gateway_response_snapshot'] = $snapshot;
        $store['orders'][$orderIndex] = $current;
    });

    dent_json_response([
        'success' => true,
        'orderToken' => $orderToken,
        'redirectUrl' => $redirectUrl,
        'resultUrl' => PAYMENTS_PUBLIC_RESULT_PATH . '?orderToken=' . rawurlencode($orderToken),
    ]);
}

if ($action === 'callback') {
    payments_api_require_method(['GET', 'POST']);

    $orderToken = dent_clean_text((string) ($_GET['orderToken'] ?? ($_POST['orderToken'] ?? '')), 120);
    if ($orderToken === '') {
        dent_error('شناسه سفارش callback معتبر نیست.', 422);
    }

    $authority = dent_clean_text((string) ($_GET['Authority'] ?? ($_GET['authority'] ?? ($_POST['Authority'] ?? ($_POST['authority'] ?? '')))), 120);
    $gatewayStatus = dent_clean_text((string) ($_GET['Status'] ?? ($_GET['status'] ?? ($_POST['Status'] ?? ($_POST['status'] ?? '')))), 40);
    $gatewayStatusLower = strtolower($gatewayStatus);

    $store = payments_read_store();
    $orderIndex = payments_find_order_index_by_token($store, $orderToken);
    if ($orderIndex < 0) {
        dent_error('سفارش callback پیدا نشد.', 404);
    }
    $order = $store['orders'][$orderIndex];

    if ((string) ($order['status'] ?? '') === PAYMENTS_ORDER_STATUS_SUCCESS) {
        payments_api_redirect_to_result($orderToken);
    }

    if ($authority === '') {
        $authority = (string) ($order['authority'] ?? '');
    }

    $canceledStatuses = ['nok', 'cancel', 'canceled', 'cancelled', 'failed'];
    if (in_array($gatewayStatusLower, $canceledStatuses, true)) {
        payments_with_store_lock(static function (array &$store) use ($orderToken, $gatewayStatus, $authority): void {
            $orderIndex = payments_find_order_index_by_token($store, $orderToken);
            if ($orderIndex < 0) {
                return;
            }

            $current = $store['orders'][$orderIndex];
            if ((string) ($current['status'] ?? '') === PAYMENTS_ORDER_STATUS_PENDING) {
                $current['status'] = PAYMENTS_ORDER_STATUS_CANCELED;
            }

            if ($authority !== '' && (string) ($current['authority'] ?? '') === '') {
                $current['authority'] = $authority;
            }

            $snapshot = is_array($current['gateway_response_snapshot'] ?? null) ? $current['gateway_response_snapshot'] : [];
            $snapshot['callback'] = [
                'authority' => $authority,
                'status' => $gatewayStatus,
                'at' => dent_iso_now(),
            ];
            $current['gateway_response_snapshot'] = $snapshot;
            $store['orders'][$orderIndex] = $current;

            $item = null;
            $itemIndex = payments_find_item_index_by_id($store, (int) ($current['item_id'] ?? 0));
            if ($itemIndex >= 0) {
                $item = $store['items'][$itemIndex];
            }
            payments_api_append_order_notification($store, $current, $item, false);
        });

        payments_api_redirect_to_result($orderToken);
    }

    $verifyResult = payments_gateway_verify_payment((string) ($order['gateway'] ?? ''), $order, [
        'authority' => $authority,
        'status' => $gatewayStatusLower !== '' ? $gatewayStatusLower : 'ok',
    ]);

    payments_log_gateway_event('verify-request', [
        'orderId' => (int) ($order['id'] ?? 0),
        'gateway' => (string) ($order['gateway'] ?? ''),
        'result' => $verifyResult,
    ]);

    $hookPayload = payments_with_store_lock(static function (array &$store) use ($orderToken, $authority, $gatewayStatus, $verifyResult): array {
        $orderIndex = payments_find_order_index_by_token($store, $orderToken);
        if ($orderIndex < 0) {
            return [];
        }

        $current = $store['orders'][$orderIndex];
        $previousStatus = (string) ($current['status'] ?? PAYMENTS_ORDER_STATUS_PENDING);
        if ($previousStatus === PAYMENTS_ORDER_STATUS_SUCCESS) {
            return [];
        }

        if ($authority !== '') {
            if ((string) ($current['authority'] ?? '') === '') {
                $current['authority'] = $authority;
            } elseif ((string) ($current['authority'] ?? '') !== $authority) {
                $current['status'] = PAYMENTS_ORDER_STATUS_FAILED;
            }
        }

        if ((bool) ($verifyResult['verified'] ?? false)) {
            $current['status'] = PAYMENTS_ORDER_STATUS_SUCCESS;
            $current['ref_id'] = dent_clean_text((string) ($verifyResult['refId'] ?? ''), 120);
            if ((string) ($current['paid_at'] ?? '') === '') {
                $current['paid_at'] = dent_iso_now();
            }
            $current['verified_at'] = dent_iso_now();
        } else {
            $nextStatus = (string) ($verifyResult['status'] ?? PAYMENTS_ORDER_STATUS_FAILED);
            if (!in_array(
                $nextStatus,
                [PAYMENTS_ORDER_STATUS_FAILED, PAYMENTS_ORDER_STATUS_CANCELED, PAYMENTS_ORDER_STATUS_EXPIRED, PAYMENTS_ORDER_STATUS_PENDING],
                true
            )) {
                $nextStatus = PAYMENTS_ORDER_STATUS_FAILED;
            }
            $current['status'] = $nextStatus;
        }

        $snapshot = is_array($current['gateway_response_snapshot'] ?? null) ? $current['gateway_response_snapshot'] : [];
        $snapshot['callback'] = [
            'authority' => $authority,
            'status' => $gatewayStatus,
            'at' => dent_iso_now(),
        ];
        $snapshot['verify'] = $verifyResult;
        $current['gateway_response_snapshot'] = $snapshot;
        $store['orders'][$orderIndex] = $current;

        if ($previousStatus !== (string) ($current['status'] ?? '')) {
            $item = null;
            $itemIndex = payments_find_item_index_by_id($store, (int) ($current['item_id'] ?? 0));
            if ($itemIndex >= 0) {
                $item = $store['items'][$itemIndex];
            }
            $notification = payments_api_append_order_notification(
                $store,
                $current,
                $item,
                (string) ($current['status'] ?? '') === PAYMENTS_ORDER_STATUS_SUCCESS
            );

            return [
                'order' => $current,
                'item' => $item,
                'notification' => $notification,
            ];
        }

        return [];
    });

    if (is_array($hookPayload['notification'] ?? null) && is_array($hookPayload['order'] ?? null)) {
        payments_api_trigger_notification_hook(
            $hookPayload['notification'],
            $hookPayload['order'],
            is_array($hookPayload['item'] ?? null) ? $hookPayload['item'] : null
        );
    }

    payments_api_redirect_to_result($orderToken);
}

if ($action === 'publicOrderResult') {
    payments_api_require_method(['GET']);
    $orderToken = dent_clean_text((string) ($_GET['orderToken'] ?? ''), 120);
    if ($orderToken === '') {
        dent_error('شناسه سفارش معتبر نیست.', 422);
    }

    $store = payments_read_store();
    $orderIndex = payments_find_order_index_by_token($store, $orderToken);
    if ($orderIndex < 0) {
        dent_error('سفارش موردنظر پیدا نشد.', 404);
    }

    $order = $store['orders'][$orderIndex];
    $item = null;
    $itemIndex = payments_find_item_index_by_id($store, (int) ($order['item_id'] ?? 0));
    if ($itemIndex >= 0) {
        $item = $store['items'][$itemIndex];
    }

    dent_json_response([
        'success' => true,
        'order' => payments_order_public_result_payload($order, $item),
    ]);
}

if ($action === 'mockGateway') {
    payments_api_require_method(['GET']);

    $orderToken = dent_clean_text((string) ($_GET['orderToken'] ?? ''), 120);
    $result = strtolower(dent_clean_text((string) ($_GET['result'] ?? 'success'), 20));
    $authority = dent_clean_text((string) ($_GET['authority'] ?? ''), 120);
    if ($orderToken === '') {
        dent_error('شناسه سفارش درگاه آزمایشی معتبر نیست.', 422);
    }

    $status = 'OK';
    if (!in_array($result, ['success', 'ok'], true)) {
        $status = 'NOK';
    }

    $callbackUrl = '/api/payments_api.php?action=callback'
        . '&orderToken=' . rawurlencode($orderToken)
        . '&Status=' . rawurlencode($status)
        . '&Authority=' . rawurlencode($authority);

    header('Location: ' . $callbackUrl, true, 302);
    exit;
}

if ($action === 'ownerDashboard') {
    payments_api_require_method(['GET']);
    dent_require_owner();

    $store = payments_read_store();
    $itemById = [];
    foreach ($store['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemById[(int) ($item['id'] ?? 0)] = $item;
    }

    $orders = is_array($store['orders'] ?? null) ? $store['orders'] : [];
    $summary = payments_api_orders_summary($orders);

    $recentOrders = [];
    foreach (array_slice($orders, 0, 10) as $order) {
        if (!is_array($order)) {
            continue;
        }
        $item = $itemById[(int) ($order['item_id'] ?? 0)] ?? null;
        $recentOrders[] = payments_owner_order_payload($order, is_array($item) ? $item : null);
    }

    $items = [];
    foreach ($store['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $items[] = payments_owner_item_payload($item);
    }

    $notifications = [];
    foreach (array_slice($store['notifications'], 0, 24) as $notification) {
        if (!is_array($notification)) {
            continue;
        }
        $notifications[] = $notification;
    }

    dent_json_response([
        'success' => true,
        'summary' => array_merge($summary, [
            'totalItems' => count($items),
            'activeItems' => count(array_filter($items, static function (array $item): bool {
                return (string) ($item['status'] ?? '') === PAYMENTS_ITEM_STATUS_ACTIVE;
            })),
            'unreadNotifications' => payments_api_notifications_unread_count($notifications),
        ]),
        'items' => $items,
        'recentOrders' => $recentOrders,
        'notifications' => $notifications,
    ]);
}

if ($action === 'ownerItems') {
    payments_api_require_method(['GET']);
    dent_require_owner();

    $store = payments_read_store();
    $items = [];
    foreach ($store['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $items[] = payments_owner_item_payload($item);
    }

    dent_json_response([
        'success' => true,
        'items' => $items,
    ]);
}

if ($action === 'ownerSaveItem') {
    payments_api_require_method(['POST']);
    dent_require_owner();

    $itemId = max(0, (int) ($_POST['id'] ?? 0));
    $title = dent_clean_text((string) ($_POST['title'] ?? ''), 140);
    if ($title === '') {
        dent_error('عنوان آیتم پرداخت الزامی است.', 422);
    }

    $slug = payments_clean_slug((string) ($_POST['slug'] ?? ''));
    if ($slug === '') {
        dent_error('اسلاگ آیتم پرداخت معتبر نیست.', 422);
    }

    $price = max(0, (int) dent_normalize_digits((string) ($_POST['price'] ?? '0')));
    if ($price <= 0) {
        dent_error('قیمت آیتم باید بیشتر از صفر باشد.', 422);
    }

    $status = (string) ($_POST['status'] ?? PAYMENTS_ITEM_STATUS_ACTIVE);
    if (!in_array($status, [PAYMENTS_ITEM_STATUS_ACTIVE, PAYMENTS_ITEM_STATUS_INACTIVE], true)) {
        $status = PAYMENTS_ITEM_STATUS_INACTIVE;
    }

    $shortDescription = dent_clean_text((string) ($_POST['shortDescription'] ?? ($_POST['short_description'] ?? '')), 460);
    $fullDescription = dent_clean_text((string) ($_POST['fullDescription'] ?? ($_POST['full_description'] ?? '')), 6000);
    $heroImage = dent_clean_text((string) ($_POST['heroImage'] ?? ($_POST['hero_image'] ?? '')), 420);
    $gallery = payments_api_parse_gallery_input($_POST['gallery'] ?? []);
    $specifications = payments_api_parse_specifications_input($_POST['specifications'] ?? []);
    $requiredFields = payments_api_parse_required_fields_input($_POST['requiredFields'] ?? ($_POST['required_fields'] ?? []));
    $successMessage = dent_clean_text((string) ($_POST['successMessage'] ?? ($_POST['success_message'] ?? 'پرداخت شما با موفقیت ثبت شد.')), 600);
    $failureMessage = dent_clean_text((string) ($_POST['failureMessage'] ?? ($_POST['failure_message'] ?? 'پرداخت شما ناموفق بود.')), 600);
    $startsAt = payments_normalize_datetime_string((string) ($_POST['startsAt'] ?? ($_POST['starts_at'] ?? '')), '');
    $expiresAt = payments_normalize_datetime_string((string) ($_POST['expiresAt'] ?? ($_POST['expires_at'] ?? '')), '');
    $capacity = payments_normalize_positive_int_nullable($_POST['capacity'] ?? null, 1000000);

    if ($startsAt !== '' && $expiresAt !== '' && strtotime($startsAt) !== false && strtotime($expiresAt) !== false) {
        if ((int) strtotime($expiresAt) <= (int) strtotime($startsAt)) {
            dent_error('تاریخ پایان باید بعد از تاریخ شروع باشد.', 422);
        }
    }

    try {
        $saved = payments_with_store_lock(static function (array &$store) use (
            $itemId,
            $title,
            $slug,
            $price,
            $status,
            $shortDescription,
            $fullDescription,
            $heroImage,
            $gallery,
            $specifications,
            $requiredFields,
            $successMessage,
            $failureMessage,
            $startsAt,
            $expiresAt,
            $capacity
        ): array {
            foreach ($store['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if ((string) ($item['slug'] ?? '') !== $slug) {
                    continue;
                }
                if ($itemId > 0 && (int) ($item['id'] ?? 0) === $itemId) {
                    continue;
                }
                throw new PaymentsApiException('این اسلاگ قبلاً استفاده شده است.', 422);
            }

            $now = dent_iso_now();
            $payload = [
                'id' => $itemId > 0 ? $itemId : payments_next_item_id($store),
                'slug' => $slug,
                'title' => $title,
                'short_description' => $shortDescription,
                'full_description' => $fullDescription,
                'hero_image' => $heroImage,
                'gallery' => $gallery,
                'specifications' => $specifications,
                'price' => $price,
                'status' => $status,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'capacity' => $capacity,
                'required_fields' => $requiredFields,
                'success_message' => $successMessage,
                'failure_message' => $failureMessage,
                'updated_at' => $now,
            ];

            if ($itemId > 0) {
                $index = payments_find_item_index_by_id($store, $itemId);
                if ($index < 0) {
                    throw new PaymentsApiException('آیتم برای ویرایش پیدا نشد.', 404);
                }
                $existing = is_array($store['items'][$index]) ? $store['items'][$index] : [];
                $payload['created_at'] = (string) ($existing['created_at'] ?? $now);
                $payload['sold_count'] = max(0, (int) ($existing['sold_count'] ?? 0));
                $store['items'][$index] = array_merge($existing, $payload);
                return $store['items'][$index];
            }

            $payload['created_at'] = $now;
            $payload['sold_count'] = 0;
            $store['items'][] = $payload;
            return $payload;
        });
    } catch (PaymentsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode());
    }

    dent_json_response([
        'success' => true,
        'item' => payments_owner_item_payload($saved),
        'message' => $itemId > 0 ? 'آیتم پرداخت ویرایش شد.' : 'آیتم پرداخت جدید ساخته شد.',
    ]);
}

if ($action === 'ownerToggleItem') {
    payments_api_require_method(['POST']);
    dent_require_owner();

    $itemId = max(0, (int) ($_POST['id'] ?? 0));
    if ($itemId <= 0) {
        dent_error('شناسه آیتم معتبر نیست.', 422);
    }

    $enabled = filter_var($_POST['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    $status = $enabled ? PAYMENTS_ITEM_STATUS_ACTIVE : PAYMENTS_ITEM_STATUS_INACTIVE;

    try {
        $item = payments_with_store_lock(static function (array &$store) use ($itemId, $status): array {
            $index = payments_find_item_index_by_id($store, $itemId);
            if ($index < 0) {
                throw new PaymentsApiException('آیتم پرداخت پیدا نشد.', 404);
            }

            $current = $store['items'][$index];
            $current['status'] = $status;
            $current['updated_at'] = dent_iso_now();
            $store['items'][$index] = $current;
            return $current;
        });
    } catch (PaymentsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode());
    }

    dent_json_response([
        'success' => true,
        'item' => payments_owner_item_payload($item),
        'message' => $enabled ? 'آیتم فعال شد.' : 'آیتم غیرفعال شد.',
    ]);
}

if ($action === 'ownerOrders') {
    payments_api_require_method(['GET']);
    dent_require_owner();

    $store = payments_read_store();
    $itemById = [];
    foreach ($store['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemById[(int) ($item['id'] ?? 0)] = $item;
    }

    $filters = [
        'itemId' => (int) ($_GET['itemId'] ?? 0),
        'status' => dent_clean_text((string) ($_GET['status'] ?? 'all'), 20),
        'dateFrom' => dent_clean_text((string) ($_GET['dateFrom'] ?? ''), 40),
        'dateTo' => dent_clean_text((string) ($_GET['dateTo'] ?? ''), 40),
        'query' => dent_clean_text((string) ($_GET['query'] ?? ''), 120),
    ];

    $filteredOrders = [];
    foreach ($store['orders'] as $order) {
        if (!is_array($order)) {
            continue;
        }
        if (!payments_api_order_matches_filters($order, $itemById, $filters)) {
            continue;
        }
        $item = $itemById[(int) ($order['item_id'] ?? 0)] ?? null;
        $filteredOrders[] = payments_owner_order_payload($order, is_array($item) ? $item : null);
    }

    usort($filteredOrders, static function (array $left, array $right): int {
        return strcmp((string) ($right['createdAt'] ?? ''), (string) ($left['createdAt'] ?? ''));
    });

    dent_json_response([
        'success' => true,
        'filters' => $filters,
        'summary' => payments_api_orders_summary($store['orders']),
        'filteredSummary' => payments_api_orders_summary(array_map(static function (array $orderPayload): array {
            return [
                'status' => $orderPayload['status'] ?? PAYMENTS_ORDER_STATUS_PENDING,
                'amount' => (int) ($orderPayload['amount'] ?? 0),
            ];
        }, $filteredOrders)),
        'orders' => $filteredOrders,
    ]);
}

if ($action === 'ownerOrderDetails') {
    payments_api_require_method(['GET']);
    dent_require_owner();

    $orderId = max(0, (int) ($_GET['id'] ?? 0));
    if ($orderId <= 0) {
        dent_error('شناسه سفارش معتبر نیست.', 422);
    }

    $store = payments_read_store();
    $orderIndex = payments_find_order_index_by_id($store, $orderId);
    if ($orderIndex < 0) {
        dent_error('سفارش موردنظر پیدا نشد.', 404);
    }

    $order = $store['orders'][$orderIndex];
    $item = null;
    $itemIndex = payments_find_item_index_by_id($store, (int) ($order['item_id'] ?? 0));
    if ($itemIndex >= 0) {
        $item = $store['items'][$itemIndex];
    }

    dent_json_response([
        'success' => true,
        'order' => payments_owner_order_payload($order, $item),
        'gatewaySnapshot' => is_array($order['gateway_response_snapshot'] ?? null) ? $order['gateway_response_snapshot'] : [],
    ]);
}

if ($action === 'ownerNotifications') {
    payments_api_require_method(['GET']);
    dent_require_owner();

    $store = payments_read_store();
    $limit = max(1, min(200, (int) ($_GET['limit'] ?? 60)));
    $notifications = [];
    foreach (array_slice($store['notifications'], 0, $limit) as $notification) {
        if (!is_array($notification)) {
            continue;
        }
        $notifications[] = $notification;
    }

    dent_json_response([
        'success' => true,
        'unreadCount' => payments_api_notifications_unread_count($notifications),
        'notifications' => $notifications,
    ]);
}

if ($action === 'ownerMarkNotificationRead') {
    payments_api_require_method(['POST']);
    dent_require_owner();

    $notificationId = max(0, (int) ($_POST['id'] ?? 0));
    $markAll = (string) ($_POST['all'] ?? '0') === '1';

    $updatedCount = payments_with_store_lock(static function (array &$store) use ($notificationId, $markAll): int {
        $count = 0;
        foreach ($store['notifications'] as $index => $notification) {
            if (!is_array($notification)) {
                continue;
            }
            if (!$markAll && (int) ($notification['id'] ?? 0) !== $notificationId) {
                continue;
            }
            if (trim((string) ($notification['read_at'] ?? '')) !== '') {
                continue;
            }
            $notification['read_at'] = dent_iso_now();
            $store['notifications'][$index] = $notification;
            $count++;
            if (!$markAll) {
                break;
            }
        }
        return $count;
    });

    dent_json_response([
        'success' => true,
        'updatedCount' => $updatedCount,
    ]);
}

dent_error('درخواست نامعتبر است.', 404);
