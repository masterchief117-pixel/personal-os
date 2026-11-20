<?php
declare(strict_types=1);
session_start();
if(!isset($_SESSION['authenticated'])||$_SESSION['authenticated']!==true){header('Location: /personal-os/login.php');exit;}
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/includes/functions.php';
requireAuthenticatedSession();
$pdo=Database::getInstance()->connection(); $uid=(int)$_SESSION['user_id']; $message=''; $error=''; $revealed=[];
$envFile='C:\\xampp\\.personal-os.env'; $vaultKey=getenv('VAULT_KEY');
if(!$vaultKey && is_readable($envFile)){foreach(file($envFile,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){if(str_starts_with(trim($line),'VAULT_KEY=')){$vaultKey=trim(substr(trim($line),10));break;}}}
if(!$vaultKey){$vaultKey=null;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!verifyCsrfToken($_POST)){$error='Invalid or expired form session.';}
 elseif(!$vaultKey){$error='Set VAULT_KEY in C:/xampp/.personal-os.env before using the vault.';}
 else{
  $action=$_POST['action']??'';
  if($action==='add'){
   $title=trim((string)($_POST['title']??'')); $value=(string)($_POST['value']??'');
   if($title===''||$value===''){$error='Title and secret are required.';}
   elseif(mb_strlen($title)>180){$error='Vault title must be 180 characters or fewer.';}
   elseif(strlen($value)>4096){$error='Secret exceeds the supported 4 KB limit.';}
   else{$iv=random_bytes(12); $tag=''; $cipher=openssl_encrypt($value,'aes-256-gcm',hash('sha256',$vaultKey,true),OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false){$error='Could not encrypt secret.';}else{$stmt=$pdo->prepare('INSERT INTO vault_entries(user_id,title,encrypted_value,iv,tag) VALUES(:uid,:title,:value,:iv,:tag)');$stmt->execute(['uid'=>$uid,'title'=>substr($title,0,180),'value'=>$cipher,'iv'=>$iv,'tag'=>$tag]);$message='Secret stored.';}}
  }elseif($action==='reveal'){
   $id=(int)($_POST['entry_id']??0);$stmt=$pdo->prepare('SELECT title,encrypted_value,iv,tag FROM vault_entries WHERE id=:id AND user_id=:uid');$stmt->execute(['id'=>$id,'uid'=>$uid]);$entry=$stmt->fetch();
   if($entry){$plain=openssl_decrypt($entry['encrypted_value'],'aes-256-gcm',hash('sha256',$vaultKey,true),OPENSSL_RAW_DATA,$entry['iv'],$entry['tag']);if($plain!==false){$revealed[$id]=$plain;}else{$error='Could not decrypt secret.';}}
  }elseif($action==='delete'){
   $id=(int)($_POST['entry_id']??0);$stmt=$pdo->prepare('DELETE FROM vault_entries WHERE id=:id AND user_id=:uid');$stmt->execute(['id'=>$id,'uid'=>$uid]);$message='Secret deleted.';
  }
 }
}
$stmt=$pdo->prepare('SELECT id,title,created_at,updated_at FROM vault_entries WHERE user_id=:uid ORDER BY updated_at DESC');$stmt->execute(['uid'=>$uid]);$entries=$stmt->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personal OS Vault</title><link rel="stylesheet" href="/personal-os/public/css/style.css"></head><body><main class="settings-shell"><section class="glass-panel settings-card"><div class="panel-heading"><div><p class="eyebrow">Encrypted local vault</p><h1>Private vault</h1></div><a class="secondary-button settings-back" href="/personal-os/">Dashboard</a></div><?php if($message):?><div class="alert success-alert"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="alert"><?=htmlspecialchars($error)?></div><?php endif;?>
<form class="settings-form" method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken())?>"><input type="hidden" name="action" value="add"><label>Title<input name="title" maxlength="180" required></label><label>Secret<textarea name="value" rows="4" required></textarea></label><button class="primary-button" type="submit">Encrypt and store</button></form>
<div class="team-list"><?php foreach($entries as $entry):?><article class="team-row"><div><strong><?=htmlspecialchars($entry['title'])?></strong><small><?=isset($revealed[$entry['id']])?'Secret: '.htmlspecialchars($revealed[$entry['id']]):'Encrypted entry'?></small></div><div class="vault-actions"><form method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken())?>"><input type="hidden" name="action" value="reveal"><input type="hidden" name="entry_id" value="<?=$entry['id']?>"><button class="secondary-button" type="submit">Reveal</button></form><form method="post" data-confirm="Delete this secret?"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="entry_id" value="<?=$entry['id']?>"><button class="secondary-button" type="submit">Delete</button></form></div></article><?php endforeach;?></div></section></main></body></html>
