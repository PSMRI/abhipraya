(function (window, document) {
  'use strict';

  function show(message = 'Please wait...') {
    const loader = document.getElementById('global-loader');
    if (!loader) return;

    const text = loader.querySelector('[data-loader-message]');
    if (text) text.textContent = message;
    loader.hidden = false;
  }

  function hide() {
    const loader = document.getElementById('global-loader');
    if (loader) loader.hidden = true;
  }

  async function wrap(promise, message) {
    show(message);
    try {
      return await promise;
    } finally {
      hide();
    }
  }

  window.AbhiprayaLoader = { show, hide, wrap };
})(window, document);
