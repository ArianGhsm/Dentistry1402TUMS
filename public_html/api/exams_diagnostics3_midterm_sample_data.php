<?php
declare(strict_types=1);

/**
 * Original 44-question midterm answer-sheet transcript. Exact authored
 * question/option text and independent explanations are compiled into JSON.
 * The native site quiz reads this through the existing on-demand payload API.
 */
function dent_exams_diagnostics3_midterm_sample_questions(): array
{
    static $questions = null;
    if (is_array($questions)) {
        return $questions;
    }
    $path = __DIR__ . '/data/diagnostics3_midterm_sample/midterm-44.json';
    $source = @file_get_contents($path);
    $parsed = is_string($source) ? json_decode($source, true) : null;
    if (!is_array($parsed) || count($parsed) !== 44) {
        return [];
    }
    foreach ($parsed as $index => $question) {
        if (!is_array($question)
            || (int) ($question['number'] ?? 0) !== $index + 1
            || trim((string) ($question['question'] ?? '')) === ''
            || !is_array($question['options'] ?? null)
            || count($question['options']) !== 4
            || !is_array($question['optionRationales'] ?? null)
            || count($question['optionRationales']) !== 4
            || trim((string) ($question['explanation'] ?? '')) === ''
            || !is_array($question['answerMeta'] ?? null)
            || !is_array($question['answerSections'] ?? null)
        ) {
            return [];
        }
        $correctIndex = $question['correctIndex'] ?? null;
        if ($correctIndex !== null && (!is_int($correctIndex) || $correctIndex < 0 || $correctIndex > 3)) {
            return [];
        }
    }
    $questions = $parsed;
    return $questions;
}

function dent_exams_diagnostics3_midterm_sample_course(): array
{
    $slug = 'diagnostics-3-midterm-sample';
    $path = '/exams/' . $slug . '/';
    $title = 'نمونه‌سؤالات میان‌ترم تشخیصی ۳';
    $addedAt = '2026-11-17T23:24:00+03:30';

    return [
        'slug' => $slug,
        'addedAt' => $addedAt,
        'title' => $title,
        'shortTitle' => 'نمونه‌سؤالات تشخیصی ۳',
        'badge' => '۴۴ سؤال',
        'cardDescription' => '۴۴ سؤال میان‌ترم با پاسخ مستقل، کلید علامت‌خورده در اسکن، بررسی تک‌تک گزینه‌ها، رفرنس جلسه و مرور آموزشی.',
        'heroTitle' => $title,
        'heroDescription' => '۴۴ سؤال نمونهٔ میان‌ترم تشخیصی ۳ همراه با کلید اسکن، پاسخ علمی مستقل، علت درست یا غلط بودن هر گزینه و ارجاع به جزوه. سؤال‌های ۲۳، ۲۴ و ۳۰ به علت ابهام در کلید و سؤال ۳۹ به علت در دسترس نبودن تصویر اصلی، فقط برای مطالعه نمایش داده می‌شوند و در محاسبهٔ نمره لحاظ نمی‌شوند.',
        'path' => $path,
        'paymentTitle' => 'دسترسی به نمونه‌سؤالات میان‌ترم تشخیصی ۳',
        'paymentDescription' => 'با یک بار پرداخت ۴۵ هزار تومان، مجموعهٔ ۴۴ سؤال نمونه‌سؤالات میان‌ترم تشخیصی ۳ برای همین حساب فعال می‌شود. این خرید از مجموعهٔ ۱۵ آزمون تمرینی جداست.',
        'paymentSuccessMessage' => 'پرداخت تأیید شد و نمونه‌سؤالات میان‌ترم تشخیصی ۳ برای همین حساب فعال شد.',
        'paymentFailureMessage' => 'پرداخت تأیید نشد. وضعیت را دوباره بررسی کنید.',
        'defaultPaymentMode' => 'paid',
        'defaultAmount' => 450000,
        'exams' => [[
            'slug' => 'midterm-44',
            'path' => $path . 'midterm-44/',
            'questionCount' => 44,
            'label' => 'نمونه‌سؤالات میان‌ترم — ۴۴ سؤال',
            'title' => 'نمونه‌سؤالات میان‌ترم تشخیصی ۳ — ۴۴ سؤال',
            'subtitle' => '۴۴ سؤال؛ ۴۰ سؤال قابل نمره‌دهی، بررسی همهٔ گزینه‌ها، پاسخ مستقل، انتخاب اسکن و منابع جلسات.',
            'description' => '۴۴ سؤال اصلی میان‌ترم تشخیصی ۳ همراه با پاسخ‌نامهٔ تشریحی مستقل و ارجاع به جزوه.',
            'eyebrow' => 'تشخیصی ۳ | نمونه‌سؤالات میان‌ترم',
            'ctaLabel' => 'انتخاب حالت و شروع آزمون',
            'backHref' => $path,
            'backLabel' => 'بازگشت به نمونه‌سؤالات میان‌ترم تشخیصی ۳',
            'autoAdvance' => true,
            'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
            'siteSubtitle' => 'آزمون‌ها',
            'siteBadge' => 'نمونه‌سؤالات میان‌ترم',
            'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
            'attemptable' => true,
            'comingSoon' => false,
            'countsTowardStats' => true,
            'addedAt' => $addedAt,
        ]],
    ];
}

function dent_exams_diagnostics3_midterm_sample_runtime_exam_payload(
    string $catalogKey,
    string $courseSlug,
    string $examSlug
): ?array {
    if ($catalogKey !== 'shared'
        || $courseSlug !== 'diagnostics-3-midterm-sample'
        || $examSlug !== 'midterm-44'
    ) {
        return null;
    }
    $questions = dent_exams_diagnostics3_midterm_sample_questions();
    if (count($questions) !== 44) {
        return null;
    }
    $course = dent_exams_diagnostics3_midterm_sample_course();
    return array_replace($course['exams'][0], ['questions' => $questions]);
}
