<?php
declare(strict_types=1);
session_start();
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/includes/functions.php';
requireAuthenticatedSession();
$pdo=Database::getInstance()->connection();$uid=(int)$_SESSION['user_id'];
$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!verifyCsrfToken($_POST)){$error='Invalid or expired form session.';}
    else{
        $date=trim((string)($_POST['entry_date']??date('Y-m-d')));
        $parsed=preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
        $errors=$parsed?DateTimeImmutable::getLastErrors():false;
        if($errors&&($errors['warning_count']>0||$errors['error_count']>0))$parsed=false;
        $mood=(string)($_POST['mood']??'okay');if(!in_array($mood,['great','good','okay','low','rough'],true))$mood='okay';
        $energy=max(1,min(5,(int)($_POST['energy']??3)));
        if(!$parsed){$error='Choose a valid journal date.';}
        else{$stmt=$pdo->prepare('INSERT INTO journal_entries(user_id,entry_date,mood,energy,gratitude,reflection,wins) VALUES(:uid,:date,:mood,:energy,:gratitude,:reflection,:wins) ON DUPLICATE KEY UPDATE mood=:mood,energy=:energy,gratitude=:gratitude,reflection=:reflection,wins=:wins');$stmt->execute(['uid'=>$uid,'date'=>$parsed->format('Y-m-d'),'mood'=>$mood,'energy'=>$energy,'gratitude'=>trim((string)($_POST['gratitude']??'')),'reflection'=>trim((string)($_POST['reflection']??'')),'wins'=>trim((string)($_POST['wins']??''))]);$message='Journal entry saved.';}
    }
}
$summary=getJournalSummary($pdo,$uid);
$todayEntry=$summary['today'];
$requestedDate=$_GET['date']??'';
if(is_string($requestedDate)&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$requestedDate)){
    $selectedDate=DateTimeImmutable::createFromFormat('!Y-m-d',$requestedDate);
    $dateErrors=DateTimeImmutable::getLastErrors();
    if($selectedDate&&(!$dateErrors||($dateErrors['warning_count']===0&&$dateErrors['error_count']===0))){
        $entryLookup=$pdo->prepare('SELECT entry_date,mood,energy,gratitude,reflection,wins FROM journal_entries WHERE user_id=:uid AND entry_date=:date');
        $entryLookup->execute(['uid'=>$uid,'date'=>$selectedDate->format('Y-m-d')]);
        $todayEntry=$entryLookup->fetch()?:[
            'entry_date'=>$selectedDate->format('Y-m-d'),
            'mood'=>'okay',
            'energy'=>3,
            'gratitude'=>'',
            'reflection'=>'',
            'wins'=>'',
        ];
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personal OS Journal</title><link rel="stylesheet" href="/personal-os/public/css/style.css"></head><body><main class="settings-shell"><section class="glass-panel settings-card"><div class="panel-heading"><div><p class="eyebrow">Daily reflection</p><h1>Journal</h1></div><a class="secondary-button settings-back" href="/personal-os/">Dashboard</a></div>
<?php if($message):?><div class="alert success-alert"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="alert"><?=htmlspecialchars($error)?></div><?php endif;?>
<div class="journal-streak"><span>Current streak</span><strong><?= (int)$summary['streak']?> days</strong></div>
<form class="settings-form" method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken())?>"><label>Date<input type="date" name="entry_date" value="<?=htmlspecialchars($todayEntry['entry_date']??date('Y-m-d'))?>"></label><label>Mood<select name="mood"><option value="great" <?=$todayEntry&&$todayEntry['mood']==='great'?'selected':''?>>Great</option><option value="good" <?=$todayEntry&&$todayEntry['mood']==='good'?'selected':''?>>Good</option><option value="okay" <?=!$todayEntry||$todayEntry['mood']==='okay'?'selected':''?>>Okay</option><option value="low" <?=$todayEntry&&$todayEntry['mood']==='low'?'selected':''?>>Low</option><option value="rough" <?=$todayEntry&&$todayEntry['mood']==='rough'?'selected':''?>>Rough</option></select></label><label>Energy (1-5)<input type="range" name="energy" min="1" max="5" value="<?=$todayEntry?$todayEntry['energy']:3?>"></label><label>Gratitude<textarea name="gratitude" rows="3"><?=htmlspecialchars($todayEntry['gratitude']??'')?></textarea></label><label>Wins<textarea name="wins" rows="3"><?=htmlspecialchars($todayEntry['wins']??'')?></textarea></label><label>Reflection<textarea name="reflection" rows="4"><?=htmlspecialchars($todayEntry['reflection']??'')?></textarea></label><button class="primary-button" type="submit">Save journal entry</button></form><?php if($todayEntry):?><form class="settings-form" method="post" action="/personal-os/api/journal.php" data-confirm="Delete today journal entry?"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken())?>"><input type="hidden" name="entry_date" value="<?=htmlspecialchars($todayEntry['entry_date'])?>"><input type="hidden" name="_method" value="DELETE"><button class="secondary-button" type="submit">Delete today’s entry</button></form><?php endif;?></section></main></body></html>
