<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_manager();

$managerId = (int)$_SESSION['manager_id'];

/* ---------- Sidebar: students with any message with this manager ---------- */
$stmt = $pdo->prepare("
    SELECT s.id, s.full_name, s.reg_no,
           (SELECT COUNT(*) FROM messages m2
            WHERE m2.sender_type='student' AND m2.sender_id=s.id
              AND m2.receiver_type='manager' AND m2.receiver_id=?
              AND m2.is_read=0) AS unread
    FROM students s
    WHERE EXISTS (
      SELECT 1 FROM messages m
      WHERE (m.sender_type='student' AND m.sender_id=s.id
             AND m.receiver_type='manager' AND m.receiver_id=?)
         OR (m.sender_type='manager' AND m.sender_id=?
             AND m.receiver_type='student' AND m.receiver_id=s.id)
    )
    ORDER BY s.full_name
");
$stmt->execute([$managerId, $managerId, $managerId]);
$students = $stmt->fetchAll();

/* ---------- Determine active student (?student=<id>) ---------- */
$activeId = (int)($_GET['student'] ?? ($students[0]['id'] ?? 0));
$active   = null;
foreach ($students as $s) if ((int)$s['id'] === $activeId) { $active = $s; break; }

/* ---------- Is the active student bookable? (for call gating) ---------- */
$activeHasBed = false;
if ($active) {
    $c = $pdo->prepare("SELECT 1 FROM bookings WHERE student_id=? AND status='active' LIMIT 1");
    $c->execute([$active['id']]);
    $activeHasBed = (bool)$c->fetchColumn();
}

$pageTitle = 'Messages';
require '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">💬 Messages</h3>
  <small class="text-muted">Auto-refreshes every 3s</small>
</div>

<div class="row g-3">

  <!-- ===================== Sidebar ===================== -->
  <div class="col-md-3">
    <div class="card">
      <div class="card-header py-2 small fw-bold">
        Students (<?= count($students) ?>)
      </div>
      <div class="list-group list-group-flush" style="max-height:600px;overflow-y:auto">
        <?php if (!$students): ?>
          <div class="p-3 text-muted small">No conversations yet.</div>
        <?php else: ?>
          <?php foreach ($students as $s): ?>
            <a href="?student=<?= (int)$s['id'] ?>"
               class="list-group-item list-group-item-action <?= (int)$s['id'] === $activeId ? 'active' : '' ?>">
              <div class="d-flex justify-content-between align-items-start">
                <div class="me-2">
                  <div class="small fw-bold"><?= htmlspecialchars($s['full_name']) ?></div>
                  <div class="small opacity-75"><?= htmlspecialchars($s['reg_no']) ?></div>
                </div>
                <div class="d-flex flex-column align-items-end gap-1">
                  <?php if ((int)$s['unread'] > 0): ?>
                    <span class="badge bg-danger"><?= (int)$s['unread'] ?></span>
                  <?php endif; ?>
                  <a href="../manager/call.php?to=<?= (int)$s['id'] ?>"
                     class="btn btn-sm btn-outline-success py-0 px-1"
                     title="Call <?= htmlspecialchars($s['full_name']) ?>"
                     onclick="event.stopPropagation();">📞</a>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ===================== Chat ===================== -->
  <div class="col-md-9">
    <?php if ($active): ?>
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
          <div>
            <strong><?= htmlspecialchars($active['full_name']) ?></strong>
            <span class="text-muted small">· <?= htmlspecialchars($active['reg_no']) ?></span>
          </div>
          <div class="d-flex gap-2">
            <?php if ($activeHasBed): ?>
              <a href="../manager/call.php?to=<?= (int)$active['id'] ?>"
                 class="btn btn-sm btn-success">
                 📞 Call
              </a>
            <?php else: ?>
              <button class="btn btn-sm btn-outline-secondary" disabled
                      title="Student has no active bed">📞 Call</button>
            <?php endif; ?>

            <a href="../manager/students.php?q=<?= urlencode($active['reg_no']) ?>"
               class="btn btn-sm btn-outline-dark"
               title="Open student record">
               👤 Profile
            </a>
          </div>
        </div>

        <div class="card-body" id="chatBox"
             style="height:420px;overflow-y:auto;background:#fafafa">
          <div class="text-muted text-center small py-3">Loading…</div>
        </div>

        <div class="card-footer">
          <form id="chatForm" class="d-flex gap-2">
            <input id="chatInput" class="form-control"
                   placeholder="Reply to <?= htmlspecialchars($active['full_name']) ?>…"
                   autocomplete="off">
            <button class="btn btn-dark">Send</button>
          </form>
        </div>
      </div>
    <?php else: ?>
      <div class="alert alert-info">
        Select a student from the left to start a conversation.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($active): ?>
<script>
(function () {
  const chatBox   = document.getElementById('chatBox');
  const form      = document.getElementById('chatForm');
  const input     = document.getElementById('chatInput');
  const studentId = <?= (int)$active['id'] ?>;
  let   lastId    = 0;
  let   isFirst   = true;

  function esc(s) {
    return String(s).replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
  }

  function render(msg) {
    const isMe = msg.sender_type === 'manager';
    const wrap = document.createElement('div');
    wrap.className = 'mb-2 d-flex ' + (isMe ? 'justify-content-end' : 'justify-content-start');
    wrap.innerHTML =
      '<div class="p-2 rounded ' + (isMe ? 'bg-dark text-white' : 'bg-white border') +
      '" style="max-width:75%">' +
        '<div class="small fw-bold">' + esc(msg.sender_name || '') + '</div>' +
        '<div>' + esc(msg.body).replace(/\n/g, '<br>') + '</div>' +
        '<div class="small opacity-75 text-end">' + (msg.created_at || '').slice(11,16) + '</div>' +
      '</div>';
    chatBox.appendChild(wrap);
  }

  async function load() {
    try {
      const r = await fetch('../api/chat_fetch.php?after=' + lastId + '&with_id=' + studentId)
                      .then(r => r.json());
      if (!r.ok) return;

      if (isFirst) {
        chatBox.innerHTML = '';
        isFirst = false;
        if (!r.messages.length) {
          chatBox.innerHTML = '<div class="text-muted text-center small py-3">No messages yet — say hi 👋</div>';
        }
      }
      for (const m of r.messages) {
        lastId = Math.max(lastId, +m.id);
        if (chatBox.firstElementChild && chatBox.firstElementChild.classList.contains('text-muted')) {
          chatBox.innerHTML = '';
        }
        render(m);
      }
      if (r.messages.length) chatBox.scrollTop = chatBox.scrollHeight;
    } catch (e) { /* silent */ }
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const body = input.value.trim();
    if (!body) return;
    input.value = '';
    await fetch('../api/chat_send.php', {
      method: 'POST',
      body: new URLSearchParams({ body, to_type: 'student', to_id: studentId })
    });
    load();
  });

  load();
  setInterval(load, 3000);
})();
</script>
<?php endif; ?>

<?php require '../includes/footer.php'; ?>