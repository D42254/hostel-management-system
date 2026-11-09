<?php
require '../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['manager_id'])) { header("Location: login.php"); exit; }

$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalRooms    = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$totalBeds     = $pdo->query("SELECT COUNT(*) FROM beds")->fetchColumn();
$occupiedBeds  = $pdo->query("SELECT COUNT(*) FROM beds WHERE status='occupied'")->fetchColumn();

$pageTitle = 'Manager Dashboard';
require '../includes/header.php';
?>
<h3>🛠 Manager Dashboard</h3>
<p class="text-muted">Welcome, <?= htmlspecialchars($_SESSION['manager_name'] ?? 'Manager') ?></p>

<div class="row g-3 mt-2">
  <div class="col-md-3"><div class="card p-3"><h6>Students</h6><h2><?= $totalStudents ?></h2></div></div>
  <div class="col-md-3"><div class="card p-3"><h6>Rooms</h6><h2><?= $totalRooms ?></h2></div></div>
  <div class="col-md-3"><div class="card p-3"><h6>Total Beds</h6><h2><?= $totalBeds ?></h2></div></div>
  <div class="col-md-3"><div class="card p-3"><h6>Occupied</h6><h2><?= $occupiedBeds ?></h2></div></div>
</div>

<div class="alert alert-info mt-4">Phase 2 will add: room/bed grid, student list, alerts, and messaging.</div>
<?php require '../includes/footer.php'; ?>