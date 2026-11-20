<?php
declare(strict_types=1);
$checks=[];
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/mailer.php';
$pdo=Database::getInstance()->connection();
function checkReg(string $name,callable $callback){global $checks;try{$callback();$checks[]=['name'=>$name,'passed'=>true,'detail'=>''];}catch(Throwable $e){$checks[]=['name'=>$name,'passed'=>false,'detail'=>$e->getMessage()];}}
checkReg('Database connection',function()use($pdo){if((int)$pdo->query('SELECT 1')->fetchColumn()!==1)throw new RuntimeException('Database unavailable');});
checkReg('Invitation email requires explicit SMTP settings',function(){
    $keys=['MAIL_HOST','MAIL_PORT','MAIL_USERNAME','MAIL_PASSWORD','MAIL_ENCRYPTION','MAIL_FROM_ADDRESS'];
    $previous=[];
    foreach($keys as $key){$previous[$key]=getenv($key);putenv($key);}
    try{
        if(sendInvitationEmail('test@example.invalid','https://127.0.0.1/personal-os/accept_invitation.php?token=test','member')!=='not_configured'){
            throw new RuntimeException('Email must remain disabled until SMTP is configured');
        }
    }finally{
        foreach($previous as $key=>$value){
            if($value===false){putenv($key);}else{putenv($key.'='.$value);}
        }
    }
});
checkReg('Goals table',function()use($pdo){$pdo->query('SELECT COUNT(*) FROM goals');});
checkReg('Journal table',function()use($pdo){$pdo->query('SELECT COUNT(*) FROM journal_entries');});
checkReg('Focus table',function()use($pdo){$pdo->query('SELECT COUNT(*) FROM focus_sessions');});
checkReg('Backup schedule table',function()use($pdo){$pdo->query('SELECT COUNT(*) FROM backup_schedules');});
checkReg('Goal CRUD and ownership',function()use($pdo){
    $insert=$pdo->prepare('INSERT INTO goals(user_id,title,target_value,current_value,unit,status) VALUES(1,:title,10,2,"tasks","active")');
    $insert->execute(['title'=>'Regression Goal']);
    $id=(int)$pdo->lastInsertId();
    $update=$pdo->prepare('UPDATE goals SET current_value=5 WHERE id=:id AND user_id=1');
    $update->execute(['id'=>$id]);
    $delete=$pdo->prepare('DELETE FROM goals WHERE id=:id AND user_id=1');
    $delete->execute(['id'=>$id]);
});
checkReg('Journal create update and delete',function()use($pdo){
    $insert=$pdo->prepare('INSERT INTO journal_entries(user_id,entry_date,mood,energy,gratitude) VALUES(1,"2099-01-01","good",4,"Regression")');
    $insert->execute();
    $update=$pdo->prepare('UPDATE journal_entries SET mood="great" WHERE user_id=1 AND entry_date="2099-01-01"');
    $update->execute();
    $delete=$pdo->prepare('DELETE FROM journal_entries WHERE user_id=1 AND entry_date="2099-01-01"');
    $delete->execute();
});
checkReg('Focus create completion and cancellation',function()use($pdo){
    $insert=$pdo->prepare('INSERT INTO focus_sessions(user_id,duration_minutes,started_at,status) VALUES(1,25,NOW(),"running")');
    $insert->execute();$id=(int)$pdo->lastInsertId();
    $update=$pdo->prepare('UPDATE focus_sessions SET status="completed",completed_at=NOW() WHERE id=:id AND user_id=1');
    $update->execute(['id'=>$id]);
    $delete=$pdo->prepare('DELETE FROM focus_sessions WHERE id=:id AND user_id=1');
    $delete->execute(['id'=>$id]);
});
checkReg('Automation rule ownership and toggle',function()use($pdo){
    $insert=$pdo->prepare('INSERT INTO automation_rules(user_id,name,trigger_type,trigger_value,action_type,action_value,is_enabled) VALUES(1,"Regression automation","task_completed","","create_task","Regression follow-up",TRUE)');
    $insert->execute();
    $id=(int)$pdo->lastInsertId();
    $toggle=$pdo->prepare('UPDATE automation_rules SET is_enabled=NOT is_enabled WHERE id=:id AND user_id=1');
    $toggle->execute(['id'=>$id]);
    $delete=$pdo->prepare('DELETE FROM automation_rules WHERE id=:id AND user_id=1');
    $delete->execute(['id'=>$id]);
});
checkReg('Event automation executes once and is auditable',function()use($pdo){
    require_once __DIR__.'/../includes/functions.php';
    $insert=$pdo->prepare('INSERT INTO automation_rules(user_id,name,trigger_type,trigger_value,action_type,action_value,is_enabled) VALUES(1,"Regression event automation","task_completed","","create_task","Regression follow-up",TRUE)');
    $insert->execute();
    $ruleId=(int)$pdo->lastInsertId();
    $before=(int)$pdo->query('SELECT COUNT(*) FROM tasks WHERE user_id=1 AND title="Regression follow-up"')->fetchColumn();
    $processed=executeAutomationRules($pdo,1,'task_completed',['category'=>'Personal','priority'=>'medium','title'=>'Regression source']);
    $after=(int)$pdo->query('SELECT COUNT(*) FROM tasks WHERE user_id=1 AND title="Regression follow-up"')->fetchColumn();
    if($processed!==1||$after!==$before+1)throw new RuntimeException('Automation execution count mismatch');
    $deleteTasks=$pdo->prepare('DELETE FROM tasks WHERE user_id=1 AND title="Regression follow-up"');
    $deleteTasks->execute();
    $deleteRule=$pdo->prepare('DELETE FROM automation_rules WHERE id=:id AND user_id=1');
    $deleteRule->execute(['id'=>$ruleId]);
});
checkReg('Backup schedule create update and delete',function()use($pdo){
    $pdo->beginTransaction();
    try{
        $insert=$pdo->prepare('INSERT INTO backup_schedules(user_id,frequency,is_enabled,next_run_at) VALUES(1,"daily",TRUE,DATE_ADD(NOW(),INTERVAL 1 DAY)) ON DUPLICATE KEY UPDATE frequency="daily",is_enabled=TRUE,next_run_at=DATE_ADD(NOW(),INTERVAL 1 DAY)');
        $insert->execute();
        $update=$pdo->prepare('UPDATE backup_schedules SET frequency="weekly",is_enabled=FALSE WHERE user_id=1');
        $update->execute();
        $schedule=$pdo->query('SELECT frequency,is_enabled FROM backup_schedules WHERE user_id=1')->fetch();
        if(!$schedule||$schedule['frequency']!=='weekly'||(int)$schedule['is_enabled']!==0)throw new RuntimeException('Backup schedule update mismatch');
        $pdo->rollBack();
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $exception;
    }
});
checkReg('Reminder create delivery ownership',function()use($pdo){
    $pdo->beginTransaction();
    try{
        $insert=$pdo->prepare('INSERT INTO reminders(user_id,task_id,title,remind_at,is_sent) VALUES(1,NULL,"Regression reminder",DATE_ADD(NOW(),INTERVAL 1 HOUR),FALSE)');
        $insert->execute();
        $id=(int)$pdo->lastInsertId();
        $update=$pdo->prepare('UPDATE reminders SET is_sent=TRUE WHERE id=:id AND user_id=1');
        $update->execute(['id'=>$id]);
        $sent=(int)$pdo->query('SELECT is_sent FROM reminders WHERE id='.$id)->fetchColumn();
        if($sent!==1)throw new RuntimeException('Reminder delivery state mismatch');
        $pdo->rollBack();
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $exception;
    }
});
$failed=0;$lines=["Personal OS Regression Report","Generated: ".date(DATE_ATOM),""];
foreach($checks as $check){
    $status=$check['passed']?'PASS':'FAIL';
    $detail=$check['detail']!==''?' - '.$check['detail']:'';
    $lines[]=$status.' '.$check['name'].$detail;
    if(!$check['passed'])$failed++;
}
$lines[]='';$lines[]='SUMMARY '.(count($checks)-$failed).'/'.count($checks).' passed';
$report=implode(PHP_EOL,$lines).PHP_EOL;echo $report;
@mkdir('C:\\xampp\\htdocs\\personal-os\\reports',0700,true);file_put_contents('C:\\xampp\\htdocs\\personal-os\\reports\\regression-report.txt',$report,LOCK_EX);
exit($failed===0?0:1);
