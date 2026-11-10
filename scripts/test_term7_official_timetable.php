<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/public_html/api/academic_term7.php';

$failures = 0;
$checks = 0;
$assert = static function (bool $condition, string $label) use (&$failures, &$checks): void {
    $checks++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: {$label}\n");
    } else {
        echo "PASS: {$label}\n";
    }
};

$schedule = dent_term7_schedule();
$assert(
    ($schedule['timePolicy'] ?? []) === [
        'theoryMorning' => ['start' => '07:00', 'end' => '08:00'],
        'practicalMorning' => ['start' => '08:15', 'end' => '11:15'],
        'thesisDefense' => ['start' => '11:15', 'end' => '12:15'],
        'practicalAfternoon' => ['start' => '11:45', 'end' => '14:15'],
    ],
    'Official Term 7 time policy has one canonical source'
);

$expectedTheory = [
    6 => [['periodontology-theory-1', '07:00', '08:00']],
    7 => [['diagnostic-dentistry-3-sun', '07:00', '08:00']],
    1 => [
        ['ent', '07:00', '08:00'],
        ['diagnostic-dentistry-3-mon', '11:30', '12:30'],
        ['orthodontics-theory-1', '12:40', '13:40'],
        ['endodontics-theory-1', '13:50', '15:50'],
    ],
    2 => [['oral-health-theory-2', '07:00', '08:00']],
    3 => [['partial-basics-theory', '07:00', '08:00']],
];
foreach ($expectedTheory as $weekday => $expected) {
    $actual = array_map(
        static fn(array $event): array => [
            (string) ($event['slug'] ?? ''),
            (string) ($event['start'] ?? ''),
            (string) ($event['end'] ?? ''),
        ],
        is_array($schedule['theory'][$weekday] ?? null) ? $schedule['theory'][$weekday] : []
    );
    $assert($actual === $expected, "Official theory order and clocks match weekday {$weekday}");
}
$assert(!isset($schedule['theory'][4]) || $schedule['theory'][4] === [], 'Thursday theory is completely absent');

$researchCount = 0;
foreach (['A', 'B'] as $rotation) {
    foreach (($schedule['rotations'][$rotation]['days'] ?? []) as $events) {
        foreach (is_array($events) ? $events : [] as $event) {
            if (!is_array($event)) continue;
            $period = (string) ($event['period'] ?? '');
            $slug = (string) ($event['slug'] ?? '');
            $start = (string) ($event['start'] ?? '');
            $end = (string) ($event['end'] ?? '');
            if ($slug === 'research-methods-2-practical') {
                $researchCount++;
                $assert($start === '11:45' && $end === '14:15', 'Research Methodology 2 uses the official second-shift 11:45-14:15 clock');
                continue;
            }
            if ($period === 'morning') {
                $assert($start === '08:15' && $end === '11:15', "Morning practical {$slug} uses 08:15-11:15");
            } elseif ($period === 'afternoon') {
                $assert($start === '11:45' && $end === '14:15', "Afternoon practical {$slug} uses 11:45-14:15");
            }
        }
    }
}
$assert($researchCount === 4, 'Research Methodology 2 appears exactly in the four canonical rotation/day slots');

$catalog = classops_term7_syllabus_catalog();
$competingTiming = [];
$segmentTiming = [];
foreach ($catalog as $courseKey => $course) {
    if (!is_array($course)) continue;
    if (array_key_exists('sourceTiming', $course)) $competingTiming[] = (string) $courseKey;
    foreach (is_array($course['sessions'] ?? null) ? $course['sessions'] : [] as $session) {
        if (!is_array($session)) continue;
        foreach (is_array($session['segments'] ?? null) ? $session['segments'] : [] as $segment) {
            if (is_array($segment) && array_key_exists('time', $segment)) $segmentTiming[] = (string) $courseKey;
        }
    }
}
$assert($competingTiming === [], 'Course-syllabus metadata contains no second timetable source');
$assert($segmentTiming === [], 'Course-syllabus segment metadata contains no stale clock values');
$assert(!function_exists('classops_term7_syllabus_apply_source_times'), 'No runtime syllabus time-override function exists');

$thursday = dent_term7_resolve_jalali('1405/07/09', 4, []);
$assert(($thursday['theory'] ?? null) === [], 'Resolved Thursday has no theory events');

$monday = dent_term7_resolve_jalali('1405/07/06', 1, []);
$mondayEndo = array_values(array_filter(
    dent_term7_enriched_event_groups($monday)['theory'],
    static fn(array $event): bool => ($event['slug'] ?? '') === 'endodontics-theory-1'
));
$assert(
    count($mondayEndo) === 1
        && ($mondayEndo[0]['sessionNumber'] ?? null) === 3
        && ($mondayEndo[0]['start'] ?? '') === '13:50'
        && ($mondayEndo[0]['end'] ?? '') === '15:50'
        && ($mondayEndo[0]['sourceDate'] ?? '') === '1405/07/09',
    'Endodontics source metadata enriches Monday without restoring the old Thursday clock'
);

// Announced session-mode corrections must propagate to the canonical schedule
// as well as bot, booklet and reminder projections from the same syllabus.
foreach ([
    ['1405/08/11', 8, 'دکتر قدیریان', 'virtual', '', 'مجازی'],
    ['1405/10/06', 16, 'دکتر تنباکوچی', 'in_person', 'آمفی‌تئاتر ۹۰', 'حضوری'],
] as [$date, $sessionNumber, $instructor, $mode, $location, $label]) {
    $resolved = dent_term7_resolve_jalali($date, 1, []);
    $enriched = dent_term7_enriched_event_groups($resolved);
    $sessions = array_values(array_filter(
        $enriched['theory'] ?? [],
        static fn(array $event): bool => ($event['slug'] ?? '') === 'orthodontics-theory-1'
            && (int) ($event['sessionNumber'] ?? 0) === $sessionNumber
    ));
    $assert(count($sessions) === 1, "Orthodontics session {$sessionNumber} is uniquely scheduled");
    if (count($sessions) !== 1) continue;
    $event = $sessions[0];
    $assert(($event['sessionMode'] ?? '') === $mode, "Orthodontics session {$sessionNumber} mode corrected");
    $assert(($event['sessionModeLabel'] ?? '') === $label, "Orthodontics session {$sessionNumber} Persian mode label");
    $assert(($event['instructor'] ?? '') === $instructor, "Orthodontics session {$sessionNumber} instructor preserved");
    $assert(($event['location'] ?? '') === $location, "Orthodontics session {$sessionNumber} location agrees with mode");
    $assert(($event['start'] ?? '') === '12:40' && ($event['end'] ?? '') === '13:40', "Orthodontics session {$sessionNumber} clock unchanged");
}

// Dr. Bahrami's session 13 moved from Wednesday 18 Azar to Monday 16 Azar.
// Time is intentionally unconfirmed: inheriting Wednesday's 07:00 slot
// would conflict with the actual Monday ENT class.
$movedMonday = dent_term7_resolve_jalali('1405/09/16', 1, []);
$movedWednesday = dent_term7_resolve_jalali('1405/09/18', 3, []);
$movedRows = array_values(array_filter(
    dent_term7_ordered_schedule_rows($movedMonday),
    static fn(array $event): bool => str_contains((string) ($event['title'] ?? ''), 'رست و جایگاه رست')
));
$assert(count($movedRows) === 1, 'Moved partial session appears exactly once on Monday 16 Azar');
if (count($movedRows) === 1) {
    $assert(
        ($movedRows[0]['instructor'] ?? '') === 'دکتر بهرامی'
            && ($movedRows[0]['start'] ?? 'x') === ''
            && ($movedRows[0]['end'] ?? 'x') === '',
        'Moved Dr Bahrami class preserves instructor without inventing Monday time'
    );
}
$assert(
    array_values(array_filter($movedWednesday['theory'] ?? [], static fn(array $e): bool => ($e['slug'] ?? '') === 'partial-basics-theory')) === [],
    'Moved partial class absent from Wednesday 18 Azar'
);
$syllabus = classops_term7_syllabus_catalog()['partial-basics-theory']['sessions'];
$session13 = array_values(array_filter($syllabus, static fn(array $e): bool => (int) ($e['sessionNumber'] ?? 0) === 13));
$assert(count($session13) === 1
    && ($session13[0]['dates'] ?? []) === ['1405/09/16']
    && ($session13[0]['rescheduledFrom'] ?? '') === '1405/09/18'
    && ($session13[0]['sourceDate'] ?? '') === '05/09/18',
    'Single traceable source-date correction feeds class and reminder timetable'
);

// One-off diagnostic session 18 (Shakib) moves onto the existing Sunday slot.
$diagnosticSessions = array_values(array_filter(
    $catalog['diagnostic-dentistry-3']['sessions'] ?? [],
    static fn(array $session): bool => (int) ($session['sessionNumber'] ?? 0) === 18
));
$assert(
    count($diagnosticSessions) === 1
        && ($diagnosticSessions[0]['dates'] ?? []) === ['1405/08/24']
        && ($diagnosticSessions[0]['sourceDate'] ?? '') === '1405/08/25'
        && ($diagnosticSessions[0]['instructor'] ?? '') === 'دکتر شکیب',
    'Shakib session 18 moves once with preserved instructor and source date'
);
$diagnosticSunday = dent_term7_enriched_event_groups(
    dent_term7_resolve_jalali('1405/08/24', 7, [])
)['theory'];
$diagnosticMonday = dent_term7_enriched_event_groups(
    dent_term7_resolve_jalali('1405/08/25', 1, [])
)['theory'];
$diagnosticOnSunday = array_values(array_filter(
    $diagnosticSunday,
    static fn(array $event): bool => str_starts_with((string) ($event['slug'] ?? ''), 'diagnostic-dentistry-3-')
));
$shakibOnSunday = array_values(array_filter(
    $diagnosticOnSunday,
    static fn(array $event): bool => (int) ($event['sessionNumber'] ?? 0) === 18
));
$originalSunday = array_values(array_filter(
    $diagnosticOnSunday,
    static fn(array $event): bool => (int) ($event['sessionNumber'] ?? 0) === 17
));
$shakibOnMonday = array_values(array_filter(
    $diagnosticMonday,
    static fn(array $event): bool => ($event['slug'] ?? '') === 'diagnostic-dentistry-3-mon'
));
$assert(
    count($diagnosticOnSunday) === 2 && count($shakibOnSunday) === 1
        && ($shakibOnSunday[0]['instructor'] ?? '') === 'دکتر شکیب'
        && ($shakibOnSunday[0]['start'] ?? '') === '07:00'
        && ($shakibOnSunday[0]['end'] ?? '') === '08:00'
        && ($shakibOnSunday[0]['location'] ?? '') === 'آمفی‌تئاتر ۹۰',
    'Shakib appears exactly once on Sunday at 07:00–08:00 with original venue'
);
$assert(
    count($originalSunday) === 1
        && ($originalSunday[0]['instructor'] ?? '') === 'دکتر شیرازیان'
        && ($originalSunday[0]['sessionMode'] ?? '') === 'offline',
    'Original Sunday asynchronous session 17 is not overwritten'
);
$assert(
    $shakibOnMonday === []
        && count(array_filter(
            $diagnosticMonday,
            static fn(array $event): bool => ($event['slug'] ?? '') === 'ent'
        )) === 1,
    'Cancelled Monday Shakib occurrence is absent without affecting ENT'
);

echo "Official Term 7 timetable tests: {$checks}; failures: {$failures}\n";
exit($failures === 0 ? 0 : 1);
