<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../public_html/api/bot_store.php';
require_once __DIR__ . '/../public_html/api/academic_term7_booklet_system.php';
restore_error_handler();
restore_exception_handler();

function booklet_import_groups(): array
{
    return [
        1 => ['فاطمه مرجانی', 'کوثر حاجیان نژاد', 'مهدیه دهقانی'],
        2 => ['وستا ایزدی', 'فاطمه فتحی', 'زهرا کریمی'],
        3 => ['امیرحسین علوی', 'امیرمهدی طاهری', 'محمدرضا یگانه'],
        4 => ['هومن چاوشی فر', 'محمدحسین رضازاده', 'عماد ریاست', 'نسترن طبسی'],
        5 => ['مانی موسوی'],
        6 => ['سحر سلیمانی', 'فاطمه موسوی زاده', 'مبینا لطفی'],
        7 => ['عسل عزیزمحمدی', 'درسا ابراهیم زاده', 'نازنین ختائی'],
        8 => ['محمدرضا باجلان', 'آرمان اعوانی'],
        9 => ['آنیتا شیرخانی'],
        10 => ['امیرمحمد قلندری', 'حسین محمدی'],
        11 => ['احسان فضلی', 'حسین آهنگ', 'بهاره ابراهیمی'],
        12 => ['علی باطبی', 'امیرحسین حاتمی'],
        13 => ['امیررضا شادمهر', 'امیرحسین درواری', 'مهدی رستمی', 'امیرمحمد امجدی'],
        14 => ['هستی غلامی'],
        15 => ['شیدا سادات کریمی', 'کوثر قربانی', 'بهاره سادات مهدیقلی'],
        16 => ['فاطمه صابری', 'فاطمه عابدی', 'فاطمه زین العابدینی'],
        17 => ['آرین قاسم پور'],
        18 => ['شقایق صمدیان', 'آیلین هاشمی', 'آرین تقوی'],
        19 => ['ثنا مهدوی', 'رضوانه کاظمی مقدم', 'سارینا صادق پور'],
        20 => ['ایلیا خان محمدی'],
        21 => ['سحر جهانزاد'],
        22 => ['علی سینا امیری', 'محمدمهدی زارع'],
        23 => ['آتنا یوسف زاده', 'محدثه رستملو', 'رژین جباری'],
        24 => ['مهدی اسماعیلی'],
        25 => ['محمدرضا حسینی جی'],
        26 => ['محدثه هدایتی نسب', 'صبا مالکی'],
        27 => ['محمدجواد نصر'],
        28 => ['بشری محمودی', 'ریحانه حقیقی', 'مریم قنبری'],
        29 => ['عرفان محبوبی نیا'],
        30 => ['شقایق باقری'],
        31 => ['مهدی پورهاشمی'],
    ];
}

function booklet_import_course_groups(): array
{
    return [
        'orthodontics-theory-1' => [8, 21, 28, 29],
        'partial-basics-theory' => [6, 15, 19, 22],
        'endodontics-theory-1' => [3, 10, 22, 31],
        'diagnostic-dentistry-3-khanmohammadi' => [1, 13, 20, 26],
        'periodontology-theory-1' => [2, 12, 14, 25],
        'endodontics-basics-2' => [12, 15],
        'oral-health-theory-2' => [4, 5, 16, 17],
        'research-methods-2' => [7, 18, 24, 30],
        'ent' => [11, 24, 30],
        'diagnostic-dentistry-3-nasr' => [9, 23, 27, 28],
    ];
}

function booklet_import_manager_names(): array
{
    return [
        'سحر جهانزاد' => ['orthodontics-theory-1'],
        'علی سینا امیری' => ['partial-basics-theory', 'endodontics-theory-1'],
        'ایلیا خان محمدی' => ['diagnostic-dentistry-3-khanmohammadi'],
        'بردیا باطبی' => ['periodontology-theory-1', 'endodontics-basics-2'],
        'امیرحسین حاتمی' => ['periodontology-theory-1', 'endodontics-basics-2'],
        'مانی موسوی' => ['oral-health-theory-2'],
        'مهدی اسماعیلی' => ['research-methods-2', 'ent'],
        'محمدجواد نصر' => ['diagnostic-dentistry-3-nasr'],
    ];
}

function booklet_import_special_roles(): array
{
    return [
        'حسین شاهسواری' => [[
            'key' => DENT_TERM7_BOOKLET_INFOGRAPHIC_ROLE,
            'label' => 'مسئول اینفوگرافیک',
            'grantsFreeSubscription' => true,
        ]],
    ];
}

function booklet_import_extra_aliases(): array
{
    $pairs = [
        'فاطمه مرجانی' => 'فاطمه سادات مرجانی',
        'مهدیه دهقانی' => 'مهدیه دهقانی نیری',
        'امیرحسین علوی' => 'سید امیرحسین علوی',
        'فاطمه موسوی زاده' => 'فاطمه سادات موسی زاده',
        'مبینا لطفی' => 'مبینالطفی',
        'بهاره سادات مهدیقلی' => 'مهدیقلی بهاره سادات',
        'سحر جهانزاد' => 'سحر جهان زاد',
    ];
    $out = [];
    foreach ($pairs as $input => $canonical) {
        $out[dent_term7_normalize_person_name($input)] = dent_term7_normalize_person_name($canonical);
    }
    return $out;
}

function booklet_import_name_key(string $name): array
{
    $normalized = dent_term7_normalize_person_name($name);
    $extra = booklet_import_extra_aliases();
    if ($normalized !== '' && isset($extra[$normalized])) {
        return ['key' => $extra[$normalized], 'matchedBy' => 'bookletAlias'];
    }
    return dent_term7_import_person_name_key($name);
}

function booklet_import_resolve(string $name, array $nameIndex): array
{
    $match = booklet_import_name_key($name);
    $candidates = $match['key'] !== '' ? ($nameIndex[$match['key']] ?? []) : [];
    return [
        'input' => $name,
        'normalized' => (string) $match['key'],
        'matchedBy' => (string) $match['matchedBy'],
        'candidates' => array_values($candidates),
    ];
}

$options = getopt('', ['commit']);
$commit = array_key_exists('commit', $options);
$store = dent_load_user_store();
$nameIndex = [];
$directory = [];
foreach (is_array($store['users'] ?? null) ? $store['users'] : [] as $studentNumberRaw => $user) {
    if (!is_array($user)) continue;
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $studentNumberRaw));
    $name = dent_term7_normalize_person_name((string) ($user['name'] ?? ''));
    if ($studentNumber === '' || $name === '') continue;
    $nameIndex[$name][] = $studentNumber;
    $directory[$studentNumber] = $user;
}

$report = [
    'contractVersion' => 'term7-booklet-import-v1',
    'committed' => false,
    'matched' => [],
    'unmatched' => [],
    'ambiguous' => [],
    'duplicates' => [],
    'managerMatched' => [],
    'specialRoleMatched' => [],
];
$seen = [];
$state = dent_term7_booklet_system_default();

foreach (booklet_import_groups() as $group => $names) {
    $resolvedMembers = [];
    $leaderStudentNumber = '';
    foreach ($names as $index => $name) {
        $result = booklet_import_resolve($name, $nameIndex);
        $candidates = $result['candidates'];
        if (count($candidates) === 0) {
            $report['unmatched'][] = ['group' => $group, 'name' => $name];
            continue;
        }
        if (count($candidates) > 1) {
            $report['ambiguous'][] = ['group' => $group, 'name' => $name, 'candidates' => $candidates];
            continue;
        }
        $studentNumber = (string) $candidates[0];
        if (isset($seen[$studentNumber])) {
            $report['duplicates'][] = ['group' => $group, 'name' => $name, 'studentNumber' => $studentNumber];
            continue;
        }
        $seen[$studentNumber] = true;
        $resolvedMembers[] = $studentNumber;
        if ($index === 0) $leaderStudentNumber = $studentNumber;
        $report['matched'][] = [
            'group' => $group,
            'studentNumber' => $studentNumber,
            'name' => (string) ($directory[$studentNumber]['name'] ?? $name),
            'leader' => $index === 0,
            'matchedBy' => $result['matchedBy'],
            'cohortKey' => dent_user_cohort_key($directory[$studentNumber] ?? []),
        ];
    }
    $state['groups'][(string) $group] = [
        'group' => $group,
        'leaderStudentNumber' => $leaderStudentNumber,
        'members' => $resolvedMembers,
        'updatedAt' => dent_iso_now(),
    ];
}

$state['courseGroups'] = booklet_import_course_groups();

foreach (booklet_import_manager_names() as $name => $courseKeys) {
    $result = booklet_import_resolve($name, $nameIndex);
    $candidates = $result['candidates'];
    if (count($candidates) === 0) {
        $report['unmatched'][] = ['role' => 'manager', 'name' => $name];
        continue;
    }
    if (count($candidates) > 1) {
        $report['ambiguous'][] = ['role' => 'manager', 'name' => $name, 'candidates' => $candidates];
        continue;
    }
    $studentNumber = (string) $candidates[0];
    $existing = is_array($state['managers'][$studentNumber] ?? null) ? $state['managers'][$studentNumber] : [
        'studentNumber' => $studentNumber,
        'courseKeys' => [],
        'updatedAt' => dent_iso_now(),
    ];
    $existing['courseKeys'] = array_values(array_unique(array_merge($existing['courseKeys'], $courseKeys)));
    $state['managers'][$studentNumber] = $existing;
    $report['managerMatched'][] = [
        'studentNumber' => $studentNumber,
        'name' => (string) ($directory[$studentNumber]['name'] ?? $name),
        'courseKeys' => $courseKeys,
        'matchedBy' => $result['matchedBy'],
    ];
}

foreach (booklet_import_special_roles() as $name => $roles) {
    $result = booklet_import_resolve($name, $nameIndex);
    $candidates = $result['candidates'];
    if (count($candidates) === 0) {
        $report['unmatched'][] = ['role' => 'special', 'name' => $name];
        continue;
    }
    if (count($candidates) > 1) {
        $report['ambiguous'][] = ['role' => 'special', 'name' => $name, 'candidates' => $candidates];
        continue;
    }
    $studentNumber = (string) $candidates[0];
    $state['specialRoles'][$studentNumber] = $roles;
    $report['specialRoleMatched'][] = [
        'studentNumber' => $studentNumber,
        'name' => (string) ($directory[$studentNumber]['name'] ?? $name),
        'roles' => $roles,
    ];
}

$state['sourceMeta'] = [
    'title' => 'ساختار جزوه‌نویسی ترم ۷',
    'importedAt' => dent_iso_now(),
    'updatedAt' => dent_iso_now(),
];
$state = dent_term7_booklet_system_normalize($state);

$classCount = 0;
$classBookletMembers = 0;
foreach ($directory as $studentNumberRaw => $user) {
    $studentNumber = (string) $studentNumberRaw;
    if (dent_user_cohort_key($user) !== DENT_TERM7_COHORT) continue;
    $classCount++;
    if (is_int(dent_term7_booklet_membership($studentNumber, $state)['group'] ?? null)) {
        $classBookletMembers++;
    }
}
$freeNumbers = [];
foreach ($state['groups'] as $record) {
    foreach ($record['members'] as $studentNumber) $freeNumbers[$studentNumber] = true;
}
foreach ($state['specialRoles'] as $studentNumber => $roles) {
    foreach ($roles as $role) {
        if (!empty($role['grantsFreeSubscription'])) {
            $freeNumbers[$studentNumber] = true;
            break;
        }
    }
}

$classFreeEligible = 0;
foreach ($directory as $studentNumberRaw => $user) {
    $studentNumber = (string) $studentNumberRaw;
    if (dent_user_cohort_key($user) !== DENT_TERM7_COHORT) continue;
    $profile = dent_term7_booklet_public_profile($studentNumber, $state, $directory);
    if (!empty($profile['freeSubscriptionEligible'])) $classFreeEligible++;
}
$report['summary'] = [
    'groups' => count($state['groups']),
    'bookletMembers' => count($seen),
    'classCount' => $classCount,
    'classBookletMembers' => $classBookletMembers,
    'classOutsideBookletGroups' => $classCount - $classBookletMembers,
    'classFreeEligible' => $classFreeEligible,
    'classPaidMembers' => $classCount - $classFreeEligible,
    'managers' => count($state['managers']),
    'freeEligible' => count($freeNumbers),
];

if (
    $commit
    && $report['unmatched'] === []
    && $report['ambiguous'] === []
    && $report['duplicates'] === []
) {
    dent_term7_booklet_system_with_lock(static function (array &$locked) use ($state): array {
        $locked = $state;
        return [];
    });
    $report['committed'] = true;
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(
    $report['unmatched'] === [] && $report['ambiguous'] === [] && $report['duplicates'] === []
        ? 0
        : 1
);
