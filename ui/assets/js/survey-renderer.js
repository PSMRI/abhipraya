(function (window, document) {
  'use strict';

  function localized(value, language = 'en') {
    if (value === null || value === undefined) return '';
    if (typeof value === 'string') return value;
    return value[language] ?? value.en ?? Object.values(value)[0] ?? '';
  }

  function inputName(question) {
    return `answer_${question.question_id}`;
  }

  function renderOptions(question, language, answers) {
    const host = document.createElement('div');
    host.className = 'survey-option-grid';

    (question.options || []).forEach((option) => {
      const label = document.createElement('label');
      label.className = 'survey-option';

      const input = document.createElement('input');
      input.type = question.type === 'multiple_choice' ? 'checkbox' : 'radio';
      input.name = inputName(question);
      input.value = option.value;

      const current = answers[question.question_id];
      input.checked = Array.isArray(current)
        ? current.includes(option.value)
        : current === option.value;

      const text = document.createElement('span');
      text.textContent = localized(option.label, language);

      label.append(input, text);
      host.appendChild(label);
    });

    return host;
  }

  function renderQuestion(question, language = 'en', answers = {}) {
    const card = document.createElement('article');
    card.className = 'survey-question-card';
    card.dataset.questionId = question.question_id;

    const heading = document.createElement('h2');
    heading.className = 'survey-question-text';
    heading.textContent = localized(question.text, language);

    card.appendChild(heading);

    if (question.help_text) {
      const help = document.createElement('p');
      help.className = 'survey-question-help';
      help.textContent = localized(question.help_text, language);
      card.appendChild(help);
    }

    let control;

    if (['yes_no', 'yes_no_dont_know', 'single_choice', 'multiple_choice', 'emoji_rating'].includes(question.type)) {
      control = renderOptions(question, language, answers);
    } else if (question.type === 'dropdown') {
      control = document.createElement('select');
      control.className = 'form-select';
      control.name = inputName(question);

      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = localized(question.placeholder, language) || 'Select';
      control.appendChild(placeholder);

      (question.options || []).forEach((option) => {
        const item = document.createElement('option');
        item.value = option.value;
        item.textContent = localized(option.label, language);
        item.selected = answers[question.question_id] === option.value;
        control.appendChild(item);
      });
    } else if (question.type === 'textarea') {
      control = document.createElement('textarea');
      control.className = 'form-control';
      control.name = inputName(question);
      control.value = answers[question.question_id] || '';
    } else {
      control = document.createElement('input');
      control.className = 'form-control';
      control.name = inputName(question);
      control.type = {
        number: 'number',
        date: 'date',
        rating: 'number'
      }[question.type] || 'text';

      control.value = answers[question.question_id] ?? '';

      if (question.validation?.min !== undefined) control.min = question.validation.min;
      if (question.validation?.max !== undefined) control.max = question.validation.max;
    }

    control.dataset.questionId = question.question_id;
    if (question.required) control.required = true;

    card.appendChild(control);
    return card;
  }

  function readAnswer(container, question) {
    const name = inputName(question);

    if (question.type === 'multiple_choice') {
      return [...container.querySelectorAll(`[name="${name}"]:checked`)].map((input) => input.value);
    }

    return container.querySelector(`[name="${name}"]:checked, [name="${name}"]`)?.value ?? null;
  }

  window.AbhiprayaSurveyRenderer = {
    localized,
    renderQuestion,
    readAnswer
  };
})(window, document);
