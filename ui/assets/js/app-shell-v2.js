(() => {
  'use strict';

  if (!document.querySelector('script[src*="/ui/assets/js/admin-language.js"]')) {
    const languageScript = document.createElement('script');
    languageScript.src = '/ui/assets/js/admin-language.js?v=20260908-all-departments';
    languageScript.dataset.adminLanguageLoader = 'true';
    document.head.appendChild(languageScript);
  }

  function loadSearchableSelects() {
    if (!document.querySelector('link[href="/ui/assets/css/searchable-select.css"]')) {
      const stylesheet = document.createElement('link');
      stylesheet.rel = 'stylesheet';
      stylesheet.href = '/ui/assets/css/searchable-select.css';
      document.head.appendChild(stylesheet);
    }
    if (window.AbhiprayaSearchableSelect) {
      window.AbhiprayaSearchableSelect.initializeAll();
      return;
    }
    if (document.querySelector('script[data-searchable-select-loader]')) return;
    const script = document.createElement('script');
    script.src = '/ui/assets/js/searchable-select.js?v=20260908-nin-search';
    script.dataset.searchableSelectLoader = 'true';
    document.head.appendChild(script);
  }

  function loadAccessibilityControls() {
    if (!document.querySelector('link[href="/ui/assets/css/accessibility-wcag.css"]')) {
      const stylesheet = document.createElement('link');
      stylesheet.rel = 'stylesheet';
      stylesheet.href = '/ui/assets/css/accessibility-wcag.css';
      document.head.appendChild(stylesheet);
    }
    if (window.AbhiprayaAccessibility || document.querySelector('script[data-accessibility-loader]')) return;
    const script = document.createElement('script');
    script.src = '/ui/assets/js/accessibility-wcag.js?v=20260908-feedback-accessibility';
    script.dataset.accessibilityLoader = 'true';
    document.head.appendChild(script);
  }

  loadSearchableSelects();
  loadAccessibilityControls();

  function standardizeHeader() {
    if (!document.querySelector('style[data-standard-header-style]')) {
      const style = document.createElement('style');
      style.dataset.standardHeaderStyle = 'true';
      style.textContent = '.feedback-header-actions .shell-updated{color:var(--ab-muted);font-size:.78rem;white-space:nowrap}.admin-language-icon{display:grid;place-items:center;width:34px;height:34px;padding:0;color:#075985;background:#e0f2fe;border:1px solid #7dd3fc;border-radius:6px;font-size:17px;cursor:pointer}.admin-language-icon:hover,.admin-language-icon:focus-visible{color:#fff;background:#0369a1;border-color:#0369a1}';
      document.head.appendChild(style);
    }
    const headerActions = document.querySelector('.header-actions, .feedback-header-actions');
    const feedbackHeader = headerActions?.classList.contains('feedback-header-actions');
    const iconButtonClass = feedbackHeader ? 'feedback-icon-button' : 'icon-button';
    const profileButtonClass = feedbackHeader ? 'feedback-profile-button' : 'profile-button';
    const profileSelector = feedbackHeader ? '.feedback-profile' : '.profile';
    if (headerActions && !headerActions.querySelector('[aria-label="Notifications"]')) {
      const notification = document.createElement('button');
      notification.type = 'button';
      notification.className = iconButtonClass;
      notification.setAttribute('aria-label', 'Notifications');
      notification.innerHTML = '<i class="bi bi-bell" aria-hidden="true"></i>';
      const profile = headerActions.querySelector(profileSelector);
      if (profile) headerActions.insertBefore(notification, profile);
      else headerActions.appendChild(notification);
    }
    if (headerActions && !headerActions.querySelector('a[href="/admin/account"]')) {
      const profileButton = document.createElement('a');
      profileButton.href = '/admin/account';
      profileButton.className = profileButtonClass;
      profileButton.innerHTML = '<i class="bi bi-person-gear" aria-hidden="true"></i><span>My Profile</span>';
      const logout = headerActions.querySelector('[data-logout]');
      if (logout) headerActions.insertBefore(profileButton, logout);
      else headerActions.appendChild(profileButton);
    }
    if (headerActions && !headerActions.querySelector('.shell-updated, #data-updated')) {
      const updated = document.createElement('span');
      updated.className = 'data-updated shell-updated';
      updated.dataset.noTranslate = 'true';
      updated.textContent = `Updated ${new Intl.DateTimeFormat(undefined, {
        day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit'
      }).format(new Date())}`;
      const firstAction = headerActions.querySelector('button, a, .profile');
      if (firstAction) headerActions.insertBefore(updated, firstAction);
      else headerActions.appendChild(updated);
    }

    const nav = document.querySelector('.top-navigation, .feedback-top-navigation');
    const iconByHref = {
      '/admin/dashboard': 'bi-house-door',
      '/admin/feedback': 'bi-chat-square-text',
      '/admin/qr': 'bi-qr-code',
      '/admin/analytics': 'bi-graph-up-arrow',
      '/admin/social-audit': 'bi-people',
      '/admin/reports': 'bi-file-earmark-bar-graph',
      '/admin/capa': 'bi-clipboard2-pulse'
    };
    nav?.querySelectorAll('a[href]').forEach((link) => {
      const icon = iconByHref[link.getAttribute('href')];
      if (!icon || link.querySelector('i')) return;
      const element = document.createElement('i');
      element.className = `bi ${icon}`;
      element.setAttribute('aria-hidden', 'true');
      link.prepend(element);
    });

    let mobileMenuCreated = false;
    if (nav && !nav.parentElement.querySelector('[data-topnav-toggle]')) {
      const mobileMenu = document.createElement('button');
      mobileMenu.type = 'button';
      mobileMenu.className = nav.classList.contains('feedback-top-navigation')
        ? 'feedback-mobile-menu-button'
        : 'mobile-menu-button';
      mobileMenu.dataset.topnavToggle = 'true';
      mobileMenu.setAttribute('aria-expanded', 'false');
      mobileMenu.setAttribute('aria-controls', nav.id);
      mobileMenu.innerHTML = '<i class="bi bi-list" aria-hidden="true"></i><span>Menu</span>';
      nav.parentElement.prepend(mobileMenu);
      mobileMenuCreated = true;
    }
    if (mobileMenuCreated) nav.parentElement.querySelector('[data-topnav-toggle]')?.addEventListener('click', (event) => {
      const isOpen = nav.classList.toggle('is-open');
      event.currentTarget.setAttribute('aria-expanded', String(isOpen));
    });
  }

  standardizeHeader();

  const topNavigation = document.querySelector('.top-navigation');
  if (topNavigation && !topNavigation.querySelector('a[href="/admin/feedback"]')) {
    const feedbackLink = document.createElement('a');
    feedbackLink.href = '/admin/feedback';
    feedbackLink.innerHTML = '<i class="bi bi-chat-square-text" aria-hidden="true"></i>Feedback Explorer';
    const homeLink = topNavigation.querySelector('a[href="/admin/dashboard"]');
    if (homeLink) homeLink.insertAdjacentElement('afterend', feedbackLink);
    else topNavigation.prepend(feedbackLink);
  }
  if (topNavigation && !topNavigation.querySelector('a[href="/admin/capa"]')) {
    const capaLink = document.createElement('a');
    capaLink.href = '/admin/capa';
    capaLink.innerHTML = '<i class="bi bi-clipboard2-pulse" aria-hidden="true"></i>CAPA';
    topNavigation.appendChild(capaLink);
  }

  const sidebar = document.querySelector('.ab-sidebar');
  const overlay = document.querySelector('.ab-overlay');
  const open = () => {
    sidebar?.classList.add('is-open');
    overlay?.classList.add('is-open');
    document.body.classList.add('ab-navigation-open');
  };
  const close = () => {
    sidebar?.classList.remove('is-open');
    overlay?.classList.remove('is-open');
    document.body.classList.remove('ab-navigation-open');
  };
  document.querySelector('[data-menu-toggle]')?.addEventListener('click', () => sidebar?.classList.contains('is-open') ? close() : open());
  overlay?.addEventListener('click', close);
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });

  async function json(url, options = {}) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      ...options,
      headers: { Accept: 'application/json', ...(options.headers || {}) }
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok || body.status !== 'success') {
      const error = new Error(body.message || 'Request failed');
      error.status = response.status;
      throw error;
    }
    return body;
  }
  window.AbhiprayaUI = { json };

  function applyName(name) {
    const safeName = String(name || '').trim() || 'Administrator';
    document.querySelectorAll('[data-user-name]').forEach((element) => { element.textContent = safeName; });
    document.querySelectorAll('[data-avatar]').forEach((element) => { element.textContent = (safeName[0] || 'A').toUpperCase(); });
  }

  function applyFacilityName(name) {
    const safeName = String(name || '').trim();
    if (!safeName) return;
    document.querySelectorAll('[data-facility-name]').forEach((element) => { element.textContent = safeName; });
    document.querySelectorAll('[data-user-name]').forEach((element) => { element.textContent = safeName; });
    document.querySelectorAll('[data-avatar]').forEach((element) => { element.textContent = (safeName[0] || 'A').toUpperCase(); });
  }

  json('/api/v1/auth/me').then(async ({ data }) => {
    const user = data?.user || {};
    applyName(user.full_name || user.u_name || 'Administrator');
    applyFacilityName(user.facility_name);
    document.querySelectorAll('[data-user-role]').forEach((element) => { element.textContent = user.role_name || 'Administrator'; });
    try {
      const profilePayload = await json('/api/v1/auth/profile');
      const profile = profilePayload.data?.profile || {};
      const profileName = [profile.first_name, profile.middle_name, profile.last_name].filter(Boolean).join(' ').trim();
      if (profileName) applyName(profileName);
    } catch (_) {
      // The account remains usable if secure profile storage is not initialized.
    }
  }).catch((error) => {
    if (error?.status === 401 || error?.status === 403) {
      location.assign('/admin/login?reason=session-expired');
    }
  });

  document.querySelector('[data-logout]')?.addEventListener('click', async () => {
    try {
      await fetch('/api/v1/auth/logout', { method: 'POST', credentials: 'same-origin' });
    } finally {
      location.assign('/admin/login');
    }
  });
})();
