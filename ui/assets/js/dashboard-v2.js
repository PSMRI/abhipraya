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
      const payload = await getJson('/api/v1/analytics/summary?summary_only=1&trend_months=6');
      const summary = payload.data?.summary || {};
      document.getElementById('total-responses').textContent = summary.total_responses ?? 0;
      document.getElementById('facility-count').textContent = summary.facility_count ?? 0;
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
      if (/unauthorized/i.test(error.message)) { window.location.assign('/admin/login?reason=session-expired'); return; }
      window.AbhiprayaFeedback?.error('Unable to load account information. Please refresh the page.');
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
