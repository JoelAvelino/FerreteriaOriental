'use strict';

document.querySelectorAll('[data-character-count]').forEach((field) => {
    const counter = document.getElementById(`${field.id}-counter`);
    const maxLength = field.maxLength;

    if (!counter || maxLength < 0) {
        return;
    }

    const updateCounter = () => {
        counter.textContent = `${field.value.length} / ${maxLength} caracteres`;
    };

    field.addEventListener('input', updateCounter);
    updateCounter();
});

document.querySelectorAll('form[data-client-name-validation]').forEach((form) => {
    const nameField = form.querySelector('#nombre');
    const feedback = document.getElementById('nombre-format-error');

    if (!nameField || !feedback) {
        return;
    }

    const validateName = (normalize = false) => {
        if (normalize) {
            nameField.value = nameField.value.trim();
        }

        const value = nameField.value.trim();
        let message = '';
        if (value === '') {
            message = 'El nombre del cliente es obligatorio.';
        } else if (!/^[\p{L}]+(?:\s+[\p{L}]+)*$/u.test(value)) {
            message = 'El nombre solo puede contener letras y espacios entre palabras.';
        }

        nameField.setCustomValidity(message);
        feedback.textContent = message;
        feedback.hidden = !message;
    };

    nameField.addEventListener('input', () => validateName());
    nameField.addEventListener('blur', () => validateName(true));
    form.addEventListener('submit', () => validateName(true));
    validateName();
});

document.querySelectorAll('form[data-client-contact-validation]').forEach((form) => {
    const emailField = form.querySelector('#correo');
    const phoneField = form.querySelector('#telefono');
    const feedback = document.getElementById('contact-method-error');

    if (!emailField || !phoneField || !feedback) {
        return;
    }

    const validateContact = (normalize = false, showMissing = false) => {
        if (normalize) {
            emailField.value = emailField.value.trim();
            phoneField.value = phoneField.value.trim();
        }

        const missingContact = emailField.value.trim() === '' && phoneField.value.trim() === '';
        const message = missingContact
            ? 'Debes proporcionar un correo electrónico o un número telefónico como mínimo'
            : '';

        const validityMessage = showMissing ? message : '';
        emailField.setCustomValidity(validityMessage);
        phoneField.setCustomValidity(validityMessage);
        feedback.textContent = message;
        feedback.hidden = !missingContact || !showMissing;
    };

    emailField.addEventListener('input', () => validateContact(false, true));
    phoneField.addEventListener('input', () => validateContact(false, true));
    emailField.addEventListener('blur', () => validateContact(true, true));
    phoneField.addEventListener('blur', () => validateContact(true, true));
    form.addEventListener('submit', (event) => {
        validateContact(true, true);
        if (emailField.value === '' && phoneField.value === '') {
            event.preventDefault();
            emailField.reportValidity();
        }
    });
    validateContact();
});

document.querySelectorAll('form[data-unique-name]').forEach((form) => {
    const nameField = form.querySelector('[data-unique-name-field]');
    const feedback = document.getElementById('nombre-duplicate-error');

    if (!nameField || !feedback) {
        return;
    }

    const existingNames = [...document.querySelectorAll('[data-existing-name]')]
        .map((record) => record.dataset.existingName.trim());

    const validateUniqueName = () => {
        const value = nameField.value.trim();
        const duplicate = value !== '' && existingNames.some(
            (existingName) => existingName.localeCompare(value, undefined, { sensitivity: 'base' }) === 0
        );
        const message = duplicate ? 'Ya existe un registro con ese nombre.' : '';

        nameField.setCustomValidity(message);
        feedback.textContent = message;
        feedback.hidden = !duplicate;
    };

    nameField.addEventListener('input', validateUniqueName);
    form.addEventListener('submit', validateUniqueName);
    validateUniqueName();
});

document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented) {
            return;
        }

        const confirmation = form.dataset.confirmDelete;
        if (confirmation && !window.confirm(confirmation)) {
            event.preventDefault();
            return;
        }

        const button = form.querySelector('[data-submit-button]');

        if (!button) {
            return;
        }

        button.disabled = true;
        button.textContent = 'Guardando...';
        form.setAttribute('aria-busy', 'true');
    });
});
