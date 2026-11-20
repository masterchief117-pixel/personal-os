<?php

declare(strict_types=1);

session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: /personal-os/login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAuthenticatedSession();

$pdo = Database::getInstance()->connection();
$userId = (int) $_SESSION['user_id'];
$statement = $pdo->prepare(
    'SELECT title,status,priority,category,due_at FROM tasks
     WHERE user_id=:user_id AND status!="completed" AND status!="archived"
     ORDER BY FIELD(priority,"urgent","high","medium","low"), due_at IS NULL, due_at ASC'
);
$statement->execute(['user_id' => $userId]);
$tasks = $statement->fetchAll();
$focusTasks = array_slice(array_values(array_filter($tasks, static fn(array $task): bool => in_array($task['priority'], ['urgent','high'], true))) ?: $tasks, 0, 3);
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personal OS Planning Assistant</title><link rel="stylesheet" href="/personal-os/public/css/style.css"></head>
<body>
  <main class="settings-shell">
    <section class="glass-panel settings-card">
      <div class="panel-heading"><div><p class="eyebrow">Local planning</p><h1>Daily focus assistant</h1></div><a class="secondary-button settings-back" href="/personal-os/">Dashboard</a></div>
      <div class="planning-summary">
        <p class="eyebrow">Recommended focus</p>
        <?php if (!$focusTasks): ?>
          <article class="team-row"><div><strong>Your queue is clear.</strong><small>Add one meaningful task for the next focus block.</small></div></article>
        <?php else: ?>
          <?php foreach ($focusTasks as $index => $task): ?>
            <article class="team-row"><div class="avatar"><?= $index + 1 ?></div><div><strong><?= htmlspecialchars($task['title']) ?></strong><small><?= htmlspecialchars($task['category']) ?> · <?= htmlspecialchars($task['priority']) ?> priority</small></div></article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="export-actions">
        <a class="secondary-button" href="/personal-os/calendar.php">Open calendar</a>
        <button class="primary-button refresh-plan-button" type="button">Refresh plan</button>
      </div>
    </section>
  </main>
</body>
</html>
