<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/validators.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$errors = []; $done = false;

$mgr = null;
if ($token) {
    $s = $pdo->prepare("SELECT id, reset_expires FROM managers WHERE reset_token=?");
    $s->execute([$token]);
    $mgr = $s->fetch();
    if (!$mgr || strtotime($mgr['reset_expires']) < time()) {
        $errors['token'] = 'Invalid or expired reset link.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mgr && !$errors) {
    $p1 = $_POST['password']  ?? '';
    $p2 = $_POST['password2'] ?? '';
    if (!v_password($p1)) $errors['password']  = 'Password ≥6 chars, letters + digits';
    if ($p1 !== $p2)      $errors['password2'] = 'Passwords do not match';
    if (!$errors) {
        $hash = password_hash($p1, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE managers SET password_hash=?, reset_token=NULL, reset_expires=NULL WHERE id=?")
            ->execute([$hash, $mgr['id']]);
        $done = true;
    }
}
$pageTitle = 'Reset Password';
require '../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-5">
  <div class="card p-4">
    <h4 class="mb-3">🔐 Reset Password (Manager)</h4>
    <?php if (isset($errors['token'])): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($errors['token']) ?></div>
      <a href="forgot.php" class="btn btn-dark">Request new link</a>
    <?php elseif ($done): ?>
      <div class="alert alert-success">Password updated.</div>
      <a href="login.php" class="btn btn-dark w-100">Go to Login</a>
    <?php else: ?>
      <form method="post" data-validate novalidate>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="mb-3"><label class="form-label">New password</label>
          <input type="password" name="password" class="form-control" data-rules="required|password" required></div>
        <div class="mb-3"><label class="form-label">Confirm password</label>
          <input type="password" name="password2" class="form-control" data-rules="required|match:password" data-match="password" required></div>
        <button class="btn btn-dark w-100">Update Password</button>
      </form>
    <?php endif; ?>
  </div>
</div></div>
<?php require '../includes/footer.php'; ?>