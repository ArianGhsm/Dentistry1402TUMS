<?php
declare(strict_types=1);

// Auth-store persistence primitives. Loaded from auth_store.php after cohort helpers.

function dent_auth_store_path(): string
{
    return dent_storage_path('auth/users.json');
}

function dent_auth_store_backup_path(): string
{
    return dent_storage_path('auth/users.backup.json');
}

function dent_auth_store_seed_payload(): array
{
    return [
        'schemaVersion' => 2,
        'ownerStudentNumber' => dent_owner_student_number(),
        'cohorts' => dent_default_cohort_catalog(),
        'users' => [],
    ];
}

function dent_auth_store_runtime_cache(?array $nextStore = null, bool $replace = false, bool $clear = false): ?array
{
    static $cachedStore = null;
    static $cachedSignature = '';

    $signature = static function (): string {
        $path = dent_auth_store_path();
        clearstatcache(false, $path);
        if (!is_file($path)) {
            return 'missing';
        }

        $mtime = @filemtime($path);
        $size = @filesize($path);
        return ($mtime === false ? 'unknown' : (string) $mtime) . '|' . ($size === false ? 'unknown' : (string) $size);
    };

    if ($clear) {
        $cachedStore = null;
        $cachedSignature = '';
        return null;
    }

    if ($replace) {
        $cachedStore = $nextStore;
        $cachedSignature = $signature();
    }

    if ($cachedStore !== null && $cachedSignature !== $signature()) {
        $cachedStore = null;
        $cachedSignature = '';
    }

    return $cachedStore;
}

function dent_decode_auth_store_snapshot(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }
    if (!isset($decoded['users']) || !is_array($decoded['users'])) {
        return null;
    }
    if (isset($decoded['schemaVersion']) && (!is_int($decoded['schemaVersion']) || $decoded['schemaVersion'] < 1)) {
        return null;
    }

    return $decoded;
}

function dent_write_auth_store_payload(array $payload, bool $refreshBackup = true): void
{
    $path = dent_auth_store_path();
    $backupPath = dent_auth_store_backup_path();
    $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $json = json_encode($payload, $flags);
    if ($json === false) {
        dent_error('خطا در تولید داده JSON.', 500);
    }

    $writeSnapshot = static function (string $targetPath, string $contents): void {
        dent_ensure_directory(dirname($targetPath));
        $tmpPath = $targetPath . '.tmp.' . bin2hex(random_bytes(6));
        $handle = @fopen($tmpPath, 'xb');
        if ($handle === false) {
            dent_error('خطا در ذخیره‌سازی داده‌ها.', 500);
        }
        $total = 0;
        $length = strlen($contents);
        try {
            while ($total < $length) {
                $written = @fwrite($handle, substr($contents, $total));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('AUTH_STORE_SHORT_WRITE');
                }
                $total += $written;
            }
            if (!@fflush($handle)) {
                throw new RuntimeException('AUTH_STORE_FLUSH_FAILED');
            }
            if (function_exists('fsync') && !@fsync($handle)) {
                throw new RuntimeException('AUTH_STORE_FSYNC_FAILED');
            }
        } catch (Throwable $exception) {
            @fclose($handle);
            @unlink($tmpPath);
            dent_error('خطا در ذخیره‌سازی داده‌ها.', 500);
        } finally {
            if (is_resource($handle)) {
                @fclose($handle);
            }
        }
        if ($total !== $length) {
            @unlink($tmpPath);
            dent_error('خطا در ذخیره‌سازی داده‌ها.', 500);
        }
        $verified = dent_decode_auth_store_snapshot($tmpPath);
        if (!is_array($verified) || @filesize($tmpPath) !== $length) {
            @unlink($tmpPath);
            dent_error('خطا در ذخیره‌سازی داده‌ها.', 500);
        }
        if (!@rename($tmpPath, $targetPath)) {
            @unlink($tmpPath);
            dent_error('خطا در ذخیره‌سازی داده‌ها.', 500);
        }
    };

    if ($refreshBackup && is_file($path) && filesize($path) > 0) {
        $current = dent_decode_auth_store_snapshot($path);
        if (is_array($current)) {
            $currentJson = json_encode($current, $flags);
            if ($currentJson !== false) {
                $writeSnapshot($backupPath, $currentJson . PHP_EOL);
            }
        }
    }

    $writeSnapshot($path, $json . PHP_EOL);
    dent_auth_store_runtime_cache(null, false, true);
}
