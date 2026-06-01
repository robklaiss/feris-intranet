document.addEventListener('click', function (event) {
    const toggle = event.target.closest('[data-nav-toggle]');
    if (toggle) {
        const nav = document.querySelector('[data-nav]');
        if (nav) {
            nav.classList.toggle('is-open');
        }
    }

    const addButton = event.target.closest('[data-add-item]');
    if (addButton) {
        const target = document.querySelector(addButton.getAttribute('data-add-item'));
        const firstRow = target ? target.querySelector('[data-item-row]') : null;
        if (target && firstRow) {
            const clone = firstRow.cloneNode(true);
            clone.querySelectorAll('input').forEach(function (input) {
                if (input.hasAttribute('data-line-total')) {
                    input.value = '0,00';
                    return;
                }

                if (input.type === 'checkbox' || input.type === 'radio') {
                    input.checked = false;
                    return;
                }

                if (input.type === 'hidden') {
                    return;
                }

                input.value = '';
            });
            target.appendChild(clone);
            const form = target.closest('form');
            if (form) {
                updateFormTotals(form);
            }
        }
    }

    const remove = event.target.closest('[data-remove-row]');
    if (remove) {
        const row = remove.closest('[data-item-row]');
        const tbody = row ? row.parentElement : null;
        if (row && tbody && tbody.querySelectorAll('[data-item-row]').length > 1) {
            row.remove();
            const form = tbody.closest('form');
            if (form) {
                updateFormTotals(form);
            }
        }
    }
});

document.addEventListener('submit', function (event) {
    const form = event.target.closest('[data-confirm]');
    if (form && !window.confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
    }
});

document.addEventListener('input', function (event) {
    if (event.target.matches('[data-quantity], [data-price]')) {
        const form = event.target.closest('form');
        if (form) {
            updateFormTotals(form);
        }
    }
});

document.querySelectorAll('form[data-calc-totals]').forEach(function (form) {
    updateFormTotals(form);
});

function updateFormTotals(form) {
    let total = 0;

    form.querySelectorAll('[data-item-row], tbody tr').forEach(function (row) {
        const quantityInput = row.querySelector('[data-quantity]');
        const priceInput = row.querySelector('[data-price]');
        const totalTargets = row.querySelectorAll('[data-line-total]');

        if (!quantityInput || !priceInput || totalTargets.length === 0) {
            return;
        }

        const quantity = parseFloat(quantityInput.value || '0') || 0;
        const price = parseFloat(priceInput.value || '0') || 0;
        const lineTotal = quantity * price;
        total += lineTotal;

        totalTargets.forEach(function (target) {
            if (target.tagName === 'INPUT') {
                target.value = formatMoney(lineTotal);
            } else {
                target.textContent = formatMoney(lineTotal);
            }
        });
    });

    form.querySelectorAll('[data-form-total]').forEach(function (target) {
        target.textContent = formatMoney(total);
    });
}

function formatMoney(value) {
    return new Intl.NumberFormat('es-PY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value || 0);
}
