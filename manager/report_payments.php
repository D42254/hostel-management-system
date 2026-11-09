<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_manager();

$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? date('Y-m-d');
$status = $_GET['status'] ?? '';   // '', 'paid', 'pending', 'failed'
$type   = $_GET['type']   ?? '';
$csv    = isset($_GET['csv']);

$where  = "WHERE DATE(p.created_at) BETWEEN ? AND ?";
$params = [$from, $to];

if (in_array($status, ['paid','pending','failed'], true)) {
    $where   .= " AND p.status = ?";
    $params[] = $status;
}
if (in_array($type, ['rent','registration'], true)) {
    $where   .= " AND p.type = ?";
    $params[] = $type;
}

$sql = "
    SELECT p.*, s.full_name, s.reg_no
    FROM payments p
    JOIN students s ON s.id = p.student_id
    $where
    ORDER BY p.created_at DESC
    LIMIT 1000
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* ================= CSV EXPORT ================= */
if ($csv) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payments_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Date','Student','Reg No','Type','Month','Amount','Status','M-Pesa Ref','Result Code']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['created_at'], $r['full_name'], $r['reg_no'],
            $r['type'], $r['month'], $r['amount'], $r['status'],
            $r['mpesa_ref'], $r['result_code']
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Payment Report';
require '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">💳 Payment Report</h3>
  <div class="d-flex gap-2">
    <a href="reports.php" class="btn btn-sm btn-outline-secondary">← Back</a>
    <a href="?<?= http_build_query(array_merge($_GET, ['csv' => 1])) ?>"
       class="btn btn-sm btn-success">⬇ Export CSV</a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-dark">🖨 Print</button>
  </div>
</div>

<form method="get" class="card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-2">
      <label class="form-label small">From</label>
      <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label small">To</label>
      <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label small">Status</label>
      <select name="status" class="form-select">
        <option value=""        <?= $status === ''        ? 'selected' : '' ?>>All</option>
        <option value="paid"    <?= $status === 'paid'    ? 'selected' : '' ?>>Paid</option>
        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="failed"  <?= $status === 'failed'  ? 'selected' : '' ?>>Failed</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small">Type</label>
      <select name="type" class="form-select">
        <option value=""             <?= $type === ''             ? 'selected' : '' ?>>All</option>
        <option value="rent"         <?= $type === 'rent'         ? 'selected' : '' ?>>Rent</option>
        <option value="registration" <?= $type === 'registration' ? 'selected' : '' ?>>Registration</option>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100">Apply</button>
    </div>
  </div>
</form>

<div class="card">
  <div class="card-header"><?= count($rows) ?> payments · <?= $from ?> → <?= $to ?></div>
  <div class="table-responsive">
    <table class="table table-sm mb-0">
      <thead class="table-dark">
        <tr>
          <th>ID</th><th>Date</th><th>Student</th><th>Type</th><th>Month</th>
          <th class="text-end">Amount</th><th>Status</th><th>M-Pesa Ref</th><th>Code</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">No payments in this range.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
            <td><?= htmlspecialchars($r['full_name']) ?><br>
                <small class="text-muted"><?= htmlspecialchars($r['reg_no']) ?></small></td>
            <td><?= htmlspecialchars($r['type']) ?></td>
            <td><?= htmlspecialchars($r['month'] ?? '—') ?></td>
            <td class="text-end">KSh <?= number_format($r['amount']) ?></td>
            <td>
              <?php if ($r['status'] === 'paid'): ?><span class="badge bg-success">paid</span>
              <?php elseif ($r['status'] === 'failed'): ?><span class="badge bg-danger">failed</span>
              <?php else: ?><span class="badge bg-warning text-dark">pending</span><?php endif; ?>
            </td>
            <td><code><?= htmlspecialchars($r['mpesa_ref'] ?? '—') ?></code></td>
            <td><?= htmlspecialchars($r['result_code'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
@media print { nav, footer, form, .btn, a.btn { display: none !important; } }
</style>

<?php require '../includes/footer.php'; ?>