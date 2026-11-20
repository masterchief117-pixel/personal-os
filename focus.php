<?php
declare(strict_types=1);
session_start();
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/includes/functions.php';
requireAuthenticatedSession();
$pdo=Database::getInstance()->connection();$uid=(int)$_SESSION['user_id'];
$summary=getFocusSummary($pdo,$uid);
$tasks=$pdo->prepare('SELECT id,title FROM tasks WHERE user_id=:uid AND status!="completed" AND status!="archived" ORDER BY due_at IS NULL,due_at ASC LIMIT 20');$tasks->execute(['uid'=>$uid]);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personal OS Focus</title><link rel="stylesheet" href="/personal-os/public/css/style.css"></head><body><main class="settings-shell"><section class="glass-panel settings-card focus-card"><div class="panel-heading"><div><p class="eyebrow">Deep work</p><h1>Focus timer</h1></div><a class="secondary-button settings-back" href="/personal-os/">Dashboard</a></div><div class="focus-timer" id="focusTimer" data-duration="1500">25:00</div><form class="settings-form" id="focusForm"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken())?>"><label>Duration<select name="duration"><option value="25">25 minutes</option><option value="50">50 minutes</option><option value="15">15 minutes</option><option value="5">5 minutes</option></select></label><label>Linked task<select name="task_id"><option value="">No task</option><?php foreach($tasks->fetchAll() as $task):?><option value="<?=$task['id']?>"><?=htmlspecialchars($task['title'])?></option><?php endforeach;?></select></label><div class="export-actions"><button class="primary-button" type="submit" id="startFocus">Start session</button><button class="secondary-button" type="button" id="resetFocus">Reset</button></div></form><div class="stat-grid analytics-grid"><div><strong><?= (int)$summary['completed']?></strong><small>Completed</small></div><div><strong><?= (int)$summary['minutes']?></strong><small>Focus minutes</small></div><div><strong><?= (int)$summary['today']?></strong><small>Today</small></div></div></section></main><script src="/personal-os/public/js/app.js"></script></body></html>
