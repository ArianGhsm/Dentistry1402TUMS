<?php
declare(strict_types=1);

function vault_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
}

$testRoot = sys_get_temp_dir() . '/hamiad-vault-test-' . bin2hex(random_bytes(8));
mkdir($testRoot, 0700);
putenv('DENT_STORAGE_ROOT=' . $testRoot);
putenv('DENT_SERVER_ONLY_ROOT=' . $testRoot . '/server-only');
try {
    require_once dirname(__DIR__) . '/public_html/api/hamiad_credentials.php';
    vault_check(hamiad_credentials_status()['saved'] === false, 'Empty status');
    vault_check(hamiad_credentials_load() === null, 'Empty load');
    try {
        hamiad_credentials_save('bad user', 'sample-only');
        throw new RuntimeException('Bad username was accepted');
    } catch (RuntimeException $error) {
        vault_check(str_contains($error->getMessage(), 'معتبر نیست'), 'Reject invalid username');
    }
    try {
        hamiad_credentials_save('112223333', '');
        throw new RuntimeException('Empty password accepted');
    } catch (RuntimeException $error) {
        vault_check(str_contains($error->getMessage(), 'معتبر نیست'), 'Reject empty password');
    }
    $status = hamiad_credentials_save('112223333', 'sample-secret-for-unit-test');
    vault_check($status['saved'] === true, 'Saved status');
    vault_check($status['usernameMasked'] === '11*****33', 'Masked username');
    vault_check(!array_key_exists('password', $status), 'Never disclose secret in status');
    $path = hamiad_credentials_path();
    $raw = file_get_contents($path);
    vault_check(is_string($raw) && !str_contains($raw,'sample-secret-for-unit-test'), 'Secret is ciphertext');
    vault_check(!str_contains($raw, '112223333'), 'Full username not plaintext');
    vault_check((fileperms($path) & 0777) === 0600, 'Owner-only file permissions');
    vault_check(hamiad_credentials_load()['password'] === 'sample-secret-for-unit-test', 'Backend decrypts');
    hamiad_credentials_save('112223333','updated-sample-secret');
    vault_check(hamiad_credentials_load()['password'] === 'updated-sample-secret', 'Replace secret');
    vault_check(hamiad_credentials_delete()['saved'] === false, 'Delete credentials');
    vault_check(!file_exists($path), 'Ciphertext deleted');
    $html = file_get_contents(dirname(__DIR__) . '/public_html/hamiad/index.php');
    $js = file_get_contents(dirname(__DIR__) . '/public_html/assets/site/scripts/hamiad-bridge.js');
    $api = file_get_contents(dirname(__DIR__) . '/public_html/api/hamiad_bridge_api.php');
    vault_check(str_contains($html, 'id="hamiad-credentials-form"'), 'Form in owner view');
    vault_check(str_contains($html, 'id="hamiad-credentials-delete"'), 'Delete in owner view');
    vault_check(str_contains($js, 'request("saveCredentials"'), 'Save UI handler');
    vault_check(str_contains($js, 'request("deleteCredentials"'), 'Delete UI handler');
    vault_check(str_contains($api, 'dent_require_owner();'), 'Owner guard');
    vault_check(str_contains($api, 'dent_auth_session_require_csrf();'), 'CSRF guard');
    echo "HAMIAD_CREDENTIALS_TESTS_OK\n";
} finally {
    foreach (@scandir($testRoot.'/hamiad') ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            @unlink($testRoot.'/hamiad/'.$entry);
        }
    }
    @rmdir($testRoot.'/hamiad');
    @rmdir($testRoot);
}
