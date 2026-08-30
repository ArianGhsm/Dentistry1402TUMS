<?php
declare(strict_types=1);

require_once __DIR__ . '/notifications_store.php';

function dent_bot_notifications_require_owner(array $user): void
{
    $role = dent_normalize_role((string) ($user['role'] ?? 'student'), (string) ($user['studentNumber'] ?? ''));
    if ($role !== 'owner') {
        dent_error('این عملیات فقط برای مالک مجاز است.', 403, ['code' => 'OWNER_REQUIRED']);
    }
}

function dent_bot_create_deploy_notification(array $user, array $payload): array
{
    dent_bot_notifications_require_owner($user);
    $event = $payload['event'] ?? null;
    if (!is_array($event)) {
        dent_error('رویداد استقرار نامعتبر است.', 422, ['code' => 'INVALID_DEPLOY_EVENT']);
    }
    $record = notifications_create_owner_deploy_event_notice($user, $event);
    return [
        'success' => true,
        'notificationId' => (string) ($record['id'] ?? ''),
        'eventId' => (string) ($event['event_id'] ?? ''),
    ];
}

function dent_bot_notification_feed(array $user, array $payload): array
{
    notifications_process_due_queue($user);
    $store = notifications_read_store();
    $limit = max(1, min(40, (int) ($payload['limit'] ?? 20)));
    return ['success' => true, 'data' => notifications_list_payload_for_user($store, $user, $limit)];
}

function dent_bot_mark_notification_read(array $user, array $payload): array
{
    $notificationId = trim((string) ($payload['notificationId'] ?? ''));
    if (preg_match('/^nt-[A-Za-z0-9._-]{6,80}$/', $notificationId) !== 1) {
        dent_error('اعلان پیدا نشد.', 404, ['code' => 'NOTIFICATION_NOT_FOUND']);
    }
    $summary = notifications_mark_read($user, [$notificationId]);
    return ['success' => true, 'notificationId' => $notificationId, 'summary' => $summary];
}

function dent_bot_notification_audience(array $user, array $payload): array
{
    dent_bot_notifications_require_owner($user);
    return [
        'success' => true,
        'data' => notifications_audience_payload($user, trim((string) ($payload['notificationId'] ?? ''))),
    ];
}

function dent_bot_notification_configured_since(): ?int
{
    $raw = trim((string) (getenv('DENT_BOT_NOTIFICATIONS_SINCE') ?: ''));
    if ($raw === '') {
        return null;
    }
    $timestamp = strtotime($raw);
    return $timestamp === false ? null : $timestamp;
}

function dent_bot_notification_delivery_key(string $platform, string $identityHash, string $notificationId): string
{
    return hash_hmac('sha256', $platform . ':' . $identityHash . ':' . $notificationId, dent_auth_secret_key());
}

function dent_bot_notification_delivery_id(string $deliveryKey): string
{
    return 'nd-' . substr($deliveryKey, 0, 32);
}

function dent_bot_notification_absolute_cta(string $href): string
{
    $clean = notifications_clean_cta_href($href);
    return $clean === '' ? '' : dent_bot_site_origin() . $clean;
}

function dent_bot_claim_notification_deliveries(array $caller, string $platform, array $payload): array
{
    dent_bot_notifications_require_owner($caller);
    $limit = max(1, min(20, (int) ($payload['limit'] ?? 10)));
    $leaseSeconds = max(30, min(600, (int) (getenv('DENT_BOT_NOTIFICATION_LEASE_SECONDS') ?: 120)));
    $maxAttempts = max(1, min(12, (int) (getenv('DENT_BOT_NOTIFICATION_MAX_ATTEMPTS') ?: 5)));
    $configuredSince = dent_bot_notification_configured_since();
    $notificationStore = notifications_read_store();
    $retiredNotificationIds = array_fill_keys(
        is_array($notificationStore['retiredNotificationIds'] ?? null)
            ? $notificationStore['retiredNotificationIds']
            : [],
        true
    );
    $now = time();

    $claimed = dent_bot_store_with_lock(static function (array &$store) use (
        $platform,
        $limit,
        $leaseSeconds,
        $maxAttempts,
        $configuredSince,
        $notificationStore,
        $retiredNotificationIds,
        $now
    ): array {
        dent_bot_cleanup_store($store, $now);
        foreach (($store['notificationDeliveries'] ?? []) as $deliveryKey => $delivery) {
            if (!is_array($delivery)) {
                continue;
            }
            $notificationId = (string) ($delivery['notificationId'] ?? '');
            $status = (string) ($delivery['status'] ?? 'pending');
            if ($notificationId === '' || !isset($retiredNotificationIds[$notificationId]) || in_array($status, ['delivered', 'failed'], true)) {
                continue;
            }
            $delivery['status'] = 'failed';
            $delivery['leaseUntil'] = 0;
            $delivery['reasonCode'] = 'EXAM_REMINDER_RETIRED';
            $store['notificationDeliveries'][$deliveryKey] = $delivery;
        }
        $storedSince = strtotime((string) ($store['notificationDispatchSince'] ?? ''));
        $since = $configuredSince ?? ($storedSince !== false ? $storedSince : $now);
        if ($storedSince === false) {
            $store['notificationDispatchSince'] = gmdate('c', $since);
        }
        $deliveries = [];
        foreach ($store['links'] ?? [] as $identityHash => $link) {
            if (count($deliveries) >= $limit || !is_array($link) || (string) ($link['platform'] ?? '') !== $platform) {
                continue;
            }
            $studentNumber = dent_normalize_student_number((string) ($link['studentNumber'] ?? ''));
            $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
            $platformUserId = dent_decrypt_secret_text($link['platformUserIdEncrypted'] ?? null);
            if (!is_array($user) || preg_match('/^[0-9]{1,24}$/', $platformUserId) !== 1) {
                continue;
            }
            foreach (notifications_visible_records_for_user($notificationStore, $user) as $record) {
                if (notifications_record_is_retired_exam_reminder($record)) {
                    continue;
                }
                if (count($deliveries) >= $limit) {
                    break 2;
                }
                $notificationId = (string) ($record['id'] ?? '');
                $recordMeta = is_array($record['meta'] ?? null) ? $record['meta'] : [];
                if (!empty($recordMeta['disablePush'])) {
                    continue;
                }
                $effectiveAt = notifications_timestamp(notifications_record_effective_at($record));
                if ($notificationId === '' || $effectiveAt < $since) {
                    continue;
                }
                $deliveryKey = dent_bot_notification_delivery_key($platform, (string) $identityHash, $notificationId);
                $existing = is_array($store['notificationDeliveries'][$deliveryKey] ?? null)
                    ? $store['notificationDeliveries'][$deliveryKey]
                    : [];
                $status = (string) ($existing['status'] ?? 'pending');
                $leaseUntil = (int) ($existing['leaseUntil'] ?? 0);
                $attempts = max(0, (int) ($existing['attempts'] ?? 0));
                if ($status === 'delivered' || $status === 'failed' || ($status === 'leased' && $leaseUntil > $now)) {
                    continue;
                }
                if ($attempts >= $maxAttempts) {
                    $existing['status'] = 'failed';
                    $existing['leaseUntil'] = 0;
                    $existing['reasonCode'] = (string) (($existing['reasonCode'] ?? '') ?: 'MAX_ATTEMPTS');
                    $store['notificationDeliveries'][$deliveryKey] = $existing;
                    continue;
                }
                $attempts++;
                $deliveryId = dent_bot_notification_delivery_id($deliveryKey);
                $store['notificationDeliveries'][$deliveryKey] = [
                    'deliveryId' => $deliveryId,
                    'notificationId' => $notificationId,
                    'identityHash' => (string) $identityHash,
                    'platform' => $platform,
                    'status' => 'leased',
                    'attempts' => $attempts,
                    'leaseUntil' => $now + $leaseSeconds,
                    'lastAttemptAt' => dent_iso_now(),
                    'deliveredAt' => '',
                    'reasonCode' => '',
                ];
                $deliveries[] = [
                    'deliveryId' => $deliveryId,
                    'chatId' => $platformUserId,
                    'notification' => [
                        'id' => $notificationId,
                        'source' => (string) ($record['source'] ?? ''),
                        'sourceKey' => (string) ($record['sourceKey'] ?? ''),
                        'title' => (string) ($record['title'] ?? ''),
                        'body' => (string) ($record['body'] ?? ''),
                        'tone' => (string) ($record['tone'] ?? 'accent'),
                        'important' => notifications_record_is_important($record),
                        'effectiveAt' => notifications_record_effective_at($record),
                        'ctaLabel' => (string) ($record['ctaLabel'] ?? ''),
                        'ctaUrl' => dent_bot_notification_absolute_cta((string) ($record['ctaHref'] ?? '')),
                    ],
                ];
            }
        }
        return ['deliveries' => $deliveries];
    });

    return ['success' => true, 'deliveries' => array_values($claimed['deliveries'] ?? [])];
}

function dent_bot_ack_notification_delivery(array $caller, string $platform, array $payload): array
{
    dent_bot_notifications_require_owner($caller);
    $deliveryId = trim((string) ($payload['deliveryId'] ?? ''));
    $delivered = filter_var($payload['delivered'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $reasonCode = dent_clean_text((string) ($payload['reasonCode'] ?? ''), 60);
    if (preg_match('/^nd-[a-f0-9]{32}$/', $deliveryId) !== 1) {
        dent_error('شناسه تحویل نامعتبر است.', 422, ['code' => 'INVALID_DELIVERY_ID']);
    }

    $updated = dent_bot_store_with_lock(static function (array &$store) use ($deliveryId, $platform, $delivered, $reasonCode): array {
        foreach ($store['notificationDeliveries'] ?? [] as $key => $delivery) {
            if (!is_array($delivery) || (string) ($delivery['deliveryId'] ?? '') !== $deliveryId || (string) ($delivery['platform'] ?? '') !== $platform) {
                continue;
            }
            $retired = !$delivered && $reasonCode === 'EXAM_REMINDER_RETIRED';
            $delivery['status'] = $delivered ? 'delivered' : ($retired ? 'failed' : 'pending');
            $delivery['leaseUntil'] = 0;
            $delivery['deliveredAt'] = $delivered ? dent_iso_now() : '';
            $delivery['reasonCode'] = $delivered ? '' : $reasonCode;
            $store['notificationDeliveries'][$key] = $delivery;
            return ['found' => true];
        }
        return ['found' => false];
    });
    if (empty($updated['found'])) {
        dent_error('تحویل اعلان پیدا نشد.', 404, ['code' => 'DELIVERY_NOT_FOUND']);
    }
    return ['success' => true, 'deliveryId' => $deliveryId, 'delivered' => $delivered];
}
