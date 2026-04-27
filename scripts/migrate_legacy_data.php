<?php
declare(strict_types=1);

function project_root(): string
{
    return dirname(__DIR__);
}

function source_path(string $relativePath): string
{
    return project_root() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
}

function env_path_value(string $name): string
{
    $value = getenv($name);
    if (!is_string($value)) {
        return '';
    }

    return trim($value);
}

function is_absolute_path(string $path): bool
{
    if ($path === '') {
        return false;
    }

    if (DIRECTORY_SEPARATOR === '\\') {
        return preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1 || str_starts_with($path, '\\\\');
    }

    return str_starts_with($path, '/');
}

function resolve_path(string $path, string $basePath): string
{
    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($path));
    if ($normalized === '') {
        return rtrim($basePath, '\\/');
    }

    if (is_absolute_path($normalized)) {
        return rtrim($normalized, '\\/');
    }

    return rtrim($basePath, '\\/') . DIRECTORY_SEPARATOR . trim($normalized, '\\/');
}

function default_server_only_root(): string
{
    $configured = env_path_value('DENT_SERVER_ONLY_ROOT');
    if ($configured !== '') {
        return resolve_path($configured, project_root());
    }

    return source_path('server-only');
}

function resolve_storage_root(): string
{
    $configuredStorage = env_path_value('DENT_STORAGE_ROOT');
    if ($configuredStorage !== '') {
        return resolve_path($configuredStorage, project_root());
    }

    $serverOnlyStorage = default_server_only_root() . DIRECTORY_SEPARATOR . 'storage';
    $legacyStorage = source_path('storage');

    if (is_dir($serverOnlyStorage) || !is_dir($legacyStorage)) {
        return $serverOnlyStorage;
    }

    return $legacyStorage;
}

function normalize_digits(string $value): string
{
    return str_replace(
        ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
        trim($value)
    );
}

function normalize_student_number(string $value): string
{
    return preg_replace('/\D+/u', '', normalize_digits($value)) ?? '';
}

function ensure_directory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create directory: ' . $directory);
    }
}

function base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function hash_password_value(string $password): string
{
    $iterations = 210000;
    $salt = random_bytes(16);
    $derived = hash_pbkdf2('sha256', normalize_digits($password), $salt, $iterations, 32, true);

    return 'pbkdf2_sha256$' . $iterations . '$' . base64url_encode($salt) . '$' . base64url_encode($derived);
}

function read_csv_rows(string $path): array
{
    if (!file_exists($path)) {
        throw new RuntimeException('Missing CSV source: ' . $path);
    }

    $handle = fopen($path, 'r');
    if ($handle === false) {
        throw new RuntimeException('Could not read CSV source: ' . $path);
    }

    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        throw new RuntimeException('Invalid CSV header in: ' . $path);
    }

    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = $row;
    }
    fclose($handle);

    return [$header, $rows];
}

$chatUsersPath = source_path('public_html/chat/users.csv');
$gradesPath = source_path('public_html/grades/grades.csv');
$chatMessagesPath = source_path('public_html/chat/data/messages.json');
$chatStatePath = source_path('public_html/chat/data/state.json');

[$chatHeader, $chatRows] = read_csv_rows($chatUsersPath);
[$gradesHeader, $gradesRows] = read_csv_rows($gradesPath);

$chatMap = [];
$chatUserIndex = [
    'username' => array_search('username', $chatHeader, true),
    'password' => array_search('password', $chatHeader, true),
    'name' => array_search('name', $chatHeader, true),
];

foreach ($chatUserIndex as $column => $index) {
    if ($index === false) {
        throw new RuntimeException('chat/users.csv is missing required column: ' . $column);
    }
}

foreach ($chatRows as $row) {
    $studentNumber = normalize_student_number((string) ($row[$chatUserIndex['username']] ?? ''));
    if ($studentNumber === '') {
        continue;
    }

    $chatMap[$studentNumber] = [
        'studentNumber' => $studentNumber,
        'password' => normalize_digits((string) ($row[$chatUserIndex['password']] ?? '')),
        'name' => trim((string) ($row[$chatUserIndex['name']] ?? $studentNumber)),
    ];
}

$gradeIndex = [
    'studentNumber' => array_search('StudentID', $gradesHeader, true),
    'password' => array_search('Password', $gradesHeader, true),
    'name' => array_search('Name', $gradesHeader, true),
];

foreach ($gradeIndex as $column => $index) {
    if ($index === false) {
        throw new RuntimeException('grades/grades.csv is missing required column: ' . $column);
    }
}

$mergedUsers = [];
foreach ($chatMap as $studentNumber => $chatUser) {
    $role = $studentNumber === '40211272003' ? 'owner' : ($studentNumber === '40211272021' ? 'representative' : 'student');
    $mergedUsers[$studentNumber] = [
        'studentNumber' => $studentNumber,
        'name' => $chatUser['name'],
        'passwordHash' => hash_password_value($chatUser['password']),
        'role' => $role,
        'profile' => [
            'bio' => '',
            'contactHandle' => '',
            'focusArea' => '',
        ],
        'createdAt' => date('c'),
        'updatedAt' => date('c'),
    ];
}

ksort($mergedUsers, SORT_STRING);

$storageRoot = resolve_storage_root();
$authDirectory = $storageRoot . DIRECTORY_SEPARATOR . 'auth';
$gradesDirectory = $storageRoot . DIRECTORY_SEPARATOR . 'grades';
$chatDirectory = $storageRoot . DIRECTORY_SEPARATOR . 'chat';

ensure_directory($authDirectory);
ensure_directory($gradesDirectory);
ensure_directory($chatDirectory);

$usersJsonPath = $authDirectory . DIRECTORY_SEPARATOR . 'users.json';
file_put_contents($usersJsonPath, json_encode([
    'schemaVersion' => 1,
    'ownerStudentNumber' => '40211272003',
    'users' => $mergedUsers,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);

$passwordColumnIndex = $gradeIndex['password'];
$strippedHeader = [];
foreach ($gradesHeader as $index => $label) {
    if ($index === $passwordColumnIndex) {
        continue;
    }
    $strippedHeader[] = $label;
}

$gradesOutputPath = $gradesDirectory . DIRECTORY_SEPARATOR . 'grades.csv';
$gradesHandle = fopen($gradesOutputPath, 'w');
if ($gradesHandle === false) {
    throw new RuntimeException('Could not open grades storage file for writing.');
}

fputcsv($gradesHandle, $strippedHeader);
foreach ($gradesRows as $row) {
    $studentNumber = normalize_student_number((string) ($row[$gradeIndex['studentNumber']] ?? ''));
    if ($studentNumber === '') {
        continue;
    }

    $strippedRow = [];
    foreach ($row as $index => $value) {
        if ($index === $passwordColumnIndex) {
            continue;
        }
        $strippedRow[] = $value;
    }

    fputcsv($gradesHandle, $strippedRow);
}
fclose($gradesHandle);

if (file_exists($chatMessagesPath)) {
    copy($chatMessagesPath, $chatDirectory . DIRECTORY_SEPARATOR . 'messages.json');
}

if (file_exists($chatStatePath)) {
    copy($chatStatePath, $chatDirectory . DIRECTORY_SEPARATOR . 'state.json');
}

echo 'Migrated ' . count($mergedUsers) . " users into $usersJsonPath\n";
echo "Wrote grades CSV without password column to $gradesOutputPath\n";
echo "Copied chat state into $chatDirectory\n";
