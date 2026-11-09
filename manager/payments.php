<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_manager();

$rows = $pdo->query("
    SELECT p.*, s.full_name, s.reg_no
    FROM payments p JOIN students s ON s.id = p.student_id
    ORDER BY p.created_at DESC LIMIT 200
")->fetchAll();

$pageTitle = 'Payments';
require '../includes/header.php';
?>
<h3>💳 Payments (<?= count($rows) ?>)</h3>
<div class="table-responsive">
  <table class="table table-sm table-hover">
    <thead class="table-dark"><tr><th>Date</th><th>Student</th><th>Type</th><th>Month</th><th>Amount</th><th>Status</th><th>Ref</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['created_at']) ?></td>
          <td><?= htmlspecialchars($r['reg_no']) ?> — <?= htmlspecialchars($r['full_name']) ?></td>
          <td><?= htmlspecialchars($r['type']) ?></td>
          <td><?= htmlspecialchars($r['month'] ?? '—') ?></td>
          <td>KSh <?= number_format($r['amount']) ?></td>
          <td>
            <?php if ($r['status']==='paid'): ?><span class="badge bg-success">Paid</span>
            <?php elseif ($r['status']==='failed'): ?><span class="badge bg-danger">Failed</span>
            <?php else: ?><span class="badge bg-warning text-dark">Pending</span><?php endif; ?>
          </td>
          <td><code><?= htmlspecialchars($r['mpesa_ref'] ?? '—') ?></code></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require '../includes/footer.php'; ?>