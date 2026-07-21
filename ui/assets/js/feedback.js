(function (window, document) {
  'use strict';

  const containerId = 'ab-global-feedback';

  function container() {
    let node = document.getElementById(containerId);
    if (node) return node;
    node = document.createElement('div');
    node.id = containerId;
    node.className = 'ab-global-feedback';
    node.setAttribute('aria-live', 'polite');
    node.setAttribute('aria-relevant', 'additions');
    document.body.appendChild(node);
    return node;
  }

  function show(type, message, options) {
    const settings = Object.assign({ timeout: type === 'error' ? 8000 : 5000 }, options || {});
    const notice = document.createElement('div');
    notice.className = 'ab-notice ab-notice-' + type;
    notice.setAttribute('role', type === 'error' ? 'alert' : 'status');

    const icon = document.createElement('i');
    icon.className = type === 'success' ? 'bi bi-check-circle-fill' : type === 'error' ? 'bi bi-exclamation-octagon-fill' : type === 'warning' ? 'bi bi-exclamation-triangle-fill' : 'bi bi-info-circle-fill';
    icon.setAttribute('aria-hidden', 'true');
    const text = document.createElement('span');
    text.textContent = String(message || 'Request completed.');
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'ab-notice-close';
    close.setAttribute('aria-label', 'Dismiss message');
    close.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
    const dismiss = () => notice.remove();
    close.addEventListener('click', dismiss);
    notice.append(icon, text, close);
    container().appendChild(notice);
    if (settings.timeout > 0) window.setTimeout(dismiss, settings.timeout);
    return notice;
  }

  window.AbhiprayaFeedback = {
    success: (message, options) => show('success', message, options),
    error: (message, options) => show('error', message, options),
    warning: (message, options) => show('warning', message, options),
    info: (message, options) => show('info', message, options)
  };
}(window, document));
