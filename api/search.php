<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuthenticatedSession();

function searchJsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    searchJsonResponse(['error' => 'Method not allowed'], 405);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    searchJsonResponse(['error' => 'Invalid search request'], 400);
}
requireCsrf($input);

$rawQuery = $input['query'] ?? '';
if (!is_string($rawQuery)) {
    searchJsonResponse(['error' => 'Search query must be text'], 422);
}
$query = trim($rawQuery);
if (mb_strlen($query) > 100) {
    searchJsonResponse(['error' => 'Search query is too long'], 422);
}
if (mb_strlen($query) < 2) {
    searchJsonResponse(['results' => []]);
}

$escapedQuery = strtr($query, ['!' => '!!', '%' => '!%', '_' => '!_']);
$pattern = '%' . $escapedQuery . '%';
$prefix = $escapedQuery . '%';
$userId = (int) $_SESSION['user_id'];

try {
    $pdo = Database::getInstance()->connection();
    $results = [];

    $tasks = $pdo->prepare(
        "SELECT id, title, category, status
         FROM tasks
         WHERE user_id = :user_id AND status <> 'archived'
           AND (title LIKE :title_pattern ESCAPE '!'
             OR description LIKE :description_pattern ESCAPE '!'
             OR category LIKE :category_pattern ESCAPE '!')
         ORDER BY (title = :exact_title) DESC,
                  (title LIKE :prefix_title ESCAPE '!') DESC,
                  updated_at DESC
         LIMIT 4"
    );
    $tasks->execute([
        'user_id' => $userId,
        'title_pattern' => $pattern,
        'description_pattern' => $pattern,
        'category_pattern' => $pattern,
        'exact_title' => $query,
        'prefix_title' => $prefix,
    ]);
    foreach ($tasks->fetchAll() as $row) {
        $results[] = [
            'id' => 'task-' . (int) $row['id'],
            'type' => 'task',
            'title' => (string) $row['title'],
            'detail' => trim((string) $row['category'] . ' · ' . (string) $row['status']),
            'href' => '/personal-os/?focus_task=' . (int) $row['id'],
        ];
    }

    $notes = $pdo->prepare(
        "SELECT id, title, content
         FROM notes
         WHERE user_id = :user_id
           AND (title LIKE :title_pattern ESCAPE '!'
             OR content LIKE :content_pattern ESCAPE '!')
         ORDER BY (title = :exact_title) DESC,
                  (title LIKE :prefix_title ESCAPE '!') DESC,
                  updated_at DESC
         LIMIT 4"
    );
    $notes->execute([
        'user_id' => $userId,
        'title_pattern' => $pattern,
        'content_pattern' => $pattern,
        'exact_title' => $query,
        'prefix_title' => $prefix,
    ]);
    foreach ($notes->fetchAll() as $row) {
        $content = preg_replace('/\s+/u', ' ', (string) $row['content']) ?? '';
        $results[] = [
            'id' => 'note-' . (int) $row['id'],
            'type' => 'note',
            'title' => (string) $row['title'],
            'detail' => mb_substr(trim($content), 0, 96) ?: 'Note',
            'href' => '/personal-os/?focus_note=' . (int) $row['id'],
        ];
    }

    $goals = $pdo->prepare(
        "SELECT id, title, status, target_date
         FROM goals
         WHERE user_id = :user_id AND status <> 'archived'
           AND (title LIKE :title_pattern ESCAPE '!'
             OR description LIKE :description_pattern ESCAPE '!')
         ORDER BY (title = :exact_title) DESC,
                  (title LIKE :prefix_title ESCAPE '!') DESC,
                  updated_at DESC
         LIMIT 4"
    );
    $goals->execute([
        'user_id' => $userId,
        'title_pattern' => $pattern,
        'description_pattern' => $pattern,
        'exact_title' => $query,
        'prefix_title' => $prefix,
    ]);
    foreach ($goals->fetchAll() as $row) {
        $detail = (string) $row['status'];
        if (!empty($row['target_date'])) {
            $detail .= ' · due ' . date('M j, Y', strtotime((string) $row['target_date']));
        }
        $results[] = [
            'id' => 'goal-' . (int) $row['id'],
            'type' => 'goal',
            'title' => (string) $row['title'],
            'detail' => $detail,
            'href' => '/personal-os/goals.php#goal-' . (int) $row['id'],
        ];
    }

    $habits = $pdo->prepare(
        "SELECT id, name, recurrence
         FROM habits
         WHERE user_id = :user_id AND is_active = TRUE
           AND name LIKE :name_pattern ESCAPE '!'
         ORDER BY (name = :exact_name) DESC,
                  (name LIKE :prefix_name ESCAPE '!') DESC,
                  created_at DESC
         LIMIT 4"
    );
    $habits->execute([
        'user_id' => $userId,
        'name_pattern' => $pattern,
        'exact_name' => $query,
        'prefix_name' => $prefix,
    ]);
    foreach ($habits->fetchAll() as $row) {
        $results[] = [
            'id' => 'habit-' . (int) $row['id'],
            'type' => 'habit',
            'title' => (string) $row['name'],
            'detail' => ucfirst((string) $row['recurrence']) . ' habit',
            'href' => '/personal-os/habits.php#habit-' . (int) $row['id'],
        ];
    }

    $journal = $pdo->prepare(
        "SELECT id, entry_date, mood
         FROM journal_entries
         WHERE user_id = :user_id
           AND (CAST(entry_date AS CHAR) LIKE :date_pattern ESCAPE '!'
             OR mood LIKE :mood_pattern ESCAPE '!'
             OR COALESCE(gratitude, '') LIKE :gratitude_pattern ESCAPE '!'
             OR COALESCE(wins, '') LIKE :wins_pattern ESCAPE '!'
             OR COALESCE(reflection, '') LIKE :reflection_pattern ESCAPE '!')
         ORDER BY (CAST(entry_date AS CHAR) = :exact_date) DESC, entry_date DESC
         LIMIT 4"
    );
    $journal->execute([
        'user_id' => $userId,
        'date_pattern' => $pattern,
        'mood_pattern' => $pattern,
        'gratitude_pattern' => $pattern,
        'wins_pattern' => $pattern,
        'reflection_pattern' => $pattern,
        'exact_date' => $query,
    ]);
    foreach ($journal->fetchAll() as $row) {
        $entryDate = (string) $row['entry_date'];
        $results[] = [
            'id' => 'journal-' . (int) $row['id'],
            'type' => 'journal',
            'title' => 'Journal · ' . date('M j, Y', strtotime($entryDate)),
            'detail' => ucfirst((string) $row['mood']) . ' · private entry',
            'href' => '/personal-os/journal.php?date=' . rawurlencode($entryDate),
        ];
    }

    searchJsonResponse(['results' => $results]);
} catch (Throwable $exception) {
    error_log('Workspace search failed: ' . $exception->getMessage());
    searchJsonResponse(['error' => 'Unable to search workspace records'], 500);
}
