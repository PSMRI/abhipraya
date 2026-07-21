(function (window, document) {
  'use strict';

  let scope = window.AbhiprayaStorage?.get('scope', {
    facility_id: null,
    department_id: null
  }) || {};

  function get() {
    return { ...scope };
  }

  function set(next) {
    scope = {
      facility_id: next?.facility_id || null,
      department_id: next?.department_id || null
    };

    window.AbhiprayaStorage?.set('scope', scope);

    document.dispatchEvent(new CustomEvent('abhipraya:scopechange', {
      detail: get()
    }));

    updateDisplay();
  }

  function append(query = {}) {
    return {
      ...query,
      ...(scope.facility_id ? { facility_id: scope.facility_id } : {}),
      ...(scope.department_id ? { department_id: scope.department_id } : {})
    };
  }

  function updateDisplay() {
    document.querySelectorAll('[data-current-scope-name]').forEach((element) => {
      element.textContent = scope.facility_id
        ? `Facility #${scope.facility_id}`
        : 'All permitted facilities';
    });

    document.querySelectorAll('[data-current-scope-type]').forEach((element) => {
      element.textContent = scope.department_id
        ? `Department #${scope.department_id}`
        : 'Organization scope';
    });
  }

  document.addEventListener('DOMContentLoaded', updateDisplay);

  window.AbhiprayaScope = { get, set, append };
})(window, document);
