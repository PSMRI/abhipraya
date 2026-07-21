(function () {
  'use strict';
  const root = document.getElementById('survey-root');
  const alertBox = document.getElementById('survey-alert');
  const reference = new URLSearchParams(location.search).get('ref') || '';
  let context = null;
  let language = 1;
  function apiUrl(path, params) { const url = new URL('/api/v1/public-survey/' + path.replace(/\.php$/, ''), location.origin); Object.entries(params || {}).forEach(([key, value]) => url.searchParams.set(key, value)); return url; }
  function showError(message) { alertBox.textContent = message; alertBox.setAttribute('role', 'alert'); }
  function deviceId() { const key = 'abhipraya_public_device_id'; let value = localStorage.getItem(key); if (!value) { value = crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + '-' + Math.random(); localStorage.setItem(key, value); } return value; }
  async function request(url, options) { const response = await fetch(url, options); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status !== 'success') throw new Error(payload.message || 'Unable to continue.'); return payload.data; }
  async function loadQuestions() { const data = await request(apiUrl('questions.php', { ref: reference, lang: language })); renderQuestions(data.questions); }
  function renderQuestions(questions) {
    const form = document.createElement('form'); form.id = 'survey-form';
    questions.forEach((question) => { const fieldset = document.createElement('fieldset'); fieldset.className = 'ab-survey-question'; const legend = document.createElement('legend'); legend.textContent = question.qn + '. ' + question.ques; fieldset.appendChild(legend); const options = document.createElement('div'); options.className = 'ab-survey-options'; (question.options || []).forEach((option, index) => { const label = document.createElement('label'); label.className = 'ab-survey-option'; const radio = document.createElement('input'); radio.type = 'radio'; radio.name = 'q_' + question.qn; radio.value = String(index + 1); radio.required = true; const text = document.createElement('span'); text.textContent = option.text; label.append(radio, text); options.appendChild(label); }); fieldset.appendChild(options); form.appendChild(fieldset); });
    const submit = document.createElement('button'); submit.type = 'submit'; submit.className = 'ab-primary ab-survey-submit'; submit.textContent = 'Submit feedback'; form.appendChild(submit); form.addEventListener('submit', submitSurvey); root.replaceChildren(form);
  }
  async function submitSurvey(event) {
    event.preventDefault(); alertBox.textContent = ''; alertBox.removeAttribute('role'); const form = event.currentTarget; const submit = form.querySelector('[type="submit"]'); const answers = {}; new FormData(form).forEach((value, key) => { if (key.startsWith('q_')) answers[key.slice(2)] = value; }); submit.disabled = true; submit.textContent = 'Checking location…';
    try { const position = await new Promise((resolve, reject) => navigator.geolocation ? navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }) : reject(new Error('Location is unavailable.'))); submit.textContent = 'Submitting feedback…'; const data = await request(apiUrl('submit.php'), { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ ref: reference, lang: language, device_id: deviceId(), latitude: position.coords.latitude, longitude: position.coords.longitude, answers }) }); root.innerHTML = '<section class="ab-survey-success"><h1>Thank you</h1><p>Your feedback has been submitted successfully.</p></section>'; }
    catch (error) { showError(error.code === 1 ? 'Location permission is required to submit feedback from the facility premises.' : (error.message || 'Unable to submit feedback.')); submit.disabled = false; submit.textContent = 'Submit feedback'; }
  }
  async function start() {
    if (!reference) { showError('This QR survey link is invalid.'); root.textContent = ''; return; }
    try { context = await request(apiUrl('resolve.php', { ref: reference })); root.innerHTML = '<section class="ab-survey-context"><h1></h1><p></p></section><div class="ab-survey-language"><label for="survey-language">Language</label><select id="survey-language"><option value="1">English</option><option value="2">हिन्दी</option></select></div><div class="ab-survey-loading">Loading questions…</div>'; root.querySelector('h1').textContent = context.department.name; root.querySelector('p').textContent = context.facility.name; root.querySelector('#survey-language').addEventListener('change', async (event) => { language = Number(event.target.value); await loadQuestions(); }); await loadQuestions(); }
    catch (error) { showError(error.message || 'This survey is unavailable.'); root.textContent = ''; }
  }
  start();
}());
