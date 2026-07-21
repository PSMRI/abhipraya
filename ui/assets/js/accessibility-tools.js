(function () {
  'use strict';
  const key = 'abhipraya_text_scale';
  const status = document.querySelector('[data-accessibility-status]');
  const readButton = document.querySelector('[data-read-page]');
  const stopButton = document.querySelector('[data-stop-reading]');
  let scale = Number(localStorage.getItem(key) || 1);

  function clamp(value) { return Math.min(1.3, Math.max(.85, Math.round(value * 100) / 100)); }
  function label() { return scale === 1 ? 'normal' : (scale > 1 ? Math.round((scale - 1) * 100) + '% larger' : Math.round((1 - scale) * 100) + '% smaller'); }
  function applyScale() {
    document.documentElement.style.setProperty('--ab-user-text-scale', String(scale));
    document.documentElement.setAttribute('data-text-scale', scale > 1 ? 'large' : (scale < 1 ? 'small' : 'normal'));
    if (status) status.textContent = 'Text size: ' + label();
    try { localStorage.setItem(key, String(scale)); } catch (_) { /* Storage is optional. */ }
  }
  document.querySelectorAll('[data-text-size]').forEach((button) => button.addEventListener('click', () => {
    const action = button.getAttribute('data-text-size');
    scale = action === 'increase' ? clamp(scale + .1) : (action === 'decrease' ? clamp(scale - .1) : 1);
    applyScale();
  }));
  function stopReading() {
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
    if (readButton) readButton.disabled = false;
    if (stopButton) stopButton.disabled = true;
    if (status) status.textContent = 'Reading stopped. Text size: ' + label();
  }
  readButton?.addEventListener('click', () => {
    if (!('speechSynthesis' in window)) { if (status) status.textContent = 'Read aloud is not supported by this browser.'; return; }
    const main = document.querySelector('main');
    const text = (main?.innerText || document.body.innerText).replace(/\s+/g, ' ').trim();
    window.speechSynthesis.cancel();
    const speech = new SpeechSynthesisUtterance(text);
    speech.onend = stopReading;
    speech.onerror = stopReading;
    window.speechSynthesis.speak(speech);
    readButton.disabled = true;
    if (stopButton) stopButton.disabled = false;
    if (status) status.textContent = 'Reading this page aloud.';
  });
  stopButton?.addEventListener('click', stopReading);
  applyScale();
}());
