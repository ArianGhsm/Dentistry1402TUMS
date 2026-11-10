<?php
declare(strict_types=1);
require_once __DIR__ . '/hamiad_storage.php';

/** Owner-only storage, out of public_html and encrypted at rest. */
function hamiad_credentials_path(): string
{
    return dent_storage_path('hamiad/owner-credentials.json');
}

function hamiad_credentials_load(): ?array
{
    $stored = dent_read_json_file(hamiad_credentials_path(), []);
    if ($stored === []) return null;
    $plain = hamiad_unseal($stored['sealed'] ?? null);
    if (!is_array($plain)
        || !is_string($plain['username'] ?? null)
        || !preg_match('/^[A-Za-z0-9._@-]{3,80}$/D', $plain['username'])
        || !is_string($plain['password'] ?? null)
        || $plain['password'] === ''
        || strlen($plain['password']) > 256) {
        throw new RuntimeException('اطلاعات ورود ذخیره‌شده معتبر نیست؛ مجدداً ثبت کن.');
    }
    return ['username'=>$plain['username'], 'password'=>$plain['password']];
}

function hamiad_credentials_status(): array
{
    $stored = dent_read_json_file(hamiad_credentials_path(), []);
    if ($stored === []) return ['saved'=>false,'usernameMasked'=>'','savedAt'=>''];
    hamiad_credentials_load(); // Validate ciphertext and schema before reporting success.
    return ['saved'=>true,
        'usernameMasked'=>(string)($stored['usernameMasked']??''),
        'savedAt'=>(string)($stored['savedAt']??'')];
}

function hamiad_credentials_save(string $username, string $password): array
{
    $username = trim($username);
    if (!preg_match('/^[A-Za-z0-9._@-]{3,80}$/D', $username)
        || $password === '' || strlen($password) > 256) {
        throw new RuntimeException('نام کاربری یا رمز عبور معتبر نیست.');
    }
    $path = hamiad_credentials_path();
    $masked = substr($username,0,2)
        . str_repeat('*',max(2,strlen($username)-4))
        . substr($username,-2);
    $oldUmask = umask(0077);
    try {
        dent_write_json_file($path, [
            'schemaVersion'=>1,'savedAt'=>dent_iso_now(),
            'usernameMasked'=>$masked,
            'sealed'=>hamiad_seal(['username'=>$username,'password'=>$password]),
        ]);
        if (!@chmod($path,0600)) {
            throw new RuntimeException('اعمال مجوز امن ذخیره‌سازی ناموفق بود.');
        }
    } finally {
        umask($oldUmask);
    }
    return hamiad_credentials_status();
}

function hamiad_credentials_delete(): array
{
    $path = hamiad_credentials_path();
    $oldUmask = umask(0077);
    try {
        dent_ensure_directory(dirname($path));
        $lock = @fopen($path.'.lock','c');
        if (!is_resource($lock) || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) fclose($lock);
            throw new RuntimeException('قفل ذخیره‌سازی در دسترس نیست.');
        }
        try {
            if (is_file($path) && !@unlink($path)) {
                throw new RuntimeException('حذف مشخصات انجام نشد.');
            }
        } finally {
            flock($lock,LOCK_UN);
            fclose($lock);
        }
    } finally {
        umask($oldUmask);
    }
    return hamiad_credentials_status();
}
