<?php
declare(strict_types=1);

/**
 * Original 24-question orthodontics midterm answer-sheet transcript. Exact authored
 * question/option text and independent explanations are compiled into JSON.
 * The native site quiz reads this through the existing on-demand payload API.
 */
function dent_exams_orthodontics_midterm_sample_questions(): array
{
    static $questions = null;
    if (is_array($questions)) {
        return $questions;
    }
    // PHP source, not directly downloadable answer JSON on the public web root.
    require_once __DIR__ . '/exams_orthodontics_midterm_sample_questions.php';
    $parsed = dent_exams_orthodontics_midterm_sample_source();
    if (!is_array($parsed) || count($parsed) !== 24) {
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

function dent_exams_orthodontics_midterm_sample_course(): array
{
    $slug = 'orthodontics-theory-1-midterm-sample';
    $path = '/exams/' . $slug . '/';
    $title = 'نمونه‌سؤالات میان‌ترم ارتودانتیکس نظری ۱';
    $addedAt = '2026-11-27T10:30:00+03:30';

    return [
        'slug' => $slug,
        'addedAt' => $addedAt,
        'title' => $title,
        'shortTitle' => 'نمونه‌سؤالات ارتودانتیکس نظری ۱',
        'badge' => '۲۴ سؤال',
        'cardDescription' => '۲۴ سؤال میان‌ترم با پاسخ مستقل، کلید علامت‌خورده در اسکن، بررسی تک‌تک گزینه‌ها، رفرنس جلسه و مرور آموزشی.',
        'heroTitle' => $title,
        'heroDescription' => '۲۴ سؤال نمونهٔ میان‌ترم ارتودانتیکس نظری ۱ همراه با کلید اسکن، پاسخ علمی مستقل، علت درست یا غلط بودن هر گزینه و ارجاع به جزوه. پاسخ سؤال ۵ با کلید اسکن متفاوت است؛ سؤال‌های ۲۰ تا ۲۴ خارج از بازهٔ جلسات ۱ تا ۸ هستند و با منابع تکمیلی جلسات ۹ و ۱۰ پاسخ داده شده‌اند؛ سؤال ۱۹ نیز از جلسهٔ ۱۰ تکمیل شده است. ابهام‌های متن و کلید در پاسخ‌نامه مشخص شده‌اند.',
        'path' => $path,
        'paymentTitle' => 'دسترسی به نمونه‌سؤالات میان‌ترم ارتودانتیکس نظری ۱',
        'paymentDescription' => 'با یک بار پرداخت ۴۵ هزار تومان، مجموعهٔ ۲۴ سؤال نمونه‌سؤالات میان‌ترم ارتودانتیکس نظری ۱ برای همین حساب فعال می‌شود.',
        'paymentSuccessMessage' => 'پرداخت تأیید شد و نمونه‌سؤالات میان‌ترم ارتودانتیکس نظری ۱ برای همین حساب فعال شد.',
        'paymentFailureMessage' => 'پرداخت تأیید نشد. وضعیت را دوباره بررسی کنید.',
        'defaultPaymentMode' => 'paid',
        'defaultAmount' => 450000,
        'exams' => [[
            'slug' => 'midterm-24',
            'path' => $path . 'midterm-24/',
            'questionCount' => 24,
            'label' => 'نمونه‌سؤالات میان‌ترم — ۲۴ سؤال',
            'title' => 'نمونه‌سؤالات میان‌ترم ارتودانتیکس نظری ۱ — ۲۴ سؤال',
            'subtitle' => '۲۴ سؤال با کلید مستقل، بررسی همهٔ گزینه‌ها، پاسخ مستقل، انتخاب اسکن و منابع جلسات.',
            'description' => '۲۴ سؤال اصلی میان‌ترم ارتودانتیکس نظری ۱ همراه با پاسخ‌نامهٔ تشریحی مستقل و ارجاع به جزوه.',
            'eyebrow' => 'ارتودانتیکس نظری ۱ | نمونه‌سؤالات میان‌ترم',
            'ctaLabel' => 'انتخاب حالت و شروع آزمون',
            'backHref' => $path,
            'backLabel' => 'بازگشت به نمونه‌سؤالات میان‌ترم ارتودانتیکس نظری ۱',
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

function dent_exams_orthodontics_midterm_sample_runtime_exam_payload(
    string $catalogKey,
    string $courseSlug,
    string $examSlug
): ?array {
    if ($catalogKey !== 'shared'
        || $courseSlug !== 'orthodontics-theory-1-midterm-sample'
        || $examSlug !== 'midterm-24'
    ) {
        return null;
    }
    $questions = dent_exams_orthodontics_midterm_sample_questions();
    if (count($questions) !== 24) {
        return null;
    }
    $course = dent_exams_orthodontics_midterm_sample_course();
    return array_replace($course['exams'][0], ['questions' => $questions]);
}
