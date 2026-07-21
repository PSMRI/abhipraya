(function () {
  'use strict';

  const root = document.getElementById('survey-root');
  const alertBox = document.getElementById('survey-alert');
  const parameters = new URLSearchParams(window.location.search);
  const suppliedNin = parameters.get('nin') || '';
  const suppliedDepartment = parameters.get('department') || parameters.get('dept') || '';
  const reference = parameters.get('ref') || (suppliedDepartment ? `${suppliedNin}_${suppliedDepartment}` : suppliedNin);

  let context = null;
  let questions = [];
  let language = 1;
  let currentIndex = 0;
  const answers = {};

  function apiUrl(path, query) {
    const url = new URL(`/api/v1/public-survey/${path.replace(/\.php$/, '')}`, window.location.origin);
    Object.entries(query || {}).forEach(([key, value]) => url.searchParams.set(key, value));
    return url;
  }

  function showError(message) {
    alertBox.textContent = message;
    alertBox.setAttribute('role', 'alert');
  }

  function clearError() {
    alertBox.textContent = '';
    alertBox.removeAttribute('role');
  }

  function deviceId() {
    const key = 'abhipraya_public_device_id';
    let value = localStorage.getItem(key);
    if (!value) {
      value = window.crypto && crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
      localStorage.setItem(key, value);
    }
    return value;
  }

  async function request(url, options) {
    const response = await fetch(url, options);
    const raw = await response.text();
    let payload;
    try {
      payload = raw ? JSON.parse(raw) : {};
    } catch (_) {
      throw new Error('The server returned an invalid response.');
    }
    if (!response.ok || payload.status !== 'success') {
      throw new Error(payload.message || 'Unable to continue.');
    }
    return payload.data;
  }

  function brand(showFacility) {
    const header = document.createElement('header');
    header.className = 'ab-public-head';

    const logo = document.createElement('img');
    logo.className = 'ab-public-logo';
    logo.src = '/ui/assets/img/abhipraya-logo.png';
    logo.alt = 'Abhipraya';
    header.appendChild(logo);

    if (showFacility) {
      const facility = document.createElement('p');
      facility.className = 'ab-public-facility';
      facility.textContent = context.facility.name;
      header.appendChild(facility);

      if (context.facility.address) {
        const address = document.createElement('p');
        address.className = 'ab-public-address';
        address.textContent = context.facility.address;
        header.appendChild(address);
      }
    }
    return header;
  }

  function questionIcon(question) {
    const image = document.createElement('img');
    image.className = 'ab-survey-icon-image';
    image.alt = '';
    image.setAttribute('aria-hidden', 'true');
    const configuredPath = String(question.icon || '').replace(/^\/+/, '');
    image.src = configuredPath ? `/api/${configuredPath}` : '/ui/assets/img/abhipraya-logo.png';
    image.addEventListener('error', () => {
      image.hidden = true;
    }, { once: true });
    return image;
  }

  function renderLanguageChoice() {
    clearError();
    const view = document.createElement('section');
    view.className = 'ab-language-view';
    view.appendChild(brand(true));

    const actions = document.createElement('div');
    actions.className = 'ab-language-actions';
    [[1, 'English'], [2, '\u0939\u093f\u0928\u094d\u0926\u0940']].forEach(([code, label]) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'ab-language-button';
      button.lang = code === 2 ? 'hi' : 'en';
      button.textContent = label;
      button.addEventListener('click', async () => {
        language = code;
        await loadQuestions();
      });
      actions.appendChild(button);
    });
    view.appendChild(actions);
    root.replaceChildren(view);
  }

  async function loadQuestions() {
    clearError();
    root.textContent = 'Loading questions\u2026';
    try {
      const data = await request(apiUrl('questions.php', { ref: reference, lang: language }));
      questions = data.questions || [];
      currentIndex = 0;
      if (!questions.length) {
        throw new Error('No survey questions are available.');
      }
      renderQuestion();
    } catch (error) {
      showError(error.message || 'Unable to load questions.');
      root.textContent = '';
    }
  }

  function renderQuestion() {
    clearError();
    const question = questions[currentIndex];
    const view = document.createElement('section');
    view.className = 'ab-survey-view';
    view.appendChild(brand(false));
    view.appendChild(questionIcon(question));

    const title = document.createElement('h1');
    title.className = 'ab-survey-question';
    title.textContent = question.ques;

    const options = document.createElement('fieldset');
    options.className = 'ab-survey-options';
    const legend = document.createElement('legend');
    legend.className = 'visually-hidden';
    legend.textContent = question.ques;
    options.appendChild(legend);

    (question.options || []).forEach((option, index) => {
      const label = document.createElement('label');
      label.className = 'ab-survey-option';
      const radio = document.createElement('input');
      radio.type = 'radio';
      radio.name = `q_${question.qn}`;
      radio.value = String(index + 1);
      radio.checked = String(answers[question.qn] || '') === radio.value;
      radio.addEventListener('change', () => { answers[question.qn] = radio.value; });
      const text = document.createElement('span');
      text.textContent = option.text;
      label.append(radio, text);
      options.appendChild(label);
    });

    const navigation = document.createElement('nav');
    navigation.className = 'ab-survey-nav';
    navigation.setAttribute('aria-label', 'Survey navigation');

    const previous = document.createElement('button');
    previous.type = 'button';
    previous.textContent = 'Previous';
    previous.hidden = currentIndex === 0;
    previous.addEventListener('click', () => {
      currentIndex -= 1;
      renderQuestion();
    });

    const step = document.createElement('p');
    step.className = 'ab-survey-step';
    step.textContent = `${currentIndex + 1} / ${questions.length}`;

    const next = document.createElement('button');
    next.type = 'button';
    next.textContent = currentIndex === questions.length - 1 ? 'Submit' : 'Next';
    next.addEventListener('click', async () => {
      if (!answers[question.qn]) {
        showError('Please select an answer before continuing.');
        return;
      }
      if (currentIndex < questions.length - 1) {
        currentIndex += 1;
        renderQuestion();
        return;
      }
      await submitSurvey(next);
    });

    navigation.append(previous, step, next);
    view.append(title, options, navigation);
    root.replaceChildren(view);
  }

  async function submitSurvey(button) {
    clearError();
    button.disabled = true;
    button.textContent = 'Checking location\u2026';
    try {
      const position = await new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
          reject(new Error('Location is unavailable.'));
          return;
        }
        navigator.geolocation.getCurrentPosition(resolve, reject, {
          enableHighAccuracy: true,
          timeout: 15000,
          maximumAge: 0,
        });
      });

      button.textContent = 'Submitting\u2026';
      await request(apiUrl('submit.php'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ref: reference,
          lang: language,
          device_id: deviceId(),
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
          answers,
        }),
      });

      const success = document.createElement('section');
      success.className = 'ab-survey-success';
      const heading = document.createElement('h1');
      heading.textContent = 'Thank you';
      const message = document.createElement('p');
      message.textContent = 'Your anonymous feedback has been submitted successfully.';
      success.append(heading, message);
      root.replaceChildren(success);
    } catch (error) {
      const denied = error && error.code === 1;
      showError(denied ? 'Location permission is required to submit feedback from the facility premises.' : (error.message || 'Unable to submit feedback.'));
      button.disabled = false;
      button.textContent = 'Submit';
    }
  }

  async function start() {
    if (!/^\d{6,20}_\d{1,2}$/.test(reference)) {
      showError('This QR survey link is invalid.');
      root.textContent = '';
      return;
    }
    try {
      context = await request(apiUrl('resolve.php', { ref: reference }));
      renderLanguageChoice();
    } catch (error) {
      showError(error.message || 'This survey is unavailable.');
      root.textContent = '';
    }
  }

  start();
}());
