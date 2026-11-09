<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_student();

$me = current_student($pdo);
$manager = $pdo->query("SELECT id,name FROM managers WHERE is_active=1 ORDER BY role DESC, id LIMIT 1")->fetch();

$pageTitle = 'Call Manager';
require '../includes/header.php';
?>
<h3>📹 Video / Voice Call</h3>
<p class="text-muted small">
  You can call management, or accept an incoming call from them.
  Requires HTTPS. Allow camera and microphone when prompted.
</p>

<div class="mt-3 mb-3 d-flex gap-2">
  <button id="startBtn" class="btn btn-success">📞 Call Manager</button>
  <button id="hangupBtn" class="btn btn-danger" disabled>📴 Hang Up</button>
  <span id="status" class="align-self-center text-muted small">Idle.</span>
</div>

<div id="incomingBanner" class="alert alert-warning d-none">
  <div class="d-flex justify-content-between align-items-center">
    <div>📲 <b>Incoming call</b> from <span id="callerName">management</span></div>
    <div class="d-flex gap-2">
      <button id="acceptBtn"  class="btn btn-sm btn-success">✅ Accept</button>
      <button id="declineBtn" class="btn btn-sm btn-outline-danger">✖ Decline</button>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-6"><video id="localVideo"  autoplay muted playsinline class="w-100 rounded border bg-dark" style="min-height:240px"></video></div>
  <div class="col-md-6"><video id="remoteVideo" autoplay playsinline class="w-100 rounded border bg-dark" style="min-height:240px"></video></div>
</div>

<script>
const ICE = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };
const studentId = <?= (int)$me['id'] ?>;
const managerId = <?= (int)($manager['id'] ?? 0) ?>;

const localVideo  = document.getElementById('localVideo');
const remoteVideo = document.getElementById('remoteVideo');
const statusEl    = document.getElementById('status');
const startBtn    = document.getElementById('startBtn');
const hangupBtn   = document.getElementById('hangupBtn');

const banner     = document.getElementById('incomingBanner');
const callerName = document.getElementById('callerName');
const acceptBtn  = document.getElementById('acceptBtn');
const declineBtn = document.getElementById('declineBtn');

let pc, localStream;
let currentCallId = null;
let currentPeerId = null;
let incomingOfferPayload = null;
let pollTimer = null;

async function initMedia() {
  if (localStream) return;
  localStream = await navigator.mediaDevices.getUserMedia({ video:true, audio:true });
  localVideo.srcObject = localStream;
}

function createPeer() {
  pc = new RTCPeerConnection(ICE);
  localStream.getTracks().forEach(t => pc.addTrack(t, localStream));
  pc.ontrack = e => { remoteVideo.srcObject = e.streams[0]; };
  pc.onicecandidate = e => {
    if (e.candidate) sendSignal('candidate', JSON.stringify(e.candidate));
  };
}

async function sendSignal(type, payload) {
  await fetch('../api/webrtc_signal.php', {
    method: 'POST',
    body: new URLSearchParams({
      call_id: currentCallId,
      to_type: 'manager',
      to_id: currentPeerId || managerId,
      signal_type: type,
      payload
    })
  });
}

/* ------- student initiates ------- */
startBtn.addEventListener('click', async () => {
  if (!managerId) { alert('No manager available'); return; }
  try { await initMedia(); }
  catch (e) { alert('Camera blocked. Use HTTPS.'); return; }

  currentPeerId = managerId;
  currentCallId = 'call_s' + studentId + '_m' + managerId + '_' + Date.now();

  createPeer();
  const offer = await pc.createOffer();
  await pc.setLocalDescription(offer);
  await sendSignal('offer', JSON.stringify(offer));

  startBtn.disabled = true;
  hangupBtn.disabled = false;
  statusEl.textContent = 'Ringing manager… waiting for answer';
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
        resetCall('Manager hung up');
        return;
      }
    }
    await fetch('../api/webrtc_signal.php?call_id=' +
                encodeURIComponent(currentCallId) + '&consume=1');
  }, 2000);
}

/* ------- student receives from manager ------- */
async function pollIncoming() {
  try {
    const r = await fetch('../api/webrtc_incoming.php').then(x => x.json());
    if (!r.ok) return;
    for (const s of r.signals) {
      if (s.signal_type === 'offer' && !currentCallId) {
        currentCallId = s.call_id;
        currentPeerId = +s.from_id;
        incomingOfferPayload = s.payload;
        callerName.textContent = s.from_name || 'Manager';
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
  catch (e) { alert('Camera blocked.'); resetCall('Media error'); return; }

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
        resetCall('Manager hung up');
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

resetCall('Idle.');
setInterval(pollIncoming, 2500);
</script>
<?php require '../includes/footer.php'; ?>