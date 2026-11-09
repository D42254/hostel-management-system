<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_manager();

$q = trim($_GET['q'] ?? '');

$sql = "
    SELECT s.*,
           bk.status AS bstatus, r.room_no, b.bed_label, b.position, r.price
    FROM students s
    LEFT JOIN bookings bk ON bk.student_id = s.id AND bk.status = 'active'
    LEFT JOIN beds  b ON b.id = bk.bed_id
    LEFT JOIN rooms r ON r.id = b.room_id
";
$params = [];
if ($q !== '') {
    $sql .= " WHERE s.full_name LIKE ? OR s.email LIKE ? OR s.reg_no LIKE ? OR s.phone LIKE ?";
    $params = ["%$q%","%$q%","%$q%","%$q%"];
}
$sql .= " ORDER BY s.created_at DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Students';
require '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h3 class="mb-0">👥 Students (<?= count($rows) ?>)</h3>
  <form class="d-flex" method="get">
    <input name="q" class="form-control me-2"
           placeholder="Search name / reg / phone / email"
           value="<?= htmlspecialchars($q) ?>">
    <button class="btn btn-outline-primary">Search</button>
  </form>
</div>

<div class="table-responsive">
  <table class="table table-sm table-hover align-middle">
    <thead class="table-dark">
      <tr>
        <th>Reg No</th>
        <th>Name</th>
        <th>Phone</th>
        <th>County</th>
        <th>Bed</th>
        <th>Type</th>
        <th>Reg Fee</th>
        <th>Status</th>
        <th class="text-end">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $s): ?>
        <tr>
          <td><code><?= htmlspecialchars($s['reg_no']) ?></code></td>
          <td><?= htmlspecialchars($s['full_name']) ?></td>
          <td><?= htmlspecialchars($s['phone']) ?></td>
          <td><?= htmlspecialchars($s['location']) ?></td>
          <td>
            <?php if ($s['room_no']): ?>
              R<?= htmlspecialchars($s['room_no']) ?> · <?= htmlspecialchars($s['bed_label']) ?>
              (<?= ucfirst($s['position']) ?>)
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($s['is_returning']): ?>
              <span class="badge bg-info text-dark">Returning</span>
            <?php else: ?>
              <span class="badge bg-secondary">New</span>
            <?php endif; ?>
          </td>
          <td>
            <?= $s['registration_paid']
                ? '✅'
                : ($s['is_returning'] ? '<span class="text-muted">N/A</span>' : '❌') ?>
          </td>
          <td>
            <?php if ($s['on_holiday']): ?>
              <span class="badge bg-warning text-dark">Holiday</span>
            <?php elseif ($s['bstatus'] === 'active'): ?>
              <span class="badge bg-success">Active</span>
            <?php else: ?>
              <span class="badge bg-secondary">No bed</span>
            <?php endif; ?>
          </td>
          <td class="text-end text-nowrap">
            <!-- 📞 Call -->
            <?php if ($s['bstatus'] === 'active'): ?>
              <a href="../manager/call.php?to=<?= (int)$s['id'] ?>"
                 class="btn btn-sm btn-outline-success"
                 title="Call <?= htmlspecialchars($s['full_name']) ?>">
                 📞 Call
              </a>
            <?php else: ?>
              <button class="btn btn-sm btn-outline-secondary" disabled
                      title="Student has no active bed">
                📞 Call
              </button>
            <?php endif; ?>

            <!-- 💬 Message -->
            <a href="../manager/messages.php?student=<?= (int)$s['id'] ?>"
               class="btn btn-sm btn-outline-primary"
               title="Message <?= htmlspecialchars($s['full_name']) ?>">
               💬
            </a>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (!$rows): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">No students found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require '../includes/footer.php'; ?>