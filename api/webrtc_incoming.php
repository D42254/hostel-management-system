<?php
// api/webrtc_incoming.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$isStudent = !empty($_SESSION['student_id']);
$isManager = !empty($_SESSION['manager_id']);
if (!$isStudent && !$isManager) { echo json_encode(['ok'=>false]); exit; }

$meType = $isStudent ? 'student' : 'manager';
$meId   = $isStudent ? (int)$_SESSION['student_id'] : (int)$_SESSION['manager_id'];

/* ACK: hide this offer from now on (was accepted/declined) */
if (!empty($_GET['ack'])) {
    $pdo->prepare("DELETE FROM webrtc_signals
                   WHERE call_id=? AND to_type=? AND to_id=? AND signal_type='offer'")
        ->execute([$_GET['ack'], $meType, $meId]);
    echo json_encode(['ok'=>true]); exit;
}

/* Poll for offers + hangups addressed to me */
$stmt = $pdo->prepare("
    SELECT s.*,
           CASE WHEN s.from_type='student'
                THEN (SELECT full_name FROM students WHERE id=s.from_id)
                ELSE (SELECT name      FROM managers WHERE id=s.from_id) END AS from_name
    FROM webrtc_signals s
    WHERE s.to_type=? AND s.to_id=?
      AND s.signal_type IN ('offer','hangup')
    ORDER BY s.id ASC
    LIMIT 20
");
$stmt->execute([$meType, $meId]);
echo json_encode(['ok'=>true, 'signals'=>$stmt->fetchAll()]);