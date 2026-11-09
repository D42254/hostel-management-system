<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_manager();

$filter = $_GET['filter'] ?? 'all'; // all | active | holiday | overdue | returning | nobed

$thisMonth = date('Y-m');

$base = "
    SELECT s.*,
           bk.status AS bstatus,
           r.room_no, b.bed_label,
           (SELECT COUNT(*) FROM payments p
            WHERE p.student_id=s.id AND p.type='rent'
              AND p.month=? AND p.status='paid') AS paid_rent
    FROM students s
    LEFT JOIN bookings bk ON bk.student_id=s.id AND bk.status='active'
    LEFT JOIN beds  b ON b.id=bk.bed_id
    LEFT JOIN rooms r ON r.id=b.room_id
";
$params = [$thisMonth];
$where  = [];

switch ($filter) {
    case 'active':    $where[] = "bk.status='active'";               break;
    case 'holiday':   $where[] = "s.on_holiday=1";                   break;
    case 'returning': $where[] = "s.is_returning=1";                 break;
    case 'nobed':     $where[] = "bk.id IS NULL";                    break;
    case 'overdue':   $where[] = "bk.status='active' AND paid_rent=0"; break;
}

$sql = $base . ($where ? " WHERE " . implode(' AND ', $where) : '') . " ORDER BY s.full_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Student Report';
require '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">👥 Student Report</h3>
  <div class="d-flex gap-2">
    <a href="reports.php" class="btn btn-sm btn-outline-secondary">← Back</a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-dark">🖨 Print</button>
  </div>
</div>

<div class="mb-3 d-flex gap-2 flex-wrap">
  <?php
  $tabs = [
    'all'       => 'All',
    'active'    => 'Active (with bed)',
    'overdue'   => 'Overdue rent',
    'holiday'   => 'On holiday',
    'returning' => 'Returning',
    'nobed'     => 'No bed',
  ];
  foreach ($tabs as $key => $label):
    $cls = $filter === $key ? 'btn-primary' : 'btn-outline-primary';
  ?>
    <a href="?filter=<?= $key ?>" class="btn btn-sm <?= $cls ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header"><?= count($rows) ?> students · <?= htmlspecialchars($tabs[$filter] ?? 'All') ?></div>
  <div class="table-responsive">
    <table class="table table-sm mb-0">
      <thead class="table-dark">
        <tr>
          <th>Reg No</th><th>Name</th><th>Phone</th><th>County</th>
          <th>Bed</th><th>Type</th><th>Status</th><th>Rent <?= $thisMonth ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No students in this filter.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $s): ?>
          <tr>
            <td><code><?= htmlspecialchars($s['reg_no']) ?></code></td>
            <td><?= htmlspecialchars($s['full_name']) ?></td>
            <td><?= htmlspecialchars($s['phone']) ?></td>
            <td><?= htmlspecialchars($s['location']) ?></td>
            <td><?= $s['room_no'] ? htmlspecialchars($s['room_no']) . ' · ' . htmlspecialchars($s['bed_label']) : '—' ?></td>
            <td><?= $s['is_returning'] ? '<span class="badge bg-info text-dark">Returning</span>' : '<span class="badge bg-secondary">New</span>' ?></td>
            <td>
              <?php if ($s['on_holiday']): ?><span class="badge bg-warning text-dark">Holiday</span>
              <?php elseif ($s['bstatus'] === 'active'): ?><span class="badge bg-success">Active</span>
              <?php else: ?><span class="badge bg-secondary">No bed</span><?php endif; ?>
            </td>
            <td>
              <?php if ((int)$s['paid_rent'] > 0): ?><span class="text-success">✅ paid</span>
              <?php elseif ($s['bstatus'] === 'active'): ?><span class="text-danger">❌ unpaid</span>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
@media print { nav, footer, .btn, a.btn { display: none !important; } }
</style>

<?php require '../includes/footer.php'; ?>