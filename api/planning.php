<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuthenticatedSession();

$pdo = Database::getInstance()->connection();
$userId = (int) $_SESSION['user_id'];

$statement = $pdo->prepare(
    'SELECT title,status,priority,category,due_at
     FROM tasks
     WHERE user_id=:user_id AND status!="completed" AND status!="archived"
     ORDER BY FIELD(priority,"urgent","high","medium","low"), due_at IS NULL, due_at ASC'
);
$statement->execute(['user_id' => $userId]);
$tasks = $statement->fetchAll();

$completed = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE user_id=:user_id AND status="completed"');
$completed->execute(['user_id' => $userId]);
$completedCount = (int) $completed->fetchColumn();

$urgent = array_values(array_filter($tasks, static fn(array $task): bool => in_array($task['priority'], ['urgent','high'], true)));
$focus = array_slice($urgent !== [] ? $urgent : $tasks, 0, 3);
$actions = array_map(static fn(array $task): string => 'Start "' . $task['title'] . '" and reserve one 25-minute focus block.', $focus);

$summary = [
    'open_tasks' => count($tasks),
    'completed_tasks' => $completedCount,
    'priority_tasks' => count($urgent),
    'focus_plan' => $actions,
    'recommendation' => count($tasks) === 0
        ? 'Your queue is clear. Choose a meaningful goal and add one focused task.'
        : 'Protect the first focus block, complete one high-priority task, and reschedule anything that lacks a realistic deadline.',
];

echo json_encode(['summary' => $summary], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
