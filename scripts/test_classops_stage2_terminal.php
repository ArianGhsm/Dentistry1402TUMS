<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/public_html/api/classops_stage2/notifications.php';

$item = [
    'id' => 'cop_' . str_repeat('a', 24),
    'revision' => 7,
    'cohortKey' => 'dentistry-1402',
    'type' => 'task',
    'title' => 'terminal fixture',
    'description' => '',
    'course' => null,
    'timing' => ['startsAt'=>null,'endsAt'=>null,'dueAt'=>null,'timezone'=>'Asia/Tehran'],
    'location' => '',
    'importance' => 'normal',
    'requireAck' => false,
    'status' => 'completed',
    'extensions' => [],
];

$plans = classops_stage2_delivery_plan_for_item($item, [], ['private_users','class_group']);
if (array_keys($plans) !== ['private_users','class_group']) {
    throw new RuntimeException('Terminal plan must preserve requested symbolic destination keys.');
}
foreach ($plans as $plan) {
    if (($plan['contractVersion'] ?? '') !== CLASSOPS_DELIVERY_CONTRACT_VERSION
        || ($plan['intents'] ?? null) !== []
        || ($plan['outcomes'] ?? null) !== []
        || ($plan['blockedReason'] ?? '') !== 'item_status_completed') {
        throw new RuntimeException('Terminal plan must be deterministic, blocked and intent-free.');
    }
}

$notifications = classops_stage2_ensure_notification_record($item, ['402999999'], true);
if ($notifications !== []) {
    throw new RuntimeException('Terminal revision must not create a new ClassOps notification.');
}

print("ClassOps Stage2 terminal side-effect guards passed\n");
