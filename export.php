<?php

declare(strict_types=1);

session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: /personal-os/login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAuthenticatedSession();

$pdo = Database::getInstance()->connection();
$userId = (int) $_SESSION['user_id'];
$format = strtolower((string) ($_GET['format'] ?? 'json'));

$taskStatement = $pdo->prepare('SELECT id, title, description, status, priority, category, due_at, completed_at, created_at FROM tasks WHERE user_id = :user_id ORDER BY created_at DESC');
$taskStatement->execute(['user_id' => $userId]);
$tasks = $taskStatement->fetchAll();

$noteStatement = $pdo->prepare('SELECT id, title, content, color, is_pinned, created_at, updated_at FROM notes WHERE user_id = :user_id ORDER BY created_at DESC');
$noteStatement->execute(['user_id' => $userId]);
$notes = $noteStatement->fetchAll();

$filename = 'personal-os-' . date('Y-m-d');

$sanitizeCsv = static function ($value): mixed {
    if (!is_string($value)) return $value;
    $trimmed = ltrim($value);
    if ($trimmed !== '' && str_contains("=+-@\t\r", $trimmed[0])) return "'" . $value;
    return $value;
};

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '-tasks.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['id', 'title', 'description', 'status', 'priority', 'category', 'due_at', 'completed_at', 'created_at']);
    foreach ($tasks as $task) {
        fputcsv($output, array_map($sanitizeCsv, $task));
    }
    fclose($output);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.json"');
echo json_encode(['exported_at' => date(DATE_ATOM), 'tasks' => $tasks, 'notes' => $notes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
