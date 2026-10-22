<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function hamiad_session_path(): string
{
    return dent_storage_path('hamiad/owner-session.json');
}

function hamiad_crypt_key(): string
{
    return dent_load_or_create_base64_secret_file(dent_storage_path('hamiad/.secret.key'));
}

function hamiad_seal($value): array
{
    $body = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($body)) {
        throw new RuntimeException('خطای آماده‌سازی نشست همیاد.');
    }
    $iv = random_bytes(12);
    $tag = '';
    $encrypted = openssl_encrypt($body, 'aes-256-gcm', hamiad_crypt_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if (!is_string($encrypted) || strlen($tag) !== 16) {
        throw new RuntimeException('رمزگذاری نشست همیاد ناموفق بود.');
    }
    return ['v' => 1, 'alg' => 'aes-256-gcm', 'iv' => base64_encode($iv), 'tag' => base64_encode($tag), 'ct' => base64_encode($encrypted)];
}

function hamiad_unseal($data): ?array
{
    if (!is_array($data) || ($data['alg'] ?? '') !== 'aes-256-gcm') {
        return null;
    }
    $iv = base64_decode((string) ($data['iv'] ?? ''), true);
    $tag = base64_decode((string) ($data['tag'] ?? ''), true);
    $cipher = base64_decode((string) ($data['ct'] ?? ''), true);
    if (!is_string($iv) || strlen($iv) !== 12 || !is_string($tag) || strlen($tag) !== 16 || !is_string($cipher)) {
        return null;
    }
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', hamiad_crypt_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if (!is_string($plain)) {
        return null;
    }
    $decoded = json_decode($plain, true);
    return is_array($decoded) ? $decoded : null;
}

function hamiad_session_status(): array
{
    $saved = dent_read_json_file(hamiad_session_path(), []);
    $cookies = hamiad_unseal($saved['cookies'] ?? null);
    return [
        'connected' => is_array($cookies) && count($cookies) > 0,
        'connectedAt' => (string) ($saved['connectedAt'] ?? ''),
        'usernameMasked' => (string) ($saved['usernameMasked'] ?? ''),
    ];
}

function hamiad_load_cookies(): array
{
    $saved = dent_read_json_file(hamiad_session_path(), []);
    $cookies = hamiad_unseal($saved['cookies'] ?? null);
    return is_array($cookies) ? $cookies : [];
}
