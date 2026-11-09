<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validators.php';
require_once __DIR__ . '/../includes/flash.php';
require_admin();

$errors = [];

/* ---------------- Handle POST ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $role  = $_POST['role'] ?? 'manager';

    // Two modes:
    //  - manual: admin sets the password
    //  - auto:   system generates a temp password (shown once)
    $mode  = $_POST['mode'] ?? 'auto';

    $p1 = $_POST['password']  ?? '';
    $p2 = $_POST['password2'] ?? '';

    // --- validation ---
    if (!v_required($name) || strlen($name) < 3)   $errors['name']  = 'Name must be at least 3 characters';
    if (!v_email($email))                          $errors['email'] = 'Enter a valid email';
    if (!in_array($role, ['manager','admin'], true)) $role = 'manager';
    if (!in_array($mode, ['auto','manual'], true))   $mode = 'auto';

    if ($mode === 'manual') {
        if (!v_password($p1)) $errors['password']  = 'Password ≥6 chars, letters + digits';
        if ($p1 !== $p2)      $errors['password2'] = 'Passwords do not match';
    }

    // --- duplicate email ---
    if (!$errors) {
        $s = $pdo->prepare("SELECT id FROM managers WHERE email = ?");
        $s->execute([$email]);
        if ($s->fetch()) $errors['email'] = 'That email is already registered';
    }

    // --- save ---
    if (!$errors) {
        // Decide password source
        if ($mode === 'manual') {
            $plainPassword = $p1;
            $showOnce      = false;
        } else {
            // strong temp password: 8 hex chars + 'A1' = 10 chars, letters+digits guaranteed
            $plainPassword = strtoupper(bin2hex(random_bytes(4))) . 'A1';
            $showOnce      = true;
        }

        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO managers (name, email, role, password_hash, is_active, created_by)
            VALUES (?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([
            $name, $email, $role, $hash,
            $_SESSION['manager_id'],
        ]);
        $newId = (int)$pdo->lastInsertId();

        if ($showOnce) {
            // stash temp password once — shown on the next page then wiped
            $_SESSION['temp_password_reveal'] = [
                'id'       => $newId,
                'name'     => $name,
                'email'    => $email,
                'role'     => $role,
                'password' => $plainPassword,
            ];
            header("Location: admin_created_manager.php"); exit;
        }

        flash('success', "Manager '$name' created successfully ($role).");
        header("Location: admin_managers.php"); exit;
    }
}

$pageTitle = 'Register Manager';
require '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">➕ Register new Manager / Admin</h3>
  <a href="admin_managers.php" class="btn btn-outline-secondary btn-sm">← Back to list</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">Please fix the highlighted fields below.</div>
<?php endif; ?>

<form method="post" class="card p-4" data-validate novalidate style="max-width:760px">

  <div class="row g-3">
    <!-- Name -->
    <div class="col-md-6">
      <label class="form-label">Full name *</label>
      <input name="name"
             class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>"
             data-rules="required|min:3"
             required
             value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      <?php if (isset($errors['name'])): ?>
        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['name']) ?></div>
      <?php endif; ?>
    </div>

    <!-- Email -->
    <div class="col-md-6">
      <label class="form-label">Email *</label>
      <input type="email" name="email"
             class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
             data-rules="required|email"
             required
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      <?php if (isset($errors['email'])): ?>
        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['email']) ?></div>
      <?php endif; ?>
    </div>

    <!-- Role -->
    <div class="col-md-6">
      <label class="form-label">Role *</label>
      <select name="role" class="form-select">
        <option value="manager" <?= ($_POST['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Manager</option>
        <option value="admin"   <?= ($_POST['role'] ?? '') === 'admin'   ? 'selected' : '' ?>>Admin</option>
      </select>
      <small class="text-muted">Admin can register other managers.</small>
    </div>

    <!-- Password mode -->
    <div class="col-md-6">
      <label class="form-label">Password mode *</label>
      <?php $mode = $_POST['mode'] ?? 'auto'; ?>
      <div class="form-check">
        <input class="form-check-input" type="radio" name="mode" id="modeAuto" value="auto"
               <?= $mode === 'auto' ? 'checked' : '' ?>>
        <label class="form-check-label" for="modeAuto">
          <b>Auto-generate</b> a temporary password (shown once)
        </label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="radio" name="mode" id="modeManual" value="manual"
               <?= $mode === 'manual' ? 'checked' : '' ?>>
        <label class="form-check-label" for="modeManual">
          <b>Set manually</b> — I'll type the password
        </label>
      </div>
    </div>
  </div>

  <!-- Manual password section -->
  <div id="manualPwBlock" class="row g-3 mt-2 <?= $mode === 'manual' ? '' : 'd-none' ?>">
    <div class="col-md-6">
      <label class="form-label">Password *</label>
      <input type="password" name="password"
             id="pw1"
             class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
             data-rules="password"
             data-strength="1"
             minlength="6">
      <small class="text-muted">≥6 characters, at least one letter and one digit</small>
      <?php if (isset($errors['password'])): ?>
        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['password']) ?></div>
      <?php endif; ?>
    </div>

    <div class="col-md-6">
      <label class="form-label">Confirm password *</label>
      <input type="password" name="password2"
             id="pw2"
             class="form-control<?= isset($errors['password2']) ? ' is-invalid' : '' ?>"
             data-rules="match:password"
             data-match="password"
             minlength="6">
      <?php if (isset($errors['password2'])): ?>
        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['password2']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Info box -->
  <div class="alert alert-info mt-4 mb-0 small">
    <b>Note:</b> passwords are stored as <b>bcrypt hashes</b> — they can never be viewed again
    (even by an admin) after creation. In <b>Auto</b> mode, the temp password is shown <b>exactly once</b>
    on the next screen, so copy it and give it to the new user immediately.
    They can change it anytime via <i>Forgot password</i>.
  </div>

  <div class="mt-3">
    <button class="btn btn-primary">Create Account</button>
    <a href="admin_managers.php" class="btn btn-outline-secondary ms-2">Cancel</a>
  </div>
</form>

<script>
  // Toggle manual password fields based on the radio mode
  document.querySelectorAll('input[name="mode"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      const block = document.getElementById('manualPwBlock');
      if (this.value === 'manual') {
        block.classList.remove('d-none');
        block.querySelectorAll('input[type=password]').forEach(i => i.required = true);
      } else {
        block.classList.add('d-none');
        block.querySelectorAll('input[type=password]').forEach(i => {
          i.required = false;
          i.value = '';
          i.classList.remove('is-invalid');
        });
      }
    });
  });

  // On page load: enforce required based on current mode
  (function () {
    const mode = document.querySelector('input[name="mode"]:checked').value;
    document.querySelectorAll('#manualPwBlock input[type=password]').forEach(i => {
      i.required = (mode === 'manual');
    });
  })();
</script>

<?php require '../includes/footer.php'; ?>