<?php
declare(strict_types=1);

require_once __DIR__ . '/grades_store.php';
require_once __DIR__ . '/bot_payments.php';
require_once __DIR__ . '/bot_notifications.php';
require_once __DIR__ . '/bot_navid.php';
require_once __DIR__ . '/bot_student_assistant.php';

function dent_bot_store_path(): string
{
    return dent_storage_path('integrations/bot_links.json');
}

function dent_bot_store_default(): array
{
    return [
        'schemaVersion' => 2,
        'links' => [],
        'challenges' => [],
        'identityCandidates' => [],
        'identityClaims' => [],
        'nonces' => [],
        'audit' => [],
        'notificationDeliveries' => [],
        'notificationDispatchSince' => '',
    ];
}

function dent_bot_store_with_lock(callable $callback): array
{
    $path = dent_bot_store_path();
    dent_ensure_directory(dirname($path));
    $handle = fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        dent_error('سرویس اتصال حساب موقتاً در دسترس نیست.', 503);
    }

    try {
        rewind($handle);
        $raw = stream_get_contents($handle);
        $store = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;
        if (!is_array($store)) {
            $store = dent_bot_store_default();
        }
        $store = array_merge(dent_bot_store_default(), $store);
        foreach (['links', 'challenges', 'identityCandidates', 'identityClaims', 'nonces', 'audit', 'notificationDeliveries'] as $key) {
            if (!is_array($store[$key] ?? null)) {
                $store[$key] = [];
            }
        }

        $result = $callback($store);
        $json = json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            dent_error('ذخیره اتصال حساب انجام نشد.', 500);
        }
        rewind($handle);
        ftruncate($handle, 0);
        if (fwrite($handle, $json . PHP_EOL) === false) {
            dent_error('ذخیره اتصال حساب انجام نشد.', 500);
        }
        fflush($handle);
        return is_array($result) ? $result : [];
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function dent_bot_store_read(callable $callback): array
{
    $path = dent_bot_store_path();
    if (!is_file($path)) {
        $store = dent_bot_store_default();
        $result = $callback($store);
        return is_array($result) ? $result : [];
    }
    $handle = fopen($path, 'rb');
    if ($handle === false || !flock($handle, LOCK_SH)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        dent_error('سرویس اتصال حساب موقتاً در دسترس نیست.', 503);
    }
    try {
        $raw = stream_get_contents($handle);
        $store = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;
        if (!is_array($store)) {
            $store = dent_bot_store_default();
        }
        $store = array_merge(dent_bot_store_default(), $store);
        $result = $callback($store);
        return is_array($result) ? $result : [];
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function dent_bot_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function dent_bot_service_secret(): string
{
    $raw = trim((string) (getenv('DENT_BOT_SERVICE_SECRET') ?: ''));
    $decoded = null;
    if (preg_match('/^[a-f0-9]{64,}$/i', $raw) === 1) {
        $decoded = hex2bin(substr($raw, 0, 64));
    } elseif (preg_match('/^[A-Za-z0-9_-]{43,}$/', $raw) === 1) {
        $padding = str_repeat('=', (4 - strlen($raw) % 4) % 4);
        $decoded = base64_decode(strtr($raw . $padding, '-_', '+/'), true);
    }
    if (!is_string($decoded) || strlen($decoded) < 32) {
        dent_error('سرویس ربات پیکربندی نشده است.', 503);
    }
    return substr($decoded, 0, 32);
}

function dent_bot_identity(string $platform, string $platformUserId): array
{
    $platform = strtolower(trim($platform));
    $platformUserId = trim($platformUserId);
    if (!in_array($platform, ['telegram', 'bale'], true) || preg_match('/^[0-9]{1,24}$/', $platformUserId) !== 1) {
        dent_error('هویت ربات نامعتبر است.', 422);
    }
    return [$platform, $platformUserId];
}

function dent_bot_identity_hash(string $platform, string $platformUserId): string
{
    [$platform, $platformUserId] = dent_bot_identity($platform, $platformUserId);
    return hash_hmac('sha256', $platform . ':' . $platformUserId, dent_auth_secret_key());
}

function dent_bot_token_hash(string $token): string
{
    return hash_hmac('sha256', $token, dent_auth_secret_key());
}

function dent_bot_cleanup_store(array &$store, int $now): void
{
    foreach ($store['challenges'] as $key => $challenge) {
        if (!is_array($challenge) || (int) ($challenge['expiresAt'] ?? 0) < $now - 3600) {
            unset($store['challenges'][$key]);
        }
    }
    foreach ($store['nonces'] as $key => $expiresAt) {
        if ((int) $expiresAt < $now) {
            unset($store['nonces'][$key]);
        }
    }
    if (count($store['audit']) > 500) {
        $store['audit'] = array_slice($store['audit'], -500);
    }

    $retentionDays = max(7, min(365, (int) (getenv('DENT_BOT_NOTIFICATION_DELIVERY_RETENTION_DAYS') ?: 90)));
    $terminalBefore = $now - ($retentionDays * 86400);
    foreach ($store['notificationDeliveries'] as $key => $delivery) {
        if (!is_array($delivery)) {
            unset($store['notificationDeliveries'][$key]);
            continue;
        }
        $status = (string) ($delivery['status'] ?? '');
        $referenceAt = strtotime((string) (($delivery['deliveredAt'] ?? '') ?: ($delivery['lastAttemptAt'] ?? '')));
        if (in_array($status, ['delivered', 'failed'], true) && $referenceAt !== false && $referenceAt < $terminalBefore) {
            unset($store['notificationDeliveries'][$key]);
        }
    }
}

function dent_bot_audit(array &$store, string $event, string $identityHash, string $studentNumber = '', string $reason = ''): void
{
    $record = [
        'event' => dent_clean_text($event, 80),
        'identityRef' => substr($identityHash, 0, 16),
        'studentRef' => $studentNumber !== '' ? substr(hash('sha256', $studentNumber), 0, 16) : '',
        'createdAt' => dent_iso_now(),
    ];
    if ($reason !== '') {
        $record['reason'] = dent_clean_text($reason, 240);
    }
    $store['audit'][] = $record;
}

function dent_bot_verify_service_signature(string $body, string $timestamp, string $nonce, string $signature): void
{
    if (!ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 90) {
        dent_error('درخواست سرویس نامعتبر است.', 401);
    }
    if (preg_match('/^[A-Za-z0-9_-]{20,120}$/', $nonce) !== 1 || preg_match('/^[a-f0-9]{64}$/i', $signature) !== 1) {
        dent_error('درخواست سرویس نامعتبر است.', 401);
    }
    $expected = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . hash('sha256', $body), dent_bot_service_secret());
    if (!hash_equals($expected, strtolower($signature))) {
        dent_error('درخواست سرویس نامعتبر است.', 401);
    }

    dent_bot_store_with_lock(static function (array &$store) use ($nonce): array {
        $now = time();
        dent_bot_cleanup_store($store, $now);
        $nonceKey = hash_hmac('sha256', $nonce, dent_auth_secret_key());
        if (isset($store['nonces'][$nonceKey])) {
            dent_error('درخواست سرویس تکراری است.', 409);
        }
        $store['nonces'][$nonceKey] = $now + 180;
        return [];
    });
}

function dent_bot_service_request(): array
{
    if (!in_array(dent_request_method(), ['POST', 'PUT'], true)) {
        dent_error('متد سرویس نامعتبر است.', 405);
    }
    $body = file_get_contents('php://input');
    if (!is_string($body) || $body === '' || strlen($body) > 32768) {
        dent_error('بدنه درخواست سرویس نامعتبر است.', 400);
    }
    dent_bot_verify_service_signature(
        $body,
        trim((string) ($_SERVER['HTTP_X_DENT_TIMESTAMP'] ?? '')),
        trim((string) ($_SERVER['HTTP_X_DENT_NONCE'] ?? '')),
        trim((string) ($_SERVER['HTTP_X_DENT_SIGNATURE'] ?? ''))
    );
    $payload = json_decode($body, true);
    if (!is_array($payload)) {
        dent_error('بدنه درخواست سرویس نامعتبر است.', 400);
    }
    return $payload;
}

function dent_bot_public_user(array $user): array
{
    $public = dent_public_user($user);
    return [
        'name' => (string) ($public['name'] ?? ''),
        'studentNumber' => (string) ($public['studentNumber'] ?? ''),
        'role' => (string) ($public['role'] ?? 'student'),
        'roleLabel' => (string) ($public['roleLabel'] ?? ''),
        'cohortKey' => (string) ($public['cohortKey'] ?? ''),
        'isOwner' => !empty($public['isOwner']),
    ];
}

function dent_bot_normalize_identity_name(string $value): string
{
    $value = strtr($value, ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک']);
    $value = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}]/u', ' ', $value) ?? $value;
    $value = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

function dent_bot_telegram_profile(array $payload): array
{
    $profile = is_array($payload['telegramProfile'] ?? null) ? $payload['telegramProfile'] : [];
    $username = ltrim(dent_clean_text((string) ($profile['username'] ?? ''), 32), '@');
    if ($username !== '' && preg_match('/^[A-Za-z0-9_]{5,32}$/', $username) !== 1) {
        $username = '';
    }
    return [
        'displayName' => dent_clean_text((string) ($profile['displayName'] ?? ''), 128),
        'username' => $username,
        'languageCode' => preg_match('/^[A-Za-z-]{2,16}$/', (string) ($profile['languageCode'] ?? '')) === 1
            ? (string) $profile['languageCode']
            : '',
        'isPremium' => !empty($profile['isPremium']),
    ];
}

function dent_bot_conflicting_link(array $store, string $identityHash, string $platform, string $studentNumber): ?array
{
    foreach ($store['links'] as $key => $link) {
        if (!is_array($link)) {
            continue;
        }
        if ((string) $key === $identityHash && (string) ($link['studentNumber'] ?? '') !== $studentNumber) {
            return ['type' => 'identity', 'key' => (string) $key, 'link' => $link];
        }
        if ((string) $key !== $identityHash
            && (string) ($link['platform'] ?? '') === $platform
            && (string) ($link['studentNumber'] ?? '') === $studentNumber) {
            return ['type' => 'student', 'key' => (string) $key, 'link' => $link];
        }
    }
    return null;
}

function dent_bot_public_identity_state(string $platform, string $platformUserId): array
{
    $identityHash = dent_bot_identity_hash($platform, $platformUserId);
    return dent_bot_store_read(static function (array $store) use ($identityHash): array {
        $candidate = $store['identityCandidates'][$identityHash] ?? null;
        $claim = $store['identityClaims'][$identityHash] ?? null;
        return [
            'recognized' => is_array($candidate),
            'candidateStatus' => is_array($candidate) ? (string) ($candidate['status'] ?? 'recognized') : '',
            'claimStatus' => is_array($claim) ? (string) ($claim['status'] ?? '') : '',
        ];
    });
}

function dent_bot_require_owner(array $user): void
{
    if (empty(dent_public_user($user)['isOwner'])) {
        dent_error('این عملیات فقط برای مالک در دسترس است.', 403);
    }
}

function dent_bot_import_identity_candidates(array $owner, string $platform, array $payload): array
{
    dent_bot_require_owner($owner);
    $items = $payload['candidates'] ?? null;
    if (!is_array($items) || count($items) < 1 || count($items) > 200) {
        dent_error('فهرست تطبیق هویت نامعتبر است.', 422);
    }
    $accepted = 0;
    $linked = 0;
    $waitingAccount = 0;
    $skipped = 0;
    dent_bot_store_with_lock(static function (array &$store) use ($items, $platform, &$accepted, &$linked, &$waitingAccount, &$skipped): array {
        foreach ($items as $item) {
            if (!is_array($item)) {
                $skipped++;
                continue;
            }
            $cleanPlatform = strtolower(trim($platform));
            $platformUserId = trim((string) ($item['platformUserId'] ?? ''));
            if (!in_array($cleanPlatform, ['telegram', 'bale'], true)
                || preg_match('/^[0-9]{1,24}$/', $platformUserId) !== 1) {
                $skipped++;
                continue;
            }
            $studentNumber = dent_normalize_student_number((string) ($item['studentNumber'] ?? ''));
            $matchMethod = (string) ($item['matchMethod'] ?? '');
            if ($studentNumber === '' || !in_array($matchMethod, ['verified_phone', 'exact_unique_name'], true)) {
                $skipped++;
                continue;
            }
            $identityHash = dent_bot_identity_hash($cleanPlatform, $platformUserId);
            $user = dent_get_user_record($studentNumber);
            $approved = !empty($item['ownerApproved']);
            $status = is_array($user) ? ($approved ? 'approved' : 'recognized') : 'waiting_account';
            $store['identityCandidates'][$identityHash] = [
                'identityHash' => $identityHash,
                'platform' => $cleanPlatform,
                'platformUserIdEncrypted' => dent_encrypt_secret_text($platformUserId),
                'studentNumber' => $studentNumber,
                'matchMethod' => $matchMethod,
                'status' => $status,
                'source' => 'owner-class-group-import',
                'updatedAt' => dent_iso_now(),
            ];
            $accepted++;
            if (!is_array($user)) {
                $waitingAccount++;
                continue;
            }
            if (!$approved) {
                continue;
            }
            $conflict = dent_bot_conflicting_link($store, $identityHash, $cleanPlatform, $studentNumber);
            if (is_array($conflict)) {
                $skipped++;
                continue;
            }
            $store['links'][$identityHash] = [
                'identityHash' => $identityHash,
                'platform' => $cleanPlatform,
                'platformUserIdEncrypted' => dent_encrypt_secret_text($platformUserId),
                'studentNumber' => $studentNumber,
                'linkedAt' => dent_iso_now(),
                'source' => 'owner-class-group-import',
            ];
            dent_bot_audit($store, 'class-identity-linked', $identityHash, $studentNumber);
            $linked++;
        }
        return [];
    });
    return [
        'success' => true,
        'accepted' => $accepted,
        'linked' => $linked,
        'waitingAccount' => $waitingAccount,
        'skipped' => $skipped,
    ];
}

function dent_bot_submit_identity_claim(string $platform, string $platformUserId, array $payload): array
{
    [$platform, $platformUserId] = dent_bot_identity($platform, $platformUserId);
    $claimedName = dent_clean_text((string) ($payload['name'] ?? ''), 120);
    $normalizedName = dent_bot_normalize_identity_name($claimedName);
    $characterCount = preg_match_all('/./u', $normalizedName, $unusedMatches);
    if (!is_int($characterCount) || $characterCount < 5) {
        dent_error('نام و نام خانوادگی کامل را وارد کن.', 422);
    }
    $identityHash = dent_bot_identity_hash($platform, $platformUserId);
    if (is_array(dent_bot_link_for_identity($platform, $platformUserId))) {
        return ['success' => true, 'status' => 'already-linked'];
    }
    $telegramProfile = dent_bot_telegram_profile($payload);
    $candidate = dent_bot_store_read(static function (array $store) use ($identityHash): array {
        return ['candidate' => is_array($store['identityCandidates'][$identityHash] ?? null)
            ? $store['identityCandidates'][$identityHash]
            : null];
    })['candidate'] ?? null;
    $targetStudentNumber = is_array($candidate)
        ? dent_normalize_student_number((string) ($candidate['studentNumber'] ?? ''))
        : '';
    if ($targetStudentNumber === '') {
        $matches = [];
        foreach ((dent_load_user_store()['users'] ?? []) as $studentNumber => $user) {
            if (!is_array($user) || dent_user_cohort_key($user) !== dent_primary_cohort_key()) {
                continue;
            }
            if (dent_bot_normalize_identity_name((string) ($user['name'] ?? '')) === $normalizedName) {
                $matches[] = dent_normalize_student_number((string) $studentNumber);
            }
        }
        if (count($matches) === 1) {
            $targetStudentNumber = $matches[0];
        }
    }
    $claimRef = dent_bot_base64url_encode(random_bytes(9));
    dent_bot_store_with_lock(static function (array &$store) use ($identityHash, $platform, $platformUserId, $claimedName, $targetStudentNumber, $claimRef, $telegramProfile): array {
        $store['identityClaims'][$identityHash] = [
            'ref' => $claimRef,
            'identityHash' => $identityHash,
            'platform' => $platform,
            'platformUserIdEncrypted' => dent_encrypt_secret_text($platformUserId),
            'claimedNameEncrypted' => dent_encrypt_secret_text($claimedName),
            'platformProfileEncrypted' => dent_encrypt_secret_text((string) json_encode($telegramProfile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'studentNumber' => $targetStudentNumber,
            'status' => 'pending',
            'createdAt' => dent_iso_now(),
            'updatedAt' => dent_iso_now(),
        ];
        dent_bot_audit($store, 'identity-claim-created', $identityHash, $targetStudentNumber);
        return [];
    });
    return ['success' => true, 'status' => 'pending'];
}

function dent_bot_identity_claims(array $owner): array
{
    dent_bot_require_owner($owner);
    return dent_bot_store_read(static function (array $store): array {
        $items = [];
        foreach ($store['identityClaims'] as $claim) {
            if (!is_array($claim) || (string) ($claim['status'] ?? '') !== 'pending') {
                continue;
            }
            $studentNumber = dent_normalize_student_number((string) ($claim['studentNumber'] ?? ''));
            $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
            $profileRaw = dent_decrypt_secret_text($claim['platformProfileEncrypted'] ?? null);
            $profile = json_decode($profileRaw, true);
            if (!is_array($profile)) {
                $profile = [];
            }
            $items[] = [
                'ref' => (string) ($claim['ref'] ?? ''),
                'candidateFound' => is_array($user),
                'name' => is_array($user) ? (string) ($user['name'] ?? '') : 'نیازمند تطبیق دستی',
                'claimedName' => dent_decrypt_secret_text($claim['claimedNameEncrypted'] ?? null),
                'studentNumber' => is_array($user) ? $studentNumber : '',
                'platform' => (string) ($claim['platform'] ?? ''),
                'platformUserId' => dent_decrypt_secret_text($claim['platformUserIdEncrypted'] ?? null),
                'telegramProfile' => $profile,
                'createdAt' => (string) ($claim['createdAt'] ?? ''),
            ];
        }
        return ['success' => true, 'claims' => array_slice($items, 0, 50)];
    });
}

function dent_bot_resolve_identity_claim(array $owner, array $payload): array
{
    dent_bot_require_owner($owner);
    $claimRef = trim((string) ($payload['claimRef'] ?? ''));
    $decision = (string) ($payload['decision'] ?? '');
    if (preg_match('/^[A-Za-z0-9_-]{12}$/', $claimRef) !== 1 || !in_array($decision, ['approve', 'reject'], true)) {
        dent_error('درخواست بررسی هویت نامعتبر است.', 422);
    }
    return dent_bot_store_with_lock(static function (array &$store) use ($claimRef, $decision): array {
        $identityHash = '';
        $claim = null;
        foreach ($store['identityClaims'] as $key => $candidate) {
            if (is_array($candidate) && (string) ($candidate['ref'] ?? '') === $claimRef) {
                $identityHash = (string) $key;
                $claim = $candidate;
                break;
            }
        }
        if (!is_array($claim) || (string) ($claim['status'] ?? '') !== 'pending') {
            dent_error('درخواست هویت پیدا نشد یا قبلاً بررسی شده است.', 404);
        }
        $studentNumber = dent_normalize_student_number((string) ($claim['studentNumber'] ?? ''));
        $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
        if ($decision === 'approve' && !is_array($user)) {
            dent_error('برای این فرد هنوز حساب canonical سایت پیدا نشده است.', 409);
        }
        $store['identityClaims'][$identityHash]['status'] = $decision === 'approve' ? 'approved' : 'rejected';
        $store['identityClaims'][$identityHash]['updatedAt'] = dent_iso_now();
        if ($decision === 'approve') {
            $conflict = dent_bot_conflicting_link($store, $identityHash, (string) ($claim['platform'] ?? ''), $studentNumber);
            if (is_array($conflict)) {
                dent_error('این فرد یا حساب تلگرام قبلاً اتصال قطعی دیگری دارد؛ فقط مالک می‌تواند آن را از مدیریت اتصال‌ها تغییر دهد.', 409);
            }
            $store['links'][$identityHash] = [
                'identityHash' => $identityHash,
                'platform' => (string) ($claim['platform'] ?? ''),
                'platformUserIdEncrypted' => $claim['platformUserIdEncrypted'] ?? null,
                'studentNumber' => $studentNumber,
                'linkedAt' => dent_iso_now(),
                'source' => 'owner-approved-claim',
            ];
        }
        dent_bot_audit($store, $decision === 'approve' ? 'identity-claim-approved' : 'identity-claim-rejected', $identityHash, $studentNumber);
        return ['success' => true, 'status' => $store['identityClaims'][$identityHash]['status']];
    });
}

function dent_bot_identity_mappings(array $owner, string $platform): array
{
    dent_bot_require_owner($owner);
    $platform = strtolower(trim($platform));
    if (!in_array($platform, ['telegram', 'bale'], true)) {
        dent_error('پلتفرم اتصال نامعتبر است.', 422);
    }
    return dent_bot_store_read(static function (array $store) use ($platform): array {
        $items = [];
        foreach ($store['links'] as $identityHash => $link) {
            if (!is_array($link) || (string) ($link['platform'] ?? '') !== $platform) {
                continue;
            }
            $studentNumber = dent_normalize_student_number((string) ($link['studentNumber'] ?? ''));
            $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
            $items[] = [
                'ref' => substr((string) $identityHash, 0, 16),
                'name' => is_array($user) ? (string) ($user['name'] ?? '') : 'حساب سایت حذف‌شده',
                'studentNumber' => $studentNumber,
                'platform' => $platform,
                'platformUserId' => dent_decrypt_secret_text($link['platformUserIdEncrypted'] ?? null),
                'linkedAt' => (string) ($link['linkedAt'] ?? ''),
                'source' => (string) ($link['source'] ?? 'secure-site-link'),
            ];
        }
        usort($items, static fn(array $left, array $right): int => strcmp((string) ($right['linkedAt'] ?? ''), (string) ($left['linkedAt'] ?? '')));
        return ['success' => true, 'mappings' => array_slice($items, 0, 200)];
    });
}

function dent_bot_set_identity_mapping(array $owner, string $platform, array $payload): array
{
    dent_bot_require_owner($owner);
    [$platform, $targetPlatformUserId] = dent_bot_identity($platform, (string) ($payload['targetPlatformUserId'] ?? ''));
    $studentNumber = dent_normalize_student_number((string) ($payload['studentNumber'] ?? ''));
    $reason = dent_clean_text((string) ($payload['reason'] ?? ''), 240);
    $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
    $reasonLength = preg_match_all('/./u', $reason, $unusedReasonMatches);
    if (!is_array($user) || !is_int($reasonLength) || $reasonLength < 5) {
        dent_error('حساب سایت یا دلیل تغییر معتبر نیست.', 422);
    }
    $identityHash = dent_bot_identity_hash($platform, $targetPlatformUserId);
    return dent_bot_store_with_lock(static function (array &$store) use ($platform, $targetPlatformUserId, $studentNumber, $reason, $identityHash): array {
        foreach ($store['links'] as $key => $link) {
            if (!is_array($link)) {
                continue;
            }
            if ((string) $key === $identityHash
                || ((string) ($link['platform'] ?? '') === $platform && (string) ($link['studentNumber'] ?? '') === $studentNumber)) {
                unset($store['links'][$key]);
            }
        }
        $store['links'][$identityHash] = [
            'identityHash' => $identityHash,
            'platform' => $platform,
            'platformUserIdEncrypted' => dent_encrypt_secret_text($targetPlatformUserId),
            'studentNumber' => $studentNumber,
            'linkedAt' => dent_iso_now(),
            'source' => 'owner-manual',
        ];
        if (is_array($store['identityClaims'][$identityHash] ?? null)) {
            $store['identityClaims'][$identityHash]['status'] = 'owner-linked';
            $store['identityClaims'][$identityHash]['updatedAt'] = dent_iso_now();
        }
        dent_bot_audit($store, 'identity-mapping-owner-set', $identityHash, $studentNumber, $reason);
        return ['success' => true, 'status' => 'linked', 'mappingRef' => substr($identityHash, 0, 16)];
    });
}

function dent_bot_delete_identity_mapping(array $owner, string $platform, array $payload): array
{
    dent_bot_require_owner($owner);
    $mappingRef = trim((string) ($payload['mappingRef'] ?? ''));
    $reason = dent_clean_text((string) ($payload['reason'] ?? ''), 240);
    $reasonLength = preg_match_all('/./u', $reason, $unusedReasonMatches);
    if (preg_match('/^[a-f0-9]{16}$/', $mappingRef) !== 1 || !is_int($reasonLength) || $reasonLength < 5) {
        dent_error('درخواست حذف اتصال نامعتبر است.', 422);
    }
    return dent_bot_store_with_lock(static function (array &$store) use ($mappingRef, $reason, $platform): array {
        $matches = [];
        foreach ($store['links'] as $identityHash => $link) {
            if (is_array($link) && str_starts_with((string) $identityHash, $mappingRef)
                && (string) ($link['platform'] ?? '') === $platform) {
                $matches[(string) $identityHash] = $link;
            }
        }
        if (count($matches) !== 1) {
            dent_error('اتصال موردنظر پیدا نشد.', 404);
        }
        $identityHash = (string) array_key_first($matches);
        $link = $matches[$identityHash];
        $studentNumber = dent_normalize_student_number((string) ($link['studentNumber'] ?? ''));
        unset($store['links'][$identityHash]);
        dent_bot_audit($store, 'identity-mapping-owner-deleted', $identityHash, $studentNumber, $reason);
        return ['success' => true, 'status' => 'deleted'];
    });
}

function dent_bot_link_for_identity(string $platform, string $platformUserId): ?array
{
    $identityHash = dent_bot_identity_hash($platform, $platformUserId);
    $result = dent_bot_store_read(static function (array $store) use ($identityHash): array {
        $link = $store['links'][$identityHash] ?? null;
        return ['link' => is_array($link) ? $link : null];
    });
    return is_array($result['link'] ?? null) ? $result['link'] : null;
}

function dent_bot_linked_user(string $platform, string $platformUserId): ?array
{
    $link = dent_bot_link_for_identity($platform, $platformUserId);
    if (!is_array($link)) {
        return null;
    }
    $studentNumber = dent_normalize_student_number((string) ($link['studentNumber'] ?? ''));
    $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
    return is_array($user) ? $user : null;
}

function dent_bot_site_origin(): string
{
    $origin = rtrim(trim((string) (getenv('DENT_SITE_PUBLIC_URL') ?: 'https://dentistry1402tums.ir')), '/');
    if (filter_var($origin, FILTER_VALIDATE_URL) === false || parse_url($origin, PHP_URL_SCHEME) !== 'https') {
        dent_error('نشانی عمومی سایت نامعتبر است.', 500);
    }
    return $origin;
}

function dent_bot_start_link(string $platform, string $platformUserId): array
{
    [$platform, $platformUserId] = dent_bot_identity($platform, $platformUserId);
    $identityHash = dent_bot_identity_hash($platform, $platformUserId);
    $existing = dent_bot_linked_user($platform, $platformUserId);
    if (is_array($existing)) {
        return ['success' => true, 'alreadyLinked' => true, 'user' => dent_bot_public_user($existing)];
    }

    $token = dent_bot_base64url_encode(random_bytes(32));
    $tokenHash = dent_bot_token_hash($token);
    $expiresAt = time() + 600;
    dent_bot_store_with_lock(static function (array &$store) use ($tokenHash, $identityHash, $platform, $platformUserId, $expiresAt): array {
        dent_bot_cleanup_store($store, time());
        $store['challenges'][$tokenHash] = [
            'identityHash' => $identityHash,
            'platform' => $platform,
            'platformUserIdEncrypted' => dent_encrypt_secret_text($platformUserId),
            'createdAt' => time(),
            'expiresAt' => $expiresAt,
            'usedAt' => 0,
        ];
        dent_bot_audit($store, 'link-challenge-created', $identityHash);
        return [];
    });

    return [
        'success' => true,
        'alreadyLinked' => false,
        'linkUrl' => dent_bot_site_origin() . '/account/bot-link/?token=' . rawurlencode($token),
        'expiresAt' => gmdate('c', $expiresAt),
    ];
}

function dent_bot_link_challenge_info(string $token): array
{
    if (preg_match('/^[A-Za-z0-9_-]{43}$/', $token) !== 1) {
        dent_error('لینک اتصال نامعتبر یا منقضی است.', 404);
    }
    $tokenHash = dent_bot_token_hash($token);
    $result = dent_bot_store_with_lock(static function (array &$store) use ($tokenHash): array {
        dent_bot_cleanup_store($store, time());
        $challenge = $store['challenges'][$tokenHash] ?? null;
        if (!is_array($challenge) || (int) ($challenge['usedAt'] ?? 0) > 0 || (int) ($challenge['expiresAt'] ?? 0) < time()) {
            return ['challenge' => null];
        }
        return ['challenge' => $challenge];
    });
    $challenge = $result['challenge'] ?? null;
    if (!is_array($challenge)) {
        dent_error('لینک اتصال نامعتبر یا منقضی است.', 404);
    }
    return [
        'success' => true,
        'platform' => (string) ($challenge['platform'] ?? ''),
        'expiresAt' => gmdate('c', (int) ($challenge['expiresAt'] ?? 0)),
    ];
}

function dent_bot_confirm_link(string $token, array $user): array
{
    if (preg_match('/^[A-Za-z0-9_-]{43}$/', $token) !== 1) {
        dent_error('لینک اتصال نامعتبر یا منقضی است.', 404);
    }
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        dent_error('حساب سایت نامعتبر است.', 422);
    }
    $tokenHash = dent_bot_token_hash($token);
    return dent_bot_store_with_lock(static function (array &$store) use ($tokenHash, $studentNumber, $user): array {
        $now = time();
        dent_bot_cleanup_store($store, $now);
        $challenge = $store['challenges'][$tokenHash] ?? null;
        if (!is_array($challenge) || (int) ($challenge['usedAt'] ?? 0) > 0 || (int) ($challenge['expiresAt'] ?? 0) < $now) {
            dent_error('لینک اتصال نامعتبر یا منقضی است.', 404);
        }
        $identityHash = (string) ($challenge['identityHash'] ?? '');
        $existing = $store['links'][$identityHash] ?? null;
        if (is_array($existing) && (string) ($existing['studentNumber'] ?? '') !== $studentNumber) {
            dent_error('این حساب ربات قبلاً به حساب دیگری متصل شده است.', 409);
        }

        $platform = (string) ($challenge['platform'] ?? '');
        $conflict = dent_bot_conflicting_link($store, $identityHash, $platform, $studentNumber);
        if (is_array($conflict)) {
            dent_error('این فرد یا حساب ربات قبلاً اتصال قطعی دیگری دارد؛ تغییر فقط توسط مالک ممکن است.', 409);
        }
        $store['links'][$identityHash] = [
            'identityHash' => $identityHash,
            'platform' => $platform,
            'platformUserIdEncrypted' => $challenge['platformUserIdEncrypted'] ?? null,
            'studentNumber' => $studentNumber,
            'linkedAt' => dent_iso_now(),
            'source' => 'secure-site-link',
        ];
        $store['challenges'][$tokenHash]['usedAt'] = $now;
        if (is_array($store['identityClaims'][$identityHash] ?? null)) {
            $store['identityClaims'][$identityHash]['status'] = 'linked-secure-site';
            $store['identityClaims'][$identityHash]['updatedAt'] = dent_iso_now();
        }
        if (is_array($store['identityCandidates'][$identityHash] ?? null)) {
            $store['identityCandidates'][$identityHash]['status'] = 'linked-secure-site';
            $store['identityCandidates'][$identityHash]['updatedAt'] = dent_iso_now();
        }
        dent_bot_audit($store, 'account-linked', $identityHash, $studentNumber);
        return ['success' => true, 'platform' => $platform, 'user' => dent_bot_public_user($user)];
    });
}

function dent_bot_service_dispatch(array $payload): array
{
    $action = trim((string) ($payload['action'] ?? ''));
    $platform = (string) ($payload['platform'] ?? '');
    $platformUserId = (string) ($payload['platformUserId'] ?? '');

    if ($action === 'startLink') {
        return dent_bot_start_link($platform, $platformUserId);
    }
    if ($action === 'submitIdentityClaim') {
        return dent_bot_submit_identity_claim($platform, $platformUserId, $payload);
    }
    $user = dent_bot_linked_user($platform, $platformUserId);
    if ($action === 'account') {
        return [
            'success' => true,
            'linked' => is_array($user),
            'user' => is_array($user) ? dent_bot_public_user($user) : null,
            'identity' => is_array($user) ? ['recognized' => true, 'claimStatus' => 'approved'] : dent_bot_public_identity_state($platform, $platformUserId),
        ];
    }
    if (!is_array($user)) {
        dent_error('اتصال حساب لازم است.', 403, ['code' => 'ACCOUNT_LINK_REQUIRED']);
    }
    if ($action === 'grades') {
        dent_grades_set_active_cohort(dent_user_cohort_key($user));
        return dent_build_grades_payload($user);
    }
    if ($action === 'createBotPayment') {
        return dent_bot_create_offer_payment($user, $platform, $platformUserId, $payload);
    }
    if ($action === 'paymentStatus') {
        return dent_bot_payment_status($user, $payload);
    }
    if ($action === 'studentAssistantSummaryV1') {
        return dent_bot_student_assistant_summary_v1($user, $platform, $platformUserId, $payload);
    }
    if ($action === 'performIntegrationActionV1') {
        return dent_bot_perform_integration_action_v1($user, $platform, $platformUserId, $payload);
    }
    if ($action === 'integrationChallengeAnswerV1') {
        return dent_bot_integration_challenge_answer_v1($user, $platform, $platformUserId, $payload);
    }
    if ($action === 'notifications') {
        return dent_bot_notification_feed($user, $payload);
    }
    if ($action === 'createDeployNotification') {
        return dent_bot_create_deploy_notification($user, $payload);
    }
    if ($action === 'markNotificationRead') {
        return dent_bot_mark_notification_read($user, $payload);
    }
    if ($action === 'notificationAudience') {
        return dent_bot_notification_audience($user, $payload);
    }
    if ($action === 'claimNotificationDeliveries') {
        return dent_bot_claim_notification_deliveries($user, $platform, $payload);
    }
    if ($action === 'ackNotificationDelivery') {
        return dent_bot_ack_notification_delivery($user, $platform, $payload);
    }
    if ($action === 'navidDailyStart') {
        return dent_bot_navid_daily_start($user, $platform, $payload);
    }
    if ($action === 'navidStatus') {
        return dent_bot_navid_status($user);
    }
    if ($action === 'navidDailyComplete') {
        return dent_bot_navid_daily_complete($user, $platform, $payload);
    }
    if ($action === 'importIdentityCandidates') {
        return dent_bot_import_identity_candidates($user, $platform, $payload);
    }
    if ($action === 'identityClaims') {
        return dent_bot_identity_claims($user);
    }
    if ($action === 'resolveIdentityClaim') {
        return dent_bot_resolve_identity_claim($user, $payload);
    }
    if ($action === 'identityMappings') {
        return dent_bot_identity_mappings($user, $platform);
    }
    if ($action === 'setIdentityMapping') {
        return dent_bot_set_identity_mapping($user, $platform, $payload);
    }
    if ($action === 'deleteIdentityMapping') {
        return dent_bot_delete_identity_mapping($user, $platform, $payload);
    }
    if ($action === 'setGrade') {
        $targetStudentNumber = dent_normalize_student_number((string) ($payload['studentNumber'] ?? ''));
        $target = $targetStudentNumber !== '' ? dent_get_user_record($targetStudentNumber) : null;
        if (!is_array($target)) {
            dent_error('دانشجوی موردنظر پیدا نشد.', 404);
        }
        $targetCohort = dent_user_cohort_key($target);
        if (!dent_user_has_cohort_management_access($user, $targetCohort)) {
            dent_error('اجازه ثبت نمره را نداری.', 403);
        }
        $courseLabel = dent_clean_grade_course_label((string) ($payload['courseLabel'] ?? ''));
        $score = dent_parse_grade_score($payload['score'] ?? null);
        $maxScore = dent_parse_grade_score($payload['maxScore'] ?? 20);
        if ($courseLabel === '' || $score === null || $maxScore === null || $maxScore <= 0 || $maxScore > 100 || $score < 0 || $score > $maxScore) {
            dent_error('مشخصات نمره نامعتبر است.', 422);
        }
        dent_grades_set_active_cohort($targetCohort);
        $result = dent_owner_apply_grade_import($courseLabel, $maxScore, [[
            'studentNumber' => $targetStudentNumber,
            'score' => $score,
        ]]);
        return [
            'success' => true,
            'course' => $result['course'] ?? null,
            'grades' => dent_owner_grades_payload($targetStudentNumber, (string) ($target['name'] ?? '')),
        ];
    }
    dent_error('عملیات سرویس پشتیبانی نمی‌شود.', 404);
}
