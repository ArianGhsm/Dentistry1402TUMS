<?php
declare(strict_types=1);

$tmp=sys_get_temp_dir().'/dent-classops-carry-'.bin2hex(random_bytes(6));
@mkdir($tmp,0700,true);
putenv('DENT_STORAGE_ROOT='.$tmp);
putenv('DENT_CLASSOPS_STAGE2_STATE_PATH='.$tmp.'/domain-state.json');

require_once dirname(__DIR__).'/public_html/api/classops_stage2/state_migrations.php';

try {
    $student='402999999';
    $item1=[
        'id'=>'cop_'.str_repeat('a',24),'revision'=>2,'cohortKey'=>'dentistry-1402','type'=>'task',
        'title'=>'carry fixture','status'=>'scheduled',
    ];
    $item2=$item1;
    $item2['revision']=3;
    $item2['status']='active';

    if (classops_stage2_ensure_task_states($item1,[$student])!==1) throw new RuntimeException('initial state was not created');
    $initial=classops_stage2_get_task_state($item1,$student,false);
    if (!is_array($initial)||($initial['state']??'')!=='pending') throw new RuntimeException('initial state invalid');
    $submitted=classops_stage2_transition_task(
        $item1,$student,(int)$initial['stateRevision'],'submitted','carry-submit-0001','student:'.str_repeat('b',32),'fixture'
    );
    if (($submitted['state']??'')!=='submitted') throw new RuntimeException('submitted transition failed');

    $carried=classops_stage2_carry_task_states($item1,$item2,[$student]);
    if ($carried!==1) throw new RuntimeException('state was not carried');
    $next=classops_stage2_get_task_state($item2,$student,false);
    if (!is_array($next)
        || ($next['state']??'')!=='submitted'
        || (int)($next['itemRevision']??0)!==3
        || (int)($next['stateRevision']??0)!==(int)$submitted['stateRevision']) {
        throw new RuntimeException('carried state lost semantic state/revision');
    }
    print("ClassOps Stage2 task-state revision carry passed\n");
} finally {
    $delete=function(string $path) use (&$delete): void {
        if (is_dir($path)) {
            foreach (scandir($path)?:[] as $name) if ($name!=='.'&&$name!=='..') $delete($path.'/'.$name);
            @rmdir($path);
        } elseif (is_file($path)) @unlink($path);
    };
    $delete($tmp);
}
