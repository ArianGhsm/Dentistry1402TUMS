<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';

const NOTES_1402_SCHEMA_VERSION = 1;
const NOTES_1402_MIN_TERM = 5;
const NOTES_1402_MAX_TERM = 12;
const NOTES_1402_SEED_BACKFILL_VERSION = 1;

function notes_1402_store_path(): string
{
    return dent_storage_path('notes/1402_terms.json');
}

function notes_1402_lock_path(): string
{
    return dent_storage_path('notes/1402_terms.lock');
}

function notes_1402_term_template(int $term): array
{
    if ($term === 5) {
        return [
            'term' => 5,
            'kicker' => 'ترم ۵',
            'title' => 'فایل‌های فعلی آرشیو',
            'description' => 'همه منابعی که قبلاً در صفحه جزوات ۱۴۰۲ بودند، فعلاً در این ترم قرار گرفته‌اند.',
            'emptyMessage' => 'برای ترم ۵ هنوز منبعی ثبت نشده است.',
        ];
    }

    return [
        'term' => $term,
        'kicker' => 'ترم ' . dent_to_fa_digits((string) $term),
        'title' => 'آرشیو منابع ترم ' . dent_to_fa_digits((string) $term),
        'description' => 'منابع این ترم به‌مرور اضافه می‌شوند.',
        'emptyMessage' => 'منابع ترم ' . dent_to_fa_digits((string) $term) . ' هنوز ثبت نشده است.',
    ];
}

function notes_1402_seed_term_5_items(): array
{
    return [
        [
            'id' => 1,
            'badge' => 'برنامه',
            'title' => 'برنامه امتحانات پایان‌ترم',
            'description' => 'برنامه پایان‌ترم.',
            'buttonLabel' => 'دیدن',
            'buttonUrl' => 'https://dentistry.tums.ac.ir/uploads/351/2026/Jan/20/%D8%A8%D8%B1%D9%86%D8%A7%D9%85%D9%87%20%D8%A7%D9%85%D8%AA%D8%AD%D8%A7%D9%86%D8%A7%D8%AA%20%D9%BE%D8%A7%DB%8C%D8%A7%D9%86%20%D8%AA%D8%B1%D9%85%20%D8%AF%DA%A9%D8%AA%D8%B1%D8%A7%20%D9%86%DB%8C%D9%85%D8%B3%D8%A7%D9%84%20%D8%A7%D9%88%D9%84%201404-1405_1.jpg',
        ],
        [
            'id' => 2,
            'badge' => 'سیستمیک',
            'title' => 'جزوات بیماری‌های سیستمیک',
            'description' => 'جلسه‌های نهم تا هفدهم.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s5.uupload.ir/files/arianghsm/Systemicdiseases.zip',
        ],
        [
            'id' => 3,
            'badge' => 'ترمیمی',
            'title' => 'بارم‌بندی ترمیمی',
            'description' => 'بارم‌بندی و تعداد سؤال‌ها.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s31.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201402/_lrm_⁨بارم%20بندی%20پایان%20ترم%20مبانی%20ترمیمی⁩.pdf',
        ],
        [
            'id' => 4,
            'badge' => 'ترمیمی',
            'title' => 'جزوات ترمیمی',
            'description' => 'جلسه‌های ۱، ۲، ۴، ۵، ۷، ۸، ۹، ۱۰، ۱۱، ۱۲ و ۱۴ به‌همراه مواد دندانی.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s15.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201402/Restorative%20Dentistry.zip',
        ],
        [
            'id' => 5,
            'badge' => 'جراحی',
            'title' => 'جزوات جراحی نظری ۱',
            'description' => 'همه جلسه‌ها به‌جز جلسه پنجم.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s15.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201402/Surgery%20(-%205).zip',
        ],
        [
            'id' => 6,
            'badge' => 'رادیولوژی',
            'title' => 'جزوات رادیولوژی نظری ۲',
            'description' => 'جلسه‌های اول تا هفتم.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s15.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201402/Radiology%20(1-7).zip',
        ],
        [
            'id' => 7,
            'badge' => 'فارماکولوژی',
            'title' => 'جزوات فارماکولوژی',
            'description' => 'جلسه‌های ۷، ۹، ۱۰، ۱۱ و ۱۶.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s5.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201402/Pharmacology.zip',
        ],
        [
            'id' => 8,
            'badge' => 'اخلاق',
            'title' => 'جزوات اخلاق پزشکی',
            'description' => 'جلسه‌های ۱، ۴، ۵، ۷، ۹، ۱۱، ۱۵ و ۱۶.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s15.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201402/Medical%20Ethics%20(1-4-5-7-9-11-15-16).zip',
        ],
        [
            'id' => 9,
            'badge' => 'جراحی',
            'title' => 'کتاب CDR جراحی نظری ۱',
            'description' => 'فایل کامل کتاب.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s15.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201402/پیترسون%20CDR%202019.pdf',
        ],
        [
            'id' => 10,
            'badge' => 'جراحی',
            'title' => 'رفرنس فارسی جراحی نظری ۱',
            'description' => 'فصل‌های ۴، ۷، ۸، ۹، ۱۱، ۱۶ و ۱۷.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s31.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201402/رفرنس%20جراحی%20پیترسون.zip',
        ],
        [
            'id' => 11,
            'badge' => 'پارسیل',
            'title' => 'کتاب گام‌به‌گام با پروتز پارسیل',
            'description' => 'فایل کامل کتاب.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s5.uupload.ir/files/arianghsm/_lrm_⁨گام%20به%20گام%20با%20پروتز%20پارسیل⁩.pdf',
        ],
    ];
}

function notes_1402_item_signature(array $item): string
{
    $title = dent_utf8_strtolower(trim((string) ($item['title'] ?? '')));
    $buttonUrl = trim((string) ($item['buttonUrl'] ?? ''));
    return $title . '|' . $buttonUrl;
}

function notes_1402_needs_term_5_seed_backfill(array $seed): bool
{
    $backfillVersion = (int) ($seed['seedBackfillVersion'] ?? 0);
    if ($backfillVersion >= NOTES_1402_SEED_BACKFILL_VERSION) {
        return false;
    }

    $term5 = is_array($seed['terms']['5'] ?? null) ? $seed['terms']['5'] : [];
    $items = is_array($term5['items'] ?? null) ? $term5['items'] : [];
    if (count($items) === 0 || count($items) >= count(notes_1402_seed_term_5_items())) {
        return false;
    }

    // Legacy bad migration left term 5 with only a couple of seed items.
    // Keep backfill conservative so owner-managed states are not overridden.
    if (count($items) > 3) {
        return false;
    }

    $seedSignatures = [];
    foreach (notes_1402_seed_term_5_items() as $seedItem) {
        $normalized = notes_1402_normalize_item_record($seedItem);
        if ($normalized === null) {
            continue;
        }
        $seedSignatures[notes_1402_item_signature($normalized)] = true;
    }

    foreach ($items as $item) {
        if (!is_array($item)) {
            return false;
        }

        $normalized = notes_1402_normalize_item_record($item);
        if ($normalized === null) {
            return false;
        }

        if (!isset($seedSignatures[notes_1402_item_signature($normalized)])) {
            return false;
        }
    }

    return true;
}

function notes_1402_apply_term_5_seed_backfill(array $seed): array
{
    if (!notes_1402_needs_term_5_seed_backfill($seed)) {
        return $seed;
    }

    if (!isset($seed['terms']) || !is_array($seed['terms'])) {
        $seed['terms'] = [];
    }
    if (!isset($seed['terms']['5']) || !is_array($seed['terms']['5'])) {
        $seed['terms']['5'] = notes_1402_term_template(5);
    }

    $term5 = $seed['terms']['5'];
    $currentItems = is_array($term5['items'] ?? null) ? $term5['items'] : [];
    $mergedItems = [];
    $knownSignatures = [];
    $maxId = 0;

    foreach ($currentItems as $itemSeed) {
        if (!is_array($itemSeed)) {
            continue;
        }
        $normalized = notes_1402_normalize_item_record($itemSeed);
        if ($normalized === null) {
            continue;
        }

        $signature = notes_1402_item_signature($normalized);
        if (isset($knownSignatures[$signature])) {
            continue;
        }
        $knownSignatures[$signature] = true;
        $maxId = max($maxId, (int) ($normalized['id'] ?? 0));
        $mergedItems[] = $normalized;
    }

    foreach (notes_1402_seed_term_5_items() as $seedItem) {
        $normalized = notes_1402_normalize_item_record($seedItem);
        if ($normalized === null) {
            continue;
        }

        $signature = notes_1402_item_signature($normalized);
        if (isset($knownSignatures[$signature])) {
            continue;
        }

        $knownSignatures[$signature] = true;
        $maxId = max($maxId, (int) ($normalized['id'] ?? 0));
        $mergedItems[] = $normalized;
    }

    usort($mergedItems, static function (array $left, array $right): int {
        return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
    });

    $term5['items'] = $mergedItems;
    $seed['terms']['5'] = $term5;
    $seed['nextItemId'] = max((int) ($seed['nextItemId'] ?? 1), $maxId + 1);
    $seed['seedBackfillVersion'] = NOTES_1402_SEED_BACKFILL_VERSION;

    return $seed;
}

function notes_1402_default_store(): array
{
    $terms = [];
    for ($term = NOTES_1402_MIN_TERM; $term <= NOTES_1402_MAX_TERM; $term++) {
        $template = notes_1402_term_template($term);
        $template['items'] = $term === 5 ? notes_1402_seed_term_5_items() : [];
        $terms[(string) $term] = $template;
    }

    return [
        'schemaVersion' => NOTES_1402_SCHEMA_VERSION,
        'seedBackfillVersion' => NOTES_1402_SEED_BACKFILL_VERSION,
        'nextItemId' => 12,
        'terms' => $terms,
    ];
}

function notes_1402_ensure_storage(): void
{
    dent_ensure_directory(dirname(notes_1402_store_path()));
    if (!is_file(notes_1402_store_path())) {
        dent_write_json_file(notes_1402_store_path(), notes_1402_default_store());
    }
}

function notes_1402_normalize_url(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $clean = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $value);
    if (!is_string($clean)) {
        $clean = $value;
    }
    $clean = trim($clean);
    if ($clean === '') {
        return '';
    }

    if (str_starts_with($clean, '/') && !str_starts_with($clean, '//')) {
        return $clean;
    }

    if (!preg_match('/^https?:\/\//i', $clean)) {
        return '';
    }

    $validated = filter_var($clean, FILTER_VALIDATE_URL);
    if (!is_string($validated) || trim($validated) === '') {
        $parsed = @parse_url($clean);
        if (
            !is_array($parsed) ||
            !in_array(dent_utf8_strtolower((string) ($parsed['scheme'] ?? '')), ['http', 'https'], true) ||
            trim((string) ($parsed['host'] ?? '')) === ''
        ) {
            return '';
        }

        return $clean;
    }

    return $validated;
}

function notes_1402_normalize_item_record(array $seed): ?array
{
    $id = max(0, (int) ($seed['id'] ?? 0));
    if ($id <= 0) {
        return null;
    }

    $badge = dent_clean_text((string) ($seed['badge'] ?? ''), 70);
    $title = dent_clean_text((string) ($seed['title'] ?? ''), 180);
    $description = dent_clean_text((string) ($seed['description'] ?? ''), 600);
    $buttonLabel = dent_clean_text((string) ($seed['buttonLabel'] ?? ''), 70);
    $buttonUrl = notes_1402_normalize_url((string) ($seed['buttonUrl'] ?? ''));

    if ($badge === '' || $title === '' || $description === '' || $buttonLabel === '' || $buttonUrl === '') {
        return null;
    }

    return [
        'id' => $id,
        'badge' => $badge,
        'title' => $title,
        'description' => $description,
        'buttonLabel' => $buttonLabel,
        'buttonUrl' => $buttonUrl,
        'createdAt' => (string) ($seed['createdAt'] ?? dent_iso_now()),
        'updatedAt' => (string) ($seed['updatedAt'] ?? dent_iso_now()),
    ];
}

function notes_1402_normalize_store(array $seed): array
{
    $defaults = notes_1402_default_store();
    $termsSeed = is_array($seed['terms'] ?? null) ? $seed['terms'] : [];

    $normalizedTerms = [];
    $maxItemId = 0;
    for ($term = NOTES_1402_MIN_TERM; $term <= NOTES_1402_MAX_TERM; $term++) {
        $termKey = (string) $term;
        $defaultTerm = $defaults['terms'][$termKey];
        $termSeed = is_array($termsSeed[$termKey] ?? null) ? $termsSeed[$termKey] : [];
        $itemsSeed = is_array($termSeed['items'] ?? null) ? $termSeed['items'] : [];

        $items = [];
        foreach ($itemsSeed as $itemSeed) {
            if (!is_array($itemSeed)) {
                continue;
            }
            $item = notes_1402_normalize_item_record($itemSeed);
            if ($item === null) {
                continue;
            }
            $maxItemId = max($maxItemId, (int) $item['id']);
            $items[] = $item;
        }

        usort($items, static function (array $left, array $right): int {
            return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
        });

        $normalizedTerms[$termKey] = [
            'term' => $term,
            'kicker' => dent_clean_text((string) ($termSeed['kicker'] ?? $defaultTerm['kicker']), 80),
            'title' => dent_clean_text((string) ($termSeed['title'] ?? $defaultTerm['title']), 160),
            'description' => dent_clean_text((string) ($termSeed['description'] ?? $defaultTerm['description']), 800),
            'emptyMessage' => dent_clean_text((string) ($termSeed['emptyMessage'] ?? $defaultTerm['emptyMessage']), 400),
            'items' => $items,
        ];
    }

    return [
        'schemaVersion' => NOTES_1402_SCHEMA_VERSION,
        'seedBackfillVersion' => max(0, (int) ($seed['seedBackfillVersion'] ?? 0)),
        'nextItemId' => max(1, (int) ($seed['nextItemId'] ?? 1), $maxItemId + 1),
        'terms' => $normalizedTerms,
    ];
}

function notes_1402_load_store_unlocked(): array
{
    $raw = dent_read_json_file(notes_1402_store_path(), notes_1402_default_store());
    if (!is_array($raw)) {
        $raw = notes_1402_default_store();
    }
    $raw = notes_1402_apply_term_5_seed_backfill($raw);

    return notes_1402_normalize_store($raw);
}

function notes_1402_save_store_unlocked(array $store): void
{
    dent_write_json_file(notes_1402_store_path(), notes_1402_normalize_store($store));
}

function notes_1402_read_store(): array
{
    notes_1402_ensure_storage();

    $lock = fopen(notes_1402_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل آرشیو منابع.', 500);
    }

    $store = notes_1402_default_store();
    try {
        if (!flock($lock, LOCK_SH)) {
            throw new RuntimeException('Unable to acquire notes shared lock.');
        }

        $store = notes_1402_load_store_unlocked();
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }

    return $store;
}

/**
 * @template T
 * @param callable(array):T $callback
 * @return T
 */
function notes_1402_with_store_lock(callable $callback)
{
    notes_1402_ensure_storage();

    $lock = fopen(notes_1402_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل آرشیو منابع.', 500);
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to acquire notes exclusive lock.');
        }

        $store = notes_1402_load_store_unlocked();
        $result = $callback($store);
        notes_1402_save_store_unlocked($store);
        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function notes_1402_parse_term($raw): int
{
    $term = (int) dent_normalize_digits((string) $raw);
    if ($term < NOTES_1402_MIN_TERM || $term > NOTES_1402_MAX_TERM) {
        dent_error('شماره ترم معتبر نیست.', 422);
    }

    return $term;
}

function notes_1402_item_payload(array $item): array
{
    $url = (string) ($item['buttonUrl'] ?? '');
    $isExternal = !str_starts_with($url, '/');

    return [
        'id' => (int) ($item['id'] ?? 0),
        'badge' => (string) ($item['badge'] ?? ''),
        'title' => (string) ($item['title'] ?? ''),
        'description' => (string) ($item['description'] ?? ''),
        'buttonLabel' => (string) ($item['buttonLabel'] ?? ''),
        'buttonUrl' => $url,
        'isExternal' => $isExternal,
        'createdAt' => (string) ($item['createdAt'] ?? ''),
        'updatedAt' => (string) ($item['updatedAt'] ?? ''),
    ];
}

function notes_1402_term_payload(array $store, int $term): array
{
    $termRecord = $store['terms'][(string) $term] ?? notes_1402_term_template($term);
    $items = is_array($termRecord['items'] ?? null) ? $termRecord['items'] : [];
    $itemPayloads = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemPayloads[] = notes_1402_item_payload($item);
    }

    return [
        'term' => $term,
        'kicker' => (string) ($termRecord['kicker'] ?? ''),
        'title' => (string) ($termRecord['title'] ?? ''),
        'description' => (string) ($termRecord['description'] ?? ''),
        'emptyMessage' => (string) ($termRecord['emptyMessage'] ?? ''),
        'items' => $itemPayloads,
    ];
}

function notes_1402_next_item_id(array &$store): int
{
    $next = max(1, (int) ($store['nextItemId'] ?? 1));
    $store['nextItemId'] = $next + 1;
    return $next;
}

function notes_1402_require_method(array $allowed): void
{
    $method = dent_request_method();
    if (!in_array($method, $allowed, true)) {
        dent_error('متد درخواست معتبر نیست.', 405);
    }
}

function notes_1402_parse_item_id($raw): int
{
    $itemId = (int) dent_normalize_digits((string) $raw);
    if ($itemId <= 0) {
        dent_error('شناسه کارت معتبر نیست.', 422);
    }

    return $itemId;
}

$action = dent_request_action();

if ($action === 'term') {
    notes_1402_require_method(['GET']);

    $term = notes_1402_parse_term($_GET['term'] ?? '');
    $store = notes_1402_read_store();
    $viewer = dent_current_user();
    $isOwner = is_array($viewer) && (($viewer['role'] ?? '') === 'owner');

    dent_json_response([
        'success' => true,
        'term' => notes_1402_term_payload($store, $term),
        'canManage' => $isOwner,
    ]);
}

if ($action === 'addItem') {
    notes_1402_require_method(['POST']);
    dent_require_owner();

    $term = notes_1402_parse_term($_POST['term'] ?? '');
    $badge = dent_clean_text((string) ($_POST['badge'] ?? ''), 70);
    $title = dent_clean_text((string) ($_POST['title'] ?? ''), 180);
    $description = dent_clean_text((string) ($_POST['description'] ?? ''), 600);
    $buttonLabel = dent_clean_text((string) ($_POST['buttonLabel'] ?? ''), 70);
    $buttonUrl = notes_1402_normalize_url((string) ($_POST['buttonUrl'] ?? ''));

    if ($badge === '' || $title === '' || $description === '' || $buttonLabel === '' || $buttonUrl === '') {
        dent_error('همه فیلدهای کارت باید کامل و معتبر باشند.', 422);
    }

    $created = notes_1402_with_store_lock(static function (array &$store) use (
        $term,
        $badge,
        $title,
        $description,
        $buttonLabel,
        $buttonUrl
    ): array {
        $termKey = (string) $term;
        if (!isset($store['terms'][$termKey]) || !is_array($store['terms'][$termKey])) {
            $store['terms'][$termKey] = notes_1402_term_template($term);
            $store['terms'][$termKey]['items'] = [];
        }

        $item = [
            'id' => notes_1402_next_item_id($store),
            'badge' => $badge,
            'title' => $title,
            'description' => $description,
            'buttonLabel' => $buttonLabel,
            'buttonUrl' => $buttonUrl,
            'createdAt' => dent_iso_now(),
            'updatedAt' => dent_iso_now(),
        ];

        if (!is_array($store['terms'][$termKey]['items'] ?? null)) {
            $store['terms'][$termKey]['items'] = [];
        }

        array_unshift($store['terms'][$termKey]['items'], $item);
        return $item;
    });

    dent_json_response([
        'success' => true,
        'item' => notes_1402_item_payload($created),
        'message' => 'کارت منبع جدید با موفقیت ثبت شد.',
    ]);
}

if ($action === 'deleteItem') {
    notes_1402_require_method(['POST']);
    dent_require_owner();

    $term = notes_1402_parse_term($_POST['term'] ?? '');
    $itemId = notes_1402_parse_item_id($_POST['itemId'] ?? '');

    try {
        $deleted = notes_1402_with_store_lock(static function (array &$store) use ($term, $itemId): array {
            $termKey = (string) $term;
            if (!is_array($store['terms'][$termKey]['items'] ?? null)) {
                throw new RuntimeException('item-not-found');
            }

            $items = &$store['terms'][$termKey]['items'];
            foreach ($items as $index => $item) {
                if ((int) ($item['id'] ?? 0) !== $itemId) {
                    continue;
                }

                $deleted = is_array($item) ? $item : [];
                array_splice($items, $index, 1);
                return $deleted;
            }

            throw new RuntimeException('item-not-found');
        });
    } catch (RuntimeException $error) {
        if ($error->getMessage() === 'item-not-found') {
            dent_error('کارت موردنظر پیدا نشد.', 404);
        }

        throw $error;
    }

    dent_json_response([
        'success' => true,
        'item' => notes_1402_item_payload($deleted),
        'message' => 'کارت منبع حذف شد.',
    ]);
}

dent_error('درخواست نامعتبر است.', 404);
