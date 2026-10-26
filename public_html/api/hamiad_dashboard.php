<?php
declare(strict_types=1);

require_once __DIR__ . '/hamiad_courses.php';
require_once __DIR__ . '/hamiad_calendar.php';

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

/** Parse official Moodle dates without treating CLOSED or relative labels as a deadline. */
function hamiad_date_iso(string $value): string
{
    $raw = trim(strtr($value, [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        'ي'=>'ی','ك'=>'ک',
    ]));
    if ($raw === '' || preg_match('/^(?:-+|نامشخص|هیچ|never|none|closed|بسته)/iu', $raw)) return '';
    $year=0; $month=0; $day=0;
    $months=['فروردین'=>1,'اردیبهشت'=>2,'خرداد'=>3,'تیر'=>4,'مرداد'=>5,'شهریور'=>6,'مهر'=>7,'آبان'=>8,'آذر'=>9,'دی'=>10,'بهمن'=>11,'اسفند'=>12];
    if (preg_match('/(?<!\d)(1[34]\d{2})[\/-](\d{1,2})[\/-](\d{1,2})(?!\d)/u', $raw, $m)) {
        [$year,$month,$day]=[(int)$m[1],(int)$m[2],(int)$m[3]];
    } else {
        foreach ($months as $name=>$number) {
            if (preg_match('/(?<!\d)(\d{1,2})\s*'.preg_quote($name,'/').'\s*(1[34]\d{2})(?!\d)/u', $raw, $m)) {
                [$year,$month,$day]=[(int)$m[2],$number,(int)$m[1]]; break;
            }
        }
    }
    if ($year) {
        if ($month<1 || $month>12 || $day<1 || $day>($month<=6?31:($month<=11?30:30))) return '';
        if (!class_exists('IntlCalendar')) return ''; // Never invent calendar conversion.
        $clock='00:00';
        if (preg_match('/(?<!\d)([01]?\d|2[0-3]):([0-5]\d)/', $raw, $m)) {
            $hour=(int)$m[1];
            if ($hour===12 && preg_match('/صبح|بامداد/u', $raw)) $hour=0;
            elseif ($hour<12 && preg_match('/بعد\s*از\s*ظهر|بعدازظهر|عصر|شب/u', $raw)) $hour+=12;
            $clock=sprintf('%02d:%02d',$hour,(int)$m[2]);
        }
        $cal=IntlCalendar::createInstance('Asia/Tehran','fa_IR@calendar=persian');
        if (!$cal || $cal->getType() !== 'persian') return '';
        $cal->clear(); $cal->set($year,$month-1,$day,(int)substr($clock,0,2),(int)substr($clock,3,2),0);
        $seconds=$cal->getTime();
        if ($seconds===false) return '';
        return (new DateTimeImmutable('@'.(string)round($seconds/1000)))
            ->setTimezone(new DateTimeZone('Asia/Tehran'))->format(DATE_ATOM);
    }
    // For Gregorian Moodle dates, only parse an actual date, never a countdown.
    if (!preg_match('/20\d{2}[-\/](?:0?[1-9]|1[012])[-\/](?:0?[1-9]|[12]\d|3[01])|\b(?:January|February|March|April|May|June|July|August|September|October|November|December)\b/iu', $raw)) return '';
    try { return (new DateTimeImmutable($raw,new DateTimeZone('Asia/Tehran')))->format(DATE_ATOM); }
    catch (Throwable $error) { return ''; }
}

function hamiad_date_field(array $fields, string $pattern): array
{
    foreach ($fields as $key=>$value) {
        if (!preg_match($pattern, trim((string)$key))) continue;
        $text=trim((string)$value);
        if ($text === '') continue;
        return ['text'=>$text,'at'=>hamiad_date_iso($text)];
    }
    return ['text'=>'','at'=>''];
}

function hamiad_assignment_timing(array $fields, int $clock): array
{
    $open=hamiad_date_field($fields,'/(?:شروع\s*(?:ارسال|تحویل|تکلیف)|تاریخ\s*شروع|از\s*تاریخ|Allow submissions from|Available from|Open(?:ing)? date|Start date)/iu');
    $due=hamiad_date_field($fields,'/(?:تاریخ\s*تحویل|موعد\s*تحویل|مهلت\s*تحویل|سررسید|Due date|Deadline)/iu');
    $cutoff=hamiad_date_field($fields,'/(?:آخرین\s*مهلت|مهلت\s*نهایی|Cut.?off date|Final submission date)/iu');
    $last=$cutoff['text']!==''?$cutoff:$due;
    $submission=trim((string)($fields['وضعیت تحویل']??$fields['Submission status']??$fields['وضعیت ارسال']??''));
    $submitted=(bool)preg_match('/(?:Submitted for grading|Submitted|ارسال\s*شده|تحویل\s*شده|ثبت\s*شده)/iu',$submission)
        && !preg_match('/(?:Not submitted|No submission|ارسال\s*نشده|هیچ\s*ارسال|عدم\s*ارسال)/iu',$submission);
    $closed=(bool)preg_match('/(?:^|\W)(?:CLOSED|Closed)(?:\W|$)|بسته|غیرفعال/iu',$submission);
    $startTime=$open['at']!==''?strtotime($open['at']):false;
    $lastTime=$last['at']!==''?strtotime($last['at']):false;
    if ($submitted) $status='submitted';
    elseif ($startTime!==false && $startTime>$clock) $status='not_open';
    elseif ($lastTime!==false && $lastTime<=$clock) $status='overdue';
    elseif ($closed) $status='closed';
    elseif ($lastTime!==false && $lastTime>$clock) $status='upcoming';
    else $status='unknown';
    return [
        'opensAt'=>$open['at'],'opensText'=>$open['text'],
        'dueAt'=>$due['at'],'dueDateText'=>$due['text'],
        'cutoffAt'=>$cutoff['at'],'cutoffText'=>$cutoff['text'],
        'deadlineAt'=>$last['at'],'deadlineText'=>$last['text'],
        'dueStatus'=>$status,'submissionStatus'=>$submission,
        'submissionWindowStatus'=>$status==='not_open'?'not_open':($status==='submitted'?'submitted':($status==='overdue'||$status==='closed'?'closed':($status==='upcoming'?'open':'unknown'))),
    ];
}

function hamiad_assignment_snapshot_path(): string
{
    return dent_storage_path('hamiad/assignments-snapshot.json');
}

/** Last verified good owner-only snapshot survives connection errors and new code deployments. */
function hamiad_assignment_snapshot_read(): ?array
{
    $stored=dent_read_json_file(hamiad_assignment_snapshot_path(),[]);
    $snapshot=$stored['dashboard']??null;
    return is_array($snapshot) && !empty($snapshot['courses']) && is_array($snapshot['assignments']??null)
        ? $snapshot : null;
}

function hamiad_assignment_snapshot_save(array $dashboard): void
{
    if (empty($dashboard['courses']) || !is_array($dashboard['assignments']??null)) {
        throw new RuntimeException('اطلاعات همیاد ناقص است؛ نسخه معتبر قبلی حفظ شد.');
    }
    $prior=hamiad_assignment_snapshot_read();
    if (count($dashboard['assignments']) === 0 && count($prior['assignments'] ?? []) > 0) {
        throw new RuntimeException('پاسخ بدون تکلیف همیاد نیازمند تأیید است؛ اطلاعات قبلی حفظ شد.');
    }
    dent_write_json_file(hamiad_assignment_snapshot_path(),[
        'version'=>1,'savedAt'=>date(DATE_ATOM),'dashboard'=>$dashboard,
    ]);
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
    $timing = hamiad_assignment_timing($fields, time());
    $gradingStatus = (string) ($fields['وضعیت تصحیح'] ?? $fields['Grading status'] ?? '');
    $remainingText = (string) ($fields['زمان باقیمانده'] ?? $fields['Time remaining'] ?? '');
    $remainingSeconds = hamiad_remaining_seconds($remainingText);
    // A relative countdown is display-only. CLOSED is not proof of expiry.
    $due = $timing['deadlineText'] !== '' ? $timing['deadlineText'] : $remainingText;
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
        'dueAt' => $timing['deadlineAt'],
        'duePrecision' => $timing['deadlineAt'] !== '' ? 'absolute' : 'unknown',
        'dueStatus' => $timing['dueStatus'], 'submissionStatus' => $timing['submissionStatus'],
        'submissionWindowStatus' => $timing['submissionWindowStatus'],
        'opensAt' => $timing['opensAt'], 'opensText' => $timing['opensText'],
        'dueDateAt' => $timing['dueAt'], 'dueDateText' => $timing['dueDateText'],
        'cutoffAt' => $timing['cutoffAt'], 'cutoffText' => $timing['cutoffText'],
        'deadlineAt' => $timing['deadlineAt'], 'deadlineText' => $timing['deadlineText'],
        'gradingStatus' => $gradingStatus, 'remainingText' => $remainingText,
        'description' => mb_substr($description, 0, 2000), 'fields' => $fields,
    ];
}

/** Owner-only snapshot. No cross-student identity inference and no Navid fallback. */
function hamiad_term7_dashboard(): array
{
    $cached = hamiad_assignment_snapshot_read();
    $status = hamiad_session_status();
    if (!$status['connected']) {
        return $cached === null
            ? ['connected'=>false,'courses'=>[],'assignments'=>[],'resources'=>0,'stale'=>false]
            : array_merge($cached,['connected'=>false,'stale'=>true,'notice'=>'اطلاعات ذخیره‌شده؛ اتصال همیاد نیازمند ورود مجدد است.']);
    }
    try {
        $courses = hamiad_term7_courses();
        // An empty response is not evidence that every previously found course vanished.
        if ($courses === []) {
            throw new RuntimeException('فهرست درس‌های همیاد به‌صورت معتبر دریافت نشد.');
        }
        $allAssignments = [];
        $resources = 0;
        $previous = [];
        foreach (($cached['assignments'] ?? []) as $oldTask) {
            if (is_array($oldTask)) $previous[hamiad_assignment_identity_key($oldTask)]=$oldTask;
        }
        $detailFailed = false;
        foreach ($courses as &$course) {
            $activities = hamiad_course_activities((int)$course['id']);
            $tasks = array_values(array_filter($activities,static fn($a):bool=>$a['kind']==='assign'));
            $course['resourceCount']=count(array_filter($activities,static fn($a):bool=>$a['kind']!=='assign'));
            $course['assignmentCount']=count($tasks);
            $resources+=$course['resourceCount'];
            foreach ($tasks as $task) {
                $key=hamiad_assignment_identity_key($task);
                try { $task=array_merge($task,hamiad_assignment_detail((int)$course['id'],(int)$task['id'],$activities)); }
                catch (RuntimeException $error) {
                    $detailFailed=true;
                    if (isset($previous[$key])) {
                        $task=array_merge($task,$previous[$key]);
                    } else {
                        $task+=['dueStatus'=>'unknown','dueText'=>'','dueAt'=>'','deadlineAt'=>'','deadlineText'=>'','opensAt'=>'','opensText'=>''];
                    }
                    $task['detailStale']=true;
                    $task['detailError']='جزئیات این تکلیف در همگام‌سازی اخیر دریافت نشد.';
                }
                $task['courseTitle']=$course['title'];
                $allAssignments[]=$task;
            }
        }
        unset($course);
        // The Moodle assignment-detail table often omits dates. Its official
        // calendar contains opening/due events keyed by the real activity ID.
        $calendarDates=[];
        try { $calendarDates=hamiad_calendar_assignment_timings($allAssignments); }
        catch (RuntimeException $error) { $detailFailed=true; }
        foreach ($allAssignments as &$task) {
            $id=(int)($task['id']??0);
            $key=hamiad_assignment_identity_key($task);
            $record=$calendarDates[$id]??null;
            if (is_array($record)) {
                $timing=hamiad_assignment_timing([
                    'شروع ارسال'=>(string)($record['opensText']??''),
                    'موعد تحویل'=>(string)($record['dueDateText']??''),
                    'وضعیت تحویل'=>(string)($task['submissionStatus']??''),
                ],time());
                if ($timing['opensAt']!=='' && $timing['dueAt']!=='') {
                    $task=array_merge($task,$timing);
                    $task['dueAt']=$timing['deadlineAt'];
                    $task['dueText']=$timing['deadlineText'];
                    $task['duePrecision']='absolute';
                    continue;
                }
            }
            // A temporarily incomplete calendar response must not erase the
            // previously confirmed start and deadline or trigger fresh alerts.
            $prior=$previous[$key]??null;
            if (is_array($prior) && !empty($prior['deadlineAt'])) {
                foreach (['opensAt','opensText','dueDateAt','dueDateText','cutoffAt','cutoffText',
                    'dueAt','dueText','duePrecision','deadlineAt','deadlineText','dueStatus',
                    'submissionWindowStatus'] as $field) {
                    if (array_key_exists($field,$prior)) $task[$field]=$prior[$field];
                }
                $task['detailStale']=true;
                $detailFailed=true;
            }
        }
        unset($task);
        usort($allAssignments,static function(array $a,array $b):int{
            $ta=strtotime((string)($a['deadlineAt']??$a['dueAt']??'')) ?: PHP_INT_MAX;
            $tb=strtotime((string)($b['deadlineAt']??$b['dueAt']??'')) ?: PHP_INT_MAX;
            return ($ta<=>$tb) ?: strcmp((string)($a['title']??''),(string)($b['title']??''));
        });
        $dashboard=[
            'connected'=>true,'courses'=>$courses,'assignments'=>$allAssignments,
            'resources'=>$resources,'checkedAt'=>date(DATE_ATOM),'stale'=>false,
            'partial'=>$detailFailed,
        ];
        // Preserve complete dates if one detail request failed; never replace a complete snapshot.
        if (!$detailFailed) hamiad_assignment_snapshot_save($dashboard);
        return $dashboard;
    } catch (RuntimeException $error) {
        if ($cached === null) throw $error;
        return array_merge($cached,[
            'connected'=>true,'stale'=>true,'notice'=>'همیاد در دسترس نیست؛ آخرین اطلاعات معتبر ذخیره‌شده نمایش داده می‌شود.',
            'liveError'=>'همگام‌سازی جدید ناموفق بود.',
        ]);
    }
}

function hamiad_assignment_identity_key(array $task): string
{
    return (int)($task['courseId']??0).':'.(int)($task['id']??0);
}

/** Owner-authenticated callers may read a previously verified activity while LMS is offline. */
function hamiad_assignment_detail_or_snapshot(int $courseId, int $activityId): array
{
    $cached = hamiad_assignment_snapshot_read();
    $cachedTask = null;
    $courseAllowed = false;
    foreach (($cached['courses'] ?? []) as $course) {
        if ((int)($course['id'] ?? 0) === $courseId) { $courseAllowed=true; break; }
    }
    foreach (($cached['assignments'] ?? []) as $task) {
        if ((int)($task['courseId']??0)===$courseId && (int)($task['id']??0)===$activityId) {
            $cachedTask=$task; break;
        }
    }
    if (!$courseAllowed) {
        foreach (hamiad_term7_courses() as $course) {
            if ((int)($course['id']??0)===$courseId) { $courseAllowed=true; break; }
        }
    }
    if (!$courseAllowed) throw new RuntimeException('تکلیف متعلق به درس‌های ترم جاری نیست.');
    if (!empty(hamiad_session_status()['connected'])) {
        try { return hamiad_assignment_detail($courseId, $activityId); }
        catch (RuntimeException $error) { /* Last verified record remains readable. */ }
    }
    if ($cachedTask !== null) {
        $cachedTask['detailStale']=true;
        return $cachedTask;
    }
    throw new RuntimeException('جزئیات معتبر این تکلیف هنوز همگام‌سازی نشده است.');
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
