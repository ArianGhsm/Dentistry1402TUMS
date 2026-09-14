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
        && ($orth[1]['location'] ?? 'x') === '',
    'Orthodontics shared-date virtual row is distinct and has no fabricated physical room'
);

$endo = classops_term7_syllabus_enrich_events([
    syllabus_event('endodontics-theory-1', 'اندو نظری ۱'),
], '1405/08/14');
syllabus_assert(array_column($endo, 'sessionNumber') === [11, 12], 'Endodontics preserves sessions 11 and 12 as distinct rows on one date');

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
        && ($diagQuiz[0]['sessionModeLabel'] ?? '') === 'حضوری + کوییز کلاسی',
    'Diagnostic quiz metadata is preserved on its dated occurrence'
);

$research = classops_term7_syllabus_enrich_events([
    syllabus_event('research-methods-2-practical', 'روش تحقیق ۲'),
], '1405/07/29');
syllabus_assert(count($research) === 2, 'Research Methodology keeps session 10 plus virtual session 11 on 1405/07/29');
syllabus_assert(
    ($research[0]['sessionNumber'] ?? null) === 10
        && ($research[1]['sessionNumber'] ?? null) === 11
        && ($research[1]['sessionMode'] ?? '') === 'virtual'
        && ($research[1]['location'] ?? 'x') === '',
    'Research Methodology virtual row is distinct without overriding canonical timetable recurrence'
);

$healthTheory = classops_term7_syllabus_enrich_events([
    syllabus_event('oral-health-theory-2', 'سلامت دهان نظری ۲'),
], '1405/07/28');
syllabus_assert(
    array_column($healthTheory, 'sessionNumber') === [5, 6]
        && ($healthTheory[1]['sessionMode'] ?? '') === 'offline'
        && ($healthTheory[1]['location'] ?? 'x') === '',
    'Oral Health Theory preserves same-date in-person and offline sessions separately'
);

$perio = classops_term7_syllabus_enrich_events([
    syllabus_event('periodontology-theory-1', 'پریو نظری ۱'),
], '1405/08/02');
syllabus_assert(
    array_column($perio, 'sessionNumber') === [6, 7]
        && ($perio[1]['sessionMode'] ?? '') === 'virtual',
    'Periodontology preserves the second 1405/08/02 row as a distinct virtual session'
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

$partial = classops_term7_syllabus_enrich_events([
    syllabus_event('partial-basics-theory', 'مبانی پارسیل نظری'),
], '1405/09/04');
syllabus_assert(array_column($partial, 'sessionNumber') === [9, 10, 11], 'Existing Partial Theory same-date behavior is preserved by the shared registry');
syllabus_assert(count(array_unique(array_column($partial, 'sessionKey'))) === 3, 'Shared registry gives same-date sessions stable distinct keys');

if ($failures > 0) {
    fwrite(STDERR, "Term 7 syllabus registry tests: {$checks}; failures: {$failures}\n");
    exit(1);
}
echo "Term 7 syllabus registry tests: {$checks}; failures: 0\n";
