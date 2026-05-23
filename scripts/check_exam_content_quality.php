<?php
declare(strict_types=1);

require_once __DIR__ . '/../public_html/api/exams_store.php';

restore_exception_handler();

const EXAM_TEXT_MOJIBAKE_RE = '/(?:Ã˜|Ã™|Ã¢â‚¬|Ã¯Â»Â¿|ï¿½)/u';
const EXAM_TEXT_PRESENTATION_FORMS_RE = '/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u';
const EXAM_TEXT_HTML_RE = '/<\s*\/?\s*(?:strong|br|div|span|p|ul|ol|li|em|b|i|small|sub|sup|a|img|section|article|h[1-6])(?:\s+[^>]*)?>/iu';
const EXAM_TEXT_ARTIFACT_RE = '/(?:'
    . '\x{0646}\x{0627}\x{0645}\s+\x{0641}\x{0627}\x{06CC}\x{0644}\s+\x{0645}\x{0628}\x{062F}\x{0627}'
    . '|'
    . '\x{067E}\x{0627}\x{0633}\x{062E}\x{0646}\x{0627}\x{0645}\x{0647}\s+\x{062A}\x{0634}\x{0631}\x{06CC}\x{062D}\x{06CC}'
    . '|'
    . '\x{062A}\x{0639}\x{062F}\x{0627}\x{062F}\s+\x{0633}\x{0648}\x{0627}\x{0644}\x{0627}\x{062A}'
    . '|'
    . '(?:^|[\s|])\x{0641}\x{0648}\x{0646}\x{062A}(?:$|[\s|])'
    . '|'
    . '\.zip\s+\|\s+\x{0628}\x{062E}\x{0634}'
    . '|White & Pharoah\s+\|\s+\x{0635}\x{0641}\x{062D}\x{0647}'
    . '|Pasted text'
    . '|Paste\s+\x{0634}\x{062F}\x{0647}\s+\x{0646}\x{06CC}\x{0627}\x{0645}\x{062F}\x{0647}\s+\x{0627}\x{0633}\x{062A}'
    . ')/u';
const EXAM_TEXT_REVERSED_RE = '/(?:'
    . '\b\x{06CC}\x{0645}\b'
    . '|\b\x{062F}\x{0648}\x{0634}\b'
    . '|\b\x{0647}\x{062F}\x{0634}\b'
    . '|\b\x{062F}\x{0646}\x{06A9}\b'
    . '|\b\x{0646}\x{0627}\x{0632}\x{06CC}\x{0646}\x{0648}\x{06CC}\b'
    . '|\b\x{0648}\x{062A}\x{0631}\x{067E}\b'
    . '|\b\x{06CC}\x{06A9}\x{0634}\x{0632}\x{067E}\b'
    . '|\b\x{0646}\x{06CC}\x{0631}\x{062A}\x{0634}\x{06CC}\x{0628}\b'
    . '|\b\x{062A}\x{0628}\x{0633}\x{0646}\b'
    . '|\b\x{062A}\x{0644}\x{0639}\b'
    . '|\b\x{062F}\x{062A}\x{0641}\x{0627}\b'
    . ')/u';

function exam_quality_cli_targets(array $argv): array
{
    $targets = [];
    $scanAll = false;

    for ($index = 1, $count = count($argv); $index < $count; $index += 1) {
        $arg = (string) $argv[$index];
        if ($arg === '--all') {
            $scanAll = true;
            continue;
        }
        if ($arg === '--course' && isset($argv[$index + 1])) {
            $targets[] = trim(strtolower((string) $argv[$index + 1]));
            $index += 1;
        }
    }

    if ($scanAll) {
        return ['__all__'];
    }
    if ($targets !== []) {
        return array_values(array_unique(array_filter($targets, static fn (string $value): bool => $value !== '')));
    }

    $git = @shell_exec('git status --short -- public_html/api 2>NUL');
    if (!is_string($git) || trim($git) === '') {
        return [];
    }

    $derived = [];
    foreach (preg_split('/\R+/', trim($git)) as $line) {
        if (!is_string($line) || trim($line) === '') {
            continue;
        }
        if (!preg_match('/public_html\/api\/exams_(.+?)_data\.php$/', str_replace('\\', '/', $line), $match)) {
            continue;
        }
        $derived[] = str_replace('_', '-', strtolower(trim($match[1])));
    }

    return array_values(array_unique(array_filter($derived, static fn (string $value): bool => $value !== '')));
}

function exam_quality_has_explicit_scope(array $argv): bool
{
    return in_array('--all', $argv, true) || in_array('--course', $argv, true);
}

function exam_quality_add_issue(array &$issues, string $path, string $reason, string $value): void
{
    $snippet = trim($value);
    if (function_exists('mb_substr')) {
        $snippet = mb_substr($snippet, 0, 220);
    } else {
        $snippet = substr($snippet, 0, 220);
    }

    $issues[] = [
        'path' => $path,
        'reason' => $reason,
        'value' => $snippet,
    ];
}

function exam_quality_check_string(array &$issues, string $path, mixed $value): void
{
    if (!is_string($value)) {
        return;
    }

    $text = trim($value);
    if ($text === '') {
        return;
    }

    if (preg_match(EXAM_TEXT_MOJIBAKE_RE, $text)) {
        exam_quality_add_issue($issues, $path, 'mojibake', $text);
    }
    if (preg_match(EXAM_TEXT_PRESENTATION_FORMS_RE, $text)) {
        exam_quality_add_issue($issues, $path, 'presentation-forms', $text);
    }
    if (preg_match(EXAM_TEXT_HTML_RE, $text)) {
        exam_quality_add_issue($issues, $path, 'raw-html', $text);
    }
    if (preg_match(EXAM_TEXT_ARTIFACT_RE, $text)) {
        exam_quality_add_issue($issues, $path, 'pdf-artifact', $text);
    }
    if (preg_match(EXAM_TEXT_REVERSED_RE, $text)) {
        exam_quality_add_issue($issues, $path, 'reversed-token', $text);
    }
}

function exam_quality_check_question(array &$issues, array $question, string $path): void
{
    exam_quality_check_string($issues, $path . '.question', $question['question'] ?? '');
    exam_quality_check_string($issues, $path . '.explanation', $question['explanation'] ?? '');

    $options = $question['options'] ?? null;
    if (!is_array($options)) {
        return;
    }

    foreach ($options as $index => $option) {
        exam_quality_check_string($issues, $path . '.options[' . $index . ']', $option);
    }
}

function exam_quality_check_exam(array &$issues, array $exam, string $path): void
{
    foreach (['label', 'title', 'subtitle', 'description', 'eyebrow', 'backLabel', 'siteTitle', 'siteSubtitle', 'siteBadge', 'footerText'] as $field) {
        exam_quality_check_string($issues, $path . '.' . $field, $exam[$field] ?? '');
    }

    $questions = $exam['questions'] ?? null;
    if (!is_array($questions)) {
        return;
    }

    foreach ($questions as $questionIndex => $question) {
        if (is_array($question)) {
            exam_quality_check_question($issues, $question, $path . '.questions[' . $questionIndex . ']');
        }
    }
}

function exam_quality_check_course(array &$issues, array $course, string $path): void
{
    foreach (['title', 'shortTitle', 'badge', 'cardDescription', 'heroTitle', 'heroDescription', 'paymentTitle', 'paymentDescription', 'paymentSuccessMessage', 'paymentFailureMessage'] as $field) {
        exam_quality_check_string($issues, $path . '.' . $field, $course[$field] ?? '');
    }

    $exams = $course['exams'] ?? null;
    if (!is_array($exams)) {
        return;
    }

    foreach ($exams as $examIndex => $exam) {
        if (is_array($exam)) {
            exam_quality_check_exam($issues, $exam, $path . '.exams[' . $examIndex . ']');
        }
    }
}

try {
    $issues = [];
    $targets = exam_quality_cli_targets($argv);
    $hasExplicitScope = exam_quality_has_explicit_scope($argv);
    $catalogs = dent_exams_catalogs();

    foreach ($catalogs as $catalogKey => $catalog) {
        if (!is_array($catalog)) {
            continue;
        }

        $courses = $catalog['courses'] ?? null;
        if (!is_array($courses)) {
            continue;
        }

        foreach ($courses as $courseSlug => $course) {
            $normalizedCourseSlug = strtolower((string) $courseSlug);
            if ($targets !== [] && $targets !== ['__all__'] && !in_array($normalizedCourseSlug, $targets, true)) {
                continue;
            }
            if (is_array($course)) {
                $guardEnabled = !empty($course['qualityGuard']);
                if (!$hasExplicitScope && !$guardEnabled) {
                    continue;
                }
                exam_quality_check_course($issues, $course, 'catalogs.' . $catalogKey . '.courses.' . $courseSlug);
            }
        }
    }

    if ($targets === []) {
        fwrite(STDOUT, "OK: no changed exam course data detected for quality scan.\n");
        exit(0);
    }

    if ($issues !== []) {
        fwrite(STDERR, "ERROR: exam content quality check found " . count($issues) . " issue(s).\n");
        foreach (array_slice($issues, 0, 80) as $issue) {
            fwrite(
                STDERR,
                '- ' . $issue['path'] . ' [' . $issue['reason'] . "] => " . $issue['value'] . "\n"
            );
        }
        exit(1);
    }

    fwrite(STDOUT, "OK: exam content quality check passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "ERROR: exam content quality check crashed.\n");
    fwrite(STDERR, get_class($exception) . ': ' . $exception->getMessage() . "\n");
    exit(1);
}
