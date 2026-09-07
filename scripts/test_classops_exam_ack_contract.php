<?php
declare(strict_types=1);

require_once __DIR__ . '/../public_html/api/classops_modules/exams/exam_ops.php';
require_once __DIR__ . '/../public_html/api/classops_modules/ack/critical_ack.php';

$assertions = 0;

function contract_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('ASSERTION_FAILED: ' . $message);
    }
}

function contract_same($expected, $actual, string $message): void
{
    contract_assert($expected === $actual, $message . ' expected=' . json_encode($expected) . ' actual=' . json_encode($actual));
}

$path = __DIR__ . '/../contracts/candidates/classops-exam-ack-v1.json';
$contract = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

contract_same('classops-exam-ack-v1', $contract['contract'] ?? null, 'Candidate contract version.');
contract_same('candidate', $contract['status'] ?? null, 'Contract remains candidate.');
contract_same(true, $contract['promotionRequired'] ?? null, 'Promotion is explicit.');
contract_same(false, $contract['runtimeWiring'] ?? null, 'Candidate does not claim runtime wiring.');
contract_same('classops-v1', $contract['baseContract'] ?? null, 'Frozen Foundation is the base contract.');
contract_assert(in_array('canonical-student-identity-v1', $contract['dependencies'] ?? [], true), 'Canonical student identity dependency is explicit.');
contract_assert(in_array('notification-integration-v1', $contract['dependencies'] ?? [], true), 'Notification integration dependency is explicit.');

contract_same(CLASSOPS_EXAM_EXTENSION_KEY, $contract['exam']['extensionNamespace'] ?? null, 'Exam namespace matches implementation.');
contract_same(CLASSOPS_EXAM_PROJECTION_VERSION, $contract['exam']['projectionVersion'] ?? null, 'Exam projection version matches implementation.');
contract_same(CLASSOPS_EXAM_REMINDER_VERSION, $contract['exam']['reminderPolicyVersion'] ?? null, 'Reminder version matches implementation.');
contract_same(classops_exam_default_reminder_policy(), $contract['exam']['defaultReminderPolicy'] ?? null, 'Reminder defaults match code exactly.');
contract_same(true, $contract['exam']['schedulerOwnsDueCalculation'] ?? null, 'Scheduler owns due calculation.');
contract_same([], $contract['exam']['foundationReminderPolicy']['rules'] ?? null, 'Frozen reminder placeholder remains non-executable.');
contract_assert(in_array('verifiedSuccess', $contract['exam']['forbiddenParallelStateFields'] ?? [], true), 'Verified payment success is forbidden parallel state.');
contract_assert(in_array('attemptsByUser', $contract['exam']['forbiddenParallelStateFields'] ?? [], true), 'Assessment attempts are forbidden parallel state.');

contract_same(CLASSOPS_CRITICAL_ACK_RECORD_VERSION, $contract['criticalAck']['recordVersion'] ?? null, 'ACK record version matches implementation.');
contract_same(CLASSOPS_CRITICAL_ACK_STATE_VERSION, $contract['criticalAck']['stateVersion'] ?? null, 'ACK state version matches implementation.');
contract_same(CLASSOPS_CRITICAL_ACK_ELIGIBILITY_VERSION, $contract['criticalAck']['eligibilityVersion'] ?? null, 'Eligibility version matches implementation.');
contract_same(['itemId', 'revision', 'studentNumber'], $contract['criticalAck']['tuple'] ?? null, 'ACK tuple is exact-version canonical identity.');
contract_same(false, $contract['criticalAck']['transportDeliveryOrReadReceiptIsAck'] ?? null, 'Transport receipt is not ACK.');
contract_same(true, $contract['criticalAck']['newRevisionRequiresNewAck'] ?? null, 'New revision requires new ACK.');
contract_same(true, $contract['criticalAck']['crossAudienceFailsClosed'] ?? null, 'Cross-audience ACK fails closed.');
contract_same(['eligible', 'acked', 'pending'], $contract['criticalAck']['ownerAggregateFields'] ?? null, 'Owner aggregate is counts-only.');
contract_same(false, $contract['criticalAck']['auditContainsRawStudentNumber'] ?? null, 'Audit excludes raw student identity.');
contract_same(false, $contract['criticalAck']['auditContainsRawIdempotencyKey'] ?? null, 'Audit excludes raw idempotency key.');

foreach (['externalReference', 'reminderPolicy', 'paymentAccessReference', 'examExtension', 'ackRecord', 'eligibilityProof', 'ownerAggregate'] as $definition) {
    contract_same(false, $contract['$defs'][$definition]['additionalProperties'] ?? null, "{$definition} is strict.");
}
contract_same('reference_only', $contract['$defs']['paymentAccessReference']['properties']['mode']['const'] ?? null, 'Payment schema cannot assert verification state.');
contract_same('explicit_user_ack', $contract['$defs']['ackRecord']['properties']['intent']['const'] ?? null, 'ACK schema requires explicit intent.');
contract_same(true, $contract['apiCandidate']['notYetWired'] ?? null, 'ACK API is explicitly not wired yet.');
contract_same(true, $contract['storageCandidate']['private'] ?? null, 'ACK storage is private.');
contract_same(true, $contract['storageCandidate']['runtimeOnly'] ?? null, 'ACK storage is runtime-only.');
contract_same(false, $contract['storageCandidate']['gitTrackedData'] ?? null, 'ACK runtime data is not Git-tracked.');
contract_same(true, $contract['storageCandidate']['notYetWired'] ?? null, 'ACK storage is explicitly not wired yet.');
contract_assert(in_array('studentNumber', $contract['apiCandidate']['serverDerived'] ?? [], true), 'Canonical actor is server-derived.');
contract_assert(in_array('ackedAt', $contract['apiCandidate']['serverDerived'] ?? [], true), 'ACK timestamp is server-derived.');

$serialized = json_encode($contract, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
contract_assert(is_string($serialized), 'Candidate contract serializes deterministically.');
foreach (['BOT_' . 'TOKEN', 'API_' . 'KEY=', 'SE' . 'CRET=', 'PRIVATE' . ' KEY-----', 's' . 'k-'] as $secretMarker) {
    contract_assert(!str_contains($serialized, $secretMarker), "Contract does not contain secret marker {$secretMarker}.");
}

echo "PASS classops exam/critical-ack contract tests ({$assertions} assertions)\n";
