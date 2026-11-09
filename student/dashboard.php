<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_student();

$me = current_student($pdo);
if (!$me) { header("Location: login.php"); exit; }

$bk = current_booking($pdo, $me['id']);

/* ============================================================
   RENT DUE LOGIC
   ============================================================ */
$dueDay     = 5;
$today      = (int)date('j');
$thisMonth  = date('Y-m');

$paidThisMonth = false;
$lastPayment   = null;
$nextDueDate   = null;
$overdue       = false;

if ($bk) {
    $chk = $pdo->prepare("SELECT * FROM payments
                          WHERE student_id=? AND type='rent' AND month=? AND status='paid'
                          ORDER BY paid_at DESC LIMIT 1");
    $chk->execute([$me['id'], $thisMonth]);
    $lastPayment   = $chk->fetch() ?: null;
    $paidThisMonth = (bool)$lastPayment;

    if ($paidThisMonth) {
        // next month's due day
        $nextDueDate = date('Y-m-') . str_pad($dueDay, 2, '0', STR_PAD_LEFT);
        $nextDueDate = date('Y-m-d', strtotime($nextDueDate . ' +1 month'));
    } else {
        $nextDueDate = date('Y-m-') . str_pad($dueDay, 2, '0', STR_PAD_LEFT);
        $overdue     = ($today > $dueDay);
    }
}

/* ============================================================
   ALERTS — active now only (respect start/expiry window)
   ============================================================ */
$alerts = $pdo->query("
    SELECT * FROM alerts
    WHERE (starts_at  IS NULL OR starts_at  <= NOW())
      AND (expires_at IS NULL OR expires_at >  NOW())
    ORDER BY created_at DESC
    LIMIT 3
")->fetchAll();

/* ============================================================
   UNREAD MESSAGES
   ============================================================ */
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages
                       WHERE receiver_type='student' AND receiver_id=? AND is_read=0");
$stmt->execute([$me['id']]);
$unreadCount = (int)$stmt->fetchColumn();

/* ============================================================
   RECENT PAYMENTS (last 5)
   ============================================================ */
$hist = $pdo->prepare("SELECT * FROM payments WHERE student_id=?
                       ORDER BY created_at DESC LIMIT 5");
$hist->execute([$me['id']]);
$recentPayments = $hist->fetchAll();

$pageTitle = 'Dashboard';
require '../includes/header.php';
?>

<!-- ============================================================
     HEADER
     ============================================================ -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-1">👋 Welcome, <?= htmlspecialchars($me['full_name']) ?></h3>
    <div class="text-muted small">
      Reg No: <b><?= htmlspecialchars($me['reg_no']) ?></b> ·
      County: <?= htmlspecialchars($me['location']) ?> ·
      <?php if ($me['is_returning']): ?>
        <span class="badge bg-info text-dark">Returning</span>
      <?php else: ?>
        <span class="badge bg-secondary">First-time</span>
      <?php endif; ?>
      <?php if ($me['on_holiday']): ?>
        <span class="badge bg-warning text-dark">On holiday</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="mt-2 mt-md-0">
    <a href="messages.php" class="btn btn-outline-primary">
      💬 Messages
      <?php if ($unreadCount): ?><span class="badge bg-danger"><?= $unreadCount ?></span><?php endif; ?>
    </a>
  </div>
</div>

<!-- ============================================================
     BROADCAST ALERTS (with countdown when expires_at is set)
     ============================================================ -->
<?php foreach ($alerts as $a): ?>
  <div class="alert alert-info d-flex justify-content-between align-items-start">
    <div>
      <b>📢 <?= htmlspecialchars($a['title']) ?>:</b>
      <?= nl2br(htmlspecialchars($a['body'])) ?>
    </div>
    <div class="text-end ms-3 text-nowrap small">
      <?php if ($a['expires_at']): ?>
        <span class="text-muted d-block">until
          <?= htmlspecialchars(date('d M H:i', strtotime($a['expires_at']))) ?>
        </span>
        <span class="expiry-timer text-warning fw-bold"
              data-expires="<?= htmlspecialchars($a['expires_at']) ?>"></span>
      <?php else: ?>
        <span class="text-muted">no expiry</span>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<!-- ============================================================
     RENT DUE / PAID BANNER
     ============================================================ -->
<?php if ($bk && !$paidThisMonth): ?>
  <div class="alert <?= $overdue ? 'alert-danger' : 'alert-warning' ?> d-flex justify-content-between align-items-center">
    <div>
      ⏰ <b>Rent due:</b> KSh <?= number_format($bk['price']) ?> for <?= $thisMonth ?>
      (due on the <?= $dueDay ?>th<?= $overdue ? ' — <span class="fw-bold">overdue!</span>' : '' ?>)
    </div>
    <a href="payment.php" class="btn btn-sm <?= $overdue ? 'btn-danger' : 'btn-warning' ?>">Pay Now</a>
  </div>
<?php elseif ($bk && $paidThisMonth && $lastPayment): ?>
  <div class="alert alert-success d-flex justify-content-between align-items-center">
    <div>
      ✅ Rent paid for <b><?= $thisMonth ?></b>
      · Ref: <code><?= htmlspecialchars($lastPayment['mpesa_ref'] ?? '—') ?></code>
      · Next due: <b><?= $nextDueDate ?></b>
    </div>
    <a href="receipt.php?id=<?= $lastPayment['id'] ?>"
       class="btn btn-sm btn-outline-success" target="_blank">Receipt</a>
  </div>
<?php endif; ?>

<!-- ============================================================
     MAIN CARDS
     ============================================================ -->
<div class="row g-3 mt-2">

  <!-- Registration fee -->
  <div class="col-md-4">
    <div class="card p-3 h-100">
      <h6>Registration Fee</h6>
      <?php if ($me['is_returning']): ?>
        <p class="mb-1 text-muted">Returning student — exempt</p>
        <span class="badge bg-info text-dark">N/A</span>
      <?php elseif ($me['registration_paid']): ?>
        <p class="mb-1 text-success">✅ Paid</p>
        <span class="badge bg-success">KSh 1,000</span>
      <?php else: ?>
        <p class="mb-1">❌ Not paid (KSh 1,000)</p>
        <a href="payment.php?type=registration" class="btn btn-sm btn-primary">Pay Now</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- My bed -->
  <div class="col-md-4">
    <div class="card p-3 h-100">
      <h6>My Bed</h6>
      <?php if ($bk): ?>
        <p class="mb-1">
          Room <b><?= htmlspecialchars($bk['room_no']) ?></b> ·
          Bed <b><?= htmlspecialchars($bk['bed_label']) ?></b>
          (<?= ucfirst($bk['position']) ?>)
        </p>
        <p class="small text-muted mb-2">
          KSh <?= number_format($bk['price']) ?>/month · started <?= htmlspecialchars($bk['start_date']) ?>
        </p>
        <div class="d-flex gap-2 flex-wrap">
          <a href="payment.php" class="btn btn-sm btn-success">Pay Rent</a>
          <a href="holiday.php" class="btn btn-sm btn-outline-warning">Go on Holiday</a>
        </div>
      <?php else: ?>
        <p class="mb-1 text-muted">No bed booked</p>
        <a href="book.php" class="btn btn-sm btn-primary">Book a Bed</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Communication -->
  <div class="col-md-4">
    <div class="card p-3 h-100">
      <h6>Communication</h6>
      <p class="mb-1 small text-muted">Chat with management or start a video/voice call.</p>
      <div class="d-flex gap-2 flex-wrap">
        <a href="messages.php" class="btn btn-sm btn-outline-primary">
          💬 Chat <?php if ($unreadCount): ?><span class="badge bg-danger"><?= $unreadCount ?></span><?php endif; ?>
        </a>
        <a href="call.php" class="btn btn-sm btn-outline-dark">📹 Call Manager</a>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     RECENT PAYMENTS
     ============================================================ -->
<h5 class="mt-4">💳 Recent Payments</h5>
<?php if (!$recentPayments): ?>
  <div class="alert alert-light border">No payments yet.</div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead class="table-light">
        <tr><th>Date</th><th>Type</th><th>Month</th><th>Amount</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentPayments as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
            <td><?= htmlspecialchars(ucfirst($r['type'])) ?></td>
            <td><?= htmlspecialchars($r['month'] ?? '—') ?></td>
            <td>KSh <?= number_format($r['amount']) ?></td>
            <td>
              <?php if ($r['status'] === 'paid'): ?>
                <span class="badge bg-success">Paid</span>
              <?php elseif ($r['status'] === 'failed'): ?>
                <span class="badge bg-danger">Failed</span>
              <?php else: ?>
                <span class="badge bg-warning text-dark">Pending</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($r['status'] === 'paid'): ?>
                <a href="receipt.php?id=<?= $r['id'] ?>"
                   class="btn btn-sm btn-outline-dark" target="_blank">Receipt</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<!-- ============================================================
     QUICK HELP
     ============================================================ -->
<div class="card mt-4 p-3 bg-light">
  <h6 class="mb-2">💡 Quick help</h6>
  <ul class="mb-0 small">
    <li>Rent is due on the <b><?= $dueDay ?>th of every month</b>. Pay via M-Pesa to avoid penalties.</li>
    <li>Going home for a break? Click <b>Go on Holiday</b> — your bed is released and you keep "returning student" status.</li>
    <li>Facing an issue with your room? <a href="messages.php">Chat with management</a> or <a href="call.php">call them</a>.</li>
    <li>Alerts with a clock icon ⏳ will disappear automatically when their time ends.</li>
  </ul>
</div>

<!-- ============================================================
     LIVE COUNTDOWN FOR ALERTS  (Option 4)
     ============================================================ -->
<script>
(function () {
  document.querySelectorAll('.expiry-timer').forEach(function (el) {
    const target = new Date(el.dataset.expires.replace(' ', 'T')).getTime();

    function tick() {
      const left = target - Date.now();
      if (left <= 0) {
        el.textContent = 'expired';
        el.classList.remove('text-warning');
        el.classList.add('text-danger');
        // hide the whole alert after 2 s once expired
        setTimeout(() => {
          const box = el.closest('.alert');
          if (box) box.remove();
        }, 2000);
        return;
      }
      const d = Math.floor(left / 86400000);
      const h = Math.floor((left % 86400000) / 3600000);
      const m = Math.floor((left % 3600000) / 60000);
      const s = Math.floor((left % 60000) / 1000);

      let text = 'expires in ';
      if (d > 0)      text += d + 'd ';
      if (d > 0 || h > 0) text += h + 'h ';
      text += m + 'm';

      el.textContent = text;
      setTimeout(tick, 30000);   // update every 30 s (cheap)
    }
    tick();
  });
})();
</script>

<?php require '../includes/footer.php'; ?>