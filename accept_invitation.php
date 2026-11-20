<?php
declare(strict_types=1);
session_start();
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/includes/functions.php';
$pdo=Database::getInstance()->connection();
$token=(string)($_GET['token']??$_POST['token']??'');
$error='';
$message='';
if($token===''){
    $error='Invitation token is required.';
}elseif(!isset($_SESSION['authenticated'])||$_SESSION['authenticated']!==true){
    $error='Sign in first, then open this invitation link again.';
}elseif($_SERVER['REQUEST_METHOD']!== 'POST'||!verifyCsrfToken($_POST)){
    $error='Confirm the invitation with a secure POST request.';
}else{
    $hash=hash('sha256',$token);
    $stmt=$pdo->prepare('SELECT id,email,role,expires_at,accepted_at FROM invitations WHERE token_hash=:hash LIMIT 1');
    $stmt->execute(['hash'=>$hash]);$invitation=$stmt->fetch();
    if(!$invitation){$error='Invitation is invalid.';}
    elseif($invitation['accepted_at']){$error='Invitation has already been accepted.';}
    elseif(strtotime((string)$invitation['expires_at'])<time()){$error='Invitation has expired.';}
    else{
        $user=$pdo->prepare('SELECT id,email FROM users WHERE id=:id');$user->execute(['id'=>(int)$_SESSION['user_id']]);$current=$user->fetch();
        if(!$current||strtolower((string)$current['email'])!==strtolower((string)$invitation['email'])){$error='This invitation belongs to a different account.';}
        else{
            $pdo->beginTransaction();
            $update=$pdo->prepare('UPDATE invitations SET accepted_at=NOW() WHERE id=:id');
            $update->execute(['id'=>$invitation['id']]);
            $role=$pdo->prepare('UPDATE users SET role=:role WHERE id=:id');
            $role->execute(['role'=>$invitation['role'],'id'=>(int)$_SESSION['user_id']]);
            $pdo->commit();$message='Invitation accepted.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personal OS Invitation</title><link rel="stylesheet" href="/personal-os/public/css/style.css"></head><body><main class="settings-shell"><section class="glass-panel settings-card"><p class="eyebrow">Invitation</p><h1>Workspace access</h1><?php if($error):?><div class="alert"><?=htmlspecialchars($error)?></div><?php endif;?><?php if($message):?><div class="alert success-alert"><?=htmlspecialchars($message)?></div><?php endif;?><?php if(isset($_SESSION['authenticated'])):?><?php if($token!==''&&!$message):?><form method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken())?>"><input type="hidden" name="token" value="<?=htmlspecialchars($token)?>"><button class="primary-button" type="submit">Accept invitation</button></form><?php endif;?> <a class="secondary-button settings-back" href="/personal-os/">Dashboard</a><?php else:?><a class="secondary-button settings-back" href="/personal-os/login.php">Sign in</a><?php endif;?></section></main></body></html>
