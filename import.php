<?php
declare(strict_types=1);
session_start();
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/includes/functions.php';
requireAuthenticatedSession();
$pdo=Database::getInstance()->connection();$uid=(int)$_SESSION['user_id'];$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!verifyCsrfToken($_POST)){$error='Invalid or expired form session.';}
 elseif(!isset($_FILES['import_file'])||$_FILES['import_file']['error']!==UPLOAD_ERR_OK||!is_uploaded_file($_FILES['import_file']['tmp_name'])){$error='Choose a valid import JSON file.';}
 elseif(($_FILES['import_file']['size']??0)>5*1024*1024){$error='Import files must be smaller than 5 MB.';}
 else{
  $payload=json_decode((string)file_get_contents($_FILES['import_file']['tmp_name']),true);
  if(json_last_error()!==JSON_ERROR_NONE){$error='The selected file contains invalid JSON.';}
  elseif(!is_array($payload)||(!isset($payload['tasks'])&&!isset($payload['notes']))){$error='This is not a supported Personal OS import.';}
  elseif(isset($payload['tasks'])&&!is_array($payload['tasks'])){$error='The imported tasks section is invalid.';}
  elseif(isset($payload['notes'])&&!is_array($payload['notes'])){$error='The imported notes section is invalid.';}
  elseif(count($payload['tasks']??[])>10000||count($payload['notes']??[])>10000){$error='Import contains too many records.';}
  else{
   $tasksImported=0;$notesImported=0;
   try{
    $pdo->beginTransaction();
    $taskInsert=$pdo->prepare('INSERT INTO tasks(user_id,title,description,status,priority,category,due_at) VALUES(:uid,:title,:description,:status,:priority,:category,:due_at)');
    foreach($payload['tasks']??[] as $task){
    if(!is_array($task)||trim((string)($task['title']??''))==='')throw new InvalidArgumentException('Invalid task record');
    if(isset($task['due_at'])&&$task['due_at']!==null&&$task['due_at']!==''){
     $due=$task['due_at'];if(!is_string($due)||!preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/',$due))throw new InvalidArgumentException('Invalid task due date');
    }
     $status=in_array($task['status']??'',['todo','in_progress','completed','archived'],true)?$task['status']:'todo';
     $priority=in_array($task['priority']??'',['low','medium','high','urgent'],true)?$task['priority']:'medium';
     $taskInsert->execute(['uid'=>$uid,'title'=>substr((string)$task['title'],0,180),'description'=>substr((string)($task['description']??''),0,65535),'status'=>$status,'priority'=>$priority,'category'=>substr((string)($task['category']??'Personal'),0,60),'due_at'=>$task['due_at']??null]);
     $tasksImported++;
    }
    $noteInsert=$pdo->prepare('INSERT INTO notes(user_id,title,content,color,is_pinned) VALUES(:uid,:title,:content,:color,:pinned)');
    foreach($payload['notes']??[] as $note){
     if(!is_array($note)||trim((string)($note['title']??''))==='')continue;
     $color=in_array($note['color']??'',['violet','amber','cyan','rose'],true)?$note['color']:'violet';
     $noteInsert->execute(['uid'=>$uid,'title'=>substr((string)$note['title'],0,180),'content'=>substr((string)($note['content']??''),0,65535),'color'=>$color,'pinned'=>!empty($note['is_pinned'])?1:0]);
     $notesImported++;
    }
    $pdo->commit();$message="Imported {$tasksImported} tasks and {$notesImported} notes.";
   }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();error_log($exception->getMessage());$error='The import could not be completed.';}
  }
 }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personal OS Import</title><link rel="stylesheet" href="/personal-os/public/css/style.css"></head><body><main class="settings-shell"><section class="glass-panel settings-card"><div class="panel-heading"><div><p class="eyebrow">Data transfer</p><h1>Import data</h1></div><a class="secondary-button settings-back" href="/personal-os/settings.php">Settings</a></div><?php if($message):?><div class="alert success-alert"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="alert"><?=htmlspecialchars($error)?></div><?php endif;?><form class="settings-form" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken())?>"><label>Personal OS JSON file<input type="file" name="import_file" accept="application/json,.json" required></label><button class="primary-button" type="submit">Import data</button></form></section></main></body></html>
