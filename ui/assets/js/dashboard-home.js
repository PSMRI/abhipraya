(() => {
  'use strict';

  const DASHBOARD_ENDPOINT = document.body.dataset.dashboardEndpoint || '';
  const $ = (selector) => document.querySelector(selector);
  const byId = (id) => document.getElementById(id);
  const number = new Intl.NumberFormat();

  const escapeHtml = (value) => String(value ?? '').replace(
    /[&<>'"]/g,
    (character) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      "'": '&#39;',
      '"': '&quot;'
    }[character])
  );

  function setText(id, value) {
    const element = byId(id);
    if (!element) return;
    element.textContent = value;
    element.classList.remove('skeleton');
  }

  function starMarkup(score) {
    const safe = Math.max(0, Math.min(5, Number(score)));
    const filled = Math.round(safe);
    return `<span class="average-stars ${scoreToneClass(safe)}" aria-label="${safe.toFixed(1)} out of 5 stars"><span aria-hidden="true">${'\u2605'.repeat(filled)}</span><span class="empty-stars" aria-hidden="true">${'\u2606'.repeat(5 - filled)}</span></span>`;
  }

  function scoreToneClass(score) {
    if (!Number.isFinite(score) || score < 2) return 'score-critical';
    if (score < 3) return 'score-priority';
    if (score < 4) return 'score-monitor';
    if (score < 4.5) return 'score-good';
    return 'score-excellent';
  }

  function setAverageScore(id, score) {
    const element = byId(id);
    if (!element) return;
    element.classList.remove('skeleton');
    element.innerHTML = Number.isFinite(score)
      ? `<span>${score.toFixed(1)}/5</span>${starMarkup(score)}`
      : '\u2014';
  }

  function renderFacilityBreakdown(items) {
    const host = byId('kpi-facility-breakdown');
    if (!host) return;
    const types = Array.isArray(items) ? items.filter((item) => Number(item.configured) > 0) : [];
    if (!types.length) {
      host.replaceChildren();
      return;
    }
    host.innerHTML = types.map((item) => {
      const type = String(item.type || 'Other');
      const colorKey = type.toLowerCase().replace(/[^a-z0-9]+/g, '-') || 'other';
      return `<span class="facility-type-${escapeHtml(colorKey)}" title="${escapeHtml(type)}: ${number.format(Number(item.configured) || 0)} configured facilities"><i aria-hidden="true"></i>${escapeHtml(type)} <b>${number.format(Number(item.configured) || 0)}</b></span>`;
    }).join('');
  }

  function indicatorIconMarkup(icon, fallback = 'bi-exclamation-octagon-fill') {
    if (!icon || typeof icon !== 'object') {
      return `<i class="bi ${fallback}" aria-hidden="true"></i>`;
    }
    const value = String(icon.value || '');
    if (icon.type === 'image' && /^\/api\/assets\/img\/[A-Za-z0-9._/-]+$/.test(value)) {
      return `<img src="${escapeHtml(value)}" alt="" loading="lazy">`;
    }
    if (icon.type === 'bootstrap' && /^bi-[a-z0-9-]+$/.test(value)) {
      return `<i class="bi ${escapeHtml(value)}" aria-hidden="true"></i>`;
    }
    if (icon.type === 'text' && value) {
      return `<span aria-hidden="true">${escapeHtml(value.slice(0, 8))}</span>`;
    }
    return `<i class="bi ${fallback}" aria-hidden="true"></i>`;
  }

  function categoryRiskItems(categories) {
    const risks = [];
    (categories || []).forEach((indicator) => {
      (indicator.items || []).forEach((item) => {
        const semantic = [
          item.sentiment,
          item.status,
          item.consent === false ? 'declined' : '',
          item.label
        ].filter(Boolean).join(' ').toLowerCase();
        const risky = /(negative|unavailable|not available|critical|high|severe|poor|very poor|declined)/.test(semantic);
        const percentage = Number(item.percentage);
        if (!risky || !Number.isFinite(percentage) || percentage < 20) return;
        risks.push({
          ...indicator,
          option_label: item.label,
          percentage,
          count: Number(item.count) || 0,
          severity: percentage >= 40 ? 'critical' : 'priority'
        });
      });
    });
    return risks.sort((left, right) => right.percentage - left.percentage);
  }

  function progressClass(value, maximum = 100) {
    const percentage = maximum > 0 ? Number(value) / maximum * 100 : 0;
    const step = Math.round(Math.min(100, Math.max(0, percentage)) / 10) * 10;
    return `progress-${step}`;
  }

  function trendPointColor(value, maximum) {
    const ratio = maximum > 0 ? Number(value) / maximum : 0;
    if (ratio < 0.4) return '#c62828';
    if (ratio < 0.7) return '#d97706';
    return '#16834b';
  }

  function currentFilters() {
    const query = new URLSearchParams();
    query.set('lang', localStorage.getItem('abhipraya_admin_language') === 'hi' ? '2' : '1');
    [
      ['facility_nin', 'facility-filter'],
      ['department_id', 'department-filter'],
      ['survey_version', 'survey-version-filter'],
      ['from', 'from-filter'],
      ['to', 'to-filter']
    ].forEach(([parameter, id]) => {
      const value = byId(id)?.value?.trim();
      if (value) query.set(parameter, value);
    });
    return query;
  }

  function setWelcomeName() {
    const userSource = $('[data-user-name]');
    const facilitySource = $('[data-facility-name]');
    const target = $('[data-user-first-name]');
    if (!userSource || !target) return;
    const update = () => {
      const facilityName = facilitySource?.textContent.trim();
      const userName = userSource.textContent.trim();
      const name = facilityName && facilityName !== 'Administrator' ? facilityName : userName;
      if (name && name !== 'Administrator') target.textContent = name;
    };
    update();
    new MutationObserver(update).observe(userSource, { childList: true, characterData: true, subtree: true });
  }

  function renderTrend(items) {
    const host = byId('trend-chart');
    host.replaceChildren();
    if (!items.length) {
      host.innerHTML = '<div class="empty">No recent feedback data is available.</div>';
      return;
    }

    const width = 760;
    const height = 250;
    const padX = 38;
    const padTop = 48;
    const padBottom = 38;
    const responseMaximum = Math.max(...items.map((item) => Number(item.response_count) || 0), 1);
    const facilityMaximum = Math.max(...items.map((item) => Number(item.facility_count) || 0), 1);
    const plotHeight = height - padTop - padBottom;
    const points = items.map((item, index) => ({
      x: items.length === 1
        ? width / 2
        : padX + index * ((width - (padX * 2)) / (items.length - 1)),
      responseY: height - padBottom - ((Number(item.response_count) || 0) / responseMaximum) * plotHeight,
      facilityY: height - padBottom - ((Number(item.facility_count) || 0) / facilityMaximum) * plotHeight,
      label: item.month_key || '',
      responses: Number(item.response_count) || 0,
      facilities: Number(item.facility_count) || 0
    }));

    const namespace = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(namespace, 'svg');
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', 'Monthly feedback responses and reporting facilities');
    const description = document.createElementNS(namespace, 'desc');
    description.textContent = points.map((point) => (
      `${point.label}: ${point.responses} responses from ${point.facilities} facilities`
    )).join('; ');
    svg.appendChild(description);

    const legendBar = document.createElementNS(namespace, 'rect');
    legendBar.setAttribute('x', padX);
    legendBar.setAttribute('y', '14');
    legendBar.setAttribute('width', '16');
    legendBar.setAttribute('height', '10');
    legendBar.setAttribute('rx', '3');
    legendBar.setAttribute('fill', '#16834b');
    legendBar.setAttribute('opacity', '0.68');
    svg.appendChild(legendBar);
    const legendBarText = document.createElementNS(namespace, 'text');
    legendBarText.setAttribute('x', padX + 23);
    legendBarText.setAttribute('y', '23');
    legendBarText.setAttribute('font-size', '11');
    legendBarText.setAttribute('fill', '#334155');
    legendBarText.textContent = 'Feedback responses';
    svg.appendChild(legendBarText);

    const legendLine = document.createElementNS(namespace, 'line');
    legendLine.setAttribute('x1', '194');
    legendLine.setAttribute('x2', '218');
    legendLine.setAttribute('y1', '19');
    legendLine.setAttribute('y2', '19');
    legendLine.setAttribute('stroke', '#176b87');
    legendLine.setAttribute('stroke-width', '4');
    svg.appendChild(legendLine);
    const legendLineText = document.createElementNS(namespace, 'text');
    legendLineText.setAttribute('x', '225');
    legendLineText.setAttribute('y', '23');
    legendLineText.setAttribute('font-size', '11');
    legendLineText.setAttribute('fill', '#334155');
    legendLineText.textContent = 'Reporting facilities';
    svg.appendChild(legendLineText);

    const baseline = document.createElementNS(namespace, 'line');
    baseline.setAttribute('x1', padX);
    baseline.setAttribute('x2', width - padX);
    baseline.setAttribute('y1', height - padBottom);
    baseline.setAttribute('y2', height - padBottom);
    baseline.setAttribute('stroke', '#d8e1e8');
    svg.appendChild(baseline);

    const barWidth = Math.max(
      14,
      Math.min(46, ((width - (padX * 2)) / Math.max(items.length, 1)) * 0.48)
    );
    points.forEach((point) => {
      const bar = document.createElementNS(namespace, 'rect');
      bar.setAttribute('x', point.x - (barWidth / 2));
      bar.setAttribute('y', point.responseY);
      bar.setAttribute('width', barWidth);
      bar.setAttribute('height', Math.max(0, height - padBottom - point.responseY));
      bar.setAttribute('rx', '4');
      bar.setAttribute('fill', trendPointColor(point.responses, responseMaximum));
      bar.setAttribute('opacity', '0.58');
      const title = document.createElementNS(namespace, 'title');
      title.textContent = `${point.label}: ${number.format(point.responses)} responses from ${number.format(point.facilities)} facilities`;
      bar.appendChild(title);
      svg.appendChild(bar);
    });

    const line = document.createElementNS(namespace, 'polyline');
    line.setAttribute(
      'points',
      points.length === 1
        ? `${points[0].x - 42},${points[0].facilityY} ${points[0].x + 42},${points[0].facilityY}`
        : points.map((point) => `${point.x},${point.facilityY}`).join(' ')
    );
    line.setAttribute('fill', 'none');
    line.setAttribute('stroke', '#176b87');
    line.setAttribute('stroke-width', '4');
    line.setAttribute('stroke-linecap', 'round');
    line.setAttribute('stroke-linejoin', 'round');
    svg.appendChild(line);

    points.forEach((point) => {
      const circle = document.createElementNS(namespace, 'circle');
      circle.setAttribute('cx', point.x);
      circle.setAttribute('cy', point.facilityY);
      circle.setAttribute('r', '5');
      circle.setAttribute('fill', '#176b87');
      circle.setAttribute('stroke', '#ffffff');
      circle.setAttribute('stroke-width', '3');
      const title = document.createElementNS(namespace, 'title');
      title.textContent = `${point.label}: ${number.format(point.facilities)} reporting facilities`;
      circle.appendChild(title);
      svg.appendChild(circle);

      const valueLabel = document.createElementNS(namespace, 'text');
      valueLabel.setAttribute('x', point.x);
      valueLabel.setAttribute('y', Math.min(height - padBottom - 6, point.responseY + 15));
      valueLabel.setAttribute('text-anchor', 'middle');
      valueLabel.setAttribute('font-size', '11');
      valueLabel.setAttribute('font-weight', '700');
      valueLabel.setAttribute('fill', '#123d55');
      valueLabel.textContent = number.format(point.responses);
      svg.appendChild(valueLabel);

      const facilityLabel = document.createElementNS(namespace, 'text');
      facilityLabel.setAttribute('x', point.x);
      facilityLabel.setAttribute('y', Math.max(12, point.facilityY - 9));
      facilityLabel.setAttribute('text-anchor', 'middle');
      facilityLabel.setAttribute('font-size', '10');
      facilityLabel.setAttribute('font-weight', '700');
      facilityLabel.setAttribute('fill', '#176b87');
      facilityLabel.textContent = `${number.format(point.facilities)}F`;
      svg.appendChild(facilityLabel);

      const label = document.createElementNS(namespace, 'text');
      label.setAttribute('x', point.x);
      label.setAttribute('y', height - 12);
      label.setAttribute('text-anchor', 'middle');
      label.setAttribute('font-size', '11');
      label.setAttribute('fill', '#52606d');
      label.textContent = point.label;
      svg.appendChild(label);
    });

    host.appendChild(svg);
  }

  function renderAlerts(indicators, categories = []) {
    const host = byId('alert-list');
    const ratingRisks = [...indicators]
      .filter((item) => Number.isFinite(Number(item.score)) && Number(item.score) < 3)
      .sort((left, right) => Number(left.score) - Number(right.score))
      .map((item) => ({ ...item, kind: 'rating', severity: Number(item.score) < 2 ? 'critical' : 'priority' }));
    const distributionRisks = categoryRiskItems(categories)
      .map((item) => ({ ...item, kind: 'distribution' }));
    const priorityItems = [...distributionRisks, ...ratingRisks].slice(0, 3);

    if (!priorityItems.length) {
      host.innerHTML = '<div class="empty">No priority issues were found for this period.</div>';
      return;
    }

    host.innerHTML = priorityItems.map((item) => {
      const score = Number(item.score);
      const title = item.kind === 'distribution'
        ? `${item.question_name}: ${item.option_label}`
        : item.indicator_name;
      const result = item.kind === 'distribution'
        ? `<span class="alert-score">${item.percentage.toFixed(1)}%</span><span>${number.format(item.count)} response(s)</span>`
        : `<span class="alert-score">${score.toFixed(1)}/5 ${starMarkup(score)}</span><span>${number.format(Number(item.responses) || 0)} response(s)</span>`;
      return `
        <article class="priority-alert ${item.severity}">
          <div class="priority-alert-title">
            <span class="priority-alert-icon">${indicatorIconMarkup(item.icon)}</span>
            <h3>${escapeHtml(title)}</h3>
          </div>
          <p>${escapeHtml(item.facility_name)} &middot; ${escapeHtml(item.department_name)}</p>
          <div class="alert-meta">${result}</div>
        </article>`;
    }).join('');
  }

  function aggregateIndicators(indicators) {
    const departmentGroups = new Map();
    indicators.forEach((item) => {
      const score = Number(item.score_raw ?? item.score);
      if (!Number.isFinite(score)) return;
      const responses = Math.max(0, Number(item.responses) || 0);
      if (responses === 0) return;
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
        indicator.departments.set(
          department.departmentKey,
          department.department_name || department.departmentKey
        );
      }
    });

    return [...indicatorGroups.values()].map((indicator) => {
      const facilityNames = [...indicator.facilities.values()];
      const departmentNames = [...indicator.departments.values()];
      return {
        ...indicator,
        score: indicator.responses > 0 ? indicator.weightedScore / indicator.responses : null,
        facility_name: facilityNames.length === 1
          ? facilityNames[0]
          : `${facilityNames.length} facilities`,
        department_name: departmentNames.length === 1
          ? departmentNames[0]
          : `${departmentNames.length} departments`
      };
    });
  }

  function renderDepartments(items) {
    const host = byId('department-list');
    const departments = items
      .filter((item) => Number.isFinite(Number(item.score)))
      .sort((left, right) => Number(right.score) - Number(left.score))
      .slice(0, 6);

    if (!departments.length) {
      host.innerHTML = '<div class="empty">No department performance data is available.</div>';
      return;
    }

    host.innerHTML = departments.map((item) => {
      const score = Number(item.score);
      return `
        <div class="indicator-row">
          <span title="${escapeHtml(item.department_name)}">${escapeHtml(item.department_name)}</span>
          <div class="progress" role="progressbar" aria-label="${escapeHtml(item.department_name)} score" aria-valuenow="${score}" aria-valuemin="0" aria-valuemax="5">
            <span class="${progressClass(score, 5)} ${scoreToneClass(score)}"></span>
          </div>
          <strong>${score.toFixed(1)}/5 ${starMarkup(score)}</strong>
        </div>`;
    }).join('');
  }

  function aggregateDepartmentPerformance(items) {
    const groups = new Map();
    (items || []).forEach((item) => {
      const key = String(item.department_id ?? item.department_name ?? '');
      if (!key) return;
      if (!groups.has(key)) {
        groups.set(key, {
          department_id: item.department_id,
          department_name: item.department_name,
          responses: 0,
          weighted: 0,
          weight: 0
        });
      }
      const group = groups.get(key);
      const responses = Number(item.responses) || 0;
      const score = Number(item.score);
      group.responses += responses;
      if (Number.isFinite(score)) {
        const weight = Math.max(1, responses);
        group.weighted += score * weight;
        group.weight += weight;
      }
    });
    return [...groups.values()].map((group) => ({
      department_id: group.department_id,
      department_name: group.department_name,
      responses: group.responses,
      score: group.weight > 0 ? group.weighted / group.weight : null
    }));
  }

  function renderRecent(items) {
    const host = byId('recent-feedback');
    if (!items.length) {
      host.innerHTML = '<tr><td colspan="5" class="empty">No feedback was received in this period.</td></tr>';
      return;
    }

    host.innerHTML = items.slice(0, 6).map((item) => {
      const rating = Number(item.rating ?? item.score ?? 0);
      const sentiment = String(item.sentiment || 'neutral').toLowerCase();
      const date = String(item.submitted_at || item.srvy_rpl_dt || '').slice(0, 10) || '\u2014';
      return `
        <tr>
          <td>${escapeHtml(date)}</td>
          <td>${escapeHtml(item.facility_name || item.hospital_nin || '\u2014')}</td>
          <td>${escapeHtml(item.department_name || item.department_id || '\u2014')}</td>
          <td>${rating ? `${rating.toFixed(1)}/5` : '\u2014'}</td>
          <td><span class="badge ${escapeHtml(sentiment)}">${escapeHtml(sentiment)}</span></td>
        </tr>`;
    }).join('');
  }

  function populateOptions(id, items, key, label) {
    const select = byId(id);
    if (!select || select.dataset.ready === 'true') return;
    items.forEach((item) => {
      const option = document.createElement('option');
      option.value = item[key] ?? '';
      option.textContent = item[label] ?? item[key] ?? '';
      select.appendChild(option);
    });
    select.dataset.ready = 'true';
  }

  function applyFacilityScope(items) {
    const select = byId('facility-filter');
    if (!select || !Array.isArray(items)) return;
    if (items.length === 1) {
      const facility = items[0];
      select.value = String(facility.facility_nin ?? facility.facilityNIN ?? '');
      select.disabled = true;
      select.dispatchEvent(new Event('change', { bubbles: true }));
      return;
    }
    select.disabled = false;
  }

  function normalizeUnifiedPayload(payload) {
    const data = payload?.data || payload || {};
    return {
      summary: data.summary || {},
      trend: data.trend || data.monthly_trend || [],
      facilities: data.facilities || [],
      departments: data.departments || [],
      indicators: data.indicators || data.alerts || [],
      categories: data.categories || [],
      recent: data.recent_feedback || data.responses || [],
      responseFilters: data.filters || {},
      availableVersions: data.available_versions || []
    };
  }

  async function loadExistingEndpoints(api, query) {
    const filterQuery = query.toString();
    const suffix = filterQuery ? `&${filterQuery}` : '';
    const detailedUrl = `/api/v1/analytics/summary${filterQuery ? `?${filterQuery}` : ''}`;
    const responseUrl = `/api/v1/responses?page=1&limit=10${filterQuery ? `&${filterQuery}` : ''}`;

    const [summaryResponse, detailResponse, responseList] = await Promise.all([
      api(`/api/v1/analytics/summary?summary_only=1&trend_months=6${suffix}`),
      api(detailedUrl),
      api(responseUrl)
    ]);
    const monthlyTrend = summaryResponse.data?.monthly_trend || [];
    if (
      monthlyTrend.length === 1
      && (monthlyTrend[0].facility_count === undefined || monthlyTrend[0].facility_count === null)
    ) {
      monthlyTrend[0].facility_count = summaryResponse.data?.summary?.facility_count || 0;
    }

    return {
      summary: {
        ...(summaryResponse.data?.summary || {}),
        ...(detailResponse.data?.summary || {}),
        // Do not let score-processing add a facility to the KPI.  The raw
        // summary count is the distinct NIN count in srvy_responses.
        facility_count: summaryResponse.data?.summary?.facility_count || 0
      },
      trend: monthlyTrend,
      facilities: responseList.data?.filters?.facilities || summaryResponse.data?.facilities || [],
      departments: detailResponse.data?.departments || [],
      indicators: detailResponse.data?.indicators || [],
      categories: detailResponse.data?.categories || [],
      recent: responseList.data?.items || [],
      responseFilters: responseList.data?.filters || {},
      availableVersions: detailResponse.data?.available_versions
        || summaryResponse.data?.available_versions
        || []
    };
  }

  function renderDashboard(model) {
    const indicators = aggregateIndicators(
      model.indicators.filter((item) => Number.isFinite(Number(item.score)))
    );
    const average = model.summary.score !== null && model.summary.score !== undefined
      ? Number(model.summary.score)
      : (indicators.length
        ? indicators.reduce((total, item) => total + Number(item.score), 0) / indicators.length
        : null);

    setText('kpi-responses', number.format(Number(model.summary.total_responses) || 0));
    setAverageScore('kpi-rating', average);
    setText(
      'kpi-facilities',
      `${number.format(Number(model.summary.facility_count) || 0)}/${number.format(Number(model.summary.configured_facility_count) || 0)}`
    );
    renderFacilityBreakdown(model.summary.facility_type_counts);
    const categoryRisks = categoryRiskItems(model.categories);
    setText('kpi-priority', number.format(indicators.filter((item) => Number(item.score) < 3).length + categoryRisks.length));

    renderTrend(model.trend);
    renderAlerts(indicators, model.categories);
    renderDepartments(aggregateDepartmentPerformance(model.departments));
    renderRecent(model.recent);
    const permittedFacilities = model.responseFilters.facilities || model.facilities;
    populateOptions('facility-filter', permittedFacilities, 'facility_nin', 'facility_name');
    applyFacilityScope(permittedFacilities);
    populateOptions('department-filter', model.responseFilters.departments || model.departments, 'department_id', 'department_name');
    populateSurveyVersions(model.availableVersions);
    setText('data-updated', `Updated ${new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date())}`);
  }

  function renderFailure() {
    ['kpi-responses', 'kpi-rating', 'kpi-facilities', 'kpi-priority'].forEach((id) => setText(id, '\u2014'));
    renderFacilityBreakdown([]);
    renderTrend([]);
    renderAlerts([], []);
    renderDepartments([]);
    renderRecent([]);
    setText('data-updated', 'Data could not be updated');
  }

  async function load() {
    const api = window.AbhiprayaUI?.json;
    if (!api) return;
    const from = byId('from-filter');
    const to = byId('to-filter');
    from?.setCustomValidity('');
    to?.setCustomValidity('');
    if (from.value && !to.value) {
      to.setCustomValidity('Select a To date to complete the date range.');
      to.reportValidity();
      return;
    }
    if (!from.value && to.value) {
      from.setCustomValidity('Select a From date to complete the date range.');
      from.reportValidity();
      return;
    }
    if (from.value && to.value && from.value > to.value) {
      to.setCustomValidity('To date must be the same as or later than From date.');
      to.reportValidity();
      return;
    }
    byId('apply-filters')?.setAttribute('disabled', 'disabled');
    try {
      const query = currentFilters();
      const model = DASHBOARD_ENDPOINT
        ? normalizeUnifiedPayload(await api(`${DASHBOARD_ENDPOINT}?${query.toString()}`))
        : await loadExistingEndpoints(api, query);
      renderDashboard(model);
    } catch (error) {
      console.error('Dashboard load failed:', error);
      renderFailure();
    } finally {
      byId('apply-filters')?.removeAttribute('disabled');
    }
  }

  setWelcomeName();
  const today = new Date();
  const localIsoDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  };
  const todayValue = localIsoDate(today);
  const fromFilter = byId('from-filter');
  const toFilter = byId('to-filter');
  if (fromFilter) {
    fromFilter.max = todayValue;
  }

  function populateSurveyVersions(versions) {
    const select = byId('survey-version-filter');
    if (!select) return;
    const selected = select.value;
    select.replaceChildren(new Option('All survey versions', ''));
    (versions || []).forEach((version) => {
      select.add(new Option(`Version ${version}`, version));
    });
    if ([...select.options].some((option) => option.value === selected)) {
      select.value = selected;
    }
  }
  if (toFilter) {
    toFilter.max = todayValue;
  }

  $('[data-topnav-toggle]')?.addEventListener('click', (event) => {
    const navigation = byId('primary-navigation');
    const isOpen = navigation.classList.toggle('is-open');
    event.currentTarget.setAttribute('aria-expanded', String(isOpen));
  });
  byId('dashboard-filter-form')?.addEventListener('submit', (event) => {
    event.preventDefault();
    load();
  });
  load();
})();
