(function (window, document) {
  'use strict';

  const KEY = 'abhipraya_theme';
  const ALLOWED = ['light', 'dark', 'system', 'high-contrast'];
  const media = window.matchMedia('(prefers-color-scheme: dark)');

  function getPreference() {
    try {
      const value = localStorage.getItem(KEY);
      return ALLOWED.includes(value) ? value : 'system';
    } catch (error) {
      return 'system';
    }
  }

  function resolve(preference) {
    if (preference === 'system') {
      return media.matches ? 'dark' : 'light';
    }
    return preference;
  }

  function apply(preference, persist = true) {
    if (!ALLOWED.includes(preference)) {
      preference = 'system';
    }

    const resolved = resolve(preference);
    document.documentElement.dataset.theme = resolved;
    document.documentElement.dataset.themePreference = preference;

    if (persist) {
      try {
        localStorage.setItem(KEY, preference);
      } catch (error) {
        console.warn('Unable to save theme.', error);
      }
    }

    document.dispatchEvent(new CustomEvent('abhipraya:themechange', {
      detail: { preference, resolved }
    }));

    updateMenuState(preference);
  }

  function updateMenuState(preference) {
    document.querySelectorAll('[data-theme-option]').forEach((button) => {
      const selected = button.dataset.themeOption === preference;
      button.setAttribute('aria-checked', String(selected));
      button.classList.toggle('is-active', selected);
    });
  }

  function bind() {
    document.querySelectorAll('[data-theme-option]').forEach((button) => {
      button.addEventListener('click', () => {
        apply(button.dataset.themeOption);
        const menu = button.closest('[role="menu"]');
        if (menu) menu.hidden = true;
      });
    });

    media.addEventListener?.('change', () => {
      if (getPreference() === 'system') {
        apply('system', false);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    apply(getPreference(), false);
    bind();
  });

  window.AbhiprayaTheme = { apply, getPreference, resolve };
})(window, document);
