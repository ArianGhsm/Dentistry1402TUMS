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
    $finalBlockQuestionCount = 0;
    foreach ($questionBank as $exam) {
        if (is_array($exam)) {
            $finalBlockQuestionCount += max(0, (int) ($exam['questionCount'] ?? 0));
        }
    }

    return [
        'endotorabinejad' => [
            'slug' => 'endotorabinejad',
            'title' => 'کمپلکس پالپ و پری‌اپیکال | ترابی‌نژاد',
            'shortTitle' => 'ترابی‌نژاد',
            'badge' => '۳ بخش',
            'cardDescription' => 'آزمون‌های مرجع ترابی‌نژاد برای واحد «کمپلکس پالپ و پری‌اپیکال» در سه بازهٔ جدا از همین صفحه در دسترس است.',
            'heroTitle' => 'بخش‌بندی آزمون‌های کمپلکس پالپ و پری‌اپیکال',
            'heroDescription' => 'این مجموعه مربوط به واحد «کمپلکس پالپ و پری‌اپیکال» است و در سه بازهٔ پنج‌فصلی مدیریت می‌شود. فصول ۱۱ تا ۱۵ اکنون با آزمون‌های کامل در دسترس هستند.',
            'path' => '/exams/endotorabinejad/',
            'paymentTitle' => 'بخش‌بندی آزمون‌های کمپلکس پالپ و پری‌اپیکال',
            'paymentDescription' => 'برای هر بازهٔ پنج‌فصلیِ این واحد از همین‌جا وارد بخش مربوطه شوید؛ خرید هر بخش از همان صفحه انجام می‌شود.',
            'paymentSuccessMessage' => 'این صفحه صرفاً برای هدایت به بخش‌های مختلف آزمون‌های کمپلکس پالپ و پری‌اپیکال است.',
            'paymentFailureMessage' => 'برای فعال‌سازی هر بازه از صفحهٔ همان بخش استفاده کنید.',
            'defaultPaymentMode' => 'free',
            'defaultAmount' => 0,
            'exams' => [
                [
                    'slug' => 'chapters-1-5',
                    'path' => '/exams/endotorabinejad/1-5/',
                    'questionCount' => 0,
                    'label' => 'فصول ۱ تا ۵',
                    'title' => 'بخش فصول ۱ تا ۵',
                    'description' => 'این بازه به‌زودی بارگذاری می‌شود. خرید و وضعیت دسترسی آن از صفحهٔ همین بخش انجام می‌شود.',
                    'ctaLabel' => 'مشاهده بخش',
                ],
                [
                    'slug' => 'chapters-6-10',
                    'path' => '/exams/endotorabinejad/6-10/',
                    'questionCount' => 0,
                    'label' => 'فصول ۶ تا ۱۰',
                    'title' => 'بخش فصول ۶ تا ۱۰',
                    'description' => 'این بازه هم به‌زودی بارگذاری می‌شود و مثل سایر بخش‌ها به‌صورت مستقل مدیریت خواهد شد.',
                    'ctaLabel' => 'مشاهده بخش',
                ],
                [
                    'slug' => 'chapters-11-15',
                    'path' => '/exams/endotorabinejad/11-15/',
                    'questionCount' => $finalBlockQuestionCount,
                    'label' => 'فصول ۱۱ تا ۱۵',
                    'title' => '۱۰ آزمون برای فصول ۱۱ تا ۱۵',
                    'description' => 'برای هر فصل دو آزمون نیمهٔ اول و نیمهٔ دوم آماده شده است و این بازه به‌صورت یک درس ۳۰ هزارتومانی فعال می‌شود.',
                    'ctaLabel' => 'مشاهده بخش',
                ],
            ],
        ],
        'endotorabinejad-1-5' => [
            'slug' => 'endotorabinejad-1-5',
            'visibleOnCatalog' => false,
            'title' => 'ترابی‌نژاد - فصول ۱ تا ۵',
            'shortTitle' => 'فصول ۱ تا ۵',
            'badge' => 'بزودی',
            'cardDescription' => 'بخش فصول ۱ تا ۵ مرجع ترابی‌نژاد برای واحد کمپلکس پالپ و پری‌اپیکال.',
            'heroTitle' => 'ترابی‌نژاد - فصول ۱ تا ۵',
            'heroDescription' => 'این بازه مربوط به واحد کمپلکس پالپ و پری‌اپیکال است و به‌صورت یک بخش مستقل با هزینهٔ ۳۰ هزارتومان مدیریت می‌شود. محتوای آزمون‌های این بخش به‌زودی بارگذاری خواهد شد.',
            'path' => '/exams/endotorabinejad/1-5/',
            'paymentTitle' => 'دسترسی به فصول ۱ تا ۵ ترابی‌نژاد',
            'paymentDescription' => 'با فعال‌سازی این بخش، دسترسی همین حساب به محتوای فصول ۱ تا ۵ ترابی‌نژاد برای واحد کمپلکس پالپ و پری‌اپیکال باز می‌شود. آزمون‌های این بازه به‌زودی بارگذاری خواهند شد.',
            'paymentSuccessMessage' => 'پرداخت شما برای بخش فصول ۱ تا ۵ ثبت شد. پس از انتشار آزمون‌ها، از همین صفحه در دسترس خواهند بود.',
            'paymentFailureMessage' => 'پرداخت این بخش تایید نشد. در صورت کسر وجه، نتیجه را دوباره از همین صفحه بررسی کنید.',
            'defaultPaymentMode' => 'paid',
            'defaultAmount' => 300000,
            'exams' => [
                [
                    'slug' => 'coming-soon-1-5',
                    'path' => '',
                    'questionCount' => 0,
                    'label' => 'فصول ۱ تا ۵',
                    'title' => 'بخش فصول ۱ تا ۵ به‌زودی بارگذاری می‌شود',
                    'description' => 'پس از انتشار آزمون‌های این بازه، از همین صفحه قابل دسترسی خواهد بود.',
                    'ctaLabel' => 'بزودی بارگذاری می‌شود',
                    'countsTowardStats' => false,
                ],
            ],
        ],
        'endotorabinejad-6-10' => [
            'slug' => 'endotorabinejad-6-10',
            'visibleOnCatalog' => false,
            'title' => 'ترابی‌نژاد - فصول ۶ تا ۱۰',
            'shortTitle' => 'فصول ۶ تا ۱۰',
            'badge' => 'بزودی',
            'cardDescription' => 'بخش فصول ۶ تا ۱۰ مرجع ترابی‌نژاد برای واحد کمپلکس پالپ و پری‌اپیکال.',
            'heroTitle' => 'ترابی‌نژاد - فصول ۶ تا ۱۰',
            'heroDescription' => 'این بازه هم مربوط به واحد کمپلکس پالپ و پری‌اپیکال است و به‌صورت یک بخش مستقل با هزینهٔ ۳۰ هزارتومان فعال می‌شود و آزمون‌هایش به‌زودی اضافه خواهند شد.',
            'path' => '/exams/endotorabinejad/6-10/',
            'paymentTitle' => 'دسترسی به فصول ۶ تا ۱۰ ترابی‌نژاد',
            'paymentDescription' => 'با فعال‌سازی این بخش، دسترسی همین حساب به محتوای فصول ۶ تا ۱۰ ترابی‌نژاد برای واحد کمپلکس پالپ و پری‌اپیکال باز می‌شود. آزمون‌های این بازه به‌زودی بارگذاری خواهند شد.',
            'paymentSuccessMessage' => 'پرداخت شما برای بخش فصول ۶ تا ۱۰ ثبت شد. پس از انتشار آزمون‌ها، از همین صفحه در دسترس خواهند بود.',
            'paymentFailureMessage' => 'پرداخت این بخش تایید نشد. در صورت کسر وجه، نتیجه را دوباره از همین صفحه بررسی کنید.',
            'defaultPaymentMode' => 'paid',
            'defaultAmount' => 300000,
            'exams' => [
                [
                    'slug' => 'coming-soon-6-10',
                    'path' => '',
                    'questionCount' => 0,
                    'label' => 'فصول ۶ تا ۱۰',
                    'title' => 'بخش فصول ۶ تا ۱۰ به‌زودی بارگذاری می‌شود',
                    'description' => 'پس از انتشار آزمون‌های این بازه، از همین صفحه قابل دسترسی خواهد بود.',
                    'ctaLabel' => 'بزودی بارگذاری می‌شود',
                    'countsTowardStats' => false,
                ],
            ],
        ],
        'endotorabinejad-11-15' => [
            'slug' => 'endotorabinejad-11-15',
            'visibleOnCatalog' => false,
            'title' => 'ترابی‌نژاد - فصول ۱۱ تا ۱۵',
            'shortTitle' => 'فصول ۱۱ تا ۱۵',
            'badge' => '۱۰ آزمون',
            'cardDescription' => 'برای هر فصل دو آزمون نیمهٔ اول و نیمهٔ دوم آماده شده است.',
            'heroTitle' => 'ترابی‌نژاد - فصول ۱۱ تا ۱۵',
            'heroDescription' => 'این بازه مربوط به واحد کمپلکس پالپ و پری‌اپیکال است، شامل ۵ فصل است و برای هر فصل دو آزمون ۵۰ سوالی آماده شده است. دسترسی این مجموعه با یک خرید ۳۰ هزارتومانی فعال می‌شود.',
            'path' => '/exams/endotorabinejad/11-15/',
            'paymentTitle' => 'دسترسی به فصول ۱۱ تا ۱۵ ترابی‌نژاد',
            'paymentDescription' => 'با یک بار پرداخت، همهٔ آزمون‌های فصول ۱۱ تا ۱۵ ترابی‌نژاد برای واحد کمپلکس پالپ و پری‌اپیکال روی همین حساب فعال می‌شود.',
            'paymentSuccessMessage' => 'پرداخت شما تایید شد و همهٔ آزمون‌های فصول ۱۱ تا ۱۵ ترابی‌نژاد برای این حساب باز شد.',
            'paymentFailureMessage' => 'پرداخت این بخش تایید نشد. در صورت کسر وجه، نتیجه را دوباره از همین صفحه بررسی کنید.',
            'defaultPaymentMode' => 'paid',
            'defaultAmount' => 300000,
            'exams' => array_values($questionBank),
        ],
    ];
}
