<?php
declare(strict_types=1);

require_once __DIR__ . '/classops_partial_theory_syllabus.php';
require_once __DIR__ . '/classops_term7_syllabus_data.php';

const CLASSOPS_TERM7_SYLLABUS_VERSION = '1405-1406-1.corrected.1';

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

function classops_term7_syllabus_sessions_for_date(array $course, string $jalaliDate): array
{
    $sessions = [];
    foreach (is_array($course['sessions'] ?? null) ? $course['sessions'] : [] as $session) {
        if (!is_array($session)) continue;
        $dates = array_values(array_filter(array_map(
            static fn($value): string => trim((string) $value),
            is_array($session['dates'] ?? null) ? $session['dates'] : []
        )));
        if (in_array($jalaliDate, $dates, true)) {
            $sessions[] = $session;
        }
    }
    usort($sessions, static function (array $left, array $right): int {
        $a = (int) (($left['sessionNumbers'][0] ?? null) ?: ($left['sessionNumber'] ?? 0));
        $b = (int) (($right['sessionNumbers'][0] ?? null) ?: ($right['sessionNumber'] ?? 0));
        if ($a !== $b) return $a <=> $b;
        return strcmp((string) ($left['sessionKey'] ?? ''), (string) ($right['sessionKey'] ?? ''));
    });
    return $sessions;
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

function classops_term7_syllabus_enrich_events(array $events, string $jalaliDate): array
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
        $sessions = classops_term7_syllabus_sessions_for_date($course, $jalaliDate);
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
