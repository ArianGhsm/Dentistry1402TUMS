<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/public_html/api/classops_modules/audience/resolver.php';

function audience_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function audience_test_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
    }
}

function audience_test_warning_count(array $result, string $code): int
{
    foreach (($result['warnings'] ?? []) as $warning) {
        if (is_array($warning) && ($warning['code'] ?? '') === $code) {
            return (int) ($warning['count'] ?? 0);
        }
    }
    return 0;
}

function audience_test_expect(string $code, callable $callback, ?int $status = null): DentClassOpsAudienceException
{
    try {
        $callback();
    } catch (DentClassOpsAudienceException $exception) {
        audience_test_same($code, $exception->reasonCode, "Expected {$code}, got {$exception->reasonCode}");
        if ($status !== null) {
            audience_test_same($status, $exception->httpStatus, "Unexpected HTTP status for {$code}");
        }
        return $exception;
    }
    throw new RuntimeException("Expected exception {$code}");
}

final class AudienceFixtureSource implements DentClassOpsAudienceSourceV1
{
    public array $context;
    public int $reads = 0;

    public function __construct(array $context)
    {
        $this->context = $context;
    }

    public function loadAudienceContext(string $cohortKey): array
    {
        $this->reads++;
        return $this->context;
    }
}

final class AudienceFailingSource implements DentClassOpsAudienceSourceV1
{
    public function loadAudienceContext(string $cohortKey): array
    {
        throw new DentClassOpsAudienceSourceException(
            'CLASSOPS_AUDIENCE_FIXTURE_SOURCE_DOWN',
            'fixture detail that must not leak'
        );
    }
}

function audience_fixture_context(): array
{
    return [
        'cohortKey' => 'dentistry-1402',
        'roster' => [
            ['studentNumber' => '10001', 'cohortKey' => 'dentistry-1402'],
            ['studentNumber' => '10002', 'cohortKey' => 'dentistry-1402'],
            ['studentNumber' => '10003', 'cohortKey' => 'dentistry-1402'],
            ['studentNumber' => '10004', 'cohortKey' => 'dentistry-1402'],
            ['studentNumber' => '10005', 'cohortKey' => 'dentistry-1402'],
        ],
        'identityCohorts' => [
            '10001' => 'dentistry-1402',
            '10002' => 'dentistry-1402',
            '10003' => 'dentistry-1402',
            '10004' => 'dentistry-1402',
            '10005' => 'dentistry-1402',
            '10006' => 'dentistry-1403',
        ],
        'selectors' => [
            'role:student' => [
                'kind' => 'role',
                'key' => 'student',
                'cohortKey' => 'dentistry-1402',
                'studentNumbers' => ['10001', '10002', '10003'],
                'sourceRef' => 'fixture.role.student',
            ],
            'role:representative' => [
                'kind' => 'role',
                'key' => 'representative',
                'cohortKey' => 'dentistry-1402',
                'studentNumbers' => ['10004'],
                'sourceRef' => 'fixture.role.representative',
            ],
            'group:g1' => [
                'kind' => 'group',
                'key' => 'g1',
                'cohortKey' => 'dentistry-1402',
                'studentNumbers' => ['10001', '10002'],
                'sourceRef' => 'fixture.group.g1',
            ],
            'group:g2' => [
                'kind' => 'group',
                'key' => 'g2',
                'cohortKey' => 'dentistry-1402',
                'studentNumbers' => ['10003', '10004'],
                'sourceRef' => 'fixture.group.g2',
            ],
            'category:c1' => [
                'kind' => 'category',
                'key' => 'c1',
                'cohortKey' => 'dentistry-1402',
                'studentNumbers' => ['10001'],
                'sourceRef' => 'fixture.category.c1',
            ],
            'category:c2' => [
                'kind' => 'category',
                'key' => 'c2',
                'cohortKey' => 'dentistry-1402',
                'studentNumbers' => ['10002'],
                'sourceRef' => 'fixture.category.c2',
            ],
        ],
        'source' => [
            'type' => 'fixture-canonical-v1',
            'version' => 'fixture-v1',
        ],
    ];
}

function audience_owner_scope(): array
{
    return ['role' => 'owner', 'cohortKeys' => ['dentistry-1402']];
}

function audience_spec(array $expression, string $mode = 'live', array $include = [], array $exclude = []): array
{
    return [
        'version' => CLASSOPS_AUDIENCE_CONTRACT_VERSION,
        'resolutionMode' => $mode,
        'expression' => $expression,
        'includeStudentNumbers' => $include,
        'excludeStudentNumbers' => $exclude,
    ];
}

$source = new AudienceFixtureSource(audience_fixture_context());
$scope = audience_owner_scope();

// Whole cohort, canonical sorting and destination independence.
$whole = classops_audience_resolve_from_source(
    $source,
    audience_spec(['op' => 'whole_cohort']),
    'dentistry-1402',
    $scope
);
audience_test_same(['10001', '10002', '10003', '10004', '10005'], $whole['recipientStudentNumbers'], 'whole cohort failed');
audience_test_same(5, $whole['recipientCount'], 'whole cohort count failed');
audience_test_assert(!isset($whole['destination'], $whole['platform'], $whole['botLinks']), 'audience must be destination/platform independent');
audience_test_assert(preg_match('/^[a-f0-9]{64}$/', $whole['deterministicHash']) === 1, 'resolution hash is invalid');

// Explicit canonical IDs: Persian digits normalize; duplicates are machine-readable.
$explicit = classops_audience_resolve_from_source(
    $source,
    audience_spec(['op' => 'students', 'studentNumbers' => ['۱۰۰۰۲', '10001', '10001']]),
    'dentistry-1402',
    $scope
);
audience_test_same(['10001', '10002'], $explicit['recipientStudentNumbers'], 'explicit IDs failed');
audience_test_same(1, audience_test_warning_count($explicit, 'AUDIENCE_DUPLICATE_STUDENT_REF'), 'duplicate warning failed');

// Include/exclude ordering and contradiction policy: exclude wins.
$includeExclude = classops_audience_resolve_from_source(
    $source,
    audience_spec(
        ['op' => 'students', 'studentNumbers' => ['10001']],
        'live',
        ['10002', '10003'],
        ['10003']
    ),
    'dentistry-1402',
    $scope
);
audience_test_same(['10001', '10002'], $includeExclude['recipientStudentNumbers'], 'include/exclude precedence failed');
audience_test_same(1, audience_test_warning_count($includeExclude, 'AUDIENCE_INCLUDE_EXCLUDE_CONFLICT'), 'include/exclude conflict warning missing');

// Missing IDs resolve as unresolved, never as another identity.
$missing = classops_audience_resolve_from_source(
    $source,
    audience_spec(['op' => 'students', 'studentNumbers' => ['19999']]),
    'dentistry-1402',
    $scope
);
audience_test_same([], $missing['recipientStudentNumbers'], 'missing student unexpectedly resolved');
audience_test_same(
    [['kind' => 'student', 'reference' => '19999', 'code' => 'AUDIENCE_STUDENT_NOT_FOUND']],
    $missing['unresolvedReferences'],
    'missing student unresolved output failed'
);
audience_test_same(1, audience_test_warning_count($missing, 'AUDIENCE_EMPTY_RESULT'), 'empty audience warning missing');

// Known cross-cohort identities fail closed and error text does not echo the ID.
$cross = audience_test_expect(
    'CLASSOPS_AUDIENCE_CROSS_COHORT_REFERENCE',
    static fn() => classops_audience_resolve_from_source(
        $source,
        audience_spec(['op' => 'students', 'studentNumbers' => ['10006']]),
        'dentistry-1402',
        $scope
    )
);
audience_test_assert(!str_contains($cross->getMessage(), '10006'), 'cross-cohort error leaked the student reference');

// Role/group/category selectors consume only canonical student-number memberships.
$selectorResult = classops_audience_resolve_from_source(
    $source,
    audience_spec([
        'op' => 'any',
        'children' => [
            ['op' => 'selector', 'kind' => 'group', 'key' => 'g1'],
            ['op' => 'selector', 'kind' => 'category', 'key' => 'c2'],
            ['op' => 'selector', 'kind' => 'role', 'key' => 'representative'],
        ],
    ]),
    'dentistry-1402',
    $scope
);
audience_test_same(['10001', '10002', '10004'], $selectorResult['recipientStudentNumbers'], 'canonical selector resolution failed');

// AND contradiction is explicit and machine-readable.
$contradiction = classops_audience_resolve_from_source(
    $source,
    audience_spec([
        'op' => 'all',
        'children' => [
            ['op' => 'selector', 'kind' => 'category', 'key' => 'c1'],
            ['op' => 'selector', 'kind' => 'category', 'key' => 'c2'],
        ],
    ]),
    'dentistry-1402',
    $scope
);
audience_test_same([], $contradiction['recipientStudentNumbers'], 'contradictory AND should be empty');
audience_test_same(1, audience_test_warning_count($contradiction, 'AUDIENCE_CONTRADICTORY_INTERSECTION'), 'contradiction warning missing');

// NOT unresolved must fail closed rather than complementing empty to whole cohort.
$negatedMissing = classops_audience_resolve_from_source(
    $source,
    audience_spec([
        'op' => 'not',
        'child' => ['op' => 'selector', 'kind' => 'group', 'key' => 'missing'],
    ]),
    'dentistry-1402',
    $scope
);
audience_test_same([], $negatedMissing['recipientStudentNumbers'], 'NOT unresolved broadened audience');
audience_test_same(1, audience_test_warning_count($negatedMissing, 'AUDIENCE_NEGATION_UNRESOLVED'), 'NOT unresolved warning missing');
audience_test_same('AUDIENCE_SELECTOR_NOT_FOUND', $negatedMissing['unresolvedReferences'][0]['code'] ?? '', 'NOT unresolved reference missing');

// A resolved NOT is scoped only to the target cohort roster.
$negated = classops_audience_resolve_from_source(
    $source,
    audience_spec([
        'op' => 'not',
        'child' => ['op' => 'selector', 'kind' => 'group', 'key' => 'g1'],
    ]),
    'dentistry-1402',
    $scope
);
audience_test_same(['10003', '10004', '10005'], $negated['recipientStudentNumbers'], 'NOT roster complement failed');

// Equivalent specs normalize to the same ordering/hash.
$deterministicA = classops_audience_resolve_from_source(
    $source,
    audience_spec([
        'op' => 'any',
        'children' => [
            ['op' => 'students', 'studentNumbers' => ['10002', '10001']],
            ['op' => 'selector', 'kind' => 'group', 'key' => 'g2'],
        ],
    ]),
    'dentistry-1402',
    $scope
);
$deterministicB = classops_audience_resolve_from_source(
    $source,
    audience_spec([
        'op' => 'any',
        'children' => [
            ['op' => 'selector', 'kind' => 'group', 'key' => 'g2'],
            ['op' => 'students', 'studentNumbers' => ['10001', '10002']],
        ],
    ]),
    'dentistry-1402',
    $scope
);
audience_test_same($deterministicA['normalizedSpec'], $deterministicB['normalizedSpec'], 'normalized ordering is nondeterministic');
audience_test_same($deterministicA['deterministicHash'], $deterministicB['deterministicHash'], 'equivalent audience hash changed');

// Snapshot: initial resolution freezes the recipient set and provenance.
$snapshotSource = new AudienceFixtureSource(audience_fixture_context());
$snapshotInitial = classops_audience_resolve_from_source(
    $snapshotSource,
    audience_spec(['op' => 'selector', 'kind' => 'group', 'key' => 'g1'], 'snapshot'),
    'dentistry-1402',
    $scope
);
audience_test_same('live_initial_snapshot', $snapshotInitial['resolutionSource'], 'initial snapshot source marker invalid');
audience_test_assert(is_array($snapshotInitial['snapshot'] ?? null), 'initial snapshot missing');
$savedSnapshot = $snapshotInitial['snapshot'];
$snapshotSource->context['selectors']['group:g1']['studentNumbers'] = ['10002', '10003'];
$snapshotSource->context['source']['version'] = 'fixture-v2';
$snapshotStable = classops_audience_resolve_from_source(
    $snapshotSource,
    audience_spec(['op' => 'selector', 'kind' => 'group', 'key' => 'g1'], 'snapshot'),
    'dentistry-1402',
    $scope,
    $savedSnapshot
);
audience_test_same(['10001', '10002'], $snapshotStable['recipientStudentNumbers'], 'snapshot silently drifted');
audience_test_same($snapshotInitial['deterministicHash'], $snapshotStable['deterministicHash'], 'snapshot hash changed');
audience_test_same($snapshotInitial['provenance'], $snapshotStable['provenance'], 'snapshot provenance changed');

// Snapshot tampering is detected even when fields remain structurally valid.
$tampered = $savedSnapshot;
$tampered['provenance']['source']['version'] = 'fixture-tampered';
audience_test_expect(
    'CLASSOPS_AUDIENCE_SNAPSHOT_TAMPERED',
    static fn() => classops_audience_resolve_from_source(
        $snapshotSource,
        audience_spec(['op' => 'selector', 'kind' => 'group', 'key' => 'g1'], 'snapshot'),
        'dentistry-1402',
        $scope,
        $tampered
    )
);

// Live re-resolution changes when canonical membership changes; expected hash blocks silent drift.
$liveSource = new AudienceFixtureSource(audience_fixture_context());
$liveInitial = classops_audience_resolve_from_source(
    $liveSource,
    audience_spec(['op' => 'selector', 'kind' => 'group', 'key' => 'g1'], 'live'),
    'dentistry-1402',
    $scope
);
$liveSource->context['selectors']['group:g1']['studentNumbers'] = ['10002', '10003'];
$liveSource->context['source']['version'] = 'fixture-v2';
$liveNext = classops_audience_resolve_from_source(
    $liveSource,
    audience_spec(['op' => 'selector', 'kind' => 'group', 'key' => 'g1'], 'live'),
    'dentistry-1402',
    $scope
);
audience_test_same(['10002', '10003'], $liveNext['recipientStudentNumbers'], 'live audience did not re-resolve');
audience_test_assert($liveInitial['deterministicHash'] !== $liveNext['deterministicHash'], 'live membership drift did not change hash');
audience_test_expect(
    'CLASSOPS_AUDIENCE_DRIFT',
    static fn() => classops_audience_resolve_from_source(
        $liveSource,
        audience_spec(['op' => 'selector', 'kind' => 'group', 'key' => 'g1'], 'live'),
        'dentistry-1402',
        $scope,
        null,
        $liveInitial['deterministicHash']
    ),
    409
);

// Preview is aggregate by default and detailed identifiers are explicit opt-in.
$previewPrevious = classops_audience_resolve_from_source(
    $source,
    audience_spec(['op' => 'students', 'studentNumbers' => ['10001', '10002']]),
    'dentistry-1402',
    $scope
);
$previewCurrent = classops_audience_resolve_from_source(
    $source,
    audience_spec(['op' => 'students', 'studentNumbers' => ['10002', '10003', '19999']]),
    'dentistry-1402',
    $scope
);
$preview = classops_audience_preview($previewCurrent, $previewPrevious);
audience_test_same(2, $preview['total'], 'preview total failed');
audience_test_same(1, $preview['added'], 'preview added count failed');
audience_test_same(1, $preview['removed'], 'preview removed count failed');
audience_test_same(1, $preview['unresolved'], 'preview unresolved count failed');
audience_test_assert(!array_key_exists('addedStudentNumbers', $preview), 'aggregate preview leaked added identifiers');
audience_test_assert(!array_key_exists('removedStudentNumbers', $preview), 'aggregate preview leaked removed identifiers');
audience_test_assert(!array_key_exists('unresolvedReferences', $preview), 'aggregate preview leaked unresolved identifiers');
$detailedPreview = classops_audience_preview($previewCurrent, $previewPrevious, true);
audience_test_same(['10003'], $detailedPreview['addedStudentNumbers'], 'detailed preview added diff failed');
audience_test_same(['10001'], $detailedPreview['removedStudentNumbers'], 'detailed preview removed diff failed');
audience_test_same('19999', $detailedPreview['unresolvedReferences'][0]['reference'] ?? '', 'detailed preview unresolved diff failed');

// Owner scope and source cohort isolation are fail-closed.
audience_test_expect(
    'CLASSOPS_AUDIENCE_OWNER_REQUIRED',
    static fn() => classops_audience_resolve_from_source(
        $source,
        audience_spec(['op' => 'whole_cohort']),
        'dentistry-1402',
        ['role' => 'student', 'cohortKeys' => ['dentistry-1402']]
    ),
    403
);
audience_test_expect(
    'CLASSOPS_AUDIENCE_OWNER_SCOPE_DENIED',
    static fn() => classops_audience_resolve_from_source(
        $source,
        audience_spec(['op' => 'whole_cohort']),
        'dentistry-1402',
        ['role' => 'owner', 'cohortKeys' => ['dentistry-1403']]
    ),
    403
);
$badContext = audience_fixture_context();
$badContext['roster'][0]['cohortKey'] = 'dentistry-1403';
$badSource = new AudienceFixtureSource($badContext);
audience_test_expect(
    'CLASSOPS_AUDIENCE_CROSS_COHORT_SOURCE',
    static fn() => classops_audience_resolve_from_source(
        $badSource,
        audience_spec(['op' => 'whole_cohort']),
        'dentistry-1402',
        $scope
    )
);

// Display names cannot be used as identity/authorization references; errors are PII-safe.
$nameError = audience_test_expect(
    'CLASSOPS_AUDIENCE_INVALID_STUDENT_REF',
    static fn() => classops_audience_resolve_from_source(
        $source,
        audience_spec(['op' => 'students', 'studentNumbers' => ['Person Fixture Name']]),
        'dentistry-1402',
        $scope
    )
);
audience_test_assert(!str_contains($nameError->getMessage(), 'Person Fixture Name'), 'invalid identity error leaked display input');
audience_test_expect(
    'CLASSOPS_AUDIENCE_UNKNOWN_FIELD',
    static fn() => classops_audience_resolve_from_source(
        $source,
        audience_spec(['op' => 'students', 'studentNumbers' => ['10001'], 'displayName' => 'Forbidden']),
        'dentistry-1402',
        $scope
    )
);

// Source failures are converted to a PII/detail-safe 503.
$sourceFailure = audience_test_expect(
    'CLASSOPS_AUDIENCE_FIXTURE_SOURCE_DOWN',
    static fn() => classops_audience_resolve_from_source(
        new AudienceFailingSource(),
        audience_spec(['op' => 'whole_cohort']),
        'dentistry-1402',
        $scope
    ),
    503
);
audience_test_assert(!str_contains($sourceFailure->getMessage(), 'fixture detail'), 'source exception detail leaked');

// Legacy placeholder compatibility is explicit and bounded.
$legacyWhole = classops_audience_upgrade_placeholder([
    'version' => 'classops-audience-placeholder-v1',
    'mode' => 'entire_cohort',
    'refs' => [],
]);
audience_test_same(['op' => 'whole_cohort'], $legacyWhole['expression'], 'legacy whole cohort upgrade failed');
$legacyStudents = classops_audience_upgrade_placeholder([
    'version' => 'classops-audience-placeholder-v1',
    'mode' => 'explicit_students',
    'refs' => ['10002', '10001'],
]);
audience_test_same(['10001', '10002'], $legacyStudents['expression']['studentNumbers'], 'legacy explicit upgrade failed');
audience_test_expect(
    'CLASSOPS_AUDIENCE_LEGACY_MODE_REQUIRES_MIGRATION',
    static fn() => classops_audience_upgrade_placeholder([
        'version' => 'classops-audience-placeholder-v1',
        'mode' => 'academic_group',
        'refs' => ['g1'],
    ])
);

// Depth boundary: depth 6 is accepted; depth 7 is rejected.
$depthSix = ['op' => 'whole_cohort'];
for ($i = 0; $i < 5; $i++) {
    $depthSix = ['op' => 'not', 'child' => $depthSix];
}
$normalizedDepthSix = classops_audience_normalize_spec(audience_spec($depthSix));
audience_test_same(CLASSOPS_AUDIENCE_CONTRACT_VERSION, $normalizedDepthSix['version'], 'depth six should normalize');
$depthSeven = ['op' => 'not', 'child' => $depthSix];
audience_test_expect(
    'CLASSOPS_AUDIENCE_MAX_DEPTH',
    static fn() => classops_audience_normalize_spec(audience_spec($depthSeven))
);

// Child and node budgets.
$tooManyChildren = [];
for ($i = 0; $i < 17; $i++) {
    $tooManyChildren[] = ['op' => 'selector', 'kind' => 'group', 'key' => 'g' . $i];
}
audience_test_expect(
    'CLASSOPS_AUDIENCE_INVALID_CHILDREN',
    static fn() => classops_audience_normalize_spec(audience_spec(['op' => 'any', 'children' => $tooManyChildren]))
);
$nodeBudgetChildren = [];
for ($i = 0; $i < 16; $i++) {
    $nested = [];
    for ($j = 0; $j < 3; $j++) {
        $nested[] = ['op' => 'selector', 'kind' => 'group', 'key' => 'n' . $i . 'x' . $j];
    }
    $nodeBudgetChildren[] = ['op' => 'all', 'children' => $nested];
}
audience_test_expect(
    'CLASSOPS_AUDIENCE_MAX_NODES',
    static fn() => classops_audience_normalize_spec(audience_spec(['op' => 'any', 'children' => $nodeBudgetChildren]))
);

// Per-list and aggregate student-reference budgets.
audience_test_expect(
    'CLASSOPS_AUDIENCE_REF_LIMIT',
    static fn() => classops_audience_normalize_spec(audience_spec([
        'op' => 'students',
        'studentNumbers' => array_fill(0, 501, '10001'),
    ]))
);
$first500 = [];
$second500 = [];
for ($i = 10000; $i < 10500; $i++) {
    $first500[] = (string) $i;
}
for ($i = 10500; $i < 11000; $i++) {
    $second500[] = (string) $i;
}
audience_test_expect(
    'CLASSOPS_AUDIENCE_REF_LIMIT',
    static fn() => classops_audience_normalize_spec(audience_spec(
        ['op' => 'students', 'studentNumbers' => $first500],
        'live',
        $second500,
        ['11000']
    ))
);

// The canonical auth-store adapter must not acquire transport/name-matching dependencies.
$adapterSource = file_get_contents(
    dirname(__DIR__) . '/public_html/api/classops_modules/audience/auth_store_source.php'
);
audience_test_assert(is_string($adapterSource), 'unable to read canonical audience adapter source');
foreach (['dent_bot_', 'bot_store.php', 'telegram', 'bale', 'dent_rotation_assignment_for_name', 'dent_user_rotation_assignment'] as $forbiddenMarker) {
    audience_test_assert(
        stripos($adapterSource, $forbiddenMarker) === false,
        'canonical audience adapter contains forbidden dependency marker: ' . $forbiddenMarker
    );
}

fwrite(STDOUT, "ClassOps audience policy tests passed.\n");
