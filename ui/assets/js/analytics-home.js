(() => {
  'use strict';

  const number = new Intl.NumberFormat();
  const byId = (id) => document.getElementById(id);
  let configurationLoaded = false;
  let currentMonthlyPerformance = [];
  let currentIndicators = [];

  const escapeHtml = (value) => String(value ?? '').replace(
    /[&<>'"]/g,
    (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character])
  );

  function reportTypeLabel(type) {
    return String(type || 'rating').replaceAll('_', ' ');
  }

  function reportTypeSymbol(type) {
    return ({
      rating: '\u2605',
      binary: '\u25d0',
      category: '\u25a4',
      multi_category: '\u2611',
      numeric: '#',
      text: '\u270e',
      duration: '\u23f1',
      date: '\u25a6',
      availability: '\u25c9',
      severity: '!',
      demographic: '\u25c9',
      consent: '\u2713',
      ranking: '\u2261',
      matrix_rating: '\u25a6',
      file: '\u25a3'
    }[String(type || '').toLowerCase()] || '\u25cf');
  }

  function indicatorIconMarkup(icon, reportType) {
    const type = String(icon?.type || '');
    const value = String(icon?.value || '');
    if (type === 'image' && /^\/api\/assets\/img\/[A-Za-z0-9_./-]+\.(?:png|svg|webp)$/i.test(value) && !value.includes('..')) {
      return `<span class="indicator-icon"><img src="${escapeHtml(value)}" alt=""></span>`;
    }
    if (type === 'bootstrap' && /^bi-[a-z0-9-]+$/i.test(value)) {
      return `<span class="indicator-icon"><i class="bi ${escapeHtml(value)}" aria-hidden="true"></i></span>`;
    }
    if (type === 'text' && value) {
      return `<span class="indicator-icon" aria-hidden="true">${escapeHtml(value)}</span>`;
    }
    return `<span class="indicator-icon" aria-hidden="true">${escapeHtml(reportTypeSymbol(reportType))}</span>`;
  }

  function distributionClass(item, reportType) {
    const sentiment = String(item.sentiment || '').toLowerCase();
    const status = String(item.status || '').toLowerCase();
    const label = String(item.label || '').toLowerCase();
    if (item.consent === true) return 'is-consent';
    if (item.consent === false) return 'is-negative';
    if (sentiment === 'positive') return 'is-positive';
    if (sentiment === 'negative') return 'is-negative';
    if (status === 'available') return 'is-available';
    if (status === 'unavailable') return 'is-unavailable';
    if (reportType === 'severity') {
      if (status === 'critical' || label.includes('critical')) return 'is-critical';
      if (status === 'high' || label.includes('high')) return 'is-negative';
      if (status === 'medium' || label.includes('medium')) return 'is-warning';
      return 'is-positive';
    }
    return '';
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
        const percentage = Number(item.percentage);
        if (
          !/(negative|unavailable|not available|critical|high|severe|poor|very poor|declined)/.test(semantic)
          || !Number.isFinite(percentage)
          || percentage < 20
        ) return;
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

  function shortQuestion(question, maximumLength = 88) {
    const text = String(question || '').trim();
    if (text.length <= maximumLength) return text;
    const shortened = text.slice(0, maximumLength);
    const lastSpace = shortened.lastIndexOf(' ');
    return `${(lastSpace > 35 ? shortened.slice(0, lastSpace) : shortened).trim()}…`;
  }

  function compactIndicatorLabel(question) {
    const original = String(question || '').trim();
    let label = original.replace(/^how would you rate\s+(?:the\s+)?/i, '');
    label = label.replace(/\s+(?:in|within) the hospital(?:,.*)?\??$/i, '');
    label = label.replace(/,\s*(?:such as|including)\s+.*\??$/i, '');
    label = label.replace(/\?$/, '').trim();
    if (/^overall,?\s+how satisfied were you/i.test(original)) label = 'Overall satisfaction';
    if (!label || label === original) return shortQuestion(original);
    return label.charAt(0).toUpperCase() + label.slice(1);
  }

  function renderAdaptiveIndicators(categories, indicators, facilitySelected) {
    const host = byId('adaptive-indicators');
    const ratingCards = (indicators || []).filter((item) => Number.isFinite(Number(item.score))).map((item) => {
      const score = Number(item.score);
      return `
        <article class="adaptive-card indicator-rating-card">
          <header class="adaptive-card-head">
            <div class="indicator-identity">
              ${indicatorIconMarkup(item.icon, item.report_type)}
              <div><h3 title="${escapeHtml(item.indicator_name)}">${escapeHtml(compactIndicatorLabel(item.indicator_name))}</h3><p>${number.format(Number(item.responses) || 0)} responses</p></div>
            </div>
            <span class="indicator-type">Overall rating</span>
          </header>
          <div class="indicator-overall-rating"><strong>${score.toFixed(1)}/5</strong>${starMarkup(score)}</div>
        </article>`;
    });
    if (!ratingCards.length && !categories.length) {
      host.innerHTML = '<div class="empty">No type-specific indicator responses are available for this selection.</div>';
      return;
    }
    const categoryCards = !facilitySelected ? [] : categories.map((item) => {
      const reportType = String(item.report_type || 'category');
      const unit = item.unit ? ` ${escapeHtml(item.unit)}` : '';
      let body = '';
      if (item.statistics) {
        const statistics = item.statistics;
        if (reportType === 'numeric') {
          body = `<dl class="adaptive-statistics">
            <div><dt>Average</dt><dd>${statistics.average ?? '\u2014'}${unit}</dd></div>
            <div><dt>Minimum</dt><dd>${statistics.minimum ?? '\u2014'}${unit}</dd></div>
            <div><dt>Maximum</dt><dd>${statistics.maximum ?? '\u2014'}${unit}</dd></div>
            <div><dt>Total</dt><dd>${statistics.total ?? '\u2014'}${unit}</dd></div>
          </dl>`;
        } else if (reportType === 'duration') {
          body = `<dl class="adaptive-statistics">
            <div><dt>Average</dt><dd>${statistics.average ?? '\u2014'}${unit}</dd></div>
            <div><dt>Median</dt><dd>${statistics.median ?? '\u2014'}${unit}</dd></div>
            <div><dt>Minimum</dt><dd>${statistics.minimum ?? '\u2014'}${unit}</dd></div>
            <div><dt>Maximum</dt><dd>${statistics.maximum ?? '\u2014'}${unit}</dd></div>
          </dl>`;
        } else {
          body = `<div class="adaptive-count"><strong>${number.format(Number(statistics.count) || 0)}</strong><span>${reportType === 'file' ? 'files awaiting review' : 'anonymous responses'}</span></div>`;
        }
      } else {
        const itemCount = Math.max(1, (item.items || []).length);
        const rows = (item.items || []).map((option) => {
          const isMatrix = Number.isFinite(Number(option.average));
          const isRanking = Number.isFinite(Number(option.average_rank));
          const percentage = isMatrix
            ? Math.max(0, Math.min(100, Number(option.average) / 5 * 100))
            : isRanking
              ? Math.max(0, Math.min(100, (itemCount + 1 - Number(option.average_rank)) / itemCount * 100))
              : Math.max(0, Math.min(100, Number(option.percentage) || 0));
          const output = isMatrix
            ? `${Number(option.average).toFixed(1)}/5`
            : isRanking
              ? `#${Number(option.average_rank).toFixed(1)}`
              : `${percentage.toFixed(1)}%`;
          return `
            <div class="distribution-row ${distributionClass(option, reportType)}">
              <span title="${escapeHtml(option.label)}">${escapeHtml(option.label)}</span>
              <progress max="100" value="${percentage}" aria-label="${escapeHtml(option.label)}: ${escapeHtml(output)}"></progress>
              <strong>${escapeHtml(output)}</strong>
            </div>`;
        }).join('');
        body = `<div class="distribution-list">${rows || '<div class="empty">No responses</div>'}</div>`;
      }
      return `
        <article class="adaptive-card">
          <header class="adaptive-card-head">
            <div class="indicator-identity">
              ${indicatorIconMarkup(item.icon, reportType)}
              <div><h3 title="${escapeHtml(item.question_name)}">${escapeHtml(shortQuestion(item.question_name))}</h3><p>${number.format(Number(item.total_responses) || 0)} responses &middot; Version ${escapeHtml(item.survey_version)}</p></div>
            </div>
            <span class="indicator-type">${escapeHtml(reportTypeLabel(reportType))}</span>
          </header>
          ${body}
        </article>`;
    });
    host.innerHTML = [...ratingCards, ...categoryCards].join('');
  }

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

  function scoreColor(score) {
    if (!Number.isFinite(score) || score < 2) return '#c62828';
    if (score < 3) return '#e05a33';
    if (score < 4) return '#d97706';
    if (score < 4.5) return '#2e7d32';
    return '#087f5b';
  }

  function setAverageScore(id, score) {
    const element = byId(id);
    if (!element) return;
    element.classList.remove('skeleton');
    element.innerHTML = Number.isFinite(score)
      ? `<span>${score.toFixed(1)}/5</span>${starMarkup(score)}`
      : '\u2014';
  }

  function filters() {
    const query = new URLSearchParams();
    query.set('lang', localStorage.getItem('abhipraya_admin_language') === 'hi' ? '2' : '1');
    [
      ['facility_nin', 'facility-filter'],
      ['department_id', 'department-filter'],
      ['survey_version', 'survey-version-filter'],
      ['from', 'from-filter'],
      ['to', 'to-filter']
    ].forEach(([key, id]) => {
      const value = byId(id)?.value?.trim();
      if (value) query.set(key, value);
    });
    return query;
  }

  function scoreBand(score) {
    if (!Number.isFinite(score)) return { label: 'No score', className: 'no-score' };
    if (score < 2) return { label: 'Critical', className: 'critical' };
    if (score < 3) return { label: 'Priority', className: 'priority' };
    if (score < 4) return { label: 'Monitor', className: 'monitor' };
    if (score < 4.5) return { label: 'Good', className: 'good' };
    return { label: 'Excellent', className: 'excellent' };
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

  function scoreBar(score) {
    const safe = Number.isFinite(score) ? Math.min(5, Math.max(0, score)) : 0;
    return `<div class="score-cell"><div class="score-bar"><span class="${progressClass(safe, 5)} ${scoreToneClass(score)}"></span></div><span class="score-value">${Number.isFinite(score) ? `${score.toFixed(1)} ${starMarkup(score)}` : '\u2014'}</span></div>`;
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

  function renderTrend(items) {
    const host = byId('analytics-trend');
    host.replaceChildren();
    if (!items.length) {
      host.innerHTML = '<div class="empty">No monthly trend data is available for this selection.</div>';
      return;
    }

    const width = 820;
    const height = 270;
    const padX = 42;
    const padTop = 50;
    const padBottom = 38;
    const responseValues = items.map((item) => Number(item.response_count) || 0);
    const facilityValues = items.map((item) => Number(item.facility_count) || 0);
    const responseMaximum = Math.max(...responseValues, 1);
    const facilityMaximum = Math.max(...facilityValues, 1);
    const plotHeight = height - padTop - padBottom;
    const points = items.map((item, index) => ({
      x: items.length === 1
        ? width / 2
        : padX + index * ((width - padX * 2) / (items.length - 1)),
      responseY: height - padBottom - (responseValues[index] / responseMaximum) * plotHeight,
      facilityY: height - padBottom - (facilityValues[index] / facilityMaximum) * plotHeight,
      responses: responseValues[index],
      facilities: facilityValues[index],
      label: item.month_key || ''
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
    legendLine.setAttribute('x1', '198');
    legendLine.setAttribute('x2', '222');
    legendLine.setAttribute('y1', '19');
    legendLine.setAttribute('y2', '19');
    legendLine.setAttribute('stroke', '#176b87');
    legendLine.setAttribute('stroke-width', '4');
    svg.appendChild(legendLine);
    const legendLineText = document.createElementNS(namespace, 'text');
    legendLineText.setAttribute('x', '229');
    legendLineText.setAttribute('y', '23');
    legendLineText.setAttribute('font-size', '11');
    legendLineText.setAttribute('fill', '#334155');
    legendLineText.textContent = 'Reporting facilities';
    svg.appendChild(legendLineText);

    [0, .5, 1].forEach((fraction) => {
      const y = height - padBottom - fraction * plotHeight;
      const grid = document.createElementNS(namespace, 'line');
      grid.setAttribute('x1', padX);
      grid.setAttribute('x2', width - padX);
      grid.setAttribute('y1', y);
      grid.setAttribute('y2', y);
      grid.setAttribute('stroke', '#e5ebef');
      grid.setAttribute('stroke-dasharray', '4 5');
      svg.appendChild(grid);
    });

    const barWidth = Math.max(
      12,
      Math.min(42, ((width - (padX * 2)) / Math.max(items.length, 1)) * 0.5)
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
      valueLabel.setAttribute('y', Math.min(height - padBottom - 6, point.responseY + 14));
      valueLabel.setAttribute('text-anchor', 'middle');
      valueLabel.setAttribute('font-size', '10');
      valueLabel.setAttribute('font-weight', '700');
      valueLabel.setAttribute('fill', '#123d55');
      valueLabel.textContent = number.format(point.responses);
      svg.appendChild(valueLabel);

      const facilityLabel = document.createElementNS(namespace, 'text');
      facilityLabel.setAttribute('x', point.x);
      facilityLabel.setAttribute('y', Math.max(12, point.facilityY - 9));
      facilityLabel.setAttribute('text-anchor', 'middle');
      facilityLabel.setAttribute('font-size', '9');
      facilityLabel.setAttribute('font-weight', '700');
      facilityLabel.setAttribute('fill', '#176b87');
      facilityLabel.textContent = `${number.format(point.facilities)}F`;
      svg.appendChild(facilityLabel);

      const label = document.createElementNS(namespace, 'text');
      label.setAttribute('x', point.x);
      label.setAttribute('y', height - 11);
      label.setAttribute('text-anchor', 'middle');
      label.setAttribute('font-size', '10');
      label.setAttribute('fill', '#52606d');
      label.textContent = point.label;
      svg.appendChild(label);
    });
    host.appendChild(svg);
  }

  function renderDepartmentTrend(items, hostId = 'analytics-trend') {
    const host = byId(hostId);
    host.replaceChildren();
    if (!items.length) {
      host.innerHTML = '<div class="empty">No monthly rating data is available for this facility and department.</div>';
      return;
    }

    const width = 820;
    const height = 270;
    const padX = 42;
    const padTop = 50;
    const padBottom = 38;
    const responses = items.map((item) => Number(item.response_count) || 0);
    const responseMaximum = Math.max(...responses, 1);
    const plotHeight = height - padTop - padBottom;
    const points = items.map((item, index) => {
      const score = item.average_score === null || item.average_score === undefined
        ? null
        : Number(item.average_score);
      return {
        x: items.length === 1
          ? width / 2
          : padX + index * ((width - padX * 2) / (items.length - 1)),
        responseY: height - padBottom - (responses[index] / responseMaximum) * plotHeight,
        scoreY: Number.isFinite(score)
          ? height - padBottom - (Math.min(5, Math.max(0, score)) / 5) * plotHeight
          : height - padBottom,
        responses: responses[index],
        score,
        label: item.month_key || ''
      };
    });

    const namespace = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(namespace, 'svg');
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', 'Monthly response count and average rating');
    const description = document.createElementNS(namespace, 'desc');
    description.textContent = points.map((point) => (
      `${point.label}: ${point.responses} responses, average rating ${
        Number.isFinite(point.score) ? `${point.score.toFixed(1)} out of 5` : 'not available'
      }`
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
    legendBarText.textContent = 'Responses';
    svg.appendChild(legendBarText);

    const legendLine = document.createElementNS(namespace, 'line');
    legendLine.setAttribute('x1', '148');
    legendLine.setAttribute('x2', '172');
    legendLine.setAttribute('y1', '19');
    legendLine.setAttribute('y2', '19');
    legendLine.setAttribute('stroke', '#b7791f');
    legendLine.setAttribute('stroke-width', '4');
    svg.appendChild(legendLine);
    const legendLineText = document.createElementNS(namespace, 'text');
    legendLineText.setAttribute('x', '179');
    legendLineText.setAttribute('y', '23');
    legendLineText.setAttribute('font-size', '11');
    legendLineText.setAttribute('fill', '#334155');
    legendLineText.textContent = 'Average rating (out of 5)';
    svg.appendChild(legendLineText);

    [0, .5, 1].forEach((fraction) => {
      const y = height - padBottom - fraction * plotHeight;
      const grid = document.createElementNS(namespace, 'line');
      grid.setAttribute('x1', padX);
      grid.setAttribute('x2', width - padX);
      grid.setAttribute('y1', y);
      grid.setAttribute('y2', y);
      grid.setAttribute('stroke', '#e5ebef');
      grid.setAttribute('stroke-dasharray', '4 5');
      svg.appendChild(grid);
    });

    const barWidth = Math.max(
      12,
      Math.min(42, ((width - (padX * 2)) / Math.max(items.length, 1)) * 0.5)
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
      title.textContent = `${point.label}: ${number.format(point.responses)} responses`;
      bar.appendChild(title);
      svg.appendChild(bar);
    });

    const scoredPoints = points.filter((point) => Number.isFinite(point.score));
    if (scoredPoints.length) {
      const line = document.createElementNS(namespace, 'polyline');
      line.setAttribute(
        'points',
        scoredPoints.length === 1
          ? `${scoredPoints[0].x - 42},${scoredPoints[0].scoreY} ${scoredPoints[0].x + 42},${scoredPoints[0].scoreY}`
          : scoredPoints.map((point) => `${point.x},${point.scoreY}`).join(' ')
      );
      line.setAttribute('fill', 'none');
      line.setAttribute('stroke', '#b7791f');
      line.setAttribute('stroke-width', '4');
      line.setAttribute('stroke-linecap', 'round');
      line.setAttribute('stroke-linejoin', 'round');
      svg.appendChild(line);
    }

    points.forEach((point) => {
      const responseLabel = document.createElementNS(namespace, 'text');
      responseLabel.setAttribute('x', point.x);
      responseLabel.setAttribute('y', Math.min(height - padBottom - 6, point.responseY + 14));
      responseLabel.setAttribute('text-anchor', 'middle');
      responseLabel.setAttribute('font-size', '10');
      responseLabel.setAttribute('font-weight', '700');
      responseLabel.setAttribute('fill', '#123d55');
      responseLabel.textContent = number.format(point.responses);
      svg.appendChild(responseLabel);

      if (Number.isFinite(point.score)) {
        const circle = document.createElementNS(namespace, 'circle');
        circle.setAttribute('cx', point.x);
        circle.setAttribute('cy', point.scoreY);
        circle.setAttribute('r', '5');
        circle.setAttribute('fill', scoreColor(point.score));
        circle.setAttribute('stroke', '#ffffff');
        circle.setAttribute('stroke-width', '3');
        const title = document.createElementNS(namespace, 'title');
        title.textContent = `${point.label}: ${point.score.toFixed(1)} out of 5 stars`;
        circle.appendChild(title);
        svg.appendChild(circle);

        const scoreLabel = document.createElementNS(namespace, 'text');
        scoreLabel.setAttribute('x', point.x);
        scoreLabel.setAttribute('y', Math.max(12, point.scoreY - 9));
        scoreLabel.setAttribute('text-anchor', 'middle');
        scoreLabel.setAttribute('font-size', '10');
        scoreLabel.setAttribute('font-weight', '700');
        scoreLabel.setAttribute('fill', scoreColor(point.score));
        scoreLabel.textContent = `\u2605 ${point.score.toFixed(1)}`;
        svg.appendChild(scoreLabel);
      }

      const monthLabel = document.createElementNS(namespace, 'text');
      monthLabel.setAttribute('x', point.x);
      monthLabel.setAttribute('y', height - 11);
      monthLabel.setAttribute('text-anchor', 'middle');
      monthLabel.setAttribute('font-size', '10');
      monthLabel.setAttribute('fill', '#52606d');
      monthLabel.textContent = point.label;
      svg.appendChild(monthLabel);
    });

    host.appendChild(svg);
  }

  function populateIndicatorOptions(indicators, enabled) {
    const select = byId('indicator-filter');
    const selected = select.value;
    const unique = new Map();
    indicators.forEach((item) => {
      const id = String(item.indicator_id || '');
      if (id && !unique.has(id)) {
        unique.set(id, item);
      }
    });
    select.replaceChildren(new Option(
      enabled ? 'Select an indicator' : 'Select facility and department first',
      ''
    ));
    [...unique.entries()]
      .sort((left, right) => left[0].localeCompare(right[0], undefined, { numeric: true }))
      .forEach(([id, item]) => select.add(new Option(
        `${reportTypeSymbol(item.report_type)} ${id} \u2014 ${item.indicator_name || id}`,
        id
      )));
    select.disabled = !enabled || unique.size === 0;
    if ([...select.options].some((option) => option.value === selected)) {
      select.value = selected;
    }
  }

  function renderSelectedIndicatorTrend() {
    const host = byId('indicator-trend');
    const indicatorId = byId('indicator-filter').value;
    if (!indicatorId) {
      host.innerHTML = '<div class="empty">Select an indicator to view its monthly response and rating trend.</div>';
      setText('indicator-trend-title', 'Monthly indicator performance');
      setText('indicator-trend-description', 'Select a facility, department, and indicator to compare valid responses with its average rating.');
      return;
    }
    const indicator = currentIndicators.find((item) => String(item.indicator_id) === indicatorId);
    const series = currentMonthlyPerformance.map((item) => ({
      month_key: item.month_key,
      response_count: Number(item.indicator_counts?.[indicatorId]) || 0,
      average_score: item.indicator_scores?.[indicatorId] ?? null
    }));
    setText('indicator-trend-title', indicator?.indicator_name || indicatorId);
    setText(
      'indicator-trend-description',
      `${indicatorId}: monthly valid responses and average rating out of 5.`
    );
    renderDepartmentTrend(series, 'indicator-trend');
  }

  function renderHighest(indicators) {
    const host = byId('highest-indicators');
    const items = indicators
      .filter((item) => Number.isFinite(Number(item.score)) && Number(item.score) > 4)
      .sort((left, right) => Number(right.score) - Number(left.score))
      .slice(0, 6);
    window.__abhiprayaHighestIndicatorKeys = new Set(items.map((item) => String(item.indicator_id ?? item.indicator_name)));
    if (!items.length) {
      host.innerHTML = '<div class="empty">No indicators are rated above 4.0/5 in this selection.</div>';
      return;
    }
    host.innerHTML = items.map((item) => {
      const score = Number(item.score);
      return `
        <article class="highest-item">
          <div class="highest-item-title">
            ${indicatorIconMarkup(item.icon, item.report_type)}
            <div><h3>${escapeHtml(item.indicator_name)}</h3><p>${escapeHtml(item.facility_name)} &middot; ${escapeHtml(item.department_name)}</p></div>
          </div>
          <div class="highest-item-score"><strong>${score.toFixed(1)}/5 ${starMarkup(score)}</strong><span>${number.format(Number(item.responses) || 0)} responses</span></div>
        </article>`;
    }).join('');
    return;

    const valid = indicators.filter((item) => Number.isFinite(Number(item.score)));
    const groups = [
      { label: 'Excellent (4.5\u20135.0)', count: valid.filter((item) => Number(item.score) >= 4.5).length, className: 'excellent' },
      { label: 'Good (4.0\u20134.4)', count: valid.filter((item) => Number(item.score) >= 4 && Number(item.score) < 4.5).length, className: 'good' },
      { label: 'Monitor (3.0\u20133.9)', count: valid.filter((item) => Number(item.score) >= 3 && Number(item.score) < 4).length, className: 'monitor' },
      { label: 'Priority (2.0\u20132.9)', count: valid.filter((item) => Number(item.score) >= 2 && Number(item.score) < 3).length, className: 'priority' },
      { label: 'Critical (below 2.0)', count: valid.filter((item) => Number(item.score) < 2).length, className: 'critical' }
    ];
    const total = valid.length;
    const donut = byId('health-donut');
    donut.querySelector('svg')?.remove();
    const namespace = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(namespace, 'svg');
    svg.setAttribute('viewBox', '0 0 120 120');
    svg.setAttribute('aria-hidden', 'true');
    const track = document.createElementNS(namespace, 'circle');
    track.setAttribute('class', 'health-track');
    track.setAttribute('cx', '60');
    track.setAttribute('cy', '60');
    track.setAttribute('r', '48');
    track.setAttribute('pathLength', '100');
    svg.appendChild(track);
    let cursor = 0;
    groups.forEach((group) => {
      const percentage = total ? group.count / total * 100 : 0;
      if (percentage <= 0) return;
      const segment = document.createElementNS(namespace, 'circle');
      segment.setAttribute('class', `health-segment ${group.className}`);
      segment.setAttribute('cx', '60');
      segment.setAttribute('cy', '60');
      segment.setAttribute('r', '48');
      segment.setAttribute('pathLength', '100');
      segment.setAttribute('stroke-dasharray', `${percentage} ${100 - percentage}`);
      segment.setAttribute('stroke-dashoffset', String(-cursor));
      segment.setAttribute('transform', 'rotate(-90 60 60)');
      svg.appendChild(segment);
      cursor += percentage;
    });
    donut.prepend(svg);
    donut.querySelector('strong').textContent = number.format(total);
    byId('health-legend').innerHTML = groups.map((group) => `
      <div class="legend-row"><span class="legend-dot ${group.className}"></span><span>${group.label}</span><strong>${number.format(group.count)}</strong></div>
    `).join('');
  }

  function renderPriority(indicators, categories = []) {
    const host = byId('priority-indicators');
    const highestKeys = window.__abhiprayaHighestIndicatorKeys || new Set();
    const ratingItems = indicators
      .filter((item) => Number.isFinite(Number(item.score)) && Number(item.score) < 3)
      .filter((item) => !highestKeys.has(String(item.indicator_id ?? item.indicator_name)))
      .sort((left, right) => Number(left.score) - Number(right.score))
      .map((item) => ({ ...item, kind: 'rating', severity: Number(item.score) < 2 ? 'critical' : 'priority' }));
    const distributionItems = categoryRiskItems(categories)
      .map((item) => ({ ...item, kind: 'distribution' }));
    const relativeLowest = ratingItems.length ? ratingItems : indicators
      .filter((item) => Number.isFinite(Number(item.score)))
      .filter((item) => !highestKeys.has(String(item.indicator_id ?? item.indicator_name)))
      .sort((left, right) => Number(left.score) - Number(right.score))
      .slice(0, 3)
      .map((item) => ({ ...item, kind: 'rating', severity: 'priority' }));
    const items = [...distributionItems, ...relativeLowest].slice(0, 6);
    if (!items.length) {
      const validScores = indicators.map((item) => Number(item.score)).filter(Number.isFinite);
      if (validScores.length && Math.max(...validScores) === Math.min(...validScores)) {
        host.innerHTML = `<div class="empty">All rated indicators are tied at ${Math.min(...validScores).toFixed(1)}/5; there is no distinct lowest-scoring indicator.</div>`;
        return;
      }
      host.innerHTML = '<div class="empty">No priority indicators are available for this selection.</div>';
      return;
    }
    host.innerHTML = items.map((item) => {
      const score = Number(item.score);
      const title = item.kind === 'distribution'
        ? `${item.question_name}: ${item.option_label}`
        : item.indicator_name;
      const result = item.kind === 'distribution'
        ? `<strong>${item.percentage.toFixed(1)}%</strong><span>${number.format(item.count)} responses</span>`
        : `<strong>${score.toFixed(1)}/5 ${starMarkup(score)}</strong><span>${number.format(Number(item.responses) || 0)} responses</span>`;
      return `
        <article class="priority-item ${item.severity === 'critical' ? 'critical' : ''}">
          <div class="priority-item-title">
            ${indicatorIconMarkup(item.icon, item.report_type)}
            <h3>${escapeHtml(title)}</h3>
          </div>
          <p>${escapeHtml(item.facility_name)} &middot; ${escapeHtml(item.department_name)}</p>
          <div>${result}</div>
        </article>`;
    }).join('');
  }

  function renderFacilities(items) {
    const host = byId('facility-results');
    const rows = [...items].sort((left, right) => (Number(right.score) || 0) - (Number(left.score) || 0));
    if (!rows.length) {
      host.innerHTML = '<tr><td colspan="4" class="empty">No facility data is available.</td></tr>';
      return;
    }
    host.innerHTML = rows.map((item) => {
      const score = Number(item.score);
      const band = scoreBand(score);
      return `<tr><td>${escapeHtml(item.facility_name)}</td><td>${number.format(Number(item.responses) || 0)}</td><td>${scoreBar(score)}</td><td><span class="performance-badge ${band.className}">${band.label}</span></td></tr>`;
    }).join('');
  }

  function aggregateDepartments(items) {
    const groups = new Map();
    items.forEach((item) => {
      const key = String(item.department_id ?? item.department_name);
      const responses = Number(item.responses) || 0;
      const score = Number(item.score);
      if (!groups.has(key)) groups.set(key, { department_name: item.department_name, responses: 0, weighted: 0, weight: 0, facilities: new Set() });
      const group = groups.get(key);
      group.responses += responses;
      if (Number.isFinite(score)) {
        const weight = Math.max(responses, 1);
        group.weighted += score * weight;
        group.weight += weight;
      }
      if (item.facility_nin) group.facilities.add(item.facility_nin);
    });
    return [...groups.values()].map((group) => ({
      department_name: group.department_name,
      responses: group.responses,
      score: group.weight ? group.weighted / group.weight : null,
      facility_count: group.facilities.size
    })).sort((left, right) => (right.score || 0) - (left.score || 0));
  }

  function renderDepartments(items) {
    const host = byId('department-results');
    const rows = aggregateDepartments(items);
    if (!rows.length) {
      host.innerHTML = '<div class="empty">No department data is available.</div>';
      return;
    }
    host.innerHTML = rows.map((item) => {
      const score = Number(item.score);
      return `
        <div class="comparison-row">
          <span class="comparison-name"><strong>${escapeHtml(item.department_name)}</strong><small>${number.format(item.facility_count)} reporting facilities</small></span>
          ${scoreBar(score)}
          <span>${number.format(item.responses)} responses</span>
          <span>${scoreBand(score).label}</span>
        </div>`;
    }).join('');
  }

  function renderQuestions(items) {
    const host = byId('question-results');
    const rows = [...items].filter((item) => Number.isFinite(Number(item.score))).sort((left, right) => Number(left.score) - Number(right.score));
    if (!rows.length) {
      host.innerHTML = '<tr><td colspan="6" class="empty">No question-level ratings are available.</td></tr>';
      return;
    }
    host.innerHTML = rows.map((item) => `
      <tr>
        <td><div class="indicator-identity">${indicatorIconMarkup(item.icon, item.report_type)}<span>${escapeHtml(item.indicator_name)}</span></div></td>
        <td><span class="indicator-type">${escapeHtml(reportTypeLabel(item.report_type))}</span></td>
        <td>${escapeHtml(item.facility_name)}</td>
        <td>${escapeHtml(item.department_name)}</td>
        <td>${number.format(Number(item.responses) || 0)}</td>
        <td>${scoreBar(Number(item.score))}</td>
      </tr>`).join('');
  }

  function populateConfiguration(configuration) {
    if (configurationLoaded) return;
    const facilitySelect = byId('facility-filter');
    (configuration.facilities || []).forEach((facility) => facilitySelect.add(new Option(facility.facilityName, facility.facilityNIN)));
    const departmentSelect = byId('department-filter');
    (configuration.departments || []).forEach((item) => {
      if (![...departmentSelect.options].some((option) => option.value === String(item.departmentId))) {
        departmentSelect.add(new Option(item.departmentName, item.departmentId));
      }
    });
    configurationLoaded = true;
  }

  function populateSurveyVersions(versions) {
    const select = byId('survey-version-filter');
    const selected = select.value;
    select.replaceChildren(new Option('All survey versions', ''));
    (versions || []).forEach((version) => {
      select.add(new Option(`Version ${version}`, version));
    });
    if ([...select.options].some((option) => option.value === selected)) {
      select.value = selected;
    }
  }

  async function load() {
    const api = window.AbhiprayaUI?.json;
    if (!api) return;
    const button = byId('apply-filters');
    button.disabled = true;
    byId('analytics-message').hidden = true;
    try {
      const query = filters();
      const queryString = query.toString();
      const suffix = queryString ? `&${queryString}` : '';
      const detailsUrl = `/api/v1/analytics/summary${queryString ? `?${queryString}` : ''}`;
      const [summaryPayload, detailsPayload, configurationPayload] = await Promise.all([
        api(`/api/v1/analytics/summary?summary_only=1&trend_months=12${suffix}`),
        api(detailsUrl),
        configurationLoaded ? Promise.resolve(null) : api('/api/v1/qr?limit=500')
      ]);
      if (configurationPayload) populateConfiguration(configurationPayload.data || {});
      populateSurveyVersions(detailsPayload.data?.available_versions || summaryPayload.data?.available_versions || []);

      const summary = detailsPayload.data?.summary || summaryPayload.data?.summary || {};
      const reportingSummary = summaryPayload.data?.summary || summary;
      const facilities = detailsPayload.data?.facilities || [];
      const departments = detailsPayload.data?.departments || [];
      const indicators = aggregateIndicators(detailsPayload.data?.indicators || []);
      const categories = detailsPayload.data?.categories || [];
      const monthlyTrend = summaryPayload.data?.monthly_trend || [];
      if (
        monthlyTrend.length === 1
        && (monthlyTrend[0].facility_count === undefined || monthlyTrend[0].facility_count === null)
      ) {
        monthlyTrend[0].facility_count = summary.facility_count || 0;
      }
      setText('metric-feedback', number.format(Number(summary.total_responses) || 0));
      setAverageScore('metric-score', summary.score === null || summary.score === undefined ? null : Number(summary.score));
      // A reporting facility is a distinct NIN with a response in the
      // database. The summary-only endpoint is the authoritative raw count.
      setText('metric-facilities', number.format(Number(reportingSummary.facility_count) || 0));
      const categoryRisks = categoryRiskItems(categories);
      setText('metric-priority', number.format(indicators.filter((item) => Number(item.score) < 3).length + categoryRisks.length));
      const effectiveFilters = detailsPayload.data?.filters || {};
      const selectedFacility = query.get('facility_nin') || effectiveFilters.facility_nin;
      const selectedDepartment = query.get('department_id') || effectiveFilters.department_id;
      currentMonthlyPerformance = detailsPayload.data?.monthly_performance || [];
      currentIndicators = indicators;
      populateIndicatorOptions(currentIndicators, Boolean(selectedFacility && selectedDepartment));
      if (selectedFacility && selectedDepartment) {
        const facilityOption = [...byId('facility-filter').options].find((option) => option.value === String(selectedFacility));
        const departmentOption = [...byId('department-filter').options].find((option) => option.value === String(selectedDepartment));
        const facilityName = facilityOption?.textContent || selectedFacility;
        const departmentName = departmentOption?.textContent || selectedDepartment;
        setText('trend-title', `${departmentName} monthly performance`);
        setText('trend-description', `${facilityName}: response volume and average rating out of 5.`);
        renderDepartmentTrend(currentMonthlyPerformance);
      } else {
        setText('trend-title', 'Feedback trend');
        setText('trend-description', 'Monthly response volume and reporting facilities for the selected scope.');
        renderTrend(monthlyTrend);
      }
      renderSelectedIndicatorTrend();
      renderHighest(indicators);
      renderPriority(indicators, categories);
      renderFacilities(facilities);
      renderDepartments(departments);
      renderAdaptiveIndicators(categories, indicators, Boolean(selectedFacility));
      renderQuestions(indicators);
      setText('data-updated', `Updated ${new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date())}`);
    } catch (error) {
      const alert = byId('analytics-message');
      alert.textContent = error.message || 'Unable to load analytics.';
      alert.hidden = false;
      ['metric-feedback', 'metric-score', 'metric-facilities', 'metric-priority'].forEach((id) => setText(id, '\u2014'));
      renderTrend([]);
      renderHighest([]);
      renderPriority([], []);
      renderFacilities([]);
      renderDepartments([]);
      renderQuestions([]);
      setText('data-updated', 'Data could not be updated');
    } finally {
      button.disabled = false;
    }
  }

  function activateTab(name, focus = false) {
    document.querySelectorAll('[data-tab]').forEach((button) => {
      const selected = button.dataset.tab === name;
      button.setAttribute('aria-selected', String(selected));
      if (selected && focus) button.focus();
    });
    document.querySelectorAll('.tab-panel').forEach((panel) => {
      panel.hidden = panel.id !== `panel-${name}`;
    });
  }

  document.querySelectorAll('[data-tab]').forEach((button, index, buttons) => {
    button.addEventListener('click', () => activateTab(button.dataset.tab));
    button.addEventListener('keydown', (event) => {
      if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
      event.preventDefault();
      const next = event.key === 'ArrowRight' ? (index + 1) % buttons.length : (index - 1 + buttons.length) % buttons.length;
      activateTab(buttons[next].dataset.tab, true);
    });
  });
  document.querySelector('[data-topnav-toggle]')?.addEventListener('click', (event) => {
    const navigation = byId('primary-navigation');
    const open = navigation.classList.toggle('is-open');
    event.currentTarget.setAttribute('aria-expanded', String(open));
  });
  byId('apply-filters').addEventListener('click', load);
  byId('indicator-filter').addEventListener('change', renderSelectedIndicatorTrend);
  ['facility-filter', 'department-filter'].forEach((id) => {
    byId(id).addEventListener('change', () => {
      const indicator = byId('indicator-filter');
      indicator.replaceChildren(new Option('Apply facility and department first', ''));
      indicator.disabled = true;
      byId('indicator-trend').innerHTML = '<div class="empty">Apply the facility and department filters to load indicators.</div>';
    });
  });
  byId('reset-filters').addEventListener('click', () => {
    ['facility-filter', 'department-filter', 'survey-version-filter', 'indicator-filter', 'from-filter', 'to-filter'].forEach((id) => { byId(id).value = ''; });
    load();
  });
  load();
})();
