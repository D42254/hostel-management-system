<?php
require '../config/db.php';
require '../includes/auth.php';
require '../includes/validators.php';
require '../includes/flash.php';
require_student();

$me = current_student($pdo);
$existing = current_booking($pdo, $me['id']);

$errors = [];

// ---- handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing) {
    $bedId = (int)($_POST['bed_id'] ?? 0);
    if (!$bedId) $errors['bed_id'] = "Pick a bed";

    // Check bed exists and available (lock row)
    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $s = $pdo->prepare("SELECT * FROM beds WHERE id = ? FOR UPDATE");
            $s->execute([$bedId]);
            $bed = $s->fetch();
            if (!$bed)                                throw new Exception("Bed not found");
            if ($bed['status'] !== 'available')       throw new Exception("Bed already taken");

            // insert booking
            $ins = $pdo->prepare("INSERT INTO bookings (student_id, bed_id, start_date, status)
                                  VALUES (?,?,CURDATE(),'active')");
            $ins->execute([$me['id'], $bedId]);

            // mark bed occupied
            $pdo->prepare("UPDATE beds SET status='occupied' WHERE id=?")
                ->execute([$bedId]);

            // booking also cancels holiday flag
            $pdo->prepare("UPDATE students SET on_holiday=0 WHERE id=?")
                ->execute([$me['id']]);

            $pdo->commit();
            flash('success', 'Bed booked successfully!');
            header("Location: dashboard.php"); exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['general'] = $e->getMessage();
        }
    }
}

// ---- load rooms + beds ----
$rooms = $pdo->query("
    SELECT r.*,
           COUNT(b.id) AS total,
           SUM(b.status='available') AS free
    FROM rooms r
    LEFT JOIN beds b ON b.room_id = r.id
    GROUP BY r.id
    HAVING free > 0
    ORDER BY r.capacity, r.room_no
")->fetchAll();

$selectedRoom = (int)($_GET['room'] ?? ($rooms[0]['id'] ?? 0));
$bedStmt = $pdo->prepare("SELECT * FROM beds WHERE room_id = ? ORDER BY position DESC, bed_label");
$bedStmt->execute([$selectedRoom]);
$bedsInRoom = $bedStmt->fetchAll();

$pageTitle = 'Book a Bed';
require '../includes/header.php';
?>
<h3 class="mb-3">🛏 Book a Bed</h3>

<?php if ($existing): ?>
  <div class="alert alert-info">
    You already have an active bed:
    <b>Room <?= htmlspecialchars($existing['room_no']) ?> · Bed <?= htmlspecialchars($existing['bed_label']) ?></b>
    (<?= ucfirst($existing['position']) ?>, KSh <?= number_format($existing['price']) ?>/month).
    <a href="holiday.php" class="alert-link">Go on holiday</a> to release it.
  </div>
<?php else: ?>

  <?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-header">Step 1 · Choose room type</div>
    <div class="card-body">
      <div class="row g-2">
        <?php foreach ($rooms as $r): ?>
          <div class="col-md-3">
            <a href="?room=<?= $r['id'] ?>"
               class="btn w-100 text-start <?= $r['id']==$selectedRoom ? 'btn-primary' : 'btn-outline-primary' ?>">
              <strong><?= $r['capacity'] ?>-bed</strong> · KSh <?= number_format($r['price']) ?><br>
              <small><?= $r['free'] ?> free · Room <?= htmlspecialchars($r['room_no']) ?></small>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <form method="post" class="card p-3" data-validate novalidate>
    <div class="card-header bg-transparent px-0">Step 2 · Pick a bed (upper or lower)</div>
    <input type="hidden" name="room_id" value="<?= $selectedRoom ?>">

    <div class="row g-2 mt-1">
      <?php if (!$bedsInRoom): ?>
        <div class="alert alert-warning mb-0">No beds in this room.</div>
      <?php else: ?>
        <?php foreach ($bedsInRoom as $b): ?>
          <?php $taken = $b['status'] !== 'available'; ?>
          <div class="col-6 col-md-3">
            <label class="w-100">
              <input type="radio" name="bed_id" value="<?= $b['id'] ?>"
                     class="btn-check" <?= $taken ? 'disabled' : '' ?>
                     <?= (($_POST['bed_id'] ?? '') == $b['id']) ? 'checked' : '' ?>
                     data-rules="required">
              <div class="p-3 rounded text-center border <?= $taken ? 'bg-light text-muted' : 'bed-available' ?>"
                   style="cursor:<?= $taken ? 'not-allowed' : 'pointer' ?>">
                <div class="fw-bold"><?= htmlspecialchars($b['bed_label']) ?></div>
                <div class="small"><?= ucfirst($b['position']) ?></div>
                <div class="small"><?= $taken ? '🚫 Taken' : '✅ Free' ?></div>
              </div>
            </label>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if (!empty($errors['bed_id'])): ?>
      <div class="text-danger small mt-2"><?= htmlspecialchars($errors['bed_id']) ?></div>
    <?php endif; ?>

    <button class="btn btn-primary mt-3" <?= !$bedsInRoom ? 'disabled' : '' ?>>Confirm Booking</button>
  </form>
<?php endif; ?>

<?php require '../includes/footer.php'; ?>