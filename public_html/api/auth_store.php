<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function dent_owner_student_number(): string
{
    return '40211272003';
}

function dent_auth_store_path(): string
{
    return dent_storage_path('auth/users.json');
}

function dent_public_html_path(string $relativePath): string
{
    $trimmed = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
    return DENT_PROJECT_ROOT . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . $trimmed;
}

function dent_default_profile(): array
{
    return [
        'about' => '',
        'bio' => '',
        'contactHandle' => '',
        'focusArea' => '',
        'avatarUrl' => '',
    ];
}

function dent_rotation_name_canonical(string $value): string
{
    $normalized = dent_force_utf8($value);
    $normalized = str_replace(
        ['ي', 'ك', 'ة', 'ۀ', 'أ', 'إ', 'ٱ', 'آ', 'ؤ', 'ئ'],
        ['ی', 'ک', 'ه', 'ه', 'ا', 'ا', 'ا', 'ا', 'و', 'ی'],
        $normalized
    );
    $normalized = preg_replace('/[\x{200c}\x{200d}\x{200e}\x{200f}\s\-_]+/u', '', $normalized) ?? '';
    $normalized = preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?? '';
    $normalized = trim(dent_utf8_strtolower($normalized));
    return $normalized;
}

function dent_rotation_name_keys(string $value): array
{
    $base = dent_rotation_name_canonical($value);
    if ($base === '') {
        return [];
    }

    $keys = [$base => true];
    $prefixes = ['سیده', 'سید', 'سادات'];
    foreach ($prefixes as $prefix) {
        if (!str_starts_with($base, $prefix)) {
            continue;
        }
        $next = trim(dent_utf8_substr($base, dent_utf8_strlen($prefix), dent_utf8_strlen($base)));
        if ($next !== '') {
            $keys[$next] = true;
        }
    }

    return array_keys($keys);
}

function dent_rotation_group_catalog(): array
{
    return [
        [
            'rotationId' => 1,
            'groupNumber' => 1,
            'groupTitle' => 'گروه آقای آقایی',
            'members' => [
                'سید مهدی آقایی',
                'سجاد اشرف',
                'سجاد اشرف گنجوئی',
                'سجاد اشرف گنجویی',
                'حسام قربانی',
                'حسین شاهسواری',
                'مهدی گنجی وطن',
                'نازنین عبادی',
                'مبینا روحانی',
                'مینا مطبعه چی',
                'زهرا رفیعی راد',
                'فرزاد مصطفوی',
            ],
        ],
        [
            'rotationId' => 1,
            'groupNumber' => 2,
            'groupTitle' => 'گروه آقای پورهاشمی',
            'members' => [
                'حسام محمدی',
                'امیرمهدی طاهری',
                'رضا رحیمی',
                'حسین عبدی',
                'محمدرضا حسینی جی',
                'شقایق باقری',
                'سارینا غلامی',
                'مهدی پورهاشمی',
                'عرفان محبوبی‌نیا',
                'عرفان محبوبی نیا',
            ],
        ],
        [
            'rotationId' => 1,
            'groupNumber' => 3,
            'groupTitle' => 'گروه آقای آهنگ',
            'members' => [
                'حسین آهنگ',
                'احسان فضلی',
                'علیرضا نصرتی',
                'ابولفضل موسی زاده',
                'ابوالفضل موسی زاده',
                'امیرحسین درواری',
                'سیدامیرحسین درواری',
                'آیناز شاهنده',
                'مبینا لطفی',
                'اسما زارعی پور',
                'سیده اسما زارعی پور',
                'فاطمه موسی زاده',
                'شیدا سادات کریمی',
                'بهاره مهدیقلی',
            ],
        ],
        [
            'rotationId' => 1,
            'groupNumber' => 4,
            'groupTitle' => 'گروه آقای موسوی',
            'members' => [
                'مانی موسوی',
                'آرمان اعوانی',
                'آتنا یوسف زاده',
                'مهدی اسماعیلی',
                'رژین جباری',
                'مائده بابایی آذر',
                'محدثه رستملو',
                'رضوانه کاظمی مقدم',
                'محمدحسین رضازاده',
                'علیرضا تیموری',
            ],
        ],
        [
            'rotationId' => 1,
            'groupNumber' => 5,
            'groupTitle' => 'گروه خانم کریمی',
            'members' => [
                'زهرا کریمی',
                'بهاره ابراهیمی',
                'وستا ایزدی',
                'نسترن طبسی',
                'فاطمه فتحی',
                'ثنا مهدوی',
                'مجتبی نیک مراد',
                'امیرحسین شهیدی',
                'آرمین عظیمی',
                'مهدی رستمی',
            ],
        ],
        [
            'rotationId' => 2,
            'groupNumber' => 6,
            'groupTitle' => 'گروه خانم مالکی',
            'members' => [
                'صبا مالکی',
                'الناز امیردادی',
                'کوثر قربانی',
                'آرین قاسم پور',
                'فاطمه افلاطونی',
                'مریم قنبری',
                'سحر سلیمانی',
                'محدثه هدایتی',
                'فاطمه سالمی',
            ],
        ],
        [
            'rotationId' => 2,
            'groupNumber' => 7,
            'groupTitle' => 'گروه خانم جهان‌زاد',
            'members' => [
                'درسا ابراهیم‌زاده',
                'درسا ابراهیم زاده',
                'امیرمحمد امجدی',
                'بردیا باطبی',
                'آرین تقوی',
                'سحر جهان‌زاد',
                'سحر جهان زاد',
                'امیرحسین حاتمی',
                'نازنین ختایی',
                'عسل عزیزمحمدی',
                'آیلین هاشمی',
                'شقایق صمدیان',
            ],
        ],
        [
            'rotationId' => 2,
            'groupNumber' => 8,
            'groupTitle' => 'گروه آقای زارع',
            'members' => [
                'علی‌سینا امیری',
                'علی سینا امیری',
                'محمد بهمن‌آبادی',
                'محمد بهمن ابادی',
                'هومن چاووشی‌فر',
                'هومن چاووشی فر',
                'ایلیا خان‌محمدی',
                'ایلیا خان محمدی',
                'محمد مهدی زارع',
                'سید امیر‌حسین علوی',
                'سید امیرحسین علوی',
                'امیر‌محمد قلندری',
                'امیرمحمد قلندری',
                'حسین محمدی',
                'محمد‌جواد نصر',
                'محمدجواد نصر',
                'محمد جواد نصر',
                'محمد‌رضا یگانه',
                'محمدرضا یگانه',
                'محمد رضا یگانه',
            ],
        ],
        [
            'rotationId' => 2,
            'groupNumber' => 9,
            'groupTitle' => 'گروه خانم عابدی',
            'members' => [
                'فاطمه عابدی',
                'فاطمه سادات مرجانی',
                'هستی غلامی',
                'فاطمه صابری',
                'فاطمه زین العابدینی',
                'مهدیه دهقان',
                'کوثر حاجیان نژاد',
                'آنیتا شیرخانی',
                'مبینا زمانی',
            ],
        ],
        [
            'rotationId' => 2,
            'groupNumber' => 10,
            'groupTitle' => 'گروه خانم محمودی',
            'members' => [
                'امیررضا شادمهر',
                'امیرمحمد قیصری',
                'امیر محمد قیصری',
                'محمد نوشادیان',
                'بشری محمودی',
                'ریحانه حقیقی',
                'سارا زارع کاشانی',
                'فاطمه باباپور',
                'عماد ریاست',
                'محمدرضا باجلان',
                'سارینا صادق پور',
            ],
        ],
    ];
}

function dent_rotation_assignment_index(): array
{
    static $index = null;
    if (is_array($index)) {
        return $index;
    }

    $index = [];
    foreach (dent_rotation_group_catalog() as $group) {
        $rotationId = (int) ($group['rotationId'] ?? 0);
        $groupNumber = (int) ($group['groupNumber'] ?? 0);
        if (!in_array($rotationId, [1, 2], true) || $groupNumber <= 0) {
            continue;
        }

        $assignment = [
            'rotationId' => $rotationId,
            'rotationLabel' => 'روتیشن ' . ($rotationId === 1 ? '۱' : '۲'),
            'groupNumber' => $groupNumber,
            'groupLabel' => 'گروه ' . strtr((string) $groupNumber, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']),
            'groupTitle' => (string) ($group['groupTitle'] ?? ''),
        ];
        $assignment['summary'] = $assignment['rotationLabel'] . ' • ' . $assignment['groupLabel'] . ' (' . $assignment['groupTitle'] . ')';

        $members = is_array($group['members'] ?? null) ? $group['members'] : [];
        foreach ($members as $memberName) {
            foreach (dent_rotation_name_keys((string) $memberName) as $key) {
                if (!isset($index[$key])) {
                    $index[$key] = $assignment;
                }
            }
        }
    }

    return $index;
}

function dent_rotation_assignment_for_name(string $name): ?array
{
    $keys = dent_rotation_name_keys($name);
    if ($keys === []) {
        return null;
    }

    $index = dent_rotation_assignment_index();
    foreach ($keys as $key) {
        if (isset($index[$key])) {
            return $index[$key];
        }
    }

    foreach ($keys as $key) {
        if (dent_utf8_strlen($key) < 6) {
            continue;
        }
        foreach ($index as $indexedName => $assignment) {
            if (dent_utf8_strlen($indexedName) < 6) {
                continue;
            }
            if (str_contains($indexedName, $key) || str_contains($key, $indexedName)) {
                return $assignment;
            }
        }
    }

    return null;
}

function dent_user_rotation_assignment(array $user): ?array
{
    $name = trim((string) ($user['name'] ?? ''));
    if ($name === '') {
        return null;
    }
    return dent_rotation_assignment_for_name($name);
}

function dent_clean_avatar_url(?string $value, bool $strict = false): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (strlen($value) > 400000) {
        if ($strict) {
            dent_error('اندازه عکس پروفایل بیش از حد مجاز است.', 422);
        }
        return '';
    }

    if (preg_match('/^data:image\/(png|jpe?g|webp);base64,/i', $value) === 1) {
        $parts = explode(',', $value, 2);
        if (count($parts) !== 2) {
            if ($strict) {
                dent_error('فرمت عکس پروفایل نامعتبر است.', 422);
            }
            return '';
        }

        $payload = preg_replace('/\s+/u', '', $parts[1]) ?? '';
        if ($payload === '') {
            if ($strict) {
                dent_error('داده عکس پروفایل خالی است.', 422);
            }
            return '';
        }

        $binary = base64_decode($payload, true);
        if ($binary === false) {
            if ($strict) {
                dent_error('داده عکس پروفایل معتبر نیست.', 422);
            }
            return '';
        }

        if (strlen($binary) > (220 * 1024)) {
            if ($strict) {
                dent_error('حجم عکس پروفایل بیش از حد مجاز است.', 422);
            }
            return '';
        }

        return $parts[0] . ',' . $payload;
    }

    if (str_starts_with($value, '/')) {
        return dent_clean_text($value, 400);
    }

    $parts = parse_url($value);
    if ($parts === false) {
        if ($strict) {
            dent_error('آدرس عکس پروفایل معتبر نیست.', 422);
        }
        return '';
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['https', 'http'], true)) {
        if ($strict) {
            dent_error('فقط آدرس http/https برای عکس پروفایل مجاز است.', 422);
        }
        return '';
    }

    return dent_clean_text($value, 400);
}

function dent_hash_password(string $password): string
{
    $password = dent_normalize_digits($password);
    if ($password === '') {
        dent_error('رمز عبور خالی معتبر نیست.', 422);
    }

    $iterations = 210000;
    $salt = random_bytes(16);
    $derived = hash_pbkdf2('sha256', $password, $salt, $iterations, 32, true);

    return 'pbkdf2_sha256$' . $iterations . '$' . dent_base64url_encode($salt) . '$' . dent_base64url_encode($derived);
}

function dent_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function dent_base64url_decode(string $value): string
{
    $padding = strlen($value) % 4;
    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
}

function dent_verify_password(string $password, string $storedHash): bool
{
    $password = dent_normalize_digits($password);
    $storedHash = trim($storedHash);

    if ($password === '' || $storedHash === '') {
        return false;
    }

    if (str_starts_with($storedHash, 'pbkdf2_sha256$')) {
        $parts = explode('$', $storedHash, 4);
        if (count($parts) !== 4) {
            return false;
        }

        $iterations = (int) $parts[1];
        $salt = dent_base64url_decode($parts[2]);
        $expected = dent_base64url_decode($parts[3]);
        if ($iterations < 10000 || $salt === '' || $expected === '') {
            return false;
        }

        $derived = hash_pbkdf2('sha256', $password, $salt, $iterations, strlen($expected), true);
        return hash_equals($expected, $derived);
    }

    $normalizedStored = dent_normalize_digits($storedHash);
    if ($normalizedStored !== '' && hash_equals($normalizedStored, $password)) {
        return true;
    }

    if (preg_match('/^[a-f0-9]{32}$/i', $storedHash) === 1) {
        return hash_equals(strtolower($storedHash), md5($password));
    }

    if (preg_match('/^[a-f0-9]{40}$/i', $storedHash) === 1) {
        return hash_equals(strtolower($storedHash), sha1($password));
    }

    return password_verify($password, $storedHash);
}

function dent_password_needs_rehash(string $storedHash): bool
{
    if (!str_starts_with($storedHash, 'pbkdf2_sha256$')) {
        return true;
    }

    $parts = explode('$', $storedHash, 4);
    if (count($parts) !== 4) {
        return true;
    }

    return (int) $parts[1] !== 210000;
}

function dent_normalize_role(?string $role, string $studentNumber): string
{
    if ($studentNumber === dent_owner_student_number()) {
        return 'owner';
    }

    $role = trim((string) $role);
    if ($role === 'representative') {
        return 'representative';
    }

    return 'student';
}

function dent_role_label(string $role): string
{
    if ($role === 'owner') {
        return 'مالک سامانه';
    }

    if ($role === 'representative') {
        return 'نماینده';
    }

    return 'دانشجو';
}

function dent_permissions_for_role(string $role): array
{
    if ($role === 'owner') {
        return [
            'moderateChat' => true,
            'manageUsers' => true,
            'manageRepresentatives' => true,
        ];
    }

    if ($role === 'representative') {
        return [
            'moderateChat' => true,
            'manageUsers' => false,
            'manageRepresentatives' => false,
        ];
    }

    return [
        'moderateChat' => false,
        'manageUsers' => false,
        'manageRepresentatives' => false,
    ];
}

function dent_normalize_user_record($studentNumber, array $user): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    $profile = $user['profile'] ?? [];
    $defaults = dent_default_profile();
    $name = dent_clean_text((string) ($user['name'] ?? $studentNumber), 120);
    if ($name === '') {
        $name = $studentNumber;
    }

    $about = dent_clean_text($profile['about'] ?? ($profile['bio'] ?? $defaults['about']), 280);
    if ($about === '') {
        $about = dent_clean_text($profile['bio'] ?? '', 280);
    }

    $bio = dent_clean_text($profile['bio'] ?? $about, 280);
    if ($bio === '') {
        $bio = $about;
    }

    $phoneNumber = dent_normalize_phone_number((string) (
        $user['phoneNumber']
        ?? ($user['phone']['number'] ?? '')
    ));
    $phoneVerifiedAt = trim((string) (
        $user['phoneVerifiedAt']
        ?? ($user['phone']['verifiedAt'] ?? '')
    ));
    $phoneLoginEnabledRaw = $user['phoneLoginEnabled']
        ?? ($user['phone']['otpLoginEnabled'] ?? null);
    $phoneLoginEnabled = $phoneLoginEnabledRaw === null
        ? ($phoneVerifiedAt !== '')
        : filter_var($phoneLoginEnabledRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if (!is_bool($phoneLoginEnabled)) {
        $phoneLoginEnabled = $phoneVerifiedAt !== '';
    }
    $phoneNudgeDismissedAt = trim((string) (
        $user['phoneNudgeDismissedAt']
        ?? ($user['phone']['nudgeDismissedAt'] ?? '')
    ));
    if ($phoneNumber === '') {
        $phoneVerifiedAt = '';
        $phoneLoginEnabled = false;
    } elseif ($phoneVerifiedAt === '') {
        $phoneLoginEnabled = false;
    }

    $normalized = [
        'studentNumber' => $studentNumber,
        'name' => $name,
        'passwordHash' => (string) ($user['passwordHash'] ?? ''),
        'role' => dent_normalize_role($user['role'] ?? 'student', $studentNumber),
        'profile' => [
            'about' => $about,
            'bio' => $bio,
            'contactHandle' => dent_clean_text($profile['contactHandle'] ?? $defaults['contactHandle'], 80),
                'focusArea' => dent_clean_text($profile['focusArea'] ?? $defaults['focusArea'], 80),
                'avatarUrl' => dent_clean_avatar_url($profile['avatarUrl'] ?? $defaults['avatarUrl'], false),
        ],
        'phoneNumber' => $phoneNumber,
        'phoneVerifiedAt' => $phoneVerifiedAt,
        'phoneLoginEnabled' => $phoneLoginEnabled,
        'phoneNudgeDismissedAt' => $phoneNudgeDismissedAt,
        'createdAt' => trim((string) ($user['createdAt'] ?? dent_iso_now())),
        'updatedAt' => trim((string) ($user['updatedAt'] ?? dent_iso_now())),
    ];

    if ($normalized['passwordHash'] === '') {
        dent_error('یک حساب کاربری بدون رمز معتبر در storage پیدا شد.', 500);
    }

    return $normalized;
}

function dent_read_csv_table(string $path): array
{
    if (!file_exists($path)) {
        return [[], []];
    }

    $handle = @fopen($path, 'r');
    if ($handle === false) {
        return [[], []];
    }

    $header = fgetcsv($handle);
    if ($header === false || !is_array($header)) {
        fclose($handle);
        return [[], []];
    }
    $header = array_map(
        static fn($value): string => dent_force_utf8((string) $value),
        $header
    );

    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = array_map(
            static fn($value): string => dent_force_utf8((string) $value),
            is_array($row) ? $row : []
        );
    }

    fclose($handle);
    return [$header, $rows];
}

function dent_legacy_role(string $studentNumber): string
{
    if ($studentNumber === dent_owner_student_number()) {
        return 'owner';
    }

    if ($studentNumber === '40211272021') {
        return 'representative';
    }

    return 'student';
}

function dent_legacy_user_record(string $studentNumber, string $name, string $password): ?array
{
    $normalizedStudentNumber = dent_normalize_student_number($studentNumber);
    $normalizedPassword = dent_normalize_digits($password);

    if ($normalizedStudentNumber === '' || $normalizedPassword === '') {
        return null;
    }

    $timestamp = dent_iso_now();

    return [
        'studentNumber' => $normalizedStudentNumber,
        'name' => trim($name) !== '' ? trim($name) : $normalizedStudentNumber,
        'passwordHash' => $normalizedPassword,
        'role' => dent_legacy_role($normalizedStudentNumber),
        'profile' => dent_default_profile(),
        'createdAt' => $timestamp,
        'updatedAt' => $timestamp,
    ];
}

function dent_load_legacy_users(): array
{
    $users = [];

    [$chatHeader, $chatRows] = dent_read_csv_table(dent_public_html_path('chat/users.csv'));
    $chatUserIdx = array_search('username', $chatHeader, true);
    $chatPassIdx = array_search('password', $chatHeader, true);
    $chatNameIdx = array_search('name', $chatHeader, true);

    if ($chatUserIdx !== false && $chatPassIdx !== false) {
        foreach ($chatRows as $row) {
            $record = dent_legacy_user_record(
                (string) ($row[$chatUserIdx] ?? ''),
                (string) ($row[$chatNameIdx !== false ? $chatNameIdx : $chatUserIdx] ?? ''),
                (string) ($row[$chatPassIdx] ?? '')
            );

            if ($record === null) {
                continue;
            }

            $users[$record['studentNumber']] = $record;
        }
    }

    [$gradesHeader, $gradesRows] = dent_read_csv_table(dent_public_html_path('grades/grades.csv'));
    $gradesUserIdx = array_search('StudentID', $gradesHeader, true);
    $gradesPassIdx = array_search('Password', $gradesHeader, true);
    $gradesNameIdx = array_search('Name', $gradesHeader, true);

    if ($gradesUserIdx !== false && $gradesPassIdx !== false) {
        foreach ($gradesRows as $row) {
            $record = dent_legacy_user_record(
                (string) ($row[$gradesUserIdx] ?? ''),
                (string) ($row[$gradesNameIdx !== false ? $gradesNameIdx : $gradesUserIdx] ?? ''),
                (string) ($row[$gradesPassIdx] ?? '')
            );

            if ($record === null) {
                continue;
            }

            $studentNumber = $record['studentNumber'];
            if (!isset($users[$studentNumber])) {
                $users[$studentNumber] = $record;
                continue;
            }

            if (($users[$studentNumber]['name'] ?? $studentNumber) === $studentNumber && $record['name'] !== '') {
                $users[$studentNumber]['name'] = $record['name'];
            }

            if (trim((string) ($users[$studentNumber]['passwordHash'] ?? '')) === '' && $record['passwordHash'] !== '') {
                $users[$studentNumber]['passwordHash'] = $record['passwordHash'];
            }
        }
    }

    $normalizedUsers = [];
    foreach ($users as $studentNumber => $user) {
        $normalized = dent_normalize_user_record((string) $studentNumber, $user);
        $normalizedUsers[$normalized['studentNumber']] = $normalized;
    }

    ksort($normalizedUsers, SORT_STRING);
    return $normalizedUsers;
}

function dent_load_user_store(): array
{
    $path = dent_auth_store_path();
    $store = dent_read_json_file($path, [
        'schemaVersion' => 1,
        'ownerStudentNumber' => dent_owner_student_number(),
        'users' => [],
    ]);

    if (!is_array($store)) {
        $store = [
            'schemaVersion' => 1,
            'ownerStudentNumber' => dent_owner_student_number(),
            'users' => [],
        ];
    }

    $users = $store['users'] ?? [];
    if (!is_array($users)) {
        $users = [];
    }

    $normalizedUsers = [];
    foreach ($users as $key => $user) {
        if (!is_array($user)) {
            continue;
        }

        $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $key));
        if ($studentNumber === '') {
            continue;
        }

        $normalizedUsers[$studentNumber] = dent_normalize_user_record($studentNumber, $user);
    }

    ksort($normalizedUsers, SORT_STRING);

    if (!$normalizedUsers) {
        $normalizedUsers = dent_load_legacy_users();
    }

    return [
        'schemaVersion' => 1,
        'ownerStudentNumber' => dent_owner_student_number(),
        'users' => $normalizedUsers,
    ];
}

function dent_save_user_store(array $store): void
{
    $users = $store['users'] ?? [];
    if (!is_array($users)) {
        $users = [];
    }

    $normalizedUsers = [];
    foreach ($users as $studentNumber => $user) {
        if (!is_array($user)) {
            continue;
        }

        $normalizedStudentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $studentNumber));
        if ($normalizedStudentNumber === '') {
            continue;
        }

        $normalizedUsers[$normalizedStudentNumber] = dent_normalize_user_record($normalizedStudentNumber, $user);
    }

    ksort($normalizedUsers, SORT_STRING);

    dent_write_json_file(dent_auth_store_path(), [
        'schemaVersion' => 1,
        'ownerStudentNumber' => dent_owner_student_number(),
        'users' => $normalizedUsers,
    ]);
}

function dent_get_user_record($studentNumber): ?array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        return null;
    }

    $store = dent_load_user_store();
    return $store['users'][$studentNumber] ?? null;
}

function dent_public_user(array $user): array
{
    $role = dent_normalize_role($user['role'] ?? 'student', (string) ($user['studentNumber'] ?? ''));
    $permissions = dent_permissions_for_role($role);
    $rotation = dent_user_rotation_assignment($user);
    $rotationPayload = [
        'assigned' => $rotation !== null,
        'rotationId' => $rotation['rotationId'] ?? null,
        'rotationLabel' => $rotation['rotationLabel'] ?? '',
        'groupNumber' => $rotation['groupNumber'] ?? null,
        'groupLabel' => $rotation['groupLabel'] ?? '',
        'groupTitle' => $rotation['groupTitle'] ?? '',
        'summary' => $rotation['summary'] ?? '',
    ];

    return [
        'studentNumber' => (string) ($user['studentNumber'] ?? ''),
        'name' => (string) ($user['name'] ?? ''),
        'role' => $role,
        'roleLabel' => dent_role_label($role),
        'isOwner' => $role === 'owner',
        'isRepresentative' => $role === 'representative',
        'canModerateChat' => $permissions['moderateChat'],
        'permissions' => $permissions,
        'profile' => [
            'about' => (string) (($user['profile']['about'] ?? ($user['profile']['bio'] ?? '')) ?: ''),
            'bio' => (string) (($user['profile']['bio'] ?? ($user['profile']['about'] ?? '')) ?: ''),
            'contactHandle' => (string) (($user['profile']['contactHandle'] ?? '') ?: ''),
            'focusArea' => (string) (($user['profile']['focusArea'] ?? '') ?: ''),
            'avatarUrl' => (string) (($user['profile']['avatarUrl'] ?? '') ?: ''),
        ],
        'phone' => [
            'hasNumber' => (string) ($user['phoneNumber'] ?? '') !== '',
            'numberMasked' => dent_mask_phone_number((string) ($user['phoneNumber'] ?? '')),
            'verified' => (string) ($user['phoneVerifiedAt'] ?? '') !== '',
            'verifiedAt' => (string) ($user['phoneVerifiedAt'] ?? ''),
            'otpLoginEnabled' => (bool) ($user['phoneLoginEnabled'] ?? false),
            'canLoginWithOtp' => dent_user_phone_ready_for_otp($user),
            'nudgeDismissedAt' => (string) ($user['phoneNudgeDismissedAt'] ?? ''),
        ],
        'rotation' => $rotationPayload,
        'createdAt' => (string) ($user['createdAt'] ?? ''),
        'updatedAt' => (string) ($user['updatedAt'] ?? ''),
    ];
}

function dent_auth_status(array $user): string
{
    return 'logged-in';
}

function dent_current_user(): ?array
{
    $studentNumber = dent_normalize_student_number((string) ($_SESSION['student_number'] ?? ''));
    if ($studentNumber === '') {
        return null;
    }

    $user = dent_get_user_record($studentNumber);
    if ($user === null) {
        unset($_SESSION['student_number'], $_SESSION['auth_at']);
        return null;
    }

    return $user;
}

function dent_require_user(): array
{
    $user = dent_current_user();
    if ($user === null) {
        dent_error('نیاز به ورود به حساب کاربری است.', 401, ['loggedOut' => true]);
    }

    return $user;
}

function dent_require_owner(): array
{
    $user = dent_require_user();
    if (($user['role'] ?? 'student') !== 'owner') {
        dent_error('دسترسی این بخش فقط برای مالک سامانه مجاز است.', 403);
    }

    return $user;
}

function dent_can_moderate_chat(array $user): bool
{
    return (bool) dent_permissions_for_role((string) ($user['role'] ?? 'student'))['moderateChat'];
}

function dent_verify_credentials(string $studentNumber, string $password): ?array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    $password = dent_normalize_digits($password);

    $user = dent_get_user_record($studentNumber);
    if ($user === null) {
        return null;
    }

    if (!dent_verify_password($password, (string) $user['passwordHash'])) {
        return null;
    }

    if (str_starts_with((string) ($user['passwordHash'] ?? ''), 'pbkdf2_sha256$') &&
        dent_password_needs_rehash((string) $user['passwordHash'])) {
        $user['passwordHash'] = dent_hash_password($password);
        $user['updatedAt'] = dent_iso_now();
        dent_persist_user($user);
    }

    return $user;
}

function dent_persist_user(array $user): array
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        dent_error('شناسه کاربر نامعتبر است.', 422);
    }

    $store = dent_load_user_store();
    $existing = $store['users'][$studentNumber] ?? [];

    $merged = array_merge($existing, $user);
    $merged['studentNumber'] = $studentNumber;
    $merged['updatedAt'] = dent_iso_now();
    if (empty($merged['createdAt'])) {
        $merged['createdAt'] = dent_iso_now();
    }

    $store['users'][$studentNumber] = dent_normalize_user_record($studentNumber, $merged);
    dent_save_user_store($store);

    return $store['users'][$studentNumber];
}

function dent_login_user(array $user): array
{
    session_regenerate_id(true);
    $_SESSION['student_number'] = (string) ($user['studentNumber'] ?? '');
    $_SESSION['auth_at'] = time();

    return dent_public_user($user);
}

function dent_logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?: '/',
            $params['domain'] ?? '',
            (bool) ($params['secure'] ?? false),
            (bool) ($params['httponly'] ?? true)
        );
    }

    session_destroy();
}

function dent_update_profile(array $user, array $input): array
{
    $existingProfile = is_array($user['profile'] ?? null) ? $user['profile'] : [];
    $aboutInput = $input['about'] ?? ($input['bio'] ?? ($existingProfile['about'] ?? ($existingProfile['bio'] ?? '')));
    $about = dent_clean_text((string) $aboutInput, 280);

    $avatarSource = array_key_exists('avatarUrl', $input)
        ? (string) $input['avatarUrl']
        : (string) ($existingProfile['avatarUrl'] ?? '');

    $user['profile'] = [
        'about' => $about,
        'bio' => $about,
        'contactHandle' => dent_clean_text($input['contactHandle'] ?? ($existingProfile['contactHandle'] ?? ''), 80),
        'focusArea' => dent_clean_text($input['focusArea'] ?? ($existingProfile['focusArea'] ?? ''), 80),
        'avatarUrl' => dent_clean_avatar_url($avatarSource, array_key_exists('avatarUrl', $input)),
    ];

    return dent_persist_user($user);
}

function dent_change_user_password(array $user, string $currentPassword, string $newPassword): array
{
    $currentPassword = dent_normalize_digits($currentPassword);
    $newPassword = dent_normalize_digits($newPassword);

    if ($currentPassword === '' || $newPassword === '') {
        dent_error('رمز فعلی و رمز جدید را کامل وارد کن.', 422);
    }

    if (!dent_verify_password($currentPassword, (string) $user['passwordHash'])) {
        dent_error('رمز فعلی درست نیست.', 422);
    }

    if (dent_utf8_strlen($newPassword) < 6) {
        dent_error('رمز جدید باید حداقل ۶ کاراکتر باشد.', 422);
    }

    $user['passwordHash'] = dent_hash_password($newPassword);

    return dent_persist_user($user);
}

function dent_list_public_users(): array
{
    $store = dent_load_user_store();
    $users = [];

    foreach ($store['users'] as $studentNumber => $user) {
        $public = dent_public_user($user);
        $public['sortableName'] = trim((string) ($user['name'] ?? $studentNumber));
        $users[] = $public;
    }

    usort($users, static function (array $left, array $right): int {
        if (($left['role'] ?? '') === 'owner' && ($right['role'] ?? '') !== 'owner') {
            return -1;
        }

        if (($right['role'] ?? '') === 'owner' && ($left['role'] ?? '') !== 'owner') {
            return 1;
        }

        if (($left['role'] ?? '') === 'representative' && ($right['role'] ?? '') === 'student') {
            return -1;
        }

        if (($right['role'] ?? '') === 'representative' && ($left['role'] ?? '') === 'student') {
            return 1;
        }

        return strcasecmp((string) ($left['sortableName'] ?? ''), (string) ($right['sortableName'] ?? ''));
    });

    foreach ($users as &$user) {
        unset($user['sortableName']);
    }
    unset($user);

    return $users;
}

function dent_set_representative_status(string $studentNumber, bool $isRepresentative): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }

    $user = dent_get_user_record($studentNumber);
    if ($user === null) {
        dent_error('کاربر موردنظر پیدا نشد.', 404);
    }

    if ($studentNumber === dent_owner_student_number()) {
        dent_error('حساب مالک اصلی نمی‌تواند از نقش مالک خارج شود.', 422);
    }

    $user['role'] = $isRepresentative ? 'representative' : 'student';

    return dent_persist_user($user);
}

function dent_create_student_account(
    string $firstName,
    string $lastName,
    string $studentNumber,
    string $password
): array {
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }

    if (strlen($studentNumber) < 5 || strlen($studentNumber) > 20) {
        dent_error('شماره دانشجویی باید بین ۵ تا ۲۰ رقم باشد.', 422);
    }

    $firstName = dent_clean_text($firstName, 60);
    $lastName = dent_clean_text($lastName, 60);
    if ($firstName === '' || $lastName === '') {
        dent_error('نام و نام خانوادگی الزامی است.', 422);
    }

    $password = dent_normalize_digits($password);
    if (dent_utf8_strlen($password) < 6) {
        dent_error('رمز عبور باید حداقل ۶ کاراکتر باشد.', 422);
    }

    $store = dent_load_user_store();
    if (isset($store['users'][$studentNumber])) {
        dent_error('برای این شماره دانشجویی حسابی وجود دارد.', 409);
    }

    $name = trim($firstName . ' ' . $lastName);
    $now = dent_iso_now();
    $store['users'][$studentNumber] = dent_normalize_user_record($studentNumber, [
        'studentNumber' => $studentNumber,
        'name' => $name,
        'passwordHash' => dent_hash_password($password),
        'role' => 'student',
        'profile' => dent_default_profile(),
        'createdAt' => $now,
        'updatedAt' => $now,
    ]);

    dent_save_user_store($store);
    return $store['users'][$studentNumber];
}

function dent_owner_remove_user_phone(string $studentNumber): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }

    if ($studentNumber === dent_owner_student_number()) {
        dent_error('حذف شماره موبایل حساب مالک اصلی از این بخش مجاز نیست.', 422);
    }

    $user = dent_get_user_record($studentNumber);
    if ($user === null) {
        dent_error('کاربر موردنظر پیدا نشد.', 404);
    }

    return dent_remove_phone_number($user);
}

function dent_owner_delete_student_account(string $studentNumber): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }

    if ($studentNumber === dent_owner_student_number()) {
        dent_error('حساب مالک اصلی قابل حذف نیست.', 422);
    }

    $store = dent_load_user_store();
    $user = $store['users'][$studentNumber] ?? null;
    if (!is_array($user)) {
        dent_error('کاربر موردنظر پیدا نشد.', 404);
    }

    $phoneNumber = dent_normalize_phone_number((string) ($user['phoneNumber'] ?? ''));
    unset($store['users'][$studentNumber]);
    dent_save_user_store($store);

    dent_clear_phone_related_otp_records($studentNumber, $phoneNumber);

    return dent_normalize_user_record($studentNumber, $user);
}

function dent_auth_meta_path(): string
{
    return dent_storage_path('auth/meta.json');
}

function dent_auth_secret_key_path(): string
{
    return dent_storage_path('auth/.secret.key');
}

function dent_default_auth_meta_store(): array
{
    return [
        'schemaVersion' => 1,
        'sms' => [
            'enabled' => false,
            'apiKeyEncrypted' => null,
            'patternCode' => '',
            'senderLine' => '',
            'domain' => '',
            'codeParam' => 'code',
            'updatedAt' => '',
            'lastHealthAt' => '',
            'lastHealthStatus' => 'unknown',
            'lastHealthMessage' => '',
        ],
        'otp' => [
            'records' => [],
        ],
    ];
}

function dent_load_auth_meta_store(): array
{
    $defaults = dent_default_auth_meta_store();
    $raw = dent_read_json_file(dent_auth_meta_path(), $defaults);
    if (!is_array($raw)) {
        $raw = $defaults;
    }

    $store = $defaults;
    foreach (['schemaVersion', 'sms', 'otp'] as $key) {
        if (array_key_exists($key, $raw)) {
            $store[$key] = $raw[$key];
        }
    }

    if (!is_array($store['sms'])) {
        $store['sms'] = $defaults['sms'];
    } else {
        $store['sms'] = array_merge($defaults['sms'], $store['sms']);
    }
    if (!is_array($store['otp'])) {
        $store['otp'] = $defaults['otp'];
    } else {
        $store['otp'] = array_merge($defaults['otp'], $store['otp']);
    }
    if (!is_array($store['otp']['records'] ?? null)) {
        $store['otp']['records'] = [];
    }

    return $store;
}

function dent_save_auth_meta_store(array $store): void
{
    $defaults = dent_default_auth_meta_store();
    if (!is_array($store['sms'] ?? null)) {
        $store['sms'] = $defaults['sms'];
    } else {
        $store['sms'] = array_merge($defaults['sms'], $store['sms']);
    }
    if (!is_array($store['otp'] ?? null)) {
        $store['otp'] = $defaults['otp'];
    } else {
        $store['otp'] = array_merge($defaults['otp'], $store['otp']);
    }
    if (!is_array($store['otp']['records'] ?? null)) {
        $store['otp']['records'] = [];
    }

    dent_write_json_file(dent_auth_meta_path(), [
        'schemaVersion' => 1,
        'sms' => $store['sms'],
        'otp' => $store['otp'],
    ]);
}

function dent_auth_secret_key(): string
{
    static $cached = null;
    if (is_string($cached) && strlen($cached) === 32) {
        return $cached;
    }

    $env = trim((string) (getenv('DENT_AUTH_SECRET_KEY') ?: ''));
    if ($env !== '') {
        $decoded = base64_decode($env, true);
        if (is_string($decoded) && strlen($decoded) >= 32) {
            $cached = substr($decoded, 0, 32);
            return $cached;
        }
        $cached = hash('sha256', $env, true);
        return $cached;
    }

    $path = dent_auth_secret_key_path();
    if (is_file($path)) {
        $content = trim((string) file_get_contents($path));
        if ($content !== '') {
            $decoded = base64_decode($content, true);
            if (is_string($decoded) && strlen($decoded) === 32) {
                $cached = $decoded;
                return $cached;
            }
        }
    }

    $key = random_bytes(32);
    dent_ensure_directory(dirname($path));
    @file_put_contents($path, base64_encode($key), LOCK_EX);
    $cached = $key;
    return $cached;
}

function dent_encrypt_secret_text(string $plainText): array
{
    if (!function_exists('openssl_encrypt')) {
        dent_error('رمزنگاری سمت سرور در دسترس نیست.', 500);
    }

    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plainText, 'aes-256-gcm', dent_auth_secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if (!is_string($cipher) || $cipher === '' || $tag === '') {
        dent_error('رمزنگاری داده محرمانه انجام نشد.', 500);
    }

    return [
        'v' => 1,
        'alg' => 'aes-256-gcm',
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
        'ct' => base64_encode($cipher),
    ];
}

function dent_decrypt_secret_text($payload): string
{
    if (!is_array($payload) || !function_exists('openssl_decrypt')) {
        return '';
    }

    $iv = base64_decode((string) ($payload['iv'] ?? ''), true);
    $tag = base64_decode((string) ($payload['tag'] ?? ''), true);
    $ct = base64_decode((string) ($payload['ct'] ?? ''), true);
    if (!is_string($iv) || !is_string($tag) || !is_string($ct)) {
        return '';
    }

    $plain = openssl_decrypt($ct, 'aes-256-gcm', dent_auth_secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return is_string($plain) ? $plain : '';
}

function dent_normalize_phone_number(?string $value): string
{
    $digits = preg_replace('/\D+/u', '', dent_normalize_digits($value)) ?? '';
    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '0098')) {
        $digits = substr($digits, 4);
    } elseif (str_starts_with($digits, '98')) {
        $digits = substr($digits, 2);
    }

    if (str_starts_with($digits, '0')) {
        $digits = ltrim($digits, '0');
    }

    if (strlen($digits) !== 10 || !str_starts_with($digits, '9')) {
        return '';
    }

    return '+98' . $digits;
}

function dent_mask_phone_number(string $phoneNumber): string
{
    $normalized = dent_normalize_phone_number($phoneNumber);
    if ($normalized === '') {
        return '';
    }

    $head = substr($normalized, 0, 6);
    $tail = substr($normalized, -2);
    return $head . '***' . $tail;
}

function dent_user_phone_ready_for_otp(array $user): bool
{
    $phoneNumber = dent_normalize_phone_number((string) ($user['phoneNumber'] ?? ''));
    $verifiedAt = trim((string) ($user['phoneVerifiedAt'] ?? ''));
    $otpEnabled = (bool) ($user['phoneLoginEnabled'] ?? false);
    return $phoneNumber !== '' && $verifiedAt !== '' && $otpEnabled;
}

function dent_phone_number_in_use(string $phoneNumber, string $excludeStudentNumber = ''): bool
{
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        return false;
    }

    $excludeStudentNumber = dent_normalize_student_number($excludeStudentNumber);
    $store = dent_load_user_store();
    foreach ((array) ($store['users'] ?? []) as $studentNumber => $user) {
        if (!is_array($user)) {
            continue;
        }
        $normalizedStudentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $studentNumber));
        if ($excludeStudentNumber !== '' && $normalizedStudentNumber === $excludeStudentNumber) {
            continue;
        }
        if (dent_normalize_phone_number((string) ($user['phoneNumber'] ?? '')) === $normalizedPhone) {
            return true;
        }
    }

    return false;
}

function dent_find_user_by_phone(string $phoneNumber): ?array
{
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        return null;
    }

    $store = dent_load_user_store();
    foreach ((array) ($store['users'] ?? []) as $user) {
        if (!is_array($user)) {
            continue;
        }
        if (dent_normalize_phone_number((string) ($user['phoneNumber'] ?? '')) === $normalizedPhone) {
            return $user;
        }
    }

    return null;
}

function dent_sms_env_bool(string $name): ?bool
{
    $raw = getenv($name);
    if ($raw === false) {
        return null;
    }
    $parsed = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return is_bool($parsed) ? $parsed : null;
}

function dent_normalize_sms_sender_number(string $value): string
{
    $clean = trim(dent_normalize_digits($value));
    if ($clean === '') {
        return '';
    }
    $digits = preg_replace('/\D+/u', '', $clean) ?? '';
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '0098')) {
        $digits = substr($digits, 2);
    }

    return $digits;
}

function dent_sms_provider_recipient_number(string $value): string
{
    $clean = trim(dent_normalize_digits($value));
    if ($clean === '') {
        return '';
    }

    if (str_starts_with($clean, '+')) {
        $clean = substr($clean, 1);
    }

    $digits = preg_replace('/\D+/u', '', $clean) ?? '';
    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '0098')) {
        $digits = '0' . substr($digits, 4);
    } elseif (str_starts_with($digits, '98')) {
        $digits = '0' . substr($digits, 2);
    } elseif (str_starts_with($digits, '9')) {
        $digits = '0' . $digits;
    }

    if (strlen($digits) !== 11 || !str_starts_with($digits, '09')) {
        return '';
    }

    return $digits;
}

function dent_sms_log(string $event, array $context = []): void
{
    $safe = [
        'event' => $event,
        'at' => dent_iso_now(),
    ];
    foreach ($context as $key => $value) {
        if (in_array((string) $key, ['apiKey', 'authorization', 'otpCode', 'code'], true)) {
            continue;
        }
        $safe[(string) $key] = is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    error_log('[dent_sms] ' . json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function dent_sms_resolved_config(): array
{
    $meta = dent_load_auth_meta_store();
    $sms = is_array($meta['sms'] ?? null) ? $meta['sms'] : [];

    $storedApiKey = dent_decrypt_secret_text($sms['apiKeyEncrypted'] ?? null);
    $envApiKey = trim((string) (getenv('DENT_SMS_FARAZ_API_KEY') ?: ''));
    $apiKey = $storedApiKey !== '' ? $storedApiKey : $envApiKey;

    $patternCode = dent_clean_text((string) ($sms['patternCode'] ?? ''), 80);
    if ($patternCode === '') {
        $patternCode = dent_clean_text((string) (getenv('DENT_SMS_FARAZ_PATTERN_CODE') ?: ''), 80);
    }

    $senderLine = dent_clean_text((string) ($sms['senderLine'] ?? ''), 40);
    if ($senderLine === '') {
        $senderLine = dent_clean_text((string) (getenv('DENT_SMS_FARAZ_SENDER_LINE') ?: ''), 40);
    }
    $senderLine = dent_normalize_sms_sender_number($senderLine);

    $domain = dent_clean_text((string) ($sms['domain'] ?? ''), 120);
    if ($domain === '') {
        $domain = dent_clean_text((string) (getenv('DENT_SMS_FARAZ_DOMAIN') ?: ''), 120);
    }
    $domain = trim($domain, " \t\n\r\0\x0B/");

    $codeParam = dent_clean_text((string) ($sms['codeParam'] ?? ''), 40);
    if ($codeParam === '') {
        $codeParam = dent_clean_text((string) (getenv('DENT_SMS_FARAZ_CODE_PARAM') ?: 'code'), 40);
    }
    if ($codeParam === '') {
        $codeParam = 'code';
    }

    $enabled = (bool) ($sms['enabled'] ?? false);
    $envEnabled = dent_sms_env_bool('DENT_SMS_FARAZ_ENABLED');
    if ($envEnabled !== null) {
        $enabled = $envEnabled;
    } elseif ($storedApiKey === '' && $envApiKey !== '' && $patternCode !== '' && $senderLine !== '') {
        $enabled = true;
    }

    return [
        'enabled' => $enabled,
        'apiKey' => $apiKey,
        'patternCode' => $patternCode,
        'senderLine' => $senderLine,
        'domain' => $domain,
        'codeParam' => $codeParam,
        'lastHealthAt' => (string) ($sms['lastHealthAt'] ?? ''),
        'lastHealthStatus' => (string) ($sms['lastHealthStatus'] ?? 'unknown'),
        'lastHealthMessage' => (string) ($sms['lastHealthMessage'] ?? ''),
    ];
}

function dent_sms_health_store_update(bool $ok, string $message): void
{
    $meta = dent_load_auth_meta_store();
    if (!is_array($meta['sms'] ?? null)) {
        $meta['sms'] = dent_default_auth_meta_store()['sms'];
    }
    $meta['sms']['lastHealthAt'] = dent_iso_now();
    $meta['sms']['lastHealthStatus'] = $ok ? 'ok' : 'error';
    $meta['sms']['lastHealthMessage'] = dent_clean_text($message, 220);
    dent_save_auth_meta_store($meta);
}

function dent_sms_status_payload(): array
{
    $config = dent_sms_resolved_config();
    return [
        'enabled' => (bool) $config['enabled'],
        'provider' => 'farazsms',
        'apiKeyConfigured' => trim((string) $config['apiKey']) !== '',
        'patternConfigured' => trim((string) $config['patternCode']) !== '',
        'senderLineConfigured' => trim((string) $config['senderLine']) !== '',
        'senderLine' => (string) $config['senderLine'],
        'domainConfigured' => trim((string) $config['domain']) !== '',
        'domain' => (string) $config['domain'],
        'codeParam' => (string) $config['codeParam'],
        'lastHealthAt' => (string) $config['lastHealthAt'],
        'lastHealthStatus' => (string) $config['lastHealthStatus'],
        'lastHealthMessage' => (string) $config['lastHealthMessage'],
    ];
}

function dent_save_sms_owner_config(array $input): array
{
    $meta = dent_load_auth_meta_store();
    if (!is_array($meta['sms'] ?? null)) {
        $meta['sms'] = dent_default_auth_meta_store()['sms'];
    }

    $enabled = filter_var($input['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $meta['sms']['enabled'] = is_bool($enabled) ? $enabled : false;
    $meta['sms']['patternCode'] = dent_clean_text((string) ($input['patternCode'] ?? ''), 80);
    $meta['sms']['senderLine'] = dent_clean_text((string) ($input['senderLine'] ?? ''), 40);
    $meta['sms']['domain'] = dent_clean_text((string) ($input['domain'] ?? ''), 120);
    $meta['sms']['domain'] = trim((string) $meta['sms']['domain'], " \t\n\r\0\x0B/");
    $meta['sms']['codeParam'] = dent_clean_text((string) ($input['codeParam'] ?? 'code'), 40);
    if ($meta['sms']['codeParam'] === '') {
        $meta['sms']['codeParam'] = 'code';
    }

    $apiKey = trim((string) ($input['apiKey'] ?? ''));
    $clearApiKey = filter_var($input['clearApiKey'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    if ($clearApiKey) {
        $meta['sms']['apiKeyEncrypted'] = null;
    } elseif ($apiKey !== '') {
        $meta['sms']['apiKeyEncrypted'] = dent_encrypt_secret_text($apiKey);
    }

    $meta['sms']['updatedAt'] = dent_iso_now();
    dent_save_auth_meta_store($meta);
    return dent_sms_status_payload();
}

function dent_sms_send_pattern(string $phoneNumber, string $otpCode): array
{
    $config = dent_sms_resolved_config();
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    $providerPhone = dent_sms_provider_recipient_number($normalizedPhone);

    if (!(bool) $config['enabled']) {
        dent_sms_log('send_blocked', ['reason' => 'disabled', 'phone' => dent_mask_phone_number($normalizedPhone)]);
        return ['success' => false, 'message' => 'سرویس پیامکی غیرفعال است.'];
    }
    if (trim((string) $config['apiKey']) === '') {
        dent_sms_log('send_blocked', ['reason' => 'missing_api_key', 'phone' => dent_mask_phone_number($normalizedPhone)]);
        return ['success' => false, 'message' => 'کلید API سرویس پیامکی تنظیم نشده است.'];
    }
    if (trim((string) $config['patternCode']) === '') {
        dent_sms_log('send_blocked', ['reason' => 'missing_pattern', 'phone' => dent_mask_phone_number($normalizedPhone)]);
        return ['success' => false, 'message' => 'کد پترن پیامکی تنظیم نشده است.'];
    }
    if (trim((string) $config['senderLine']) === '') {
        dent_sms_log('send_blocked', ['reason' => 'missing_sender', 'phone' => dent_mask_phone_number($normalizedPhone)]);
        return ['success' => false, 'message' => 'لاین/شماره ارسال پیامک تنظیم نشده است.'];
    }

    if ($normalizedPhone === '' || $providerPhone === '') {
        return ['success' => false, 'message' => 'شماره موبایل مقصد نامعتبر است.'];
    }

    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'cURL روی سرور فعال نیست.'];
    }

    $params = [
        (string) $config['codeParam'] => $otpCode,
    ];
    if (trim((string) $config['domain']) !== '') {
        $params['domain'] = (string) $config['domain'];
    }

    $payload = [
        'code' => (string) $config['patternCode'],
        'recipient' => $providerPhone,
        'line_number' => (string) $config['senderLine'],
        'number_format' => 'english',
        'attributes' => $params,
    ];

    $attempts = 3;
    $raw = '';
    $httpCode = 0;
    $curlError = '';
    $decoded = null;

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        dent_sms_log('send_attempt', [
            'attempt' => $attempt,
            'phone' => dent_mask_phone_number($normalizedPhone),
            'patternConfigured' => true,
            'sender' => (string) $config['senderLine'],
            'params' => implode(',', array_keys($params)),
        ]);

        $ch = curl_init('https://api.iranpayamak.com/ws/v1/sms/pattern');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Api-Key: ' . (string) $config['apiKey'],
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $raw = is_string($response) ? $response : '';
        $decoded = $raw !== '' ? json_decode($raw, true) : null;

        $temporaryFailure = $curlError !== '' || $httpCode >= 500;
        if (!$temporaryFailure) {
            break;
        }
        if ($attempt < $attempts) {
            usleep(250000 * $attempt);
            continue;
        }
    }

    if ($raw === '') {
        dent_sms_log('send_failed', [
            'reason' => 'empty_response',
            'phone' => dent_mask_phone_number($normalizedPhone),
            'httpStatus' => $httpCode,
            'curlError' => dent_clean_text($curlError, 160),
        ]);
        return [
            'success' => false,
            'message' => $curlError !== '' ? ('خطای ارتباط با سرویس پیامک: ' . $curlError) : 'پاسخی از سرویس پیامکی دریافت نشد.',
            'httpStatus' => $httpCode,
        ];
    }

    if (!is_array($decoded)) {
        dent_sms_log('send_failed', [
            'reason' => 'invalid_json',
            'phone' => dent_mask_phone_number($normalizedPhone),
            'httpStatus' => $httpCode,
        ]);
        if ($httpCode >= 500) {
            return ['success' => false, 'message' => 'سرویس پیامکی موقتاً در دسترس نیست.', 'httpStatus' => $httpCode];
        }
        $brief = dent_clean_text(trim(strip_tags($raw)), 120);
        $detail = $brief !== '' ? (' جزئیات: ' . $brief) : '';
        return ['success' => false, 'message' => 'پاسخ سرویس پیامکی نامعتبر است.' . $detail, 'httpStatus' => $httpCode];
    }

    $statusRaw = strtolower(trim((string) ($decoded['status'] ?? '')));
    $ok = $statusRaw === 'success';
    $messageCode = dent_clean_text((string) ($decoded['code'] ?? ''), 40);
    $message = '';
    $rawMessage = $decoded['message'] ?? '';
    if (is_string($rawMessage)) {
        $message = dent_clean_text($rawMessage, 220);
    } elseif (is_array($rawMessage)) {
        foreach ($rawMessage as $messageItem) {
            if (is_string($messageItem)) {
                $message = dent_clean_text($messageItem, 220);
                if ($message !== '') {
                    break;
                }
                continue;
            }
            if (!is_array($messageItem)) {
                continue;
            }
            foreach ($messageItem as $nestedMessage) {
                if (!is_string($nestedMessage)) {
                    continue;
                }
                $message = dent_clean_text($nestedMessage, 220);
                if ($message !== '') {
                    break 2;
                }
            }
        }
    }
    if ($message === '') {
        $message = $ok ? 'ارسال انجام شد.' : 'ارسال پیامک انجام نشد.';
    }
    if (!$ok && $httpCode >= 500) {
        $message = 'سرویس پیامکی موقتاً در دسترس نیست.';
    }

    dent_sms_log($ok ? 'send_ok' : 'send_failed', [
        'phone' => dent_mask_phone_number($normalizedPhone),
        'httpStatus' => $httpCode,
        'messageCode' => $messageCode,
        'providerMessage' => $message,
    ]);

    return [
        'success' => $ok,
        'message' => $message,
        'httpStatus' => $httpCode,
        'messageCode' => $messageCode,
    ];
}

function dent_otp_ttl_seconds(): int
{
    return 180;
}

function dent_otp_cooldown_seconds(): int
{
    return 60;
}

function dent_otp_max_attempts(): int
{
    return 5;
}

function dent_otp_window_seconds(): int
{
    return 3600;
}

function dent_otp_max_send_per_window(): int
{
    return 6;
}

function dent_otp_record_key(string $purpose, string $phoneNumber): string
{
    return hash('sha256', $purpose . '|' . dent_normalize_phone_number($phoneNumber));
}

function dent_otp_cleanup_records(array &$metaStore): bool
{
    $changed = false;
    $now = time();
    $records = is_array($metaStore['otp']['records'] ?? null) ? $metaStore['otp']['records'] : [];
    foreach ($records as $key => $record) {
        if (!is_array($record)) {
            unset($records[$key]);
            $changed = true;
            continue;
        }
        $expiresAt = (int) ($record['expiresAt'] ?? 0);
        $consumedAt = (int) ($record['consumedAt'] ?? 0);
        $issuedAt = (int) ($record['issuedAt'] ?? 0);
        if ($expiresAt > 0 && $expiresAt + 86400 < $now) {
            unset($records[$key]);
            $changed = true;
            continue;
        }
        if ($consumedAt > 0 && $consumedAt + 86400 < $now) {
            unset($records[$key]);
            $changed = true;
            continue;
        }
        if ($issuedAt > 0 && $issuedAt + (2 * 86400) < $now) {
            unset($records[$key]);
            $changed = true;
            continue;
        }
    }

    if ($changed) {
        $metaStore['otp']['records'] = $records;
    }
    return $changed;
}

function dent_issue_otp_for_phone(string $purpose, string $phoneNumber, string $studentNumber = ''): array
{
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        return ['success' => false, 'error' => 'شماره موبایل نامعتبر است.', 'statusCode' => 422];
    }

    $meta = dent_load_auth_meta_store();
    $metaChanged = dent_otp_cleanup_records($meta);
    $records = is_array($meta['otp']['records'] ?? null) ? $meta['otp']['records'] : [];
    $now = time();
    $key = dent_otp_record_key($purpose, $normalizedPhone);
    $record = is_array($records[$key] ?? null) ? $records[$key] : [];

    $cooldownUntil = (int) ($record['cooldownUntil'] ?? 0);
    if ($cooldownUntil > $now) {
        if ($metaChanged) {
            dent_save_auth_meta_store($meta);
        }
        return [
            'success' => false,
            'error' => 'برای دریافت مجدد کد کمی صبر کن.',
            'statusCode' => 429,
            'cooldownSeconds' => $cooldownUntil - $now,
        ];
    }

    $windowStart = (int) ($record['sendWindowStart'] ?? 0);
    $sendCount = (int) ($record['sendCount'] ?? 0);
    if ($windowStart <= 0 || ($now - $windowStart) > dent_otp_window_seconds()) {
        $windowStart = $now;
        $sendCount = 0;
    }
    if ($sendCount >= dent_otp_max_send_per_window()) {
        if ($metaChanged) {
            dent_save_auth_meta_store($meta);
        }
        return [
            'success' => false,
            'error' => 'تعداد درخواست‌های کد تایید بیش از حد مجاز است. بعداً دوباره تلاش کن.',
            'statusCode' => 429,
        ];
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $salt = dent_base64url_encode(random_bytes(12));
    $codeHash = hash_hmac('sha256', $code, dent_auth_secret_key() . '|' . $salt);

    $records[$key] = array_merge($record, [
        'purpose' => $purpose,
        'phoneNumber' => $normalizedPhone,
        'studentNumber' => dent_normalize_student_number($studentNumber),
        'issuedAt' => $now,
        'cooldownUntil' => $now + dent_otp_cooldown_seconds(),
        'sendWindowStart' => $windowStart,
        'sendCount' => $sendCount + 1,
        'lastSendFailedAt' => 0,
    ]);
    $meta['otp']['records'] = $records;
    dent_save_auth_meta_store($meta);

    $sendResult = dent_sms_send_pattern($normalizedPhone, $code);
    dent_sms_health_store_update((bool) ($sendResult['success'] ?? false), (string) ($sendResult['message'] ?? ''));
    if (!(bool) ($sendResult['success'] ?? false)) {
        $records[$key]['lastSendFailedAt'] = time();
        $records[$key]['lastSendFailureMessage'] = dent_clean_text((string) ($sendResult['message'] ?? ''), 220);
        $records[$key]['codeHash'] = '';
        $records[$key]['salt'] = '';
        $records[$key]['expiresAt'] = 0;
        $records[$key]['attempts'] = 0;
        $records[$key]['maxAttempts'] = dent_otp_max_attempts();
        $records[$key]['consumedAt'] = 0;
        $meta['otp']['records'] = $records;
        dent_save_auth_meta_store($meta);
        return [
            'success' => false,
            'error' => (string) ($sendResult['message'] ?? 'ارسال کد تایید انجام نشد.'),
            'statusCode' => 502,
            'cooldownSeconds' => dent_otp_cooldown_seconds(),
        ];
    }

    $records[$key] = [
        'purpose' => $purpose,
        'phoneNumber' => $normalizedPhone,
        'studentNumber' => dent_normalize_student_number($studentNumber),
        'codeHash' => $codeHash,
        'salt' => $salt,
        'issuedAt' => $now,
        'expiresAt' => $now + dent_otp_ttl_seconds(),
        'cooldownUntil' => $now + dent_otp_cooldown_seconds(),
        'attempts' => 0,
        'maxAttempts' => dent_otp_max_attempts(),
        'consumedAt' => 0,
        'sendWindowStart' => $windowStart,
        'sendCount' => $sendCount + 1,
    ];
    $meta['otp']['records'] = $records;
    dent_save_auth_meta_store($meta);

    return [
        'success' => true,
        'cooldownSeconds' => dent_otp_cooldown_seconds(),
        'expiresInSeconds' => dent_otp_ttl_seconds(),
        'phoneMasked' => dent_mask_phone_number($normalizedPhone),
    ];
}

function dent_verify_otp_for_phone(string $purpose, string $phoneNumber, string $code, string $studentNumber = ''): array
{
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        return ['success' => false, 'error' => 'شماره موبایل نامعتبر است.', 'statusCode' => 422];
    }

    $normalizedCode = preg_replace('/\D+/u', '', dent_normalize_digits($code)) ?? '';
    if ($normalizedCode === '') {
        return ['success' => false, 'error' => 'کد تایید نامعتبر است.', 'statusCode' => 422];
    }

    $meta = dent_load_auth_meta_store();
    dent_otp_cleanup_records($meta);
    $records = is_array($meta['otp']['records'] ?? null) ? $meta['otp']['records'] : [];
    $key = dent_otp_record_key($purpose, $normalizedPhone);
    $record = is_array($records[$key] ?? null) ? $records[$key] : null;
    if ($record === null) {
        dent_save_auth_meta_store($meta);
        return ['success' => false, 'error' => 'درخواست کد تایید پیدا نشد یا منقضی شده است.', 'statusCode' => 404];
    }

    $now = time();
    $expiresAt = (int) ($record['expiresAt'] ?? 0);
    if ($expiresAt <= $now) {
        unset($records[$key]);
        $meta['otp']['records'] = $records;
        dent_save_auth_meta_store($meta);
        return ['success' => false, 'error' => 'کد تایید منقضی شده است.', 'statusCode' => 422];
    }

    $consumedAt = (int) ($record['consumedAt'] ?? 0);
    if ($consumedAt > 0) {
        return ['success' => false, 'error' => 'این کد قبلاً استفاده شده است.', 'statusCode' => 409];
    }

    $expectedStudent = dent_normalize_student_number((string) ($record['studentNumber'] ?? ''));
    $normalizedStudent = dent_normalize_student_number($studentNumber);
    if ($expectedStudent !== '' && $normalizedStudent !== '' && $expectedStudent !== $normalizedStudent) {
        return ['success' => false, 'error' => 'این کد برای کاربر دیگری صادر شده است.', 'statusCode' => 403];
    }

    $attempts = max(0, (int) ($record['attempts'] ?? 0));
    $maxAttempts = max(1, (int) ($record['maxAttempts'] ?? dent_otp_max_attempts()));
    $salt = (string) ($record['salt'] ?? '');
    $expectedHash = (string) ($record['codeHash'] ?? '');
    if ($expectedHash === '' || $salt === '') {
        return ['success' => false, 'error' => 'کد فعالی برای این شماره ثبت نشده است. دوباره درخواست ارسال بده.', 'statusCode' => 404];
    }
    $candidateHash = hash_hmac('sha256', $normalizedCode, dent_auth_secret_key() . '|' . $salt);
    if (!hash_equals($expectedHash, $candidateHash)) {
        $attempts++;
        if ($attempts >= $maxAttempts) {
            unset($records[$key]);
        } else {
            $record['attempts'] = $attempts;
            $records[$key] = $record;
        }
        $meta['otp']['records'] = $records;
        dent_save_auth_meta_store($meta);
        return [
            'success' => false,
            'error' => 'کد تایید صحیح نیست.',
            'statusCode' => 422,
            'remainingAttempts' => max(0, $maxAttempts - $attempts),
        ];
    }

    $record['consumedAt'] = $now;
    $records[$key] = $record;
    $meta['otp']['records'] = $records;
    dent_save_auth_meta_store($meta);

    return ['success' => true];
}

function dent_request_phone_enrollment_otp(array $user, string $phoneNumber): array
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        dent_error('شناسه کاربر نامعتبر است.', 422);
    }

    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        dent_error('شماره موبایل نامعتبر است.', 422);
    }

    if (dent_phone_number_in_use($normalizedPhone, $studentNumber)) {
        dent_error('این شماره موبایل قبلاً روی حساب دیگری ثبت شده است.', 409);
    }

    $result = dent_issue_otp_for_phone('enroll:' . $studentNumber, $normalizedPhone, $studentNumber);
    if (!(bool) ($result['success'] ?? false)) {
        dent_error((string) ($result['error'] ?? 'ارسال کد تایید انجام نشد.'), (int) ($result['statusCode'] ?? 422), $result);
    }

    return $result;
}

function dent_verify_phone_enrollment_otp(array $user, string $phoneNumber, string $otpCode): array
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($studentNumber === '' || $normalizedPhone === '') {
        dent_error('اطلاعات تایید شماره موبایل نامعتبر است.', 422);
    }
    if (dent_phone_number_in_use($normalizedPhone, $studentNumber)) {
        dent_error('این شماره موبایل قبلاً روی حساب دیگری ثبت شده است.', 409);
    }

    $verify = dent_verify_otp_for_phone('enroll:' . $studentNumber, $normalizedPhone, $otpCode, $studentNumber);
    if (!(bool) ($verify['success'] ?? false)) {
        dent_error((string) ($verify['error'] ?? 'تایید شماره موبایل انجام نشد.'), (int) ($verify['statusCode'] ?? 422), $verify);
    }

    $user['phoneNumber'] = $normalizedPhone;
    $user['phoneVerifiedAt'] = dent_iso_now();
    $user['phoneLoginEnabled'] = true;
    $user['phoneNudgeDismissedAt'] = '';
    return dent_persist_user($user);
}

function dent_dismiss_phone_nudge(array $user): array
{
    if (dent_normalize_phone_number((string) ($user['phoneNumber'] ?? '')) !== '') {
        return $user;
    }
    $user['phoneNudgeDismissedAt'] = dent_iso_now();
    return dent_persist_user($user);
}

function dent_set_phone_login_enabled(array $user, bool $enabled): array
{
    if (dent_normalize_phone_number((string) ($user['phoneNumber'] ?? '')) === '') {
        dent_error('برای فعال‌سازی ورود پیامکی، ابتدا شماره موبایل را ثبت و تایید کنید.', 422);
    }
    if (trim((string) ($user['phoneVerifiedAt'] ?? '')) === '') {
        dent_error('شماره موبایل هنوز تایید نشده است.', 422);
    }
    $user['phoneLoginEnabled'] = $enabled;
    return dent_persist_user($user);
}

function dent_remove_phone_number(array $user): array
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        dent_error('شناسه کاربر نامعتبر است.', 422);
    }

    $currentPhone = dent_normalize_phone_number((string) ($user['phoneNumber'] ?? ''));
    if ($currentPhone === '') {
        $user['phoneNumber'] = '';
        $user['phoneVerifiedAt'] = '';
        $user['phoneLoginEnabled'] = false;
        return dent_persist_user($user);
    }

    $user['phoneNumber'] = '';
    $user['phoneVerifiedAt'] = '';
    $user['phoneLoginEnabled'] = false;
    $user['phoneNudgeDismissedAt'] = dent_iso_now();
    $updated = dent_persist_user($user);

    dent_clear_phone_related_otp_records($studentNumber, $currentPhone);
    return $updated;
}

function dent_request_login_otp(string $phoneNumber): array
{
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        dent_error('شماره موبایل نامعتبر است.', 422);
    }

    $user = dent_find_user_by_phone($normalizedPhone);
    if ($user === null || !dent_user_phone_ready_for_otp($user)) {
        dent_error('برای این شماره، ورود با کد تایید فعال نیست.', 403);
    }

    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $result = dent_issue_otp_for_phone('login', $normalizedPhone, $studentNumber);
    if (!(bool) ($result['success'] ?? false)) {
        dent_error((string) ($result['error'] ?? 'ارسال کد تایید انجام نشد.'), (int) ($result['statusCode'] ?? 422), $result);
    }

    return array_merge($result, [
        'phoneMasked' => dent_mask_phone_number($normalizedPhone),
    ]);
}

function dent_verify_login_otp(string $phoneNumber, string $otpCode): array
{
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        dent_error('شماره موبایل نامعتبر است.', 422);
    }

    $user = dent_find_user_by_phone($normalizedPhone);
    if ($user === null || !dent_user_phone_ready_for_otp($user)) {
        dent_error('برای این شماره، ورود با کد تایید فعال نیست.', 403);
    }

    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $verify = dent_verify_otp_for_phone('login', $normalizedPhone, $otpCode, $studentNumber);
    if (!(bool) ($verify['success'] ?? false)) {
        dent_error((string) ($verify['error'] ?? 'تایید کد انجام نشد.'), (int) ($verify['statusCode'] ?? 422), $verify);
    }

    return dent_login_user($user);
}

function dent_clear_phone_related_otp_records(string $studentNumber, string $phoneNumber): void
{
    $normalizedStudentNumber = dent_normalize_student_number($studentNumber);
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedStudentNumber === '' || $normalizedPhone === '') {
        return;
    }

    $meta = dent_load_auth_meta_store();
    if (!is_array($meta['otp'] ?? null)) {
        return;
    }
    $records = is_array($meta['otp']['records'] ?? null) ? $meta['otp']['records'] : [];
    if (!$records) {
        return;
    }

    $keys = [
        dent_otp_record_key('login', $normalizedPhone),
        dent_otp_record_key('enroll:' . $normalizedStudentNumber, $normalizedPhone),
    ];
    $changed = false;
    foreach ($keys as $key) {
        if (!isset($records[$key])) {
            continue;
        }
        unset($records[$key]);
        $changed = true;
    }

    if (!$changed) {
        return;
    }

    $meta['otp']['records'] = $records;
    dent_save_auth_meta_store($meta);
}

function dent_sms_health_check(?string $phoneNumber = null): array
{
    $status = dent_sms_status_payload();
    if ($phoneNumber === null || trim($phoneNumber) === '') {
        return [
            'success' => $status['enabled'] && $status['apiKeyConfigured'] && $status['patternConfigured'],
            'message' => ($status['enabled'] && $status['apiKeyConfigured'] && $status['patternConfigured'])
                ? 'تنظیمات پیامکی کامل است.'
                : 'تنظیمات پیامکی کامل نیست.',
            'status' => $status,
        ];
    }

    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        dent_error('شماره موبایل تست نامعتبر است.', 422);
    }

    $testCode = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $sendResult = dent_sms_send_pattern($normalizedPhone, $testCode);
    dent_sms_health_store_update((bool) ($sendResult['success'] ?? false), (string) ($sendResult['message'] ?? ''));
    return [
        'success' => (bool) ($sendResult['success'] ?? false),
        'message' => (string) ($sendResult['message'] ?? ''),
        'status' => dent_sms_status_payload(),
    ];
}
