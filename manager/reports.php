<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_manager();

/* ============================================================
   SUMMARY METRICS
   ============================================================ */
$totalStudents    = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$returningCount   = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE is_returning=1")->fetchColumn();
$onHoliday        = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE on_holiday=1")->fetchColumn();
$withBed          = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='active'")->fetchColumn();

$totalBeds        = (int)$pdo->query("SELECT COUNT(*) FROM beds")->fetchColumn();
$occupiedBeds     = (int)$pdo->query("SELECT COUNT(*) FROM beds WHERE status='occupied'")->fetchColumn();
$availableBeds    = $totalBeds - $occupiedBeds;
$occupancyRate    = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0;

$thisMonth        = date('Y-m');
$revenueThisMonth = (int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments
                                      WHERE status='paid' AND month='$thisMonth'")->fetchColumn();
$revenueAllTime   = (int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments
                                      WHERE status='paid'")->fetchColumn();
$pendingCount     = (int)$pdo->query("SELECT COUNT(*) FROM payments
                                      WHERE status='pending'")->fetchColumn();

/* students without a paid rent for current month (only those with a bed) */
$overdueCount = (int)$pdo->query("
    SELECT COUNT(*) FROM bookings b
    WHERE b.status='active'
      AND NOT EXISTS (
        SELECT 1 FROM payments p
        WHERE p.student_id=b.student_id
          AND p.type='rent' AND p.month='$thisMonth' AND p.status='paid'
      )
")->fetchColumn();

/* ============================================================
   MONTHLY REVENUE — last 6 months
   ============================================================ */
$rev = $pdo->query("
    SELECT month,
           SUM(CASE WHEN type='rent'         THEN amount ELSE 0 END) AS rent,
           SUM(CASE WHEN type='registration' THEN amount ELSE 0 END) AS reg,
           SUM(amount) AS total
    FROM payments
    WHERE status='paid' AND month IS NOT NULL
    GROUP BY month
    ORDER BY month DESC
    LIMIT 6
")->fetchAll();
$rev = array_reverse($rev); // oldest first

$pageTitle = 'Reports';
require '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">📊 Reports</h3>
  <div class="small text-muted">Updated <?= date('d M Y H:i') ?></div>
</div>

<!-- ================= SUMMARY CARDS ================= -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card p-3 h-100 border-start border-primary border-4">
      <div class="small text-muted">Students</div>
      <div class="h3 mb-0"><?= $totalStudents ?></div>
      <div class="small text-muted">
        <?= $withBed ?> with bed · <?= $returningCount ?> returning · <?= $onHoliday ?> on holiday
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card p-3 h-100 border-start border-success border-4">
      <div class="small text-muted">Occupancy</div>
      <div class="h3 mb-0"><?= $occupancyRate ?>%</div>
      <div class="small text-muted">
        <?= $occupiedBeds ?> occupied · <?= $availableBeds ?> free · <?= $totalBeds ?> total
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card p-3 h-100 border-start border-warning border-4">
      <div class="small text-muted">Revenue — <?= $thisMonth ?></div>
      <div class="h3 mb-0">KSh <?= number_format($revenueThisMonth) ?></div>
      <div class="small text-muted">All-time: KSh <?= number_format($revenueAllTime) ?></div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card p-3 h-100 border-start border-danger border-4">
      <div class="small text-muted">Attention</div>
      <div class="h3 mb-0 text-danger"><?= $overdueCount ?></div>
      <div class="small text-muted">
        overdue rent · <?= $pendingCount ?> pending payments
      </div>
    </div>
  </div>
</div>

<!-- ================= QUICK LINKS ================= -->
<div class="row g-3">
  <div class="col-md-3">
    <a href="report_revenue.php" class="card p-4 text-decoration-none h-100 text-dark">
      <div class="h5 mb-1">💰 Revenue</div>
      <div class="small text-muted">Payments by month, type & student</div>
    </a>
  </div>
  <div class="col-md-3">
    <a href="report_occupancy.php" class="card p-4 text-decoration-none h-100 text-dark">
      <div class="h5 mb-1">🛏 Occupancy</div>
      <div class="small text-muted">Beds used, free, per room</div>
    </a>
  </div>
  <div class="col-md-3">
    <a href="report_students.php" class="card p-4 text-decoration-none h-100 text-dark">
      <div class="h5 mb-1">👥 Students</div>
      <div class="small text-muted">Active, holiday, unpaid, returning</div>
    </a>
  </div>
  <div class="col-md-3">
    <a href="report_payments.php" class="card p-4 text-decoration-none h-100 text-dark">
      <div class="h5 mb-1">💳 Payments</div>
      <div class="small text-muted">Filterable list + CSV export</div>
    </a>
  </div>
</div>

<!-- ================= 6-MONTH REVENUE ================= -->
<div class="card mt-4">
  <div class="card-body">
    <h6 class="mb-3">Revenue — last 6 months</h6>
    <?php if (!$rev): ?>
      <div class="text-muted small">No paid payments yet.</div>
    <?php else: ?>
      <?php
        $max = 1;
        foreach ($rev as $r) $max = max($max, (int)$r['total']);
      ?>
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr><th>Month</th><th class="text-end">Rent</th><th class="text-end">Registration</th><th class="text-end">Total</th><th style="width:40%">Bar</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rev as $r): ?>
            <tr>
              <td><b><?= htmlspecialchars($r['month']) ?></b></td>
              <td class="text-end">KSh <?= number_format($r['rent']) ?></td>
              <td class="text-end">KSh <?= number_format($r['reg']) ?></td>
              <td class="text-end fw-bold">KSh <?= number_format($r['total']) ?></td>
              <td>
                <div class="progress" style="height:8px">
                  <div class="progress-bar bg-success"
                       style="width: <?= (int)round(($r['total'] / $max) * 100) ?>%"></div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php require '../includes/footer.php'; ?>