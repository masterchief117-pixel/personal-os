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
$raw = (string) file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) parse_str($raw, $input);
if (!is_array($input)) $input = $_POST;

try {
    if ($method === 'GET') {
        $statement = $pdo->prepare(
            'SELECT w.id,w.name,w.owner_id,wm.role
             FROM workspaces w
             JOIN workspace_members wm ON wm.workspace_id=w.id
             WHERE wm.user_id=:user_id
             ORDER BY w.created_at DESC'
        );
        $statement->execute(['user_id' => $userId]);
        echo json_encode(['workspaces' => $statement->fetchAll()]);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    requireCsrf($input);
    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 100) {
        http_response_code(422);
        echo json_encode(['error' => 'Workspace name must contain 1-100 characters']);
        exit;
    }
    $pdo->beginTransaction();
    $create = $pdo->prepare('INSERT INTO workspaces (name,owner_id) VALUES (:name,:owner_id)');
    $create->execute(['name' => $name, 'owner_id' => $userId]);
    $workspaceId = (int) $pdo->lastInsertId();
    $member = $pdo->prepare('INSERT INTO workspace_members (workspace_id,user_id,role) VALUES (:workspace_id,:user_id,"owner")');
    $member->execute(['workspace_id' => $workspaceId, 'user_id' => $userId]);
    $pdo->commit();
    echo json_encode(['id' => $workspaceId, 'message' => 'Workspace created'], 201);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to process workspace']);
}
