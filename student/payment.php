<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_student();

$me = current_student($pdo);
$bk = current_booking($pdo, $me['id']);

$pageTitle = 'Payments';
require '../includes/header.php';
?>
<h3>💳 Payments</h3>

<div class="row g-3 mt-2">

  <!-- REGISTRATION FEE -->
  <div class="col-md-6"><div class="card p-3 h-100">
    <h6>Registration Fee</h6>
    <?php if ($me['is_returning']): ?>
      <p class="text-muted">You are a returning student — exempt.</p>
    <?php elseif ($me['registration_paid']): ?>
      <p class="text-success">✅ Paid</p>
    <?php else: ?>
      <p>Amount: <b>KSh 1,000</b> (one-time)</p>
      <button class="btn btn-primary pay-btn" data-type="registration" data-amount="1000">Pay via M-Pesa</button>
    <?php endif; ?>
  </div></div>

  <!-- RENT -->
  <div class="col-md-6"><div class="card p-3 h-100">
    <h6>Monthly Rent</h6>
    <?php if (!$bk): ?>
      <p class="text-muted">Book a bed first to pay rent.</p>
    <?php else: ?>
      <p>Your bed: <b>Room <?= htmlspecialchars($bk['room_no']) ?> · <?= htmlspecialchars($bk['bed_label']) ?></b></p>
      <p>Amount: <b>KSh <?= number_format($bk['price']) ?></b>/month</p>
      <div class="mb-2">
        <label class="form-label small">For month</label>
        <input type="month" class="form-control form-control-sm" id="rentMonth" value="<?= date('Y-m') ?>">
      </div>
      <button class="btn btn-success pay-btn" data-type="rent" data-amount="<?= $bk['price'] ?>">Pay Rent via M-Pesa</button>
    <?php endif; ?>
  </div></div>
</div>

<!-- Recent payments -->
<h5 class="mt-4">Recent Payments</h5>
<?php
$stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id=? ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$me['id']]);
$rows = $stmt->fetchAll();
?>
<div class="table-responsive">
  <table class="table table-sm">
    <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Month</th><th>Amount</th><th>Status</th><th>Receipt</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['created_at']) ?></td>
          <td><?= htmlspecialchars($r['type']) ?></td>
          <td><?= htmlspecialchars($r['month'] ?? '—') ?></td>
          <td>KSh <?= number_format($r['amount']) ?></td>
          <td>
            <?php if ($r['status']==='paid'): ?><span class="badge bg-success">Paid</span>
            <?php elseif ($r['status']==='failed'): ?><span class="badge bg-danger">Failed</span>
            <?php else: ?><span class="badge bg-warning text-dark">Pending</span><?php endif; ?>
          </td>
          <td>
            <?php if ($r['status']==='paid'): ?>
              <a href="receipt.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-dark" target="_blank">View</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Payment modal / status -->
<div class="modal fade" id="payModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">M-Pesa Payment</h5></div>
    <div class="modal-body" id="payBody">
      <p>Sending STK push…</p>
      <div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-bs-dismiss="modal" id="closeModalBtn" disabled>Close</button>
    </div>
  </div></div>
</div>

<script>
document.querySelectorAll('.pay-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    const type = btn.dataset.type;
    const monthInput = document.getElementById('rentMonth');
    const month = type === 'rent' ? (monthInput ? monthInput.value : '') : '';

    const modalEl = document.getElementById('payModal');
    const modal = new bootstrap.Modal(modalEl);
    document.getElementById('payBody').innerHTML =
      '<p>📲 Sending STK push to your phone…</p><div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div></div>';
    document.getElementById('closeModalBtn').disabled = true;
    modal.show();

    const fd = new FormData();
    fd.append('type', type);
    if (month) fd.append('month', month);

    const res = await fetch('../api/mpesa_stk.php', { method:'POST', body:fd });
    const json = await res.json();

    if (!json.ok) {
      document.getElementById('payBody').innerHTML =
        '<div class="alert alert-danger mb-0">'+ (json.error || 'Failed to start payment') +'</div>';
      document.getElementById('closeModalBtn').disabled = false;
      return;
    }

    document.getElementById('payBody').innerHTML =
      '<p>✅ STK push sent. <b>Enter your M-Pesa PIN on your phone.</b></p>' +
      '<p class="small text-muted">Waiting for confirmation…</p>' +
      '<div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div></div>';

    // Poll every 3s for up to 90s
    const paymentId = json.payment_id;
    let elapsed = 0;
    const timer = setInterval(async () => {
      elapsed += 3;
      const st = await fetch('../api/mpesa_status.php?payment_id=' + paymentId).then(r=>r.json());
      if (st.status === 'paid') {
        clearInterval(timer);
        document.getElementById('payBody').innerHTML =
          '<div class="alert alert-success mb-0">✅ Payment received! Receipt: <b>'+(st.receipt||'')+'</b></div>';
        document.getElementById('closeModalBtn').disabled = false;
        setTimeout(()=>location.reload(), 2000);
      } else if (st.status === 'failed') {
        clearInterval(timer);
        document.getElementById('payBody').innerHTML =
          '<div class="alert alert-danger mb-0">❌ Payment failed: '+(st.reason||'Cancelled or timed out')+'</div>';
        document.getElementById('closeModalBtn').disabled = false;
      } else if (elapsed >= 90) {
        clearInterval(timer);
        document.getElementById('payBody').innerHTML =
          '<div class="alert alert-warning mb-0">⏳ Timed out waiting. If you paid, check "Recent Payments" above.</div>';
        document.getElementById('closeModalBtn').disabled = false;
      }
    }, 3000);
  });
});
</script>
<?php require '../includes/footer.php'; ?>