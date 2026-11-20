<?php
declare(strict_types=1);
session_start();
if(!isset($_SESSION['authenticated'])||$_SESSION['authenticated']!==true){header('Location: /personal-os/login.php');exit;}
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/includes/functions.php';
requireAuthenticatedSession();
$pdo=Database::getInstance()->connection(); $uid=(int)$_SESSION['user_id'];
$taskStmt=$pdo->prepare('SELECT COUNT(*) total,SUM(status="completed") completed,SUM(status="in_progress") active,SUM(status="todo") todo FROM tasks WHERE user_id=:uid');$taskStmt->execute(['uid'=>$uid]);$tasks=$taskStmt->fetch();
$categoryStmt=$pdo->prepare('SELECT category,COUNT(*) count FROM tasks WHERE user_id=:uid GROUP BY category ORDER BY count DESC LIMIT 6');$categoryStmt->execute(['uid'=>$uid]);$categories=$categoryStmt->fetchAll();
$noteCount=$pdo->prepare('SELECT COUNT(*) FROM notes WHERE user_id=:uid');$noteCount->execute(['uid'=>$uid]);
$habitCount=$pdo->prepare('SELECT COUNT(*) FROM habits WHERE user_id=:uid AND is_active=TRUE');$habitCount->execute(['uid'=>$uid]);
$completion=$tasks['total']?round(((int)$tasks['completed']/(int)$tasks['total'])*100):0;
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personal OS Analytics</title><link rel="stylesheet" href="/personal-os/public/css/style.css"></head><body><main class="settings-shell"><section class="glass-panel settings-card"><div class="panel-heading"><div><p class="eyebrow">Reporting</p><h1>Productivity analytics</h1></div><a class="secondary-button settings-back" href="/personal-os/">Dashboard</a></div><div class="stat-grid analytics-grid"><div><strong><?=$completion?>%</strong><small>Completion</small></div><div><strong><?=htmlspecialchars((string)$tasks['completed'])?></strong><small>Completed</small></div><div><strong><?=htmlspecialchars((string)$tasks['active'])?></strong><small>Active</small></div><div><strong><?=htmlspecialchars((string)$tasks['todo'])?></strong><small>To do</small></div><div><strong><?=$noteCount->fetchColumn()?></strong><small>Notes</small></div><div><strong><?=$habitCount->fetchColumn()?></strong><small>Habits</small></div></div><h2 class="section-title">Tasks by category</h2><div class="category-bars"><?php foreach($categories as $category):$width=$tasks['total']?max(8,round(((int)$category['count']/(int)$tasks['total'])*100)):0;?><div><span><?=htmlspecialchars($category['category'])?></span><div class="bar-track"><i style="width:<?=$width?>%"></i></div><strong><?= (int)$category['count']?></strong></div><?php endforeach;?></div></section></main></body></html>
