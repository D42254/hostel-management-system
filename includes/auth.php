<?php
// includes/auth.php
// Guards + helpers used across protected pages.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Auto-detect the app base path (e.g. "/hostel").
 */
if (!function_exists('app_base')) {
    function app_base(): string {
        if (isset($GLOBALS['base']) && $GLOBALS['base'] !== '') return rtrim($GLOBALS['base'], '/');
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $parts  = explode('/', trim($script, '/'));
        return $parts && $parts[0] !== '' ? '/' . $parts[0] : '';
    }
}

if (!function_exists('require_student')) {
    function require_student(): void {
        if (empty($_SESSION['student_id'])) {
            header("Location: " . app_base() . "/student/login.php");
            exit;
        }
    }
}

if (!function_exists('require_manager')) {
    function require_manager(): void {
        if (empty($_SESSION['manager_id'])) {
            header("Location: " . app_base() . "/manager/login.php");
            exit;
        }
    }
}

if (!function_exists('require_admin')) {
    function require_admin(): void {
        if (empty($_SESSION['manager_id'])) {
            header("Location: " . app_base() . "/manager/login.php");
            exit;
        }
        global $pdo;
        $s = $pdo->prepare("SELECT role FROM managers WHERE id = ?");
        $s->execute([$_SESSION['manager_id']]);
        if ($s->fetchColumn() !== 'admin') {
            http_response_code(403);
            die("Access denied — admin only. <a href='" . app_base() . "/manager/dashboard.php'>Back</a>");
        }
    }
}

if (!function_exists('current_student')) {
    function current_student(PDO $pdo): ?array {
        if (empty($_SESSION['student_id'])) return null;
        $s = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $s->execute([$_SESSION['student_id']]);
        return $s->fetch() ?: null;
    }
}

if (!function_exists('current_manager')) {
    function current_manager(PDO $pdo): ?array {
        if (empty($_SESSION['manager_id'])) return null;
        $s = $pdo->prepare("SELECT * FROM managers WHERE id = ?");
        $s->execute([$_SESSION['manager_id']]);
        return $s->fetch() ?: null;
    }
}

if (!function_exists('current_booking')) {
    function current_booking(PDO $pdo, int $studentId): ?array {
        $s = $pdo->prepare("
            SELECT b.*, beds.bed_label, beds.position, rooms.room_no, rooms.capacity, rooms.price
            FROM bookings b
            JOIN beds  ON beds.id  = b.bed_id
            JOIN rooms ON rooms.id = beds.room_id
            WHERE b.student_id = ? AND b.status = 'active'
            LIMIT 1
        ");
        $s->execute([$studentId]);
        return $s->fetch() ?: null;
    }
}

if (!function_exists('is_admin')) {
    function is_admin(PDO $pdo): bool {
        $m = current_manager($pdo);
        return $m && ($m['role'] ?? '') === 'admin';
    }
}