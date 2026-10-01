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

echo "Official Term 7 timetable tests: {$checks}; failures: {$failures}\n";
exit($failures === 0 ? 0 : 1);
