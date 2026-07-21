(function (window, document) {
  'use strict';

  let currentUser = null;

  async function login(credentials) {
    const payload = await window.AbhiprayaApi.post('/api/modules/auth/v1/login.php', credentials);
    currentUser = payload.data?.user || null;

    if (payload.data?.csrf_token) {
      window.AbhiprayaCsrf?.set(payload.data.csrf_token);
    }

    return payload;
  }

  async function logout() {
    try {
      await window.AbhiprayaApi.post('/api/modules/auth/v1/logout.php', {});
    } finally {
      currentUser = null;
      window.AbhiprayaCsrf?.clear();
      window.AbhiprayaStorage?.remove('user');
      window.location.href = '/ui/login.html';
    }
  }

  async function me(force = false) {
    if (!force && currentUser) return currentUser;

    const payload = await window.AbhiprayaApi.get('/api/modules/auth/v1/me.php');
    currentUser = payload.data?.user || payload.data || null;
    window.AbhiprayaStorage?.set('user', currentUser);

    document.dispatchEvent(new CustomEvent('abhipraya:userloaded', {
      detail: currentUser
    }));

    return currentUser;
  }

  async function requireAuth() {
    try {
      return await me();
    } catch (error) {
      window.location.href = '/ui/login.html';
      throw error;
    }
  }

  document.addEventListener('click', (event) => {
    if (event.target.closest('#logout-button, [data-action="logout"]')) {
      event.preventDefault();
      logout();
    }
  });

  document.addEventListener('abhipraya:unauthorized', () => {
    if (!location.pathname.endsWith('/login.html')) {
      location.href = '/ui/login.html?reason=session-expired';
    }
  });

  window.AbhiprayaAuth = {
    login,
    logout,
    me,
    requireAuth,
    get user() {
      return currentUser;
    }
  };
})(window, document);
