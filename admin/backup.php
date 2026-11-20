<?php

declare(strict_types=1);

session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: /personal-os/login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuthenticatedSession();

$pdo = Database::getInstance()->connection();
$userId = (int) $_SESSION['user_id'];
$role = $pdo->prepare('SELECT role FROM users WHERE id = :id');
$role->execute(['id' => $userId]);
if ($role->fetchColumn() !== 'admin') {
    http_response_code(403);
    echo 'Administrator access required.';
    exit;
}

$message = '';
$error = '';

$scheduleStatement = $pdo->prepare('SELECT id,frequency,is_enabled,last_run_at,next_run_at FROM backup_schedules WHERE user_id=:uid');
$scheduleStatement->execute(['uid'=>$userId]);
$backupSchedule = $scheduleStatement->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'schedule') {
    if (!verifyCsrfToken($_POST)) {
        $error = 'Invalid or expired form session.';
    } else {
        $frequency = in_array($_POST['frequency'] ?? '', ['daily','weekly','monthly'], true) ? $_POST['frequency'] : 'daily';
        $enabled = isset($_POST['is_enabled']) ? 1 : 0;
        $interval = $frequency === 'weekly' ? '+1 week' : ($frequency === 'monthly' ? '+1 month' : '+1 day');
        $next = date('Y-m-d H:i:s', strtotime($interval));
        $statement = $pdo->prepare(
            'INSERT INTO backup_schedules(user_id,frequency,is_enabled,next_run_at)
             VALUES(:uid,:frequency,:enabled,:next_run)
             ON DUPLICATE KEY UPDATE frequency=:frequency,is_enabled=:enabled,next_run_at=:next_run'
        );
        $statement->execute(['uid'=>$userId,'frequency'=>$frequency,'enabled'=>$enabled,'next_run'=>$next]);
        $message = 'Backup schedule saved.';
        $scheduleStatement->execute(['uid'=>$userId]);
        $backupSchedule = $scheduleStatement->fetch();
        auditEvent($pdo,$userId,'backup.schedule_saved','backup_schedule',$backupSchedule['id'] ?? null);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore') {
    if (!verifyCsrfToken($_POST)) {
        $error = 'Invalid or expired form session.';
    } elseif (!isset($_FILES['backup']) || ($_FILES['backup']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Choose a valid backup JSON file.';
    } elseif (!is_uploaded_file($_FILES['backup']['tmp_name'])) {
        $error = 'The uploaded backup could not be validated.';
    } elseif (($_FILES['backup']['size'] ?? 0) > 5 * 1024 * 1024) {
        $error = 'Backup files must be smaller than 5 MB.';
    } else {
        $json = file_get_contents($_FILES['backup']['tmp_name']);
        $backup = json_decode((string) $json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($backup) || !isset($backup['tasks'], $backup['notes']) || !is_array($backup['tasks']) || !is_array($backup['notes'])) {
            $error = 'The selected file is not a Personal OS backup.';
        } elseif (count($backup['tasks']) > 10000 || count($backup['notes']) > 10000) {
            $error = 'The backup contains too many records.';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM tasks WHERE user_id = :user_id')->execute(['user_id' => $userId]);
                $pdo->prepare('DELETE FROM notes WHERE user_id = :user_id')->execute(['user_id' => $userId]);

                $taskInsert = $pdo->prepare(
                    'INSERT INTO tasks (user_id,title,description,status,priority,category,due_at,completed_at,created_at)
                     VALUES (:user_id,:title,:description,:status,:priority,:category,:due_at,:completed_at,:created_at)'
                );
                foreach ($backup['tasks'] as $task) {
                    if (!is_array($task)) throw new InvalidArgumentException('Invalid task record');
                    if (!is_string($task['title'] ?? null) || trim((string)$task['title']) === '') throw new InvalidArgumentException('Invalid task title');
                    $taskInsert->execute([
                        'user_id' => $userId,
                        'title' => substr((string) ($task['title'] ?? ''), 0, 180),
                        'description' => (string) ($task['description'] ?? ''),
                        'status' => in_array($task['status'] ?? '', ['todo','in_progress','completed','archived'], true) ? $task['status'] : 'todo',
                        'priority' => in_array($task['priority'] ?? '', ['low','medium','high','urgent'], true) ? $task['priority'] : 'medium',
                        'category' => substr((string) ($task['category'] ?? 'Personal'), 0, 60),
                        'due_at' => $task['due_at'] ?? null,
                        'completed_at' => $task['completed_at'] ?? null,
                        'created_at' => $task['created_at'] ?? null,
                    ]);
                }

                $noteInsert = $pdo->prepare(
                    'INSERT INTO notes (user_id,title,content,color,is_pinned,created_at)
                     VALUES (:user_id,:title,:content,:color,:pinned,:created_at)'
                );
                foreach ($backup['notes'] as $note) {
                    $noteInsert->execute([
                        'user_id' => $userId,
                        'title' => substr((string) ($note['title'] ?? 'Untitled'), 0, 180),
                        'content' => (string) ($note['content'] ?? ''),
                        'color' => in_array($note['color'] ?? '', ['violet','amber','cyan','rose'], true) ? $note['color'] : 'violet',
                        'pinned' => !empty($note['is_pinned']) ? 1 : 0,
                        'created_at' => $note['created_at'] ?? null,
                    ]);
                }
                $pdo->commit();
                $message = 'Backup restored successfully.';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log($exception->getMessage());
                $error = 'The backup could not be restored.';
            }
        }
    }
}

$tasks = $pdo->prepare('SELECT id,title,description,status,priority,category,due_at,completed_at,created_at FROM tasks WHERE user_id = :id');
$tasks->execute(['id' => $userId]);
$taskRows = $tasks->fetchAll();
$notes = $pdo->prepare('SELECT id,title,content,color,is_pinned,created_at,updated_at FROM notes WHERE user_id = :id');
$notes->execute(['id' => $userId]);
$noteRows = $notes->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'download') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="personal-os-backup-' . date('Y-m-d') . '.json"');
    echo json_encode(['exported_at' => date(DATE_ATOM), 'tasks' => $taskRows, 'notes' => $noteRows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personal OS Backup</title><link rel="stylesheet" href="/personal-os/public/css/style.css"></head>
<body>
  <main class="settings-shell">
    <section class="glass-panel settings-card">
      <div class="panel-heading"><div><p class="eyebrow">Administration</p><h1>Backup and restore</h1></div><a class="secondary-button settings-back" href="/personal-os/settings.php">Settings</a></div>
      <?php if ($message): ?><div class="alert success-alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form class="settings-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="download">
        <button class="primary-button" type="submit">Download backup</button>
      </form>
      <form class="settings-form" id="restoreBackupForm" method="post" enctype="multipart/form-data" data-confirm="Restore replaces tasks and notes. Continue?">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="restore">
        <label>Restore JSON backup<input type="file" name="backup" accept="application/json,.json" required></label>
        <button class="secondary-button" type="submit" form="restoreBackupForm">Restore backup</button>
      </form>
      <form class="settings-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="schedule">
        <label>Backup frequency<select name="frequency"><option value="daily" <?=($backupSchedule&&$backupSchedule['frequency']==='daily')?'selected':''?>>Daily</option><option value="weekly" <?=($backupSchedule&&$backupSchedule['frequency']==='weekly')?'selected':''?>>Weekly</option><option value="monthly" <?=($backupSchedule&&$backupSchedule['frequency']==='monthly')?'selected':''?>>Monthly</option></select></label>
        <label class="pin-option"><input type="checkbox" name="is_enabled" value="1" <?=!$backupSchedule||$backupSchedule['is_enabled']?'checked':''?>> Enable scheduled backup</label>
        <button class="secondary-button" type="submit">Save backup schedule</button>
      </form>
    </section>
  </main>
</body>
</html>
