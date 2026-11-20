<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=300');

require_once __DIR__ . '/../includes/functions.php';
requireAuthenticatedSession();

$coordinates = [
    'latitude' => 51.5072,
    'longitude' => -0.1276,
];
$envFile = 'C:\\xampp\\.personal-os.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === 'WEATHER_LATITUDE' || $key === 'WEATHER_LONGITUDE') {
            $coordinates[$key === 'WEATHER_LATITUDE' ? 'latitude' : 'longitude'] = $value;
        }
    }
}

$latitude = filter_var($coordinates['latitude'], FILTER_VALIDATE_FLOAT);
$longitude = filter_var($coordinates['longitude'], FILTER_VALIDATE_FLOAT);
if ($latitude === false || $latitude < -90 || $latitude > 90 ||
    $longitude === false || $longitude < -180 || $longitude > 180) {
    http_response_code(500);
    echo json_encode(['error' => 'Weather coordinates are invalid']);
    exit;
}

$url = 'https://api.open-meteo.com/v1/forecast?' . http_build_query([
    'latitude' => $latitude,
    'longitude' => $longitude,
    'current' => 'temperature_2m,weather_code,wind_speed_10m',
    'timezone' => 'auto',
]);

$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
]);
$body = curl_exec($curl);
$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
$curlError = curl_error($curl);
curl_close($curl);

if (!is_string($body) || $status < 200 || $status >= 300) {
    error_log('Open-Meteo request failed: ' . ($curlError !== '' ? $curlError : 'HTTP ' . $status));
    http_response_code(502);
    echo json_encode(['error' => 'Weather data is temporarily unavailable']);
    exit;
}

$weather = json_decode($body, true);
$current = is_array($weather) ? ($weather['current'] ?? null) : null;
if (!is_array($current) ||
    !is_numeric($current['temperature_2m'] ?? null) ||
    !is_numeric($current['weather_code'] ?? null) ||
    !is_numeric($current['wind_speed_10m'] ?? null)) {
    http_response_code(502);
    echo json_encode(['error' => 'Weather provider returned an invalid response']);
    exit;
}

$code = (int) $current['weather_code'];
$conditions = match (true) {
    $code === 0 => 'Clear',
    in_array($code, [1, 2], true) => 'Partly cloudy',
    $code === 3 => 'Overcast',
    in_array($code, [45, 48], true) => 'Fog',
    in_array($code, [51, 53, 55, 56, 57], true) => 'Drizzle',
    in_array($code, [61, 63, 65, 66, 67, 80, 81, 82], true) => 'Rain',
    in_array($code, [71, 73, 75, 77, 85, 86], true) => 'Snow',
    in_array($code, [95, 96, 99], true) => 'Thunderstorm',
    default => 'Unknown',
};

echo json_encode([
    'location' => (string) ($weather['timezone'] ?? 'Local weather'),
    'temperature_c' => round((float) $current['temperature_2m'], 1),
    'weather_code' => $code,
    'conditions' => $conditions,
    'wind_speed_kmh' => round((float) $current['wind_speed_10m'], 1),
    'observed_at' => (string) ($current['time'] ?? ''),
], JSON_UNESCAPED_SLASHES);
