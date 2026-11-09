<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validators.php';
require_once __DIR__ . '/../includes/flash.php';
require_manager();

/* SMS config — unchanged */
define('SMS_USERNAME', 'sandbox');
define('SMS_API_KEY',  '');
define('SMS_ENABLED',  SMS_API_KEY !== '');

$title   = '';
$body    = '';
$target  = 'all';
$startsAt  = '';
$expiresAt = '';
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title     = trim($_POST['title']  ?? '');
    $body      = trim($_POST['body']   ?? '');
    $target    = $_POST['target'] ?? 'all';
    $studentIds= $_POST['student_ids'] ?? [];
    $startsAt  = trim($_POST['starts_at']  ?? '');
    $expiresAt = trim($_POST['expires_at'] ?? '');

    if (!v_required($title))    $errors['title'] = 'Title is required';
    if (strlen($title) > 100)   $errors['title'] = 'Title must be ≤100 characters';
    if (!v_required($body))     $errors['body']  = 'Message is required';
    if (strlen($body) > 500)    $errors['body']  = 'Message must be ≤500 characters';
    if (!in_array($target, ['all','selected'], true)) $target = 'all';

    if ($target === 'selected' && empty($studentIds)) {
        $errors['student_ids'] = 'Select at least one student';
    }

    // Validate datetime strings
    $startsTs  = $startsAt  !== '' ? strtotime($startsAt)  : null;
    $expiresTs = $expiresAt !== '' ? strtotime($expiresAt) : null;

    if ($startsAt !== '' && !$startsTs) {
        $errors['starts_at'] = 'Invalid start date/time';
    }
    if ($expiresAt !== '' && !$expiresTs) {
        $errors['expires_at'] = 'Invalid expiry date/time';
    }
    if ($startsTs && $expiresTs && $expiresTs <= $startsTs) {
        $errors['expires_at'] = 'Expiry must be after start';
    }

    if (!$errors) {
        // Store as MySQL DATETIME or NULL
        $startsSql  = $startsTs  ? date('Y-m-d H:i:s', $startsTs)  : null;
        $expiresSql = $expiresTs ? date('Y-m-d H:i:s', $expiresTs) : null;

        // Insert alert
        $stmt = $pdo->prepare("
            INSERT INTO alerts (manager_id, title, body, starts_at, expires_at, target)
            VALUES (?,?,?,?,?,?)
        ");
        $stmt->execute([
            $_SESSION['manager_id'], $title, $body,
            $startsSql, $expiresSql, $target
        ]);
        $alertId = (int)$pdo->lastInsertId();

        /* -------- recipients -------- */
        if ($target === 'all') {
            $recipients = $pdo->query("SELECT id, phone, full_name FROM students")->fetchAll();
        } else {
            $ids = array_values(array_filter(array_map('intval', (array)$studentIds)));
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $s  = $pdo->prepare("SELECT id, phone, full_name FROM students WHERE id IN ($in)");
                $s->execute($ids);
                $recipients = $s->fetchAll();
            } else {
                $recipients = [];
            }
        }

        /* -------- SMS (unchanged) -------- */
        $smsOk = false; $smsErr = null;
        if (SMS_ENABLED && !empty($recipients)) {
            try {
                require_once __DIR__ . '/../vendor/autoload.php';
                $AT  = new AfricasTalking\SDK\AfricasTalking(SMS_USERNAME, SMS_API_KEY);
                $sms = $AT->sms();
                $phones = [];
                foreach ($recipients as $r) {
                    $p = preg_replace('/\s+/', '', $r['phone']);
                    $p = preg_replace('/^0/', '+254', $p);
                    if (preg_match('/^\+254\d{9}$/', $p)) $phones[] = $p;
                }
                if ($phones) {
                    $sms->send(['to'=>$phones, 'message'=>$title . ': ' . $body]);
                    $smsOk = true;
                }
            } catch (Exception $e) { $smsErr = $e->getMessage(); }
        }

        $count = count($recipients);
        $window = $expiresSql
            ? " — shown until " . date('d M Y H:i', $expiresTs)
            : " — no expiry";

        if (SMS_ENABLED && $smsOk) {
            flash('success', "Alert sent to $count student(s) via in-app + SMS$window.");
        } elseif (SMS_ENABLED && $smsErr) {
            flash('warning', "Alert saved for $count student(s). SMS failed: " . substr($smsErr, 0, 100));
        } else {
            flash('success', "Alert saved for $count student(s) (in-app only)$window.");
        }

        header('Location: alerts.php'); exit;
    }
}

/* ---------- recent alerts, with live status ---------- */
$sent = $pdo->query("
    SELECT a.*, m.name AS mgr,
           CASE
             WHEN a.starts_at IS NOT NULL AND a.starts_at > NOW()            THEN 'scheduled'
             WHEN a.expires_at IS NOT NULL AND a.expires_at < NOW()          THEN 'expired'
             ELSE 'active'
           END AS live_status
    FROM alerts a
    LEFT JOIN managers m ON m.id = a.manager_id
    ORDER BY a.created_at DESC
    LIMIT 30
")->fetchAll();

$students = $pdo->query("SELECT id, reg_no, full_name FROM students ORDER BY full_name")->fetchAll();

$pageTitle = 'Alerts';
require '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">📢 Alerts</h3>
  <div class="small text-muted">
    SMS: <?= SMS_ENABLED
      ? '<span class="badge bg-success">Enabled</span>'
      : '<span class="badge bg-secondary">In-app only</span>' ?>
  </div>
</div>

<div class="row g-3">
  <!-- ================= Compose ================= -->
  <div class="col-md-5">
    <form method="post" class="card p-3" data-validate novalidate>
      <h6 class="mb-3">New alert</h6>

      <div class="mb-2">
        <label class="form-label">Title *</label>
        <input name="title"
               class="form-control<?= isset($errors['title']) ? ' is-invalid' : '' ?>"
               data-rules="required|max:100" maxlength="100"
               value="<?= htmlspecialchars($title) ?>" required>
        <?php if (isset($errors['title'])): ?>
          <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['title']) ?></div>
        <?php endif; ?>
      </div>

      <div class="mb-2">
        <label class="form-label">Message *</label>
        <textarea name="body"
                  class="form-control<?= isset($errors['body']) ? ' is-invalid' : '' ?>"
                  data-rules="required|max:500" rows="3" maxlength="500"
                  required><?= htmlspecialchars($body) ?></textarea>
        <?php if (isset($errors['body'])): ?>
          <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['body']) ?></div>
        <?php endif; ?>
      </div>

      <!-- ============ DATE / TIME WINDOW ============ -->
      <div class="row g-2 mb-2">
        <div class="col-6">
          <label class="form-label small mb-1">Show from (optional)</label>
          <input type="datetime-local" name="starts_at"
                 class="form-control form-control-sm<?= isset($errors['starts_at']) ? ' is-invalid' : '' ?>"
                 value="<?= htmlspecialchars($startsAt) ?>">
          <?php if (isset($errors['starts_at'])): ?>
            <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['starts_at']) ?></div>
          <?php endif; ?>
        </div>
        <div class="col-6">
          <label class="form-label small mb-1">Hide after (optional)</label>
          <input type="datetime-local" name="expires_at"
                 class="form-control form-control-sm<?= isset($errors['expires_at']) ? ' is-invalid' : '' ?>"
                 value="<?= htmlspecialchars($expiresAt) ?>">
          <?php if (isset($errors['expires_at'])): ?>
            <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['expires_at']) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="form-text small mb-2">
        Leave blank = show immediately / forever. Example: start now, expire in 3 days at 18:00.
      </div>
      <!-- ============================================== -->

      <div class="mb-2">
        <label class="form-label">Send to</label>
        <select name="target" class="form-select" id="targetSelect">
          <option value="all"      <?= $target==='all'?'selected':'' ?>>All students</option>
          <option value="selected" <?= $target==='selected'?'selected':'' ?>>Selected students</option>
        </select>
      </div>

      <div id="studentPicker"
           class="mb-2 <?= $target === 'selected' ? '' : 'd-none' ?>">
        <label class="form-label">Pick students</label>
        <div class="border rounded p-2" style="max-height:200px;overflow-y:auto">
          <?php foreach ($students as $s): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="student_ids[]"
                     value="<?= $s['id'] ?>" id="stu<?= $s['id'] ?>"
                     <?= in_array((int)$s['id'], array_map('intval', $_POST['student_ids'] ?? []), true) ? 'checked' : '' ?>>
              <label class="form-check-label small" for="stu<?= $s['id'] ?>">
                <?= htmlspecialchars($s['full_name']) ?>
                <span class="text-muted">(<?= htmlspecialchars($s['reg_no']) ?>)</span>
              </label>
            </div>
          <?php endforeach; ?>
          <?php if (!$students): ?><div class="text-muted small">No students.</div><?php endif; ?>
        </div>
        <?php if (isset($errors['student_ids'])): ?>
          <div class="text-danger small mt-1"><?= htmlspecialchars($errors['student_ids']) ?></div>
        <?php endif; ?>
      </div>

      <?php if (SMS_ENABLED): ?>
        <div class="alert alert-info small py-2 mb-2">
          📱 SMS will also be sent to valid +254 numbers of chosen students.
        </div>
      <?php endif; ?>

      <button class="btn btn-warning w-100">Send Alert</button>
    </form>
  </div>

  <!-- ================= Recent ================= -->
  <div class="col-md-7">
    <h6>Recent alerts (<?= count($sent) ?>)</h6>
    <?php if (!$sent): ?>
      <div class="alert alert-light border">No alerts yet.</div>
    <?php else: ?>
      <?php foreach ($sent as $a): ?>
        <?php
          $statusBadge = [
            'active'    => '<span class="badge bg-success">active</span>',
            'scheduled' => '<span class="badge bg-info text-dark">scheduled</span>',
            'expired'   => '<span class="badge bg-secondary">expired</span>',
          ][$a['live_status']] ?? '';
        ?>
        <div class="card mb-2">
          <div class="card-body py-2">
            <div class="d-flex justify-content-between">
              <strong><?= htmlspecialchars($a['title']) ?></strong>
              <div>
                <?= $statusBadge ?>
                <small class="text-muted ms-2"><?= htmlspecialchars($a['created_at']) ?></small>
              </div>
            </div>
            <div class="small mt-1"><?= nl2br(htmlspecialchars($a['body'])) ?></div>
            <div class="small text-muted mt-1">
              → <?= htmlspecialchars($a['target']) ?>
              · by <?= htmlspecialchars($a['mgr'] ?? '?') ?>
              <?php if ($a['starts_at']): ?>
                · from <?= htmlspecialchars($a['starts_at']) ?>
              <?php endif; ?>
              <?php if ($a['expires_at']): ?>
                · until <?= htmlspecialchars($a['expires_at']) ?>
              <?php else: ?>
                · no expiry
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
  // toggle student picker
  document.getElementById('targetSelect').addEventListener('change', function () {
    document.getElementById('studentPicker')
            .classList.toggle('d-none', this.value !== 'selected');
  });

  // smart default: when manager sets start, auto-set expiry +7 days (editable)
  const s = document.querySelector('[name="starts_at"]');
  const e = document.querySelector('[name="expires_at"]');
  s.addEventListener('change', () => {
    if (s.value && !e.value) {
      const d = new Date(s.value);
      d.setDate(d.getDate() + 7);
      // convert to local datetime-local format
      const pad = n => String(n).padStart(2, '0');
      e.value = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }
  });
</script>

<?php require '../includes/footer.php'; ?>