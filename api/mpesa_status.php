<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['student_id'])) { echo json_encode(['status' => 'error']); exit; }

$id = (int)($_GET['payment_id'] ?? 0);
$stmt = $pdo->prepare("SELECT status, mpesa_ref, result_desc FROM payments WHERE id=? AND student_id=?");
$stmt->execute([$id, $_SESSION['student_id']]);
$pay = $stmt->fetch();

if (!$pay) { echo json_encode(['status' => 'error', 'reason' => 'Not found']); exit; }

echo json_encode([
    'status'  => $pay['status'], // 'pending', 'paid', 'failed'
    'receipt' => $pay['mpesa_ref'],
    'reason'  => $pay['result_desc'],
]);