<?php
declare(strict_types=1);

function dent_exams_partial_foundations_midterm_sample_course(): array
{
    $slug = 'partial-foundations-midterm-sample';
    $path = '/exams/' . $slug . '/';
    $title = 'نمونه‌سؤالات میان‌ترم مبانی پروتز پارسیل ۱';
    $examTitle = 'نمونه‌سؤالات میان‌ترم مبانی پروتز پارسیل ۱ — ۴۱ سؤال';
    return [
        'slug' => $slug,
        'addedAt' => '2026-11-08T00:30:00+03:30',
        'title' => $title,
        'shortTitle' => 'میان‌ترم مبانی پارسیل',
        'badge' => '۴۱ سؤال',
        'cardDescription' => '۴۱ سؤال میان‌ترم ترم ۷؛ پاسخ مستقل، بررسی گزینه‌ها، منابع جلسات و یادداشت‌های آموزشی.',
        'heroTitle' => $title,
        'heroDescription' => 'مجموعه ۴۱ سؤال میان‌ترم مبانی پروتز پارسیل ۱ برای ترم ۷، با پاسخ مستقل و تحلیل هر گزینه. در چهار سؤال، کلید اسکن با بررسی مستقل متفاوت است. سؤال تصویریِ شماره ۶ به علت در دسترس نبودن تصویر اسکن، در نمره لحاظ نمی‌شود. هزینه دسترسی طبق تعرفه استاندارد آزمون‌ها ۴۵ هزار تومان است.',
        'path' => $path,
        'paymentTitle' => 'دسترسی به ' . $title,
        'paymentDescription' => 'با پرداخت ۴۵ هزار تومان، این مجموعه برای حساب شما فعال می‌شود.',
        'paymentSuccessMessage' => 'پرداخت تأیید شد و آزمون میان‌ترم مبانی پروتز پارسیل ۱ برای این حساب فعال شد.',
        'paymentFailureMessage' => 'پرداخت تأیید نشد. وضعیت را دوباره بررسی کنید.',
        'defaultPaymentMode' => 'paid',
        'defaultAmount' => 450000,
        'exams' => [[
            'slug' => 'midterm-41',
            'path' => $path . 'midterm-41/',
            'questionCount' => 41,
            'label' => 'میان‌ترم مبانی پارسیل — ۴۱ سؤال',
            'title' => $examTitle,
            'subtitle' => '۴۱ سؤال، ۴۰ سؤال قابل نمره‌دهی؛ کلید اسکن، پاسخ مستقل، تحلیل گزینه‌ها و مرجع جلسه در کادرهای جداگانه.',
            'description' => 'مرور ۴۱ سؤال نمونه میان‌ترم مبانی پارسیل با تحلیل تشریحی.',
            'eyebrow' => 'ترم ۷ | مبانی پروتز پارسیل ۱',
            'ctaLabel' => 'انتخاب حالت و شروع آزمون',
            'backHref' => $path,
            'backLabel' => 'بازگشت به فهرست میان‌ترم مبانی پارسیل',
            'autoAdvance' => true,
            'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
            'siteSubtitle' => 'آزمون‌ها',
            'siteBadge' => 'نمونه‌سؤالات میان‌ترم',
            'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
            'attemptable' => true,
            'comingSoon' => false,
            'countsTowardStats' => true,
            'addedAt' => '2026-11-08T00:30:00+03:30',
        ]],
    ];
}

function dent_exams_partial_foundations_midterm_sample_runtime_exam_payload(
    string $catalogKey, string $courseSlug, string $examSlug
): ?array {
    if ($catalogKey !== 'shared' || $courseSlug !== 'partial-foundations-midterm-sample' || $examSlug !== 'midterm-41') {
        return null;
    }
    dent_exams_require_optional_module('exams_partial_foundations_midterm_sample_questions.php');
    $questions = dent_exams_partial_foundations_midterm_sample_questions();
    if (!is_array($questions) || count($questions) !== 41) {
        return null;
    }
    $course = dent_exams_partial_foundations_midterm_sample_course();
    return array_replace($course['exams'][0], ['questions' => $questions]);
}
