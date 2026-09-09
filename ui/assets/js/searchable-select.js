(() => {
  'use strict';

  const instances = new WeakMap();
  let sequence = 0;

  function optionLabel(option) {
    return String(option?.textContent || '').trim();
  }

  function optionSearchText(option) {
    return `${optionLabel(option)} ${String(option?.dataset?.searchText || '')}`.trim();
  }

  function initialize(select) {
    if (!(select instanceof HTMLSelectElement) || instances.has(select)) return;

    const originalRequired = select.required;
    const originalId = select.id;
    const wrapper = document.createElement('div');
    wrapper.className = 'searchable-select';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);

    select.classList.add('searchable-native-select');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    select.required = false;

    const input = document.createElement('input');
    input.type = 'search';
    input.className = 'searchable-select-input';
    input.autocomplete = 'off';
    input.spellcheck = false;
    input.id = `searchable-select-${++sequence}`;
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'searchable-select-toggle';
    toggle.setAttribute('aria-label', 'Show available options');
    toggle.innerHTML = '<span aria-hidden="true">\u25be</span>';

    const list = document.createElement('div');
    list.className = 'searchable-select-list';
    list.id = `${input.id}-list`;
    list.setAttribute('role', 'listbox');
    list.hidden = true;
    input.setAttribute('aria-controls', list.id);

    wrapper.append(input, toggle, list);

    const externalLabel = originalId
      ? document.querySelector(`label[for="${CSS.escape(originalId)}"]`)
      : null;
    if (externalLabel) externalLabel.htmlFor = input.id;

    function selectedOption() {
      return select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
    }

    function placeholderText() {
      const blank = [...select.options].find((option) => option.value === '');
      return optionLabel(blank) || 'Search options';
    }

    function setValidity() {
      if (originalRequired && !select.value) {
        input.setCustomValidity('Select an option from the list.');
      } else {
        input.setCustomValidity('');
      }
    }

    function syncSelection() {
      const selected = selectedOption();
      input.value = selected && selected.value !== '' ? optionLabel(selected) : '';
      input.placeholder = placeholderText();
      setValidity();
    }

    function syncState() {
      input.disabled = select.disabled;
      toggle.disabled = select.disabled;
      input.required = originalRequired && !select.disabled;
      wrapper.classList.toggle('is-disabled', select.disabled);
      if (select.disabled) close();
    }

    function availableButtons() {
      return [...list.querySelectorAll('[role="option"]:not([disabled])')];
    }

    function choose(option) {
      if (!option || option.disabled) return;
      select.value = option.value;
      syncSelection();
      select.dispatchEvent(new Event('input', { bubbles: true }));
      select.dispatchEvent(new Event('change', { bubbles: true }));
      close();
      input.focus();
    }

    function render(query = '') {
      const normalized = String(query).trim().toLocaleLowerCase();
      list.replaceChildren();
      const matches = [...select.options].filter((option) => {
        if (option.hidden) return false;
        return !normalized || optionSearchText(option).toLocaleLowerCase().includes(normalized);
      });

      matches.forEach((option) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'searchable-select-option';
        item.setAttribute('role', 'option');
        item.setAttribute('aria-selected', String(option.selected));
        item.textContent = optionLabel(option);
        item.disabled = option.disabled;
        item.addEventListener('mousedown', (event) => event.preventDefault());
        item.addEventListener('click', () => choose(option));
        item.addEventListener('keydown', (event) => {
          const buttons = availableButtons();
          const index = buttons.indexOf(item);
          if (event.key === 'ArrowDown') {
            event.preventDefault();
            (buttons[index + 1] || buttons[0])?.focus();
          } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (index <= 0) input.focus();
            else buttons[index - 1]?.focus();
          } else if (event.key === 'Escape') {
            event.preventDefault();
            close();
            input.focus();
          }
        });
        list.appendChild(item);
      });

      if (!matches.length) {
        const empty = document.createElement('p');
        empty.className = 'searchable-select-empty';
        empty.textContent = 'No matching options';
        list.appendChild(empty);
      }
    }

    function open(showAll = false) {
      if (select.disabled) return;
      render(showAll ? '' : input.value);
      list.hidden = false;
      wrapper.classList.add('is-open');
      input.setAttribute('aria-expanded', 'true');
    }

    function close() {
      list.hidden = true;
      wrapper.classList.remove('is-open');
      input.setAttribute('aria-expanded', 'false');
    }

    input.addEventListener('focus', () => {
      input.select();
      open(true);
    });
    input.addEventListener('input', () => {
      const selected = selectedOption();
      if (!selected || input.value !== optionLabel(selected)) {
        select.value = '';
      }
      setValidity();
      open();
    });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        open();
        availableButtons()[0]?.focus();
      } else if (event.key === 'Enter' && !list.hidden) {
        const first = availableButtons()[0];
        if (first) {
          event.preventDefault();
          first.click();
        }
      } else if (event.key === 'Escape') {
        close();
      }
    });
    input.addEventListener('blur', () => {
      window.setTimeout(() => {
        if (!wrapper.contains(document.activeElement)) close();
        setValidity();
      }, 0);
    });
    toggle.addEventListener('click', () => {
      if (list.hidden) {
        open(true);
        input.focus();
        input.select();
      } else {
        close();
      }
    });
    select.addEventListener('change', syncSelection);
    document.addEventListener('click', (event) => {
      if (!wrapper.contains(event.target)) close();
    });

    const form = select.closest('form');
    form?.addEventListener('submit', () => setValidity(), true);
    form?.addEventListener('reset', () => window.setTimeout(syncSelection, 0));

    const observer = new MutationObserver(() => {
      syncState();
      if (document.activeElement === input && !list.hidden) render(input.value);
      else syncSelection();
    });
    observer.observe(select, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['disabled']
    });

    instances.set(select, { input, list, syncSelection, syncState });
    syncState();
    syncSelection();
  }

  function initializeAll(root = document) {
    root.querySelectorAll('select:not([data-search-disabled])').forEach(initialize);
  }

  window.AbhiprayaSearchableSelect = { initializeAll };
  initializeAll();

  const pageObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (!(node instanceof Element)) return;
        if (node.matches('select:not([data-search-disabled])')) initialize(node);
        initializeAll(node);
      });
    });
  });
  pageObserver.observe(document.body, { childList: true, subtree: true });
})();
