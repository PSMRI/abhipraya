(function (window, document) {
  'use strict';

  function ask(message, options = {}) {
    const dialog = document.getElementById('confirmation-dialog');
    if (!dialog) return Promise.resolve(window.confirm(message));

    dialog.querySelector('[data-confirm-title]').textContent =
      options.title || 'Confirm action';

    dialog.querySelector('[data-confirm-message]').textContent = message;

    return new Promise((resolve) => {
      const accept = dialog.querySelector('[data-confirm-accept]');
      const cancelButtons = dialog.querySelectorAll('[data-confirm-cancel]');

      const cleanup = (result) => {
        dialog.hidden = true;
        accept.onclick = null;
        cancelButtons.forEach((button) => {
          button.onclick = null;
        });
        resolve(result);
      };

      accept.onclick = () => cleanup(true);
      cancelButtons.forEach((button) => {
        button.onclick = () => cleanup(false);
      });

      dialog.hidden = false;
      accept.focus();
    });
  }

  window.AbhiprayaConfirm = { ask };
})(window, document);
