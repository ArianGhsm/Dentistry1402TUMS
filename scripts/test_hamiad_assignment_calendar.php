<?php
declare(strict_types=1);
$root = sys_get_temp_dir() . '/dent-hamiad-calendar-test-' . bin2hex(random_bytes(5));
putenv('DENT_STORAGE_ROOT=' . $root);
putenv('DENT_SERVER_ONLY_ROOT=' . $root . '/server-only');
require_once dirname(__DIR__) . '/public_html/api/hamiad_dashboard.php';
function verify_hamiad_calendar(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
try {
    $month = <<<'HTML'
<html><body><table><tr><td data-day="21">
<a data-timestamp="1797021000">۲۱ آذر</a>
<ul><li data-region="event-item" data-event-component="mod_assign" data-event-eventtype="due">
<a href="https://lms.tums.ac.ir/mod/assign/view.php?id=719491">موعد تکلیف پیگمانتاسیون سر رسیده است</a>
</li></ul></td></tr></table></body></html>
HTML;
    $matched=hamiad_calendar_month_events($month,[719491=>true]);
    verify_hamiad_calendar(($matched[719491]??0)===1797021000,'exact assignment ID maps to real calendar day');
    verify_hamiad_calendar(hamiad_calendar_month_events($month,[719486=>true])===[],'other task date cannot leak');
    $spoof=str_replace('https://lms.tums.ac.ir','https://other.example',$month);
    verify_hamiad_calendar(hamiad_calendar_month_events($spoof,[719491=>true])===[],'untrusted event URL rejected');

    $day = <<<'HTML'
<html><body><div data-event-title="موعد تکلیف پیگمانتاسیون سر رسیده است">
<ul><li class="calendaropen">شروع: «شنبه، 25 مهر 1405، 12:00 صبح»</li>
<li class="calendardue">موعد شنبه، 21 آذر 1405، 12:00 صبح سر رسیده است</li>
<li class="calendargradingdue">زمان نمره‌دهی چهارشنبه، 09 دی 1405، 12:00 صبح است</li></ul>
<a href="https://lms.tums.ac.ir/mod/assign/view.php?id=719491">رفتن به فعالیت</a>
</div></body></html>
HTML;
    $dates=hamiad_calendar_day_details($day,719491);
    verify_hamiad_calendar(str_contains($dates['opensText']??'','۲۵ مهر ۱۴۰۵'),'official start date extracted in Persian');
    verify_hamiad_calendar(str_contains($dates['dueDateText']??'','۲۱ آذر ۱۴۰۵'),'official due date extracted in Persian');
    verify_hamiad_calendar(!str_contains($dates['dueDateText'],'سر رسیده'),'misleading event title does not change due status');
    verify_hamiad_calendar(hamiad_calendar_day_details($day,719486)===[],'unknown assignment cannot get another task dates');
    verify_hamiad_calendar(!str_contains(json_encode($dates,JSON_UNESCAPED_UNICODE),'۰۹ دی'),'grading deadline never used as cutoff');

    if (class_exists('IntlCalendar')) {
        $open=hamiad_date_iso($dates['opensText']);
        $due=hamiad_date_iso($dates['dueDateText']);
        verify_hamiad_calendar($open==='2026-10-17T00:00:00+03:30','Persian 12 AM = midnight at opening');
        verify_hamiad_calendar($due==='2026-12-12T00:00:00+03:30','Persian 12 AM = midnight at due');
        verify_hamiad_calendar(hamiad_date_iso('۱ آبان ۱۴۰۵ ۱۲:۰۰ بعد از ظهر')==='2026-10-23T12:00:00+03:30','Persian 12 PM remains noon');
        $status=hamiad_assignment_timing([
            'شروع ارسال'=>$dates['opensText'],
            'موعد تحویل'=>$dates['dueDateText'],
            'وضعیت تحویل'=>'CLOSED',
        ],strtotime('2026-10-26T08:00:00+03:30'));
        verify_hamiad_calendar($status['dueStatus']==='closed','CLOSED is not false expiry despite future deadline');
        verify_hamiad_calendar($status['opensAt']===$open && $status['dueAt']===$due,'separate start and due timestamps retained');
        verify_hamiad_calendar($status['cutoffAt']==='','grading date must not masquerade as cutoff');
    }
    echo "HAMIAD_CALENDAR_ASSIGNMENT_DATES_OK\n";
} finally {
    if (is_dir($root)) {
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $node) { $node->isDir()?@rmdir($node->getPathname()):@unlink($node->getPathname()); }
        @rmdir($root);
    }
}
