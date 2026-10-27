'use strict';

const salesForm = document.querySelector('form[action="?modulo=ventas"]');

if (salesForm) {
    const lines = salesForm.querySelector('[data-sale-lines]');
    const template = salesForm.querySelector('[data-sale-line-template]');
    const addLineButton = salesForm.querySelector('[data-add-sale-line]');
    const totalOutput = salesForm.querySelector('[data-sale-total]');

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

    const update = () => {
        let total = 0n;
        const productSelections = [...lines.querySelectorAll('[data-sale-product]')];

        productSelections.forEach((select) => {
            const duplicate = select.value !== '' && productSelections.some(
                (other) => other !== select && other.value === select.value
            );
            const error = select.closest('[data-sale-line]').querySelector('[data-duplicate-error]');
            select.setCustomValidity(duplicate ? 'No repitas un producto en varias líneas.' : '');
            error.hidden = !duplicate;

            const row = select.closest('[data-sale-line]');
            const quantityField = row.querySelector('[data-sale-quantity]');
            const quantity = Number.parseInt(quantityField.value, 10);
            const price = selectedPrice(select);
            let subtotal = 0n;

            if (price !== null && price > 0n && Number.isSafeInteger(quantity) && quantity > 0) {
                subtotal = price * BigInt(quantity);
                total += subtotal;
            }

            row.querySelector('[data-line-subtotal]').textContent = formatCents(subtotal);
        });

        totalOutput.textContent = formatCents(total);
        lines.querySelectorAll('[data-remove-sale-line]').forEach((button) => {
            button.disabled = lines.querySelectorAll('[data-sale-line]').length === 1;
        });
    };

    lines.addEventListener('input', update);
    lines.addEventListener('change', update);
    lines.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-sale-line]');
        if (removeButton) {
            removeButton.closest('[data-sale-line]').remove();
            update();
        }
    });

    addLineButton.addEventListener('click', () => {
        lines.append(template.content.cloneNode(true));
        update();
    });

    salesForm.addEventListener('submit', update);
    update();
}
