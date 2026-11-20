<?php
declare(strict_types=1);
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) { header('Location: /personal-os/login.php'); exit; }
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/includes/functions.php';
requireAuthenticatedSession();
$pdo=Database::getInstance()->connection(); $userId=(int)$_SESSION['user_id']; $message=''; $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!verifyCsrfToken($_POST)){$error='Invalid or expired form session.';}
 else{
  $action=$_POST['action']??'';
  if($action==='create'){
   $name=trim((string)($_POST['name']??'')); $title=trim((string)($_POST['title']??''));
   if($name===''||$title===''){$error='Template name and task title are required.';}
   else{
    $stmt=$pdo->prepare('INSERT INTO task_templates(user_id,name,title,description,priority,category,recurrence) VALUES(:uid,:name,:title,:description,:priority,:category,:recurrence)');
    $stmt->execute(['uid'=>$userId,'name'=>$name,'title'=>$title,'description'=>trim((string)($_POST['description']??'')),'priority'=>in_array($_POST['priority']??'',['low','medium','high','urgent'],true)?$_POST['priority']:'medium','category'=>substr(trim((string)($_POST['category']??'Personal')),0,60),'recurrence'=>in_array($_POST['recurrence']??'',['none','daily','weekly','monthly'],true)?$_POST['recurrence']:'none']);
    $message='Template created.';
   }
  }elseif($action==='use'){
   $id=(int)($_POST['template_id']??0);
   $stmt=$pdo->prepare('SELECT name,title,description,priority,category,recurrence FROM task_templates WHERE id=:id AND user_id=:uid');
   $stmt->execute(['id'=>$id,'uid'=>$userId]); $template=$stmt->fetch();
   if($template){
    $stmt=$pdo->prepare('INSERT INTO tasks(user_id,title,description,status,priority,category,recurrence,next_recurrence_at) VALUES(:uid,:title,:description,"todo",:priority,:category,:recurrence,NULL)');
    $stmt->execute(['uid'=>$userId,'title'=>$template['title'],'description'=>$template['description'],'priority'=>$template['priority'],'category'=>$template['category'],'recurrence'=>$template['recurrence']]);
    $message='Template task created.';
   }
  }
 }
}
$stmt=$pdo->prepare('SELECT * FROM task_templates WHERE user_id=:uid ORDER BY created_at DESC'); $stmt->execute(['uid'=>$userId]); $templates=$stmt->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personal OS Templates</title><link rel="stylesheet" href="/personal-os/public/css/style.css"></head><body><main class="settings-shell"><section class="glass-panel settings-card"><div class="panel-heading"><div><p class="eyebrow">Reuse</p><h1>Task templates</h1></div><a class="secondary-button settings-back" href="/personal-os/">Dashboard</a></div><?php if($message):?><div class="alert success-alert"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="alert"><?=htmlspecialchars($error)?></div><?php endif;?>
<form class="settings-form" method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken())?>"><input type="hidden" name="action" value="create"><label>Name<input name="name" maxlength="100" required></label><label>Task title<input name="title" maxlength="180" required></label><label>Description<textarea name="description"></textarea></label><label>Priority<select name="priority"><option>medium</option><option>low</option><option>high</option><option>urgent</option></select></label><label>Category<input name="category" value="Personal" maxlength="60"></label><label>Repeat<select name="recurrence"><option>none</option><option>daily</option><option>weekly</option><option>monthly</option></select></label><button class="primary-button" type="submit">Create template</button></form>
<div class="team-list"><?php foreach($templates as $template):?><article class="team-row"><div><strong><?=htmlspecialchars($template['name'])?></strong><small><?=htmlspecialchars($template['title'])?></small></div><form method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken())?>"><input type="hidden" name="action" value="use"><input type="hidden" name="template_id" value="<?=$template['id']?>"><button class="secondary-button" type="submit">Use</button></form></article><?php endforeach;?></div></section></main></body></html>
