<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if (empty($_SESSION['temp_password_reveal'])) {
    header("Location: admin_managers.php"); exit;
}
$info = $_SESSION['temp_password_reveal'];
unset($_SESSION['temp_password_reveal']);   // show once only

$pageTitle = 'Manager Created';
require '../includes/header.php';
?>
<div class="alert alert-success">
  <h5>✅ Account created</h5>
  <p class="mb-1"><b>Name:</b> <?= htmlspecialchars($info['name']) ?></p>
  <p class="mb-1"><b>Email:</b> <?= htmlspecialchars($info['email']) ?></p>
  <p class="mb-1"><b>Role:</b> <?= htmlspecialchars($info['role']) ?></p>
  <p class="mb-1"><b>Temporary password:</b>
    <code id="tp" class="fs-5"><?= htmlspecialchars($info['password']) ?></code>
    <button class="btn btn-sm btn-outline-secondary ms-2"
            onclick="navigator.clipboard.writeText(document.getElementById('tp').innerText)">Copy</button>
  </p>
  <hr>
  <div class="mb-0 small">
    ⚠️ This password will <b>not</b> be shown again. Share it securely with the new user, then ask them to change it after first login.
  </div>
</div>
<a href="admin_managers.php" class="btn btn-primary">Back to list</a>
<?php require '../includes/footer.php'; ?>