<?php

declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$loginTimeout = 60 * 60 * 8;
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: /personal-os/login.php');
    exit;
}
if (isset($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > $loginTimeout) {
    session_unset();
    session_destroy();
    header('Location: /personal-os/login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

$userId = (int) ($_SESSION['user_id'] ?? 1);
$pageTitle = 'Personal OS';
$errorMessage = null;

try {
    $pdo = Database::getInstance()->connection();
    $user = getCurrentUser($pdo, $userId);
    $tasks = getTasks($pdo, $userId);
    $requestedTaskId = isset($_GET['focus_task']) && is_string($_GET['focus_task'])
        ? filter_var($_GET['focus_task'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    if ($requestedTaskId !== false) {
        $focusedTask = $pdo->prepare(
            "SELECT id, title, description, status, priority, category, due_at, completed_at
             FROM tasks
             WHERE id = :id AND user_id = :user_id AND status <> 'archived'"
        );
        $focusedTask->execute(['id' => $requestedTaskId, 'user_id' => $userId]);
        $focusedTaskRow = $focusedTask->fetch();
        $taskIsLoaded = false;
        foreach ($tasks as $task) {
            if ((int) $task['id'] === $requestedTaskId) {
                $taskIsLoaded = true;
                break;
            }
        }
        if ($focusedTaskRow && !$taskIsLoaded) {
            $tasks[] = $focusedTaskRow;
        }
    }
    $notes = getRecentNotes($pdo, $userId);
    $requestedNoteId = isset($_GET['focus_note']) && is_string($_GET['focus_note'])
        ? filter_var($_GET['focus_note'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    if ($requestedNoteId !== false) {
        $focusedNote = $pdo->prepare(
            'SELECT id, title, content, color, is_pinned, created_at, updated_at
             FROM notes
             WHERE id = :id AND user_id = :user_id'
        );
        $focusedNote->execute(['id' => $requestedNoteId, 'user_id' => $userId]);
        $focusedNoteRow = $focusedNote->fetch();
        $noteIsLoaded = false;
        foreach ($notes as $note) {
            if ((int) $note['id'] === $requestedNoteId) {
                $noteIsLoaded = true;
                break;
            }
        }
        if ($focusedNoteRow && !$noteIsLoaded) {
            $notes[] = $focusedNoteRow;
        }
    }
    $stats = getSystemStats($pdo, $userId);
    $logs = getRecentSystemLogs($pdo, $userId);
    $weeklyActivity = getWeeklyTaskActivity($pdo, $userId);
    $goals = getGoals($pdo, $userId);
    $journal = getJournalSummary($pdo, $userId);
    $focusSummary = getFocusSummary($pdo, $userId);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $errorMessage = 'Personal OS could not connect to MySQL. Import database/schema.sql and confirm XAMPP MySQL is running.';
    $user = [
        'name' => 'Alex Morgan',
        'email' => '',
        'role' => 'admin',
        'avatar_url' => '',
        'timezone' => 'Europe/Istanbul',
    ];
    $tasks = [];
    $notes = [];
    $stats = [
        'total_tasks' => 0,
        'completed_tasks' => 0,
        'active_tasks' => 0,
        'pending_tasks' => 0,
        'total_notes' => 0,
        'total_logs' => 0,
        'warning_logs' => 0,
        'error_logs' => 0,
        'completion_rate' => 0,
    ];
    $logs = [];
    $weeklyActivity = [];
    $goals = [];
    $journal = ['entries' => [], 'today' => null, 'streak' => 0];
    $focusSummary = ['sessions' => 0, 'completed' => 0, 'minutes' => 0, 'today' => 0];
}

require __DIR__ . '/views/dashboard.php';
