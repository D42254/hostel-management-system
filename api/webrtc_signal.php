<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$isStudent = !empty($_SESSION['student_id']);
$isManager = !empty($_SESSION['manager_id']);
if (!$isStudent && !$isManager) { echo json_encode(['ok'=>false]); exit; }

$meType = $isStudent ? 'student' : 'manager';
$meId   = $isStudent ? (int)$_SESSION['student_id'] : (int)$_SESSION['manager_id'];

// --- POST: send a signal ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $callId = $_POST['call_id'] ?? '';
    $toType = $_POST['to_type'] ?? '';
    $toId   = (int)($_POST['to_id'] ?? 0);
    $type   = $_POST['signal_type'] ?? '';
    $payload = $_POST['payload'] ?? '';
    if (!$callId || !in_array($type,['offer','answer','candidate','hangup'],true)) {
        echo json_encode(['ok'=>false,'error'=>'bad input']); exit;
    }
    $pdo->prepare("INSERT INTO webrtc_signals (call_id,from_type,from_id,to_type,to_id,signal_type,payload)
                   VALUES (?,?,?,?,?,?,?)")
        ->execute([$callId,$meType,$meId,$toType,$toId,$type,$payload]);
    echo json_encode(['ok'=>true]); exit;
}

// --- GET: poll for incoming signals ---
$callId = $_GET['call_id'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM webrtc_signals 
                       WHERE call_id=? AND to_type=? AND to_id=? 
                       ORDER BY id ASC");
$stmt->execute([$callId, $meType, $meId]);
$rows = $stmt->fetchAll();

// if consume=1, delete them after reading
if (!empty($_GET['consume'])) {
    $pdo->prepare("DELETE FROM webrtc_signals WHERE call_id=? AND to_type=? AND to_id=?")
        ->execute([$callId, $meType, $meId]);
}

echo json_encode(['ok'=>true,'signals'=>$rows]);