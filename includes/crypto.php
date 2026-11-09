<?php
// includes/crypto.php
// define('APP_KEY', 'a-32-byte-random-string-here'); // in config, never in Git
function enc(string $plain): string {
    $key = hash('sha256', APP_KEY, true);
    $iv  = random_bytes(12);
    $ct  = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $ct);
}
function dec(string $encoded): ?string {
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 28) return null;
    $key = hash('sha256', APP_KEY, true);
    $iv  = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ct  = substr($raw, 28);
    return openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag) ?: null;
}