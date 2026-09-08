<?php
declare(strict_types=1);

$tmp=sys_get_temp_dir().'/dent-classops-carry-'.bin2hex(random_bytes(6));
@mkdir($tmp,0700,true);
putenv('DENT_STORAGE_ROOT='.$tmp);
putenv('DENT_CLASSOPS_STAGE2_STATE_PATH='.$tmp.'/domain-state.json');

require_once dirname(__DIR__).'/public_html/api/classops_stage2/state_migrations.php';

try {
    $student='402999999';

    // Task/requirement state must survive a benign item revision for an eligible
    // student without changing the state-machine revision/history semantics.
    $item1=[
        'id'=>'cop_'.str_repeat('a',24),'revision'=>2,'cohortKey'=>'dentistry-1402','type'=>'task',
        'title'=>'carry fixture','status'=>'scheduled',
    ];
    $item2=$item1;
    $item2['revision']=3;
    $item2['status']='active';

    if (classops_stage2_ensure_task_states($item1,[$student])!==1) throw new RuntimeException('initial task state was not created');
    $initial=classops_stage2_get_task_state($item1,$student,false);
    if (!is_array($initial)||($initial['state']??'')!=='pending') throw new RuntimeException('initial task state invalid');
    $submitted=classops_stage2_transition_task(
        $item1,$student,(int)$initial['stateRevision'],'submitted','carry-submit-0001','student:'.str_repeat('b',32),'fixture'
    );
    if (($submitted['state']??'')!=='submitted') throw new RuntimeException('submitted transition failed');

    $carried=classops_stage2_carry_task_states($item1,$item2,[$student]);
    if ($carried!==1) throw new RuntimeException('task state was not carried');
    $next=classops_stage2_get_task_state($item2,$student,false);
    if (!is_array($next)
        || ($next['state']??'')!=='submitted'
        || (int)($next['itemRevision']??0)!==3
        || (int)($next['stateRevision']??0)!==(int)$submitted['stateRevision']
        || ($next['history']??null)!==($submitted['history']??null)) {
        throw new RuntimeException('carried task state lost semantic state/revision/history');
    }

    // Service/Saba state is local-only. Carrying it to a newer revision must
    // preserve completion while explicitly remaining not externally verified.
    $service1=[
        'id'=>'cop_'.str_repeat('c',24),'revision'=>4,'cohortKey'=>'dentistry-1402','type'=>'service_reminder',
        'title'=>'Saba carry fixture','status'=>'scheduled',
    ];
    $service2=$service1;
    $service2['revision']=5;
    $service2['status']='active';
    if (classops_stage2_ensure_service_states($service1,[$student])!==1) throw new RuntimeException('initial service state was not created');
    $serviceState=classops_stage2_get_service_state($service1,$student);
    if (!is_array($serviceState)||($serviceState['state']??'')!=='pending') throw new RuntimeException('initial service state invalid');
    $completed=classops_stage2_transition_service_state(
        $service1,$student,(int)$serviceState['stateRevision'],'completed','carry-service-complete-0001'
    );
    if (($completed['state']??'')!=='completed'||($completed['externallyVerified']??true)!==false) {
        throw new RuntimeException('local service completion semantics invalid');
    }
    if (classops_stage2_carry_service_states($service1,$service2,[$student])!==1) {
        throw new RuntimeException('service state was not carried');
    }
    $serviceNext=classops_stage2_get_service_state($service2,$student);
    if (!is_array($serviceNext)
        || ($serviceNext['state']??'')!=='completed'
        || (int)($serviceNext['itemRevision']??0)!==5
        || (int)($serviceNext['stateRevision']??0)!==(int)$completed['stateRevision']
        || ($serviceNext['externallyVerified']??true)!==false) {
        throw new RuntimeException('carried service state lost local-only semantics');
    }

    print("ClassOps Stage2 task/service revision carry passed\n");
} finally {
    $delete=function(string $path) use (&$delete): void {
        if (is_dir($path)) {
            foreach (scandir($path)?:[] as $name) if ($name!=='.'&&$name!=='..') $delete($path.'/'.$name);
            @rmdir($path);
        } elseif (is_file($path)) @unlink($path);
    };
    $delete($tmp);
}
