<?php

declare(strict_types=1);

putenv('DENT_AUTH_SECRET_KEY=' . base64_encode(str_repeat('endo-tools-test-', 3)));

require_once __DIR__ . '/../public_html/api/payments_store.php';
require_once __DIR__ . '/../public_html/api/endosim_catalog.php';

restore_error_handler();
restore_exception_handler();

$failures = 0;
$total = 0;

function endo_tools_assert(bool $condition, string $label): void
{
    global $failures, $total;
    $total++;
    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }
    $failures++;
    echo "FAIL: {$label}\n";
}

$catalog = endo_tools_catalog();
endo_tools_assert(count($catalog) === 388, 'catalog contains six core tools plus every priced bur variant');

$prices = [];
$slugs = [];
$diamondCount = 0;
$carbideCount = 0;
$taiwanCount = 0;
$belgiumCount = 0;
foreach ($catalog as $product) {
    $slug = (string) ($product['slug'] ?? '');
    $slugs[] = $slug;
    $prices[$slug] = (int) ($product['price'] ?? 0);
    endo_tools_assert(str_starts_with($slug, ENDO_TOOLS_SLUG_PREFIX), "catalog slug uses replacement prefix: {$slug}");
    endo_tools_assert(!str_starts_with($slug, ENDOSIM_SLUG_PREFIX), "catalog excludes legacy tooth slug: {$slug}");
    $image = (string) ($product['heroImage'] ?? '');
    endo_tools_assert($image !== '' && is_file(__DIR__ . '/../public_html' . $image), "product image exists: {$slug}");

    $kind = (string) ($product['kind'] ?? '');
    if ($kind === 'Diamond Burs') {
        $diamondCount++;
    } elseif ($kind === 'Carbide Burs') {
        $carbideCount++;
    }
    if (str_ends_with($slug, '-taiwan')) {
        $taiwanCount++;
        if ($kind === 'Diamond Burs' || $kind === 'Carbide Burs') {
            endo_tools_assert((int) ($product['price'] ?? 0) === 2050000, "Taiwan bur price is 205,000 toman: {$slug}");
        }
    } elseif (str_ends_with($slug, '-belgium')) {
        $belgiumCount++;
        if ($kind === 'Diamond Burs' || $kind === 'Carbide Burs') {
            endo_tools_assert((int) ($product['price'] ?? 0) === 2450000, "Belgium bur price is 245,000 toman: {$slug}");
        }
    }
}

endo_tools_assert(count(array_unique($slugs)) === count($slugs), 'all catalog slugs are unique');
endo_tools_assert($diamondCount === 298, '149 FG Diamond Bur codes/sizes are available in both Taiwan and Belgium variants');
endo_tools_assert($carbideCount === 84, '42 Carbide Bur codes/sizes are available in both Taiwan and Belgium variants');
endo_tools_assert($taiwanCount === 191, 'all 191 catalog bur specs have a Taiwan variant');
endo_tools_assert($belgiumCount === 191, 'all 191 catalog bur specs have a Belgium variant');
endo_tools_assert(count(array_filter($prices, static fn(int $price): bool => $price > 0)) === 388, 'every active catalog product has a positive price');

$coreExpected = [
    'endo-tool-fine-plugger-double' => 7450000,
    'endo-tool-fine-plugger-single' => 7850000,
    'endo-tool-fine-plugger-single-premium' => 9300000,
    'endo-tool-scalpel-handle-3-black' => 2900000,
    'endo-tool-scalpel-handle-3' => 4000000,
    'endo-tool-scalpel-handle-3-premium' => 4750000,
];
foreach ($coreExpected as $slug => $expectedPrice) {
    endo_tools_assert(($prices[$slug] ?? 0) === $expectedPrice, "core tool price remains correct: {$slug}");
}

$premiumScalpel = null;
foreach ($catalog as $product) {
    if (($product['slug'] ?? '') === 'endo-tool-scalpel-handle-3-premium') {
        $premiumScalpel = $product;
        break;
    }
}
endo_tools_assert(is_array($premiumScalpel), 'premium scalpel product exists');
endo_tools_assert(count($premiumScalpel['gallery'] ?? []) === 1, 'premium scalpel keeps its second real product image');
foreach (($premiumScalpel['gallery'] ?? []) as $image) {
    endo_tools_assert(is_file(__DIR__ . '/../public_html' . $image), 'premium scalpel gallery image exists');
}

$bur = null;
foreach ($catalog as $product) {
    if (($product['slug'] ?? '') === 'endo-tool-safe-end-bur-508-taiwan') {
        $bur = $product;
        break;
    }
}
endo_tools_assert(is_array($bur), 'Safe End catalog item exists');
endo_tools_assert(str_starts_with((string) ($bur['title'] ?? ''), 'Safe End 508'), 'Safe End name remains English exactly as catalog terminology');
endo_tools_assert(!str_contains((string) ($bur['title'] ?? ''), 'ایمن انتهایی'), 'Safe End title is not translated to Persian');
$burSpecs = [];
foreach (($bur['specifications'] ?? []) as $spec) {
    $burSpecs[(string) ($spec['label'] ?? '')] = (string) ($spec['value'] ?? '');
}
endo_tools_assert(($burSpecs['شکل'] ?? '') === '508', 'Safe End shape matches catalog');
endo_tools_assert(($burSpecs['سایز'] ?? '') === '016', 'Safe End size matches catalog');
endo_tools_assert(($burSpecs['طول سر'] ?? '') === '9.0 mm', 'Safe End head length matches catalog');
endo_tools_assert(($burSpecs['کد سفارش'] ?? '') === 'SD161', 'Safe End order code matches catalog');
endo_tools_assert(($burSpecs['بسته'] ?? '') === '۵ عددی', 'Safe End is sold as a five-piece pack');
$historicalOrder = [
    'id' => 44,
    'item_id' => 12,
    'item_slug' => 'endosim-ct-6ul-g400',
    'status' => PAYMENTS_ORDER_STATUS_SUCCESS,
    'quantity' => 2,
];
$store = [
    'schemaVersion' => PAYMENTS_SCHEMA_VERSION,
    'nextItemId' => 100,
    'items' => [
        [
            'id' => 12,
            'slug' => 'endosim-ct-6ul-g400',
            'category' => ENDOSIM_CATEGORY,
            'title' => 'دندان آموزشی قدیمی',
            'price' => 1000000,
            'status' => PAYMENTS_ITEM_STATUS_ACTIVE,
            'sold_count' => 7,
            'created_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-01T00:00:00+00:00',
        ],
        [
            'id' => 13,
            'slug' => 'endo-tool-obsolete',
            'category' => ENDO_TOOLS_CATEGORY,
            'title' => 'محصول قدیمی ابزار',
            'price' => 1000000,
            'status' => PAYMENTS_ITEM_STATUS_ACTIVE,
            'sold_count' => 0,
            'created_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-01T00:00:00+00:00',
        ],
    ],
    'orders' => [$historicalOrder],
];

$first = endo_tools_import_into_store($store);
endo_tools_assert(($first['created'] ?? -1) === 388, 'first import creates all 388 active catalog products');
endo_tools_assert(($first['updated'] ?? -1) === 0, 'first import does not report updates');
endo_tools_assert(($first['retired'] ?? -1) === 2, 'first import retires legacy tooth and stale tool');
endo_tools_assert(($store['orders'][0] ?? null) === $historicalOrder, 'historical order record is preserved');

$legacyIndex = payments_find_item_index_by_slug($store, 'endosim-ct-6ul-g400');
endo_tools_assert($legacyIndex >= 0, 'legacy tooth record remains in store');
endo_tools_assert(($store['items'][$legacyIndex]['status'] ?? '') === PAYMENTS_ITEM_STATUS_INACTIVE, 'legacy tooth is inactive rather than deleted');
endo_tools_assert((int) ($store['items'][$legacyIndex]['id'] ?? 0) === 12, 'legacy tooth keeps original item id');
endo_tools_assert((int) ($store['items'][$legacyIndex]['sold_count'] ?? 0) === 7, 'legacy tooth keeps historical sold count');

foreach ($slugs as $slug) {
    $index = payments_find_item_index_by_slug($store, $slug);
    endo_tools_assert($index >= 0, "new product exists after import: {$slug}");
    endo_tools_assert(($store['items'][$index]['status'] ?? '') === PAYMENTS_ITEM_STATUS_ACTIVE, "new product is active: {$slug}");
    endo_tools_assert(($store['items'][$index]['category'] ?? '') === ENDO_TOOLS_CATEGORY, "new product has expected category: {$slug}");
}

$safeEndIndex = payments_find_item_index_by_slug($store, 'endo-tool-safe-end-bur-508-taiwan');
endo_tools_assert(
    $safeEndIndex >= 0 && str_contains((string) ($store['items'][$safeEndIndex]['support_note'] ?? ''), 'مطابق کاتالوگ'),
    'imported bur keeps catalog-verification support metadata'
);

$second = endo_tools_import_into_store($store);
endo_tools_assert(($second['created'] ?? -1) === 0, 'second import is idempotent for created products');
endo_tools_assert(($second['updated'] ?? -1) === 388, 'second import updates exactly all 388 catalog products');
endo_tools_assert(($second['retired'] ?? -1) === 0, 'second import does not retire products twice');
endo_tools_assert(($store['orders'][0] ?? null) === $historicalOrder, 'reimport still preserves historical orders');

echo "\n{$total} assertions, {$failures} failure(s).\n";
exit($failures === 0 ? 0 : 1);
