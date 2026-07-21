(function (window) {
  'use strict';

  const PREFIX = 'abhipraya_';

  const Storage = {
    get(key, fallback = null) {
      try {
        const raw = localStorage.getItem(PREFIX + key);
        return raw === null ? fallback : JSON.parse(raw);
      } catch (error) {
        console.warn('Storage read failed:', key, error);
        return fallback;
      }
    },

    set(key, value) {
      try {
        localStorage.setItem(PREFIX + key, JSON.stringify(value));
        return true;
      } catch (error) {
        console.warn('Storage write failed:', key, error);
        return false;
      }
    },

    remove(key) {
      try {
        localStorage.removeItem(PREFIX + key);
      } catch (error) {
        console.warn('Storage remove failed:', key, error);
      }
    },

    clear() {
      try {
        Object.keys(localStorage)
          .filter((key) => key.startsWith(PREFIX))
          .forEach((key) => localStorage.removeItem(key));
      } catch (error) {
        console.warn('Storage clear failed:', error);
      }
    },

    sessionGet(key, fallback = null) {
      try {
        const raw = sessionStorage.getItem(PREFIX + key);
        return raw === null ? fallback : JSON.parse(raw);
      } catch (error) {
        return fallback;
      }
    },

    sessionSet(key, value) {
      try {
        sessionStorage.setItem(PREFIX + key, JSON.stringify(value));
        return true;
      } catch (error) {
        return false;
      }
    },

    sessionRemove(key) {
      try {
        sessionStorage.removeItem(PREFIX + key);
      } catch (error) {
        console.warn('Session storage remove failed:', error);
      }
    }
  };

  window.AbhiprayaStorage = Storage;
})(window);
