<?php
declare(strict_types=1);

require_once __DIR__ . '/../public_html/api/classops_domain_store_adapter.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) {
        throw new RuntimeException('ASSERTION_FAILED: ' . $message);
    }
};
$expect = static function (string $reasonCode, callable $callback, string $message) use (&$checks): void {
    $checks++;
    try {
        $callback();
    } catch (DentClassOpsDomainException $exception) {
        if ($exception->reasonCode !== $reasonCode) {
            throw new RuntimeException('ASSERTION_FAILED: ' . $message . ' got=' . $exception->reasonCode);
        }
        return;
    }
    throw new RuntimeException('ASSERTION_FAILED: ' . $message . ' did not throw');
};

$foreign = ['foreign_v1' => ['opaque' => 'preserve-me']];
$normalizedForeign = classops_domain_store_normalize_extensions($foreign, 'announcement');
$assert($normalizedForeign === $foreign, 'unknown historical extension namespaces remain opaque/readable');

$taskExtensions = [
    CLASSOPS_TASKS_EXTENSION_KEY => [
        'contractVersion' => CLASSOPS_TASKS_CONTRACT_VERSION,
        'audienceChangePolicy' => 'snapshot',
        'audienceResolutionHash' => str_repeat('a', 64),
        'requirement' => null,
    ],
    'foreign_v1' => ['opaque' => true],
];
$normalizedTask = classops_domain_store_normalize_extensions($taskExtensions, 'task');
$assert($normalizedTask[CLASSOPS_TASKS_EXTENSION_KEY]['contractVersion'] === CLASSOPS_TASKS_CONTRACT_VERSION, 'task extension is normalized by promoted contract');
$assert($normalizedTask['foreign_v1'] === ['opaque' => true], 'known normalization preserves foreign extension namespaces');

$expect('CLASSOPS_TASK_VERSION_UNSUPPORTED', static function (): void {
    classops_domain_store_normalize_extensions([
        CLASSOPS_TASKS_EXTENSION_KEY => [
            'contractVersion' => 'classops-tasks-v999',
            'audienceChangePolicy' => 'snapshot',
            'audienceResolutionHash' => null,
            'requirement' => null,
        ],
    ], 'task');
}, 'unsupported task extension version fails closed');

$expect('CLASSOPS_TASK_TYPE_REQUIRED', static function (): void {
    classops_domain_store_normalize_extensions([
        CLASSOPS_TASKS_EXTENSION_KEY => [
            'contractVersion' => CLASSOPS_TASKS_CONTRACT_VERSION,
            'audienceChangePolicy' => 'snapshot',
            'audienceResolutionHash' => null,
            'requirement' => null,
        ],
    ], 'announcement');
}, 'task extension cannot be stranded under another item type');

$examCreate = classops_exam_build_create_input([
    'cohortKey' => 'dentistry-1402',
    'title' => 'Operational exam',
    'startsAt' => '2026-10-10T08:30:00+03:30',
    'audienceSpec' => [
        'version' => 'classops-audience-placeholder-v1',
        'mode' => 'entire_cohort',
        'refs' => [],
    ],
]);
$normalizedExam = classops_domain_store_normalize_create($examCreate);
$assert($normalizedExam['type'] === 'exam', 'exam stays on Foundation item type');
$assert(isset($normalizedExam['extensions'][CLASSOPS_EXAM_EXTENSION_KEY]), 'exam_ops_v1 is preserved and normalized');
$assert($normalizedExam['extensions'][CLASSOPS_EXAM_EXTENSION_KEY]['contractVersion'] === CLASSOPS_EXAM_ACK_CANDIDATE_VERSION, 'exam extension version is strict');
$assert($normalizedExam['audienceSpec']['version'] === 'classops-audience-placeholder-v1', 'frozen audience placeholder remains stored contract');
$assert($normalizedExam['reminderPolicy']['version'] === 'classops-reminder-placeholder-v1', 'frozen reminder placeholder remains stored contract');

$expect('CLASSOPS_EXAM_EXTENSION_VERSION_UNSUPPORTED', static function () use ($examCreate): void {
    $bad = $examCreate;
    $bad['extensions'][CLASSOPS_EXAM_EXTENSION_KEY]['contractVersion'] = 'classops-exam-ack-v999';
    classops_domain_store_normalize_create($bad);
}, 'unsupported exam extension version fails closed');

$expect('CLASSOPS_EXAM_TYPE_REQUIRED', static function () use ($examCreate): void {
    $bad = $examCreate;
    $bad['type'] = 'announcement';
    classops_domain_store_normalize_create($bad);
}, 'exam extension cannot be stored under another item type');

$currentTask = [
    'type' => 'task',
    'extensions' => $taskExtensions,
];
$expect('CLASSOPS_TASK_TYPE_REQUIRED', static function () use ($currentTask): void {
    classops_domain_store_normalize_patch($currentTask, ['type' => 'announcement']);
}, 'type-only patch validates complete prospective known-extension set');

$legacyPlaceholderItem = [
    'type' => 'announcement',
    'extensions' => ['legacy_namespace_v1' => ['still' => 'readable']],
];
$before = serialize($legacyPlaceholderItem);
classops_domain_store_validate_known_extensions($legacyPlaceholderItem);
$assert(serialize($legacyPlaceholderItem) === $before, 'known-extension validation is read-only');

$foundationReject = static function (): void {
    classops_domain_store_normalize_create([
        'cohortKey' => 'dentistry-1402',
        'type' => 'announcement',
        'title' => 'No v1 reinterpretation',
        'audienceSpec' => [
            'version' => 'classops-audience-v1',
            'resolutionMode' => 'snapshot',
            'expression' => ['op' => 'whole_cohort'],
            'includeStudentNumbers' => [],
            'excludeStudentNumbers' => [],
        ],
    ]);
};
$expect('CLASSOPS_UNKNOWN_FIELD', $foundationReject, 'promoted audience contract does not silently reinterpret frozen stored placeholder');

echo "ClassOps domain store adapter tests passed ({$checks} checks)\n";
