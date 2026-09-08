<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/public_html/api/classops_modules/audience/resolver.php';

function audience_snapshot_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function audience_snapshot_test_expect(string $code, callable $callback): void
{
    try {
        $callback();
    } catch (DentClassOpsAudienceException $exception) {
        audience_snapshot_test_assert(
            $exception->reasonCode === $code,
            "Expected {$code}, got {$exception->reasonCode}"
        );
        return;
    }
    throw new RuntimeException("Expected exception {$code}");
}

final class AudienceSnapshotFixtureSource implements DentClassOpsAudienceSourceV1
{
    public function __construct(private array $context)
    {
    }

    public function loadAudienceContext(string $cohortKey): array
    {
        return $this->context;
    }
}

function audience_snapshot_context(): array
{
    return [
        'cohortKey' => 'dentistry-1402',
        'roster' => [
            ['studentNumber' => '10001', 'cohortKey' => 'dentistry-1402'],
            ['studentNumber' => '10002', 'cohortKey' => 'dentistry-1402'],
        ],
        'identityCohorts' => [
            '10001' => 'dentistry-1402',
            '10002' => 'dentistry-1402',
        ],
        'selectors' => [],
        'source' => [
            'type' => 'fixture-canonical-v1',
            'version' => 'fixture-v1',
        ],
    ];
}

function audience_snapshot_scope(): array
{
    return ['role' => 'owner', 'cohortKeys' => ['dentistry-1402']];
}

function audience_snapshot_spec(array $expression): array
{
    return [
        'version' => 'classops-audience-v1',
        'resolutionMode' => 'snapshot',
        'expression' => $expression,
        'includeStudentNumbers' => [],
        'excludeStudentNumbers' => [],
    ];
}

$source = new AudienceSnapshotFixtureSource(audience_snapshot_context());
$scope = audience_snapshot_scope();

// A maximum-length selector key creates a selectorId longer than 96 bytes.
// Snapshot validation must validate the selectorId grammar, not feed the whole
// `group:<key>` string back into the key-only 96-character validator.
$longKey = str_repeat('x', 96);
$longSpec = audience_snapshot_spec([
    'op' => 'selector',
    'kind' => 'group',
    'key' => $longKey,
]);
$initialMissing = classops_audience_resolve_from_source(
    $source,
    $longSpec,
    'dentistry-1402',
    $scope
);
audience_snapshot_test_assert(
    ($initialMissing['unresolvedReferences'][0]['reference'] ?? '') === 'group:' . $longKey,
    'maximum-length unresolved selector was not produced canonically'
);
$roundTripMissing = classops_audience_resolve_from_source(
    $source,
    $longSpec,
    'dentistry-1402',
    $scope,
    $initialMissing['snapshot']
);
audience_snapshot_test_assert(
    $roundTripMissing['deterministicHash'] === $initialMissing['deterministicHash'],
    'maximum-length unresolved selector snapshot did not round-trip'
);

// Kind/code pairs in unresolved references are strict and machine-readable.
$mismatchedUnresolved = $initialMissing['snapshot'];
$mismatchedUnresolved['unresolvedReferences'][0]['code'] = 'AUDIENCE_STUDENT_NOT_FOUND';
audience_snapshot_test_expect(
    'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID',
    static fn() => classops_audience_resolve_from_source(
        $source,
        $longSpec,
        'dentistry-1402',
        $scope,
        $mismatchedUnresolved
    )
);

// Server-generated unresolved references are unique; duplicates are rejected.
$duplicateUnresolved = $initialMissing['snapshot'];
$duplicateUnresolved['unresolvedReferences'][] = $duplicateUnresolved['unresolvedReferences'][0];
audience_snapshot_test_expect(
    'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID',
    static fn() => classops_audience_resolve_from_source(
        $source,
        $longSpec,
        'dentistry-1402',
        $scope,
        $duplicateUnresolved
    )
);

// Server-generated warnings are from a closed v1 vocabulary and unique by code.
$unknownWarning = $initialMissing['snapshot'];
$unknownWarning['warnings'][0]['code'] = 'AUDIENCE_UNKNOWN_WARNING';
audience_snapshot_test_expect(
    'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID',
    static fn() => classops_audience_resolve_from_source(
        $source,
        $longSpec,
        'dentistry-1402',
        $scope,
        $unknownWarning
    )
);
$duplicateWarning = $initialMissing['snapshot'];
$duplicateWarning['warnings'][] = $duplicateWarning['warnings'][0];
audience_snapshot_test_expect(
    'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID',
    static fn() => classops_audience_resolve_from_source(
        $source,
        $longSpec,
        'dentistry-1402',
        $scope,
        $duplicateWarning
    )
);

// Every snapshot recipient must have exactly one non-empty reason row.
$wholeSpec = audience_snapshot_spec(['op' => 'whole_cohort']);
$whole = classops_audience_resolve_from_source(
    $source,
    $wholeSpec,
    'dentistry-1402',
    $scope
);
$duplicateReasonRow = $whole['snapshot'];
$duplicateReasonRow['recipientReasons'][1] = $duplicateReasonRow['recipientReasons'][0];
audience_snapshot_test_expect(
    'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID',
    static fn() => classops_audience_resolve_from_source(
        $source,
        $wholeSpec,
        'dentistry-1402',
        $scope,
        $duplicateReasonRow
    )
);
$emptyReasonList = $whole['snapshot'];
$emptyReasonList['recipientReasons'][0]['reasons'] = [];
audience_snapshot_test_expect(
    'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID',
    static fn() => classops_audience_resolve_from_source(
        $source,
        $wholeSpec,
        'dentistry-1402',
        $scope,
        $emptyReasonList
    )
);
$duplicateReasonValue = $whole['snapshot'];
$duplicateReasonValue['recipientReasons'][0]['reasons'][] = 'whole_cohort';
audience_snapshot_test_expect(
    'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID',
    static fn() => classops_audience_resolve_from_source(
        $source,
        $wholeSpec,
        'dentistry-1402',
        $scope,
        $duplicateReasonValue
    )
);

fwrite(STDOUT, "ClassOps strict audience snapshot tests passed.\n");
