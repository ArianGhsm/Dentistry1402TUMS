<?php
declare(strict_types=1);

require_once __DIR__ . '/../public_html/api/private_notes_store.php';

// Creates and normalizes the private-notes JSON store. This is the repository's
// migration equivalent because the project uses file-backed storage, not SQL.

$checkOnly = in_array('--check', $argv, true);
$beforeExists = is_file(private_notes_store_path());

private_notes_ensure_storage();
$store = private_notes_read_store();

$summary = [
    'path' => private_notes_store_path(),
    'schemaVersion' => (int) ($store['schemaVersion'] ?? 0),
    'semesters' => count($store['semesters'] ?? []),
    'courses' => count($store['courses'] ?? []),
    'documents' => count($store['documents'] ?? []),
    'courseMemberships' => count($store['courseMemberships'] ?? []),
    'documentPermissions' => count($store['documentPermissions'] ?? []),
    'processingJobs' => count($store['processingJobs'] ?? []),
    'registeredDevices' => count($store['registeredDevices'] ?? []),
    'activeViewingSessions' => count($store['activeViewingSessions'] ?? []),
    'documentViewEvents' => count($store['documentViewEvents'] ?? []),
    'securityEvents' => count($store['securityEvents'] ?? []),
    'temporarySuspensions' => count($store['temporarySuspensions'] ?? []),
];

if (!$checkOnly) {
    private_notes_with_store_lock(static function (array &$lockedStore): array {
        $lockedStore = private_notes_normalize_store($lockedStore);
        return $lockedStore;
    });
}

echo ($checkOnly ? 'CHECK' : 'MIGRATED') . ': private notes store' . PHP_EOL;
foreach ($summary as $key => $value) {
    echo $key . ': ' . (string) $value . PHP_EOL;
}
echo 'createdNow: ' . ($beforeExists ? 'no' : 'yes') . PHP_EOL;
