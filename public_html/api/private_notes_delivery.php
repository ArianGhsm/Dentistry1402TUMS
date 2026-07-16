<?php
declare(strict_types=1);

require_once __DIR__ . '/private_notes_processing.php';

function private_notes_tile_token_ttl_seconds(): int
{
    return private_notes_env_int('DENT_PRIVATE_NOTES_TILE_TOKEN_TTL_SECONDS', 60, 10, 600);
}

function private_notes_viewing_session_ttl_seconds(): int
{
    return private_notes_env_int('DENT_PRIVATE_NOTES_VIEWING_SESSION_TTL_SECONDS', 14400, 300, 86400);
}

function private_notes_tile_runtime_cache_ttl_seconds(): int
{
    return private_notes_env_int('DENT_PRIVATE_NOTES_TILE_RUNTIME_CACHE_TTL_SECONDS', 20, 0, 120);
}

function private_notes_watermark_opacity(): float
{
    $raw = trim((string) (getenv('DENT_PRIVATE_NOTES_WATERMARK_OPACITY') ?: ''));
    if ($raw === '' || !is_numeric($raw)) {
        return 0.18;
    }
    return min(0.45, max(0.08, (float) $raw));
}

function private_notes_watermark_font_path(): string
{
    $configured = trim((string) (getenv('DENT_PRIVATE_NOTES_WATERMARK_FONT') ?: ''));
    if ($configured !== '') {
        $path = dent_is_absolute_path($configured) ? $configured : dent_resolve_path($configured, DENT_PROJECT_ROOT);
        if (is_file($path)) {
            return $path;
        }
    }

    $candidates = [
        DENT_PROJECT_ROOT . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . 'Sahel-Bold.ttf',
        DENT_PROJECT_ROOT . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . 'B Nazanin Bold-.ttf',
        DENT_PROJECT_ROOT . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . 'AbarHigh-Bold.ttf',
    ];
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return '';
}

function private_notes_watermark_version(): string
{
    $version = dent_clean_text((string) (getenv('DENT_PRIVATE_NOTES_WATERMARK_VERSION') ?: 'v1'), 40);
    return $version !== '' ? $version : 'v1';
}

function private_notes_delivery_signing_secret(): string
{
    $secret = trim((string) (getenv('DENT_PRIVATE_NOTES_TILE_SIGNING_SECRET') ?: ''));
    if ($secret === '') {
        return '';
    }
    return hash('sha256', $secret, true);
}

function private_notes_delivery_secret_available(): bool
{
    return private_notes_delivery_signing_secret() !== '';
}

function private_notes_request_user_agent(): string
{
    return dent_clean_text((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 220);
}

function private_notes_request_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        $value = trim((string) ($_SERVER[$key] ?? ''));
        if ($value === '') {
            continue;
        }
        if ($key === 'HTTP_X_FORWARDED_FOR') {
            $value = trim((string) explode(',', $value)[0]);
        }
        return dent_clean_text($value, 80);
    }
    return '';
}

function private_notes_delivery_hash(string $purpose, string $value): string
{
    $secret = private_notes_delivery_signing_secret();
    if ($secret === '') {
        return '';
    }
    return hash_hmac('sha256', $purpose . '|' . $value, $secret);
}

function private_notes_device_token_hash(string $userKey, string $deviceToken): string
{
    return private_notes_delivery_hash('device-token|' . $userKey, $deviceToken);
}

function private_notes_request_user_agent_hash(): string
{
    return private_notes_delivery_hash('user-agent', private_notes_request_user_agent());
}

function private_notes_request_ip_hash(): string
{
    return private_notes_delivery_hash('ip', private_notes_request_ip());
}

function private_notes_clean_device_token(string $token): string
{
    $token = trim($token);
    return preg_match('/^[A-Za-z0-9_-]{24,160}$/', $token) === 1 ? $token : '';
}

function private_notes_new_device_token(): string
{
    return dent_base64url_encode(random_bytes(32));
}

function private_notes_find_device_by_token(array $store, string $userKey, string $deviceToken): ?array
{
    $hash = private_notes_device_token_hash($userKey, $deviceToken);
    if ($userKey === '' || $hash === '') {
        return null;
    }
    foreach ($store['registeredDevices'] ?? [] as $device) {
        if (!is_array($device)) {
            continue;
        }
        if ((string) ($device['userKey'] ?? '') === $userKey
            && (string) ($device['tokenHash'] ?? '') !== ''
            && hash_equals((string) ($device['tokenHash'] ?? ''), $hash)
        ) {
            return $device;
        }
    }
    return null;
}

function private_notes_session_trace_code(array $session): string
{
    $seed = (string) ($session['id'] ?? '') . '|' . (string) ($session['userKey'] ?? '') . '|' . (string) ($session['startedAt'] ?? '');
    $hash = private_notes_delivery_hash('session-trace', $seed);
    if ($hash === '') {
        $hash = hash('sha256', $seed);
    }
    return strtoupper(substr($hash, 0, 8));
}

function private_notes_start_viewing_session(array $user, string $documentId, string $deviceToken = '', string $deviceLabel = ''): array
{
    if (!private_notes_delivery_secret_available()) {
        dent_error('Private notes tile signing secret is not configured.', 500);
    }

    $userKey = private_notes_user_key($user);
    $documentId = private_notes_clean_id($documentId, 'pndoc-');
    if ($userKey === '' || $documentId === '') {
        dent_error('Viewing session request is invalid.', 422);
    }

    return private_notes_with_store_lock(static function (array &$store) use ($user, $userKey, $documentId, $deviceToken, $deviceLabel): array {
        $access = private_notes_user_can_view_document($store, $user, $documentId);
        if (empty($access['allowed'])) {
            dent_error('You do not have permission to view this private document.', 403, ['reason' => $access['reason'] ?? 'denied']);
        }
        $document = $store['documents'][$documentId] ?? null;
        if (!is_array($document) || (string) ($document['processingStatus'] ?? '') !== 'ready') {
            dent_error('Document is not ready for secure viewing.', 409);
        }

        $newDeviceToken = '';
        $cleanDeviceToken = private_notes_clean_device_token($deviceToken);
        if ($deviceToken !== '' && $cleanDeviceToken === '') {
            dent_error('Registered device token is invalid.', 422);
        }

        $device = null;
        if ($cleanDeviceToken !== '') {
            $device = private_notes_find_device_by_token($store, $userKey, $cleanDeviceToken);
            if ($device === null) {
                dent_error('Registered device was not recognized.', 403);
            }
            if ((string) ($device['status'] ?? '') !== 'active') {
                dent_error('Registered device is not allowed.', 403);
            }
        } else {
            $newDeviceToken = private_notes_new_device_token();
            $deviceId = private_notes_next_id('pndev-');
            $device = private_notes_normalize_registered_device($deviceId, [
                'id' => $deviceId,
                'userKey' => $userKey,
                'label' => $deviceLabel !== '' ? $deviceLabel : 'Private notes device',
                'tokenHash' => private_notes_device_token_hash($userKey, $newDeviceToken),
                'userAgentHash' => private_notes_request_user_agent_hash(),
                'status' => 'active',
                'firstSeenAt' => dent_iso_now(),
                'lastSeenAt' => dent_iso_now(),
            ]);
            if ($device === null) {
                dent_error('Registered device could not be created.', 500);
            }
            $store['registeredDevices'][(string) $device['id']] = $device;
        }

        $device['lastSeenAt'] = dent_iso_now();
        $device['userAgentHash'] = private_notes_request_user_agent_hash();
        $store['registeredDevices'][(string) $device['id']] = private_notes_normalize_registered_device((string) $device['id'], $device);

        $sessionId = private_notes_next_id('pnses-');
        $session = private_notes_normalize_viewing_session($sessionId, [
            'id' => $sessionId,
            'userKey' => $userKey,
            'documentId' => $documentId,
            'deviceId' => (string) ($device['id'] ?? ''),
            'status' => 'active',
            'startedAt' => dent_iso_now(),
            'lastSeenAt' => dent_iso_now(),
            'ipHash' => private_notes_request_ip_hash(),
            'userAgentHash' => private_notes_request_user_agent_hash(),
        ]);
        if ($session === null) {
            dent_error('Viewing session could not be created.', 500);
        }
        $store['activeViewingSessions'][$sessionId] = $session;

        return [
            'sessionId' => $sessionId,
            'deviceId' => (string) ($device['id'] ?? ''),
            'deviceToken' => $newDeviceToken,
            'traceCode' => private_notes_session_trace_code($session),
            'expiresInSeconds' => private_notes_viewing_session_ttl_seconds(),
        ];
    });
}

function private_notes_clean_tile_request(array $input): array
{
    return [
        'documentId' => private_notes_clean_id((string) ($input['documentId'] ?? ''), 'pndoc-'),
        'sessionId' => private_notes_clean_id((string) ($input['sessionId'] ?? ''), 'pnses-'),
        'pageNumber' => max(0, (int) dent_normalize_digits((string) ($input['pageNumber'] ?? $input['page'] ?? '0'))),
        'zoomLevel' => max(0, (int) dent_normalize_digits((string) ($input['zoomLevel'] ?? $input['z'] ?? '0'))),
        'tileX' => max(0, (int) dent_normalize_digits((string) ($input['tileX'] ?? $input['x'] ?? '0'))),
        'tileY' => max(0, (int) dent_normalize_digits((string) ($input['tileY'] ?? $input['y'] ?? '0'))),
    ];
}

function private_notes_find_tile_metadata(array $document, int $pageNumber, int $zoomLevel, int $tileX, int $tileY): ?array
{
    $page = $document['pages'][(string) $pageNumber] ?? null;
    if (!is_array($page)) {
        return null;
    }
    foreach ((array) ($page['levels'] ?? []) as $level) {
        if (!is_array($level) || (int) ($level['level'] ?? -1) !== $zoomLevel) {
            continue;
        }
        foreach ((array) ($level['tiles'] ?? []) as $tile) {
            if (!is_array($tile)) {
                continue;
            }
            if ((int) ($tile['x'] ?? -1) === $tileX && (int) ($tile['y'] ?? -1) === $tileY) {
                return $tile;
            }
        }
    }
    return null;
}

function private_notes_viewer_manifest_for_document(array $document): array
{
    $pages = [];
    foreach ((array) ($document['pages'] ?? []) as $page) {
        if (!is_array($page)) {
            continue;
        }
        $levels = [];
        foreach ((array) ($page['levels'] ?? []) as $level) {
            if (!is_array($level)) {
                continue;
            }
            $tiles = [];
            foreach ((array) ($level['tiles'] ?? []) as $tile) {
                if (!is_array($tile)) {
                    continue;
                }
                $tiles[] = [
                    'x' => max(0, (int) ($tile['x'] ?? 0)),
                    'y' => max(0, (int) ($tile['y'] ?? 0)),
                    'width' => max(0, (int) ($tile['width'] ?? 0)),
                    'height' => max(0, (int) ($tile['height'] ?? 0)),
                ];
            }
            $levels[] = [
                'level' => max(0, (int) ($level['level'] ?? 0)),
                'scale' => max(0.0, (float) ($level['scale'] ?? 1.0)),
                'width' => max(0, (int) ($level['width'] ?? 0)),
                'height' => max(0, (int) ($level['height'] ?? 0)),
                'tileSize' => max(0, (int) ($level['tileSize'] ?? 0)),
                'tiles' => $tiles,
            ];
        }
        $pages[] = [
            'pageNumber' => max(1, (int) ($page['pageNumber'] ?? 1)),
            'width' => max(0, (int) ($page['width'] ?? 0)),
            'height' => max(0, (int) ($page['height'] ?? 0)),
            'levels' => $levels,
        ];
    }
    usort($pages, static fn(array $left, array $right): int => (int) $left['pageNumber'] <=> (int) $right['pageNumber']);

    return [
        'id' => (string) ($document['id'] ?? ''),
        'title' => (string) ($document['title'] ?? ''),
        'courseId' => (string) ($document['courseId'] ?? ''),
        'semesterId' => (string) ($document['semesterId'] ?? ''),
        'pageCount' => max(0, (int) ($document['pageCount'] ?? count($pages))),
        'renderProfile' => [
            'tileSize' => max(0, (int) ($document['renderProfile']['tileSize'] ?? 0)),
            'format' => 'png',
        ],
        'pages' => $pages,
    ];
}

function private_notes_get_viewer_manifest(array $user, string $documentId): array
{
    $documentId = private_notes_clean_id($documentId, 'pndoc-');
    if ($documentId === '') {
        dent_error('Document id is invalid.', 422);
    }
    $store = private_notes_read_store();
    $access = private_notes_user_can_view_document($store, $user, $documentId);
    if (empty($access['allowed'])) {
        dent_error('You do not have permission to view this private document.', 403, ['reason' => $access['reason'] ?? 'denied']);
    }
    $document = $access['document'] ?? ($store['documents'][$documentId] ?? null);
    if (!is_array($document) || (string) ($document['processingStatus'] ?? '') !== 'ready') {
        dent_error('Document is not ready for secure viewing.', 409);
    }
    return [
        'document' => private_notes_viewer_manifest_for_document($document),
        'tokenTtlSeconds' => private_notes_tile_token_ttl_seconds(),
        'sessionTtlSeconds' => private_notes_viewing_session_ttl_seconds(),
    ];
}

function private_notes_tile_absolute_path(string $storageKey): string
{
    $safe = private_notes_clean_original_file_ref($storageKey);
    if ($safe === '') {
        return '';
    }
    return private_notes_pages_dir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safe);
}

function private_notes_validate_viewing_session_and_device(array $store, array $user, array $document, string $sessionId, ?string $now = null): array
{
    $session = $store['activeViewingSessions'][$sessionId] ?? null;
    if (!is_array($session)) {
        return ['ok' => false, 'reason' => 'viewing-session-not-found'];
    }
    $userKey = private_notes_user_key($user);
    if ((string) ($session['userKey'] ?? '') !== $userKey || (string) ($session['documentId'] ?? '') !== (string) ($document['id'] ?? '')) {
        return ['ok' => false, 'reason' => 'viewing-session-mismatch'];
    }
    if ((string) ($session['status'] ?? '') !== 'active') {
        return ['ok' => false, 'reason' => 'viewing-session-not-active'];
    }
    $lastSeen = strtotime((string) ($session['lastSeenAt'] ?? ''));
    $current = $now !== null ? strtotime(private_notes_now($now)) : time();
    if ($lastSeen !== false && $current !== false && $current - $lastSeen > private_notes_viewing_session_ttl_seconds()) {
        return ['ok' => false, 'reason' => 'viewing-session-expired'];
    }

    $deviceId = (string) ($session['deviceId'] ?? '');
    $device = $store['registeredDevices'][$deviceId] ?? null;
    if (!is_array($device)) {
        return ['ok' => false, 'reason' => 'registered-device-not-found'];
    }
    if ((string) ($device['userKey'] ?? '') !== $userKey || (string) ($device['status'] ?? '') !== 'active') {
        return ['ok' => false, 'reason' => 'registered-device-not-allowed'];
    }
    $currentUaHash = private_notes_request_user_agent_hash();
    $sessionUaHash = (string) ($session['userAgentHash'] ?? '');
    if ($sessionUaHash !== '' && $currentUaHash !== '' && !hash_equals($sessionUaHash, $currentUaHash)) {
        return ['ok' => false, 'reason' => 'viewing-session-user-agent-mismatch'];
    }

    return ['ok' => true, 'session' => $session, 'device' => $device];
}

function private_notes_authorize_tile_request(array $store, array $user, array $request, ?string $now = null): array
{
    if (($request['documentId'] ?? '') === '' || ($request['sessionId'] ?? '') === '' || (int) ($request['pageNumber'] ?? 0) <= 0) {
        return ['ok' => false, 'reason' => 'tile-request-invalid'];
    }

    $access = private_notes_user_can_view_document($store, $user, (string) $request['documentId'], $now);
    if (empty($access['allowed'])) {
        return ['ok' => false, 'reason' => (string) ($access['reason'] ?? 'access-denied')];
    }
    $document = $access['document'] ?? ($store['documents'][(string) $request['documentId']] ?? null);
    if (!is_array($document) || (string) ($document['processingStatus'] ?? '') !== 'ready') {
        return ['ok' => false, 'reason' => 'document-not-ready'];
    }

    $sessionCheck = private_notes_validate_viewing_session_and_device($store, $user, $document, (string) $request['sessionId'], $now);
    if (empty($sessionCheck['ok'])) {
        return $sessionCheck;
    }

    $tile = private_notes_find_tile_metadata(
        $document,
        (int) $request['pageNumber'],
        (int) $request['zoomLevel'],
        (int) $request['tileX'],
        (int) $request['tileY']
    );
    if ($tile === null) {
        return ['ok' => false, 'reason' => 'tile-not-found'];
    }

    return [
        'ok' => true,
        'document' => $document,
        'session' => $sessionCheck['session'],
        'device' => $sessionCheck['device'],
        'tile' => $tile,
    ];
}

function private_notes_sign_tile_token(array $claims): string
{
    $secret = private_notes_delivery_signing_secret();
    if ($secret === '') {
        return '';
    }
    ksort($claims, SORT_STRING);
    $payloadJson = json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($payloadJson) || $payloadJson === '') {
        return '';
    }
    $payload = dent_base64url_encode($payloadJson);
    $signature = dent_base64url_encode(hash_hmac('sha256', $payload, $secret, true));
    return $payload . '.' . $signature;
}

function private_notes_issue_tile_token(array $user, array $input): array
{
    if (!private_notes_delivery_secret_available()) {
        dent_error('Private notes tile signing secret is not configured.', 500);
    }
    $deviceToken = private_notes_clean_device_token((string) ($input['deviceToken'] ?? ''));
    if ($deviceToken === '') {
        dent_error('Registered device token is required.', 422);
    }

    $request = private_notes_clean_tile_request($input);
    $store = private_notes_read_store();
    $auth = private_notes_authorize_tile_request($store, $user, $request);
    if (empty($auth['ok'])) {
        dent_error('Tile request is not allowed.', 403, ['reason' => $auth['reason'] ?? 'denied']);
    }
    $device = $auth['device'];
    $knownDevice = private_notes_find_device_by_token($store, private_notes_user_key($user), $deviceToken);
    if ($knownDevice === null || (string) ($knownDevice['id'] ?? '') !== (string) ($device['id'] ?? '')) {
        dent_error('Registered device token does not match this viewing session.', 403);
    }

    $expires = time() + private_notes_tile_token_ttl_seconds();
    $claims = [
        'v' => 1,
        'uid' => private_notes_user_key($user),
        'doc' => $request['documentId'],
        'sid' => $request['sessionId'],
        'page' => (int) $request['pageNumber'],
        'z' => (int) $request['zoomLevel'],
        'x' => (int) $request['tileX'],
        'y' => (int) $request['tileY'],
        'exp' => $expires,
        'nonce' => dent_base64url_encode(random_bytes(8)),
    ];
    $token = private_notes_sign_tile_token($claims);
    if ($token === '') {
        dent_error('Tile token could not be signed.', 500);
    }

    return [
        'token' => $token,
        'expiresAt' => date('c', $expires),
        'expiresInSeconds' => private_notes_tile_token_ttl_seconds(),
        'tile' => [
            'documentId' => $request['documentId'],
            'sessionId' => $request['sessionId'],
            'pageNumber' => (int) $request['pageNumber'],
            'zoomLevel' => (int) $request['zoomLevel'],
            'tileX' => (int) $request['tileX'],
            'tileY' => (int) $request['tileY'],
        ],
    ];
}

function private_notes_validate_tile_token(string $token, array $expected, ?int $now = null): array
{
    $secret = private_notes_delivery_signing_secret();
    if ($secret === '') {
        return ['ok' => false, 'reason' => 'tile-signing-secret-missing'];
    }
    $token = trim($token);
    $parts = explode('.', $token);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
        return ['ok' => false, 'reason' => 'tile-token-malformed'];
    }
    $expectedSignature = dent_base64url_encode(hash_hmac('sha256', $parts[0], $secret, true));
    if (!hash_equals($expectedSignature, $parts[1])) {
        return ['ok' => false, 'reason' => 'tile-token-bad-signature'];
    }
    $json = dent_base64url_decode($parts[0]);
    $claims = json_decode($json, true);
    if (!is_array($claims)) {
        return ['ok' => false, 'reason' => 'tile-token-invalid-payload'];
    }
    $current = $now ?? time();
    $exp = (int) ($claims['exp'] ?? 0);
    if ($exp <= 0 || $current > $exp) {
        return ['ok' => false, 'reason' => 'tile-token-expired'];
    }

    $checks = [
        'uid' => (string) ($expected['uid'] ?? ''),
        'doc' => (string) ($expected['documentId'] ?? ''),
        'sid' => (string) ($expected['sessionId'] ?? ''),
        'page' => (int) ($expected['pageNumber'] ?? 0),
        'z' => (int) ($expected['zoomLevel'] ?? 0),
        'x' => (int) ($expected['tileX'] ?? 0),
        'y' => (int) ($expected['tileY'] ?? 0),
    ];
    foreach ($checks as $key => $expectedValue) {
        $actual = $claims[$key] ?? null;
        if (is_int($expectedValue)) {
            if ((int) $actual !== $expectedValue) {
                return ['ok' => false, 'reason' => 'tile-token-claim-mismatch', 'claim' => $key];
            }
        } elseif (!hash_equals($expectedValue, (string) $actual)) {
            return ['ok' => false, 'reason' => 'tile-token-claim-mismatch', 'claim' => $key];
        }
    }

    return ['ok' => true, 'claims' => $claims];
}

function private_notes_mask_email(string $email): string
{
    $email = trim($email);
    if ($email === '' || !str_contains($email, '@')) {
        return '';
    }
    [$local, $domain] = explode('@', $email, 2);
    $prefix = dent_utf8_substr($local, 0, 2);
    return $prefix . '***@' . $domain;
}

function private_notes_watermark_contact(array $user): string
{
    $phone = dent_mask_phone_number((string) ($user['phoneNumber'] ?? ''));
    if ($phone !== '') {
        return $phone;
    }
    return private_notes_mask_email((string) ($user['email'] ?? ''));
}

function private_notes_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function private_notes_watermark_svg(array $user, array $session, array $request, int $width, int $height): string
{
    $traceCode = private_notes_session_trace_code($session);
    $contact = private_notes_watermark_contact($user);
    $name = dent_clean_text((string) ($user['name'] ?? ''), 80);
    $student = private_notes_user_key($user);
    $parts = array_values(array_filter([
        $name !== '' ? $name : 'Private viewer',
        $student !== '' ? 'ID ' . $student : '',
        $contact,
        date('Y-m-d H:i'),
        'Trace ' . $traceCode,
    ], static fn(string $value): bool => trim($value) !== ''));
    $line = implode(' | ', $parts);
    $secondary = 'TN-' . $traceCode . '-' . strtoupper(substr(private_notes_delivery_hash('secondary-trace', json_encode($request) ?: ''), 0, 6));
    $opacity = private_notes_watermark_opacity();
    $fontPath = private_notes_watermark_font_path();
    $fontCss = $fontPath !== '' ? "font-family: 'PrivateNotesWatermark';" : "font-family: sans-serif;";
    $fontFace = '';
    if ($fontPath !== '') {
        $fontBytes = @file_get_contents($fontPath);
        if (is_string($fontBytes) && $fontBytes !== '') {
            $fontFace = "<defs><style>@font-face{font-family:'PrivateNotesWatermark';src:url(data:font/ttf;base64," . base64_encode($fontBytes) . ") format('truetype');}</style></defs>";
        }
    }
    $hash = hexdec(substr(private_notes_delivery_hash('watermark-offset', (string) ($session['id'] ?? '')), 0, 6));
    $offsetX = $hash % 180;
    $offsetY = intdiv((int) $hash, 17) % 140;
    $stepX = 360;
    $stepY = 180;
    $texts = [];
    for ($y = -$height; $y < $height * 2; $y += $stepY) {
        for ($x = -$width; $x < $width * 2; $x += $stepX) {
            $texts[] = '<text x="' . ($x + $offsetX) . '" y="' . ($y + $offsetY) . '" transform="rotate(-32 ' . ($x + $offsetX) . ' ' . ($y + $offsetY) . ')" direction="rtl" unicode-bidi="plaintext">' . private_notes_xml_escape($line) . '</text>';
        }
    }
    $secondaryText = '<text class="secondary" x="' . max(10, $width - 12) . '" y="' . max(18, $height - 10) . '" text-anchor="end">' . private_notes_xml_escape($secondary) . '</text>';

    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">'
        . $fontFace
        . '<style>text{' . $fontCss . 'font-size:22px;font-weight:700;fill:#1b1b1b;fill-opacity:' . $opacity . ';}.secondary{' . $fontCss . 'font-size:9px;font-weight:700;fill:#111;fill-opacity:0.32;}</style>'
        . implode('', $texts)
        . $secondaryText
        . '</svg>';
}

function private_notes_tile_cache_dir(): string
{
    return DENT_TMP_ROOT . DIRECTORY_SEPARATOR . 'private_notes_tile_cache';
}

function private_notes_cleanup_tile_cache(): void
{
    $ttl = max(1, private_notes_tile_runtime_cache_ttl_seconds()) * 4;
    $dir = private_notes_tile_cache_dir();
    if (!is_dir($dir) || random_int(1, 25) !== 1) {
        return;
    }
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.png') ?: [] as $file) {
        if (is_file($file) && time() - (int) filemtime($file) > $ttl) {
            @unlink($file);
        }
    }
}

function private_notes_watermarked_tile_path(array $user, array $session, array $request, string $sourcePath): array
{
    $magick = private_notes_find_binary(['magick', 'convert'], 'DENT_PRIVATE_NOTES_IMAGEMAGICK_BIN');
    if ($magick === '') {
        return ['ok' => false, 'error' => 'Tile watermarking requires ImageMagick.'];
    }
    [$width, $height] = private_notes_image_dimensions($sourcePath);
    if ($width <= 0 || $height <= 0) {
        return ['ok' => false, 'error' => 'Tile dimensions could not be read.'];
    }

    $cacheTtl = private_notes_tile_runtime_cache_ttl_seconds();
    $cacheBucket = $cacheTtl > 0 ? (int) floor(time() / $cacheTtl) : time();
    $cacheKey = hash('sha256', implode('|', [
        private_notes_user_key($user),
        (string) ($session['id'] ?? ''),
        (string) ($request['documentId'] ?? ''),
        (string) ($request['pageNumber'] ?? ''),
        (string) ($request['zoomLevel'] ?? ''),
        (string) ($request['tileX'] ?? ''),
        (string) ($request['tileY'] ?? ''),
        private_notes_watermark_version(),
        (string) @filemtime($sourcePath),
        (string) $cacheBucket,
    ]));
    $cacheDir = private_notes_tile_cache_dir();
    dent_ensure_directory($cacheDir);
    $outputPath = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.png';
    if ($cacheTtl > 0 && is_file($outputPath) && time() - (int) filemtime($outputPath) <= $cacheTtl) {
        return ['ok' => true, 'path' => $outputPath, 'cached' => true];
    }

    $svgPath = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.svg';
    $svg = private_notes_watermark_svg($user, $session, $request, $width, $height);
    if (@file_put_contents($svgPath, $svg, LOCK_EX) === false) {
        return ['ok' => false, 'error' => 'Watermark overlay could not be prepared.'];
    }
    $render = private_notes_run_command([$magick, $sourcePath, $svgPath, '-compose', 'over', '-composite', $outputPath], 60);
    @unlink($svgPath);
    if ((int) ($render['exitCode'] ?? 1) !== 0 || !is_file($outputPath)) {
        return ['ok' => false, 'error' => 'Watermarked tile could not be rendered: ' . trim((string) ($render['stderr'] ?? ''))];
    }
    @chmod($outputPath, 0640);
    private_notes_cleanup_tile_cache();
    return ['ok' => true, 'path' => $outputPath, 'cached' => false];
}

function private_notes_rate_limit_tile_hook(array $user, array $request): array
{
    return [
        'ok' => true,
        'scope' => 'private-notes-tile',
        'userKey' => private_notes_user_key($user),
        'documentId' => (string) ($request['documentId'] ?? ''),
        'sessionId' => (string) ($request['sessionId'] ?? ''),
    ];
}

function private_notes_record_tile_view_event(array $user, array $request, array $session): void
{
    private_notes_with_store_lock(static function (array &$store) use ($user, $request, $session): array {
        $sessionId = (string) ($session['id'] ?? $request['sessionId'] ?? '');
        if (is_array($store['activeViewingSessions'][$sessionId] ?? null)) {
            $updated = $store['activeViewingSessions'][$sessionId];
            $updated['lastSeenAt'] = dent_iso_now();
            $store['activeViewingSessions'][$sessionId] = private_notes_normalize_viewing_session($sessionId, $updated);
        }
        $eventId = private_notes_next_id('pnevt-');
        $event = private_notes_normalize_view_event($eventId, [
            'id' => $eventId,
            'sessionId' => $sessionId,
            'userKey' => private_notes_user_key($user),
            'documentId' => (string) ($request['documentId'] ?? ''),
            'pageNumber' => (int) ($request['pageNumber'] ?? 0),
            'zoomLevel' => (int) ($request['zoomLevel'] ?? 0),
            'tileX' => (int) ($request['tileX'] ?? 0),
            'tileY' => (int) ($request['tileY'] ?? 0),
            'eventType' => 'tile-view',
            'createdAt' => dent_iso_now(),
            'ipHash' => private_notes_request_ip_hash(),
            'userAgentHash' => private_notes_request_user_agent_hash(),
        ]);
        if ($event !== null) {
            $store['documentViewEvents'][$eventId] = $event;
        }
        return [];
    });
}

function private_notes_deliver_tile(array $user, array $input): void
{
    if (!private_notes_delivery_secret_available()) {
        dent_error('Private notes tile signing secret is not configured.', 500);
    }
    $request = private_notes_clean_tile_request($input);
    $token = trim((string) ($input['token'] ?? ''));
    $expected = array_merge($request, ['uid' => private_notes_user_key($user)]);
    $tokenCheck = private_notes_validate_tile_token($token, $expected);
    if (empty($tokenCheck['ok'])) {
        dent_error('Tile token is invalid.', 403, ['reason' => $tokenCheck['reason'] ?? 'invalid-token']);
    }
    $rate = private_notes_rate_limit_tile_hook($user, $request);
    if (empty($rate['ok'])) {
        dent_error('Tile request is rate limited.', 429);
    }

    $store = private_notes_read_store();
    $auth = private_notes_authorize_tile_request($store, $user, $request);
    if (empty($auth['ok'])) {
        dent_error('Tile request is not allowed.', 403, ['reason' => $auth['reason'] ?? 'denied']);
    }
    $tile = $auth['tile'];
    $sourcePath = private_notes_tile_absolute_path((string) ($tile['storageKey'] ?? ''));
    if ($sourcePath === '' || !is_file($sourcePath)) {
        dent_error('Private tile is not available.', 404);
    }

    $watermarked = private_notes_watermarked_tile_path($user, $auth['session'], $request, $sourcePath);
    if (empty($watermarked['ok'])) {
        dent_error((string) ($watermarked['error'] ?? 'Watermarked tile could not be rendered.'), 500);
    }
    private_notes_record_tile_view_event($user, $request, $auth['session']);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: image/png');
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . (string) filesize((string) $watermarked['path']));
    }
    readfile((string) $watermarked['path']);
    exit;
}
