(() => {
  'use strict';

  const byId = (id) => document.getElementById(id);
  let currentUser = {};

  function showMessage(id, text, isError = false) {
    const element = byId(id);
    element.textContent = text;
    element.hidden = !text;
    element.classList.toggle('is-error', isError);
  }

  async function requestJson(url, options = {}) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      ...options,
      headers: { Accept: 'application/json', ...(options.headers || {}) }
    });
    const payload = await response.json().catch(() => ({}));
    if (response.status === 401 && String(options.method || 'GET').toUpperCase() === 'GET') {
      location.assign('/admin/login?reason=session-expired');
      throw new Error('Your session has expired.');
    }
    if (!response.ok || payload.status !== 'success') {
      const errors = payload?.data?.errors || payload?.errors || {};
      const firstError = Object.values(errors)[0];
      throw new Error(firstError || payload.message || 'The request could not be completed.');
    }
    return payload;
  }

  async function csrfToken() {
    const payload = await requestJson('/api/v1/auth/csrf');
    return payload.data?.csrf_token || '';
  }

  function profilePayload() {
    return {
      first_name: byId('first-name').value.trim(),
      middle_name: byId('middle-name').value.trim(),
      last_name: byId('last-name').value.trim(),
      email: byId('profile-email').value.trim(),
      mobile: byId('profile-mobile').value.trim(),
      job_title: byId('job-title').value.trim()
    };
  }

  function displayName(profile) {
    const name = [profile.first_name, profile.middle_name, profile.last_name].filter(Boolean).join(' ').trim();
    return currentUser.facility_name || name || currentUser.full_name || currentUser.u_name || 'Administrator';
  }

  function updateIdentity(profile) {
    const name = displayName(profile);
    const initial = (name.trim()[0] || 'A').toUpperCase();
    document.querySelectorAll('[data-user-name]').forEach((element) => { element.textContent = name; });
    document.querySelectorAll('[data-avatar], [data-profile-avatar]').forEach((element) => { element.textContent = initial; });
    byId('identity-username').textContent = profile.username || currentUser.u_name || '\u2014';
    byId('identity-designation').textContent = profile.job_title || 'Not provided';
    document.querySelector('[data-profile-display-name]').textContent = name;
    document.querySelector('[data-profile-role]').textContent = currentUser.role_name || 'Administrator';
  }

  function populateProfile(profile) {
    byId('profile-username').value = profile.username || currentUser.u_name || '';
    byId('first-name').value = profile.first_name || '';
    byId('middle-name').value = profile.middle_name || '';
    byId('last-name').value = profile.last_name || '';
    byId('profile-email').value = profile.email || '';
    byId('profile-mobile').value = profile.mobile || '';
    byId('job-title').value = profile.job_title || '';
    updateIdentity(profile);
  }

  async function loadProfile() {
    try {
      const [userPayload, profilePayloadResponse] = await Promise.all([
        requestJson('/api/v1/auth/me'),
        requestJson('/api/v1/auth/profile')
      ]);
      currentUser = userPayload.data?.user || {};
      populateProfile(profilePayloadResponse.data?.profile || {});
    } catch (error) {
      showMessage('profile-page-message', error.message || 'Unable to load your profile.', true);
    }
  }

  async function saveProfile(event) {
    event.preventDefault();
    showMessage('profile-message', '');
    const payload = profilePayload();
    if (payload.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email)) {
      showMessage('profile-message', 'Enter a valid work email address.', true);
      byId('profile-email').focus();
      return;
    }
    if (payload.mobile && !/^[0-9+() -]{7,20}$/.test(payload.mobile)) {
      showMessage('profile-message', 'Enter a valid mobile number.', true);
      byId('profile-mobile').focus();
      return;
    }

    const button = byId('save-profile');
    button.disabled = true;
    button.innerHTML = '<i class="bi bi-arrow-repeat" aria-hidden="true"></i>Saving...';
    try {
      const token = await csrfToken();
      const response = await requestJson('/api/v1/auth/profile', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify(payload)
      });
      const saved = { ...response.data?.profile, username: byId('profile-username').value };
      populateProfile(saved);
      showMessage('profile-message', 'Profile updated successfully.');
    } catch (error) {
      showMessage('profile-message', error.message || 'Unable to update your profile.', true);
    } finally {
      button.disabled = false;
      button.innerHTML = '<i class="bi bi-check2" aria-hidden="true"></i>Save changes';
    }
  }

  async function changePassword(event) {
    event.preventDefault();
    showMessage('password-message', '');
    const currentPassword = byId('current-password').value;
    const newPassword = byId('new-password').value;
    const confirmPassword = byId('confirm-password').value;
    if (!currentPassword || !newPassword || !confirmPassword) {
      showMessage('password-message', 'Complete all password fields.', true);
      return;
    }
    if (newPassword !== confirmPassword) {
      showMessage('password-message', 'New passwords do not match.', true);
      byId('confirm-password').focus();
      return;
    }
    if (!/^(?=.{8,128}$)(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9\s])\S+$/.test(newPassword)) {
      showMessage('password-message', 'Use 8\u2013128 characters with uppercase, lowercase, number, and special character. Spaces are not allowed.', true);
      byId('new-password').focus();
      return;
    }

    const button = byId('save-password');
    button.disabled = true;
    button.innerHTML = '<i class="bi bi-arrow-repeat" aria-hidden="true"></i>Updating...';
    try {
      const token = await csrfToken();
      await requestJson('/api/v1/auth/change-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify({
          current_password: currentPassword,
          new_password: newPassword,
          confirm_password: confirmPassword
        })
      });
      event.currentTarget.reset();
      showMessage('password-message', 'Password updated successfully.');
    } catch (error) {
      showMessage('password-message', error.message || 'Unable to update your password.', true);
    } finally {
      button.disabled = false;
      button.innerHTML = '<i class="bi bi-shield-check" aria-hidden="true"></i>Update password';
    }
  }

  document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = byId(button.dataset.passwordToggle);
      const visible = input.type === 'text';
      input.type = visible ? 'password' : 'text';
      button.setAttribute('aria-label', `${visible ? 'Show' : 'Hide'} ${input.id.replaceAll('-', ' ')}`);
      button.querySelector('i').className = visible ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
  });
  document.querySelector('[data-topnav-toggle]')?.addEventListener('click', (event) => {
    const navigation = byId('primary-navigation');
    const open = navigation.classList.toggle('is-open');
    event.currentTarget.setAttribute('aria-expanded', String(open));
  });
  byId('profile-form').addEventListener('submit', saveProfile);
  byId('password-form').addEventListener('submit', changePassword);
  loadProfile();
})();
