<?php
// api/notify_check.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$isStudent = !empty($_SESSION['student_id']);
$isManager = !empty($_SESSION['manager_id']);
if (!$isStudent && !$isManager) { echo json_encode(['ok'=>false]); exit; }

$meType = $isStudent ? 'student' : 'manager';
$meId   = $isStudent ? (int)$_SESSION['student_id'] : (int)$_SESSION['manager_id'];

/* ---------- unread count ---------- */
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages
                       WHERE receiver_type=? AND receiver_id=? AND is_read=0");
$stmt->execute([$meType, $meId]);
$unread = (int)$stmt->fetchColumn();

/* ---------- latest unread message (for banner) ---------- */
$lastMsg = null;
if ($unread > 0) {
    $s = $pdo->prepare("
        SELECT m.id, m.body, m.created_at, m.sender_id,
               CASE WHEN m.sender_type='student'
                    THEN (SELECT full_name FROM students WHERE id=m.sender_id)
                    ELSE (SELECT name FROM managers WHERE id=m.sender_id) END AS sender_name
        FROM messages m
        WHERE m.receiver_type=? AND m.receiver_id=? AND m.is_read=0
        ORDER BY m.id DESC LIMIT 1
    ");
    $s->execute([$meType, $meId]);
    $lastMsg = $s->fetch() ?: null;
}

/* ---------- incoming call (BOTH sides now) ---------- */
$incomingCall = null;

// A call "offer" is pending TO ME and I have not answered yet
$q = $pdo->prepare("
    SELECT s.id, s.call_id, s.from_type, s.from_id,
           CASE WHEN s.from_type='student'
                THEN (SELECT full_name FROM students WHERE id=s.from_id)
                ELSE (SELECT name      FROM managers WHERE id=s.from_id) END AS peer_name
    FROM webrtc_signals s
    WHERE s.to_type = ? AND s.to_id = ?
      AND s.signal_type = 'offer'
      AND s.created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)   -- ignore stale offers
      AND NOT EXISTS (
          SELECT 1 FROM webrtc_signals a
          WHERE a.call_id = s.call_id
            AND a.signal_type = 'answer'
            AND a.from_type   = ?
            AND a.from_id     = ?
      )
    ORDER BY s.id DESC
    LIMIT 1
");
$q->execute([$meType, $meId, $meType, $meId]);
$incomingCall = $q->fetch() ?: null;
if ($incomingCall) $incomingCall['kind'] = 'incoming_offer';

echo json_encode([
    'ok'            => true,
    'role'          => $meType,
    'unread'        => $unread,
    'last_msg'      => $lastMsg,
    'incoming_call' => $incomingCall,
]);