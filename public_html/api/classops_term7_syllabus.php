<?php
declare(strict_types=1);

require_once __DIR__ . '/classops_partial_theory_syllabus.php';
require_once __DIR__ . '/classops_term7_syllabus_data.php';

const CLASSOPS_TERM7_SYLLABUS_VERSION = '1405-1406-1.corrected.6';

function classops_term7_syllabus_mode_label(string $mode): string
{
    return match ($mode) {
        'virtual' => 'مجازی',
        'offline' => 'مجازی (آفلاین)',
        'in_person_quiz' => 'حضوری + کوییز کلاسی',
        'flipped' => 'کلاس وارونه',
        'conditional_virtual' => 'در صورت برگزاری مجازی، متعاقباً اطلاع‌رسانی می‌شود',
        default => 'حضوری',
    };
}

function classops_term7_syllabus_catalog(): array
{
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }
    $catalog = classops_term7_syllabus_source_catalog();
    $partial = classops_partial_theory_syllabus();
    $partialSessions = [];
    foreach (is_array($partial['sessions'] ?? null) ? $partial['sessions'] : [] as $session) {
        if (!is_array($session)) continue;
        $date = trim((string) ($session['jalaliDate'] ?? ''));
        $session['dates'] = $date !== '' ? [$date] : [];
        $session['sessionNumbers'] = isset($session['sessionNumber']) ? [(int) $session['sessionNumber']] : [];
        $session['sessionMode'] = (string) ($session['sessionMode'] ?? 'in_person');
        $partialSessions[] = $session;
    }
    $catalog['partial-basics-theory'] = [
        'version' => (string) ($partial['version'] ?? ''),
        'eventSlugs' => ['partial-basics-theory'],
        'courseTitle' => (string) ($partial['courseTitle'] ?? 'مبانی پارسیل نظری'),
        'sourceCourseTitle' => (string) ($partial['sourceCourseTitle'] ?? 'مبانی پروتز پارسیل نظری'),
        'sourceFile' => 'مبانی پروتز پارسیل نظری.pdf',
        'courseCoordinator' => (string) ($partial['courseCoordinator'] ?? ''),
        'sourceTiming' => [
            'default' => ['start' => '07:30', 'end' => '08:30', 'appliesToVirtual' => true],
        ],
        'sessions' => $partialSessions,
    ];
    return $catalog;
}

function classops_term7_syllabus_course_for_slug(string $slug): ?array
{
    static $bySlug = null;
    if (!is_array($bySlug)) {
        $bySlug = [];
        foreach (classops_term7_syllabus_catalog() as $courseKey => $course) {
            if (!is_array($course)) continue;
            foreach (is_array($course['eventSlugs'] ?? null) ? $course['eventSlugs'] : [] as $eventSlug) {
                $eventSlug = trim((string) $eventSlug);
                if ($eventSlug !== '') {
                    $bySlug[$eventSlug] = ['courseKey' => (string) $courseKey, 'course' => $course];
                }
            }
        }
    }
    return is_array($bySlug[$slug] ?? null) ? $bySlug[$slug] : null;
}

function classops_term7_syllabus_jalali_day_of_year(string $jalaliDate): ?int
{
    if (!preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', trim($jalaliDate), $match)) return null;
    $year = (int) $match[1];
    $month = (int) $match[2];
    $day = (int) $match[3];
    if ($year !== 1405 || $month < 1 || $month > 12) return null;
    $monthLengths = [1 => 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
    if ($day < 1 || $day > $monthLengths[$month]) return null;
    $ordinal = $day;
    for ($cursor = 1; $cursor < $month; $cursor++) $ordinal += $monthLengths[$cursor];
    return $ordinal;
}

function classops_term7_syllabus_rotation_anchor(string $rotation): string
{
    return match (strtoupper(trim($rotation))) {
        'A' => '1405/06/28',
        'B' => '1405/08/23',
        default => '',
    };
}

function classops_term7_syllabus_session_matches_date(array $course, array $session, string $jalaliDate, string $rotation): bool
{
    $dates = array_values(array_filter(array_map(
        static fn($value): string => trim((string) $value),
        is_array($session['dates'] ?? null) ? $session['dates'] : []
    )));
    if (empty($course['rotationRelative']) || !in_array(strtoupper(trim($rotation)), ['A', 'B'], true)) {
        return in_array($jalaliDate, $dates, true);
    }

    $sourceAnchor = trim((string) ($course['rotationSourceAnchor'] ?? ''));
    $targetAnchor = classops_term7_syllabus_rotation_anchor($rotation);
    $sourceOrdinal = classops_term7_syllabus_jalali_day_of_year($sourceAnchor);
    $targetOrdinal = classops_term7_syllabus_jalali_day_of_year($targetAnchor);
    $dateOrdinal = classops_term7_syllabus_jalali_day_of_year($jalaliDate);
    if ($sourceOrdinal === null || $targetOrdinal === null || $dateOrdinal === null) return false;
    $targetOffset = $dateOrdinal - $targetOrdinal;
    if ($targetOffset < 0) return false;
    foreach ($dates as $sourceDate) {
        $sourceDateOrdinal = classops_term7_syllabus_jalali_day_of_year($sourceDate);
        if ($sourceDateOrdinal !== null && ($sourceDateOrdinal - $sourceOrdinal) === $targetOffset) return true;
    }
    return false;
}

function classops_term7_syllabus_sessions_for_date(array $course, string $jalaliDate, string $rotation = ''): array
{
    $sessions = [];
    foreach (is_array($course['sessions'] ?? null) ? $course['sessions'] : [] as $session) {
        if (!is_array($session)) continue;
        if (classops_term7_syllabus_session_matches_date($course, $session, $jalaliDate, $rotation)) {
            $sessions[] = $session;
        }
    }
    usort($sessions, static function (array $left, array $right): int {
        $aNumber = (int) (($left['sessionNumbers'][0] ?? null) ?: ($left['sessionNumber'] ?? 0));
        $bNumber = (int) (($right['sessionNumbers'][0] ?? null) ?: ($right['sessionNumber'] ?? 0));
        $a = $aNumber > 0 ? $aNumber : PHP_INT_MAX;
        $b = $bNumber > 0 ? $bNumber : PHP_INT_MAX;
        if ($a !== $b) return $a <=> $b;
        return strcmp((string) ($left['sessionKey'] ?? ''), (string) ($right['sessionKey'] ?? ''));
    });
    return $sessions;
}

function classops_term7_syllabus_source_occurrence_decision(
    string $eventSlug,
    string $jalaliDate,
    string $rotation = ''
): ?bool {
    $mapping = classops_term7_syllabus_course_for_slug($eventSlug);
    if ($mapping === null) return null;
    $course = $mapping['course'];
    if (empty($course['sourceDatesAuthoritative'])) return null;

    $rotation = strtoupper(trim($rotation));
    $scopedRotations = array_values(array_filter(array_map(
        static fn($value): string => strtoupper(trim((string) $value)),
        is_array($course['sourceOccurrenceRotations'] ?? null) ? $course['sourceOccurrenceRotations'] : []
    )));
    if ($scopedRotations !== [] && !in_array($rotation, $scopedRotations, true)) {
        return null;
    }

    return classops_term7_syllabus_sessions_for_date($course, $jalaliDate, $rotation) !== [];
}


function classops_term7_syllabus_valid_clock(string $value): bool
{
    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', trim($value)) === 1;
}

function classops_term7_syllabus_timing_for_session(array $course, array $sessions, array $session, string $eventSlug): ?array
{
    $config = is_array($course['sourceTiming'] ?? null) ? $course['sourceTiming'] : [];
    if ($config === []) return null;

    $timing = null;
    $bySlug = is_array($config['byEventSlug'] ?? null) ? $config['byEventSlug'] : [];
    if (is_array($bySlug[$eventSlug] ?? null)) {
        $timing = $bySlug[$eventSlug];
    } elseif (is_array($config['singleSessionDay'] ?? null) && is_array($config['multiSessionDay'] ?? null)) {
        $sessionCount = 0;
        foreach ($sessions as $row) {
            if (!is_array($row)) continue;
            $numbers = array_values(array_filter(array_map('intval', is_array($row['sessionNumbers'] ?? null) ? $row['sessionNumbers'] : []), static fn(int $value): bool => $value > 0));
            if ($numbers !== []) {
                $sessionCount += count($numbers);
            } elseif (isset($row['sessionNumber']) && (int) $row['sessionNumber'] > 0) {
                $sessionCount++;
            }
        }
        $timing = $sessionCount > 1 ? $config['multiSessionDay'] : $config['singleSessionDay'];
    } elseif (is_array($config['default'] ?? null)) {
        $timing = $config['default'];
    }
    if (!is_array($timing)) return null;

    $mode = trim((string) ($session['sessionMode'] ?? 'in_person')) ?: 'in_person';
    if (in_array($mode, ['virtual', 'offline'], true) && empty($timing['appliesToVirtual'])) {
        return null;
    }
    $start = trim((string) ($timing['start'] ?? ''));
    $end = trim((string) ($timing['end'] ?? ''));
    if (!classops_term7_syllabus_valid_clock($start) || !classops_term7_syllabus_valid_clock($end)) return null;
    return ['start' => $start, 'end' => $end];
}

function classops_term7_syllabus_source_time_for_event(string $eventSlug, string $jalaliDate, string $rotation = ''): ?array
{
    $mapping = classops_term7_syllabus_course_for_slug($eventSlug);
    if ($mapping === null) return null;
    $course = $mapping['course'];
    $sessions = classops_term7_syllabus_sessions_for_date($course, $jalaliDate, $rotation);
    if ($sessions === []) return null;

    $timings = [];
    foreach ($sessions as $session) {
        if (!is_array($session)) continue;
        $timing = classops_term7_syllabus_timing_for_session($course, $sessions, $session, $eventSlug);
        if ($timing !== null) $timings[] = $timing;
    }
    if ($timings === []) return null;
    usort($timings, static fn(array $a, array $b): int => strcmp((string) $a['start'], (string) $b['start']));
    $start = (string) $timings[0]['start'];
    $end = (string) $timings[0]['end'];
    foreach ($timings as $timing) {
        if (strcmp((string) $timing['start'], $start) < 0) $start = (string) $timing['start'];
        if (strcmp((string) $timing['end'], $end) > 0) $end = (string) $timing['end'];
    }
    return ['start' => $start, 'end' => $end];
}

function classops_term7_syllabus_apply_source_times(array $events, string $jalaliDate, string $rotation = ''): array
{
    return array_map(static function (array $event) use ($jalaliDate, $rotation): array {
        $slug = trim((string) ($event['slug'] ?? ''));
        if ($slug === '') return $event;
        $timing = classops_term7_syllabus_source_time_for_event($slug, $jalaliDate, $rotation);
        if ($timing === null) return $event;
        $event['start'] = $timing['start'];
        $event['end'] = $timing['end'];
        $event['sourceTimeExplicit'] = true;
        $event['timeSource'] = 'term7-course-syllabus';
        return $event;
    }, $events);
}

function classops_term7_syllabus_session_label(array $session): string
{
    $explicit = trim((string) ($session['sessionLabel'] ?? ''));
    if ($explicit !== '') return $explicit;
    $numbers = array_values(array_map('intval', is_array($session['sessionNumbers'] ?? null) ? $session['sessionNumbers'] : []));
    if ($numbers === [] && isset($session['sessionNumber']) && (int) $session['sessionNumber'] > 0) {
        $numbers = [(int) $session['sessionNumber']];
    }
    if ($numbers === []) return 'محتوای تکمیلی';
    if (count($numbers) === 1) return 'جلسه ' . $numbers[0];
    return 'جلسات ' . implode(' و ', $numbers);
}

function classops_term7_syllabus_enrich_events(array $events, string $jalaliDate, string $rotation = ''): array
{
    $out = [];
    foreach ($events as $event) {
        if (!is_array($event)) continue;
        $slug = trim((string) ($event['slug'] ?? ''));
        $mapping = classops_term7_syllabus_course_for_slug($slug);
        if ($mapping === null) {
            $out[] = $event;
            continue;
        }
        $course = $mapping['course'];
        $sessions = classops_term7_syllabus_sessions_for_date($course, $jalaliDate, $rotation);
        if ($sessions === []) {
            $out[] = $event;
            continue;
        }
        foreach ($sessions as $index => $session) {
            $copy = $event;
            $numbers = array_values(array_map('intval', is_array($session['sessionNumbers'] ?? null) ? $session['sessionNumbers'] : []));
            $single = count($numbers) === 1 ? $numbers[0] : null;
            $sessionKey = trim((string) ($session['sessionKey'] ?? ''));
            if ($sessionKey === '') {
                $sessionKey = $numbers !== [] ? implode('-', $numbers) : ('supplement-' . ($index + 1));
            }
            $mode = trim((string) ($session['sessionMode'] ?? 'in_person')) ?: 'in_person';
            $modeLabel = classops_term7_syllabus_mode_label($mode);
            $sessionLabel = classops_term7_syllabus_session_label($session);
            $sessionTitle = trim((string) ($session['title'] ?? ''));
            $courseTitle = trim((string) ($course['courseTitle'] ?? ($event['title'] ?? '')));
            $copy['courseTitle'] = $courseTitle;
            $copy['sessionKey'] = $sessionKey;
            $copy['sessionNumber'] = $single;
            $copy['sessionNumbers'] = $numbers;
            $copy['sessionLabel'] = $sessionLabel;
            $copy['sessionTitle'] = $sessionTitle;
            $copy['sessionDetails'] = trim((string) ($session['sessionDetails'] ?? ''));
            $copy['sourceTitle'] = trim((string) ($session['sourceTitle'] ?? ''));
            $copy['instructor'] = trim((string) ($session['instructor'] ?? ''));
            $copy['references'] = is_array($session['references'] ?? null) ? $session['references'] : [];
            $copy['sessionMode'] = $mode;
            $copy['sessionModeLabel'] = $modeLabel;
            $timing = classops_term7_syllabus_timing_for_session($course, $sessions, $session, $slug);
            if ($timing !== null) {
                $copy['start'] = $timing['start'];
                $copy['end'] = $timing['end'];
                $copy['sourceTimeExplicit'] = true;
                $copy['timeSource'] = 'term7-course-syllabus';
            } elseif (in_array($mode, ['virtual', 'offline'], true)) {
                $copy['start'] = '';
                $copy['end'] = '';
                $copy['sourceTimeExplicit'] = false;
            }
            $copy['segments'] = is_array($session['segments'] ?? null) ? $session['segments'] : [];
            $copy['sourceFile'] = (string) ($course['sourceFile'] ?? '');
            $copy['sourcePage'] = max(0, (int) ($session['sourcePage'] ?? 0));
            $copy['sourceDate'] = (string) ($session['sourceDate'] ?? '');
            $copy['sessionSource'] = 'term7-course-syllabus';
            if (isset($session['assessmentPart'])) $copy['assessmentPart'] = (string) $session['assessmentPart'];
            $suffix = $mode === 'in_person' ? '' : ' · ' . $modeLabel;
            $copy['title'] = $courseTitle . ' — ' . $sessionLabel . ': ' . $sessionTitle . $suffix;
            if (in_array($mode, ['virtual', 'offline'], true)) {
                $copy['location'] = '';
            }
            $out[] = $copy;
        }
    }
    return $out;
}


function classops_term7_syllabus_booklet_tag(string $courseKey, array $course): string
{
    $aliases = [
        'orthodontics-theory-1' => 'ارتو_نظری۱',
        'endodontics-theory-1' => 'اندو_نظری۱',
        'diagnostic-dentistry-3' => 'تشخیصی۳',
        'research-methods-2' => 'روش_تحقیق۲',
        'endodontics-basics-2' => 'مبانی_اندودانتیکس۲',
        'oral-health-practical-2' => 'سلامت_دهان_عملی۲',
        'pathology-practical-1' => 'آسیب_شناسی_عملی۱',
        'oral-health-theory-2' => 'سلامت_دهان_نظری۲',
        'ent' => 'گوش_حلق_بینی',
        'periodontology-theory-1' => 'پریو_نظری۱',
        'partial-basics-theory' => 'مبانی_پروتز_پارسیل',
    ];
    if (isset($aliases[$courseKey])) return $aliases[$courseKey];

    $title = trim((string) ($course['sourceCourseTitle'] ?? $course['courseTitle'] ?? $courseKey));
    $title = strtr($title, ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', "‌" => '_']);
    $tag = preg_replace('/[^\p{L}\p{N}]+/u', '_', $title);
    return trim((string) $tag, '_');
}

function classops_term7_syllabus_booklet_tag_aliases(string $courseKey, string $canonical): array
{
    $aliases = [
        'orthodontics-theory-1' => [
            'ارتو_نظری۱',
            'ارتودنسی_نظری۱',
            'ارتودانتیکس_نظری۱',
        ],
        'endodontics-theory-1' => [
            'اندو_نظری۱',
            'اندودانتیکس_نظری۱',
        ],
        'diagnostic-dentistry-3' => [
            'تشخیصی۳',
            'دندانپزشکی_تشخیصی۳',
        ],
        'research-methods-2' => [
            'روش_تحقیق۲',
            'روش_شناسی_تحقیق۲',
            'روششناسی_تحقیق۲',
        ],
        'endodontics-basics-2' => [
            'مبانی_اندودانتیکس۲',
            'مبانی_اندو۲',
        ],
        'oral-health-practical-2' => [
            'سلامت_دهان_عملی۲',
            'سلامت_عملی۲',
        ],
        'pathology-practical-1' => [
            'آسیب_شناسی_عملی۱',
            'آسیب_شناسی۱',
            'پاتولوژی_عملی۱',
        ],
        'oral-health-theory-2' => [
            'سلامت_دهان_نظری۲',
            'سلامت_نظری۲',
        ],
        'ent' => [
            'گوش_حلق_بینی',
            'گوش_حلق_و_بینی',
        ],
        'periodontology-theory-1' => [
            'پریو_نظری۱',
            'پریودنتولوژی_نظری۱',
        ],
        'partial-basics-theory' => [
            'مبانی_پروتز_پارسیل',
            'مبانی_پروتز_پارسیل_نظری',
            'مبانی_پارسیل_نظری',
            'پروتز_پارسیل_نظری',
        ],
    ];

    $values = array_merge([$canonical], $aliases[$courseKey] ?? []);
    $values = array_values(array_unique(array_filter(array_map(
        static fn($value): string => trim((string) $value),
        $values
    ))));
    return $values;
}

/**
 * Canonical booklet projection of the same Term 7 syllabus registry used by
 * the daily schedule and ClassOps. Numbered sessions are expanded one-by-one
 * so the bot never maintains a second syllabus copy.
 */
function classops_term7_syllabus_booklet_catalog(): array
{
    $courses = [];
    foreach (classops_term7_syllabus_catalog() as $courseKey => $course) {
        if (!is_array($course)) continue;
        $sessions = [];
        foreach (is_array($course['sessions'] ?? null) ? $course['sessions'] : [] as $session) {
            if (!is_array($session)) continue;
            $numbers = array_values(array_filter(array_map(
                'intval',
                is_array($session['sessionNumbers'] ?? null) ? $session['sessionNumbers'] : []
            ), static fn(int $number): bool => $number > 0 && $number <= 40));
            if ($numbers === [] && isset($session['sessionNumber']) && (int) $session['sessionNumber'] > 0) {
                $numbers = [(int) $session['sessionNumber']];
            }
            foreach ($numbers as $number) {
                if (isset($sessions[$number])) continue;
                $mode = trim((string) ($session['sessionMode'] ?? 'in_person')) ?: 'in_person';
                $sessions[$number] = [
                    'sessionNumber' => $number,
                    'title' => trim((string) ($session['title'] ?? '')),
                    'instructor' => trim((string) ($session['instructor'] ?? '')),
                    'sessionMode' => $mode,
                    'sessionModeLabel' => classops_term7_syllabus_mode_label($mode),
                    'sourcePage' => max(0, (int) ($session['sourcePage'] ?? 0)),
                ];
            }
        }
        if ($sessions === []) continue;
        ksort($sessions, SORT_NUMERIC);
        $courses[] = [
            'courseKey' => (string) $courseKey,
            'courseTitle' => trim((string) ($course['courseTitle'] ?? $course['sourceCourseTitle'] ?? $courseKey)),
            'sourceCourseTitle' => trim((string) ($course['sourceCourseTitle'] ?? $course['courseTitle'] ?? '')),
            'bookletTag' => classops_term7_syllabus_booklet_tag((string) $courseKey, $course),
            'bookletTagAliases' => classops_term7_syllabus_booklet_tag_aliases(
                (string) $courseKey,
                classops_term7_syllabus_booklet_tag((string) $courseKey, $course)
            ),
            'term' => 7,
            'sourceFile' => trim((string) ($course['sourceFile'] ?? '')),
            'sessions' => array_values($sessions),
        ];
    }
    return [
        'contractVersion' => 'term7-booklet-catalog-v1',
        'syllabusVersion' => CLASSOPS_TERM7_SYLLABUS_VERSION,
        'term' => 7,
        'courses' => $courses,
    ];
}
