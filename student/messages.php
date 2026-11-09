<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_student();

$me = current_student($pdo);
if (!$me) { header('Location: login.php'); exit; }

/* Pick the first manager as default chat partner.
   (If you later have multiple, this would become a selector.) */
$mgr = $pdo->query("SELECT id, name FROM managers WHERE is_active=1 ORDER BY role DESC, id LIMIT 1")->fetch();
$managerId = (int)($mgr['id'] ?? 0);

$pageTitle = 'Messages';
require '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">💬 Messages</h3>
  <a href="call.php" class="btn btn-sm btn-outline-dark">📹 Call Manager</a>
</div>

<?php if (!$mgr): ?>
  <div class="alert alert-warning">No manager available to chat with yet.</div>
<?php else: ?>

<p class="text-muted small mb-2">
  Chatting with <b><?= htmlspecialchars($mgr['name']) ?></b> (management).
</p>

<div class="card">
  <div class="card-body" id="chatBox"
       style="height:420px;overflow-y:auto;background:#fafafa">
    <div class="text-muted text-center small py-3">Loading…</div>
  </div>
  <div class="card-footer">
    <form id="chatForm" class="d-flex gap-2">
      <input id="chatInput" class="form-control"
             placeholder="Type a message…" autocomplete="off">
      <button class="btn btn-primary">Send</button>
    </form>
  </div>
</div>

<script>
(function () {
  const chatBox   = document.getElementById('chatBox');
  const form      = document.getElementById('chatForm');
  const input     = document.getElementById('chatInput');
  const managerId = <?= $managerId ?>;
  let   lastId    = 0;
  let   isFirst   = true;

  function esc(s) {
    return String(s).replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
  }

  function render(msg) {
    const isMe = msg.sender_type === 'student';
    const wrap = document.createElement('div');
    wrap.className = 'mb-2 d-flex ' + (isMe ? 'justify-content-end' : 'justify-content-start');
    wrap.innerHTML =
      '<div class="p-2 rounded ' + (isMe ? 'bg-primary text-white' : 'bg-white border') +
      '" style="max-width:75%">' +
        '<div class="small fw-bold">' + esc(msg.sender_name || '') + '</div>' +
        '<div>' + esc(msg.body).replace(/\n/g, '<br>') + '</div>' +
        '<div class="small opacity-75 text-end">' + (msg.created_at || '').slice(11,16) + '</div>' +
      '</div>';
    chatBox.appendChild(wrap);
  }

  async function load() {
    try {
      const url = '../api/chat_fetch.php?after=' + lastId +
                  (managerId ? '&with_id=' + managerId : '');
      const r = await fetch(url).then(r => r.json());
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
      body: new URLSearchParams({ body, to_type: 'manager', to_id: managerId })
    });
    load();
  });

  load();
  setInterval(load, 3000);
})();
</script>

<?php endif; ?>
<?php require '../includes/footer.php'; ?>