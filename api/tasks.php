<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json');

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuthenticatedSession();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = (int) ($_SESSION['user_id'] ?? 1);

try {
    $pdo = Database::getInstance()->connection();

    if ($method === 'GET') {
        $statement = $pdo->prepare(
            'SELECT id, title, description, status, priority, category, due_at, recurrence, next_recurrence_at, completed_at, created_at
             FROM tasks WHERE user_id = :user_id AND status != "archived"
             ORDER BY FIELD(status, "in_progress", "todo", "completed"), due_at ASC'
        );
        $statement->execute(['user_id' => $userId]);
        jsonResponse(['tasks' => $statement->fetchAll()]);
    }

    $rawInput = (string) file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        parse_str($rawInput, $input);
    }
    if (!is_array($input) || !$input) $input = $_POST;
    if ($method !== 'GET') requireCsrf($input);

    if ($method === 'POST') {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') jsonResponse(['error' => 'Task title is required'], 422);
        if (mb_strlen($title) > 180) jsonResponse(['error' => 'Task title is too long'], 422);

        $priority = in_array($input['priority'] ?? '', ['low', 'medium', 'high', 'urgent'], true)
            ? $input['priority'] : 'medium';
        $category = trim((string) ($input['category'] ?? 'Personal'));
        if (mb_strlen($category) > 60) jsonResponse(['error' => 'Category is too long'], 422);
        $recurrence = in_array($input['recurrence'] ?? 'none', ['none', 'daily', 'weekly', 'monthly'], true)
            ? $input['recurrence'] : 'none';
            $dueAt = trim((string) ($input['due_at'] ?? ''));
            $dueAtValue = null;
            if ($dueAt !== '') {
                $dueDate = date_create($dueAt);
                if ($dueDate !== false) $dueAtValue = $dueDate->format('Y-m-d H:i:s');
            }

        $statement = $pdo->prepare(
                'INSERT INTO tasks (user_id, title, description, status, priority, category, due_at, recurrence, next_recurrence_at)
                 VALUES (:user_id, :title, :description, "todo", :priority, :category, :due_at, :recurrence, :next_recurrence_at)'
        );
        $statement->execute([
            'user_id' => $userId,
            'title' => $title,
            'description' => trim((string) ($input['description'] ?? '')),
            'priority' => $priority,
            'category' => $category !== '' ? $category : 'Personal',
            'due_at' => $dueAtValue,
            'recurrence' => $recurrence,
            'next_recurrence_at' => $recurrence !== 'none'
                ? ($dueAtValue ?? (new DateTimeImmutable())->modify(
                    match ($recurrence) {
                        'daily' => '+1 day',
                        'weekly' => '+1 week',
                        'monthly' => '+1 month',
                        default => '+0 seconds',
                    }
                )->format('Y-m-d H:i:s'))
                : null,
        ]);
        $taskId = (int) $pdo->lastInsertId();
        auditEvent($pdo,$userId,'task.created','task',$taskId);
        executeAutomationRules($pdo, $userId, 'task_created', [
            'category' => $category !== '' ? $category : 'Personal',
            'priority' => $priority,
            'title' => $title,
        ]);
        jsonResponse(['id' => $taskId, 'message' => 'Task added'], 201);
    }

    if ($method === 'PATCH') {
        $id = (int) ($input['id'] ?? 0);
        if ($id < 1) jsonResponse(['error' => 'Valid task ID is required'], 422);

        if (array_key_exists('status', $input)) {
            $status = (string) $input['status'];
            if (!in_array($status, ['todo', 'in_progress', 'completed', 'archived'], true)) {
                jsonResponse(['error' => 'Invalid task status'], 422);
            }
            $existing = $pdo->prepare('SELECT status,title,priority,category FROM tasks WHERE id=:id AND user_id=:user_id');
            $existing->execute(['id' => $id, 'user_id' => $userId]);
            $task = $existing->fetch();
            $statement = $pdo->prepare(
                'UPDATE tasks SET status = :status,
                 completed_at = CASE WHEN :status = "completed" THEN COALESCE(completed_at, NOW()) ELSE NULL END
                 WHERE id = :id AND user_id = :user_id'
            );
            $statement->execute(['status' => $status, 'id' => $id, 'user_id' => $userId]);
            if (!$task) jsonResponse(['error' => 'Task not found'], 404);
            auditEvent($pdo,$userId,'task.status_changed','task',$id);
            if ($task && $task['status'] !== 'completed' && $status === 'completed') {
                executeAutomationRules($pdo, $userId, 'task_completed', [
                    'category' => (string) $task['category'],
                    'priority' => (string) $task['priority'],
                    'title' => (string) $task['title'],
                ]);
            }
        } elseif (array_key_exists('title', $input)) {
            $title = trim((string) $input['title']);
            if ($title === '') jsonResponse(['error' => 'Task title is required'], 422);
            if (mb_strlen($title) > 180) jsonResponse(['error' => 'Task title is too long'], 422);
            $existing = $pdo->prepare('SELECT id FROM tasks WHERE id=:id AND user_id=:user_id');
            $existing->execute(['id' => $id, 'user_id' => $userId]);
            if (!$existing->fetch()) jsonResponse(['error' => 'Task not found'], 404);
            $statement = $pdo->prepare('UPDATE tasks SET title = :title WHERE id = :id AND user_id = :user_id');
            $statement->execute(['title' => $title, 'id' => $id, 'user_id' => $userId]);
        } elseif (array_key_exists('due_at', $input)) {
            $dueAt = trim((string) $input['due_at']);
            $date = $dueAt === '' ? null : date_create($dueAt);
            if ($dueAt !== '' && $date === false) jsonResponse(['error' => 'Invalid due date'], 422);
            $statement = $pdo->prepare('UPDATE tasks SET due_at = :due_at, next_recurrence_at = :next_due_at WHERE id = :id AND user_id = :user_id');
            $value = $date ? $date->format('Y-m-d H:i:s') : null;
            $statement->execute(['due_at' => $value, 'next_due_at' => $value, 'id' => $id, 'user_id' => $userId]);
            if ($statement->rowCount() === 0) jsonResponse(['error' => 'Task not found'], 404);
        } elseif (array_key_exists('priority', $input)) {
            $priority = (string) $input['priority'];
            if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
                jsonResponse(['error' => 'Invalid task priority'], 422);
            }
            $existing = $pdo->prepare('SELECT title,priority,category FROM tasks WHERE id=:id AND user_id=:user_id');
            $existing->execute(['id' => $id, 'user_id' => $userId]);
            $task = $existing->fetch();
            if (!$task) jsonResponse(['error' => 'Task not found'], 404);
            $statement = $pdo->prepare('UPDATE tasks SET priority=:priority WHERE id=:id AND user_id=:user_id');
            $statement->execute(['priority' => $priority, 'id' => $id, 'user_id' => $userId]);
            auditEvent($pdo,$userId,'task.priority_changed','task',$id);
            if ($task && $task['priority'] !== $priority) {
                executeAutomationRules($pdo, $userId, 'priority_changed', [
                    'category' => (string) $task['category'],
                    'priority' => $priority,
                    'title' => (string) $task['title'],
                ]);
            }
        }
        jsonResponse(['message' => 'Task updated']);
    }

    if ($method === 'DELETE') {
        $id = (int) ($input['id'] ?? 0);
        if ($id < 1) jsonResponse(['error' => 'Valid task ID is required'], 422);
        $statement = $pdo->prepare('DELETE FROM tasks WHERE id = :id AND user_id = :user_id');
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        if ($statement->rowCount() !== 1) jsonResponse(['error' => 'Task not found'], 404);
        jsonResponse(['message' => 'Task deleted']);
    }

    jsonResponse(['error' => 'Method not allowed'], 405);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(['error' => 'Unable to process task request'], 500);
}
