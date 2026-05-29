<?php
declare(strict_types=1);

require_once __DIR__ . '/exams_endotorabinejad_data.php';

function dent_exams_apply_endotorabinejad_catalog_overrides(array $bank): array
{
    $courses = $bank['catalogs']['shared']['courses'] ?? null;
    if (!is_array($courses)) {
        return $bank;
    }

    $endoCourses = dent_exams_endotorabinejad_course_map();
    $rebuilt = [];
    $radiologyCourse = null;

    foreach ($courses as $slug => $course) {
        if ($slug === 'radiology2') {
            $radiologyCourse = $course;
            continue;
        }

        $rebuilt[$slug] = $course;
    }

    $rebuilt['endotorabinejad'] = $endoCourses['endotorabinejad'];

    if (is_array($radiologyCourse)) {
        $rebuilt['radiology2'] = $radiologyCourse;
    }

    $rebuilt['endotorabinejad-1-5'] = $endoCourses['endotorabinejad-1-5'];
    $rebuilt['endotorabinejad-6-10'] = $endoCourses['endotorabinejad-6-10'];
    $rebuilt['endotorabinejad-11-15'] = $endoCourses['endotorabinejad-11-15'];
    $bank['catalogs']['shared']['courses'] = $rebuilt;

    return $bank;
}

function dent_exams_endotorabinejad_course_map(): array
{
    $questionBank = dent_exams_endotorabinejad_exam_bank();
    $initialBlockExams = dent_exams_endotorabinejad_filter_exams($questionBank, 1, 5);
    $middleBlockExams = dent_exams_endotorabinejad_filter_exams($questionBank, 6, 10);
    $finalBlockExams = dent_exams_endotorabinejad_filter_exams($questionBank, 11, 15);
    $initialBlockQuestionCount = dent_exams_endotorabinejad_total_questions($initialBlockExams);
    $middleBlockQuestionCount = dent_exams_endotorabinejad_total_questions($middleBlockExams);
    $finalBlockQuestionCount = dent_exams_endotorabinejad_total_questions($finalBlockExams);

    return [
        'endotorabinejad' => [
            'slug' => 'endotorabinejad',
            'title' => 'کمپلکس پالپ و پری‌اپیکال | ترابی‌نژاد',
            'shortTitle' => 'ترابی‌نژاد',
            'badge' => '۳ بخش',
            'cardDescription' => 'آزمون‌های مرجع ترابی‌نژاد برای واحد «کمپلکس پالپ و پری‌اپیکال» در سه بازهٔ پنج‌فصلی ارائه می‌شود و هر سه بازه اکنون فعال هستند.',
            'heroTitle' => 'بخش‌بندی آزمون‌های کمپلکس پالپ و پری‌اپیکال',
            'heroDescription' => 'این مجموعه مربوط به واحد «کمپلکس پالپ و پری‌اپیکال» است و در سه بازهٔ پنج‌فصلی مدیریت می‌شود. برای هر فصل دو آزمون نیمهٔ اول و نیمهٔ دوم آماده شده و اکنون هر سه بازه با مجموع ۳۰ آزمون کامل در دسترس هستند.',
            'path' => '/exams/endotorabinejad/',
            'paymentTitle' => 'بخش‌بندی آزمون‌های کمپلکس پالپ و پری‌اپیکال',
            'paymentDescription' => 'برای هر بازهٔ پنج‌فصلیِ این واحد از همین‌جا وارد بخش مربوطه شوید؛ خرید هر بخش از همان صفحه انجام می‌شود.',
            'paymentSuccessMessage' => 'این صفحه فقط برای هدایت به بخش‌های مختلف آزمون‌های کمپلکس پالپ و پری‌اپیکال است.',
            'paymentFailureMessage' => 'برای فعال‌سازی هر بازه از صفحهٔ همان بخش استفاده کنید.',
            'defaultPaymentMode' => 'free',
            'defaultAmount' => 0,
            'exams' => [
                [
                    'slug' => 'chapters-1-5',
                    'path' => '/exams/endotorabinejad/1-5/',
                    'questionCount' => $initialBlockQuestionCount,
                    'label' => 'فصول ۱ تا ۵',
                    'title' => '۱۰ آزمون برای فصول ۱ تا ۵',
                    'description' => 'برای هر فصل دو آزمون نیمهٔ اول و نیمهٔ دوم آماده شده است و این بازه با یک خرید ۳۰ هزارتومانی فعال می‌شود.',
                    'ctaLabel' => 'مشاهده بخش',
                ],
                [
                    'slug' => 'chapters-6-10',
                    'path' => '/exams/endotorabinejad/6-10/',
                    'questionCount' => $middleBlockQuestionCount,
                    'label' => 'فصول ۶ تا ۱۰',
                    'title' => '۱۰ آزمون برای فصول ۶ تا ۱۰',
                    'description' => 'برای هر فصل دو آزمون نیمهٔ اول و نیمهٔ دوم آماده شده است و این بازه با یک خرید ۳۰ هزارتومانی فعال می‌شود.',
                    'ctaLabel' => 'مشاهده بخش',
                ],
                [
                    'slug' => 'chapters-11-15',
                    'path' => '/exams/endotorabinejad/11-15/',
                    'questionCount' => $finalBlockQuestionCount,
                    'label' => 'فصول ۱۱ تا ۱۵',
                    'title' => '۱۰ آزمون برای فصول ۱۱ تا ۱۵',
                    'description' => 'برای هر فصل دو آزمون نیمهٔ اول و نیمهٔ دوم آماده شده است و این بازه با یک خرید ۳۰ هزارتومانی فعال می‌شود.',
                    'ctaLabel' => 'مشاهده بخش',
                ],
            ],
        ],
        'endotorabinejad-1-5' => [
            'slug' => 'endotorabinejad-1-5',
            'visibleOnCatalog' => false,
            'title' => 'ترابی‌نژاد - فصول ۱ تا ۵',
            'shortTitle' => 'فصول ۱ تا ۵',
            'badge' => '۱۰ آزمون',
            'cardDescription' => 'برای هر فصل دو آزمون نیمهٔ اول و نیمهٔ دوم بر اساس فایل جدید ترابی‌نژاد آماده شده است.',
            'heroTitle' => 'ترابی‌نژاد - فصول ۱ تا ۵',
            'heroDescription' => 'این بازه مربوط به واحد کمپلکس پالپ و پری‌اپیکال است، شامل ۵ فصل است و برای هر فصل دو آزمون ۵۰ سوالی آماده شده است. دسترسی این مجموعه با یک خرید ۳۰ هزارتومانی فعال می‌شود.',
            'path' => '/exams/endotorabinejad/1-5/',
            'paymentTitle' => 'دسترسی به فصول ۱ تا ۵ ترابی‌نژاد',
            'paymentDescription' => 'با یک بار پرداخت، همهٔ آزمون‌های فصول ۱ تا ۵ ترابی‌نژاد برای واحد کمپلکس پالپ و پری‌اپیکال روی همین حساب فعال می‌شود.',
            'paymentSuccessMessage' => 'پرداخت شما تایید شد و همهٔ آزمون‌های فصول ۱ تا ۵ ترابی‌نژاد برای این حساب باز شد.',
            'paymentFailureMessage' => 'پرداخت این بخش تایید نشد. در صورت کسر وجه، نتیجه را دوباره از همین صفحه بررسی کنید.',
            'defaultPaymentMode' => 'paid',
            'defaultAmount' => 300000,
            'exams' => $initialBlockExams,
        ],
        'endotorabinejad-6-10' => [
            'slug' => 'endotorabinejad-6-10',
            'visibleOnCatalog' => false,
            'title' => 'ترابی‌نژاد - فصول ۶ تا ۱۰',
            'shortTitle' => 'فصول ۶ تا ۱۰',
            'badge' => '۱۰ آزمون',
            'cardDescription' => 'برای هر فصل دو آزمون نیمهٔ اول و نیمهٔ دوم بر اساس دیتاست جدید ترابی‌نژاد آماده شده است.',
            'heroTitle' => 'ترابی‌نژاد - فصول ۶ تا ۱۰',
            'heroDescription' => 'این بازه مربوط به واحد کمپلکس پالپ و پری‌اپیکال است، شامل ۵ فصل است و برای هر فصل دو آزمون ۵۰ سوالی آماده شده است. دسترسی این مجموعه با یک خرید ۳۰ هزارتومانی فعال می‌شود.',
            'path' => '/exams/endotorabinejad/6-10/',
            'paymentTitle' => 'دسترسی به فصول ۶ تا ۱۰ ترابی‌نژاد',
            'paymentDescription' => 'با یک بار پرداخت، همهٔ آزمون‌های فصول ۶ تا ۱۰ ترابی‌نژاد برای واحد کمپلکس پالپ و پری‌اپیکال روی همین حساب فعال می‌شود.',
            'paymentSuccessMessage' => 'پرداخت شما تایید شد و همهٔ آزمون‌های فصول ۶ تا ۱۰ ترابی‌نژاد برای این حساب باز شد.',
            'paymentFailureMessage' => 'پرداخت این بخش تایید نشد. در صورت کسر وجه، نتیجه را دوباره از همین صفحه بررسی کنید.',
            'defaultPaymentMode' => 'paid',
            'defaultAmount' => 300000,
            'exams' => $middleBlockExams,
        ],
        'endotorabinejad-11-15' => [
            'slug' => 'endotorabinejad-11-15',
            'visibleOnCatalog' => false,
            'title' => 'ترابی‌نژاد - فصول ۱۱ تا ۱۵',
            'shortTitle' => 'فصول ۱۱ تا ۱۵',
            'badge' => '۱۰ آزمون',
            'cardDescription' => 'برای هر فصل دو آزمون نیمهٔ اول و نیمهٔ دوم بر اساس فایل جدید ترابی‌نژاد آماده شده است.',
            'heroTitle' => 'ترابی‌نژاد - فصول ۱۱ تا ۱۵',
            'heroDescription' => 'این بازه مربوط به واحد کمپلکس پالپ و پری‌اپیکال است، شامل ۵ فصل است و برای هر فصل دو آزمون ۵۰ سوالی آماده شده است. دسترسی این مجموعه با یک خرید ۳۰ هزارتومانی فعال می‌شود.',
            'path' => '/exams/endotorabinejad/11-15/',
            'paymentTitle' => 'دسترسی به فصول ۱۱ تا ۱۵ ترابی‌نژاد',
            'paymentDescription' => 'با یک بار پرداخت، همهٔ آزمون‌های فصول ۱۱ تا ۱۵ ترابی‌نژاد برای واحد کمپلکس پالپ و پری‌اپیکال روی همین حساب فعال می‌شود.',
            'paymentSuccessMessage' => 'پرداخت شما تایید شد و همهٔ آزمون‌های فصول ۱۱ تا ۱۵ ترابی‌نژاد برای این حساب باز شد.',
            'paymentFailureMessage' => 'پرداخت این بخش تایید نشد. در صورت کسر وجه، نتیجه را دوباره از همین صفحه بررسی کنید.',
            'defaultPaymentMode' => 'paid',
            'defaultAmount' => 300000,
            'exams' => $finalBlockExams,
        ],
    ];
}

function dent_exams_endotorabinejad_filter_exams(array $questionBank, int $fromChapter, int $toChapter): array
{
    $filtered = [];
    foreach ($questionBank as $slug => $exam) {
        if (!is_array($exam)) {
            continue;
        }

        $examSlug = (string) ($exam['slug'] ?? $slug);
        $chapter = dent_exams_endotorabinejad_chapter_from_slug($examSlug);
        if ($chapter < $fromChapter || $chapter > $toChapter) {
            continue;
        }

        $filtered[] = $exam;
    }

    usort($filtered, static function (array $left, array $right): int {
        return dent_exams_endotorabinejad_compare_exam_order(
            (string) ($left['slug'] ?? ''),
            (string) ($right['slug'] ?? '')
        );
    });

    return array_values($filtered);
}

function dent_exams_endotorabinejad_total_questions(array $exams): int
{
    $total = 0;
    foreach ($exams as $exam) {
        if (!is_array($exam)) {
            continue;
        }
        $total += max(0, (int) ($exam['questionCount'] ?? 0));
    }
    return $total;
}

function dent_exams_endotorabinejad_chapter_from_slug(string $slug): int
{
    [$chapter] = dent_exams_endotorabinejad_slug_parts($slug);
    return $chapter;
}

function dent_exams_endotorabinejad_compare_exam_order(string $leftSlug, string $rightSlug): int
{
    [$leftChapter, $leftHalf] = dent_exams_endotorabinejad_slug_parts($leftSlug);
    [$rightChapter, $rightHalf] = dent_exams_endotorabinejad_slug_parts($rightSlug);

    if ($leftChapter !== $rightChapter) {
        return $leftChapter <=> $rightChapter;
    }

    return $leftHalf <=> $rightHalf;
}

function dent_exams_endotorabinejad_slug_parts(string $slug): array
{
    $parts = explode('-', trim($slug));
    $chapter = isset($parts[0]) ? (int) $parts[0] : 0;
    $half = isset($parts[1]) ? (int) $parts[1] : 0;
    return [$chapter, $half];
}
