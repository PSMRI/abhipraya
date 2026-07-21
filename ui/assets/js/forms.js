(function (window, document) {
  'use strict';

  function serialize(form) {
    const data = {};
    const formData = new FormData(form);

    for (const [key, value] of formData.entries()) {
      if (key in data) {
        data[key] = Array.isArray(data[key])
          ? [...data[key], value]
          : [data[key], value];
      } else {
        data[key] = value;
      }
    }

    return data;
  }

  function fill(form, values = {}) {
    Object.entries(values).forEach(([name, value]) => {
      const fields = form.querySelectorAll(`[name="${CSS.escape(name)}"]`);

      fields.forEach((field) => {
        if (field.type === 'checkbox') {
          field.checked = Array.isArray(value)
            ? value.map(String).includes(String(field.value))
            : Boolean(value);
        } else if (field.type === 'radio') {
          field.checked = String(field.value) === String(value);
        } else {
          field.value = value ?? '';
        }
      });
    });
  }

  function setSubmitting(form, submitting) {
    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
      button.disabled = submitting;
      button.classList.toggle('is-loading', submitting);
    });
  }

  function bind(form, handler) {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();

      const validation = window.AbhiprayaValidation?.validateForm(form);
      if (validation && !validation.valid) return;

      setSubmitting(form, true);

      try {
        await handler(serialize(form), event);
      } finally {
        setSubmitting(form, false);
      }
    });
  }

  window.AbhiprayaForms = { serialize, fill, bind, setSubmitting };
})(window, document);
