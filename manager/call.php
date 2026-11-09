<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_manager();

$managerId = (int)$_SESSION['manager_id'];

/* List students that have an active bed (only reasonable people to call) */
$students = $pdo->query("
    SELECT s.id, s.full_name, s.reg_no, s.phone
    FROM students s
    JOIN bookings b ON b.student_id = s.id AND b.status='active'
    ORDER BY s.full_name
")->fetchAll();

/* Pre-selected student if ?to= is given */
$toId = (int)($_GET['to'] ?? 0);

$pageTitle = 'Calls';
require '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">📞 Video / Voice Calls</h3>
  <small class="text-muted">Requires HTTPS (ngrok)</small>
</div>

<!-- ================== START A CALL ================== -->
<div class="card mb-3">
  <div class="card-body">
    <label class="form-label small">Call a student</label>
    <div class="d-flex gap-2 flex-wrap">
      <select id="studentSelect" class="form-select" style="max-width:320px">
        <option value="">— pick a student —</option>
        <?php foreach ($students as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= $toId === (int)$s['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['full_name']) ?>
            (<?= htmlspecialchars($s['reg_no']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <button id="startBtn" class="btn btn-success">📞 Call student</button>
      <button id="hangupBtn" class="btn btn-danger" disabled>📴 Hang Up</button>
    </div>
    <div id="status" class="small text-muted mt-2">Idle.</div>
  </div>
</div>

<!-- ============== INCOMING CALL BANNER ============== -->
<div id="incomingBanner" class="alert alert-warning d-none">
  <div class="d-flex justify-content-between align-items-center">
    <div>📲 <b>Incoming call</b> from <span id="callerName">a student</span></div>
    <div class="d-flex gap-2">
      <button id="acceptBtn"  class="btn btn-sm btn-success">✅ Accept</button>
      <button id="declineBtn" class="btn btn-sm btn-outline-danger">✖ Decline</button>
    </div>
  </div>
</div>

<!-- ================== VIDEO ================== -->
<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label small">You</label>
    <video id="localVideo"  autoplay muted playsinline class="w-100 rounded border bg-dark" style="min-height:260px"></video>
  </div>
  <div class="col-md-6">
    <label class="form-label small">Student</label>
    <video id="remoteVideo" autoplay playsinline class="w-100 rounded border bg-dark" style="min-height:260px"></video>
  </div>
</div>

<script>
const ICE = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };
const managerId = <?= $managerId ?>;

const localVideo  = document.getElementById('localVideo');
const remoteVideo = document.getElementById('remoteVideo');
const statusEl    = document.getElementById('status');
const startBtn    = document.getElementById('startBtn');
const hangupBtn   = document.getElementById('hangupBtn');
const studentSel  = document.getElementById('studentSelect');

const banner     = document.getElementById('incomingBanner');
const callerName = document.getElementById('callerName');
const acceptBtn  = document.getElementById('acceptBtn');
const declineBtn = document.getElementById('declineBtn');

let pc = null;
let localStream = null;
let currentCallId = null;
let currentPeerId = null;         // student id we're talking to
let outgoingOffer = null;         // when manager is the caller
let incomingOfferPayload = null;  // when manager is the callee
let pollTimer = null;

/* ---------------- media & peer helpers ---------------- */
async function initMedia() {
  if (localStream) return;
  localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
  localVideo.srcObject = localStream;
}

function createPeer() {
  pc = new RTCPeerConnection(ICE);
  localStream.getTracks().forEach(t => pc.addTrack(t, localStream));
  pc.ontrack = e => { remoteVideo.srcObject = e.streams[0]; };
  pc.onicecandidate = e => {
    if (e.candidate) sendSignal('candidate', JSON.stringify(e.candidate));
  };
  pc.onconnectionstatechange = () => {
    if (pc.connectionState === 'failed' || pc.connectionState === 'disconnected') {
      statusEl.textContent = 'Connection lost';
    }
  };
}

async function sendSignal(type, payload) {
  await fetch('../api/webrtc_signal.php', {
    method: 'POST',
    body: new URLSearchParams({
      call_id: currentCallId,
      to_type: 'student',
      to_id: currentPeerId,
      signal_type: type,
      payload
    })
  });
}

/* ---------------- OUTGOING (manager → student) ---------------- */
startBtn.addEventListener('click', async () => {
  const sid = parseInt(studentSel.value, 10);
  if (!sid) { alert('Pick a student first'); return; }

  try { await initMedia(); }
  catch (e) { alert('Camera/mic blocked. Use HTTPS and allow access.'); return; }

  currentPeerId = sid;
  currentCallId = 'call_m' + managerId + '_s' + sid + '_' + Date.now();

  createPeer();
  const offer = await pc.createOffer();
  await pc.setLocalDescription(offer);

  await sendSignal('offer', JSON.stringify(offer));

  startBtn.disabled = true;
  hangupBtn.disabled = false;
  statusEl.textContent = 'Ringing student… waiting for answer';
  pollOutgoing();
});

async function pollOutgoing() {
  if (pollTimer) clearInterval(pollTimer);
  pollTimer = setInterval(async () => {
    if (!currentCallId) return;
    const r = await fetch('../api/webrtc_signal.php?call_id=' +
                          encodeURIComponent(currentCallId)).then(x => x.json());
    if (!r.ok) return;
    for (const s of r.signals) {
      if (s.signal_type === 'answer' && pc.signalingState === 'have-local-offer') {
        await pc.setRemoteDescription(JSON.parse(s.payload));
        statusEl.textContent = 'Connected';
      } else if (s.signal_type === 'candidate') {
        try { await pc.addIceCandidate(JSON.parse(s.payload)); } catch (e) {}
      } else if (s.signal_type === 'hangup') {
        resetCall('Student hung up');
        return;
      }
    }
    await fetch('../api/webrtc_signal.php?call_id=' +
                encodeURIComponent(currentCallId) + '&consume=1');
  }, 2000);
}

/* ---------------- INCOMING (student → manager) ---------------- */
async function pollIncoming() {
  try {
    const r = await fetch('../api/webrtc_incoming.php').then(x => x.json());
    if (!r.ok) return;
    for (const s of r.signals) {
      if (s.signal_type === 'offer' && !currentCallId) {
        currentCallId = s.call_id;
        currentPeerId = +s.from_id;
        incomingOfferPayload = s.payload;
        callerName.textContent = s.from_name || ('Student #' + s.from_id);
        banner.classList.remove('d-none');
        statusEl.textContent = 'Incoming call…';
        await fetch('../api/webrtc_incoming.php?ack=' + encodeURIComponent(s.call_id));
      } else if (s.signal_type === 'hangup' && s.call_id === currentCallId) {
        resetCall('Caller hung up');
      } else if (s.signal_type === 'candidate' && s.call_id === currentCallId && pc) {
        try { await pc.addIceCandidate(JSON.parse(s.payload)); } catch (e) {}
      }
    }
  } catch (e) {}
}

acceptBtn.addEventListener('click', async () => {
  if (!incomingOfferPayload || !currentCallId) return;
  banner.classList.add('d-none');
  statusEl.textContent = 'Connecting…';

  try { await initMedia(); }
  catch (e) { alert('Camera blocked. Use HTTPS.'); resetCall('Media error'); return; }

  createPeer();
  await pc.setRemoteDescription(JSON.parse(incomingOfferPayload));
  const answer = await pc.createAnswer();
  await pc.setLocalDescription(answer);
  await sendSignal('answer', JSON.stringify(answer));

  hangupBtn.disabled = false;
  statusEl.textContent = 'Connected';
  pollDuringCall();
});

declineBtn.addEventListener('click', async () => {
  if (currentCallId) await sendSignal('hangup', '{}');
  resetCall('Declined');
});

function pollDuringCall() {
  if (pollTimer) clearInterval(pollTimer);
  pollTimer = setInterval(async () => {
    if (!currentCallId) return;
    const r = await fetch('../api/webrtc_signal.php?call_id=' +
                          encodeURIComponent(currentCallId)).then(x => x.json());
    if (!r.ok) return;
    for (const s of r.signals) {
      if (s.signal_type === 'candidate') {
        try { await pc.addIceCandidate(JSON.parse(s.payload)); } catch (e) {}
      } else if (s.signal_type === 'hangup') {
        resetCall('Student hung up');
        return;
      }
    }
    await fetch('../api/webrtc_signal.php?call_id=' +
                encodeURIComponent(currentCallId) + '&consume=1');
  }, 2000);
}

hangupBtn.addEventListener('click', async () => {
  if (currentCallId) await sendSignal('hangup', '{}');
  resetCall('Ended');
});

/* ---------------- cleanup ---------------- */
function resetCall(msg) {
  if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  if (pc) { try { pc.close(); } catch (e) {} pc = null; }
  if (localStream) { localStream.getTracks().forEach(t => t.stop()); localStream = null; }
  localVideo.srcObject  = null;
  remoteVideo.srcObject = null;
  banner.classList.add('d-none');
  startBtn.disabled  = false;
  hangupBtn.disabled = true;
  statusEl.textContent = msg || 'Idle.';
  currentCallId = null;
  currentPeerId = null;
  incomingOfferPayload = null;
}

/* ---------------- boot ---------------- */
resetCall('Idle.');
setInterval(pollIncoming, 2500);
</script>
<?php require '../includes/footer.php'; ?>