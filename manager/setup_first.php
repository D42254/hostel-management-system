<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/validators.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/* -------- Lockout: as soon as at least one manager exists, this page is dead -------- */
$count = (int)$pdo->query("SELECT COUNT(*) FROM managers")->fetchColumn();
if ($count > 0) {
    http_response_code(403);
    die("⛔ Setup is locked. A manager already exists. Go to <a href='login.php'>login</a>.");
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $p1    = $_POST['password']  ?? '';
    $p2    = $_POST['password2'] ?? '';

    if (!v_required($name) || strlen($name) < 3) $errors['name']  = 'Name must be at least 3 characters';
    if (!v_email($email))                        $errors['email'] = 'Enter a valid email';
    if (!v_password($p1))                        $errors['password']  = 'Password ≥6 chars, letters + digits';
    if ($p1 !== $p2)                             $errors['password2'] = 'Passwords do not match';

    if (!$errors) {
        $hash = password_hash($p1, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO managers (name, email, role, password_hash, is_active, created_by)
            VALUES (?, ?, 'admin', ?, 1, NULL)
        ");
        $stmt->execute([$name, $email, $hash]);

        $_SESSION['manager_id']   = (int)$pdo->lastInsertId();
        $_SESSION['manager_name'] = $name;
        $_SESSION['manager_role'] = 'admin';

        header("Location: dashboard.php"); exit;
    }
}

$pageTitle = 'First-time Setup';
require '../includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card p-4 shadow-sm">
      <h4 class="mb-2 text-center">🚀 First-time Setup</h4>
      <p class="text-muted small text-center mb-3">
        No manager exists yet. Create the <b>first administrator</b> account.
        This page will lock itself immediately afterwards.
      </p>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">Fix the highlighted fields below.</div>
      <?php endif; ?>

      <form method="post" data-validate novalidate>
        <div class="mb-3">
          <label class="form-label">Full name *</label>
          <input name="name" class="form-control<?= isset($errors['name'])?' is-invalid':'' ?>"
                 data-rules="required|min:3" required
                 value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
          <?php if (isset($errors['name'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label">Email *</label>
          <input type="email" name="email" class="form-control<?= isset($errors['email'])?' is-invalid':'' ?>"
                 data-rules="required|email" required
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          <?php if (isset($errors['email'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label">Password *</label>
          <input type="password" name="password" class="form-control<?= isset($errors['password'])?' is-invalid':'' ?>"
                 data-rules="required|password" data-strength="1" required>
          <small class="text-muted">≥6 chars, letters & digits</small>
          <?php if (isset($errors['password'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($errors['password']) ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label">Confirm password *</label>
          <input type="password" name="password2" class="form-control<?= isset($errors['password2'])?' is-invalid':'' ?>"
                 data-rules="required|match:password" data-match="password" required>
          <?php if (isset($errors['password2'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($errors['password2']) ?></div><?php endif; ?>
        </div>

        <div class="alert alert-info small">
          This account will have the <b>admin</b> role and can register other managers.
          Delete this file after setup — or leave it; it locks itself.
        </div>

        <button class="btn btn-dark w-100">Create Admin & Log In</button>
      </form>
    </div>
  </div>
</div>
<?php require '../includes/footer.php'; ?>