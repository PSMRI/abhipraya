(function () {
  'use strict';

  const root = document.documentElement;
  const header = document.querySelector('.ab-topbar');
  if (!header) return;

  const navigation = document.querySelector('.ab-nav');
  if (navigation && !navigation.querySelector('a[href="/admin/capa"]')) {
    const link = document.createElement('a');
    link.href = '/admin/capa';
    link.setAttribute('aria-label', 'CAPA');
    link.innerHTML = '<i class="bi bi-clipboard2-pulse" aria-hidden="true"></i><span>CAPA</span>';
    navigation.appendChild(link);
  }

  function updateThemeIcon(button) {
    const dark = root.dataset.theme === 'dark';
    const icon = button.querySelector('i');
    if (icon) icon.className = 'bi bi-circle-half';
    button.setAttribute('aria-label', dark ? 'Switch to light theme' : 'Switch to dark theme');
    button.title = button.getAttribute('aria-label');
  }

  let themeButton = header.querySelector('[data-theme-toggle]');
  if (!themeButton) {
    themeButton = document.createElement('button');
    themeButton.type = 'button';
    themeButton.className = 'ab-theme-button ab-icon-button';
    themeButton.dataset.themeToggle = '';
    themeButton.innerHTML = '<i class="bi bi-circle-half" aria-hidden="true"></i><span class="sr-only">Switch colour theme</span>';
    header.insertBefore(themeButton, header.querySelector('.ab-profile-menu'));
  }
  try { root.dataset.theme = localStorage.getItem('abhipraya_theme') || root.dataset.theme || 'light'; } catch (_) { /* Storage is optional. */ }
  updateThemeIcon(themeButton);
  themeButton.addEventListener('click', () => {
    root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem('abhipraya_theme', root.dataset.theme); } catch (_) { /* Storage is optional. */ }
    updateThemeIcon(themeButton);
  });

  if (header.querySelector('.ab-accessibility-menu')) return;
  const menu = document.createElement('details');
  menu.className = 'ab-accessibility-menu';
  menu.innerHTML = '<summary title="Accessibility options"><i class="bi bi-universal-access" aria-hidden="true"></i><span class="sr-only">Open accessibility options</span></summary><div class="ab-accessibility-dropdown" aria-label="Accessibility options"><p>Text size</p><div class="ab-accessibility-actions"><button type="button" data-size="decrease" aria-label="Decrease text size">A−</button><button type="button" data-size="reset" aria-label="Reset text size">A</button><button type="button" data-size="increase" aria-label="Increase text size">A+</button></div><button type="button" data-read-page><i class="bi bi-volume-up" aria-hidden="true"></i>Read page aloud</button><button type="button" data-stop-reading disabled><i class="bi bi-volume-mute" aria-hidden="true"></i>Stop reading</button><p class="ab-accessibility-status" aria-live="polite">Text size: normal</p></div>';
  header.insertBefore(menu, themeButton);
  const status = menu.querySelector('.ab-accessibility-status');
  const read = menu.querySelector('[data-read-page]');
  const stop = menu.querySelector('[data-stop-reading]');
  let scale = 1;
  try { scale = Number(localStorage.getItem('abhipraya_text_scale') || 1); } catch (_) { /* Storage is optional. */ }
  function applyScale() {
    scale = Math.max(0.85, Math.min(1.3, Number(scale.toFixed(2))));
    root.style.setProperty('--ab-user-text-scale', String(scale));
    status.textContent = 'Text size: ' + (scale === 1 ? 'normal' : Math.round(scale * 100) + '%');
    try { localStorage.setItem('abhipraya_text_scale', String(scale)); } catch (_) { /* Storage is optional. */ }
  }
  applyScale();
  menu.querySelectorAll('[data-size]').forEach((button) => button.addEventListener('click', () => {
    const action = button.dataset.size;
    scale = action === 'increase' ? scale + 0.1 : action === 'decrease' ? scale - 0.1 : 1;
    applyScale();
  }));
  read.addEventListener('click', () => {
    if (!('speechSynthesis' in window)) { status.textContent = 'Screen reader is not available in this browser.'; return; }
    window.speechSynthesis.cancel();
    const content = document.querySelector('main')?.innerText || document.body.innerText;
    const speech = new SpeechSynthesisUtterance(content);
    speech.onend = () => { stop.disabled = true; status.textContent = 'Reading stopped.'; };
    window.speechSynthesis.speak(speech);
    stop.disabled = false;
    status.textContent = 'Reading page aloud.';
  });
  stop.addEventListener('click', () => { window.speechSynthesis?.cancel(); stop.disabled = true; status.textContent = 'Reading stopped.'; });
}());
