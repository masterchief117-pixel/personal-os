<?php

declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';
$needsSetup = false;

try {
    $setupStatement = Database::getInstance()->connection()->prepare('SELECT password_hash FROM users WHERE id = 1');
    $setupStatement->execute();
    $needsSetup = trim((string) $setupStatement->fetchColumn()) === '*UNPROVISIONED*';
} catch (Throwable) {
    $needsSetup = false;
}

if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header('Location: /personal-os/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post = $_POST;
    if (!verifyCsrfToken($post)) {
        $error = 'Invalid or expired form session. Refresh and try again.';
    } else {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    try {
        $pdo = Database::getInstance()->connection();
        $attemptStatement = $pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE succeeded = FALSE
               AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
               AND (ip_address = :ip_address OR email = :email)'
        );
        $normalizedEmail = strtolower(trim($email));
        $attemptStatement->execute(['ip_address' => $ipAddress, 'email' => $normalizedEmail]);
        if ((int) $attemptStatement->fetchColumn() >= 5) {
            $error = 'Too many failed attempts. Try again in 15 minutes.';
        } else {
        $statement = $pdo->prepare(
            'SELECT id, name, email, password_hash FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $record = $pdo->prepare('INSERT INTO login_attempts(ip_address,email,succeeded) VALUES(:ip_address,:email,TRUE)');
            $record->execute(['ip_address' => $ipAddress, 'email' => $email]);
            $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)')->execute();
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['last_activity'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: /personal-os/');
            exit;
        }

        $record = $pdo->prepare('INSERT INTO login_attempts(ip_address,email,succeeded) VALUES(:ip_address,:email,FALSE)');
        $record->execute(['ip_address' => $ipAddress, 'email' => $email]);
        $error = 'Invalid email or password.';
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $error = 'Unable to connect. Verify MySQL is running and the schema is imported.';
    }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Personal OS - Sign in</title>
  <link rel="stylesheet" href="/personal-os/public/css/style.css">
</head>
<body class="auth-page">
  <main class="auth-shell">
    <section class="auth-card glass-panel">
      <a class="brand auth-brand" href="/personal-os/login.php">
        <span class="brand-mark">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 21 7v10l-9 5-9-5V7l9-5Zm0 4.2L6.8 9l5.2 2.9L17.2 9 12 6.2Z"/></svg>
        </span>
        <span>Personal OS</span>
      </a>
      <div class="auth-heading">
        <p class="eyebrow">Private workspace</p>
        <h1>Welcome back</h1>
        <p>Sign in to access your command center.</p>
      </div>
      <?php if ($error): ?><div class="alert auth-alert" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if (isset($_GET['timeout'])): ?><div class="alert auth-alert" role="status">Your session expired. Please sign in again.</div><?php endif; ?>
      <form class="auth-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <label>Email address<input type="email" name="email" value="<?= htmlspecialchars((string) ($_POST['email'] ?? '')) ?>" required autocomplete="email"></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password" placeholder="Enter password"></label>
        <button class="primary-button" type="submit">Sign in</button>
      </form>
      <?php if ($needsSetup): ?>
        <p class="auth-demo"><a href="/personal-os/setup.php">Provision the administrator account</a></p>
      <?php else: ?>
        <p class="auth-demo">A private local workspace.</p>
      <?php endif; ?>
    </section>
  </main>
  <script src="/personal-os/public/js/app.js"></script>
</body>
</html>
