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
        if ($name === '' || mb_strlen($name) > 100) {
            $error = 'Workspace name must contain 1-100 characters.';
        } else {
            try {
                $pdo->beginTransaction();
                $create = $pdo->prepare('INSERT INTO workspaces (name,owner_id) VALUES (:name,:owner_id)');
                $create->execute(['name' => $name, 'owner_id' => $userId]);
                $workspaceId = (int) $pdo->lastInsertId();
                $member = $pdo->prepare('INSERT INTO workspace_members (workspace_id,user_id,role) VALUES (:workspace_id,:user_id,"owner")');
                $member->execute(['workspace_id' => $workspaceId, 'user_id' => $userId]);
                $pdo->commit();
                $message = 'Workspace created.';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Could not create workspace.';
            }
        }
    }
}

$statement = $pdo->prepare(
    'SELECT w.id,w.name,w.owner_id,wm.role
     FROM workspaces w JOIN workspace_members wm ON wm.workspace_id=w.id
     WHERE wm.user_id=:user_id ORDER BY w.created_at DESC'
);
$statement->execute(['user_id' => $userId]);
$workspaces = $statement->fetchAll();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personal OS Workspaces</title><link rel="stylesheet" href="/personal-os/public/css/style.css"></head>
<body>
  <main class="settings-shell">
    <section class="glass-panel settings-card">
      <div class="panel-heading"><div><p class="eyebrow">Collaboration</p><h1>Workspaces</h1></div><a class="secondary-button settings-back" href="/personal-os/">Dashboard</a></div>
      <?php if ($message): ?><div class="alert success-alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form class="settings-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <label>Workspace name<input name="name" maxlength="100" required placeholder="Family or Product"></label>
        <button class="primary-button" type="submit">Create workspace</button>
      </form>
      <div class="team-list">
        <?php foreach ($workspaces as $workspace): ?>
          <article class="team-row"><div class="avatar"><?= htmlspecialchars(strtoupper(substr($workspace['name'], 0, 1))) ?></div><div><strong><?= htmlspecialchars($workspace['name']) ?></strong><small><?= htmlspecialchars($workspace['role']) ?></small></div></article>
        <?php endforeach; ?>
        <?php if (!$workspaces): ?><p class="auth-demo">No shared workspaces yet.</p><?php endif; ?>
      </div>
    </section>
  </main>
</body>
</html>
