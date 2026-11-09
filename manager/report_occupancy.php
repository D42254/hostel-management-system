<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_manager();

$rooms = $pdo->query("
    SELECT r.id, r.room_no, r.capacity, r.price,
           COUNT(b.id)                               AS total_beds,
           SUM(b.status = 'occupied')                AS occupied,
           SUM(b.status = 'available')               AS available,
           SUM(b.status = 'reserved')                AS reserved,
           SUM(CASE WHEN b.status='occupied'
                    THEN (SELECT COUNT(*) FROM bookings bk
                          WHERE bk.bed_id=b.id AND bk.status='active')
                    ELSE 0 END)                       AS active_bookings
    FROM rooms r
    LEFT JOIN beds b ON b.room_id = r.id
    GROUP BY r.id
    ORDER BY r.capacity, r.room_no
")->fetchAll();

$grand = [
    'total'     => 0,
    'occupied'  => 0,
    'available' => 0,
    'reserved'  => 0,
];
foreach ($rooms as $r) {
    $grand['total']     += (int)$r['total_beds'];
    $grand['occupied']  += (int)$r['occupied'];
    $grand['available'] += (int)$r['available'];
    $grand['reserved']  += (int)$r['reserved'];
}
$rate = $grand['total'] > 0 ? round(($grand['occupied'] / $grand['total']) * 100, 1) : 0;

$pageTitle = 'Occupancy Report';
require '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">🛏 Occupancy Report</h3>
  <div class="d-flex gap-2">
    <a href="reports.php" class="btn btn-sm btn-outline-secondary">← Back</a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-dark">🖨 Print</button>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card p-3">
      <div class="small text-muted">Total beds</div>
      <div class="h3 mb-0"><?= $grand['total'] ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3 border-start border-danger border-4">
      <div class="small text-muted">Occupied</div>
      <div class="h3 mb-0 text-danger"><?= $grand['occupied'] ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3 border-start border-success border-4">
      <div class="small text-muted">Available</div>
      <div class="h3 mb-0 text-success"><?= $grand['available'] ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3">
      <div class="small text-muted">Occupancy</div>
      <div class="h3 mb-0"><?= $rate ?>%</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">Per room</div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-dark">
        <tr>
          <th>Room</th><th class="text-end">Capacity</th><th class="text-end">Price</th>
          <th class="text-end">Total beds</th><th class="text-end">Occupied</th>
          <th class="text-end">Available</th><th class="text-end">Reserved</th>
          <th style="width:22%">Fill</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rooms as $r): ?>
          <?php $pct = $r['total_beds'] > 0 ? round(($r['occupied'] / $r['total_beds']) * 100) : 0; ?>
          <tr>
            <td><b><?= htmlspecialchars($r['room_no']) ?></b></td>
            <td class="text-end"><?= (int)$r['capacity'] ?></td>
            <td class="text-end">KSh <?= number_format($r['price']) ?></td>
            <td class="text-end"><?= (int)$r['total_beds'] ?></td>
            <td class="text-end text-danger"><?= (int)$r['occupied'] ?></td>
            <td class="text-end text-success"><?= (int)$r['available'] ?></td>
            <td class="text-end text-muted"><?= (int)$r['reserved'] ?></td>
            <td>
              <div class="progress" style="height:8px">
                <div class="progress-bar <?= $pct > 80 ? 'bg-danger' : ($pct > 50 ? 'bg-warning' : 'bg-success') ?>"
                     style="width: <?= $pct ?>%"></div>
              </div>
              <small class="text-muted"><?= $pct ?>%</small>
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