(function (document) {
  'use strict';

  function trapFocus(container) {
    const selector =
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), ' +
      'textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    container.addEventListener('keydown', (event) => {
      if (event.key !== 'Tab') return;

      const items = [...container.querySelectorAll(selector)].filter((element) => !element.hidden);
      if (!items.length) return;

      const first = items[0];
      const last = items[items.length - 1];

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });
  }

  function announce(message) {
    let region = document.getElementById('a11y-announcer');

    if (!region) {
      region = document.createElement('div');
      region.id = 'a11y-announcer';
      region.className = 'sr-only';
      region.setAttribute('aria-live', 'polite');
      document.body.appendChild(region);
    }

    region.textContent = '';
    setTimeout(() => {
      region.textContent = message;
    }, 20);
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.modal').forEach(trapFocus);
  });

  window.AbhiprayaAccessibility = { trapFocus, announce };
})(document);
