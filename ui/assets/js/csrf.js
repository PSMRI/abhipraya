(function (window) {
  'use strict';

  let token = null;
  let pending = null;

  async function fetchToken() {
    if (pending) return pending;

    pending = fetch('/api/v1/auth/csrf-token', {
      credentials: 'include',
      headers: { Accept: 'application/json' },
      cache: 'no-store'
    })
      .then(async (response) => {
        if (!response.ok) return null;
        const payload = await response.json();
        token = payload?.data?.csrf_token || payload?.csrf_token || null;
        return token;
      })
      .finally(() => {
        pending = null;
      });

    return pending;
  }

  window.AbhiprayaCsrf = {
    async get() {
      return token || fetchToken();
    },

    set(value) {
      token = value || null;
    },

    clear() {
      token = null;
    },

    refresh() {
      token = null;
      return fetchToken();
    }
  };
})(window);
