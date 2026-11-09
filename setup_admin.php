<?php
require_once __DIR__ . '/config/db.php';

// ---- EDIT THESE ----
$name     = 'System Admin';
$email    = 'admin@hostel.local';
$password = 'Admin@123';          // change this after first login
$role     = 'admin';              // 'admin' or 'manager'
// --------------------

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO managers (name, email, role, password_hash, is_active, created_by)
    VALUES (?, ?, ?, ?, 1, NULL)
    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        role = VALUES(role),
        password_hash = VALUES(password_hash),
        is_active = 1
");
$stmt->execute([$name, $email, $role, $hash]);

echo "✅ Admin ensured:<br>";
echo "Email: <b>" . htmlspecialchars($email) . "</b><br>";
echo "Password: <b>" . htmlspecialchars($password) . "</b><br>";
echo "<b>Delete this file now!</b>";