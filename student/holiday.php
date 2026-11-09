<?php
require '../config/db.php';
require '../includes/auth.php';
require '../includes/flash.php';
require_student();

$me = current_student($pdo);
$bk = current_booking($pdo, $me['id']);

$pageTitle = 'Go on Holiday';
require '../includes/header.php';
?>
<h3>🏖 Go on Holiday</h3>

<?php if (!$bk): ?>
  <div class="alert alert-warning">You don't have an active bed to release.</div>
<?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
  <?php
    $pdo->beginTransaction();
    try {
        // end booking
        $pdo->prepare("UPDATE bookings SET status='holiday', end_date=CURDATE() WHERE id=?")
            ->execute([$bk['id']]);

        // free bed
        $pdo->prepare("UPDATE beds SET status='available' WHERE id=?")
            ->execute([$bk['bed_id']]);

        // student: on holiday AND mark as returning
        $pdo->prepare("UPDATE students SET on_holiday=1, is_returning=1 WHERE id=?")
            ->execute([$me['id']]);

        $pdo->commit();
        flash('success', 'Bed released. When you come back, book again as a returning student (no registration fee).');
        header("Location: dashboard.php"); exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo '<div class="alert alert-danger">'.$e->getMessage().'</div>';
    }
  ?>
<?php endif; ?>

<?php if ($bk && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
  <div class="card p-3">
    <p>You are about to release:</p>
    <ul>
      <li><b>Room:</b> <?= htmlspecialchars($bk['room_no']) ?></li>
      <li><b>Bed:</b> <?= htmlspecialchars($bk['bed_label']) ?> (<?= ucfirst($bk['position']) ?>)</li>
      <li><b>Price:</b> KSh <?= number_format($bk['price']) ?>/month</li>
    </ul>
    <div class="alert alert-info mb-3">
      Once released, the bed becomes available to others. You'll be marked as a <b>returning student</b>
      so you won't pay the KSh 1,000 registration fee again.
    </div>
    <form method="post">
      <button class="btn btn-warning">Yes, I'm going on holiday</button>
      <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
    </form>
  </div>
<?php endif; ?>
<?php require '../includes/footer.php'; ?>