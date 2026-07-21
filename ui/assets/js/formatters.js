(function (window) {
  'use strict';

  function number(value, options = {}) {
    return new Intl.NumberFormat(options.locale || document.documentElement.lang || 'en', options)
      .format(Number(value || 0));
  }

  function percent(value, digits = 1) {
    return `${number(value, {
      minimumFractionDigits: digits,
      maximumFractionDigits: digits
    })}%`;
  }

  function text(value, fallback = '—') {
    return value === null || value === undefined || value === '' ? fallback : String(value);
  }

  function status(value) {
    const normalized = String(value || '').toLowerCase();
    return {
      label: normalized ? normalized.replace(/_/g, ' ') : 'unknown',
      className: {
        active: 'status-badge-success',
        published: 'status-badge-success',
        completed: 'status-badge-success',
        inactive: 'status-badge-neutral',
        draft: 'status-badge-warning',
        archived: 'status-badge-neutral',
        failed: 'status-badge-danger',
        rejected: 'status-badge-danger'
      }[normalized] || 'status-badge-info'
    };
  }

  window.AbhiprayaFormat = { number, percent, text, status };
})(window);
