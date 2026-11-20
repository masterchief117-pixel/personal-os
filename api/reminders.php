<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuthenticatedSession();

$pdo = Database::getInstance()->connection();
$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawInput = (string) file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) parse_str($rawInput, $input);
if (!is_array($input)) $input = $_POST;
if ($method !== 'GET') requireCsrf($input);

try {
    if ($method === 'GET') {
        $statement = $pdo->prepare(
            'SELECT r.id, r.title, r.remind_at, r.is_sent, r.task_id
             FROM reminders r
             WHERE r.user_id = :user_id
             ORDER BY r.remind_at ASC'
        );
        $statement->execute(['user_id' => $userId]);
        echo json_encode(['reminders' => $statement->fetchAll()]);
        exit;
    }

    if ($method === 'PATCH') {
        $id = (int) ($input['id'] ?? 0);
        if ($id < 1) {
            http_response_code(422);
            echo json_encode(['error' => 'Valid reminder ID is required']);
            exit;
        }
        $owned = $pdo->prepare('SELECT id FROM reminders WHERE id=:id AND user_id=:user_id');
        $owned->execute(['id' => $id, 'user_id' => $userId]);
        if (!$owned->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Reminder not found']);
            exit;
        }
        $statement = $pdo->prepare('UPDATE reminders SET is_sent=TRUE WHERE id=:id AND user_id=:user_id');
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        echo json_encode(['message' => 'Reminder marked delivered']);
        exit;
    }

    if ($method === 'POST') {
        $title = trim((string) ($input['title'] ?? ''));
        $remindAt = trim((string) ($input['remind_at'] ?? ''));
        $taskId = (int) ($input['task_id'] ?? 0);
        $date = date_create($remindAt);
        if ($title === '' || $date === false) {
            http_response_code(422);
            echo json_encode(['error' => 'Title and valid reminder time are required']);
            exit;
        }
        if (mb_strlen($title) > 180) {
            http_response_code(422);
            echo json_encode(['error' => 'Reminder title is too long']);
            exit;
        }
        $statement = $pdo->prepare(
            'INSERT INTO reminders (user_id, task_id, title, remind_at)
             VALUES (:user_id, :task_id, :title, :remind_at)'
        );
        $statement->execute([
            'user_id' => $userId,
            'task_id' => $taskId > 0 ? $taskId : null,
            'title' => $title,
            'remind_at' => $date->format('Y-m-d H:i:s'),
        ]);
        echo json_encode(['id' => (int) $pdo->lastInsertId(), 'message' => 'Reminder added'], 201);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to process reminder']);
}
