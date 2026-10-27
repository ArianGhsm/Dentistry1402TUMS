<?php
declare(strict_types=1);

require_once __DIR__ . '/classops_partial_theory_syllabus.php';
require_once __DIR__ . '/classops_term7_syllabus_data.php';

// Quiz 5 with Dr. Hamidzadeh restored to its 1405/08/10 source session; refresh quiz reminder identities.
const CLASSOPS_TERM7_SYLLABUS_VERSION = '1405-1406-1.corrected.19';

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

function classops_term7_syllabus_one_off_overrides(): array
{
    return [
        'orthodontics-theory-1|1405/07/13|3' => [
            'sessionMode' => 'offline',
            'sessionModeLabel' => 'مجازی (آفلاین)',
            'overrideKind' => 'one_off',
            'overrideReason' => 'برگزاری استثنایی این جلسه به‌صورت مجازی آفلاین',
        ],
        'orthodontics-theory-1|1405/08/11|8' => [
            'sessionMode' => 'virtual',
            'sessionModeLabel' => 'مجازی',
            'overrideKind' => 'one_off',
            'overrideReason' => 'تغییر نحوه برگزاری جلسه هشتم با دکتر قدیریان به مجازی',
        ],
        'orthodontics-theory-1|1405/10/06|16' => [
            'sessionMode' => 'in_person',
            'sessionModeLabel' => 'حضوری',
            'overrideKind' => 'one_off',
            'overrideReason' => 'تغییر نحوه برگزاری جلسه پایانی با دکتر تنباکوچی به حضوری',
        ],
    ];
}

/** Calendar-date-specific clock corrections must not follow rotation-relative syllabus rows into Rotation B. */
function classops_term7_syllabus_one_off_clock_overrides(): array
{
    return [
        'research-methods-2-practical|1405/07/22|8' => [
            'start' => '12:45',
            'end' => '13:45',
            'reason' => 'تغییر استثنایی ساعت جلسه روش تحقیق ۲ در چهارشنبه ۲۲ مهر ۱۴۰۵',
        ],
    ];
}

function classops_term7_syllabus_apply_one_off_override(string $courseKey, array $session): array
{
    $numbers = array_values(array_filter(array_map(
        'intval',
        is_array($session['sessionNumbers'] ?? null) ? $session['sessionNumbers'] : []
    ), static fn(int $number): bool => $number > 0));
    if ($numbers === [] && isset($session['sessionNumber']) && (int) $session['sessionNumber'] > 0) {
        $numbers = [(int) $session['sessionNumber']];
    }
    $dateSource = is_array($session['canonicalDates'] ?? null)
        ? $session['canonicalDates']
        : (is_array($session['dates'] ?? null) ? $session['dates'] : []);
    foreach ($dateSource as $dateValue) {
        $date = trim((string) $dateValue);
        if ($date === '') continue;
        foreach ($numbers as $number) {
            $key = trim($courseKey) . '|' . $date . '|' . $number;
            $override = classops_term7_syllabus_one_off_overrides()[$key] ?? null;
            if (!is_array($override)) continue;
            $session['sessionMode'] = (string) ($override['sessionMode'] ?? $session['sessionMode'] ?? 'in_person');
            $session['sessionModeLabel'] = (string) ($override['sessionModeLabel'] ?? '');
            $session['sessionOverrideKind'] = (string) ($override['overrideKind'] ?? 'one_off');
            $session['sessionOverrideReason'] = (string) ($override['overrideReason'] ?? '');
            $session['sessionOverrideDate'] = $date;
            return $session;
        }
    }
    return $session;
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
        'sessions' => $partialSessions,
    ];

    foreach ($catalog as $courseKey => &$course) {
        if (!is_array($course)) continue;
        $sessions = [];
        foreach (is_array($course['sessions'] ?? null) ? $course['sessions'] : [] as $session) {
            if (!is_array($session)) continue;
            $sessions[] = classops_term7_syllabus_apply_one_off_override((string) $courseKey, $session);
        }
        $course['sessions'] = $sessions;
    }
    unset($course);
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
    $dateSource = is_array($session['canonicalDates'] ?? null) ? $session['canonicalDates'] : ($session['dates'] ?? []);
    $dates = array_values(array_filter(array_map(
        static fn($value): string => trim((string) $value),
        is_array($dateSource) ? $dateSource : []
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


function classops_term7_syllabus_theory_occurrence_cancelled(string $eventSlug, string $jalaliDate): bool
{
    $mapping = classops_term7_syllabus_course_for_slug($eventSlug);
    if ($mapping === null) return false;
    $course = $mapping['course'];
    $dates = is_array($course['oneOffCancelledTheoryDates'] ?? null)
        ? $course['oneOffCancelledTheoryDates']
        : [];
    return in_array($jalaliDate, $dates, true);
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

/**
 * Traceable owner-announced rescheduling of existing source-syllabus quizzes.
 * Keys include the canonical course, Jalali class date and source session number.
 * No standalone competing quiz calendar is introduced.
 */
function classops_term7_syllabus_quiz_adjustments(): array
{
    return [
        'endodontics-basics-2|1405/08/03|11' => ['quizTitle' => '', 'removeFromSessionTitle' => true],
        'endodontics-basics-2|1405/08/05|12' => ['quizTitle' => 'کوییز ۴', 'quizInstructor' => 'دکتر اسدیان', 'sourceSessionNumber' => 11],
        // 1405/08/05: Owner reconfirmed quiz 5 for Sunday 1405/08/10; this matches source session 13.
    ];
}

function classops_term7_syllabus_quiz_adjustment(string $courseKey, array $session, string $jalaliDate): ?array
{
    $number = (int) ($session['sessionNumber'] ?? ($session['sessionNumbers'][0] ?? 0));
    $key = $courseKey . '|' . $jalaliDate . '|' . $number;
    $record = classops_term7_syllabus_quiz_adjustments()[$key] ?? null;
    return is_array($record) ? $record : null;
}

/**
 * For each actual quiz, distinguish its PDF source from a later owner-confirmed
 * date correction. Never present a moved quiz as if that corrected date was in
 * the original syllabus.
 */
function classops_term7_syllabus_quiz_provenance(
    string $courseKey,
    array $session,
    string $jalaliDate,
    string $quizTitle
): array {
    if ($quizTitle === '') return [];
    $adjustment = classops_term7_syllabus_quiz_adjustment($courseKey, $session, $jalaliDate);
    $title = trim((string) ($session['title'] ?? ''));
    $explicit = preg_match('/کوییز|کوئیز|آزمونک|\\bquiz\\b/iu', $title) === 1
        || (string) ($session['sessionMode'] ?? '') === 'in_person_quiz';
    if ($adjustment !== null && isset($adjustment['sourceSessionNumber'])) {
        return [
            'quizOrigin' => 'syllabus-date-correction',
            'quizSourceLabel' => 'کوییز در طرح درس درج شده؛ تاریخ طبق اصلاحیهٔ اعلام‌شده تغییر کرده است.',
        ];
    }
    if ($explicit) {
        return [
            'quizOrigin' => 'syllabus',
            'quizSourceLabel' => 'در طرح درس این جلسه، کوییز درج شده است.',
        ];
    }
    // A future manually announced assessment without an explicit source
    // must not be mislabeled as documented by a syllabus.
    return ['quizOrigin' => 'announced', 'quizSourceLabel' => 'طبق اطلاعیهٔ آموزشی'];
}

/** Assessment metadata comes from the same syllabus session as the class, not a second calendar. */
function classops_term7_syllabus_quiz_title(string $courseKey, array $session, string $jalaliDate): string
{
    $adjustment = classops_term7_syllabus_quiz_adjustment($courseKey, $session, $jalaliDate);
    if ($adjustment !== null && array_key_exists('quizTitle', $adjustment)) {
        return (string) $adjustment['quizTitle'];
    }
    $title = trim((string) ($session['title'] ?? ''));
    if (preg_match('/(?:کوییز|کوئیز|آزمونک)\s*([0-9۰-۹٠-٩]+)/u', $title, $match)) {
        return 'کوییز ' . strtr($match[1], ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹','٠'=>'۰','١'=>'۱','٢'=>'۲','٣'=>'۳','٤'=>'۴','٥'=>'۵','٦'=>'۶','٧'=>'۷','٨'=>'۸','٩'=>'۹']);
    }
    if (preg_match('/کوییز|کوئیز|آزمونک|\bquiz\b/iu', $title)
        || (string) ($session['sessionMode'] ?? '') === 'in_person_quiz') {
        return 'کوییز کلاسی';
    }
    return '';
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
            $modeLabel = trim((string) ($session['sessionModeLabel'] ?? ''));
            if ($modeLabel === '') $modeLabel = classops_term7_syllabus_mode_label($mode);
            $sessionLabel = classops_term7_syllabus_session_label($session);
            $sessionTitle = trim((string) ($session['title'] ?? ''));
            $quizAdjustment = classops_term7_syllabus_quiz_adjustment((string) $mapping['courseKey'], $session, $jalaliDate);
            if (!empty($quizAdjustment['removeFromSessionTitle'])) {
                $sessionTitle = preg_replace('/^کوییز\s*[0-9۰-۹]+\s*\+\s*/u', '', $sessionTitle) ?? $sessionTitle;
            }
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
            $copy['resident'] = trim((string) ($session['resident'] ?? ''));
            $copy['references'] = is_array($session['references'] ?? null) ? $session['references'] : [];
            $copy['sessionMode'] = $mode;
            $copy['sessionModeLabel'] = $modeLabel;
            $copy['quizTitle'] = classops_term7_syllabus_quiz_title((string) $mapping['courseKey'], $session, $jalaliDate);
            $copy['quizInstructor'] = $copy['quizTitle'] !== ''
                ? trim((string) ($quizAdjustment['quizInstructor'] ?? $session['instructor'] ?? ''))
                : '';
            $copy['quizSourceSessionNumber'] = (int) ($quizAdjustment['sourceSessionNumber'] ?? ($session['sessionNumber'] ?? 0));
            $quizProvenance = classops_term7_syllabus_quiz_provenance(
                (string) $mapping['courseKey'], $session, $jalaliDate, $copy['quizTitle']
            );
            $copy['quizOrigin'] = (string) ($quizProvenance['quizOrigin'] ?? '');
            $copy['quizSourceLabel'] = (string) ($quizProvenance['quizSourceLabel'] ?? '');
            $copy['timeSource'] = 'academic-term7';
            if (isset($session['oneOffStart'])) {
                $copy['start'] = (string) $session['oneOffStart'];
                if (!empty($session['oneOffEndUnconfirmed'])) $copy['end'] = '';
                $copy['timeSource'] = 'one-off-academic-notice';
            }
            if (!empty($session['oneOffTimeUnconfirmed'])) {
                $copy['start'] = '';
                $copy['end'] = '';
                $copy['timeSource'] = 'one-off-time-unconfirmed';
            }
            if (isset($session['rescheduleReason'])) $copy['rescheduleReason'] = (string) $session['rescheduleReason'];
            $clockKey = $slug . '|' . $jalaliDate . '|' . ($single === null ? $sessionKey : (string) $single);
            $clockOverride = classops_term7_syllabus_one_off_clock_overrides()[$clockKey] ?? null;
            if (is_array($clockOverride)) {
                $copy['start'] = (string) $clockOverride['start'];
                $copy['end'] = (string) $clockOverride['end'];
                $copy['timeSource'] = 'one-off-academic-notice';
                $copy['rescheduleReason'] = (string) $clockOverride['reason'];
            }
            $copy['segments'] = is_array($session['segments'] ?? null) ? $session['segments'] : [];
            $copy['sourceFile'] = (string) ($course['sourceFile'] ?? '');
            $copy['sourcePage'] = max(0, (int) ($session['sourcePage'] ?? 0));
            $sourceDates = is_array($session['dates'] ?? null) ? $session['dates'] : [];
            $copy['sourceDate'] = (string) (($session['sourceDate'] ?? '') ?: ($sourceDates[0] ?? ''));
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
            'پاتو_عملی۱',
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
                    'resident' => trim((string) ($session['resident'] ?? '')),
                    'sessionMode' => $mode,
                    'sessionModeLabel' => trim((string) ($session['sessionModeLabel'] ?? '')) ?: classops_term7_syllabus_mode_label($mode),
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
