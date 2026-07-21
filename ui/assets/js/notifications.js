(function (window, document) {
  'use strict';

  function toast(message, options = {}) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${options.type || 'info'}`;
    toast.setAttribute('role', options.type === 'danger' ? 'alert' : 'status');

    toast.innerHTML = `
      <div class="toast-content">
        ${options.title ? `<strong class="toast-title"></strong>` : ''}
        <p class="toast-message"></p>
      </div>
      <button type="button" class="toast-close" aria-label="Dismiss">×</button>
    `;

    const title = toast.querySelector('.toast-title');
    if (title) title.textContent = options.title;

    toast.querySelector('.toast-message').textContent = message;
    toast.querySelector('.toast-close').addEventListener('click', () => toast.remove());

    container.appendChild(toast);

    setTimeout(() => {
      toast.classList.add('is-visible');
    }, 20);

    setTimeout(() => {
      toast.remove();
    }, options.duration || 5000);
  }

  function alert(container, message, type = 'info') {
    const element = typeof container === 'string'
      ? document.querySelector(container)
      : container;

    if (!element) return;

    const box = document.createElement('div');
    box.className = `alert alert-${type}`;
    box.textContent = message;
    element.replaceChildren(box);
  }

  window.AbhiprayaNotifications = { toast, alert };
})(window, document);
