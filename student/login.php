<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/validators.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!v_email($email))   $errors['email']    = "Enter a valid email";
    if (!v_required($pass)) $errors['password'] = "Password required";

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE email = ?");
        $stmt->execute([$email]);
        $s = $stmt->fetch();
        if ($s && password_verify($pass, $s['password_hash'])) {
            $_SESSION['student_id']   = $s['id'];
            $_SESSION['student_name'] = $s['full_name'];
            header("Location: dashboard.php"); exit;
        }
        $errors['general'] = "Invalid email or password";
    }
}
$pageTitle = 'Student Login';
require '../includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card p-4">
      <h4 class="mb-3">Student Login</h4>
      <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
      <?php endif; ?>
      <form method="post" data-validate novalidate>
        <div class="mb-3"><label class="form-label">Email</label>
          <input type="email" name="email" class="form-control<?= isset($errors['email'])?' is-invalid':'' ?>"
                 data-rules="required|email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
          <?php if (isset($errors['email'])): ?>
            <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['email']) ?></div>
          <?php endif; ?>
        </div>
        <div class="mb-3"><label class="form-label">Password</label>
          <input type="password" name="password"
                 class="form-control<?= isset($errors['password'])?' is-invalid':'' ?>"
                 data-rules="required" required>
          <?php if (isset($errors['password'])): ?>
            <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['password']) ?></div>
          <?php endif; ?>
        </div>
        <button class="btn btn-primary w-100">Login</button>
      </form>
      <a href="forgot.php" class="small">Forgot password?</a>
      <p class="mt-3 mb-0 text-center small">No account? <a href="register.php">Register here</a></p>
    </div>
  </div>
</div>
<?php require '../includes/footer.php'; ?>