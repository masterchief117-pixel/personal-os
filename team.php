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
$roleStatement = $pdo->prepare('SELECT role FROM users WHERE id = :id');
$roleStatement->execute(['id' => $userId]);
$role = (string) $roleStatement->fetchColumn();
if ($role !== 'admin') {
    http_response_code(403);
    echo 'Administrator access required.';
    exit;
}

$usersStatement = $pdo->query('SELECT id, name, email, role, timezone, created_at FROM users ORDER BY name');
$users = $usersStatement->fetchAll();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personal OS Team</title><link rel="stylesheet" href="/personal-os/public/css/style.css"></head>
<body>
  <main class="settings-shell">
    <section class="glass-panel settings-card">
      <div class="panel-heading"><div><p class="eyebrow">Administration</p><h1>Team and roles</h1></div><a class="secondary-button settings-back" href="/personal-os/">Dashboard</a></div>
      <div class="export-actions"><a class="secondary-button" href="/personal-os/invitations.php">Manage invitations</a><a class="secondary-button" href="/personal-os/admin/backup.php">Backup and restore</a></div>
      <div class="team-list">
        <?php foreach ($users as $member): ?>
          <article class="team-row">
            <div class="avatar"><?= htmlspecialchars(strtoupper(substr($member['name'], 0, 1))) ?></div>
            <div><strong><?= htmlspecialchars($member['name']) ?></strong><small><?= htmlspecialchars($member['email']) ?></small></div>
            <span class="priority priority-medium"><?= htmlspecialchars($member['role']) ?></span>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
</body>
</html>
