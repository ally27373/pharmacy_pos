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
const importUndoBtn = document.getElementById('import-undo-btn');
const importMessage = document.getElementById('import-message');
const importPreview = document.getElementById('import-preview');
const importHistoryTable = document.getElementById('import-history-table');
const importHistoryMessage = document.getElementById('import-history-message');

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
            ? NxSwal.confirm('Import ' + lastPreview.valid_count + ' sales records?', 'This will insert historical sales records into the database. Recommendation: back up the database first. Undo is available afterwards.', { confirmText: 'Import', icon: 'warning' })
            : Promise.resolve(confirm('Import ' + lastPreview.valid_count + ' sales records?')));
        if (!proceed) return;

        const result = await callImport(true);
        if (!result) return;
        if (result.status === 'success') {
            // Build a richer success message that includes the forecast refresh
            // outcome so the user sees the real-time update status without
            // needing to run any VPS commands.
            let msg = result.message;
            const refresh = result.refresh || null;
            if (refresh && refresh.attempted) {
                if (refresh.success) {
                    msg += ' Forecast refresh: ' + (refresh.ready ? 'READY (updated)' : 'completed (' + refresh.status + ')')
                         + (refresh.forecast_first_date ? ' — first forecast date ' + refresh.forecast_first_date + '.' : '.');
                } else {
                    msg += ' Forecast refresh FAILED: ' + (refresh.error || 'unknown error') + '. Run manual refresh on the server.';
                }
            }
            showImportMessage(refresh && refresh.success ? 'success' : 'warning', msg);
            importPreview.innerHTML = '';
            if (importFile) importFile.value = '';
            lastPreview = null;
            importConfirmBtn.disabled = true;
            if (importUndoBtn) importUndoBtn.disabled = false;
            loadImportHistory();

            // Real-time chart update: the refresh above already rewrote the
            // forecast files on the server. Redirect to the analytics dashboard
            // so the charts render with the fresh data (no manual page reload
            // or VPS command required).
            if (refresh && refresh.success) {
                setTimeout(function () {
                    window.location.href = '../analytics/index.php?forecast_refreshed=1';
                }, 1500);
            }
        } else {
            showImportMessage('danger', result.message || 'Import failed.');
        }
    });
}

if (importUndoBtn) {
    importUndoBtn.addEventListener('click', async () => {
        const proceed = await (typeof NxSwal !== 'undefined'
            ? NxSwal.confirm('Undo ALL imported sales?', 'This removes every sale marked as imported historical data (and their line items). Live POS sales are NOT affected. This cannot be undone.', { confirmText: 'Undo', icon: 'warning' })
            : Promise.resolve(confirm('Undo ALL imported sales?')));
        if (!proceed) return;

        importUndoBtn.disabled = true;
        try {
            const formData = new FormData();
            formData.append('csrf_token', window.CSRF_TOKEN || '');
            const response = await fetch('ajax/undo_import.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            });
            const result = await response.json();
            if (result.status === 'success') {
                showImportMessage('success', result.message);
                if (importPreview) importPreview.innerHTML = '';
                lastPreview = null;
                importConfirmBtn.disabled = true;
                loadImportHistory();
            } else {
                showImportMessage('danger', result.message || 'Undo failed.');
                importUndoBtn.disabled = false;
            }
        } catch (err) {
            showImportMessage('danger', 'Undo request failed: ' + (err.message || err));
            importUndoBtn.disabled = false;
        }
    });
}

async function loadImportHistory() {
    if (!importHistoryTable) return;
    try {
        const response = await fetch('ajax/import_history.php', { credentials: 'same-origin' });
        const result = await response.json();
        if (result.status !== 'success') {
            if (importHistoryMessage) showHistoryMessage('danger', result.message || 'Failed to load history.');
            return;
        }
        const tbody = importHistoryTable.querySelector('tbody');
        if (!tbody) return;
        if (!result.entries || result.entries.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center">No import actions yet.</td></tr>';
            if (importUndoBtn) importUndoBtn.disabled = true;
            return;
        }
        let hasImport = false;
        tbody.innerHTML = result.entries.map(e => {
            if (e.action_type === 'IMPORT') hasImport = true;
            const badge = e.action_type === 'IMPORT'
                ? '<span class="badge bg-success">Import</span>'
                : '<span class="badge bg-danger">Undo</span>';
            const when = new Date(e.created_at).toLocaleString();
            return '<tr>' +
                '<td>' + when + '</td>' +
                '<td>' + badge + '</td>' +
                '<td>' + (e.description || '') + '</td>' +
                '<td>' + (e.user_id || '') + '</td>' +
                '</tr>';
        }).join('');
        if (importUndoBtn) importUndoBtn.disabled = !hasImport;
    } catch (err) {
        if (importHistoryMessage) showHistoryMessage('danger', 'Failed to load history: ' + (err.message || err));
    }
}

function showHistoryMessage(type, text) {
    if (!importHistoryMessage) return;
    const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
    importHistoryMessage.innerHTML = '<div class="alert alert-' + type + ' py-1 mb-0 small"><i class="bi ' + icon + ' me-2"></i>' + text + '</div>';
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadImportHistory, { once: true });
} else {
    loadImportHistory();
}
