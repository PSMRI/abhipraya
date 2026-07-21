(function (window) {
  'use strict';

  const validators = {
    required(value) {
      return value !== undefined && value !== null && String(value).trim() !== '';
    },

    email(value) {
      return value === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value));
    },

    mobile(value) {
      return value === '' || /^[0-9+\-\s]{8,15}$/.test(String(value));
    },

    number(value) {
      return value === '' || !Number.isNaN(Number(value));
    },

    integer(value) {
      return value === '' || Number.isInteger(Number(value));
    },

    minLength(value, length) {
      return String(value || '').length >= Number(length);
    },

    maxLength(value, length) {
      return String(value || '').length <= Number(length);
    },

    pattern(value, pattern) {
      return value === '' || new RegExp(pattern).test(String(value));
    }
  };

  function validateField(field) {
    const errors = [];
    const value = field.type === 'checkbox' ? field.checked : field.value;

    if (field.required && !validators.required(value)) {
      errors.push('This field is required.');
    }

    if (field.type === 'email' && !validators.email(value)) {
      errors.push('Enter a valid email address.');
    }

    if (field.minLength > 0 && !validators.minLength(value, field.minLength)) {
      errors.push(`Use at least ${field.minLength} characters.`);
    }

    if (field.maxLength > 0 && !validators.maxLength(value, field.maxLength)) {
      errors.push(`Use no more than ${field.maxLength} characters.`);
    }

    if (field.pattern && !validators.pattern(value, field.pattern)) {
      errors.push('Enter a value in the required format.');
    }

    setFieldError(field, errors[0] || '');
    return errors;
  }

  function setFieldError(field, message) {
    field.setAttribute('aria-invalid', String(Boolean(message)));
    let error = field.parentElement?.querySelector('.form-error');

    if (message && !error) {
      error = document.createElement('p');
      error.className = 'form-error';
      field.insertAdjacentElement('afterend', error);
    }

    if (error) {
      error.textContent = message;
      error.hidden = !message;
    }
  }

  function validateForm(form) {
    const result = {};
    form.querySelectorAll('input, select, textarea').forEach((field) => {
      const errors = validateField(field);
      if (errors.length) result[field.name || field.id] = errors;
    });

    const firstInvalid = form.querySelector('[aria-invalid="true"]');
    firstInvalid?.focus();

    return {
      valid: Object.keys(result).length === 0,
      errors: result
    };
  }

  window.AbhiprayaValidation = {
    validators,
    validateField,
    validateForm,
    setFieldError
  };
})(window);
