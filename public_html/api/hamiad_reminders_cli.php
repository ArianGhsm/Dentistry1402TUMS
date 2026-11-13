<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/hamiad_reminders.php';
require_once __DIR__ . '/hamiad_mobile_login.php';

$dryRun = in_array('--dry-run', $argv, true);
$clock = time();
$out = [];

// 1) Keep the Moodle session alive (every tick). Never fatal.
try {
    $out['keepalive'] = hamiad_keepalive_touch()['state'];
} catch (Throwable $error) {
    $out['keepalive'] = 'error';
}

// 1b) Expired or missing: restore it without a CAPTCHA via the mobile token.
if (in_array($out['keepalive'], ['expired', 'none'], true)) {
    try {
        $out['relogin'] = hamiad_mobile_relogin()['reason'];
    } catch (Throwable $error) {
        $out['relogin'] = 'error';
    }
}

// 2) Once a day, check whether CAPTCHA-free mobile login is possible. Never fatal.
try {
    if (hamiad_mobile_probe_due($clock)) {
        $probe = hamiad_mobile_probe();
        $out['mobileProbe'] = !empty($probe['answered']) ? 'answered' : 'unanswered';
    }
} catch (Throwable $error) {
    $out['mobileProbe'] = 'error';
}

// 3) Reminders keep their three daily slots.
$slot = hamiad_reminder_slot_key($clock);
$slotPath = dent_storage_path('hamiad/reminder-slot.json');
$lastSlot = (string) (dent_read_json_file($slotPath, [])['slot'] ?? '');
if ($dryRun || ($slot !== null && $slot !== $lastSlot)) {
    try {
        $out['reminders'] = hamiad_process_reminders($dryRun);
        if (!$dryRun) {
            hamiad_keepalive_write($slotPath, ['slot' => $slot, 'at' => dent_iso_now()]);
        }
    } catch (Throwable $error) {
        fwrite(STDERR, "پایش تکالیف همیاد ناموفق بود؛ نشست دانشگاه یا ارتباط سرور بررسی شود.\n");
        echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
        exit(1);
    }
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
