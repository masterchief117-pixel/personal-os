<?php

declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = Database::getInstance()->connection();
$setupStatement = $pdo->prepare('SELECT password_hash FROM users WHERE id = 1');
$setupStatement->execute();
$bootstrapHash = (string) $setupStatement->fetchColumn();
$provisioningAllowed = $bootstrapHash === '*UNPROVISIONED*';
$remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$localAddress = in_array($remoteAddress, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true);
$error = '';
if (!$provisioningAllowed) {
    $error = 'Administrator setup is already complete.';
} elseif (!$localAddress) {
    $error = 'First-run setup is restricted to localhost.';
    http_response_code(403);
}
$message = '';

if ($provisioningAllowed && $localAddress && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST)) {
        $error = 'Invalid or expired setup session.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            $error = 'Use a valid email and a password with at least 12 characters.';
        } else {
            $statement = $pdo->prepare(
                'UPDATE users SET name = :name, email = :email, password_hash = :password_hash, role = "admin"
                 WHERE id = 1 AND password_hash = "*UNPROVISIONED*"'
            );
            $statement->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            ]);
            if ($statement->rowCount() !== 1) {
                $error = 'Administrator setup has already been completed.';
            } else {
                $message = 'Administrator account provisioned. You can now sign in.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personal OS Setup</title><link rel="stylesheet" href="/personal-os/public/css/style.css"></head>
<body class="auth-page">
  <main class="auth-shell">
    <section class="auth-card glass-panel">
      <p class="eyebrow">First run</p><h1>Provision your workspace</h1>
      <?php if ($error): ?><div class="alert auth-alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($message): ?><div class="alert success-alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <form class="auth-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <label>Display name<input name="name" required></label>
        <label>Email address<input type="email" name="email" required></label>
        <label>Password<input type="password" name="password" minlength="12" required autocomplete="new-password"></label>
        <button class="primary-button" type="submit">Create administrator</button>
      </form>
    </section>
  </main>
</body>
</html>
