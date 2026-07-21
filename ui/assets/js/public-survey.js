(function (window, document) {
  'use strict';

  const state = {
    token: null,
    context: null,
    definition: null,
    language: 'en',
    answers: {},
    visibleQuestions: [],
    currentIndex: 0,
    location: null,
    submitting: false
  };

  function tokenFromUrl() {
    return (
      new URLSearchParams(location.search).get('token') ||
      location.pathname.split('/').filter(Boolean).pop()
    );
  }

  async function resolve() {
    state.token = tokenFromUrl();

    if (!state.token) {
      throw new Error('Survey token is missing.');
    }

    const payload = await window.AbhiprayaApi.get(
      '/api/public/v1/survey/resolve',
      { token: state.token }
    );

    state.context = payload.data;
    return payload.data;
  }

  async function loadSurvey(language = state.language) {
    state.language = language;

    const payload = await window.AbhiprayaApi.get(
      '/api/public/v1/survey',
      { token: state.token, lang: language }
    );

    state.context = payload.data?.context || state.context;
    state.definition = payload.data?.configuration || payload.data;
    state.visibleQuestions = payload.data?.questions || [];

    const saved = window.AbhiprayaSurveyStorage?.load();
    state.answers = saved?.answers || {};
    state.currentIndex = Number(saved?.current_index || 0);

    renderCurrent();
    updateProgress();
    updateNavigation();

    return payload.data;
  }

  function renderCurrent() {
    const container = document.getElementById('public-survey-content');
    if (!container) return;

    const question = state.visibleQuestions[state.currentIndex];
    if (!question) return;

    container.replaceChildren(
      window.AbhiprayaSurveyRenderer.renderQuestion(
        question,
        state.language,
        state.answers
      )
    );

    container.querySelectorAll('input, select, textarea').forEach((field) => {
      field.addEventListener('change', saveCurrentAnswer);
      field.addEventListener('input', saveCurrentAnswer);
    });
  }

  function saveCurrentAnswer() {
    const container = document.getElementById('public-survey-content');
    const question = state.visibleQuestions[state.currentIndex];

    if (!container || !question) return;

    state.answers[question.question_id] =
      window.AbhiprayaSurveyRenderer.readAnswer(container, question);

    persist();
  }

  function persist() {
    window.AbhiprayaSurveyStorage?.save({
      token: state.token,
      language: state.language,
      answers: state.answers,
      current_index: state.currentIndex,
      submission_id: null
    });
  }

  function validateCurrent() {
    saveCurrentAnswer();

    const question = state.visibleQuestions[state.currentIndex];
    const errors = window.AbhiprayaSurveyValidator.validateAnswer(
      question,
      state.answers[question.question_id]
    );

    if (errors.length) {
      window.AbhiprayaNotifications?.alert(
        '#public-survey-alert',
        errors[0],
        'danger'
      );
      return false;
    }

    return true;
  }

  function next() {
    if (!validateCurrent()) return;

    if (state.currentIndex < state.visibleQuestions.length - 1) {
      state.currentIndex += 1;
      persist();
      renderCurrent();
      updateProgress();
      updateNavigation();
      document.getElementById('public-survey-main')?.focus();
    }
  }

  function previous() {
    if (state.currentIndex > 0) {
      state.currentIndex -= 1;
      persist();
      renderCurrent();
      updateProgress();
      updateNavigation();
    }
  }

  function updateProgress() {
    const total = state.visibleQuestions.length;
    const current = total ? state.currentIndex + 1 : 0;
    const percentage = total ? Math.round((current / total) * 100) : 0;

    document.querySelector('[data-progress-label]')?.replaceChildren(
      document.createTextNode(`Question ${current} of ${total}`)
    );

    document.querySelector('[data-progress-percentage]')?.replaceChildren(
      document.createTextNode(`${percentage}%`)
    );

    const bar = document.getElementById('survey-progress-bar');
    if (bar) bar.style.width = `${percentage}%`;

    const track = document.querySelector('.survey-progress-track');
    track?.setAttribute('aria-valuenow', String(percentage));
  }

  function updateNavigation() {
    const previousButton = document.getElementById('survey-previous-button');
    const nextButton = document.getElementById('survey-next-button');
    const submitButton = document.getElementById('survey-submit-button');
    const final = state.currentIndex >= state.visibleQuestions.length - 1;

    if (previousButton) previousButton.disabled = state.currentIndex === 0;
    if (nextButton) nextButton.hidden = final;
    if (submitButton) submitButton.hidden = !final;
  }

  async function submit() {
    if (state.submitting || !validateCurrent()) return;

    state.submitting = true;
    const button = document.getElementById('survey-submit-button');
    if (button) button.disabled = true;

    try {
      const answers = Object.entries(state.answers).map(([question_id, value]) => ({
        question_id,
        value
      }));

      const payload = await window.AbhiprayaApi.post(
        '/api/public/v1/responses',
        {
          token: state.token,
          device_id: window.AbhiprayaDevice?.getId(),
          language: state.language,
          latitude: state.location?.latitude || null,
          longitude: state.location?.longitude || null,
          answers
        }
      );

      window.AbhiprayaSurveyStorage?.clear();

      const submissionId = payload.data?.submission_id;
      location.href = `/ui/pages/public/thank-you.html?submission_id=${encodeURIComponent(submissionId || '')}`;
    } catch (error) {
      if (error.status === 409) {
        location.href = '/ui/pages/public/duplicate.html';
        return;
      }

      window.AbhiprayaNotifications?.alert(
        '#public-survey-alert',
        error.message || 'Unable to submit feedback.',
        'danger'
      );
    } finally {
      state.submitting = false;
      if (button) button.disabled = false;
    }
  }

  async function requestLocation() {
    try {
      state.location = await window.AbhiprayaGeolocation.get();
      return state.location;
    } catch (error) {
      return null;
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('survey-next-button')?.addEventListener('click', next);
    document.getElementById('survey-previous-button')?.addEventListener('click', previous);
    document.getElementById('survey-submit-button')?.addEventListener('click', submit);
  });

  window.AbhiprayaPublicSurvey = {
    state,
    resolve,
    loadSurvey,
    next,
    previous,
    submit,
    requestLocation
  };
})(window, document);
