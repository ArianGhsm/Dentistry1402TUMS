<?php
declare(strict_types=1);

/**
 * Diagnostic 3 midterm practice. The reviewed per-session question data is
 * the source of truth; the shared exam UI/attempt/report flow stays unchanged.
 */
function dent_exams_diagnostics3_midterm_practice_data_dir(): string
{
    return __DIR__ . '/data/diagnostics3_midterm_practice';
}

function dent_exams_diagnostics3_midterm_practice_session_index(): array
{
    static $index = null;
    if (is_array($index)) {
        return $index;
    }

    $index = [];
    $paths = glob(dent_exams_diagnostics3_midterm_practice_data_dir() . '/session-*.txt') ?: [];
    foreach ($paths as $path) {
        $filename = basename($path);
        if (preg_match('/^session-([0-9]+)\.txt$/', $filename, $matches) !== 1) {
            continue;
        }
        $session = (int) $matches[1];
        $raw = @file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)
            || (int) ($data['session'] ?? -1) !== $session
            || trim((string) ($data['topic'] ?? '')) === ''
            || !is_array($data['questions'] ?? null)
            || count($data['questions']) !== (int) ($data['questionCount'] ?? -1)
            || count($data['questions']) < 1
        ) {
            continue;
        }
        $index[$session] = [
            'session' => $session,
            'topic' => (string) $data['topic'],
            'questionCount' => (int) $data['questionCount'],
            'path' => $path,
        ];
    }
    ksort($index, SORT_NUMERIC);
    return $index;
}

function dent_exams_diagnostics3_midterm_practice_fa(int $number): string
{
    return strtr((string) $number, [
        '0'=>'۰', '1'=>'۱', '2'=>'۲', '3'=>'۳', '4'=>'۴',
        '5'=>'۵', '6'=>'۶', '7'=>'۷', '8'=>'۸', '9'=>'۹',
    ]);
}

function dent_exams_diagnostics3_midterm_practice_catalog_course(): array
{
    $courseSlug = 'diagnostics-3-midterm-practice';
    $title = 'آزمون‌های تمرینی میان‌ترم تشخیصی ۳';
    $path = '/exams/' . $courseSlug . '/';
    $addedAt = '2026-11-17T17:00:00+03:30';
    $sessions = dent_exams_diagnostics3_midterm_practice_session_index();
    $exams = [];
    $total = 0;

    foreach ($sessions as $session) {
        $number = (int) $session['session'];
        $topic = (string) $session['topic'];
        $count = (int) $session['questionCount'];
        $label = 'جلسهٔ ' . dent_exams_diagnostics3_midterm_practice_fa($number);
        $total += $count;
        $exams[] = [
            'slug' => (string) $number,
            'path' => $path . $number . '/',
            'label' => $label,
            'title' => $label . ' | ' . $topic,
            'subtitle' => dent_exams_diagnostics3_midterm_practice_fa($count) . ' سؤال چهارگزینه‌ای با پاسخ تشریحی',
            'description' => 'آزمون تمرینی ' . $label . ' از میان‌ترم تشخیصی ۳، مبحث «' . $topic . '».',
            'eyebrow' => 'تشخیصی ۳ | میان‌ترم | ' . $label,
            'ctaLabel' => 'انتخاب حالت و شروع آزمون',
            'backHref' => $path,
            'backLabel' => 'بازگشت به آزمون‌های تمرینی تشخیصی ۳',
            'autoAdvance' => true,
            'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
            'siteSubtitle' => 'آزمون‌های تمرینی',
            'siteBadge' => 'میان‌ترم تشخیصی ۳',
            'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
            'attemptable' => true,
            'comingSoon' => false,
            'countsTowardStats' => true,
            'questionCount' => $count,
            'addedAt' => $addedAt,
        ];
    }
    $examCount = dent_exams_diagnostics3_midterm_practice_fa(count($exams));
    $questionCount = dent_exams_diagnostics3_midterm_practice_fa($total);
    return [
        'slug' => $courseSlug,
        'title' => $title,
        'shortTitle' => 'میان‌ترم تشخیصی ۳',
        'badge' => $examCount . ' آزمون',
        'cardDescription' => $examCount . ' آزمون جلسه‌ای با مجموع ' . $questionCount . ' سؤال چهارگزینه‌ای و پاسخ تشریحی.',
        'heroTitle' => $title,
        'heroDescription' => 'آزمون‌های جلسه‌به‌جلسهٔ تشخیصی ۳، در قالب آزمون‌گیر مشترک سایت همراه با مرور، کارنامه و پاسخ تشریحی. فقط جلساتی نمایش داده می‌شوند که سؤال و کلید معتبر دارند.',
        'path' => $path,
        'defaultPaymentMode' => 'free',
        'defaultAmount' => 0,
        'addedAt' => $addedAt,
        'exams' => $exams,
    ];
}

function dent_exams_diagnostics3_midterm_practice_runtime_exam_payload(
    string $catalogKey,
    string $courseSlug,
    string $examSlug
): ?array {
    if ($catalogKey !== 'shared' || $courseSlug !== 'diagnostics-3-midterm-practice') {
        return null;
    }
    if (!ctype_digit($examSlug)) {
        return null;
    }

    $sessions = dent_exams_diagnostics3_midterm_practice_session_index();
    $session = $sessions[(int) $examSlug] ?? null;
    if (!is_array($session)) {
        return null;
    }
    $raw = @file_get_contents((string) $session['path']);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data) || !is_array($data['questions'] ?? null)) {
        return null;
    }
    $questions = $data['questions'];
    if (count($questions) !== (int) $session['questionCount']) {
        return null;
    }
    foreach ($questions as $question) {
        if (!is_array($question)
            || trim((string) ($question['question'] ?? '')) === ''
            || !is_array($question['options'] ?? null)
            || count($question['options']) !== 4
            || !is_int($question['correctIndex'] ?? null)
            || $question['correctIndex'] < 0
            || $question['correctIndex'] > 3
            || trim((string) ($question['explanation'] ?? '')) === ''
        ) {
            return null;
        }
    }
    return [
        'questions' => $questions,
        'questionCount' => count($questions),
        'attemptable' => true,
        'comingSoon' => false,
    ];
}
