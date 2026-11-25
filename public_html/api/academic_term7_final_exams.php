<?php
declare(strict_types=1);

/** Owner-confirmed final examination timetable, first semester 1405–1406.
 * Dates are Gregorian machine keys; Jalali labels are derived, never duplicated.
 * Add later official exams here with stable identifiers and actual start times.
 */
function dent_term7_final_exams(): array
{
    $schedule = [
        ['endo-theory-1', '2026-12-09', '14:30', 'اندو نظری ۱'],
        ['oral-health-theory-2', '2026-12-15', '14:30', 'سلامت دهان نظری ۲'],
        ['ent', '2026-12-28', '07:15', 'گوش و حلق و بینی'],
        ['perio-theory-1', '2027-01-02', '07:15', 'پریو نظری ۱'],
        ['ortho-theory-1', '2027-01-11', '13:00', 'ارتو نظری ۱'],
        ['research-methods-2', '2027-01-20', '10:00', 'روش تحقیق ۲'],
        ['diagnostic-3', '2027-01-25', '08:30', 'تشخیصی ۳'],
        ['partial-theory', '2027-01-30', '12:00', 'مبانی پروتز پارسیل نظری'],
    ];
    $timezone = new DateTimeZone(DENT_TERM7_TIMEZONE);
    $rows = [];
    foreach ($schedule as [$id, $date, $time, $title]) {
        $start = new DateTimeImmutable($date . ' ' . $time, $timezone);
        $rows[] = [
            'id' => $id, 'title' => $title,
            'localDate' => $date, 'startsAt' => $start->format(DATE_ATOM),
            'jalaliDate' => dent_term7_jalali_key($start),
            'weekdayLabel' => dent_term7_weekday_label((int) $start->format('N')),
            'start' => $time, 'location' => 'آمفی‌تئاتر ۴۰۰ نفره',
            'typeLabel' => 'پایان‌ترم نظری',
        ];
    }
    usort($rows, static fn(array $a, array $b): int => strcmp($a['startsAt'], $b['startsAt']));
    return $rows;
}

function dent_term7_final_exam_payload(): array
{
    $rows = dent_term7_final_exams();
    return [
        'semesterLabel' => 'نیمسال اول ۱۴۰۵–۱۴۰۶',
        'revision' => substr(hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), 0, 24),
        'rows' => $rows,
    ];
}

/** Inclusive calendar horizon from the selected local day through day + 10. */
function dent_term7_upcoming_final_exams(DateTimeImmutable $date): array
{
    $day = $date->setTimezone(new DateTimeZone(DENT_TERM7_TIMEZONE))->setTime(0, 0);
    $from = $day->format('Y-m-d');
    $through = $day->modify('+10 days')->format('Y-m-d');
    return array_values(array_filter(dent_term7_final_exams(), static fn(array $row): bool =>
        $row['localDate'] >= $from && $row['localDate'] <= $through
    ));
}

function dent_term7_final_exam_due(DateTimeImmutable $now): array
{
    $due = [];
    foreach (dent_term7_final_exams() as $row) {
        $start = new DateTimeImmutable($row['startsAt']);
        $remaining = $start->getTimestamp() - $now->getTimestamp();
        foreach ([168 => 'یک هفته قبل', 24 => '۲۴ ساعت قبل', 12 => '۱۲ ساعت قبل'] as $hours => $label) {
            $seconds = $hours * 3600;
            // One-hour recovery window; never emit stale reminders after it.
            if ($remaining <= $seconds && $remaining > $seconds - 3600) {
                $key = 'term7-final:' . $row['id'] . ':' . $row['localDate'] . ':' . $row['start'] . ':' . $hours;
                $due[] = ['key' => $key, 'label' => $label, 'row' => $row];
            }
        }
    }
    return $due;
}

function dent_term7_final_exam_body(array $rows): string
{
    $lines = [];
    foreach ($rows as $row) {
        $lines[] = $row['title'] . ' | ' . $row['weekdayLabel'] . ' '
            . dent_to_fa_digits($row['jalaliDate']) . ' | ساعت '
            . dent_to_fa_digits($row['start']) . ' | ' . $row['location'];
    }
    return implode("\n", $lines);
}
