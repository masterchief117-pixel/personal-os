<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json');

function notesJson(array $payload, int $status = 200): never
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
            'SELECT id, title, content, color, is_pinned, created_at, updated_at
             FROM notes WHERE user_id = :user_id
             ORDER BY is_pinned DESC, updated_at DESC'
        );
        $statement->execute(['user_id' => $userId]);
        notesJson(['notes' => $statement->fetchAll()]);
    }

    $rawInput = (string) file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        parse_str($rawInput, $input);
    }
    if (!is_array($input) || !$input) $input = $_POST;
    if ($method !== 'GET') requireCsrf($input);

    if ($method === 'POST') {
        $title = trim((string) ($input['title'] ?? 'Untitled'));
        $content = trim((string) ($input['content'] ?? ''));
        if (mb_strlen($title) > 180) notesJson(['error' => 'Note title is too long'], 422);
        $color = in_array($input['color'] ?? '', ['violet', 'amber', 'cyan', 'rose'], true)
            ? $input['color'] : 'violet';
        $statement = $pdo->prepare(
            'INSERT INTO notes (user_id, title, content, color, is_pinned)
             VALUES (:user_id, :title, :content, :color, :pinned)'
        );
        $statement->execute([
            'user_id' => $userId,
            'title' => $title !== '' ? $title : 'Untitled',
            'content' => $content,
            'color' => $color,
            'pinned' => !empty($input['is_pinned']) ? 1 : 0,
        ]);
        notesJson(['id' => (int) $pdo->lastInsertId(), 'message' => 'Note added'], 201);
    }

    if ($method === 'PATCH') {
        $id = (int) ($input['id'] ?? 0);
        if ($id < 1) notesJson(['error' => 'Valid note ID is required'], 422);
        $fields = [];
        $params = ['id' => $id, 'user_id' => $userId];
        if (array_key_exists('title', $input)) {
            $fields[] = 'title = :title';
            $params['title'] = trim((string) $input['title']);
        }
        if (array_key_exists('content', $input)) {
            $fields[] = 'content = :content';
            $params['content'] = trim((string) $input['content']);
        }
        if (array_key_exists('is_pinned', $input)) {
            $fields[] = 'is_pinned = :pinned';
            $params['pinned'] = !empty($input['is_pinned']) ? 1 : 0;
        }
        if (!$fields) notesJson(['error' => 'No note fields supplied'], 422);
        $statement = $pdo->prepare('UPDATE notes SET ' . implode(', ', $fields) . ' WHERE id = :id AND user_id = :user_id');
        $statement->execute($params);
        notesJson(['message' => 'Note updated']);
    }

    if ($method === 'DELETE') {
        $id = (int) ($input['id'] ?? 0);
        if ($id < 1) notesJson(['error' => 'Valid note ID is required'], 422);
        $statement = $pdo->prepare('DELETE FROM notes WHERE id = :id AND user_id = :user_id');
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        notesJson(['message' => 'Note deleted']);
    }

    notesJson(['error' => 'Method not allowed'], 405);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    notesJson(['error' => 'Unable to process note request'], 500);
}
