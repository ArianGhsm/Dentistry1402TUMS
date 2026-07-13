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
    $courseTitle = 'آزمون های تمرینی میانترم مبانی کامل نظری';
    $addedAt = '2026-07-13T14:45:00+03:30';

    $sessions = [
        [
            'slug' => '1',
            'label' => 'جلسه ۱',
            'topic' => 'حالت بی‌دندانی',
            'professor' => 'رفیعی',
            'patterns' => ['session1_*.txt'],
        ],
        [
            'slug' => '2',
            'label' => 'جلسه ۲',
            'topic' => 'عوارض استفاده از پروتز کامل',
            'professor' => 'رفیعی',
            'patterns' => ['session2_*.txt'],
        ],
        [
            'slug' => '3',
            'label' => 'جلسه ۳',
            'topic' => 'خصوصیات مواد قالب‌گیری',
            'professor' => 'شریفی',
            'patterns' => ['session3_*.txt'],
        ],
        [
            'slug' => '5-7',
            'label' => 'جلسات ۵ و ۷',
            'topic' => 'ساخت تری اختصاصی، بوردرمولدینگ، قالب‌گیری نهایی و ریختن کست نهایی فک بالا و پایین',
            'professor' => 'اسدی',
            'patterns' => ['session5_7_*.txt'],
        ],
        [
            'slug' => '6',
            'label' => 'جلسه ۶',
            'topic' => 'ساخت رکورد بیس، مراحل رکوردگیری و ثبت روابط عمودی و افقی فکی',
            'professor' => 'عطری',
            'patterns' => ['session6_*.txt'],
        ],
    ];

    $examDefinitions = [];
    foreach ($sessions as $session) {
        $examDefinitions[] = [
            'slug' => (string) $session['slug'],
            'label' => (string) $session['label'],
            'topic' => (string) $session['topic'],
            'patterns' => $session['patterns'],
            'parser' => 'dent_exams_complete_foundations_theory_midterm_practice_parse_file',
        ];
    }

    $course = dent_exams_text_source_build_course([
        'courseSlug' => $courseSlug,
        'title' => $courseTitle,
        'shortTitle' => 'تمرینی میانترم',
        'termLabel' => 'ترم ۶',
        'dataDir' => __DIR__ . '/data/complete_foundations_theory_midterm_practice',
        'paymentAmount' => 300000,
        'examDefinitions' => $examDefinitions,
    ]);

    if ($course === []) {
        return [];
    }

    $sessionBySlug = [];
    foreach ($sessions as $session) {
        $sessionBySlug[(string) $session['slug']] = $session;
    }

    $totalQuestions = 0;
    foreach (($course['exams'] ?? []) as $index => $exam) {
        if (!is_array($exam)) {
            continue;
        }

        $slug = (string) ($exam['slug'] ?? '');
        $session = is_array($sessionBySlug[$slug] ?? null) ? $sessionBySlug[$slug] : [];
        $topic = (string) ($session['topic'] ?? '');
        $professor = (string) ($session['professor'] ?? '');
        $questionCount = max(0, (int) ($exam['questionCount'] ?? 0));
        $questionCountFa = dent_exams_text_source_to_persian_digits((string) $questionCount);
        $totalQuestions += $questionCount;

        $course['exams'][$index]['addedAt'] = $addedAt;
        $course['exams'][$index]['subtitle'] = $questionCountFa . ' سوال چهارگزینه‌ای با پاسخ تشریحی'
            . ($professor !== '' ? ' • استاد ' . $professor : '');
        $course['exams'][$index]['description'] = 'مرور ' . $questionCountFa . ' سوال از مبحث «' . $topic . '» در میانترم مبانی کامل نظری.';
        $course['exams'][$index]['siteBadge'] = 'میانترم مبانی کامل نظری';
        $course['exams'][$index]['comingSoon'] = false;
        $course['exams'][$index]['countsTowardStats'] = true;

        foreach ((is_array($exam['questions'] ?? null) ? $exam['questions'] : []) as $questionIndex => $question) {
            if (!is_array($question)) {
                continue;
            }
            if (empty($question['topic'])) {
                $course['exams'][$index]['questions'][$questionIndex]['topic'] = $topic;
            }
        }
    }

    $examCount = count(is_array($course['exams'] ?? null) ? $course['exams'] : []);
    $examCountFa = dent_exams_text_source_to_persian_digits((string) $examCount);
    $questionCountFa = dent_exams_text_source_to_persian_digits((string) $totalQuestions);

    $course['addedAt'] = $addedAt;
    $course['title'] = $courseTitle;
    $course['shortTitle'] = 'تمرینی میانترم';
    $course['badge'] = $examCountFa . ' آزمون';
    $course['cardDescription'] = $examCountFa . ' آزمون میانترم مبانی کامل نظری با مجموع ' . $questionCountFa . ' سوال و پاسخ تشریحی در این بخش قرار گرفت.';
    $course['heroTitle'] = $courseTitle;
    $course['heroDescription'] = 'این مجموعه برای درس پروتز کامل نظری ترم ۶ آماده شده و شامل ' . $examCountFa
        . ' آزمون فعال با مجموع ' . $questionCountFa
        . ' سوال است. جلسه‌های ۵ و ۷ به‌دلیل هم‌پوشانی مبحث، در یک آزمون مشترک قرار گرفته‌اند. با یک بار پرداخت ۳۰ هزار تومان، کل این مجموعه برای همین حساب فعال می‌شود.';
    $course['paymentTitle'] = 'دسترسی به ' . $courseTitle;
    $course['paymentDescription'] = 'با یک بار پرداخت ۳۰ هزار تومان، همه آزمون‌های میانترم مبانی کامل نظری برای همین حساب فعال می‌شود.';
    $course['paymentSuccessMessage'] = 'پرداخت شما تایید شد و همه آزمون‌های ' . $courseTitle . ' برای این حساب باز شد.';
    $course['paymentFailureMessage'] = 'فعال‌سازی ' . $courseTitle . ' انجام نشد. نتیجه را دوباره بررسی کنید.';

    return $course;
}

function dent_exams_complete_foundations_theory_midterm_practice_parse_file(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [
            'topic' => '',
            'questions' => [],
        ];
    }

    $text = dent_exams_text_source_normalize_text($raw);
    $parts = preg_split('/^\s*پاسخنامه\s+تشریحی\s*$/miu', $text, 2);
    $questionText = trim((string) ($parts[0] ?? $text));
    $answerText = trim((string) ($parts[1] ?? ''));

    $questionMap = dent_exams_text_source_collect_questions($questionText);
    $answerMap = dent_exams_complete_foundations_theory_midterm_practice_collect_answers($answerText);
    $questions = [];

    foreach ($questionMap as $questionData) {
        if (!is_array($questionData)) {
            continue;
        }

        $number = max(0, (int) ($questionData['number'] ?? 0));
        $options = is_array($questionData['options'] ?? null) ? array_values($questionData['options']) : [];
        $answerData = is_array($answerMap[$number] ?? null) ? $answerMap[$number] : null;
        if ($number <= 0 || count($options) !== 4 || $answerData === null) {
            continue;
        }

        $questions[] = [
            'number' => $number,
            'question' => (string) ($questionData['question'] ?? ''),
            'options' => $options,
            'correctIndex' => max(0, min(3, (int) ($answerData['correctIndex'] ?? 0))),
            'explanation' => (string) ($answerData['explanation'] ?? ''),
            'reference' => (string) ($answerData['reference'] ?? ''),
            'optionRationales' => is_array($answerData['optionRationales'] ?? null) ? $answerData['optionRationales'] : [],
            'answerMeta' => is_array($answerData['answerMeta'] ?? null) ? $answerData['answerMeta'] : [],
        ];
    }

    return [
        'topic' => '',
        'questions' => $questions,
    ];
}

function dent_exams_complete_foundations_theory_midterm_practice_collect_answers(string $text): array
{
    if ($text === '') {
        return [];
    }

    $parts = preg_split('/^\s*س(?:ؤ|و)ال\s*(\d+)\s*$/mu', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $answers = [];

    for ($index = 1; $index + 1 < count($parts); $index += 2) {
        $number = max(0, (int) ($parts[$index] ?? 0));
        $chunk = trim((string) ($parts[$index + 1] ?? ''));
        if ($number <= 0 || $chunk === '') {
            continue;
        }

        $answer = dent_exams_complete_foundations_theory_midterm_practice_parse_answer_chunk($chunk);
        if ($answer !== null) {
            $answers[$number] = $answer;
        }
    }

    return $answers;
}

function dent_exams_complete_foundations_theory_midterm_practice_parse_answer_chunk(string $chunk): ?array
{
    $lines = preg_split('/\R/u', $chunk) ?: [];
    $correctRaw = '';
    $reasonLines = [];
    $analysisLines = [];
    $referenceLines = [];
    $section = '';

    for ($index = 0; $index < count($lines); $index++) {
        $line = trim((string) $lines[$index]);
        if ($line === '') {
            continue;
        }

        if (preg_match('/^پاسخ\s+درست\s*:\s*(.*)$/u', $line, $matches) === 1) {
            $tail = trim((string) ($matches[1] ?? ''));
            if ($tail === '') {
                for ($next = $index + 1; $next < count($lines); $next++) {
                    $candidate = trim((string) $lines[$next]);
                    if ($candidate !== '') {
                        $tail = $candidate;
                        $index = $next;
                        break;
                    }
                }
            }
            $correctRaw = $tail;
            $section = '';
            continue;
        }

        if (preg_match('/^دلیل\s+درست(?:‌|-)?بودن\s*:\s*$/u', $line) === 1) {
            $section = 'reason';
            continue;
        }

        if (preg_match('/^بررسی\s+گزینه‌ها\s*:\s*$/u', $line) === 1) {
            $section = 'analysis';
            continue;
        }

        if (preg_match('/^محل\s+پاسخ\s+در\s+منبع\s*:\s*$/u', $line) === 1) {
            $section = 'reference';
            continue;
        }

        if ($section === 'reason') {
            $reasonLines[] = $line;
        } elseif ($section === 'analysis') {
            $analysisLines[] = $line;
        } elseif ($section === 'reference') {
            $referenceLines[] = $line;
        }
    }

    $letter = dent_exams_complete_foundations_theory_midterm_practice_extract_answer_letter($correctRaw);
    if ($letter === '') {
        return null;
    }

    $correctIndex = dent_exams_text_source_option_letter_to_index($letter);
    $reference = dent_exams_complete_foundations_theory_midterm_practice_join_lines($referenceLines);
    $optionRationales = dent_exams_complete_foundations_theory_midterm_practice_option_rationales($analysisLines);
    $explanation = dent_exams_complete_foundations_theory_midterm_practice_compose_explanation(
        $correctRaw,
        $reasonLines,
        $analysisLines,
        $referenceLines
    );

    $answerMeta = [
        [
            'label' => 'پاسخ درست',
            'value' => dent_exams_text_source_restore_digits($correctRaw),
            'tone' => 'success',
        ],
    ];
    if ($reference !== '') {
        $answerMeta[] = [
            'label' => 'محل پاسخ در منبع',
            'value' => $reference,
            'tone' => 'info',
            'wide' => true,
        ];
    }

    return [
        'correctIndex' => $correctIndex,
        'explanation' => $explanation,
        'reference' => $reference,
        'optionRationales' => $optionRationales,
        'answerMeta' => $answerMeta,
    ];
}

function dent_exams_complete_foundations_theory_midterm_practice_extract_answer_letter(string $value): string
{
    $value = trim($value);
    if (preg_match('/گزینه\s*(الف|ب|ج|د|a|b|c|d)/iu', $value, $matches) === 1) {
        return (string) ($matches[1] ?? '');
    }
    if (preg_match('/^(الف|ب|ج|د|a|b|c|d)$/iu', $value, $matches) === 1) {
        return (string) ($matches[1] ?? '');
    }
    return '';
}

function dent_exams_complete_foundations_theory_midterm_practice_compose_explanation(
    string $correctRaw,
    array $reasonLines,
    array $analysisLines,
    array $referenceLines
): string {
    $sections = [];
    if (trim($correctRaw) !== '') {
        $sections[] = '**پاسخ درست:** ' . dent_exams_text_source_restore_digits(trim($correctRaw));
    }
    if ($reasonLines !== []) {
        $sections[] = "**دلیل درست‌بودن:**\n" . dent_exams_complete_foundations_theory_midterm_practice_join_lines($reasonLines);
    }
    if ($analysisLines !== []) {
        $sections[] = "**بررسی گزینه‌ها:**\n" . dent_exams_complete_foundations_theory_midterm_practice_join_lines($analysisLines);
    }
    if ($referenceLines !== []) {
        $sections[] = "**محل پاسخ در منبع:**\n" . dent_exams_complete_foundations_theory_midterm_practice_join_lines($referenceLines);
    }

    return implode("\n\n", array_values(array_filter($sections, static fn(string $section): bool => trim($section) !== '')));
}

function dent_exams_complete_foundations_theory_midterm_practice_option_rationales(array $analysisLines): array
{
    $rationales = ['', '', '', ''];
    $currentIndex = null;

    foreach ($analysisLines as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }

        if (preg_match('/^(الف|ب|ج|د)\)\s*(.+)$/u', $line, $matches) === 1) {
            $currentIndex = dent_exams_text_source_option_letter_to_index((string) ($matches[1] ?? ''));
            $rationales[$currentIndex] = dent_exams_text_source_restore_digits(trim((string) ($matches[2] ?? '')));
            continue;
        }

        if ($currentIndex !== null) {
            $rationales[$currentIndex] = trim($rationales[$currentIndex] . ' ' . dent_exams_text_source_restore_digits($line));
        }
    }

    return $rationales;
}

function dent_exams_complete_foundations_theory_midterm_practice_join_lines(array $lines): string
{
    $clean = [];
    foreach ($lines as $line) {
        $line = trim(preg_replace('/[ \t]+/u', ' ', (string) $line) ?? (string) $line);
        if ($line !== '') {
            $clean[] = dent_exams_text_source_restore_digits($line);
        }
    }

    return implode("\n", $clean);
}
