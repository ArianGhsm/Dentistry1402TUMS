<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/notes_download_host.php';

const NOTES_1402_SCHEMA_VERSION = 1;
const NOTES_1402_MIN_TERM = 5;
const NOTES_1402_MAX_TERM = 12;
const NOTES_1402_SEED_BACKFILL_VERSION = 0;
const NOTES_1403_SCHEMA_VERSION = 1;
const NOTES_1403_MIN_TERM = 3;
const NOTES_1403_MAX_TERM = 12;
const NOTES_1404_SCHEMA_VERSION = 1;
const NOTES_1404_MIN_TERM = 1;
const NOTES_1404_MAX_TERM = 12;
const NOTES_PROSTHESIS_1402_SCHEMA_VERSION = 1;

function notes_1402_store_path(): string
{
    return dent_storage_path('notes/1402_terms.json');
}

function notes_1402_lock_path(): string
{
    return dent_storage_path('notes/1402_terms.lock');
}

function notes_1403_store_path(): string
{
    return dent_storage_path('notes/1403_terms.json');
}

function notes_1403_lock_path(): string
{
    return dent_storage_path('notes/1403_terms.lock');
}

function notes_1403_legacy_store_path(): string
{
    return dent_storage_path('notes/1403_archive.json');
}

function notes_1403_legacy_lock_path(): string
{
    return dent_storage_path('notes/1403_archive.lock');
}

function notes_1404_store_path(): string
{
    return dent_storage_path('notes/1404_terms.json');
}

function notes_1404_lock_path(): string
{
    return dent_storage_path('notes/1404_terms.lock');
}

function notes_prosthesis_1402_store_path(): string
{
    return dent_storage_path('notes/prosthesis_1402_terms.json');
}

function notes_prosthesis_1402_lock_path(): string
{
    return dent_storage_path('notes/prosthesis_1402_terms.lock');
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

function notes_1403_archive_template(): array
{
    return [
        'kicker' => 'ورودی ۱۴۰۳',
        'title' => 'فایل‌های موجود',
        'description' => 'کارت‌های منابع این آرشیو از پنل مالک مدیریت می‌شوند.',
        'emptyMessage' => 'برای آرشیو ۱۴۰۳ هنوز منبعی ثبت نشده است.',
    ];
}

function notes_1403_seed_items(): array
{
    return [
        [
            'id' => 1,
            'badge' => 'برنامه',
            'title' => 'برنامه امتحانات پایان‌ترم',
            'description' => 'برنامه پایان‌ترم.',
            'buttonLabel' => 'دیدن',
            'buttonUrl' => 'https://my.uupload.ir/dl/EOwg2LrM',
        ],
        [
            'id' => 2,
            'badge' => 'نورواناتومی',
            'title' => 'جزوه جامع نورواناتومی',
            'description' => 'فایل کامل.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s21.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201403/جزوه%20جامع%20نوروآناتومی.pdf',
        ],
        [
            'id' => 3,
            'badge' => 'ویروس',
            'title' => 'جزوه جامع ویروس‌شناسی پایان‌ترم',
            'description' => 'جزوه پایان‌ترم.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s21.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201403/جزوه%20جامع%20ویروس_شناسی%20پایانترم.pdf',
        ],
        [
            'id' => 4,
            'badge' => 'ژنتیک',
            'title' => 'جزوه جامع ژنتیک ۱ تا ۸',
            'description' => 'جلسه‌های ۱ تا ۸.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s21.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201403/جزوه%20جامع%20ژنتیک%20۱%20تا%20۸.pdf',
        ],
        [
            'id' => 5,
            'badge' => 'فیزیک پزشکی',
            'title' => 'جزوه جامع فیزیک پزشکی',
            'description' => 'به‌جز جلسه ۴.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s21.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201403/جزوه%20جامع%20فیزیک%20پزشکی%20بجز%20۴.pdf',
        ],
        [
            'id' => 6,
            'badge' => 'متون',
            'title' => 'تفسیر موضوعی قرآن کریم',
            'description' => 'فایل کامل کتاب.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s21.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201403/تفسیر_موضوعی_قرآن_کریم_محمدعلی_رضایی_اصفهانی.pdf',
        ],
        [
            'id' => 7,
            'badge' => 'انقلاب',
            'title' => 'کتاب صعود چهل‌ساله',
            'description' => 'فایل کامل کتاب.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s21.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201403/صعود%20چهل%20ساله%20۲.pdf',
        ],
        [
            'id' => 8,
            'badge' => 'زبان',
            'title' => 'مجموعه فایل‌های زبان عمومی',
            'description' => 'فایل‌های درس.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s31.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201403/زبان%20عمومی/زبان%20عمومی.zip',
        ],
        [
            'id' => 9,
            'badge' => 'متون',
            'title' => 'نمونه سؤال متون',
            'description' => 'فایل نمونه سؤال.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s31.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201403/خلاصه%20و%20نمونه%20سوال%20متون/نمونه%20سوال%20متون.pdf',
        ],
    ];
}

function notes_fixed_terms_term_template(int $term, string $cohortLabel, int $startingTerm): array
{
    $termFa = dent_to_fa_digits((string) $term);
    if ($term === $startingTerm) {
        return [
            'term' => $term,
            'kicker' => 'ترم ' . $termFa,
            'title' => 'فایل‌های فعلی آرشیو',
            'description' => 'همه منابع فعلی ' . $cohortLabel . ' فعلاً در این ترم قرار گرفته‌اند.',
            'emptyMessage' => 'برای ترم ' . $termFa . ' هنوز منبعی ثبت نشده است.',
        ];
    }

    return [
        'term' => $term,
        'kicker' => 'ترم ' . $termFa,
        'title' => 'آرشیو منابع ترم ' . $termFa,
        'description' => 'منابع این ترم به‌مرور اضافه می‌شوند.',
        'emptyMessage' => 'منابع ترم ' . $termFa . ' هنوز ثبت نشده است.',
    ];
}

function notes_1403_term_template(int $term): array
{
    return notes_fixed_terms_term_template($term, 'ورودی ۱۴۰۳', NOTES_1403_MIN_TERM);
}

function notes_1404_term_template(int $term): array
{
    return notes_fixed_terms_term_template($term, 'ورودی ۱۴۰۴', NOTES_1404_MIN_TERM);
}

/**
 * @param array<int, array<int, mixed>> $seedItemsByTerm
 */
function notes_fixed_terms_default_store(
    int $schemaVersion,
    int $minTerm,
    int $maxTerm,
    callable $templateFn,
    array $seedItemsByTerm = []
): array {
    $terms = [];
    $maxItemId = 0;
    for ($term = $minTerm; $term <= $maxTerm; $term++) {
        $template = $templateFn($term);
        $items = is_array($seedItemsByTerm[$term] ?? null) ? $seedItemsByTerm[$term] : [];
        $template['items'] = $items;
        foreach ($items as $itemSeed) {
            if (!is_array($itemSeed)) {
                continue;
            }
            $maxItemId = max($maxItemId, (int) ($itemSeed['id'] ?? 0));
        }
        $terms[(string) $term] = $template;
    }

    return [
        'schemaVersion' => $schemaVersion,
        'nextItemId' => max(1, $maxItemId + 1),
        'terms' => $terms,
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

function notes_1403_default_store(): array
{
    return notes_fixed_terms_default_store(
        NOTES_1403_SCHEMA_VERSION,
        NOTES_1403_MIN_TERM,
        NOTES_1403_MAX_TERM,
        'notes_1403_term_template',
        [NOTES_1403_MIN_TERM => notes_1403_seed_items()]
    );
}

function notes_1404_default_store(): array
{
    return notes_fixed_terms_default_store(
        NOTES_1404_SCHEMA_VERSION,
        NOTES_1404_MIN_TERM,
        NOTES_1404_MAX_TERM,
        'notes_1404_term_template'
    );
}

function notes_fixed_terms_normalize_store(
    array $seed,
    int $schemaVersion,
    int $minTerm,
    int $maxTerm,
    callable $templateFn
): array {
    $defaults = notes_fixed_terms_default_store($schemaVersion, $minTerm, $maxTerm, $templateFn);
    $termsSeed = is_array($seed['terms'] ?? null) ? $seed['terms'] : [];
    $normalizedTerms = [];
    $maxItemId = 0;

    for ($term = $minTerm; $term <= $maxTerm; $term++) {
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
        'schemaVersion' => $schemaVersion,
        'nextItemId' => max(1, (int) ($seed['nextItemId'] ?? 1), $maxItemId + 1),
        'terms' => $normalizedTerms,
    ];
}

function notes_1403_normalize_store(array $seed): array
{
    return notes_fixed_terms_normalize_store(
        $seed,
        NOTES_1403_SCHEMA_VERSION,
        NOTES_1403_MIN_TERM,
        NOTES_1403_MAX_TERM,
        'notes_1403_term_template'
    );
}

function notes_1404_normalize_store(array $seed): array
{
    return notes_fixed_terms_normalize_store(
        $seed,
        NOTES_1404_SCHEMA_VERSION,
        NOTES_1404_MIN_TERM,
        NOTES_1404_MAX_TERM,
        'notes_1404_term_template'
    );
}

function notes_1403_migrate_legacy_store(array $legacy): array
{
    $store = notes_fixed_terms_default_store(
        NOTES_1403_SCHEMA_VERSION,
        NOTES_1403_MIN_TERM,
        NOTES_1403_MAX_TERM,
        'notes_1403_term_template'
    );

    $archive = is_array($legacy['archive'] ?? null) ? $legacy['archive'] : [];
    $termKey = (string) NOTES_1403_MIN_TERM;
    $termSeed = $store['terms'][$termKey] ?? notes_1403_term_template(NOTES_1403_MIN_TERM);
    $termSeed['kicker'] = dent_clean_text((string) ($archive['kicker'] ?? $termSeed['kicker']), 80);
    $termSeed['title'] = dent_clean_text((string) ($archive['title'] ?? $termSeed['title']), 160);
    $termSeed['description'] = dent_clean_text((string) ($archive['description'] ?? $termSeed['description']), 800);
    $termSeed['emptyMessage'] = dent_clean_text((string) ($archive['emptyMessage'] ?? $termSeed['emptyMessage']), 400);
    $termSeed['items'] = is_array($archive['items'] ?? null) ? $archive['items'] : [];
    $store['terms'][$termKey] = $termSeed;

    return notes_1403_normalize_store($store);
}

function notes_1403_ensure_storage(): void
{
    dent_ensure_directory(dirname(notes_1403_store_path()));
    if (is_file(notes_1403_store_path())) {
        return;
    }

    $seed = notes_1403_default_store();
    if (is_file(notes_1403_legacy_store_path())) {
        $legacy = dent_read_json_file(notes_1403_legacy_store_path(), []);
        if (is_array($legacy)) {
            $seed = notes_1403_migrate_legacy_store($legacy);
        }
    }

    dent_write_json_file(notes_1403_store_path(), $seed);
}

function notes_1404_ensure_storage(): void
{
    dent_ensure_directory(dirname(notes_1404_store_path()));
    if (!is_file(notes_1404_store_path())) {
        dent_write_json_file(notes_1404_store_path(), notes_1404_default_store());
    }
}

function notes_1403_load_store_unlocked(): array
{
    $raw = dent_read_json_file(notes_1403_store_path(), notes_1403_default_store());
    if (!is_array($raw)) {
        $raw = notes_1403_default_store();
    }

    return notes_1403_normalize_store($raw);
}

function notes_1403_save_store_unlocked(array $store): void
{
    dent_write_json_file(notes_1403_store_path(), notes_1403_normalize_store($store));
}

function notes_1404_load_store_unlocked(): array
{
    $raw = dent_read_json_file(notes_1404_store_path(), notes_1404_default_store());
    if (!is_array($raw)) {
        $raw = notes_1404_default_store();
    }

    return notes_1404_normalize_store($raw);
}

function notes_1404_save_store_unlocked(array $store): void
{
    dent_write_json_file(notes_1404_store_path(), notes_1404_normalize_store($store));
}

function notes_prosthesis_1402_default_store(): array
{
    return [
        'schemaVersion' => NOTES_PROSTHESIS_1402_SCHEMA_VERSION,
        'nextTermId' => 1,
        'nextItemId' => 1,
        'terms' => [],
    ];
}

function notes_prosthesis_1402_ensure_storage(): void
{
    dent_ensure_directory(dirname(notes_prosthesis_1402_store_path()));
    if (!is_file(notes_prosthesis_1402_store_path())) {
        dent_write_json_file(notes_prosthesis_1402_store_path(), notes_prosthesis_1402_default_store());
    }
}

function notes_prosthesis_1402_parse_term_id($raw): int
{
    $termId = (int) dent_normalize_digits((string) $raw);
    if ($termId <= 0) {
        dent_error('شناسه ترم پروتز معتبر نیست.', 422);
    }

    return $termId;
}

function notes_prosthesis_1402_normalize_term_record(array $seed): ?array
{
    $id = max(0, (int) ($seed['id'] ?? 0));
    if ($id <= 0) {
        return null;
    }

    $title = dent_clean_text((string) ($seed['title'] ?? ''), 160);
    if ($title === '') {
        return null;
    }

    $kicker = dent_clean_text((string) ($seed['kicker'] ?? ''), 80);
    if ($kicker === '') {
        $kicker = 'پروتز ۱۴۰۲';
    }

    $description = dent_clean_text((string) ($seed['description'] ?? ''), 800);
    if ($description === '') {
        $description = 'منابع این ترم به‌مرور اضافه می‌شوند.';
    }

    $emptyMessage = dent_clean_text((string) ($seed['emptyMessage'] ?? ''), 400);
    if ($emptyMessage === '') {
        $emptyMessage = 'برای این ترم هنوز منبعی ثبت نشده است.';
    }

    $itemsSeed = is_array($seed['items'] ?? null) ? $seed['items'] : [];
    $items = [];
    foreach ($itemsSeed as $itemSeed) {
        if (!is_array($itemSeed)) {
            continue;
        }
        $item = notes_1402_normalize_item_record($itemSeed);
        if ($item !== null) {
            $items[] = $item;
        }
    }
    usort($items, static function (array $left, array $right): int {
        return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
    });

    return [
        'id' => $id,
        'kicker' => $kicker,
        'title' => $title,
        'description' => $description,
        'emptyMessage' => $emptyMessage,
        'items' => $items,
        'createdAt' => (string) ($seed['createdAt'] ?? dent_iso_now()),
        'updatedAt' => (string) ($seed['updatedAt'] ?? dent_iso_now()),
    ];
}

function notes_prosthesis_1402_normalize_store(array $seed): array
{
    $termsSeed = is_array($seed['terms'] ?? null) ? $seed['terms'] : [];
    $terms = [];
    $maxTermId = 0;
    $maxItemId = 0;

    foreach ($termsSeed as $termSeed) {
        if (!is_array($termSeed)) {
            continue;
        }
        $term = notes_prosthesis_1402_normalize_term_record($termSeed);
        if ($term === null) {
            continue;
        }
        $termId = (int) $term['id'];
        $maxTermId = max($maxTermId, $termId);
        foreach ($term['items'] as $item) {
            $maxItemId = max($maxItemId, (int) ($item['id'] ?? 0));
        }
        $terms[(string) $termId] = $term;
    }

    uasort($terms, static function (array $left, array $right): int {
        return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
    });

    return [
        'schemaVersion' => NOTES_PROSTHESIS_1402_SCHEMA_VERSION,
        'nextTermId' => max(1, (int) ($seed['nextTermId'] ?? 1), $maxTermId + 1),
        'nextItemId' => max(1, (int) ($seed['nextItemId'] ?? 1), $maxItemId + 1),
        'terms' => $terms,
    ];
}

function notes_prosthesis_1402_load_store_unlocked(): array
{
    $raw = dent_read_json_file(notes_prosthesis_1402_store_path(), notes_prosthesis_1402_default_store());
    if (!is_array($raw)) {
        $raw = notes_prosthesis_1402_default_store();
    }

    return notes_prosthesis_1402_normalize_store($raw);
}

function notes_prosthesis_1402_save_store_unlocked(array $store): void
{
    dent_write_json_file(notes_prosthesis_1402_store_path(), notes_prosthesis_1402_normalize_store($store));
}

function notes_prosthesis_1402_read_store(): array
{
    notes_prosthesis_1402_ensure_storage();

    $lock = fopen(notes_prosthesis_1402_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل آرشیو پروتز.', 500);
    }

    try {
        if (!flock($lock, LOCK_SH)) {
            throw new RuntimeException('Unable to acquire prosthesis notes shared lock.');
        }

        return notes_prosthesis_1402_load_store_unlocked();
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

/**
 * @template T
 * @param callable(array):T $callback
 * @return T
 */
function notes_prosthesis_1402_with_store_lock(callable $callback)
{
    notes_prosthesis_1402_ensure_storage();

    $lock = fopen(notes_prosthesis_1402_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل آرشیو پروتز.', 500);
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to acquire prosthesis notes exclusive lock.');
        }

        $store = notes_prosthesis_1402_load_store_unlocked();
        $result = $callback($store);
        notes_prosthesis_1402_save_store_unlocked($store);
        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function notes_1403_read_store(): array
{
    notes_1403_ensure_storage();

    $lock = fopen(notes_1403_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل آرشیو منابع.', 500);
    }

    $store = notes_1403_default_store();
    try {
        if (!flock($lock, LOCK_SH)) {
            throw new RuntimeException('Unable to acquire notes 1403 shared lock.');
        }

        $store = notes_1403_load_store_unlocked();
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
function notes_1403_with_store_lock(callable $callback)
{
    notes_1403_ensure_storage();

    $lock = fopen(notes_1403_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل آرشیو منابع.', 500);
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to acquire notes 1403 exclusive lock.');
        }

        $store = notes_1403_load_store_unlocked();
        $result = $callback($store);
        notes_1403_save_store_unlocked($store);
        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function notes_1404_read_store(): array
{
    notes_1404_ensure_storage();

    $lock = fopen(notes_1404_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل آرشیو منابع.', 500);
    }

    $store = notes_1404_default_store();
    try {
        if (!flock($lock, LOCK_SH)) {
            throw new RuntimeException('Unable to acquire notes 1404 shared lock.');
        }

        $store = notes_1404_load_store_unlocked();
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
function notes_1404_with_store_lock(callable $callback)
{
    notes_1404_ensure_storage();

    $lock = fopen(notes_1404_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل آرشیو منابع.', 500);
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to acquire notes 1404 exclusive lock.');
        }

        $store = notes_1404_load_store_unlocked();
        $result = $callback($store);
        notes_1404_save_store_unlocked($store);
        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
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

function notes_parse_cohort($raw): string
{
    $cohort = dent_normalize_digits(trim((string) $raw));
    if ($cohort === '') {
        return '1402';
    }

    if (!in_array($cohort, ['1402', '1403', '1404', 'prosthesis-1402'], true)) {
        dent_error('آرشیو منابع معتبر نیست.', 422);
    }

    return $cohort;
}

function notes_require_term_for_cohort(string $cohort, $raw): int
{
    if ($cohort === '1402') {
        return notes_1402_parse_term($raw);
    }

    if ($cohort === '1403') {
        $term = (int) dent_normalize_digits((string) $raw);
        if ($term < NOTES_1403_MIN_TERM || $term > NOTES_1403_MAX_TERM) {
            dent_error('شماره ترم معتبر نیست.', 422);
        }
        return $term;
    }

    if ($cohort === '1404') {
        $term = (int) dent_normalize_digits((string) $raw);
        if ($term < NOTES_1404_MIN_TERM || $term > NOTES_1404_MAX_TERM) {
            dent_error('شماره ترم معتبر نیست.', 422);
        }
        return $term;
    }

    return 0;
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
        'cohort' => '1402',
        'term' => $term,
        'id' => $term,
        'kicker' => (string) ($termRecord['kicker'] ?? ''),
        'title' => (string) ($termRecord['title'] ?? ''),
        'description' => (string) ($termRecord['description'] ?? ''),
        'emptyMessage' => (string) ($termRecord['emptyMessage'] ?? ''),
        'items' => $itemPayloads,
    ];
}

function notes_1402_terms_payload(array $store): array
{
    $terms = [];
    for ($term = NOTES_1402_MIN_TERM; $term <= NOTES_1402_MAX_TERM; $term++) {
        $terms[] = notes_1402_term_payload($store, $term);
    }

    return $terms;
}

function notes_fixed_terms_term_payload(string $cohort, array $store, int $term, callable $templateFn): array
{
    $termRecord = $store['terms'][(string) $term] ?? $templateFn($term);
    $items = is_array($termRecord['items'] ?? null) ? $termRecord['items'] : [];
    $itemPayloads = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemPayloads[] = notes_1402_item_payload($item);
    }

    return [
        'cohort' => $cohort,
        'term' => $term,
        'id' => $term,
        'kicker' => (string) ($termRecord['kicker'] ?? ''),
        'title' => (string) ($termRecord['title'] ?? ''),
        'description' => (string) ($termRecord['description'] ?? ''),
        'emptyMessage' => (string) ($termRecord['emptyMessage'] ?? ''),
        'items' => $itemPayloads,
    ];
}

function notes_1403_term_payload(array $store, int $term): array
{
    return notes_fixed_terms_term_payload('1403', $store, $term, 'notes_1403_term_template');
}

function notes_1404_term_payload(array $store, int $term): array
{
    return notes_fixed_terms_term_payload('1404', $store, $term, 'notes_1404_term_template');
}

function notes_1403_terms_payload(array $store): array
{
    $terms = [];
    for ($term = NOTES_1403_MIN_TERM; $term <= NOTES_1403_MAX_TERM; $term++) {
        $terms[] = notes_1403_term_payload($store, $term);
    }

    return $terms;
}

function notes_1404_terms_payload(array $store): array
{
    $terms = [];
    for ($term = NOTES_1404_MIN_TERM; $term <= NOTES_1404_MAX_TERM; $term++) {
        $terms[] = notes_1404_term_payload($store, $term);
    }

    return $terms;
}

function notes_prosthesis_1402_term_payload(array $termRecord): array
{
    $items = is_array($termRecord['items'] ?? null) ? $termRecord['items'] : [];
    $itemPayloads = [];
    foreach ($items as $item) {
        if (is_array($item)) {
            $itemPayloads[] = notes_1402_item_payload($item);
        }
    }

    return [
        'cohort' => 'prosthesis-1402',
        'term' => (int) ($termRecord['id'] ?? 0),
        'id' => (int) ($termRecord['id'] ?? 0),
        'kicker' => (string) ($termRecord['kicker'] ?? ''),
        'title' => (string) ($termRecord['title'] ?? ''),
        'description' => (string) ($termRecord['description'] ?? ''),
        'emptyMessage' => (string) ($termRecord['emptyMessage'] ?? ''),
        'items' => $itemPayloads,
        'createdAt' => (string) ($termRecord['createdAt'] ?? ''),
        'updatedAt' => (string) ($termRecord['updatedAt'] ?? ''),
    ];
}

function notes_prosthesis_1402_terms_payload(array $store): array
{
    $terms = [];
    foreach (($store['terms'] ?? []) as $termRecord) {
        if (is_array($termRecord)) {
            $terms[] = notes_prosthesis_1402_term_payload($termRecord);
        }
    }

    usort($terms, static function (array $left, array $right): int {
        return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
    });

    return $terms;
}

function notes_can_manage_cohort(string $cohort, ?array $viewer): bool
{
    if (!is_array($viewer)) {
        return false;
    }

    $role = (string) ($viewer['role'] ?? 'student');
    if ($role === 'owner') {
        return true;
    }

    $targetCohortKey = $cohort === '1402'
        ? dent_primary_cohort_key()
        : ($cohort === 'prosthesis-1402' ? dent_prosthesis_legacy_cohort_key() : ('dentistry-' . $cohort));

    if (dent_user_cohort_key($viewer) !== dent_clean_cohort_key($targetCohortKey)) {
        return false;
    }

    return !empty(dent_permissions_for_role($role, $targetCohortKey)['manageNotes']);
}

function notes_require_manage_cohort(string $cohort): array
{
    $viewer = dent_require_user();
    if (!notes_can_manage_cohort($cohort, $viewer)) {
        dent_error('اجازه مدیریت این آرشیو را ندارید.', 403);
    }

    return $viewer;
}

function notes_1402_next_item_id(array &$store): int
{
    $next = max(1, (int) ($store['nextItemId'] ?? 1));
    $store['nextItemId'] = $next + 1;
    return $next;
}

function notes_prosthesis_1402_next_term_id(array &$store): int
{
    $next = max(1, (int) ($store['nextTermId'] ?? 1));
    $store['nextTermId'] = $next + 1;
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

function notes_parse_item_fields_from_post(): array
{
    $badge = dent_clean_text((string) ($_POST['badge'] ?? ''), 70);
    $title = dent_clean_text((string) ($_POST['title'] ?? ''), 180);
    $description = dent_clean_text((string) ($_POST['description'] ?? ''), 600);
    $buttonLabel = dent_clean_text((string) ($_POST['buttonLabel'] ?? ''), 70);
    $buttonUrl = notes_1402_normalize_url((string) ($_POST['buttonUrl'] ?? ''));

    if ($badge === '' || $title === '' || $description === '' || $buttonLabel === '' || $buttonUrl === '') {
        dent_error('همه فیلدهای کارت باید کامل و معتبر باشند.', 422);
    }

    return [
        'badge' => $badge,
        'title' => $title,
        'description' => $description,
        'buttonLabel' => $buttonLabel,
        'buttonUrl' => $buttonUrl,
    ];
}

function notes_download_host_scope_root_for_cohort(string $cohort): string
{
    if ($cohort === '1403' || $cohort === '1404') {
        return $cohort;
    }
    if ($cohort === 'prosthesis-1402') {
        return 'prosthesis-1402';
    }

    return '1402';
}

function notes_download_host_scope_for_viewer(string $cohort, array $viewer): ?string
{
    $role = (string) ($viewer['role'] ?? 'student');
    if ($role === 'owner') {
        return null;
    }

    return notes_download_host_scope_root_for_cohort($cohort);
}

function notes_download_host_manager_url(string $defaultPath, string $cohort): string
{
    $query = [];
    if ($defaultPath !== '') {
        $query['path'] = $defaultPath;
    }
    if ($cohort !== '') {
        $query['cohort'] = $cohort;
    }

    return '/notes/files/' . ($query === [] ? '' : ('?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986)));
}

function notes_download_host_term_payload(string $cohort, int $term, array $termPayload, ?array $viewer): array
{
    $isEnabled = notes_download_host_is_enabled();
    $canManage = notes_can_manage_cohort($cohort, $viewer);
    $role = is_array($viewer) ? (string) ($viewer['role'] ?? 'student') : 'guest';
    $defaultRelativeDir = notes_download_host_default_relative_dir(
        $cohort,
        $term,
        (string) ($termPayload['title'] ?? '')
    );

    return [
        'enabled' => $isEnabled,
        'publicBaseUrl' => notes_download_host_public_base_url(),
        'defaultRelativeDir' => $defaultRelativeDir,
        'scopeRoot' => notes_download_host_scope_root_for_cohort($cohort),
        'canUpload' => $canManage && $isEnabled,
        'canManageAllRoots' => $role === 'owner' && $isEnabled,
        'managerUrl' => notes_download_host_manager_url($defaultRelativeDir, $cohort),
    ];
}

function notes_parse_prosthesis_term_fields_from_post(): array
{
    $title = dent_clean_text((string) ($_POST['title'] ?? ''), 160);
    $kicker = dent_clean_text((string) ($_POST['kicker'] ?? ''), 80);
    $description = dent_clean_text((string) ($_POST['description'] ?? ''), 800);
    $emptyMessage = dent_clean_text((string) ($_POST['emptyMessage'] ?? ''), 400);

    if ($title === '') {
        dent_error('عنوان ترم پروتز الزامی است.', 422);
    }

    return [
        'title' => $title,
        'kicker' => $kicker !== '' ? $kicker : 'پروتز ۱۴۰۲',
        'description' => $description !== '' ? $description : 'منابع این ترم به‌مرور اضافه می‌شوند.',
        'emptyMessage' => $emptyMessage !== '' ? $emptyMessage : 'برای این ترم هنوز منبعی ثبت نشده است.',
    ];
}

function notes_new_item(array &$store, array $fields): array
{
    return array_merge($fields, [
        'id' => notes_1402_next_item_id($store),
        'createdAt' => dent_iso_now(),
        'updatedAt' => dent_iso_now(),
    ]);
}

function notes_update_item_record(array $current, array $fields): array
{
    return array_merge($current, $fields, [
        'updatedAt' => dent_iso_now(),
    ]);
}

function notes_1402_add_item(int $term, array $fields): array
{
    return notes_1402_with_store_lock(static function (array &$store) use ($term, $fields): array {
        $termKey = (string) $term;
        if (!isset($store['terms'][$termKey]) || !is_array($store['terms'][$termKey])) {
            $store['terms'][$termKey] = notes_1402_term_template($term);
            $store['terms'][$termKey]['items'] = [];
        }

        if (!is_array($store['terms'][$termKey]['items'] ?? null)) {
            $store['terms'][$termKey]['items'] = [];
        }

        $item = notes_new_item($store, $fields);
        array_unshift($store['terms'][$termKey]['items'], $item);
        return $item;
    });
}

function notes_fixed_terms_add_item(string $cohort, int $term, array $fields): array
{
    $withLock = $cohort === '1403' ? 'notes_1403_with_store_lock' : 'notes_1404_with_store_lock';
    $template = $cohort === '1403' ? 'notes_1403_term_template' : 'notes_1404_term_template';

    return $withLock(static function (array &$store) use ($term, $fields, $template): array {
        $termKey = (string) $term;
        if (!isset($store['terms'][$termKey]) || !is_array($store['terms'][$termKey])) {
            $store['terms'][$termKey] = $template($term);
            $store['terms'][$termKey]['items'] = [];
        }
        if (!is_array($store['terms'][$termKey]['items'] ?? null)) {
            $store['terms'][$termKey]['items'] = [];
        }

        $item = notes_new_item($store, $fields);
        array_unshift($store['terms'][$termKey]['items'], $item);
        return $item;
    });
}

function notes_1403_add_item(int $term, array $fields): array
{
    return notes_fixed_terms_add_item('1403', $term, $fields);
}

function notes_1404_add_item(int $term, array $fields): array
{
    return notes_fixed_terms_add_item('1404', $term, $fields);
}

function notes_prosthesis_1402_add_term(array $fields): array
{
    return notes_prosthesis_1402_with_store_lock(static function (array &$store) use ($fields): array {
        $termId = notes_prosthesis_1402_next_term_id($store);
        $term = array_merge($fields, [
            'id' => $termId,
            'items' => [],
            'createdAt' => dent_iso_now(),
            'updatedAt' => dent_iso_now(),
        ]);
        $store['terms'][(string) $termId] = $term;
        return $term;
    });
}

function notes_prosthesis_1402_edit_term(int $termId, array $fields): array
{
    return notes_prosthesis_1402_with_store_lock(static function (array &$store) use ($termId, $fields): array {
        $termKey = (string) $termId;
        if (!is_array($store['terms'][$termKey] ?? null)) {
            throw new RuntimeException('term-not-found');
        }

        $store['terms'][$termKey] = array_merge($store['terms'][$termKey], $fields, [
            'id' => $termId,
            'updatedAt' => dent_iso_now(),
        ]);
        return $store['terms'][$termKey];
    });
}

function notes_prosthesis_1402_delete_term(int $termId): array
{
    return notes_prosthesis_1402_with_store_lock(static function (array &$store) use ($termId): array {
        $termKey = (string) $termId;
        if (!is_array($store['terms'][$termKey] ?? null)) {
            throw new RuntimeException('term-not-found');
        }

        $deleted = $store['terms'][$termKey];
        unset($store['terms'][$termKey]);
        return $deleted;
    });
}

function notes_prosthesis_1402_add_item(int $termId, array $fields): array
{
    return notes_prosthesis_1402_with_store_lock(static function (array &$store) use ($termId, $fields): array {
        $termKey = (string) $termId;
        if (!is_array($store['terms'][$termKey] ?? null)) {
            throw new RuntimeException('term-not-found');
        }
        if (!is_array($store['terms'][$termKey]['items'] ?? null)) {
            $store['terms'][$termKey]['items'] = [];
        }

        $item = notes_new_item($store, $fields);
        array_unshift($store['terms'][$termKey]['items'], $item);
        $store['terms'][$termKey]['updatedAt'] = dent_iso_now();
        return $item;
    });
}

function notes_1402_edit_item(int $term, int $itemId, array $fields): array
{
    return notes_1402_with_store_lock(static function (array &$store) use ($term, $itemId, $fields): array {
        $termKey = (string) $term;
        if (!is_array($store['terms'][$termKey]['items'] ?? null)) {
            throw new RuntimeException('item-not-found');
        }

        foreach ($store['terms'][$termKey]['items'] as $index => $item) {
            if ((int) ($item['id'] ?? 0) !== $itemId) {
                continue;
            }

            $updated = notes_update_item_record(is_array($item) ? $item : [], $fields);
            $store['terms'][$termKey]['items'][$index] = $updated;
            return $updated;
        }

        throw new RuntimeException('item-not-found');
    });
}

function notes_fixed_terms_edit_item(string $cohort, int $term, int $itemId, array $fields): array
{
    $withLock = $cohort === '1403' ? 'notes_1403_with_store_lock' : 'notes_1404_with_store_lock';

    return $withLock(static function (array &$store) use ($term, $itemId, $fields): array {
        $termKey = (string) $term;
        if (!is_array($store['terms'][$termKey]['items'] ?? null)) {
            throw new RuntimeException('item-not-found');
        }

        foreach ($store['terms'][$termKey]['items'] as $index => $item) {
            if ((int) ($item['id'] ?? 0) !== $itemId) {
                continue;
            }

            $updated = notes_update_item_record(is_array($item) ? $item : [], $fields);
            $store['terms'][$termKey]['items'][$index] = $updated;
            return $updated;
        }

        throw new RuntimeException('item-not-found');
    });
}

function notes_1403_edit_item(int $term, int $itemId, array $fields): array
{
    return notes_fixed_terms_edit_item('1403', $term, $itemId, $fields);
}

function notes_1404_edit_item(int $term, int $itemId, array $fields): array
{
    return notes_fixed_terms_edit_item('1404', $term, $itemId, $fields);
}

function notes_prosthesis_1402_edit_item(int $termId, int $itemId, array $fields): array
{
    return notes_prosthesis_1402_with_store_lock(static function (array &$store) use ($termId, $itemId, $fields): array {
        $termKey = (string) $termId;
        if (!is_array($store['terms'][$termKey]['items'] ?? null)) {
            throw new RuntimeException('item-not-found');
        }

        foreach ($store['terms'][$termKey]['items'] as $index => $item) {
            if ((int) ($item['id'] ?? 0) !== $itemId) {
                continue;
            }

            $updated = notes_update_item_record(is_array($item) ? $item : [], $fields);
            $store['terms'][$termKey]['items'][$index] = $updated;
            $store['terms'][$termKey]['updatedAt'] = dent_iso_now();
            return $updated;
        }

        throw new RuntimeException('item-not-found');
    });
}

function notes_1402_delete_item(int $term, int $itemId): array
{
    return notes_1402_with_store_lock(static function (array &$store) use ($term, $itemId): array {
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
}

function notes_fixed_terms_delete_item(string $cohort, int $term, int $itemId): array
{
    $withLock = $cohort === '1403' ? 'notes_1403_with_store_lock' : 'notes_1404_with_store_lock';

    return $withLock(static function (array &$store) use ($term, $itemId): array {
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
}

function notes_1403_delete_item(int $term, int $itemId): array
{
    return notes_fixed_terms_delete_item('1403', $term, $itemId);
}

function notes_1404_delete_item(int $term, int $itemId): array
{
    return notes_fixed_terms_delete_item('1404', $term, $itemId);
}

function notes_prosthesis_1402_delete_item(int $termId, int $itemId): array
{
    return notes_prosthesis_1402_with_store_lock(static function (array &$store) use ($termId, $itemId): array {
        $termKey = (string) $termId;
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
            $store['terms'][$termKey]['updatedAt'] = dent_iso_now();
            return $deleted;
        }

        throw new RuntimeException('item-not-found');
    });
}

$action = dent_request_action();

if ($action === 'terms') {
    notes_1402_require_method(['GET']);

    $cohort = notes_parse_cohort($_GET['cohort'] ?? '1402');
    $viewer = dent_current_user();
    if ($cohort === 'prosthesis-1402') {
        $terms = notes_prosthesis_1402_terms_payload(notes_prosthesis_1402_read_store());
    } elseif ($cohort === '1403') {
        $terms = notes_1403_terms_payload(notes_1403_read_store());
    } elseif ($cohort === '1404') {
        $terms = notes_1404_terms_payload(notes_1404_read_store());
    } else {
        $terms = notes_1402_terms_payload(notes_1402_read_store());
    }
    dent_json_response([
        'success' => true,
        'terms' => $terms,
        'canManage' => notes_can_manage_cohort($cohort, $viewer),
    ]);
}

if ($action === 'addTerm') {
    notes_1402_require_method(['POST']);
    $cohort = notes_parse_cohort($_POST['cohort'] ?? 'prosthesis-1402');
    if ($cohort !== 'prosthesis-1402') {
        dent_error('افزودن ترم فقط برای آرشیو پروتز فعال است.', 422);
    }
    notes_require_manage_cohort($cohort);

    $created = notes_prosthesis_1402_add_term(notes_parse_prosthesis_term_fields_from_post());
    dent_json_response([
        'success' => true,
        'term' => notes_prosthesis_1402_term_payload($created),
        'message' => 'ترم پروتز ثبت شد.',
    ]);
}

if ($action === 'editTerm') {
    notes_1402_require_method(['POST']);
    $cohort = notes_parse_cohort($_POST['cohort'] ?? 'prosthesis-1402');
    if ($cohort !== 'prosthesis-1402') {
        dent_error('ویرایش ترم فقط برای آرشیو پروتز فعال است.', 422);
    }
    notes_require_manage_cohort($cohort);
    $termId = notes_prosthesis_1402_parse_term_id($_POST['term'] ?? '');

    try {
        $updated = notes_prosthesis_1402_edit_term($termId, notes_parse_prosthesis_term_fields_from_post());
    } catch (RuntimeException $error) {
        if ($error->getMessage() === 'term-not-found') {
            dent_error('ترم موردنظر پیدا نشد.', 404);
        }
        throw $error;
    }

    dent_json_response([
        'success' => true,
        'term' => notes_prosthesis_1402_term_payload($updated),
        'message' => 'ترم پروتز ذخیره شد.',
    ]);
}

if ($action === 'deleteTerm') {
    notes_1402_require_method(['POST']);
    $cohort = notes_parse_cohort($_POST['cohort'] ?? 'prosthesis-1402');
    if ($cohort !== 'prosthesis-1402') {
        dent_error('حذف ترم فقط برای آرشیو پروتز فعال است.', 422);
    }
    notes_require_manage_cohort($cohort);
    $termId = notes_prosthesis_1402_parse_term_id($_POST['term'] ?? '');

    try {
        $deleted = notes_prosthesis_1402_delete_term($termId);
    } catch (RuntimeException $error) {
        if ($error->getMessage() === 'term-not-found') {
            dent_error('ترم موردنظر پیدا نشد.', 404);
        }
        throw $error;
    }

    dent_json_response([
        'success' => true,
        'term' => notes_prosthesis_1402_term_payload($deleted),
        'message' => 'ترم پروتز حذف شد.',
    ]);
}

if ($action === 'term') {
    notes_1402_require_method(['GET']);

    $cohort = notes_parse_cohort($_GET['cohort'] ?? '1402');
    $term = $cohort === 'prosthesis-1402'
        ? notes_prosthesis_1402_parse_term_id($_GET['term'] ?? '')
        : notes_require_term_for_cohort($cohort, $_GET['term'] ?? '');
    $viewer = dent_current_user();
    $termPayload = null;

    if ($cohort === '1403') {
        $termPayload = notes_1403_term_payload(notes_1403_read_store(), $term);
    } elseif ($cohort === '1404') {
        $termPayload = notes_1404_term_payload(notes_1404_read_store(), $term);
    } elseif ($cohort === 'prosthesis-1402') {
        $store = notes_prosthesis_1402_read_store();
        $termRecord = $store['terms'][(string) $term] ?? null;
        if (!is_array($termRecord)) {
            dent_error('ترم موردنظر پیدا نشد.', 404);
        }
        $termPayload = notes_prosthesis_1402_term_payload($termRecord);
    } else {
        $termPayload = notes_1402_term_payload(notes_1402_read_store(), $term);
    }

    dent_json_response([
        'success' => true,
        'term' => $termPayload,
        'canManage' => notes_can_manage_cohort($cohort, $viewer),
        'downloadHost' => notes_download_host_term_payload($cohort, $term, $termPayload, $viewer),
    ]);
}

if ($action === 'downloadHostBrowse') {
    notes_1402_require_method(['GET']);
    $viewer = dent_require_user();
    $cohort = notes_parse_cohort($_GET['cohort'] ?? '');
    if ($cohort === '') {
        $cohort = '1402';
    }
    if (!notes_can_manage_cohort($cohort, $viewer)) {
        dent_error('اجازه مدیریت فایل‌های منابع را ندارید.', 403);
    }

    $scopeRoot = notes_download_host_scope_for_viewer($cohort, $viewer);
    $path = trim((string) ($_GET['path'] ?? ''));
    if ($path === '' && $scopeRoot !== null) {
        $path = $scopeRoot;
    }

    $payload = notes_download_host_browse($path, $scopeRoot);
    dent_json_response([
        'success' => true,
        'browse' => $payload,
        'downloadHost' => [
            'enabled' => notes_download_host_is_enabled(),
            'publicBaseUrl' => notes_download_host_public_base_url(),
            'scopeRoot' => $scopeRoot,
            'canManageAllRoots' => (string) ($viewer['role'] ?? '') === 'owner',
        ],
    ]);
}

if ($action === 'downloadHostUpload') {
    notes_1402_require_method(['POST']);
    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    $viewer = notes_require_manage_cohort($cohort);
    $scopeRoot = notes_download_host_scope_for_viewer($cohort, $viewer);
    $relativeDir = trim((string) ($_POST['path'] ?? $_POST['relativeDir'] ?? ''));
    if ($relativeDir === '') {
        $term = $cohort === 'prosthesis-1402'
            ? notes_prosthesis_1402_parse_term_id($_POST['term'] ?? '1')
            : notes_require_term_for_cohort($cohort, $_POST['term'] ?? '');
        $relativeDir = notes_download_host_default_relative_dir($cohort, $term, trim((string) ($_POST['termTitle'] ?? '')));
    }

    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        dent_error('فایل برای آپلود ارسال نشد.', 422);
    }

    $desiredName = trim((string) ($_POST['fileName'] ?? ''));
    $uploaded = notes_download_host_upload_file($relativeDir, $_FILES['file'], $desiredName, $scopeRoot);
    dent_json_response([
        'success' => true,
        'file' => $uploaded,
        'message' => $uploaded['message'] ?? 'فایل روی هاست دانلود ذخیره شد.',
    ]);
}

if ($action === 'downloadHostCreateDir') {
    notes_1402_require_method(['POST']);
    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    $viewer = notes_require_manage_cohort($cohort);
    $scopeRoot = notes_download_host_scope_for_viewer($cohort, $viewer);
    $parentPath = trim((string) ($_POST['path'] ?? ''));
    if ($parentPath === '' && $scopeRoot !== null) {
        $parentPath = $scopeRoot;
    }
    $created = notes_download_host_create_dir($parentPath, (string) ($_POST['name'] ?? ''), $scopeRoot);
    dent_json_response([
        'success' => true,
        'entry' => $created,
        'message' => 'پوشه جدید روی هاست دانلود ساخته شد.',
    ]);
}

if ($action === 'downloadHostRenameEntry') {
    notes_1402_require_method(['POST']);
    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    $viewer = notes_require_manage_cohort($cohort);
    $scopeRoot = notes_download_host_scope_for_viewer($cohort, $viewer);
    $renamed = notes_download_host_rename_entry(
        (string) ($_POST['path'] ?? ''),
        (string) ($_POST['name'] ?? ''),
        $scopeRoot
    );
    dent_json_response([
        'success' => true,
        'entry' => $renamed,
        'message' => 'نام فایل یا پوشه تغییر کرد.',
    ]);
}

if ($action === 'downloadHostDeleteEntry') {
    notes_1402_require_method(['POST']);
    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    $viewer = notes_require_manage_cohort($cohort);
    $scopeRoot = notes_download_host_scope_for_viewer($cohort, $viewer);
    $deleted = notes_download_host_delete_entry(
        (string) ($_POST['path'] ?? ''),
        (string) ($_POST['entryType'] ?? 'file'),
        $scopeRoot
    );
    dent_json_response([
        'success' => true,
        'entry' => $deleted,
        'message' => 'آیتم انتخابی از هاست دانلود حذف شد.',
    ]);
}

if ($action === 'addItem') {
    notes_1402_require_method(['POST']);

    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    notes_require_manage_cohort($cohort);
    $term = $cohort === 'prosthesis-1402'
        ? notes_prosthesis_1402_parse_term_id($_POST['term'] ?? '')
        : notes_require_term_for_cohort($cohort, $_POST['term'] ?? '');
    $fields = notes_parse_item_fields_from_post();
    try {
        if ($cohort === '1403') {
            $created = notes_1403_add_item($term, $fields);
        } elseif ($cohort === '1404') {
            $created = notes_1404_add_item($term, $fields);
        } elseif ($cohort === 'prosthesis-1402') {
            $created = notes_prosthesis_1402_add_item($term, $fields);
        } else {
            $created = notes_1402_add_item($term, $fields);
        }
    } catch (RuntimeException $error) {
        if ($error->getMessage() === 'term-not-found') {
            dent_error('ترم موردنظر پیدا نشد.', 404);
        }
        throw $error;
    }

    dent_json_response([
        'success' => true,
        'item' => notes_1402_item_payload($created),
        'message' => 'کارت منبع جدید با موفقیت ثبت شد.',
    ]);
}

if ($action === 'editItem') {
    notes_1402_require_method(['POST']);

    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    notes_require_manage_cohort($cohort);
    $term = $cohort === 'prosthesis-1402'
        ? notes_prosthesis_1402_parse_term_id($_POST['term'] ?? '')
        : notes_require_term_for_cohort($cohort, $_POST['term'] ?? '');
    $itemId = notes_1402_parse_item_id($_POST['itemId'] ?? '');
    $fields = notes_parse_item_fields_from_post();

    try {
        if ($cohort === '1403') {
            $updated = notes_1403_edit_item($term, $itemId, $fields);
        } elseif ($cohort === '1404') {
            $updated = notes_1404_edit_item($term, $itemId, $fields);
        } elseif ($cohort === 'prosthesis-1402') {
            $updated = notes_prosthesis_1402_edit_item($term, $itemId, $fields);
        } else {
            $updated = notes_1402_edit_item($term, $itemId, $fields);
        }
    } catch (RuntimeException $error) {
        if ($error->getMessage() === 'term-not-found') {
            dent_error('ترم موردنظر پیدا نشد.', 404);
        }
        if ($error->getMessage() === 'item-not-found') {
            dent_error('کارت موردنظر پیدا نشد.', 404);
        }

        throw $error;
    }

    dent_json_response([
        'success' => true,
        'item' => notes_1402_item_payload($updated),
        'message' => 'کارت منبع ذخیره شد.',
    ]);
}

if ($action === 'deleteItem') {
    notes_1402_require_method(['POST']);

    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    notes_require_manage_cohort($cohort);
    $term = $cohort === 'prosthesis-1402'
        ? notes_prosthesis_1402_parse_term_id($_POST['term'] ?? '')
        : notes_require_term_for_cohort($cohort, $_POST['term'] ?? '');
    $itemId = notes_1402_parse_item_id($_POST['itemId'] ?? '');

    try {
        if ($cohort === '1403') {
            $deleted = notes_1403_delete_item($term, $itemId);
        } elseif ($cohort === '1404') {
            $deleted = notes_1404_delete_item($term, $itemId);
        } elseif ($cohort === 'prosthesis-1402') {
            $deleted = notes_prosthesis_1402_delete_item($term, $itemId);
        } else {
            $deleted = notes_1402_delete_item($term, $itemId);
        }
    } catch (RuntimeException $error) {
        if ($error->getMessage() === 'term-not-found') {
            dent_error('ترم موردنظر پیدا نشد.', 404);
        }
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
