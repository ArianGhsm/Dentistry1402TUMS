<?php
declare(strict_types=1);

require_once __DIR__ . '/academic_term7_management.php';

const DENT_TERM7_BOOKLET_SYSTEM_SCHEMA = 1;
const DENT_TERM7_BOOKLET_SYSTEM_VERSION = '1405-1406.1';
const DENT_TERM7_BOOKLET_INFOGRAPHIC_ROLE = 'infographic';
const DENT_TERM7_BOOKLET_PODCAST_ROLE = 'podcast';

function dent_term7_booklet_system_path(): string
{
    return dent_storage_path('academic/term7-1405-1406-booklet-system.json');
}

function dent_term7_booklet_special_role_catalog(): array
{
    return [
        DENT_TERM7_BOOKLET_INFOGRAPHIC_ROLE => [
            'label' => 'مسئول اینفوگرافیک',
            'grantsFreeSubscription' => true,
        ],
        DENT_TERM7_BOOKLET_PODCAST_ROLE => [
            'label' => 'مسئول پادکست',
            'grantsFreeSubscription' => true,
        ],
    ];
}

function dent_term7_booklet_course_catalog(): array
{
    return [
        'orthodontics-theory-1' => ['title' => 'ارتودانتیکس نظری ۱'],
        'partial-basics-theory' => ['title' => 'مبانی پروتز پارسیل نظری'],
        'endodontics-theory-1' => ['title' => 'اندودانتیکس نظری ۱'],
        'diagnostic-dentistry-3-khanmohammadi' => ['title' => 'تشخیصی ۳ — آقای خان‌محمدی'],
        'periodontology-theory-1' => ['title' => 'پریودونتولوژی نظری ۱'],
        'endodontics-basics-2' => ['title' => 'مبانی اندودانتیکس ۲'],
        'oral-health-theory-2' => ['title' => 'سلامت دهان نظری ۲'],
        'research-methods-2' => ['title' => 'روش تحقیق ۲'],
        'ent' => ['title' => 'گوش و حلق و بینی'],
        'diagnostic-dentistry-3-nasr' => ['title' => 'تشخیصی ۳ — آقای نصر'],
    ];
}

function dent_term7_booklet_system_default(): array
{
    $groups = [];
    for ($group = 1; $group <= 31; $group++) {
        $groups[(string) $group] = [
            'group' => $group,
            'leaderStudentNumber' => '',
            'members' => [],
            'updatedAt' => '',
        ];
    }
    $courseGroups = [];
    foreach (dent_term7_booklet_course_catalog() as $key => $_meta) {
        $courseGroups[$key] = [];
    }
    return [
        'schemaVersion' => DENT_TERM7_BOOKLET_SYSTEM_SCHEMA,
        'sourceVersion' => DENT_TERM7_BOOKLET_SYSTEM_VERSION,
        'groups' => $groups,
        'courseGroups' => $courseGroups,
        'managers' => [],
        'specialRoles' => [],
        'sourceMeta' => [],
    ];
}

function dent_term7_booklet_system_normalize(array $raw): array
{
    $state = dent_term7_booklet_system_default();
    $seenMembers = [];
    foreach (range(1, 31) as $group) {
        $key = (string) $group;
        $source = is_array($raw['groups'][$key] ?? null) ? $raw['groups'][$key] : [];
        $members = [];
        foreach (is_array($source['members'] ?? null) ? $source['members'] : [] as $studentNumberRaw) {
            $studentNumber = dent_normalize_student_number((string) $studentNumberRaw);
            if ($studentNumber === '' || isset($seenMembers[$studentNumber])) {
                continue;
            }
            $seenMembers[$studentNumber] = true;
            $members[] = $studentNumber;
        }
        sort($members, SORT_STRING);
        $leader = dent_normalize_student_number((string) ($source['leaderStudentNumber'] ?? ''));
        if ($leader === '' || !in_array($leader, $members, true)) {
            $leader = '';
        }
        $state['groups'][$key] = [
            'group' => $group,
            'leaderStudentNumber' => $leader,
            'members' => $members,
            'updatedAt' => trim((string) ($source['updatedAt'] ?? '')),
        ];
    }

    $catalog = dent_term7_booklet_course_catalog();
    foreach ($catalog as $courseKey => $_meta) {
        $groups = [];
        foreach (is_array($raw['courseGroups'][$courseKey] ?? null) ? $raw['courseGroups'][$courseKey] : [] as $groupRaw) {
            $group = (int) $groupRaw;
            if ($group >= 1 && $group <= 31) {
                $groups[$group] = $group;
            }
        }
        ksort($groups, SORT_NUMERIC);
        $state['courseGroups'][$courseKey] = array_values($groups);
    }

    foreach (is_array($raw['managers'] ?? null) ? $raw['managers'] : [] as $studentNumberRaw => $managerRaw) {
        if (!is_array($managerRaw)) {
            continue;
        }
        $studentNumber = dent_normalize_student_number((string) ($managerRaw['studentNumber'] ?? $studentNumberRaw));
        if ($studentNumber === '') {
            continue;
        }
        $courseKeys = [];
        foreach (is_array($managerRaw['courseKeys'] ?? null) ? $managerRaw['courseKeys'] : [] as $courseKeyRaw) {
            $courseKey = trim((string) $courseKeyRaw);
            if (isset($catalog[$courseKey])) {
                $courseKeys[$courseKey] = $courseKey;
            }
        }
        if ($courseKeys === []) {
            continue;
        }
        $state['managers'][$studentNumber] = [
            'studentNumber' => $studentNumber,
            'courseKeys' => array_values($courseKeys),
            'updatedAt' => trim((string) ($managerRaw['updatedAt'] ?? '')),
        ];
    }
    ksort($state['managers'], SORT_STRING);

    foreach (is_array($raw['specialRoles'] ?? null) ? $raw['specialRoles'] : [] as $studentNumberRaw => $rolesRaw) {
        $studentNumber = dent_normalize_student_number((string) $studentNumberRaw);
        if ($studentNumber === '' || !is_array($rolesRaw)) {
            continue;
        }
        $roles = [];
        $roleCatalog = dent_term7_booklet_special_role_catalog();
        foreach ($rolesRaw as $roleRaw) {
            if (!is_array($roleRaw)) {
                continue;
            }
            $roleKey = trim((string) ($roleRaw['key'] ?? ''));
            if (!isset($roleCatalog[$roleKey])) {
                continue;
            }
            $roles[$roleKey] = [
                'key' => $roleKey,
                'label' => (string) ($roleCatalog[$roleKey]['label'] ?? ''),
                'grantsFreeSubscription' => !empty($roleCatalog[$roleKey]['grantsFreeSubscription']),
            ];
        }
        if ($roles !== []) {
            $state['specialRoles'][$studentNumber] = array_values($roles);
        }
    }
    ksort($state['specialRoles'], SORT_STRING);

    $sourceMeta = is_array($raw['sourceMeta'] ?? null) ? $raw['sourceMeta'] : [];
    $state['sourceMeta'] = [
        'title' => trim((string) ($sourceMeta['title'] ?? '')),
        'importedAt' => trim((string) ($sourceMeta['importedAt'] ?? '')),
        'updatedAt' => trim((string) ($sourceMeta['updatedAt'] ?? '')),
    ];
    return $state;
}

/** @template T @param callable(array):T $callback @return T */
function dent_term7_booklet_system_with_lock(callable $callback)
{
    $path = dent_term7_booklet_system_path();
    dent_ensure_directory(dirname($path));
    $handle = fopen($path . '.lock', 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        dent_error('ذخیره‌سازی گروه‌های جزوه‌نویسی موقتاً در دسترس نیست.', 503, ['code' => 'BOOKLET_GROUP_STORE_UNAVAILABLE']);
    }
    try {
        $decoded = is_file($path) ? dent_read_json_file($path, []) : [];
        if (!is_array($decoded)) {
            throw new DentJsonPersistenceException('BOOKLET_GROUP_STORE_SCHEMA_INVALID', 'Booklet group state must be an object');
        }
        $state = dent_term7_booklet_system_normalize($decoded);
        $result = $callback($state);
        $state = dent_term7_booklet_system_normalize($state);
        dent_write_json_file($path, $state, true);
        return $result;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function dent_term7_booklet_system_read(): array
{
    $path = dent_term7_booklet_system_path();
    if (!is_file($path)) {
        return dent_term7_booklet_system_default();
    }
    $handle = fopen($path . '.lock', 'c');
    if ($handle === false || !flock($handle, LOCK_SH)) {
        if (is_resource($handle)) fclose($handle);
        return dent_term7_booklet_system_default();
    }
    try {
        $decoded = dent_read_json_file($path, []);
        return is_array($decoded) ? dent_term7_booklet_system_normalize($decoded) : dent_term7_booklet_system_default();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function dent_term7_booklet_user_directory(): array
{
    $store = dent_load_user_store();
    $directory = [];
    foreach (is_array($store['users'] ?? null) ? $store['users'] : [] as $studentNumberRaw => $user) {
        if (!is_array($user)) continue;
        $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $studentNumberRaw));
        if ($studentNumber === '') continue;
        $directory[$studentNumber] = [
            'studentNumber' => $studentNumber,
            'name' => trim((string) ($user['name'] ?? '')),
            'cohortKey' => dent_user_cohort_key($user),
            'role' => dent_normalize_role((string) ($user['role'] ?? 'student'), $studentNumber),
        ];
    }
    return $directory;
}

function dent_term7_booklet_membership(string $studentNumber, ?array $state = null): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    $state = $state ?? dent_term7_booklet_system_read();
    foreach ($state['groups'] as $groupRaw) {
        if (!is_array($groupRaw)) continue;
        $group = (int) ($groupRaw['group'] ?? 0);
        if ($group < 1 || $group > 31 || !in_array($studentNumber, $groupRaw['members'] ?? [], true)) continue;
        $leader = (string) ($groupRaw['leaderStudentNumber'] ?? '') === $studentNumber;
        return [
            'group' => $group,
            'status' => $leader ? 'leader' : 'member',
            'statusLabel' => $leader ? 'سرگروه' : 'عضو',
        ];
    }
    return ['group' => null, 'status' => 'unassigned', 'statusLabel' => 'بدون گروه'];
}

function dent_term7_booklet_course_rows(array $courseKeys): array
{
    $catalog = dent_term7_booklet_course_catalog();
    $rows = [];
    foreach ($courseKeys as $keyRaw) {
        $key = (string) $keyRaw;
        if (!isset($catalog[$key])) continue;
        $rows[] = [
            'key' => $key,
            'title' => (string) $catalog[$key]['title'],
        ];
    }
    return $rows;
}

function dent_term7_booklet_group_course_keys(int $group, ?array $state = null): array
{
    if ($group < 1 || $group > 31) return [];
    $state = $state ?? dent_term7_booklet_system_read();
    $keys = [];
    foreach ($state['courseGroups'] as $courseKey => $groups) {
        if (in_array($group, is_array($groups) ? $groups : [], true)) {
            $keys[] = (string) $courseKey;
        }
    }
    return $keys;
}

function dent_term7_booklet_public_profile(string $studentNumber, ?array $state = null, ?array $directory = null): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    $state = $state ?? dent_term7_booklet_system_read();
    $directory = $directory ?? dent_term7_booklet_user_directory();
    $membership = dent_term7_booklet_membership($studentNumber, $state);
    $group = is_int($membership['group'] ?? null) ? (int) $membership['group'] : null;
    $groupCourseKeys = $group !== null ? dent_term7_booklet_group_course_keys($group, $state) : [];
    $manager = is_array($state['managers'][$studentNumber] ?? null) ? $state['managers'][$studentNumber] : [];
    $managerCourseKeys = array_values(is_array($manager['courseKeys'] ?? null) ? $manager['courseKeys'] : []);
    $specialRoles = array_values(is_array($state['specialRoles'][$studentNumber] ?? null) ? $state['specialRoles'][$studentNumber] : []);
    $specialFree = false;
    foreach ($specialRoles as $role) {
        if (is_array($role) && !empty($role['grantsFreeSubscription'])) {
            $specialFree = true;
            break;
        }
    }
    $isOwner = (string) ($directory[$studentNumber]['role'] ?? '') === 'owner';
    $free = $group !== null || $specialFree || $isOwner;
    return [
        'contractVersion' => 'term7-booklet-system-v1',
        'studentNumber' => $studentNumber,
        'name' => (string) ($directory[$studentNumber]['name'] ?? ''),
        'group' => $group,
        'status' => (string) ($membership['status'] ?? 'unassigned'),
        'statusLabel' => (string) ($membership['statusLabel'] ?? 'بدون گروه'),
        'groupCourses' => dent_term7_booklet_course_rows($groupCourseKeys),
        'isBookletManager' => $managerCourseKeys !== [],
        'managerCourses' => dent_term7_booklet_course_rows($managerCourseKeys),
        'specialRoles' => $specialRoles,
        'freeSubscriptionEligible' => $free,
        'subscriptionPriceRials' => $free ? 0 : 1500000,
        'subscriptionLabel' => $free ? 'رایگان · فعال‌سازی خودکار ماهانه' : '۱۵۰٬۰۰۰ تومان ماهانه',
    ];
}

function dent_term7_booklet_public_context(string $studentNumber, ?array $state = null, ?array $directory = null): array
{
    $state = $state ?? dent_term7_booklet_system_read();
    $directory = $directory ?? dent_term7_booklet_user_directory();
    $profile = dent_term7_booklet_public_profile($studentNumber, $state, $directory);
    $group = is_int($profile['group'] ?? null) ? (int) $profile['group'] : null;
    $members = [];
    $leaderName = '';
    if ($group !== null) {
        $record = is_array($state['groups'][(string) $group] ?? null) ? $state['groups'][(string) $group] : [];
        $leaderStudentNumber = (string) ($record['leaderStudentNumber'] ?? '');
        foreach (is_array($record['members'] ?? null) ? $record['members'] : [] as $memberStudentNumber) {
            $memberStudentNumber = dent_normalize_student_number((string) $memberStudentNumber);
            $name = trim((string) ($directory[$memberStudentNumber]['name'] ?? ''));
            if ($name === '') continue;
            $members[] = [
                'studentNumber' => $memberStudentNumber,
                'name' => $name,
                'status' => $memberStudentNumber === $leaderStudentNumber ? 'leader' : 'member',
                'statusLabel' => $memberStudentNumber === $leaderStudentNumber ? 'سرگروه' : 'عضو',
            ];
            if ($memberStudentNumber === $leaderStudentNumber) $leaderName = $name;
        }
    }
    $profile['leaderName'] = $leaderName;
    $profile['memberCount'] = count($members);
    $profile['members'] = $members;
    return $profile;
}

function dent_term7_booklet_group_rows(?array $state = null, ?array $directory = null): array
{
    $state = $state ?? dent_term7_booklet_system_read();
    $directory = $directory ?? dent_term7_booklet_user_directory();
    $rows = [];
    foreach (range(1, 31) as $group) {
        $record = $state['groups'][(string) $group];
        $leaderStudentNumber = (string) ($record['leaderStudentNumber'] ?? '');
        $members = [];
        foreach ($record['members'] as $studentNumber) {
            $name = trim((string) ($directory[$studentNumber]['name'] ?? ''));
            if ($name === '') continue;
            $members[] = [
                'studentNumber' => $studentNumber,
                'name' => $name,
                'status' => $studentNumber === $leaderStudentNumber ? 'leader' : 'member',
                'statusLabel' => $studentNumber === $leaderStudentNumber ? 'سرگروه' : 'عضو',
            ];
        }
        $rows[] = [
            'group' => $group,
            'leaderStudentNumber' => $leaderStudentNumber,
            'leaderName' => (string) ($directory[$leaderStudentNumber]['name'] ?? ''),
            'memberCount' => count($members),
            'members' => $members,
            'courses' => dent_term7_booklet_course_rows(dent_term7_booklet_group_course_keys($group, $state)),
        ];
    }
    return $rows;
}

function dent_term7_booklet_owner_payload(array $owner): array
{
    dent_term7_require_owner($owner);
    $state = dent_term7_booklet_system_read();
    $directory = dent_term7_booklet_user_directory();
    $classRoster = [];
    $classMembers = 0;
    $classFreeEligible = 0;
    foreach ($directory as $studentNumberRaw => $user) {
        $studentNumber = (string) $studentNumberRaw;
        if (($user['cohortKey'] ?? '') !== DENT_TERM7_COHORT) continue;
        $profile = dent_term7_booklet_public_profile($studentNumber, $state, $directory);
        if (!empty($profile['group'])) $classMembers++;
        if (!empty($profile['freeSubscriptionEligible'])) $classFreeEligible++;
        $classRoster[] = [
            'studentNumber' => $studentNumber,
            'name' => (string) ($user['name'] ?? ''),
            'bookletSystem' => $profile,
        ];
    }
    usort($classRoster, static fn(array $a, array $b): int => strcmp(
        dent_term7_normalize_person_name((string) ($a['name'] ?? '')),
        dent_term7_normalize_person_name((string) ($b['name'] ?? ''))
    ));

    $relatedNumbers = [];
    foreach ($state['groups'] as $record) {
        foreach ($record['members'] ?? [] as $studentNumber) $relatedNumbers[(string) $studentNumber] = true;
    }
    foreach (array_keys($state['managers']) as $studentNumber) $relatedNumbers[(string) $studentNumber] = true;
    foreach (array_keys($state['specialRoles']) as $studentNumber) $relatedNumbers[(string) $studentNumber] = true;
    $members = [];
    foreach (array_keys($relatedNumbers) as $studentNumberRaw) {
        $studentNumber = (string) $studentNumberRaw;
        if (!isset($directory[$studentNumber])) continue;
        $members[] = [
            'studentNumber' => $studentNumber,
            'name' => (string) ($directory[$studentNumber]['name'] ?? ''),
            'cohortKey' => (string) ($directory[$studentNumber]['cohortKey'] ?? ''),
            'bookletSystem' => dent_term7_booklet_public_profile($studentNumber, $state, $directory),
        ];
    }
    usort($members, static fn(array $a, array $b): int => strcmp(
        dent_term7_normalize_person_name((string) ($a['name'] ?? '')),
        dent_term7_normalize_person_name((string) ($b['name'] ?? ''))
    ));

    $free = array_values(array_filter(
        $members,
        static fn(array $row): bool => !empty($row['bookletSystem']['freeSubscriptionEligible'])
    ));

    return [
        'contractVersion' => 'term7-booklet-system-v1',
        'sourceVersion' => (string) ($state['sourceVersion'] ?? DENT_TERM7_BOOKLET_SYSTEM_VERSION),
        'summary' => [
            'classCount' => count($classRoster),
            'classBookletMembers' => $classMembers,
            'classOutsideBookletGroups' => count($classRoster) - $classMembers,
            'classFreeEligible' => $classFreeEligible,
            'classPaidMembers' => count($classRoster) - $classFreeEligible,
            'bookletMembers' => count(array_filter($members, static fn(array $row): bool => !empty($row['bookletSystem']['group']))),
            'freeEligible' => count($free),
        ],
        'groups' => dent_term7_booklet_group_rows($state, $directory),
        'classRoster' => $classRoster,
        'members' => $members,
    ];
}

function dent_term7_booklet_require_user(string $studentNumber): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
    if (!is_array($user)) {
        dent_error('دانشجو پیدا نشد.', 404, ['code' => 'BOOKLET_STUDENT_NOT_FOUND']);
    }
    return $user;
}

function dent_term7_booklet_owner_update_group(array $owner, string $studentNumber, ?int $group): array
{
    dent_term7_require_owner($owner);
    $user = dent_term7_booklet_require_user($studentNumber);
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $studentNumber));
    if ($group !== null && ($group < 1 || $group > 31)) {
        dent_error('شماره گروه جزوه‌نویسی معتبر نیست.', 422, ['code' => 'BOOKLET_GROUP_RANGE_INVALID']);
    }
    dent_term7_booklet_system_with_lock(static function (array &$state) use ($studentNumber, $group): array {
        foreach ($state['groups'] as $key => &$record) {
            $record['members'] = array_values(array_filter(
                $record['members'],
                static fn(string $value): bool => $value !== $studentNumber
            ));
            if ((string) ($record['leaderStudentNumber'] ?? '') === $studentNumber) {
                $record['leaderStudentNumber'] = '';
            }
            $record['updatedAt'] = dent_iso_now();
        }
        unset($record);
        if ($group !== null) {
            $key = (string) $group;
            $state['groups'][$key]['members'][] = $studentNumber;
            $state['groups'][$key]['members'] = array_values(array_unique($state['groups'][$key]['members']));
            sort($state['groups'][$key]['members'], SORT_STRING);
            $state['groups'][$key]['updatedAt'] = dent_iso_now();
        }
        $state['sourceMeta']['updatedAt'] = dent_iso_now();
        return [];
    });
    return dent_term7_booklet_public_profile($studentNumber);
}

function dent_term7_booklet_owner_set_leader(array $owner, string $studentNumber, bool $leader): array
{
    dent_term7_require_owner($owner);
    $user = dent_term7_booklet_require_user($studentNumber);
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $studentNumber));
    $membership = dent_term7_booklet_membership($studentNumber);
    $group = is_int($membership['group'] ?? null) ? (int) $membership['group'] : null;
    if ($group === null) {
        dent_error('برای تعیین سرگروه ابتدا گروه جزوه‌نویسی را مشخص کن.', 409, ['code' => 'BOOKLET_GROUP_REQUIRED']);
    }
    dent_term7_booklet_system_with_lock(static function (array &$state) use ($studentNumber, $group, $leader): array {
        $key = (string) $group;
        if ($leader) {
            $state['groups'][$key]['leaderStudentNumber'] = $studentNumber;
        } elseif ((string) ($state['groups'][$key]['leaderStudentNumber'] ?? '') === $studentNumber) {
            $state['groups'][$key]['leaderStudentNumber'] = '';
        }
        $state['groups'][$key]['updatedAt'] = dent_iso_now();
        $state['sourceMeta']['updatedAt'] = dent_iso_now();
        return [];
    });
    return dent_term7_booklet_public_profile($studentNumber);
}

function dent_term7_booklet_free_subscription_roster(array $owner): array
{
    dent_term7_require_owner($owner);
    $state = dent_term7_booklet_system_read();
    $directory = dent_term7_booklet_user_directory();
    $numbers = [];
    foreach ($state['groups'] as $record) {
        foreach ($record['members'] ?? [] as $studentNumber) $numbers[(string) $studentNumber] = true;
    }
    foreach ($state['specialRoles'] as $studentNumber => $roles) {
        foreach (is_array($roles) ? $roles : [] as $role) {
            if (is_array($role) && !empty($role['grantsFreeSubscription'])) {
                $numbers[(string) $studentNumber] = true;
                break;
            }
        }
    }
    $ownerStudentNumber = dent_normalize_student_number((string) ($owner['studentNumber'] ?? ''));
    if ($ownerStudentNumber !== '') $numbers[$ownerStudentNumber] = true;

    $eligible = [];
    foreach (array_keys($numbers) as $studentNumberRaw) {
        $studentNumber = (string) $studentNumberRaw;
        if (!isset($directory[$studentNumber])) continue;
        $profile = dent_term7_booklet_public_profile($studentNumber, $state, $directory);
        $eligible[] = [
            'studentNumber' => $studentNumber,
            'name' => (string) ($directory[$studentNumber]['name'] ?? ''),
            'group' => $profile['group'],
            'specialRoles' => $profile['specialRoles'],
        ];
    }
    usort($eligible, static fn(array $a, array $b): int => strcmp((string) $a['studentNumber'], (string) $b['studentNumber']));
    return [
        'contractVersion' => 'term7-booklet-free-roster-v1',
        'term' => 7,
        'eligible' => $eligible,
        'count' => count($eligible),
    ];
}
