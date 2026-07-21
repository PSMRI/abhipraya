(function (window) {
  'use strict';

  const allowedTypes = new Set([
    'yes_no',
    'yes_no_dont_know',
    'single_choice',
    'multiple_choice',
    'dropdown',
    'rating',
    'emoji_rating',
    'text',
    'textarea',
    'number',
    'date'
  ]);

  function validateDefinition(definition) {
    const errors = {};

    if (!definition?.survey_code) errors.survey_code = ['survey_code is required.'];
    if (!definition?.version) errors.version = ['version is required.'];
    if (!Array.isArray(definition?.questions)) errors.questions = ['questions must be an array.'];

    const ids = new Set();

    (definition?.questions || []).forEach((question, index) => {
      const prefix = `questions.${index}`;

      if (!question.question_id) {
        errors[`${prefix}.question_id`] = ['question_id is required.'];
      } else if (ids.has(question.question_id)) {
        errors[`${prefix}.question_id`] = ['question_id must be unique.'];
      } else {
        ids.add(question.question_id);
      }

      if (!allowedTypes.has(question.type)) {
        errors[`${prefix}.type`] = ['Unsupported question type.'];
      }

      if (!question.text) {
        errors[`${prefix}.text`] = ['Question text is required.'];
      }
    });

    return {
      valid: Object.keys(errors).length === 0,
      errors
    };
  }

  function isVisible(question, answers) {
    const condition = question?.visibility || question?.conditional;
    if (!condition?.depends_on) return true;

    const actual = answers[condition.depends_on];

    switch (condition.operator) {
      case 'equals': return actual === condition.value;
      case 'not_equals': return actual !== condition.value;
      case 'contains': return Array.isArray(actual) && actual.includes(condition.value);
      case 'in': return Array.isArray(condition.value) && condition.value.includes(actual);
      default: return true;
    }
  }

  function validateAnswer(question, value) {
    const errors = [];

    if (question.required) {
      const empty =
        value === undefined ||
        value === null ||
        value === '' ||
        (Array.isArray(value) && value.length === 0);

      if (empty) errors.push('This question is required.');
    }

    return errors;
  }

  window.AbhiprayaSurveyValidator = {
    allowedTypes,
    validateDefinition,
    validateAnswer,
    isVisible
  };
})(window);
