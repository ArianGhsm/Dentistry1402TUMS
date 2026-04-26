<?php
require __DIR__ . '/../public_html/api/bootstrap.php';
require __DIR__ . '/../public_html/api/navid_store.php';
require __DIR__ . '/../public_html/api/navid_service.php';
$backupPath = __DIR__ . '/navid-integration.before-real-test.json';
$storePath = dent_storage_path('navid/integration.json');
if (is_file($storePath)) {
    copy($storePath, $backupPath);
}
$store = navid_load_store();
$store['config']['enabled'] = true;
$store['config']['loginUrl'] = 'https://navid.tums.ac.ir/account/loginsipadservice';
$store['config']['syncIntervalMinutes'] = 30;
$store['config']['captchaStrategy'] = 'python_ocr';
navid_set_credentials($store, '40211272003', 'ARIANgh@138435');
navid_clear_session_cookies($store);
navid_clear_challenge($store);
$store['state']['requiresReconnect'] = false;
$store['state']['lastError'] = '';
$store['state']['lastFailedCourses'] = 0;
navid_save_store($store);
$result = navid_sync_browser(true);
$store = navid_load_store();
echo json_encode([
  'result' => [
    'success' => $result['success'] ?? null,
    'status' => $result['status'] ?? null,
    'message' => $result['message'] ?? null,
    'summary' => $result['summary'] ?? null,
    'hasCaptchaDataUri' => !empty($result['captchaDataUri'])
  ],
  'ownerStatus' => navid_build_owner_status($store),
  'snapshotCourseKeys' => array_keys(is_array($store['snapshot']['courses'] ?? null) ? $store['snapshot']['courses'] : []),
  'snapshotAssignmentCount' => count(is_array($store['snapshot']['assignments'] ?? null) ? $store['snapshot']['assignments'] : []),
  'updatesCount' => count(is_array($store['updates'] ?? null) ? $store['updates'] : [])
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);