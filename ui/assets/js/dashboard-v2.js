(function () {
  'use strict';

  const app = document.querySelector('.ab-app');
  const sidebar = document.getElementById('primary-navigation');
  const sidebarToggle = document.querySelector('[data-sidebar-toggle]');

  function setSidebarCollapsed(collapsed) {
    app?.classList.toggle('is-collapsed', collapsed);
    sidebarToggle?.setAttribute('aria-expanded', String(!collapsed));
    sidebarToggle?.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    sidebarToggle?.querySelector('.sr-only')?.replaceChildren(collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    try { localStorage.setItem('abhipraya_sidebar_collapsed', String(collapsed)); } catch (_) { /* Storage is optional. */ }
  }

  async function getJson(url, options) {
    const response = await fetch(url, Object.assign({
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    }, options || {}));
    const raw = await response.text();
    let payload = {};
    try { payload = raw ? JSON.parse(raw) : {}; } catch (_) { throw new Error('The server returned an invalid response.'); }
    if (!response.ok || payload.status !== 'success') throw new Error(payload.message || 'Request failed.');
    return payload;
  }

  async function loadOverview() {
    try {
      const [payload, detailPayload] = await Promise.all([
        getJson('/api/v1/analytics/summary?summary_only=1&trend_months=6'),
        getJson('/api/v1/analytics/summary')
      ]);
      const summary = payload.data?.summary || {};
      document.getElementById('total-responses').textContent = summary.total_responses ?? 0;
      document.getElementById('facility-count').textContent = summary.facility_count ?? 0;
      const metricGrid = document.querySelector('.ab-metric-grid');
      if (metricGrid && !document.getElementById('facilities-without-feedback')) {
        metricGrid.classList.add('has-real-gap');
        const gapCard = document.createElement('article');
        gapCard.id = 'facilities-without-feedback';
        gapCard.className = 'ab-metric-card';
        gapCard.innerHTML = '<span>Facilities without recent feedback</span><strong>—</strong><small>Facilities requiring outreach</small>';
        metricGrid.appendChild(gapCard);
      }
      let configuredFacilities = Number(summary.configured_facility_count);
      if (!Number.isFinite(configuredFacilities) || configuredFacilities <= 0) {
        try {
          const masterResponse = await fetch('/api/masters/facilityCodes.json', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
          const masterFacilities = await masterResponse.json();
          configuredFacilities = Array.isArray(masterFacilities) ? masterFacilities.length : 0;
        } catch (_) { configuredFacilities = 0; }
      }
      const reportingFacilities = Number(summary.facility_count || 0);
      const gapValue = Number.isFinite(configuredFacilities) ? Math.max(0, configuredFacilities - reportingFacilities) : 0;
      document.querySelector('#facilities-without-feedback strong')?.replaceChildren(String(gapValue));
      const indicators = (detailPayload.data?.indicators || []).filter((item) => Number.isFinite(Number(item.score)));
      const ranked = [...indicators].sort((a, b) => Number(b.score) - Number(a.score));
      const cards = document.querySelectorAll('.ab-overview-card strong');
      if (cards[0]) cards[0].textContent = summary.score == null ? '—' : `${summary.score} / 5`;
      if (cards[1]) cards[1].textContent = ranked[0] ? ranked[0].indicator_name : '—';
      if (cards[2]) cards[2].textContent = ranked.at(-1) ? ranked.at(-1).indicator_name : '—';
      if (cards[3]) cards[3].textContent = summary.total_responses ?? 0;
      const overviewGrid = document.querySelector('.ab-overview-grid');
      if (overviewGrid && !document.getElementById('today-responses-card')) {
        const todayCard = document.createElement('article');
        todayCard.id = 'today-responses-card';
        todayCard.className = 'ab-overview-card status-blue';
        todayCard.innerHTML = '<span>Today\'s responses</span><strong>—</strong><small>Feedback received today</small>';
        overviewGrid.appendChild(todayCard);
      }
      const now = new Date();
      const iso = now.toISOString().slice(0, 10);
      try {
      const todayPayload = await getJson(`/api/v1/analytics/summary?summary_only=1&from=${iso}&to=${iso}`);
        const todayValue = todayPayload.data?.summary?.total_responses ?? 0;
        document.querySelector('#today-responses-card strong')?.replaceChildren(String(todayValue));
        const monthStart = `${iso.slice(0, 8)}01`;
        const monthPayload = await getJson(`/api/v1/analytics/summary?summary_only=1&from=${monthStart}&to=${iso}`);
        if (cards[3]) cards[3].textContent = monthPayload.data?.summary?.total_responses ?? 0;
      } catch (_) { /* The main overview remains usable if the optional daily count fails. */ }
      const labels = document.querySelectorAll('.ab-overview-card span');
      if (labels[1] && ranked[0]) labels[1].textContent = `Highest-rated: ${ranked[0].indicator_name}`;
      if (labels[2] && ranked.at(-1)) labels[2].textContent = `Lowest-rated: ${ranked.at(-1).indicator_name}`;
      const notes = document.querySelectorAll('.ab-overview-card small');
      if (notes[1] && ranked[0]) notes[1].textContent = `Average rating: ${ranked[0].score} / 5`;
      if (notes[2] && ranked.at(-1)) notes[2].textContent = `Average rating: ${ranked.at(-1).score} / 5`;
      const analysisGrid = document.querySelector('.ab-analysis-grid');
      if (analysisGrid && ranked.length && !document.getElementById('indicator-high-low-summary')) {
        const summaryCard = document.createElement('section');
        summaryCard.id = 'indicator-high-low-summary';
        summaryCard.className = 'ab-analysis-card ab-indicator-summary';
        summaryCard.innerHTML = `<header><h3>Top-performing indicators</h3><span>Current period</span></header>${ranked.slice(0, 3).map((item, index) => `<p class="highlight-good"><span>#${index + 1}</span><b>${item.indicator_name}<small>★★★★★ ${Number(item.score).toFixed(1)}/5</small></b></p>`).join('')}`;
        analysisGrid.appendChild(summaryCard);
      }
      const healthHeading = document.querySelector('.ab-analysis-card:first-child h3');
      if (healthHeading) healthHeading.textContent = 'Low-performing indicators';
      const healthCard = document.querySelector('.ab-analysis-card:first-child');
      if (healthCard) {
        healthCard.querySelectorAll('p').forEach((row) => row.remove());
        [...indicators].sort((a, b) => Number(a.score) - Number(b.score)).slice(0, 3).forEach((match) => {
          const score = Number(match.score);
          const status = score >= 4 ? 'good' : (score >= 3 ? 'watch' : 'priority');
          const row = document.createElement('p');
          row.className = `ab-health-row ${status}`;
          const stars = Array.from({ length: 5 }, (_, index) => index + 1 <= Math.round(score) ? '★' : '☆').join('');
          row.innerHTML = `<span class="ab-health-icon" aria-hidden="true">${status === 'good' ? '✓' : (status === 'watch' ? '!' : '×')}</span><span class="ab-health-name">${match.indicator_name}</span><b class="ab-health-rating" aria-label="Rating ${score.toFixed(1)} out of 5">${stars}<small>${score.toFixed(1)}/5</small></b>`;
          healthCard.appendChild(row);
        });
      }
      renderMonthlyTrend(payload.data?.monthly_trend || []);
    } catch (error) {
      document.getElementById('total-responses').textContent = '—';
      document.getElementById('facility-count').textContent = '—';
      renderMonthlyTrend([]);
      window.AbhiprayaFeedback?.error('Feedback overview could not be loaded.');
    }
  }

  function renderMonthlyTrend(items) {
    const trend = document.getElementById('monthly-response-trend');
    if (!trend) return;
    trend.replaceChildren();
    trend.setAttribute('aria-busy', 'false');
    if (!items.length) {
      const empty = document.createElement('p');
      empty.className = 'muted';
      empty.textContent = 'No feedback responses are available for the recent months.';
      trend.appendChild(empty);
      return;
    }

    const svgNamespace = 'http://www.w3.org/2000/svg';
    const createSvg = (name, attributes) => {
      const node = document.createElementNS(svgNamespace, name);
      Object.entries(attributes || {}).forEach(([key, value]) => node.setAttribute(key, String(value)));
      return node;
    };
    const maximum = Math.max(...items.map((item) => Number(item.response_count || 0)), 1);
    const width = 720;
    const height = 220;
    const plot = { left: 46, right: 18, top: 28, bottom: 42 };
    const plotWidth = width - plot.left - plot.right;
    const plotHeight = height - plot.top - plot.bottom;
    const points = items.map((item, index) => {
      const month = String(item.month_key || '');
      const date = /^\d{4}-\d{2}$/.test(month) ? new Date(month + '-01T00:00:00') : null;
      const label = date ? date.toLocaleDateString(undefined, { month: 'short', year: '2-digit' }) : month;
      const count = Number(item.response_count || 0);
      return {
        label,
        count,
        x: plot.left + (items.length === 1 ? plotWidth / 2 : (index * plotWidth) / (items.length - 1)),
        y: plot.top + plotHeight - (count / maximum) * plotHeight
      };
    });

    const svg = createSvg('svg', {
      viewBox: `0 0 ${width} ${height}`,
      role: 'img',
      'aria-label': 'Monthly response trend: ' + points.map((point) => `${point.label}, ${point.count} responses`).join('; ')
    });
    [0, 0.5, 1].forEach((fraction) => {
      const y = plot.top + plotHeight - fraction * plotHeight;
      svg.appendChild(createSvg('line', { x1: plot.left, x2: width - plot.right, y1: y, y2: y, class: 'ab-trend-grid-line' }));
      const axis = createSvg('text', { x: plot.left - 8, y: y + 4, class: 'ab-trend-axis-label' });
      axis.textContent = String(Math.round(maximum * fraction));
      svg.appendChild(axis);
    });
    const polylinePoints = points.map((point) => `${point.x},${point.y}`).join(' ');
    const area = createSvg('path', { d: `M ${points[0].x} ${plot.top + plotHeight} L ${polylinePoints.replaceAll(' ', ' L ')} L ${points[points.length - 1].x} ${plot.top + plotHeight} Z`, class: 'ab-trend-area' });
    svg.appendChild(area);
    svg.appendChild(createSvg('polyline', { points: polylinePoints, class: 'ab-trend-line' }));
    points.forEach((point) => {
      const count = createSvg('text', { x: point.x, y: Math.max(16, point.y - 11), class: 'ab-trend-count' });
      count.textContent = String(point.count);
      const marker = createSvg('circle', { cx: point.x, cy: point.y, r: 5, class: 'ab-trend-point' });
      const label = createSvg('text', { x: point.x, y: height - 15, class: 'ab-trend-label' });
      label.textContent = point.label;
      svg.append(count, marker, label);
    });
    trend.appendChild(svg);
  }

  async function loadUser() {
    try {
      const payload = await getJson('/api/v1/auth/me');
      const user = payload.data?.user;
      if (!user || ![1, 2, 3].includes(Number(user.role_id))) throw new Error('Unauthorized');
      const roleLabel = String(user.role_name || 'Administrator');
      const username = String(user.u_name || '');
      const displayName = user.full_name || (/^\d+$/.test(username) ? '' : username) || roleLabel;
      document.querySelectorAll('.ab-user, [data-profile-name]').forEach((node) => { node.textContent = displayName; });
      document.getElementById('page-title').textContent = 'Welcome back, ' + displayName;
    } catch (error) {
      /* Any failed session/user lookup means the admin context is unusable. */
      window.location.assign('/admin/login?reason=session-expired');
    }
  }

  sidebarToggle?.addEventListener('click', () => {
    if (window.matchMedia('(max-width: 880px)').matches) {
      const open = !sidebar.classList.contains('is-open');
      sidebar.classList.toggle('is-open', open);
      sidebarToggle.setAttribute('aria-expanded', String(open));
      return;
    }
    setSidebarCollapsed(!app?.classList.contains('is-collapsed'));
  });

  document.getElementById('logout-button')?.addEventListener('click', async () => {
    try { await fetch('/api/v1/auth/logout', { method: 'POST', credentials: 'same-origin' }); }
    finally { window.location.assign('/admin/login'); }
  });

  try {
    setSidebarCollapsed(localStorage.getItem('abhipraya_sidebar_collapsed') === 'true');
  } catch (_) { setSidebarCollapsed(false); }

  loadUser();
  loadOverview();
}());
