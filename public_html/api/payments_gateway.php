<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!defined('PAYMENTS_GATEWAY_ZARINPAL')) {
    define('PAYMENTS_GATEWAY_ZARINPAL', 'zarinpal');
}

if (!defined('PAYMENTS_GATEWAY_MOCK')) {
    define('PAYMENTS_GATEWAY_MOCK', 'mock');
}

function payments_gateway_default(): string
{
    $configured = payments_gateway_clean((string) getenv('DENT_PAYMENT_GATEWAY'));
    if ($configured !== '') {
        return $configured;
    }

    return PAYMENTS_GATEWAY_MOCK;
}

function payments_gateway_clean(string $value): string
{
    $value = trim(strtolower($value));
    if ($value === PAYMENTS_GATEWAY_ZARINPAL) {
        return PAYMENTS_GATEWAY_ZARINPAL;
    }

    if ($value === PAYMENTS_GATEWAY_MOCK) {
        return PAYMENTS_GATEWAY_MOCK;
    }

    return '';
}

function payments_gateway_http_post_json(string $url, array $payload, int $timeoutSeconds = 20): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return [
            'ok' => false,
            'statusCode' => 0,
            'raw' => null,
            'json' => null,
            'error' => 'json-encode-failed',
        ];
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($body),
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'ok' => false,
                'statusCode' => 0,
                'raw' => null,
                'json' => null,
                'error' => 'curl-init-failed',
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => max(3, $timeoutSeconds - 3),
            CURLOPT_TIMEOUT => max(3, $timeoutSeconds),
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return [
                'ok' => false,
                'statusCode' => $statusCode,
                'raw' => null,
                'json' => null,
                'error' => $curlError !== '' ? $curlError : 'curl-request-failed',
            ];
        }

        $decoded = json_decode((string) $raw, true);
        return [
            'ok' => $statusCode >= 200 && $statusCode < 300,
            'statusCode' => $statusCode,
            'raw' => $raw,
            'json' => is_array($decoded) ? $decoded : null,
            'error' => is_array($decoded) ? '' : 'invalid-json-response',
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => max(3, $timeoutSeconds),
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    $statusCode = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('/\s(\d{3})\s/u', (string) $headerLine, $matches) === 1) {
                $statusCode = (int) $matches[1];
                break;
            }
        }
    }

    if ($raw === false) {
        return [
            'ok' => false,
            'statusCode' => $statusCode,
            'raw' => null,
            'json' => null,
            'error' => 'http-request-failed',
        ];
    }

    $decoded = json_decode((string) $raw, true);
    return [
        'ok' => $statusCode >= 200 && $statusCode < 300,
        'statusCode' => $statusCode,
        'raw' => $raw,
        'json' => is_array($decoded) ? $decoded : null,
        'error' => is_array($decoded) ? '' : 'invalid-json-response',
    ];
}

function payments_gateway_start_payment(string $gateway, array $item, array $order, array $context = []): array
{
    $gateway = payments_gateway_clean($gateway);
    if ($gateway === '') {
        return [
            'success' => false,
            'gateway' => '',
            'error' => 'درگاه پرداخت انتخاب‌شده معتبر نیست.',
            'raw' => null,
        ];
    }

    if ($gateway === PAYMENTS_GATEWAY_ZARINPAL) {
        return payments_zarinpal_start_payment($item, $order, $context);
    }

    return payments_mock_start_payment($item, $order, $context);
}

function payments_gateway_verify_payment(string $gateway, array $order, array $context = []): array
{
    $gateway = payments_gateway_clean($gateway);
    if ($gateway === '') {
        return [
            'success' => false,
            'gateway' => '',
            'verified' => false,
            'status' => 'failed',
            'refId' => '',
            'error' => 'درگاه پرداخت سفارش معتبر نیست.',
            'raw' => null,
        ];
    }

    if ($gateway === PAYMENTS_GATEWAY_ZARINPAL) {
        return payments_zarinpal_verify_payment($order, $context);
    }

    return payments_mock_verify_payment($order, $context);
}

function payments_zarinpal_start_payment(array $item, array $order, array $context = []): array
{
    $merchantId = trim((string) getenv('DENT_PAYMENT_ZARINPAL_MERCHANT_ID'));
    if ($merchantId === '') {
        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_ZARINPAL,
            'error' => 'تنظیمات زرین‌پال کامل نیست (merchant_id).',
            'raw' => null,
        ];
    }

    $requestUrl = trim((string) getenv('DENT_PAYMENT_ZARINPAL_REQUEST_URL'));
    if ($requestUrl === '') {
        $requestUrl = 'https://payment.zarinpal.com/pg/v4/payment/request.json';
    }

    $description = dent_clean_text(
        (string) ($context['description'] ?? ('پرداخت بابت ' . (string) ($item['title'] ?? 'آیتم پرداخت'))),
        200
    );
    if ($description === '') {
        $description = 'پرداخت آنلاین';
    }

    $amount = max(0, (int) ($order['amount'] ?? 0));
    if ($amount <= 0) {
        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_ZARINPAL,
            'error' => 'مبلغ سفارش معتبر نیست.',
            'raw' => null,
        ];
    }

    $payload = [
        'merchant_id' => $merchantId,
        'amount' => $amount,
        'callback_url' => (string) ($context['callbackUrl'] ?? ''),
        'description' => $description,
    ];

    $metadata = [];
    $mobile = trim((string) ($context['mobile'] ?? ''));
    if ($mobile !== '') {
        $metadata['mobile'] = $mobile;
    }
    $email = trim((string) ($context['email'] ?? ''));
    if ($email !== '') {
        $metadata['email'] = $email;
    }
    if ($metadata !== []) {
        $payload['metadata'] = $metadata;
    }

    $httpResponse = payments_gateway_http_post_json($requestUrl, $payload, 22);
    $responsePayload = is_array($httpResponse['json'] ?? null) ? $httpResponse['json'] : [];
    $data = is_array($responsePayload['data'] ?? null) ? $responsePayload['data'] : [];
    $errors = is_array($responsePayload['errors'] ?? null) ? $responsePayload['errors'] : [];
    $authority = trim((string) ($data['authority'] ?? ''));
    $code = (int) ($data['code'] ?? ($errors['code'] ?? 0));

    if ($authority === '' || !in_array($code, [100], true)) {
        $errorMessage = trim((string) ($errors['message'] ?? ''));
        if ($errorMessage === '') {
            $errorMessage = 'درخواست پرداخت زرین‌پال پذیرفته نشد.';
        }

        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_ZARINPAL,
            'error' => $errorMessage,
            'raw' => [
                'request' => $payload,
                'response' => $httpResponse,
            ],
        ];
    }

    $startUrl = trim((string) getenv('DENT_PAYMENT_ZARINPAL_START_URL'));
    if ($startUrl === '') {
        $startUrl = 'https://www.zarinpal.com/pg/StartPay/';
    }
    $startUrl = rtrim($startUrl, '/') . '/';

    return [
        'success' => true,
        'gateway' => PAYMENTS_GATEWAY_ZARINPAL,
        'authority' => $authority,
        'redirectUrl' => $startUrl . rawurlencode($authority),
        'raw' => [
            'request' => $payload,
            'response' => $httpResponse,
        ],
    ];
}

function payments_zarinpal_verify_payment(array $order, array $context = []): array
{
    $merchantId = trim((string) getenv('DENT_PAYMENT_ZARINPAL_MERCHANT_ID'));
    if ($merchantId === '') {
        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_ZARINPAL,
            'verified' => false,
            'status' => 'failed',
            'refId' => '',
            'error' => 'تنظیمات زرین‌پال کامل نیست (merchant_id).',
            'raw' => null,
        ];
    }

    $authority = trim((string) ($context['authority'] ?? ($order['authority'] ?? '')));
    if ($authority === '') {
        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_ZARINPAL,
            'verified' => false,
            'status' => 'failed',
            'refId' => '',
            'error' => 'authority سفارش برای verify معتبر نیست.',
            'raw' => null,
        ];
    }

    $verifyUrl = trim((string) getenv('DENT_PAYMENT_ZARINPAL_VERIFY_URL'));
    if ($verifyUrl === '') {
        $verifyUrl = 'https://payment.zarinpal.com/pg/v4/payment/verify.json';
    }

    $amount = max(0, (int) ($order['amount'] ?? 0));
    $payload = [
        'merchant_id' => $merchantId,
        'amount' => $amount,
        'authority' => $authority,
    ];

    $httpResponse = payments_gateway_http_post_json($verifyUrl, $payload, 22);
    $responsePayload = is_array($httpResponse['json'] ?? null) ? $httpResponse['json'] : [];
    $data = is_array($responsePayload['data'] ?? null) ? $responsePayload['data'] : [];
    $errors = is_array($responsePayload['errors'] ?? null) ? $responsePayload['errors'] : [];
    $code = (int) ($data['code'] ?? ($errors['code'] ?? 0));
    $refId = trim((string) ($data['ref_id'] ?? ''));

    if (in_array($code, [100, 101], true) && $refId !== '') {
        return [
            'success' => true,
            'gateway' => PAYMENTS_GATEWAY_ZARINPAL,
            'verified' => true,
            'status' => 'success',
            'refId' => $refId,
            'error' => '',
            'raw' => [
                'request' => $payload,
                'response' => $httpResponse,
            ],
        ];
    }

    $errorMessage = trim((string) ($errors['message'] ?? ''));
    if ($errorMessage === '') {
        $errorMessage = 'verify زرین‌پال موفق نبود.';
    }

    return [
        'success' => false,
        'gateway' => PAYMENTS_GATEWAY_ZARINPAL,
        'verified' => false,
        'status' => 'failed',
        'refId' => $refId,
        'error' => $errorMessage,
        'raw' => [
            'request' => $payload,
            'response' => $httpResponse,
        ],
    ];
}

function payments_mock_start_payment(array $item, array $order, array $context = []): array
{
    $token = trim((string) ($order['public_token'] ?? ''));
    if ($token === '') {
        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_MOCK,
            'error' => 'شناسه سفارش برای درگاه آزمایشی موجود نیست.',
            'raw' => null,
        ];
    }

    $authority = 'MOCK-' . strtoupper(substr(sha1($token . '|' . dent_iso_now()), 0, 16));
    $redirectUrl = '/api/payments_api.php?action=mockGateway'
        . '&orderToken=' . rawurlencode($token)
        . '&authority=' . rawurlencode($authority)
        . '&result=success';

    return [
        'success' => true,
        'gateway' => PAYMENTS_GATEWAY_MOCK,
        'authority' => $authority,
        'redirectUrl' => $redirectUrl,
        'raw' => [
            'request' => [
                'orderToken' => $token,
                'itemId' => (int) ($item['id'] ?? 0),
            ],
            'response' => [
                'result' => 'mock',
                'authority' => $authority,
            ],
        ],
    ];
}

function payments_mock_verify_payment(array $order, array $context = []): array
{
    $status = strtolower(trim((string) ($context['status'] ?? 'ok')));
    $authority = trim((string) ($context['authority'] ?? ($order['authority'] ?? '')));

    if (in_array($status, ['ok', 'success', 'approved'], true)) {
        $refId = 'MOCK-REF-' . strtoupper(substr(sha1((string) ($order['public_token'] ?? '') . '|' . dent_iso_now()), 0, 12));
        return [
            'success' => true,
            'gateway' => PAYMENTS_GATEWAY_MOCK,
            'verified' => true,
            'status' => 'success',
            'refId' => $refId,
            'error' => '',
            'raw' => [
                'authority' => $authority,
                'status' => $status,
            ],
        ];
    }

    if (in_array($status, ['cancel', 'canceled', 'cancelled', 'nok'], true)) {
        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_MOCK,
            'verified' => false,
            'status' => 'canceled',
            'refId' => '',
            'error' => 'پرداخت توسط کاربر لغو شد.',
            'raw' => [
                'authority' => $authority,
                'status' => $status,
            ],
        ];
    }

    return [
        'success' => false,
        'gateway' => PAYMENTS_GATEWAY_MOCK,
        'verified' => false,
        'status' => 'failed',
        'refId' => '',
        'error' => 'پرداخت در درگاه آزمایشی ناموفق بود.',
        'raw' => [
            'authority' => $authority,
            'status' => $status,
        ],
    ];
}
