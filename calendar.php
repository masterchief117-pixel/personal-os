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

$month = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', (string) $_GET['month'])
    ? (string) $_GET['month'] : date('Y-m');
$first = new DateTimeImmutable($month . '-01');
$daysInMonth = (int) $first->format('t');
$offset = (int) $first->format('N') - 1;

$statement = $pdo->prepare(
    'SELECT id, title, status, priority, category, due_at
     FROM tasks
     WHERE user_id = :user_id AND due_at >= :start AND due_at < :end
     ORDER BY due_at ASC'
);
$statement->execute([
    'user_id' => $userId,
    'start' => $first->format('Y-m-d 00:00:00'),
    'end' => $first->modify('+1 month')->format('Y-m-d 00:00:00'),
]);
$tasksByDay = [];
foreach ($statement->fetchAll() as $task) {
    $day = (int) date('j', strtotime($task['due_at']));
    $tasksByDay[$day][] = $task;
}

$previous = $first->modify('-1 month')->format('Y-m');
$next = $first->modify('+1 month')->format('Y-m');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Personal OS - Calendar</title>
  <link rel="stylesheet" href="/personal-os/public/css/style.css">
</head>
<body>
  <main class="calendar-shell">
    <section class="glass-panel calendar-card">
      <div class="panel-heading calendar-heading">
        <div><p class="eyebrow">Daily planning</p><h1><?= htmlspecialchars($first->format('F Y')) ?></h1></div>
        <div class="calendar-actions">
          <a class="secondary-button" href="/personal-os/calendar.php?month=<?= $previous ?>">Previous</a>
          <a class="secondary-button" href="/personal-os/calendar.php?month=<?= date('Y-m') ?>">Today</a>
          <a class="secondary-button" href="/personal-os/calendar.php?month=<?= $next ?>">Next</a>
          <a class="primary-button" href="/personal-os/">Dashboard</a>
        </div>
      </div>
      <div class="calendar-grid">
        <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $name): ?>
          <div class="calendar-weekday"><?= $name ?></div>
        <?php endforeach; ?>
        <?php for ($i = 0; $i < $offset; $i++): ?><div class="calendar-day muted-day"></div><?php endfor; ?>
        <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
          <div class="calendar-day <?= $day === (int) date('j') && $month === date('Y-m') ? 'today' : '' ?>" data-date="<?= htmlspecialchars($month . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT)) ?>">
            <span class="day-number"><?= $day ?></span>
            <?php foreach ($tasksByDay[$day] ?? [] as $task): ?>
              <a class="calendar-task priority-<?= htmlspecialchars($task['priority']) ?>" data-task-id="<?= (int) $task['id'] ?>" draggable="true" href="/personal-os/#tasks">
                <?= htmlspecialchars(date('H:i', strtotime($task['due_at']))) ?> <?= htmlspecialchars($task['title']) ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endfor; ?>
      </div>
    </section>
  </main>
</body>
</html>
