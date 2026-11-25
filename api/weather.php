<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=300');

require_once __DIR__ . '/../includes/functions.php';
requireAuthenticatedSession();

// Configurable default coordinates (London coordinates by default, easily changed)
$defaultLatitude = 51.5074;
$defaultLongitude = -0.1278;

// Allow override from environment variables if present
$envFile = 'C:\\xampp\\.personal-os.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === 'WEATHER_LATITUDE' && is_numeric($value)) {
            $defaultLatitude = (float) $value;
        } elseif ($key === 'WEATHER_LONGITUDE' && is_numeric($value)) {
            $defaultLongitude = (float) $value;
        }
    }
}

// Allow override from query parameters ($_GET['latitude'] / $_GET['lat'], $_GET['longitude'] / $_GET['lon'])
$latitude = $_GET['latitude'] ?? $_GET['lat'] ?? $defaultLatitude;
$longitude = $_GET['longitude'] ?? $_GET['lon'] ?? $defaultLongitude;

$latitude = filter_var($latitude, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($longitude, FILTER_VALIDATE_FLOAT);

if ($latitude === false || $latitude < -90 || $latitude > 90 ||
    $longitude === false || $longitude < -180 || $longitude > 180) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid weather coordinates provided.'], JSON_UNESCAPED_SLASHES);
    exit;
}

// Build Open-Meteo API query
$apiUrl = 'https://api.open-meteo.com/v1/forecast?' . http_build_query([
    'latitude' => round($latitude, 4),
    'longitude' => round($longitude, 4),
    'current' => 'temperature_2m,weather_code,wind_speed_10m',
    'timezone' => 'auto',
]);

// Fetch weather data using cURL with fallback to file_get_contents
$body = false;
$statusCode = 0;
$errorMessage = '';

if (function_exists('curl_init')) {
    $curl = curl_init($apiUrl);
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: PersonalOS/1.0'],
    ];

    // Use local CA bundle if available
    $caPath = 'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt';
    if (file_exists($caPath)) {
        $curlOptions[CURLOPT_CAINFO] = $caPath;
    }

    curl_setopt_array($curl, $curlOptions);
    $body = curl_exec($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $errorMessage = curl_error($curl);
    curl_close($curl);
}

// Fallback to file_get_contents if cURL was unsuccessful
if ($body === false || $statusCode < 200 || $statusCode >= 300) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'header' => "Accept: application/json\r\nUser-Agent: PersonalOS/1.0\r\n",
        ],
    ]);
    $fileBody = @file_get_contents($apiUrl, false, $context);
    if ($fileBody !== false) {
        $body = $fileBody;
        $statusCode = 200;
        $errorMessage = '';
    }
}

$cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'personal_os_weather_cache.json';

if (!is_string($body) || $statusCode < 200 || $statusCode >= 300) {
    // Graceful offline fallback: serve latest cached weather data if available
    if (file_exists($cacheFile) && is_readable($cacheFile)) {
        $cachedData = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cachedData) && isset($cachedData['temperature_c'], $cachedData['conditions'])) {
            $cachedData['cached'] = true;
            echo json_encode($cachedData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            exit;
        }
    }
    error_log('Open-Meteo API error: ' . ($errorMessage !== '' ? $errorMessage : 'HTTP status ' . $statusCode));
    http_response_code(502);
    echo json_encode(['error' => 'Weather data is temporarily unavailable.'], JSON_UNESCAPED_SLASHES);
    exit;
}

$payload = json_decode($body, true);
$current = is_array($payload) ? ($payload['current'] ?? null) : null;

if (!is_array($current) ||
    !isset($current['temperature_2m'], $current['weather_code'], $current['wind_speed_10m']) ||
    !is_numeric($current['temperature_2m']) ||
    !is_numeric($current['weather_code']) ||
    !is_numeric($current['wind_speed_10m'])) {
    if (file_exists($cacheFile) && is_readable($cacheFile)) {
        $cachedData = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cachedData) && isset($cachedData['temperature_c'], $cachedData['conditions'])) {
            $cachedData['cached'] = true;
            echo json_encode($cachedData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            exit;
        }
    }
    http_response_code(502);
    echo json_encode(['error' => 'Invalid response format from weather provider.'], JSON_UNESCAPED_SLASHES);
    exit;
}

$weatherCode = (int) $current['weather_code'];

// Map WMO Weather Interpretation Codes to human-readable strings, icon classes, and emojis
$weatherMap = [
    0  => ['condition' => 'Clear',                 'icon' => 'clear',             'emoji' => '☀️'],
    1  => ['condition' => 'Mainly Clear',          'icon' => 'mainly-clear',      'emoji' => '🌤️'],
    2  => ['condition' => 'Partly Cloudy',         'icon' => 'partly-cloudy',     'emoji' => '⛅'],
    3  => ['condition' => 'Overcast',              'icon' => 'overcast',          'emoji' => '☁️'],
    45 => ['condition' => 'Fog',                   'icon' => 'fog',               'emoji' => '🌫️'],
    48 => ['condition' => 'Depositing Rime Fog',   'icon' => 'fog',               'emoji' => '🌫️'],
    51 => ['condition' => 'Light Drizzle',         'icon' => 'drizzle',           'emoji' => '🌦️'],
    53 => ['condition' => 'Moderate Drizzle',      'icon' => 'drizzle',           'emoji' => '🌦️'],
    55 => ['condition' => 'Dense Drizzle',         'icon' => 'drizzle',           'emoji' => '🌧️'],
    56 => ['condition' => 'Light Freezing Drizzle','icon' => 'freezing-drizzle',  'emoji' => '🌨️'],
    57 => ['condition' => 'Dense Freezing Drizzle','icon' => 'freezing-drizzle',  'emoji' => '🌨️'],
    61 => ['condition' => 'Slight Rain',           'icon' => 'rain',              'emoji' => '🌧️'],
    63 => ['condition' => 'Moderate Rain',         'icon' => 'rain',              'emoji' => '🌧️'],
    65 => ['condition' => 'Heavy Rain',            'icon' => 'rain',              'emoji' => '🌧️'],
    66 => ['condition' => 'Light Freezing Rain',   'icon' => 'freezing-rain',     'emoji' => '🌧️'],
    67 => ['condition' => 'Heavy Freezing Rain',   'icon' => 'freezing-rain',     'emoji' => '🌧️'],
    71 => ['condition' => 'Slight Snow',           'icon' => 'snow',              'emoji' => '❄️'],
    73 => ['condition' => 'Moderate Snow',         'icon' => 'snow',              'emoji' => '❄️'],
    75 => ['condition' => 'Heavy Snow',            'icon' => 'snow',              'emoji' => '❄️'],
    77 => ['condition' => 'Snow Grains',           'icon' => 'snow',              'emoji' => '❄️'],
    80 => ['condition' => 'Slight Rain Showers',   'icon' => 'rain-showers',      'emoji' => '🌦️'],
    81 => ['condition' => 'Moderate Rain Showers', 'icon' => 'rain-showers',      'emoji' => '🌧️'],
    82 => ['condition' => 'Violent Rain Showers',  'icon' => 'rain-showers',      'emoji' => '⛈️'],
    85 => ['condition' => 'Slight Snow Showers',   'icon' => 'snow-showers',      'emoji' => '🌨️'],
    86 => ['condition' => 'Heavy Snow Showers',    'icon' => 'snow-showers',      'emoji' => '🌨️'],
    95 => ['condition' => 'Thunderstorm',          'icon' => 'thunderstorm',      'emoji' => '⛈️'],
    96 => ['condition' => 'Thunderstorm with Hail','icon' => 'thunderstorm-hail', 'emoji' => '⛈️'],
    99 => ['condition' => 'Heavy Thunderstorm',    'icon' => 'thunderstorm-hail', 'emoji' => '⛈️'],
];

$mapped = $weatherMap[$weatherCode] ?? ['condition' => 'Partly Cloudy', 'icon' => 'partly-cloudy', 'emoji' => '🌤️'];

$responseData = [
    'location' => (string) ($payload['timezone'] ?? 'Local Weather'),
    'latitude' => $latitude,
    'longitude' => $longitude,
    'temperature_c' => round((float) $current['temperature_2m'], 1),
    'weather_code' => $weatherCode,
    'conditions' => $mapped['condition'],
    'icon' => $mapped['icon'],
    'emoji' => $mapped['emoji'],
    'wind_speed_kmh' => round((float) $current['wind_speed_10m'], 1),
    'observed_at' => (string) ($current['time'] ?? ''),
    'cached' => false,
];

@file_put_contents($cacheFile, json_encode($responseData, JSON_UNESCAPED_SLASHES));

echo json_encode($responseData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
