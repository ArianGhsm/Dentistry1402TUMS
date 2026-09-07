<?php
declare(strict_types=1);

require_once __DIR__ . '/../public_html/api/classops_modules/exams/exam_ops.php';
require_once __DIR__ . '/../public_html/api/classops_modules/ack/critical_ack.php';

$assertions = 0;

function test_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('ASSERTION_FAILED: ' . $message);
    }
}

function test_same($expected, $actual, string $message): void
{
    test_assert($expected === $actual, $message . ' expected=' . json_encode($expected) . ' actual=' . json_encode($actual));
}

function test_exam_exception(string $reasonCode, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (DentClassOpsExamOpsException $exception) {
        test_same($reasonCode, $exception->reasonCode, $message . ' reason code');
        return;
    }
    throw new RuntimeException('ASSERTION_FAILED: ' . $message . ' did not throw');
}

function test_ack_exception(string $reasonCode, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (DentClassOpsCriticalAckException $exception) {
        test_same($reasonCode, $exception->reasonCode, $message . ' reason code');
        return;
    }
    throw new RuntimeException('ASSERTION_FAILED: ' . $message . ' did not throw');
}

function test_exam_input(): array
{
    return [
        'cohortKey' => 'dentistry-1402',
        'title' => 'Final diagnostics exam',
        'description' => 'Operational schedule only.',
        'course' => ['ref' => 'course:diagnostics2', 'title' => 'Diagnostics 2'],
        'startsAt' => '2026-10-10T08:30:00+03:30',
        'endsAt' => '2026-10-10T10:00:00+03:30',
        'location' => 'Faculty - Hall A',
        'importance' => 'critical',
        'audienceSpec' => [
            'version' => 'classops-audience-placeholder-v1',
            'mode' => 'entire_cohort',
            'refs' => [],
        ],
        'reference' => ['system' => 'academic_calendar', 'ref' => 'term7:diagnostics2-final'],
        'scope' => 'Lectures 1-8',
        'notes' => 'Bring student card.',
        'assessmentRef' => ['system' => 'website_assessment', 'ref' => 'term6:diagnostics2:final-sample'],
        'resourceRefs' => [['kind' => 'guide', 'ref' => 'resource:diagnostics2-final']],
        'paymentAccessRef' => ['system' => 'website_assessment_access', 'ref' => 'term6:diagnostics2'],
        'status' => 'scheduled',
    ];
}

function test_exam_item(int $revision = 1): array
{
    $item = classops_exam_build_create_input(test_exam_input());
    $item['id'] = 'cop_0123456789abcdef';
    $item['revision'] = $revision;
    return $item;
}

function test_notice(int $revision = 3, string $id = 'cop_fedcba9876543210'): array
{
    return [
        'id' => $id,
        'type' => 'critical_notice',
        'revision' => $revision,
        'requireAck' => true,
        'status' => 'active',
    ];
}

function test_eligibility(array $notice, string $studentNumber, bool $eligible = true): array
{
    return [
        'contractVersion' => CLASSOPS_CRITICAL_ACK_ELIGIBILITY_VERSION,
        'itemId' => $notice['id'],
        'revision' => $notice['revision'],
        'studentNumber' => $studentNumber,
        'eligible' => $eligible,
        'audienceFingerprint' => hash('sha256', 'fixture-audience-v1|' . $notice['id'] . '|' . $notice['revision']),
    ];
}

$create = classops_exam_build_create_input(test_exam_input());
test_same('exam', $create['type'], 'Exam builder sets Foundation item type.');
test_same('2026-10-10T05:00:00Z', $create['timing']['startsAt'], 'Exam start is canonical UTC.');
test_same('2026-10-10T06:30:00Z', $create['timing']['endsAt'], 'Exam end is canonical UTC.');
test_same('Asia/Tehran', $create['timing']['timezone'], 'Frozen classops-v1 timezone marker is retained.');
test_same(false, $create['requireAck'], 'Exam operation does not create critical ACK state.');
test_same([], $create['reminderPolicy']['rules'], 'Foundation placeholder is not presented as executable scheduler policy.');

test_exam_exception('CLASSOPS_EXAM_INVALID_TIME_RANGE', static function (): void {
    $input = test_exam_input();
    $input['endsAt'] = '2026-10-10T07:00:00+03:30';
    classops_exam_build_create_input($input);
}, 'Exam rejects end-before-start.');

test_exam_exception('CLASSOPS_EXAM_UNKNOWN_FIELD', static function (): void {
    $input = test_exam_input();
    $input['mystery'] = true;
    classops_exam_build_create_input($input);
}, 'Exam schema rejects unknown fields.');

test_exam_exception('CLASSOPS_EXAM_PARALLEL_ASSESSMENT_STATE_FORBIDDEN', static function (): void {
    $input = test_exam_input();
    $input['attempts'] = [['attemptId' => 'fake']];
    classops_exam_build_create_input($input);
}, 'Exam rejects duplicate attempt state.');

test_exam_exception('CLASSOPS_EXAM_PARALLEL_ASSESSMENT_STATE_FORBIDDEN', static function (): void {
    $input = test_exam_input();
    $input['paymentStatus'] = 'success';
    classops_exam_build_create_input($input);
}, 'Exam rejects duplicate verified payment state.');

test_exam_exception('CLASSOPS_EXAM_INVALID_AUDIENCE', static function (): void {
    $input = test_exam_input();
    $input['audienceSpec'] = [
        'version' => 'classops-audience-placeholder-v1',
        'mode' => 'entire_cohort',
        'refs' => ['1402123456'],
    ];
    classops_exam_build_create_input($input);
}, 'Exam matches frozen entire-cohort audience cardinality.');

test_exam_exception('CLASSOPS_EXAM_INVALID_FOUNDATION_REF', static function (): void {
    $input = test_exam_input();
    $input['course']['ref'] = 'course/diagnostics2';
    classops_exam_build_create_input($input);
}, 'Exam course ref matches frozen Foundation canonical-ref syntax.');

$reminders = classops_exam_default_reminder_policy();
test_same(CLASSOPS_EXAM_REMINDER_VERSION, $reminders['version'], 'Reminder version is explicit.');
test_same(4, count($reminders['rules']), 'Four default reminder intents are serialized.');
test_same(259200, $reminders['rules'][0]['offsetSeconds'], 'T-3 reminder is declarative.');
test_same(86400, $reminders['rules'][1]['offsetSeconds'], 'T-1 reminder is declarative.');
test_same('night_before', $reminders['rules'][2]['marker'], 'Night-before marker is declarative.');
test_same('morning_of', $reminders['rules'][3]['marker'], 'Morning-of marker is declarative.');
test_assert(!array_key_exists('dueAt', $reminders), 'Reminder policy does not calculate scheduler due times.');

$item = test_exam_item(1);
$item['extensions']['foreign_v1'] = ['preserve' => true];
$reschedule = classops_exam_build_reschedule_command($item, 1, [
    'startsAt' => '2026-10-11T09:00:00+03:30',
    'endsAt' => '2026-10-11T10:30:00+03:30',
    'reason' => 'Faculty timetable changed.',
]);
test_same('update', $reschedule['operation'], 'Reschedule emits Foundation update command.');
test_same(1, $reschedule['expectedRevision'], 'Reschedule carries exact expected revision.');
test_same(1, $reschedule['patch']['extensions']['exam_ops_v1']['lastScheduleChange']['supersedesRevision'], 'Reschedule records superseded revision.');
test_same(['preserve' => true], $reschedule['patch']['extensions']['foreign_v1'], 'Reschedule preserves foreign extension namespaces.');
test_same('2026-10-11T05:30:00Z', $reschedule['patch']['timing']['startsAt'], 'Rescheduled start is UTC.');

test_exam_exception('CLASSOPS_EXAM_STALE_REVISION', static function () use ($item): void {
    classops_exam_build_reschedule_command($item, 2, [
        'startsAt' => '2026-10-11T09:00:00+03:30',
        'reason' => 'Stale writer.',
    ]);
}, 'Reschedule fails closed on stale revision.');

$cancel = classops_exam_build_cancel_command($item, 1, 'Exam cancelled by faculty.');
test_same('cancel', $cancel['operation'], 'Cancel emits Foundation cancel command.');
test_same(1, $cancel['expectedRevision'], 'Cancel carries exact expected revision.');

test_exam_exception('CLASSOPS_EXAM_STALE_REVISION', static function () use ($item): void {
    classops_exam_build_cancel_command($item, 2, 'Stale cancel.');
}, 'Cancel fails closed on stale revision.');

$projectionA = classops_exam_projection($item);
$projectionB = classops_exam_projection($item);
test_same($projectionA, $projectionB, 'Exam projection is deterministic.');
foreach (['attempts', 'answers', 'timer', 'access', 'paymentStatus', 'verifiedSuccess'] as $forbiddenKey) {
    test_assert(!array_key_exists($forbiddenKey, $projectionA), "Projection excludes {$forbiddenKey}.");
}
test_same('reference_only', $projectionA['paymentAccessRef']['mode'], 'Payment access is reference-only.');

$state = classops_ack_empty_state();
$noticeV3 = test_notice(3);
$student = '1402123456';
$actor = ['studentNumber' => $student, 'role' => 'student'];
$eligibilityV3 = test_eligibility($noticeV3, $student, true);
$ack1 = classops_ack_record(
    $state,
    $noticeV3,
    $actor,
    $eligibilityV3,
    3,
    'ack-test-0001',
    '2026-09-08T02:15:00+03:30'
);
$state = $ack1['state'];
test_same(false, $ack1['idempotentReplay'], 'First ACK is not a replay.');
test_same(false, $ack1['alreadyAcknowledged'], 'First ACK creates canonical record.');
test_same(3, $ack1['ack']['revision'], 'ACK record pins exact revision.');
test_same($student, $ack1['ack']['studentNumber'], 'ACK stores canonical student actor privately.');
test_same('2026-09-07T22:45:00Z', $ack1['ack']['ackedAt'], 'ACK timestamp is canonical UTC.');
test_same(1, count($state['history']), 'First ACK appends non-destructive audit history.');
test_assert(classops_ack_is_satisfied($state, $noticeV3, $student), 'Current exact revision is satisfied.');

$replay = classops_ack_record(
    $state,
    $noticeV3,
    $actor,
    $eligibilityV3,
    3,
    'ack-test-0001',
    '2026-09-08T02:16:00+03:30'
);
test_same(true, $replay['idempotentReplay'], 'Exact duplicate ACK is idempotent replay.');
test_same(false, $replay['stateChanged'], 'Exact idempotent replay does not mutate state.');
test_same(1, count($replay['state']['history']), 'Idempotent replay does not append history.');

$duplicateNewKey = classops_ack_record(
    $state,
    $noticeV3,
    $actor,
    $eligibilityV3,
    3,
    'ack-test-0002',
    '2026-09-08T02:17:00+03:30'
);
test_same(true, $duplicateNewKey['alreadyAcknowledged'], 'Second key does not create a second ACK tuple.');
test_same(1, count($duplicateNewKey['state']['acks']), 'ACK tuple stays unique.');
test_same(1, count($duplicateNewKey['state']['history']), 'Duplicate semantic ACK does not append history.');

$noticeV4 = test_notice(4);
$eligibilityV4 = test_eligibility($noticeV4, $student, true);
test_same(false, classops_ack_is_satisfied($state, $noticeV4, $student), 'Revised notice invalidates prior revision satisfaction.');

test_ack_exception('CLASSOPS_ACK_STALE_REVISION', static function () use ($state, $noticeV4, $actor, $eligibilityV4): void {
    classops_ack_record($state, $noticeV4, $actor, $eligibilityV4, 3, 'ack-test-0003', '2026-09-08T02:18:00+03:30');
}, 'ACK rejects stale requested revision.');

test_ack_exception('CLASSOPS_ACK_NOT_ELIGIBLE', static function () use ($state, $noticeV3, $actor): void {
    $other = test_eligibility($noticeV3, '1402123999', true);
    classops_ack_record($state, $noticeV3, $actor, $other, 3, 'ack-test-0004', '2026-09-08T02:19:00+03:30');
}, 'Cross-audience/actor proof fails closed.');

test_ack_exception('CLASSOPS_ACK_NOT_ELIGIBLE', static function () use ($state, $noticeV3, $actor, $student): void {
    $denied = test_eligibility($noticeV3, $student, false);
    classops_ack_record($state, $noticeV3, $actor, $denied, 3, 'ack-test-0005', '2026-09-08T02:20:00+03:30');
}, 'Ineligible actor cannot ACK.');

test_ack_exception('CLASSOPS_ACK_EXPLICIT_INTENT_REQUIRED', static function () use ($state, $noticeV3, $actor, $eligibilityV3): void {
    classops_ack_record($state, $noticeV3, $actor, $eligibilityV3, 3, 'ack-test-0006', '2026-09-08T02:21:00+03:30', 'telegram_read_receipt');
}, 'Platform read receipt cannot become ACK.');

test_ack_exception('CLASSOPS_ACK_EXPLICIT_INTENT_REQUIRED', static function (): void {
    classops_ack_from_transport_receipt(['platform' => 'telegram', 'delivered' => true, 'read' => true]);
}, 'Transport callback mapping cannot bypass explicit ACK intent.');

$stats = classops_ack_owner_stats($state, $noticeV3, [$student, '1402123001', '1402123002', $student]);
test_same(3, $stats['eligible'], 'Owner aggregate deduplicates eligible actors.');
test_same(1, $stats['acked'], 'Owner aggregate counts exact-revision ACKs.');
test_same(2, $stats['pending'], 'Owner aggregate reports pending exact-revision ACKs.');
test_same(['itemId', 'revision', 'eligible', 'acked', 'pending'], array_keys($stats), 'Owner aggregate exposes counts only.');

$cancelledNotice = $noticeV3;
$cancelledNotice['status'] = 'cancelled';
$cancelledStats = classops_ack_owner_stats($state, $cancelledNotice, [$student, '1402123001']);
test_same(1, $cancelledStats['acked'], 'Historical aggregate remains readable after notice cancellation.');

test_ack_exception('CLASSOPS_ACK_NOTICE_NOT_ACKABLE', static function () use ($state, $cancelledNotice): void {
    $newStudent = '1402123001';
    classops_ack_record(
        $state,
        $cancelledNotice,
        ['studentNumber' => $newStudent, 'role' => 'student'],
        test_eligibility($cancelledNotice, $newStudent, true),
        3,
        'ack-test-0007',
        '2026-09-08T02:21:30+03:30'
    );
}, 'Cancelled notice history is readable but new ACK mutation is closed.');

$projection = classops_ack_actor_projection($state, $noticeV3, $actor, $eligibilityV3);
test_same(true, $projection['acked'], 'Actor projection exposes own ACK state.');
test_assert(!array_key_exists('studentNumber', $projection), 'Actor projection does not echo identity.');

$auditJson = json_encode($state['history'], JSON_UNESCAPED_SLASHES);
test_assert(is_string($auditJson), 'Audit history serializes.');
test_assert(!str_contains($auditJson, $student), 'Audit history omits raw student number.');
test_assert(!str_contains($auditJson, 'ack-test-0001'), 'Audit history omits raw idempotency key.');
test_assert(!str_contains($auditJson, 'telegram'), 'Audit history omits transport identifiers.');
test_assert(!str_contains($auditJson, 'bale'), 'Audit history omits transport identifiers for Bale.');

$otherNotice = test_notice(1, 'cop_aaaaaaaaaaaaaaaa');
$otherEligibility = test_eligibility($otherNotice, $student, true);
test_ack_exception('CLASSOPS_ACK_IDEMPOTENCY_CONFLICT', static function () use ($state, $otherNotice, $actor, $otherEligibility): void {
    classops_ack_record($state, $otherNotice, $actor, $otherEligibility, 1, 'ack-test-0001', '2026-09-08T02:22:00+03:30');
}, 'Idempotency key cannot be reused for a different payload.');

echo "PASS classops exam/critical-ack domain tests ({$assertions} assertions)\n";
