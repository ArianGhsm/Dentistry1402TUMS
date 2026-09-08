<?php
declare(strict_types=1);

require_once __DIR__ . '/../public_html/api/classops_modules/tasks/task_domain.php';

function tassert(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function texpect(string $code, callable $fn): void {
    try { $fn(); } catch (DentClassOpsTaskException $e) { tassert($e->reasonCode === $code, 'unexpected code ' . $e->reasonCode); return; }
    throw new RuntimeException('expected ' . $code);
}

$ext = classops_task_normalize_extension([
    'contractVersion'=>'classops-tasks-v1',
    'audienceChangePolicy'=>'snapshot',
    'audienceResolutionHash'=>str_repeat('a', 64),
    'requirement'=>['kind'=>'count','targetCount'=>3],
], 'requirement');
tassert(classops_requirement_progress($ext, 2)['remaining'] === 1, 'requirement progress mismatch');
tassert(classops_requirement_progress($ext, 3)['satisfied'] === true, 'requirement satisfaction mismatch');

$state = classops_task_new_state('cop_0123456789abcdef', 1, 'dentistry-1402', '1402123456', '2026-09-08T00:00:00Z');
tassert($state['state'] === 'pending' && $state['stateRevision'] === 1, 'initial task state mismatch');
$state = classops_task_transition($state, 1, 'submitted', 'cmd.submit.001', 'usr_owner001', '2026-09-08T00:01:00Z', 'submitted');
tassert($state['state'] === 'submitted' && $state['stateRevision'] === 2, 'submit transition mismatch');
$replay = classops_task_transition($state, 2, 'submitted', 'cmd.submit.001', 'usr_owner001', '2026-09-08T00:01:00Z', 'submitted');
tassert($replay['idempotentReplay'] === true && $replay['stateRevision'] === 2, 'idempotent replay wrote state');
$state = classops_task_transition($state, 2, 'needs_revision', 'cmd.revise.001', 'usr_owner001', '2026-09-08T00:02:00Z', 'revise');
$state = classops_task_transition($state, 3, 'submitted', 'cmd.resubmit.001', 'usr_student001', '2026-09-08T00:03:00Z', 'resubmit');
$state = classops_task_transition($state, 4, 'completed', 'cmd.complete.001', 'usr_owner001', '2026-09-08T00:04:00Z', 'complete');
tassert($state['state'] === 'completed', 'complete transition mismatch');
$state = classops_task_transition($state, 5, 'pending', 'cmd.reopen.001', 'usr_owner001', '2026-09-08T00:05:00Z', 'reopen');
tassert($state['state'] === 'pending', 'reopen mismatch');
$state = classops_task_transition($state, 6, 'waived', 'cmd.waive.001', 'usr_owner001', '2026-09-08T00:06:00Z', 'waive');
tassert($state['state'] === 'waived', 'waive mismatch');

texpect('CLASSOPS_TASK_TRANSITION_FORBIDDEN', fn() => classops_task_transition(
    classops_task_new_state('cop_0123456789abcdef', 1, 'dentistry-1402', '1402123456', '2026-09-08T00:00:00Z'),
    1, 'needs_revision', 'cmd.bad.001', 'usr_owner001', '2026-09-08T00:01:00Z'
));
texpect('CLASSOPS_TASK_REVISION_CONFLICT', fn() => classops_task_transition(
    classops_task_new_state('cop_0123456789abcdef', 1, 'dentistry-1402', '1402123456', '2026-09-08T00:00:00Z'),
    99, 'submitted', 'cmd.stale.001', 'usr_owner001', '2026-09-08T00:01:00Z'
));
texpect('CLASSOPS_TASK_IDEMPOTENCY_CONFLICT', function (): void {
    $s = classops_task_new_state('cop_0123456789abcdef', 1, 'dentistry-1402', '1402123456', '2026-09-08T00:00:00Z');
    $s = classops_task_transition($s, 1, 'submitted', 'cmd.same.001', 'usr_owner001', '2026-09-08T00:01:00Z');
    classops_task_transition($s, 2, 'completed', 'cmd.same.001', 'usr_owner001', '2026-09-08T00:02:00Z');
});
texpect('CLASSOPS_TASK_FORBIDDEN', fn() => classops_task_student_projection(
    classops_task_new_state('cop_0123456789abcdef', 1, 'dentistry-1402', '1402123456', '2026-09-08T00:00:00Z'),
    '1402999999', '2026-09-07T23:00:00Z', '2026-09-08T01:00:00Z'
));
texpect('CLASSOPS_TASK_PRIVATE_CONTENT_FORBIDDEN', fn() => classops_task_normalize_metadata(['password'=>'x']));

$pending = classops_task_new_state('cop_0123456789abcdef', 1, 'dentistry-1402', '1402123456', '2026-09-08T00:00:00Z');
tassert(classops_task_is_overdue($pending, '2026-09-08T00:30:00Z', '2026-09-08T01:00:00Z') === true, 'overdue derived state mismatch');
$before = serialize($pending);
classops_task_student_projection($pending, '1402123456', '2026-09-08T00:30:00Z', '2026-09-08T01:00:00Z');
tassert(serialize($pending) === $before, 'projection mutated state');

echo "ClassOps tasks/requirements tests passed\n";
