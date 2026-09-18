<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/public_html/api/classops_term7_syllabus.php';

$checks = 0;
$failures = 0;
function syllabus_assert(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if ($condition) {
        echo "PASS: {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL: {$message}\n";
}

function syllabus_event(string $slug, string $title, string $location = 'آمفی‌تئاتر ۹۰'): array
{
    return [
        'slug' => $slug,
        'title' => $title,
        'start' => '07:30',
        'end' => '08:30',
        'location' => $location,
    ];
}

$catalog = classops_term7_syllabus_catalog();
$expectedKeys = [
    'orthodontics-theory-1',
    'endodontics-theory-1',
    'diagnostic-dentistry-3',
    'research-methods-2',
    'endodontics-basics-2',
    'oral-health-practical-2',
    'oral-health-theory-2',
    'periodontology-theory-1',
    'partial-basics-theory',
];
foreach ($expectedKeys as $key) {
    syllabus_assert(isset($catalog[$key]) && is_array($catalog[$key]), "Syllabus catalog contains {$key}");
}

$counts = [
    'orthodontics-theory-1' => 16,
    'endodontics-theory-1' => 10,
    'diagnostic-dentistry-3' => 33,
    'research-methods-2' => 17,
    'endodontics-basics-2' => 14,
    'oral-health-practical-2' => 8,
    'oral-health-theory-2' => 16,
    'periodontology-theory-1' => 17,
    'partial-basics-theory' => 15,
];
foreach ($counts as $key => $expected) {
    syllabus_assert(count($catalog[$key]['sessions'] ?? []) === $expected, "{$key} preserves {$expected} source rows/sessions");
}

$partialAmbiguous = $catalog['partial-basics-theory']['sessions'][2] ?? [];
syllabus_assert(
    ($partialAmbiguous['sessionNumber'] ?? null) === 3 && ($partialAmbiguous['dates'] ?? []) === [],
    'Partial session 3 remains fail-closed because its source date is ambiguous'
);

$orth = classops_term7_syllabus_enrich_events([
    syllabus_event('orthodontics-theory-1', 'ارتودنسی نظری ۱'),
], '1405/07/27');
syllabus_assert(count($orth) === 2, 'Orthodontics preserves in-person and virtual sessions sharing 1405/07/27');
syllabus_assert(
    array_column($orth, 'sessionNumber') === [5, 6]
        && ($orth[1]['sessionMode'] ?? '') === 'virtual'
        && ($orth[1]['location'] ?? 'x') === ''
        && ($orth[1]['start'] ?? '') === '12:30'
        && ($orth[1]['end'] ?? '') === '13:30'
        && !empty($orth[1]['sourceTimeExplicit']),
    'Orthodontics virtual row keeps the explicit syllabus clock while omitting physical room'
);

$endo = classops_term7_syllabus_enrich_events([
    syllabus_event('endodontics-theory-1', 'اندو نظری ۱'),
], '1405/08/14');
syllabus_assert(
    array_column($endo, 'sessionNumber') === [11, 12]
        && ($endo[0]['start'] ?? '') === '08:30'
        && ($endo[0]['end'] ?? '') === '10:30'
        && ($endo[1]['start'] ?? '') === '08:30'
        && ($endo[1]['end'] ?? '') === '10:30',
    'Endodontics preserves sessions 11 and 12 and applies the two-session-day 08:30-10:30 window'
);
$endoSingle = classops_term7_syllabus_enrich_events([
    syllabus_event('endodontics-theory-1', 'اندو نظری ۱'),
], '1405/07/09');
syllabus_assert(
    count($endoSingle) === 1
        && ($endoSingle[0]['sessionNumber'] ?? null) === 3
        && ($endoSingle[0]['start'] ?? '') === '08:30'
        && ($endoSingle[0]['end'] ?? '') === '09:30',
    'Endodontics single-session day uses 08:30-09:30 from the syllabus header'
);


$diagnostic = $catalog['diagnostic-dentistry-3']['sessions'] ?? [];
syllabus_assert(
    array_column($diagnostic, 'sessionNumber') === range(1, 33)
        && count(array_filter($diagnostic, static fn(array $row): bool => ($row['assessmentPart'] ?? '') === 'میان‌ترم')) === 17
        && count(array_filter($diagnostic, static fn(array $row): bool => ($row['assessmentPart'] ?? '') === 'پایان‌ترم')) === 16,
    'Diagnostic Dentistry 3 keeps the source 17-session midterm and 16-session final split'
);
$diagQuiz = classops_term7_syllabus_enrich_events([
    syllabus_event('diagnostic-dentistry-3-mon', 'دندانپزشکی تشخیصی ۳'),
], '1405/07/13');
syllabus_assert(
    count($diagQuiz) === 1
        && ($diagQuiz[0]['sessionNumber'] ?? null) === 6
        && ($diagQuiz[0]['sessionModeLabel'] ?? '') === 'حضوری + کوییز کلاسی'
        && ($diagQuiz[0]['start'] ?? '') === '13:15'
        && ($diagQuiz[0]['end'] ?? '') === '14:15',
    'Diagnostic Monday metadata uses the syllabus-priority 13:15-14:15 clock'
);
$diagnosticFallback = classops_term7_syllabus_apply_source_times([[
    'slug' => 'diagnostic-dentistry-3-mon',
    'title' => 'دندانپزشکی تشخیصی ۳',
    'start' => '13:45',
    'end' => '14:45',
]], '1405/07/14');
syllabus_assert(
    count($diagnosticFallback) === 1
        && ($diagnosticFallback[0]['start'] ?? '') === '13:45'
        && ($diagnosticFallback[0]['end'] ?? '') === '14:45'
        && empty($diagnosticFallback[0]['sourceTimeExplicit']),
    'Missing source session preserves the canonical fallback clock'
);

$research = classops_term7_syllabus_enrich_events([
    syllabus_event('research-methods-2-practical', 'روش تحقیق ۲'),
], '1405/07/29');
syllabus_assert(count($research) === 2, 'Research Methodology keeps session 10 plus virtual session 11 on 1405/07/29');
syllabus_assert(
    ($research[0]['sessionNumber'] ?? null) === 10
        && ($research[0]['start'] ?? '') === '13:00'
        && ($research[0]['end'] ?? '') === '15:30'
        && ($research[1]['sessionNumber'] ?? null) === 11
        && ($research[1]['sessionMode'] ?? '') === 'virtual'
        && ($research[1]['location'] ?? 'x') === ''
        && ($research[1]['start'] ?? 'x') === ''
        && ($research[1]['end'] ?? 'x') === '',
    'Research Methodology uses 13:00-15:30 for timed rows and keeps source dash-time virtual rows untimed'
);

$researchWithSupplement = classops_term7_syllabus_enrich_events([
    syllabus_event('research-methods-2-practical', 'روش تحقیق ۲'),
], '1405/07/08', 'A');
syllabus_assert(
    count($researchWithSupplement) === 2
        && ($researchWithSupplement[0]['sessionNumber'] ?? null) === 4
        && ($researchWithSupplement[1]['sessionLabel'] ?? '') === 'محتوای تکمیلی جلسه ۴',
    'Research Methodology keeps the numbered session before its supplemental row'
);

$researchRotationB = classops_term7_syllabus_enrich_events([
    syllabus_event('research-methods-2-practical', 'روش تحقیق ۲'),
], '1405/09/25', 'B');
syllabus_assert(
    array_column($researchRotationB, 'sessionNumber') === [10, 11]
        && ($researchRotationB[0]['instructor'] ?? '') !== ''
        && ($researchRotationB[1]['sessionMode'] ?? '') === 'virtual',
    'Research Methodology repeats the same source-relative session sequence in Rotation B'
);


$endoBasics = $catalog['endodontics-basics-2']['sessions'] ?? [];
syllabus_assert(
    count($endoBasics) === 14
        && ($endoBasics[0]['dates'] ?? []) === ['1405/06/29']
        && str_contains((string) ($endoBasics[0]['sessionDetails'] ?? ''), 'گروه‌بندی')
        && ($endoBasics[0]['instructor'] ?? 'x') === '',
    'Endodontics Foundations 2 preserves all 14 Rotation A rows without fabricating an instructor'
);
$endoBasicsQuiz = classops_term7_syllabus_enrich_events([
    syllabus_event('endodontics-basics-2', 'مبانی اندو ۲', 'پری‌کلینیک منفی ۲'),
], '1405/07/12', 'A');
syllabus_assert(
    count($endoBasicsQuiz) === 1
        && ($endoBasicsQuiz[0]['sessionNumber'] ?? null) === 5
        && str_contains((string) ($endoBasicsQuiz[0]['sessionTitle'] ?? ''), 'کوییز ۱')
        && ($endoBasicsQuiz[0]['instructor'] ?? '') === 'دکتر ملک پور'
        && ($endoBasicsQuiz[0]['start'] ?? '') === '07:30'
        && ($endoBasicsQuiz[0]['end'] ?? '') === '08:30'
        && empty($endoBasicsQuiz[0]['sourceTimeExplicit']),
    'Endodontics Foundations 2 enriches Rotation A quiz/demo metadata without inventing a source clock'
);
$endoBasicsPractice = classops_term7_syllabus_enrich_events([
    syllabus_event('endodontics-basics-2', 'مبانی اندو ۲', 'پری‌کلینیک منفی ۲'),
], '1405/07/14', 'A');
syllabus_assert(
    count($endoBasicsPractice) === 1
        && ($endoBasicsPractice[0]['sessionNumber'] ?? null) === 6
        && ($endoBasicsPractice[0]['instructor'] ?? 'x') === '',
    'Endodontics Foundations 2 practice rows keep the source instructor blank'
);
$endoBasicsRotationB = classops_term7_syllabus_enrich_events([
    syllabus_event('endodontics-basics-2', 'مبانی اندو ۲', 'پری‌کلینیک منفی ۲'),
], '1405/09/07', 'B');
syllabus_assert(
    count($endoBasicsRotationB) === 1 && !isset($endoBasicsRotationB[0]['sessionNumber']),
    'Endodontics Foundations 2 does not infer Rotation B syllabus metadata from the Rotation A-only source'
);
$endoBasicsRubberDam = $endoBasics[10] ?? [];
syllabus_assert(
    ($endoBasicsRubberDam['sessionNumber'] ?? null) === 11
        && str_contains((string) ($endoBasicsRubberDam['sessionTitle'] ?? ($endoBasicsRubberDam['title'] ?? '')), 'کوییز ۴')
        && str_contains((string) ($endoBasicsRubberDam['sessionDetails'] ?? ''), 'رابردم')
        && ($endoBasicsRubberDam['instructor'] ?? '') === 'دکتر اسدیان',
    'Endodontics Foundations 2 keeps quiz 4, rubber-dam work and the named demonstrator together'
);

$healthTheory = classops_term7_syllabus_enrich_events([
    syllabus_event('oral-health-theory-2', 'سلامت دهان نظری ۲'),
], '1405/07/28');
syllabus_assert(
    array_column($healthTheory, 'sessionNumber') === [5, 6]
        && ($healthTheory[1]['sessionMode'] ?? '') === 'offline'
        && ($healthTheory[1]['location'] ?? 'x') === ''
        && ($healthTheory[1]['start'] ?? '') === '07:30'
        && ($healthTheory[1]['end'] ?? '') === '08:30',
    'Oral Health Theory preserves offline session with the explicit syllabus clock and no physical room'
);

$perio = classops_term7_syllabus_enrich_events([
    syllabus_event('periodontology-theory-1', 'پریو نظری ۱'),
], '1405/08/02');
syllabus_assert(
    array_column($perio, 'sessionNumber') === [6, 7]
        && ($perio[1]['sessionMode'] ?? '') === 'virtual'
        && ($perio[1]['start'] ?? '') === '07:30'
        && ($perio[1]['end'] ?? '') === '08:30',
    'Periodontology virtual row keeps the explicit 07:30-08:30 syllabus clock'
);

$boardRows = classops_term7_syllabus_enrich_events([
    syllabus_event('periodontology-theory-1', 'پریو نظری ۱'),
], '1405/09/28');
syllabus_assert(
    count($boardRows) === 1
        && ($boardRows[0]['sessionTitle'] ?? '') === 'آزمون بورد'
        && ($boardRows[0]['instructor'] ?? 'x') === '',
    'Missing instructor in source remains blank and is not replaced by course coordinator'
);

$healthPractical = $catalog['oral-health-practical-2']['sessions'][0] ?? [];
syllabus_assert(
    ($healthPractical['sessionNumber'] ?? null) === 1
        && ($healthPractical['dates'] ?? []) === ['1405/06/28', '1405/06/30', '1405/07/01'],
    'Oral Health Practical models one weekly session repeated across Saturday, Monday and Wednesday'
);
$healthVirtual = classops_term7_syllabus_enrich_events([
    syllabus_event('oral-health-practical-2', 'سلامت دهان عملی ۲', ''),
], '1405/07/11', 'A');
syllabus_assert(
    count($healthVirtual) === 1
        && ($healthVirtual[0]['sessionNumber'] ?? null) === 3
        && ($healthVirtual[0]['sessionMode'] ?? '') === 'virtual'
        && ($healthVirtual[0]['start'] ?? '') === '09:00'
        && ($healthVirtual[0]['end'] ?? '') === '12:00',
    'Oral Health Practical virtual week keeps the explicit 09:00-12:00 syllabus clock'
);

$healthField = $catalog['oral-health-practical-2']['sessions'][4] ?? [];
syllabus_assert(
    ($healthField['sessionNumber'] ?? null) === 5
        && ($healthField['title'] ?? '') === 'فیلد ۱ — نیازسنجی و ثبت شاخص کودکان دبستانی'
        && str_contains((string) ($healthField['sessionDetails'] ?? ''), 'فلورایدتراپی'),
    'Oral Health Practical keeps a concise scan-friendly title while preserving full source details'
);
foreach (['1405/06/28', '1405/06/30', '1405/07/01'] as $date) {
    $rows = classops_term7_syllabus_enrich_events([
        syllabus_event('oral-health-practical-2', 'سلامت دهان عملی ۲', ''),
    ], $date);
    syllabus_assert(
        count($rows) === 1
            && ($rows[0]['sessionNumber'] ?? null) === 1
            && str_contains((string) ($rows[0]['title'] ?? ''), 'جلسه 1'),
        "Oral Health Practical repeats session 1 metadata on {$date} without creating a new session number"
    );
}

foreach (['1405/08/23', '1405/08/25', '1405/08/27'] as $date) {
    $rows = classops_term7_syllabus_enrich_events([
        syllabus_event('oral-health-practical-2', 'سلامت دهان عملی ۲', ''),
    ], $date, 'B');
    syllabus_assert(
        count($rows) === 1
            && ($rows[0]['sessionNumber'] ?? null) === 1
            && ($rows[0]['instructor'] ?? '') === 'دکتر سرگران / دکتر پاکدامن',
        "Oral Health Practical repeats Rotation B week 1 as session 1 on {$date}"
    );
}
$healthRotationBWeek8 = classops_term7_syllabus_enrich_events([
    syllabus_event('oral-health-practical-2', 'سلامت دهان عملی ۲', ''),
], '1405/10/14', 'B');
syllabus_assert(
    count($healthRotationBWeek8) === 1
        && ($healthRotationBWeek8[0]['sessionNumber'] ?? null) === 8
        && str_contains((string) ($healthRotationBWeek8[0]['sessionTitle'] ?? ''), 'ارائه کار گروهی'),
    'Oral Health Practical maps Rotation B week 8 to source session 8'
);
$healthNoRotationGuess = classops_term7_syllabus_enrich_events([
    syllabus_event('oral-health-practical-2', 'سلامت دهان عملی ۲', ''),
], '1405/08/23');
syllabus_assert(
    count($healthNoRotationGuess) === 1 && !isset($healthNoRotationGuess[0]['sessionNumber']),
    'Rotation-relative syllabus metadata fails closed when rotation context is absent'
);

$partial = classops_term7_syllabus_enrich_events([
    syllabus_event('partial-basics-theory', 'مبانی پارسیل نظری'),
], '1405/09/04');
syllabus_assert(array_column($partial, 'sessionNumber') === [9, 10, 11], 'Existing Partial Theory same-date behavior is preserved by the shared registry');
syllabus_assert(count(array_unique(array_column($partial, 'sessionKey'))) === 3, 'Shared registry gives same-date sessions stable distinct keys');
syllabus_assert(
    ($partial[1]['sessionMode'] ?? '') === 'virtual'
        && ($partial[1]['start'] ?? '') === '07:30'
        && ($partial[1]['end'] ?? '') === '08:30'
        && ($partial[1]['location'] ?? 'x') === '',
    'Partial virtual session keeps the explicit 07:30-08:30 syllabus clock without physical room'
);

if ($failures > 0) {
    fwrite(STDERR, "Term 7 syllabus registry tests: {$checks}; failures: {$failures}\n");
    exit(1);
}
echo "Term 7 syllabus registry tests: {$checks}; failures: 0\n";
