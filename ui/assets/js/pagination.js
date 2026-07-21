(function (window) {
  'use strict';

  function render(container, meta, onChange) {
    if (!container) return;

    const page = Number(meta.page || 1);
    const totalPages = Number(meta.total_pages || 0);

    container.querySelector('[data-page-start]')?.replaceChildren(
      document.createTextNode(String(meta.total ? ((page - 1) * meta.limit) + 1 : 0))
    );
    container.querySelector('[data-page-end]')?.replaceChildren(
      document.createTextNode(String(Math.min(page * meta.limit, meta.total || 0)))
    );
    container.querySelector('[data-page-total]')?.replaceChildren(
      document.createTextNode(String(meta.total || 0))
    );

    const pagesHost = container.querySelector('[data-pagination-pages]');
    if (pagesHost) {
      pagesHost.innerHTML = '';

      const start = Math.max(1, page - 2);
      const end = Math.min(totalPages, page + 2);

      for (let current = start; current <= end; current += 1) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'pagination-button';
        button.textContent = String(current);
        if (current === page) button.setAttribute('aria-current', 'page');
        button.addEventListener('click', () => onChange(current));
        pagesHost.appendChild(button);
      }
    }

    const bind = (selector, target, disabled) => {
      const button = container.querySelector(selector);
      if (!button) return;
      button.disabled = disabled;
      button.onclick = () => onChange(target);
    };

    bind('[data-pagination-first]', 1, page <= 1);
    bind('[data-pagination-previous]', page - 1, page <= 1);
    bind('[data-pagination-next]', page + 1, page >= totalPages);
    bind('[data-pagination-last]', totalPages, page >= totalPages || totalPages === 0);
  }

  window.AbhiprayaPagination = { render };
})(window);
