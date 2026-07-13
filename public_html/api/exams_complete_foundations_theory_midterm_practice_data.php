<?php
declare(strict_types=1);

require_once __DIR__ . '/text_source_parser.php';

function dent_exams_complete_foundations_theory_midterm_practice_course(): array
{
    static $course = null;
    if (is_array($course)) {
        return $course;
    }

    $courseSlug = 'complete-foundations-theory-midterm-practice';
    $courseTitle = 'آزمون تمرینی جلسه به جلسه میان‌ترم مبانی کامل نظری';
    $addedAt = '2026-07-13T16:30:00+03:30';

    $sessions = [
        ['slug' => '1', 'label' => 'جلسه اول', 'topic' => 'حالت بی‌دندانی', 'professor' => 'رفیعی'],
        ['slug' => '2', 'label' => 'جلسه دوم', 'topic' => 'عوارض استفاده از پروتز کامل', 'professor' => 'رفیعی'],
        ['slug' => '3', 'label' => 'جلسه سوم', 'topic' => 'خصوصیات مواد قالب‌گیری', 'professor' => 'شریفی'],
        ['slug' => '4', 'label' => 'جلسه چهارم', 'topic' => 'آناتومی فانکشنال و قالب‌گیری اولیه فک بالا و پایین', 'professor' => 'شریفی'],
        ['slug' => '5', 'label' => 'جلسه پنجم', 'topic' => 'ساخت تری اختصاصی، بوردرمولدینگ و قالب‌گیری نهایی و ریختن کست نهایی فک بالا', 'professor' => 'اسدی'],
        ['slug' => '6', 'label' => 'جلسه ششم', 'topic' => 'ساخت رکورد بیس، مراحل رکوردگیری و ثبت روابط عمودی و افقی فکی', 'professor' => 'عطری'],
        ['slug' => '7', 'label' => 'جلسه هفتم', 'topic' => 'ساخت تری اختصاصی، بوردرمولدینگ و قالب‌گیری نهایی و ریختن کست نهایی فک پایین', 'professor' => 'اسدی'],
        ['slug' => '8', 'label' => 'جلسه هشتم', 'topic' => 'انتخاب و چیدن دندان‌های قدامی', 'professor' => 'حاج‌محمودی'],
        ['slug' => '9', 'label' => 'جلسه نهم', 'topic' => 'چیدن دندان‌های خلفی در اکلوژن کلاس یک و فلسفه‌های اکلوژن', 'professor' => 'حاج‌محمودی'],
        ['slug' => '10', 'label' => 'جلسه دهم', 'topic' => 'امتحان دندان‌های قدامی و خلفی، بالانس قبل از پخت و تراش سد خلفی', 'professor' => 'جوکار'],
        ['slug' => '11', 'label' => 'جلسه یازدهم', 'topic' => 'مدلاژ و مفل‌گذاری و پرداخت', 'professor' => 'شریفی'],
        ['slug' => '12', 'label' => 'جلسه دوازدهم', 'topic' => 'بالانس بعد از پخت و تحویل', 'professor' => 'مصطفوی'],
    ];

    $examDefinitions = [];
    foreach ($sessions as $session) {
        $examDefinitions[] = [
            'slug' => (string) $session['slug'],
            'label' => (string) $session['label'],
            'topic' => (string) $session['topic'],
            'patterns' => ['session' . (string) $session['slug'] . '*.txt'],
            'parser' => 'dent_exams_text_source_parse_file_multiline',
        ];
    }

    $course = dent_exams_text_source_build_course([
        'courseSlug' => $courseSlug,
        'title' => $courseTitle,
        'shortTitle' => 'تمرینی میان‌ترم',
        'termLabel' => 'ترم ۶',
        'dataDir' => __DIR__ . '/data/complete_foundations_theory_midterm_practice',
        'paymentAmount' => 300000,
        'examDefinitions' => $examDefinitions,
    ]);

    if ($course === []) {
        return [];
    }

    $course['addedAt'] = $addedAt;
    $course['shortTitle'] = 'تمرینی میان‌ترم';
    $course['badge'] = '۱۲ جلسه';
    $course['cardDescription'] = 'فعلاً عنوان و ساختار ۱۲ جلسه‌ی میان‌ترم مبانی پروتز کامل نظری ثبت شده و صفحه‌ی هر جلسه جداگانه آماده است.';
    $course['heroTitle'] = $courseTitle;
    $course['heroDescription'] = 'این مجموعه برای مبانی پروتز کامل نظری ترم ۶ آماده شده است. فعلاً عنوان هر ۱۲ جلسه و صفحه‌ی جداگانه‌ی آن‌ها ثبت شده تا سوال‌ها و پاسخ‌های هر جلسه از همین مسیر تکمیل شوند. با یک بار پرداخت ۳۰ هزار تومان، کل این مجموعه برای همین حساب فعال می‌شود.';

    foreach (($course['exams'] ?? []) as $index => $exam) {
        $session = $sessions[$index] ?? null;
        if (!is_array($exam) || !is_array($session)) {
            continue;
        }

        $label = (string) $session['label'];
        $topic = (string) $session['topic'];
        $professor = (string) $session['professor'];

        $course['exams'][$index]['addedAt'] = $addedAt;
        $course['exams'][$index]['subtitle'] = 'استاد ' . $professor . ' • صفحه‌ی این جلسه آماده شده و سوال‌ها به‌زودی اضافه می‌شوند.';
        $course['exams'][$index]['description'] = 'عنوان این جلسه: «' . $topic . '». سوال‌ها و پاسخ‌های این بخش هنوز اضافه نشده‌اند.';
        $course['exams'][$index]['emptyStateTitle'] = 'سوالات ' . $label . ' به‌زودی اضافه می‌شود';
        $course['exams'][$index]['emptyStateMessage'] = 'صفحه‌ی ' . $label . ' با موضوع «' . $topic . '» و تدریس استاد ' . $professor . ' آماده شده است. سوال‌های این جلسه از همین مسیر اضافه می‌شوند.';
    }

    return $course;
}
