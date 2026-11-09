<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) session_start();

/* ---------- auto-detect $base (e.g. "/hostel") ---------- */
if (!isset($base)) {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $parts  = explode('/', trim($script, '/'));
    $base   = isset($parts[0]) && $parts[0] !== '' ? '/' . $parts[0] : '';
}

$isStudent = !empty($_SESSION['student_id']);
$isManager = !empty($_SESSION['manager_id']);
$isAdmin   = $isManager && (($_SESSION['manager_role'] ?? '') === 'admin');
$isLoggedIn = $isStudent || $isManager;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle ?? 'Hostel Management') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= $base ?>/assets/css/style.css" rel="stylesheet">
  <?php if (!empty($extraStyles)): foreach ((array)$extraStyles as $href): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($href) ?>">
  <?php endforeach; endif; ?>
</head>
<body>

<!-- ============================================================
     GLOBAL NOTIFICATION BANNER (only when logged in)
     Slides down from top-center. Filled by notify.js.
     ============================================================ -->
<?php if ($isLoggedIn): ?>
  <div id="notifyBanner"
       class="position-fixed top-0 start-50 translate-middle-x mt-2 d-none"
       style="z-index:1090;max-width:560px;width:96%">
    <div class="alert alert-warning shadow d-flex justify-content-between align-items-center py-2 mb-0"
         id="notifyBannerInner">
      <div id="notifyBannerText" class="small"></div>
      <a id="notifyBannerLink" href="#" class="btn btn-sm btn-dark ms-2">Open</a>
    </div>
  </div>
<?php endif; ?>

<!-- ============================================================
     NAVBAR
     ============================================================ -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
  <div class="container">

    <a class="navbar-brand fw-bold" href="<?= $base ?>/index.php">🏠 Hostel System</a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto">

        <?php if ($isStudent): ?>
          <!-- ================= STUDENT ================= -->
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>/student/dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>/student/book.php">Book Bed</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>/student/payment.php">Payments</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>/student/messages.php">Messages</a></li>

        <?php elseif ($isManager): ?>
          <!-- ================= MANAGER / ADMIN ================= -->
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>/manager/dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>/manager/rooms.php">Rooms & Beds</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>/manager/students.php">Students</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>/manager/messages.php">Messages</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>/manager/alerts.php">Alerts</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>/manager/payments.php">Payments</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>/manager/call.php">📞 Calls</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>/manager/reports.php">📊 Reports</a></li>

          <?php if ($isAdmin): ?>
            <!-- ============== ADMIN ONLY ============== -->
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">⚙️ Admin</a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="<?= $base ?>/manager/admin_managers.php">👥 Managers</a></li>
                <li><a class="dropdown-item" href="<?= $base ?>/manager/admin_register_manager.php">➕ Add Manager</a></li>
              </ul>
            </li>
          <?php endif; ?>
        <?php endif; ?>

      </ul>

      <div class="d-flex align-items-center gap-2">

        <?php if ($isLoggedIn): ?>
          <!-- ================ 🔔 BELL ================ -->
          <a href="#" id="notifyBell"
             class="btn btn-sm btn-outline-light position-relative"
             title="Notifications"
             onclick="return false;">
            🔔
            <span id="notifyBellBadge"
                  class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">
              0
            </span>
          </a>
        <?php endif; ?>

        <?php if ($isStudent): ?>
          <span class="text-light small">👤 <?= htmlspecialchars($_SESSION['student_name'] ?? '') ?></span>
          <a class="btn btn-sm btn-outline-light" href="<?= $base ?>/student/logout.php">Logout</a>
        <?php elseif ($isManager): ?>
          <span class="text-light small">
            <?= $isAdmin ? '👑' : '🛠' ?>
            <?= htmlspecialchars($_SESSION['manager_name'] ?? 'Manager') ?>
          </span>
          <a class="btn btn-sm btn-outline-light" href="<?= $base ?>/manager/logout.php">Logout</a>
        <?php else: ?>
          <a class="btn btn-sm btn-outline-light" href="<?= $base ?>/student/login.php">Student Login</a>
          <a class="btn btn-sm btn-primary" href="<?= $base ?>/student/register.php">Register</a>
        <?php endif; ?>
      </div>

    </div>
  </div>
</nav>

<main class="container pb-3">