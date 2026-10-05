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

function endo_tools_core_catalog(): array
{
    return [
        [
            'slug' => 'endo-tool-fine-plugger-double',
            'title' => 'پلاگر با نوک ظریف — دوسر',
            'kind' => 'پلاگر',
            'price' => 7450000,
            'heroImage' => '/assets/images/buy/endo-tools/plugger-double.svg',
            'gallery' => [],
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
            'gallery' => [],
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
            'gallery' => [],
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
            'gallery' => ['/assets/images/buy/endo-tools/scalpel-handle-3-premium-angle.webp'],
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
    ];
}

function endo_bur_catalog_specs(): array
{
    static $specs = null;
    if (is_array($specs)) {
        return $specs;
    }

    $decoded = json_decode(<<<'JSON'
[{"series":"diamond","page":4,"name":"Inverted Cone","shape":"805","size":"012","head":"1.6","code":"SD12","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Inverted Cone","shape":"805","size":"014","head":"1.8","code":"SD13","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Inverted Cone","shape":"805","size":"016","head":"1.8","code":"SD14","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Inverted Cone","shape":"805","size":"018","head":"1.8","code":"SD15","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Inverted Cone","shape":"807","size":"012","head":"4.0","code":"SD16","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Inverted Cone","shape":"807","size":"014","head":"4.0","code":"SD17","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Barrel","shape":"811","size":"031","head":"4.0","code":"SD18","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Barrel","shape":"811","size":"035","head":"4.0","code":"SD18/2","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Amalgam Remover","shape":"813","size":"016","head":"2.3","code":"SD19","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Flat End Cylinder","shape":"837","size":"010","head":"8.0","code":"SD30","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Flat End Cylinder","shape":"837","size":"012","head":"8.0","code":"SD31","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Flat End Cylinder","shape":"837","size":"014","head":"8.0","code":"SD32","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Flat End Cylinder","shape":"837","size":"016","head":"8.0","code":"SD33","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Long Flat End Cylinder","shape":"837L","size":"010","head":"10","code":"SD34","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Long Flat End Cylinder","shape":"837L","size":"012","head":"10","code":"SD35","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Long Flat End Cylinder","shape":"837L","size":"016","head":"8.0","code":"SD36","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Flat End Taper","shape":"847","size":"010","head":"8.0","code":"SD37","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Flat End Taper","shape":"847","size":"012","head":"8.0","code":"SD38","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Flat End Taper","shape":"847","size":"014","head":"8.0","code":"SD39","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Flat End Taper","shape":"847","size":"016","head":"8.5","code":"SD40","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":4,"name":"Flat End Taper","shape":"847","size":"018","head":"8.5","code":"SD41","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Round","shape":"801","size":"010","head":"1.0","code":"SD01","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Round","shape":"801","size":"012","head":"1.2","code":"SD02","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Round","shape":"801","size":"014","head":"1.4","code":"SD03","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Round","shape":"801","size":"016","head":"1.6","code":"SD04","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Round","shape":"801","size":"018","head":"1.8","code":"SD05","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Round","shape":"801","size":"021","head":"2.1","code":"SD06","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Round","shape":"801","size":"023","head":"2.3","code":"SD07","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Long Shank Round","shape":"801L","size":"012","head":"1.2","code":"SD08","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Long Shank Round","shape":"801L","size":"014","head":"1.4","code":"SD09","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Long Shank Round","shape":"801L","size":"016","head":"1.6","code":"SD10","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Long Shank Round","shape":"801L","size":"018","head":"1.8","code":"SD11","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Long neck Round","shape":"6801L","size":"012","head":"1.2","code":"SD162","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Long neck Round","shape":"6801L","size":"014","head":"1.4","code":"SD163","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Long neck Round","shape":"6801L","size":"016","head":"1.6","code":"SD164","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Long neck Round","shape":"6801L","size":"018","head":"1.8","code":"SD165","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Depth Marker","shape":"","size":"018","head":"6.8","code":"SD20","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Flat End Cylinder","shape":"835","size":"006","head":"4.0","code":"SD21/0","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Flat End Cylinder","shape":"835","size":"007","head":"4.0","code":"SD21","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Flat End Cylinder","shape":"835","size":"008","head":"4.0","code":"SD22","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Flat End Cylinder","shape":"835","size":"009","head":"4.0","code":"SD23","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Flat End Cylinder","shape":"835","size":"010","head":"4.0","code":"SD24","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Flat End Cylinder","shape":"835","size":"012","head":"4.0","code":"SD25","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Flat End Cylinder","shape":"835","size":"014","head":"4.0","code":"SD26","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Flat End Cylinder","shape":"836","size":"010","head":"6.0","code":"SD27","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Flat End Cylinder","shape":"836","size":"012","head":"6.0","code":"SD28","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":5,"name":"Flat End Cylinder","shape":"836","size":"014","head":"6.0","code":"SD29","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Long Round End Taper","shape":"850L","size":"016","head":"11.5","code":"SD52","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Long Round End Taper","shape":"850L","size":"018","head":"11.5","code":"SD53","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Round End Taper","shape":"856","size":"010","head":"8.0","code":"SD54","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Round End Taper","shape":"856","size":"012","head":"8.0","code":"SD55","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Round End Taper","shape":"856","size":"014","head":"8.0","code":"SD56","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Round End Taper","shape":"856","size":"016","head":"8.0","code":"SD57","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Needle","shape":"858","size":"012","head":"8.0","code":"SD58","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Needle","shape":"858","size":"014","head":"8.0","code":"SD59","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Needle","shape":"858","size":"016","head":"8.0","code":"SD60","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Beveled Cylinder","shape":"863","size":"012","head":"10","code":"SD70","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Beveled Cylinder","shape":"863","size":"014","head":"10","code":"SD71","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Beveled Cylinder","shape":"863","size":"016","head":"10","code":"SD72","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Beveled Cylinder","shape":"877","size":"008","head":"6.0","code":"SD73","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Beveled Cylinder","shape":"877","size":"010","head":"6.0","code":"SD74","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Beveled Cylinder","shape":"877","size":"012","head":"6.0","code":"SD75","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Cylinder","shape":"878","size":"010","head":"8.0","code":"SD76","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Cylinder","shape":"878","size":"012","head":"8.0","code":"SD77","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Beveled Cylinder","shape":"879","size":"010","head":"10","code":"SD78","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Beveled Cylinder","shape":"879","size":"012","head":"10","code":"SD79","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":6,"name":"Beveled Cylinder","shape":"879","size":"014","head":"10","code":"SD80","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Flat End Taper","shape":"848","size":"010","head":"10","code":"SD42","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Flat End Taper","shape":"848","size":"012","head":"10","code":"SD43","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Flat End Taper","shape":"848","size":"014","head":"10","code":"SD44","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Flat End Taper","shape":"848","size":"016","head":"10","code":"SD45","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Flat End Taper","shape":"848","size":"018","head":"10","code":"SD46","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Round End Taper","shape":"850","size":"010","head":"10","code":"SD47","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Round End Taper","shape":"850","size":"012","head":"10","code":"SD48","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Round End Taper","shape":"850","size":"014","head":"10","code":"SD49","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Round End Taper","shape":"850","size":"016","head":"10","code":"SD50","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Round End Taper","shape":"850","size":"018","head":"10","code":"SD51","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Needle","shape":"859","size":"012","head":"10","code":"SD61","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Needle","shape":"859","size":"014","head":"10","code":"SD62","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Needle","shape":"859","size":"016","head":"10","code":"SD63","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Flame","shape":"860","size":"010","head":"4.0","code":"SD64","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Flame","shape":"859","size":"012","head":"5.0","code":"SD65","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Flame","shape":"862","size":"010","head":"8.0","code":"SD66","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Flame","shape":"862","size":"012","head":"8.0","code":"SD67","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Flame","shape":"862","size":"014","head":"8.0","code":"SD68","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":7,"name":"Flame","shape":"862","size":"016","head":"8.0","code":"SD69","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":8,"name":"Round End Cylinder","shape":"882","size":"010","head":"10","code":"SD89","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":8,"name":"Round End Cylinder","shape":"882","size":"012","head":"10","code":"SD90","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":8,"name":"Round End Cylinder","shape":"882","size":"014","head":"10","code":"SD91","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":8,"name":"Beveled Cylinder","shape":"884","size":"012","head":"6.0","code":"SD92","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":8,"name":"Beveled Cylinder","shape":"885","size":"010","head":"8.0","code":"SD93","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":8,"name":"Beveled Cylinder","shape":"885","size":"012","head":"8.0","code":"SD94","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":8,"name":"Beveled Cylinder","shape":"885","size":"014","head":"8.0","code":"SD95","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":8,"name":"Football","shape":"379","size":"018","head":"4.0","code":"SD104","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":8,"name":"Football","shape":"379","size":"023","head":"4.5","code":"SD105","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":8,"name":"Wheel","shape":"909","size":"040","head":"1.0","code":"SD106","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":8,"name":"Cylinder","shape":"515","size":"018","head":"8.0","code":"SD107","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":8,"name":"End Cut","shape":"839","size":"012","head":"","code":"SD159","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":8,"name":"End Cut","shape":"839","size":"014","head":"","code":"SD160","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":8,"name":"Safe End","shape":"508","size":"016","head":"9.0","code":"SD161","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":9,"name":"Pointed Taper","shape":"879K","size":"014","head":"10","code":"SD81","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":9,"name":"Pointed Taper","shape":"879","size":"016","head":"10","code":"SD82","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":9,"name":"Round End Cylinder","shape":"880","size":"008","head":"6.0","code":"SD83","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":9,"name":"Round End Cylinder","shape":"880","size":"010","head":"6.0","code":"SD84","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":9,"name":"Round End Cylinder","shape":"880","size":"012","head":"6.0","code":"SD85","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":9,"name":"Round End Cylinder","shape":"881","size":"010","head":"8.0","code":"SD86","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":9,"name":"Round End Cylinder","shape":"881","size":"012","head":"8.0","code":"SD87","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":9,"name":"Round End Cylinder","shape":"881","size":"014","head":"8.0","code":"SD88","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":9,"name":"Beveled Cylinder","shape":"886","size":"012","head":"10","code":"SD96","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":9,"name":"Beveled Cylinder","shape":"886","size":"014","head":"10","code":"SD97","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":9,"name":"Beveled Cylinder","shape":"886","size":"016","head":"10","code":"SD98","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":9,"name":"Flame","shape":"889","size":"010","head":"4.0","code":"SD99","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":9,"name":"Flame","shape":"889L","size":"008","head":"4.0","code":"SD100","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":9,"name":"Flame","shape":"889L","size":"010","head":"4.0","code":"SD101","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":9,"name":"Pointed Football","shape":"368","size":"018","head":"4.6","code":"SD102","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":9,"name":"Pointed Football","shape":"368","size":"023","head":"5.0","code":"SD103","grain":"Coarse 151 µm","shank":"FG"},{"series":"diamond","page":10,"name":"Needle","shape":"858","size":"012","head":"4.0","code":"SD117","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":10,"name":"Needle","shape":"858","size":"014","head":"4.0","code":"SD118","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":10,"name":"Needle","shape":"859","size":"012","head":"10","code":"SD119","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":10,"name":"Needle","shape":"859","size":"014","head":"10","code":"SD120","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":10,"name":"Needle","shape":"859","size":"016","head":"10","code":"SD121","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":10,"name":"Flame","shape":"860","size":"010","head":"4.0","code":"SD122","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":10,"name":"Flame","shape":"860","size":"012","head":"4.0","code":"SD123","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":10,"name":"Pointed Football","shape":"368","size":"018","head":"4.5","code":"SD134","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":10,"name":"Pointed Football","shape":"368","size":"023","head":"5.0","code":"SD135","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":10,"name":"Football","shape":"379","size":"018","head":"4.0","code":"SD136","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":10,"name":"Football","shape":"379","size":"023","head":"4.5","code":"SD137","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":10,"name":"Acorn","shape":"905","size":"023","head":"2.7","code":"SD138","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":10,"name":"Acorn","shape":"905","size":"027","head":"2.9","code":"SD139","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Round","shape":"801","size":"018","head":"1.8","code":"SD108","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Round","shape":"801","size":"021","head":"2.1","code":"SD109","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Round","shape":"801","size":"023","head":"2.3","code":"SD110","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Long Pear","shape":"830L","size":"016","head":"5.0","code":"SD111","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Round End Taper","shape":"850","size":"010","head":"10","code":"SD112","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Round End Taper","shape":"850","size":"012","head":"10","code":"SD113","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Round End Taper","shape":"850","size":"014","head":"10","code":"SD114","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Round End Taper","shape":"850","size":"016","head":"10","code":"SD115","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Round End Taper","shape":"850","size":"018","head":"10","code":"SD116","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Flame","shape":"862","size":"010","head":"8.0","code":"SD124","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Flame","shape":"862","size":"012","head":"8.0","code":"SD125","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Flame","shape":"862","size":"014","head":"8.0","code":"SD126","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Flame","shape":"862","size":"016","head":"8.0","code":"SD127","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Flame","shape":"863","size":"012","head":"10","code":"SD128","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Flame","shape":"863","size":"014","head":"10","code":"SD129","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Flame","shape":"863","size":"016","head":"10","code":"SD130","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Flame","shape":"889","size":"008","head":"4.0","code":"SD131/0","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Flame","shape":"889","size":"010","head":"4.0","code":"SD131","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Flame","shape":"889L","size":"008","head":"4.0","code":"SD132","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"diamond","page":11,"name":"Flame","shape":"889L","size":"010","head":"4.0","code":"SD133","grain":"Extra Fine 28 µm","shank":"FG"},{"series":"carbide","page":12,"name":"C1 Round","model":"","shank":"RA","order":"1/2","size":"006","head":"","extra":""},{"series":"carbide","page":12,"name":"C1 Round","model":"","shank":"RA","order":"1","size":"008","head":"","extra":""},{"series":"carbide","page":12,"name":"C1 Round","model":"","shank":"RA","order":"2","size":"010","head":"","extra":""},{"series":"carbide","page":12,"name":"C1 Round","model":"","shank":"RA","order":"3","size":"012","head":"","extra":""},{"series":"carbide","page":12,"name":"C1 Round","model":"","shank":"RA","order":"4","size":"014","head":"","extra":""},{"series":"carbide","page":12,"name":"C1 Round","model":"","shank":"RA","order":"5","size":"016","head":"","extra":""},{"series":"carbide","page":12,"name":"C1 Round","model":"","shank":"RA","order":"6","size":"018","head":"","extra":""},{"series":"carbide","page":12,"name":"C1 Round","model":"","shank":"RA","order":"7","size":"021","head":"","extra":""},{"series":"carbide","page":12,"name":"C1 Round","model":"","shank":"RA","order":"8","size":"023","head":"","extra":""},{"series":"carbide","page":12,"name":"C1 Round","model":"","shank":"RAL","order":"4","size":"014","head":"","extra":""},{"series":"carbide","page":12,"name":"C1 Round","model":"","shank":"RAL","order":"5","size":"016","head":"","extra":""},{"series":"carbide","page":12,"name":"C1 Round","model":"","shank":"RAL","order":"6","size":"018","head":"","extra":""},{"series":"carbide","page":12,"name":"C1 Round","model":"","shank":"RAL","order":"7","size":"021","head":"","extra":""},{"series":"carbide","page":12,"name":"C1 Round","model":"","shank":"RAL","order":"8","size":"023","head":"","extra":""},{"series":"carbide","page":12,"name":"C1 Round","model":"","shank":"HP","order":"5","size":"016","head":"","extra":""},{"series":"carbide","page":12,"name":"C1 Round","model":"","shank":"HP","order":"6","size":"018","head":"","extra":""},{"series":"carbide","page":12,"name":"C1 Round","model":"","shank":"HP","order":"7","size":"021","head":"","extra":""},{"series":"carbide","page":12,"name":"C1 Round","model":"","shank":"HP","order":"8","size":"023","head":"","extra":""},{"series":"carbide","page":12,"name":"C21 Cylinder","model":"","shank":"FG","order":"58","size":"012","head":"","extra":""},{"series":"carbide","page":12,"name":"C21 Cylinder","model":"","shank":"FG","order":"59","size":"014","head":"","extra":""},{"series":"carbide","page":12,"name":"C21 Cylinder","model":"","shank":"FGL","order":"58L","size":"012","head":"","extra":""},{"series":"carbide","page":12,"name":"C21 Cylinder","model":"","shank":"FGL","order":"59L","size":"014","head":"","extra":""},{"series":"carbide","page":12,"name":"C21 Cylinder","model":"","shank":"HP","order":"58","size":"012","head":"","extra":""},{"series":"carbide","page":12,"name":"C21 Cylinder","model":"","shank":"HP","order":"59","size":"014","head":"","extra":""},{"series":"carbide","page":12,"name":"C21 Cylinder","model":"","shank":"HP","order":"60","size":"016","head":"","extra":""},{"series":"carbide","page":12,"name":"C21 Cylinder","model":"","shank":"HP","order":"61","size":"018","head":"","extra":""},{"series":"carbide","page":12,"name":"C33L Cross-cut Taper","model":"","shank":"HP","order":"701L","size":"012","head":"","extra":""},{"series":"carbide","page":12,"name":"C33L Cross-cut Taper","model":"","shank":"HP","order":"702L","size":"016","head":"","extra":""},{"series":"carbide","page":12,"name":"C31 Cross-cut Cylinder","model":"","shank":"HP","order":"558","size":"012","head":"","extra":""},{"series":"carbide","page":12,"name":"C31 Cross-cut Cylinder","model":"","shank":"HP","order":"559","size":"014","head":"","extra":""},{"series":"carbide","page":12,"name":"C31 Cross-cut Cylinder","model":"","shank":"HP","order":"560","size":"016","head":"","extra":""},{"series":"carbide","page":13,"name":"Crown Cutter","model":"","shank":"FG","order":"1931","size":"010","head":"2.6","extra":""},{"series":"carbide","page":13,"name":"Crown Cutter","model":"","shank":"FG","order":"1957","size":"010","head":"4.2","extra":""},{"series":"carbide","page":13,"name":"Crown Cutter","model":"","shank":"FG","order":"1958","size":"012","head":"4.2","extra":""},{"series":"carbide","page":13,"name":"Crown Cutter","model":"","shank":"FG","order":"2057","size":"010","head":"5.0","extra":""},{"series":"carbide","page":13,"name":"Finishing & Polishing","model":"C135","shank":"FG","order":"Et9","size":"014","head":"9.0","extra":"12 blades"},{"series":"carbide","page":13,"name":"Finishing & Polishing","model":"C48L","shank":"FG","order":"4810","size":"010","head":"8.0","extra":"12 blades"},{"series":"carbide","page":13,"name":"Finishing & Polishing","model":"C48L","shank":"FG","order":"4812","size":"012","head":"8.0","extra":"12 blades"},{"series":"carbide","page":13,"name":"Finishing & Polishing","model":"C46","shank":"FG","order":"7106","size":"018","head":"3.8","extra":"12 blades"},{"series":"carbide","page":13,"name":"Finishing & Polishing","model":"C46","shank":"FG","order":"7108","size":"023","head":"4.5","extra":"12 blades"},{"series":"carbide","page":13,"name":"Finishing & Polishing","model":"C375R","shank":"FG","order":"7653","size":"012","head":"8.0","extra":"12 blades"},{"series":"carbide","page":13,"name":"Finishing & Polishing","model":"C375R","shank":"FG","order":"7664","size":"014","head":"8.0","extra":"12 blades"}]
JSON, true);

    $specs = is_array($decoded) ? $decoded : [];
    return $specs;
}

function endo_bur_slug_piece(string $value): string
{
    $clean = strtolower(trim($value));
    $clean = preg_replace('/[^a-z0-9]+/i', '-', $clean) ?? '';
    return trim($clean, '-');
}

function endo_bur_products(): array
{
    $origins = [
        ['key' => 'taiwan', 'label' => 'Taiwan', 'price' => 2050000],
        ['key' => 'belgium', 'label' => 'Belgium', 'price' => 2450000],
    ];

    $products = [];
    foreach (endo_bur_catalog_specs() as $spec) {
        if (!is_array($spec)) {
            continue;
        }

        $series = (string) ($spec['series'] ?? '');
        $page = max(1, (int) ($spec['page'] ?? 0));
        $name = trim((string) ($spec['name'] ?? ''));
        $size = trim((string) ($spec['size'] ?? ''));
        $head = trim((string) ($spec['head'] ?? ''));
        $shank = trim((string) ($spec['shank'] ?? ''));
        $image = '/assets/images/buy/endo-tools/catalog/catalog-page-' . str_pad((string) $page, 2, '0', STR_PAD_LEFT) . '.jpg';

        foreach ($origins as $origin) {
            $originKey = (string) $origin['key'];
            $originLabel = (string) $origin['label'];
            $price = (int) $origin['price'];

            if ($series === 'diamond') {
                $shape = trim((string) ($spec['shape'] ?? ''));
                $code = trim((string) ($spec['code'] ?? ''));
                if ($name === '' || $size === '' || $code === '') {
                    continue;
                }

                if ($code === 'SD161') {
                    $slug = 'endo-tool-safe-end-bur-508-' . $originKey;
                } else {
                    $slug = 'endo-tool-bur-diamond-' . endo_bur_slug_piece($code) . '-' . $originKey;
                }

                $catalogName = trim($name . ($shape !== '' ? ' ' . $shape : ''));
                $title = $catalogName . ' — ' . $size . ' — ' . $code . ' — ' . $originLabel;
                $details = ['FG Diamond Burs', (string) ($spec['grain'] ?? ''), 'Size ' . $size];
                if ($shape !== '') {
                    $details[] = 'Shape ' . $shape;
                }
                if ($head !== '') {
                    $details[] = 'Head L ' . $head . ' mm';
                }
                $details[] = 'Order Code ' . $code;
                $short = implode(' · ', array_filter($details)) . ' · بسته ۵ عددی.';

                $specifications = [
                    ['label' => 'دسته', 'value' => 'Diamond Burs'],
                    ['label' => 'نام کاتالوگ', 'value' => $name],
                    ['label' => 'شنک', 'value' => 'FG'],
                    ['label' => 'سایز', 'value' => $size],
                    ['label' => 'کد سفارش', 'value' => $code],
                    ['label' => 'مدل', 'value' => $originLabel],
                    ['label' => 'بسته', 'value' => '۵ عددی'],
                ];
                if ($shape !== '') {
                    $specifications[] = ['label' => 'شکل', 'value' => $shape];
                }
                if ($head !== '') {
                    $specifications[] = ['label' => 'طول سر', 'value' => $head . ' mm'];
                }
                $grain = trim((string) ($spec['grain'] ?? ''));
                if ($grain !== '') {
                    $specifications[] = ['label' => 'دانه‌بندی', 'value' => $grain];
                }

                $products[] = [
                    'slug' => $slug,
                    'title' => $title,
                    'kind' => 'Diamond Burs',
                    'price' => $price,
                    'heroImage' => $image,
                    'gallery' => [],
                    'shortDescription' => $short,
                    'fullDescription' => 'نام، Shape، Size و Order Code بدون ترجمه و مطابق کاتالوگ ارسالی ثبت شده‌اند. قیمت مربوط به بسته ۵ عددی مدل ' . $originLabel . ' است.',
                    'specifications' => $specifications,
                    'maxQuantity' => 10,
                ];
                continue;
            }

            if ($series !== 'carbide') {
                continue;
            }

            $order = trim((string) ($spec['order'] ?? ''));
            $model = trim((string) ($spec['model'] ?? ''));
            $extra = trim((string) ($spec['extra'] ?? ''));
            if ($name === '' || $size === '' || $order === '' || $shank === '') {
                continue;
            }

            $slug = implode('-', array_filter([
                'endo-tool-bur-carbide',
                'p' . $page,
                endo_bur_slug_piece($model !== '' ? $model : $name),
                endo_bur_slug_piece($shank),
                endo_bur_slug_piece($order),
                $originKey,
            ]));
            $catalogName = trim($name . ($model !== '' ? ' ' . $model : ''));
            $title = $catalogName . ' — ' . $shank . ' — ' . $size . ' — ' . $order . ' — ' . $originLabel;
            $details = ['Carbide Burs', $catalogName, $shank, 'ISO ' . $size, 'ORDER.NO ' . $order];
            if ($head !== '') {
                $details[] = 'Head Length ' . $head . ' mm';
            }
            if ($extra !== '') {
                $details[] = $extra;
            }
            $short = implode(' · ', array_filter($details)) . ' · بسته ۵ عددی.';

            $specifications = [
                ['label' => 'دسته', 'value' => 'Carbide Burs'],
                ['label' => 'نام کاتالوگ', 'value' => $name],
                ['label' => 'شنک', 'value' => $shank],
                ['label' => 'سایز', 'value' => $size],
                ['label' => 'کد سفارش', 'value' => $order],
                ['label' => 'مدل', 'value' => $originLabel],
                ['label' => 'بسته', 'value' => '۵ عددی'],
            ];
            if ($model !== '') {
                $specifications[] = ['label' => 'مدل کاتالوگ', 'value' => $model];
            }
            if ($head !== '') {
                $specifications[] = ['label' => 'طول سر', 'value' => $head . ' mm'];
            }
            if ($extra !== '') {
                $specifications[] = ['label' => 'ویژگی', 'value' => $extra];
            }

            $products[] = [
                'slug' => $slug,
                'title' => $title,
                'kind' => 'Carbide Burs',
                'price' => $price,
                'heroImage' => $image,
                'gallery' => [],
                'shortDescription' => $short,
                'fullDescription' => 'نام، ISO، ORDER.NO و نوع شنک بدون ترجمه و مطابق کاتالوگ ارسالی ثبت شده‌اند. قیمت مربوط به بسته ۵ عددی مدل ' . $originLabel . ' است.',
                'specifications' => $specifications,
                'maxQuantity' => 10,
            ];
        }
    }

    return $products;
}

function endo_tools_catalog(): array
{
    return array_merge(endo_tools_core_catalog(), endo_bur_products());
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
            'support_note' => (string) (in_array((string) ($product['kind'] ?? ''), ['Diamond Burs', 'Carbide Burs'], true)
                ? 'نام، سایز و کد سفارش فرز مطابق کاتالوگ ارسالی ثبت شده است.'
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
