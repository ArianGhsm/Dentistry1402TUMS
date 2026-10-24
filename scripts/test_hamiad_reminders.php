<?php
declare(strict_types=1);
require_once __DIR__ . '/../public_html/api/hamiad_reminders.php';

function testAssert(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
}
testAssert(hamiad_remaining_seconds('۴۸ روز ۷ ساعت باقیمانده') === 48*86400+7*3600, 'persian countdown');
testAssert(hamiad_remaining_seconds('17 روز 7 ساعت باقیمانده') === 17*86400+7*3600, 'latin countdown');
testAssert(hamiad_remaining_seconds('۲ روز گذشته') === -2*86400, 'expired countdown');
testAssert(hamiad_remaining_seconds('تاریخ مشخص نیست') === null, 'unknown countdown');

$now = strtotime('2026-10-24T08:00:00+03:30');
$task = static fn(int $id,int $left,string $status='upcoming'): array => [
    'id'=>$id,'courseId'=>7772,'title'=>'تکلیف نمونه','courseTitle'=>'تشخیصی ۳',
    'dueStatus'=>$status,'dueAt'=>date(DATE_ATOM,$now+$left),
    'dueText'=>'مهلت ثبت شده','submissionStatus'=>'هیچ ارسالی انجام نشده است',
];
$baseline=['connected'=>true,'assignments'=>[$task(10,48*86400),$task(11,17*86400)]];
$empty=['initialized'=>false,'seen'=>[],'sent'=>[]];
$plan=hamiad_reminder_events($baseline,$empty,$now);
testAssert(count($plan['events'])===0,'initial baseline does not spam old assignments');
testAssert(count($plan['seen'])===2,'both assignments retained');

$prior=['initialized'=>true,'seen'=>['7772:10'=>true,'7772:11'=>true],'sent'=>[]];
$new=hamiad_reminder_events(['assignments'=>[$task(10,48*86400),$task(12,9*86400)]],$prior,$now);
testAssert(count($new['events'])===1 && $new['events'][0]['kind']==='created','new assignment announcement');
foreach ([8=>0,7=>7,2=>2,1=>1] as $days=>$threshold) {
    $planned=hamiad_reminder_events(['assignments'=>[$task(10,$days*86400)]],$prior,$now);
    testAssert(count($planned['events'])===($threshold===0?0:1),'threshold '.$days);
    if ($threshold!==0)testAssert($planned['events'][0]['days']===$threshold,'window '.$days);
}
$expired=hamiad_reminder_events(['assignments'=>[$task(10,-1,'overdue')]],$prior,$now);
$submitted=hamiad_reminder_events(['assignments'=>[$task(10,3600,'submitted')]],$prior,$now);
testAssert(count($expired['events'])===0 && count($submitted['events'])===0,'no reminders after deadline or submission');
$first=hamiad_reminder_events(['assignments'=>[$task(10,3600)]],$prior,$now);
$key=$first['events'][0]['key'];
$prior['sent'][$key]='2026-10-24';
$again=hamiad_reminder_events(['assignments'=>[$task(10,3600)]],$prior,$now);
testAssert(count($again['events'])===0,'same reminder is deduplicated');
$meta = notifications_clean_meta(['courseId'=>7772,'assignmentId'=>719491,'dueAt'=>date(DATE_ATOM,$now+86400),'dueText'=>'یک روز مانده','reminderDays'=>1]);
testAssert(($meta['courseId'] ?? 0)===7772 && ($meta['assignmentId'] ?? 0)===719491, 'canonical activity ids preserved');
testAssert(($meta['reminderDays'] ?? 0)===1 && !empty($meta['dueAt']), 'deadline metadata preserved');
$firstRunNear = hamiad_reminder_events(['assignments'=>[$task(77,3600)]], $empty, $now);
testAssert(count($firstRunNear['events'])===0, 'first run never spams existing due soon');
$apiSource = file_get_contents(__DIR__ . '/../public_html/api/notifications_api.php');
testAssert(str_contains($apiSource, "'hamiadAssignmentAlerts' => \$_POST['hamiadAssignmentAlerts'] ?? null"), 'Hamiad preference must reach notification API save handler');
echo "HAMIAD_REMINDER_CONTRACT_OK\n";
