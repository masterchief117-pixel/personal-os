<?php

declare(strict_types=1);

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function verifyCsrfToken(array $input): bool
{
    $supplied = (string) ($input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $stored = (string) ($_SESSION['csrf_token'] ?? '');
    return $stored !== '' && $supplied !== '' && hash_equals($stored, $supplied);
}

function requireCsrf(array $input): void
{
    if (!verifyCsrfToken($input)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid or missing CSRF token']);
        exit;
    }
}

function auditEvent(PDO $pdo, ?int $userId, string $action, string $entityType, ?int $entityId = null): void
{
    $statement = $pdo->prepare(
        'INSERT INTO audit_events (user_id,action,entity_type,entity_id,ip_address)
         VALUES (:user_id,:action,:entity_type,:entity_id,:ip_address)'
    );
    $statement->execute([
        'user_id' => $userId,
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function executeAutomationRules(
    PDO $pdo,
    int $userId,
    string $triggerType,
    array $context = []
): int
{
    $statement = $pdo->prepare(
        'SELECT id,name,trigger_value,action_type,action_value
         FROM automation_rules
         WHERE user_id=:user_id AND is_enabled=TRUE AND trigger_type=:trigger_type'
    );
    $statement->execute(['user_id' => $userId, 'trigger_type' => $triggerType]);
    $rules = $statement->fetchAll();

    $createdTask = $pdo->prepare(
        'INSERT INTO tasks (user_id,title,description,status,priority,category)
         VALUES (:user_id,:title,"","todo","medium","Personal")'
    );
    $createdReminder = $pdo->prepare(
        'INSERT INTO reminders (user_id,title,remind_at)
         VALUES (:user_id,:title,DATE_ADD(NOW(),INTERVAL 1 HOUR))'
    );
    $processed = 0;

    foreach ($rules as $rule) {
        $triggerValue = strtolower(trim((string) $rule['trigger_value']));
        $category = strtolower(trim((string) ($context['category'] ?? '')));
        $priority = strtolower(trim((string) ($context['priority'] ?? '')));
        $title = strtolower(trim((string) ($context['title'] ?? '')));

        if ($triggerValue !== '') {
            $matches = in_array($triggerValue, [$category, $priority, $title], true);
            if (!$matches) continue;
        }

        $actionValue = trim((string) ($rule['action_value'] ?? ''));
        if ($rule['action_type'] === 'create_task') {
            $createdTask->execute([
                'user_id' => $userId,
                'title' => substr($actionValue !== '' ? $actionValue : (string) $rule['name'], 0, 180),
            ]);
        } elseif ($rule['action_type'] === 'send_reminder') {
            $createdReminder->execute([
                'user_id' => $userId,
                'title' => substr($actionValue !== '' ? $actionValue : (string) $rule['name'], 0, 180),
            ]);
        } elseif ($rule['action_type'] === 'mark_review') {
            $createdTask->execute([
                'user_id' => $userId,
                'title' => substr($actionValue !== '' ? $actionValue : 'Weekly review', 0, 180),
            ]);
        } else {
            continue;
        }

        auditEvent(
            $pdo,
            $userId,
            'automation.executed',
            'automation',
            (int) $rule['id']
        );
        $processed++;
    }

    return $processed;
}

function requireAuthenticatedSession(int $timeoutSeconds = 28800): ?int
{
    if (!isset($_SESSION['authenticated'], $_SESSION['user_id']) || $_SESSION['authenticated'] !== true) {
        http_response_code(401);
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/personal-os/api/')) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentication required']);
        } else {
            header('Location: /personal-os/login.php');
        }
        exit;
    }
    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
    if ($lastActivity > 0 && time() - $lastActivity > $timeoutSeconds) {
        $_SESSION = [];
        session_destroy();
        http_response_code(401);
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/personal-os/api/')) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Session expired']);
        } else {
            header('Location: /personal-os/login.php?timeout=1');
        }
        exit;
    }
    $_SESSION['last_activity'] = time();
    return (int) $_SESSION['user_id'];
}

function getTasks(PDO $pdo, int $userId = 1, int $limit = 20): array
{
    $statement = $pdo->prepare(
        'SELECT id, title, description, status, priority, category, due_at, completed_at
         FROM tasks
         WHERE user_id = :user_id AND status != "archived"
         ORDER BY
           FIELD(status, "in_progress", "todo", "completed"),
           FIELD(priority, "urgent", "high", "medium", "low"),
           due_at ASC
         LIMIT :limit'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function getRecentNotes(PDO $pdo, int $userId = 1, int $limit = 8): array
{
    $statement = $pdo->prepare(
        'SELECT id, title, content, color, is_pinned, created_at, updated_at
         FROM notes
         WHERE user_id = :user_id
         ORDER BY is_pinned DESC, updated_at DESC
         LIMIT :limit'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function getSystemStats(PDO $pdo, int $userId = 1): array
{
    $taskStatement = $pdo->prepare(
        'SELECT
           COUNT(*) AS total_tasks,
           SUM(status = "completed") AS completed_tasks,
           SUM(status = "in_progress") AS active_tasks,
           SUM(status = "todo") AS pending_tasks
         FROM tasks
         WHERE user_id = :user_id'
    );
    $taskStatement->execute(['user_id' => $userId]);
    $tasks = $taskStatement->fetch();

    $noteStatement = $pdo->prepare('SELECT COUNT(*) AS total_notes FROM notes WHERE user_id = :user_id');
    $noteStatement->execute(['user_id' => $userId]);
    $notes = $noteStatement->fetch();

    $logStatement = $pdo->prepare(
        'SELECT COUNT(*) AS total_logs,
                SUM(level = "warning") AS warning_logs,
                SUM(level IN ("error", "critical")) AS error_logs
         FROM system_logs
         WHERE user_id = :user_id OR user_id IS NULL'
    );
    $logStatement->execute(['user_id' => $userId]);
    $logs = $logStatement->fetch();

    $totalTasks = (int) ($tasks['total_tasks'] ?? 0);
    $completedTasks = (int) ($tasks['completed_tasks'] ?? 0);

    return [
        'total_tasks' => $totalTasks,
        'completed_tasks' => $completedTasks,
        'active_tasks' => (int) ($tasks['active_tasks'] ?? 0),
        'pending_tasks' => (int) ($tasks['pending_tasks'] ?? 0),
        'total_notes' => (int) ($notes['total_notes'] ?? 0),
        'total_logs' => (int) ($logs['total_logs'] ?? 0),
        'warning_logs' => (int) ($logs['warning_logs'] ?? 0),
        'error_logs' => (int) ($logs['error_logs'] ?? 0),
        'completion_rate' => $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0,
    ];
}

function getRecentSystemLogs(PDO $pdo, int $userId = 1, int $limit = 5): array
{
    $statement = $pdo->prepare(
        'SELECT event_type, level, message, created_at
         FROM system_logs
         WHERE user_id = :user_id OR user_id IS NULL
         ORDER BY created_at DESC
         LIMIT :limit'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function getCurrentUser(PDO $pdo, int $userId = 1): array
{
    $statement = $pdo->prepare(
        'SELECT id, name, email, role, avatar_url, timezone FROM users WHERE id = :user_id'
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetch() ?: [
        'id' => 1,
        'name' => 'Alex Morgan',
        'email' => '',
        'role' => 'admin',
        'avatar_url' => '',
        'timezone' => 'Europe/Istanbul',
    ];
}

function getGoals(PDO $pdo, int $userId = 1): array
{
    $statement = $pdo->prepare(
        'SELECT id,title,description,target_value,current_value,unit,target_date,status,created_at
         FROM goals WHERE user_id=:user_id AND status!="archived"
         ORDER BY FIELD(status,"active","paused","completed"), target_date IS NULL, target_date ASC'
    );
    $statement->execute(['user_id' => $userId]);
    return $statement->fetchAll();
}

function getJournalSummary(PDO $pdo, int $userId = 1): array
{
    $statement = $pdo->prepare(
        'SELECT entry_date,mood,energy,gratitude,reflection,wins
         FROM journal_entries WHERE user_id=:user_id
         ORDER BY entry_date DESC LIMIT 14'
    );
    $statement->execute(['user_id' => $userId]);
    $entries = $statement->fetchAll();
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $todayEntry = null;
    foreach ($entries as $entry) {
        if ($entry['entry_date'] === $today) {
            $todayEntry = $entry;
            break;
        }
    }
    $streak = 0;
    $cursor = new DateTimeImmutable('today');
    $entryDates = array_column($entries, 'entry_date');
    while (in_array($cursor->format('Y-m-d'), $entryDates, true)) {
        $streak++;
        $cursor = $cursor->modify('-1 day');
    }
    return ['entries' => $entries, 'today' => $todayEntry, 'streak' => $streak];
}

function getFocusSummary(PDO $pdo, int $userId = 1): array
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) AS session_count,
                COALESCE(SUM(status="completed"),0) AS completed_count,
                COALESCE(SUM(CASE WHEN status="completed" THEN duration_minutes ELSE 0 END),0) AS focus_minutes
         FROM focus_sessions WHERE user_id=:user_id'
    );
    $statement->execute(['user_id' => $userId]);
    $summary = $statement->fetch();
    $today = $pdo->prepare(
        'SELECT COUNT(*) FROM focus_sessions
         WHERE user_id=:user_id AND DATE(started_at)=CURDATE() AND status="completed"'
    );
    $today->execute(['user_id' => $userId]);
    return [
        'sessions' => (int)($summary['session_count'] ?? 0),
        'completed' => (int)($summary['completed_count'] ?? 0),
        'minutes' => (int)($summary['focus_minutes'] ?? 0),
        'today' => (int)$today->fetchColumn(),
    ];
}

function getWeeklyTaskActivity(PDO $pdo, int $userId = 1): array
{
    $statement = $pdo->prepare(
        'SELECT DATE(created_at) AS day_key, COUNT(*) AS created_count,
                SUM(status = "completed") AS completed_count
         FROM tasks
         WHERE user_id = :user_id
           AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(created_at)
         ORDER BY day_key'
    );
    $statement->execute(['user_id' => $userId]);
    $rows = $statement->fetchAll();
    $values = [];
    for ($offset = 6; $offset >= 0; $offset--) {
        $date = new DateTimeImmutable("-{$offset} days");
        $key = $date->format('Y-m-d');
        $row = array_values(array_filter($rows, static fn(array $item): bool => $item['day_key'] === $key))[0] ?? null;
        $values[] = [
            'label' => $date->format('D'),
            'created' => (int) ($row['created_count'] ?? 0),
            'completed' => (int) ($row['completed_count'] ?? 0),
        ];
    }
    return $values;
}
