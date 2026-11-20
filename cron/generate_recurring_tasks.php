<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$pdo = Database::getInstance()->connection();
try {
    $pdo->beginTransaction();
    $statement = $pdo->prepare(
        'SELECT id,user_id,title,description,priority,category,recurrence,next_recurrence_at
         FROM tasks
         WHERE recurrence != "none"
           AND status = "completed"
           AND next_recurrence_at IS NOT NULL
           AND next_recurrence_at <= NOW()
         FOR UPDATE'
    );
    $statement->execute();
    $sourceTasks = $statement->fetchAll();

    $insert = $pdo->prepare(
        'INSERT INTO tasks (user_id,title,description,status,priority,category,due_at,recurrence,next_recurrence_at)
         VALUES (:user_id,:title,:description,"todo",:priority,:category,:due_at,:recurrence,:next_recurrence_at)'
    );

    foreach ($sourceTasks as $task) {
        $current = new DateTimeImmutable((string) $task['next_recurrence_at']);
        $now = new DateTimeImmutable('now');
        $modify = match ($task['recurrence']) {
            'daily' => '+1 day',
            'weekly' => '+1 week',
            'monthly' => '+1 month',
            default => null,
        };
        if ($modify === null) continue;

        $next = $current->modify($modify);
        while ($next <= $now) {
            $next = $next->modify($modify);
        }

        $insert->execute([
            'user_id' => $task['user_id'],
            'title' => $task['title'],
            'description' => $task['description'],
            'priority' => $task['priority'],
            'category' => $task['category'],
            'due_at' => $next->format('Y-m-d H:i:s'),
            'recurrence' => $task['recurrence'],
            'next_recurrence_at' => $next->format('Y-m-d H:i:s'),
        ]);

        $update = $pdo->prepare(
            'UPDATE tasks SET recurrence="none",next_recurrence_at=NULL WHERE id=:id'
        );
        $update->execute(['id' => $task['id']]);
    }
    $pdo->commit();
    echo 'PROCESSED ' . count($sourceTasks) . ' recurring task(s)' . PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($exception->getMessage());
    fwrite(STDERR, 'Recurring task processing failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
