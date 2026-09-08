<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/notifications_store.php';
require_once __DIR__ . '/academic_term7.php';
require_once __DIR__ . '/classops_stage2_store.php';
require_once __DIR__ . '/classops_modules/domain_facade.php';
require_once __DIR__ . '/classops_modules/audience/auth_store_source.php';
require_once __DIR__ . '/classops_modules/ai/copilot.php';
require_once __DIR__ . '/classops_modules/exams/exam_ops.php';

const CLASSOPS_STAGE2_BINDING_VERSION = 'classops-stage2-binding-v1';
const CLASSOPS_STAGE2_EXTENSION_KEY = 'classops_stage2_v1';
const CLASSOPS_STAGE2_SURFACE_VERSION = 'classops-surface-v1';
const CLASSOPS_STAGE2_MAX_DESTINATIONS = 8;

function classops_stage2_bool_env(string $name): ?bool
{
    $raw = strtolower(trim((string) getenv($name)));
    if ($raw === '') return null;
    if (in_array($raw, ['1','true','yes','on'], true)) return true;
    if (in_array($raw, ['0','false','no','off'], true)) return false;
    return null;
}

function classops_stage2_ai_configured(): bool
{
    return trim((string) getenv('DENT_CLASSOPS_AI_AVALAI_API_KEY')) !== ''
        && trim((string) getenv('DENT_CLASSOPS_AI_MODEL')) !== '';
}

function classops_stage2_platform_state(string $platform): string
{
    $enabled = classops_stage2_bool_env($platform === 'telegram' ? 'DENT_CLASSOPS_TELEGRAM_ENABLED' : 'DENT_CLASSOPS_BALE_ENABLED');
    return $enabled === true ? 'available' : ($enabled === false ? 'unavailable' : 'unknown');
}

function classops_stage2_capabilities(): array
{
    return [
        'success' => true,
        'surfaceVersion' => CLASSOPS_STAGE2_SURFACE_VERSION,
        'foundation' => ['state'=>'available','contract'=>'classops-v1'],
        'audience' => ['state'=>'available','contract'=>'classops-audience-v1'],
        'deliveryPlanning' => ['state'=>'available','contract'=>'classops-delivery-v1'],
        'ai' => [
            'state' => classops_stage2_ai_configured() ? 'configured' : 'unconfigured',
            'provider' => 'avalai',
            'manualFallback' => true,
            'directMutation' => false,
            'directSend' => false,
        ],
        'tasksRequirements' => ['state'=>'available','contract'=>'classops-tasks-v1'],
        'examAck' => ['state'=>'available','contract'=>'classops-exam-ack-v1'],
        'scheduler' => ['state'=>'available','contract'=>'classops-reminder-v1','coordinator'=>'server-canonical'],
        'digest' => ['state'=>'available','contract'=>'classops-digest-v1'],
        'telegram' => ['state'=>classops_stage2_platform_state('telegram')],
        'bale' => ['state'=>classops_stage2_platform_state('bale')],
        'website' => ['state'=>'available','notifications'=>'canonical-existing-subsystem'],
        'saba' => ['state'=>'reminder-only','credentialsAccepted'=>false,'loginAutomation'=>false],
    ];
}

function classops_stage2_require_item_id($value): string
{
    $id = trim((string) $value);
    if (preg_match('/^cop_[a-f0-9]{16,64}$/D', $id) !== 1) {
        classops_domain_error('CLASSOPS_ITEM_ID_INVALID', 'شناسه آیتم ClassOps معتبر نیست.');
    }
    return $id;
}

function classops_stage2_student_number(array $user): string
{
    $student = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($student === '') classops_domain_error('CLASSOPS_CANONICAL_IDENTITY_REQUIRED', 'هویت canonical کاربر در دسترس نیست.', 403);
    return $student;
}

function classops_stage2_is_owner(array $user): bool
{
    return (string) ($user['role'] ?? 'student') === 'owner';
}

function classops_stage2_owner_scope(array $owner, string $cohortKey): array
{
    if (!classops_stage2_is_owner($owner)) {
        classops_domain_error('CLASSOPS_OWNER_REQUIRED', 'این عملیات فقط برای مالک سامانه مجاز است.', 403);
    }
    return ['scope'=>'owner','cohortKey'=>$cohortKey];
}

function classops_stage2_default_audience_spec(): array
{
    return [
        'version'=>'classops-audience-v1',
        'resolutionMode'=>'snapshot',
        'expression'=>['op'=>'whole_cohort'],
        'includeStudentNumbers'=>[],
        'excludeStudentNumbers'=>[],
    ];
}

function classops_stage2_resolve_audience(array $owner, string $cohortKey, $spec, ?string $expectedHash = null): array
{
    if (!is_array($spec) || array_is_list($spec)) {
        classops_domain_error('CLASSOPS_AUDIENCE_INVALID_SPEC', 'تعریف مخاطب باید object باشد.');
    }
    classops_api_validate_cohort($cohortKey);
    $source = new DentClassOpsAuthStoreAudienceSource();
    try {
        return classops_audience_resolve_from_source(
            $source,
            $spec,
            $cohortKey,
            classops_stage2_owner_scope($owner, $cohortKey),
            null,
            $expectedHash
        );
    } catch (DentClassOpsAudienceException $exception) {
        $status = property_exists($exception, 'httpStatus') ? (int) $exception->httpStatus : 422;
        classops_domain_error($exception->reasonCode ?? 'CLASSOPS_AUDIENCE_ERROR', $exception->getMessage(), $status);
    }
}

function classops_stage2_foundation_audience(array $resolution): array
{
    $spec = $resolution['normalizedSpec'] ?? [];
    $expr = $spec['expression'] ?? [];
    $recipients = $resolution['recipientStudentNumbers'] ?? [];
    if (($expr['op'] ?? '') === 'whole_cohort'
        && ($spec['includeStudentNumbers'] ?? []) === []
        && ($spec['excludeStudentNumbers'] ?? []) === []) {
        return ['version'=>'classops-audience-placeholder-v1','mode'=>'entire_cohort','refs'=>[]];
    }
    if (($expr['op'] ?? '') === 'students' && is_array($recipients) && count($recipients) <= 500) {
        return [
            'version'=>'classops-audience-placeholder-v1',
            'mode'=>count($recipients) === 1 ? 'single_student' : 'explicit_students',
            'refs'=>array_values($recipients),
        ];
    }
    return [
        'version'=>'classops-audience-placeholder-v1',
        'mode'=>'snapshot',
        'refs'=>['aud_' . substr((string) ($resolution['deterministicHash'] ?? ''), 0, 24)],
    ];
}

function classops_stage2_normalize_destinations($value): array
{
    if ($value === null || $value === []) return ['private_users'];
    if (!is_array($value) || !array_is_list($value) || count($value) > CLASSOPS_STAGE2_MAX_DESTINATIONS) {
        classops_domain_error('CLASSOPS_DESTINATIONS_INVALID', 'فهرست مقصدهای ارسال معتبر نیست.');
    }
    $allowed = ['private_users','class_group','information_channel'];
    $out = [];
    foreach ($value as $entry) {
        $name = trim((string) $entry);
        if (!in_array($name, $allowed, true)) classops_domain_error('CLASSOPS_DESTINATION_UNKNOWN', 'مقصد ارسال شناخته‌شده نیست.');
        $out[$name] = true;
    }
    if ($out === []) classops_domain_error('CLASSOPS_DESTINATION_REQUIRED', 'حداقل یک مقصد ارسال الزامی است.');
    return array_keys($out);
}

function classops_stage2_destination_registry(string $cohortKey): array
{
    $physical = static function (string $kind, string $platform, string $binding) use ($cohortKey): array {
        return ['kind'=>$kind,'cohortId'=>$cohortKey,'platform'=>$platform,'bindingRef'=>$binding];
    };
    return [
        'version'=>CLASSOPS_DESTINATION_REGISTRY_VERSION,
        'id'=>'registry.classops.' . str_replace('-', '_', $cohortKey),
        'cohortId'=>$cohortKey,
        'destinations'=>[
            'private.telegram'=>$physical('private_recipient','telegram','canonical.notification.private'),
            'private.bale'=>$physical('private_recipient','bale','canonical.notification.private'),
            'private_users'=>['kind'=>'logical','cohortId'=>$cohortKey,'routes'=>[
                ['alias'=>'private.telegram'],['alias'=>'private.bale'],
            ]],
            'group.telegram'=>$physical('class_group','telegram','class_group'),
            'group.bale'=>$physical('class_group','bale','class_group'),
            'class_group'=>['kind'=>'logical','cohortId'=>$cohortKey,'routes'=>[
                ['alias'=>'group.telegram'],['alias'=>'group.bale'],
            ]],
            'channel.telegram'=>$physical('channel','telegram','information_channel'),
            'channel.bale'=>$physical('channel','bale','information_channel'),
            'information_channel'=>['kind'=>'logical','cohortId'=>$cohortKey,'routes'=>[
                ['alias'=>'channel.telegram'],['alias'=>'channel.bale'],
            ]],
        ],
    ];
}

function classops_stage2_capability_overrides(): array
{
    $overrides = [];
    $baleChannel = classops_stage2_bool_env('DENT_CLASSOPS_BALE_CHANNEL_SUPPORTED');
    if ($baleChannel !== null) {
        $overrides['bale']['channel_delivery'] = [
            'state'=>$baleChannel ? 'supported' : 'unsupported',
            'evidence'=>'runtime-explicit-config',
        ];
    }
    return $overrides;
}

function classops_stage2_delivery_policy(string $destination): array
{
    $capability = match ($destination) {
        'private_users' => 'private_recipient',
        'class_group' => 'group_delivery',
        'information_channel' => 'channel_delivery',
        default => 'text_message',
    };
    return [
        'requiredCapabilities'=>['text_message',$capability],
        'platformMode'=>'independent',
        'allowFallback'=>false,
    ];
}

function classops_stage2_item_snapshot_hash(array $item): string
{
    return hash('sha256', classops_canonical_json($item));
}

function classops_stage2_delivery_plan_for_item(array $item, array $resolution, array $destinations, string $purpose = 'initial', ?string $scheduledAt = null, array $previousIntents = []): array
{
    $scheduledAt = $scheduledAt ?? gmdate('Y-m-d\TH:i:s\Z');
    $audHash = (string) ($resolution['deterministicHash'] ?? '');
    $itemRef = [
        'id'=>(string) $item['id'],
        'revision'=>(int) $item['revision'],
        'snapshotHash'=>classops_stage2_item_snapshot_hash($item),
        'cohortId'=>(string) $item['cohortKey'],
        'status'=>(string) $item['status'],
    ];
    $audienceRef = [
        'contractVersion'=>'classops-audience-v1',
        'ref'=>'audience.' . substr($audHash, 0, 24),
        'version'=>'classops-audience-snapshot-v1',
        'cohortId'=>(string) $item['cohortKey'],
        'hash'=>$audHash,
    ];
    $availability = [
        'telegram'=>classops_stage2_platform_state('telegram'),
        'bale'=>classops_stage2_platform_state('bale'),
    ];
    $registry = classops_stage2_destination_registry((string) $item['cohortKey']);
    $plans = [];
    foreach ($destinations as $destination) {
        $occurrence = [
            'key'=>'occ_' . hash('sha256', implode('|', [(string)$item['id'],(string)$item['revision'],$destination,$purpose,$scheduledAt])),
            'purpose'=>$purpose,
            'scheduledAt'=>$scheduledAt,
        ];
        try {
            $plans[$destination] = classops_delivery_plan(
                $registry,
                $destination,
                $itemRef,
                $audienceRef,
                $occurrence,
                classops_stage2_delivery_policy($destination),
                $availability,
                $previousIntents,
                classops_stage2_capability_overrides()
            );
        } catch (DentClassOpsDeliveryException $exception) {
            classops_domain_error('CLASSOPS_DELIVERY_PLAN_FAILED', 'برنامه ارسال قابل محاسبه نیست: ' . $exception->getMessage(), 422);
        }
    }
    return $plans;
}

function classops_stage2_message_for_item(array $item, string $prefix = ''): array
{
    $typeLabels = [
        'announcement'=>'اطلاعیه','event'=>'رویداد','class_change'=>'تغییر برنامه','deadline'=>'ددلاین',
        'task'=>'تسک','requirement'=>'الزام','exam'=>'آزمون','critical_notice'=>'اطلاعیه مهم','service_reminder'=>'یادآوری خدمت',
    ];
    $title = trim(($prefix !== '' ? $prefix . ' — ' : '') . ($typeLabels[$item['type']] ?? 'ClassOps') . ': ' . (string) $item['title']);
    $lines = [];
    $description = trim((string) ($item['description'] ?? ''));
    if ($description !== '') $lines[] = $description;
    $timing = is_array($item['timing'] ?? null) ? $item['timing'] : [];
    foreach ([['startsAt','شروع'],['endsAt','پایان'],['dueAt','مهلت']] as [$key,$label]) {
        if (!empty($timing[$key])) $lines[] = $label . ': ' . (string) $timing[$key];
    }
    if (trim((string) ($item['location'] ?? '')) !== '') $lines[] = 'مکان: ' . (string) $item['location'];
    if (($item['type'] ?? '') === 'service_reminder') {
        $stage2 = $item['extensions'][CLASSOPS_STAGE2_EXTENSION_KEY] ?? [];
        if (($stage2['serviceRef'] ?? null) === 'saba') $lines[] = 'این فقط یادآوری صباست؛ وضعیت ورود/انجام در صبا تأیید نمی‌شود.';
    }
    return [
        'title'=>dent_clean_text($title, 180),
        'body'=>dent_clean_text(implode("\n", $lines), 4000),
        'ctaHref'=>'/classops/',
        'ctaLabel'=>'مشاهده در ClassOps',
    ];
}

function classops_stage2_notification_source_key(array $item, string $studentNumber = '', string $occurrence = 'initial'): string
{
    $parts = ['item',(string)$item['id'],'r'.(int)$item['revision'],$occurrence];
    if ($studentNumber !== '') $parts[] = 'student-' . hash('sha256', $studentNumber);
    return implode(':', $parts);
}

function classops_stage2_remove_prior_notifications(string $itemId, int $revision): void
{
    notifications_with_store_lock(static function (array &$store) use ($itemId, $revision): void {
        foreach (($store['notifications'] ?? []) as $id => $record) {
            if (!is_array($record) || ($record['source'] ?? '') !== 'classops') continue;
            $sourceKey = (string) ($record['sourceKey'] ?? '');
            if (!str_starts_with($sourceKey, 'item:' . $itemId . ':r')) continue;
            if (preg_match('/^item:' . preg_quote($itemId, '/') . ':r(\d+):/', $sourceKey, $match) !== 1) continue;
            if ((int) $match[1] < $revision) unset($store['notifications'][$id]);
        }
    });
}

function classops_stage2_ensure_notification_record(array $item, array $recipientNumbers, bool $allowPrivateBotPush, string $occurrence = 'initial', ?string $publishAt = null): array
{
    $recipientNumbers = array_values(array_unique(array_map('strval', $recipientNumbers)));
    sort($recipientNumbers, SORT_STRING);
    $message = classops_stage2_message_for_item($item);
    $cohort = (string) $item['cohortKey'];
    $wholeCohort = false;
    $userStore = dent_load_user_store();
    $cohortMembers = [];
    foreach (($userStore['users'] ?? []) as $key => $user) {
        if (!is_array($user) || dent_user_cohort_key($user) !== $cohort) continue;
        $student = dent_normalize_student_number((string) ($user['studentNumber'] ?? $key));
        if ($student !== '') $cohortMembers[] = $student;
    }
    sort($cohortMembers, SORT_STRING);
    $wholeCohort = $recipientNumbers === $cohortMembers && $recipientNumbers !== [];
    $publishAt = $publishAt === null ? dent_iso_now() : notifications_normalize_iso_datetime($publishAt);
    if ($publishAt === '') classops_domain_error('CLASSOPS_NOTIFICATION_TIME_INVALID', 'زمان اعلان canonical معتبر نیست.');
    $scheduled = notifications_timestamp($publishAt) > time();

    return notifications_with_store_lock(static function (array &$store) use ($item, $recipientNumbers, $allowPrivateBotPush, $occurrence, $message, $cohort, $wholeCohort, $publishAt, $scheduled): array {
        $created = [];
        $targets = $wholeCohort ? [['kind'=>'cohort','student'=>'']] : array_map(static fn(string $s): array => ['kind'=>'user','student'=>$s], $recipientNumbers);
        foreach ($targets as $targetSpec) {
            $student = (string) $targetSpec['student'];
            $sourceKey = classops_stage2_notification_source_key($item, $student, $occurrence);
            $existing = null;
            foreach (($store['notifications'] ?? []) as $candidate) {
                if (is_array($candidate) && ($candidate['source'] ?? '') === 'classops' && ($candidate['sourceKey'] ?? '') === $sourceKey) {
                    $existing = $candidate; break;
                }
            }
            if (is_array($existing)) { $created[] = $existing; continue; }
            $recipients = $targetSpec['kind'] === 'cohort'
                ? notifications_snapshot_recipients_for_target(DENT_NOTIFICATION_TARGET_COHORT, $cohort)
                : notifications_snapshot_recipients_for_target(DENT_NOTIFICATION_TARGET_USER, '', $student);
            if ($recipients === []) continue;
            $id = notifications_generate_id();
            $record = notifications_normalize_record($id, [
                'id'=>$id,
                'kind'=>DENT_NOTIFICATION_KIND_ANNOUNCEMENT,
                'title'=>$message['title'],
                'body'=>$message['body'],
                'tone'=>(($item['importance'] ?? '') === 'critical' ? 'warning' : 'accent'),
                'target'=>$targetSpec['kind'] === 'cohort' ? DENT_NOTIFICATION_TARGET_COHORT : DENT_NOTIFICATION_TARGET_USER,
                'cohortKey'=>$targetSpec['kind'] === 'cohort' ? $cohort : '',
                'targetStudentNumber'=>$student,
                'source'=>'classops',
                'sourceKey'=>$sourceKey,
                'ctaHref'=>$message['ctaHref'],
                'ctaLabel'=>$message['ctaLabel'],
                'createdAt'=>dent_iso_now(),
                'publishAt'=>$publishAt,
                'releasedAt'=>$scheduled ? '' : $publishAt,
                'status'=>$scheduled ? DENT_NOTIFICATION_STATUS_SCHEDULED : DENT_NOTIFICATION_STATUS_ACTIVE,
                'createdByStudentNumber'=>'',
                'createdByName'=>'',
                'createdByRole'=>'سیستم',
                'meta'=>[
                    'disablePush'=>!$allowPrivateBotPush,
                    'eventId'=>'classops-' . substr(hash('sha256', $sourceKey), 0, 32),
                    'important'=>in_array((string) ($item['importance'] ?? ''), ['important','critical'], true),
                ],
                'recipients'=>$recipients,
                'sendSms'=>false,
                'smsStatus'=>DENT_NOTIFICATION_SMS_STATUS_NONE,
            ]);
            if ($record !== null) {
                $store['notifications'][$record['id']] = $record;
                $created[] = $record;
            }
        }
        return $created;
    });
}

function classops_stage2_normalize_reminder_policy($policy, string $itemType): ?array
{
    if ($policy === null || $policy === []) {
        if ($itemType === 'exam') {
            return [
                'version'=>'classops-reminder-v1','timezone'=>'Asia/Tehran',
                'catchUp'=>['mode'=>'skip','maxAgeSeconds'=>0],
                'rules'=>[
                    ['ruleId'=>'exam-t3','type'=>'relative','anchor'=>'startsAt','offsetSeconds'=>-259200,'reason'=>'exam-t3'],
                    ['ruleId'=>'exam-t1','type'=>'relative','anchor'=>'startsAt','offsetSeconds'=>-86400,'reason'=>'exam-t1'],
                    ['ruleId'=>'exam-night','type'=>'daypart','anchor'=>'startsAt','dayOffset'=>-1,'daypart'=>'night','reason'=>'exam-night-before'],
                    ['ruleId'=>'exam-morning','type'=>'daypart','anchor'=>'startsAt','dayOffset'=>0,'daypart'=>'morning','reason'=>'exam-morning-of'],
                ],
            ];
        }
        return null;
    }
    if (!is_array($policy) || array_is_list($policy)) classops_domain_error('CLASSOPS_REMINDER_POLICY_INVALID', 'سیاست یادآوری معتبر نیست.');
    // Reuse the pure planner as the canonical validator by embedding a harmless
    // future item. It has zero write/network side effects.
    $probe = [
        'contractVersion'=>'classops-reminder-v1','planningHorizonSeconds'=>86400,'maxOccurrences'=>1,
        'items'=>[["."
            'itemId'=>'cop_' . str_repeat('a', 24),'revision'=>1,'itemType'=>$itemType,'status'=>'active',
            'timing'=>['startsAt'=>'2099-01-02T00:00:00Z','dueAt'=>'2099-01-02T00:00:00Z'],
            'audience'=>['ref'=>'audience_probe','hash'=>hash('sha256','probe')],
            'deliveryPolicyRef'=>'delivery_policy_probe','reminderPolicy'=>$policy,
            'serviceRef'=>$itemType === 'service_reminder' ? 'saba' : null,
        ]], 'knownOccurrences'=>[],
    ];
    $probeResult = classops_reminder_plan($probe, static fn(): DateTimeImmutable => new DateTimeImmutable('2099-01-01T00:00:00Z', new DateTimeZone('UTC')));
    if (($probeResult['ok'] ?? false) !== true) {
        $code = (string) ($probeResult['errors'][0]['code'] ?? 'CLASSOPS_REMINDER_POLICY_INVALID');
        classops_domain_error($code, 'سیاست یادآوری معتبر نیست.');
    }
    return $policy;
}

function classops_stage2_binding_extension(array $resolution, array $destinations, ?array $reminderPolicy, ?string $serviceRef): array
{
    if ($serviceRef !== null) {
        $serviceRef = strtolower(trim($serviceRef));
        if ($serviceRef !== 'saba') classops_domain_error('CLASSOPS_SERVICE_REF_INVALID', 'خدمت یادآوری شناخته‌شده نیست.');
    }
    return [
        'contractVersion'=>CLASSOPS_STAGE2_BINDING_VERSION,
        'audienceSpec'=>$resolution['normalizedSpec'],
        'audienceResolutionHash'=>(string) $resolution['deterministicHash'],
        'destinations'=>$destinations,
        'deliveryPolicy'=>['platformMode'=>'independent','allowFallback'=>false],
        'reminderPolicy'=>$reminderPolicy,
        'serviceRef'=>$serviceRef,
        'confirmedAt'=>gmdate('Y-m-d\TH:i:s\Z'),
    ];
}

function classops_stage2_validate_binding_extension(array $value, string $itemType): array
{
    classops_assert_known_keys($value, ['contractVersion','audienceSpec','audienceResolutionHash','destinations','deliveryPolicy','reminderPolicy','serviceRef','confirmedAt']);
    if (($value['contractVersion'] ?? '') !== CLASSOPS_STAGE2_BINDING_VERSION) classops_domain_error('CLASSOPS_STAGE2_BINDING_VERSION_INVALID','Stage2 binding version is invalid.');
    if (!is_array($value['audienceSpec'] ?? null) || ($value['audienceSpec']['version'] ?? '') !== 'classops-audience-v1') classops_domain_error('CLASSOPS_STAGE2_AUDIENCE_INVALID','Stage2 audience binding is invalid.');
    if (preg_match('/^[a-f0-9]{64}$/D', (string) ($value['audienceResolutionHash'] ?? '')) !== 1) classops_domain_error('CLASSOPS_STAGE2_AUDIENCE_HASH_INVALID','Stage2 audience hash is invalid.');
    $destinations = classops_stage2_normalize_destinations($value['destinations'] ?? null);
    $serviceRef = $value['serviceRef'] ?? null;
    if ($itemType === 'service_reminder' && $serviceRef !== 'saba') classops_domain_error('CLASSOPS_REMINDER_SERVICE_REF_REQUIRED','service_reminder Stage2 requires supported serviceRef.');
    if ($itemType !== 'service_reminder' && $serviceRef !== null) classops_domain_error('CLASSOPS_SERVICE_REF_FORBIDDEN','serviceRef is only valid for service_reminder.');
    return $value + ['destinations'=>$destinations];
}

function classops_stage2_preview(array $owner, array $input): array
{
    classops_assert_known_keys($input, ['item','audienceSpec','destinations','reminderPolicy','serviceRef','expectedAudienceHash']);
    $itemInput = $input['item'] ?? null;
    if (!is_array($itemInput) || array_is_list($itemInput)) classops_domain_error('CLASSOPS_INVALID_OBJECT', 'item باید object باشد.');
    $normalized = classops_domain_store_normalize_create($itemInput);
    $cohort = (string) $normalized['cohortKey'];
    classops_api_validate_cohort($cohort);
    $spec = is_array($input['audienceSpec'] ?? null) ? $input['audienceSpec'] : classops_stage2_default_audience_spec();
    $resolution = classops_stage2_resolve_audience($owner, $cohort, $spec, trim((string) ($input['expectedAudienceHash'] ?? '')) ?: null);
    $destinations = classops_stage2_normalize_destinations($input['destinations'] ?? null);
    $reminder = classops_stage2_normalize_reminder_policy($input['reminderPolicy'] ?? null, (string) $normalized['type']);
    $serviceRef = isset($input['serviceRef']) ? strtolower(trim((string) $input['serviceRef'])) : null;
    if (($normalized['type'] ?? '') === 'service_reminder' && $serviceRef === null) $serviceRef = 'saba';
    $extension = classops_stage2_binding_extension($resolution, $destinations, $reminder, $serviceRef);
    $draftItem = [
        'id'=>'cop_' . str_repeat('b', 24), 'revision'=>1,'contractVersion'=>'classops-v1',
    ] + array_replace($normalized, [
        'audienceSpec'=>classops_stage2_foundation_audience($resolution),
        'extensions'=>array_replace($normalized['extensions'] ?? [], [CLASSOPS_STAGE2_EXTENSION_KEY=>$extension]),
        'status'=>'scheduled',
    ]) + ['createdBy'=>['kind'=>'canonical_user','ref'=>'usr_' . str_repeat('0',24),'role'=>'owner'],'createdAt'=>gmdate('Y-m-d\TH:i:s\Z'),'updatedAt'=>gmdate('Y-m-d\TH:i:s\Z')];
    $delivery = classops_stage2_delivery_plan_for_item($draftItem, $resolution, $destinations);
    return [
        'success'=>true,
        'item'=>$normalized,
        'audience'=>classops_audience_preview($resolution, null, true),
        'audienceResolution'=>$resolution,
        'destinations'=>$delivery,
        'reminderPolicy'=>$reminder,
        'serviceRef'=>$serviceRef,
        'confirmation'=>[
            'required'=>true,
            'audienceHash'=>(string) $resolution['deterministicHash'],
            'mutationAuthority'=>'owner-confirm-only',
            'aiDirectSend'=>false,
        ],
    ];
}

function classops_stage2_ai_create(array $owner, string $ownerText, ?string $forwardedText, string $cohortKey): array
{
    if (!classops_stage2_ai_configured()) classops_domain_error('CLASSOPS_AI_NOT_CONFIGURED', 'هوش مصنوعی ClassOps تنظیم نشده؛ ورود دستی همچنان فعال است.', 503);
    classops_api_validate_cohort($cohortKey);
    $copilot = new DentClassOpsAiCopilot(DentClassOpsAiAvalAiClient::fromEnvironment());
    try {
        return ['success'=>true] + $copilot->createDraft($ownerText, $forwardedText, ['cohortKey'=>$cohortKey]);
    } catch (DentClassOpsAiException $exception) {
        classops_domain_error($exception->reasonCode, 'سرویس AI ClassOps در دسترس نیست یا خروجی معتبر نبود.', $exception->httpStatus);
    }
}

function classops_stage2_ai_edit(array $owner, array $priorDraft, string $editText): array
{
    if (!classops_stage2_ai_configured()) classops_domain_error('CLASSOPS_AI_NOT_CONFIGURED', 'هوش مصنوعی ClassOps تنظیم نشده؛ ویرایش دستی همچنان فعال است.', 503);
    $copilot = new DentClassOpsAiCopilot(DentClassOpsAiAvalAiClient::fromEnvironment());
    try {
        return ['success'=>true] + $copilot->editDraft($priorDraft, $editText);
    } catch (DentClassOpsAiException $exception) {
        classops_domain_error($exception->reasonCode, 'سرویس AI ClassOps در دسترس نیست یا خروجی معتبر نبود.', $exception->httpStatus);
    }
}

function classops_stage2_final_status_for_item(array $item): string
{
    $timing = $item['timing'] ?? [];
    $future = false;
    foreach (['startsAt','dueAt'] as $key) {
        $ts = strtotime((string) ($timing[$key] ?? '')) ?: 0;
        if ($ts > time()) { $future = true; break; }
    }
    return $future ? 'scheduled' : 'active';
}

function classops_stage2_confirm(array $owner, array $payload): array
{
    classops_assert_known_keys($payload, ['mode','item','id','expectedRevision','audienceSpec','expectedAudienceHash','destinations','reminderPolicy','serviceRef','idempotencyKey','reason']);
    $mode = trim((string) ($payload['mode'] ?? 'create'));
    if (!in_array($mode, ['create','update'], true)) classops_domain_error('CLASSOPS_CONFIRM_MODE_INVALID','حالت تأیید معتبر نیست.');
    $itemInput = $payload['item'] ?? null;
    if (!is_array($itemInput) || array_is_list($itemInput)) classops_domain_error('CLASSOPS_INVALID_OBJECT','item باید object باشد.');
    $idempotencyKey = trim((string) ($payload['idempotencyKey'] ?? ''));
    $reason = trim((string) ($payload['reason'] ?? 'owner confirmed'));
    if ($mode === 'create') {
        $normalized = classops_domain_store_normalize_create($itemInput);
        $cohort = (string) $normalized['cohortKey'];
    } else {
        $id = classops_stage2_require_item_id($payload['id'] ?? '');
        $current = classops_get_item($id);
        $patch = classops_domain_store_normalize_patch($current, $itemInput);
        $cohort = (string) ($patch['cohortKey'] ?? $current['cohortKey']);
        $normalized = array_replace($current, $patch);
    }
    classops_api_validate_cohort($cohort);
    $spec = is_array($payload['audienceSpec'] ?? null) ? $payload['audienceSpec'] : classops_stage2_default_audience_spec();
    $expectedHash = trim((string) ($payload['expectedAudienceHash'] ?? ''));
    if ($expectedHash === '') classops_domain_error('CLASSOPS_AUDIENCE_CONFIRMATION_HASH_REQUIRED','برای commit باید hash همان preview ارسال شود.', 409);
    $resolution = classops_stage2_resolve_audience($owner, $cohort, $spec, $expectedHash);
    $destinations = classops_stage2_normalize_destinations($payload['destinations'] ?? null);
    $reminder = classops_stage2_normalize_reminder_policy($payload['reminderPolicy'] ?? null, (string) $normalized['type']);
    $serviceRef = isset($payload['serviceRef']) ? strtolower(trim((string) $payload['serviceRef'])) : null;
    if (($normalized['type'] ?? '') === 'service_reminder' && $serviceRef === null) $serviceRef = 'saba';
    $binding = classops_stage2_binding_extension($resolution, $destinations, $reminder, $serviceRef);
    $foundationAudience = classops_stage2_foundation_audience($resolution);

    if ($mode === 'create') {
        $createInput = $normalized;
        $createInput['status'] = 'draft';
        $createInput['audienceSpec'] = $foundationAudience;
        $createInput['extensions'] = array_replace($createInput['extensions'] ?? [], [CLASSOPS_STAGE2_EXTENSION_KEY=>$binding]);
        $created = classops_domain_store_create_item($createInput, $owner, $idempotencyKey . ':draft', $reason);
        $item = $created['item'];
        $finalStatus = classops_stage2_final_status_for_item($item);
        $committed = classops_domain_store_update_item(
            (string) $item['id'], (int) $item['revision'],
            ['status'=>$finalStatus], $owner, $idempotencyKey . ':confirm', $reason
        );
        $item = $committed['item'];
    } else {
        $current = classops_get_item(classops_stage2_require_item_id($payload['id'] ?? ''));
        $expectedRevision = (int) ($payload['expectedRevision'] ?? 0);
        if ($expectedRevision < 1) classops_domain_error('CLASSOPS_EXPECTED_REVISION_REQUIRED','expectedRevision معتبر الزامی است.');
        $patch = classops_domain_store_normalize_patch($current, $itemInput);
        $nextExtensions = array_replace($current['extensions'] ?? [], $patch['extensions'] ?? [], [CLASSOPS_STAGE2_EXTENSION_KEY=>$binding]);
        $patch['extensions'] = $nextExtensions;
        $patch['audienceSpec'] = $foundationAudience;
        if (!array_key_exists('status', $patch) && ($current['status'] ?? '') === 'draft') $patch['status'] = classops_stage2_final_status_for_item(array_replace($current, $patch));
        $committed = classops_domain_store_update_item((string)$current['id'], $expectedRevision, $patch, $owner, $idempotencyKey, $reason);
        $item = $committed['item'];
        classops_stage2_supersede_item_deliveries((string)$item['id'], (int)$item['revision'], 'newer_revision');
    }

    classops_stage2_save_audience($item, $resolution['normalizedSpec'], $resolution);
    classops_stage2_ensure_task_states($item, $resolution['recipientStudentNumbers']);
    classops_stage2_remove_prior_notifications((string)$item['id'], (int)$item['revision']);
    $allowPrivate = in_array('private_users', $destinations, true);
    $notificationRecords = classops_stage2_ensure_notification_record($item, $resolution['recipientStudentNumbers'], $allowPrivate);
    $plans = classops_stage2_delivery_plan_for_item($item, $resolution, $destinations);
    $direct = [];
    foreach ($plans as $destination => $plan) {
        if ($destination === 'private_users') continue; // canonical notification workers own private bot delivery.
        foreach (($plan['intents'] ?? []) as $intent) $direct[] = $intent;
    }
    classops_stage2_store_delivery_intents($direct, classops_stage2_message_for_item($item));
    return [
        'success'=>true,'item'=>$item,
        'audience'=>classops_audience_preview($resolution, null, false),
        'notificationCount'=>count($notificationRecords),
        'directDeliveryIntentCount'=>count($direct),
        'deliveryPreview'=>$plans,
    ];
}

function classops_stage2_item_audience_for_student(array $item, string $studentNumber): ?array
{
    $record = classops_stage2_get_audience((string) $item['id'], (int) $item['revision']);
    if (!is_array($record)) return null;
    $recipients = $record['snapshot']['recipientStudentNumbers'] ?? [];
    return in_array($studentNumber, $recipients, true) ? $record : null;
}

function classops_stage2_student_item_projection(array $item, array $user): array
{
    $student = classops_stage2_student_number($user);
    if (dent_user_cohort_key($user) !== (string) ($item['cohortKey'] ?? '')) classops_domain_error('CLASSOPS_ITEM_NOT_VISIBLE','این آیتم برای حساب شما قابل مشاهده نیست.',403);
    $audience = classops_stage2_item_audience_for_student($item, $student);
    if ($audience === null) classops_domain_error('CLASSOPS_ITEM_NOT_VISIBLE','این آیتم برای حساب شما قابل مشاهده نیست.',403);
    $projection = [
        'id'=>$item['id'],'revision'=>$item['revision'],'type'=>$item['type'],'title'=>$item['title'],
        'description'=>$item['description'],'course'=>$item['course'],'timing'=>$item['timing'],'location'=>$item['location'],
        'importance'=>$item['importance'],'status'=>$item['status'],'requireAck'=>$item['requireAck'],
    ];
    if (in_array((string) $item['type'], ['task','requirement'], true)) {
        $state = classops_stage2_get_task_state($item, $student, true);
        $due = is_array($item['timing'] ?? null) ? ($item['timing']['dueAt'] ?? null) : null;
        $projection['task'] = $state === null ? null : classops_task_student_projection($state, $student, is_string($due) ? $due : null, gmdate('Y-m-d\TH:i:s\Z'));
    }
    if (($item['type'] ?? '') === 'exam') {
        try { $projection['exam'] = classops_exam_projection($item); } catch (Throwable $e) { $projection['exam'] = null; }
    }
    if (($item['type'] ?? '') === 'critical_notice' && !empty($item['requireAck'])) {
        $projection['ack'] = [
            'acked'=>classops_ack_is_satisfied(classops_stage2_ack_state(), $item, $student),
            'revision'=>(int) $item['revision'],
        ];
    }
    if (($item['type'] ?? '') === 'service_reminder') {
        $projection['service']=['serviceRef'=>$item['extensions'][CLASSOPS_STAGE2_EXTENSION_KEY]['serviceRef'] ?? null,'externallyVerified'=>false];
    }
    return $projection;
}

function classops_stage2_student_list(array $user, array $filters = []): array
{
    $student = classops_stage2_student_number($user);
    $cohort = dent_user_cohort_key($user);
    if ($cohort === '') classops_domain_error('CLASSOPS_CANONICAL_COHORT_REQUIRED','ورودی canonical کاربر مشخص نیست.',403);
    $store = classops_read_store();
    $items = [];
    foreach (($store['items'] ?? []) as $item) {
        if (!is_array($item) || ($item['cohortKey'] ?? '') !== $cohort || ($item['status'] ?? '') === 'archived') continue;
        if (isset($filters['type']) && $filters['type'] !== '' && ($item['type'] ?? '') !== $filters['type']) continue;
        if (classops_stage2_item_audience_for_student($item, $student) === null) continue;
        $items[] = classops_stage2_student_item_projection($item, $user);
    }
    usort($items, static fn(array $a,array $b): int => strcmp((string)$b['id'],(string)$a['id']));
    return ['count'=>count($items),'items'=>array_slice($items,0,100)];
}

function classops_stage2_student_task_transition(array $user, array $payload): array
{
    classops_assert_known_keys($payload, ['id','expectedStateRevision','target','commandId','reason']);
    $item = classops_get_item(classops_stage2_require_item_id($payload['id'] ?? ''));
    if (!in_array((string) $item['type'], ['task','requirement'], true)) classops_domain_error('CLASSOPS_TASK_TYPE_REQUIRED','این آیتم task/requirement نیست.');
    $student = classops_stage2_student_number($user);
    if (classops_stage2_item_audience_for_student($item, $student) === null) classops_domain_error('CLASSOPS_ITEM_NOT_VISIBLE','این آیتم برای حساب شما قابل مشاهده نیست.',403);
    $target = trim((string) ($payload['target'] ?? ''));
    // Students can submit/re-submit/mark complete. needs_revision/waive/reopen are owner decisions.
    if (!in_array($target, ['submitted','completed'], true)) classops_domain_error('CLASSOPS_TASK_STUDENT_TRANSITION_FORBIDDEN','این تغییر وضعیت برای دانشجو مجاز نیست.',403);
    return classops_stage2_transition_task(
        $item,$student,(int)($payload['expectedStateRevision']??0),$target,
        trim((string)($payload['commandId']??'')),
        'student:' . hash('sha256',$student),
        trim((string)($payload['reason']??''))
    );
}

function classops_stage2_owner_task_transition(array $owner, array $payload): array
{
    classops_assert_known_keys($payload, ['id','studentNumber','expectedStateRevision','target','commandId','reason']);
    $item = classops_get_item(classops_stage2_require_item_id($payload['id'] ?? ''));
    if (!in_array((string) $item['type'], ['task','requirement'], true)) classops_domain_error('CLASSOPS_TASK_TYPE_REQUIRED','این آیتم task/requirement نیست.');
    $student = dent_normalize_student_number((string) ($payload['studentNumber'] ?? ''));
    if ($student === '' || classops_stage2_item_audience_for_student($item, $student) === null) classops_domain_error('CLASSOPS_TASK_STUDENT_NOT_ELIGIBLE','دانشجو عضو audience این revision نیست.',403);
    $actor = classops_actor($owner);
    return classops_stage2_transition_task($item,$student,(int)($payload['expectedStateRevision']??0),trim((string)($payload['target']??'')),trim((string)($payload['commandId']??'')),(string)$actor['ref'],trim((string)($payload['reason']??'')));
}

function classops_stage2_student_ack(array $user, array $payload): array
{
    classops_assert_known_keys($payload, ['id','expectedRevision','idempotencyKey']);
    $item = classops_get_item(classops_stage2_require_item_id($payload['id'] ?? ''));
    $student = classops_stage2_student_number($user);
    $aud = classops_stage2_item_audience_for_student($item, $student);
    if ($aud === null) classops_domain_error('CLASSOPS_ACK_NOT_ELIGIBLE','این اطلاعیه برای حساب شما نیست.',403);
    if ((int)($payload['expectedRevision']??0) !== (int)$item['revision']) classops_domain_error('CLASSOPS_ACK_STALE_REVISION','نسخه اطلاعیه تغییر کرده است.',409);
    return classops_stage2_record_ack($item,$student,(string)$aud['resolutionHash'],trim((string)($payload['idempotencyKey']??'')));
}

function classops_stage2_owner_ack_stats(array $item): array
{
    if (($item['type'] ?? '') !== 'critical_notice' || empty($item['requireAck'])) classops_domain_error('CLASSOPS_ACK_NOT_REQUIRED','این آیتم ACK ندارد.');
    $aud = classops_stage2_get_audience((string)$item['id'],(int)$item['revision']);
    if (!is_array($aud)) classops_domain_error('CLASSOPS_AUDIENCE_SNAPSHOT_MISSING','snapshot مخاطبان این revision در دسترس نیست.',503);
    return classops_ack_owner_stats(classops_stage2_ack_state(),$item,$aud['snapshot']['recipientStudentNumbers'] ?? []);
}

function classops_stage2_digest_record_from_item(array $item, array $viewer, ?array $taskState = null): array
{
    $type=(string)$item['type'];
    $source = match($type){'task'=>'task','requirement'=>'requirement','exam'=>'exam','critical_notice'=>'ack','service_reminder'=>'service',default=>'classops'};
    $status = match((string)$item['status']){'cancelled'=>'cancelled','completed','archived'=>'completed','draft','scheduled'=>'pending',default=>'active'};
    $timing=is_array($item['timing']??null)?$item['timing']:[];
    $taskProjected=null;
    if ($taskState!==null) {
        $taskProjected = (string)($taskState['state']??'pending');
        if (in_array($taskProjected,['submitted','needs_revision','waived'],true)) $taskProjected='pending';
    }
    $student = $viewer['scope']==='student' ? ($viewer['studentNumber']??null) : null;
    $ackState='not_required';
    if ($type==='critical_notice' && !empty($item['requireAck'])) {
        $ackState=$student!==null && classops_ack_is_satisfied(classops_stage2_ack_state(),$item,(string)$student)?'acked':'pending';
    }
    return [
        'recordVersion'=>'classops-digest-record-v1','entityRef'=>(string)$item['id'],'revision'=>(int)$item['revision'],'cohortKey'=>(string)$item['cohortKey'],
        'source'=>$source,'itemType'=>$type,'title'=>(string)$item['title'],'description'=>(string)$item['description'],'course'=>$item['course'],'location'=>(string)$item['location'],
        'importance'=>(string)$item['importance'],'status'=>$status,'scheduleRef'=>null,
        'timing'=>[
            'allDay'=>false,'localDate'=>null,
            'startsAtUtc'=>$timing['startsAt']??null,'endsAtUtc'=>$timing['endsAt']??null,'dueAtUtc'=>$timing['dueAt']??null,'timezone'=>'Asia/Tehran',
        ],
        'visibility'=>['studentAllowed'=>true,'ownerAllowed'=>true,'subject'=>null],
        'state'=>['taskState'=>$taskProjected,'ackState'=>$ackState],
        'change'=>['kind'=>$status==='cancelled'?'cancelled':'none','changedAtUtc'=>$status==='cancelled'?(string)$item['updatedAt']:null],
        'supersedesRef'=>null,
    ];
}

function classops_stage2_term7_digest_record(array $user, string $nowUtc): array
{
    $student = classops_stage2_student_number($user);
    try {
        $now = new DateTimeImmutable($nowUtc,new DateTimeZone('UTC'));
        $tomorrowLocal=$now->setTimezone(new DateTimeZone('Asia/Tehran'))->modify('+1 day');
        // The canonical resolver takes Jalali date/weekday. Conversion helper is
        // already owned by the Term7 module; if it is unavailable we fail explicit.
        if (!function_exists('dent_term7_gregorian_to_jalali')) throw new RuntimeException('term7-date-converter-unavailable');
        [$jy,$jm,$jd]=dent_term7_gregorian_to_jalali((int)$tomorrowLocal->format('Y'),(int)$tomorrowLocal->format('n'),(int)$tomorrowLocal->format('j'));
        $jalali=sprintf('%04d/%02d/%02d',$jy,$jm,$jd);
        $weekday=(int)$tomorrowLocal->format('N');
        // Term7 uses PHP weekday convention through its own resolver; evidence is
        // captured as a single canonical schedule reference, never copied state.
        $resolved=dent_term7_resolve_jalali($jalali,$weekday,dent_term7_assignment_for_student($student));
        $body=dent_term7_summary_body($resolved);
        return [
            'recordVersion'=>'classops-digest-record-v1','entityRef'=>'term7:' . str_replace('/','-',$jalali) . ':' . hash('sha256',$student),'revision'=>1,
            'cohortKey'=>DENT_TERM7_COHORT,'source'=>'schedule','itemType'=>'schedule_ref','title'=>'برنامه رسمی ترم ۷','description'=>$body,
            'course'=>null,'location'=>'','importance'=>'normal','status'=>'active','scheduleRef'=>'term7:' . DENT_TERM7_SCHEDULE_VERSION,
            'timing'=>['allDay'=>true,'localDate'=>$tomorrowLocal->format('Y-m-d'),'startsAtUtc'=>null,'endsAtUtc'=>null,'dueAtUtc'=>null,'timezone'=>'Asia/Tehran'],
            'visibility'=>['studentAllowed'=>true,'ownerAllowed'=>true,'subject'=>['kind'=>'studentNumber','value'=>$student]],
            'state'=>['taskState'=>null,'ackState'=>'not_required'],'change'=>['kind'=>'none','changedAtUtc'=>null],'supersedesRef'=>null,
        ];
    } catch (Throwable $e) {
        return [
            'recordVersion'=>'classops-digest-record-v1','entityRef'=>'term7:unavailable:' . hash('sha256',$student),'revision'=>1,
            'cohortKey'=>DENT_TERM7_COHORT,'source'=>'schedule','itemType'=>'schedule_ref','title'=>'برنامه رسمی ترم ۷ در دسترس نیست','description'=>'منبع canonical برنامه در این لحظه قابل resolve نبود؛ برنامه حدس زده نشده است.',
            'course'=>null,'location'=>'','importance'=>'important','status'=>'active','scheduleRef'=>'term7:' . DENT_TERM7_SCHEDULE_VERSION,
            'timing'=>['allDay'=>true,'localDate'=>(new DateTimeImmutable($nowUtc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Tehran'))->modify('+1 day')->format('Y-m-d'),'startsAtUtc'=>null,'endsAtUtc'=>null,'dueAtUtc'=>null,'timezone'=>'Asia/Tehran'],
            'visibility'=>['studentAllowed'=>true,'ownerAllowed'=>true,'subject'=>['kind'=>'studentNumber','value'=>$student]],
            'state'=>['taskState'=>null,'ackState'=>'not_required'],'change'=>['kind'=>'none','changedAtUtc'=>null],'supersedesRef'=>null,
        ];
    }
}

function classops_stage2_digest(array $user, string $kind, ?string $nowUtc = null): array
{
    if (!in_array($kind,['tomorrow','weekly'],true)) classops_domain_error('CLASSOPS_DIGEST_KIND_INVALID','نوع خلاصه معتبر نیست.');
    $nowUtc=$nowUtc??gmdate('Y-m-d\TH:i:s\Z');
    $cohort=dent_user_cohort_key($user);
    $owner=classops_stage2_is_owner($user);
    $student=$owner?null:classops_stage2_student_number($user);
    $viewer=['scope'=>$owner?'owner':'student','cohortKey'=>$cohort,'studentNumber'=>$student,'canonicalUserId'=>null];
    $records=[];
    $store=classops_read_store();
    foreach (($store['items']??[]) as $item) {
        if (!is_array($item)||($item['cohortKey']??'')!==$cohort||($item['status']??'')==='archived') continue;
        $taskState=null;
        if (!$owner) {
            if (classops_stage2_item_audience_for_student($item,(string)$student)===null) continue;
            if (in_array((string)$item['type'],['task','requirement'],true)) $taskState=classops_stage2_get_task_state($item,(string)$student,true);
        }
        $records[]=classops_stage2_digest_record_from_item($item,$viewer,$taskState);
    }
    if (!$owner && $cohort===DENT_TERM7_COHORT) $records[]=classops_stage2_term7_digest_record($user,$nowUtc);
    try {
        return classops_digest_build([
            'contractVersion'=>'classops-digest-v1','digestKind'=>$kind,'viewer'=>$viewer,
            'window'=>['nowUtc'=>$nowUtc,'timezone'=>'Asia/Tehran'],'budget'=>['maxItems'=>60,'maxEstimatedChars'=>7000],'records'=>$records,
        ]);
    } catch (DentClassOpsDigestException $exception) {
        classops_domain_error($exception->reasonCode,'خلاصه ClassOps قابل تولید نیست.',422);
    }
}

function classops_stage2_cancel_or_archive(array $owner, string $action, string $id, int $expectedRevision, string $idempotencyKey, string $reason): array
{
    $result=classops_transition_item($action,$id,$expectedRevision,$owner,$idempotencyKey,$reason);
    $item=$result['item'];
    classops_stage2_supersede_item_deliveries((string)$item['id'],(int)$item['revision']+1,'item_' . $action);
    classops_stage2_remove_prior_notifications((string)$item['id'],PHP_INT_MAX);
    return $result;
}
