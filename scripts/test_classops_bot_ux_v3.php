<?php
declare(strict_types=1);

$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dent-classops-owner-partial-' . bin2hex(random_bytes(5));
putenv('DENT_STORAGE_ROOT=' . $testRoot);
putenv('DENT_SERVER_ONLY_ROOT=' . $testRoot . DIRECTORY_SEPARATOR . 'server-only');
putenv('DENT_AUTH_SECRET_KEY=' . base64_encode(str_repeat('t', 32)));

require_once dirname(__DIR__) . '/public_html/api/classops_bot_ux_v3.php';

function classops_v3_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$timezone = new DateTimeZone('Asia/Tehran');
$start = dent_term7_academic_context(new DateTimeImmutable('2026-09-09 12:00:00', $timezone));
$boundary = dent_term7_academic_context(new DateTimeImmutable('2027-02-12 12:00:00', $timezone));
$after = dent_term7_academic_context(new DateTimeImmutable('2027-02-13 12:00:00', $timezone));

classops_v3_assert(($start['term'] ?? null) === 7, 'Term 7 must be active on 1405/06/18');
classops_v3_assert(($boundary['currentJalaliDate'] ?? '') === '1405/11/23', 'Boundary date mismatch');
classops_v3_assert(($boundary['term'] ?? null) === 7, 'Term 7 must include 1405/11/23');
classops_v3_assert(($after['currentJalaliDate'] ?? '') === '1405/11/24', 'Post-boundary date mismatch');
classops_v3_assert(($after['term'] ?? null) === null, 'Term 7 must stop after 1405/11/23');
classops_v3_assert(($after['state'] ?? '') === 'after_window', 'Post-boundary state must be explicit');


$basePartial = array_values(array_filter(
    dent_term7_schedule()['theory'][3] ?? [],
    static fn(array $event): bool => ($event['slug'] ?? '') === 'partial-basics-theory'
));
classops_v3_assert(
    count($basePartial) === 1
        && ($basePartial[0]['start'] ?? '') === '07:30'
        && ($basePartial[0]['end'] ?? '') === '08:30',
    'Canonical Wednesday partial theory recurrence remains unchanged at 07:30-08:30'
);

$syllabus = classops_partial_theory_syllabus();
classops_v3_assert(count($syllabus['sessions'] ?? []) === 15, 'Partial theory syllabus preserves all 15 PDF sessions');
$ambiguous = $syllabus['sessions'][2] ?? [];
classops_v3_assert(
    ($ambiguous['sessionNumber'] ?? null) === 3
        && ($ambiguous['sourceDate'] ?? '') === '054/07/15'
        && ($ambiguous['jalaliDate'] ?? null) === null
        && !empty($ambiguous['dateAmbiguous']),
    'Suspicious session 3 source date is preserved and intentionally left unmapped'
);

$studentNumber = '40211272991';
$state = dent_term7_state_default();
$state['assignments'][$studentNumber] = dent_term7_normalize_assignment($studentNumber, [
    'studentNumber' => $studentNumber,
    'group10' => 6,
    'group8' => 15,
    'updatedAt' => '',
]);
$student = ['studentNumber' => $studentNumber, 'cohortKey' => DENT_TERM7_COHORT, 'role' => 'student'];
$ownerStudent = ['studentNumber' => $studentNumber, 'cohortKey' => DENT_TERM7_COHORT, 'role' => 'owner'];
$ownerWithoutAssignment = ['studentNumber' => '40211272992', 'cohortKey' => DENT_TERM7_COHORT, 'role' => 'owner'];
$otherCohortOwner = ['studentNumber' => $studentNumber, 'cohortKey' => 'prosthesis-1402', 'role' => 'owner'];
$partialDate = new DateTimeImmutable('2026-11-25 00:00:00', $timezone);

$practicalDate = new DateTimeImmutable('2026-09-19 00:00:00', $timezone);
$practicalRows = array_values(array_filter(
    classops_bot_ux_v3_term7_records($student, $practicalDate, $state),
    static fn(array $item): bool => ($item['type'] ?? '') === 'practical'
));
classops_v3_assert(
    count(array_filter($practicalRows, static fn(array $item): bool => str_contains((string) ($item['startsAt'] ?? ''), 'T09:00:00') && str_contains((string) ($item['endsAt'] ?? ''), 'T12:00:00'))) >= 1,
    'Morning practical projection uses explicit 09:00-12:00 clock range'
);
classops_v3_assert(
    count(array_filter($practicalRows, static fn(array $item): bool => str_contains((string) ($item['startsAt'] ?? ''), 'T13:00:00') && str_contains((string) ($item['endsAt'] ?? ''), 'T15:00:00'))) >= 1,
    'Afternoon practical projection uses explicit 13:00-15:00 clock range'
);
classops_v3_assert(
    count(array_filter($practicalRows, static fn(array $item): bool => in_array((string) ($item['timeLabel'] ?? ''), ['صبح', 'عصر'], true))) === 0,
    'Practical timeline no longer leaks ambiguous morning/afternoon labels'
);

$studentAcademic = classops_bot_ux_v3_term7_records($student, $partialDate, $state);
$ownerAcademic = classops_bot_ux_v3_term7_records($ownerStudent, $partialDate, $state);
classops_v3_assert($studentAcademic === $ownerAcademic, 'Dual-role owner receives same personal Term 7 projection as student identity');
classops_v3_assert($ownerAcademic !== [], 'Owner role must not suppress student academic projection');
classops_v3_assert(classops_stage2_is_owner($ownerStudent), 'Owner privilege remains intact');
dent_term7_state_with_lock(static function (array &$storedState) use ($studentNumber): array {
    $storedState['assignments'][$studentNumber] = dent_term7_normalize_assignment($studentNumber, [
        'studentNumber' => $studentNumber,
        'group10' => 6,
        'group8' => 15,
        'updatedAt' => '',
    ]);
    return [];
});
$studentTimeline = classops_bot_ux_v3_timeline(['startDate' => '2026-11-25', 'days' => 1], $student);
$ownerTimeline = classops_bot_ux_v3_timeline(['startDate' => '2026-11-25', 'days' => 1], $ownerStudent);
$studentTimelineAcademic = array_values(array_filter(
    $studentTimeline['days'][0]['items'] ?? [],
    static fn(array $item): bool => ($item['source'] ?? '') === 'term7'
));
$ownerTimelineAcademic = array_values(array_filter(
    $ownerTimeline['days'][0]['items'] ?? [],
    static fn(array $item): bool => ($item['source'] ?? '') === 'term7'
));
classops_v3_assert($studentTimelineAcademic === $ownerTimelineAcademic, 'Student-facing timeline academic rows are identical for student and dual-role owner');
classops_v3_assert(classops_bot_ux_v3_term7_records($ownerWithoutAssignment, $partialDate, $state) === [], 'Owner without explicit Term 7 assignment receives no fabricated projection');
classops_v3_assert(classops_bot_ux_v3_term7_records($otherCohortOwner, $partialDate, $state) === [], 'Non-target cohort behavior remains unchanged');

$partialRows = array_values(array_filter(
    $ownerAcademic,
    static fn(array $item): bool => ($item['courseTitle'] ?? '') === 'مبانی پارسیل نظری'
));
classops_v3_assert(array_column($partialRows, 'sessionNumber') === [9, 10, 11], 'All same-date partial sessions are preserved in syllabus order');
classops_v3_assert(count(array_unique(array_column($partialRows, 'ref'))) === 3, 'Same-date sessions have distinct deterministic refs');
classops_v3_assert(
    ($partialRows[0]['title'] ?? '') === 'مبانی پارسیل نظری — جلسه 9: نگهدارنده مستقیم (۲)'
        && ($partialRows[0]['instructor'] ?? '') === 'دکتر حاجی محمودی',
    'Session title number and instructor enrich canonical occurrence'
);
classops_v3_assert(
    ($partialRows[1]['sessionModeLabel'] ?? '') === 'مجازی'
        && str_contains((string) ($partialRows[1]['title'] ?? ''), 'مجازی')
        && ($partialRows[1]['location'] ?? 'x') === ''
        && ($partialRows[1]['startsAt'] ?? 'x') === ''
        && ($partialRows[1]['endsAt'] ?? 'x') === '',
    'Virtual session 10 keeps virtual label without fabricating clock time or physical room'
);
classops_v3_assert(
    ($partialRows[2]['sessionModeLabel'] ?? '') === 'مجازی'
        && str_contains((string) ($partialRows[2]['title'] ?? ''), 'مجازی'),
    'Virtual session 11 is labeled explicitly'
);

$doubleDate = new DateTimeImmutable('2026-12-16 00:00:00', $timezone);
$doubleRows = array_values(array_filter(
    classops_bot_ux_v3_term7_records($student, $doubleDate, $state),
    static fn(array $item): bool => ($item['courseTitle'] ?? '') === 'مبانی پارسیل نظری'
));
classops_v3_assert(array_column($doubleRows, 'sessionNumber') === [14, 15], 'Sessions 14 and 15 remain distinct on shared date');
classops_v3_assert(($doubleRows[1]['sessionModeLabel'] ?? '') === 'مجازی', 'Session 15 keeps virtual label');

function classops_v3_cleanup(string $path): void
{
    if (!is_dir($path)) return;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
}
classops_v3_cleanup($testRoot);

echo "ClassOps UX V3 owner/student + partial syllabus checks passed.\n";
