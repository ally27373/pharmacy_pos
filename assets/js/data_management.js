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

    // === CONFIRMATION ===
    const proceed = await NxSwal.confirm(
        `Export ${label} dataset?`,
        "This will download the full dataset as a CSV file.",
        { confirmText: "Download CSV", icon: "question" }
    );

    if (!proceed) return;

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

        if (typeof NxToast !== 'undefined') {
            NxToast.show(`📥 ${label} dataset exported successfully.`, "success");
        } else {
            showMessage('success', `${label} dataset exported successfully.`);
        }
    } catch (error) {
        console.error(`${label} export error:`, error);
        if (typeof NxToast !== 'undefined') {
            NxToast.show(`❌ ${error.message || label + ' export failed.'}`, "danger");
        } else {
            showMessage('danger', error.message || `${label} export failed.`);
        }
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

// ---------- Historical Sales Import ----------
const importFile = document.getElementById('import-file');
const importPreviewBtn = document.getElementById('import-preview-btn');
const importConfirmBtn = document.getElementById('import-confirm-btn');
const importMessage = document.getElementById('import-message');
const importPreview = document.getElementById('import-preview');

let lastPreview = null;

function showImportMessage(type, text) {
    if (!importMessage) return;
    const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
    importMessage.innerHTML = `<div class="alert alert-${type} mb-0"><i class="bi ${icon} me-2"></i>${text}</div>`;
}

async function callImport(confirm) {
    if (!importFile || !importFile.files || !importFile.files[0]) {
        showImportMessage('danger', 'Please select a CSV file first.');
        return null;
    }
    const formData = new FormData();
    formData.append('file', importFile.files[0]);
    formData.append('csrf_token', window.CSRF_TOKEN || '');
    if (confirm) formData.append('confirm', '1');

    if (importPreviewBtn) importPreviewBtn.disabled = true;
    if (importConfirmBtn) importConfirmBtn.disabled = true;

    try {
        const response = await fetch('ajax/import_sales.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        });
        const result = await response.json();
        return result;
    } catch (err) {
        showImportMessage('danger', 'Import request failed: ' + (err.message || err));
        return null;
    } finally {
        if (importPreviewBtn) importPreviewBtn.disabled = false;
    }
}

function renderPreview(preview) {
    if (!importPreview) return;
    if (!preview) { importPreview.innerHTML = ''; return; }

    const unmatched = (preview.unmatched_product_names || []).map(n => `<li>${n}</li>`).join('');
    const dateRows = (preview.date_summary || []).map(d =>
        `<tr><td>${d.date}</td><td>${d.items}</td><td>${Number(d.total).toFixed(2)}</td></tr>`
    ).join('');

    importPreview.innerHTML = `
        <div class="card">
            <div class="card-body">
                <h6>Preview</h6>
                <ul class="mb-2">
                    <li>Total rows: ${preview.total_rows}</li>
                    <li>Valid rows: ${preview.valid_count}</li>
                    <li>Invalid rows: ${preview.invalid_count}</li>
                    <li>Duplicate rows: ${preview.duplicate_count}</li>
                    <li>Matched products: ${preview.matched_products}</li>
                    <li>Unmatched products: ${preview.unmatched_products}</li>
                </ul>
                ${preview.unmatched_products > 0 ? `<div class="alert alert-warning py-1"><strong>Unmatched:</strong><ul class="mb-0">${unmatched}</ul></div>` : ''}
                ${preview.invalid_count > 0 ? `<div class="alert alert-danger py-1">Some rows are invalid and will be skipped.</div>` : ''}
                <table class="table table-sm">
                    <thead><tr><th>Date</th><th>Items</th><th>Total</th></tr></thead>
                    <tbody>${dateRows}</tbody>
                </table>
            </div>
        </div>
    `;
}

if (importPreviewBtn) {
    importPreviewBtn.addEventListener('click', async () => {
        importPreview.innerHTML = '';
        if (importConfirmBtn) importConfirmBtn.disabled = true;
        const result = await callImport(false);
        if (!result) return;
        if (result.status === 'preview') {
            lastPreview = result.preview;
            renderPreview(result.preview);
            if (result.preview.valid_count > 0) {
                if (importConfirmBtn) importConfirmBtn.disabled = false;
            }
            showImportMessage('success', result.message);
        } else {
            showImportMessage('danger', result.message || 'Preview failed.');
        }
    });
}

if (importConfirmBtn) {
    importConfirmBtn.addEventListener('click', async () => {
        if (!lastPreview || lastPreview.valid_count === 0) {
            showImportMessage('danger', 'Nothing to import. Run preview first.');
            return;
        }
        const proceed = await (typeof NxSwal !== 'undefined'
            ? NxSwal.confirm('Import sales data?', 'This will insert historical sales records. This cannot be undone easily.', { confirmText: 'Import', icon: 'warning' })
            : Promise.resolve(confirm('Import sales data?')));
        if (!proceed) return;

        const result = await callImport(true);
        if (!result) return;
        if (result.status === 'success') {
            showImportMessage('success', result.message);
            importPreview.innerHTML = '';
            if (importFile) importFile.value = '';
            lastPreview = null;
            importConfirmBtn.disabled = true;
        } else {
            showImportMessage('danger', result.message || 'Import failed.');
        }
    });
}
