(function () {
  'use strict';

  const headerControls = document.createElement('script');
  headerControls.src = '/ui/assets/js/header-controls.js';
  document.head.appendChild(headerControls);

  const app = document.querySelector('.ab-app');
  const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
  const form = document.getElementById('report-filter');
  const facility = document.getElementById('report-facility');
  const facilitySearch = document.getElementById('report-facility-search');
  const facilityResults = document.getElementById('report-facility-results');
  const department = document.getElementById('report-department');
  const month = document.getElementById('report-month');
  const tableBody = document.getElementById('indicator-results');
  const table = tableBody.closest('table');
  const tableHead = table.querySelector('thead tr');
  const cards = document.getElementById('indicator-cards');
  const context = document.getElementById('report-context');
  const facilityField = facilitySearch.closest('.ab-field');
  const monthField = month.closest('.ab-field');
  const departmentField = department.closest('.ab-field');
  facilityField.classList.add('ab-report-facility');
  monthField.classList.add('ab-report-month');
  departmentField.classList.add('ab-report-department');
  const filterRow = facilityField.parentElement;
  const submitButton = form.querySelector('.ab-primary');
  let facilities = [];
  let searchTimer;
  let reportItems = [];
  let reportCategories = [];
  let facilitySummary = [];
  let currentPage = 1;
  const pageSize = 12;
  let facilityAdministrator = false;

  const typeField = document.createElement('div');
  typeField.className = 'ab-field ab-report-type';
  typeField.innerHTML = '<label for="report-type">Report type</label><select class="ab-select" id="report-type"><option value="facility_month">Facility-wise monthly</option><option value="facility_range">Facility-wise date range</option><option value="all_facility_range">All facilities by date range</option><option value="overall_facilities">Overall facilities response count</option></select>';
  const reportType = typeField.querySelector('select');
  filterRow.prepend(typeField);
  const submitField = document.createElement('div');
  submitField.className = 'ab-report-submit';
  submitField.appendChild(submitButton);
  filterRow.appendChild(submitField);
  const filterHint = form.querySelector(':scope > .ab-hint');
  if (filterHint) filterHint.hidden = true;

  const rangeField = document.createElement('div');
  rangeField.className = 'ab-field ab-report-range';
  rangeField.hidden = true;
  rangeField.innerHTML = '<label>Date range</label><div class="ab-date-range"><label class="sr-only" for="report-from">From date</label><input class="ab-input" id="report-from" type="date"><label class="sr-only" for="report-to">To date</label><input class="ab-input" id="report-to" type="date"></div>';
  monthField.after(rangeField);
  const from = rangeField.querySelector('#report-from');
  const to = rangeField.querySelector('#report-to');

  const categoryHeading = document.createElement('h2');
  const categoryCards = document.createElement('div');
  categoryHeading.className = 'ab-subheading';
  categoryHeading.textContent = 'Respondent profile breakdown';
  categoryHeading.hidden = true;
  categoryCards.className = 'ab-category-grid';
  categoryCards.hidden = true;
  tableBody.closest('.ab-table-wrap').before(categoryHeading, categoryCards);

  const actionBar = document.createElement('div');
  const exportButton = document.createElement('button');
  const pagination = document.createElement('nav');
  actionBar.className = 'ab-report-actions';
  exportButton.type = 'button';
  exportButton.className = 'ab-primary';
  exportButton.disabled = true;
  exportButton.innerHTML = '<i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> Download Excel';
  pagination.className = 'ab-pagination';
  pagination.setAttribute('aria-label', 'Report pagination');
  actionBar.appendChild(exportButton);
  categoryCards.before(actionBar);
  tableBody.closest('.ab-table-wrap').after(pagination);

  function currentMonth() { const now = new Date(); return now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0'); }
  function monthRange(value) {
    if (!/^\d{4}-\d{2}$/.test(value)) return null;
    const [year, number] = value.split('-').map(Number);
    const first = new Date(year, number - 1, 1); const last = new Date(year, number, 0);
    const iso = (date) => date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    return { from: iso(first), to: iso(last), label: first.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }) };
  }
  function stars(score) { const count = Math.max(0, Math.min(5, Math.round(Number(score || 0)))); return '★'.repeat(count) + '☆'.repeat(5 - count); }
  function clear(target, columns, text) {
    target.replaceChildren();
    if (target === cards) { const item = document.createElement('p'); item.className = 'muted'; item.textContent = text; target.appendChild(item); return; }
    const row = document.createElement('tr'); const cell = document.createElement('td'); cell.colSpan = columns; cell.className = 'muted'; cell.textContent = text; row.appendChild(cell); target.appendChild(row);
  }
  function scoreNode(score) { const wrap = document.createElement('span'); wrap.className = 'ab-score'; const icon = document.createElement('span'); icon.className = 'ab-stars'; icon.textContent = stars(score); icon.setAttribute('aria-label', Number(score).toFixed(1) + ' out of 5 stars'); const label = document.createElement('small'); label.textContent = Number(score).toFixed(1) + ' / 5'; wrap.append(icon, label); return wrap; }
  async function getJson(url) {
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const raw = await response.text(); let data;
    try { data = raw ? JSON.parse(raw) : {}; } catch (_) { throw new Error('The server returned an invalid response. Please try again.'); }
    if (response.status === 401) { location.assign('/admin/login?reason=session-expired'); throw new Error('Session expired.'); }
    if (!response.ok || data.status !== 'success') throw new Error(data.message || 'Unable to load report.');
    return data;
  }
  async function loadUser() {
    const data = await getJson('/api/v1/auth/me'); const user = data.data?.user || {};
    const username = String(user.u_name || ''); const role = String(user.role_name || 'Administrator');
    const name = user.full_name || (/^\d+$/.test(username) ? '' : username) || role;
    facilityAdministrator = Number(user.role_id) === 2;
    document.body.classList.toggle('ab-facility-admin', facilityAdministrator);
    if (facilityAdministrator) {
      reportType.querySelector('option[value="all_facility_range"]')?.remove();
      reportType.querySelector('option[value="overall_facilities"]')?.remove();
      facilityField.hidden = true;
      updateMode();
    }
    document.querySelectorAll('.ab-user, [data-profile-name]').forEach((node) => { node.textContent = name; });
  }
  function chooseFacility(item) { facility.value = String(item.facilityNIN); facilitySearch.value = item.facilityName + ' — ' + item.facilityNIN; facilityResults.hidden = true; facilitySearch.setAttribute('aria-expanded', 'false'); }
  function renderFacilityMatches(query) {
    const needle = String(query || '').trim().toLowerCase();
    const matches = facilities.filter((item) => !needle || String(item.facilityName || '').toLowerCase().includes(needle) || String(item.facilityNIN || '').includes(needle)).slice(0, 25);
    facilityResults.replaceChildren();
    matches.forEach((item) => { const option = document.createElement('button'); option.type = 'button'; option.className = 'ab-facility-option'; option.role = 'option'; option.innerHTML = '<strong></strong><small></small>'; option.querySelector('strong').textContent = item.facilityName || 'Unnamed facility'; option.querySelector('small').textContent = 'NIN ' + item.facilityNIN; option.addEventListener('click', () => chooseFacility(item)); facilityResults.appendChild(option); });
    facilityResults.hidden = matches.length === 0; facilitySearch.setAttribute('aria-expanded', String(matches.length > 0));
  }
  async function searchFacilities(query) { const data = await getJson('/api/v1/qr?limit=25&search=' + encodeURIComponent(query || '')); facilities = data.data.facilities || []; renderFacilityMatches(query); return data; }
  async function loadOptions() { const data = await getJson('/api/v1/qr?limit=25&search='); facilities = data.data.facilities || []; (data.data.departments || []).forEach((item) => department.add(new Option(item.departmentName, item.departmentId))); if (facilities.length === 1) chooseFacility(facilities[0]); }

  function setHeaders(labels) { tableHead.replaceChildren(); labels.forEach((label) => { const heading = document.createElement('th'); heading.scope = 'col'; heading.textContent = label; tableHead.appendChild(heading); }); }
  function renderPagination(total, renderPage) {
    const pages = Math.max(1, Math.ceil(total / pageSize)); currentPage = Math.min(currentPage, pages); pagination.replaceChildren();
    if (pages < 2) return;
    const previous = document.createElement('button'); previous.type = 'button'; previous.className = 'ab-pagination-button'; previous.textContent = 'Previous'; previous.disabled = currentPage === 1; previous.addEventListener('click', () => { currentPage--; renderPage(); });
    const status = document.createElement('span'); status.className = 'ab-pagination-status'; status.textContent = 'Page ' + currentPage + ' of ' + pages;
    const next = document.createElement('button'); next.type = 'button'; next.className = 'ab-pagination-button'; next.textContent = 'Next'; next.disabled = currentPage === pages; next.addEventListener('click', () => { currentPage++; renderPage(); });
    pagination.append(previous, status, next);
  }
  function renderRatings(items) {
    reportItems = items; currentPage = 1; setHeaders(['Department', 'Indicator', 'Responses', 'Score']);
    const draw = () => { cards.replaceChildren(); tableBody.replaceChildren(); const slice = reportItems.slice((currentPage - 1) * pageSize, currentPage * pageSize); slice.forEach((item) => { const card = document.createElement('article'); card.className = 'ab-indicator-card'; const dept = document.createElement('p'); dept.className = 'ab-indicator-department'; dept.textContent = item.department_name; const title = document.createElement('h3'); title.textContent = item.indicator_name; const count = document.createElement('small'); count.className = 'ab-indicator-responses'; count.textContent = item.responses + ' response' + (Number(item.responses) === 1 ? '' : 's'); card.append(dept, title, scoreNode(item.score), count); cards.appendChild(card); const row = document.createElement('tr'); [item.department_name, item.indicator_name, item.responses].forEach((value) => { const cell = document.createElement('td'); cell.textContent = String(value); row.appendChild(cell); }); const score = document.createElement('td'); score.appendChild(scoreNode(item.score)); row.appendChild(score); tableBody.appendChild(row); }); renderPagination(reportItems.length, draw); }; draw();
  }
  function renderFacilitySummary(items) {
    facilitySummary = items; reportItems = []; reportCategories = []; currentPage = 1; categoryHeading.hidden = true; categoryCards.hidden = true; setHeaders(['Facility', 'Responses', 'Average rating']);
    const draw = () => { cards.replaceChildren(); tableBody.replaceChildren(); const slice = facilitySummary.slice((currentPage - 1) * pageSize, currentPage * pageSize); slice.forEach((item) => { const card = document.createElement('article'); card.className = 'ab-indicator-card'; const name = document.createElement('h3'); name.textContent = item.facility_name; const count = document.createElement('strong'); count.textContent = item.responses + ' responses'; const rating = item.score === null ? document.createTextNode('No rating available') : scoreNode(item.score); card.append(name, count, rating); cards.appendChild(card); const row = document.createElement('tr'); const nameCell = document.createElement('td'); nameCell.textContent = item.facility_name; const countCell = document.createElement('td'); countCell.textContent = item.responses; const ratingCell = document.createElement('td'); ratingCell.append(item.score === null ? document.createTextNode('—') : scoreNode(item.score)); row.append(nameCell, countCell, ratingCell); tableBody.appendChild(row); }); renderPagination(facilitySummary.length, draw); }; draw();
  }
  function renderCategories(groups) { reportCategories = groups; categoryCards.replaceChildren(); categoryHeading.hidden = groups.length === 0; categoryCards.hidden = groups.length === 0; groups.forEach((group) => { const card = document.createElement('article'); card.className = 'ab-category-card'; const title = document.createElement('h3'); title.textContent = group.question_name; const total = document.createElement('p'); total.className = 'ab-category-total'; total.textContent = group.total_responses + ' response' + (Number(group.total_responses) === 1 ? '' : 's'); const list = document.createElement('ul'); list.className = 'ab-category-list'; (group.items || []).forEach((item) => { const row = document.createElement('li'); const label = document.createElement('span'); label.textContent = item.label; const value = document.createElement('strong'); value.textContent = item.count + ' (' + Number(item.percentage).toFixed(1) + '%)'; row.append(label, value); list.appendChild(row); }); card.append(title, total, list); categoryCards.appendChild(card); }); }
  function updateMode() {
    const allFacilities = reportType.value === 'overall_facilities' || reportType.value === 'all_facility_range'; const range = reportType.value === 'facility_range' || reportType.value === 'all_facility_range';
    facilityField.hidden = allFacilities || facilityAdministrator; departmentField.hidden = allFacilities; monthField.hidden = range || allFacilities; rangeField.hidden = !range;
    if (range) { from.required = true; to.required = true; } else { from.required = false; to.required = false; }
  }
  function selectedRange() {
    if (reportType.value === 'facility_month') return monthRange(month.value);
    if (reportType.value === 'facility_range' || reportType.value === 'all_facility_range') { if (!from.value || !to.value || from.value > to.value) return null; return { from: from.value, to: to.value, label: from.value + ' to ' + to.value }; }
    return { from: '', to: '', label: 'All available dates' };
  }
  async function loadReport() {
    const range = selectedRange(); const mode = reportType.value;
    if (!range) { context.textContent = 'Choose a valid date or month range.'; clear(cards, 0, 'Choose a valid date or month range.'); clear(tableBody, 4, 'Choose a valid date or month range.'); return; }
    const allFacilities = mode === 'overall_facilities' || mode === 'all_facility_range';
    if (!allFacilities && !facility.value) { context.textContent = 'Select a facility, then choose View report.'; clear(cards, 0, 'Select a facility to view this report.'); clear(tableBody, 4, 'Select a facility to view this report.'); return; }
    const params = new URLSearchParams(); if (range.from) params.set('from', range.from); if (range.to) params.set('to', range.to); if (allFacilities) params.set('summary_only', '1'); if (!allFacilities) params.set('facility_nin', facility.value); if (!allFacilities && department.value) params.set('department_id', department.value);
    exportButton.disabled = true; cards.setAttribute('aria-busy', 'true'); pagination.replaceChildren(); clear(cards, 0, 'Loading report…'); clear(tableBody, 4, 'Loading report…');
    try {
      const data = await getJson('/api/v1/analytics/summary?' + params.toString());
      if (allFacilities) { context.textContent = 'All facilities · ' + range.label; renderFacilitySummary(data.data?.facilities || []); if (!(data.data?.facilities || []).length) { clear(cards, 0, 'No facility feedback responses are available.'); clear(tableBody, 3, 'No facility feedback responses are available.'); } }
      else { const items = data.data?.indicators || []; const groups = data.data?.categories || []; context.textContent = facilitySearch.value + ' · ' + range.label + (department.value ? ' · ' + department.selectedOptions[0].textContent : ''); renderCategories(groups); if (items.length) renderRatings(items); else { reportItems = []; pagination.replaceChildren(); setHeaders(['Department', 'Indicator', 'Responses', 'Score']); clear(cards, 0, groups.length ? 'No star-rated questions match these filters.' : 'No feedback responses match these filters.'); clear(tableBody, 4, groups.length ? 'No star-rated questions match these filters.' : 'No feedback responses match these filters.'); } }
      exportButton.disabled = allFacilities ? facilitySummary.length === 0 : reportItems.length === 0 && reportCategories.length === 0;
    } catch (error) { reportItems = []; reportCategories = []; facilitySummary = []; exportButton.disabled = true; categoryHeading.hidden = true; categoryCards.hidden = true; pagination.replaceChildren(); clear(cards, 0, error.message || 'Unable to load the report.'); clear(tableBody, 4, error.message || 'Unable to load the report.'); window.AbhiprayaFeedback?.error(error.message || 'Unable to load the report.'); }
    finally { cards.setAttribute('aria-busy', 'false'); }
  }
  function downloadReport() {
    const rows = [['Report type', 'Facility', 'Period', 'Department', 'Question / category', 'Option', 'Responses', 'Percentage', 'Score out of 5']]; const type = reportType.selectedOptions[0].textContent; const label = facilitySearch.value || 'All facilities'; const period = selectedRange()?.label || '';
    if (reportType.value === 'overall_facilities' || reportType.value === 'all_facility_range') facilitySummary.forEach((item) => rows.push([type, item.facility_name, period, '', '', '', item.responses, '', item.score === null ? '' : Number(item.score).toFixed(1)]));
    else { reportItems.forEach((item) => rows.push([type, label, period, item.department_name, item.indicator_name, '', item.responses, '', Number(item.score).toFixed(1)])); reportCategories.forEach((group) => (group.items || []).forEach((item) => rows.push([type, label, period, group.department_name, group.question_name, item.label, item.count, Number(item.percentage).toFixed(1) + '%', '']))); }
    const csv = '\ufeff' + rows.map((row) => row.map((value) => '"' + String(value ?? '').replace(/"/g, '""') + '"').join(',')).join('\r\n'); const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' })); const link = document.createElement('a'); link.href = url; link.download = 'abhipraya-' + reportType.value + '-' + (month.value || 'report') + '.csv'; document.body.appendChild(link); link.click(); link.remove(); URL.revokeObjectURL(url);
  }

  form.addEventListener('submit', (event) => { event.preventDefault(); loadReport(); });
  reportType.addEventListener('change', updateMode);
  facilitySearch.addEventListener('input', () => { facility.value = ''; window.clearTimeout(searchTimer); searchTimer = window.setTimeout(() => searchFacilities(facilitySearch.value).catch((error) => window.AbhiprayaFeedback?.error(error.message || 'Unable to search facilities.')), 180); });
  facilitySearch.addEventListener('focus', () => { searchFacilities(facilitySearch.value).catch(() => { facilityResults.hidden = true; }); });
  facilitySearch.addEventListener('keydown', (event) => { if (event.key === 'Escape') { facilityResults.hidden = true; facilitySearch.setAttribute('aria-expanded', 'false'); } });
  exportButton.addEventListener('click', downloadReport);
  sidebarToggle?.addEventListener('click', () => { if (window.matchMedia('(max-width: 880px)').matches) { const sidebar = document.getElementById('primary-navigation'); const open = !sidebar.classList.contains('is-open'); sidebar.classList.toggle('is-open', open); sidebarToggle.setAttribute('aria-expanded', String(open)); return; } const collapsed = !app.classList.contains('is-collapsed'); app.classList.toggle('is-collapsed', collapsed); try { localStorage.setItem('abhipraya_sidebar_collapsed', String(collapsed)); } catch (_) {} });
  try { app.classList.toggle('is-collapsed', localStorage.getItem('abhipraya_sidebar_collapsed') === 'true'); } catch (_) {}
  document.getElementById('logout-button')?.addEventListener('click', async () => { await fetch('/api/v1/auth/logout', { method: 'POST', credentials: 'same-origin' }); location.assign('/admin/login'); });
  month.value = currentMonth(); updateMode(); Promise.all([loadUser(), loadOptions()]).then(() => { context.textContent = 'Choose a report type and filters, then select View report.'; clear(cards, 0, 'Choose report filters to load data.'); clear(tableBody, 4, 'Choose report filters to load data.'); }).catch((error) => { clear(cards, 0, error.message || 'Unable to load report settings.'); clear(tableBody, 4, error.message || 'Unable to load report settings.'); });
}());
