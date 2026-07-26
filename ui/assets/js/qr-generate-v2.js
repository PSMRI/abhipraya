(function () {
  'use strict';
  const form = document.getElementById('qr-form');
  const workspace = document.getElementById('qr-workspace');
  const message = document.getElementById('qr-message');
  const search = document.getElementById('facility-search');
  const nin = document.getElementById('facility-nin');
  const results = document.getElementById('facility-results');
  const department = document.getElementById('department-id');
  const generate = document.getElementById('generate-button');
  const app = document.querySelector('.ab-app');
  const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
  const selected = document.getElementById('selected-facility');
  let departments = [];
  let queryTimer;
  let visibleFacilities = [];
  let activeFacilityIndex = -1;
  let posterDataUrl = '';
  let facilityAdministrator = false;
  function setSidebarCollapsed(collapsed) {
    app?.classList.toggle('is-collapsed', collapsed);
    sidebarToggle?.setAttribute('aria-pressed', String(collapsed));
    sidebarToggle?.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    sidebarToggle?.querySelector('.sr-only')?.replaceChildren(collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    try { localStorage.setItem('abhipraya_sidebar_collapsed', String(collapsed)); } catch (_) { /* Storage is optional. */ }
  }

  async function json(response) {
    const raw = await response.text();
    try { return raw ? JSON.parse(raw) : {}; }
    catch (_) { throw new Error('The server returned an invalid response. Please refresh the page and sign in again.'); }
  }
  function showMessage(text, error) {
    message.textContent = text || '';
    message.toggleAttribute('role', Boolean(error));
    if (text) {
      const notify = error ? window.AbhiprayaFeedback?.error : window.AbhiprayaFeedback?.success;
      notify?.(text);
    }
  }
  function option(facility, index) {
    const button = document.createElement('button');
    button.type = 'button'; button.className = 'ab-facility-option'; button.role = 'option'; button.id = 'facility-option-' + index;
    button.setAttribute('aria-selected', 'false');
    button.innerHTML = '<strong></strong><small></small>';
    button.querySelector('strong').textContent = facility.facilityName || 'Unnamed facility';
    button.querySelector('small').textContent = 'NIN ' + facility.facilityNIN + (facility.facilityAddress ? ' · ' + facility.facilityAddress : '');
    button.addEventListener('click', () => choose(facility));
    return button;
  }
  function choose(facility) {
    nin.value = facility.facilityNIN;
    search.value = facility.facilityName + ' — ' + facility.facilityNIN;
    document.getElementById('selected-facility-name').textContent = facility.facilityName;
    document.getElementById('selected-facility-meta').textContent = 'NIN ' + facility.facilityNIN + (facility.facilityAddress ? ' · ' + facility.facilityAddress : '');
    selected.hidden = false; results.hidden = true; search.setAttribute('aria-expanded', 'false');
    department.disabled = false; generate.disabled = false;
  }
  function renderFacilities(facilities) {
    visibleFacilities = facilities;
    activeFacilityIndex = -1;
    results.replaceChildren();
    facilities.forEach((facility, index) => results.appendChild(option(facility, index)));
    results.hidden = facilities.length === 0;
    search.setAttribute('aria-expanded', String(facilities.length > 0));
    search.removeAttribute('aria-activedescendant');
  }
  function setActiveFacility(index) {
    if (visibleFacilities.length === 0) return;
    activeFacilityIndex = (index + visibleFacilities.length) % visibleFacilities.length;
    results.querySelectorAll('[role="option"]').forEach((item, itemIndex) => {
      const active = itemIndex === activeFacilityIndex;
      item.setAttribute('aria-selected', String(active));
      item.classList.toggle('is-active', active);
      if (active) item.scrollIntoView({ block: 'nearest' });
    });
    search.setAttribute('aria-activedescendant', 'facility-option-' + activeFacilityIndex);
  }
  async function loadConfig(query) {
    const url = '/api/v1/qr?limit=25&search=' + encodeURIComponent(query || '');
    const payload = await json(await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }));
    if (payload.status !== 'success') throw new Error(payload.message || 'Unable to load facilities.');
    departments = payload.data.departments || [];
    department.replaceChildren(new Option('Select department', ''));
    departments.forEach((item) => department.add(new Option(item.departmentName, item.departmentId)));
    const facilities = payload.data.facilities || [];
    if (facilities.length === 1 && !nin.value) choose(facilities[0]);
    else renderFacilities(facilities);
  }
  async function initialise() {
    try {
      const payload = await json(await fetch('/api/v1/auth/me', { credentials: 'same-origin', headers: { Accept: 'application/json' } }));
      const roleId = Number(payload.data?.user?.role_id);
      if (payload.status !== 'success' || ![1, 2, 3].includes(roleId)) throw new Error('Unauthorized');
      const username = String(payload.data.user.u_name || '');
      const roleLabel = String(payload.data.user.role_name || 'Administrator');
      const displayName = payload.data.user.full_name || (/^\d+$/.test(username) ? '' : username) || roleLabel;
      facilityAdministrator = roleId === 2;
      document.body.classList.toggle('ab-facility-admin', facilityAdministrator);
      document.querySelectorAll('.ab-user, [data-profile-name]').forEach((node) => { node.textContent = displayName; });
      document.getElementById('current-date').textContent = new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date());
    } catch (error) {
      if (/Unauthorized/i.test(error.message)) {
        window.location.assign('/admin/login?reason=session-expired');
        return;
      }
      workspace.hidden = false;
      showMessage('Unable to verify administrator access. Please refresh the page.', true);
      return;
    }

    workspace.hidden = false;
    try {
      await loadConfig('');
      if (facilityAdministrator) {
        search.closest('.ab-field').hidden = true;
      }
      showMessage('');
    } catch (error) {
      showMessage(error.message || 'Unable to load QR configuration. Please try again.', true);
    }
  }
  search.addEventListener('input', () => {
    nin.value = ''; selected.hidden = true; department.disabled = true; generate.disabled = true;
    window.clearTimeout(queryTimer);
    queryTimer = window.setTimeout(() => loadConfig(search.value.trim()).catch((error) => showMessage(error.message, true)), 180);
  });
  search.addEventListener('focus', () => { if (results.childElementCount) results.hidden = false; });
  search.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowDown') { event.preventDefault(); if (!results.hidden) setActiveFacility(activeFacilityIndex + 1); }
    if (event.key === 'ArrowUp') { event.preventDefault(); if (!results.hidden) setActiveFacility(activeFacilityIndex - 1); }
    if (event.key === 'Enter' && activeFacilityIndex >= 0) { event.preventDefault(); choose(visibleFacilities[activeFacilityIndex]); }
    if (event.key === 'Escape') { results.hidden = true; search.setAttribute('aria-expanded', 'false'); search.removeAttribute('aria-activedescendant'); }
  });
  department.addEventListener('change', () => { generate.disabled = !nin.value || !department.value; });

  function loadImage(source, anonymous) {
    return new Promise((resolve, reject) => {
      const image = new Image();
      if (anonymous) image.crossOrigin = 'anonymous';
      image.onload = () => resolve(image);
      image.onerror = () => reject(new Error('Unable to prepare the QR poster image.'));
      image.src = source;
    });
  }

  function fitPosterText(context, text, maximumWidth, initialSize) {
    let size = initialSize;
    context.font = `700 ${size}px Arial, sans-serif`;
    while (size > 12 && context.measureText(text).width > maximumWidth) {
      size -= 1;
      context.font = `700 ${size}px Arial, sans-serif`;
    }
    return size;
  }

  async function createPoster(data) {
    const qrSource = '/ui/qr-image.php?data=' + encodeURIComponent(data.survey_url);
    const sourceImage = document.getElementById('qr-image');
    sourceImage.src = qrSource;
    let template;
    let qrImage;
    try {
      [template, qrImage] = await Promise.all([
        loadImage('/ui/assets/img/qr-poster-template.jpg', false),
        loadImage(qrSource, false)
      ]);
    } catch (_) {
      /* Production environments may block canvas CORS for the QR provider.
         Keep the generated QR usable instead of failing the whole request. */
      posterDataUrl = qrSource;
      const poster = document.getElementById('qr-poster');
      const sourceImage = document.getElementById('qr-image');
      poster.src = '/ui/assets/img/qr-poster-template.jpg';
      poster.alt = `Feedback QR poster outline for ${data.facility_name || 'facility'}`;
      sourceImage.hidden = true;
      sourceImage.src = qrSource;
      sourceImage.alt = `Generated feedback QR code for ${data.facility_name || 'facility'}`;
      return;
    }
    const canvas = document.createElement('canvas');
    canvas.width = template.naturalWidth;
    canvas.height = template.naturalHeight;
    const context = canvas.getContext('2d');
    context.drawImage(template, 0, 0);

    const width = canvas.width;
    const height = canvas.height;
    const facilityName = String(data.facility_name || '');
    const departmentName = String(data.department_name || '');
    context.textAlign = 'center';
    context.fillStyle = '#ffffff';
    fitPosterText(context, facilityName, width * 0.82, Math.round(width * 0.040));
    context.fillText(facilityName, width / 2, Math.round(height * 0.060));
    context.fillStyle = '#dfeeff';
    fitPosterText(context, departmentName, width * 0.80, Math.round(width * 0.029));
    context.fillText(departmentName, width / 2, Math.round(height * 0.096));

    const size = Math.round(width * 0.545);
    const x = Math.round((width - size) / 2);
    const y = Math.round(height * 0.265);
    const quietZone = Math.max(8, Math.round(width * 0.012));
    context.fillStyle = '#ffffff';
    context.fillRect(x - quietZone, y - quietZone, size + quietZone * 2, size + quietZone * 2);
    context.imageSmoothingEnabled = false;
    context.drawImage(qrImage, x, y, size, size);

    const poster = document.getElementById('qr-poster');
    try {
      posterDataUrl = canvas.toDataURL('image/png');
      poster.src = posterDataUrl;
      poster.alt = `Feedback QR poster for ${facilityName}, ${departmentName}`;
    } catch (_) {
      /* A cross-origin QR image can taint the canvas during export. */
      posterDataUrl = qrSource;
      poster.src = '/ui/assets/img/qr-poster-template.jpg';
      poster.alt = `Feedback QR poster outline for ${facilityName || 'facility'}`;
      const sourceImage = document.getElementById('qr-image');
      sourceImage.hidden = true;
      sourceImage.src = qrSource;
      sourceImage.alt = `Generated feedback QR code for ${facilityName || 'facility'}`;
    }
  }
  form.addEventListener('submit', async (event) => {
    event.preventDefault(); showMessage('');
    if (!nin.value || !department.value) { showMessage('Select a facility and department.', true); return; }
    generate.disabled = true; generate.textContent = 'Generating…';
    try {
      const csrfToken = await window.AbhiprayaCsrf?.get();
      const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
      if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
      const response = await fetch('/api/v1/qr/generate', { method: 'POST', credentials: 'same-origin', headers, body: JSON.stringify({ facility_nin: nin.value, department_id: department.value }) });
      const payload = await json(response);
      if (!response.ok || payload.status !== 'success') throw new Error(payload.message || 'Unable to generate the QR code.');
      const data = payload.data;
      document.getElementById('qr-url').value = data.survey_url;
      document.getElementById('qr-facility').textContent = data.facility_name;
      document.getElementById('qr-department').textContent = data.department_name;
      document.getElementById('qr-reference').textContent = data.reference;
      await createPoster(data);
      document.getElementById('qr-empty-state').hidden = true;
      document.getElementById('qr-result').hidden = false;
      showMessage('QR code generated successfully. You can now download or print it.', false);
    } catch (error) { showMessage(error.message, true); }
    finally { generate.disabled = false; generate.textContent = 'Generate QR code'; }
  });
  document.getElementById('copy-qr-url').addEventListener('click', async function () {
    try {
      await navigator.clipboard.writeText(document.getElementById('qr-url').value);
      showMessage('Survey link copied to the clipboard.', false);
    } catch (_) {
      showMessage('Unable to copy the survey link. Please try again.', true);
    }
  });
  document.getElementById('download-qr-poster').addEventListener('click', function () {
    if (!posterDataUrl) { showMessage('Generate a QR poster before downloading it.', true); return; }
    const fileName = [document.getElementById('qr-reference').textContent || 'feedback', 'qr-poster']
      .join('-').replace(/[^a-z0-9_-]+/gi, '-').replace(/^-+|-+$/g, '').toLowerCase() + '.png';
    const download = document.createElement('a');
    download.href = posterDataUrl;
    download.download = fileName;
    document.body.appendChild(download);
    download.click();
    download.remove();
  });
  document.querySelector('[data-menu-toggle]')?.addEventListener('click', function () { const sidebar = document.getElementById('primary-navigation'); const open = sidebar.classList.toggle('is-open'); this.setAttribute('aria-expanded', String(open)); });
  document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => { document.documentElement.dataset.theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'; });
  try { setSidebarCollapsed(localStorage.getItem('abhipraya_sidebar_collapsed') === 'true'); } catch (_) { /* Use default. */ }
  sidebarToggle?.addEventListener('click', () => { if (window.matchMedia('(max-width: 880px)').matches) { const sidebar = document.getElementById('primary-navigation'); const open = !sidebar.classList.contains('is-open'); sidebar.classList.toggle('is-open', open); sidebarToggle.setAttribute('aria-expanded', String(open)); return; } setSidebarCollapsed(!app?.classList.contains('is-collapsed')); });
  document.getElementById('logout-button')?.addEventListener('click', async () => { await fetch('/api/v1/auth/logout', { method: 'POST', credentials: 'same-origin' }); window.location.assign('/admin/login'); });
  initialise();
}());
