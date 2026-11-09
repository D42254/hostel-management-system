<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_admin();

// toggle active / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id && $id !== $_SESSION['manager_id']) {
        if ($action === 'toggle') {
            $pdo->prepare("UPDATE managers SET is_active = 1 - is_active WHERE id=?")->execute([$id]);
            flash('success', 'Status toggled');
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM managers WHERE id=?")->execute([$id]);
            flash('success', 'Manager deleted');
        }
    } else {
        flash('danger', "You can't modify your own account here.");
    }
    header('Location: admin_managers.php'); exit;
}

$rows = $pdo->query("SELECT * FROM managers ORDER BY role DESC, name")->fetchAll();

$pageTitle = 'Managers & Admins';
require '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3>👥 Managers & Admins (<?= count($rows) ?>)</h3>
  <a href="admin_register_manager.php" class="btn btn-primary">➕ Register new</a>
</div>

<div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead class="table-dark">
      <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['name']) ?></td>
          <td><?= htmlspecialchars($r['email']) ?></td>
          <td>
            <?php if ($r['role']==='admin'): ?>
              <span class="badge bg-danger">Admin</span>
            <?php else: ?>
              <span class="badge bg-secondary">Manager</span>
            <?php endif; ?>
          </td>
          <td>
            <?= $r['is_active'] ? '<span class="badge bg-success">Active</span>'
                                : '<span class="badge bg-warning text-dark">Disabled</span>' ?>
          </td>
          <td class="small"><?= htmlspecialchars($r['created_at']) ?></td>
          <td>
            <?php if ((int)$r['id'] !== (int)$_SESSION['manager_id']): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <input type="hidden" name="action" value="toggle">
                <button class="btn btn-sm btn-outline-warning">
                  <?= $r['is_active'] ? 'Disable' : 'Enable' ?>
                </button>
              </form>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this account?')">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <button class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
              <a href="admin_view_password.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-dark">👁 View</a>
            <?php else: ?>
              <span class="text-muted small">(you)</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require '../includes/footer.php'; ?>