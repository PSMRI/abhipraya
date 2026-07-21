(function (window, document) {
  'use strict';

  let warningTimer = null;
  let expiryTimer = null;
  let countdownTimer = null;

  function clearTimers() {
    [warningTimer, expiryTimer, countdownTimer].forEach((timer) => {
      if (timer) clearTimeout(timer);
    });
  }

  function schedule(expiresAt, warningSeconds = 60) {
    clearTimers();

    const expiry = new Date(expiresAt).getTime();
    const now = Date.now();
    const warningAt = Math.max(0, expiry - now - warningSeconds * 1000);
    const expiryDelay = Math.max(0, expiry - now);

    warningTimer = setTimeout(() => showWarning(expiry, warningSeconds), warningAt);
    expiryTimer = setTimeout(() => {
      window.AbhiprayaAuth?.logout();
    }, expiryDelay);
  }

  function showWarning(expiry, warningSeconds) {
    const box = document.getElementById('session-warning');
    if (!box) return;

    box.hidden = false;
    const counter = box.querySelector('[data-session-countdown]');

    const tick = () => {
      const remaining = Math.max(0, Math.ceil((expiry - Date.now()) / 1000));
      if (counter) counter.textContent = String(remaining);

      if (remaining <= 0) {
        clearInterval(countdownTimer);
        window.AbhiprayaAuth?.logout();
      }
    };

    tick();
    countdownTimer = setInterval(tick, 1000);
  }

  async function refresh() {
    const payload = await window.AbhiprayaApi.post('/api/v1/auth/refresh-session', {});
    const expiresAt = payload.data?.expires_at;

    document.getElementById('session-warning')?.setAttribute('hidden', '');

    if (expiresAt) schedule(expiresAt);
    return payload;
  }

  document.addEventListener('click', (event) => {
    if (event.target.closest('#session-continue-button')) refresh();
    if (event.target.closest('#session-logout-button')) window.AbhiprayaAuth?.logout();
  });

  window.AbhiprayaSession = { schedule, refresh, clearTimers };
})(window, document);
