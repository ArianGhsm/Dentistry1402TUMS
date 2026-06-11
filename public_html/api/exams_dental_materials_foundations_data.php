<?php
declare(strict_types=1);

require_once __DIR__ . '/text_source_parser.php';

function dent_exams_dental_materials_foundations_course(): array
{
    static $course = null;
    if (is_array($course)) {
        return $course;
    }

    $course = dent_exams_text_source_build_course([
        'courseSlug' => 'dental-materials-foundations',
        'title' => 'مبانی مواد دندانی',
        'shortTitle' => 'مواد دندانی',
        'termLabel' => 'ترم ۶',
        'dataDir' => __DIR__ . '/data/dental_materials_foundations',
        'paymentAmount' => 300000,
        'examDefinitions' => [
            ['slug' => '5', 'label' => 'جلسه ۵', 'patterns' => ['session5*.txt']],
            ['slug' => '6', 'label' => 'جلسه ۶', 'patterns' => ['session6*.txt']],
            ['slug' => '7', 'label' => 'جلسه ۷', 'patterns' => ['session7*.txt']],
        ],
    ]);

    return $course;
}
