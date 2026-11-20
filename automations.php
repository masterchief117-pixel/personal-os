<?php
declare(strict_types=1);
session_start();
if(!isset($_SESSION['authenticated'])||$_SESSION['authenticated']!==true){header('Location: /personal-os/login.php');exit;}
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/includes/functions.php';
requireAuthenticatedSession();
$pdo=Database::getInstance()->connection();$uid=(int)$_SESSION['user_id'];$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!verifyCsrfToken($_POST)){$error='Invalid or expired form session.';}
 else{
  $formAction=trim((string)($_POST['action']??'create'));
  $ruleId=(int)($_POST['rule_id']??0);
  if(in_array($formAction,['toggle','delete'],true)&&$ruleId>0){
   if($formAction==='delete'){
    $stmt=$pdo->prepare('DELETE FROM automation_rules WHERE id=:id AND user_id=:uid');
    $stmt->execute(['id'=>$ruleId,'uid'=>$uid]);
    auditEvent($pdo,$uid,'automation.deleted','automation',$ruleId);
    $message='Automation rule deleted.';
   }else{
    $stmt=$pdo->prepare('UPDATE automation_rules SET is_enabled=NOT is_enabled WHERE id=:id AND user_id=:uid');
    $stmt->execute(['id'=>$ruleId,'uid'=>$uid]);
    auditEvent($pdo,$uid,'automation.toggled','automation',$ruleId);
    $message='Automation rule updated.';
   }
  }else{
   $name=trim((string)($_POST['name']??''));$triggerType=trim((string)($_POST['trigger_type']??''));$triggerValue=trim((string)($_POST['trigger_value']??''));$actionType=trim((string)($_POST['action_type']??''));$actionValue=trim((string)($_POST['action_value']??''));
   $validTriggers=in_array($triggerType,['task_completed','priority_changed','task_created'],true);
   $validActions=in_array($actionType,['create_task','send_reminder','mark_review'],true);
   if($name===''||!$validTriggers||!$validActions){$error='Rule name, trigger, and action are required.';}
   elseif(mb_strlen($name)>100||mb_strlen($triggerValue)>100||mb_strlen($actionValue)>100){$error='Automation values are too long.';}
   else{$stmt=$pdo->prepare('INSERT INTO automation_rules(user_id,name,trigger_type,trigger_value,action_type,action_value) VALUES(:uid,:name,:tt,:tv,:at,:av)');$stmt->execute(['uid'=>$uid,'name'=>substr($name,0,100),'tt'=>$triggerType,'tv'=>substr($triggerValue,0,100),'at'=>$actionType,'av'=>substr($actionValue,0,100)]);auditEvent($pdo,$uid,'automation.created','automation',(int)$pdo->lastInsertId());$message='Automation rule created.';}
  }
 }
}
$stmt=$pdo->prepare('SELECT * FROM automation_rules WHERE user_id=:uid ORDER BY created_at DESC');$stmt->execute(['uid'=>$uid]);$rules=$stmt->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personal OS Automations</title><link rel="stylesheet" href="/personal-os/public/css/style.css"></head><body><main class="settings-shell"><section class="glass-panel settings-card"><div class="panel-heading"><div><p class="eyebrow">Workflow rules</p><h1>Automations</h1></div><a class="secondary-button settings-back" href="/personal-os/">Dashboard</a></div><?php if($message):?><div class="alert success-alert"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="alert"><?=htmlspecialchars($error)?></div><?php endif;?>
<form class="settings-form" method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken())?>"><label>Rule name<input name="name" maxlength="100" required></label><label>When<select name="trigger_type"><option value="task_completed">A task is completed</option><option value="priority_changed">Priority becomes high or urgent</option><option value="task_created">A task is created</option></select></label><label>Trigger value<input name="trigger_value" maxlength="100" placeholder="Optional value"></label><label>Then<select name="action_type"><option value="create_task">Create task</option><option value="send_reminder">Create reminder</option><option value="mark_review">Mark for weekly review</option></select></label><label>Action value<input name="action_value" maxlength="100" placeholder="Task title, reminder, or category"></label><button class="primary-button" type="submit">Create automation</button></form>
<div class="team-list"><?php foreach($rules as $rule):?><article class="team-row"><div><strong><?=htmlspecialchars($rule['name'])?></strong><small><?=htmlspecialchars($rule['trigger_type'])?> → <?=htmlspecialchars($rule['action_type'])?></small></div><div class="modal-actions"><form method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="rule_id" value="<?=$rule['id']?>"><button class="secondary-button" type="submit"><?=$rule['is_enabled']?'Disable':'Enable'?></button></form><form method="post" data-confirm="Delete this automation?"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="rule_id" value="<?=$rule['id']?>"><button class="secondary-button" type="submit">Delete</button></form></div></article><?php endforeach;?></div></section></main></body></html>
