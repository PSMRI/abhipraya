(function (window, document) {
  'use strict';

  function initDropdowns() {
    document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
      const toggle = dropdown.querySelector('[data-dropdown-toggle]');
      const menu = dropdown.querySelector('.dropdown-menu, [role="menu"]');

      if (!toggle || !menu) return;

      toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        const open = menu.hidden;
        closeAllDropdowns();
        menu.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
      });
    });

    document.addEventListener('click', closeAllDropdowns);
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') closeAllDropdowns();
    });
  }

  function closeAllDropdowns() {
    document.querySelectorAll('[data-dropdown] .dropdown-menu, [data-dropdown] [role="menu"]')
      .forEach((menu) => {
        menu.hidden = true;
      });

    document.querySelectorAll('[data-dropdown-toggle]')
      .forEach((toggle) => toggle.setAttribute('aria-expanded', 'false'));
  }

  function setCurrentYear() {
    const year = String(new Date().getFullYear());
    document.querySelectorAll('[data-current-year]').forEach((element) => {
      element.textContent = year;
    });
  }

  function bindDatePresets() {
    document.querySelectorAll('[data-date-preset]').forEach((button) => {
      button.addEventListener('click', () => {
        const range = window.AbhiprayaDate?.range(button.dataset.datePreset);
        const root = button.closest('[data-date-range-picker]') || document;

        const from = root.querySelector('[name="date_from"]');
        const to = root.querySelector('[name="date_to"]');

        if (from) from.value = range.date_from;
        if (to) to.value = range.date_to;
      });
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    setCurrentYear();
    initDropdowns();
    bindDatePresets();

    window.AbhiprayaPermissions?.apply();

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      button.addEventListener('click', () => {
        const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        localStorage.setItem('abhipraya_theme', next);
      });
    });

    const loginForm = document.getElementById('login-form');
    if (loginForm) {
      loginForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = document.getElementById('message');
        const submit = loginForm.querySelector('[type="submit"]');
        submit.disabled = true;
        if (message) message.textContent = '';
        try {
          const form = new FormData(loginForm);
          const response = await fetch('/api/modules/auth/v1/login.php', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ username: form.get('username'), password: form.get('password') }) });
          if (!response.ok) throw new Error('Login failed');
          window.location.href = 'dashboard.html';
        } catch (error) {
          if (message) message.innerHTML = '<div class="alert alert-error">Unable to sign in. Check your credentials and try again.</div>';
        } finally { submit.disabled = false; }
      });
    }

    if (document.body.classList.contains('admin-page') || location.pathname.endsWith('/dashboard.html')) {
      fetch('/api/modules/auth/v1/me.php', { credentials: 'include', headers: { Accept: 'application/json' } }).then((response) => response.ok ? response.json() : null).then((payload) => {
        const user = payload?.data?.user || payload?.data || null;
        document.querySelectorAll('[data-current-user]').forEach((node) => { node.textContent = user?.u_name || user?.username || 'Signed in'; });
      }).catch(() => {});
    }

    document.dispatchEvent(new CustomEvent('abhipraya:ready'));
  });

  window.AbhiprayaApp = { initDropdowns, closeAllDropdowns };
})(window, document);
