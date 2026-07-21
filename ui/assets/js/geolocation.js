(function (window) {
  'use strict';

  function get(options = {}) {
    if (!navigator.geolocation) {
      return Promise.reject(new Error('Geolocation is not supported.'));
    }

    return new Promise((resolve, reject) => {
      navigator.geolocation.getCurrentPosition(
        (position) => resolve({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
          accuracy: position.coords.accuracy
        }),
        reject,
        {
          enableHighAccuracy: options.enableHighAccuracy ?? true,
          timeout: options.timeout || 10000,
          maximumAge: options.maximumAge || 30000
        }
      );
    });
  }

  function distanceMeters(a, b) {
    const radius = 6371000;
    const toRad = (value) => value * Math.PI / 180;
    const lat1 = toRad(a.latitude);
    const lat2 = toRad(b.latitude);
    const deltaLat = toRad(b.latitude - a.latitude);
    const deltaLon = toRad(b.longitude - a.longitude);

    const h =
      Math.sin(deltaLat / 2) ** 2 +
      Math.cos(lat1) * Math.cos(lat2) * Math.sin(deltaLon / 2) ** 2;

    return 2 * radius * Math.asin(Math.sqrt(h));
  }

  window.AbhiprayaGeolocation = { get, distanceMeters };
})(window);
