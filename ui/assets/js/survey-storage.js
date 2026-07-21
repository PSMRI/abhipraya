(function (window) {
  'use strict';

  const KEY = 'public_survey_state';

  function load() {
    return window.AbhiprayaStorage?.sessionGet(KEY, {
      token: null,
      language: 'en',
      answers: {},
      current_index: 0,
      submission_id: null
    });
  }

  function save(state) {
    return window.AbhiprayaStorage?.sessionSet(KEY, state);
  }

  function clear() {
    window.AbhiprayaStorage?.sessionRemove(KEY);
  }

  function setAnswer(questionId, value) {
    const state = load();
    state.answers = state.answers || {};
    state.answers[questionId] = value;
    save(state);
    return state;
  }

  window.AbhiprayaSurveyStorage = { load, save, clear, setAnswer };
})(window);
