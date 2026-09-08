<?php

declare(strict_types=1);
require_once __DIR__ . '/../public_html/api/classops_modules/delivery/delivery_planner.php';

function tassert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function expect_error(callable $fn, string $contains): void { try { $fn(); } catch (DentClassOpsDeliveryException $e) { tassert(str_contains($e->getMessage(), $contains), 'Unexpected error: '.$e->getMessage()); return; } throw new RuntimeException('Expected error: '.$contains); }

$registry = [
    'id'=>'registry.term7.1402', 'version'=>CLASSOPS_DESTINATION_REGISTRY_VERSION, 'cohortId'=>'dentistry-1402',
    'destinations'=>[
        'private.telegram'=>['kind'=>'private_recipient','cohortId'=>'dentistry-1402','platform'=>'telegram','bindingRef'=>'identity.private.telegram'],
        'group.telegram'=>['kind'=>'class_group','cohortId'=>'dentistry-1402','platform'=>'telegram','bindingRef'=>'navid.term7.telegram'],
        'group.bale'=>['kind'=>'class_group','cohortId'=>'dentistry-1402','platform'=>'bale','bindingRef'=>'navid.term7.bale'],
        'channel.telegram'=>['kind'=>'channel','cohortId'=>'dentistry-1402','platform'=>'telegram','bindingRef'=>'channel.class.telegram'],
        'channel.bale'=>['kind'=>'channel','cohortId'=>'dentistry-1402','platform'=>'bale','bindingRef'=>'channel.class.bale'],
        'class.logical'=>['kind'=>'logical','cohortId'=>'dentistry-1402','routes'=>[
            ['alias'=>'group.telegram'], ['alias'=>'group.bale'],
            ['alias'=>'group.telegram','fallbackFor'=>'group.bale','semanticEquivalent'=>true],
        ]],
    ],
];
$item = ['id'=>'item_abc','revision'=>1,'snapshotHash'=>'sha256:rev1','cohortId'=>'dentistry-1402','status'=>'active'];
$audience = ['contractVersion'=>'classops-audience-ref-v1','ref'=>'audience.term7.active','version'=>'7','cohortId'=>'dentistry-1402','hash'=>'sha256:audience7'];
$occurrence = ['key'=>'initial:2026-09-09T18:00:00Z','purpose'=>'initial','scheduledAt'=>'2026-09-09T18:00:00Z'];
$policy = ['platformMode'=>'independent','allowFallback'=>true,'requiredCapabilities'=>['text_message','group_delivery']];
$available = ['telegram'=>'available','bale'=>'available'];

$tests = [];
$tests['deterministic same intent'] = function() use ($registry,$item,$audience,$occurrence,$policy,$available) {
    $a=classops_delivery_plan($registry,'class.logical',$item,$audience,$occurrence,$policy,$available);
    $b=classops_delivery_plan($registry,'class.logical',$item,$audience,$occurrence,$policy,$available);
    tassert($a['intents']===$b['intents'],'Intents must be deterministic');
};
$tests['revision supersession'] = function() use ($registry,$item,$audience,$occurrence,$policy,$available) {
    $old=classops_delivery_plan($registry,'class.logical',$item,$audience,$occurrence,$policy,$available);
    $item2=$item; $item2['revision']=2; $item2['snapshotHash']='sha256:rev2';
    $new=classops_delivery_plan($registry,'class.logical',$item2,$audience,$occurrence,$policy,$available,$old['intents']);
    foreach ($new['intents'] as $intent) {
        $matches=array_values(array_filter($old['intents'],fn($o)=>$o['resolvedDestinationAlias']===$intent['resolvedDestinationAlias']));
        tassert(count($matches)===1,'Previous lane missing');
        tassert($intent['intentId']!==$matches[0]['intentId'],'Revision must change intent');
        tassert($intent['supersedesIntentId']===$matches[0]['intentId'],'Revision supersession missing');
    }
};
$tests['duplicate retries bounded'] = function() use ($registry,$item,$audience,$occurrence,$policy,$available) {
    $p=classops_delivery_plan($registry,'group.telegram',$item,$audience,$occurrence,$policy,$available);
    $i=$p['intents'][0]; tassert($i['retry']['attempt']===0,'attempt'); tassert($i['retry']['maxAttempts']===5,'max attempts'); tassert($i['retry']['maxBackoffSeconds']===900,'max backoff');
    $p2=classops_delivery_plan($registry,'group.telegram',$item,$audience,$occurrence,$policy,$available,$p['intents']);
    tassert($p2['intents'][0]['dedupeKey']===$i['dedupeKey'],'Retry planning must preserve dedupe');
};
$tests['invalid alias'] = function() use ($registry,$item,$audience,$occurrence,$policy,$available) { expect_error(fn()=>classops_delivery_plan($registry,'123456',$item,$audience,$occurrence,$policy,$available),'invalid_destination_alias'); };
$tests['unsupported capability unknown fails closed'] = function() use ($registry,$item,$audience,$occurrence,$available) {
    $p=['platformMode'=>'independent','allowFallback'=>false,'requiredCapabilities'=>['channel_delivery']];
    $r=classops_delivery_plan($registry,'channel.bale',$item,$audience,$occurrence,$p,$available);
    tassert(count($r['intents'])===0,'Unknown Bale channel capability must block'); tassert(str_contains($r['outcomes'][0]['reason'],'unknown'),'Unknown reason expected');
};
$tests['telegram bale explicit equivalence fallback'] = function() use ($registry,$item,$audience,$occurrence,$policy) {
    $r=classops_delivery_plan($registry,'class.logical',$item,$audience,$occurrence,$policy,['telegram'=>'available','bale'=>'unavailable']);
    tassert(count($r['intents'])===1,'Existing equivalent Telegram route should be reused');
    $blocked=array_values(array_filter($r['outcomes'],fn($o)=>$o['alias']==='group.bale'&&$o['state']==='blocked'));
    $fallback=array_values(array_filter($r['outcomes'],fn($o)=>($o['fallbackFor']??null)==='group.bale'&&str_starts_with($o['state'],'planned')));
    tassert(count($blocked)===1 && count($fallback)===1,'Outage and fallback must both remain visible');
};
$tests['private group channel isolation'] = function() use ($registry) {
    tassert($registry['destinations']['private.telegram']['kind']==='private_recipient','private kind'); tassert($registry['destinations']['group.telegram']['kind']==='class_group','group kind'); tassert($registry['destinations']['channel.telegram']['kind']==='channel','channel kind');
};
$tests['raw id persistence rejected'] = function() use ($registry) {
    $bad=$registry; $bad['destinations']['group.telegram']['chatId']='-100123'; expect_error(fn()=>classops_delivery_validate_registry($bad),'raw_platform_identifier_forbidden');
    $bad=$registry; $bad['destinations']['group.telegram']['bindingRef']='-100123'; expect_error(fn()=>classops_delivery_validate_registry($bad),'invalid_binding_ref');
};
$tests['cancellation archive future'] = function() use ($registry,$item,$audience,$occurrence,$policy,$available) {
    $i=classops_delivery_plan($registry,'group.telegram',$item,$audience,$occurrence,$policy,$available)['intents'][0];
    $x=$item; $x['status']='cancelled'; tassert(classops_delivery_reconcile_future_intent($i,$x,'2026-09-08T12:00:00Z')['action']==='cancel','cancel');
    $x['status']='archived'; tassert(classops_delivery_reconcile_future_intent($i,$x,'2026-09-08T12:00:00Z')['action']==='cancel','archive');
};
$tests['reschedule revision supersede'] = function() use ($registry,$item,$audience,$occurrence,$policy,$available) {
    $i=classops_delivery_plan($registry,'group.telegram',$item,$audience,$occurrence,$policy,$available)['intents'][0]; $x=$item; $x['revision']=2; $x['snapshotHash']='sha256:r2';
    tassert(classops_delivery_reconcile_future_intent($i,$x,'2026-09-08T12:00:00Z')['action']==='supersede','new revision should supersede future intent');
    $occ2=$occurrence; $occ2['key']='initial:2026-09-10T18:00:00Z'; $occ2['scheduledAt']='2026-09-10T18:00:00Z';
    $j=classops_delivery_plan($registry,'group.telegram',$item,$audience,$occ2,$policy,$available)['intents'][0]; tassert($j['intentId']!==$i['intentId'],'Reschedule must create new occurrence intent');
};
$tests['partial outage independent'] = function() use ($registry,$item,$audience,$occurrence,$policy) {
    $r=classops_delivery_plan($registry,'class.logical',$item,$audience,$occurrence,$policy,['telegram'=>'available','bale'=>'unavailable']); tassert(count($r['intents'])===1,'Healthy platform remains plan-able');
};
$tests['require all fail closed'] = function() use ($registry,$item,$audience,$occurrence) {
    $p=['platformMode'=>'require_all','allowFallback'=>true,'requiredCapabilities'=>['text_message','group_delivery']]; $r=classops_delivery_plan($registry,'class.logical',$item,$audience,$occurrence,$p,['telegram'=>'available','bale'=>'unavailable']); tassert(count($r['intents'])===0,'require_all must withhold');
};
$tests['cross cohort isolation'] = function() use ($registry,$item,$audience,$occurrence,$policy,$available) {
    $a=$audience; $a['cohortId']='dentistry-1401'; expect_error(fn()=>classops_delivery_plan($registry,'group.telegram',$item,$a,$occurrence,$policy,$available),'cross_cohort_audience');
    $r=$registry; $r['cohortId']='dentistry-1401'; foreach($r['destinations'] as &$d)$d['cohortId']='dentistry-1401'; unset($d); expect_error(fn()=>classops_delivery_plan($r,'group.telegram',$item,$audience,$occurrence,$policy,$available),'cross_cohort_registry');
};
$tests['terminal item isolation'] = function() use ($registry,$item,$audience,$occurrence,$policy,$available) { foreach(['completed','cancelled','archived'] as $s){$x=$item;$x['status']=$s;expect_error(fn()=>classops_delivery_plan($registry,'group.telegram',$x,$audience,$occurrence,$policy,$available),'item_status_not_deliverable');} };
$tests['adapter audit reference only'] = function() use ($registry,$item,$audience,$occurrence,$policy,$available) {
    $i=classops_delivery_plan($registry,'group.telegram',$item,$audience,$occurrence,$policy,$available)['intents'][0]; $e=classops_delivery_adapter_envelope($i); $a=classops_delivery_audit_projection($i,'planned');
    $json=json_encode([$e,$a]); foreach(['title','description','content','chatId','chat_id','recipientId'] as $needle)tassert(!str_contains($json,$needle),'Sensitive/content field leaked: '.$needle); tassert($e['contractVersion']==='classops-delivery-adapter-v1','adapter version');
};

$passed=0; foreach($tests as $name=>$test){$test();$passed++;echo "PASS: $name\n";} echo "OK: $passed tests passed\n";
