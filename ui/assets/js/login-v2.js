(function () {
  'use strict';
  const form = document.getElementById('login-form');
  const message = document.getElementById('login-message');
  const root = document.documentElement;
  const usernameField = document.getElementById('username');
  const rememberField = document.getElementById('remember-username');
  const captchaQuestion = document.getElementById('captcha-question');
  const captchaAnswer = document.getElementById('captcha-answer');
  async function readApiResponse(response) {
    const raw = await response.text();
    try {
      return raw ? JSON.parse(raw) : {};
    } catch (_) {
      throw new Error('The server returned an invalid response. Please refresh the page and try again.');
    }
  }
  async function loadCaptcha() {
    captchaQuestion.textContent = 'Loading verification question…';
    captchaAnswer.value = '';
    try {
      const response = await fetch('/api/v1/auth/captcha', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const payload = await readApiResponse(response);
      if (!response.ok || payload.status !== 'success' || !payload.data?.question) throw new Error('Verification is unavailable.');
      captchaQuestion.textContent = `What is ${payload.data.question}`;
    } catch (error) {
      captchaQuestion.textContent = 'Verification is temporarily unavailable. Refresh the page and try again.';
      captchaAnswer.disabled = true;
      message.textContent = error.message;
      message.setAttribute('role', 'alert');
    }
  }
  try { const remembered = localStorage.getItem('abhipraya_remembered_username'); if (remembered) { usernameField.value = remembered; rememberField.checked = true; } } catch (_) { /* Local storage is optional. */ }
  loadCaptcha();
  document.getElementById('captcha-refresh')?.addEventListener('click', loadCaptcha);
  document.querySelector('[data-theme-toggle]')?.addEventListener('click', function () {
    root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
  });
  form?.addEventListener('submit', async function (event) {
    event.preventDefault();
    const username = form.elements.username.value.trim();
    const password = form.elements.password.value;
    const captcha = captchaAnswer.value.trim();
    const submit = form.querySelector('[type="submit"]');
    if (!username || !password) { message.textContent = 'Enter both your username and password.'; message.setAttribute('role', 'alert'); return; }
    if (!captcha || captchaAnswer.disabled) { message.textContent = 'Enter the verification answer before signing in.'; message.setAttribute('role', 'alert'); captchaAnswer.focus(); return; }
    try { if (rememberField.checked) { localStorage.setItem('abhipraya_remembered_username', username); } else { localStorage.removeItem('abhipraya_remembered_username'); } } catch (_) { /* Local storage is optional. */ }
    submit.disabled = true; submit.textContent = 'Signing in…'; message.removeAttribute('role'); message.textContent = '';
    try {
      const response = await fetch('/api/v1/auth/login', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ username, password, captcha }) });
      const payload = await readApiResponse(response);
      if (!response.ok || payload.status !== 'success') throw new Error(payload.message || 'Invalid username or password.');
      const roleId = Number(payload.data?.user?.role_id || 0);
      if (![1, 2, 3].includes(roleId)) throw new Error('Your account does not have administrator access.');
      window.location.assign('/admin/dashboard');
    } catch (error) {
      message.textContent = error.message || 'Unable to sign in. Please try again.'; message.setAttribute('role', 'alert');
      loadCaptcha();
    } finally { submit.disabled = false; submit.textContent = 'Sign in'; }
  });
}());
