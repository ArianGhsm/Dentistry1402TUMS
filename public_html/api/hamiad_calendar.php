<?php
declare(strict_types=1);

require_once __DIR__ . '/hamiad_courses.php';

/** Parse only known assignment IDs in the owner's real Moodle month calendar. */
function hamiad_calendar_month_events(string $html, array $wanted): array
{
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try { $dom->loadHTML('<?xml encoding="UTF-8"?>' . $html); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    $xpath = new DOMXPath($dom);
    $found = [];
    foreach ($xpath->query('//li[@data-region="event-item" and @data-event-component="mod_assign"]') as $item) {
        if (!$item instanceof DOMElement || !in_array($item->getAttribute('data-event-eventtype'), ['due', 'open'], true)) continue;
        foreach ($item->getElementsByTagName('a') as $link) {
            $parts = parse_url((string) $link->getAttribute('href'));
            if (!is_array($parts) || ($parts['host'] ?? 'lms.tums.ac.ir') !== 'lms.tums.ac.ir'
                || ($parts['path'] ?? '') !== '/mod/assign/view.php') continue;
            parse_str((string) ($parts['query'] ?? ''), $query);
            $id = filter_var($query['id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$id || !isset($wanted[$id])) continue;
            $day = $item->parentNode;
            while ($day instanceof DOMNode && !($day instanceof DOMElement && $day->hasAttribute('data-day'))) {
                $day = $day->parentNode;
            }
            if (!$day instanceof DOMElement) continue;
            foreach ($xpath->query('.//*[@data-timestamp]', $day) as $dayLink) {
                if (!$dayLink instanceof DOMElement) continue;
                $timestamp = filter_var($dayLink->getAttribute('data-timestamp'), FILTER_VALIDATE_INT);
                if ($timestamp && $timestamp > 0) {
                    $found[$id] = $timestamp;
                    break;
                }
            }
        }
    }
    return $found;
}

/** Extract the official opening/due text from an event linked to that exact activity. */
function hamiad_calendar_day_details(string $html, int $assignmentId): array
{
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try { $dom->loadHTML('<?xml encoding="UTF-8"?>' . $html); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//div[@data-event-title]') as $event) {
        $matched = false;
        foreach ($event->getElementsByTagName('a') as $link) {
            $parts = parse_url((string) $link->getAttribute('href'));
            if (!is_array($parts) || ($parts['host'] ?? 'lms.tums.ac.ir') !== 'lms.tums.ac.ir'
                || ($parts['path'] ?? '') !== '/mod/assign/view.php') continue;
            parse_str((string) ($parts['query'] ?? ''), $query);
            if ((int) ($query['id'] ?? 0) === $assignmentId) { $matched = true; break; }
        }
        if (!$matched) continue;
        $dates = ['opensText'=>'','dueDateText'=>''];
        foreach ($xpath->query('.//li', $event) as $li) {
            if (!$li instanceof DOMElement) continue;
            $class = ' ' . preg_replace('/\s+/u', ' ', $li->getAttribute('class')) . ' ';
            $key = str_contains($class, ' calendaropen ') ? 'opensText'
                : (str_contains($class, ' calendardue ') ? 'dueDateText' : '');
            if ($key === '') continue; // Grading dates are not submission cutoffs.
            $label = trim(preg_replace('/\s+/u', ' ', (string) $li->textContent) ?: '');
            $label = preg_replace('/^(?:شروع\s*:\s*«?|موعد\s+)/u', '', $label) ?: $label;
            $label = preg_replace('/\s+سر\s*رسیده\s*است\s*$/u', '', $label) ?: $label;
            $label = trim($label, " \t\n\r\0\x0B«»");
            $dates[$key] = strtr($label, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
        }
        if ($dates['opensText'] !== '' && $dates['dueDateText'] !== '') return $dates;
    }
    return [];
}

/** Calendar data supplements the assignment page, never guessed from its countdown. */
function hamiad_calendar_assignment_timings(array $assignments): array
{
    $wanted = [];
    foreach ($assignments as $task) {
        $id = (int) ($task['id'] ?? 0);
        if ($id > 0 && (int) ($task['courseId'] ?? 0) > 0) $wanted[$id] = true;
    }
    if (!$wanted) return [];
    $cookies = hamiad_load_cookies();
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Tehran'));
    $found = [];
    // One owner-only bounded scan; day views reveal BOTH dates, even when the
    // opening event was in an earlier month. Never infer dates from titles.
    foreach ([0, 30, 60, 90, 120, 150, 180, -30] as $offset) {
        $timestamp = $now->modify(($offset < 0 ? '' : '+') . $offset . ' days')->getTimestamp();
        $html = hamiad_get_html_with_waf_retry(
            'https://lms.tums.ac.ir/calendar/view.php?view=month&time=' . $timestamp,
            $cookies
        );
        foreach (hamiad_calendar_month_events($html, $wanted) as $id=>$dayTimestamp) {
            $found[$id] = $dayTimestamp;
        }
        if (count($found) === count($wanted)) break;
    }
    $dates = [];
    foreach ($found as $id=>$timestamp) {
        $html = hamiad_get_html_with_waf_retry(
            'https://lms.tums.ac.ir/calendar/view.php?view=day&time=' . $timestamp,
            $cookies
        );
        $detail = hamiad_calendar_day_details($html, (int) $id);
        if ($detail) $dates[$id] = $detail;
    }
    return $dates;
}
