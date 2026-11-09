<?php
// config/mpesa.php


return [
    'env'             => 'sandbox',                  // 'sandbox' or 'production'
    'consumer_key'    => 'EjiGlNiFqqS0CRSvsABLUUgfdXxhAoc8cQbKuU5iG4hvokmE',
    'consumer_secret' => '1K3GSVOnLPWR8EpvBRA5sHohmnPQfFecEn1x6JZpJYjtCHpUUGm6Tis6Ek7uHl50',
    'shortcode'       => '174379',                   // sandbox paybill
    'passkey'         => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
    'callback_url'    => 'https://YOUR-NGROK-URL.ngrok-free.app/hostel/api/mpesa_callback.php',
    'base_url'        => 'https://sandbox.safaricom.co.ke',
];



$key = "EjiGlNiFqqS0CRSvsABLUUgfdXxhAoc8cQbKuU5iG4hvokmE"
$secret = "1K3GSVOnLPWR8EpvBRA5sHohmnPQfFecEn1x6JZpJYjtCHpUUGm6Tis6Ek7uHl50"

$auth = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("${key}:${secret}"))

$response = Invoke-RestMethod -Uri "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials" `
  -Method Get `
  -Headers @{ Authorization = "Basic $auth" }

$response