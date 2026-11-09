<?php
// config/mpesa.php
return [
    'env'             => 'sandbox',                  // 'sandbox' or 'production'
    'consumer_key'    => 'EjiGlNiFqqS0CRSvsABLUUgfdXxhAoc8cQbKuU5iG4hvokmE',
    'consumer_secret' => 'UiKfvSmyAbFmgfzjmPkLhxxQoNyCgxveKUIDX9qdJFfcSjLXaxe9Un13YfR7T4GN',
    'shortcode'       => '174379',                   // sandbox paybill
    'passkey'         => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
    'callback_url'    => 'https://jazz-tradition-jingle.ngrok-free.dev/hostel/api/mpesa_callback.php',
    'base_url'        => 'https://sandbox.safaricom.co.ke',
];