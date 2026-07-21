(function (window, document) {
  'use strict';

  function params() {
    return Object.fromEntries(new URLSearchParams(window.location.search));
  }

  function param(name, fallback = null) {
    return new URLSearchParams(window.location.search).get(name) ?? fallback;
  }

  function update(query, replace = false) {
    const url = new URL(window.location.href);

    Object.entries(query || {}).forEach(([key, value]) => {
      if (value === undefined || value === null || value === '') {
        url.searchParams.delete(key);
      } else {
        url.searchParams.set(key, value);
      }
    });

    history[replace ? 'replaceState' : 'pushState']({}, '', url);
    document.dispatchEvent(new CustomEvent('abhipraya:routechange', {
      detail: { url: url.toString(), params: params() }
    }));
  }

  function navigate(url, options = {}) {
    if (options.replace) {
      window.location.replace(url);
    } else {
      window.location.href = url;
    }
  }

  window.addEventListener('popstate', () => {
    document.dispatchEvent(new CustomEvent('abhipraya:routechange', {
      detail: { url: location.href, params: params() }
    }));
  });

  window.AbhiprayaRouter = { params, param, update, navigate };
})(window, document);
