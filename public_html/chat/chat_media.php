<?php
declare(strict_types=1);

// Chat attachment/media storage, preview, access and upload primitives.

function chat_media_root_path(): string
{
    $cohortKey = chat_active_cohort();
    if ($cohortKey === dent_prosthesis_legacy_cohort_key()) {
        return dent_storage_path('prosthesis_1402/chat/media');
    }
    if ($cohortKey === dent_primary_cohort_key()) {
        return dent_storage_path('chat/media');
    }

    return dent_storage_path('chat/' . dent_cohort_storage_slug($cohortKey) . '_media');
}

function chat_media_originals_path(): string
{
    return chat_media_root_path() . DIRECTORY_SEPARATOR . 'originals';
}

function chat_media_previews_path(): string
{
    return chat_media_root_path() . DIRECTORY_SEPARATOR . 'previews';
}

function chat_media_cleanup_log_path(): string
{
    return dent_storage_path('chat/media-cleanup.log');
}

function chat_media_target_bytes(): int
{
    $envMb = getenv('DENT_CHAT_MEDIA_TARGET_MB');
    if ($envMb !== false) {
        $parsed = (int) trim((string) $envMb);
        if ($parsed >= 128) {
            return $parsed * 1024 * 1024;
        }
    }

    return CHAT_MEDIA_DEFAULT_TARGET_BYTES;
}

function chat_public_media_url(string $attachmentId, string $variant = CHAT_MEDIA_VARIANT_ORIGINAL, bool $download = false): string
{
    $query = [
        'action' => 'media',
        'attachmentId' => $attachmentId,
        'variant' => $variant === CHAT_MEDIA_VARIANT_PREVIEW ? CHAT_MEDIA_VARIANT_PREVIEW : CHAT_MEDIA_VARIANT_ORIGINAL,
    ];
    if (chat_active_cohort() !== dent_primary_cohort_key()) {
        $query['cohort'] = chat_active_cohort();
    }
    if ($download) {
        $query['download'] = '1';
    }

    return '/chat/chat_api.php?' . http_build_query($query);
}

function chat_clean_attachment_id(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^' . preg_quote(CHAT_ATTACHMENT_ID_PREFIX, '/') . '[a-z0-9-]{1,64}$/', $value) !== 1) {
        return '';
    }

    return $value;
}

function chat_clean_media_variant(?string $value): string
{
    $value = trim((string) $value);
    if ($value === CHAT_MEDIA_VARIANT_PREVIEW) {
        return CHAT_MEDIA_VARIANT_PREVIEW;
    }
    return CHAT_MEDIA_VARIANT_ORIGINAL;
}

function chat_parse_attachment_ids_input($raw): array
{
    $items = [];
    if (is_array($raw)) {
        $items = $raw;
    } else {
        $text = trim((string) $raw);
        if ($text === '') {
            return [];
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            $items = $decoded;
        } else {
            $items = preg_split('/[\s,;]+/u', $text) ?: [];
        }
    }

    $normalized = [];
    $seen = [];
    foreach ($items as $item) {
        $clean = chat_clean_attachment_id((string) $item);
        if ($clean === '' || isset($seen[$clean])) {
            continue;
        }
        $seen[$clean] = true;
        $normalized[] = $clean;
    }

    return $normalized;
}

function chat_safe_extension(string $fileName): string
{
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($extension === '' || preg_match('/^[a-z0-9]{1,12}$/', $extension) !== 1) {
        return '';
    }
    return $extension;
}

function chat_is_blocked_upload_extension(string $extension): bool
{
    if ($extension === '') {
        return false;
    }

    static $blocked = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'exe', 'dll', 'com', 'bat', 'cmd', 'sh', 'msi',
        'jar', 'js', 'mjs', 'cjs', 'vbs', 'ps1', 'psm1',
        'scr', 'reg', 'lnk', 'app', 'apk', 'dmg',
        'html', 'htm', 'svg',
    ];

    return in_array($extension, $blocked, true);
}

function chat_attachment_category_for_mime(string $mime, string $extension, bool $voice = false): string
{
    if ($voice) {
        return 'voice';
    }

    $mime = strtolower(trim($mime));
    $extension = strtolower(trim($extension));

    if ($mime !== '') {
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if ($mime === 'application/pdf') {
            return 'pdf';
        }
        if (str_contains($mime, 'officedocument')
            || str_contains($mime, 'msword')
            || str_contains($mime, 'vnd.ms-')) {
            return 'office';
        }
        if (str_contains($mime, 'zip')
            || str_contains($mime, 'rar')
            || str_contains($mime, '7z')
            || str_contains($mime, 'tar')
            || str_contains($mime, 'gzip')) {
            return 'archive';
        }
    }

    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic', 'heif'], true)) {
        return 'image';
    }
    if (in_array($extension, ['mp4', 'webm', 'mov', 'mkv', 'avi', 'm4v'], true)) {
        return 'video';
    }
    if (in_array($extension, ['mp3', 'wav', 'ogg', 'oga', 'm4a', 'aac', 'flac', 'opus'], true)) {
        return 'audio';
    }
    if ($extension === 'pdf') {
        return 'pdf';
    }
    if (in_array($extension, ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'odt', 'ods', 'odp', 'rtf'], true)) {
        return 'office';
    }
    if (in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'bz2'], true)) {
        return 'archive';
    }
    if (in_array($extension, ['txt', 'csv', 'json', 'xml', 'md'], true)) {
        return 'document';
    }

    return 'file';
}

function chat_clean_media_relative_path(?string $value): string
{
    $value = trim(str_replace('\\', '/', (string) $value));
    if ($value === '') {
        return '';
    }

    if (str_contains($value, '..')) {
        return '';
    }

    if (preg_match('/^[a-z0-9\/_.-]{3,220}$/', strtolower($value)) !== 1) {
        return '';
    }

    return ltrim($value, '/');
}

function chat_attachment_category_label(string $category, int $count = 1): string
{
    $count = max(1, $count);
    $category = dent_clean_text($category, 20);

    if ($count > 1) {
        return $count . ' فایل';
    }

    return match ($category) {
        'image' => 'تصویر',
        'video' => 'ویدیو',
        'audio' => 'فایل صوتی',
        'voice' => 'پیام صوتی',
        'document' => 'سند',
        'pdf' => 'PDF',
        'office' => 'Office',
        'archive' => 'آرشیو',
        default => 'فایل',
    };
}

function chat_normalize_attachment_record(string $attachmentId, array $attachment, array $knownConversationIds = []): ?array
{
    $attachmentId = chat_clean_attachment_id($attachmentId !== '' ? $attachmentId : (string) ($attachment['id'] ?? ''));
    if ($attachmentId === '') {
        return null;
    }

    $conversationId = chat_clean_conversation_id((string) ($attachment['conversationId'] ?? ''));
    if ($conversationId !== '' && $knownConversationIds !== [] && !isset($knownConversationIds[$conversationId])) {
        $conversationId = '';
    }

    $messageId = isset($attachment['messageId']) ? (int) $attachment['messageId'] : null;
    if ($messageId !== null && $messageId <= 0) {
        $messageId = null;
    }

    $uploaderStudentNumber = dent_normalize_student_number((string) (
        $attachment['uploaderStudentNumber']
        ?? $attachment['studentNumber']
        ?? ''
    ));
    if ($uploaderStudentNumber === '') {
        return null;
    }

    $mime = strtolower(dent_clean_text((string) ($attachment['mime'] ?? ''), 120));
    $extension = chat_safe_extension((string) ($attachment['extension'] ?? ($attachment['ext'] ?? '')));
    $isVoice = chat_parse_bool($attachment['isVoice'] ?? false, false);
    $category = dent_clean_text((string) ($attachment['category'] ?? ''), 20);
    if ($category === '') {
        $category = chat_attachment_category_for_mime($mime, $extension, $isVoice);
    }
    if (!in_array($category, ['image', 'video', 'audio', 'voice', 'document', 'pdf', 'office', 'archive', 'file'], true)) {
        $category = chat_attachment_category_for_mime($mime, $extension, $isVoice);
    }
    if ($category === 'voice') {
        $isVoice = true;
    }

    $status = dent_clean_text((string) ($attachment['status'] ?? 'available'), 20);
    if (!in_array($status, ['available', 'expired'], true)) {
        $status = 'available';
    }

    $createdAt = chat_parse_timestamp($attachment['createdAt'] ?? null) ?? time();
    $updatedAt = chat_parse_timestamp($attachment['updatedAt'] ?? null) ?? $createdAt;
    $linkedAt = chat_parse_timestamp($attachment['linkedAt'] ?? null);
    $purgedAt = chat_parse_timestamp($attachment['purgedAt'] ?? null);
    if ($status !== 'expired') {
        $purgedAt = null;
    } elseif ($purgedAt === null) {
        $purgedAt = $updatedAt;
    }

    $originalPath = chat_clean_media_relative_path((string) ($attachment['originalPath'] ?? ''));
    $previewPath = chat_clean_media_relative_path((string) ($attachment['previewPath'] ?? ''));
    $originalExists = chat_parse_bool($attachment['originalExists'] ?? ($status === 'available' && $originalPath !== ''), $status === 'available' && $originalPath !== '');
    $previewExists = chat_parse_bool($attachment['previewExists'] ?? ($previewPath !== ''), $previewPath !== '');
    if ($status === 'expired') {
        $originalExists = false;
    }

    $originalName = dent_clean_text((string) ($attachment['originalName'] ?? ($attachment['name'] ?? '')), 180);
    if ($originalName === '') {
        $originalName = $attachmentId;
    }

    $safeFileName = dent_clean_text((string) ($attachment['safeFileName'] ?? ''), 200);
    if ($safeFileName === '') {
        $safeFileName = $attachmentId . ($extension !== '' ? ('.' . $extension) : '');
    }

    return [
        'id' => $attachmentId,
        'conversationId' => $conversationId,
        'messageId' => $messageId,
        'uploaderStudentNumber' => $uploaderStudentNumber,
        'originalName' => $originalName,
        'safeFileName' => $safeFileName,
        'mime' => $mime,
        'extension' => $extension,
        'category' => $category,
        'isVoice' => $isVoice,
        'sizeBytes' => max(0, (int) ($attachment['sizeBytes'] ?? 0)),
        'durationSeconds' => isset($attachment['durationSeconds']) ? max(0, (float) $attachment['durationSeconds']) : null,
        'width' => isset($attachment['width']) ? max(1, (int) $attachment['width']) : null,
        'height' => isset($attachment['height']) ? max(1, (int) $attachment['height']) : null,
        'createdAt' => $createdAt,
        'updatedAt' => max($updatedAt, $createdAt),
        'linkedAt' => $linkedAt,
        'status' => $status,
        'purgedAt' => $purgedAt,
        'purgeReason' => dent_clean_text((string) ($attachment['purgeReason'] ?? ''), 140),
        'originalPath' => $originalPath,
        'previewPath' => $previewPath,
        'originalExists' => $originalExists,
        'previewExists' => $previewExists,
    ];
}

function chat_get_attachment(array $store, string $attachmentId): ?array
{
    $attachmentId = chat_clean_attachment_id($attachmentId);
    if ($attachmentId === '') {
        return null;
    }

    $attachment = $store['attachments'][$attachmentId] ?? null;
    return is_array($attachment) ? $attachment : null;
}

function chat_put_attachment(array &$store, array $attachment): void
{
    $attachmentId = chat_clean_attachment_id((string) ($attachment['id'] ?? ''));
    if ($attachmentId === '') {
        return;
    }

    if (!isset($store['attachments']) || !is_array($store['attachments'])) {
        $store['attachments'] = [];
    }

    $conversationIds = [];
    foreach ((array) ($store['conversations'] ?? []) as $conversationId => $_conversation) {
        $conversationIds[(string) $conversationId] = true;
    }

    $normalized = chat_normalize_attachment_record($attachmentId, $attachment, $conversationIds);
    if ($normalized === null) {
        return;
    }

    $store['attachments'][$attachmentId] = $normalized;
}

function chat_log_media_cleanup(string $message): void
{
    $line = '[' . date('c') . '] ' . dent_force_utf8($message) . PHP_EOL;
    @file_put_contents(chat_media_cleanup_log_path(), $line, FILE_APPEND | LOCK_EX);
}

function chat_mark_attachment_expired(array &$attachment, string $reason, bool $keepStatusWhenAlreadyExpired = true): void
{
    $currentStatus = (string) ($attachment['status'] ?? 'available');
    if (!$keepStatusWhenAlreadyExpired || $currentStatus !== 'expired') {
        $attachment['status'] = 'expired';
    }
    $attachment['originalExists'] = false;
    $attachment['purgedAt'] = time();
    $attachment['updatedAt'] = time();
    if (dent_clean_text($reason, 140) !== '') {
        $attachment['purgeReason'] = dent_clean_text($reason, 140);
    }
}

function chat_attachment_size_bytes(array $attachment): int
{
    $path = chat_attachment_absolute_path((string) ($attachment['originalPath'] ?? ''));
    if ($path !== '' && is_file($path)) {
        $size = @filesize($path);
        if ($size !== false) {
            return max(0, (int) $size);
        }
    }
    return max(0, (int) ($attachment['sizeBytes'] ?? 0));
}

function chat_sync_attachment_file_flags(array &$attachment): bool
{
    $changed = false;

    $originalPath = chat_attachment_absolute_path((string) ($attachment['originalPath'] ?? ''));
    $previewPath = chat_attachment_absolute_path((string) ($attachment['previewPath'] ?? ''));
    $originalExists = $originalPath !== '' && is_file($originalPath);
    $previewExists = $previewPath !== '' && is_file($previewPath);

    if ((bool) ($attachment['originalExists'] ?? false) !== $originalExists) {
        $attachment['originalExists'] = $originalExists;
        $changed = true;
    }
    if ((bool) ($attachment['previewExists'] ?? false) !== $previewExists) {
        $attachment['previewExists'] = $previewExists;
        $changed = true;
    }

    if (!$originalExists && (string) ($attachment['status'] ?? 'available') === 'available') {
        chat_mark_attachment_expired($attachment, 'original-file-missing');
        $changed = true;
    }

    return $changed;
}

function chat_delete_attachment_original_file(array &$attachment): bool
{
    $path = chat_attachment_absolute_path((string) ($attachment['originalPath'] ?? ''));
    if ($path === '' || !is_file($path)) {
        chat_mark_attachment_expired($attachment, 'original-file-missing');
        return true;
    }

    if (!@unlink($path)) {
        return false;
    }

    chat_mark_attachment_expired($attachment, 'storage-cleanup');
    return true;
}

function chat_delete_attachment_artifacts(array $attachment): void
{
    $originalPath = chat_attachment_absolute_path((string) ($attachment['originalPath'] ?? ''));
    if ($originalPath !== '' && is_file($originalPath)) {
        @unlink($originalPath);
    }

    $previewPath = chat_attachment_absolute_path((string) ($attachment['previewPath'] ?? ''));
    if ($previewPath !== '' && is_file($previewPath)) {
        @unlink($previewPath);
    }
}

function chat_sync_media_health(array &$store, bool $forceCleanup = false): bool
{
    $attachments = is_array($store['attachments'] ?? null) ? $store['attachments'] : [];
    $maintenance = is_array($store['maintenance'] ?? null)
        ? chat_default_maintenance_state($store['maintenance'])
        : chat_default_maintenance_state();

    $changed = false;
    $usageBytes = 0;
    $originalCount = 0;
    $cleanupThreshold = max(0.1, min(0.95, (float) ($maintenance['mediaCleanupThreshold'] ?? CHAT_MEDIA_CLEANUP_THRESHOLD)));
    $targetBytes = max(64 * 1024 * 1024, (int) ($maintenance['mediaTargetBytes'] ?? chat_media_target_bytes()));

    foreach ($attachments as $attachmentId => $attachment) {
        if (!is_array($attachment)) {
            continue;
        }
        $normalizedAttachment = chat_normalize_attachment_record((string) $attachmentId, $attachment);
        if ($normalizedAttachment === null) {
            unset($attachments[$attachmentId]);
            $changed = true;
            continue;
        }

        if (chat_sync_attachment_file_flags($normalizedAttachment)) {
            $changed = true;
        }

        if ((bool) ($normalizedAttachment['originalExists'] ?? false)) {
            $originalCount++;
            $usageBytes += chat_attachment_size_bytes($normalizedAttachment);
        }

        $attachments[$attachmentId] = $normalizedAttachment;
    }

    $thresholdBytes = (int) floor($targetBytes * $cleanupThreshold);
    $cleanupTriggered = $forceCleanup || $usageBytes > $thresholdBytes;
    $purgedCountThisRun = 0;
    if ($cleanupTriggered && $usageBytes > 0) {
        $candidates = [];
        foreach ($attachments as $attachmentId => $attachment) {
            if (!is_array($attachment) || !(bool) ($attachment['originalExists'] ?? false)) {
                continue;
            }

            $candidates[] = [
                'id' => (string) $attachmentId,
                'createdAt' => (int) ($attachment['createdAt'] ?? 0),
            ];
        }

        usort($candidates, static function (array $left, array $right): int {
            if ((int) $left['createdAt'] !== (int) $right['createdAt']) {
                return (int) $left['createdAt'] <=> (int) $right['createdAt'];
            }
            return strcasecmp((string) $left['id'], (string) $right['id']);
        });

        foreach ($candidates as $candidate) {
            if ($usageBytes <= $thresholdBytes) {
                break;
            }

            $attachmentId = (string) ($candidate['id'] ?? '');
            if ($attachmentId === '' || !isset($attachments[$attachmentId]) || !is_array($attachments[$attachmentId])) {
                continue;
            }

            $size = chat_attachment_size_bytes($attachments[$attachmentId]);
            $deleted = chat_delete_attachment_original_file($attachments[$attachmentId]);
            if (!$deleted) {
                $maintenance['lastMediaCleanupStatus'] = 'failed';
                $maintenance['lastMediaCleanupError'] = 'failed-to-delete-' . $attachmentId;
                chat_log_media_cleanup('FAILED delete original for ' . $attachmentId);
                continue;
            }

            $purgedCountThisRun++;
            $usageBytes = max(0, $usageBytes - $size);
            $originalCount = max(0, $originalCount - 1);
            chat_log_media_cleanup('Purged original file for ' . $attachmentId . ' (' . $size . ' bytes)');
            $changed = true;
        }
    }

    $maintenance['mediaTargetBytes'] = $targetBytes;
    $maintenance['mediaCleanupThreshold'] = $cleanupThreshold;
    $maintenance['mediaUsageBytes'] = max(0, $usageBytes);
    $maintenance['mediaOriginalCount'] = max(0, $originalCount);
    $maintenance['lastMediaMaintenanceAt'] = time();
    if ($cleanupTriggered) {
        $maintenance['mediaCleanupRuns'] = max(0, (int) ($maintenance['mediaCleanupRuns'] ?? 0)) + 1;
        $maintenance['lastMediaCleanupAt'] = time();
        $maintenance['mediaPurgedCount'] = max(0, (int) ($maintenance['mediaPurgedCount'] ?? 0)) + $purgedCountThisRun;
        if ($purgedCountThisRun > 0) {
            $maintenance['lastMediaCleanupStatus'] = 'purged';
            $maintenance['lastMediaCleanupError'] = '';
        } elseif (($maintenance['lastMediaCleanupStatus'] ?? '') !== 'failed') {
            $maintenance['lastMediaCleanupStatus'] = 'ok';
            $maintenance['lastMediaCleanupError'] = '';
        }
    } elseif (($maintenance['lastMediaCleanupStatus'] ?? '') === '') {
        $maintenance['lastMediaCleanupStatus'] = 'ok';
    }

    if (($store['attachments'] ?? null) !== $attachments) {
        $store['attachments'] = $attachments;
        $changed = true;
    }

    if (($store['maintenance'] ?? null) !== $maintenance) {
        $store['maintenance'] = $maintenance;
        $changed = true;
    }

    return $changed;
}

function chat_media_status_payload(array $store): array
{
    $maintenance = is_array($store['maintenance'] ?? null)
        ? chat_default_maintenance_state($store['maintenance'])
        : chat_default_maintenance_state();
    $targetBytes = max(64 * 1024 * 1024, (int) ($maintenance['mediaTargetBytes'] ?? chat_media_target_bytes()));
    $usageBytes = max(0, (int) ($maintenance['mediaUsageBytes'] ?? 0));
    $usagePercent = $targetBytes > 0 ? (($usageBytes / $targetBytes) * 100) : 0;

    return [
        'targetBytes' => $targetBytes,
        'usageBytes' => $usageBytes,
        'usagePercent' => round($usagePercent, 2),
        'thresholdPercent' => round(max(0.1, min(0.95, (float) ($maintenance['mediaCleanupThreshold'] ?? CHAT_MEDIA_CLEANUP_THRESHOLD))) * 100, 2),
        'originalCount' => max(0, (int) ($maintenance['mediaOriginalCount'] ?? 0)),
        'purgedCount' => max(0, (int) ($maintenance['mediaPurgedCount'] ?? 0)),
        'cleanupRuns' => max(0, (int) ($maintenance['mediaCleanupRuns'] ?? 0)),
        'lastCleanupAt' => isset($maintenance['lastMediaCleanupAt']) ? (int) $maintenance['lastMediaCleanupAt'] : null,
        'lastMaintenanceAt' => isset($maintenance['lastMediaMaintenanceAt']) ? (int) $maintenance['lastMediaMaintenanceAt'] : null,
        'lastCleanupStatus' => (string) ($maintenance['lastMediaCleanupStatus'] ?? ''),
        'lastCleanupError' => (string) ($maintenance['lastMediaCleanupError'] ?? ''),
        'cleanupHealthy' => (string) ($maintenance['lastMediaCleanupStatus'] ?? 'ok') !== 'failed',
    ];
}

function chat_attachment_absolute_path(string $relativePath): string
{
    $clean = chat_clean_media_relative_path($relativePath);
    if ($clean === '') {
        return '';
    }

    return chat_media_root_path() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean);
}

function chat_attachment_payload(array $attachment): array
{
    $attachmentId = (string) ($attachment['id'] ?? '');
    $category = (string) ($attachment['category'] ?? 'file');
    $status = (string) ($attachment['status'] ?? 'available');
    $originalExists = (bool) ($attachment['originalExists'] ?? false);
    $previewExists = (bool) ($attachment['previewExists'] ?? false);
    $isAvailable = $status === 'available' && $originalExists;

    return [
        'id' => $attachmentId,
        'conversationId' => (string) ($attachment['conversationId'] ?? ''),
        'messageId' => isset($attachment['messageId']) ? (int) $attachment['messageId'] : null,
        'name' => (string) ($attachment['originalName'] ?? ''),
        'safeFileName' => (string) ($attachment['safeFileName'] ?? ''),
        'mime' => (string) ($attachment['mime'] ?? ''),
        'extension' => (string) ($attachment['extension'] ?? ''),
        'category' => $category,
        'isVoice' => (bool) ($attachment['isVoice'] ?? false),
        'sizeBytes' => max(0, (int) ($attachment['sizeBytes'] ?? 0)),
        'durationSeconds' => isset($attachment['durationSeconds']) ? max(0, (float) $attachment['durationSeconds']) : null,
        'width' => isset($attachment['width']) ? max(1, (int) $attachment['width']) : null,
        'height' => isset($attachment['height']) ? max(1, (int) $attachment['height']) : null,
        'createdAt' => (int) ($attachment['createdAt'] ?? time()),
        'linkedAt' => isset($attachment['linkedAt']) ? (int) ($attachment['linkedAt']) : null,
        'status' => $status,
        'available' => $isAvailable,
        'expired' => $status === 'expired' || !$isAvailable,
        'purgedAt' => isset($attachment['purgedAt']) ? (int) ($attachment['purgedAt']) : null,
        'purgeReason' => (string) ($attachment['purgeReason'] ?? ''),
        'hasPreview' => $previewExists,
        'url' => $isAvailable ? chat_public_media_url($attachmentId, CHAT_MEDIA_VARIANT_ORIGINAL, false) : '',
        'downloadUrl' => $isAvailable ? chat_public_media_url($attachmentId, CHAT_MEDIA_VARIANT_ORIGINAL, true) : '',
        'previewUrl' => $previewExists
            ? chat_public_media_url($attachmentId, CHAT_MEDIA_VARIANT_PREVIEW, false)
            : ($isAvailable && $category === 'image'
                ? chat_public_media_url($attachmentId, CHAT_MEDIA_VARIANT_ORIGINAL, false)
                : ''),
    ];
}

function chat_message_attachments_payload(array $message, array $store): array
{
    $payload = [];
    $attachmentIds = chat_parse_attachment_ids_input($message['attachmentIds'] ?? []);
    foreach ($attachmentIds as $attachmentId) {
        $attachment = chat_get_attachment($store, $attachmentId);
        if ($attachment === null) {
            continue;
        }

        $payload[] = chat_attachment_payload($attachment);
    }

    return $payload;
}

function chat_attachment_access_allowed(array $store, array $attachment, array $user): bool
{
    $viewerStudentNumber = chat_actor_student_number($user);
    if ($viewerStudentNumber === '') {
        return false;
    }

    $uploaderStudentNumber = dent_normalize_student_number((string) ($attachment['uploaderStudentNumber'] ?? ''));
    $conversationId = chat_clean_conversation_id((string) ($attachment['conversationId'] ?? ''));

    if ($conversationId === '') {
        return $viewerStudentNumber === $uploaderStudentNumber;
    }

    $conversation = chat_get_conversation($store, $conversationId);
    if ($conversation === null) {
        return false;
    }

    return chat_is_member($conversation, $viewerStudentNumber)
        || chat_user_can_view_without_membership($conversation, $user);
}

function chat_safe_download_filename(string $rawName, string $fallbackId): string
{
    $name = dent_clean_text($rawName, 180);
    if ($name === '') {
        $name = $fallbackId;
    }

    $name = str_replace(["\r", "\n", "\t"], ' ', $name);
    return preg_replace('/[^\p{L}\p{N}\s._-]+/u', '_', $name) ?: $fallbackId;
}

function chat_stream_attachment_file(array $attachment, string $variant, bool $download): void
{
    $variant = chat_clean_media_variant($variant);
    $attachmentId = (string) ($attachment['id'] ?? '');
    $category = (string) ($attachment['category'] ?? 'file');

    $relativePath = $variant === CHAT_MEDIA_VARIANT_PREVIEW
        ? (string) ($attachment['previewPath'] ?? '')
        : (string) ($attachment['originalPath'] ?? '');
    $absolutePath = chat_attachment_absolute_path($relativePath);
    if ($absolutePath === '' || !is_file($absolutePath)) {
        if ($variant === CHAT_MEDIA_VARIANT_ORIGINAL) {
            dent_error('فایل اصلی دیگر در دسترس نیست.', 410, [
                'expired' => true,
                'attachmentId' => $attachmentId,
            ]);
        }

        dent_error('پیش‌نمایش فایل در دسترس نیست.', 404, [
            'attachmentId' => $attachmentId,
        ]);
    }

    $mime = $variant === CHAT_MEDIA_VARIANT_PREVIEW
        ? 'image/jpeg'
        : strtolower(trim((string) ($attachment['mime'] ?? '')));
    if ($mime === '') {
        $mime = 'application/octet-stream';
    }

    if ($variant === CHAT_MEDIA_VARIANT_ORIGINAL && $category === 'voice') {
        if (!str_starts_with($mime, 'audio/')) {
            $mime = chat_voice_audio_mime($mime);
        }
        $download = false;
    }

    $size = @filesize($absolutePath);
    if ($size === false) {
        $size = 0;
    }

    http_response_code(200);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) max(0, (int) $size));
    header('Cache-Control: private, max-age=120');

    if ($download) {
        $fileName = chat_safe_download_filename((string) ($attachment['originalName'] ?? ''), $attachmentId);
        header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
    } else {
        header('Content-Disposition: inline');
    }

    readfile($absolutePath);
    exit;
}

function chat_collect_attachable_ids(array &$store, string $conversationId, array $user, array $attachmentIds): array
{
    $conversationId = chat_clean_conversation_id($conversationId);
    $actorStudentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($conversationId === '' || $actorStudentNumber === '') {
        return [];
    }

    $cleanIds = chat_parse_attachment_ids_input($attachmentIds);
    if ($cleanIds === []) {
        return [];
    }

    $resolved = [];
    foreach ($cleanIds as $attachmentId) {
        $attachment = chat_get_attachment($store, $attachmentId);
        if ($attachment === null) {
            dent_error('یکی از فایل‌های انتخاب‌شده پیدا نشد.', 404);
        }

        if ((string) ($attachment['conversationId'] ?? '') !== $conversationId) {
            dent_error('فایل انتخاب‌شده متعلق به این گفتگو نیست.', 422);
        }

        if (dent_normalize_student_number((string) ($attachment['uploaderStudentNumber'] ?? '')) !== $actorStudentNumber) {
            dent_error('اجازه الصاق این فایل را ندارید.', 403);
        }

        if ((string) ($attachment['status'] ?? 'available') !== 'available') {
            dent_error('یکی از فایل‌های انتخاب‌شده منقضی شده است.', 422);
        }

        $linkedMessageId = (int) ($attachment['messageId'] ?? 0);
        if ($linkedMessageId > 0) {
            dent_error('یکی از فایل‌های انتخاب‌شده قبلاً به پیام دیگری متصل شده است.', 409);
        }

        $resolved[] = $attachmentId;
    }

    return $resolved;
}

function chat_clone_attachment_for_forward(
    array &$store,
    array $attachment,
    string $targetConversationId,
    array $user
): ?string {
    $source = chat_normalize_attachment_record((string) ($attachment['id'] ?? ''), $attachment);
    if ($source === null) {
        return null;
    }
    if ((string) ($source['status'] ?? 'available') !== 'available') {
        return null;
    }

    $sourceOriginalPath = chat_attachment_absolute_path((string) ($source['originalPath'] ?? ''));
    if ($sourceOriginalPath === '' || !is_file($sourceOriginalPath)) {
        return null;
    }

    $attachmentId = chat_next_attachment_id($store);
    $extension = chat_safe_extension((string) ($source['extension'] ?? ''));
    $safeFileName = $attachmentId . ($extension !== '' ? ('.' . $extension) : '');
    $originalRelative = 'originals/' . $safeFileName;
    $originalPath = chat_attachment_absolute_path($originalRelative);
    if ($originalPath === '') {
        return null;
    }

    dent_ensure_directory(dirname($originalPath));
    if (!@copy($sourceOriginalPath, $originalPath)) {
        return null;
    }

    $previewRelative = '';
    $previewExists = false;
    $sourcePreviewPath = chat_attachment_absolute_path((string) ($source['previewPath'] ?? ''));
    if ((bool) ($source['previewExists'] ?? false) && $sourcePreviewPath !== '' && is_file($sourcePreviewPath)) {
        $previewRelative = 'previews/' . $attachmentId . '.jpg';
        $previewPath = chat_attachment_absolute_path($previewRelative);
        if ($previewPath !== '') {
            dent_ensure_directory(dirname($previewPath));
            if (@copy($sourcePreviewPath, $previewPath)) {
                $previewExists = true;
            } else {
                $previewRelative = '';
            }
        }
    }

    $savedSize = @filesize($originalPath);
    $sizeBytes = $savedSize !== false
        ? max(0, (int) $savedSize)
        : max(0, (int) ($source['sizeBytes'] ?? 0));

    $now = time();
    $clone = [
        'id' => $attachmentId,
        'conversationId' => chat_clean_conversation_id($targetConversationId),
        'messageId' => null,
        'uploaderStudentNumber' => dent_normalize_student_number((string) ($user['studentNumber'] ?? '')),
        'originalName' => (string) ($source['originalName'] ?? $attachmentId),
        'safeFileName' => $safeFileName,
        'mime' => (string) ($source['mime'] ?? ''),
        'extension' => $extension,
        'category' => (string) ($source['category'] ?? 'file'),
        'isVoice' => (bool) ($source['isVoice'] ?? false),
        'sizeBytes' => $sizeBytes,
        'durationSeconds' => isset($source['durationSeconds']) ? max(0, (float) $source['durationSeconds']) : null,
        'width' => isset($source['width']) ? max(1, (int) $source['width']) : null,
        'height' => isset($source['height']) ? max(1, (int) $source['height']) : null,
        'createdAt' => $now,
        'updatedAt' => $now,
        'linkedAt' => null,
        'status' => 'available',
        'purgedAt' => null,
        'purgeReason' => '',
        'originalPath' => $originalRelative,
        'previewPath' => $previewRelative,
        'originalExists' => true,
        'previewExists' => $previewExists,
    ];

    chat_put_attachment($store, $clone);
    return $attachmentId;
}

function chat_uploaded_file_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'حجم فایل بیشتر از حد مجاز سرور است.',
        UPLOAD_ERR_PARTIAL => 'بارگذاری فایل ناقص انجام شد. دوباره تلاش کنید.',
        UPLOAD_ERR_NO_FILE => 'فایلی برای بارگذاری انتخاب نشده است.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'بارگذاری فایل روی سرور با خطا مواجه شد.',
        default => 'بارگذاری فایل انجام نشد.',
    };
}

function chat_detect_file_mime(string $path, string $fallback = ''): string
{
    $fallback = strtolower(trim($fallback));

    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = @finfo_file($finfo, $path);
            @finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return strtolower(trim($mime));
            }
        }
    }

    if ($fallback !== '') {
        return $fallback;
    }

    return 'application/octet-stream';
}

function chat_voice_audio_mime(string $mime): string
{
    $mime = strtolower(trim($mime));
    $map = [
        'video/webm' => 'audio/webm',
        'video/ogg' => 'audio/ogg',
        'application/ogg' => 'audio/ogg',
        'video/mp4' => 'audio/mp4',
        'video/x-matroska' => 'audio/webm',
    ];
    if (isset($map[$mime])) {
        return $map[$mime];
    }
    if ($mime === '' || $mime === 'application/octet-stream') {
        return 'audio/webm';
    }

    return $mime;
}

function chat_generate_image_preview(string $sourcePath, string $targetPath): bool
{
    if (!function_exists('imagecreatefromstring')) {
        return false;
    }

    $raw = @file_get_contents($sourcePath);
    if (!is_string($raw) || $raw === '') {
        return false;
    }

    $image = @imagecreatefromstring($raw);
    if (!$image) {
        return false;
    }

    $sourceWidth = imagesx($image);
    $sourceHeight = imagesy($image);
    if ($sourceWidth <= 0 || $sourceHeight <= 0) {
        imagedestroy($image);
        return false;
    }

    $maxEdge = 480;
    $scale = min(1, $maxEdge / max($sourceWidth, $sourceHeight));
    $targetWidth = max(1, (int) floor($sourceWidth * $scale));
    $targetHeight = max(1, (int) floor($sourceHeight * $scale));

    $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
    if (!$canvas) {
        imagedestroy($image);
        return false;
    }

    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);
    imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

    dent_ensure_directory(dirname($targetPath));
    $saved = @imagejpeg($canvas, $targetPath, 76);

    imagedestroy($canvas);
    imagedestroy($image);
    return (bool) $saved;
}

function chat_exec_available(): bool
{
    if (!function_exists('exec')) {
        return false;
    }

    $disabledRaw = strtolower((string) (ini_get('disable_functions') ?: ''));
    if ($disabledRaw === '') {
        return true;
    }

    $disabled = array_map('trim', explode(',', $disabledRaw));
    return !in_array('exec', $disabled, true);
}

function chat_ffmpeg_binary(): string
{
    static $resolved = null;
    if (is_string($resolved)) {
        return $resolved;
    }

    $candidate = trim((string) (getenv('DENT_CHAT_FFMPEG_BIN') ?: 'ffmpeg'));
    if ($candidate === '') {
        $resolved = '';
        return $resolved;
    }

    $resolved = $candidate;
    return $resolved;
}

function chat_generate_video_preview(string $sourcePath, string $targetPath): bool
{
    if (!chat_exec_available()) {
        return false;
    }

    $binary = chat_ffmpeg_binary();
    if ($binary === '') {
        return false;
    }

    dent_ensure_directory(dirname($targetPath));
    $scaleFilter = "thumbnail,scale='min(480,iw)':-2";
    $commands = [
        escapeshellarg($binary)
            . ' -y -ss 00:00:01 -i ' . escapeshellarg($sourcePath)
            . ' -frames:v 1 -vf ' . escapeshellarg($scaleFilter)
            . ' -q:v 7 ' . escapeshellarg($targetPath)
            . ' 2>&1',
        escapeshellarg($binary)
            . ' -y -i ' . escapeshellarg($sourcePath)
            . ' -frames:v 1 -vf ' . escapeshellarg($scaleFilter)
            . ' -q:v 7 ' . escapeshellarg($targetPath)
            . ' 2>&1',
    ];

    foreach ($commands as $command) {
        if (is_file($targetPath)) {
            @unlink($targetPath);
        }

        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);
        if ($exitCode === 0 && is_file($targetPath)) {
            return true;
        }
    }

    return false;
}

function chat_store_uploaded_attachment(
    array &$store,
    array $user,
    string $conversationId,
    array $fileInfo,
    array $options = []
): array {
    if (!isset($fileInfo['tmp_name']) || !isset($fileInfo['name'])) {
        dent_error('فایل بارگذاری‌شده معتبر نیست.', 422);
    }

    $errorCode = (int) ($fileInfo['error'] ?? UPLOAD_ERR_OK);
    if ($errorCode !== UPLOAD_ERR_OK) {
        dent_error(chat_uploaded_file_error_message($errorCode), 422);
    }

    $tmpPath = (string) ($fileInfo['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        dent_error('فایل بارگذاری‌شده معتبر نیست.', 422);
    }

    $originalName = dent_clean_text((string) ($fileInfo['name'] ?? 'file'), 180);
    if ($originalName === '') {
        $originalName = 'file';
    }

    $extension = chat_safe_extension($originalName);
    if (chat_is_blocked_upload_extension($extension)) {
        dent_error('این نوع فایل برای امنیت سامانه قابل بارگذاری نیست.', 422);
    }

    $sizeBytes = max(0, (int) ($fileInfo['size'] ?? 0));
    if ($sizeBytes <= 0) {
        $sizeBytes = max(0, (int) (@filesize($tmpPath) ?: 0));
    }
    if ($sizeBytes <= 0) {
        dent_error('فایل انتخاب‌شده خالی است.', 422);
    }
    if ($sizeBytes > CHAT_MEDIA_MAX_FILE_BYTES) {
        dent_error('حجم فایل از سقف مجاز بیشتر است.', 422);
    }

    $voiceFlag = chat_parse_bool($options['isVoice'] ?? false, false);
    $durationSeconds = isset($options['durationSeconds']) ? max(0, (float) $options['durationSeconds']) : null;
    $mime = chat_detect_file_mime($tmpPath, (string) ($fileInfo['type'] ?? ''));
    $category = chat_attachment_category_for_mime($mime, $extension, $voiceFlag);
    if ($voiceFlag && !in_array($category, ['audio', 'voice'], true)) {
        dent_error('فایل ضبط‌شده به‌عنوان پیام صوتی معتبر نیست.', 422);
    }
    if ($voiceFlag) {
        $category = 'voice';
        if (!str_starts_with($mime, 'audio/')) {
            $clientMime = strtolower(trim((string) ($fileInfo['type'] ?? '')));
            $clientMime = explode(';', $clientMime)[0];
            $mime = str_starts_with($clientMime, 'audio/') ? $clientMime : chat_voice_audio_mime($mime);
        }
    }

    $attachmentId = chat_next_attachment_id($store);
    $safeFileName = $attachmentId . ($extension !== '' ? ('.' . $extension) : '');
    $originalRelative = 'originals/' . $safeFileName;
    $originalPath = chat_attachment_absolute_path($originalRelative);
    if ($originalPath === '') {
        dent_error('مسیر ذخیره فایل معتبر نیست.', 500);
    }
    dent_ensure_directory(dirname($originalPath));

    if (!@move_uploaded_file($tmpPath, $originalPath)) {
        dent_error('ذخیره فایل روی سرور انجام نشد.', 500);
    }

    $savedSize = @filesize($originalPath);
    if ($savedSize !== false) {
        $sizeBytes = max(0, (int) $savedSize);
    }

    $previewRelative = '';
    $previewExists = false;
    if ($category === 'image' || $category === 'video') {
        $previewRelative = 'previews/' . $attachmentId . '.jpg';
        $previewPath = chat_attachment_absolute_path($previewRelative);
        $previewGenerated = false;
        if ($previewPath !== '') {
            if ($category === 'image') {
                $previewGenerated = chat_generate_image_preview($originalPath, $previewPath);
            } else {
                $previewGenerated = chat_generate_video_preview($originalPath, $previewPath);
            }
        }

        if ($previewPath !== '' && $previewGenerated) {
            $previewExists = true;
        } else {
            $previewRelative = '';
            $previewExists = false;
        }
    }

    $now = time();
    $attachment = [
        'id' => $attachmentId,
        'conversationId' => $conversationId,
        'messageId' => null,
        'uploaderStudentNumber' => dent_normalize_student_number((string) ($user['studentNumber'] ?? '')),
        'originalName' => $originalName,
        'safeFileName' => $safeFileName,
        'mime' => $mime,
        'extension' => $extension,
        'category' => $category,
        'isVoice' => $category === 'voice',
        'sizeBytes' => $sizeBytes,
        'durationSeconds' => $durationSeconds,
        'width' => null,
        'height' => null,
        'createdAt' => $now,
        'updatedAt' => $now,
        'linkedAt' => null,
        'status' => 'available',
        'purgedAt' => null,
        'purgeReason' => '',
        'originalPath' => $originalRelative,
        'previewPath' => $previewRelative,
        'originalExists' => true,
        'previewExists' => $previewExists,
    ];

    chat_put_attachment($store, $attachment);
    chat_sync_media_health($store, false);

    $storedAttachment = chat_get_attachment($store, $attachmentId);
    if ($storedAttachment === null) {
        dent_error('فایل بارگذاری شد اما ثبت متادیتا انجام نشد.', 500);
    }

    return chat_attachment_payload($storedAttachment);
}
