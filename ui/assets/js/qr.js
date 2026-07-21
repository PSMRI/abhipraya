(function (window) {
  'use strict';

  async function generate(payload) {
    return window.AbhiprayaApi.post('/api/v1/qr', payload);
  }

  async function regenerate(id) {
    return window.AbhiprayaApi.post(`/api/v1/qr/${id}/regenerate`, {});
  }

  async function activate(id) {
    return window.AbhiprayaApi.post(`/api/v1/qr/${id}/activate`, {});
  }

  async function deactivate(id) {
    return window.AbhiprayaApi.post(`/api/v1/qr/${id}/deactivate`, {});
  }

  async function download(id, format = 'png') {
    return window.AbhiprayaApi.download(
      `/api/v1/qr/${id}/download`,
      { format }
    );
  }

  function renderCard(container, record) {
    container.querySelector('[data-qr-image]')?.setAttribute('src', record.preview_url || '');
    container.querySelector('[data-qr-department]').textContent = record.department_name || '';
    container.querySelector('[data-qr-facility]').textContent = record.facility_name || '';
    container.querySelector('[data-qr-url]').textContent = record.qr_url || '';
    container.querySelector('[data-qr-generated-at]').textContent = record.generated_at || '';
  }

  window.AbhiprayaQr = {
    generate,
    regenerate,
    activate,
    deactivate,
    download,
    renderCard
  };
})(window);
