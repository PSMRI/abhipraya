(function () {
  'use strict';
  const app = document.querySelector('.ab-app');
  const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
  const profileForm = document.getElementById('profile-form');
  const passwordForm = document.getElementById('change-password-form');
  const passwordRule = /^(?=.{8,128}$)(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9\s])\S+$/;

  function setSidebarCollapsed(collapsed) {
    app?.classList.toggle('is-collapsed', collapsed);
    sidebarToggle?.setAttribute('aria-pressed', String(collapsed));
    sidebarToggle?.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    try { localStorage.setItem('abhipraya_sidebar_collapsed', String(collapsed)); } catch (_) { /* Optional. */ }
  }
  function showMessage(element, message, isError) {
    if (!element) return;
    element.textContent = message;
    element.setAttribute('role', isError ? 'alert' : 'status');
    if (message) {
      const notify = isError ? window.AbhiprayaFeedback?.error : window.AbhiprayaFeedback?.success;
      notify?.(message);
    }
  }
  async function request(url, options) {
    const response = await fetch(url, Object.assign({ credentials: 'same-origin', headers: { Accept: 'application/json' } }, options));
    const text = await response.text();
    let payload = {};
    try { payload = text ? JSON.parse(text) : {}; } catch (_) { throw new Error('The server returned an invalid response. Please sign in again.'); }
    if (!response.ok || payload.status !== 'success') throw new Error(payload.message || 'Unable to complete the request.');
    return payload;
  }
  async function loadProfile() {
    const message = document.getElementById('profile-message');
    try {
      const payload = await request('/api/v1/auth/profile');
      const profile = payload.data?.profile || {};
      document.getElementById('profile-username').value = profile.username || '';
      ['first_name', 'middle_name', 'last_name', 'email', 'mobile', 'job_title'].forEach((field) => {
        const input = document.querySelector('[name="' + field + '"]');
        if (input) input.value = profile[field] || '';
      });
    } catch (error) {
      showMessage(message, 'Profile storage is not ready yet. Please apply the secure-profile database migration, then reload this page.', true);
    }
  }
  try { setSidebarCollapsed(localStorage.getItem('abhipraya_sidebar_collapsed') === 'true'); } catch (_) { /* Default state. */ }
  sidebarToggle?.addEventListener('click', () => { if (window.matchMedia('(max-width: 880px)').matches) { const sidebar = document.getElementById('primary-navigation'); const open = !sidebar.classList.contains('is-open'); sidebar.classList.toggle('is-open', open); sidebarToggle.setAttribute('aria-expanded', String(open)); return; } setSidebarCollapsed(!app?.classList.contains('is-collapsed')); });
  profileForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const message = document.getElementById('profile-message');
    const button = profileForm.querySelector('[type="submit"]');
    const data = Object.fromEntries(new FormData(profileForm).entries());
    button.disabled = true;
    try {
      const payload = await request('/api/v1/auth/profile', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
      showMessage(message, payload.message || 'Profile saved securely.', false);
    } catch (error) { showMessage(message, error.message, true); }
    finally { button.disabled = false; }
  });
  passwordForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const message = document.getElementById('password-message');
    const current = document.getElementById('current-password').value;
    const next = document.getElementById('new-password').value;
    const confirm = document.getElementById('confirm-password').value;
    if (next !== confirm) return showMessage(message, 'New passwords do not match.', true);
    if (!passwordRule.test(next)) return showMessage(message, 'Use 8–128 characters with uppercase, lowercase, number and special character. Spaces are not allowed.', true);
    if (current === next) return showMessage(message, 'Choose a password different from your current password.', true);
    const button = passwordForm.querySelector('[type="submit"]');
    button.disabled = true;
    try {
      const payload = await request('/api/v1/auth/change-password', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ current_password: current, new_password: next, confirm_password: confirm }) });
      passwordForm.reset();
      showMessage(message, payload.message || 'Password updated successfully.', false);
    } catch (error) { showMessage(message, error.message, true); }
    finally { button.disabled = false; }
  });
  loadProfile();
}());
