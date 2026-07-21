(function (window) {
  'use strict';

  function render(tableBody, rows, columns, options = {}) {
    tableBody.innerHTML = '';

    if (!Array.isArray(rows) || rows.length === 0) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = columns.length;
      td.innerHTML = options.emptyHtml || '<div class="empty-state empty-state-sm">No records found.</div>';
      tr.appendChild(td);
      tableBody.appendChild(tr);
      return;
    }

    rows.forEach((row) => {
      const tr = document.createElement('tr');

      columns.forEach((column) => {
        const td = document.createElement('td');
        const value = typeof column.value === 'function'
          ? column.value(row)
          : row[column.key];

        if (column.render) {
          const rendered = column.render(value, row);
          if (rendered instanceof Node) td.appendChild(rendered);
          else td.innerHTML = String(rendered ?? '');
        } else {
          td.textContent = value ?? '';
        }

        if (column.className) td.className = column.className;
        tr.appendChild(td);
      });

      tableBody.appendChild(tr);
    });
  }

  function bindSort(table, callback) {
    table.querySelectorAll('th[data-sort]').forEach((header) => {
      header.tabIndex = 0;
      header.addEventListener('click', () => {
        const current = header.dataset.order || 'asc';
        const next = current === 'asc' ? 'desc' : 'asc';

        table.querySelectorAll('th[data-sort]').forEach((th) => {
          th.removeAttribute('data-order');
          th.setAttribute('aria-sort', 'none');
        });

        header.dataset.order = next;
        header.setAttribute('aria-sort', next === 'asc' ? 'ascending' : 'descending');
        callback(header.dataset.sort, next);
      });
    });
  }

  window.AbhiprayaTables = { render, bindSort };
})(window);
