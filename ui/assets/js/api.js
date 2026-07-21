(function (window) {
  'use strict';

  const DEFAULT_HEADERS = {
    Accept: 'application/json'
  };

  class ApiError extends Error {
    constructor(message, status = 0, payload = null, response = null) {
      super(message);
      this.name = 'ApiError';
      this.status = status;
      this.payload = payload;
      this.response = response;
    }
  }

  function buildUrl(url, query = null) {
    const finalUrl = new URL(url, window.location.origin);
    if (query) {
      Object.entries(query).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          finalUrl.searchParams.set(key, String(value));
        }
      });
    }
    return finalUrl.toString();
  }

  async function request(url, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    const headers = new Headers(DEFAULT_HEADERS);

    Object.entries(options.headers || {}).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        headers.set(key, String(value));
      }
    });

    let body = options.body;

    if (body && !(body instanceof FormData) && typeof body !== 'string') {
      headers.set('Content-Type', 'application/json');
      body = JSON.stringify(body);
    }

    if (!['GET', 'HEAD'].includes(method) && window.AbhiprayaCsrf) {
      const token = await window.AbhiprayaCsrf.get();
      if (token) headers.set('X-CSRF-Token', token);
    }

    const controller = new AbortController();
    const timeout = window.setTimeout(
      () => controller.abort(),
      options.timeout || 30000
    );

    try {
      const response = await fetch(buildUrl(url, options.query), {
        method,
        headers,
        body,
        credentials: 'include',
        signal: options.signal || controller.signal,
        cache: options.cache || 'no-store'
      });

      const contentType = response.headers.get('content-type') || '';
      let payload = null;

      if (contentType.includes('application/json')) {
        payload = await response.json();
      } else if (options.responseType === 'blob') {
        payload = await response.blob();
      } else {
        payload = await response.text();
      }

      if (!response.ok) {
        if (response.status === 401) {
          document.dispatchEvent(new CustomEvent('abhipraya:unauthorized'));
        }

        if (response.status === 403) {
          document.dispatchEvent(new CustomEvent('abhipraya:forbidden'));
        }

        throw new ApiError(
          payload?.message || `Request failed with status ${response.status}.`,
          response.status,
          payload,
          response
        );
      }

      if (!['GET', 'HEAD'].includes(method) && payload?.data?.csrf_token) {
        window.AbhiprayaCsrf?.set(payload.data.csrf_token);
      }

      return payload;
    } catch (error) {
      if (error.name === 'AbortError') {
        throw new ApiError('The request timed out. Please try again.', 0);
      }
      throw error;
    } finally {
      clearTimeout(timeout);
    }
  }

  async function download(url, query = null, filename = null) {
    const response = await fetch(buildUrl(url, query), {
      credentials: 'include'
    });

    if (!response.ok) {
      throw new ApiError('Unable to download file.', response.status);
    }

    const blob = await response.blob();
    const link = document.createElement('a');
    const objectUrl = URL.createObjectURL(blob);

    link.href = objectUrl;
    link.download =
      filename ||
      response.headers.get('content-disposition')?.match(/filename="?([^"]+)"?/)?.[1] ||
      'download';

    document.body.appendChild(link);
    link.click();
    link.remove();

    URL.revokeObjectURL(objectUrl);
  }

  window.AbhiprayaApi = {
    request,
    download,
    get: (url, query, options = {}) => request(url, { ...options, method: 'GET', query }),
    post: (url, body, options = {}) => request(url, { ...options, method: 'POST', body }),
    put: (url, body, options = {}) => request(url, { ...options, method: 'PUT', body }),
    patch: (url, body, options = {}) => request(url, { ...options, method: 'PATCH', body }),
    delete: (url, body = null, options = {}) => request(url, { ...options, method: 'DELETE', body }),
    ApiError
  };
})(window);
