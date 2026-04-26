<?php
require __DIR__ . '/../public_html/api/bootstrap.php';
require __DIR__ . '/../public_html/api/navid_store.php';
require __DIR__ . '/../public_html/api/navid_service.php';
$payload = navid_feed_payload(true);
$courses = is_array(($payload['data']['ownerStatus']['snapshotCounts'] ?? null)) ? $payload['data']['ownerStatus']['snapshotCounts'] : [];
$assignments = array_slice(is_array($payload['data']['currentAssignments'] ?? null) ? $payload['data']['currentAssignments'] : [], 0, 10);
$updates = array_slice(is_array($payload['data']['updates'] ?? null) ? $payload['data']['updates'] : [], 0, 5);
echo json_encode([
  'publicStatus' => $payload['data']['publicStatus'] ?? null,
  'ownerStatusCounts' => $courses,
  'assignmentsPreview' => array_map(static function ($item) {
      return [
        'courseTitle' => $item['courseTitle'] ?? '',
        'title' => $item['title'] ?? '',
        'endDateShamsi' => $item['endDateShamsi'] ?? '',
        'files' => is_array($item['files'] ?? null) ? count($item['files']) : 0,
      ];
  }, $assignments),
  'updatesPreview' => array_map(static function ($item) {
      return [
        'eventType' => $item['eventType'] ?? '',
        'courseTitle' => $item['courseTitle'] ?? '',
        'title' => $item['title'] ?? '',
      ];
  }, $updates)
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);