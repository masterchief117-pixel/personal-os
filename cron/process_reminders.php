<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$pdo = Database::getInstance()->connection();
$statement = $pdo->prepare(
    'SELECT id,title,remind_at FROM reminders
     WHERE is_sent=FALSE AND remind_at<=NOW()
     ORDER BY remind_at ASC'
);
$statement->execute();
$due = $statement->fetchAll();

$update = $pdo->prepare('UPDATE reminders SET is_sent=TRUE WHERE id=:id');
foreach ($due as $reminder) {
    $update->execute(['id' => $reminder['id']]);
}

echo 'PROCESSED ' . count($due) . ' reminder(s)' . PHP_EOL;
