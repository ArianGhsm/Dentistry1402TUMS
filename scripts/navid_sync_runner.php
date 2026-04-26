<?php
declare(strict_types=1);

require_once __DIR__ . '/../public_html/api/navid_service.php';

$force = true;
if (PHP_SAPI === 'cli' && isset($argv[1]) && $argv[1] === '--due-only') {
    $force = false;
}

$result = navid_sync_browser($force);
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
}

echo json_encode($result, $flags | JSON_PRETTY_PRINT) . PHP_EOL;

exit(!empty($result['success']) ? 0 : 1);
