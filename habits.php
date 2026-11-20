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
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST)) {
        $error = 'Invalid or expired form session.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $recurrence = (string) ($_POST['recurrence'] ?? 'daily');
        $target = max(1, min(99, (int) ($_POST['target_count'] ?? 1)));
        if ($name === '') {
            $error = 'Habit name is required.';
        } elseif (mb_strlen($name) > 180) {
            $error = 'Habit name must be 180 characters or fewer.';
        } elseif (!in_array($recurrence, ['daily', 'weekly', 'monthly'], true)) {
            $error = 'Invalid recurrence.';
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO habits (user_id, name, recurrence, target_count) VALUES (:user_id, :name, :recurrence, :target)'
            );
            $statement->execute(['user_id' => $userId, 'name' => $name, 'recurrence' => $recurrence, 'target' => $target]);
            $message = 'Habit created.';
        }
    }
}

$statement = $pdo->prepare(
    'SELECT h.id, h.name, h.recurrence, h.target_count,
            COALESCE((SELECT hl.count FROM habit_logs hl WHERE hl.habit_id = h.id AND hl.user_id = h.user_id AND hl.log_date = CURDATE()), 0) AS today_count
     FROM habits h WHERE h.user_id = :user_id AND h.is_active = TRUE ORDER BY h.created_at DESC'
);
$statement->execute(['user_id' => $userId]);
$habits = $statement->fetchAll();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personal OS Habits</title><link rel="stylesheet" href="/personal-os/public/css/style.css"></head>
<body>
  <main class="settings-shell">
    <section class="glass-panel settings-card">
      <div class="panel-heading"><div><p class="eyebrow">Recurring routines</p><h1>Habits</h1></div><a class="secondary-button settings-back" href="/personal-os/">Dashboard</a></div>
      <?php if ($message): ?><div class="alert success-alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form class="settings-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <label>Habit name<input name="name" required placeholder="Morning walk"></label>
        <label>Recurrence<select name="recurrence"><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></label>
        <label>Target count<input type="number" name="target_count" value="1" min="1" max="99"></label>
        <button class="primary-button" type="submit">Create habit</button>
      </form>
      <div class="habit-list">
        <?php foreach ($habits as $habit): ?>
          <article class="team-row" id="habit-<?= (int) $habit['id'] ?>">
            <div class="avatar">✓</div>
            <div><strong><?= htmlspecialchars($habit['name']) ?></strong><small><?= htmlspecialchars($habit['recurrence']) ?> · <?= (int) $habit['today_count'] ?>/<?= (int) $habit['target_count'] ?> today</small></div>
          </article>
        <?php endforeach; ?>
        <?php if (!$habits): ?><p class="auth-demo">No habits yet.</p><?php endif; ?>
      </div>
    </section>
  </main>
</body>
</html>
