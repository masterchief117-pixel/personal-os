<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/functions.php';
requireAuthenticatedSession();

$rootPath = DIRECTORY_SEPARATOR === '\\'
    ? (getenv('SystemDrive') ?: 'C:') . '\\'
    : DIRECTORY_SEPARATOR;
$totalBytes = disk_total_space($rootPath);
$freeBytes = disk_free_space($rootPath);

if (!is_float($totalBytes) && !is_int($totalBytes) ||
    !is_float($freeBytes) && !is_int($freeBytes) ||
    $totalBytes <= 0 || $freeBytes < 0) {
    error_log('Unable to read disk capacity for ' . $rootPath);
    http_response_code(500);
    echo json_encode(['error' => 'System disk metrics are unavailable']);
    exit;
}

$usedPercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;
echo json_encode([
    'disk_total_bytes' => (int) $totalBytes,
    'disk_free_bytes' => (int) $freeBytes,
    'disk_used_percent' => round(max(0, min(100, $usedPercent)), 1),
    'php_version' => PHP_VERSION,
    'server_os' => php_uname(),
], JSON_UNESCAPED_SLASHES);
