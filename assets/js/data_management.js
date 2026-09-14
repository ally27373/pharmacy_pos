'use strict';

console.log('Data Management JS Loaded');

const inventoryButton = document.getElementById('export-inventory-btn');
const salesButton = document.getElementById('export-sales-btn');
const message = document.getElementById('export-message');

function showMessage(type, text) {
    if (!message) return;

    const icon = type === 'success'
        ? 'bi-check-circle-fill'
        : 'bi-exclamation-triangle-fill';

    message.innerHTML = `
        <div class="alert alert-${type} mb-0">
            <i class="bi ${icon} me-2"></i>
            ${text}
        </div>
    `;
}

async function downloadCsv(button, endpoint, label) {
    if (!button) return;

    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = `
        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
        Preparing ${label}...
    `;

    if (message) message.innerHTML = '';

    try {
        const response = await fetch(endpoint, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store'
        });

        const contentType = response.headers.get('content-type') || '';

        if (!response.ok) {
            let serverMessage = `${label} export failed.`;

            if (contentType.includes('application/json')) {
                const result = await response.json();
                serverMessage = result.message || serverMessage;
            }

            throw new Error(serverMessage);
        }

        const blob = await response.blob();
        const disposition = response.headers.get('content-disposition') || '';
        const match = disposition.match(/filename="?([^";]+)"?/i);
        const filename = match ? match[1] : `${label.toLowerCase().replace(/\s+/g, '_')}_export.csv`;

        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);

        showMessage('success', `${label} dataset exported successfully.`);
    } catch (error) {
        console.error(`${label} export error:`, error);
        showMessage('danger', error.message || `${label} export failed.`);
    } finally {
        button.disabled = false;
        button.innerHTML = originalHtml;
    }
}

if (inventoryButton) {
    inventoryButton.addEventListener('click', () => {
        downloadCsv(
            inventoryButton,
            'ajax/export_inventory.php',
            'Inventory'
        );
    });
}

if (salesButton) {
    salesButton.addEventListener('click', () => {
        downloadCsv(
            salesButton,
            'ajax/export_sales.php',
            'Sales'
        );
    });
}
