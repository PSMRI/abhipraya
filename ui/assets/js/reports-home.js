(() => {
  'use strict';

  const byId = (id) => document.getElementById(id);
  const number = new Intl.NumberFormat();
  let currentExport = { headers: [], rows: [], fileName: 'abhipraya-report' };

  const escapeHtml = (value) => String(value ?? '').replace(
    /[&<>'"]/g,
    (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character])
  );

  function isoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function scoreClass(score) {
    if (!Number.isFinite(score) || score < 2) return 'critical';
    if (score < 3) return 'priority';
    if (score < 4) return 'monitor';
    if (score < 4.5) return 'good';
    return 'excellent';
  }

  function starMarkup(score) {
    const safe = Math.max(0, Math.min(5, Number(score)));
    const filled = Math.round(safe);
    return `<span class="average-stars score-${scoreClass(safe)}" aria-label="${safe.toFixed(1)} out of 5 stars"><span aria-hidden="true">${'\u2605'.repeat(filled)}</span><span class="empty-stars" aria-hidden="true">${'\u2606'.repeat(5 - filled)}</span></span>`;
  }

  function scoreBadge(score) {
    return Number.isFinite(score)
      ? `<span class="report-score ${scoreClass(score)}">${score.toFixed(1)}/5 ${starMarkup(score)}</span>`
      : '\u2014';
  }

  function reportTypeLabel(type) {
    return ({
      rating: 'Rating',
      binary: 'Yes / No',
      category: 'Category',
      multi_category: 'Multiple choice',
      numeric: 'Numeric',
      text: 'Text',
      duration: 'Duration',
      date: 'Date',
      availability: 'Availability',
      severity: 'Severity',
      demographic: 'Demographic',
      consent: 'Consent',
      ranking: 'Ranking',
      matrix_rating: 'Matrix rating',
      file: 'File'
    })[String(type || '').toLowerCase()] || 'Indicator';
  }

  function indicatorIconMarkup(icon) {
    if (!icon || typeof icon !== 'object') {
      return '<span class="report-indicator-icon"><i class="bi bi-bar-chart-fill" aria-hidden="true"></i></span>';
    }
    const value = String(icon.value || '');
    if (icon.type === 'image' && /^\/api\/assets\/img\/[A-Za-z0-9._/-]+$/.test(value)) {
      return `<span class="report-indicator-icon"><img src="${escapeHtml(value)}" alt="" loading="lazy"></span>`;
    }
    if (icon.type === 'bootstrap' && /^bi-[a-z0-9-]+$/.test(value)) {
      return `<span class="report-indicator-icon"><i class="bi ${escapeHtml(value)}" aria-hidden="true"></i></span>`;
    }
    if (icon.type === 'text' && value) {
      return `<span class="report-indicator-icon" aria-hidden="true">${escapeHtml(value.slice(0, 8))}</span>`;
    }
    return '<span class="report-indicator-icon"><i class="bi bi-bar-chart-fill" aria-hidden="true"></i></span>';
  }

  function indicatorLabel(item, nameKey = 'indicator_name') {
    return `<span class="report-indicator">${indicatorIconMarkup(item.icon)}<span><strong>${escapeHtml(item[nameKey] || 'Indicator')}</strong><small>${escapeHtml(reportTypeLabel(item.report_type))}</small></span></span>`;
  }

  function reportFilters() {
    const query = new URLSearchParams();
    const facility = byId('report-facility').value;
    const department = byId('report-department').value;
    const surveyVersion = byId('report-survey-version').value;
    const from = byId('report-from').value;
    const to = byId('report-to').value;
    if (facility) query.set('facility_nin', facility);
    if (department) query.set('department_id', department);
    if (surveyVersion) query.set('survey_version', surveyVersion);
    if (from) query.set('from', from);
    if (to) query.set('to', to);
    return query;
  }

  function aggregateDepartments(items) {
    const groups = new Map();
    items.forEach((item) => {
      const key = String(item.department_id ?? item.department_name);
      if (!groups.has(key)) groups.set(key, { name: item.department_name, responses: 0, weighted: 0, weight: 0, facilities: new Set() });
      const group = groups.get(key);
      const responses = Number(item.responses) || 0;
      const score = Number(item.score);
      group.responses += responses;
      if (Number.isFinite(score)) {
        const weight = Math.max(1, responses);
        group.weighted += score * weight;
        group.weight += weight;
      }
      if (item.facility_nin) group.facilities.add(item.facility_nin);
    });
    return [...groups.values()].map((group) => ({
      name: group.name,
      responses: group.responses,
      score: group.weight ? group.weighted / group.weight : null,
      facilities: group.facilities.size
    }));
  }

  function aggregateIndicators(items) {
    const departmentGroups = new Map();
    items.forEach((item) => {
      const score = Number(item.score_raw ?? item.score);
      const responses = Math.max(0, Number(item.responses) || 0);
      if (!Number.isFinite(score) || responses === 0) return;
      const indicatorKey = String(item.indicator_id ?? item.indicator_name);
      const departmentKey = String(item.department_id ?? item.department_name ?? '');
      const key = `${indicatorKey}:${departmentKey}`;
      if (!departmentGroups.has(key)) {
        departmentGroups.set(key, {
          ...item,
          indicatorKey,
          departmentKey,
          responses: 0,
          weightedScore: 0,
          facilities: new Map()
        });
      }
      const group = departmentGroups.get(key);
      group.responses += responses;
      group.weightedScore += score * responses;
      const facilityKey = String(item.facility_nin ?? item.facility_name ?? '');
      if (facilityKey) group.facilities.set(facilityKey, item.facility_name || facilityKey);
    });

    const indicatorGroups = new Map();
    departmentGroups.forEach((department) => {
      const departmentAverage = department.weightedScore / department.responses;
      if (!indicatorGroups.has(department.indicatorKey)) {
        indicatorGroups.set(department.indicatorKey, {
          ...department,
          responses: 0,
          weightedScore: 0,
          facilities: new Map(),
          departments: new Map()
        });
      }
      const indicator = indicatorGroups.get(department.indicatorKey);
      indicator.responses += department.responses;
      indicator.weightedScore += departmentAverage * department.responses;
      department.facilities.forEach((name, key) => indicator.facilities.set(key, name));
      if (department.departmentKey) {
        indicator.departments.set(department.departmentKey, department.department_name || department.departmentKey);
      }
    });

    return [...indicatorGroups.values()].map((indicator) => {
      const facilityNames = [...indicator.facilities.values()];
      const departmentNames = [...indicator.departments.values()];
      return {
        ...indicator,
        score: indicator.responses > 0 ? indicator.weightedScore / indicator.responses : null,
        facility_name: facilityNames.length === 1 ? facilityNames[0] : `${facilityNames.length} facilities`,
        department_name: departmentNames.length === 1 ? departmentNames[0] : `${departmentNames.length} departments`
      };
    });
  }

  function setHeaders(headers) {
    byId('report-table-head').innerHTML = `<tr>${headers.map((header) => `<th>${escapeHtml(header)}</th>`).join('')}</tr>`;
  }

  function renderTable(type, data) {
    const body = byId('report-table-body');
    let headers = [];
    let rows = [];
    let htmlRows = [];

    if (type === 'facilities') {
      headers = ['Facility', 'Responses', 'Average score'];
      const items = [...data.facilities].sort((left, right) => (Number(right.score) || 0) - (Number(left.score) || 0));
      rows = items.map((item) => [item.facility_name, Number(item.responses) || 0, item.score ?? '']);
      htmlRows = items.map((item) => {
        const score = Number(item.score);
        return `<tr><td>${escapeHtml(item.facility_name)}</td><td>${number.format(Number(item.responses) || 0)}</td><td>${scoreBadge(score)}</td></tr>`;
      });
    } else if (type === 'questions') {
      headers = ['Indicator', 'Type', 'Facilities', 'Departments', 'Valid responses', 'Average score'];
      const items = [...data.indicators].filter((item) => Number.isFinite(Number(item.score))).sort((left, right) => Number(left.score) - Number(right.score));
      rows = items.map((item) => [item.indicator_name, reportTypeLabel(item.report_type), item.facility_name, item.department_name, Number(item.responses) || 0, Number(item.score).toFixed(1)]);
      htmlRows = items.map((item) => `<tr><td>${indicatorLabel(item)}</td><td><span class="report-type-badge">${escapeHtml(reportTypeLabel(item.report_type))}</span></td><td>${escapeHtml(item.facility_name)}</td><td>${escapeHtml(item.department_name)}</td><td>${number.format(Number(item.responses) || 0)}</td><td>${scoreBadge(Number(item.score))}</td></tr>`);
    } else if (type === 'distributions') {
      headers = ['Indicator', 'Type', 'Facility', 'Department', 'Measure / option', 'Count', 'Result'];
      data.categories.forEach((item) => {
        const base = [
          item.question_name || 'Indicator',
          reportTypeLabel(item.report_type),
          item.facility_name || '',
          item.department_name || ''
        ];
        const statistics = item.statistics || null;
        if (statistics) {
          const measures = item.report_type === 'numeric'
            ? [
                ['Average', statistics.count, statistics.average],
                ['Minimum', statistics.count, statistics.minimum],
                ['Maximum', statistics.count, statistics.maximum],
                ['Total', statistics.count, statistics.total]
              ]
            : item.report_type === 'duration'
              ? [
                  ['Average', statistics.count, statistics.average],
                  ['Median', statistics.count, statistics.median],
                  ['Minimum', statistics.count, statistics.minimum],
                  ['Maximum', statistics.count, statistics.maximum]
                ]
            : [['Response count', statistics.count, statistics.count]];
          measures.forEach(([label, count, result]) => {
            rows.push([...base, label, Number(count) || 0, result ?? '']);
            htmlRows.push(`<tr><td>${indicatorLabel(item, 'question_name')}</td><td><span class="report-type-badge">${escapeHtml(reportTypeLabel(item.report_type))}</span></td><td>${escapeHtml(item.facility_name)}</td><td>${escapeHtml(item.department_name)}</td><td>${escapeHtml(label)}</td><td>${number.format(Number(count) || 0)}</td><td>${escapeHtml(result ?? '\u2014')}${item.unit ? ` ${escapeHtml(item.unit)}` : ''}</td></tr>`);
          });
          return;
        }
        (item.items || []).forEach((option) => {
          const result = option.percentage !== undefined
            ? `${Number(option.percentage).toFixed(1)}%`
            : option.average !== undefined
              ? `${Number(option.average).toFixed(1)}/5`
              : option.average_rank !== undefined
                ? `Rank ${Number(option.average_rank).toFixed(1)}`
                : '';
          rows.push([...base, option.label || option.value || '', Number(option.count) || 0, result]);
          htmlRows.push(`<tr><td>${indicatorLabel(item, 'question_name')}</td><td><span class="report-type-badge">${escapeHtml(reportTypeLabel(item.report_type))}</span></td><td>${escapeHtml(item.facility_name)}</td><td>${escapeHtml(item.department_name)}</td><td>${escapeHtml(option.label || option.value || '')}</td><td>${number.format(Number(option.count) || 0)}</td><td>${escapeHtml(result || '\u2014')}</td></tr>`);
        });
      });
    } else {
      headers = ['Department', 'Reporting facilities', 'Responses', 'Average score'];
      const items = aggregateDepartments(data.departments).sort((left, right) => (Number(right.score) || 0) - (Number(left.score) || 0));
      rows = items.map((item) => [item.name, item.facilities, item.responses, item.score === null ? '' : item.score.toFixed(1)]);
      htmlRows = items.map((item) => {
        const score = Number(item.score);
        return `<tr><td>${escapeHtml(item.name)}</td><td>${number.format(item.facilities)}</td><td>${number.format(item.responses)}</td><td>${scoreBadge(score)}</td></tr>`;
      });
    }

    setHeaders(headers);
    body.innerHTML = htmlRows.length ? htmlRows.join('') : `<tr><td colspan="${headers.length}" class="empty">No report data matches the selected filters.</td></tr>`;
    currentExport = {
      headers,
      rows,
      fileName: `abhipraya-${type}-report-${byId('report-from').value || 'all'}-${byId('report-to').value || 'dates'}`
    };
    return rows.length;
  }

  function renderSummary(summary) {
    const averageScore = summary.score === null || summary.score === undefined ? null : Number(summary.score);
    byId('report-total').textContent = number.format(Number(summary.total_responses) || 0);
    byId('report-score').textContent = averageScore === null ? '\u2014' : `${averageScore.toFixed(1)}/5`;
    const scoreStars = byId('report-score-stars');
    scoreStars.innerHTML = averageScore === null ? '' : starMarkup(averageScore);
    scoreStars.setAttribute('aria-label', averageScore === null ? 'No average score available' : `${averageScore.toFixed(1)} out of 5 stars`);
    byId('report-facility-count').textContent = number.format(Number(summary.facility_count) || 0);
  }

  function contextLabel() {
    const type = byId('report-type').selectedOptions[0].textContent;
    const facility = byId('report-facility').selectedOptions[0].textContent;
    const department = byId('report-department').selectedOptions[0].textContent;
    const surveyVersion = byId('report-survey-version').selectedOptions[0].textContent;
    const from = byId('report-from').value;
    const to = byId('report-to').value;
    const scope = [facility, department, surveyVersion].filter((value) => !/^All /.test(value)).join(' \u00b7 ') || 'All permitted data';
    const period = from || to ? `${from || 'Beginning'} to ${to || 'Today'}` : 'All available dates';
    return { type, scope, period };
  }

  async function generateReport(event) {
    event.preventDefault();
    const api = window.AbhiprayaUI?.json;
    if (!api) return;
    const from = byId('report-from').value;
    const to = byId('report-to').value;
    if (from && to && from > to) {
      showError('The From date must be earlier than the To date.');
      return;
    }

    const button = byId('generate-report');
    button.disabled = true;
    button.innerHTML = '<i class="bi bi-arrow-repeat" aria-hidden="true"></i>Generating...';
    showError('');
    try {
      const query = reportFilters().toString();
      const reportType = byId('report-type').value;
      const reportQuery = new URLSearchParams(query);
      if (reportType === 'distributions') reportQuery.set('include_distributions', '1');
      const encodedQuery = reportQuery.toString();
      const payload = await api(`/api/v1/analytics/summary${encodedQuery ? `?${encodedQuery}` : ''}`);
      const data = {
        summary: payload.data?.summary || {},
        facilities: payload.data?.facilities || [],
        departments: payload.data?.departments || [],
        indicators: aggregateIndicators(payload.data?.indicators || []),
        categories: payload.data?.categories || []
      };
      const type = reportType;
      renderSummary(data.summary);
      const rowCount = renderTable(type, data);
      const context = contextLabel();
      byId('result-title').textContent = context.type;
      byId('result-period').textContent = `${context.scope} \u00b7 ${context.period}`;
      byId('report-context').textContent = `${context.scope} \u00b7 ${context.period}`;
      byId('report-generated-at').textContent = `Generated ${new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date())}`;
      byId('report-empty').hidden = true;
      byId('report-result').hidden = false;
      byId('download-csv').disabled = rowCount === 0;
      byId('print-report').disabled = false;
    } catch (error) {
      showError(error.message || 'Unable to generate this report.');
      byId('download-csv').disabled = true;
      byId('print-report').disabled = true;
    } finally {
      button.disabled = false;
      button.innerHTML = '<i class="bi bi-file-earmark-text" aria-hidden="true"></i>Generate report';
    }
  }

  function showError(text) {
    const message = byId('report-message');
    message.textContent = text;
    message.hidden = !text;
  }

  function downloadCsv() {
    const rows = [currentExport.headers, ...currentExport.rows];
    const csv = '\ufeff' + rows.map((row) => row.map((value) => `"${String(value ?? '').replace(/"/g, '""')}"`).join(',')).join('\r\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `${currentExport.fileName}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  }

  async function loadConfiguration() {
    const api = window.AbhiprayaUI?.json;
    if (!api) return;
    try {
      const [payload, versionPayload] = await Promise.all([
        api('/api/v1/qr?limit=500'),
        api('/api/v1/analytics/summary?summary_only=1')
      ]);
      const facilities = payload.data?.facilities || [];
      const departments = payload.data?.departments || [];
      const versions = versionPayload.data?.available_versions || [];
      facilities.forEach((item) => byId('report-facility').add(new Option(item.facilityName, item.facilityNIN)));
      departments.forEach((item) => byId('report-department').add(new Option(item.departmentName, item.departmentId)));
      versions.forEach((version) => byId('report-survey-version').add(new Option(`Version ${version}`, version)));
      if (facilities.length === 1) byId('report-facility').value = facilities[0].facilityNIN;
    } catch (error) {
      showError(error.message || 'Unable to load report settings.');
    }
  }

  const now = new Date();
  byId('report-to').value = isoDate(now);
  byId('report-from').value = isoDate(new Date(now.getFullYear(), now.getMonth(), 1));
  byId('report-form').addEventListener('submit', generateReport);
  byId('download-csv').addEventListener('click', downloadCsv);
  byId('print-report').addEventListener('click', () => window.print());
  document.querySelector('[data-topnav-toggle]')?.addEventListener('click', (event) => {
    const navigation = byId('primary-navigation');
    const open = navigation.classList.toggle('is-open');
    event.currentTarget.setAttribute('aria-expanded', String(open));
  });
  loadConfiguration();
})();
