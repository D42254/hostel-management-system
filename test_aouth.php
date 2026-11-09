<?php
$cfg = require __DIR__ . '/config/mpesa.php';

// Build the URL safely (avoid double slashes)
$url = rtrim($cfg['base_url'], '/') . '/oauth/v1/generate?grant_type=client_credentials';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Basic ' . base64_encode($cfg['consumer_key'] . ':' . $cfg['consumer_secret']),
        'Content-Type: application/json', // Add this header
    ],
    CURLOPT_TIMEOUT => 30,
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "URL: $url\n";
echo "HTTP: $code\n";
echo "Response: " . var_export($res, true) . "\n";