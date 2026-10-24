<?php
declare(strict_types=1);

require_once __DIR__ . '/hamiad_courses.php';

/** One canonical term discriminator from the official live Moodle course names. */
function hamiad_term7_courses(): array
{
    $courses = [];
    foreach (hamiad_course_list() as $course) {
        $name = strtr((string) ($course['title'] ?? ''), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
        if (!preg_match('/(?:^|\s|-)\s*ترم\s*14051(?:\s|$|-)/u', $name)) {
            continue;
        }
        $courses[] = ['id' => (int) $course['id'], 'title' => (string) $course['title']];
    }
    return $courses;
}

/** Activities are obtained from the real course page, not persisted text or old semester data. */
function hamiad_course_activities(int $courseId): array
{
    if ($courseId <= 0) {
        throw new RuntimeException('درس معتبر نیست.');
    }
    $cookies = hamiad_load_cookies();
    $html = hamiad_get_html_with_waf_retry(
        'https://lms.tums.ac.ir/course/view.php?id=' . $courseId, $cookies
    );
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try { $dom->loadHTML('<?xml encoding="UTF-8"?>' . $html); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    $types = ['resource' => 'فایل', 'folder' => 'پوشه', 'assign' => 'تکلیف', 'page' => 'صفحه آموزشی'];
    $items = [];
    foreach ($dom->getElementsByTagName('a') as $a) {
        $url = html_entity_decode((string) $a->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['port'])
            || ($parts['host'] ?? 'lms.tums.ac.ir') !== 'lms.tums.ac.ir') {
            continue;
        }
        $path = (string) ($parts['path'] ?? '');
        if (!preg_match('~^/mod/(resource|folder|assign|page)/view\.php$~', $path, $typeMatch)) {
            continue;
        }
        parse_str((string) ($parts['query'] ?? ''), $query);
        $id = filter_var($query['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) { continue; }
        $kind = $typeMatch[1];
        $name = trim(preg_replace('/\s+/u', ' ', (string) $a->textContent) ?: '');
        if ($name === '') { continue; }
        $name = preg_replace('/\s+(?:فایل|تکلیف|پوشه)\s*$/u', '', $name) ?: $name;
        // Moodle accessibility anchors can prefix an assignment with an
        // erroneous "deadline passed" announcement and append course metadata.
        // Real due state is always obtained from assignment detail, never here.
        if ($kind === 'assign' && preg_match('/^موعد\s+(.+?)\s+سر\s*رسیده\s*است(?:\s|$)/u', $name, $titleMatch)) {
            $name = trim($titleMatch[1]);
        }
        $key = $kind . ':' . $id;
        if (!isset($items[$key]) || mb_strlen($name) > mb_strlen($items[$key]['title'])) {
            $items[$key] = [
                'id' => (int) $id, 'courseId' => $courseId,
                'kind' => $kind, 'type' => $types[$kind],
                'title' => mb_substr($name, 0, 160),
            ];
        }
    }
    return array_values($items);
}

/**
 * Some Moodle views expose only a relative countdown, not an absolute due date.
 * Never interpret the assignment TITLE (which may say "سررسیده") as the due status.
 */
function hamiad_remaining_seconds(string $value): ?int
{
    $normalized = strtr($value, [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
        '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
        '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
    ]);
    if (str_contains($normalized, 'زمان مشخص نشده') || str_contains($normalized, 'نامشخص')) {
        return null;
    }
    $seconds = 0;
    $matched = false;
    foreach (['روز'=>86400,'ساعت'=>3600,'دقیقه'=>60] as $unit => $factor) {
        if (preg_match('/(\d+)\s*' . $unit . '/u', $normalized, $parts)) {
            $seconds += (int)$parts[1] * $factor;
            $matched = true;
        }
    }
    if (!$matched) {
        return null;
    }
    if (preg_match('/گذشته|سپری|منقضی/u', $normalized)) {
        return -$seconds;
    }
    if (preg_match('/باقی|مانده/u', $normalized)) {
        return $seconds;
    }
    return null;
}

function hamiad_assignment_detail(int $courseId, int $activityId, ?array $knownActivities = null): array
{
    $activities = $knownActivities ?? hamiad_course_activities($courseId);
    $matching = array_values(array_filter($activities, static fn($a): bool =>
        $a['kind'] === 'assign' && $a['id'] === $activityId
    ));
    if (!$matching) {
        throw new RuntimeException('تکلیف در این درس شناسایی نشد.');
    }
    $cookies = hamiad_load_cookies();
    $html = hamiad_get_html_with_waf_retry(
        'https://lms.tums.ac.ir/mod/assign/view.php?id=' . $activityId, $cookies
    );
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try { $dom->loadHTML('<?xml encoding="UTF-8"?>' . $html); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    $fields = [];
    foreach ($dom->getElementsByTagName('tr') as $tr) {
        $heads = $tr->getElementsByTagName('th');
        $cells = $tr->getElementsByTagName('td');
        if ($heads->length < 1 || $cells->length < 1) { continue; }
        $key = trim(preg_replace('/\s+/u', ' ', $heads->item(0)->textContent) ?: '');
        $value = trim(preg_replace('/\s+/u', ' ', $cells->item(0)->textContent) ?: '');
        if ($key !== '' && $value !== '') {
            $fields[mb_substr($key, 0, 80)] = mb_substr($value, 0, 240);
        }
    }
    $submissionStatus = (string) ($fields['وضعیت تحویل'] ?? '');
    $gradingStatus = (string) ($fields['وضعیت تصحیح'] ?? '');
    $remainingText = (string) ($fields['زمان باقیمانده'] ?? '');
    $remainingSeconds = hamiad_remaining_seconds($remainingText);
    $dueAt = $remainingSeconds === null ? '' : date(DATE_ATOM, time() + $remainingSeconds);
    $due = $remainingText;
    if ($due === '') {
        foreach ($fields as $key => $value) {
            if (preg_match('/(?:^|\\s)(?:موعد|مهلت|سررسید|تاریخ تحویل|due date|deadline)(?:\\s|$)/iu', $key)) {
                $due = $value; break;
            }
        }
    }
    $submitted = $submissionStatus !== '' && !preg_match('/هیچ ارسالی|ارسال نشده|عدم ارسال/u', $submissionStatus);
    $dueStatus = $submitted ? 'submitted' : ($remainingSeconds === null
        ? 'unknown' : ($remainingSeconds <= 0 ? 'overdue' : 'upcoming'));
    $description = '';
    $xpath = new DOMXPath($dom);
    foreach (['//*[@id="intro"]', '//*[contains(concat(" ",normalize-space(@class)," ")," activity-description ")]', '//*[contains(concat(" ",normalize-space(@class)," ")," box generalbox ")]'] as $selector) {
        $nodes = $xpath->query($selector);
        if ($nodes && $nodes->length) {
            $description = trim(preg_replace('/\s+/u', ' ', (string)$nodes->item(0)->textContent) ?: '');
            if ($description !== '') { break; }
        }
    }
    return [
        'courseId' => $courseId, 'id' => $activityId,
        'title' => $matching[0]['title'], 'dueText' => $due, 'dueAt' => $dueAt,
        'duePrecision' => $remainingSeconds !== null ? 'relative' : 'unknown',
        'dueStatus' => $dueStatus, 'submissionStatus' => $submissionStatus,
        'gradingStatus' => $gradingStatus, 'remainingText' => $remainingText,
        'description' => mb_substr($description, 0, 2000), 'fields' => $fields,
    ];
}

/** Owner-only snapshot. No cross-student identity inference and no Navid fallback. */
function hamiad_term7_dashboard(): array
{
    $status = hamiad_session_status();
    if (!$status['connected']) {
        return ['connected' => false, 'courses' => [], 'assignments' => [], 'resources' => 0];
    }
    $courses = hamiad_term7_courses();
    $allAssignments = [];
    $resources = 0;
    foreach ($courses as &$course) {
        $activities = hamiad_course_activities((int) $course['id']);
        $tasks = array_values(array_filter($activities, static fn($a): bool => $a['kind'] === 'assign'));
        $course['resourceCount'] = count(array_filter($activities, static fn($a): bool => $a['kind'] !== 'assign'));
        $course['assignmentCount'] = count($tasks);
        $resources += $course['resourceCount'];
        foreach ($tasks as $task) {
            // Reuse the already parsed course activities; the detail remains the
            // single source of truth for due time and submission status.
            try {
                $detail = hamiad_assignment_detail((int)$course['id'], (int)$task['id'], $activities);
                $task = array_merge($task, $detail);
            } catch (RuntimeException $error) {
                $task['dueStatus'] = 'unknown';
                $task['dueText'] = '';
                $task['dueAt'] = '';
                $task['detailError'] = 'جزئیات تکلیف فعلاً قابل دریافت نیست.';
            }
            $task['courseTitle'] = $course['title'];
            $allAssignments[] = $task;
        }
    }
    unset($course);
    usort($allAssignments, static function (array $a, array $b): int {
        $ta = strtotime((string)($a['dueAt'] ?? '')) ?: PHP_INT_MAX;
        $tb = strtotime((string)($b['dueAt'] ?? '')) ?: PHP_INT_MAX;
        return ($ta <=> $tb) ?: strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
    });
    return [
        'connected' => true, 'courses' => $courses,
        'assignments' => $allAssignments, 'resources' => $resources,
        'checkedAt' => date(DATE_ATOM),
    ];
}

function hamiad_term7_course_view(int $courseId): array
{
    $course = null;
    foreach (hamiad_term7_courses() as $candidate) {
        if ((int)$candidate['id'] === $courseId) {
            $course = $candidate;
            break;
        }
    }
    if ($course === null) {
        throw new RuntimeException('این درس به ترم جاری حساب متصل تعلق ندارد.');
    }
    return ['course' => $course, 'items' => hamiad_course_activities($courseId)];
}
