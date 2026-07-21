(function (window, document) {
  'use strict';

  const instances = new Map();

  function palette() {
    const styles = getComputedStyle(document.documentElement);
    return [
      styles.getPropertyValue('--color-primary').trim(),
      styles.getPropertyValue('--color-accent').trim(),
      styles.getPropertyValue('--color-success').trim(),
      styles.getPropertyValue('--color-warning').trim(),
      styles.getPropertyValue('--color-danger').trim()
    ];
  }

  function create(canvas, config) {
    if (!window.Chart) {
      throw new Error('Chart.js is not loaded.');
    }

    if (instances.has(canvas)) {
      instances.get(canvas).destroy();
    }

    const chart = new window.Chart(canvas, {
      ...config,
      data: {
        ...config.data,
        datasets: (config.data?.datasets || []).map((dataset, index) => ({
          borderColor: dataset.borderColor || palette()[index % palette().length],
          backgroundColor: dataset.backgroundColor || palette()[index % palette().length],
          ...dataset
        }))
      }
    });

    instances.set(canvas, chart);
    return chart;
  }

  document.addEventListener('abhipraya:themechange', () => {
    instances.forEach((chart) => chart.update());
  });

  window.AbhiprayaCharts = { create, palette };
})(window, document);
