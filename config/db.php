<?php
// config/db.php
$DB_HOST = 'localhost';
$DB_NAME = 'hostel_db';
$DB_USER = 'root';
$DB_PASS = 'root';        // XAMPP default is empty

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}