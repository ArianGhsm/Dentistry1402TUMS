<?php
declare(strict_types=1);

require_once __DIR__ . '/hamiad_dashboard.php';
require_once __DIR__ . '/notifications_store.php';

function hamiad_reminder_state_path(): string
{
    return dent_storage_path('hamiad/assignment-watch.json');
}

function hamiad_assignment_identity(array $task): string
{
    $courseId = (int)($task['courseId'] ?? 0);
    $activityId = (int)($task['id'] ?? 0);
    return $courseId > 0 && $activityId > 0 ? $courseId . ':' . $activityId : '';
}

/**
 * All notifications target the canonical owner identity, never the cohort.
 * Course enrollment in a personal Moodle account cannot prove another
 * student's eligibility to receive that student's assignment details.
 */
function hamiad_enqueue_owner_notice(string $sourceKey, string $title, string $body, string $tone, array $meta): bool
{
    $owner = dent_owner_student_number();
    if ($owner === '' || dent_get_user_record($owner) === null) {
        throw new RuntimeException('حساب مالک برای اعلان همیاد قابل شناسایی نیست.');
    }
    $created = notifications_with_store_lock(static function (array &$store) use (
        $owner, $sourceKey, $title, $body, $tone, $meta
    ): ?string {
        $signature = notifications_source_signature('hamiad', $sourceKey);
        if ($signature !== '' && isset($store['suppressedSources'][$signature])) {
            return null;
        }
        foreach (($store['notifications'] ?? []) as $existing) {
            if (is_array($existing) && ($existing['source'] ?? '') === 'hamiad'
                && ($existing['sourceKey'] ?? '') === $sourceKey) {
                return null;
            }
        }
        $id = notifications_generate_id();
        $record = notifications_normalize_record($id, [
            'id' => $id,
            'kind' => DENT_NOTIFICATION_KIND_HAMIAD_ASSIGNMENT,
            'title' => $title,
            'body' => $body,
            'tone' => $tone,
            'target' => DENT_NOTIFICATION_TARGET_USER,
            'targetStudentNumber' => $owner,
            'source' => 'hamiad',
            'sourceKey' => $sourceKey,
            'ctaHref' => '/hamiad/',
            'ctaLabel' => 'مشاهده تکالیف',
            'createdAt' => dent_iso_now(),
            'publishAt' => dent_iso_now(),
            'releasedAt' => dent_iso_now(),
            'status' => DENT_NOTIFICATION_STATUS_ACTIVE,
            'meta' => $meta,
            'sendSms' => false,
            'recipients' => notifications_snapshot_recipients_for_target(
                DENT_NOTIFICATION_TARGET_USER, '', $owner
            ),
        ]);
        if ($record === null) {
            return null;
        }
        $store['notifications'][$record['id']] = $record;
        return $record['id'];
    });
    if ($created === null) {
        return false;
    }
    notifications_dispatch_push_if_needed($created);
    return true;
}

function hamiad_reminder_events(array $dashboard, array $priorState, int $clock): array
{
    $known = is_array($priorState['seen'] ?? null) ? $priorState['seen'] : [];
    $sent = is_array($priorState['sent'] ?? null) ? $priorState['sent'] : [];
    $hasBaseline = !empty($priorState['initialized']);
    $events = [];
    $seen = $known;
    foreach (($dashboard['assignments'] ?? []) as $task) {
        if (!is_array($task)) {
            continue;
        }
        $key = hamiad_assignment_identity($task);
        if ($key === '') {
            continue;
        }
        $seen[$key] = true;
        if ($hasBaseline && !isset($known[$key])) {
            $events[] = [
                'key' => 'created:' . $key,
                'kind' => 'created',
                'task' => $task,
            ];
        }
        if (!$hasBaseline) {
            continue; // Establish a read-only baseline without spamming old tasks.
        }
        $due = strtotime((string)($task['dueAt'] ?? ''));
        if (($task['dueStatus'] ?? '') !== 'upcoming' || $due === false || $due <= $clock) {
            continue;
        }
        $seconds = $due - $clock;
        $threshold = $seconds <= 86400 ? 1 : ($seconds <= 172800 ? 2 :
            ($seconds <= 604800 ? 7 : 0));
        if (!$threshold) {
            continue;
        }
        $day = date('Y-m-d', $due);
        $mark = 'reminder:' . $key . ':' . $day . ':' . $threshold;
        if (!isset($sent[$mark])) {
            $events[] = [
                'key' => $mark,
                'kind' => 'reminder',
                'days' => $threshold,
                'task' => $task,
            ];
        }
    }
    return ['events' => $events, 'seen' => $seen, 'sent' => $sent];
}

function hamiad_process_reminders(bool $dryRun = false, ?array $providedDashboard = null, ?int $clock = null): array
{
    $clock ??= time();
    $dashboard = $providedDashboard ?? hamiad_term7_dashboard();
    if (empty($dashboard['connected']) || !isset($dashboard['assignments'])) {
        throw new RuntimeException('نشست فعال همیاد موجود نیست یا اطلاعات تکالیف کامل دریافت نشد.');
    }
    $lockFile = dent_storage_path('hamiad/assignment-watch.lock');
    dent_ensure_directory(dirname($lockFile));
    $lock = fopen($lockFile, 'c+');
    if (!is_resource($lock) || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('پایش دیگری از تکالیف همیاد در حال اجراست.');
    }
    try {
        $prior = dent_read_json_file(hamiad_reminder_state_path(), [
            'initialized' => false, 'seen' => [], 'sent' => [],
        ]);
        if (!is_array($prior)) {
            throw new RuntimeException('داده ذخیره‌شدهٔ پایش تکالیف نامعتبر است.');
        }
        $plan = hamiad_reminder_events($dashboard, $prior, $clock);
        if ($dryRun) {
            return [
                'success' => true,
                'dryRun' => true,
                'assignmentCount' => count($dashboard['assignments']),
                'planned' => count($plan['events']),
                'initialized' => !empty($prior['initialized']),
            ];
        }
        $state = [
            'initialized' => true,
            'seen' => $plan['seen'],
            'sent' => $plan['sent'],
            'lastCheckedAt' => date(DATE_ATOM, $clock),
        ];
        $emitted = 0;
        foreach ($plan['events'] as $event) {
            $task = $event['task'];
            $name = trim((string)($task['title'] ?? 'تکلیف'));
            $course = trim((string)($task['courseTitle'] ?? ''));
            $dueText = trim((string)($task['dueText'] ?? ''));
            $label = $event['kind'] === 'created' ? 'تکلیف جدید همیاد' : 'یادآوری مهلت تکلیف همیاد';
            $tone = $event['kind'] === 'created' ? 'info' :
                (($event['days'] ?? 0) === 1 ? 'danger' : 'warn');
            $body = $course . "\n" . $name . ($dueText !== '' ? "\nزمان باقی‌مانده: " . $dueText : '');
            $meta = [
                'courseId' => (int)($task['courseId'] ?? 0),
                'assignmentId' => (int)($task['id'] ?? 0),
                'dueAt' => (string)($task['dueAt'] ?? ''),
                'dueText' => $dueText,
                'reminderDays' => (int)($event['days'] ?? 0),
                'important' => ($event['days'] ?? 0) === 1,
            ];
            $created = hamiad_enqueue_owner_notice('assignment:' . $event['key'], $label, $body, $tone, $meta);
            $state['sent'][$event['key']] = date(DATE_ATOM, $clock);
            if ($created) {
                $emitted++;
            }
        }
        dent_write_json_file(hamiad_reminder_state_path(), $state);
        return [
            'success' => true,
            'assignmentCount' => count($dashboard['assignments']),
            'planned' => count($plan['events']),
            'sent' => $emitted,
            'initialized' => true,
        ];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
