(() => {
  'use strict';

  if (window.AbhiprayaAccessibility) return;

  const root = document.documentElement;
  const storageKey = 'abhipraya_accessibility';
  const state = {
    textSize: 'normal',
    highContrast: false,
    reducedMotion: window.matchMedia?.('(prefers-reduced-motion: reduce)').matches || false
  };

  try {
    Object.assign(state, JSON.parse(localStorage.getItem(storageKey) || '{}'));
  } catch (_) {
    // Accessibility controls continue without browser storage.
  }

  function save() {
    try {
      localStorage.setItem(storageKey, JSON.stringify(state));
    } catch (_) {
      // Browser storage is optional.
    }
  }

  function applyState() {
    root.classList.toggle('ab-text-large', state.textSize === 'large');
    root.classList.toggle('ab-text-larger', state.textSize === 'larger');
    root.classList.toggle('ab-high-contrast', Boolean(state.highContrast));
    root.classList.toggle('ab-reduce-motion', Boolean(state.reducedMotion));
  }

  function createMenu() {
    document.querySelector('.ab-accessibility-menu')?.remove();
    const menu = document.createElement('details');
    menu.className = 'ab-wcag-menu';
    menu.innerHTML = `
      <summary aria-label="Open accessibility options" title="Accessibility options">
        <i class="bi bi-universal-access" aria-hidden="true"></i>
      </summary>
      <div class="ab-wcag-panel">
        <h2>Accessibility</h2>
        <p>W3C/WCAG-friendly display and reading options.</p>
        <div class="ab-wcag-group">
          <span class="ab-wcag-label">Text size</span>
          <div class="ab-wcag-buttons">
            <button type="button" data-wcag-text="normal" aria-label="Use normal text size">A</button>
            <button type="button" data-wcag-text="large" aria-label="Use large text size">A+</button>
            <button type="button" data-wcag-text="larger" aria-label="Use largest text size">A++</button>
          </div>
        </div>
        <div class="ab-wcag-group">
          <button class="ab-wcag-toggle" type="button" data-wcag-contrast aria-pressed="false">
            <i class="bi bi-circle-half" aria-hidden="true"></i>
            High contrast
          </button>
          <button class="ab-wcag-toggle" type="button" data-wcag-motion aria-pressed="false">
            <i class="bi bi-pause-circle" aria-hidden="true"></i>
            Reduce motion
          </button>
          <button class="ab-wcag-toggle" type="button" data-wcag-read aria-pressed="false">
            <i class="bi bi-volume-up" aria-hidden="true"></i>
            Read page aloud
          </button>
        </div>
        <p class="ab-wcag-status" role="status" aria-live="polite">Accessibility options ready.</p>
      </div>`;

    const topHeaderActions = document.querySelector('.header-actions, .feedback-header-actions');
    const compactHeader = document.querySelector('.ab-topbar');
    if (topHeaderActions) {
      topHeaderActions.prepend(menu);
    } else if (compactHeader) {
      const anchor = compactHeader.querySelector('.ab-topbar-spacer');
      anchor?.insertAdjacentElement('afterend', menu);
      if (!anchor) compactHeader.prepend(menu);
    } else {
      return null;
    }
    return menu;
  }

  function setStatus(menu, message) {
    const status = menu.querySelector('.ab-wcag-status');
    if (status) status.textContent = message;
  }

  const menu = createMenu();
  applyState();
  if (!menu) {
    window.AbhiprayaAccessibility = { applyState };
    return;
  }

  const contrastButton = menu.querySelector('[data-wcag-contrast]');
  const motionButton = menu.querySelector('[data-wcag-motion]');
  const readButton = menu.querySelector('[data-wcag-read]');

  function updateButtons() {
    contrastButton.setAttribute('aria-pressed', String(Boolean(state.highContrast)));
    motionButton.setAttribute('aria-pressed', String(Boolean(state.reducedMotion)));
    menu.querySelectorAll('[data-wcag-text]').forEach((button) => {
      button.setAttribute('aria-pressed', String(button.dataset.wcagText === state.textSize));
    });
  }

  menu.querySelectorAll('[data-wcag-text]').forEach((button) => {
    button.addEventListener('click', () => {
      state.textSize = button.dataset.wcagText;
      applyState();
      updateButtons();
      save();
      setStatus(menu, `Text size changed to ${state.textSize}.`);
    });
  });

  contrastButton.addEventListener('click', () => {
    state.highContrast = !state.highContrast;
    applyState();
    updateButtons();
    save();
    setStatus(menu, `High contrast ${state.highContrast ? 'enabled' : 'disabled'}.`);
  });

  motionButton.addEventListener('click', () => {
    state.reducedMotion = !state.reducedMotion;
    applyState();
    updateButtons();
    save();
    setStatus(menu, `Reduced motion ${state.reducedMotion ? 'enabled' : 'disabled'}.`);
  });

  function stopReading(message = 'Reading stopped.') {
    window.speechSynthesis?.cancel();
    readButton.setAttribute('aria-pressed', 'false');
    readButton.innerHTML = '<i class="bi bi-volume-up" aria-hidden="true"></i>Read page aloud';
    setStatus(menu, message);
  }

  readButton.addEventListener('click', () => {
    if (readButton.getAttribute('aria-pressed') === 'true') {
      stopReading();
      return;
    }
    if (!('speechSynthesis' in window)) {
      setStatus(menu, 'Read aloud is not supported by this browser.');
      return;
    }
    const main = document.querySelector('main');
    const content = (main?.innerText || '').replace(/\s+/g, ' ').trim();
    if (!content) {
      setStatus(menu, 'No readable page content was found.');
      return;
    }
    window.speechSynthesis.cancel();
    const speech = new SpeechSynthesisUtterance(content);
    speech.lang = root.lang || 'en';
    speech.onend = () => stopReading('Reading completed.');
    speech.onerror = () => stopReading('Reading stopped.');
    readButton.setAttribute('aria-pressed', 'true');
    readButton.innerHTML = '<i class="bi bi-stop-circle" aria-hidden="true"></i>Stop reading';
    setStatus(menu, 'Reading page aloud.');
    window.speechSynthesis.speak(speech);
  });

  menu.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      menu.open = false;
      menu.querySelector('summary')?.focus();
    }
  });

  document.addEventListener('click', (event) => {
    if (menu.open && !menu.contains(event.target)) menu.open = false;
  });

  updateButtons();
  window.AbhiprayaAccessibility = { applyState };
})();
