<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'success' => false,
    'error' => 'سامانهٔ نوید بازنشسته شده است. از بخش همیاد استفاده کن.',
    'code' => 'NAVID_RETIRED',
    'href' => '/hamiad/',
], JSON_UNESCAPED_UNICODE);
