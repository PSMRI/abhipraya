(function (window, document) {
  'use strict';

  let messages = {};
  let locale = document.documentElement.lang || 'en';

  async function load(nextLocale) {
    locale = nextLocale || 'en';

    try {
      const response = await fetch(`/ui/locales/${locale}.json`, {
        cache: 'no-store'
      });

      messages = response.ok ? await response.json() : {};
    } catch (error) {
      messages = {};
    }

    document.documentElement.lang = locale;
    apply();
  }

  function t(key, fallback = key, variables = {}) {
    const value = key.split('.').reduce((current, part) => current?.[part], messages) ?? fallback;

    return String(value).replace(/\{(\w+)\}/g, (_, name) =>
      variables[name] !== undefined ? variables[name] : `{${name}}`
    );
  }

  function apply(root = document) {
    root.querySelectorAll('[data-i18n]').forEach((element) => {
      element.textContent = t(element.dataset.i18n, element.textContent);
    });

    root.querySelectorAll('[data-i18n-placeholder]').forEach((element) => {
      element.placeholder = t(element.dataset.i18nPlaceholder, element.placeholder);
    });
  }

  window.AbhiprayaI18n = { load, t, apply, get locale() { return locale; } };
})(window, document);
