<?php

declare(strict_types=1);

$baseUrl = 'https://127.0.0.1/personal-os';
$caBundle = 'C:/xampp/apache/conf/ssl.crt/personal-os-ca.crt';
$sslContext = stream_context_create([
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'cafile' => $caBundle,
    ],
]);
$checks = [
    'login page' => '/login.php',
    'setup page' => '/setup.php',
    'schema protected' => '/database/schema.sql',
    'config protected' => '/config/db.php',
    'manifest' => '/manifest.webmanifest',
    'service worker' => '/sw.js',
    'offline shell' => '/offline.html',
];

$failed = 0;
foreach ($checks as $name => $path) {
    $headers = get_headers($baseUrl . $path, true, $sslContext);
    $status = 0;
    if (is_array($headers) && preg_match('/\s([0-9]{3})\s/', (string) ($headers[0] ?? ''), $matches)) {
        $status = (int) $matches[1];
    }
    $expected = str_contains($name, 'protected') ? 403 : 200;
    $passed = $status === $expected;
    echo ($passed ? 'PASS ' : 'FAIL ') . $name . ' HTTP ' . $status . PHP_EOL;
    if (!$passed) $failed++;
}

$cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'personal-os-qa-cookie.txt';
@unlink($cookieFile);
$login = curl_init($baseUrl . '/login.php');
curl_setopt_array($login, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['email' => 'qa@example.invalid', 'password' => 'invalid']),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CAINFO => $caBundle,
]);
$loginHtml = (string) curl_exec($login);
$loginCode = (int) curl_getinfo($login, CURLINFO_HTTP_CODE);
curl_close($login);
$invalidLoginPassed = $loginCode === 200;
echo ($invalidLoginPassed ? 'PASS ' : 'FAIL ') . 'login endpoint responds securely' . PHP_EOL;
if (!$invalidLoginPassed) $failed++;

$redirect = curl_init('http://127.0.0.1/personal-os/login.php');
curl_setopt_array($redirect, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
]);
$redirectBody = (string) curl_exec($redirect);
$redirectCode = (int) curl_getinfo($redirect, CURLINFO_HTTP_CODE);
$redirectUrl = (string) curl_getinfo($redirect, CURLINFO_REDIRECT_URL);
curl_close($redirect);
$redirectPassed = $redirectCode === 301 && str_starts_with($redirectUrl, 'https://127.0.0.1/');
echo ($redirectPassed ? 'PASS ' : 'FAIL ') . 'HTTP redirects to HTTPS HTTP ' . $redirectCode . PHP_EOL;
if (!$redirectPassed) $failed++;

$protectedApiPaths = ['/api/tasks.php', '/api/weather.php', '/api/sysinfo.php', '/api/search.php'];
foreach ($protectedApiPaths as $path) {
    $api = curl_init($baseUrl . $path);
    curl_setopt_array($api, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_CAINFO => $caBundle,
    ]);
    $apiBody = (string) curl_exec($api);
    $apiCode = (int) curl_getinfo($api, CURLINFO_HTTP_CODE);
    curl_close($api);
    $apiPassed = $apiCode === 401 && str_contains($apiBody, 'Authentication required');
    echo ($apiPassed ? 'PASS ' : 'FAIL ') . 'anonymous API blocked ' . $path . ' HTTP ' . $apiCode . PHP_EOL;
    if (!$apiPassed) $failed++;
}

$accept = curl_init($baseUrl . '/accept_invitation.php');
curl_setopt_array($accept,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CAINFO=>$caBundle]);
$acceptBody=(string)curl_exec($accept);$acceptCode=(int)curl_getinfo($accept,CURLINFO_HTTP_CODE);curl_close($accept);
$acceptPassed=$acceptCode===200&&str_contains($acceptBody,'Invitation');
echo ($acceptPassed?'PASS ':'FAIL ').'invitation acceptance route HTTP '.$acceptCode.PHP_EOL;
if(!$acceptPassed)$failed++;

$goals=curl_init($baseUrl.'/goals.php');
curl_setopt_array($goals,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CAINFO=>$caBundle]);
$goalsBody=(string)curl_exec($goals);$goalsCode=(int)curl_getinfo($goals,CURLINFO_HTTP_CODE);curl_close($goals);
$goalsPassed=$goalsCode===302;
echo ($goalsPassed?'PASS ':'FAIL ').'anonymous goals route protected HTTP '.$goalsCode.PHP_EOL;
if(!$goalsPassed)$failed++;

$journal= curl_init($baseUrl.'/journal.php');
curl_setopt_array($journal,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CAINFO=>$caBundle]);
$journalBody=(string)curl_exec($journal);$journalCode=(int)curl_getinfo($journal,CURLINFO_HTTP_CODE);curl_close($journal);
$journalPassed=$journalCode===302;
echo ($journalPassed?'PASS ':'FAIL ').'anonymous journal route protected HTTP '.$journalCode.PHP_EOL;
if(!$journalPassed)$failed++;

echo 'SUMMARY ' . (count($checks) + count($protectedApiPaths) + 5 - $failed) . '/' . (count($checks) + count($protectedApiPaths) + 5) . ' passed' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
