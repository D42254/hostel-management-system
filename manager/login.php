<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/validators.php';
require_once __DIR__ . '/../includes/flash.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in as a manager? Go to dashboard.
if (!empty($_SESSION['manager_id'])) {
    header("Location: dashboard.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!v_email($email))   $errors['email']    = 'Enter a valid email';
    if (!v_required($pass)) $errors['password'] = 'Password required';

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT * FROM managers WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $m = $stmt->fetch();

        if (!$m || !password_verify($pass, $m['password_hash'])) {
            $errors['general'] = 'Invalid email or password';
        } elseif ((int)$m['is_active'] !== 1) {
            $errors['general'] = 'This account has been disabled. Contact an administrator.';
        } else {
            // Success
            session_regenerate_id(true);
            $_SESSION['manager_id']   = (int)$m['id'];
            $_SESSION['manager_name'] = $m['name'];
            $_SESSION['manager_role'] = $m['role'];   // 'admin' | 'manager'
            header("Location: dashboard.php");
            exit;
        }
    }
}

$pageTitle = 'Manager Login';
require '../includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-5">

    <div class="card p-4 shadow-sm">
      <h4 class="mb-3 text-center">🛠 Manager Login</h4>

      <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger mb-3">
          <?= htmlspecialchars($errors['general']) ?>
        </div>
      <?php endif; ?>

      <form method="post" data-validate novalidate>

        <!-- Email -->
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email"
                 class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
                 data-rules="required|email"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 required autofocus>
          <?php if (isset($errors['email'])): ?>
            <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['email']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Password -->
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password"
                 class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                 data-rules="required"
                 required>
          <?php if (isset($errors['password'])): ?>
            <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['password']) ?></div>
          <?php endif; ?>
        </div>

        <button class="btn btn-dark w-100">Login</button>

        <div class="d-flex justify-content-between mt-3">
          <a href="forgot.php" class="small">Forgot password?</a>
          <a href="../index.php" class="small">← Home</a>
        </div>
      </form>
    </div>

    <p class="text-center text-muted small mt-3 mb-0">
      Students? <a href="../student/login.php">Login here</a>
    </p>
  </div>
</div>
<?php require '../includes/footer.php'; ?>