// assets/js/password.js
(function () {
  'use strict';

  /**
   * Auto-wraps every input[type=password] with a show/hide eye button.
   * Add data-no-toggle to skip.
   */
  function initToggles(root) {
    (root || document).querySelectorAll('input[type="password"]').forEach(function (input) {
      if (input.dataset.noToggle !== undefined) return;
      if (input.dataset.hasToggle) return; // already done
      input.dataset.hasToggle = '1';

      // wrap input in a group
      const wrapper = document.createElement('div');
      wrapper.className = 'input-group';
      input.parentNode.insertBefore(wrapper, input);
      wrapper.appendChild(input);

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-outline-secondary';
      btn.setAttribute('aria-label', 'Show password');
      btn.innerHTML = '👁';
      wrapper.appendChild(btn);

      btn.addEventListener('click', function () {
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        btn.innerHTML = showing ? '👁' : '🙈';
        btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
      });
    });
  }

  /**
   * Optional strength meter for any input with data-strength="1".
   * Shows 4 bars: weak / fair / good / strong.
   */
  function scorePassword(pw) {
    let score = 0;
    if (!pw) return 0;
    if (pw.length >= 6)  score++;
    if (pw.length >= 10) score++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
    if (/\d/.test(pw))   score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    return Math.min(score, 4);
  }

  function initStrengths(root) {
    (root || document).querySelectorAll('input[data-strength="1"]').forEach(function (input) {
      if (input.dataset.hasStrength) return;
      input.dataset.hasStrength = '1';

      const meter = document.createElement('div');
      meter.className = 'd-flex gap-1 mt-1';
      meter.innerHTML = '<div class="bar bg-secondary-subtle rounded" style="height:6px;flex:1"></div>'
                      + '<div class="bar bg-secondary-subtle rounded" style="height:6px;flex:1"></div>'
                      + '<div class="bar bg-secondary-subtle rounded" style="height:6px;flex:1"></div>'
                      + '<div class="bar bg-secondary-subtle rounded" style="height:6px;flex:1"></div>';

      const label = document.createElement('div');
      label.className = 'small text-muted';
      label.textContent = '';

      input.parentNode.appendChild(meter);
      input.parentNode.appendChild(label);

      function update() {
        const s = scorePassword(input.value);
        const colors = ['bg-danger','bg-warning','bg-info','bg-success'];
        const texts  = ['Too short','Weak','Fair','Good','Strong'];
        meter.querySelectorAll('.bar').forEach(function (b, i) {
          b.className = 'bar rounded';
          b.style.height = '6px';
          b.style.flex = '1';
          if (i < s) b.classList.add(colors[s-1] || 'bg-success');
          else       b.classList.add('bg-secondary-subtle');
        });
        label.textContent = input.value ? texts[s] : '';
      }
      input.addEventListener('input', update);
      update();
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initToggles();
    initStrengths();
  });

  // expose for dynamic content
  window.PasswordUI = { initToggles, initStrengths };
})();