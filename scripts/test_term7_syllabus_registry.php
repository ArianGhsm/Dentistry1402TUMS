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

function syllabus_virtual_session_numbers(array $sessions): array
{
    $numbers = [];
    foreach ($sessions as $session) {
        if (!is_array($session) || !in_array((string) ($session['sessionMode'] ?? ''), ['virtual', 'offline'], true)) continue;
        $sessionNumbers = is_array($session['sessionNumbers'] ?? null) ? $session['sessionNumbers'] : [];
        if ($sessionNumbers === [] && isset($session['sessionNumber']) && (int) $session['sessionNumber'] > 0) {
            $sessionNumbers = [(int) $session['sessionNumber']];
        }
        foreach ($sessionNumbers as $number) {
            $number = (int) $number;
            if ($number > 0) $numbers[$number] = true;
        }
    }
    $result = array_map('intval', array_keys($numbers));
    sort($result, SORT_NUMERIC);
    return $result;
}

function syllabus_event(
    string $slug,
    string $title,
    string $location = 'آمفی‌تئاتر ۹۰',
    string $start = '07:00',
    string $end = '08:00'
): array {
    return [
        'slug' => $slug,
        'title' => $title,
        'start' => $start,
        'end' => $end,
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
    'pathology-practical-1',
    'oral-health-theory-2',
    'periodontology-theory-1',
    'ent',
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
    'pathology-practical-1' => 12,
    'oral-health-theory-2' => 16,
    'periodontology-theory-1' => 17,
    'ent' => 12,
    'partial-basics-theory' => 15,
];
foreach ($counts as $key => $expected) {
    syllabus_assert(count($catalog[$key]['sessions'] ?? []) === $expected, "{$key} preserves {$expected} source rows/sessions");
}

$bookletCatalog = classops_term7_syllabus_booklet_catalog();
syllabus_assert(
    ($bookletCatalog['contractVersion'] ?? '') === 'term7-booklet-catalog-v1'
        && ($bookletCatalog['term'] ?? 0) === 7,
    'Booklet projection publishes the versioned Term 7 contract'
);
$bookletByKey = [];
foreach (($bookletCatalog['courses'] ?? []) as $course) {
    if (is_array($course)) $bookletByKey[(string) ($course['courseKey'] ?? '')] = $course;
}
$expectedBookletAliases = [
    'orthodontics-theory-1' => 'ارتودانتیکس_نظری۱',
    'endodontics-theory-1' => 'اندودانتیکس_نظری۱',
    'diagnostic-dentistry-3' => 'دندانپزشکی_تشخیصی۳',
    'research-methods-2' => 'روش_شناسی_تحقیق۲',
    'endodontics-basics-2' => 'مبانی_اندو۲',
    'oral-health-practical-2' => 'سلامت_عملی۲',
    'pathology-practical-1' => 'پاتو_عملی۱',
    'oral-health-theory-2' => 'سلامت_نظری۲',
    'ent' => 'گوش_حلق_و_بینی',
    'periodontology-theory-1' => 'پریودنتولوژی_نظری۱',
    'partial-basics-theory' => 'پروتز_پارسیل_نظری',
];
foreach ($expectedBookletAliases as $courseKey => $alias) {
    $course = $bookletByKey[$courseKey] ?? [];
    syllabus_assert(
        in_array($alias, $course['bookletTagAliases'] ?? [], true)
            && in_array($course['bookletTag'] ?? '', $course['bookletTagAliases'] ?? [], true),
        "Booklet catalog exposes canonical plus global alias for {$courseKey}"
    );
}
$researchBooklet = $bookletByKey['research-methods-2'] ?? [];
$researchNumbers = array_column($researchBooklet['sessions'] ?? [], 'sessionNumber');
syllabus_assert(
    $researchNumbers === range(1, 16)
        && ($researchBooklet['bookletTag'] ?? '') === 'روش_تحقیق۲',
    'Booklet projection exposes exactly the 16 numbered Research Methodology sessions and canonical hashtag'
);
$entBooklet = $bookletByKey['ent'] ?? [];
syllabus_assert(
    array_column($entBooklet['sessions'] ?? [], 'sessionNumber') === range(1, 12)
        && ($entBooklet['bookletTag'] ?? '') === 'گوش_حلق_بینی',
    'Booklet projection follows the canonical 12-session ENT syllabus instead of the old local sample'
);
$endoBooklet = $bookletByKey['endodontics-theory-1'] ?? [];
syllabus_assert(
    array_column($endoBooklet['sessions'] ?? [], 'sessionNumber') === range(1, 15),
    'Booklet projection expands multi-session Endodontics rows into individual session buttons'
);

$partialSession3 = $catalog['partial-basics-theory']['sessions'][2] ?? [];
syllabus_assert(
    ($partialSession3['sessionNumber'] ?? null) === 3
        && ($partialSession3['dates'] ?? []) === ['1405/07/15']
        && ($partialSession3['dateAmbiguous'] ?? true) === false
        && ($partialSession3['sessionMode'] ?? '') === 'virtual',
    'Partial session 3 follows the corrected PDF date and virtual modality'
);
syllabus_assert(
    syllabus_virtual_session_numbers($catalog['partial-basics-theory']['sessions'] ?? []) === [3, 4, 5, 10, 11, 14, 15],
    'Partial Theory exposes exactly seven virtual sessions from the corrected PDF'
);

$orthException = classops_term7_syllabus_enrich_events([
    syllabus_event('orthodontics-theory-1', 'ارتودنسی نظری ۱', 'آمفی‌تئاتر ۹۰', '12:40', '13:40'),
], '1405/07/13');
syllabus_assert(
    count($orthException) === 1
        && ($orthException[0]['sessionNumber'] ?? null) === 3
        && ($orthException[0]['sessionMode'] ?? '') === 'offline'
        && ($orthException[0]['sessionModeLabel'] ?? '') === 'مجازی (آفلاین)'
        && ($orthException[0]['location'] ?? 'x') === ''
        && ($orthException[0]['start'] ?? '') === '12:40'
        && ($orthException[0]['end'] ?? '') === '13:40',
    'Orthodontics 1405/07/13 applies the one-off offline virtual override'
);
$orthCatalogSession3 = $catalog['orthodontics-theory-1']['sessions'][2] ?? [];
syllabus_assert(
    ($orthCatalogSession3['sessionOverrideKind'] ?? '') === 'one_off'
        && ($orthCatalogSession3['sessionOverrideDate'] ?? '') === '1405/07/13'
        && ($orthCatalogSession3['sessionOverrideReason'] ?? '') !== '',
    'Orthodontics one-off modality is explicit and traceable in the canonical catalog'
);
syllabus_assert(
    syllabus_virtual_session_numbers($catalog['orthodontics-theory-1']['sessions'] ?? []) === [1, 3, 4, 6, 9, 10, 13, 14, 16],
    'Orthodontics exposes exactly the nine virtual sessions in the corrected PDF'
);
$orthVirtual10 = classops_term7_syllabus_enrich_events([
    syllabus_event('orthodontics-theory-1', 'ارتودنسی نظری ۱', 'آمفی‌تئاتر ۹۰', '12:40', '13:40'),
], '1405/08/25');
syllabus_assert(
    count($orthVirtual10) === 1
        && ($orthVirtual10[0]['sessionNumber'] ?? null) === 10
        && ($orthVirtual10[0]['sessionMode'] ?? '') === 'virtual'
        && ($orthVirtual10[0]['location'] ?? 'x') === ''
        && ($orthVirtual10[0]['start'] ?? '') === '12:40'
        && ($orthVirtual10[0]['end'] ?? '') === '13:40',
    'Orthodontics session 10 follows the corrected PDF virtual modality and keeps the timetable clock'
);
$orthVirtual16 = classops_term7_syllabus_enrich_events([
    syllabus_event('orthodontics-theory-1', 'ارتودنسی نظری ۱', 'آمفی‌تئاتر ۹۰', '12:40', '13:40'),
], '1405/10/06');
syllabus_assert(
    count($orthVirtual16) === 1
        && ($orthVirtual16[0]['sessionNumber'] ?? null) === 16
        && ($orthVirtual16[0]['sessionMode'] ?? '') === 'virtual'
        && ($orthVirtual16[0]['location'] ?? 'x') === ''
        && ($orthVirtual16[0]['start'] ?? '') === '12:40'
        && ($orthVirtual16[0]['end'] ?? '') === '13:40',
    'Orthodontics session 16 follows the corrected PDF virtual modality and keeps the timetable clock'
);

$orth = classops_term7_syllabus_enrich_events([
    syllabus_event('orthodontics-theory-1', 'ارتودنسی نظری ۱'),
], '1405/07/27');
syllabus_assert(count($orth) === 2, 'Orthodontics preserves in-person and virtual sessions sharing 1405/07/27');
syllabus_assert(
    array_column($orth, 'sessionNumber') === [5, 6]
        && ($orth[1]['sessionMode'] ?? '') === 'virtual'
        && ($orth[1]['location'] ?? 'x') === ''
        && ($orth[1]['start'] ?? '') === '07:00'
        && ($orth[1]['end'] ?? '') === '08:00'
        && ($orth[1]['timeSource'] ?? '') === 'academic-term7',
    'Orthodontics virtual row preserves the canonical timetable clock while omitting physical room'
);

$endo = classops_term7_syllabus_enrich_events([
    syllabus_event('endodontics-theory-1', 'اندو نظری ۱', 'آمفی‌تئاتر ۹۰', '13:50', '15:50'),
], '1405/08/11');
syllabus_assert(
    array_column($endo, 'sessionNumber') === [11, 12]
        && ($endo[0]['start'] ?? '') === '13:50'
        && ($endo[0]['end'] ?? '') === '15:50'
        && ($endo[1]['start'] ?? '') === '13:50'
        && ($endo[1]['end'] ?? '') === '15:50'
        && ($endo[0]['sourceDate'] ?? '') === '1405/08/14',
    'Endodontics preserves sessions 11 and 12 while canonical Monday timing replaces the source-PDF Thursday clock'
);
$endoVirtual = classops_term7_syllabus_enrich_events([
    syllabus_event('endodontics-theory-1', 'اندو نظری ۱', 'آمفی‌تئاتر ۹۰', '13:50', '15:50'),
], '1405/08/25');
syllabus_assert(
    count($endoVirtual) === 1
        && ($endoVirtual[0]['sessionNumber'] ?? null) === 15
        && ($endoVirtual[0]['sessionMode'] ?? '') === 'virtual'
        && ($endoVirtual[0]['sessionModeLabel'] ?? '') === 'مجازی (غیرحضوری ـ همیاد)'
        && ($endoVirtual[0]['location'] ?? 'x') === '',
    'Endodontics session 15 preserves the PDF non-presential Hamyaad modality'
);
syllabus_assert(
    syllabus_virtual_session_numbers($catalog['endodontics-theory-1']['sessions'] ?? []) === [15],
    'Endodontics Theory 1 exposes exactly one virtual session'
);

$endoSingle = classops_term7_syllabus_enrich_events([
    syllabus_event('endodontics-theory-1', 'اندو نظری ۱', 'آمفی‌تئاتر ۹۰', '13:50', '15:50'),
], '1405/07/06');
syllabus_assert(
    count($endoSingle) === 1
        && ($endoSingle[0]['sessionNumber'] ?? null) === 3
        && ($endoSingle[0]['start'] ?? '') === '13:50'
        && ($endoSingle[0]['end'] ?? '') === '15:50'
        && ($endoSingle[0]['sourceDate'] ?? '') === '1405/07/09',
    'Endodontics session metadata follows the canonical Monday occurrence without changing source provenance'
);


$diagnostic = $catalog['diagnostic-dentistry-3']['sessions'] ?? [];
syllabus_assert(
    array_column($diagnostic, 'sessionNumber') === range(1, 33)
        && count(array_filter($diagnostic, static fn(array $row): bool => ($row['assessmentPart'] ?? '') === 'میان‌ترم')) === 17
        && count(array_filter($diagnostic, static fn(array $row): bool => ($row['assessmentPart'] ?? '') === 'پایان‌ترم')) === 16,
    'Diagnostic Dentistry 3 keeps the source 17-session midterm and 16-session final split'
);
$diagnosticByDate = [];
foreach ($diagnostic as $row) {
    foreach (is_array($row['dates'] ?? null) ? $row['dates'] : [] as $date) {
        $diagnosticByDate[(string) $date] = $row;
    }
}
syllabus_assert(
    ($diagnosticByDate['1405/07/06']['title'] ?? '') === 'ضایعات واکنشی'
        && ($diagnosticByDate['1405/07/06']['instructor'] ?? '') === 'دکتر درخشان'
        && ($diagnosticByDate['1405/07/20']['title'] ?? '') === 'ضایعات سفید و قرمز'
        && ($diagnosticByDate['1405/07/20']['instructor'] ?? '') === 'دکتر منصوریان'
        && ($diagnosticByDate['1405/08/04']['title'] ?? '') === 'ضایعات خوش‌خیم اپیتلیالی'
        && ($diagnosticByDate['1405/08/04']['instructor'] ?? '') === 'دکتر مهدوی'
        && ($diagnosticByDate['1405/10/07']['title'] ?? '') === 'ضایعات خوش‌خیم مزانشیمی'
        && ($diagnosticByDate['1405/10/07']['instructor'] ?? '') === 'دکتر مرادزاده',
    'Diagnostic merged-cell boundaries preserve the first date of each instructor/topic block'
);
syllabus_assert(
    syllabus_virtual_session_numbers($diagnostic) === [6, 9, 10, 11, 15, 16, 17, 19, 20, 21, 22, 24, 25, 26, 27, 28],
    'Diagnostic Dentistry 3 exposes exactly sixteen virtual sessions'
);
$diagQuiz = classops_term7_syllabus_enrich_events([
    syllabus_event('diagnostic-dentistry-3-sun', 'دندانپزشکی تشخیصی ۳', 'آمفی‌تئاتر ۹۰', '07:00', '08:00'),
], '1405/07/12');
syllabus_assert(
    count($diagQuiz) === 1
        && ($diagQuiz[0]['sessionNumber'] ?? null) === 5
        && ($diagQuiz[0]['sessionModeLabel'] ?? '') === 'حضوری + کوییز کلاسی',
    'Diagnostic quiz remains on 1405/07/12 as stated in the corrected PDF'
);
$diagOffline = classops_term7_syllabus_enrich_events([
    syllabus_event('diagnostic-dentistry-3-mon', 'دندانپزشکی تشخیصی ۳', 'آمفی‌تئاتر ۹۰', '11:30', '12:30'),
], '1405/07/13');
syllabus_assert(
    count($diagOffline) === 1
        && ($diagOffline[0]['sessionNumber'] ?? null) === 6
        && ($diagOffline[0]['sessionMode'] ?? '') === 'offline'
        && ($diagOffline[0]['sessionModeLabel'] ?? '') === 'مجازی (آفلاین)'
        && ($diagOffline[0]['location'] ?? 'x') === ''
        && ($diagOffline[0]['start'] ?? '') === '11:30'
        && ($diagOffline[0]['end'] ?? '') === '12:30',
    'Diagnostic 1405/07/13 is offline virtual while preserving the canonical timetable clock'
);
$diagOnline = classops_term7_syllabus_enrich_events([
    syllabus_event('diagnostic-dentistry-3-mon', 'دندانپزشکی تشخیصی ۳', 'آمفی‌تئاتر ۹۰', '11:30', '12:30'),
], '1405/09/16');
syllabus_assert(
    count($diagOnline) === 1
        && ($diagOnline[0]['sessionNumber'] ?? null) === 24
        && ($diagOnline[0]['sessionMode'] ?? '') === 'virtual'
        && ($diagOnline[0]['sessionModeLabel'] ?? '') === 'مجازی (آنلاین)'
        && str_contains((string) ($diagOnline[0]['title'] ?? ''), 'مجازی (آنلاین)'),
    'Diagnostic online modality remains explicit in the final user-facing title'
);
$timingOwners = array_filter(
    $catalog,
    static fn(array $course): bool => array_key_exists('sourceTiming', $course)
);
syllabus_assert(
    $timingOwners === [],
    'Syllabus registry contains no competing timetable clock source'
);

$research = classops_term7_syllabus_enrich_events([
    syllabus_event('research-methods-2-practical', 'روش تحقیق ۲', 'آمفی‌تئاتر ۹۰', '11:45', '14:15'),
], '1405/07/29');
syllabus_assert(count($research) === 2, 'Research Methodology keeps session 10 plus virtual session 11 on 1405/07/29');
syllabus_assert(
    ($research[0]['sessionNumber'] ?? null) === 10
        && ($research[0]['start'] ?? '') === '11:45'
        && ($research[0]['end'] ?? '') === '14:15'
        && ($research[1]['sessionNumber'] ?? null) === 11
        && ($research[1]['sessionMode'] ?? '') === 'virtual'
        && ($research[1]['location'] ?? 'x') === ''
        && ($research[1]['start'] ?? '') === '11:45'
        && ($research[1]['end'] ?? '') === '14:15',
    'Research Methodology metadata preserves the canonical 11:45-14:15 clock for every row'
);

$researchWithSupplement = classops_term7_syllabus_enrich_events([
    syllabus_event('research-methods-2-practical', 'روش تحقیق ۲', 'آمفی‌تئاتر ۹۰', '11:45', '14:15'),
], '1405/07/08', 'A');
syllabus_assert(
    count($researchWithSupplement) === 2
        && ($researchWithSupplement[0]['sessionNumber'] ?? null) === 4
        && ($researchWithSupplement[1]['sessionLabel'] ?? '') === 'محتوای تکمیلی جلسه ۴',
    'Research Methodology keeps the numbered session before its supplemental row'
);

$researchRotationB = classops_term7_syllabus_enrich_events([
    syllabus_event('research-methods-2-practical', 'روش تحقیق ۲', 'آمفی‌تئاتر ۹۰', '11:45', '14:15'),
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
    syllabus_event('endodontics-basics-2', 'مبانی اندو ۲', 'پری‌کلینیک منفی ۲', '11:45', '14:15'),
], '1405/07/12', 'A');
syllabus_assert(
    count($endoBasicsQuiz) === 1
        && ($endoBasicsQuiz[0]['sessionNumber'] ?? null) === 5
        && str_contains((string) ($endoBasicsQuiz[0]['sessionTitle'] ?? ''), 'کوییز ۱')
        && ($endoBasicsQuiz[0]['instructor'] ?? '') === 'دکتر ملک پور'
        && ($endoBasicsQuiz[0]['start'] ?? '') === '11:45'
        && ($endoBasicsQuiz[0]['end'] ?? '') === '14:15'
        && ($endoBasicsQuiz[0]['timeSource'] ?? '') === 'academic-term7',
    'Endodontics Foundations 2 enriches metadata without replacing the canonical practical clock'
);
$endoBasicsPractice = classops_term7_syllabus_enrich_events([
    syllabus_event('endodontics-basics-2', 'مبانی اندو ۲', 'پری‌کلینیک منفی ۲', '11:45', '14:15'),
], '1405/07/14', 'A');
syllabus_assert(
    count($endoBasicsPractice) === 1
        && ($endoBasicsPractice[0]['sessionNumber'] ?? null) === 6
        && ($endoBasicsPractice[0]['instructor'] ?? 'x') === '',
    'Endodontics Foundations 2 practice rows keep the source instructor blank'
);
$endoBasicsRotationB = classops_term7_syllabus_enrich_events([
    syllabus_event('endodontics-basics-2', 'مبانی اندو ۲', 'پری‌کلینیک منفی ۲', '11:45', '14:15'),
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

syllabus_assert(
    classops_term7_syllabus_source_occurrence_decision('pathology-practical-1', '1405/06/29', 'A') === false
        && classops_term7_syllabus_source_occurrence_decision('pathology-practical-1', '1405/07/05', 'A') === true
        && classops_term7_syllabus_source_occurrence_decision('pathology-practical-1', '1405/08/17', 'A') === false
        && classops_term7_syllabus_source_occurrence_decision('pathology-practical-1', '1405/09/02', 'B') === null,
    'Pathology source dates authoritatively gate Rotation A only and do not invent Rotation B occurrence rules'
);

$pathologyRows = $catalog['pathology-practical-1']['sessions'] ?? [];
syllabus_assert(
    array_column($pathologyRows, 'sessionNumber') === [1,2,3,4,5,6,7,8,9,10,11,12]
        && ($pathologyRows[0]['dates'] ?? []) === ['1405/07/05']
        && ($pathologyRows[11]['dates'] ?? []) === ['1405/08/12']
        && array_column($pathologyRows, 'title') === [
            'گرانول فوردایس – لکوادما',
            'گرانولوم نوک ریشه – کیست رادیکولار',
            'ادنتوژنیک کراتوسیست – کیست گورلین',
            'آملوبلاستوما – یونی‌سیستیک آملوبلاستوما',
            'آملوبلاستیک فیبروما – میکسوما',
            'مرور',
            'ادنوماتوئید ادنتوژنیک تومور – تومور پیندبورگ',
            'ادنتوم – استئومیلیت',
            'هیپرکراتوز – لیکن پلان',
            'پمفیگوس – پمفیگوئید',
            'مرور',
            'امتحان',
        ]
        && array_column($pathologyRows, 'resident') === [
            '', 'دکتر صبوری', 'دکتر صبوری', 'دکتر صبوری', 'دکتر صبوری', 'دکتر صبوری',
            'دکتر صبوری', 'دکتر صبوری', 'دکتر صبوری', 'دکتر صبوری', 'دکتر صبوری', '',
        ],
    'Pathology Practical 1 preserves the corrected 12-row schedule, titles and resident responsibility'
);
$pathologyFirst = classops_term7_syllabus_enrich_events([[
    'slug' => 'pathology-practical-1',
    'title' => 'آسیب‌شناسی عملی ۱',
    'start' => '08:15',
    'end' => '11:15',
    'location' => '',
]], '1405/07/05', 'A');
syllabus_assert(
    count($pathologyFirst) === 1
        && ($pathologyFirst[0]['sessionNumber'] ?? null) === 1
        && ($pathologyFirst[0]['sessionTitle'] ?? '') === 'گرانول فوردایس – لکوادما'
        && ($pathologyFirst[0]['instructor'] ?? '') === 'دکتر درخشان'
        && ($pathologyFirst[0]['resident'] ?? 'x') === ''
        && ($pathologyFirst[0]['start'] ?? '') === '08:15'
        && ($pathologyFirst[0]['end'] ?? '') === '11:15'
        && ($pathologyFirst[0]['timeSource'] ?? '') === 'academic-term7',
    'Pathology Practical 1 enriches Rotation A metadata while preserving the canonical practical clock'
);
$pathologyReview = classops_term7_syllabus_enrich_events([[
    'slug' => 'pathology-practical-1',
    'title' => 'آسیب‌شناسی عملی ۱',
    'start' => '08:15',
    'end' => '11:15',
    'location' => '',
]], '1405/07/21', 'A');
syllabus_assert(
    count($pathologyReview) === 1
        && ($pathologyReview[0]['sessionNumber'] ?? null) === 6
        && ($pathologyReview[0]['sessionTitle'] ?? '') === 'مرور'
        && ($pathologyReview[0]['sourceTitle'] ?? '') === 'Review'
        && ($pathologyReview[0]['instructor'] ?? 'x') === ''
        && ($pathologyReview[0]['resident'] ?? '') === 'دکتر صبوری',
    'Pathology review row is Persian in the UI while preserving source label, blank instructor and resident'
);
$pathologyExam = classops_term7_syllabus_enrich_events([[
    'slug' => 'pathology-practical-1',
    'title' => 'آسیب‌شناسی عملی ۱',
    'start' => '08:15',
    'end' => '11:15',
    'location' => '',
]], '1405/08/12', 'A');
syllabus_assert(
    count($pathologyExam) === 1
        && ($pathologyExam[0]['sessionNumber'] ?? null) === 12
        && ($pathologyExam[0]['sessionTitle'] ?? '') === 'امتحان'
        && ($pathologyExam[0]['resident'] ?? 'x') === '',
    'Pathology source exam is corrected to row/session 12 on 1405/08/12 with no resident'
);
$pathologyRotationB = classops_term7_syllabus_enrich_events([[
    'slug' => 'pathology-practical-1',
    'title' => 'آسیب‌شناسی عملی ۱',
    'start' => '08:15',
    'end' => '11:15',
    'location' => '',
]], '1405/09/01', 'B');
syllabus_assert(
    count($pathologyRotationB) === 1
        && !isset($pathologyRotationB[0]['sessionNumber']),
    'Pathology Practical 1 does not infer Rotation B metadata from the Rotation A-only source'
);
$pathologyBooklet = $bookletByKey['pathology-practical-1'] ?? [];
syllabus_assert(
    array_column($pathologyBooklet['sessions'] ?? [], 'sessionNumber') === [1,2,3,4,5,6,7,8,9,10,11,12]
        && ($pathologyBooklet['sessions'][1]['resident'] ?? '') === 'دکتر صبوری'
        && ($pathologyBooklet['bookletTag'] ?? '') === 'آسیب_شناسی_عملی۱',
    'Booklet projection exposes the corrected Pathology Practical 1 numbering and resident metadata'
);

$healthTheory = classops_term7_syllabus_enrich_events([
    syllabus_event('oral-health-theory-2', 'سلامت دهان نظری ۲'),
], '1405/07/28');
syllabus_assert(
    array_column($healthTheory, 'sessionNumber') === [5, 6]
        && ($healthTheory[1]['sessionMode'] ?? '') === 'offline'
        && ($healthTheory[1]['location'] ?? 'x') === ''
        && ($healthTheory[1]['start'] ?? '') === '07:00'
        && ($healthTheory[1]['end'] ?? '') === '08:00',
    'Oral Health Theory preserves the canonical timetable clock and omits physical room for offline content'
);
syllabus_assert(
    syllabus_virtual_session_numbers($catalog['oral-health-theory-2']['sessions'] ?? []) === [6, 8, 9, 10, 14, 16]
        && ($catalog['oral-health-theory-2']['modalityCorrectionSource'] ?? '') === 'نامه گروه آموزش سلامت دهان و دندان مورخ ۱۴۰۵/۰۷/۱۴',
    'Oral Health Theory records the letter-backed virtual sessions in the canonical catalog'
);
$healthTheoryVirtual8 = classops_term7_syllabus_enrich_events([
    syllabus_event('oral-health-theory-2', 'سلامت دهان نظری ۲', 'آمفی‌تئاتر ۹۰', '07:00', '08:00'),
], '1405/08/12');
syllabus_assert(
    count($healthTheoryVirtual8) === 1
        && ($healthTheoryVirtual8[0]['sessionNumber'] ?? null) === 8
        && ($healthTheoryVirtual8[0]['sessionTitle'] ?? '') === 'درمان‌های محافظه‌کارانه'
        && ($healthTheoryVirtual8[0]['instructor'] ?? '') === 'دکتر افسانه پاکدامن'
        && ($healthTheoryVirtual8[0]['sessionMode'] ?? '') === 'virtual'
        && ($healthTheoryVirtual8[0]['location'] ?? 'x') === ''
        && ($healthTheoryVirtual8[0]['start'] ?? '') === '07:00'
        && ($healthTheoryVirtual8[0]['end'] ?? '') === '08:00',
    'Oral Health Theory session 8 is virtual on 1405/08/12 with the letter-backed title and instructor'
);
$healthTheoryVirtual9 = classops_term7_syllabus_enrich_events([
    syllabus_event('oral-health-theory-2', 'سلامت دهان نظری ۲', 'آمفی‌تئاتر ۹۰', '07:00', '08:00'),
], '1405/08/19');
syllabus_assert(
    count($healthTheoryVirtual9) === 2
        && ($healthTheoryVirtual9[0]['sessionNumber'] ?? null) === 9
        && ($healthTheoryVirtual9[0]['sessionTitle'] ?? '') === 'پیشگیری از صدمات تروماتیک دندانی'
        && ($healthTheoryVirtual9[0]['instructor'] ?? '') === 'دکتر سمانه رازقی'
        && ($healthTheoryVirtual9[0]['sessionMode'] ?? '') === 'virtual'
        && ($healthTheoryVirtual9[0]['location'] ?? 'x') === ''
        && ($healthTheoryVirtual9[0]['start'] ?? '') === '07:00'
        && ($healthTheoryVirtual9[0]['end'] ?? '') === '08:00'
        && ($healthTheoryVirtual9[1]['sessionNumber'] ?? null) === 10
        && ($healthTheoryVirtual9[1]['sessionMode'] ?? '') === 'offline',
    'Oral Health Theory 1405/08/19 keeps session 9 virtual plus the existing offline session 10'
);

$entVirtual = classops_term7_syllabus_enrich_events([
    syllabus_event('ent', 'گوش و حلق و بینی'),
], '1405/07/06');
syllabus_assert(
    count($entVirtual) === 1
        && ($entVirtual[0]['sessionNumber'] ?? null) === 1
        && ($entVirtual[0]['sessionTitle'] ?? '') === 'اصول معاینه در گوش و حلق و بینی'
        && ($entVirtual[0]['instructor'] ?? '') === 'دکتر سعید گل پروران'
        && ($entVirtual[0]['sessionMode'] ?? '') === 'virtual'
        && ($entVirtual[0]['location'] ?? 'x') === ''
        && ($entVirtual[0]['start'] ?? '') === '07:00'
        && ($entVirtual[0]['end'] ?? '') === '08:00',
    'ENT first virtual session uses source metadata while preserving the canonical timetable clock'
);
$entInPerson = classops_term7_syllabus_enrich_events([
    syllabus_event('ent', 'گوش و حلق و بینی'),
], '1405/07/20');
syllabus_assert(
    count($entInPerson) === 1
        && ($entInPerson[0]['sessionNumber'] ?? null) === 3
        && ($entInPerson[0]['sessionTitle'] ?? '') === 'آنومالی‌های مادرزادی گردن'
        && ($entInPerson[0]['instructor'] ?? '') === 'دکتر سارا رهاوی'
        && ($entInPerson[0]['sessionMode'] ?? '') === 'in_person'
        && ($entInPerson[0]['location'] ?? '') === 'آمفی‌تئاتر ۹۰'
        && ($entInPerson[0]['start'] ?? '') === '07:00'
        && ($entInPerson[0]['end'] ?? '') === '08:00',
    'ENT shaded source row is in-person and keeps the canonical timetable clock and room'
);
$entRows = $catalog['ent']['sessions'] ?? [];
syllabus_assert(
    array_column($entRows, 'sessionNumber') === range(1, 12)
        && count(array_filter($entRows, static fn(array $row): bool => ($row['sessionMode'] ?? '') === 'in_person')) === 5
        && count(array_filter($entRows, static fn(array $row): bool => ($row['sessionMode'] ?? '') === 'virtual')) === 7
        && ($entRows[11]['dates'] ?? []) === ['1405/09/23'],
    'ENT preserves all 12 source rows and the five shaded in-person sessions'
);

$perio = classops_term7_syllabus_enrich_events([
    syllabus_event('periodontology-theory-1', 'پریو نظری ۱'),
], '1405/08/02');
syllabus_assert(
    array_column($perio, 'sessionNumber') === [6, 7]
        && ($perio[1]['sessionMode'] ?? '') === 'virtual'
        && ($perio[1]['start'] ?? '') === '07:00'
        && ($perio[1]['end'] ?? '') === '08:00',
    'Periodontology virtual row keeps the canonical 07:00-08:00 timetable clock'
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
    syllabus_event('oral-health-practical-2', 'سلامت دهان عملی ۲', '', '08:15', '11:15'),
], '1405/07/11', 'A');
syllabus_assert(
    count($healthVirtual) === 1
        && ($healthVirtual[0]['sessionNumber'] ?? null) === 3
        && ($healthVirtual[0]['sessionMode'] ?? '') === 'virtual'
        && ($healthVirtual[0]['start'] ?? '') === '08:15'
        && ($healthVirtual[0]['end'] ?? '') === '11:15',
    'Oral Health Practical virtual week keeps the canonical 08:15-11:15 timetable clock'
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
        syllabus_event('oral-health-practical-2', 'سلامت دهان عملی ۲', '', '08:15', '11:15'),
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
        syllabus_event('oral-health-practical-2', 'سلامت دهان عملی ۲', '', '08:15', '11:15'),
    ], $date, 'B');
    syllabus_assert(
        count($rows) === 1
            && ($rows[0]['sessionNumber'] ?? null) === 1
            && ($rows[0]['instructor'] ?? '') === 'دکتر سرگران / دکتر پاکدامن',
        "Oral Health Practical repeats Rotation B week 1 as session 1 on {$date}"
    );
}
$healthRotationBWeek8 = classops_term7_syllabus_enrich_events([
    syllabus_event('oral-health-practical-2', 'سلامت دهان عملی ۲', '', '08:15', '11:15'),
], '1405/10/14', 'B');
syllabus_assert(
    count($healthRotationBWeek8) === 1
        && ($healthRotationBWeek8[0]['sessionNumber'] ?? null) === 8
        && str_contains((string) ($healthRotationBWeek8[0]['sessionTitle'] ?? ''), 'ارائه کار گروهی'),
    'Oral Health Practical maps Rotation B week 8 to source session 8'
);
$healthNoRotationGuess = classops_term7_syllabus_enrich_events([
    syllabus_event('oral-health-practical-2', 'سلامت دهان عملی ۲', '', '08:15', '11:15'),
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
        && ($partial[1]['start'] ?? '') === '07:00'
        && ($partial[1]['end'] ?? '') === '08:00'
        && ($partial[1]['location'] ?? 'x') === '',
    'Partial virtual session keeps the canonical 07:00-08:00 timetable clock without physical room'
);

if ($failures > 0) {
    fwrite(STDERR, "Term 7 syllabus registry tests: {$checks}; failures: {$failures}\n");
    exit(1);
}
echo "Term 7 syllabus registry tests: {$checks}; failures: 0\n";
