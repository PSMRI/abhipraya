(() => {
  'use strict';

  const byId = (id) => document.getElementById(id);
  const form = byId('capa-filter');
  const facilityField = byId('capa-facility-field');
  const facilitySearch = byId('capa-facility-search');
  const facilityInput = byId('capa-facility');
  const facilityResults = byId('capa-facility-results');
  const department = byId('capa-department');
  const surveyVersion = byId('capa-survey-version');
  const month = byId('capa-month');
  const message = byId('capa-message');
  const report = byId('capa-report');
  const resultHost = byId('capa-results');
  const downloadButton = byId('download-capa');
  const printButton = byId('print-capa');
  let facilities = [];
  let selectedFacility = null;
  let currentRows = [];
  let csrfToken = '';
  let searchTimer = null;
  let versionLoadId = 0;

  function currentMonth() {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  }

  function monthRange(value) {
    if (!/^\d{4}-\d{2}$/.test(value)) return null;
    const [year, monthNumber] = value.split('-').map(Number);
    const lastDay = new Date(year, monthNumber, 0).getDate();
    return {
      from: `${value}-01`,
      to: `${value}-${String(lastDay).padStart(2, '0')}`,
      label: new Date(year, monthNumber - 1, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
    };
  }

  function showMessage(text, error = false) {
    message.hidden = !text;
    message.textContent = text || '';
    message.classList.toggle('is-error', error);
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
      const details = payload.errors && typeof payload.errors === 'object'
        ? Object.values(payload.errors)[0]
        : '';
      throw new Error(details || payload.message || 'The request could not be completed.');
    }
    return payload;
  }

  async function getCsrfToken() {
    if (csrfToken) return csrfToken;
    const payload = await requestJson('/api/v1/auth/csrf');
    csrfToken = payload.data?.csrf_token || '';
    return csrfToken;
  }

  function chooseFacility(item) {
    selectedFacility = item;
    facilityInput.value = String(item.facilityNIN);
    facilitySearch.value = `${item.facilityName} — ${item.facilityNIN}`;
    facilityResults.hidden = true;
    facilitySearch.setAttribute('aria-expanded', 'false');
    department.disabled = false;
    department.value = '';
    surveyVersion.replaceChildren(new Option('Select version', ''));
    surveyVersion.disabled = true;
  }

  function renderFacilityResults(query = '') {
    const needle = query.trim().toLowerCase();
    const matches = facilities.filter((item) =>
      !needle ||
      String(item.facilityName || '').toLowerCase().includes(needle) ||
      String(item.facilityNIN || '').includes(needle)
    ).slice(0, 30);
    facilityResults.replaceChildren();
    matches.forEach((item) => {
      const option = document.createElement('button');
      option.type = 'button';
      option.role = 'option';
      const name = document.createElement('strong');
      const meta = document.createElement('small');
      name.textContent = item.facilityName || 'Unnamed facility';
      meta.textContent = `NIN ${item.facilityNIN}`;
      option.append(name, meta);
      option.addEventListener('click', () => chooseFacility(item));
      facilityResults.appendChild(option);
    });
    facilityResults.hidden = matches.length === 0;
    facilitySearch.setAttribute('aria-expanded', String(matches.length > 0));
  }

  async function loadConfiguration(query = '') {
    const payload = await requestJson(`/api/v1/qr?limit=50&search=${encodeURIComponent(query)}`);
    facilities = payload.data?.facilities || [];
    department.replaceChildren(new Option('Select department', ''));
    (payload.data?.departments || []).forEach((item) => {
      department.add(new Option(item.departmentName, item.departmentId));
    });
    if (facilities.length === 1 && !facilityInput.value) {
      chooseFacility(facilities[0]);
    } else {
      renderFacilityResults(query);
    }
  }

  function questionKey(indicator) {
    const configuredKey = String(indicator.question_key || '').trim();
    if (/^srvy_Q(?:[0-9]|[12][0-9]|30)$/.test(configuredKey)) return configuredKey;
    const match = String(indicator.indicator_id || '').match(/^Q(\d{1,2})$/);
    if (!match) return '';
    const index = Number(match[1]) - 1;
    return index >= 0 && index <= 30 ? `srvy_Q${index}` : '';
  }

  function populateSurveyVersions(versions) {
    const previous = surveyVersion.value;
    const unique = [...new Set((versions || [])
      .map((value) => String(value || '').trim())
      .filter((value) => /^\d+\.\d+(?:\.\d+)?$/.test(value)))]
      .sort((left, right) => right.localeCompare(left, undefined, { numeric: true }));
    surveyVersion.replaceChildren(new Option('Select version', ''));
    unique.forEach((version) => surveyVersion.add(new Option(`Version ${version}`, version)));
    surveyVersion.disabled = unique.length === 0;
    if (unique.includes(previous)) surveyVersion.value = previous;
    if (!surveyVersion.value && unique.length === 1) surveyVersion.value = unique[0];
    return unique;
  }

  async function refreshSurveyVersions() {
    const requestId = ++versionLoadId;
    const range = monthRange(month.value);
    if (!facilityInput.value || !department.value || !range) {
      surveyVersion.replaceChildren(new Option('Select version', ''));
      surveyVersion.disabled = true;
      return [];
    }

    surveyVersion.replaceChildren(new Option('Loading versions…', ''));
    surveyVersion.disabled = true;
    const query = new URLSearchParams({
      facility_nin: facilityInput.value,
      department_id: department.value,
      from: range.from,
      to: range.to,
      summary_only: '1'
    });
    try {
      const payload = await requestJson(`/api/v1/analytics/summary?${query}`);
      if (requestId !== versionLoadId) return [];
      const versions = populateSurveyVersions(payload.data?.available_versions || []);
      if (!versions.length) {
        surveyVersion.replaceChildren(new Option('No version available', ''));
        surveyVersion.disabled = true;
      }
      return versions;
    } catch (error) {
      if (requestId !== versionLoadId) return [];
      surveyVersion.replaceChildren(new Option('Unable to load versions', ''));
      surveyVersion.disabled = true;
      showMessage(error.message || 'Unable to load survey versions.', true);
      return [];
    }
  }

  function scoreClass(score) {
    if (score < 2) return 'is-critical';
    return '';
  }

  function starMarkup(score) {
    const safe = Math.max(0, Math.min(5, Number(score) || 0));
    const filled = Math.round(safe);
    return `<span class="capa-stars" aria-label="${safe.toFixed(1)} out of 5 stars"><span aria-hidden="true">${'★'.repeat(filled)}</span><span class="empty" aria-hidden="true">${'☆'.repeat(5 - filled)}</span></span>`;
  }

  function fieldMarkup(key, label, value, type = 'textarea', wide = false) {
    const element = type === 'input'
      ? `<input data-field="${key}" type="text" maxlength="255" value="${escapeAttribute(value)}">`
      : `<textarea data-field="${key}" maxlength="${key === 'action_plan' ? 3000 : 2000}">${escapeHtml(value)}</textarea>`;
    return `<label class="capa-field ${wide ? 'wide' : ''}"><span>${label}</span>${element}</label>`;
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (character) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    }[character]));
  }

  function escapeAttribute(value) {
    return escapeHtml(value).replace(/`/g, '&#96;');
  }

  function renderRows(rows) {
    resultHost.innerHTML = rows.map((row, index) => {
      const saved = Boolean(row.action?.id);
      const action = row.action || {};
      return `
        <article class="capa-card ${scoreClass(row.score)} ${saved ? 'is-saved' : ''}" data-question-key="${row.question_key}">
          <header class="capa-card-head">
            <span class="capa-rank">${index + 1}</span>
            <div class="capa-card-title">
              <h3>${escapeHtml(row.indicator_name)}</h3>
              <p>${escapeHtml(row.question_key)} · ${Number(row.responses) || 0} valid responses</p>
            </div>
            <div class="capa-score">${Number(row.score).toFixed(1)}/5 ${starMarkup(row.score)}
              <span class="capa-status ${saved ? 'saved' : ''}"><i class="bi ${saved ? 'bi-check-circle-fill' : 'bi-pencil-square'}" aria-hidden="true"></i>${saved ? 'Saved' : 'Not entered'}</span>
            </div>
          </header>
          <dl class="capa-summary">
            <div><dt>Root cause</dt><dd>${escapeHtml(action.root_cause || '—')}</dd></div>
            <div><dt>Action plan</dt><dd>${escapeHtml(action.action_plan || '—')}</dd></div>
            <div><dt>Responsible</dt><dd>${escapeHtml(action.responsible || '—')}</dd></div>
            <div><dt>Timeline</dt><dd>${escapeHtml(action.timeline || '—')}</dd></div>
            ${action.remarks ? `<div class="wide"><dt>Remarks</dt><dd>${escapeHtml(action.remarks)}</dd></div>` : ''}
          </dl>
          <div class="capa-fields">
            ${fieldMarkup('root_cause', 'Root cause *', action.root_cause || '', 'textarea')}
            ${fieldMarkup('action_plan', 'Corrective and preventive action plan *', action.action_plan || '', 'textarea')}
            ${fieldMarkup('responsible', 'Responsible person / designation *', action.responsible || '', 'input')}
            ${fieldMarkup('timeline', 'Completion timeline *', action.timeline || '', 'input')}
            ${fieldMarkup('remarks', 'Remarks', action.remarks || '', 'textarea', true)}
          </div>
          <div class="capa-card-actions">
            ${saved ? '<button class="edit-action" type="button"><i class="bi bi-pencil" aria-hidden="true"></i>Edit</button>' : ''}
            ${saved ? '<button class="cancel-action" type="button"><i class="bi bi-x-lg" aria-hidden="true"></i>Cancel</button>' : ''}
            <button class="save-action" type="button"><i class="bi bi-floppy" aria-hidden="true"></i>Save</button>
          </div>
        </article>`;
    }).join('');

    resultHost.querySelectorAll('.capa-card').forEach((card) => {
      const row = currentRows.find((item) => item.question_key === card.dataset.questionKey);
      const saved = Boolean(row?.action?.id);
      setCardEditing(card, !saved);
      card.querySelector('.edit-action')?.addEventListener('click', () => setCardEditing(card, true));
      card.querySelector('.cancel-action')?.addEventListener('click', () => renderRows(currentRows));
      card.querySelector('.save-action')?.addEventListener('click', () => saveCard(card));
    });
  }

  function setCardEditing(card, editing) {
    card.classList.toggle('is-editing', editing);
    card.querySelectorAll('[data-field]').forEach((field) => { field.disabled = !editing; });
    const save = card.querySelector('.save-action');
    if (save) save.hidden = !editing;
    const edit = card.querySelector('.edit-action');
    if (edit) edit.hidden = editing;
    const cancel = card.querySelector('.cancel-action');
    if (cancel) cancel.hidden = !editing;
  }

  function readCard(card) {
    const action = { question_key: card.dataset.questionKey };
    card.querySelectorAll('[data-field]').forEach((field) => { action[field.dataset.field] = field.value.trim(); });
    for (const field of ['root_cause', 'action_plan', 'responsible', 'timeline']) {
      if (!action[field]) throw new Error('Complete all required CAPA fields before saving.');
    }
    return action;
  }

  async function saveCard(card) {
    const button = card.querySelector('.save-action');
    try {
      const action = readCard(card);
      button.disabled = true;
      button.innerHTML = '<i class="bi bi-arrow-repeat" aria-hidden="true"></i>Saving...';
      const token = await getCsrfToken();
      const payload = await requestJson('/api/v1/capa/actions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify({
          facility_nin: facilityInput.value,
          dept_id: Number(department.value),
          month: month.value,
          survey_version: surveyVersion.value,
          actions: [action]
        })
      });
      const savedActions = new Map((payload.data?.actions || []).map((item) => [item.question_key, item]));
      currentRows = currentRows.map((item) => ({ ...item, action: savedActions.get(item.question_key) || item.action }));
      renderRows(currentRows);
      showMessage('CAPA action saved successfully.');
    } catch (error) {
      showMessage(error.message || 'Unable to save the CAPA action.', true);
      button.disabled = false;
      button.innerHTML = '<i class="bi bi-floppy" aria-hidden="true"></i>Save';
    }
  }

  async function loadCapa() {
    const range = monthRange(month.value);
    if (!facilityInput.value || !department.value || !range) {
      showMessage('Select a facility, department and valid month.', true);
      return;
    }
    showMessage('');
    report.hidden = true;
    resultHost.innerHTML = '<div class="surface capa-report-header">Loading the three lowest-performing indicators...</div>';
    const analyticsQuery = new URLSearchParams({
      facility_nin: facilityInput.value,
      department_id: department.value,
      from: range.from,
      to: range.to
    });
    try {
      let analytics;
      if (!surveyVersion.value) {
        const versions = await refreshSurveyVersions();
        if (versions.length === 0) {
          throw new Error('No survey version is available for this department.');
        }
        if (versions.length > 1) {
          report.hidden = true;
          showMessage('Select the survey version before loading the CAPA plan.', true);
          return;
        }
      }
      analyticsQuery.set('survey_version', surveyVersion.value);
      analytics = await requestJson(`/api/v1/analytics/summary?${analyticsQuery}`);
      populateSurveyVersions(analytics.data?.available_versions || [surveyVersion.value]);
      const actionQuery = new URLSearchParams({
        facility_nin: facilityInput.value,
        dept_id: department.value,
        month: month.value,
        survey_version: surveyVersion.value
      });
      const saved = await requestJson(`/api/v1/capa/actions?${actionQuery}`);
      const savedMap = new Map((saved.data?.actions || []).map((item) => [item.question_key, item]));
      currentRows = (analytics.data?.indicators || [])
        .filter((item) => Number.isFinite(Number(item.score)) && questionKey(item))
        .sort((left, right) => Number(left.score) - Number(right.score))
        .slice(0, 3)
        .map((item) => ({
          ...item,
          question_key: questionKey(item),
          action: savedMap.get(questionKey(item)) || null
        }));
      selectedFacility ||= facilities.find((item) => String(item.facilityNIN) === String(facilityInput.value));
      byId('capa-context').textContent = `${selectedFacility?.facilityName || facilitySearch.value} · ${department.selectedOptions[0]?.textContent || ''}`;
      byId('capa-period').textContent = `${range.label} · Survey version ${surveyVersion.value} · Facility NIN ${facilityInput.value}`;
      try {
        const monthData = await requestJson(`/api/v1/capa/actions?facility_nin=${encodeURIComponent(facilityInput.value)}&dept_id=${encodeURIComponent(department.value)}&months=1`);
        const labels = (monthData.data?.months || []).map((value) => new Date(`${value}-01T00:00:00`).toLocaleDateString(undefined, { month: 'long', year: 'numeric' }));
        showPreparedMonths();
      } catch (_) { byId('capa-prepared-months').textContent = ''; }
      report.hidden = false;
      downloadButton.disabled = currentRows.length === 0;
      if (printButton) printButton.disabled = currentRows.length === 0;
      if (!currentRows.length) {
        resultHost.innerHTML = '<div class="surface capa-report-header">No rated indicators are available for this facility, department and month.</div>';
        return;
      }
      renderRows(currentRows);
    } catch (error) {
      resultHost.replaceChildren();
      showMessage(error.message || 'Unable to load CAPA data.', true);
    }
  }

  function downloadCsv() {
    if (!currentRows.length) return;
    const facilityName = document.querySelector('#capa-context')?.textContent || facilitySearch.value || '';
    const reportMonth = month.value || '';
    const metadata = [['Facility name', facilityName], ['Facility NIN', facilityInput.value], ['Report type', 'CAPA'], ['Report month', reportMonth], []];
    const headers = ['Sl. No.', 'Survey version', 'Question key', 'Lowest indicator', 'Average score', 'Root cause', 'Action plan', 'Responsible', 'Timeline', 'Remarks', 'CAPA status'];
    const rows = currentRows.map((item, index) => [
      index + 1,
      surveyVersion.value,
      item.question_key,
      item.indicator_name,
      Number(item.score).toFixed(1),
      item.action?.root_cause || '',
      item.action?.action_plan || '',
      item.action?.responsible || '',
      item.action?.timeline || '',
      item.action?.remarks || '',
      item.action?.id ? 'Saved' : 'Not entered'
    ]);
    const csv = '\ufeff' + [...metadata, headers, ...rows].map((row) =>
      row.map((value) => `"${String(value ?? '').replace(/"/g, '""')}"`).join(',')
    ).join('\r\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'application/vnd.ms-excel;charset=utf-8' }));
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `abhipraya-capa-${facilityInput.value}-${department.value}-${month.value}.xls`;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
  }

  function printPdf() {
    if (!currentRows.length) return;
    const heading = document.getElementById('capa-context')?.textContent || 'CAPA report';
    const period = document.getElementById('capa-period')?.textContent || month.value;
    const rows = currentRows.map((item, index) => `<tr><td>${index + 1}</td><td>${escapeHtml(item.indicator_name)}</td><td>${Number(item.score).toFixed(1)}/5</td><td>${escapeHtml(item.action?.root_cause || '')}</td><td>${escapeHtml(item.action?.action_plan || '')}</td><td>${item.action?.id ? 'Saved' : 'Not entered'}</td></tr>`).join('');
    const win = window.open('', '_blank', 'noopener');
    if (!win) return;
    win.document.write(`<title>Abhipraya CAPA</title><style>body{font:14px Arial;color:#123}h1{font-size:22px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #9aa;padding:7px;text-align:left}th{background:#e9b01b}</style><h1>Abhipraya CAPA report</h1><p><b>${escapeHtml(heading)}</b><br>${escapeHtml(period)}<br>Report type: CAPA</p><table><thead><tr><th>Sl. No.</th><th>Lowest indicator</th><th>Average score</th><th>Root cause</th><th>Action plan</th><th>Status</th></tr></thead><tbody>${rows}</tbody></table>`);
    win.document.close(); win.focus(); win.print();
  }
  async function showPreparedMonths() {
    const host = byId('capa-prepared-months');
    if (!host || !facilityInput.value || !department.value) return;
    try {
      const data = await requestJson(`/api/v1/capa/actions?facility_nin=${encodeURIComponent(facilityInput.value)}&dept_id=${encodeURIComponent(department.value)}&months=1`);
      const labels = (data.data?.months || []).map((value) => new Date(`${value}-01T00:00:00`).toLocaleDateString(undefined, { month: 'long', year: 'numeric' }));
      if (!labels.length) { host.textContent = 'No CAPA prepared for this facility and department yet.'; return; }
      host.innerHTML = `CAPA prepared for ${labels.length} month(s): ` + labels.map((label, index) => `<a href="#" class="capa-month-link" data-capa-month="${data.data.months[index]}">${label}</a>`).join(' · ');
    } catch (_) { host.textContent = ''; }
  }

  async function initialise() {
    month.value = currentMonth();
    try {
      const me = await requestJson('/api/v1/auth/me');
      const user = me.data?.user || {};
      if (![1, 2, 3, 7, 8].includes(Number(user.role_id))) throw new Error('Your role cannot manage CAPA plans.');
      await loadConfiguration('');
      if (Number(user.role_id) === 2) facilityField.hidden = true;
    } catch (error) {
      showMessage(error.message || 'Unable to initialise CAPA.', true);
    }
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    loadCapa();
  });
  facilitySearch.addEventListener('input', () => {
    facilityInput.value = '';
    selectedFacility = null;
    department.disabled = true;
    surveyVersion.replaceChildren(new Option('Select version', ''));
    surveyVersion.disabled = true;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadConfiguration(facilitySearch.value).catch((error) => showMessage(error.message, true)), 180);
  });
  facilitySearch.addEventListener('focus', () => renderFacilityResults(facilitySearch.value));
  department.addEventListener('change', () => {
    surveyVersion.replaceChildren(new Option('Select version', ''));
    surveyVersion.disabled = true;
    refreshSurveyVersions();
    showPreparedMonths();
  });
  byId('capa-prepared-months')?.addEventListener('click', (event) => {
    const link = event.target.closest('[data-capa-month]');
    if (!link) return;
    event.preventDefault();
    month.value = link.dataset.capaMonth;
    refreshSurveyVersions();
    loadCapa();
  });
  month.addEventListener('change', refreshSurveyVersions);
  facilitySearch.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      facilityResults.hidden = true;
      facilitySearch.setAttribute('aria-expanded', 'false');
    }
  });
  document.addEventListener('click', (event) => {
    if (!facilityField.contains(event.target)) {
      facilityResults.hidden = true;
      facilitySearch.setAttribute('aria-expanded', 'false');
    }
  });
  downloadButton.addEventListener('click', downloadCsv);
  printButton?.addEventListener('click', printPdf);
  document.querySelector('[data-topnav-toggle]')?.addEventListener('click', (event) => {
    const navigation = byId(event.currentTarget.getAttribute('aria-controls'));
    const open = navigation?.classList.toggle('is-open') || false;
    event.currentTarget.setAttribute('aria-expanded', String(open));
  });
  initialise();
})();
