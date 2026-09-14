'use strict';

(() => {
    const API_URL = '/pharmacy_pos/app/dashboard/reports/ajax/get_report.php';
    const EXPORT_URL = '/pharmacy_pos/app/dashboard/reports/ajax/export_report.php';
    const LIMIT = 25;

    const searchInput = document.getElementById('report-search');
    const periodSelect = document.getElementById('report-period');
    const sourceSelect = document.getElementById('report-source');
    const categorySelect = document.getElementById('report-category');
    const typeSelect = document.getElementById('report-type');
    const customRange = document.getElementById('custom-date-range');
    const fromInput = document.getElementById('report-from');
    const toInput = document.getElementById('report-to');
    const applyCustomDate = document.getElementById('apply-custom-date');
    const tableBody = document.getElementById('reports-table-body');
    const summary = document.getElementById('report-summary');
    const paginationInfo = document.getElementById('report-pagination-info');
    const pageLabel = document.getElementById('report-page-label');
    const prevButton = document.getElementById('report-prev');
    const nextButton = document.getElementById('report-next');
    const message = document.getElementById('report-message');

    let currentPage = 1;
    let currentFilters = {};
    let searchTimer = null;
    let categoriesLoaded = false;
    let typesLoaded = false;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        if (!value) return '—';
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return escapeHtml(value);
        return date.toLocaleDateString('en-PH', {
            month: '2-digit',
            day: '2-digit',
            year: 'numeric'
        });
    }

    function money(value) {
        const number = Number(value || 0);
        return '₱' + number.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function showMessage(type, text) {
        if (!message) return;
        message.innerHTML = `<div class="alert alert-${type} mb-0">${escapeHtml(text)}</div>`;
    }

    function clearMessage() {
        if (message) message.innerHTML = '';
    }

    function currentQuery(page = currentPage) {
        const params = new URLSearchParams();
        params.set('page', String(page));
        params.set('limit', String(LIMIT));
        params.set('search', searchInput.value.trim());
        params.set('source', sourceSelect.value);
        params.set('category', categorySelect.value);
        params.set('type', typeSelect.value);
        params.set('period', periodSelect.value);

        if (periodSelect.value === 'custom') {
            params.set('from', fromInput.value);
            params.set('to', toInput.value);
        }

        return params;
    }

    function renderFilterOptions(data) {
        if (!categoriesLoaded && Array.isArray(data.categories)) {
            const current = categorySelect.value;
            categorySelect.innerHTML = '<option value="">Category: All</option>';
            data.categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category;
                option.textContent = 'Category: ' + category;
                categorySelect.appendChild(option);
            });
            categorySelect.value = current;
            categoriesLoaded = true;
        }

        if (!typesLoaded && Array.isArray(data.types)) {
            const current = typeSelect.value;
            typeSelect.innerHTML = '<option value="">Type: All</option>';
            data.types.forEach(type => {
                const option = document.createElement('option');
                option.value = type;
                option.textContent = 'Type: ' + type;
                typeSelect.appendChild(option);
            });
            typeSelect.value = current;
            typesLoaded = true;
        }
    }

    function renderRows(rows) {
        if (!rows.length) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="10" class="empty-state">No records matched the selected filters.</td>
                </tr>
            `;
            return;
        }

        tableBody.innerHTML = rows.map(row => {
            const sourceClass = row.source === 'Sales'
                ? 'report-source-sales'
                : 'report-source-inventory';

            return `
                <tr>
                    <td>${formatDate(row.report_date)}</td>
                    <td><span class="report-source ${sourceClass}">${escapeHtml(row.source)}</span></td>
                    <td>${escapeHtml(row.product_name)}</td>
                    <td>${escapeHtml(row.barcode)}</td>
                    <td>${escapeHtml(row.type_name)}</td>
                    <td>${escapeHtml(row.category_name)}</td>
                    <td>${escapeHtml(row.quantity)}</td>
                    <td>${money(row.unit_price)}</td>
                    <td>${money(row.total_amount)}</td>
                    <td>${escapeHtml(row.payment_method || '—')}</td>
                </tr>
            `;
        }).join('');
    }

    function renderPagination(pagination) {
        const total = Number(pagination.total || 0);
        const page = Number(pagination.page || 1);
        const limit = Number(pagination.limit || LIMIT);
        const totalPages = Number(pagination.total_pages || 0);

        currentPage = page;
        pageLabel.textContent = `Page ${page} of ${Math.max(1, totalPages)}`;

        if (total > 0) {
            const start = ((page - 1) * limit) + 1;
            const end = Math.min(page * limit, total);
            paginationInfo.innerHTML = `Showing <strong>${start}</strong>–<strong>${end}</strong> of <strong>${total}</strong> records`;
        } else {
            paginationInfo.textContent = 'No records found.';
        }

        prevButton.disabled = !pagination.has_previous;
        nextButton.disabled = !pagination.has_next;
    }

    async function loadReport(page = 1) {
        currentPage = page;
        clearMessage();
        tableBody.innerHTML = '<tr><td colspan="10" class="empty-state">Loading report data...</td></tr>';
        summary.textContent = 'Loading report...';

        try {
            const response = await fetch(`${API_URL}?${currentQuery(page).toString()}`, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Unable to load report data.');
            }

            currentFilters = data.filters || {};
            renderFilterOptions(data);
            renderRows(data.rows || []);
            renderPagination(data.pagination || {});

            const total = Number(data.pagination?.total || 0);
            summary.textContent = `${total.toLocaleString('en-PH')} record${total === 1 ? '' : 's'} found.`;
        } catch (error) {
            console.error('Reports load error:', error);
            tableBody.innerHTML = '<tr><td colspan="10" class="empty-state">Unable to load report data.</td></tr>';
            paginationInfo.textContent = 'Unable to load pagination.';
            summary.textContent = 'Report unavailable.';
            showMessage('danger', error.message || 'Unable to load report data.');
        }
    }

    function updateCustomRangeVisibility() {
        customRange.hidden = periodSelect.value !== 'custom';
    }

    function resetToFirstPageAndLoad() {
        if (periodSelect.value === 'custom' && (!fromInput.value || !toInput.value)) {
            return;
        }
        loadReport(1);
    }

    function downloadReport(format) {
        const params = currentQuery(1);
        params.delete('page');
        params.delete('limit');
        params.set('format', format);

        const url = `${EXPORT_URL}?${params.toString()}`;
        const button = document.getElementById('report-download-btn');
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Preparing...';

        fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(async response => {
                const contentType = response.headers.get('content-type') || '';
                if (!response.ok) {
                    if (contentType.includes('application/json')) {
                        const data = await response.json();
                        throw new Error(data.message || 'Export failed.');
                    }
                    throw new Error('Export failed.');
                }
                return response.blob();
            })
            .then(blob => {
                const disposition = null;
                const extension = format === 'xlsx' ? 'xlsx' : 'pdf';
                const filename = `pharmacy_report_${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.${extension}`;
                const objectUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = objectUrl;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(objectUrl);
                showMessage('success', `${format.toUpperCase()} report downloaded successfully.`);
            })
            .catch(error => {
                console.error('Report export error:', error);
                showMessage('danger', error.message || 'Report export failed.');
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = original;
            });
    }

    periodSelect.addEventListener('change', () => {
        updateCustomRangeVisibility();
        if (periodSelect.value !== 'custom') loadReport(1);
    });

    sourceSelect.addEventListener('change', resetToFirstPageAndLoad);
    categorySelect.addEventListener('change', resetToFirstPageAndLoad);
    typeSelect.addEventListener('change', resetToFirstPageAndLoad);

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadReport(1), 350);
    });

    applyCustomDate.addEventListener('click', () => {
        if (!fromInput.value || !toInput.value) {
            showMessage('warning', 'Please select both the start and end dates.');
            return;
        }
        loadReport(1);
    });

    prevButton.addEventListener('click', () => {
        if (!prevButton.disabled) loadReport(currentPage - 1);
    });

    nextButton.addEventListener('click', () => {
        if (!nextButton.disabled) loadReport(currentPage + 1);
    });

    document.querySelectorAll('.report-download-option').forEach(option => {
        option.addEventListener('click', () => downloadReport(option.dataset.format));
    });

    updateCustomRangeVisibility();
    loadReport(1);
})();
