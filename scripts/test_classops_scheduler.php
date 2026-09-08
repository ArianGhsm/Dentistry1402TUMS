<?php
declare(strict_types=1);

require_once __DIR__ . '/../public_html/api/classops_modules/scheduler/classops_reminder_planner.php';

$checks = 0;
$failures = 0;
$assert = static function (bool $condition, string $label) use (&$checks, &$failures): void {
    $checks++;
    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }
    $failures++;
    echo "FAIL: {$label}\n";
};

$clockAt = static fn(string $iso): callable => static fn(): DateTimeImmutable => new DateTimeImmutable($iso, new DateTimeZone('UTC'));
$id = static fn(int $n): string => 'cop_' . str_pad(dechex($n), 24, '0', STR_PAD_LEFT);
$audHash = hash('sha256', 'cohort-dentistry-1402');

$policy = static function (array $rules, array $catchUp = ['mode' => 'skip', 'maxAgeSeconds' => 0]): array {
    return [
        'version' => CLASSOPS_REMINDER_CONTRACT_VERSION,
        'timezone' => CLASSOPS_REMINDER_TIMEZONE,
        'catchUp' => $catchUp,
        'rules' => $rules,
    ];
};
$item = static function (string $itemId, string $type, array $rules, array $overrides = []) use ($policy, $audHash): array {
    return array_replace_recursive([
        'itemId' => $itemId,
        'revision' => 1,
        'itemType' => $type,
        'status' => 'active',
        'timing' => ['startsAt' => null, 'dueAt' => null],
        'audience' => ['ref' => 'audience_snapshot_dentistry_1402', 'hash' => $audHash],
        'deliveryPolicyRef' => 'delivery_policy_private_v1',
        'reminderPolicy' => $policy($rules),
        'serviceRef' => null,
    ], $overrides);
};
$snapshot = static function (array $items, array $known = [], int $horizon = 604800, int $max = 64): array {
    return [
        'contractVersion' => CLASSOPS_REMINDER_CONTRACT_VERSION,
        'planningHorizonSeconds' => $horizon,
        'maxOccurrences' => $max,
        'items' => $items,
        'knownOccurrences' => $known,
    ];
};
$knownFromIntent = static function (array $intent, string $state = 'planned'): array {
    return [
        'occurrenceKey' => $intent['occurrenceKey'],
        'idempotencyKey' => $intent['idempotencyKey'],
        'itemId' => $intent['itemId'],
        'revision' => $intent['revision'],
        'ruleId' => $intent['ruleId'],
        'dueAt' => $intent['dueAt'],
        'state' => $state,
    ];
};

$absoluteItem = $item($id(1), 'event', [[
    'ruleId' => 'absolute-one', 'type' => 'absolute', 'at' => '2026-09-08T10:00:00Z', 'reason' => 'absolute-test',
]]);
$absolutePlan = classops_reminder_plan($snapshot([$absoluteItem]), $clockAt('2026-09-08T09:00:00Z'));
$assert($absolutePlan['ok'] === true && count($absolutePlan['intents']) === 1, 'absolute reminder produces one due intent');
$assert(($absolutePlan['intents'][0]['dueAt'] ?? '') === '2026-09-08T10:00:00Z', 'absolute reminder preserves canonical UTC dueAt');
$absoluteAgain = classops_reminder_plan($snapshot([$absoluteItem]), $clockAt('2026-09-08T09:00:00Z'));
$assert($absolutePlan === $absoluteAgain, 'injected clock makes planning byte-structure deterministic');

$taskItem = $item($id(2), 'task', [[
    'ruleId' => 'task-minus-one-hour', 'type' => 'relative', 'anchor' => 'dueAt', 'offsetSeconds' => -3600, 'reason' => 'task-deadline',
]], ['timing' => ['dueAt' => '2026-09-10T12:00:00Z']]);
$taskBefore = $taskItem;
$taskPlan = classops_reminder_plan($snapshot([$taskItem]), $clockAt('2026-09-10T10:00:00Z'));
$assert(($taskPlan['intents'][0]['dueAt'] ?? '') === '2026-09-10T11:00:00Z', 'relative offset is resolved from dueAt');
$assert($taskItem === $taskBefore, 'task/deadline planning does not mutate the item snapshot');

$boundaryItem = $item($id(3), 'event', [[
    'ruleId' => 'night-before', 'type' => 'daypart', 'anchor' => 'startsAt', 'dayOffset' => -1, 'daypart' => 'night', 'reason' => 'night-before',
]], ['timing' => ['startsAt' => '2026-09-09T20:45:00Z']]);
$boundaryPlan = classops_reminder_plan($snapshot([$boundaryItem]), $clockAt('2026-09-08T00:00:00Z'));
$assert(($boundaryPlan['intents'][0]['dueAt'] ?? '') === '2026-09-09T17:30:00Z', 'daypart uses Tehran local date across UTC midnight boundary');

$examItem = $item($id(4), 'exam', [
    ['ruleId' => 'exam-t3', 'type' => 'relative', 'anchor' => 'startsAt', 'offsetSeconds' => -259200, 'reason' => 'exam-t3'],
    ['ruleId' => 'exam-t1', 'type' => 'relative', 'anchor' => 'startsAt', 'offsetSeconds' => -86400, 'reason' => 'exam-t1'],
    ['ruleId' => 'exam-night', 'type' => 'daypart', 'anchor' => 'startsAt', 'dayOffset' => -1, 'daypart' => 'night', 'reason' => 'exam-night-before'],
    ['ruleId' => 'exam-morning', 'type' => 'daypart', 'anchor' => 'startsAt', 'dayOffset' => 0, 'daypart' => 'morning', 'reason' => 'exam-morning-of'],
], ['timing' => ['startsAt' => '2026-09-10T05:30:00Z']]);
$examPlan = classops_reminder_plan($snapshot([$examItem]), $clockAt('2026-09-06T00:00:00Z'));
$examTimes = array_column($examPlan['intents'], 'dueAt', 'ruleId');
$assert(($examTimes['exam-t3'] ?? '') === '2026-09-07T05:30:00Z', 'exam T-3 is interpreted generically');
$assert(($examTimes['exam-t1'] ?? '') === '2026-09-09T05:30:00Z', 'exam T-1 is interpreted generically');
$assert(($examTimes['exam-night'] ?? '') === '2026-09-09T17:30:00Z', 'exam night-before maps to 21:00 Tehran');
$assert(($examTimes['exam-morning'] ?? '') === '2026-09-10T04:30:00Z', 'exam morning-of maps to 08:00 Tehran');

$firstIntent = $absolutePlan['intents'][0];
$duplicatePlan = classops_reminder_plan($snapshot([$absoluteItem], [$knownFromIntent($firstIntent)]), $clockAt('2026-09-08T09:00:00Z'));
$assert(count($duplicatePlan['intents']) === 0, 'repeated planner run suppresses known occurrence');
$assert($firstIntent['occurrenceKey'] === $absoluteAgain['intents'][0]['occurrenceKey'] && $firstIntent['idempotencyKey'] === $absoluteAgain['intents'][0]['idempotencyKey'], 'same occurrence has stable occurrence and idempotency keys');

$rescheduleOriginal = $item($id(5), 'event', [[
    'ruleId' => 'start-reminder', 'type' => 'relative', 'anchor' => 'startsAt', 'offsetSeconds' => -3600, 'reason' => 'before-start',
]], ['timing' => ['startsAt' => '2026-09-10T10:00:00Z']]);
$rescheduleFirst = classops_reminder_plan($snapshot([$rescheduleOriginal]), $clockAt('2026-09-08T00:00:00Z'));
$oldKnown = $knownFromIntent($rescheduleFirst['intents'][0]);
$rescheduled = $rescheduleOriginal;
$rescheduled['revision'] = 2;
$rescheduled['timing']['startsAt'] = '2026-09-10T12:00:00Z';
$reschedulePlan = classops_reminder_plan($snapshot([$rescheduled], [$oldKnown]), $clockAt('2026-09-08T00:00:00Z'));
$assert(count($reschedulePlan['intents']) === 1 && ($reschedulePlan['intents'][0]['dueAt'] ?? '') === '2026-09-10T11:00:00Z', 'reschedule emits the new future occurrence');
$assert(($reschedulePlan['supersessions'][0]['reason'] ?? '') === 'item_revision_changed', 'reschedule supersedes prior future planned occurrence');

$cancelled = $rescheduleOriginal;
$cancelled['revision'] = 2;
$cancelled['status'] = 'cancelled';
$cancelPlan = classops_reminder_plan($snapshot([$cancelled], [$oldKnown]), $clockAt('2026-09-08T00:00:00Z'));
$assert(count($cancelPlan['intents']) === 0 && ($cancelPlan['supersessions'][0]['reason'] ?? '') === 'item_cancelled', 'cancel supersedes future planned reminders');
$archived = $cancelled;
$archived['status'] = 'archived';
$archivePlan = classops_reminder_plan($snapshot([$archived], [$oldKnown]), $clockAt('2026-09-08T00:00:00Z'));
$assert(($archivePlan['supersessions'][0]['reason'] ?? '') === 'item_archived', 'archive supersedes future planned reminders');
$deliveredKnown = $oldKnown;
$deliveredKnown['state'] = 'delivered';
$deliveredPlan = classops_reminder_plan($snapshot([$cancelled], [$deliveredKnown]), $clockAt('2026-09-08T00:00:00Z'));
$assert($deliveredPlan['supersessions'] === [], 'planner never attempts to retract already delivered side effects');

$catchRules = [
    ['ruleId' => 'missed-old', 'type' => 'absolute', 'at' => '2026-09-08T08:00:00Z', 'reason' => 'old'],
    ['ruleId' => 'missed-latest', 'type' => 'absolute', 'at' => '2026-09-08T08:45:00Z', 'reason' => 'latest'],
];
$catchItem = $item($id(6), 'deadline', $catchRules, ['reminderPolicy' => $policy($catchRules, ['mode' => 'latest_once', 'maxAgeSeconds' => 7200])]);
$catchPlan = classops_reminder_plan($snapshot([$catchItem]), $clockAt('2026-09-08T09:00:00Z'));
$assert(count($catchPlan['intents']) === 1 && ($catchPlan['intents'][0]['ruleId'] ?? '') === 'missed-latest', 'latest_once prevents catch-up storms across multiple missed rules');
$assert(($catchPlan['intents'][0]['catchUp'] ?? false) === true && ($catchPlan['intents'][0]['plannedDueAt'] ?? '') === '2026-09-08T09:00:00Z', 'catch-up intent records original dueAt and current plannedDueAt');
$skipItem = $item($id(7), 'deadline', $catchRules);
$skipPlan = classops_reminder_plan($snapshot([$skipItem]), $clockAt('2026-09-08T09:00:00Z'));
$assert(count($skipPlan['intents']) === 0, 'skip policy does not replay missed reminders');

$farItem = $item($id(8), 'event', [[
    'ruleId' => 'far', 'type' => 'absolute', 'at' => '2026-10-01T00:00:00Z', 'reason' => 'outside-horizon',
]]);
$farPlan = classops_reminder_plan($snapshot([$farItem], [], 86400), $clockAt('2026-09-08T00:00:00Z'));
$assert($farPlan['intents'] === [], 'occurrences beyond planning horizon are excluded');
$badHorizon = classops_reminder_plan($snapshot([$farItem], [], CLASSOPS_REMINDER_MAX_HORIZON_SECONDS + 1), $clockAt('2026-09-08T00:00:00Z'));
$assert($badHorizon['ok'] === false && ($badHorizon['errors'][0]['code'] ?? '') === 'CLASSOPS_REMINDER_HORIZON_OUT_OF_RANGE', 'oversized horizon fails closed with machine-readable code');
$manyRules = [];
for ($i = 0; $i < 4; $i++) {
    $manyRules[] = ['ruleId' => 'many-' . $i, 'type' => 'absolute', 'at' => sprintf('2026-09-08T1%d:00:00Z', $i), 'reason' => 'bounded'];
}
$manyItem = $item($id(9), 'event', $manyRules);
$manyPlan = classops_reminder_plan($snapshot([$manyItem], [], 86400, 2), $clockAt('2026-09-08T09:00:00Z'));
$assert(count($manyPlan['intents']) === 2 && $manyPlan['truncated'] === true, 'planner maxOccurrences bounds output and signals truncation');

$sabaRules = [[
    'ruleId' => 'saba-daily', 'type' => 'recurring', 'frequency' => 'daily', 'interval' => 1,
    'localTime' => '20:00', 'startAt' => '2025-01-01T00:00:00Z', 'until' => null, 'maxOccurrences' => 16,
    'reason' => 'complete-saba-work',
]];
$sabaItem = $item($id(10), 'service_reminder', $sabaRules, ['serviceRef' => 'saba']);
$sabaPlan = classops_reminder_plan($snapshot([$sabaItem], [], 172800), $clockAt('2026-09-08T12:00:00Z'));
$assert($sabaPlan['ok'] === true && count($sabaPlan['intents']) >= 2, 'long-lived recurring Saba reminder plans bounded current occurrences');
$assert(($sabaPlan['intents'][0]['serviceRef'] ?? '') === 'saba', 'Saba is represented only as opaque service reminder metadata');
$assert(($sabaPlan['intents'][0]['dueAt'] ?? '') === '2026-09-08T16:30:00Z', 'recurring local 20:00 is converted from Tehran to UTC');

$taskRecurring = $item($id(11), 'task', $sabaRules);
$taskRecurringPlan = classops_reminder_plan($snapshot([$taskRecurring]), $clockAt('2026-09-08T12:00:00Z'));
$assert($taskRecurringPlan['ok'] === false && ($taskRecurringPlan['errors'][0]['code'] ?? '') === 'CLASSOPS_REMINDER_RECURRENCE_NOT_ALLOWED', 'recurrence fails closed outside service_reminder');
$sabaWrongType = $item($id(12), 'event', [[
    'ruleId' => 'saba-absolute', 'type' => 'absolute', 'at' => '2026-09-09T00:00:00Z', 'reason' => 'saba',
]], ['serviceRef' => 'saba']);
$sabaWrongPlan = classops_reminder_plan($snapshot([$sabaWrongType]), $clockAt('2026-09-08T12:00:00Z'));
$assert($sabaWrongPlan['ok'] === false && ($sabaWrongPlan['errors'][0]['code'] ?? '') === 'CLASSOPS_REMINDER_SABA_TYPE_REQUIRED', 'Saba requires service_reminder item type');

$sabaCredential = $sabaItem;
$sabaCredential['reminderPolicy']['password'] = 'fixture-secret';
$credentialPlan = classops_reminder_plan($snapshot([$sabaCredential]), $clockAt('2026-09-08T12:00:00Z'));
$assert($credentialPlan['ok'] === false && ($credentialPlan['errors'][0]['code'] ?? '') === 'CLASSOPS_REMINDER_CREDENTIAL_MATERIAL_FORBIDDEN', 'Saba/password material is rejected fail-closed');

$rawChatItem = $absoluteItem;
$rawChatItem['itemId'] = $id(13);
$rawChatItem['audience']['ref'] = 'telegram_chat_id_123456';
$rawChatPlan = classops_reminder_plan($snapshot([$rawChatItem]), $clockAt('2026-09-08T09:00:00Z'));
$assert($rawChatPlan['ok'] === false && ($rawChatPlan['errors'][0]['code'] ?? '') === 'CLASSOPS_REMINDER_TRANSPORT_ID_FORBIDDEN', 'raw Telegram/Bale chat identifiers are rejected from scheduler domain input');

$coordination = $absolutePlan['intents'][0]['coordination'] ?? [];
$assert(($coordination['requirement'] ?? '') === 'single_active_leader' && ($coordination['plannerSideEffects'] ?? '') === 'none', 'coordinator requirement is emitted only as interface metadata');

$plannerSource = (string) file_get_contents(__DIR__ . '/../public_html/api/classops_modules/scheduler/classops_reminder_planner.php');
$policySource = (string) file_get_contents(__DIR__ . '/../public_html/api/classops_modules/scheduler/classops_reminder_policy.php');
$forbiddenCalls = [
    'file_put_contents(', 'fopen(', 'flock(', 'curl_', 'fsockopen(', 'stream_socket_client(',
    'exec(', 'shell_exec(', 'system(', 'proc_open(', 'notifications_', 'bot_api', 'sendMessage', 'requests.',
];
$sourceHasSideEffect = false;
foreach ($forbiddenCalls as $needle) {
    if (str_contains($plannerSource, $needle) || str_contains($policySource, $needle)) {
        $sourceHasSideEffect = true;
        echo "INFO: forbidden side-effect token found: {$needle}\n";
    }
}
$assert(!$sourceHasSideEffect, 'planner/policy source contains zero send/write/network side-effect calls');

$unknown = $absoluteItem;
$unknown['unexpected'] = true;
$unknownPlan = classops_reminder_plan($snapshot([$unknown]), $clockAt('2026-09-08T09:00:00Z'));
$assert($unknownPlan['ok'] === false && $unknownPlan['intents'] === [] && ($unknownPlan['errors'][0]['code'] ?? '') === 'CLASSOPS_REMINDER_UNKNOWN_FIELD', 'unknown field fails closed with zero intents');
$badTime = $absoluteItem;
$badTime['reminderPolicy']['rules'][0]['at'] = '2026-09-08 10:00';
$badTimePlan = classops_reminder_plan($snapshot([$badTime]), $clockAt('2026-09-08T09:00:00Z'));
$assert($badTimePlan['ok'] === false && $badTimePlan['intents'] === [] && ($badTimePlan['errors'][0]['code'] ?? '') === 'CLASSOPS_REMINDER_INVALID_TIMESTAMP', 'non-canonical timestamp fails closed');

$draft = $absoluteItem;
$draft['itemId'] = $id(14);
$draft['status'] = 'draft';
$draftPlan = classops_reminder_plan($snapshot([$draft]), $clockAt('2026-09-08T09:00:00Z'));
$assert($draftPlan['ok'] === true && $draftPlan['intents'] === [], 'draft items produce no reminder intents');

$contractPath = __DIR__ . '/../contracts/candidates/classops-reminder-v1.json';
if (is_file($contractPath)) {
    $decoded = json_decode((string) file_get_contents($contractPath), true);
    $assert(is_array($decoded) && ($decoded['title'] ?? '') === 'ClassOps Reminder Planner v1', 'candidate JSON contract is parseable and version-labeled');
}

echo "ClassOps scheduler tests: {$checks} checks, {$failures} failures.\n";
exit($failures === 0 ? 0 : 1);
