<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['student_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']); exit;
}

$cfg = require __DIR__ . '/../config/mpesa.php';
$studentId = (int)$_SESSION['student_id'];
$type = $_POST['type'] ?? 'rent'; // 'registration' or 'rent'

// 1. Determine the amount (Server-side lookup for security)
if ($type === 'registration') {
    $amount = 1000;
    $month = null;
} else {
    $month = $_POST['month'] ?? date('Y-m');
    $stmt = $pdo->prepare("
        SELECT r.price FROM bookings b
        JOIN beds ON beds.id = b.bed_id
        JOIN rooms r ON r.id = beds.room_id
        WHERE b.student_id = ? AND b.status = 'active' LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $row = $stmt->fetch();
    if (!$row) { echo json_encode(['ok' => false, 'error' => 'No active bed found']); exit; }
    $amount = (int)$row['price'];
}

// 2. Get student phone
$stmt = $pdo->prepare("SELECT phone FROM students WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();
$phone = preg_replace('/^0/', '254', $student['phone']); // Format to 2547...

// 3. Create a PENDING record FIRST (so callback can find it)
$ins = $pdo->prepare("INSERT INTO payments (student_id, amount, type, month, status) VALUES (?,?,?,?,'pending')");
$ins->execute([$studentId, $amount, $type, $month]);
$paymentId = $pdo->lastInsertId();

// 4. Get OAuth Token
$ch = curl_init($cfg['base_url'] . '/oauth/v1/generate?grant_type=client_credentials');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($cfg['consumer_key'] . ':' . $cfg['consumer_secret'])],
]);
$tokenRes = curl_exec($ch);
curl_close($ch);
$tokenData = json_decode($tokenRes, true);
$accessToken = $tokenData['access_token'] ?? null;

if (!$accessToken) {
    $pdo->prepare("UPDATE payments SET status='failed', result_desc='Auth failed' WHERE id=?")->execute([$paymentId]);
    echo json_encode(['ok' => false, 'error' => 'Authentication failed. Check credentials.']); exit;
}

// 5. Prepare and Send STK Push
$timestamp = date('YmdHis');
$password = base64_encode($cfg['shortcode'] . $cfg['passkey'] . $timestamp);

$payload = [
    'BusinessShortCode' => $cfg['shortcode'],
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerPayBillOnline',
    'Amount' => $amount,
    'PartyA' => $phone,
    'PartyB' => $cfg['shortcode'],
    'PhoneNumber' => $phone,
    'CallBackURL' => $cfg['callback_url'],
    'AccountReference' => 'HST-' . $studentId,
    'TransactionDesc' => 'Hostel ' . ucfirst($type),
];

$ch = curl_init($cfg['base_url'] . '/mpesa/stkpush/v1/processrequest');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $accessToken],
]);
$pushRes = curl_exec($ch);
curl_close($ch);
$pushData = json_decode($pushRes, true);

// 6. Save CheckoutRequestID for correlation
if (!empty($pushData['CheckoutRequestID'])) {
    $pdo->prepare("UPDATE payments SET checkout_request_id = ? WHERE id = ?")
        ->execute([$pushData['CheckoutRequestID'], $paymentId]);
    echo json_encode(['ok' => true, 'payment_id' => $paymentId, 'message' => 'Check your phone for the M-Pesa prompt.']);
} else {
    $pdo->prepare("UPDATE payments SET status='failed', result_desc=? WHERE id=?")
        ->execute([json_encode($pushData), $paymentId]);
    echo json_encode(['ok' => false, 'error' => 'STK push failed', 'details' => $pushData]);
}