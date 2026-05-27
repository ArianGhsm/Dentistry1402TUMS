<?php
declare(strict_types=1);

require_once __DIR__ . '/exams_bank_helpers.php';

function dent_exams_require_optional_module(string $filename): void
{
    $path = __DIR__ . '/' . ltrim($filename, '\\/');
    if (is_file($path)) {
        require_once $path;
    }
}

function dent_exams_bootstrap_modules(): void
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }

    $bootstrapped = true;

    require_once __DIR__ . '/exams_radiology2_overrides.php';
    dent_exams_require_optional_module('exams_morphology_overrides.php');
    dent_exams_require_optional_module('exams_endotorabinejad_overrides.php');
    dent_exams_require_optional_module('exams_radiology2_whitepharoah_overrides.php');
}

function dent_exams_registered_catalog_override_callbacks(): array
{
    return [
        'dent_exams_apply_morphology_catalog_overrides',
        'dent_exams_apply_endotorabinejad_catalog_overrides',
        'dent_exams_apply_radiology2_whitepharoah_catalog_overrides',
        'dent_exams_apply_radiology2_overrides',
    ];
}

function dent_exams_apply_registered_catalog_overrides(array $bank): array
{
    dent_exams_bootstrap_modules();

    foreach (dent_exams_registered_catalog_override_callbacks() as $callback) {
        if (function_exists($callback)) {
            $bank = $callback($bank);
        }
    }

    return dent_exams_sync_bank_question_counts($bank);
}

dent_exams_bootstrap_modules();
