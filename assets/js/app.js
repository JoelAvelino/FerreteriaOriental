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
    form.addEventListener('submit', () => {
        const button = form.querySelector('[data-submit-button]');

        if (!button) {
            return;
        }

        button.disabled = true;
        button.textContent = 'Guardando...';
        form.setAttribute('aria-busy', 'true');
    });
});
