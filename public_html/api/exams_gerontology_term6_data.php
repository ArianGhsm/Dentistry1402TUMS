<?php
declare(strict_types=1);

require_once __DIR__ . '/text_source_parser.php';

function dent_exams_gerontology_term6_course(): array
{
    static $course = null;
    if (is_array($course)) {
        return $course;
    }

    $course = dent_exams_text_source_build_course([
        'courseSlug' => 'gerontology-term-6',
        'title' => 'سالمندشناسی',
        'shortTitle' => 'سالمندشناسی',
        'termLabel' => 'ترم ۶',
        'dataDir' => __DIR__ . '/data/gerontology_term6',
        'paymentAmount' => 300000,
        'examDefinitions' => [
            ['slug' => '1', 'label' => 'جلسه ۱', 'patterns' => ['session1*.txt']],
            ['slug' => '2', 'label' => 'جلسه ۲', 'patterns' => ['session2*.txt']],
            ['slug' => '4', 'label' => 'جلسه ۴', 'patterns' => ['session4*.txt']],
            ['slug' => '5', 'label' => 'جلسه ۵', 'patterns' => ['session5*.txt']],
            ['slug' => '6', 'label' => 'جلسه ۶', 'patterns' => ['session6*.txt']],
            ['slug' => '9', 'label' => 'جلسه ۹', 'patterns' => ['session9*.txt']],
        ],
    ]);

    return $course;
}
