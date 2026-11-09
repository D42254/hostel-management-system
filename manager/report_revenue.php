<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_manager();

/* Filters */
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$type = $_GET['type'] ?? '';   // '', 'rent', 'registration'

$where  = "WHERE p.status='paid' AND DATE(p.created_at) BETWEEN ? AND ?";
$params = [$from, $to];

if (in_array($type, ['rent','registration'], true)) {
    $where   .= " AND p.type=?";
    $params[] = $type;
}

/* Per month summary */
$monthly = $pdo->prepare("
    SELECT COALESCE(month,'—') AS m,
           SUM(amount) AS total, COUNT(*) AS n
    FROM payments p
    $where
    GROUP BY month
    ORDER BY m DESC
");
$monthly->execute($params);
$monthlyRows = $monthly->fetchAll();

/* Per student summary */
$perStudent = $pdo->prepare("
    SELECT s.full_name, s.reg_no, SUM(p.amount) AS total, COUNT(*) AS n
    FROM payments p
    JOIN students s ON s.id = p.student_id
    $where
    GROUP BY s.id
    ORDER BY total DESC
");
$perStudent->execute($params);
$studentRows = $perStudent->fetchAll();

$grandTotal = array_sum(array_column($monthlyRows, 'total'));

$pageTitle = 'Revenue Report';
require '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">💰 Revenue Report</h3>
  <div class="d-flex gap-2">
    <a href="reports.php" class="btn btn-sm btn-outline-secondary">← Back to reports</a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-dark">🖨 Print</button>
  </div>
</div>

<form method="get" class="card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small">From</label>
      <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label small">To</label>
      <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label small">Type</label>
      <select name="type" class="form-select">
        <option value=""              <?= $type === ''             ? 'selected' : '' ?>>All</option>
        <option value="rent"          <?= $type === 'rent'         ? 'selected' : '' ?>>Rent only</option>
        <option value="registration"  <?= $type === 'registration' ? 'selected' : '' ?>>Registration only</option>
      </select>
    </div>
    <div class="col-md-3">
      <button class="btn btn-primary w-100">Apply</button>
    </div>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card p-3">
      <div class="small text-muted">Grand total</div>
      <div class="h3 mb-0">KSh <?= number_format($grandTotal) ?></div>
      <div class="small text-muted"><?= $from ?> → <?= $to ?></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header">By month</div>
      <table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>Month</th><th class="text-end">Payments</th><th class="text-end">Total</th></tr></thead>
        <tbody>
          <?php if (!$monthlyRows): ?>
            <tr><td colspan="3" class="text-center text-muted py-3">No data.</td></tr>
          <?php endif; ?>
          <?php foreach ($monthlyRows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['m']) ?></td>
              <td class="text-end"><?= (int)$r['n'] ?></td>
              <td class="text-end">KSh <?= number_format($r['total']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card">
      <div class="card-header">By student</div>
      <table class="table table-sm mb-0" style="max-height:400px;overflow:auto">
        <thead class="table-light"><tr><th>Student</th><th class="text-end">Payments</th><th class="text-end">Total</th></tr></thead>
        <tbody>
          <?php if (!$studentRows): ?>
            <tr><td colspan="3" class="text-center text-muted py-3">No data.</td></tr>
          <?php endif; ?>
          <?php foreach ($studentRows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['full_name']) ?><br>
                  <small class="text-muted"><?= htmlspecialchars($r['reg_no']) ?></small></td>
              <td class="text-end"><?= (int)$r['n'] ?></td>
              <td class="text-end">KSh <?= number_format($r['total']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>
@media print {
  nav, footer, form, .btn, a.btn { display: none !important; }
  body { background: #fff; }
  .card { border: 1px solid #ccc; }
}
</style>

<?php require '../includes/footer.php'; ?>