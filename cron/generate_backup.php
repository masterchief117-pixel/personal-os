<?php
declare(strict_types=1);
require_once __DIR__.'/../config/db.php';
$pdo=Database::getInstance()->connection();
$statement=$pdo->prepare('SELECT id,user_id,frequency,next_run_at FROM backup_schedules WHERE is_enabled=TRUE AND next_run_at<=NOW()');
$statement->execute();
$schedules=$statement->fetchAll();
$processed=0;
foreach($schedules as $schedule){
 $pdo->beginTransaction();
 $lock=$pdo->prepare('SELECT id FROM backup_schedules WHERE id=:id AND is_enabled=TRUE AND next_run_at<=NOW() FOR UPDATE');
 $lock->execute(['id'=>$schedule['id']]);
 if(!$lock->fetch()){
  $pdo->rollBack();
  continue;
 }
 $tasks=$pdo->prepare('SELECT id,title,description,status,priority,category,due_at,completed_at,created_at FROM tasks WHERE user_id=:uid');
 $tasks->execute(['uid'=>$schedule['user_id']]);$taskRows=$tasks->fetchAll();
 $notes=$pdo->prepare('SELECT id,title,content,color,is_pinned,created_at,updated_at FROM notes WHERE user_id=:uid');
 $notes->execute(['uid'=>$schedule['user_id']]);$noteRows=$notes->fetchAll();
 $directory='C:\\xampp\\personal-os-backups';
 if(!is_dir($directory))mkdir($directory,0700,true);
 $filename=$directory.DIRECTORY_SEPARATOR.'personal-os-'.$schedule['user_id'].'-'.date('Y-m-d-His').'.json';
 $json=json_encode(['exported_at'=>date(DATE_ATOM),'tasks'=>$taskRows,'notes'=>$noteRows],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
 if(!is_string($json)||file_put_contents($filename,$json,LOCK_EX)===false){
  $pdo->rollBack();
  continue;
 }
 $interval=$schedule['frequency']==='weekly'?'+1 week':($schedule['frequency']==='monthly'?'+1 month':'+1 day');
 $next=date('Y-m-d H:i:s',strtotime($interval));
 $update=$pdo->prepare('UPDATE backup_schedules SET last_run_at=NOW(),next_run_at=:next WHERE id=:id');
 $update->execute(['next'=>$next,'id'=>$schedule['id']]);
 $pdo->commit();
 $processed++;
}
echo 'CREATED '.$processed.' scheduled backup(s)'.PHP_EOL;
