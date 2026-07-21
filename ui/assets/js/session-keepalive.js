(function (window, document) {
  'use strict';
  const intervalMs = 5 * 60 * 1000;
  let timer = null;

  async function keepAlive() {
    if (document.visibilityState !== 'visible') return;
    try {
      const response = await fetch('/api/v1/auth/me', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      if (response.status === 401) window.location.assign('/admin/login?reason=session-expired');
    } catch (_) {
      /* A temporary network error must not sign the user out. */
    }
  }

  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') keepAlive(); });
  timer = window.setInterval(keepAlive, intervalMs);
  window.addEventListener('beforeunload', () => window.clearInterval(timer), { once: true });
}(window, document));
