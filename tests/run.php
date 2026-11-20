<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$tests = [];

function test(string $name, callable $callback): void
{
    global $tests;
    try {
        $callback();
        $tests[] = [$name, true, null];
    } catch (Throwable $exception) {
        $tests[] = [$name, false, $exception->getMessage()];
    }
}

test('database connection', function (): void {
    $pdo = Database::getInstance()->connection();
    if ($pdo->query('SELECT 1')->fetchColumn() != 1) throw new RuntimeException('Expected SELECT 1');
});

test('seeded users exist', function (): void {
    $pdo = Database::getInstance()->connection();
    if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() < 1) throw new RuntimeException('No users');
});

test('seeded tasks exist', function (): void {
    $pdo = Database::getInstance()->connection();
    if ((int) $pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn() < 1) throw new RuntimeException('No tasks');
});

test('seeded notes exist', function (): void {
    $pdo = Database::getInstance()->connection();
    if ((int) $pdo->query('SELECT COUNT(*) FROM notes')->fetchColumn() < 1) throw new RuntimeException('No notes');
});

$failed = 0;
foreach ($tests as [$name, $passed, $error]) {
    echo ($passed ? 'PASS' : 'FAIL') . ' ' . $name;
    if ($error) echo ': ' . $error;
    echo PHP_EOL;
    if (!$passed) $failed++;
}

echo 'SUMMARY ' . (count($tests) - $failed) . '/' . count($tests) . ' passed' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
