<?php
declare(strict_types=1);

$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dent-term7-' . bin2hex(random_bytes(5));
putenv('DENT_STORAGE_ROOT=' . $testRoot);
putenv('DENT_SERVER_ONLY_ROOT=' . $testRoot . DIRECTORY_SEPARATOR . 'server-only');
putenv('DENT_AUTH_SECRET_KEY=' . base64_encode(str_repeat('t', 32)));

require_once __DIR__ . '/../public_html/api/bot_store.php';

restore_error_handler();
restore_exception_handler();

$failures = 0;
$total = 0;
function term7_assert(bool $condition, string $label): void
{
    global $failures, $total;
    $total++;
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
}
function term7_titles(array $events): array
{
    return array_values(array_map(static fn(array $event): string => (string) ($event['title'] ?? ''), $events));
}
function term7_cleanup(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
}

try {
    $group6 = ['group10' => 6, 'group8' => 15];
    $expectedA = [
        6 => ['پروتز پارسیل عملی ۱'],
        7 => ['بیماری‌های دهان عملی ۱'],
        1 => ['پروتز پارسیل عملی ۱'],
        2 => ['بیماری‌های دهان عملی ۱'],
        3 => ['ترمیمی عملی ۲'],
    ];
    foreach ($expectedA as $weekday => $expected) {
        $resolved = dent_term7_resolve_jalali('1405/07/0' . ($weekday === 6 ? '4' : '5'), $weekday, $group6);
        term7_assert(term7_titles($resolved['practicalMorning']) === $expected, "RTL guard: Rotation A group10=6 weekday {$weekday}");
    }

    $satA15 = dent_term7_resolve_jalali('1405/07/04', 6, ['group10' => 6, 'group8' => 15]);
    $sunA15 = dent_term7_resolve_jalali('1405/07/05', 7, ['group10' => 6, 'group8' => 15]);
    term7_assert(term7_titles($satA15['practicalAfternoon']) === ['ترمیمی عملی ۲'], 'Rotation A group8=15 Saturday afternoon');
    term7_assert(term7_titles($sunA15['practicalAfternoon']) === ['جراحی عملی ۲'], 'Rotation A group8=15 Sunday afternoon');

    $satB11 = dent_term7_resolve_jalali('1405/09/01', 6, ['group10' => 1, 'group8' => 11]);
    $sunB11 = dent_term7_resolve_jalali('1405/09/02', 7, ['group10' => 1, 'group8' => 11]);
    term7_assert(term7_titles($satB11['practicalAfternoon']) === ['ترمیمی عملی ۲'], 'Rotation B group8=11 Saturday afternoon');
    term7_assert(term7_titles($sunB11['practicalAfternoon']) === ['جراحی عملی ۲'], 'Rotation B group8=11 Sunday afternoon');

    $mondayA = dent_term7_resolve_jalali('1405/07/06', 1, ['group10' => 1, 'group8' => 15]);
    $mondayB = dent_term7_resolve_jalali('1405/09/03', 1, ['group10' => 6, 'group8' => 11]);
    term7_assert($mondayA['practicalAfternoon'] === [] && $mondayB['practicalAfternoon'] === [], 'Monday afternoon is disabled in both rotations');

    term7_assert(
        dent_term7_practical_time_range('morning') === ['09:00', '12:00']
            && dent_term7_practical_time_range('afternoon') === ['13:00', '15:00'],
        'Practical period contract maps morning 09:00-12:00 and afternoon 13:00-15:00'
    );
    $precisePractical = dent_term7_event('fixture', 'نمونه', 'morning', 'group10', [1], '', '10:15', '11:45');
    term7_assert(
        ($precisePractical['start'] ?? '') === '10:15' && ($precisePractical['end'] ?? '') === '11:45',
        'Explicit canonical practical time overrides the morning/afternoon fallback'
    );
    term7_assert(
        ($satA15['practicalMorning'][0]['start'] ?? '') === '09:00'
            && ($satA15['practicalMorning'][0]['end'] ?? '') === '12:00'
            && ($satA15['practicalAfternoon'][0]['start'] ?? '') === '13:00'
            && ($satA15['practicalAfternoon'][0]['end'] ?? '') === '15:00',
        'Resolved practical events carry explicit clock ranges'
    );

    $oralHealthSaturday = dent_term7_resolve_jalali('1405/06/28', 6, [
        'group10' => 1,
        'group8' => 11,
        'oralHealthRotationAWeekday' => 6,
    ]);
    term7_assert(
        term7_titles($oralHealthSaturday['practicalMorning']) === ['سلامت دهان عملی ۲']
            && empty($oralHealthSaturday['missingOralHealthRotationA']),
        'Rotation A oral-health Saturday subgroup sees oral health only on Saturday'
    );
    $oralHealthMondayOnSaturday = dent_term7_resolve_jalali('1405/06/28', 6, [
        'group10' => 1,
        'group8' => 11,
        'oralHealthRotationAWeekday' => 1,
    ]);
    term7_assert(
        $oralHealthMondayOnSaturday['practicalMorning'] === []
            && !str_contains(dent_term7_summary_body($oralHealthMondayOnSaturday), 'سلامت دهان عملی ۲'),
        'Rotation A Monday subgroup does not receive false Saturday oral health'
    );
    $oralHealthMonday = dent_term7_resolve_jalali('1405/06/30', 1, [
        'group10' => 1,
        'group8' => 11,
        'oralHealthRotationAWeekday' => 1,
    ]);
    term7_assert(term7_titles($oralHealthMonday['practicalMorning']) === ['سلامت دهان عملی ۲'], 'Rotation A Monday subgroup sees oral health on Monday');
    $oralHealthMissing = dent_term7_resolve_jalali('1405/06/28', 6, ['group10' => 1, 'group8' => 11]);
    term7_assert(
        $oralHealthMissing['practicalMorning'] === []
            && !empty($oralHealthMissing['missingOralHealthRotationA'])
            && str_contains(dent_term7_summary_body($oralHealthMissing), 'روز سلامت دهان عملی ۲ شما'),
        'Rotation A missing oral-health weekday fails closed and is explicit'
    );
    $oralHealthRotationB = dent_term7_resolve_jalali('1405/09/01', 6, ['group10' => 6, 'group8' => 11]);
    term7_assert(term7_titles($oralHealthRotationB['practicalMorning']) === ['سلامت دهان عملی ۲'], 'Rotation B remains unchanged until its subgroup roster exists');

    foreach (['1405/10/02', '1405/10/16'] as $closedDate) {
        $closed = dent_term7_resolve_jalali($closedDate, 3, ['group10' => 2, 'group8' => 14]);
        term7_assert($closed['practicalClosed'] && $closed['practicalMorning'] === [] && $closed['practicalAfternoon'] === [], "Red practical closure {$closedDate}");
    }

    foreach (['1405/10/19', '1405/10/20'] as $makeupDate) {
        $makeup = dent_term7_resolve_jalali($makeupDate, 6, ['group10' => 2, 'group8' => 14]);
        term7_assert(
            $makeup['rotation'] === 'makeup'
                && term7_titles($makeup['practicalMorning']) === ['ترمیمی عملی ۲']
                && term7_titles($makeup['practicalAfternoon']) === ['ترمیمی عملی ۲'],
            "Makeup assignments {$makeupDate}"
        );
    }

    $thursday = dent_term7_resolve_jalali('1405/07/02', 4, []);
    term7_assert(
        count($thursday['theory']) === 1
            && $thursday['theory'][0]['title'] === 'اندو نظری ۱'
            && $thursday['theory'][0]['start'] === '08:30'
            && $thursday['theory'][0]['end'] === '10:30',
        'Owner correction: Thursday Endo is 08:30-10:30'
    );

    $missing = dent_term7_resolve_jalali('1405/07/05', 7, []);
    term7_assert($missing['theory'] !== [] && $missing['practicalMorning'] === [] && $missing['missingGroup10'], 'Missing assignment keeps theory and never guesses practical');

    $eligible = ['studentNumber' => '40211272000', 'cohortKey' => DENT_TERM7_COHORT, 'role' => 'student'];
    $other = ['studentNumber' => '40211272001', 'cohortKey' => 'prosthesis-1402', 'role' => 'prosthesis_student'];
    term7_assert(dent_term7_user_is_eligible($eligible), 'Exact dentistry-1402 cohort is eligible');
    term7_assert(!dent_term7_user_is_eligible($other), 'Other cohort is ineligible');

    $tz = new DateTimeZone(DENT_TERM7_TIMEZONE);
    foreach ([15, 17, 19, 21, 23] as $hour) {
        $slot = dent_term7_food_slot(new DateTimeImmutable("2026-09-08 {$hour}:00:00", $tz));
        term7_assert(($slot['hour'] ?? null) === $hour, "Tuesday food slot {$hour}:00");
    }
    foreach ([1, 3, 5] as $hour) {
        $slot = dent_term7_food_slot(new DateTimeImmutable("2026-09-09 {$hour}:00:00", $tz));
        term7_assert(($slot['hour'] ?? null) === $hour, "Wednesday food slot {$hour}:00");
    }
    term7_assert(dent_term7_food_slot(new DateTimeImmutable('2026-09-09 06:00:00', $tz)) === null, 'Food cycle stops at Wednesday 06:00');

    $weekKey = '2026-09-08';
    term7_assert(!dent_term7_food_is_confirmed('40211272000', $weekKey), 'Food URL view alone does not confirm');
    $first = dent_term7_confirm_food($eligible, $weekKey, 'telegram');
    term7_assert($first['confirmed'] && !$first['alreadyConfirmed'], 'Telegram confirmation stores canonical state');
    term7_assert(dent_term7_food_is_confirmed('40211272000', $weekKey), 'Telegram confirmation suppresses Bale via shared state');
    $second = dent_term7_confirm_food($eligible, $weekKey, 'bale');
    term7_assert($second['confirmed'] && $second['alreadyConfirmed'], 'Bale double callback is idempotent');
    term7_assert(!dent_term7_food_is_confirmed('40211272000', '2026-09-15'), 'New Tuesday starts a new deterministic food cycle');

    $refA = dent_term7_food_action_ref('40211272000', $weekKey);
    $refB = dent_term7_food_action_ref('40211272000', $weekKey);
    term7_assert($refA === $refB && preg_match('/^[a-f0-9]{18}$/', $refA) === 1, 'Food callback ref is deterministic, bounded and opaque');

    $leaseNow = time();
    term7_assert(dent_term7_scheduler_claim_slot('academic:2026-09-08:21', $leaseNow), 'Central scheduler claims a new slot once');
    term7_assert(!dent_term7_scheduler_claim_slot('academic:2026-09-08:21', $leaseNow + 1), 'Concurrent scheduler tick is back-pressured by the slot lease');
    dent_term7_scheduler_finish_slot('academic:2026-09-08:21', true);
    term7_assert(!dent_term7_scheduler_claim_slot('academic:2026-09-08:21', $leaseNow + 600), 'Completed scheduler slot remains idempotent after restart');

    $summaryA = dent_term7_resolve_jalali('1405/07/04', 6, ['group10' => 6, 'group8' => 15]);
    $summaryB = dent_term7_resolve_jalali('1405/07/04', 6, ['group10' => 1, 'group8' => 11]);
    term7_assert($summaryA['theory'] === $summaryB['theory'], 'Synthetic fixture A/B has identical shared theory');
    term7_assert($summaryA['practicalMorning'] !== $summaryB['practicalMorning'], 'Synthetic fixture A/B has personalized practical');
    term7_assert(!dent_term7_user_is_eligible($other), 'Synthetic fixture C receives no academic or food reminder');

    $saturdayTheory = dent_term7_resolve_jalali('1405/07/04', 6, []);
    term7_assert(count($saturdayTheory['theory']) === 1, 'Rotation-scoped Research Methodology 2 is absent from shared theory');
    $researchCases = [
        ['1405/07/04', 6, ['group10' => 1, 'group8' => 11, 'oralHealthRotationAWeekday' => 1], 1],
        ['1405/07/04', 6, ['group10' => 6, 'group8' => 15], 0],
        ['1405/09/01', 6, ['group10' => 6, 'group8' => 11], 1],
        ['1405/09/01', 6, ['group10' => 1, 'group8' => 12], 0],
        ['1405/07/08', 3, ['group10' => 1, 'group8' => 18, 'oralHealthRotationAWeekday' => 1], 1],
        ['1405/09/05', 3, ['group10' => 6, 'group8' => 14], 1],
    ];
    foreach ($researchCases as [$date, $weekday, $assignment, $expectedCount]) {
        $resolvedResearch = dent_term7_resolve_jalali($date, $weekday, $assignment);
        $allRows = array_merge($resolvedResearch['theory'], $resolvedResearch['practicalMorning'], $resolvedResearch['practicalAfternoon']);
        $researchRows = array_values(array_filter($allRows, static fn(array $event): bool => str_starts_with((string) ($event['slug'] ?? ''), 'research-methods-2')));
        term7_assert(count($researchRows) === $expectedCount, 'Research Methodology 2 follows active rotation and never duplicates');
        if ($expectedCount === 1) {
            term7_assert(
                ($researchRows[0]['start'] ?? '') === '13:00'
                    && ($researchRows[0]['end'] ?? '') === '15:00'
                    && ($researchRows[0]['location'] ?? '') === (dent_term7_schedule()['theory'][6][0]['location'] ?? ''),
                'Research Methodology 2 keeps canonical time and amphitheater location'
            );
        }
    }
    term7_assert(str_contains(dent_term7_summary_body($missing), 'گروه کارآموزی'), 'Missing-group UX is explicit');
    $clockSummary = dent_term7_summary_body($satA15);
    term7_assert(
        str_contains($clockSummary, '۰۹:۰۰ تا ۱۲:۰۰')
            && str_contains($clockSummary, '۱۳:۰۰ تا ۱۵:۰۰')
            && !str_contains($clockSummary, 'کارآموزی صبح')
            && !str_contains($clockSummary, 'کارآموزی عصر'),
        'User-facing practical summary is clock-based with Persian digits'
    );
    term7_assert(dent_term7_schedule()['prepChecklists'] === [], 'Preparation checklist remains intentionally empty');
    term7_assert(dent_term7_schedule()['foodUrl'] === DENT_TERM7_FOOD_URL, 'Food URL has one canonical config source');
    term7_assert(dent_term7_schedule()['timezone'] === 'Asia/Tehran', 'Academic timezone is explicit');

    $publicWithDis = dent_bot_public_user(['studentNumber' => '40211272000', 'name' => 'کاربر تست', 'role' => 'student', 'cohortKey' => DENT_TERM7_COHORT]);
    term7_assert(array_key_exists('disNumber', $publicWithDis), 'Signed bot account contract includes canonical disNumber field');
    $statusPayload = dent_bot_term7_status(['studentNumber' => '40211272003', 'role' => 'owner', 'cohortKey' => DENT_TERM7_COHORT]);
    term7_assert(
        ($statusPayload['contractVersion'] ?? '') === DENT_TERM7_CONTRACT
            && ($statusPayload['thursdayEndo']['end'] ?? '') === '10:30'
            && ($statusPayload['sample']['morningTitles'] ?? []) === ['پروتز پارسیل عملی ۱'],
        'Owner-only live status smoke exposes version, Endo correction and RTL sample without PII'
    );

    // End-to-end synthetic fixture through canonical auth, link, schedule,
    // notification and callback stores (no production data or credentials).
    $studentA = '40211272991';
    $studentB = '40211272992';
    $studentC = '40211272993';
    $studentD = '40211272994';
    $studentE = '40211272995';
    $fixturePassword = dent_hash_password('fixture-password-only');
    dent_save_user_store([
        'cohorts' => dent_default_cohort_catalog(),
        'users' => [
            $studentA => ['studentNumber' => $studentA, 'name' => 'دانشجوی الف', 'passwordHash' => $fixturePassword, 'role' => 'student', 'cohortKey' => DENT_TERM7_COHORT],
            $studentB => ['studentNumber' => $studentB, 'name' => 'دانشجوی ب', 'passwordHash' => $fixturePassword, 'role' => 'student', 'cohortKey' => DENT_TERM7_COHORT],
            $studentC => ['studentNumber' => $studentC, 'name' => 'دانشجوی ج', 'passwordHash' => $fixturePassword, 'role' => 'prosthesis_student', 'cohortKey' => 'prosthesis-1402'],
            $studentD => ['studentNumber' => $studentD, 'name' => 'دانشجوی د', 'passwordHash' => $fixturePassword, 'role' => 'student', 'cohortKey' => DENT_TERM7_COHORT],
            $studentE => ['studentNumber' => $studentE, 'name' => 'علی باطبی', 'passwordHash' => $fixturePassword, 'role' => 'student', 'cohortKey' => DENT_TERM7_COHORT],
        ],
    ]);
    dent_term7_state_with_lock(static function (array &$state) use ($studentA, $studentB, $studentE): array {
        $state['assignments'][$studentA] = ['studentNumber' => $studentA, 'group10' => 6, 'group8' => 15, 'updatedAt' => dent_iso_now()];
        $state['assignments'][$studentB] = ['studentNumber' => $studentB, 'group10' => 1, 'group8' => 11, 'oralHealthRotationAWeekday' => 1, 'updatedAt' => dent_iso_now()];
        $state['assignments'][$studentE] = ['studentNumber' => $studentE, 'group10' => 3, 'group8' => 12, 'updatedAt' => dent_iso_now()];
        return [];
    });
    $oralHealthAliasImport = dent_term7_import_oral_health_rotation_a([
        ['name' => 'بردیا باطبی', 'weekday' => 3],
    ], true);
    $oralHealthAliasState = dent_term7_state_read();
    term7_assert(
        !empty($oralHealthAliasImport['committed'])
            && (($oralHealthAliasImport['matched'][0]['matchedBy'] ?? '') === 'explicitAlias')
            && (($oralHealthAliasState['assignments'][$studentE]['oralHealthRotationAWeekday'] ?? null) === 3),
        'Owner-confirmed Bardia Batbi alias resolves to canonical Ali Batbi'
    );
    $mobinaAlias = dent_term7_import_person_name_key('مبینا روحانی');
    term7_assert(($mobinaAlias['key'] ?? '') === dent_term7_normalize_person_name('فاطمه روحانی') && ($mobinaAlias['matchedBy'] ?? '') === 'explicitAlias', 'Owner-confirmed Mobina Rouhani alias remains explicit import metadata');
    $incompleteImport = dent_term7_import_assignments([
        ['studentNumber' => $studentA, 'name' => 'دانشجوی الف', 'group' => 6],
        ['studentNumber' => $studentB, 'name' => 'دانشجوی ب', 'group' => 1],
    ], 'group10', true);
    term7_assert(
        empty($incompleteImport['committed'])
            && count($incompleteImport['missingGroup10'] ?? []) === 1
            && count($incompleteImport['missingGroup8'] ?? []) === 1,
        'Importer reports missing group10/group8 separately and blocks incomplete target commit'
    );
    dent_bot_persistence_initialize(
        dent_bot_store_path(),
        dent_bot_store_default(),
        'dent_bot_store_normalize',
        'term7-fixture-initialize'
    );
    dent_bot_store_with_lock(static function (array &$store) use ($studentA, $studentB, $studentC): array {
        $fixtures = [
            ['telegram', '900001', $studentA],
            ['bale', '900002', $studentA],
            ['telegram', '900003', $studentB],
            ['telegram', '900004', $studentC],
        ];
        foreach ($fixtures as [$platform, $platformUserId, $studentNumber]) {
            $identityHash = dent_bot_identity_hash($platform, $platformUserId);
            $link = [
                'platform' => $platform,
                // Scheduler eligibility never decrypts transport IDs. Keep the
                // fixture crypto-free because CI PHP may omit openssl.
                'platformUserIdEncrypted' => ['iv' => 'fixture', 'tag' => 'fixture', 'ct' => 'fixture'],
                'studentNumber' => $studentNumber,
            ];
            if ($studentNumber !== $studentB) {
                $link['authVersion'] = dent_bot_canonical_auth_version();
                $link['authMethod'] = 'secure-site-login';
                $link['authCompletedAt'] = dent_iso_now();
            }
            $store['links'][$identityHash] = $link;
        }
        return [];
    });

    $preTermTick = dent_term7_scheduler_tick(new DateTimeImmutable('2026-09-08 15:00:00', $tz));
    term7_assert($preTermTick['created'] === 0 && $preTermTick['eligibleUsers'] === 0, 'Scheduler creates no academic/food reminder outside the active term window');

    $academicTick = dent_term7_scheduler_tick(new DateTimeImmutable('2026-09-18 21:00:00', $tz));
    $notificationStore = notifications_read_store();
    $academicRecords = array_values(array_filter($notificationStore['notifications'], static fn($record): bool => is_array($record) && ($record['source'] ?? '') === 'academic-term7'));
    term7_assert($academicTick['eligibleUsers'] === 2 && count($academicRecords) === 2, 'Integration: every canonical linked dentistry-1402 A/B user receives tomorrow summaries regardless of link-generation metadata');
    term7_assert(($academicRecords[0]['body'] ?? '') !== ($academicRecords[1]['body'] ?? ''), 'Integration: A/B summary bodies are personalized');
    $studentBReminder = array_values(array_filter($academicRecords, static fn($record): bool => str_ends_with((string) ($record['sourceKey'] ?? ''), ':' . $studentB)))[0] ?? [];
    term7_assert(
        !str_contains((string) ($studentBReminder['body'] ?? ''), 'سلامت دهان عملی ۲')
            && str_contains((string) ($studentBReminder['title'] ?? ''), 'شنبه')
            && str_contains((string) ($studentBReminder['title'] ?? ''), '۱۴۰۵/۰۶/۲۸'),
        'Friday-night reminder is a personalized Saturday summary with Persian date'
    );
    $studentAReminder = array_values(array_filter($academicRecords, static fn($record): bool => str_ends_with((string) ($record['sourceKey'] ?? ''), ':' . $studentA)))[0] ?? [];
    term7_assert(
        str_contains((string) ($studentAReminder['body'] ?? ''), '۰۹:۰۰ تا ۱۲:۰۰')
            && str_contains((string) ($studentAReminder['body'] ?? ''), '۱۳:۰۰ تا ۱۵:۰۰'),
        'Canonical reminder body preserves explicit practical clock ranges'
    );
    term7_assert(!str_contains(implode('|', array_column($academicRecords, 'sourceKey')), $studentC), 'Integration: cross-cohort fixture C is absent');
    $retryTick = dent_term7_scheduler_tick(new DateTimeImmutable('2026-09-18 21:30:00', $tz));
    term7_assert($retryTick['created'] === 0 && count(array_filter(notifications_read_store()['notifications'], static fn($record): bool => is_array($record) && ($record['source'] ?? '') === 'academic-term7')) === 2, 'Integration: scheduler restart/retry creates no duplicate');

    dent_term7_scheduler_tick(new DateTimeImmutable('2026-09-22 15:00:00', $tz));
    $notificationStore = notifications_read_store();
    $foodRecords = array_values(array_filter($notificationStore['notifications'], static fn($record): bool => is_array($record) && ($record['source'] ?? '') === 'food-reservation'));
    term7_assert(count($foodRecords) === 2, 'Integration: first food slot targets eligible A/B only');
    $foodARecords = array_values(array_filter($foodRecords, static fn($record): bool => str_ends_with((string) ($record['sourceKey'] ?? ''), ':' . $studentA)));
    $foodA = $foodARecords[0] ?? [];
    $actionA = (string) ($foodA['actions'][0]['ref'] ?? '');
    $userA = dent_get_user_record($studentA);
    $actionResult = is_array($userA) ? dent_bot_perform_notification_action($userA, 'telegram', ['notificationId' => $foodA['id'], 'actionRef' => $actionA]) : [];
    term7_assert(!empty($actionResult['success']), 'Integration: authenticated food callback is accepted');
    dent_term7_scheduler_tick(new DateTimeImmutable('2026-09-22 17:00:00', $tz));
    $laterFood = array_values(array_filter(notifications_read_store()['notifications'], static fn($record): bool => is_array($record) && ($record['source'] ?? '') === 'food-reservation' && str_contains((string) ($record['sourceKey'] ?? ''), ':17:')));
    term7_assert(count($laterFood) === 1 && str_ends_with((string) $laterFood[0]['sourceKey'], ':' . $studentB), 'Integration: Telegram confirmation suppresses later Bale/Telegram reminders for A only');
} finally {
    term7_cleanup($testRoot);
}

echo "Term 7 tests: {$total}; failures: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
