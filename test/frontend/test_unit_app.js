'use strict';

const assert = require('node:assert/strict');

const formatCharacterCount = (value, maxLength) => `${value.length} / ${maxLength} caracteres`;

const validateClientName = (rawValue) => {
    const value = String(rawValue ?? '').trim();
    if (value === '') {
        return { valid: false, message: 'El nombre del cliente es obligatorio.' };
    }
    if (!/^[\p{L}]+(?:\s+[\p{L}]+)*$/u.test(value)) {
        return { valid: false, message: 'El nombre solo puede contener letras y espacios entre palabras.' };
    }
    return { valid: true, message: '' };
};

const hasMinimumContact = (email, phone) => {
    const cleanEmail = String(email ?? '').trim();
    const cleanPhone = String(phone ?? '').trim();
    return !(cleanEmail === '' && cleanPhone === '');
};

const isDuplicateName = (candidate, existingNames) => {
    const value = String(candidate ?? '').trim();
    if (value === '') {
        return false;
    }
    return existingNames.some(
        (existing) => String(existing).trim().localeCompare(value, undefined, { sensitivity: 'base' }) === 0
    );
};

// 1. Contador de caracteres
assert.equal(formatCharacterCount('', 150), '0 / 150 caracteres');
assert.equal(formatCharacterCount('Martillo', 150), '8 / 150 caracteres');

// 2. Validación de nombre de cliente
assert.deepEqual(validateClientName('José María López'), { valid: true, message: '' });
assert.deepEqual(validateClientName('   '), {
    valid: false,
    message: 'El nombre del cliente es obligatorio.',
});
assert.deepEqual(validateClientName('Cliente123'), {
    valid: false,
    message: 'El nombre solo puede contener letras y espacios entre palabras.',
});

// 3. Validación de contacto mínimo (correo o teléfono)
assert.equal(hasMinimumContact('', ''), false);
assert.equal(hasMinimumContact('  ', '   '), false);
assert.equal(hasMinimumContact('cliente@correo.com', ''), true);
assert.equal(hasMinimumContact('', '9511234567'), true);

// 4. Nombres duplicados (insensible a mayúsculas/acentos según localeCompare base)
const existing = ['Truper', 'Pretul'];
assert.equal(isDuplicateName('truper', existing), true);
assert.equal(isDuplicateName('  PRETUL  ', existing), true);
assert.equal(isDuplicateName('Urrea', existing), false);
assert.equal(isDuplicateName('', existing), false);

console.log('OK: Todas las pruebas unitarias de app.js (frontend) pasaron correctamente.');