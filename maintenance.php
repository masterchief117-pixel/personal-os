<?php
declare(strict_types=1);
session_start();
if(!isset($_SESSION['authenticated'])||$_SESSION['authenticated']!==true){header('Location: /personal-os/login.php');exit;}
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/includes/functions.php';
requireAuthenticatedSession();
$pdo=Database::getInstance()->connection();$uid=(int)$_SESSION['user_id'];$message='';
$role=$pdo->prepare('SELECT role FROM users WHERE id=:uid');$role->execute(['uid'=>$uid]);if($role->fetchColumn()!=='admin'){http_response_code(403);echo 'Administrator access required.';exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!verifyCsrfToken($_POST)){http_response_code(403);exit;}
 $action=$_POST['action']??'';
 if($action==='optimize'){
  $pdo->exec('OPTIMIZE TABLE tasks,notes,habits,reminders,automation_rules,workspace_events,audit_events');
  auditEvent($pdo,$uid,'maintenance.optimize','database',null);
  $message='Database maintenance completed.';
 }elseif($action==='audit'){
  auditEvent($pdo,$uid,'maintenance.audit','database',null);
  $message='Audit event recorded.';
 }
}
$audit=$pdo->prepare('SELECT action,entity_type,entity_id,ip_address,created_at FROM audit_events WHERE user_id=:uid ORDER BY created_at DESC LIMIT 20');$audit->execute(['uid'=>$uid]);$events=$audit->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personal OS Maintenance</title><link rel="stylesheet" href="/personal-os/public/css/style.css"></head><body><main class="settings-shell"><section class="glass-panel settings-card"><div class="panel-heading"><div><p class="eyebrow">Operations</p><h1>Maintenance and audit</h1></div><a class="secondary-button settings-back" href="/personal-os/admin/backup.php">Backup</a></div><?php if($message):?><div class="alert success-alert"><?=htmlspecialchars($message)?></div><?php endif;?>
<form class="settings-form" method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken())?>"><input type="hidden" name="action" value="optimize"><button class="primary-button" type="submit">Optimize database tables</button></form><form class="settings-form" method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken())?>"><input type="hidden" name="action" value="audit"><button class="secondary-button" type="submit">Record audit check</button></form>
<div class="team-list"><?php foreach($events as $event):?><article class="team-row"><div><strong><?=htmlspecialchars($event['action'])?></strong><small><?=htmlspecialchars($event['entity_type'])?> · <?=htmlspecialchars((string)$event['ip_address'])?></small></div><time><?=htmlspecialchars($event['created_at'])?></time></article><?php endforeach;?></div></section></main></body></html>
