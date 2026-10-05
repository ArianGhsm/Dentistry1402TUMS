<?php

declare(strict_types=1);

/**
 * Source of truth for the endodontic-tools group order.
 *
 * Public prices are supplied in toman by the seller, but the payments store
 * persists rials. Every price below already includes the requested
 * 10,000-toman retail increment.
 *
 * The legacy Endosim tooth catalog used this file and its import action.
 * Compatibility aliases are intentionally kept so old owner links do not
 * resurrect the retired tooth products.
 */

const ENDO_TOOLS_CATEGORY = 'endodontic_tools';
const ENDO_TOOLS_SLUG_PREFIX = 'endo-tool-';
const ENDOSIM_CATEGORY = 'endodontic_models';
const ENDOSIM_SLUG_PREFIX = 'endosim-';

function endo_tools_catalog(): array
{
    return [
        [
            'slug' => 'endo-tool-fine-plugger-double',
            'title' => 'پلاگر با نوک ظریف — دوسر',
            'kind' => 'پلاگر',
            'price' => 7450000,
            'heroImage' => '/assets/images/buy/endo-tools/plugger-double.svg',
            'shortDescription' => 'مدل اقتصادی و دانشجویی؛ دوسر با نوک ظریف برای استفاده روزمره.',
            'fullDescription' => 'پلاگر دوسر با نوک ظریف، از مدل‌های اقتصادی و دانشجویی معرفی‌شده در فهرست فروشنده.',
            'specifications' => [
                ['label' => 'دسته', 'value' => 'پلاگر'],
                ['label' => 'ساختار', 'value' => 'دوسر'],
                ['label' => 'رده', 'value' => 'اقتصادی و دانشجویی'],
            ],
            'maxQuantity' => 10,
        ],
        [
            'slug' => 'endo-tool-fine-plugger-single',
            'title' => 'پلاگر با نوک ظریف — تک‌سر',
            'kind' => 'پلاگر',
            'price' => 7850000,
            'heroImage' => '/assets/images/buy/endo-tools/plugger-single.svg',
            'shortDescription' => 'مدل تک‌سر با کیفیت قابل قبول برای استفاده روزمره.',
            'fullDescription' => 'پلاگر تک‌سر با نوک ظریف؛ یک رده بالاتر از مدل اقتصادی و مناسب استفاده روزمره.',
            'specifications' => [
                ['label' => 'دسته', 'value' => 'پلاگر'],
                ['label' => 'ساختار', 'value' => 'تک‌سر'],
                ['label' => 'رده', 'value' => 'روزمره'],
            ],
            'maxQuantity' => 10,
        ],
        [
            'slug' => 'endo-tool-fine-plugger-single-premium',
            'title' => 'پلاگر با نوک ظریف — تک‌سر رده بالاتر',
            'kind' => 'پلاگر',
            'price' => 9300000,
            'heroImage' => '/assets/images/buy/endo-tools/plugger-single.svg',
            'shortDescription' => 'مدل تک‌سر رده بالاتر برای کسانی که کیفیت ساخت بالاتری می‌خواهند.',
            'fullDescription' => 'مدل تک‌سر رده بالاتر از گزینه‌های معرفی‌شده در فهرست فروشنده.',
            'specifications' => [
                ['label' => 'دسته', 'value' => 'پلاگر'],
                ['label' => 'ساختار', 'value' => 'تک‌سر'],
                ['label' => 'رده', 'value' => 'بالاتر'],
            ],
            'maxQuantity' => 10,
        ],
        [
            'slug' => 'endo-tool-scalpel-handle-3-black',
            'title' => 'دسته بیستوری شماره ۳ — مدل مشکی',
            'kind' => 'دسته بیستوری',
            'price' => 2900000,
            'heroImage' => '/assets/images/buy/endo-tools/scalpel-handle-3-black.webp',
            'gallery' => [],
            'shortDescription' => 'مدل مشکی؛ قیمت پایه فروشنده ۲۸۰٬۰۰۰ تومان و قیمت نهایی سفارش ۲۹۰٬۰۰۰ تومان.',
            'fullDescription' => 'دسته بیستوری شماره ۳ با دسته مشکی؛ تصویر واقعی محصول از نمونه ارسالی ثبت شده است.',
            'specifications' => [
                ['label' => 'دسته', 'value' => 'دسته بیستوری'],
                ['label' => 'شماره', 'value' => '۳'],
                ['label' => 'مدل', 'value' => 'مشکی'],
            ],
            'maxQuantity' => 10,
        ],
        [
            'slug' => 'endo-tool-scalpel-handle-3',
            'title' => 'دسته بیستوری شماره ۳ — مدل ۳۹۰',
            'kind' => 'دسته بیستوری',
            'price' => 4000000,
            'heroImage' => '/assets/images/buy/endo-tools/scalpel-handle-3.svg',
            'gallery' => [],
            'shortDescription' => 'مدل با قیمت پایه فروشنده ۳۹۰٬۰۰۰ تومان؛ قیمت نهایی سفارش ۴۰۰٬۰۰۰ تومان.',
            'fullDescription' => 'دسته بیستوری شماره ۳، مدل معرفی‌شده با قیمت پایه ۳۹۰٬۰۰۰ تومان در فهرست فروشنده.',
            'specifications' => [
                ['label' => 'دسته', 'value' => 'دسته بیستوری'],
                ['label' => 'شماره', 'value' => '۳'],
                ['label' => 'مدل', 'value' => '۳۹۰'],
            ],
            'maxQuantity' => 10,
        ],
        [
            'slug' => 'endo-tool-scalpel-handle-3-premium',
            'title' => 'دسته بیستوری شماره ۳ — مدل ۴۶۵',
            'kind' => 'دسته بیستوری',
            'price' => 4750000,
            'heroImage' => '/assets/images/buy/endo-tools/scalpel-handle-3-premium-front.webp',
            'gallery' => [
                '/assets/images/buy/endo-tools/scalpel-handle-3-premium-angle.webp',
            ],
            'shortDescription' => 'مدل استیل؛ قیمت پایه فروشنده ۴۶۵٬۰۰۰ تومان و قیمت نهایی سفارش ۴۷۵٬۰۰۰ تومان.',
            'fullDescription' => 'دسته بیستوری شماره ۳ مدل استیل؛ دو تصویر واقعی از نمونه ارسالی برای تشخیص دقیق محصول ثبت شده است.',
            'specifications' => [
                ['label' => 'دسته', 'value' => 'دسته بیستوری'],
                ['label' => 'شماره', 'value' => '۳'],
                ['label' => 'مدل', 'value' => '۴۶۵'],
                ['label' => 'ظاهر', 'value' => 'استیل'],
            ],
            'maxQuantity' => 10,
        ],
        [
            'slug' => 'endo-tool-safe-end-bur-508-taiwan',
            'title' => 'فرز ایمن انتهایی ۵۰۸ — مدل تایوانی',
            'kind' => 'فرز',
            'price' => 2050000,
            'heroImage' => '/assets/images/buy/endo-tools/bur-safe-end-508.png',
            'shortDescription' => 'بسته ۵ عددی؛ سایز ۰۱۶، طول سر ۹ میلی‌متر، کد مرجع کاتالوگ SD161.',
            'fullDescription' => 'فرز ایمن انتهایی برای تکمیل دیواره‌های دسترسی اندودانتیک با کاهش خطر آسیب به کف اتاقک. مشخصات هندسی از کاتالوگ ارسالی تطبیق داده شده است.',
            'specifications' => [
                ['label' => 'دسته', 'value' => 'فرز'],
                ['label' => 'مدل', 'value' => 'تایوانی'],
                ['label' => 'بسته', 'value' => '۵ عددی'],
                ['label' => 'شکل', 'value' => 'ایمن انتهایی ۵۰۸'],
                ['label' => 'سایز', 'value' => '۰۱۶'],
                ['label' => 'طول سر', 'value' => '۹ میلی‌متر'],
                ['label' => 'کد مرجع', 'value' => 'SD161'],
            ],
            'maxQuantity' => 10,
        ],
        [
            'slug' => 'endo-tool-safe-end-bur-508-belgium',
            'title' => 'فرز ایمن انتهایی ۵۰۸ — مدل بلژیکی',
            'kind' => 'فرز',
            'price' => 2450000,
            'heroImage' => '/assets/images/buy/endo-tools/bur-safe-end-508.png',
            'shortDescription' => 'بسته ۵ عددی؛ سایز ۰۱۶، طول سر ۹ میلی‌متر، کد مرجع کاتالوگ SD161.',
            'fullDescription' => 'فرز ایمن انتهایی برای تکمیل دیواره‌های دسترسی اندودانتیک با کاهش خطر آسیب به کف اتاقک. مدل بلژیکی طبق بازخورد فروشنده کیفیت بالاتری دارد.',
            'specifications' => [
                ['label' => 'دسته', 'value' => 'فرز'],
                ['label' => 'مدل', 'value' => 'بلژیکی'],
                ['label' => 'بسته', 'value' => '۵ عددی'],
                ['label' => 'شکل', 'value' => 'ایمن انتهایی ۵۰۸'],
                ['label' => 'سایز', 'value' => '۰۱۶'],
                ['label' => 'طول سر', 'value' => '۹ میلی‌متر'],
                ['label' => 'کد مرجع', 'value' => 'SD161'],
            ],
            'maxQuantity' => 10,
        ],
    ];
}

function endo_tools_import_into_store(array &$store): array
{
    $catalog = endo_tools_catalog();
    if (!is_array($store['items'] ?? null)) {
        $store['items'] = [];
    }

    $now = dent_iso_now();
    $activeSlugs = [];
    foreach ($catalog as $product) {
        $slug = payments_clean_slug((string) ($product['slug'] ?? ''));
        if ($slug !== '') {
            $activeSlugs[$slug] = true;
        }
    }

    $retired = 0;
    foreach ($store['items'] as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $slug = payments_clean_slug((string) ($item['slug'] ?? ''));
        $category = (string) ($item['category'] ?? '');
        $isLegacyTooth = $category === ENDOSIM_CATEGORY || str_starts_with($slug, ENDOSIM_SLUG_PREFIX);
        $isStaleTool = (
            $category === ENDO_TOOLS_CATEGORY
            || str_starts_with($slug, ENDO_TOOLS_SLUG_PREFIX)
        ) && !isset($activeSlugs[$slug]);

        if (($isLegacyTooth || $isStaleTool)
            && (string) ($item['status'] ?? '') === PAYMENTS_ITEM_STATUS_ACTIVE
        ) {
            $store['items'][$index]['status'] = PAYMENTS_ITEM_STATUS_INACTIVE;
            $store['items'][$index]['updated_at'] = $now;
            $retired++;
        }
    }

    $created = 0;
    $updated = 0;
    $deliveryNote = 'تحویل حضوری در محدوده دانشکده دندانپزشکی دانشگاه علوم پزشکی تهران هماهنگ می‌شود.';

    foreach ($catalog as $product) {
        $slug = payments_clean_slug((string) ($product['slug'] ?? ''));
        $price = max(0, (int) ($product['price'] ?? 0));
        if ($slug === '' || $price <= 0) {
            continue;
        }

        $payload = [
            'slug' => $slug,
            'category' => ENDO_TOOLS_CATEGORY,
            'title' => (string) ($product['title'] ?? ''),
            'short_description' => (string) ($product['shortDescription'] ?? ''),
            'full_description' => (string) ($product['fullDescription'] ?? ''),
            'hero_image' => (string) ($product['heroImage'] ?? ''),
            'gallery' => array_values(array_filter(
                is_array($product['gallery'] ?? null) ? $product['gallery'] : [],
                static fn($value): bool => is_string($value) && trim($value) !== ''
            )),
            'specifications' => payments_normalize_specifications($product['specifications'] ?? []),
            'price' => $price,
            'status' => PAYMENTS_ITEM_STATUS_ACTIVE,
            'starts_at' => '',
            'expires_at' => '',
            'capacity' => null,
            'max_quantity_per_order' => max(1, min(99, (int) ($product['maxQuantity'] ?? 10))),
            'required_fields' => payments_normalize_required_fields_loose($product['requiredFields'] ?? []),
            'audience_note' => 'دانشجویان دندانپزشکی',
            'delivery_note' => $deliveryNote,
            'support_note' => (string) (($product['kind'] ?? '') === 'فرز'
                ? 'کد فرز پیش از ثبت سفارش بر اساس کاتالوگ ارسالی تطبیق داده شده است.'
                : ''),
            'allow_cancellation' => false,
            'discount_codes' => [],
            'rating_average' => 0,
            'rating_count' => 0,
            'reviews' => [],
            'success_message' => 'سفارش شما با موفقیت ثبت شد. برای هماهنگی تحویل با شما تماس می‌گیریم.',
            'failure_message' => 'پرداخت شما ناموفق بود. در صورت کسر وجه، مبلغ طبق روال درگاه بازمی‌گردد.',
            'updated_at' => $now,
        ];

        $index = payments_find_item_index_by_slug($store, $slug);
        if ($index >= 0 && is_array($store['items'][$index] ?? null)) {
            $existing = $store['items'][$index];
            $payload['id'] = (int) ($existing['id'] ?? payments_next_item_id($store));
            $payload['created_at'] = (string) ($existing['created_at'] ?? $now);
            $payload['sold_count'] = max(0, (int) ($existing['sold_count'] ?? 0));
            $store['items'][$index] = array_merge($existing, $payload);
            $updated++;
            continue;
        }

        $payload['id'] = payments_next_item_id($store);
        $payload['created_at'] = $now;
        $payload['sold_count'] = 0;
        $store['items'][] = $payload;
        $created++;
    }

    return [
        'created' => $created,
        'updated' => $updated,
        'retired' => $retired,
        'total' => count($catalog),
    ];
}

// Compatibility aliases for the historical route/import action.
function endosim_catalog(): array
{
    return endo_tools_catalog();
}

function endosim_import_into_store(array &$store): array
{
    return endo_tools_import_into_store($store);
}
