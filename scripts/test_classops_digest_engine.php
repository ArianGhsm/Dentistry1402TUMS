<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/public_html/api/classops_modules/digests/digest_engine.php';

function digest_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function digest_expect(string $code, callable $callback): void
{
    try {
        $callback();
    } catch (DentClassOpsDigestException $exception) {
        digest_assert($exception->reasonCode === $code, "Expected {$code}, got {$exception->reasonCode}");
        return;
    }
    throw new RuntimeException("Expected exception {$code}");
}

function digest_timing(?string $start = null, ?string $end = null, ?string $due = null, bool $allDay = false, ?string $localDate = null): array
{
    return [
        'allDay' => $allDay,
        'localDate' => $localDate,
        'startsAtUtc' => $start,
        'endsAtUtc' => $end,
        'dueAtUtc' => $due,
        'timezone' => 'Asia/Tehran',
    ];
}

function digest_visibility(bool $studentAllowed = true, bool $ownerAllowed = true, ?array $subject = null): array
{
    return ['studentAllowed' => $studentAllowed, 'ownerAllowed' => $ownerAllowed, 'subject' => $subject];
}

function digest_record(string $ref, string $type, array $overrides = []): array
{
    $base = [
        'recordVersion' => 'classops-digest-record-v1',
        'entityRef' => $ref,
        'revision' => 1,
        'cohortKey' => 'dentistry-1402',
        'source' => 'classops',
        'itemType' => $type,
        'title' => 'Fixture ' . $ref,
        'description' => '',
        'course' => null,
        'location' => '',
        'importance' => 'normal',
        'status' => 'active',
        'scheduleRef' => null,
        'timing' => digest_timing(),
        'visibility' => digest_visibility(),
        'state' => ['taskState' => null, 'ackState' => 'not_required'],
        'change' => ['kind' => 'none', 'changedAtUtc' => null],
        'supersedesRef' => null,
    ];
    foreach ($overrides as $key => $value) {
        $base[$key] = $value;
    }
    return $base;
}

function digest_request(string $kind, array $records, array $overrides = []): array
{
    $base = [
        'contractVersion' => 'classops-digest-v1',
        'digestKind' => $kind,
        'viewer' => [
            'scope' => 'student',
            'cohortKey' => 'dentistry-1402',
            'studentNumber' => '402000001',
            'canonicalUserId' => 'usr_fixture_402000001',
        ],
        'window' => ['nowUtc' => '2026-09-08T12:00:00Z', 'timezone' => 'Asia/Tehran'],
        'budget' => ['maxItems' => 60, 'maxEstimatedChars' => 7000],
        'records' => $records,
    ];
    foreach ($overrides as $key => $value) {
        $base[$key] = $value;
    }
    return $base;
}

function digest_items(array $view, string $sectionKey): array
{
    foreach ($view['sections'] as $section) {
        if ($section['key'] === $sectionKey) {
            return $section['items'];
        }
    }
    throw new RuntimeException('Unknown section: ' . $sectionKey);
}

function digest_entity_refs(array $view): array
{
    $refs = [];
    foreach ($view['sections'] as $section) {
        foreach ($section['items'] as $item) {
            $refs[] = $item['entityRef'];
        }
    }
    return $refs;
}

// Empty day/week are deterministic and useful.
$emptyTomorrow = classops_digest_build(digest_request('tomorrow', []));
$emptyWeek = classops_digest_build(digest_request('weekly', []));
digest_assert($emptyTomorrow['empty'] === true && str_contains($emptyTomorrow['plainText'], 'موردی ثبت نشده'), 'empty tomorrow summary is not useful');
digest_assert($emptyWeek['empty'] === true && $emptyWeek['window']['weekStartsOn'] === 'saturday', 'empty weekly summary/window invalid');

// Mixed types + aggregation.
$mixed = [
    digest_record('evt_1', 'event', [
        'title' => 'کلاس فردا',
        'timing' => digest_timing('2026-09-09T04:00:00Z', '2026-09-09T05:00:00Z'),
    ]),
    digest_record('chg_1', 'class_change', [
        'title' => 'تغییر کلاس', 'importance' => 'important',
        'timing' => digest_timing('2026-09-09T06:00:00Z', '2026-09-09T07:00:00Z'),
    ]),
    digest_record('ddl_1', 'deadline', [
        'title' => 'ددلاین', 'timing' => digest_timing(null, null, '2026-09-09T17:00:00Z'),
    ]),
    digest_record('task_1', 'task', [
        'source' => 'task', 'status' => 'pending', 'state' => ['taskState' => 'pending', 'ackState' => 'not_required'],
    ]),
    digest_record('req_1', 'requirement', [
        'source' => 'requirement', 'status' => 'pending', 'state' => ['taskState' => 'overdue', 'ackState' => 'not_required'],
    ]),
    digest_record('exam_1', 'exam', [
        'source' => 'exam', 'importance' => 'important', 'timing' => digest_timing('2026-09-09T08:00:00Z', '2026-09-09T10:00:00Z'),
    ]),
    digest_record('ack_1', 'critical_notice', [
        'source' => 'ack', 'importance' => 'critical', 'state' => ['taskState' => null, 'ackState' => 'pending'],
    ]),
    digest_record('svc_1', 'service_reminder', [
        'source' => 'service', 'timing' => digest_timing(null, null, '2026-09-09T10:00:00Z'),
    ]),
];
$mixedView = classops_digest_build(digest_request('tomorrow', $mixed));
digest_assert(count(digest_items($mixedView, 'schedule')) === 2, 'event/class-change aggregation failed');
digest_assert(count(digest_items($mixedView, 'deadlines')) === 1, 'deadline aggregation failed');
digest_assert(count(digest_items($mixedView, 'tasks_requirements')) === 2, 'task/requirement aggregation failed');
digest_assert(count(digest_items($mixedView, 'exams')) === 1, 'exam aggregation failed');
digest_assert(count(digest_items($mixedView, 'critical_ack')) === 1, 'critical ACK aggregation failed');
digest_assert(count(digest_items($mixedView, 'service_reminders')) === 1, 'service reminder aggregation failed');

// Audience filtering + privacy. A mismatched subject must never escape into student output.
$privateRecords = [
    digest_record('mine', 'task', [
        'source' => 'task', 'status' => 'pending', 'state' => ['taskState' => 'pending', 'ackState' => 'not_required'],
        'title' => 'Mine',
        'visibility' => digest_visibility(true, true, ['kind' => 'studentNumber', 'value' => '402000001']),
    ]),
    digest_record('other', 'task', [
        'source' => 'task', 'status' => 'pending', 'state' => ['taskState' => 'pending', 'ackState' => 'not_required'],
        'title' => 'OTHER-STUDENT-SECRET',
        'visibility' => digest_visibility(true, true, ['kind' => 'studentNumber', 'value' => '402000999']),
    ]),
    digest_record('owner_only', 'task', [
        'source' => 'task', 'status' => 'pending', 'state' => ['taskState' => 'pending', 'ackState' => 'not_required'],
        'title' => 'Owner only', 'visibility' => digest_visibility(false, true, null),
    ]),
    digest_record('wrong_cohort', 'task', [
        'cohortKey' => 'medicine-1402', 'source' => 'task', 'status' => 'pending',
        'state' => ['taskState' => 'pending', 'ackState' => 'not_required'],
    ]),
];
$studentPrivate = classops_digest_build(digest_request('tomorrow', $privateRecords));
digest_assert(digest_entity_refs($studentPrivate) === ['mine'], 'student audience filtering leaked or omitted records');
digest_assert(!str_contains($studentPrivate['plainText'], 'OTHER-STUDENT-SECRET'), 'other student information leaked');
$ownerRequest = digest_request('tomorrow', $privateRecords);
$ownerRequest['viewer'] = ['scope' => 'owner', 'cohortKey' => 'dentistry-1402', 'studentNumber' => null, 'canonicalUserId' => null];
$ownerView = classops_digest_build($ownerRequest);
$ownerRefs = digest_entity_refs($ownerView);
sort($ownerRefs, SORT_STRING);
digest_assert($ownerRefs === ['mine', 'other', 'owner_only'], 'owner/global projection is not separated from student projection');

// Cancelled/revised/superseded revisions + deterministic dedupe.
$revisionRecords = [
    digest_record('rev_event', 'event', ['revision' => 1, 'title' => 'Old title', 'timing' => digest_timing('2026-09-09T04:00:00Z', '2026-09-09T05:00:00Z')]),
    digest_record('rev_event', 'event', ['revision' => 2, 'title' => 'New title', 'change' => ['kind' => 'revised', 'changedAtUtc' => '2026-09-08T09:00:00Z'], 'timing' => digest_timing('2026-09-09T04:00:00Z', '2026-09-09T05:00:00Z')]),
    digest_record('cancelled_event', 'event', ['status' => 'cancelled', 'change' => ['kind' => 'cancelled', 'changedAtUtc' => '2026-09-08T09:30:00Z'], 'timing' => digest_timing('2026-09-09T06:00:00Z', '2026-09-09T07:00:00Z')]),
    digest_record('old_slot', 'schedule_ref', ['source' => 'schedule', 'scheduleRef' => 'term7:old', 'timing' => digest_timing('2026-09-09T08:00:00Z', '2026-09-09T09:00:00Z')]),
    digest_record('replacement_slot', 'schedule_ref', ['source' => 'schedule', 'scheduleRef' => 'term7:new', 'supersedesRef' => 'old_slot', 'timing' => digest_timing('2026-09-09T08:00:00Z', '2026-09-09T09:00:00Z')]),
];
$revisionView = classops_digest_build(digest_request('tomorrow', $revisionRecords));
$revisionRefs = digest_entity_refs($revisionView);
digest_assert(in_array('rev_event', $revisionRefs, true) && !in_array('old_slot', $revisionRefs, true), 'revision/supersedes dedupe failed');
digest_assert(digest_items($revisionView, 'schedule')[0]['title'] !== 'Old title', 'older revision survived');
digest_assert(count(digest_items($revisionView, 'changes')) === 1 && digest_items($revisionView, 'changes')[0]['changeLabel'] === 'لغوشده', 'cancelled item not labelled');

$conflicting = digest_record('conflict', 'event', ['timing' => digest_timing('2026-09-09T04:00:00Z')]);
$conflicting2 = $conflicting;
$conflicting2['title'] = 'Different same revision';
digest_expect('CLASSOPS_DIGEST_CONFLICTING_REVISION', static fn() => classops_digest_build(digest_request('tomorrow', [$conflicting, $conflicting2])));

// Stable ordering: critical/important then time then title/ref, independent of input order.
$orderRecords = [
    digest_record('ord_b', 'event', ['title' => 'B', 'timing' => digest_timing('2026-09-09T06:00:00Z')]),
    digest_record('ord_a', 'event', ['title' => 'A', 'importance' => 'important', 'timing' => digest_timing('2026-09-09T07:00:00Z')]),
    digest_record('ord_c', 'event', ['title' => 'C', 'timing' => digest_timing('2026-09-09T05:00:00Z')]),
];
$orderedA = classops_digest_build(digest_request('tomorrow', $orderRecords));
$orderedB = classops_digest_build(digest_request('tomorrow', array_reverse($orderRecords)));
digest_assert(classops_digest_semantic_fingerprint($orderedA) === classops_digest_semantic_fingerprint($orderedB), 'ordering depends on input order');
digest_assert(array_column(digest_items($orderedA, 'schedule'), 'entityRef') === ['ord_a', 'ord_c', 'ord_b'], 'priority/time ordering invalid');

// Tomorrow timezone boundary: now=Sep 8 20:40Z => Sep 9 00:10 Tehran, so "tomorrow" is Sep 10 local.
$boundaryRecords = [
    digest_record('sep9_late', 'event', ['timing' => digest_timing('2026-09-09T20:00:00Z')]), // Sep 9 23:30 local
    digest_record('sep10_early', 'event', ['timing' => digest_timing('2026-09-09T20:45:00Z')]), // Sep 10 00:15 local
    digest_record('sep10_all_day', 'event', ['timing' => digest_timing(null, null, null, true, '2026-09-10')]),
];
$boundaryReq = digest_request('tomorrow', $boundaryRecords);
$boundaryReq['window']['nowUtc'] = '2026-09-08T20:40:00Z';
$boundaryView = classops_digest_build($boundaryReq);
digest_assert($boundaryView['window']['localStartDate'] === '2026-09-10', 'tomorrow local date boundary invalid');
$boundaryRefs = digest_entity_refs($boundaryView);
sort($boundaryRefs, SORT_STRING);
digest_assert($boundaryRefs === ['sep10_all_day', 'sep10_early'], 'timezone/all-day filtering failed');

// Weekly boundary: next week is next Saturday..Friday, never a rolling seven-day window.
$weekRecords = [
    digest_record('fri_before', 'exam', ['source' => 'exam', 'timing' => digest_timing('2026-09-11T08:00:00Z')]), // Fri before next week
    digest_record('sat_start', 'exam', ['source' => 'exam', 'timing' => digest_timing('2026-09-12T04:00:00Z')]),
    digest_record('fri_end', 'deadline', ['timing' => digest_timing(null, null, '2026-09-18T17:00:00Z')]),
    digest_record('sat_after', 'event', ['timing' => digest_timing('2026-09-19T04:00:00Z')]),
];
$weekView = classops_digest_build(digest_request('weekly', $weekRecords));
digest_assert($weekView['window']['localStartDate'] === '2026-09-12' && $weekView['window']['localEndDateExclusive'] === '2026-09-19', 'weekly Saturday boundary invalid');
$weekRefs = digest_entity_refs($weekView);
sort($weekRefs, SORT_STRING);
digest_assert($weekRefs === ['fri_end', 'sat_start'], 'weekly boundary included adjacent week');

// Weekly changed item gets a dedicated deterministic section.
$weeklyChanged = digest_record('week_changed', 'event', [
    'change' => ['kind' => 'revised', 'changedAtUtc' => '2026-09-08T10:00:00Z'],
    'timing' => digest_timing('2026-09-13T04:00:00Z'),
]);
$weeklyChangedView = classops_digest_build(digest_request('weekly', [$weeklyChanged]));
digest_assert(array_column(digest_items($weeklyChangedView, 'changes'), 'entityRef') === ['week_changed'], 'weekly revised item not grouped as change');

// Message-size budget and truncation metadata.
$many = [];
for ($i = 0; $i < 20; $i++) {
    $many[] = digest_record('budget_' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'event', [
        'title' => str_repeat('عنوان طولانی ', 8) . $i,
        'timing' => digest_timing('2026-09-09T04:' . str_pad((string) ($i % 60), 2, '0', STR_PAD_LEFT) . ':00Z'),
    ]);
}
$budgetReq = digest_request('tomorrow', $many);
$budgetReq['budget'] = ['maxItems' => 5, 'maxEstimatedChars' => 700];
$budgetView = classops_digest_build($budgetReq);
digest_assert($budgetView['budget']['truncated'] === true, 'truncation flag missing');
digest_assert($budgetView['budget']['visibleItems'] <= 5 && $budgetView['budget']['omittedItems'] > 0, 'truncation counts invalid');
digest_assert(isset($budgetView['budget']['omittedBySection']['schedule']), 'per-section truncation metadata missing');

// Injected Term7 fixture: the engine only sees a projection contract record, not academic_term7.php.
$term7Fixture = digest_record('term7_proj_1', 'schedule_ref', [
    'source' => 'schedule',
    'title' => 'اندو نظری ۱',
    'scheduleRef' => 'term7:1405-1406.2:2026-09-09:endodontics-theory-1',
    'course' => ['ref' => 'endo-theory-1', 'title' => 'اندو نظری ۱'],
    'location' => 'آمفی‌تئاتر ۹۰',
    'timing' => digest_timing('2026-09-09T05:00:00Z', '2026-09-09T07:00:00Z'),
]);
$term7View = classops_digest_build(digest_request('tomorrow', [$term7Fixture]));
digest_assert(digest_items($term7View, 'schedule')[0]['scheduleRef'] === $term7Fixture['scheduleRef'], 'injected schedule projection was not preserved');

// Site / Telegram / Bale semantic equality: all renderers must consume this exact same view model.
$siteVm = classops_digest_build(digest_request('tomorrow', $mixed));
$telegramVm = classops_digest_build(digest_request('tomorrow', $mixed));
$baleVm = classops_digest_build(digest_request('tomorrow', $mixed));
$fingerprint = classops_digest_semantic_fingerprint($siteVm);
digest_assert($fingerprint === classops_digest_semantic_fingerprint($telegramVm) && $fingerprint === classops_digest_semantic_fingerprint($baleVm), 'platform view-model semantics diverged');

// Zero-write reads: engine source contains no storage/network mutation primitive and execution leaves a marker byte-identical.
$source = file_get_contents(dirname(__DIR__) . '/public_html/api/classops_modules/digests/digest_engine.php');
digest_assert(is_string($source), 'unable to read digest engine source');
foreach (['file_put_contents(', 'fopen(', 'rename(', 'unlink(', 'curl_', 'notifications_', 'dent_term7_', 'classops_store'] as $forbidden) {
    digest_assert(!str_contains($source, $forbidden), 'pure digest engine contains forbidden IO/integration dependency: ' . $forbidden);
}
$marker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'classops-digest-zero-write-' . bin2hex(random_bytes(4));
file_put_contents($marker, 'unchanged', LOCK_EX);
$beforeHash = hash_file('sha256', $marker);
classops_digest_build(digest_request('tomorrow', $mixed));
$afterHash = hash_file('sha256', $marker);
@unlink($marker);
digest_assert($beforeHash === $afterHash, 'digest build mutated unrelated filesystem state');

// Strict schema/security edge cases.
$unknown = digest_request('tomorrow', []);
$unknown['mystery'] = true;
digest_expect('CLASSOPS_DIGEST_UNKNOWN_FIELD', static fn() => classops_digest_build($unknown));
$badVisibility = digest_record('bad_visibility', 'task', ['source' => 'task', 'status' => 'pending', 'state' => ['taskState' => 'pending', 'ackState' => 'not_required']]);
unset($badVisibility['visibility']['studentAllowed']);
digest_expect('CLASSOPS_DIGEST_INVALID_BOOLEAN', static fn() => classops_digest_build(digest_request('tomorrow', [$badVisibility])));

fwrite(STDOUT, "classops digest engine tests passed\n");
