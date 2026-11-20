<?php

declare(strict_types=1);

session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: /personal-os/login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
requireAuthenticatedSession();

$pdo = Database::getInstance()->connection();
$userId = (int) $_SESSION['user_id'];
$message = '';
$error = '';

$roleStatement = $pdo->prepare('SELECT role FROM users WHERE id = :id');
$roleStatement->execute(['id' => $userId]);
if ($roleStatement->fetchColumn() !== 'admin') {
    http_response_code(403);
    echo 'Administrator access required.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST)) {
        $error = 'Invalid or expired form session.';
    } else {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $role = (string) ($_POST['role'] ?? 'member');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } elseif (strlen($email) > 191) {
            $error = 'Email address is too long.';
        } elseif (!in_array($role, ['admin', 'member'], true)) {
            $error = 'Invalid role.';
        } else {
            $existing = $pdo->prepare(
                'SELECT id FROM invitations
                 WHERE email=:email AND accepted_at IS NULL AND expires_at>NOW()
                 LIMIT 1'
            );
            $existing->execute(['email' => $email]);
            if ($existing->fetch()) {
                $error = 'A pending invitation already exists for this email.';
            } else {
                $token = bin2hex(random_bytes(32));
                $statement = $pdo->prepare(
                    'INSERT INTO invitations (inviter_id, email, role, token_hash, expires_at)
                     VALUES (:inviter_id, :email, :role, :token_hash, DATE_ADD(NOW(), INTERVAL 7 DAY))'
                );
                $statement->execute([
                    'inviter_id' => $userId,
                    'email' => $email,
                    'role' => $role,
                    'token_hash' => hash('sha256', $token),
                ]);
                $appUrl = getenv('APP_URL') ?: 'https://127.0.0.1/personal-os';
                $acceptUrl = rtrim($appUrl, '/') . '/accept_invitation.php?token=' . rawurlencode($token);
                auditEvent($pdo,$userId,'invitation.created','invitation',(int)$pdo->lastInsertId());
                $emailStatus = sendInvitationEmail($email, $acceptUrl, $role);
                $message = match ($emailStatus) {
                    'sent' => 'Invitation created and emailed to ' . $email . '. One-time link: ' . $acceptUrl,
                    'not_configured' => 'Invitation created, but SMTP is not configured. One-time link: ' . $acceptUrl,
                    'invalid_configuration' => 'Invitation created, but the SMTP settings are invalid. One-time link: ' . $acceptUrl,
                    'failed' => 'Invitation created, but email delivery failed. Check the server log. One-time link: ' . $acceptUrl,
                    default => throw new LogicException('Unexpected invitation email status.'),
                };
            }
        }
    }
}

$statement = $pdo->prepare('SELECT email, role, expires_at, accepted_at, created_at FROM invitations WHERE inviter_id = :id ORDER BY created_at DESC');
$statement->execute(['id' => $userId]);
$invitations = $statement->fetchAll();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personal OS Invitations</title><link rel="stylesheet" href="/personal-os/public/css/style.css"></head>
<body>
  <main class="settings-shell">
    <section class="glass-panel settings-card">
      <div class="panel-heading"><div><p class="eyebrow">Collaboration</p><h1>Invitations</h1></div><a class="secondary-button settings-back" href="/personal-os/team.php">Team</a></div>
      <?php if ($message): ?><div class="alert success-alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form class="settings-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <label>Email address<input type="email" name="email" required></label>
        <label>Role<select name="role"><option value="member">Member</option><option value="admin">Admin</option></select></label>
        <button class="primary-button" type="submit">Create invitation</button>
      </form>
      <div class="team-list">
        <?php foreach ($invitations as $invitation): ?>
          <article class="team-row"><div><strong><?= htmlspecialchars($invitation['email']) ?></strong><small><?= htmlspecialchars($invitation['role']) ?> · <?= $invitation['accepted_at'] ? 'Accepted' : 'Pending' ?></small></div></article>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
</body>
</html>
