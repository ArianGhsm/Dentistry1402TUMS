<?php
declare(strict_types=1);

/** @return list<array<string,mixed>> */
function dent_bot_payment_normalize_cart_checkout_items($value): array
{
    if (!is_array($value) || $value === [] || count($value) > 20) {
        dent_error('سبد خرید معتبر نیست.', 422, ['code' => 'BOT_CART_INVALID']);
    }

    $items = [];
    $seen = [];
    $nowEpoch = time();
    foreach ($value as $raw) {
        if (!is_array($raw)) {
            dent_error('یکی از محصولات سبد معتبر نیست.', 422, ['code' => 'BOT_CART_ITEM_INVALID']);
        }

        $offerRef = trim((string) ($raw['offerRef'] ?? ''));
        $itemKey = dent_clean_text((string) ($raw['itemKey'] ?? ''), 180);
        $kind = dent_clean_text((string) ($raw['kind'] ?? 'offer'), 32);
        $title = dent_clean_text((string) ($raw['title'] ?? ''), 160);
        $description = dent_clean_text((string) ($raw['description'] ?? ''), 360);
        $amount = max(0, (int) dent_normalize_digits((string) ($raw['amountRials'] ?? '0')));
        $version = max(1, min(1000000, (int) ($raw['productVersion'] ?? 1)));
        $availableFrom = dent_bot_payment_iso((string) ($raw['availableFrom'] ?? ''));
        $expiresAt = dent_bot_payment_iso((string) ($raw['expiresAt'] ?? ''));
        $capacity = max(0, min(1000000, (int) ($raw['capacity'] ?? 0)));
        $maxPerUser = max(0, min(10000, (int) ($raw['maxPurchasesPerUser'] ?? 1)));
        $fulfillment = dent_bot_payment_safe_fulfillment($raw['fulfillment'] ?? []);

        if (preg_match('/^[A-Za-z0-9_-]{16,80}$/D', $offerRef) !== 1
            || $itemKey === ''
            || $title === ''
            || $amount < 10000
            || $amount > 100000000000) {
            dent_error('یکی از محصولات سبد معتبر نیست.', 422, ['code' => 'BOT_CART_ITEM_INVALID']);
        }
        if (isset($seen[$offerRef])) {
            dent_error('یک محصول در سبد تکرار شده است.', 409, ['code' => 'BOT_CART_DUPLICATE_ITEM']);
        }
        if (($availableFrom !== '' && (int) strtotime($availableFrom) > $nowEpoch)
            || ($expiresAt !== '' && (int) strtotime($expiresAt) <= $nowEpoch)) {
            dent_error('یکی از محصولات سبد دیگر قابل خرید نیست.', 409, ['code' => 'PRODUCT_NOT_AVAILABLE']);
        }

        $seen[$offerRef] = true;
        $item = [
            'itemKey' => $itemKey,
            'kind' => $kind,
            'offerRef' => $offerRef,
            'title' => $title,
            'description' => $description,
            'amountRials' => $amount,
            'productVersion' => $version,
            'availableFrom' => $availableFrom,
            'expiresAt' => $expiresAt,
            'capacity' => $capacity,
            'maxPurchasesPerUser' => $maxPerUser,
            'fulfillment' => $fulfillment,
        ];
        foreach (['term', 'sessionNo'] as $integerKey) {
            if (isset($raw[$integerKey])) {
                $item[$integerKey] = max(0, (int) $raw[$integerKey]);
            }
        }
        foreach (['courseCode', 'courseTag', 'billingPeriod'] as $stringKey) {
            $clean = dent_clean_text((string) ($raw[$stringKey] ?? ''), 100);
            if ($clean !== '') {
                $item[$stringKey] = $clean;
            }
        }
        $items[] = $item;
    }

    return $items;
}

function dent_bot_payment_cart_discount(array $payload, int $subtotal, array $orders): array
{
    $raw = is_array($payload['discount'] ?? null) ? $payload['discount'] : [];
    if ($raw === []) {
        return ['code' => '', 'amount' => 0, 'snapshot' => []];
    }

    $code = strtoupper(preg_replace('/\s+/', '', (string) ($raw['code'] ?? '')) ?? '');
    $kind = strtolower(trim((string) ($raw['kind'] ?? '')));
    $value = max(0, (int) ($raw['amount'] ?? 0));
    $minimum = max(0, (int) ($raw['minSubtotalRials'] ?? 0));
    $maxUses = max(0, min(1000000, (int) ($raw['maxUses'] ?? 0)));
    $expiresAt = dent_bot_payment_iso((string) ($raw['expiresAt'] ?? ''));

    if (preg_match('/^[A-Z0-9][A-Z0-9_-]{3,23}$/D', $code) !== 1
        || !in_array($kind, ['percent', 'fixed'], true)
        || ($kind === 'percent' && ($value < 1 || $value > 90))
        || ($kind === 'fixed' && $value < 10000)
        || $subtotal < $minimum
        || ($expiresAt !== '' && (int) strtotime($expiresAt) <= time())) {
        dent_error('کد تخفیف برای این سبد معتبر نیست.', 409, ['code' => 'DISCOUNT_CODE_INVALID']);
    }

    if ($maxUses > 0) {
        $used = 0;
        foreach ($orders as $order) {
            if (!is_array($order) || !dent_bot_payment_is_offer_order($order)) {
                continue;
            }
            if (!hash_equals((string) ($order['discount_code'] ?? ''), $code)) {
                continue;
            }
            $status = (string) ($order['status'] ?? PAYMENTS_ORDER_STATUS_PENDING);
            if ($status === PAYMENTS_ORDER_STATUS_SUCCESS
                || ($status === PAYMENTS_ORDER_STATUS_PENDING && dent_bot_payment_pending_reserves_slot($order))) {
                $used++;
            }
        }
        if ($used >= $maxUses) {
            dent_error('ظرفیت استفاده از این کد تخفیف تکمیل شده است.', 409, ['code' => 'DISCOUNT_USAGE_LIMIT_REACHED']);
        }
    }

    $discount = $kind === 'percent'
        ? intdiv($subtotal * $value, 100)
        : min($subtotal, $value);
    if ($subtotal - $discount < 10000) {
        dent_error('مبلغ نهایی پس از تخفیف برای درگاه معتبر نیست.', 409, ['code' => 'DISCOUNT_AMOUNT_INVALID']);
    }

    return [
        'code' => $code,
        'amount' => $discount,
        'snapshot' => [
            'code' => $code,
            'kind' => $kind,
            'amount' => $value,
            'minSubtotalRials' => $minimum,
            'maxUses' => $maxUses,
            'expiresAt' => $expiresAt,
        ],
    ];
}

/** @return list<array<string,mixed>> */
function dent_bot_payment_cart_order_lines(array $items, int $discountAmount): array
{
    $subtotal = array_sum(array_map(
        static fn(array $item): int => (int) ($item['amountRials'] ?? 0),
        $items
    ));
    $remaining = max(0, $discountAmount);
    $last = count($items) - 1;
    $lines = [];

    foreach ($items as $index => $item) {
        $lineSubtotal = max(0, (int) ($item['amountRials'] ?? 0));
        $lineDiscount = $index === $last
            ? min($lineSubtotal, $remaining)
            : min($lineSubtotal, $subtotal > 0 ? intdiv($discountAmount * $lineSubtotal, $subtotal) : 0);
        $remaining -= $lineDiscount;
        $lines[] = [
            'item_id' => 0,
            'slug' => (string) ($item['offerRef'] ?? ''),
            'title' => (string) ($item['title'] ?? 'محصول'),
            'quantity' => 1,
            'unit_price' => $lineSubtotal,
            'subtotal' => $lineSubtotal,
            'discount_code' => '',
            'discount_amount' => $lineDiscount,
            'amount' => max(0, $lineSubtotal - $lineDiscount),
            'extra_form_data' => ['source' => 'bot-cart-line'],
        ];
    }

    return $lines;
}


function dent_bot_create_cart_payment(array $user, string $platform, string $platformUserId, array $payload): array
{
    dent_bot_payment_require_v2($payload);
    $items = dent_bot_payment_normalize_cart_checkout_items($payload['items'] ?? []);
    $subtotal = array_sum(array_map(static fn(array $item): int => (int) $item['amountRials'], $items));
    if ($subtotal < 10000 || $subtotal > 100000000000) {
        dent_error('مبلغ سبد خرید معتبر نیست.', 422, ['code' => 'PAYMENT_AMOUNT_INVALID']);
    }

    $requestRef = dent_bot_payment_request_ref(
        $platform,
        $platformUserId,
        (string) ($payload['requestId'] ?? '')
    );
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $payerKeys = dent_bot_payment_payer_keys($user);
    $payerKey = (string) ($payerKeys[0] ?? '');
    $payerName = dent_clean_text((string) ($user['name'] ?? ''), 120);
    $payerPhone = payments_normalize_phone((string) ($user['phoneNumber'] ?? ''));

    if ($payerKey === '' || $payerName === '') {
        dent_error(
            'اطلاعات پروفایل تأییدشده برای پرداخت کامل نیست.',
            422,
            ['code' => 'PAYMENT_PROFILE_INCOMPLETE']
        );
    }
    if ($payerPhone === '' || strlen($payerPhone) < 10 || strlen($payerPhone) > 14) {
        dent_error(
            'شماره موبایل تأییدشده برای پرداخت لازم است.',
            422,
            ['code' => 'PAYMENT_PHONE_REQUIRED']
        );
    }

    $appEnvironment = strtolower(trim((string) dent_env_value('DENT_APP_ENV')));
    $allowMock = in_array($appEnvironment, ['development', 'test'], true);
    $enabledGateways = payments_gateway_enabled_checkout_keys($allowMock);
    $gateway = payments_gateway_default_enabled_checkout($allowMock);
    if ($gateway === '' || !in_array($gateway, $enabledGateways, true)) {
        dent_error(
            'درگاه پرداخت فعالی وجود ندارد.',
            503,
            ['code' => 'PAYMENT_GATEWAY_UNAVAILABLE']
        );
    }

    $created = payments_with_store_lock(static function (array &$store) use (
        $items,
        $subtotal,
        $payload,
        $requestRef,
        $studentNumber,
        $payerKey,
        $payerKeys,
        $payerName,
        $payerPhone,
        $gateway,
        $platform,
        $platformUserId
    ): array {
        $orders = is_array($store['orders'] ?? null) ? $store['orders'] : [];
        $existing = null;
        foreach ($orders as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $extra = dent_bot_payment_extra($candidate);
            if (in_array((string) ($candidate['user_id'] ?? ''), $payerKeys, true)
                && (string) ($extra['bot_request_ref'] ?? '') !== ''
                && hash_equals((string) $extra['bot_request_ref'], $requestRef)) {
                $existing = $candidate;
                break;
            }
        }

        if (is_array($existing) && !dent_bot_payment_existing_needs_gateway_retry($existing)) {
            return ['existing' => $existing];
        }

        foreach ($items as $item) {
            $reservation = dent_bot_payment_reservation_state(
                $orders,
                (string) $item['offerRef'],
                $payerKey,
                $requestRef,
                null,
                $payerKeys
            );
            $reservationError = dent_bot_payment_reservation_error(
                $reservation,
                (int) $item['capacity'],
                (int) $item['maxPurchasesPerUser']
            );
            if ($reservationError === 'PRODUCT_CAPACITY_REACHED') {
                dent_error(
                    'ظرفیت یکی از محصولات سبد تکمیل شده است.',
                    409,
                    ['code' => 'PRODUCT_CAPACITY_REACHED']
                );
            }
            if ($reservationError === 'PRODUCT_PURCHASE_LIMIT_REACHED') {
                dent_error(
                    'سقف خرید یکی از محصولات سبد برای حساب شما تکمیل شده است.',
                    409,
                    ['code' => 'PRODUCT_PURCHASE_LIMIT_REACHED']
                );
            }
        }

        $discount = dent_bot_payment_cart_discount($payload, $subtotal, $orders);
        if (is_array($existing)) {
            if ((string) ($existing['status'] ?? '') === PAYMENTS_ORDER_STATUS_SUCCESS) {
                return ['existing' => $existing];
            }
            return ['retry' => $existing];
        }

        $amount = $subtotal - (int) $discount['amount'];
        $expiresCandidates = array_values(array_filter(array_map(
            static fn(array $item): string => (string) ($item['expiresAt'] ?? ''),
            $items
        )));
        sort($expiresCandidates, SORT_STRING);
        $expiresAt = (string) ($expiresCandidates[0] ?? '');
        $now = dent_iso_now();
        $order = [
            'id' => payments_next_order_id($store),
            'item_id' => 0,
            'user_id' => $payerKey,
            'payer_name' => $payerName,
            'payer_phone' => $payerPhone,
            'payer_student_number' => $studentNumber,
            'extra_form_data' => [
                'source' => 'bot-cart',
                'bot_request_ref' => $requestRef,
                'bot_origin_platform' => $platform,
                'bot_origin_identity_hash' => dent_bot_identity_hash($platform, $platformUserId),
                'bot_origin_route_encrypted_json' => json_encode(
                    dent_encrypt_secret_text($platformUserId),
                    JSON_UNESCAPED_SLASHES
                ) ?: '',
                'bot_cart_items_json' => json_encode(
                    $items,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) ?: '[]',
                'bot_cart_discount_json' => json_encode(
                    $discount['snapshot'],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) ?: '{}',
            ],
            'cart_items' => dent_bot_payment_cart_order_lines($items, (int) $discount['amount']),
            'quantity' => count($items),
            'unit_price' => $subtotal,
            'subtotal' => $subtotal,
            'discount_code' => (string) $discount['code'],
            'discount_amount' => (int) $discount['amount'],
            'amount' => $amount,
            'gateway' => $gateway,
            'authority' => '',
            'ref_id' => '',
            'status' => PAYMENTS_ORDER_STATUS_PENDING,
            'gateway_response_snapshot' => [
                'created' => ['at' => $now, 'source' => 'bot-cart'],
            ],
            'created_at' => $now,
            'payment_started_at' => '',
            'paid_at' => '',
            'verified_at' => '',
            'updated_at' => $now,
            'expires_at' => $expiresAt,
            'public_token' => payments_random_token(),
        ];
        $store['orders'][] = $order;
        return ['order' => $order];
    });

    if (is_array($created['existing'] ?? null)) {
        $response = dent_bot_payment_existing_response($created['existing']);
        if (is_array($response)) {
            return $response;
        }
        dent_error(
            'درخواست پرداخت قبلی هنوز در حال ایجاد است.',
            409,
            ['code' => 'PAYMENT_REQUEST_IN_PROGRESS']
        );
    }

    $gatewayRefreshed = is_array($created['retry'] ?? null);
    $order = $gatewayRefreshed
        ? $created['retry']
        : (is_array($created['order'] ?? null) ? $created['order'] : []);
    $checkoutItems = dent_bot_payment_cart_items($order);
    if ($checkoutItems === []) {
        dent_error('جزئیات سبد پرداخت معتبر نیست.', 409, ['code' => 'BOT_CART_SNAPSHOT_MISSING']);
    }

    $gatewayAmount = max(0, (int) ($order['amount'] ?? 0));
    $gatewayPhone = payments_normalize_phone(
        (string) (($order['payer_phone'] ?? '') ?: $payerPhone)
    );
    $title = count($checkoutItems) === 1
        ? (string) $checkoutItems[0]['title']
        : 'سبد خرید ' . count($checkoutItems) . ' محصولی';
    $description = count($checkoutItems) === 1
        ? (string) ($checkoutItems[0]['description'] ?? '')
        : implode(
            '، ',
            array_slice(
                array_map(
                    static fn(array $item): string => (string) ($item['title'] ?? ''),
                    $checkoutItems
                ),
                0,
                4
            )
        );
    $syntheticItem = [
        'id' => 0,
        'title' => $title,
        'description' => $description,
        'price' => $gatewayAmount,
        'amount' => $gatewayAmount,
    ];
    $orderToken = (string) ($order['public_token'] ?? '');
    $callbackUrl = dent_bot_site_origin()
        . '/api/payments_api.php?action=callback&orderToken='
        . rawurlencode($orderToken);

    $startResult = payments_gateway_start_payment(
        (string) ($order['gateway'] ?? ''),
        $syntheticItem,
        $order,
        [
            'callbackUrl' => $callbackUrl,
            'description' => 'پرداخت ' . $title,
            'mobile' => $gatewayPhone,
            'orderId' => $orderToken,
        ]
    );

    if (!(bool) ($startResult['success'] ?? false)) {
        payments_with_store_lock(
            static function (array &$store) use ($order, $startResult, $gatewayRefreshed): void {
                $index = payments_find_order_index_by_id($store, (int) ($order['id'] ?? 0));
                if ($index < 0) {
                    return;
                }
                if (!$gatewayRefreshed) {
                    $store['orders'][$index]['status'] = PAYMENTS_ORDER_STATUS_FAILED;
                    $store['orders'][$index]['gateway_response_snapshot']['start'] = $startResult;
                } else {
                    $store['orders'][$index]['gateway_response_snapshot']['retryFailure'] = [
                        'at' => dent_iso_now(),
                        'result' => $startResult,
                    ];
                }
                $store['orders'][$index]['updated_at'] = dent_iso_now();
            }
        );
        dent_error(
            'ساخت درخواست درگاه انجام نشد.',
            503,
            ['code' => 'PAYMENT_START_FAILED']
        );
    }

    $redirectUrl = dent_bot_payment_public_redirect(
        $startResult,
        (string) ($order['gateway'] ?? '')
    );
    payments_with_store_lock(
        static function (array &$store) use ($order, $startResult, $gatewayRefreshed): void {
            $index = payments_find_order_index_by_id($store, (int) ($order['id'] ?? 0));
            if ($index < 0) {
                return;
            }
            $current = $store['orders'][$index];
            $snapshot = is_array($current['gateway_response_snapshot'] ?? null)
                ? $current['gateway_response_snapshot']
                : [];
            if ($gatewayRefreshed) {
                $snapshot = dent_bot_payment_archive_gateway_start($current, $snapshot);
            }
            $snapshot['start'] = $startResult;
            unset($snapshot['retryFailure']);
            $current['gateway_response_snapshot'] = $snapshot;
            $current['authority'] = dent_clean_text(
                (string) ($startResult['authority'] ?? ''),
                120
            );
            $current['ref_id'] = '';
            $current['status'] = PAYMENTS_ORDER_STATUS_PENDING;
            $current['payment_started_at'] = dent_iso_now();
            $current['updated_at'] = dent_iso_now();
            $store['orders'][$index] = $current;
        }
    );

    return [
        'success' => true,
        'alreadyCreated' => $gatewayRefreshed,
        'gatewayRefreshed' => $gatewayRefreshed,
        'orderToken' => $orderToken,
        'subtotalRials' => max(0, (int) ($order['subtotal'] ?? 0)),
        'discountCode' => (string) ($order['discount_code'] ?? ''),
        'discountAmountRials' => max(0, (int) ($order['discount_amount'] ?? 0)),
        'amountRials' => $gatewayAmount,
        'cartItems' => dent_bot_payment_cart_items($order),
        'redirectUrl' => $redirectUrl,
        'resultUrl' => dent_bot_payment_result_url($orderToken),
        'status' => PAYMENTS_ORDER_STATUS_PENDING,
    ];
}
