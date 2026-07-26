(() => {
  'use strict';

  function loadSearchableSelects() {
    if (!document.querySelector('link[href="/ui/assets/css/searchable-select.css"]')) {
      const stylesheet = document.createElement('link');
      stylesheet.rel = 'stylesheet';
      stylesheet.href = '/ui/assets/css/searchable-select.css';
      document.head.appendChild(stylesheet);
    }
    if (window.AbhiprayaSearchableSelect) {
      window.AbhiprayaSearchableSelect.initializeAll();
      return;
    }
    if (document.querySelector('script[data-searchable-select-loader]')) return;
    const script = document.createElement('script');
    script.src = '/ui/assets/js/searchable-select.js';
    script.dataset.searchableSelectLoader = 'true';
    document.head.appendChild(script);
  }

  function loadAccessibilityControls() {
    if (!document.querySelector('link[href="/ui/assets/css/accessibility-wcag.css"]')) {
      const stylesheet = document.createElement('link');
      stylesheet.rel = 'stylesheet';
      stylesheet.href = '/ui/assets/css/accessibility-wcag.css';
      document.head.appendChild(stylesheet);
    }
    if (window.AbhiprayaAccessibility || document.querySelector('script[data-accessibility-loader]')) return;
    const script = document.createElement('script');
    script.src = '/ui/assets/js/accessibility-wcag.js';
    script.dataset.accessibilityLoader = 'true';
    document.head.appendChild(script);
  }

  loadSearchableSelects();
  loadAccessibilityControls();

  const topNavigation = document.querySelector('.top-navigation');
  if (topNavigation && !topNavigation.querySelector('a[href="/admin/capa"]')) {
    const capaLink = document.createElement('a');
    capaLink.href = '/admin/capa';
    capaLink.innerHTML = '<i class="bi bi-clipboard2-pulse" aria-hidden="true"></i>CAPA';
    topNavigation.appendChild(capaLink);
  }

  const sidebar = document.querySelector('.ab-sidebar');
  const overlay = document.querySelector('.ab-overlay');
  const open = () => {
    sidebar?.classList.add('is-open');
    overlay?.classList.add('is-open');
    document.body.classList.add('ab-navigation-open');
  };
  const close = () => {
    sidebar?.classList.remove('is-open');
    overlay?.classList.remove('is-open');
    document.body.classList.remove('ab-navigation-open');
  };
  document.querySelector('[data-menu-toggle]')?.addEventListener('click', () => sidebar?.classList.contains('is-open') ? close() : open());
  overlay?.addEventListener('click', close);
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });

  async function json(url, options = {}) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      ...options,
      headers: { Accept: 'application/json', ...(options.headers || {}) }
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok || body.status !== 'success') throw new Error(body.message || 'Request failed');
    return body;
  }
  window.AbhiprayaUI = { json };

  function applyName(name) {
    const safeName = String(name || '').trim() || 'Administrator';
    document.querySelectorAll('[data-user-name]').forEach((element) => { element.textContent = safeName; });
    document.querySelectorAll('[data-avatar]').forEach((element) => { element.textContent = (safeName[0] || 'A').toUpperCase(); });
  }

  json('/api/v1/auth/me').then(async ({ data }) => {
    const user = data?.user || {};
    applyName(user.full_name || user.u_name || 'Administrator');
    document.querySelectorAll('[data-user-role]').forEach((element) => { element.textContent = user.role_name || 'Administrator'; });
    try {
      const profilePayload = await json('/api/v1/auth/profile');
      const profile = profilePayload.data?.profile || {};
      const profileName = [profile.first_name, profile.middle_name, profile.last_name].filter(Boolean).join(' ').trim();
      if (profileName) applyName(profileName);
    } catch (_) {
      // The account remains usable if secure profile storage is not initialized.
    }
  }).catch(() => location.assign('/admin/login?reason=session-expired'));

  document.querySelector('[data-logout]')?.addEventListener('click', async () => {
    try {
      await fetch('/api/v1/auth/logout', { method: 'POST', credentials: 'same-origin' });
    } finally {
      location.assign('/admin/login');
    }
  });
})();
