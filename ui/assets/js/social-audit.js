(() => {
  'use strict';

  const $ = (id) => document.getElementById(id);
  const message = (text) => {
    $('social-message').textContent = text;
    $('social-message').hidden = !text;
  };
  const request = async (url, options = {}) => {
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', ...(options.headers || {}) },
      ...options,
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok || body.status !== 'success') {
      throw new Error(body.message || 'Request failed.');
    }
    return body.data;
  };

  async function init() {
    try {
      const data = await request('/api/v1/qr?limit=500');
      (data.facilities || []).forEach((item) => {
        $('social-facility').add(new Option(`${item.facilityName} — ${item.facilityNIN}`, item.facilityNIN));
      });
      $('social-date').min = new Date().toISOString().slice(0, 10);
    } catch (error) {
      message(error.message);
    }
  }

  async function loadGeneratedQr(surveyUrl) {
    const response = await fetch(
      `/ui/qr-image.php?format=svg&data=${encodeURIComponent(location.origin + surveyUrl)}`,
      { credentials: 'same-origin', cache: 'no-store' }
    );
    const svg = await response.text();
    if (!response.ok || !svg.includes('<svg')) {
      throw new Error('Unable to generate the QR image.');
    }
    return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
  }

  $('social-audit-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      const csrf = (await request('/api/v1/auth/csrf')).csrf_token;
      const data = await request('/api/v1/social-audit/generate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({
          facility_nin: $('social-facility').value,
          survey_date: $('social-date').value,
        }),
      });
      const qr = $('social-qr');
      qr.src = '/ui/assets/icons/qr.svg';
      $('social-facility-name').textContent = data.facility_name;
      $('social-date-name').textContent = data.survey_date;
      $('social-open').href = data.survey_url;
      $('social-result').hidden = false;
      try {
        qr.src = await loadGeneratedQr(data.survey_url);
        message('');
      } catch (error) {
        message('The QR image could not be created. Use “Open public survey” to continue.');
      }
    } catch (error) {
      message(error.message);
    }
  });

  init();
})();
