(function (window, document) {
  'use strict';

  let activeModal = null;
  let lastFocused = null;

  function open(target) {
    const modal = typeof target === 'string' ? document.querySelector(target) : target;
    if (!modal) return;

    lastFocused = document.activeElement;
    activeModal = modal;
    modal.hidden = false;
    document.body.classList.add('modal-open');

    const focusable = modal.querySelector(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    focusable?.focus();
  }

  function close(target = activeModal) {
    const modal = typeof target === 'string' ? document.querySelector(target) : target;
    if (!modal) return;

    modal.hidden = true;
    document.body.classList.remove('modal-open');
    activeModal = null;
    lastFocused?.focus();
  }

  document.addEventListener('click', (event) => {
    const openButton = event.target.closest('[data-modal-open]');
    if (openButton) open(openButton.dataset.modalOpen);

    if (event.target.closest('[data-modal-close]')) {
      close(event.target.closest('.modal'));
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && activeModal) close();
  });

  window.AbhiprayaModal = { open, close };
})(window, document);
