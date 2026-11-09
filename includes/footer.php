<?php
// includes/footer.php
// Expects optional globals:
//   $base          — usually set in header.php (e.g. "/hostel")
//   $extraScripts  — array of extra <script src="..."> to load after validate.js
//   $extraStyles   — array of extra <link rel="stylesheet"> to load
//   $_SESSION['flash']  — ['type'=>'success'|'danger'|..., 'msg'=>'...'] shown once

if (!isset($base)) {
    // Fallback: derive base from SCRIPT_NAME (works for /hostel/student/x.php etc.)
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $parts  = explode('/', trim($script, '/'));
    $base   = isset($parts[0]) && $parts[0] !== '' ? '/' . $parts[0] : '';
}

$currentPath = $_SERVER['REQUEST_URI'] ?? '';
?>

</main><!-- /main.container -->

<!-- ===== Visible site footer ===== -->
<footer class="site-footer mt-5">
  <div class="container py-4">
    <div class="row gy-4">

      <div class="col-md-4">
        <h6 class="fw-bold mb-2">🏠 Hostel Management System</h6>
        <p class="small text-muted mb-0">
          Book a bed, pay rent via M-Pesa, and talk to management — all in one place.
        </p>
      </div>

      <div class="col-md-2">
        <h6 class="fw-bold mb-2">Students</h6>
        <ul class="list-unstyled small mb-0">
          <li><a class="footer-link" href="<?= $base ?>/student/register.php">Register</a></li>
          <li><a class="footer-link" href="<?= $base ?>/student/login.php">Login</a></li>
          <li><a class="footer-link" href="<?= $base ?>/student/dashboard.php">Dashboard</a></li>
        </ul>
      </div>

      <div class="col-md-2">
        <h6 class="fw-bold mb-2">Management</h6>
        <ul class="list-unstyled small mb-0">
          <li><a class="footer-link" href="<?= $base ?>/manager/login.php">Manager Login</a></li>
          <li><a class="footer-link" href="<?= $base ?>/manager/dashboard.php">Dashboard</a></li>
        </ul>
      </div>

      <div class="col-md-4">
        <h6 class="fw-bold mb-2">Contact</h6>
        <ul class="list-unstyled small mb-0">
          <li>📞 <a class="footer-link" href="tel:+254700000000">+254 700 000 000</a></li>
          <li>✉️ <a class="footer-link" href="mailto:info@hostel.local">info@hostel.local</a></li>
          <li>💳 M-Pesa Paybill: <b>000000</b> · Acc: <b>HOSTEL</b></li>
        </ul>
      </div>
    </div>

    <hr class="my-3">
    <div class="d-flex flex-wrap justify-content-between small text-muted">
      <div>&copy; <?= date('Y') ?> Hostel Management System. All rights reserved.</div>
      <div>Built for XAMPP · PHP <?= htmlspecialchars(PHP_VERSION) ?></div>
    </div>
  </div>
</footer>

<!-- ===== Flash message auto-dismiss ===== -->
<?php if (!empty($_SESSION['flash'])): ?>
  <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
  <div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080">
    <div class="toast show text-bg-<?= htmlspecialchars($flash['type'] ?? 'secondary') ?>" role="alert">
      <div class="d-flex">
        <div class="toast-body"><?= htmlspecialchars($flash['msg'] ?? '') ?></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- ===== Scripts ===== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php if (!empty($extraStyles)): ?>
  <?php foreach ((array)$extraStyles as $href): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($href) ?>">
  <?php endforeach; ?>
<?php endif; ?>

<!-- Always load the reusable validator (harmless if no form opts in) -->
<script src="<?= $base ?>/assets/js/validate.js"></script>
<script src="<?= $base ?>/assets/js/password.js"></script>

<?php if (!empty($_SESSION['student_id']) || !empty($_SESSION['manager_id'])): ?>
  <script>window.APP_BASE = "<?= htmlspecialchars($base ?? '') ?>";</script>
  <script src="<?= $base ?>/assets/js/notify.js"></script>
<?php endif; ?>

<?php if (!empty($extraScripts)): ?>
  <?php foreach ((array)$extraScripts as $src): ?>
    <script src="<?= htmlspecialchars($src) ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>

<!-- Auto-init Bootstrap toasts and dismiss after 4s -->
<script>
  document.querySelectorAll('.toast.show').forEach(function (el) {
    new bootstrap.Toast(el, { delay: 4000 }).show();
  });
</script>
</body>
</html>