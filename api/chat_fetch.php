<?php
// api/chat_fetch.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$isStudent = !empty($_SESSION['student_id']);
$isManager = !empty($_SESSION['manager_id']);
if (!$isStudent && !$isManager) { echo json_encode(['ok'=>false,'error'=>'Not logged in']); exit; }

$meType = $isStudent ? 'student' : 'manager';
$meId   = $isStudent ? (int)$_SESSION['student_id'] : (int)$_SESSION['manager_id'];
$after  = (int)($_GET['after'] ?? 0);
$withId = (int)($_GET['with_id'] ?? 0);   // 👈 the "other party" id

/* -------- STUDENT VIEW --------
   Students only chat with "the manager" (one thread).
   If a specific manager id is passed, filter to that one.
   Otherwise show messages between this student and ANY manager. */
if ($isStudent) {
    $sql = "SELECT m.*, 
                   CASE WHEN m.sender_type='student'
                        THEN (SELECT full_name FROM students WHERE id=m.sender_id)
                        ELSE (SELECT name FROM managers WHERE id=m.sender_id) END AS sender_name
            FROM messages m
            WHERE m.id > ?
              AND (
                (m.sender_type='student' AND m.sender_id=?  AND m.receiver_type='manager')
                OR
                (m.receiver_type='student' AND m.receiver_id=? AND m.sender_type='manager')
              )";
    $params = [$after, $meId, $meId];
    if ($withId) {
        $sql .= " AND (m.sender_type='manager' AND m.sender_id=? OR m.receiver_type='manager' AND m.receiver_id=?)";
        $params[] = $withId; $params[] = $withId;
    }
    $sql .= " ORDER BY m.id ASC LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

/* -------- MANAGER VIEW --------
   Manager must specify WHICH student. Without with_id, return nothing
   (prevents the mixed-conversation bug). */
} else {
    if (!$withId) {
        echo json_encode(['ok'=>true, 'messages'=>[]]);
        exit;
    }
    $sql = "SELECT m.*,
                   CASE WHEN m.sender_type='student'
                        THEN (SELECT full_name FROM students WHERE id=m.sender_id)
                        ELSE (SELECT name FROM managers WHERE id=m.sender_id) END AS sender_name
            FROM messages m
            WHERE m.id > ?
              AND (
                (m.sender_type='student'  AND m.sender_id=?    AND m.receiver_type='manager' AND m.receiver_id=?)
                OR
                (m.sender_type='manager'  AND m.sender_id=?    AND m.receiver_type='student' AND m.receiver_id=?)
              )
            ORDER BY m.id ASC
            LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$after, $withId, $meId, $meId, $withId]);
}

$rows = $stmt->fetchAll();

/* Mark messages TO me FROM this partner as read */
if ($withId && $rows) {
    $pdo->prepare("UPDATE messages SET is_read=1
                   WHERE receiver_type=? AND receiver_id=?
                     AND sender_type=? AND sender_id=?
                     AND is_read=0")
        ->execute([$meType, $meId, ($meType === 'manager' ? 'student' : 'manager'), $withId]);
} elseif ($isStudent && $rows) {
    // student reads all incoming from any manager
    $pdo->prepare("UPDATE messages SET is_read=1
                   WHERE receiver_type='student' AND receiver_id=? AND is_read=0")
        ->execute([$meId]);
}

echo json_encode(['ok'=>true, 'messages'=>$rows]);