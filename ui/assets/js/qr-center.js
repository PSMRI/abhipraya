(() => {
  'use strict';

  const form = document.getElementById('qr-form');
  const search = document.getElementById('facility-search');
  const facilityNin = document.getElementById('facility-nin');
  const facilityResults = document.getElementById('facility-results');
  const selectedFacility = document.getElementById('selected-facility');
  const department = document.getElementById('department-id');
  const generateButton = document.getElementById('generate-button');
  const message = document.getElementById('qr-message');
  let visibleFacilities = [];
  let selectedFacilityData = null;
  let activeFacilityIndex = -1;
  let searchTimer = null;
  let posterDataUrl = '';

  function showMessage(text, isError = false) {
    message.textContent = text;
    message.hidden = !text;
    message.classList.toggle('is-error', isError);
    if (text) message.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function updateSteps() {
    const facilitySelected = Boolean(facilityNin.value);
    const departmentSelected = Boolean(department.value);
    document.querySelectorAll('[data-step]').forEach((step) => {
      const index = Number(step.dataset.step);
      step.classList.toggle('is-complete', index === 1 ? facilitySelected : index === 2 ? departmentSelected : false);
      step.classList.toggle('is-active', index === (facilitySelected ? (departmentSelected ? 3 : 2) : 1));
    });
    generateButton.disabled = !facilitySelected || !departmentSelected;
  }

  function facilityOption(facility, index) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'facility-option';
    button.id = `facility-option-${index}`;
    button.setAttribute('role', 'option');
    button.setAttribute('aria-selected', 'false');

    const name = document.createElement('strong');
    name.textContent = facility.facilityName || 'Unnamed facility';
    const meta = document.createElement('small');
    meta.textContent = `NIN ${facility.facilityNIN}${facility.facilityAddress ? ` \u00b7 ${facility.facilityAddress}` : ''}`;
    button.append(name, meta);
    button.addEventListener('click', () => chooseFacility(facility));
    return button;
  }

  function renderFacilityResults(facilities) {
    visibleFacilities = facilities;
    activeFacilityIndex = -1;
    facilityResults.replaceChildren();
    facilities.forEach((facility, index) => facilityResults.appendChild(facilityOption(facility, index)));
    facilityResults.hidden = facilities.length === 0;
    search.setAttribute('aria-expanded', String(facilities.length > 0));
    search.removeAttribute('aria-activedescendant');
  }

  function chooseFacility(facility) {
    selectedFacilityData = facility;
    facilityNin.value = facility.facilityNIN;
    search.value = `${facility.facilityName} \u2014 ${facility.facilityNIN}`;
    document.getElementById('selected-facility-name').textContent = facility.facilityName;
    document.getElementById('selected-facility-meta').textContent =
      `NIN ${facility.facilityNIN}${facility.facilityAddress ? ` \u00b7 ${facility.facilityAddress}` : ''}`;
    selectedFacility.hidden = false;
    facilityResults.hidden = true;
    search.setAttribute('aria-expanded', 'false');
    department.disabled = false;
    updateSteps();
  }

  function clearFacility() {
    selectedFacilityData = null;
    facilityNin.value = '';
    search.value = '';
    selectedFacility.hidden = true;
    department.value = '';
    department.disabled = true;
    document.getElementById('qr-result').hidden = true;
    document.getElementById('qr-empty-state').hidden = false;
    document.getElementById('preview-status').textContent = 'Not generated';
    document.getElementById('preview-status').classList.remove('is-ready');
    posterDataUrl = '';
    updateSteps();
    search.focus();
    loadConfiguration('').catch((error) => showMessage(error.message, true));
  }

  function setActiveFacility(index) {
    if (!visibleFacilities.length) return;
    activeFacilityIndex = (index + visibleFacilities.length) % visibleFacilities.length;
    facilityResults.querySelectorAll('[role="option"]').forEach((item, itemIndex) => {
      const active = itemIndex === activeFacilityIndex;
      item.classList.toggle('is-active', active);
      item.setAttribute('aria-selected', String(active));
      if (active) item.scrollIntoView({ block: 'nearest' });
    });
    search.setAttribute('aria-activedescendant', `facility-option-${activeFacilityIndex}`);
  }

  async function requestJson(url, options = {}) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      ...options,
      headers: { Accept: 'application/json', ...(options.headers || {}) }
    });
    const payload = await response.json().catch(() => ({}));
    if (response.status === 401) {
      location.assign('/admin/login?reason=session-expired');
      throw new Error('Your session has expired.');
    }
    if (!response.ok || payload.status !== 'success') {
      const validation = payload?.data?.errors || payload?.errors || {};
      const firstError = Object.values(validation)[0];
      throw new Error(firstError || payload.message || 'The request could not be completed.');
    }
    return payload;
  }

  async function csrfToken() {
    const payload = await requestJson('/api/v1/auth/csrf');
    return payload.data?.csrf_token || '';
  }

  async function loadConfiguration(query = '') {
    const payload = await requestJson(`/api/v1/qr?limit=50&search=${encodeURIComponent(query)}`);
    const departments = payload.data?.departments || [];
    if (department.options.length <= 1) {
      departments.forEach((item) => department.add(new Option(item.departmentName, item.departmentId)));
    }

    const facilities = payload.data?.facilities || [];
    renderFacilityResults(facilities);
    if (facilities.length === 1 && !facilityNin.value) chooseFacility(facilities[0]);
    return payload.data || {};
  }

  function loadImage(source) {
    return new Promise((resolve, reject) => {
      const image = new Image();
      image.onload = () => resolve(image);
      image.onerror = () => reject(new Error('Unable to prepare the QR poster image.'));
      image.src = source;
    });
  }

  function fitText(context, text, maximumWidth, initialSize) {
    let size = initialSize;
    context.font = `700 ${size}px Arial, sans-serif`;
    while (size > 12 && context.measureText(text).width > maximumWidth) {
      size -= 1;
      context.font = `700 ${size}px Arial, sans-serif`;
    }
  }

  async function createPoster(data) {
    const qrSource = `/ui/qr-image.php?data=${encodeURIComponent(data.survey_url)}`;
    const templateSource = '/ui/assets/img/qr-poster-template.jpg';
    const qrImage = document.getElementById('qr-image');
    const poster = document.getElementById('qr-poster');
    qrImage.src = qrSource;
    qrImage.alt = `Feedback QR code for ${data.facility_name}`;

    try {
      const [template, generatedQr] = await Promise.all([loadImage(templateSource), loadImage(qrSource)]);
      const canvas = document.createElement('canvas');
      canvas.width = template.naturalWidth;
      canvas.height = template.naturalHeight;
      const context = canvas.getContext('2d');
      context.drawImage(template, 0, 0);

      const width = canvas.width;
      const height = canvas.height;
      context.textAlign = 'center';
      context.fillStyle = '#ffffff';
      fitText(context, String(data.facility_name || ''), width * .82, Math.round(width * .04));
      context.fillText(String(data.facility_name || ''), width / 2, Math.round(height * .06));
      context.fillStyle = '#dfeeff';
      fitText(context, String(data.department_name || ''), width * .8, Math.round(width * .029));
      context.fillText(String(data.department_name || ''), width / 2, Math.round(height * .096));

      const size = Math.round(width * .545);
      const x = Math.round((width - size) / 2);
      const y = Math.round(height * .265);
      const quietZone = Math.max(8, Math.round(width * .012));
      context.fillStyle = '#ffffff';
      context.fillRect(x - quietZone, y - quietZone, size + quietZone * 2, size + quietZone * 2);
      context.imageSmoothingEnabled = false;
      context.drawImage(generatedQr, x, y, size, size);

      posterDataUrl = canvas.toDataURL('image/png');
      poster.src = posterDataUrl;
      poster.alt = '';
      poster.hidden = true;
      qrImage.hidden = true;
    } catch (error) {
      posterDataUrl = qrSource;
      poster.hidden = true;
      qrImage.hidden = true;
      throw new Error('The QR link was created, but the poster template could not be prepared. You can still download the QR code.');
    }
  }

  async function copySurveyUrl(url) {
    try {
      await navigator.clipboard.writeText(url);
      showMessage('Survey link copied to the clipboard.');
    } catch (_) {
      showMessage('Unable to copy the survey link. Please copy it from the browser address bar.', true);
    }
  }

  async function generateQr(event) {
    event.preventDefault();
    showMessage('');
    if (!facilityNin.value || !department.value) {
      showMessage('Select a facility and department before generating the poster.', true);
      return;
    }

    generateButton.disabled = true;
    generateButton.querySelector('span').textContent = 'Generating...';
    try {
      const token = await csrfToken();
      const payload = await requestJson('/api/v1/qr/generate', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token
        },
        body: JSON.stringify({
          facility_nin: facilityNin.value,
          department_id: department.value
        })
      });
      const data = payload.data;
      document.getElementById('qr-url').value = data.survey_url;
      document.getElementById('qr-facility').textContent = data.facility_name;
      document.getElementById('qr-department').textContent = data.department_name;
      document.getElementById('qr-reference').textContent = data.reference;
      document.getElementById('open-survey-url').href = data.survey_url;

      let posterWarning = '';
      try {
        await createPoster(data);
      } catch (error) {
        posterWarning = error.message;
      }

      document.getElementById('qr-empty-state').hidden = true;
      document.getElementById('qr-result').hidden = false;
      const status = document.getElementById('preview-status');
      status.textContent = 'Ready';
      status.classList.add('is-ready');
      showMessage(posterWarning || 'QR poster generated successfully. It is ready to download or test.', Boolean(posterWarning));
    } catch (error) {
      showMessage(error.message || 'Unable to generate the QR poster.', true);
    } finally {
      generateButton.querySelector('span').textContent = 'Generate QR poster';
      updateSteps();
    }
  }

  function downloadPoster() {
    if (!posterDataUrl) {
      showMessage('Generate a QR poster before downloading it.', true);
      return;
    }
    const reference = document.getElementById('qr-reference').textContent || 'feedback';
    const fileName = `${reference}-qr-poster`.replace(/[^a-z0-9_-]+/gi, '-').replace(/^-+|-+$/g, '').toLowerCase();
    const link = document.createElement('a');
    link.href = posterDataUrl;
    link.download = `${fileName}.png`;
    document.body.appendChild(link);
    link.click();
    link.remove();
  }

  search.addEventListener('input', () => {
    facilityNin.value = '';
    selectedFacility.hidden = true;
    department.value = '';
    department.disabled = true;
    updateSteps();
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      loadConfiguration(search.value.trim()).catch((error) => showMessage(error.message, true));
    }, 220);
  });
  search.addEventListener('focus', () => {
    if (facilityResults.childElementCount) facilityResults.hidden = false;
  });
  search.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      if (!facilityResults.hidden) setActiveFacility(activeFacilityIndex + 1);
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      if (!facilityResults.hidden) setActiveFacility(activeFacilityIndex - 1);
    } else if (event.key === 'Enter' && activeFacilityIndex >= 0) {
      event.preventDefault();
      chooseFacility(visibleFacilities[activeFacilityIndex]);
    } else if (event.key === 'Escape') {
      facilityResults.hidden = true;
      search.setAttribute('aria-expanded', 'false');
      search.removeAttribute('aria-activedescendant');
    }
  });
  document.addEventListener('click', (event) => {
    if (!event.target.closest('.facility-search-wrap') && !event.target.closest('#facility-results')) {
      facilityResults.hidden = true;
      search.setAttribute('aria-expanded', 'false');
    }
  });
  department.addEventListener('change', updateSteps);
  document.getElementById('change-facility').addEventListener('click', clearFacility);
  form.addEventListener('submit', generateQr);
  document.getElementById('copy-qr-url').addEventListener('click', () => copySurveyUrl(document.getElementById('qr-url').value));
  document.getElementById('download-qr-poster').addEventListener('click', downloadPoster);
  document.querySelector('[data-topnav-toggle]')?.addEventListener('click', (event) => {
    const navigation = document.getElementById('primary-navigation');
    const open = navigation.classList.toggle('is-open');
    event.currentTarget.setAttribute('aria-expanded', String(open));
  });

  updateSteps();
  loadConfiguration('').catch((error) => showMessage(error.message || 'Unable to load QR configuration.', true));
})();
