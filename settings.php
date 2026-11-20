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
$user = getCurrentUser($pdo, $userId);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST)) {
        $error = 'Invalid or expired form session.';
    } else {
    $name = trim((string) ($_POST['name'] ?? ''));
    $timezone = trim((string) ($_POST['timezone'] ?? 'Europe/Istanbul'));
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '') {
        $error = 'Display name is required.';
    } elseif ($password !== '' && strlen($password) < 12) {
        $error = 'Password must be at least 12 characters.';
    } else {
        try {
            if ($password !== '') {
                $statement = $pdo->prepare(
                    'UPDATE users SET name = :name, timezone = :timezone, password_hash = :password_hash WHERE id = :id'
                );
                $statement->execute([
                    'name' => $name,
                    'timezone' => $timezone,
                    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                    'id' => $userId,
                ]);
            } else {
                $statement = $pdo->prepare('UPDATE users SET name = :name, timezone = :timezone WHERE id = :id');
                $statement->execute(['name' => $name, 'timezone' => $timezone, 'id' => $userId]);
            }
            $user = getCurrentUser($pdo, $userId);
            $message = 'Settings saved.';
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $error = 'Could not save settings.';
        }
    }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#090b10">
  <title>Personal OS - Settings</title>
  <link rel="manifest" href="/personal-os/manifest.webmanifest">
  <link rel="stylesheet" href="/personal-os/public/css/style.css">
</head>
<body>
  <main class="settings-shell">
    <section class="glass-panel settings-card">
      <div class="panel-heading">
        <div><p class="eyebrow">Account</p><h1>Profile settings</h1></div>
        <a class="secondary-button settings-back" href="/personal-os/">Back to dashboard</a>
      </div>
      <?php if ($message): ?><div class="alert success-alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form class="settings-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <label>Display name<input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required></label>
        <label>Email address<input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled></label>
        <label>Timezone<select name="timezone">
          <?php foreach (['Europe/Istanbul', 'Europe/London', 'America/New_York', 'America/Los_Angeles', 'Asia/Tokyo', 'UTC'] as $timezone): ?>
            <option value="<?= htmlspecialchars($timezone) ?>" <?= $timezone === $user['timezone'] ? 'selected' : '' ?>><?= htmlspecialchars($timezone) ?></option>
          <?php endforeach; ?>
        </select></label>
        <label>New password<input type="password" name="password" autocomplete="new-password" placeholder="Leave blank to keep current password"></label>
        <button class="primary-button" type="submit">Save changes</button>
      </form>
      <div class="export-actions">
        <a class="secondary-button" href="/personal-os/export.php?format=json">Export JSON</a>
        <a class="secondary-button" href="/personal-os/export.php?format=csv">Export tasks CSV</a>
        <a class="secondary-button" href="/personal-os/calendar.php">Open calendar</a>
        <a class="secondary-button" href="/personal-os/import.php">Import JSON</a>
      </div>
    </section>
  </main>
  <script src="/personal-os/public/js/app.js"></script>
</body>
</html>
