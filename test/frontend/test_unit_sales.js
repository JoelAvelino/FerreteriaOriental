'use strict';

const assert = require('node:assert/strict');

// Funciones puras extraídas/replicadas de assets/js/sales.js para prueba unitaria
const formatCents = (cents) => {
    const whole = (cents / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const fraction = (cents % 100n).toString().padStart(2, '0');
    return `${whole}.${fraction}`;
};

const selectedPrice = (select) => {
    const price = select.selectedOptions[0]?.dataset.price ?? '';
    const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(price);
    if (!match) {
        return null;
    }

    return BigInt(match[1]) * 100n + BigInt((match[2] ?? '').padEnd(2, '0') || '0');
};

// --- Pruebas de formatCents ---
assert.equal(formatCents(0n), '0.00');
assert.equal(formatCents(500n), '5.00');
assert.equal(formatCents(1250n), '12.50');
assert.equal(formatCents(125000n), '1,250.00');
assert.equal(formatCents(150000000n), '1,500,000.00');

// --- Pruebas de selectedPrice ---
assert.equal(
    selectedPrice({ selectedOptions: [{ dataset: { price: '25.5' } }] }),
    2550n
);
assert.equal(
    selectedPrice({ selectedOptions: [{ dataset: { price: '199.99' } }] }),
    19999n
);
assert.equal(
    selectedPrice({ selectedOptions: [{ dataset: { price: '100' } }] }),
    10000n
);
assert.equal(
    selectedPrice({ selectedOptions: [{ dataset: { price: '' } }] }),
    null
);
assert.equal(
    selectedPrice({ selectedOptions: [{ dataset: { price: '-10.00' } }] }),
    null
);
assert.equal(
    selectedPrice({ selectedOptions: [] }),
    null
);

// --- Prueba de cálculo de subtotal y total ---
const calcularTotalLinea = (precioCentavos, cantidad) => {
    if (precioCentavos === null || precioCentavos <= 0n || !Number.isInteger(cantidad) || cantidad <= 0) {
        return 0n;
    }
    return precioCentavos * BigInt(cantidad);
};

assert.equal(calcularTotalLinea(2550n, 3), 7650n);
assert.equal(formatCents(calcularTotalLinea(2550n, 3)), '76.50');
assert.equal(calcularTotalLinea(2550n, 0), 0n);
assert.equal(calcularTotalLinea(2550n, -2), 0n);

console.log('OK: Todas las pruebas unitarias de ventas (frontend) pasaron correctamente.');