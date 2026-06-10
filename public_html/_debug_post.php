<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'emoji_raw' => $_POST['emoji'] ?? null,
    'bytes' => isset($_POST['emoji']) ? array_map('dechex', array_map('ord', str_split($_POST['emoji']))) : null,
    'len' => isset($_POST['emoji']) ? strlen($_POST['emoji']) : null,
    'match1' => isset($_POST['emoji']) ? @preg_match('/(?:\p{Extended_Pictographic}|[\x{2600}-\x{27BF}]|[\x{1F1E6}-\x{1F1FF}]|\x{1F3F4})/u', $_POST['emoji']) : null,
]);
