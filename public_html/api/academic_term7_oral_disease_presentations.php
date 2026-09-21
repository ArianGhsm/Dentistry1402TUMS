<?php
declare(strict_types=1);

const DENT_TERM7_ORAL_DISEASE_PRESENTATION_SCHEMA = 1;
const DENT_TERM7_ORAL_DISEASE_PRESENTATION_VERSION = '1405-rotation2-v1';
const DENT_TERM7_ORAL_DISEASE_PRESENTATION_SOURCE = 'oral-disease-presentation-schedule';

function dent_term7_oral_disease_presentation_state_path(): string
{
    return dent_storage_path('academic/term7-1405-1406-oral-disease-presentations.json');
}

function dent_term7_oral_disease_presentation_source_schedule(): array
{
    return [
        ['group' => 10, 'date' => '1405/07/04', 'weekdayLabel' => 'شنبه', 'topic' => 'Basic lesion', 'presenters' => ['آهنگ']],
        ['group' => 10, 'date' => '1405/07/11', 'weekdayLabel' => 'شنبه', 'topic' => 'آفت راجعه و سندرم بهجت', 'presenters' => ['درواری']],
        ['group' => 10, 'date' => '1405/07/18', 'weekdayLabel' => 'شنبه', 'topic' => 'زخم مزمن منفرد (SCC و زخم تروماتیک)', 'presenters' => ['زارعی']],
        ['group' => 10, 'date' => '1405/07/25', 'weekdayLabel' => 'شنبه', 'topic' => 'لیکن پلان و لیکنویید ری‌اکشن', 'presenters' => ['شاهنده']],
        ['group' => 10, 'date' => '1405/07/28', 'weekdayLabel' => 'سه‌شنبه', 'topic' => 'لکوپلاکیا و اریترولکوپلاکیا', 'presenters' => ['موسی زاده فاطمه']],
        ['group' => 10, 'date' => '1405/08/02', 'weekdayLabel' => 'شنبه', 'topic' => 'ضایعات مربوط به لب', 'presenters' => ['فضلی']],
        ['group' => 10, 'date' => '1405/08/05', 'weekdayLabel' => 'سه‌شنبه', 'topic' => 'ضایعات مربوط به زبان', 'presenters' => ['موسی زاده ابوالفضل']],
        ['group' => 10, 'date' => '1405/08/09', 'weekdayLabel' => 'شنبه', 'topic' => 'ضایعات مربوط به لثه', 'presenters' => ['کریمی']],
        ['group' => 10, 'date' => '1405/08/12', 'weekdayLabel' => 'سه‌شنبه', 'topic' => 'ضایعات مربوط به صورت', 'presenters' => ['نصرتی']],
        ['group' => 10, 'date' => '1405/08/16', 'weekdayLabel' => 'شنبه', 'topic' => 'ضایعات مربوط به کام سخت', 'presenters' => ['لطفی']],

        ['group' => 8, 'date' => '1405/07/06', 'weekdayLabel' => 'دوشنبه', 'topic' => 'زخم‌های حاد و متعدد (HSV و اریتم مولتی‌فرم)', 'presenters' => ['بابایی']],
        ['group' => 8, 'date' => '1405/07/13', 'weekdayLabel' => 'دوشنبه', 'topic' => 'زخم‌های متعدد و مزمن (پمفیگوس ولگاریس، پمفیگوئید بولوز و MMP)', 'presenters' => ['پورهاشمی', 'کاظمی']],
        ['group' => 8, 'date' => '1405/07/20', 'weekdayLabel' => 'دوشنبه', 'topic' => 'کاندیدیازیس', 'presenters' => ['حسینی']],
        ['group' => 8, 'date' => '1405/07/27', 'weekdayLabel' => 'دوشنبه', 'topic' => 'لکوپلاکیا و اریترولکوپلاکیا', 'presenters' => ['رحیمی', 'محبوبی']],
        ['group' => 8, 'date' => '1405/08/04', 'weekdayLabel' => 'دوشنبه', 'topic' => 'ضایعات مربوط به زبان', 'presenters' => ['طاهری']],
        ['group' => 8, 'date' => '1405/08/11', 'weekdayLabel' => 'دوشنبه', 'topic' => 'ضایعات مربوط به صورت', 'presenters' => ['عبدی', 'محمدی']],
        ['group' => 8, 'date' => '1405/08/18', 'weekdayLabel' => 'دوشنبه', 'topic' => 'ضایعات پیگمانته', 'presenters' => ['غلامی']],

        ['group' => 9, 'date' => '1405/07/06', 'weekdayLabel' => 'دوشنبه', 'topic' => 'Basic lesion', 'presenters' => ['اسماعیلی']],
        ['group' => 9, 'date' => '1405/07/13', 'weekdayLabel' => 'دوشنبه', 'topic' => 'آفت راجعه و سندرم بهجت', 'presenters' => ['اعوانی', 'موسوی']],
        ['group' => 9, 'date' => '1405/07/20', 'weekdayLabel' => 'دوشنبه', 'topic' => 'زخم مزمن منفرد (SCC و زخم تروماتیک)', 'presenters' => ['جباری']],
        ['group' => 9, 'date' => '1405/07/27', 'weekdayLabel' => 'دوشنبه', 'topic' => 'لیکن پلان و لیکنویید ری‌اکشن', 'presenters' => ['رستملو']],
        ['group' => 9, 'date' => '1405/08/04', 'weekdayLabel' => 'دوشنبه', 'topic' => 'ضایعات مربوط به لب', 'presenters' => ['رضازاده']],
        ['group' => 9, 'date' => '1405/08/06', 'weekdayLabel' => 'چهارشنبه', 'topic' => 'ضایعات مربوط به زبان', 'presenters' => ['هدایتی']],
        ['group' => 9, 'date' => '1405/08/11', 'weekdayLabel' => 'دوشنبه', 'topic' => 'ضایعات مربوط به لثه', 'presenters' => ['رفیعی']],
        ['group' => 9, 'date' => '1405/08/18', 'weekdayLabel' => 'دوشنبه', 'topic' => 'ضایعات مربوط به کام سخت', 'presenters' => ['مالکی', 'یوسف زاده']],

        ['group' => 7, 'date' => '1405/07/08', 'weekdayLabel' => 'چهارشنبه', 'topic' => 'زخم‌های حاد و متعدد (HSV و اریتم مولتی‌فرم)', 'presenters' => ['آقائی']],
        ['group' => 7, 'date' => '1405/07/12', 'weekdayLabel' => 'یکشنبه', 'topic' => 'آفت راجعه و سندرم بهجت', 'presenters' => ['قربانی']],
        ['group' => 7, 'date' => '1405/07/15', 'weekdayLabel' => 'چهارشنبه', 'topic' => 'زخم‌های متعدد و مزمن (پمفیگوس ولگاریس، پمفیگوئید بولوز و MMP)', 'presenters' => ['اشرف']],
        ['group' => 7, 'date' => '1405/07/22', 'weekdayLabel' => 'چهارشنبه', 'topic' => 'کاندیدیازیس', 'presenters' => ['باقری']],
        ['group' => 7, 'date' => '1405/07/29', 'weekdayLabel' => 'چهارشنبه', 'topic' => 'لکوپلاکیا و اریترولکوپلاکیا', 'presenters' => ['زکی']],
        ['group' => 7, 'date' => '1405/08/03', 'weekdayLabel' => 'یکشنبه', 'topic' => 'ضایعات مربوط به لب', 'presenters' => ['گنجی']],
        ['group' => 7, 'date' => '1405/08/06', 'weekdayLabel' => 'چهارشنبه', 'topic' => 'ضایعات مربوط به زبان', 'presenters' => ['روحانی']],
        ['group' => 7, 'date' => '1405/08/10', 'weekdayLabel' => 'یکشنبه', 'topic' => 'ضایعات مربوط به لثه', 'presenters' => ['نیکوئی زاده']],
        ['group' => 7, 'date' => '1405/08/13', 'weekdayLabel' => 'چهارشنبه', 'topic' => 'ضایعات مربوط به صورت', 'presenters' => ['شاهسواری']],
        ['group' => 7, 'date' => '1405/08/17', 'weekdayLabel' => 'یکشنبه', 'topic' => 'ضایعات مربوط به کام سخت', 'presenters' => ['هاشمی نژاد']],
        ['group' => 7, 'date' => '1405/08/20', 'weekdayLabel' => 'چهارشنبه', 'topic' => 'ضایعات پیگمانته', 'presenters' => ['عبادی']],

        ['group' => 6, 'date' => '1405/07/05', 'weekdayLabel' => 'یکشنبه', 'topic' => 'Basic lesion', 'presenters' => ['ابراهیمی']],
        ['group' => 6, 'date' => '1405/07/07', 'weekdayLabel' => 'سه‌شنبه', 'topic' => 'زخم‌های حاد و متعدد (HSV و اریتم مولتی‌فرم)', 'presenters' => ['ایزدی']],
        ['group' => 6, 'date' => '1405/07/14', 'weekdayLabel' => 'سه‌شنبه', 'topic' => 'زخم‌های متعدد و مزمن (پمفیگوس ولگاریس، پمفیگوئید بولوز و MMP)', 'presenters' => ['رستمی']],
        ['group' => 6, 'date' => '1405/07/19', 'weekdayLabel' => 'یکشنبه', 'topic' => 'زخم مزمن منفرد (SCC و زخم تروماتیک)', 'presenters' => ['طبسی']],
        ['group' => 6, 'date' => '1405/07/21', 'weekdayLabel' => 'سه‌شنبه', 'topic' => 'کاندیدیازیس', 'presenters' => ['فتحی']],
        ['group' => 6, 'date' => '1405/07/26', 'weekdayLabel' => 'یکشنبه', 'topic' => 'لیکن پلان و لیکنویید ری‌اکشن', 'presenters' => ['شهیدی']],
        ['group' => 6, 'date' => '1405/07/28', 'weekdayLabel' => 'سه‌شنبه', 'topic' => 'لکوپلاکیا و اریترولکوپلاکیا', 'presenters' => ['عظیمی']],
        ['group' => 6, 'date' => '1405/08/03', 'weekdayLabel' => 'یکشنبه', 'topic' => 'ضایعات مربوط به لب', 'presenters' => ['مهدوی']],
        ['group' => 6, 'date' => '1405/08/12', 'weekdayLabel' => 'سه‌شنبه', 'topic' => 'ضایعات مربوط به صورت', 'presenters' => ['کریمی طرقبه']],
        ['group' => 6, 'date' => '1405/08/19', 'weekdayLabel' => 'سه‌شنبه', 'topic' => 'ضایعات پیگمانته', 'presenters' => ['نیک مراد']],
    ];
}

function dent_term7_oral_disease_presentation_source_aliases(): array
{
    return [
        dent_term7_normalize_person_name('بابایی') => dent_term7_normalize_person_name('مائده بابائی آذر'),
        dent_term7_normalize_person_name('کریمی طرقبه') => dent_term7_normalize_person_name('زهرا کریمی'),
    ];
}

function dent_term7_oral_disease_presentation_state_default(): array
{
    return [
        'schemaVersion' => DENT_TERM7_ORAL_DISEASE_PRESENTATION_SCHEMA,
        'sourceVersion' => DENT_TERM7_ORAL_DISEASE_PRESENTATION_VERSION,
        'presentations' => [],
        'sourceMeta' => [
            'rotationLabel' => 'روتیشن دوم',
            'courseTitle' => 'بیماری‌های دهان عملی ۱',
            'legacyUnmatched' => [],
            'importedAt' => '',
        ],
    ];
}

function dent_term7_oral_disease_presentation_normalize_state(array $raw): array
{
    $state = dent_term7_oral_disease_presentation_state_default();
    foreach (is_array($raw['presentations'] ?? null) ? $raw['presentations'] : [] as $studentNumberRaw => $rowsRaw) {
        $studentNumber = dent_normalize_student_number((string) $studentNumberRaw);
        if ($studentNumber === '' || !is_array($rowsRaw)) {
            continue;
        }
        $rows = [];
        foreach ($rowsRaw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $group = (int) ($row['group'] ?? 0);
            $date = trim((string) ($row['date'] ?? ''));
            $weekdayLabel = dent_clean_text((string) ($row['weekdayLabel'] ?? ''), 40);
            $topic = dent_clean_text((string) ($row['topic'] ?? ''), 260);
            if ($group < 6 || $group > 10 || preg_match('/^\d{4}\/\d{2}\/\d{2}$/D', $date) !== 1 || $topic === '') {
                continue;
            }
            $partners = [];
            foreach (is_array($row['partnerStudentNumbers'] ?? null) ? $row['partnerStudentNumbers'] : [] as $partnerRaw) {
                $partner = dent_normalize_student_number((string) $partnerRaw);
                if ($partner !== '' && $partner !== $studentNumber) {
                    $partners[$partner] = $partner;
                }
            }
            ksort($partners, SORT_STRING);
            $rows[] = [
                'group' => $group,
                'date' => $date,
                'weekdayLabel' => $weekdayLabel,
                'topic' => $topic,
                'partnerStudentNumbers' => array_values($partners),
                'sourcePresenterLabel' => dent_clean_text((string) ($row['sourcePresenterLabel'] ?? ''), 100),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => strcmp((string) $a['date'], (string) $b['date']));
        if ($rows !== []) {
            $state['presentations'][$studentNumber] = $rows;
        }
    }
    ksort($state['presentations'], SORT_STRING);
    $meta = is_array($raw['sourceMeta'] ?? null) ? $raw['sourceMeta'] : [];
    $state['sourceMeta']['legacyUnmatched'] = array_values(array_filter(
        is_array($meta['legacyUnmatched'] ?? null) ? $meta['legacyUnmatched'] : [],
        static fn($row): bool => is_array($row)
    ));
    $state['sourceMeta']['importedAt'] = trim((string) ($meta['importedAt'] ?? ''));
    return $state;
}

/** @template T @param callable(array):T $callback @return T */
function dent_term7_oral_disease_presentation_state_with_lock(callable $callback)
{
    $path = dent_term7_oral_disease_presentation_state_path();
    dent_ensure_directory(dirname($path));
    $handle = fopen($path . '.lock', 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        dent_error('ذخیره‌سازی برنامه ارائه‌ها موقتاً در دسترس نیست.', 503, ['code' => 'ORAL_DISEASE_PRESENTATION_STORE_UNAVAILABLE']);
    }
    try {
        $decoded = is_file($path) ? dent_read_json_file($path, []) : [];
        if (!is_array($decoded)) {
            throw new DentJsonPersistenceException('ORAL_DISEASE_PRESENTATION_STORE_SCHEMA_INVALID', 'Presentation state must be an object');
        }
        $state = dent_term7_oral_disease_presentation_normalize_state($decoded);
        $result = $callback($state);
        $state = dent_term7_oral_disease_presentation_normalize_state($state);
        dent_write_json_file($path, $state, true);
        return $result;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function dent_term7_oral_disease_presentation_state_read(): array
{
    $path = dent_term7_oral_disease_presentation_state_path();
    if (!is_file($path)) {
        return dent_term7_oral_disease_presentation_state_default();
    }
    $handle = fopen($path . '.lock', 'c');
    if ($handle === false || !flock($handle, LOCK_SH)) {
        if (is_resource($handle)) fclose($handle);
        return dent_term7_oral_disease_presentation_state_default();
    }
    try {
        $decoded = dent_read_json_file($path, []);
        return dent_term7_oral_disease_presentation_normalize_state(is_array($decoded) ? $decoded : []);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function dent_term7_oral_disease_presentation_compact_name(string $name): string
{
    return str_replace(' ', '', dent_term7_normalize_person_name($name));
}

function dent_term7_oral_disease_presentation_match_source_name(
    string $sourceName,
    int $group,
    array $groupMembers,
    array $users
): array {
    $normalized = dent_term7_normalize_person_name($sourceName);
    $aliases = dent_term7_oral_disease_presentation_source_aliases();
    $target = $aliases[$normalized] ?? '';
    $candidates = [];
    foreach ($groupMembers as $studentNumber) {
        $user = is_array($users[$studentNumber] ?? null) ? $users[$studentNumber] : [];
        $canonicalName = dent_term7_normalize_person_name((string) ($user['name'] ?? ''));
        if ($canonicalName === '') continue;
        if ($target !== '') {
            if ($canonicalName === $target) {
                $candidates[] = (string) $studentNumber;
            }
            continue;
        }
        $sourceTokens = array_values(array_filter(
            preg_split('/\s+/u', $normalized) ?: [],
            static fn(string $token): bool => mb_strlen($token) >= 2
        ));
        $canonicalCompact = dent_term7_oral_disease_presentation_compact_name($canonicalName);
        $sourceCompact = dent_term7_oral_disease_presentation_compact_name($normalized);
        $allTokens = $sourceTokens !== [];
        foreach ($sourceTokens as $token) {
            if (mb_strpos($canonicalName, $token) === false && mb_strpos($canonicalCompact, str_replace(' ', '', $token)) === false) {
                $allTokens = false;
                break;
            }
        }
        if (($sourceCompact !== '' && mb_strpos($canonicalCompact, $sourceCompact) !== false) || $allTokens) {
            $candidates[] = (string) $studentNumber;
        }
    }
    $candidates = array_values(array_unique($candidates));
    return [
        'sourceName' => $sourceName,
        'group' => $group,
        'candidates' => $candidates,
        'matchedBy' => $target !== '' ? 'sourceAlias' : 'groupScopedName',
    ];
}

function dent_term7_import_oral_disease_presentations(bool $commit): array
{
    $userStore = dent_load_user_store();
    $users = is_array($userStore['users'] ?? null) ? $userStore['users'] : [];
    $academicState = dent_term7_state_read();
    $groupMembers = [6 => [], 7 => [], 8 => [], 9 => [], 10 => []];
    foreach ($users as $studentNumber => $user) {
        if (!is_array($user) || dent_user_cohort_key($user) !== DENT_TERM7_COHORT) continue;
        $assignment = dent_term7_assignment_for_student((string) $studentNumber, $academicState);
        $group = $assignment['group10'] ?? null;
        if (is_int($group) && isset($groupMembers[$group])) {
            $groupMembers[$group][] = (string) $studentNumber;
        }
    }

    $report = [
        'sourceCells' => 0,
        'sourcePresenterNames' => 0,
        'matched' => [],
        'legacyUnmatched' => [],
        'ambiguous' => [],
        'duplicates' => [],
        'missingCurrentStudents' => [],
        'committed' => false,
    ];
    $pending = [];
    $seen = [];

    foreach (dent_term7_oral_disease_presentation_source_schedule() as $cellIndex => $cell) {
        $report['sourceCells']++;
        $group = (int) ($cell['group'] ?? 0);
        $presenters = is_array($cell['presenters'] ?? null) ? $cell['presenters'] : [];
        $matchedInCell = [];
        $cellMatches = [];
        foreach ($presenters as $sourceNameRaw) {
            $report['sourcePresenterNames']++;
            $sourceName = (string) $sourceNameRaw;
            $match = dent_term7_oral_disease_presentation_match_source_name(
                $sourceName,
                $group,
                $groupMembers[$group] ?? [],
                $users
            );
            if (count($match['candidates']) === 0) {
                $report['legacyUnmatched'][] = [
                    'cell' => $cellIndex + 1,
                    'group' => $group,
                    'date' => (string) ($cell['date'] ?? ''),
                    'sourceName' => $sourceName,
                ];
                continue;
            }
            if (count($match['candidates']) > 1) {
                $report['ambiguous'][] = [
                    'cell' => $cellIndex + 1,
                    'group' => $group,
                    'date' => (string) ($cell['date'] ?? ''),
                    'sourceName' => $sourceName,
                    'candidates' => $match['candidates'],
                ];
                continue;
            }
            $studentNumber = (string) $match['candidates'][0];
            if (isset($seen[$studentNumber])) {
                $report['duplicates'][] = [
                    'studentNumber' => $studentNumber,
                    'sourceName' => $sourceName,
                    'previous' => $seen[$studentNumber],
                    'current' => ['group' => $group, 'date' => (string) ($cell['date'] ?? '')],
                ];
                continue;
            }
            $seen[$studentNumber] = ['group' => $group, 'date' => (string) ($cell['date'] ?? '')];
            $matchedInCell[] = $studentNumber;
            $cellMatches[] = [
                'studentNumber' => $studentNumber,
                'sourceName' => $sourceName,
                'matchedBy' => (string) $match['matchedBy'],
            ];
        }

        foreach ($cellMatches as $matched) {
            $studentNumber = (string) $matched['studentNumber'];
            $partners = array_values(array_filter(
                $matchedInCell,
                static fn(string $candidate): bool => $candidate !== $studentNumber
            ));
            $pending[$studentNumber][] = [
                'group' => $group,
                'date' => (string) ($cell['date'] ?? ''),
                'weekdayLabel' => (string) ($cell['weekdayLabel'] ?? ''),
                'topic' => (string) ($cell['topic'] ?? ''),
                'partnerStudentNumbers' => $partners,
                'sourcePresenterLabel' => (string) $matched['sourceName'],
            ];
            $report['matched'][] = [
                'studentNumber' => $studentNumber,
                'name' => (string) ($users[$studentNumber]['name'] ?? ''),
                'group' => $group,
                'date' => (string) ($cell['date'] ?? ''),
                'topic' => (string) ($cell['topic'] ?? ''),
                'partnerCount' => count($partners),
                'matchedBy' => (string) $matched['matchedBy'],
            ];
        }
    }

    foreach ($groupMembers as $group => $members) {
        foreach ($members as $studentNumber) {
            if (!isset($pending[$studentNumber])) {
                $report['missingCurrentStudents'][] = [
                    'studentNumber' => $studentNumber,
                    'name' => (string) ($users[$studentNumber]['name'] ?? ''),
                    'group' => $group,
                ];
            }
        }
    }

    if (
        $commit
        && $report['ambiguous'] === []
        && $report['duplicates'] === []
        && $report['missingCurrentStudents'] === []
    ) {
        dent_term7_oral_disease_presentation_state_with_lock(static function (array &$state) use ($pending, $report): array {
            $state = dent_term7_oral_disease_presentation_state_default();
            $state['presentations'] = $pending;
            $state['sourceMeta']['legacyUnmatched'] = $report['legacyUnmatched'];
            $state['sourceMeta']['importedAt'] = dent_iso_now();
            return [];
        });
        $report['committed'] = true;
    }

    $report['currentStudentCount'] = array_sum(array_map('count', $groupMembers));
    $report['matchedStudentCount'] = count($pending);
    $report['legacyUnmatchedCount'] = count($report['legacyUnmatched']);
    return $report;
}

function dent_term7_oral_disease_presentations_for_student(string $studentNumber, ?array $state = null): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') return [];
    $source = $state ?? dent_term7_oral_disease_presentation_state_read();
    return array_values(is_array($source['presentations'][$studentNumber] ?? null) ? $source['presentations'][$studentNumber] : []);
}

function dent_term7_oral_disease_public_rows_for_student(string $studentNumber, ?array $state = null): array
{
    $rows = dent_term7_oral_disease_presentations_for_student($studentNumber, $state);
    if ($rows === []) return [];
    $userStore = dent_load_user_store();
    $users = is_array($userStore['users'] ?? null) ? $userStore['users'] : [];
    $out = [];
    foreach ($rows as $row) {
        $partners = [];
        foreach (is_array($row['partnerStudentNumbers'] ?? null) ? $row['partnerStudentNumbers'] : [] as $partnerStudentNumber) {
            $partner = is_array($users[$partnerStudentNumber] ?? null) ? $users[$partnerStudentNumber] : [];
            $name = trim((string) ($partner['name'] ?? ''));
            if ($name !== '') $partners[] = $name;
        }
        $out[] = [
            'group' => (int) ($row['group'] ?? 0),
            'jalaliDate' => (string) ($row['date'] ?? ''),
            'weekdayLabel' => (string) ($row['weekdayLabel'] ?? ''),
            'topic' => (string) ($row['topic'] ?? ''),
            'partners' => $partners,
            'partnerLabel' => $partners === [] ? 'انفرادی' : implode('، ', $partners),
        ];
    }
    usort($out, static fn(array $a, array $b): int => strcmp((string) $a['jalaliDate'], (string) $b['jalaliDate']));
    return $out;
}

function dent_term7_oral_disease_public_presentation_on_date(
    string $studentNumber,
    string $jalaliDate,
    ?array $state = null
): ?array {
    foreach (dent_term7_oral_disease_public_rows_for_student($studentNumber, $state) as $row) {
        if ((string) ($row['jalaliDate'] ?? '') === $jalaliDate) {
            return $row;
        }
    }
    return null;
}

function dent_term7_oral_disease_presentation_notification_candidate(string $studentNumber): ?array
{
    $rows = dent_term7_oral_disease_public_rows_for_student($studentNumber);
    if ($rows === []) return null;

    $body = [
        'برنامه ارائه‌های بیماری‌های دهان عملی ۱ برای روتیشن دوم به برنامه شخصی شما اضافه شد.',
        '',
    ];
    foreach ($rows as $row) {
        $body[] = '• ' . (string) ($row['weekdayLabel'] ?? '') . ' ' . dent_to_fa_digits((string) ($row['jalaliDate'] ?? ''));
        $body[] = '  🎤 موضوع: ' . (string) ($row['topic'] ?? '');
        $body[] = '  👥 همراه: ' . (string) ($row['partnerLabel'] ?? 'انفرادی');
    }
    $body[] = '';
    $body[] = 'این مورد در برنامه روزانه و یادآوری همان روز هم نمایش داده می‌شود.';

    return [
        'source' => DENT_TERM7_ORAL_DISEASE_PRESENTATION_SOURCE,
        'sourceKey' => 'oral-disease-presentations:' . DENT_TERM7_ORAL_DISEASE_PRESENTATION_VERSION . ':' . $studentNumber,
        'title' => '🎤 برنامه ارائه‌های بیماری‌های دهان عملی ۱',
        'body' => implode("
", $body),
        'tone' => 'accent',
        'meta' => [
            'important' => true,
            'scheduleVersion' => DENT_TERM7_SCHEDULE_VERSION,
            'oralDiseasePresentationRows' => $rows,
        ],
    ];
}
