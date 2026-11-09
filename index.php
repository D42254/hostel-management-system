<?php
$pageTitle = 'Welcome';
require 'includes/header.php';
?>
<div class="text-center py-5">
  <h1 class="mb-3">🏠 Hostel Management System</h1>
  <p class="lead text-muted">Book a bed, pay rent, talk to management — all in one place.</p>
  <div class="mt-4">
    <a href="student/register.php" class="btn btn-primary btn-lg me-2">Register (New Student)</a>
    <a href="student/login.php" class="btn btn-outline-primary btn-lg me-2">Student Login</a>
    <a href="manager/login.php" class="btn btn-outline-dark btn-lg">Manager Login</a>
  </div>
</div>
<?php require 'includes/footer.php'; ?>