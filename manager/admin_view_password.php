<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crypto.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$m  = $pdo->prepare("SELECT id, name, email, password_encrypted FROM managers WHERE id=?");
$m->execute([$id]);
$mgr = $m->fetch();
if (!$mgr) { die('Manager not found'); }

$revealed = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reveal'])) {
    if (!empty($mgr['password_encrypted'])) {
        $revealed = dec($mgr['password_encrypted']);
    }
}
$pageTitle = 'View Manager Password';
require '../includes/header.php';
?>
<h3>👁 View Password — <?= htmlspecialchars($mgr['name']) ?></h3>
<div class="alert alert-danger small">
  ⚠️ You are about to view another user's password. This is logged and discouraged. Prefer password resets.
</div>

<?php if ($revealed !== null): ?>
  <div class="alert alert-warning">
    <b>Password:</b> <code><?= htmlspecialchars($revealed) ?></code>
  </div>
<?php endif; ?>

<form method="post" class="card p-3" style="max-width:420px">
  <p><b>Name:</b> <?= htmlspecialchars($mgr['name']) ?><br>
     <b>Email:</b> <?= htmlspecialchars($mgr['email']) ?></p>
  <?php if (empty($mgr['password_encrypted'])): ?>
    <div class="alert alert-info mb-0">No stored password. User must reset via "Forgot password".</div>
  <?php else: ?>
    <button name="reveal" value="1" class="btn btn-danger">Reveal password</button>
  <?php endif; ?>
</form>
<a href="admin_managers.php" class="btn btn-link mt-2">← Back</a>
<?php require '../includes/footer.php'; ?>