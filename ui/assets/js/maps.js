(function (window) {
  'use strict';

  function create(element, options = {}) {
    if (!window.L) {
      throw new Error('Leaflet is not loaded.');
    }

    const map = window.L.map(element).setView(
      options.center || [25.6, 85.1],
      options.zoom || 6
    );

    window.L.tileLayer(
      options.tileUrl || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      {
        attribution: options.attribution || '&copy; OpenStreetMap contributors',
        maxZoom: options.maxZoom || 19
      }
    ).addTo(map);

    return map;
  }

  function addMarkers(map, items = [], popup) {
    items.forEach((item) => {
      if (!Number.isFinite(Number(item.latitude)) || !Number.isFinite(Number(item.longitude))) {
        return;
      }

      const marker = window.L.marker([item.latitude, item.longitude]).addTo(map);
      if (popup) marker.bindPopup(popup(item));
    });
  }

  window.AbhiprayaMaps = { create, addMarkers };
})(window);
