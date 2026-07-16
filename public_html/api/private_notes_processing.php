<?php
declare(strict_types=1);

require_once __DIR__ . '/private_notes_store.php';

function private_notes_env_int(string $name, int $default, int $min, int $max): int
{
    $raw = trim((string) (getenv($name) ?: ''));
    $value = ctype_digit($raw) ? (int) $raw : $default;
    return min($max, max($min, $value));
}

function private_notes_upload_max_bytes(): int
{
    $megabytes = private_notes_env_int('DENT_PRIVATE_NOTES_MAX_UPLOAD_MB', 200, 1, 2048);
    return $megabytes * 1024 * 1024;
}

function private_notes_max_page_count(): int
{
    return private_notes_env_int('DENT_PRIVATE_NOTES_MAX_PAGES', 400, 1, 3000);
}

function private_notes_render_dpi(): int
{
    return private_notes_env_int('DENT_PRIVATE_NOTES_RENDER_DPI', 220, 120, 300);
}

function private_notes_tile_size(): int
{
    return private_notes_env_int('DENT_PRIVATE_NOTES_TILE_SIZE', 512, 128, 2048);
}

function private_notes_processing_tmp_dir(): string
{
    return DENT_TMP_ROOT . DIRECTORY_SEPARATOR . 'private_notes';
}

function private_notes_storage_key(string $documentId): string
{
    $date = date('Y/m');
    return $date . '/' . $documentId . '-' . bin2hex(random_bytes(12)) . '.pdf';
}

function private_notes_original_absolute_path(string $storageKey): string
{
    $safe = private_notes_clean_original_file_ref($storageKey);
    if ($safe === '') {
        dent_error('Private PDF storage key is invalid.', 500);
    }
    return private_notes_originals_dir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safe);
}

function private_notes_pages_absolute_dir(string $assetsKey): string
{
    $safe = private_notes_clean_original_file_ref($assetsKey);
    if ($safe === '') {
        dent_error('Private page asset key is invalid.', 500);
    }
    return private_notes_pages_dir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safe);
}

function private_notes_sanitize_upload_filename(string $filename): string
{
    $filename = trim(str_replace('\\', '/', $filename));
    $filename = basename($filename);
    $filename = preg_replace('/[^\p{L}\p{N}._ -]+/u', '-', $filename) ?? 'document.pdf';
    $filename = trim($filename, " .-\t\n\r\0\x0B");
    if ($filename === '') {
        $filename = 'document.pdf';
    }
    if (!preg_match('/\.pdf$/iu', $filename)) {
        $filename .= '.pdf';
    }
    return dent_clean_text($filename, 180);
}

function private_notes_detect_mime_type(string $path): string
{
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        return is_string($mime) ? trim(strtolower($mime)) : '';
    }
    return '';
}

function private_notes_file_starts_with_pdf_signature(string $path): bool
{
    $handle = @fopen($path, 'rb');
    if (!is_resource($handle)) {
        return false;
    }
    $head = (string) fread($handle, 1024);
    fclose($handle);
    return preg_match('/^%PDF-\d\.\d/', $head) === 1;
}

function private_notes_pdf_contains_encrypt_marker(string $path): bool
{
    $handle = @fopen($path, 'rb');
    if (!is_resource($handle)) {
        return false;
    }
    $tail = '';
    $chunkSize = 1024 * 1024;
    while (!feof($handle)) {
        $chunk = fread($handle, $chunkSize);
        if (!is_string($chunk) || $chunk === '') {
            break;
        }
        $scan = $tail . $chunk;
        if (strpos($scan, '/Encrypt') !== false) {
            fclose($handle);
            return true;
        }
        $tail = substr($scan, -16);
    }
    fclose($handle);
    return false;
}

function private_notes_validate_pdf_file(string $path, string $originalName = '', string $reportedMime = ''): array
{
    if (!is_file($path) || !is_readable($path)) {
        return ['ok' => false, 'error' => 'Uploaded file is not readable.'];
    }

    $size = filesize($path);
    if ($size === false || $size <= 0) {
        return ['ok' => false, 'error' => 'Uploaded PDF is empty.'];
    }
    if ($size > private_notes_upload_max_bytes()) {
        return ['ok' => false, 'error' => 'Uploaded PDF is larger than the configured limit.'];
    }

    $safeName = private_notes_sanitize_upload_filename($originalName !== '' ? $originalName : 'document.pdf');
    if (!preg_match('/\.pdf$/iu', $safeName)) {
        return ['ok' => false, 'error' => 'Only PDF files are accepted.'];
    }

    if (!private_notes_file_starts_with_pdf_signature($path)) {
        return ['ok' => false, 'error' => 'The uploaded file is not a valid PDF file.'];
    }

    $detectedMime = private_notes_detect_mime_type($path);
    $reportedMime = trim(strtolower($reportedMime));
    $allowed = ['application/pdf', 'application/x-pdf'];
    if ($detectedMime !== '' && !in_array($detectedMime, $allowed, true)) {
        return ['ok' => false, 'error' => 'The uploaded file MIME type is not supported.'];
    }
    if ($detectedMime === '' && $reportedMime === '') {
        return ['ok' => false, 'error' => 'PDF MIME type could not be verified on this server.'];
    }
    if ($detectedMime === '' && $reportedMime !== '' && !in_array($reportedMime, $allowed, true)) {
        return ['ok' => false, 'error' => 'The uploaded file MIME type is not supported.'];
    }

    if (private_notes_pdf_contains_encrypt_marker($path)) {
        return ['ok' => false, 'error' => 'Encrypted PDF files are not supported.'];
    }

    return [
        'ok' => true,
        'safeName' => $safeName,
        'mimeType' => $detectedMime !== '' ? $detectedMime : ($reportedMime !== '' ? $reportedMime : 'application/pdf'),
        'sizeBytes' => (int) $size,
        'sha256' => hash_file('sha256', $path) ?: '',
    ];
}

function private_notes_find_binary(array $candidates, string $envName = ''): string
{
    $env = $envName !== '' ? trim((string) (getenv($envName) ?: '')) : '';
    if ($env !== '' && is_file($env)) {
        return $env;
    }
    if ($env !== '') {
        array_unshift($candidates, $env);
    }

    foreach ($candidates as $candidate) {
        $command = DIRECTORY_SEPARATOR === '\\'
            ? 'where ' . escapeshellarg($candidate)
            : 'command -v ' . escapeshellarg($candidate);
        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);
        if ($exitCode === 0 && isset($output[0]) && trim((string) $output[0]) !== '') {
            return trim((string) $output[0]);
        }
    }

    return '';
}

function private_notes_run_command(array $parts, int $timeoutSeconds = 300): array
{
    $command = implode(' ', array_map('escapeshellarg', $parts));
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = @proc_open($command, $descriptorSpec, $pipes);
    if (!is_resource($process)) {
        return ['exitCode' => 127, 'stdout' => '', 'stderr' => 'Process could not be started.'];
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $started = time();
    while (true) {
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (!is_array($status) || empty($status['running'])) {
            break;
        }
        if (time() - $started > $timeoutSeconds) {
            @proc_terminate($process);
            break;
        }
        usleep(50000);
    }
    $stdout .= (string) stream_get_contents($pipes[1]);
    $stderr .= (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    return ['exitCode' => (int) $exitCode, 'stdout' => $stdout, 'stderr' => $stderr];
}

function private_notes_pdf_info(string $pdfPath): array
{
    $pdfinfo = private_notes_find_binary(['pdfinfo'], 'DENT_PRIVATE_NOTES_PDFINFO_BIN');
    if ($pdfinfo === '') {
        return ['ok' => false, 'error' => 'PDF processing requires Poppler pdfinfo.'];
    }

    $result = private_notes_run_command([$pdfinfo, $pdfPath], 120);
    $output = (string) ($result['stdout'] ?? '') . "\n" . (string) ($result['stderr'] ?? '');
    if ((int) ($result['exitCode'] ?? 1) !== 0) {
        return ['ok' => false, 'error' => 'PDF metadata could not be read. The file may be encrypted, malformed or unsupported.'];
    }
    if (preg_match('/^Encrypted:\s+yes/im', $output) === 1) {
        return ['ok' => false, 'error' => 'Encrypted PDF files are not supported.'];
    }
    if (preg_match('/^Pages:\s+(\d+)/im', $output, $matches) !== 1) {
        return ['ok' => false, 'error' => 'PDF page count could not be detected.'];
    }
    $pages = max(0, (int) $matches[1]);
    if ($pages <= 0) {
        return ['ok' => false, 'error' => 'PDF does not contain readable pages.'];
    }
    if ($pages > private_notes_max_page_count()) {
        return ['ok' => false, 'error' => 'PDF page count exceeds the configured limit.'];
    }

    return ['ok' => true, 'pageCount' => $pages];
}

function private_notes_safe_remove_tree(string $path, string $root): void
{
    $rootReal = realpath($root);
    if ($rootReal === false) {
        return;
    }
    $pathReal = realpath($path);
    if ($pathReal === false || $pathReal === $rootReal || !str_starts_with($pathReal, $rootReal . DIRECTORY_SEPARATOR)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($pathReal, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $itemPath = $item->getPathname();
        if ($item->isDir()) {
            @rmdir($itemPath);
        } else {
            @unlink($itemPath);
        }
    }
    @rmdir($pathReal);
}

function private_notes_prepare_clean_dir(string $path): void
{
    if (is_dir($path)) {
        private_notes_safe_remove_tree($path, dirname($path));
    }
    dent_ensure_directory($path);
}

function private_notes_render_pdf_pages(string $pdfPath, string $workDir, int $dpi): array
{
    $pdftoppm = private_notes_find_binary(['pdftoppm'], 'DENT_PRIVATE_NOTES_PDFTOPPM_BIN');
    if ($pdftoppm === '') {
        return ['ok' => false, 'error' => 'PDF rendering requires Poppler pdftoppm.'];
    }

    dent_ensure_directory($workDir);
    $prefix = $workDir . DIRECTORY_SEPARATOR . 'page';
    $result = private_notes_run_command([$pdftoppm, '-r', (string) $dpi, '-png', $pdfPath, $prefix], 900);
    if ((int) ($result['exitCode'] ?? 1) !== 0) {
        return ['ok' => false, 'error' => 'PDF pages could not be rendered: ' . trim((string) ($result['stderr'] ?? ''))];
    }

    $files = glob($workDir . DIRECTORY_SEPARATOR . 'page-*.png') ?: [];
    natsort($files);
    $files = array_values($files);
    if ($files === []) {
        return ['ok' => false, 'error' => 'PDF renderer did not produce any page images.'];
    }

    return ['ok' => true, 'files' => $files, 'renderer' => 'pdftoppm'];
}

function private_notes_image_dimensions(string $path): array
{
    $size = @getimagesize($path);
    if (!is_array($size)) {
        return [0, 0];
    }
    return [max(0, (int) ($size[0] ?? 0)), max(0, (int) ($size[1] ?? 0))];
}

function private_notes_generate_page_tiles(string $sourcePath, string $pageDir, string $assetPrefix, int $pageNumber, int $tileSize): array
{
    $magick = private_notes_find_binary(['magick', 'convert'], 'DENT_PRIVATE_NOTES_IMAGEMAGICK_BIN');
    if ($magick === '') {
        return ['ok' => false, 'error' => 'Tile generation requires ImageMagick.'];
    }

    [$fullWidth, $fullHeight] = private_notes_image_dimensions($sourcePath);
    if ($fullWidth <= 0 || $fullHeight <= 0) {
        return ['ok' => false, 'error' => 'Rendered page dimensions could not be read.'];
    }

    $levels = [];
    $level = 0;
    $width = $fullWidth;
    $height = $fullHeight;
    $scale = 1.0;
    while (true) {
        $levelDir = $pageDir . DIRECTORY_SEPARATOR . 'z' . $level;
        dent_ensure_directory($levelDir);
        $levelImage = $levelDir . DIRECTORY_SEPARATOR . 'level.png';
        if ($level === 0) {
            if (!@copy($sourcePath, $levelImage)) {
                return ['ok' => false, 'error' => 'Rendered page could not be staged for tiling.'];
            }
        } else {
            $resize = private_notes_run_command([$magick, $sourcePath, '-resize', $width . 'x' . $height . '!', $levelImage], 300);
            if ((int) ($resize['exitCode'] ?? 1) !== 0) {
                return ['ok' => false, 'error' => 'Zoom level could not be generated: ' . trim((string) ($resize['stderr'] ?? ''))];
            }
        }

        $tiles = [];
        for ($y = 0; $y < $height; $y += $tileSize) {
            for ($x = 0; $x < $width; $x += $tileSize) {
                $tileWidth = min($tileSize, $width - $x);
                $tileHeight = min($tileSize, $height - $y);
                $tileName = 'tile-' . $x . '-' . $y . '.png';
                $tilePath = $levelDir . DIRECTORY_SEPARATOR . $tileName;
                $crop = private_notes_run_command([
                    $magick,
                    $levelImage,
                    '-crop',
                    $tileWidth . 'x' . $tileHeight . '+' . $x . '+' . $y,
                    '+repage',
                    $tilePath,
                ], 300);
                if ((int) ($crop['exitCode'] ?? 1) !== 0) {
                    return ['ok' => false, 'error' => 'Tile could not be generated: ' . trim((string) ($crop['stderr'] ?? ''))];
                }
                $tiles[] = [
                    'x' => $x,
                    'y' => $y,
                    'width' => $tileWidth,
                    'height' => $tileHeight,
                    'storageKey' => $assetPrefix . '/p' . $pageNumber . '/z' . $level . '/' . $tileName,
                    'bytes' => is_file($tilePath) ? (int) filesize($tilePath) : 0,
                ];
            }
        }

        @unlink($levelImage);
        $levels[] = [
            'level' => $level,
            'scale' => $scale,
            'width' => $width,
            'height' => $height,
            'tileSize' => $tileSize,
            'tiles' => $tiles,
        ];

        if ($width <= $tileSize && $height <= $tileSize) {
            break;
        }
        $level++;
        $scale = $scale / 2;
        $width = max(1, (int) ceil($fullWidth * $scale));
        $height = max(1, (int) ceil($fullHeight * $scale));
    }

    return [
        'ok' => true,
        'page' => [
            'pageNumber' => $pageNumber,
            'width' => $fullWidth,
            'height' => $fullHeight,
            'levels' => $levels,
        ],
    ];
}

function private_notes_process_document(string $documentId): array
{
    $documentId = private_notes_clean_id($documentId, 'pndoc-');
    if ($documentId === '') {
        return ['ok' => false, 'error' => 'Document id is invalid.'];
    }

    $started = private_notes_with_store_lock(static function (array &$store) use ($documentId): array {
        if (!is_array($store['documents'][$documentId] ?? null)) {
            return ['ok' => false, 'error' => 'Document was not found.'];
        }
        $document = $store['documents'][$documentId];
        $document['processingStatus'] = 'processing';
        $document['processingError'] = '';
        $document['processingAttempts'] = max(0, (int) ($document['processingAttempts'] ?? 0)) + 1;
        $document['processingStartedAt'] = dent_iso_now();
        $document['processingCompletedAt'] = '';
        $store['documents'][$documentId] = private_notes_normalize_document($documentId, $document);
        foreach ($store['processingJobs'] as $jobId => $job) {
            if (is_array($job) && (string) ($job['documentId'] ?? '') === $documentId && in_array((string) ($job['status'] ?? ''), ['pending', 'failed'], true)) {
                $job['status'] = 'processing';
                $job['attempts'] = max(0, (int) ($job['attempts'] ?? 0)) + 1;
                $job['startedAt'] = dent_iso_now();
                $job['updatedAt'] = dent_iso_now();
                $store['processingJobs'][$jobId] = private_notes_normalize_processing_job((string) $jobId, $job);
            }
        }
        return ['ok' => true, 'document' => $store['documents'][$documentId]];
    });
    if (empty($started['ok'])) {
        return $started;
    }

    $document = $started['document'];
    $pdfPath = private_notes_original_absolute_path((string) ($document['originalFileRef'] ?: $document['originalStorageKey']));
    $assetsKey = 'documents/' . $documentId;
    $assetsDir = private_notes_pages_absolute_dir($assetsKey);
    $workDir = private_notes_processing_tmp_dir() . DIRECTORY_SEPARATOR . $documentId . '-' . bin2hex(random_bytes(4));
    $dpi = private_notes_render_dpi();
    $tileSize = private_notes_tile_size();

    try {
        if (!is_file($pdfPath)) {
            throw new RuntimeException('Original private PDF is missing from storage.');
        }

        $info = private_notes_pdf_info($pdfPath);
        if (empty($info['ok'])) {
            throw new RuntimeException((string) ($info['error'] ?? 'PDF metadata could not be read.'));
        }

        private_notes_prepare_clean_dir($assetsDir);
        private_notes_prepare_clean_dir($workDir);

        $rendered = private_notes_render_pdf_pages($pdfPath, $workDir, $dpi);
        if (empty($rendered['ok'])) {
            throw new RuntimeException((string) ($rendered['error'] ?? 'PDF rendering failed.'));
        }

        $pages = [];
        $pageNumber = 1;
        foreach ($rendered['files'] as $pageImage) {
            $pageDir = $assetsDir . DIRECTORY_SEPARATOR . 'p' . $pageNumber;
            dent_ensure_directory($pageDir);
            $tiles = private_notes_generate_page_tiles($pageImage, $pageDir, $assetsKey, $pageNumber, $tileSize);
            if (empty($tiles['ok'])) {
                throw new RuntimeException((string) ($tiles['error'] ?? 'Tile generation failed.'));
            }
            $pages[(string) $pageNumber] = $tiles['page'];
            $pageNumber++;
        }

        $pageCount = count($pages);
        private_notes_with_store_lock(static function (array &$store) use ($documentId, $pageCount, $pages, $assetsKey, $dpi, $tileSize, $rendered): array {
            $document = $store['documents'][$documentId] ?? null;
            if (!is_array($document)) {
                return [];
            }
            $document['processingStatus'] = 'ready';
            $document['processingError'] = '';
            $document['pageCount'] = $pageCount;
            $document['renderProfile'] = [
                'dpi' => $dpi,
                'tileSize' => $tileSize,
                'format' => 'png',
                'renderer' => (string) ($rendered['renderer'] ?? 'pdftoppm'),
                'generatedAt' => dent_iso_now(),
            ];
            $document['pages'] = $pages;
            $document['assetsStorageKey'] = $assetsKey;
            $document['processingCompletedAt'] = dent_iso_now();
            $document['updatedAt'] = dent_iso_now();
            $store['documents'][$documentId] = private_notes_normalize_document($documentId, $document);
            foreach ($store['processingJobs'] as $jobId => $job) {
                if (is_array($job) && (string) ($job['documentId'] ?? '') === $documentId && (string) ($job['status'] ?? '') !== 'ready') {
                    $job['status'] = 'ready';
                    $job['finishedAt'] = dent_iso_now();
                    $job['updatedAt'] = dent_iso_now();
                    $store['processingJobs'][$jobId] = private_notes_normalize_processing_job((string) $jobId, $job);
                }
            }
            return $store['documents'][$documentId];
        });

        return ['ok' => true, 'documentId' => $documentId, 'pageCount' => $pageCount];
    } catch (Throwable $error) {
        $message = dent_clean_text($error->getMessage(), 1000);
        private_notes_with_store_lock(static function (array &$store) use ($documentId, $message): array {
            if (is_array($store['documents'][$documentId] ?? null)) {
                $document = $store['documents'][$documentId];
                $document['processingStatus'] = 'failed';
                $document['processingError'] = $message;
                $document['processingCompletedAt'] = dent_iso_now();
                $document['updatedAt'] = dent_iso_now();
                $store['documents'][$documentId] = private_notes_normalize_document($documentId, $document);
                private_notes_add_security_event($store, 'document-processing-failed', 'warning', [
                    'documentId' => $documentId,
                    'courseId' => (string) ($document['courseId'] ?? ''),
                    'semesterId' => (string) ($document['semesterId'] ?? ''),
                    'message' => $message,
                ]);
            }
            foreach ($store['processingJobs'] as $jobId => $job) {
                if (is_array($job) && (string) ($job['documentId'] ?? '') === $documentId && (string) ($job['status'] ?? '') !== 'ready') {
                    $job['status'] = 'failed';
                    $job['lastError'] = $message;
                    $job['finishedAt'] = dent_iso_now();
                    $job['updatedAt'] = dent_iso_now();
                    $store['processingJobs'][$jobId] = private_notes_normalize_processing_job((string) $jobId, $job);
                }
            }
            return [];
        });
        return ['ok' => false, 'documentId' => $documentId, 'error' => $message];
    } finally {
        if (is_dir($workDir)) {
            private_notes_safe_remove_tree($workDir, private_notes_processing_tmp_dir());
        }
    }
}

function private_notes_create_processing_job(array &$store, string $documentId, array $admin): array
{
    $jobId = private_notes_next_id('pnjob-');
    $job = private_notes_normalize_processing_job($jobId, [
        'id' => $jobId,
        'documentId' => $documentId,
        'status' => 'pending',
        'attempts' => 0,
        'createdBy' => private_notes_user_key($admin),
        'createdAt' => dent_iso_now(),
        'updatedAt' => dent_iso_now(),
    ]);
    if ($job === null) {
        dent_error('Processing job could not be created.', 500);
    }
    $store['processingJobs'][$jobId] = $job;
    return $job;
}

function private_notes_next_pending_processing_job(array $store): ?array
{
    foreach ($store['processingJobs'] ?? [] as $job) {
        if (is_array($job) && (string) ($job['status'] ?? '') === 'pending') {
            return $job;
        }
    }
    return null;
}

function private_notes_process_next_pending_job(): array
{
    $store = private_notes_read_store();
    $job = private_notes_next_pending_processing_job($store);
    if ($job === null) {
        return ['ok' => true, 'processed' => false, 'message' => 'No pending private-notes processing jobs.'];
    }
    $documentId = (string) ($job['documentId'] ?? '');
    if ($documentId === '') {
        return ['ok' => false, 'processed' => false, 'error' => 'Pending job has no document id.'];
    }
    $result = private_notes_process_document($documentId);
    $result['processed'] = true;
    $result['jobId'] = (string) ($job['id'] ?? '');
    return $result;
}
