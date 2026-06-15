<?php
declare(strict_types=1);

function dent_exams_sync_bank_question_counts(array $bank): array
{
    $catalogs = $bank['catalogs'] ?? null;
    if (!is_array($catalogs)) {
        return $bank;
    }

    foreach ($catalogs as $catalogKey => $catalog) {
        $courses = $catalog['courses'] ?? null;
        if (!is_array($courses)) {
            continue;
        }

        foreach ($courses as $courseSlug => $course) {
            $exams = $course['exams'] ?? null;
            if (!is_array($exams)) {
                continue;
            }

            foreach ($exams as $examIndex => $exam) {
                if (!is_array($exam)) {
                    continue;
                }

                $questions = $exam['questions'] ?? null;
                if (!is_array($questions)) {
                    continue;
                }

                $exam['questionCount'] = count($questions);
                $exams[$examIndex] = $exam;
            }

            $course['exams'] = $exams;
            $courses[$courseSlug] = $course;
        }

        $catalog['courses'] = $courses;
        $catalogs[$catalogKey] = $catalog;
    }

    $bank['catalogs'] = $catalogs;

    return $bank;
}

function dent_exams_bank_cache_path(): string
{
    return dent_storage_path('cache/exams_bank.json');
}

/**
 * Files that influence the compiled exam bank. Their mtimes form the cache
 * signature so edits to exam content/overrides invalidate the cache.
 */
function dent_exams_bank_source_files(): array
{
    $dir = __DIR__;
    $files = [
        $dir . '/exams_bank.php',
        $dir . '/exams_bank_helpers.php',
        $dir . '/exams_modules.php',
    ];

    foreach (['/exams_*_overrides.php', '/exams_*_data.php', '/exams_term6_reference_data/*.php'] as $pattern) {
        $matches = glob($dir . $pattern);
        if (is_array($matches)) {
            $files = array_merge($files, $matches);
        }
    }

    $files = array_unique($files);
    sort($files);

    return $files;
}

function dent_exams_bank_source_signature(): string
{
    $parts = [];
    foreach (dent_exams_bank_source_files() as $file) {
        $mtime = @filemtime($file);
        $parts[] = basename($file) . ':' . ($mtime !== false ? (string) $mtime : '0');
    }

    return md5(implode('|', $parts));
}

function dent_exams_bank_load_from_cache(): ?array
{
    $cached = dent_read_json_file(dent_exams_bank_cache_path(), null);
    if (!is_array($cached)) {
        return null;
    }

    $signature = $cached['signature'] ?? null;
    $bank = $cached['bank'] ?? null;
    if (!is_string($signature) || !is_array($bank)) {
        return null;
    }

    if ($signature !== dent_exams_bank_source_signature()) {
        return null;
    }

    return $bank;
}

function dent_exams_bank_save_to_cache(array $bank): void
{
    $path = dent_exams_bank_cache_path();
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return;
    }

    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $json = json_encode([
        'signature' => dent_exams_bank_source_signature(),
        'bank' => $bank,
    ], $flags);

    if ($json === false) {
        return;
    }

    @file_put_contents($path, $json, LOCK_EX);
}
