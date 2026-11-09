<?php
// api/chat_send.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$isStudent = !empty($_SESSION['student_id']);
$isManager = !empty($_SESSION['manager_id']);
if (!$isStudent && !$isManager) { echo json_encode(['ok'=>false,'error'=>'Not logged in']); exit; }

$body = trim($_POST['body'] ?? '');
$toType = $_POST['to_type'] ?? '';
$toId   = (int)($_POST['to_id'] ?? 0);

if ($body === '') { echo json_encode(['ok'=>false,'error'=>'Empty message']); exit; }
if (!in_array($toType, ['student','manager'], true)) { echo json_encode(['ok'=>false,'error'=>'Invalid recipient']); exit; }

$fromType = $isStudent ? 'student' : 'manager';
$fromId   = $isStudent ? (int)$_SESSION['student_id'] : (int)$_SESSION['manager_id'];

$stmt = $pdo->prepare("INSERT INTO messages (sender_type, sender_id, receiver_type, receiver_id, body)
                       VALUES (?,?,?,?,?)");
$stmt->execute([$fromType, $fromId, $toType, $toId ?: null, $body]);

echo json_encode(['ok'=>true, 'id'=>$pdo->lastInsertId()]);