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

function hamiad_assignment_detail(int $courseId, int $activityId): array
{
    $activities = hamiad_course_activities($courseId);
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
    $due = '';
    foreach ($fields as $key => $value) {
        if (preg_match('/موعد|مهلت|تحویل|سررسید|due date|deadline/iu', $key)) {
            $due = $value; break;
        }
    }
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
        'title' => $matching[0]['title'], 'dueText' => $due,
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
            $task['courseTitle'] = $course['title'];
            $allAssignments[] = $task;
        }
    }
    unset($course);
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
