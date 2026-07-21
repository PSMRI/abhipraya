(function (window) {
  'use strict';

  const KEY = 'device_id';

  function uuid() {
    return crypto.randomUUID
      ? crypto.randomUUID()
      : 'device-' + Date.now() + '-' + Math.random().toString(36).slice(2);
  }

  function getId() {
    let id = window.AbhiprayaStorage?.get(KEY);
    if (!id) {
      id = uuid();
      window.AbhiprayaStorage?.set(KEY, id);
    }
    return id;
  }

  function info() {
    return {
      device_id: getId(),
      user_agent: navigator.userAgent,
      language: navigator.language,
      platform: navigator.platform,
      screen: {
        width: window.screen.width,
        height: window.screen.height
      }
    };
  }

  window.AbhiprayaDevice = { getId, info };
})(window);
