(function (window, document) {
  'use strict';

  let permissions = new Set();

  function set(values = []) {
    permissions = new Set(values);
    apply();
  }

  function has(permission) {
    return permission === '' || permissions.has(permission);
  }

  function hasAny(value) {
    const list = Array.isArray(value)
      ? value
      : String(value || '').split(',').map((item) => item.trim()).filter(Boolean);

    return list.length === 0 || list.some(has);
  }

  function hasAll(value) {
    const list = Array.isArray(value)
      ? value
      : String(value || '').split(',').map((item) => item.trim()).filter(Boolean);

    return list.every(has);
  }

  function apply(root = document) {
    root.querySelectorAll('[data-permission]').forEach((element) => {
      const allowed = has(element.dataset.permission);
      element.hidden = !allowed;
      element.setAttribute('aria-hidden', String(!allowed));
    });

    root.querySelectorAll('[data-permission-any]').forEach((element) => {
      const allowed = hasAny(element.dataset.permissionAny);
      element.hidden = !allowed;
      element.setAttribute('aria-hidden', String(!allowed));
    });

    root.querySelectorAll('[data-permission-all]').forEach((element) => {
      const allowed = hasAll(element.dataset.permissionAll);
      element.hidden = !allowed;
      element.setAttribute('aria-hidden', String(!allowed));
    });
  }

  document.addEventListener('abhipraya:userloaded', (event) => {
    set(event.detail?.permissions || []);
  });

  window.AbhiprayaPermissions = { set, has, hasAny, hasAll, apply };
})(window, document);
