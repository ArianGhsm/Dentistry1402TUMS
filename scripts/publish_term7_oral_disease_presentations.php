<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../public_html/api/bot_store.php';
restore_error_handler();
restore_exception_handler();

$options = getopt('', ['commit']);
$commit = array_key_exists('commit', $options);
$state = dent_term7_oral_disease_presentation_state_read();
$userStore = dent_load_user_store();
$users = is_array($userStore['users'] ?? null) ? $userStore['users'] : [];

$report = [
    'sourceVersion' => DENT_TERM7_ORAL_DISEASE_PRESENTATION_VERSION,
    'eligible' => 0,
    'created' => 0,
    'existing' => 0,
    'skipped' => 0,
    'commit' => $commit,
];

foreach (array_keys(is_array($state['presentations'] ?? null) ? $state['presentations'] : []) as $studentNumber) {
    $user = is_array($users[$studentNumber] ?? null) ? $users[$studentNumber] : null;
    if (!is_array($user) || dent_user_cohort_key($user) !== DENT_TERM7_COHORT) {
        $report['skipped']++;
        continue;
    }
    $candidate = dent_term7_oral_disease_presentation_notification_candidate((string) $studentNumber);
    if (!is_array($candidate)) {
        $report['skipped']++;
        continue;
    }
    $report['eligible']++;
    if (!$commit) {
        continue;
    }
    $result = notifications_ensure_user_candidate($user, $candidate);
    if (!empty($result['created'])) {
        $report['created']++;
    } elseif (is_array($result['record'] ?? null)) {
        $report['existing']++;
    } else {
        $report['skipped']++;
    }
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($report['skipped'] === 0 ? 0 : 1);
