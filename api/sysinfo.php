<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/functions.php';
requireAuthenticatedSession();

// Determine system root drive/directory for disk space measurement
$rootPath = DIRECTORY_SEPARATOR === '\\'
    ? (getenv('SystemDrive') ?: 'C:') . '\\'
    : DIRECTORY_SEPARATOR;

$totalBytes = @disk_total_space($rootPath);
$freeBytes = @disk_free_space($rootPath);

if (!is_numeric($totalBytes) || !is_numeric($freeBytes) || $totalBytes <= 0 || $freeBytes < 0) {
    // Fallback to project directory if root drive cannot be directly queried
    $rootPath = __DIR__;
    $totalBytes = disk_total_space($rootPath);
    $freeBytes = disk_free_space($rootPath);
}

if (!is_numeric($totalBytes) || !is_numeric($freeBytes) || $totalBytes <= 0 || $freeBytes < 0) {
    error_log('Unable to read disk capacity for ' . $rootPath);
    http_response_code(500);
    echo json_encode(['error' => 'System disk metrics are unavailable.'], JSON_UNESCAPED_SLASHES);
    exit;
}

$totalBytes = (float) $totalBytes;
$freeBytes = (float) $freeBytes;
$usedBytes = max(0.0, $totalBytes - $freeBytes);
$usedPercent = ($usedBytes / $totalBytes) * 100.0;
$usedPercent = max(0.0, min(100.0, round($usedPercent, 1)));

// Convert bytes to gigabytes (1 GB = 1024^3 bytes)
$bytesPerGb = 1024 ** 3;
$totalGb = round($totalBytes / $bytesPerGb, 1);
$freeGb = round($freeBytes / $bytesPerGb, 1);
$usedGb = round($usedBytes / $bytesPerGb, 1);

echo json_encode([
    'disk_root' => $rootPath,
    'disk_total_bytes' => (int) $totalBytes,
    'disk_free_bytes' => (int) $freeBytes,
    'disk_used_bytes' => (int) $usedBytes,
    'disk_total_gb' => $totalGb,
    'disk_free_gb' => $freeGb,
    'disk_used_gb' => $usedGb,
    'disk_used_percent' => $usedPercent,
    'disk_usage_formatted' => "{$freeGb} GB free / {$totalGb} GB total",
    'memory_usage_mb' => round(memory_get_usage(true) / (1024 * 1024), 2),
    'memory_peak_mb' => round(memory_get_peak_usage(true) / (1024 * 1024), 2),
    'php_version' => PHP_VERSION,
    'server_os' => php_uname(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'PHP/' . PHP_VERSION,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
