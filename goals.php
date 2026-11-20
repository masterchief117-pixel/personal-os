<?php
declare(strict_types=1);
session_start();
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/includes/functions.php';
requireAuthenticatedSession();
$pdo=Database::getInstance()->connection();
$uid=(int)$_SESSION['user_id'];
$goals=getGoals($pdo,$uid);
$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!verifyCsrfToken($_POST)){$error='Invalid or expired form session.';}
    else{
        $title=trim((string)($_POST['title']??''));
        $target=max(0.01,(float)($_POST['target_value']??100));
        $current=max(0,(float)($_POST['current_value']??0));
        $date=trim((string)($_POST['target_date']??''));
        $unit=substr(trim((string)($_POST['unit']??'percent')),0,30);
        if($title===''){$error='Goal title is required.';}
        else{
            $parsed=$date!==''?date_create($date):false;
            $stmt=$pdo->prepare('INSERT INTO goals(user_id,title,description,target_value,current_value,unit,target_date,status) VALUES(:uid,:title,:description,:target,:current,:unit,:target_date,"active")');
            $stmt->execute(['uid'=>$uid,'title'=>$title,'description'=>trim((string)($_POST['description']??'')),'target'=>$target,'current'=>min($current,$target),'unit'=>$unit,'target_date'=>$parsed?$parsed->format('Y-m-d'):null]);
            $message='Goal created.';
            $goals=getGoals($pdo,$uid);
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personal OS Goals</title><link rel="stylesheet" href="/personal-os/public/css/style.css"></head><body><main class="settings-shell"><section class="glass-panel settings-card"><div class="panel-heading"><div><p class="eyebrow">Direction</p><h1>Goals</h1></div><a class="secondary-button settings-back" href="/personal-os/">Dashboard</a></div>
<?php if($message):?><div class="alert success-alert"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="alert"><?=htmlspecialchars($error)?></div><?php endif;?>
<form class="settings-form" method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken())?>"><label>Goal title<input name="title" maxlength="180" required></label><label>Description<textarea name="description" rows="3"></textarea></label><label>Current value<input type="number" name="current_value" min="0" step="0.01" value="0"></label><label>Target value<input type="number" name="target_value" min="0.01" step="0.01" value="100"></label><label>Unit<input name="unit" maxlength="30" value="percent"></label><label>Target date<input type="date" name="target_date"></label><button class="primary-button" type="submit">Create goal</button></form>
<div class="goal-grid"><?php foreach($goals as $goal):$progress=$goal['target_value']>0?min(100,round(((float)$goal['current_value']/(float)$goal['target_value'])*100)):0;?><article class="goal-card" id="goal-<?= (int)$goal['id'] ?>"><div class="goal-card-heading"><div><strong><?=htmlspecialchars($goal['title'])?></strong><small><?=htmlspecialchars($goal['status'])?><?php if($goal['target_date']):?> · <?=htmlspecialchars(date('M j, Y',strtotime($goal['target_date'])))?><?php endif;?></small></div><strong><?= (int)$progress?>%</strong></div><p><?=htmlspecialchars($goal['description'])?></p><div class="bar-track"><i style="width:<?= (int)$progress?>%"></i></div><small><?=htmlspecialchars((string)$goal['current_value'])?> / <?=htmlspecialchars((string)$goal['target_value'])?> <?=htmlspecialchars($goal['unit'])?></small></article><?php endforeach;?></div></section></main></body></html>
