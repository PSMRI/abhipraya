(function (window) {
  'use strict';

  function toDate(value) {
    if (!value) return null;
    const date = value instanceof Date ? value : new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
  }

  function format(value, options = {}) {
    const date = toDate(value);
    if (!date) return '';

    return new Intl.DateTimeFormat(options.locale || document.documentElement.lang || 'en', {
      dateStyle: options.dateStyle || 'medium',
      ...(options.includeTime ? { timeStyle: options.timeStyle || 'short' } : {})
    }).format(date);
  }

  function isoDate(value = new Date()) {
    const date = toDate(value);
    return date ? date.toISOString().slice(0, 10) : '';
  }

  function range(preset) {
    const end = new Date();
    const start = new Date(end);

    if (preset === '7days') start.setDate(end.getDate() - 6);
    if (preset === '30days') start.setDate(end.getDate() - 29);
    if (preset === 'month') start.setDate(1);

    return { date_from: isoDate(start), date_to: isoDate(end) };
  }

  window.AbhiprayaDate = { toDate, format, isoDate, range };
})(window);
