// assets/js/validate.js
(function () {
  'use strict';

  // ---- Helpers ----
  const KENYAN_PHONE = /^(?:\+?254|0)(7\d{8}|1\d{8})$/;
  const EMAIL_RE     = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const ID_RE        = /^\d{6,10}$/;                       // national ID
  const PASSWORD_RE  = /^(?=.*[A-Za-z])(?=.*\d).{6,}$/;    // ≥6, letter+digit

  function showError(input, msg) {
    input.classList.add('is-invalid');
    let fb = input.parentElement.querySelector('.invalid-feedback');
    if (!fb) {
      fb = document.createElement('div');
      fb.className = 'invalid-feedback';
      input.parentElement.appendChild(fb);
    }
    fb.textContent = msg;
  }

  function clearError(input) {
    input.classList.remove('is-invalid');
    const fb = input.parentElement.querySelector('.invalid-feedback');
    if (fb) fb.textContent = '';
  }

  function validateField(input) {
    const rules = (input.dataset.rules || '').split('|').filter(Boolean);
    const value = input.type === 'file' ? input.files[0] : input.value.trim();
    let ok = true;

    for (const rule of rules) {
      const [name, arg] = rule.split(':');

      switch (name) {
        case 'required':
          if (!value || (input.type === 'file' && !input.files.length)) {
            showError(input, 'This field is required'); return false;
          }
          break;

        case 'min':
          if (value && value.length < +arg) { showError(input, `Must be at least ${arg} characters`); ok = false; }
          break;

        case 'max':
          if (value && value.length > +arg) { showError(input, `Must be at most ${arg} characters`); ok = false; }
          break;

        case 'email':
          if (value && !EMAIL_RE.test(value)) { showError(input, 'Enter a valid email'); ok = false; }
          break;

        case 'phone':
          if (value && !KENYAN_PHONE.test(value.replace(/\s/g, ''))) {
            showError(input, 'Enter a valid Kenyan phone (e.g. 0712345678)'); ok = false;
          }
          break;

        case 'idnumber':
          if (value && !ID_RE.test(value)) { showError(input, 'ID must be 6–10 digits'); ok = false; }
          break;

        case 'password':
          if (value && !PASSWORD_RE.test(value)) {
            showError(input, 'Password needs ≥6 chars with letters & numbers'); ok = false;
          }
          break;

        case 'match':
          {
            const other = document.querySelector(`[name="${arg}"]`);
            if (other && value !== other.value) { showError(input, 'Passwords do not match'); ok = false; }
          }
          break;

        case 'file':
          {
            const f = input.files[0];
            if (!f) break;
            const allowed = ['image/jpeg','image/png','image/jpg','application/pdf'];
            if (!allowed.includes(f.type)) { showError(input, 'Only JPG, PNG or PDF allowed'); ok = false; }
            if (f.size > 5 * 1024 * 1024)  { showError(input, 'File must be ≤ 5MB'); ok = false; }
          }
          break;
      }

      if (!ok) return false;
    }

    clearError(input);
    return true;
  }

  // ---- Wire up ----
  document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form[data-validate]');

    forms.forEach(form => {
      const fields = form.querySelectorAll('[data-rules]');

      fields.forEach(input => {
        input.addEventListener('blur',  () => validateField(input));
        input.addEventListener('input', () => {
          if (input.classList.contains('is-invalid')) validateField(input);
        });
        if (input.type === 'file') {
          input.addEventListener('change', () => validateField(input));
        }
      });

      form.addEventListener('submit', e => {
        let allOk = true;
        fields.forEach(input => { if (!validateField(input)) allOk = false; });

        // extra: match password
        const pw2 = form.querySelector('[data-match]');
        if (pw2) {
          const target = form.querySelector(`[name="${pw2.dataset.match}"]`);
          if (target && pw2.value !== target.value) {
            showError(pw2, 'Passwords do not match'); allOk = false;
          }
        }

        if (!allOk) {
          e.preventDefault();
          form.querySelector('.is-invalid')?.focus();
        }
      });
    });
  });
})();