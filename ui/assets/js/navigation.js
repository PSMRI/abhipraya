(function (document) {
  'use strict';

  function markActive() {
    const path = location.pathname.replace(/\/+$/, '');

    document.querySelectorAll('[data-nav-link], .sidebar-menu-link').forEach((link) => {
      const href = new URL(link.href, location.origin).pathname.replace(/\/+$/, '');
      const active = href === path || (href !== '/ui/dashboard.html' && path.startsWith(href));
      link.classList.toggle('is-active', active);
      if (active) link.setAttribute('aria-current', 'page');
      else link.removeAttribute('aria-current');
    });
  }

  function bindSidebar() {
    const sidebar = document.getElementById('admin-sidebar');
    const mobileButton = document.getElementById('mobile-menu-button');
    const collapseButton = document.getElementById('sidebar-collapse-button');

    mobileButton?.addEventListener('click', () => {
      const open = !sidebar.classList.contains('is-open');
      sidebar.classList.toggle('is-open', open);
      mobileButton.setAttribute('aria-expanded', String(open));
    });

    collapseButton?.addEventListener('click', () => {
      document.body.classList.toggle('sidebar-collapsed');
      const expanded = !document.body.classList.contains('sidebar-collapsed');
      collapseButton.setAttribute('aria-expanded', String(expanded));
    });

    document.querySelectorAll('.sidebar-submenu-toggle').forEach((button) => {
      button.addEventListener('click', () => {
        const target = document.getElementById(button.getAttribute('aria-controls'));
        const expanded = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', String(!expanded));
        if (target) target.hidden = expanded;
      });
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    markActive();
    bindSidebar();
  });
})(document);
