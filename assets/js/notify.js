// assets/js/notify.js
(function () {
  'use strict';

  const bell       = document.getElementById('notifyBell');
  const badge      = document.getElementById('notifyBellBadge');
  const banner     = document.getElementById('notifyBanner');
  const bannerText = document.getElementById('notifyBannerText');
  const bannerLink = document.getElementById('notifyBannerLink');

  if (!bell || !badge || !banner) return;   // not logged in — bail

  const BASE        = window.APP_BASE || '';      // set in header below
  const isManager   = !!document.getElementById('navMain'); // proxy, we can improve
  let lastUnread    = 0;
  let bannerLocked  = null;                       // avoid re-showing same call
  let bannerTimer   = null;

  function showBanner(text, href) {
    bannerText.innerHTML = text;
    bannerLink.setAttribute('href', href);
    banner.classList.remove('d-none');
    if (bannerTimer) clearTimeout(bannerTimer);
    // auto-hide after 12s for messages; calls stay until dismissed
    bannerTimer = setTimeout(() => banner.classList.add('d-none'), 12000);
  }

  function hideBanner() {
    banner.classList.add('d-none');
    if (bannerTimer) { clearTimeout(bannerTimer); bannerTimer = null; }
  }

  function isOnCallPage() {
    return /\/call\.php/.test(location.pathname);
  }

  function isOnChatPage() {
    return /\/messages\.php/.test(location.pathname);
  }

  async function poll() {
    try {
      const r = await fetch(BASE + '/api/notify_check.php', { cache: 'no-store' })
                      .then(r => r.json());
      if (!r.ok) return;

      /* ---------- bell badge ---------- */
      if (r.unread > 0) {
        badge.textContent = r.unread > 99 ? '99+' : r.unread;
        badge.classList.remove('d-none');
      } else {
        badge.classList.add('d-none');
      }

      /* ---------- incoming call takes priority ---------- */
      if (r.incoming_call) {
        if (!isOnCallPage() && bannerLocked !== r.incoming_call.call_id) {
          const who = r.incoming_call.peer_name || 'someone';
          const text = r.role === 'manager'
            ? `📞 <b>Incoming call</b> from ${escapeHtml(who)}`
            : `📞 <b>Manager is calling you</b> (${escapeHtml(who)})`;
          const href = BASE + (r.role === 'manager'
            ? '/manager/call.php'
            : '/student/call.php');
          showBanner(text, href);
          bannerLocked = r.incoming_call.call_id;
        }
        return;   // don't show message banners while a call is ringing
      } else {
        bannerLocked = null;
      }

      /* ---------- new message banner ---------- */
      if (r.unread > lastUnread && r.last_msg) {
        if (!isOnChatPage()) {
          const who = r.last_msg.sender_name || 'someone';
          const snippet = String(r.last_msg.body).slice(0, 60)
                          + (r.last_msg.body.length > 60 ? '…' : '');
          const href = BASE + (r.role === 'manager'
            ? '/manager/messages.php?student=' + r.last_msg.sender_id
            : '/student/messages.php');
          showBanner(
            `💬 <b>${escapeHtml(who)}</b>: ${escapeHtml(snippet)}`,
            href
          );
        }
      } else if (r.unread === 0) {
        hideBanner();
      }
      lastUnread = r.unread;

    } catch (e) { /* silent */ }
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
  }

  /* clicking the bell: go to messages page */
  bell.addEventListener('click', () => {
    const path = location.pathname;
    if (path.includes('/manager/')) location.href = BASE + '/manager/messages.php';
    else if (path.includes('/student/')) location.href = BASE + '/student/messages.php';
  });

  /* dismiss banner when user clicks the "Open" link */
  bannerLink.addEventListener('click', () => setTimeout(hideBanner, 50));

  /* start */
  poll();
  setInterval(poll, 5000);
})();
banner.addEventListener('mouseenter', () => {
  if (bannerTimer) { clearTimeout(bannerTimer); bannerTimer = null; }
});
banner.addEventListener('mouseleave', () => {
  if (!bannerLocked) bannerTimer = setTimeout(() => banner.classList.add('d-none'), 4000);
});
function beep() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const o = ctx.createOscillator(), g = ctx.createGain();
    o.connect(g); g.connect(ctx.destination);
    o.frequency.value = 880; g.gain.value = 0.05;
    o.start(); o.stop(ctx.currentTime + 0.12);
  } catch (e) {}
}
// call beep() inside the "new message" branch, right after showBanner(...)