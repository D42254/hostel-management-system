<?php
require '../config/db.php';
require '../includes/auth.php';
require_manager();

// fetch rooms + beds + current occupant
$rooms = $pdo->query("
    SELECT r.*,
           COUNT(b.id)                                          AS total_beds,
           SUM(b.status = 'occupied')                           AS occupied,
           SUM(b.status = 'available')                          AS available
    FROM rooms r
    LEFT JOIN beds b ON b.room_id = r.id
    GROUP BY r.id
    ORDER BY r.capacity, r.room_no
")->fetchAll();

// beds with occupant name (if any)
$beds = $pdo->query("
    SELECT b.*, r.room_no, r.capacity, r.price,
           s.full_name AS occupant, s.reg_no AS occupant_reg
    FROM beds b
    JOIN rooms r ON r.id = b.room_id
    LEFT JOIN bookings bk ON bk.bed_id = b.id AND bk.status = 'active'
    LEFT JOIN students s  ON s.id = bk.student_id
    ORDER BY r.capacity, r.room_no, b.position DESC, b.bed_label
")->fetchAll();

// group beds by room
$byRoom = [];
foreach ($beds as $b) $byRoom[$b['room_no']][] = $b;

$pageTitle = 'Rooms & Beds';
require '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">🛏 Rooms & Beds</h3>
  <div>
    <span class="badge bg-success me-1">Available</span>
    <span class="badge bg-danger me-1">Occupied</span>
    <span class="badge bg-secondary">Reserved</span>
  </div>
</div>

<div class="row g-3 mb-4">
  <?php foreach ($rooms as $r): ?>
    <div class="col-md-3">
      <div class="card p-3 h-100">
        <div class="d-flex justify-content-between">
          <strong>Room <?= htmlspecialchars($r['room_no']) ?></strong>
          <span class="badge bg-dark"><?= $r['capacity'] ?>-bed</span>
        </div>
        <div class="small text-muted">KSh <?= number_format($r['price']) ?>/month</div>
        <div class="mt-2">
          <span class="text-success"><?= (int)$r['available'] ?> free</span> ·
          <span class="text-danger"><?= (int)$r['occupied'] ?> taken</span>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<h5 class="mt-4">Bed Map</h5>
<?php foreach ($byRoom as $roomNo => $list): ?>
  <?php $cap = $list[0]['capacity']; $price = $list[0]['price']; ?>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <strong>Room <?= htmlspecialchars($roomNo) ?> · <?= $cap ?>-bed · KSh <?= number_format($price) ?></strong>
      <span class="small text-muted"><?= count($list) ?> beds</span>
    </div>
    <div class="card-body">
      <div class="row g-2">
        <?php foreach ($list as $b): ?>
          <?php
            $cls = $b['status'] === 'occupied' ? 'bg-danger text-white'
                 : ($b['status'] === 'reserved' ? 'bg-secondary text-white' : 'bg-success text-white');
          ?>
          <div class="col-6 col-md-3 col-lg-2">
            <div class="p-2 rounded <?= $cls ?> text-center small" style="min-height:64px">
              <div class="fw-bold"><?= htmlspecialchars($b['bed_label']) ?></div>
              <div style="font-size:.72rem;opacity:.9">
                <?= ucfirst($b['position']) ?>
              </div>
              <?php if ($b['occupant']): ?>
                <div style="font-size:.72rem" title="<?= htmlspecialchars($b['occupant_reg']) ?>">
                  <?= htmlspecialchars(mb_strimwidth($b['occupant'], 0, 14, '…')) ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php require '../includes/footer.php'; ?>